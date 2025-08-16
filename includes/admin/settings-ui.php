<?php
add_action('admin_menu', 'aev_email_validation_menu');
add_action('admin_init', 'aev_handle_export_csv');

function aev_email_validation_menu() {
    add_options_page(
        'Email Validation',
        'Email Validation',
        'manage_options',
        'aev-settings',
        'aev_render_settings_page'
    );

    add_submenu_page(
        null,
        'Email Validation History',
        'Email History',
        'manage_options',
        'aev-email-history',
        'aev_render_history_page'
    );
}

function aev_generic_field_callback($args) {
    $option = get_option($args['id'], $args['default']);
    $type = $args['field_type'] ?? 'text';

    if ($type === 'checkbox') {
        echo '<input type="checkbox" id="' . esc_attr($args['id']) . '" name="' . esc_attr($args['id']) . '" value="1" ' . checked(1, $option, false) . ' />';
    } else if ($type === 'textarea') {
        echo '<textarea id="' . esc_attr($args['id']) . '" name="' . esc_attr($args['id']) . '" rows="' . esc_attr($args['rows']) . '" class="large-text code">' . esc_textarea($option) . '</textarea>';
    } else {
        echo '<input type="' . esc_attr($type) . '" id="' . esc_attr($args['id']) . '" name="' . esc_attr($args['id']) . '" value="' . esc_attr($option) . '" class="regular-text" />';
    }

    if (!empty($args['description'])) {
        echo '<p class="description">' . esc_html($args['description']) . '</p>';
    }
}

function aev_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Advanced Email Validation Settings</h1>
        <form method="post" action="options.php">
            <?php
                settings_fields('aev_settings_group');
                do_settings_sections('aev-settings');
                submit_button();
            ?>
        </form>
    </div>
    <?php
}

function aev_render_history_page() {
    require_once AEV_PLUGIN_PATH . 'includes/classes/email-history-table.php';
    $table = new AEV_Email_History_Table();
    $table->prepare_items();
    ?>
    <div class="wrap">
        <h1>Email Validation History</h1>

        <form method="get">
            <input type="hidden" name="page" value="<?php echo esc_attr($_REQUEST['page']); ?>" />
            <?php $table->search_box('Search Email', 'email'); ?>
        </form>

        <form method="post">
            <?php
            // Render the bulk actions, export button, and table
            $table->display();
            ?>
        </form>
    </div>
    <?php
}

function aev_handle_export_csv() {

    if (!current_user_can('manage_options') || !isset($_POST['aev_export_csv'])) {
        return;
    }

    global $wpdb;
    $table_name = AEV_HISTORY_TABLE;

    $results = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

    if (empty($results)) {
        wp_die('No records found to export.');
    }

    // Output CSV headers
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="email-validation-history.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output CSV content
    $output = fopen('php://output', 'w');

    // Output column headings
    fputcsv($output, array_keys($results[0]));

    // Output rows
    foreach ($results as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}