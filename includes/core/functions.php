<?php
defined('ABSPATH') || exit;

function aev_enqueue_scripts() {
    wp_enqueue_style('aev-styles', AEV_PLUGIN_URL . 'assets/css/aev.css');
    wp_enqueue_script('aev-script', AEV_PLUGIN_URL . 'assets/js/checker.js', ['jquery'], null, true);
    wp_localize_script('aev-script', 'emailCheckerAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('aev_email_nonce')
    ]);
}

function aev_get_user_ip() {
    foreach ([
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'REMOTE_ADDR'
    ] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = explode(',', $_SERVER[$key]);
            return trim($ip[0]);
        }
    }
    return 'UNKNOWN';
}

function aev_get_user_identifier() {
    return is_user_logged_in() ? get_current_user_id() : aev_get_user_ip();
}

function get_aev_email_check_limit() {
    $aev_free_email_limit = get_option('aev_free_email_limit', AEV_FREE_VALIDATION_LIMIT);
    // Check if the user is logged in
    if ( ! is_user_logged_in() ) {
        return $aev_free_email_limit; // Not logged in → Free limit
    }

    // Get current user ID
    $current_user_id = aev_get_user_identifier();

    // Ensure Paid Member Subscriptions function exists
    if ( ! function_exists( 'pms_get_member_subscriptions' ) ) {
        return $aev_free_email_limit;
    }

    // Get all subscriptions for the user
    $subscriptions = pms_get_member_subscriptions( array( 'user_id' => $current_user_id ) );

    if ( ! empty( $subscriptions ) ) {
        foreach ( $subscriptions as $subscription ) {
            // Only consider active subscription
            if ( $subscription->status === 'active' ) {
                $plan_id = $subscription->subscription_plan_id;
                $plan_validation_limit = get_post_meta( $plan_id, 'email_validation_limit', true );

                // If a value is found, return it
                if ( ! empty( $plan_validation_limit ) ) {
                    return (int) $plan_validation_limit;
                }
            }
        }
        return $aev_free_email_limit;
    }
    return $aev_free_email_limit;   
}

function aev_is_limit_reached($user_id, $limit = 3) 
{
    global $wpdb;
    $today = date('Y-m-d');
    $used = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM ".AEV_HISTORY_TABLE." WHERE user_id_or_ip = %d AND DATE(checked_at) = %s", $user_id, $today));
    return $used >= $limit;
}

function aev_user_check_monthly_usage($user_id) {
    global $wpdb;
    // $today = date('Y-m-d');
    // return $wpdb->get_var(
    //     $wpdb->prepare("SELECT COUNT(*) FROM ".AEV_HISTORY_TABLE." WHERE user_id_or_ip = %d AND DATE(checked_at) = %s", $user_id, $today)
    // );

    $year  = date('Y');
    $month = date('m');
    return $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM " . AEV_HISTORY_TABLE . " 
            WHERE user_id_or_ip = %d 
            AND YEAR(checked_at) = %d 
            AND MONTH(checked_at) = %d",
            $user_id,
            $year,
            $month
        )
    );
}

function get_remaining_email_credits() {
    $user_id = aev_get_user_identifier();
    $used  = function_exists('aev_user_check_monthly_usage') ? aev_user_check_monthly_usage($user_id) : 0;
    $limit = function_exists('get_aev_email_check_limit') ? get_aev_email_check_limit() : AEV_FREE_VALIDATION_LIMIT;
    $remaining = max(0, $limit - $used);
    return $remaining;       
}

function bulk_passed_varification($num_days) {
    global $wpdb;
    $user_id = aev_get_user_identifier();
    $passed_emails = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT email FROM " . AEV_HISTORY_TABLE . "
            WHERE user_id_or_ip = %d
            AND checked_at >= DATE_SUB(NOW(), INTERVAL $num_days DAY)
            AND status = %s", $user_id, 'Passed'
        )
    );

    $passed_email_Widget = "<h3>Bulk Verifications Passed - Last {$num_days} Days</h3>";

    if(count($passed_emails)>0) {
        foreach ($passed_emails as $email) {
            $passed_email_Widget .= '<p>'.esc_html($email) . '</p>';
        }

        $passed_email_Widget .= '<form method="post" action="'.site_url('/').'wp-admin/admin-post.php">
        <input type="hidden" name="action" value="export_passed_emails_csv">
        <button type="submit" class="button button-secondary"><img draggable="false" role="img" class="emoji" alt="⬇" src="https://s.w.org/images/core/emoji/16.0.1/svg/2b07.svg"> Export Passed Emails</button>
        </form>';
    } 
    echo $passed_email_Widget;
}

function bulk_failed_varification($num_days) {
    global $wpdb;
    $user_id = aev_get_user_identifier();
    $passed_emails = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT email FROM " . AEV_HISTORY_TABLE . "
            WHERE user_id_or_ip = %d
            AND checked_at >= DATE_SUB(NOW(), INTERVAL $num_days DAY)
            AND (status = %s OR status = %s)", $user_id, 'Failed', 'Invalid'
        )
    );

    $failed_email_Widget = "<h3>Bulk Verifications Failed - Last {$num_days} Days</h3>";

    if(count($passed_emails)>0) {
        foreach ($passed_emails as $email) {
            $failed_email_Widget .= '<p>'.esc_html($email) . '</p>';
        }

        $failed_email_Widget .= '<form method="post" action="'.site_url('/').'wp-admin/admin-post.php">
        <input type="hidden" name="action" value="export_failed_emails_csv">
        <button type="submit" class="button button-secondary"><img draggable="false" role="img" class="emoji" alt="⬇" src="https://s.w.org/images/core/emoji/16.0.1/svg/2b07.svg"> Export Failed Emails</button>
        </form>';
    } 

    echo $failed_email_Widget;
}