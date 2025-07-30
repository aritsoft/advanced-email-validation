<?php

add_action('admin_notices', 'aev_show_api_error_notice');

function aev_show_api_error_notice() {
    if (!current_user_can('manage_options')) return;

    $error_message = get_transient('aev_api_error_notice');
    if ($error_message) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>Advanced Email Validation:</strong> ' . esc_html($error_message) . '</p>';
        echo '</div>';
        delete_transient('aev_api_error_notice');
    }
}