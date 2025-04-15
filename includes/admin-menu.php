<?php
// includes/admin-menu.php

if (!defined('ABSPATH')) {
    exit;
}

class TimeFlies_Admin_Menu {
    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', array($this, 'register_admin_menu'));
    }

    public function register_admin_menu() {
        add_menu_page(
            'TimeGrow',
            'TimeGrow',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU,
            array($this, 'dashboard_page'),
            'dashicons-clock',
            25
        );

        add_submenu_page(
            TIMEGROW_PARENT_MENU, // Parent menu slug (adjust if different)
            'Integrations',
            'Integrations',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU.'-integrations',
            array($this, 'render_integration_page')
        );
        
    }
    public function dashboard_page() {
        echo '<h2>Time Flies Dashboard</h2>';
    }

    public function render_integration_page() {
        require_once ARAGROW_TIMEGROW_SCREENS_DIR . 'integration-page.php';
    }

    public function expenses_list_page() {
        require_once ARAGROW_TIMEGROW_SCREENS_DIR . 'expenses-list.php';
    }

    public function expense_add_page() {
        require_once ARAGROW_TIMEGROW_SCREENS_DIR . 'expense-add.php';
    }

    public function expense_edit_page() {
        require_once ARAGROW_TIMEGROW_SCREENS_DIR . 'expense-edit.php';
    }

    // ... other page callbacks
}

TimeFlies_Admin_Menu::get_instance();