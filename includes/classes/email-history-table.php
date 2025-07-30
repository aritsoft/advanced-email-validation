<?php
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AEV_Email_History_Table extends WP_List_Table {

    public function get_columns() {
        return [
            'email' => 'Email Address',
            'syntax_valid' => 'Syntax',
            'mx_found' => 'MX Found',
            'provider' => 'Domain',
            'smtp_check' => 'SMTP',
            'status' => 'Status',
            'checked_at' => 'Checked At',
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $table = AEV_HISTORY_TABLE;

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $offset = ($current_page - 1) * $per_page;

        $columns = $this->get_columns();
        $hidden = [];
        $sortable = [];

        $search = (!empty($_REQUEST['s'])) ? sanitize_text_field($_REQUEST['s']) : '';

        $where = '';
        if ($search) {
            $where = $wpdb->prepare("WHERE email LIKE %s", '%' . $wpdb->esc_like($search) . '%');
        }

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table $where");

        $results = $wpdb->get_results("SELECT * FROM $table $where ORDER BY id DESC LIMIT $offset, $per_page", ARRAY_A);

        $hidden = [];
        $sortable = ['email'];
        $this->_column_headers = [$columns, $hidden, $sortable];
        $this->items = $results;

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ]);
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'email':
                return $item[$column_name];
            case 'syntax_valid':
                return ($item[$column_name] ? '✔️' : '❌');
            case 'mx_found':
                return ($item[$column_name] ? '✔️' : '❌');
            case 'provider':
                return esc_html($item[$column_name]);
            case 'smtp_check':
                return ($item[$column_name] ? '✔️' : '❌');
            case 'status':
                return esc_html($item[$column_name]);
            case 'checked_at':
                return esc_html($item[$column_name]);
            default:
                return print_r($item, true);
        }
    }

    public function get_sortable_columns() {
        return [
            'email' => ['email', true],
            'created_at' => ['created_at', false],
        ];
    }

    public function display() {
        $this->display_tablenav('top');

        echo '<table class="wp-list-table widefat fixed striped">';
        $this->print_column_headers();

        echo '<tbody id="the-list">';
        foreach ($this->items as $item) {
            echo '<tr>';
            foreach ($this->get_columns() as $column_name => $column_display_name) {
                echo '<td>';
                echo $this->column_default($item, $column_name);
                echo '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody>';

        echo '</table>';

        $this->display_tablenav('bottom');
    }

    public function extra_tablenav($which) {
        if ($which === 'top') {
            ?>
            <div class="alignleft actions" style="margin-right: 10px;">
                <form method="post" style="display:inline;">
                    <?php submit_button('Export CSV', 'secondary', 'aev_export_csv', false); ?>
                </form>
            </div>
            <?php
        }
    }
}