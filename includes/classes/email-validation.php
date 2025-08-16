<?php
defined('ABSPATH') || exit;

class AEV_Validate_Email {

    public function validate_email_address($email) {
        $user_id          = aev_get_user_identifier();
        $remaining_credits      = get_remaining_email_credits();
        $disposable_list  = $this->load_disposable_domains();
        $major_providers  = $this->load_major_email_providers();
        $domain           = substr(strrchr($email, "@"), 1);

        if ($remaining_credits <=0) {
            $this->respond_error('limit_reached', $email, 'You have no more credits to verify email address. <a href="' . site_url('/pricing') . '" target="_blank">Upgrade your plan</a>');
        }

        if (!$this->is_valid_syntax($email)) {
            $this->store_and_respond($email, false, false, false, '', false, 'Invalid', 'invalid_syntax', 'The address provided is not in a valid format.');
        }

        if (!$this->has_mx_record($domain)) {
            $this->store_and_respond($email, true, false, false, $domain, false, 'Invalid', 'invalid_domain', 'The address provided does not have a valid domain.');
        }

        if ($this->is_disposable_domain($domain, $disposable_list)) {
            $this->store_and_respond($email, true, true, true, $domain, false, 'Failed', 'disposable', 'The address provided is a disposable domain.');
        }

        if (in_array($domain, $major_providers)) {
            $this->store_and_respond($email, true, true, false, $domain, true, 'Passed', 'major_provider', 'The address provided passed all tests.', true);
        }

        // Step 5: Neutrino API
        $response_data = $this->check_with_neutrino($email);
        
        //echo '<pre>'; print_r($response_data); exit;

        if (!empty($response_data['api-error'])) {
            $this->respond_error('api-error', $email, $response_data['api-error-msg']);
        }

        if (!empty($response_data['valid']) && $response_data['valid'] == 1 && ($response_data['domain-status'] ?? '') === 'ok') {
            $provider = $response_data['provider'] ?? $domain;
            $this->store_and_respond($email, true, true, false, $provider, true, 'Passed', 'mailbox_exists', 'The address provided passed all tests.', true);
        }

        if (($response_data['domain-status'] ?? '') === 'invalid') {
            $domain        = $response_data['domain'] ?? $domain;
            $is_disposable = $response_data['is-disposable'] ?? false;
            $this->store_and_respond($email, true, true, $is_disposable, $domain, false, 'Invalid', 'mailbox_does_not_exist', 'The address provided does not exist.');
        }

        // Default fallback
        $this->respond_error('unknown_error', $email, 'Unable to validate the email address.');
    }

    public function load_disposable_domains() {
        $domains = get_option('aev_disposable_domains', '');
        return empty($domains) ? [] : array_filter(array_map('strtolower', array_map('trim', preg_split('/[\r\n,]+/', $domains))));
    }

    public function load_major_email_providers() {
        $providers = get_option('aev_email_providers', '');
        return empty($providers) ? [] : array_filter(array_map('strtolower', array_map('trim', preg_split('/[\r\n,]+/', $providers))));
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
            return ['valid' => false, 'api-error' => true, 'api-error-msg' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body) || !empty($body['error']) || ($body['smtp-status'] ?? '') === 'unknown') {
            $error = $body['error'] ?? 'Invalid API key or no credits left.';
            set_transient('aev_api_error_notice', $error, MINUTE_IN_SECONDS * 10);
            return ['valid' => false, 'api-error' => true, 'api-error-msg' => $error];
        }

        return $body;
    }

    public function aev_store_email_validation($email, $syntax_valid, $mx_found, $disposable_domain, $provider, $smtp_check, $status, $response) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'email_checker_history',
            [
                'user_id_or_ip'     => is_user_logged_in() ? get_current_user_id() : $_SERVER['REMOTE_ADDR'],
                'email'             => sanitize_email($email),
                'syntax_valid'      => (int) $syntax_valid,
                'mx_found'          => (int) $mx_found,
                'disposable_domain' => (int) $disposable_domain,
                'provider'          => sanitize_text_field($provider),
                'smtp_check'        => (int) $smtp_check,
                'status'            => sanitize_text_field($status),
                'response'          => wp_json_encode($response),
                'checked_at'        => current_time('mysql')
            ],
            ['%s','%s','%d','%d','%d','%s','%d','%s','%s','%s']
        );

        // Increment usage without resetting
        if(is_user_logged_in()) {
            $user_id = get_current_user_id();
            $used = (int) ( get_user_meta( $user_id, 'email_quota_used', true ) ?: 0 );
            update_user_meta( $user_id, 'email_quota_used', $used + 1 );
        }
    }

    public function is_valid_syntax($email) {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public function has_mx_record($domain) {
        return checkdnsrr($domain, 'MX');
    }

    public function is_disposable_domain($domain, $disposable_list) {
        return in_array(strtolower($domain), $disposable_list, true);
    }

    private function respond_error($status, $email, $message) {
        $user_id          = aev_get_user_identifier();
        $remaining_credits = get_remaining_email_credits();
        wp_send_json_error([
            'status'  => $status,
            'email'   => $email,
            'credits' => $remaining_credits,
            'message' => $message
        ]);
    }

    private function store_and_respond($email, $syntax_valid, $mx_found, $disposable_domain, $provider, $smtp_check, $status, $response_status, $message, $is_success = false) {
        $response = [
            'note'  => $message,
            'event' => $response_status
        ];
        $this->aev_store_email_validation($email, $syntax_valid, $mx_found, $disposable_domain, $provider, $smtp_check, $status, $response);
        
        $remaining_credits = get_remaining_email_credits();
        $result = [
            'status'  => $status,
            'email'   => $email,
            'credits' => $remaining_credits,
            'message' => $message,
            'event'   => $response_status

        ];

        $is_success ? wp_send_json_success($result) : wp_send_json_error($result);
    }
}