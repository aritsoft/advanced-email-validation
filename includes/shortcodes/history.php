<?php
function aev_email_checker_user_history() {
    global $wpdb;
    $table = $wpdb->prefix . 'email_checker_history';
    $user_id = aev_get_user_identifier();
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id_or_ip = %d ORDER BY checked_at DESC LIMIT 20", $user_id));
    ob_start(); ?>
    <table class="aev-history-table">
        <thead>
            <tr><th>Email</th><th>Status</th><th>Event</th><th>Details</th></tr>
        </thead>
        <tbody>
            <?php
            if ($rows) {
                foreach ($rows as $row) {
                    $status_color = $row->smtp_check ? 'aev-green' : ($row->syntax_valid ? 'aev-orange' : 'aev-red');
                    $response = json_decode($row->response);
                    echo '<tr>';
                    echo '<td>'.esc_html($row->email).'</td>';
                    echo '<td>'.esc_html($row->status).'</td>';
                    echo '<td>'.esc_html($response->event).'</td>';
                    echo '<td>'.esc_html($response->event).'</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="4">There are no verified email addresses in the history to be displayed.</td></tr>';
            }
            ?>
        </tbody>
    </table>
    <?php return ob_get_clean();
}
add_shortcode('aev_email_validation_user_history', 'aev_email_checker_user_history');
