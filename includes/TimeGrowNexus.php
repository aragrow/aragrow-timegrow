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
            try {
                print('<h2 class=""><p>Processing Time Entries</p></h2>');
                $time_entries = $model_entry->get_time_entries_to_bill();
                if (empty($time_entries)) {
                    $message_text = 'No time entries found.';
                    print('<h2 class="notice notice-warning"><p>' . $message_text . '</p></h2>');
                    exit;
                }

                $orders = $this->create_woo_orders_and_products($time_entries);
                if (empty($orders)) {
                    $message_text = 'No orders created.';
                    print('<h2 class="notice notice-warning"><p>' . $message_text . '</p></h2>')    ;
                    exit;   
                }

                $mark_time_entries_as_billed = $model_entry->get_time_entries_to_bill($time_entries);
                // print($orders);
                if (empty($mark_time_entries_as_billed)) {
                    $message_text = 'No time entries to mark as billed.';
                    print('<h2 class="notice notice-warning"><p>' . $message_text . '</p></h2>');
                    exit;
                }
                $model_entry->mark_time_entries_as_billed($mark_time_entries_as_billed);
                print('<br />Orders created successfully: <br />');
                foreach ($orders as $order_id) {
                    print('<br />Order ID: '.$order_id);

                }
                $message_text = 'Time entries processed successfully.';
                echo '<h2 class="notice notice-success"><p>' . $message_text . '</p></h2>';

                        
            } catch (Exception $e) {
                $message_text = 'Error initializing TimeGrowTimeEntryModel: ' . $e->getMessage();
                $message_text .= '<p>Error initializing time entries. Please check the logs.</p>';
                echo '<h2 class="notice notice-success"><p>' . $message_text . '</p></h2>';
                exit;
            }
        }


        $controller = new TimeGrowNexusController($model, $view_dashboard, $view_clock, $view_manual, $view_expense, $view_report, $projects, $reports, $list);
        $controller->display_admin_page($screen);
    }

    public function create_woo_orders_and_products($time_entries) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        if (empty($time_entries)) return [];
        // Group entries by project_id

        $order_ids = [];
        $model_project = new TimeGrowProjectModel();

        $entries_by_clients = [];
        foreach ($time_entries as $entry) {
            // print('<br />Entry...<br />');
            // print_r($entry);print('<br />');
            // print('Client Id: '. $entry->client_id);
            // print(' - Project Id: '. $entry->project_id);
            if (!isset($entries_by_clients[$entry->client_id])) {
                $entries_by_clients[$entry->client_id] = [];
            }
            if (!isset($entries_by_clients[$entry->client_id][$entry->project_id])) {
                $entries_by_clients[$entry->client_id][$entry->project_id] = [];
            }
            $entries_by_clients[$entry->client_id][$entry->project_id][] = $entry;
        }

      //  print('<br />Array of entries...<br />');
      //  print_r($entries_by_clients);

        print('<br />Creating WooCommerce Orders and Products for Manual Entries...<br />');

        foreach ($entries_by_clients as $client_id => $client_entries) {

            print('<br />---> ');print('Client Id: '.$client_id);

            $order = wc_get_orders([
                'customer_id' => $client_id,
                'status' => 'pending',
                'limit' => -1 // Get all orders
            ]);

            if (!empty($order)) {
                // If an order already exists, use it
                $order = $order[0]; // Get the first order
                print('<br />---> Order already exists for Client Id: '.$client_id);
            } else {
                // Create a new order
                print('<br />---> Creating new Order for Client Id: '.$client_id);
                $order = wc_create_order(
                    [
                    'customer_id' => $client_id,
                    'status' => 'pending',
                    'customer_note' => 'Time entries for client ID: ' . $client_id,
                    ]   
                );
                $order->add_meta_data('_timekeeping_invoice', true);
            }   



            foreach ($client_entries as $project_id => $entries) {
    
                //print_r($entries);
                $project = $model_project->select($project_id);
                if(!$project) {
                    error_log('Order creation failed: ' . 'Woo Commerce for Product for Project not found: '. $project_id);
                    continue;
                }
                //var_dump($project);
                $product_id = $project[0]->product_id;
                $woo_product = wc_get_product($product_id);


                $rate = $model_project->get_project_rate($project_id);
                $the_rate = $rate[0]->default_flat_fee ?? null;

                if (!$rate || bccomp($the_rate, '0.00', 2) === 0) {
                    $rate = $model_project->get_client_rate($client_id);
                    $the_rate = $rate[0]->default_flat_fee ?? null;
                }
                if (!$the_rate || bccomp($the_rate, '0.00', 2) === 0){
                    $rate = $model_project->get_company_rate(1);
                    $the_rate = $rate[0]->default_flat_fee ?? null;
                }
                if (!$the_rate || bccomp($the_rate, '0.00', 2) === 0){
                    $the_rate = 75;
                }

                $the_rate_10_min = $the_rate / 6; // Convert to 10 minute rate

                print("<br />---------> Project Rate: $the_rate\n");
                print("<br />---------> Project Rate (10 min): $the_rate_10_min\n");

                $product_manual_hours = 0;
                $product_clock_hours = 0;
                $product_hours = 0;
                $product_total = 0;

                foreach ($entries as $entry) {
                    if ($entry->entry_type != "MAN") continue;
                    if ($entry->billable != 1) continue;
                    if ($entry->billed != 0) continue;
                    print("<br />------> Project Type: $entry->entry_type, Project ID: $project_id, WOO Product ID: $product_id\n");
                    $project_id = (int) $entry->project_id;
                    $product_manual_hours += $entry->hours;
                    $work_done = $entry->description;
                
                } // End loop entries

                print('<br />---------> Manual Product Hours: '.$product_manual_hours);
        
                foreach ($entries as $entry) {
                    if ($entry->entry_type == "MAN") continue;
                    if (empty($entry->billable)) continue;
                    if (!empty($entry->billed)) continue;
                    print("<br />------> Project Type: $entry->entry_type, Project ID: $project_id, WOO Product ID: $product_id\n");
                    if ($entry->entry_type == 'IN') 
                        $clock_IN = $entry->clock_in_date;
                    else
                        $clock_OUT = $entry->clock_in_date;

                    if (!isset($clock_IN) || !isset($clock_OUT)) continue;

                    $project_id = (int) $entry->project_id;
                    $hours = abs($clock_OUT - $clock_IN)/3600;
                    $product_clock_hours += $hours;

                    $clock_IN = '';
                    $clock_OUT= ''; 
                    print("<br />------------------> Hours: $hours, Rate: $the_rate\n");
                
                } // End loop entries

                print('<br />---------> Clock In/Out Product Hours: '.$product_clock_hours);
                $product_hours = $product_manual_hours + $product_clock_hours;  

                $product_quantity = $this->hours_to_10min_units($product_hours);

                $product_total = round($product_quantity * $the_rate_10_min, 2);

                print('<br /> -------> Product Total Hours: '.$product_hours);
                print('<br /> -------> Product Total: '.$product_total);

                if (!$woo_product) {
                    $project = $model_project->select($project_id)[0];
                    // Product does not exist, create it
                    $product_name = $entry->display_name. ' - ' . $project->name;
                    $product = new WC_Product_Simple();
                    $product->set_name($product_name);
                    $product->set_regular_price($the_rate);
                    $product->set_virtual(true);
                    $product->set_category_ids([21]);
                    $product->set_catalog_visibility('hidden');
                    $product->save();
                    $product_id = $product->get_id();

                    $model_project->set_woo_product($project_id, $product_id);

                } else {
                    $product_name = $woo_product->get_name();
                }

                print('<br /> ---------> Product Name: '.$product_name);

                // Add as line item
                $item = new WC_Order_Item_Product();
                
                $item->set_product_id($product_id);
                $item->set_name($product_name);
                $item->set_quantity($product_quantity);
                $item->add_meta_data('_display_message', $product_hours . ' hrs in 10 minutes increments. At a rate of '. $the_rate. ' per hour.');
                $item->add_meta_data('_work_done', $work_done);

                $item->set_total($product_total);
                
                $order->add_item($item);

            } // End loop thru time for projects

            $order->calculate_totals();
            $order->update_status('processing');

            $order_ids[] = $order->get_id();

        } // End loop thru projects for clients
        
        return $order_ids;
    }

    public function get_open_order_for_client($client_id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
    }

    function hours_to_10min_units($hours) {
        $minutes = $hours * 60;
        return (int) ceil($minutes / 10);
    }

}