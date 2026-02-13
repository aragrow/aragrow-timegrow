<?php
// includes/companies.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class TimeGrowTeamMember{

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
            'Team Members',
            'Team Members',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-team-member-list',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'list' ); // Call the tracker_mvc method, passing the parameter
            },
            'dashicons-money-alt',
        );

        add_submenu_page(
            null,
            'Add Team Member',
            'Add Team Member',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-team-member-add',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'add' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Edit Team Member',
            'Edit Team Member',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-team-member-edit',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'edit' ); // Call the tracker_mvc method, passing the parameter
            },
        );

    }

    public function enqueue_scripts_styles() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // jQuery UI core and sortable
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_script('jquery-ui-draggable');

        wp_enqueue_style('timegrow-modern-style', TIMEGROW_CORE_BASE_URI . 'assets/css/timegrow-modern.css');
        wp_enqueue_style('timegrow-forms-style', TIMEGROW_CORE_BASE_URI . 'assets/css/forms.css');
        wp_enqueue_style('timegrow-companies-style', TIMEGROW_CORE_BASE_URI . 'assets/css/team_member.css');
        wp_enqueue_script('timegrow-companiues-script', TIMEGROW_CORE_BASE_URI . 'assets/js/team_member.js', array('jquery'), '1.0', true);
        wp_localize_script(
            'timegrow-team-member-script',
            'timegrow_team_member_list',
            [
                'list_url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-team-member-list'),
                'nonce' => wp_create_nonce('timegrow_team_member_nonce') // Pass the nonce to JS
            ]
        );
    }

    public function tracker_mvc_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $model = new TimeGrowTeamMemberModel();
        $view = new TimeGrowTeamMemberView();
        $model_user = new TimeGrowUserModel;
        $model_company = new TimeGrowCompanyModel;
        $model_project = new TimeGrowProjectModel; 
        $controller = new TimeGrowTeamMemberController($model, $view, $model_user, $model_company, $model_project);
        $controller->display_admin_page($screen);
    }
}
