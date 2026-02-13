<?php
// includes/companies.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class TimeGrowReport {

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
   //     add_action('wp_ajax_save_expense', array($this, 'save_ajax'));
   //     add_action('wp_ajax_delete_expense', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts_styles']);
    }


    public function register_admin_menu() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Redirect old reports menu to nexus reports page
        add_submenu_page(
            TIMEGROW_PARENT_MENU,
            'Reports',
            'Reports',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-reports-list',
            function() { // Define a closure
                // Redirect to nexus reports page
                wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-reports'));
                exit;
            },
            'dashicons-money-alt',
        );

        add_submenu_page(
            null,
            'A Report',
            'A Report',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-reports',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'report' ); // Call the tracker_mvc method, passing the parameter
            },
        );

    }

    public function enqueue_scripts_styles($hook) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__ . ' - Hook: ' . $hook);

        // Only enqueue on the individual report page (timegrow-reports)
        $report_pages = [
            'admin_page_' . TIMEGROW_PARENT_MENU . '-reports',
        ];

        if (!in_array($hook, $report_pages)) {
            return; // Exit early if not on report pages
        }

        wp_enqueue_style('timeflies-expenses-style', ARAGROW_TIMEGROW_BASE_URI . 'assets/css/reports.css');
        wp_enqueue_script('timeflies-expenses-script', ARAGROW_TIMEGROW_BASE_URI . 'assets/js/reports.js', array('jquery'), '1.0', true);
        wp_localize_script(
            'timeflies-reports-script',
            'timeflies_reports_list',
            [
                'list_url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-reports-list'),
                'nonce' => wp_create_nonce('timeflies_reports_nonce') // Pass the nonce to JS
            ]
        );
    }

    public function tracker_mvc_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $report_view = new TimeGrowReportView();
        $controller = new TimeGrowReportController($report_view);
        $controller->display_admin_page($screen);
    }
}
