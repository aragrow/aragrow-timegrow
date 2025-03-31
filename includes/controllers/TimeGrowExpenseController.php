<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowExpenseController{

    private $expense_model;
    private $receipt_model;
    private $expense_view;

    public function __construct(TimeGrowExpenseModel $expense_model, TimeGrowExpenseReceiptModel $receipt_model, TimeGrowExpenseView $expense_view) {
        $this->expense_model = $expense_model;
        $this->receipt_model = $receipt_model;
        $this->expense_view = $expense_view;
    }

    public function handle_form_submission() {
        
        if (!isset($_POST['id'])) return; 

        $data = [
            'expense_name' => sanitize_text_field($_POST['expense_name']),
            'amount' => floatval($_POST['amount']),
            'category' => sanitize_text_field($_POST['category']),
            'assigned_to' => sanitize_text_field($_POST['assigned_to']),
            'expense_description' => sanitize_textarea_field($_POST['expense_description']),
        ];

        if ($_POST['id'] == 0) {
            
            $id = $this->expense_model->create($data);

            if ($id) {
                echo '<div class="notice notice-success is-dismissible"><p>Expense added successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Error adding expense.</p></div>';
            }

        } else {

            $id = intval($_POST['id']);
            $result = $this->expense_model->update($id, $data);

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
        $expenses = $this->expense_model->select(null); // Fetch all expenses
        $this->expense_view->display_expenses($expenses);
    }

    public function add_expenses() {
        global $wpdb;
        $clients = $wpdb->get_results("SELECT ID, name FROM {$wpdb->prefix}timeflies_clients ORDER BY name", ARRAY_A);
        $this->expense_view->add_expense($clients);
    }

    public function edit_expenses($id) {
        global $wpdb;
        $clients = $wpdb->get_results("SELECT ID, name FROM {$wpdb->prefix}timeflies_clients ORDER BY name", ARRAY_A);
        $expense = $this->expense_model->select($id); // Fetch all expenses
        $receipt = $this->receipt_model->select($id); // Fetch all expenses
        $this->expense_view->edit_expense($expense, $receipt, $clients);
    }

    public function display_admin_page($screen) {
    
        if ($screen == 'list')
            $this->list_expenses();
        elseif ($screen == 'add')
            $this->add_expenses();
        elseif ($screen == 'edit') {
            $id = 0;
            if ( isset( $_GET['id'] ) ) {
                $id = intval( $_GET['id'] ); // Sanitize the ID as an integer
                return $id;
            } else {
                wp_die( 'Error: Expense ID not provided in the URL.', 'Missing Expense ID', array( 'back_link' => true ) );
            }
            $this->edit_expenses($id);
        }
    }

}
