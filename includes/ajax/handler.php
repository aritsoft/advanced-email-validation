<?php

defined('ABSPATH') || exit;

require_once AEV_PLUGIN_PATH . 'includes/classes/email-validation.php';

// === AJAX Handler ===
function handleAjaxRequests() {
    if(!isset($_REQUEST['load_data'])) {
        echo "go away";
        die();
    }

    if(isset($_REQUEST['load_data']) && $_REQUEST['load_data'] == 'email_validate') {
        $aev_email_obj = new AEV_Validate_Email();
        $email = sanitize_email($_POST['email'] ?? '');
        $aev_email_obj->validate_email_address($email);
    }
}
