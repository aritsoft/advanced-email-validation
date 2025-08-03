<?php

defined('ABSPATH') || exit;

class AEV_Bulk_Import_Email_Validation {

    public function __construct() {
        add_filter('pms_member_account_tabs', array($this, 'custom_add_account_tab'), 20, 2);
        add_filter('pms_account_shortcode_content', array($this, 'bulkemails_account_tab_content'), 10, 2);
        add_action('admin_post_export_passed_emails_csv', array($this, 'export_passed_emails_csv'));
        add_action('admin_post_export_failed_emails_csv', array($this, 'export_failed_emails_csv'));
    }

    public function custom_add_account_tab($tabs, $user_id) {
        $tabs['bulkemails'] = __('Bulk Emails', 'paid-member-subscriptions');
        return $tabs;
    }

    public function bulkemails_account_tab_content($content, $active_tab) {
        if ($active_tab !== 'bulkemails') return $content;
        $tabs = $content;
        $bulkemails = null;
        $member = pms_get_member( pms_get_current_user_id() );
        if( $member->is_member() ) {
            ob_start(); 
            include AEV_PLUGIN_PATH . 'includes/form-bulk-email-check.php';
            $bulkemails = ob_get_clean();  
        } else {
            $bulkemails = '<p>You do not have any subscriptions attached to your account.</p>';
            $bulkemails .= 'To purchase a subscription, you can <a href="/pricing">click here</a>.</p>';
        }
        return $tabs.$bulkemails;
    }

    public function bulk_email_validations() 
    {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'aev_email_nonce')) {
            wp_send_json_error(['message' => 'Nonce verification failed']);
        }

        if (empty($_FILES['bulk_email_file']['tmp_name'])) {
            wp_send_json_error(['message' => 'No file uploaded']);
        }   

        $remaining_credits = get_remaining_email_credits();
        $user_id = aev_get_user_identifier();
        $file = $_FILES['bulk_email_file'];
        $max_size = 10 * 1024 * 1024; // 10MB

        if ($file['size'] > $max_size || $file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Invalid file. Must be less than 10MB and error-free.');
        }

        // Read file content (e.g., for email processing)
        $emails = file($file['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if($remaining_credits >= count($emails)) {
            foreach($emails as $email) {
                $this->validate_email_address($email);
            }
            wp_send_json_success(['message' => 'Bulk email processing complete.']);
        } else {
            wp_send_json_error(['message' => 'You do not have enough credits for this bulk import.']);
        }
    }

    public function validate_email_address($email) {
        $user_id = aev_get_user_identifier();
        $ace_limit = get_aev_email_check_limit();
        $disposable_list = $this->load_disposable_domains();
        $major_providers = $this->load_major_email_providers();

        if (aev_is_limit_reached($user_id, $ace_limit)) {
            wp_send_json_error([
                'status' => 'limit_reached', 
                'email' => $email,
                'message' => 'You have no more credits. <a href="'.site_url().'/pricing" target="_blank">Upgrade your plan</a>'
            ]);
        }

        $email_syntax = true;
        $valid_domain = true;
        $is_disposable = false;

        // Step 1: Syntax
        if (!$this->is_valid_syntax($email)) {
            $this->aev_store_bulk_email_validation(
                $email, 
                false,  //is valid syntax
                false,  //is valid domain
                false,  // disposable domain
                '',    //provider
                false, //smtp check
                'Invalid', //status
                ['note' => 'The address provided is not in a valid format.']
            );
            return;
        }

        // Step 2: MX
        $domain = substr(strrchr($email, "@"), 1);
        if (!$this->has_mx_record($domain)) {
            $valid_domain = false;
            $this->aev_store_bulk_email_validation(
                $email, 
                true,      //is valid syntax
                false,    //is valid domain
                false,   // disposable domain
                $domain, //provider
                false, //smtp check
                'Invalid', //status
                ['note' => 'The address provided does not have a valid dns entry.', 'event'=>'invalid_domain']
            );
            return;
        }

        // Step 3: Disposable
        if ($this->is_disposable_domain($domain, $disposable_list)) {
            $this->aev_store_bulk_email_validation(
                $email, 
                true,      //is valid syntax
                true,    //is valid domain
                true,   // disposable domain
                $domain, //provider
                false, //smtp check
                'Failed', //status
                ['note' => 'The address provided is a disposable domain', 'event'=>'domain_does_not_exist']
            );
            return;
        }

        // Step 4: Major Providers
        if (in_array($domain, $major_providers)) {
            $provider = $domain;
            $this->aev_store_bulk_email_validation(
                $email, 
                true,      //is valid syntax
                true,    //is valid domain
                true,   // disposable domain
                $domain, //provider
                true, //smtp check
                'Passed', //status
                ['note' => 'The address provided passed all tests.', 'event'=>'mailbox_exists']
            );
            return;
        }

        // Step 5: Neutrino API
        if ($email_syntax && $valid_domain) {
            $response_data = $this->check_with_neutrino($email);
            if (!empty($response_data['valid']) && empty($response_data['domain-error'])) {
                $smtp_check = !empty($response_data['valid']);
                $provider = isset($response_data['domain']) ? $response_data['domain'] : $domain;
                $this->aev_store_bulk_email_validation(
                    $email, 
                    true,      //is valid syntax
                    true,    //is valid domain
                    true,   // disposable domain
                    $provider, //provider
                    true, //smtp check
                    'Passed', //status
                    ['note' => 'The address provided passed all tests.', 'event'=>'mailbox_exists']
                );
                return;
            } else {
                $provider = isset($response_data['domain']) ? $response_data['domain'] : $domain;
                $this->aev_store_bulk_email_validation(
                    $email, 
                    true,      //is valid syntax
                    true,    //is valid domain
                    true,   // disposable domain
                    $provider, //provider
                    false, //smtp check
                    'Invalid', //status
                    ['note' => 'The address provided does not have a valid dns entry.', 'event'=>'domain_does_not_exist']
                );
                return;
            }
        }
    }

    public function check_with_neutrino($email) {
        $response = wp_remote_post('https://neutrinoapi.net/email-validate', [
            'timeout' => 300,
            'body' => [
                'user-id' => AEV_NEUTRINO_API_ID,
                'api-key' => AEV_NEUTRINO_API_KEY,
                'email'   => $email
            ]
        ]);

        if (is_wp_error($response)) {
            return ['valid' => false, 'error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body) || !empty($body['error']) || $body['smtp-status'] === 'unknown') {
            $error_reason = $body['error'] ?? 'Invalid API key or no credits left.';
            set_transient('aev_api_error_notice', $error_reason, MINUTE_IN_SECONDS * 10);
            return ['valid' => false, 'error' => $error_reason];
        }

        return $body;
    }

    public function load_disposable_domains() {
        $disposable_domains = get_option('aev_disposable_domains', '');
        if (empty($disposable_domains)) {
            return [];
        }
        $lines = preg_split('/[\r\n,]+/', $disposable_domains);
        return array_filter(array_map('strtolower', array_map('trim', $lines)));
    }

    public function load_major_email_providers() {
        $major_email_providers = get_option('aev_email_providers', '');
        if (empty($major_email_providers)) {
            return [];
        }
        $lines = preg_split('/[\r\n,]+/', $major_email_providers);
        return array_filter(array_map('strtolower', array_map('trim', $lines)));
    }

    public function is_valid_syntax($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function has_mx_record($domain) {
        return checkdnsrr($domain, 'MX');
    }

    public function is_disposable_domain($domain, $disposable_list) {
        return in_array(strtolower($domain), $disposable_list);
    }

     public function aev_store_bulk_email_validation($email, $syntax_valid, $mx_found, $disposable_domain, $provider, $smtp_check, $status, $response) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'email_checker_history';
        $user_id_or_ip = is_user_logged_in() ? get_current_user_id() : $_SERVER['REMOTE_ADDR'];

        $wpdb->insert(
            $table_name,
            [
                'user_id_or_ip'     => $user_id_or_ip,
                'email'             => sanitize_email($email),
                'syntax_valid'      => $syntax_valid ? 1 : 0,
                'mx_found'          => $mx_found ? 1 : 0,
                'disposable_domain' => $disposable_domain ? 1 : 0,
                'provider'          => sanitize_text_field($provider),
                'smtp_check'        => $smtp_check ? 1 : 0,
                'status'            => sanitize_text_field($status),
                'response'          => json_encode($response),
                'is_bulk'           => 1,
                'checked_at'        => current_time('mysql')
            ],
            ['%s','%s','%d','%d','%d','%s','%d','%s','%s','%s']
        );
    }

    public function export_passed_emails_csv() {
        if ( ! is_user_logged_in() ) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $user_id = get_current_user_id();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT email, checked_at FROM " . AEV_HISTORY_TABLE . "
                WHERE user_id_or_ip = %d AND status = %s AND checked_at >= DATE_SUB(NOW(), INTERVAL 10 DAY)
                ORDER BY checked_at DESC",
                $user_id,
                'Passed'
            )
        );

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=passed-emails.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Email', 'Checked At']);

        foreach ($results as $row) {
            fputcsv($output, [$row->email, $row->checked_at]);
        }

        fclose($output);
        exit;
    }

    public function export_failed_emails_csv() {
        if ( ! is_user_logged_in() ) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $user_id = get_current_user_id();

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT email, checked_at FROM " . AEV_HISTORY_TABLE . "
                WHERE user_id_or_ip = %d AND (status = %s OR status = %s) AND checked_at >= DATE_SUB(NOW(), INTERVAL 10 DAY)
                ORDER BY checked_at DESC", $user_id, 'Failed', 'Invalid'
            )
        );

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=failed-emails.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Email', 'Checked At']);

        foreach ($results as $row) {
            fputcsv($output, [$row->email, $row->checked_at]);
        }

        fclose($output);
        exit;
    }
}

new AEV_Bulk_Import_Email_Validation();