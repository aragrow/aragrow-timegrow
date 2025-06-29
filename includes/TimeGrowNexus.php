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
            'nexus4timegrow',
            TIMEGROW_PARENT_MENU . '-nexus',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'dashboard' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Clock',
            'Clock',
            'nexus4timegrow',
            TIMEGROW_PARENT_MENU . '-nexus-clock',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'clock' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Manual',
            'Manual',
            'nexus4timegrow',
            TIMEGROW_PARENT_MENU . '-nexus-manual',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'manual' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Expenses',
            'Expenses',
           'nexus4timegrow',
            TIMEGROW_PARENT_MENU . '-nexus-expenses',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'expenses' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Process Time',
            'Process Time',
           'nexus4timegrow',
            TIMEGROW_PARENT_MENU . '-nexus-process-time',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'process_time' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            __('Reports', 'timegrow'),         // Page title
            __('Reports', 'timegrow'),         // Menu title
            'nexus4timegrow',
            TIMEGROW_PARENT_MENU . '-nexus-reports', // Menu slug
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'reports' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        // Hidden page for viewing individual reports
        // This won't appear in the menu but provides a target for report links
        add_submenu_page(
            null,                              // No parent menu (hidden)
            __('View A Report', 'timegrow'),     // Page title (for browser tab)
            __('View A Report', 'timegrow'),     // Menu title (not shown)
            'nexus4timegrow',
            TIMEGROW_PARENT_MENU . '-nexus-a-report',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'areport' ); // Call the tracker_mvc method, passing the parameter
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
        $model = new TimeGrowTimeEntryModel();
        $model_entry = new TimeGrowTimeEntryModel();
        $view_dashboard = new TimeGrowNexusView();
        $view_clock = new TimeGrowNexusClockView();
        $view_manual = new TimeGrowNexusManualView();
        $view_expense = new TimeGrowNexusExpenseView();
        $view_report = new TimeGrowNexusReportView();
        $controller_reports = new TimeGrowReportsController($view_report);

        $projects = []; // Default to empty array
        $reports = []; // Default to empty array
        $list = []; // Default to empty array
    
        if ( $screen == 'clock' or $screen == 'manual' or $screen == 'expenses' ) {
            $team_member_model = new TimeGrowTeamMemberModel();
            if (current_user_can('administrator') ) {
                // User is an administrator
                $projects = $team_member_model->get_projects_for_member(-1); // -1 for admin means all projects
                $list = $model_entry->select(); // -1 for admin means all projects
            } else {
                // User is not an administrator, get projects for the current user
                $projects = $team_member_model->get_projects_for_member(get_current_user_id());
                $list = $model_entry->select(get_current_user_id()); // Get entries for the current user
            }
        } elseif ( $screen == 'reports' ) {
            $reports = $controller_reports->get_available_reports_for_user(wp_get_current_user()); 
        } elseif ($screen == 'process_time') {
            $time_entries = $model_entry->get_time_entries_to_bill();
            $orders = $this->create_woo_orders_and_products($time_entries);
            $mark_time_entries_as_billed = $model_entry->get_time_entries_to_bill($time_entries);
            print($orders);
            exit();
        }


        $controller = new TimeGrowNexusController($model, $view_dashboard, $view_clock, $view_manual, $view_expense, $view_report, $projects, $reports, $list);
        $controller->display_admin_page($screen);
    }

    public function create_woo_orders_and_products($time_entries) {
        if (empty($time_entries)) return [];

        // Group entries by project_id
        $entries_by_project = [];
        foreach ($time_entries as $entry) {
            if (!isset($entries_by_project[$entry['project_id']])) {
                $entries_by_project[$entry['project_id']] = [];
            }
            $entries_by_project[$entry['project_id']][] = $entry;
        }

        $order_ids = [];

        foreach ($entries_by_project as $project_id => $entries) {
            $order = wc_create_order(['customer_id' => $user_id]);

            foreach ($entries as $entry) {
                if (!$entry['billable']) continue;

                $hours = (float) $entry['hours'];
                $rate = self::get_project_rate($project_id); // You can customize this
                $total = round($hours * $rate, 2);

                // Create virtual product on the fly (no need to persist it)
                $product = new WC_Product();
                $product->set_name("{$hours}h - {$entry['description']}");
                $product->set_regular_price($total);
                $product->set_virtual(true);
                $product->set_catalog_visibility('hidden');
                $product->save();

                // Add as line item
                $item = new WC_Order_Item_Product();
                $item->set_product($product);
                $item->set_quantity(1);
                $item->set_total($total);
                $order->add_item($item);
            }

            $order->calculate_totals();
            $order->update_status('processing');

            $order_ids[] = $order->get_id();

            // Optionally update billed status
            self::mark_entries_as_billed($entries);
        }
    
        if (is_wp_error($order_id)) {
            error_log('Order creation failed: ' . $order_id->get_error_message());
        } else {
            error_log('Order created successfully: ID ' . $order_id);
        }
        
        return $order_ids;
    }



}