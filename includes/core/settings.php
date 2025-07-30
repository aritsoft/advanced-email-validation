<?php

defined('ABSPATH') || exit;

// Load admin-only files
if (is_admin()) {
    require_once AEV_PLUGIN_PATH . '/includes/admin/settings-schema.php';
    require_once AEV_PLUGIN_PATH . '/includes/admin/settings-register.php';
    require_once AEV_PLUGIN_PATH . '/includes/admin/settings-ui.php';
    require_once AEV_PLUGIN_PATH . '/includes/admin/notices.php';
}