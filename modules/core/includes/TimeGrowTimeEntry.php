<?php
// #### TimeGrowTimeEntry.php ####

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class TimeGrowTimeEntry{

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
            'Time Entries',
            'Time Entries',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-time-entries-list',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'list' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null, // Hidden submenu for editing
            'Add New Time',
            'Add New Time',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-time-entry-add',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'add' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null, // Hidden submenu for editing
            'Edit Time',
            'Edit Time',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-time-entry-edit',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'edit' ); // Call the tracker_mvc method, passing the parameter
            },
        );

    }

    public function enqueue_scripts_styles() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        wp_enqueue_style('timegrow-modern-style', TIMEGROW_CORE_BASE_URI . 'assets/css/timegrow-modern.css');
        wp_enqueue_style('timegrow-forms-style', TIMEGROW_CORE_BASE_URI . 'assets/css/forms.css');
        wp_enqueue_style('timeflies-time-entries-style', TIMEGROW_CORE_BASE_URI . 'assets/css/time_entry.css');
        wp_enqueue_script('timeflies-time-entries-script', TIMEGROW_CORE_BASE_URI . 'assets/js/time_entry.js', array('jquery'), '1.0', true);
        wp_localize_script(
            'timeflies-companies-script',
            'timeflies_companies_list',
            [
                'list_url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-time-entries-list'),
                'nonce' => wp_create_nonce('timeflies_timeentry_nonce') // Pass the nonce to JS
            ]
        );
    }

    public function tracker_mvc_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $model = new TimeGrowTimeEntryModel();
        $view = new TimeGrowTimeEntryView();
        $project_model = new TimeGrowProjectModel();
        $member_model = new TimeGrowTeamMemberModel();
        $controller = new TimeGrowTimeEntryController($model, $view, $project_model, $member_model);
        $controller->display_admin_page($screen);
    }
}
