<?php
defined('ABSPATH') || exit;

function aev_enqueue_scripts() {
    wp_enqueue_style('aev-styles', AEV_PLUGIN_URL . 'assets/css/aev.css');
    wp_enqueue_script('aev-script', AEV_PLUGIN_URL . 'assets/js/checker.js', ['jquery'], null, true);
    wp_localize_script('aev-script', 'emailCheckerAjax', ['ajax_url' => admin_url('admin-ajax.php')]);
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
    $aev_free_email_limit = get_option('aev_free_email_limit', 10);
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

function get_aev_user_daily_check_usage($user_id) {
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