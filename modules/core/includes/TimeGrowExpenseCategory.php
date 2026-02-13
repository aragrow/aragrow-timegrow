<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowExpenseCategory {

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        add_action('admin_menu', [$this, 'register_admin_menu']);
    }

    public function register_admin_menu() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        add_submenu_page(
            TIMEGROW_PARENT_MENU,
            'Expense Categories',
            'Expense Categories',
            'manage_expense_categories', // Use custom capability
            TIMEGROW_PARENT_MENU . '-expense-categories',
            function() {
                $this->tracker_mvc_admin_page('list');
            }
        );

        add_submenu_page(
            null, // Hidden from menu
            'Edit Expense Category',
            'Edit Expense Category',
            'manage_expense_categories',
            TIMEGROW_PARENT_MENU . '-expense-category-edit',
            function() {
                $this->tracker_mvc_admin_page('edit');
            }
        );
    }

    public function tracker_mvc_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Initialize model and create table if needed
        $category_model = new TimeGrowExpenseCategoryModel();
        $category_model->initialize();

        $category_view = new TimeGrowExpenseCategoryView();
        $controller = new TimeGrowExpenseCategoryController($category_model, $category_view);

        // Display admin notices
        $notices = get_transient('timegrow_expense_category_notices');
        if ($notices) {
            delete_transient('timegrow_expense_category_notices');
            foreach ($notices as $notice) {
                echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible"><p>' . esc_html($notice['message']) . '</p></div>';
            }
        }

        $controller->display_admin_page($screen);
    }
}
