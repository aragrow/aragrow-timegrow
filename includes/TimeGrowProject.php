<?php
// includes/companies.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class TimeGrowProject{

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
   //     add_action('wp_ajax_save_expense', array($this, 'save_ajax'));
   //     add_action('wp_ajax_delete_expense', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('wp_ajax_save_project', array($this, 'save_ajax'));
        add_action('wp_ajax_delete_project', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles'));
    }


    public function register_admin_menu() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        add_submenu_page(
            TIMEGROW_PARENT_MENU,
            'Projects',
            'Projects',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-projects-list',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'list' ); // Call the tracker_mvc method, passing the parameter
            },
            'dashicons-money-alt',
        );

        add_submenu_page(
            null,
            'Add Project',
            'Add Project',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-project-add',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'add' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Edit Project',
            'Edit Project',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-project-edit',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'edit' ); // Call the tracker_mvc method, passing the parameter
            },
        );

    }

    public function enqueue_scripts_styles() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-slider');
        wp_enqueue_style(
            'jquery-ui-css',
            'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css'
        );
        wp_enqueue_style('timegrow-projects-style', ARAGROW_TIMEGROW_BASE_URI . 'assets/css/project.css');
        wp_enqueue_script('timegrow-projects-script', ARAGROW_TIMEGROW_BASE_URI . 'assets/js/project.js', array('jquery'), '1.0', true);
        wp_localize_script(
            'timegrow-projects-script',
            'timegrow_projectss_list',
            [
                'list_url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-projects-list'),
                'nonce' => wp_create_nonce('timegrow_project_nonce') // Pass the nonce to JS
            ]
        );
    }

    public function tracker_mvc_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $model = new TimeGrowProjectModel();
        $view = new TimeGrowProjectView();
        $model_wc_client = new TimeGrowClientModel();
        $model_wc_product = new TimeGrowWcProductModel();
        $controller = new TimeGrowProjectController($model, $view, $model_wc_client,  $model_wc_product);
        $controller->display_admin_page($screen);
    }
}
