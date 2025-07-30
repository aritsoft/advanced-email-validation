<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}
global $wpdb;
$table = $wpdb->prefix . 'email_checker_history';
$wpdb->query( "DROP TABLE IF EXISTS $table" );
