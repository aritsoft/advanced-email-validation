<?php
add_action('wp_enqueue_scripts', 'aev_enqueue_scripts');
add_action('wp_ajax_nopriv_handle_ajax_requests', 'handleAjaxRequests');
add_action('wp_ajax_handle_ajax_requests', 'handleAjaxRequests');