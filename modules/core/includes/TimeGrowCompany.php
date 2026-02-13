<?php
// includes/companies.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class TimeGrowCompany{

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
   //     add_action('wp_ajax_save_expense', array($this, 'save_ajax'));
   //     add_action('wp_ajax_delete_expense', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('wp_ajax_save_company', array($this, 'save_ajax'));
        add_action('wp_ajax_delete_company', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles'));
    }


    public function register_admin_menu() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        add_submenu_page(
            TIMEGROW_PARENT_MENU,
            'Companies',
            'Companies',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-companies-list',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'list' ); // Call the tracker_mvc method, passing the parameter
            },
            'dashicons-money-alt',
        );

        add_submenu_page(
            null,
            'Add Company',
            'Add Company',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-company-add',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'add' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Edit Company',
            'Edit Company',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-company-edit',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'edit' ); // Call the tracker_mvc method, passing the parameter
            },
        );

    }

    public function enqueue_scripts_styles() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        wp_enqueue_style('timegrow-modern-style', TIMEGROW_CORE_BASE_URI . 'assets/css/timegrow-modern.css');
        wp_enqueue_style('timegrow-forms-style', TIMEGROW_CORE_BASE_URI . 'assets/css/forms.css');
        wp_enqueue_style('timeflies-companies-style', TIMEGROW_CORE_BASE_URI . 'assets/css/company.css');
        wp_enqueue_script('timeflies-companies-script', TIMEGROW_CORE_BASE_URI . 'assets/js/company.js', array('jquery'), '1.0', true);
        wp_localize_script(
            'timeflies-companies-script',
            'timeflies_companies_list',
            [
                'list_url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-companies-list'),
                'nonce' => wp_create_nonce('timeflies_company_nonce') // Pass the nonce to JS
            ]
        );
    }

    public function tracker_mvc_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $company_model = new TimeGrowCompanyModel();
        $company_view = new TimeGrowCompanyView();
        $controller = new TimeGrowCompanyController($company_model, $company_view);
        $controller->display_admin_page($screen);
    }
}
