<?php
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AEV_Email_History_Table extends WP_List_Table {

    public function get_columns() {
        return [
            'cb'           => '<input type="checkbox" class="manage-column column-cb check-column" />',
            'email'        => 'Email Address',
            'syntax_valid' => 'Syntax',
            'mx_found'     => 'MX Found',
            'provider'     => 'Domain',
            'smtp_check'   => 'SMTP',
            'status'       => 'Status',
            'checked_at'   => 'Checked At',
        ];
    }

    public function get_sortable_columns() {
        return [
            'email'        => ['email', false],
            'syntax_valid' => ['syntax_valid', false],
            'mx_found'     => ['mx_found', false],
            'provider'     => ['provider', false],
            'smtp_check'   => ['smtp_check', false],
            'status'       => ['status', false],
            'checked_at'   => ['checked_at', false],
        ];
    }

    public function get_bulk_actions() {
        return [
            'export' => 'Export Selected',
            'delete' => 'Delete Selected',
        ];
    }

    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="email_ids[]" value="%d" />',
            intval($item['id'])
        );
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'email':
            case 'provider':
            case 'status':
            case 'checked_at':
                return esc_html($item[$column_name]);
            case 'syntax_valid':
            case 'mx_found':
            case 'smtp_check':
                return $item[$column_name] ? '✔️' : '❌';
            default:
                return esc_html(print_r($item, true));
        }
    }

    public function extra_tablenav($which) {
        if ($which === 'top') {
            ?>
            <div class="alignleft actions">
                <?php
                // Add Export All Validations button inside the same form
                wp_nonce_field('aev_export_csv_action', 'aev_export_csv_nonce');
                submit_button('Export All Validations', 'secondary', 'aev_export_csv', false);
                ?>
            </div>
            <?php
        }
    }

    public function prepare_items() {
        global $wpdb;
        $table = AEV_HISTORY_TABLE;

        $per_page     = 10;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $columns  = $this->get_columns();
        $hidden   = [];
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = [$columns, $hidden, $sortable];

        $this->process_bulk_action();

        $search = (!empty($_REQUEST['s'])) ? sanitize_text_field($_REQUEST['s']) : '';
        $where  = '';
        if ($search) {
            $like  = '%' . $wpdb->esc_like($search) . '%';
            $where = $wpdb->prepare("WHERE email LIKE %s", $like);
        }

        $orderby = !empty($_REQUEST['orderby']) ? sanitize_key($_REQUEST['orderby']) : 'id';
        $allowed = array_keys($this->get_sortable_columns());
        if (!in_array($orderby, $allowed)) {
            $orderby = 'id';
        }

        $order = (!empty($_REQUEST['order']) && in_array(strtoupper($_REQUEST['order']), ['ASC', 'DESC'])) ? strtoupper($_REQUEST['order']) : 'DESC';

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table $where");
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table $where ORDER BY $orderby $order LIMIT %d OFFSET %d", $per_page, $offset),
            ARRAY_A
        );

        $this->items = $results;

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ]);
    }

    public function process_bulk_action() {
        if (empty($_REQUEST['email_ids'])) {
            return;
        }

        $ids = array_map('intval', (array) $_REQUEST['email_ids']);
        if (empty($ids)) return;

        global $wpdb;
        $table = AEV_HISTORY_TABLE;

        if ($this->current_action() === 'delete') {
            $in = implode(',', array_fill(0, count($ids), '%d'));
            $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id IN ($in)", ...$ids));
        }

        if ($this->current_action() === 'export') {
            $in = implode(',', array_fill(0, count($ids), '%d'));
            $results = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM $table WHERE id IN ($in)", ...$ids), ARRAY_A
            );

            // Clean output buffer
            if (ob_get_length()) {
                ob_end_clean();
            }

            // Output CSV headers
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="selected-emails.csv"');
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
    }
}