<?php
// #### TimeGrowNexus.php ####

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class TimeGrowNexus{

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
   //     add_action('wp_ajax_save_expense', array($this, 'save_ajax'));
   //     add_action('wp_ajax_delete_expense', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('wp_ajax_save_nexus', array($this, 'save_ajax'));
        add_action('wp_ajax_delete_nexus', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles'));

    }


    public function register_admin_menu() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        add_submenu_page(
            TIMEGROW_PARENT_MENU,
            'Nexus',
            'Nexus',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-nexus',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'dashboard' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Clock',
            'Clock',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-nexus-clock',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'clock' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Manual',
            'Manual',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-nexus-manual',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'manual' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Expenses',
            'Expenses',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-nexus-expenses',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'expenses' ); // Call the tracker_mvc method, passing the parameter
            },
        );

    }

    public function enqueue_scripts_styles($hook) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        print_r($hook);
        $plugin_version = '1.0.0'; // Define appropriately
        if ($hook == "admin_page_timegrow-nexus-clock") {

            wp_enqueue_script(
                'timegrow-nexus-clock-script', // New handle, matches wp_localize_script
                ARAGROW_TIMEGROW_BASE_URI . 'assets/js/clock.js', // Path to your new JS file
                [], // No React dependencies needed for vanilla JS
                $plugin_version,
                true // Load in footer
            );
            wp_enqueue_style('timeflies-nexus-project-bc-style', ARAGROW_TIMEGROW_BASE_URI . 'assets/css/nexus_project_bc.css');
            // CSS remains the same, as the class names in HTML are similar
            wp_enqueue_style(
                'timegrow-clock-style',
                ARAGROW_TIMEGROW_BASE_URI . 'assets/css/clock.css',
                [],
                $plugin_version
            );
            wp_localize_script(
                    'timegrow-nexus-clock-script',
                    'timegrow_nexus_list',
                    [
                        'list_url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus'),
                        'nonce' => wp_create_nonce('timegrow_nexus_nonce') // Pass the nonce to JS
                    ]
                );

        } elseif ($hook == "admin_page_timegrow-nexus-manual") {
            
            wp_enqueue_script(
                'timegrow-nexus-manual-script', // New handle, matches wp_localize_script
                ARAGROW_TIMEGROW_BASE_URI . 'assets/js/manual.js', // Path to your new JS file
                [], // No React dependencies needed for vanilla JS
                $plugin_version,
                true // Load in footer
            );
            wp_enqueue_style('timeflies-nexus-client-bc-style', ARAGROW_TIMEGROW_BASE_URI . 'assets/css/nexus_project_bc.css');
            wp_localize_script(
                'timegrow-nexus-manual-script',
                'timegrow_nexus_list',
                [
                    'list_url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus'),
                    'nonce' => wp_create_nonce('timegrow_nexus_nonce') // Pass the nonce to JS
                ]
            );
             // CSS remains the same, as the class names in HTML are similar
           
            wp_enqueue_style(
                'timegrow-clock-style',
                ARAGROW_TIMEGROW_BASE_URI . 'assets/css/manual.css',
                [],
                $plugin_version
            );

        } elseif ($hook == "admin_page_timegrow-nexus-expenses") {
            wp_enqueue_script(
                'timegrow-nexus-expense-script', // New handle, matches wp_localize_script
                ARAGROW_TIMEGROW_BASE_URI . 'assets/js/expense-recorder.js', // Path to your new JS file
                [], // No React dependencies needed for vanilla JS
                $plugin_version,
                true // Load in footer
            );
            wp_enqueue_style('timeflies-nexus-project-bc-style', ARAGROW_TIMEGROW_BASE_URI . 'assets/css/nexus_project_bc.css');

            wp_localize_script(
                'timegrow-nexus-expense-script',
                'timegrow_nexus_list',
                [
                    'list_url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus'),
                    'nonce' => wp_create_nonce('timegrow_nexus_nonce') // Pass the nonce to JS
                ]
            );
             // CSS remains the same, as the class names in HTML are similar
           
            wp_enqueue_style(
                'timegrow-expense-style',
                ARAGROW_TIMEGROW_BASE_URI . 'assets/css/expense.css',
                [],
                $plugin_version
            );
          
        } elseif ($hook == "admin_page_timegrow-nexus-reports") {
        } elseif ($hook == "admin_page_timegrow-nexus-settings") {
        } else { // Dashboard

            wp_enqueue_style('timeflies-nexus-style', ARAGROW_TIMEGROW_BASE_URI . 'assets/css/nexus_dashboard.css');
            wp_enqueue_script('timeflies-nexus-script', ARAGROW_TIMEGROW_BASE_URI . 'assets/js/nexus_dashboard.js', array('jquery'), '1.0', true);
        
            wp_localize_script(
                'timegrow-nexus-script',
                'timegrow_nexus_list',
                [
                    'list_url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus'),
                    'nonce' => wp_create_nonce('timegrow_nexus_nonce') // Pass the nonce to JS
                ]
            );
        }
        

    }


    public function tracker_mvc_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $view_dashboard = new TimeGrowNexusView();
        $view_clock = new TimeGrowNexusClockView();
        $view_manual = new TimeGrowNexusManualView();
        $view_expense = new TimeGrowNexusExpenseView();
      
        if ( $screen == 'clock' or $screen == 'manual' or $screen == 'expenses' ) {
            $team_member_model = new TimeGrowTeamMemberModel();
            if (current_user_can('administrator') ) {
                // User is an administrator
                $projects = $team_member_model->get_projects_for_member(-1); // -1 for admin means all projects
            } else {
                // User is not an administrator, get projects for the current user
                $projects = $team_member_model->get_projects_for_member(get_current_user_id());
            }
        } else $projects = []; // Default to empty array for other screens

        $controller = new TimeGrowNexusController($view_dashboard, $view_clock, $view_manual, $view_expense, $projects);
        $controller->display_admin_page($screen);
    }
}
