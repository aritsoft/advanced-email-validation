<?php
defined('ABSPATH') || exit;

class AEV_Bulk_Import_Email_Validation {

    public function __construct() {
        add_filter('pms_member_account_tabs', [$this, 'add_account_tab'], 20, 2);
        add_filter('pms_account_shortcode_content', [$this, 'render_bulk_tab'], 10, 2);
        add_action('admin_post_export_passed_emails_csv', [$this, 'export_csv']);
        add_action('admin_post_export_failed_emails_csv', [$this, 'export_csv']);
        add_action('admin_post_export_all_emails_csv', [$this, 'export_csv']);
    }

    public function add_account_tab($tabs, $user_id) {
        $sorted_tabs = [];
        $tabs['quickemail'] = __('Quick Verify', 'paid-member-subscriptions');
        $tabs['bulkemails'] = __('Bulk Verify', 'paid-member-subscriptions');
        $tabs['subscriptions'] = __('Packages', 'paid-member-subscriptions');
        $custom_order = ['quickemail', 'bulkemails', 'subscriptions', 'payments', 'profile'];
        foreach ($custom_order as $key) {
            if (isset($tabs[$key])) {
                $sorted_tabs[$key] = $tabs[$key];
            }
        }
        $tabs = $sorted_tabs;
        return $tabs;
    }

    public function render_bulk_tab($content, $active_tab) {
        if ($active_tab == 'quickemail') {
            $quickemail = '<p>'.do_shortcode('[email_validation_form]').'</p>';
            $quickemail .= '<p>'.do_shortcode('[aev_email_validation_user_history]').'</p>';
            return $content . $quickemail;
        } else if ($active_tab == 'bulkemails') {
            if (pms_get_member(pms_get_current_user_id())->is_member()) {
                ob_start();
                include AEV_PLUGIN_PATH . 'includes/form-bulk-email-check.php';
                $bulk_content = ob_get_clean();
            } else {
                $bulk_content  = '<p>You do not have any subscriptions attached to your account.</p>';
                $bulk_content .= '<p><a href="/pricing">Click here</a> to purchase a subscription.</p>';
            }
            return $content . $bulk_content;
        } else {
            return $content;
        }
    }

    public function bulk_email_validations() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'aev_email_nonce')) {
            wp_send_json_error(['message' => 'Nonce verification failed']);
        }

        if (empty($_FILES['bulk_email_file']['tmp_name'])) {
            wp_send_json_error(['message' => 'No file uploaded']);
        }

        $file = $_FILES['bulk_email_file'];
        $content = file_get_contents($file['tmp_name']);
        if (strpos($content, ',') !== false) {
            $emails = explode(',', $content);  // Comma-separated format
        } else {
            // Line-separated format
            $emails = file($file['tmp_name'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        }
        $emails = array_map('trim', $emails);
        $credits = get_remaining_email_credits();

        if ($file['size'] > 10 * 1024 * 1024 || $file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Invalid file. Must be less than 10MB and error-free.');
        }

        if ($credits < count($emails)) {
            wp_send_json_error(['message' => 'You do not have enough credits for this bulk import.']);
        }

        foreach ($emails as $email) {
            $this->validate_email_address($email);
        }

        $bulk_passed = bulk_passed_varification(10);
        $bulk_failed = bulk_failed_varification(10);
        $remaining_credits = get_remaining_email_credits();
        wp_send_json_success([
            'message' => 'Bulk email processing complete.',
            'credits' => $remaining_credits,
            'bulk_passed' => $bulk_passed,
            'bulk_failed' => $bulk_failed,
        ]);
    }

    public function validate_email_address($email) {
        $user_id = aev_get_user_identifier();
        $remaining_credits      = get_remaining_email_credits();

        if ($remaining_credits <=0) {
            wp_send_json_error([
                'status' => 'limit_reached',
                'email' => $email,
                'message' => 'No more credits. <a href="' . site_url('/pricing') . '" target="_blank">Upgrade your plan</a>'
            ]);
        }

        $disposable_list = $this->get_domain_list('aev_disposable_domains');
        $major_providers = $this->get_domain_list('aev_email_providers');
        $domain = substr(strrchr($email, "@"), 1);

        if (!$this->is_valid_syntax($email)) {
            return $this->store_result($email, false, false, false, '', false, 'Invalid', ['event' => 'invalid_syntax', 'note'=> 'The address provided is not in a valid format.']);
        }

        if (!$this->has_mx_record($domain)) {
            return $this->store_result($email, true, false, false, $domain, false, 'Invalid', ['event' => 'invalid_domain', 'note'=> 'The address provided does not have a valid domain.']);
        }

        if ($this->is_disposable_domain($domain, $disposable_list)) {
            return $this->store_result($email, true, true, true, $domain, false, 'Failed', ['event' => 'disposable_domain', 'note'=> 'The address provided is a disposable domain.']);
        }

        if (in_array($domain, $major_providers)) {
            return $this->store_result($email, true, true, false, $domain, true, 'Passed', ['event' => 'mailbox_exists', 'note'=> 'The address provided passed all tests.']);
        }

        // Neutrino API
        $response_data = $this->check_with_neutrino($email);

        if (!empty($response_data['api-error'])) {
            wp_send_json_error([
                'status' => 'api-error',
                'email' => $email,
                'message' => $response_data['api-error-msg']
            ]);
        }

        if (!empty($response_data['valid']) && $response_data['valid'] == 1 && ($response_data['domain-status'] ?? '') === 'ok') {
            $provider = $response_data['provider'] ?? $domain;
            return $this->store_result($email, true, true, false, $domain, true, 'Passed', ['event' => 'mailbox_exists', 'note'=> 'The address provided passed all tests.'], true);
        }

        if (($response_data['domain-status'] ?? '') === 'invalid') {
            $domain        = $response_data['domain'] ?? $domain;
            $is_disposable = $response_data['is-disposable'] ?? false;
            return $this->store_result($email, true, true, $is_disposable, $domain, false, 'Invalid', ['event' => 'mailbox_does_not_exist', 'note'=> 'The address provided does not exist.'], true);
        }
    }

    private function check_with_neutrino($email) {
        $response = wp_remote_post('https://neutrinoapi.net/email-validate', [
            'timeout' => 300,
            'body' => [
                'user-id' => AEV_NEUTRINO_API_ID,
                'api-key' => AEV_NEUTRINO_API_KEY,
                'email'   => $email
            ]
        ]);

        if (is_wp_error($response)) {
            return ['valid' => false, 'api-error' => true, 'api-error-msg' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body) || !empty($body['error']) || $body['smtp-status'] === 'unknown') {
            $msg = $body['error'] ?? 'Invalid API key or no credits.';
            set_transient('aev_api_error_notice', $msg, MINUTE_IN_SECONDS * 10);
            return ['valid' => false, 'api-error' => true, 'api-error-msg' => $msg];
        }

        return $body;
    }

    private function get_domain_list($option_key) {
        $raw = get_option($option_key, '');
        return array_filter(array_map('strtolower', array_map('trim', preg_split('/[\r\n,]+/', $raw))));
    }

    private function is_valid_syntax($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function has_mx_record($domain) {
        return checkdnsrr($domain, 'MX');
    }

    private function is_disposable_domain($domain, $list) {
        return in_array(strtolower($domain), $list);
    }

    private function store_result($email, $syntax, $mx, $disposable, $provider, $smtp, $status, $response) {
        global $wpdb;

        $wpdb->insert($wpdb->prefix . 'email_checker_history', [
            'user_id_or_ip'     => aev_get_user_identifier(),
            'email'             => sanitize_email($email),
            'syntax_valid'      => $syntax ? 1 : 0,
            'mx_found'          => $mx ? 1 : 0,
            'disposable_domain' => $disposable ? 1 : 0,
            'provider'          => sanitize_text_field($provider),
            'smtp_check'        => $smtp ? 1 : 0,
            'status'            => sanitize_text_field($status),
            'response'          => wp_json_encode($response),
            'is_bulk'           => 1,
            'checked_at'        => current_time('mysql')
        ]);

        // Increment usage without resetting
        if(is_user_logged_in()) {
            $user_id = get_current_user_id();
            $used = (int) ( get_user_meta( $user_id, 'email_quota_used', true ) ?: 0 );
            update_user_meta( $user_id, 'email_quota_used', $used + 1 );
        }
    }

    public function export_csv() {
        if (!is_user_logged_in()) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $user_id = (string) get_current_user_id();
        $action  = current_action();

        if (strpos($action, 'passed') !== false) {
            $status     = ['Passed'];
            $filename   = 'passed-emails.csv';
            $dateFilter = true;
        } elseif (strpos($action, 'failed') !== false) {
            $status     = ['Failed', 'Invalid'];
            $filename   = 'failed-emails.csv';
            $dateFilter = true;
        } else { // all
            $status     = [];
            $filename   = 'all-emails.csv';
            $dateFilter = false;
        }

        // Base query
        $query = "SELECT email, checked_at FROM " . AEV_HISTORY_TABLE . " WHERE user_id_or_ip = %s";
        $params = [$user_id];

        // Add status filter if needed
        if (!empty($status)) {
            $placeholders = implode(',', array_fill(0, count($status), '%s'));
            $query .= " AND status IN ($placeholders)";
            $params = array_merge($params, $status);
        }

        // Add date filter for passed/failed only
        if ($dateFilter) {
            $query .= " AND checked_at >= DATE_SUB(NOW(), INTERVAL 10 DAY)";
        }

        $query .= " ORDER BY checked_at DESC";

        $results = $wpdb->get_results($wpdb->prepare($query, $params));

        // Output CSV
        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename=$filename");
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