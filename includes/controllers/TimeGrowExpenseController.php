<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowExpenseController{

    private $model;
    private $view;

    public function __construct(TimeGrowExpenseModel $model, TimeGrowExpenseView $view) {
        $this->model = $model;
        $this->view = $view;
    }

    public function handle_form_submission() {
        if (isset($_POST['submit_expense'])) {
            $data = [
                'expense_name' => sanitize_text_field($_POST['expense_name']),
                'amount' => floatval($_POST['amount']),
                'category' => sanitize_text_field($_POST['category']),
                'assigned_to' => sanitize_text_field($_POST['assigned_to']),
                'expense_description' => sanitize_textarea_field($_POST['expense_description']),
            ];

            $result = $this->model->create($data);

            if ($result) {
                echo '<div class="notice notice-success is-dismissible"><p>Expense added successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Error adding expense.</p></div>';
            }
        }
    }

    public function list_expenses() {
        $expenses = $this->model->select(null); // Fetch all expenses
        $this->view->display_expenses($expenses);
    }

    public function add_expenses() {
        global $wpdb;
        $clients = $wpdb->get_results("SELECT ID, name FROM {$wpdb->prefix}timeflies_clients ORDER BY name", ARRAY_A);
        $this->view->add_expense($clients);
    }

    public function edit_expenses($id) {
        global $wpdb;
        $clients = $wpdb->get_results("SELECT ID, name FROM {$wpdb->prefix}timeflies_clients ORDER BY name", ARRAY_A);
        $expense = $this->model->select($id); // Fetch all expenses
        $this->view->edit_expense($expense, $clients);
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
