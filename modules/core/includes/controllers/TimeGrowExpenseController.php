<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowExpenseController{

    private $expense_model;
    private $receipt_model;
    private $client_model;
    private $project_model;
    private $expense_view;

    public function __construct(TimeGrowExpenseModel $expense_model, 
                                TimeGrowExpenseReceiptModel $receipt_model, 
                                TimeGrowClientModel $client_model, 
                                TimeGrowProjectModel $project_model, 
                                TimeGrowExpenseView $expense_view, ) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $this->expense_model = $expense_model;
        $this->receipt_model = $receipt_model;
        $this->client_model = $client_model;
        $this->project_model = $project_model;
        $this->expense_view = $expense_view;
    }

    public function handle_form_submission() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        
        if (!isset($_POST['timegrow_expense_nonce_field']) || 
            !wp_verify_nonce($_POST['timegrow_expense_nonce_field'], 'timegrow_expense_nonce')) {
            wp_die(__('Nonce verification failed.', 'text-domain'));
        }

        if (!isset($_POST['expense_id'])) return; 
        $current_date = current_time('mysql');
        $data = [
            'expense_name' => sanitize_text_field($_POST['expense_name']),
            'expense_description' => sanitize_text_field($_POST['expense_description']),
            'expense_date' => sanitize_text_field($_POST['expense_date']),
            'expense_payment_method' => sanitize_text_field($_POST['expense_payment_method']),
            'amount' => floatval($_POST['amount']),
            'expense_category_id' => intval($_POST['expense_category_id']),
            'assigned_to' => sanitize_text_field($_POST['assigned_to']),
            'assigned_to_id' => intval($_POST['assigned_to_id']),
            'updated_at' => $current_date
        ];

        $format = [
            '%s',  // expense_name (string)
            '%s',  // expense_description (string)
            '%s',  // expense_date (string, could also use '%s' for MySQL date/datetime)
            '%s',  // expense_payment_method (string)
            '%f',  // amount (float)
            '%d',  // expense_category_id (integer)
            '%s',  // assigned_to (string)
            '%d',  // assigned_to_id (integer)
            '%s',  // updated_at (datetime string)
        ];

        $id = intval($_POST['expense_id']);
        if ($id == 0) {
            $data['created_at'] = $current_date;
            $format[] = '%s';
            $id = $this->expense_model->create($data, $format);

            if ($id) {
                echo '<div class="notice notice-success is-dismissible"><p>Expense added successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Error adding expense.</p></div>';
            }

        } else {

            $result = $this->expense_model->update($id, $data, $format);

            if ($result) {
                echo '<div class="notice notice-success is-dismissible"><p>Expense added successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Error adding expense.</p></div>';
            }

        }

        // Handle file upload
        // Note: AI analysis already happened via real-time AJAX when file was selected
        // Form fields are already auto-populated by JavaScript, so we just save the receipt reference
        if (!empty($_FILES['file_upload']['name'])) {
            $file = $_FILES['file_upload'];

            // Upload file
            $upload_result = $this->receipt_model->upload_file($file);

            if (!is_wp_error($upload_result)) {
                // Save receipt record to database
                // AI analysis data is null since analysis already happened in real-time
                $this->receipt_model->save_receipt_record($id, $upload_result['url'], null);
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Error uploading file: ' . esc_html($upload_result->get_error_message()) . '</p></div>';
            }
        }

    }

    public function list_expenses() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Get all expenses first for filter options
        $all_expenses = $this->expense_model->select(null);

        // Build WHERE clause for filtering
        global $wpdb;
        $where_conditions = [];
        $filter_assigned_to = isset($_GET['filter_assigned_to']) ? sanitize_text_field($_GET['filter_assigned_to']) : '';
        $filter_date_from = isset($_GET['filter_date_from']) ? sanitize_text_field($_GET['filter_date_from']) : '';
        $filter_date_to = isset($_GET['filter_date_to']) ? sanitize_text_field($_GET['filter_date_to']) : '';
        $filter_search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        if (!empty($filter_search)) {
            $search_term = '%' . $wpdb->esc_like($filter_search) . '%';
            $where_conditions[] = $wpdb->prepare(
                "(e.expense_name LIKE %s OR e.expense_description LIKE %s OR e.amount LIKE %s OR u.display_name LIKE %s OR p.name LIKE %s)",
                $search_term, $search_term, $search_term, $search_term, $search_term
            );
        }
        if (!empty($filter_assigned_to)) {
            $where_conditions[] = $wpdb->prepare("e.assigned_to = %s", $filter_assigned_to);
        }
        if (!empty($filter_date_from)) {
            $where_conditions[] = $wpdb->prepare("e.expense_date >= %s", $filter_date_from);
        }
        if (!empty($filter_date_to)) {
            $where_conditions[] = $wpdb->prepare("e.expense_date <= %s", $filter_date_to);
        }

        // Get orderby and order parameters
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'expense_date';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';

        // Validate orderby column
        $allowed_orderby = ['expense_date', 'amount', 'expense_name', 'assigned_to', 'expense_category_id'];
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'expense_date';
        }

        // Validate order direction
        $order = strtoupper($order);
        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'DESC';
        }

        // Map orderby to actual column names
        $orderby_column = $orderby;
        if ($orderby == 'expense_date' || $orderby == 'amount' || $orderby == 'expense_name' || $orderby == 'assigned_to' || $orderby == 'expense_category_id') {
            $orderby_column = 'e.' . $orderby;
        }

        // Fetch expenses with filtering and ordering
        $table_name = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
        $user_table = $wpdb->prefix . 'users';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $category_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_categories';
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $query = "SELECT e.*,
                         CASE
                             WHEN e.assigned_to = 'client' THEN u.display_name
                             ELSE NULL
                         END as client_name,
                         CASE
                             WHEN e.assigned_to = 'project' THEN p.name
                             ELSE NULL
                         END as project_name,
                         ec.name as category_name
                  FROM {$table_name} e
                  LEFT JOIN {$user_table} u ON (e.assigned_to = 'client' AND e.assigned_to_id = u.ID)
                  LEFT JOIN {$project_table} p ON (e.assigned_to = 'project' AND e.assigned_to_id = p.ID)
                  LEFT JOIN {$category_table} ec ON e.expense_category_id = ec.ID
                  {$where_clause}
                  ORDER BY {$orderby_column} {$order}";

        if(WP_DEBUG) error_log('Expense List Query: ' . $query);
        $expenses = $wpdb->get_results($query);
        if(WP_DEBUG) error_log('Expense Count: ' . count($expenses));

        // Get unique values for filters
        $filter_options = ['clients' => [], 'projects' => []];

        // Get clients for filter
        $clients = $this->client_model->select(null);
        foreach ($clients as $client) {
            $filter_options['clients'][$client->ID] = $client->company_name;
        }

        // Get projects for filter
        $projects = $this->project_model->select(null);
        foreach ($projects as $project) {
            $filter_options['projects'][$project->ID] = $project->name;
        }

        $this->expense_view->display_expenses($expenses, $filter_options, [
            'orderby' => $orderby,
            'order' => $order,
            'filter_assigned_to' => $filter_assigned_to,
            'filter_date_from' => $filter_date_from,
            'filter_date_to' => $filter_date_to,
            'filter_search' => $filter_search
        ]);
    }

    public function add_expenses() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $clients = $this->client_model->select(null); // Fetch all clients
        $projects = $this->project_model->select(null); // Fetch all projects
        $this->expense_view->add_expense($clients, $projects);
    }

    public function edit_expenses($id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $expense = $this->expense_model->select($id)[0]; // Fetch all expenses
        $receipts = $this->receipt_model->select_by_expense($id); // Fetch all expenses
        $clients = $this->client_model->select(null); // Fetch all clients
        $projects = $this->project_model->select(null); // Fetch all projects
        $this->expense_view->edit_expense($expense, $receipts, $clients, $projects);
    }

    public function display_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        if ($screen != 'list' && ( isset($_POST['add_item']) || isset($_POST['edit_item']) )) {
            var_dump('processing form');
            $this->handle_form_submission();
            $screen = 'list';
        }

        if ($screen == 'list')
            $this->list_expenses();
        elseif ($screen == 'add')
            $this->add_expenses();
        elseif ($screen == 'edit') {
            $id = 0;
            if ( isset( $_GET['id'] ) ) {
                $id = intval( $_GET['id'] ); // Sanitize the ID as an integer
            } else {
                wp_die( 'Error: Expense ID not provided in the URL.', 'Missing Expense ID', array( 'back_link' => true ) );
            }
            $this->edit_expenses($id);
        } elseif ($screen == 'receipt-delete') {
            $id = 0;
            if ( isset( $_GET['id'] ) ) {
                $id = intval( $_GET['id'] ); // Sanitize the ID as an integer
            } else {
                wp_die( 'Error: Expense ID not provided in the URL.', 'Missing Expense ID', array( 'back_link' => true ) );
            }
            $this->edit_expenses($id);
        }
    }

}
