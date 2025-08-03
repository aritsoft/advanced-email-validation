<?php

defined('ABSPATH') || exit;

require_once AEV_PLUGIN_PATH . 'includes/classes/email-validation.php';
require_once AEV_PLUGIN_PATH . 'includes/classes/bulk-import-validation.php';

// === AJAX Handler ===
function handleAjaxRequests() {
    if(!isset($_REQUEST['load_data'])) {
        wp_send_json_error('Invalid request');
    } 
    
    if(isset($_REQUEST['load_data']) && $_REQUEST['load_data'] == 'email_validate') {
        $aev_email_obj = new AEV_Validate_Email();
        $email = sanitize_email($_POST['email'] ?? '');
        $aev_email_obj->validate_email_address($email);
    } else if(isset($_REQUEST['load_data']) && $_REQUEST['load_data'] == 'bluk_email_validate') {
        $bulk_import_emails = new AEV_Bulk_Import_Email_Validation(); 
        $bulk_import_emails->bulk_email_validations();       
    }
}
