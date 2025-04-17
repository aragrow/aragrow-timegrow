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

       
        
    }
    public function dashboard_page() {
        echo '<h2>Time Flies Dashboard</h2>';
    }

    // ... other page callbacks
}

TimeFlies_Admin_Menu::get_instance();