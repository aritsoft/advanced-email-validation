<?php
class AEV_DB_Schema {
    public static function install() {
        global $wpdb;
        $table_name = AEV_HISTORY_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id_or_ip VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL,
            syntax_valid TINYINT(1) DEFAULT 0,
            mx_found TINYINT(1) DEFAULT 0,
            disposable_domain TINYINT(1) DEFAULT 0,
            provider VARCHAR(100) DEFAULT NULL,
            smtp_check TINYINT(1) DEFAULT 0,
            status ENUM('Passed', 'Failed', 'Invalid') DEFAULT 'Invalid',
            response TEXT DEFAULT NULL,
            is_bulk TINYINT(1) DEFAULT 0,
            plan_id INT(10) NOT NULL DEFAULT 0,
            checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY email_idx (email),
            KEY checked_at_idx (checked_at)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}
