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
            'category' => sanitize_text_field($_POST['category']),
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
            '%s',  // category (string)
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
        if (!empty($_FILES['file_upload']['name'])) {
            $file = $_FILES['file_upload'];
            $this->receipt_model->update($id, $file);
        }

    }

    public function list_expenses() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $expenses = $this->expense_model->select(null); // Fetch all expenses
        $this->expense_view->display_expenses($expenses);
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
