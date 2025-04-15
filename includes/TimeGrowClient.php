<?php
// includes/companies.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class TimeGrowClient{

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
   //     add_action('wp_ajax_save_expense', array($this, 'save_ajax'));
   //     add_action('wp_ajax_delete_expense', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('wp_ajax_save_client', array($this, 'save_ajax'));
        add_action('wp_ajax_delete_client', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles'));
    }


    public function register_admin_menu() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        add_submenu_page(
            TIMEGROW_PARENT_MENU,
            'Clients',
            'Clients',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-clients-list',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'list' ); // Call the tracker_mvc method, passing the parameter
            },
            'dashicons-money-alt',
        );

        add_submenu_page(
            null,
            'Add Client',
            'Add Client',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-client-add',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'add' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Edit Client',
            'Edit Client',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-client-edit',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'edit' ); // Call the tracker_mvc method, passing the parameter
            },
        );

    }

    public function enqueue_scripts_styles() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        wp_enqueue_style('timeflies-clients-style', ARAGROW_TIMEGROW_BASE_URI . 'assets/css/company.css');
        wp_enqueue_script('timeflies-clients-script', ARAGROW_TIMEGROW_BASE_URI . 'assets/js/company.js', array('jquery'), '1.0', true);
        wp_localize_script(
            'timegrow-clients-script',
            'timegrow_clients_list',
            [
                'list_url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-clients-list'),
                'nonce' => wp_create_nonce('timegrow_client_nonce') // Pass the nonce to JS
            ]
        );
    }

    public function tracker_mvc_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $model = new TimeGrowClientModel();
        $view = new TimeGrowClientView();
        $controller = new TimeGrowCompanyController($model, $view);
        $controller->display_admin_page($screen);
    }
}
