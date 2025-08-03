<?php
defined('ABSPATH') || exit;

class AEV_Validate_Email {

    public $email_syntax = true; 
    public $valid_domain = true; 
    public $is_disposable = false;
    public $provider = ''; 
    public $smtp_check = false;
    public $final_status = 'Invalid';

    public function validate_email_address($email) {
        $user_id = aev_get_user_identifier();
        $ace_limit = get_aev_email_check_limit();
        $disposable_list = $this->load_disposable_domains();
        $major_providers = $this->load_major_email_providers();

        if (aev_is_limit_reached($user_id, $ace_limit)) {
            wp_send_json_error([
                'status' => 'limit_reached', 
                'email' => $email,
                'message' => 'You have no more credits to verify email address. <a href="'.site_url().'/pricing" target="_blank">Upgrade your plan</a>'
            ]);
        }

        // Step 1: Syntax
        if (!$this->is_valid_syntax($email)) {
            $this->email_syntax = false;
            $this->final_status = 'Invalid';
            $this->aev_store_email_validation($email, false, false, false, '', false, $this->final_status, ['note' => 'The address provided is not in a valid format.']);
            wp_send_json_error([
                'status' => 'invalid_syntax', 
                'email' => $email,
                'message' => 'The address provided is not in a valid format.'
            ]);
        }

        // Get domain
        $domain = substr(strrchr($email, "@"), 1);

        // Step 2: MX
        if (!$this->has_mx_record($domain)) {
            $this->valid_domain = false;
            $this->final_status = 'Invalid';
            $this->aev_store_email_validation(
                $email, 
                true, 
                false, 
                false, 
                $domain, 
                false, 
                $this->final_status, 
                ['note' => 'The address provided does not have a valid dns entry.', 'event'=>'invalid_domain',]
            );
            wp_send_json_error([
                'status' => 'invalid_domain', 
                'email' => $email,
                'message' => 'The address provided does not have a valid domain'
            ]);
        }

        // Step 3: Disposable
        if ($this->is_disposable_domain($domain, $disposable_list)) {
            $this->is_disposable = true;
            $this->final_status = 'Failed';
            $this->aev_store_email_validation(
                $email, 
                true, 
                true, 
                true, 
                $domain, 
                false, 
                $this->final_status, ['note' => 'The address provided is a disposable domain', 'event'=> 'domain_does_not_exist']
            );
            wp_send_json_error([
                'status' => 'disposable', 
                'email' => $email,
                'message' => 'The address provided is a disposable domain.'
            ]);
        }

        // Step 4: Major Providers
        if (in_array($domain, $major_providers)) {
            $this->provider = $domain;
            $this->final_status = 'Passed';
            $this->aev_store_email_validation(
                $email, 
                true, 
                true, 
                false, 
                $domain, 
                true, 
                $this->final_status, 
                ['note' => 'The address provided passed all tests.', 'event'=>'mailbox_exists']
            );
            wp_send_json_success([
                'status' => 'valid_major_provider', 
                'email' => $email,
                'message' => 'The address provided passed all tests.'
            ]);
        }

        // Step 5: Neutrino API
        $response_data = $this->check_with_neutrino($email);

        if (!empty($response_data['api-error'])) {
            wp_send_json_error([
                'status' => 'api-error', 
                'email' => $email,
                'message' => $response_data['api-error-msg']
            ]);
        } else if (!empty($response_data['valid']) && empty($response_data['domain-error'])) {
            $this->smtp_check = !empty($response_data['valid']);
            $this->provider = isset($response_data['domain']) ? $response_data['domain'] : $domain;
            $this->final_status = 'Passed';
            $this->aev_store_email_validation(
                $email,
                true,
                true,
                false,
                $this->provider,
                $this->smtp_check,
                $this->final_status,
                ['note' => 'The address provided passed all tests.', 'event'=>'mailbox_exists']
            );
            wp_send_json_success(['status' => 'Passed', 'email' => $email, 'message' => 'The address provided passed all tests.']);
        } else {
            $this->smtp_check = !empty($response_data['valid']);
            $this->provider = isset($response_data['domain']) ? $response_data['domain'] : $domain;
            $this->final_status = 'Invalid';
            $this->aev_store_email_validation(
                $email,
                true,
                true,
                false,
                $this->provider,
                $this->smtp_check,
                $this->final_status,
                ['note' => 'The address provided does not have a valid dns entry.', 'event'=>'domain_does_not_exist']
            );
            wp_send_json_error(['status' => 'Invalid', 'email' => $email, 'message' => 'The address provided does not have a valid dns entry.']); 
        }
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

    public function check_with_neutrino($email) {
        $response = wp_remote_post('https://neutrinoapi.net/email-validate', [
            'timeout' => 10,
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

    public function aev_store_email_validation($email, $syntax_valid, $mx_found, $disposable_domain, $provider, $smtp_check, $status, $response) {
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
                'checked_at'        => current_time('mysql')
            ],
            ['%s','%s','%d','%d','%d','%s','%d','%s','%s','%s']
        );
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
}