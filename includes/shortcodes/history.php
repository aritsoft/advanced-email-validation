<?php
function aev_email_checker_user_history() {
    ob_start(); ?>
    <div class="aev-responsive-table">
        <form method="post" action="<?php echo site_url('/wp-admin/admin-post.php'); ?>">
        <input type="hidden" name="action" value="export_all_emails_csv">
        <button type="submit" class="button button-secondary">Export Emails</button>
        </form>
        <table class="aev-history-table" id="aev-history-table">
            <thead>
                <tr><th>Email</th><th>Status</th><th>Event</th><th>Details</th></tr>
            </thead>
            <tbody class="aevhistoryrows">
                <?php
                global $wpdb;
                $table = $wpdb->prefix . 'email_checker_history';
                $user_id = aev_get_user_identifier();
                $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE user_id_or_ip = %s ORDER BY checked_at DESC LIMIT 20", $user_id));
                if ($rows) {
                    foreach ($rows as $row) {
                        $status_color = $row->status =='Passed' ? 'aev-green' : 'aev-red';
                        $response = json_decode($row->response);
                        echo '<tr>';
                        echo '<td>'.esc_html($row->email).'</td>';
                        echo '<td><span class="aev-tag ' . esc_attr($status_color) . '">'.esc_html($row->status).'</span></td>';
                        echo '<td>'.esc_html($response->event).'</td>';
                        echo '<td>'.esc_html($response->note).'</td>';
                        echo '</tr>';
                    }
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('aev_email_validation_user_history', 'aev_email_checker_user_history');
