<?php
/**
 * Plugin Name: Advanced Email Validation
 * Description: A professional email validation tool with membership limits, history tracking.
 * Author: Pluff Pixels
 * Author URI: https://fluffpixels.com/
 * Version: 1.0
 * Text Domain: advanced-email-validation
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AdvancedEmailValidation {
    public function __construct() {
        $this->define_constants();
        $this->load_includes();
        $this->register_hooks();
    }

    private function define_constants() {
        define('AEV_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
        define('AEV_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
        define('AEV_NEUTRINO_API_ID', get_option('aev_neutrino_api_user_id'));
        define('AEV_NEUTRINO_API_KEY', get_option('aev_neutrino_api_key'));
        define('AEV_HISTORY_TABLE', $GLOBALS['wpdb']->prefix . 'email_checker_history');
        define('AEV_FREE_VALIDATION_LIMIT', get_option('aev_free_email_limit'));
    }

    private function load_includes() {
        require_once AEV_PLUGIN_PATH . 'includes/core/hooks.php';
        require_once AEV_PLUGIN_PATH . 'includes/core/functions.php';
        require_once AEV_PLUGIN_PATH . 'includes/core/settings.php';
        require_once AEV_PLUGIN_PATH . 'includes/db/schema.php';
        require_once AEV_PLUGIN_PATH . 'includes/ajax/handler.php';
        require_once AEV_PLUGIN_PATH . 'includes/shortcodes/form.php';
        require_once AEV_PLUGIN_PATH . 'includes/shortcodes/history.php';
        require_once AEV_PLUGIN_PATH . 'includes/classes/bulk-import-validation.php';
        require_once AEV_PLUGIN_PATH . 'includes/classes/meta_box_subscription_email_validation.php';
    }

    private function register_hooks() {
        register_activation_hook( __FILE__, [ 'AEV_DB_Schema', 'install' ] );
    }
}

new AdvancedEmailValidation();
