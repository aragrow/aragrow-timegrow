<?php
// includes/companies.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class TimeGrowExpense {

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
   //     add_action('wp_ajax_save_expense', array($this, 'save_ajax'));
   //     add_action('wp_ajax_delete_expense', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts_styles']);
    }


    public function register_admin_menu() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
       
        add_submenu_page(
            TIMEGROW_PARENT_MENU,
            'Expenses',
            'Expenses',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-expenses-list',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'list' ); // Call the tracker_mvc method, passing the parameter
            },
            'dashicons-money-alt',
        );

        add_submenu_page(
            null,
            'Add Expenses',
            'Add Expenses',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-expense-add',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'add' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Edit Expenses',
            'Edit Expenses',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-expense-edit',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'edit' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Delete Receipt',
            'Delete Receipt',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-expense-receipt-delete',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'receipt-delete' ); // Call the tracker_mvc method, passing the parameter
            },
        );
    }

    public function enqueue_scripts_styles() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        wp_enqueue_style('timeflies-expenses-style', ARAGROW_TIMEGROW_BASE_URI . 'assets/css/expense.css');
        wp_enqueue_script('timeflies-expenses-script', ARAGROW_TIMEGROW_BASE_URI . 'assets/js/expense.js', array('jquery'), '1.0', true);
        wp_localize_script(
            'timeflies-expenses-script',
            'timeflies_expenses_list',
            [
                'list_url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expenses-list'),
                'nonce' => wp_create_nonce('timeflies_expense_nonce') // Pass the nonce to JS
            ]
        );
    }

    public function tracker_mvc_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $expense_model = new TimeGrowExpenseModel();
        $receipt_model = new TimeGrowExpenseReceiptModel();
        $client_model = new TimeGrowClientModel();
        $project_model = new TimeGrowProjectModel();
        $expense_view = new TimeGrowExpenseView();
        $controller = new TimeGrowExpenseController($expense_model, $receipt_model, $client_model, $project_model, $expense_view);
        $controller->display_admin_page($screen);
    }
}
