<?php
// #### TimeGrowNexus.php ####

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class TimeGrowNexus{

    public function __construct() {
        if(WP_DEBUG) {
            error_log(__CLASS__.'::'.__FUNCTION__);
            error_log('Registering admin_enqueue_scripts hook');
        }
   //     add_action('wp_ajax_save_expense', array($this, 'save_ajax'));
   //     add_action('wp_ajax_delete_expense', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_menu', [$this, 'register_admin_menu'], 20);
        add_action('wp_ajax_delete_nexus', array($this, 'delete_ajax')); // Add delete action
        add_action('wp_ajax_save_time_entry', array($this, 'save_time_entry'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles'));

        if(WP_DEBUG) {
            error_log('admin_enqueue_scripts hook registered');
        }
    }


    public function register_admin_menu() {

        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        add_submenu_page(
            TIMEGROW_PARENT_MENU,
            'Nexus Dashboard',
            'Nexus Dashboard',
            'access_nexus_dashboard',
            TIMEGROW_PARENT_MENU . '-nexus',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'dashboard' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Clock',
            'Clock',
            'access_nexus_clock',
            TIMEGROW_PARENT_MENU . '-nexus-clock',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'clock' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Manual',
            'Manual',
            'access_nexus_manual_entry',
            TIMEGROW_PARENT_MENU . '-nexus-manual',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'manual' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Expenses',
            'Expenses',
            'access_nexus_record_expenses',
            TIMEGROW_PARENT_MENU . '-nexus-expenses',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'expenses' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Process Time',
            'Process Time',
           'access_nexus_process_time',
            TIMEGROW_PARENT_MENU . '-nexus-process-time',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'process_time' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            __('Reports', 'timegrow'),         // Page title
            __('Reports', 'timegrow'),         // Menu title
            'access_nexus_view_reports',
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
            'access_nexus_view_reports',
            TIMEGROW_PARENT_MENU . '-nexus-a-report',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'areport' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Settings',
            'Settings',
            'access_nexus_settings',
            TIMEGROW_PARENT_MENU . '-nexus-settings',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'settings' ); // Call the tracker_mvc method, passing the parameter
            },
        );

    }

    public function save_time_entry() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);   
        
        try {            
            $entry_type    = isset($_POST['entry_type']) ? sanitize_text_field($_POST['entry_type']) : '';
        
            if($entry_type == 'CLOCK_IN') $result = $this->save_clock_in();
            elseif($entry_type == 'CLOCK_OUT') $result = $this->save_clock_out();
            elseif($entry_type == 'MAN') $result = $this->save_manual();
            else {
                wp_send_json_error(['message' => 'Invalid entry type']);
                wp_die();
            }
            return $result;

        } catch (Exception $e) {
            error_log('Clock In Error: ' . $e->getMessage());
            echo '<div class="notice notice-error is-dismissible"><p>Error: ' . esc_html($e->getMessage()) . '</p></div>';

            return false;
        }

    }

 public function save_manual() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        try {
            error_log('$_POST[]');
            error_log(print_r($_POST,true));
            $time_entry_id = isset($_POST['time_entry_id']) ? intval($_POST['time_entry_id']) : 0;
            $project_id     = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
            $member_id     = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
            $entry_type    = isset($_POST['entry_type']) ? sanitize_text_field($_POST['entry_type']) : '';
            $nonce         = isset($_POST['timegrow_time_nexus_nonce_field']) ? sanitize_text_field($_POST['timegrow_time_nexus_nonce_field']) : '';
            $referer       = isset($_POST['_wp_http_referer']) ? esc_url_raw($_POST['_wp_http_referer']) : '';
            $date          =  isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
            $hours         = isset($_POST['hours']) ? floatval($_POST['hours']) : 0;
            $description  = isset($_POST['description']) ? sanitize_text_field($_POST['description']) : '';
            $isBillable  = isset($_POST['billable']) ? intval($_POST['billable']) : 0;
            if (
                !isset($nonce) ||
                !wp_verify_nonce($nonce, 'timegrow_time_nexus_nonce')
            ) {
                wp_send_json_error(['message' => 'Nonce verification failed: '.$nonce]);
                wp_die();
            }
            if ($referer && strpos($referer, '/wp-admin/admin.php?page=timegrow-nexus-manual') === false) {
                wp_send_json_error(['message' => 'Invalid referer']);
                wp_die();
            }
            if (!isset($time_entry_id)) {
                wp_send_json_error(['message' => 'Invalid time entry']);
                wp_die();
            } 
            if (!isset($member_id)) {
                wp_send_json_error(['message' => 'Invalid member id']);
                wp_die();
            }  
            if (!isset($entry_type)) {
                wp_send_json_error(['message' => 'Invalid entry type']);
                wp_die();
            } 
            if ($date 
                && (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)))
                {
                // $clock_date is sanitized and valid
            } else {
                wp_send_json_error(['message' => 'Invalid Date: '.$date]);
                wp_die();
            }
            error_log('Verification Passed');

            $entry_model = new TimeGrowTimeEntryModel();

            $current_date = current_time('mysql');
            $data = [
                'project_id'      => $project_id,
                'member_id'       => $member_id,
                'billable'        => $isBillable,
                'billed'          => 0,
                'description'     => $description,
                'entry_type'      => 'MAN',
                'date'            => $date,
                'hours'           => $hours, 
                'description'     => $description,
                'updated_at'      => $current_date,
                'created_at'      => $current_date
            ];
        
            $format = [
                '%d',   // project_id (integer)
                '%d',   // member_id (integer)
                '%d',   // billable ( boolean as integer 1/0)
                '%d',   // billed ( boolean as integer 1/0)
                '%s',   // description (string)
                '%s',   // entry_type (string)
                '%s',   // date (string)
                '%f',   // hours (float)
                '%s',   // updated_at (datetime string)
                '%s',   // descripiton (string )
                '%s',   // create_at (string)
                '%s',   // updated_at (string)
            ];

            $id = $entry_model->create($data, $format);

            error_log("ID processed: {$id}");

            echo "<div class='notice notice-success is-dismissible'><p>{entry_type} added successfully!</p></div>";

        } catch (Exception $e) {
            wp_send_json_error('Manual Error: ' . $e->getMessage());
            wp_die();
        }
    }
    
    public function save_clock_in() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        try {
            error_log('$_POST[]');
            error_log(print_r($_POST,true));
            $time_entry_id = isset($_POST['time_entry_id']) ? intval($_POST['time_entry_id']) : 0;
            $project_id     = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
            $member_id     = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
            $entry_type    = isset($_POST['entry_type']) ? sanitize_text_field($_POST['entry_type']) : '';
            $nonce         = isset($_POST['timegrow_time_nexus_nonce_field']) ? sanitize_text_field($_POST['timegrow_time_nexus_nonce_field']) : '';
            $referer       = isset($_POST['_wp_http_referer']) ? esc_url_raw($_POST['_wp_http_referer']) : '';
            $clock_time    = isset($_POST['clock_time']) ? sanitize_text_field($_POST['clock_time']) : '';

            if (
                !isset($nonce) ||
                !wp_verify_nonce($nonce, 'timegrow_time_nexus_nonce')
            ) {
                wp_send_json_error(['message' => 'Nonce verification failed: '.$nonce]);
                wp_die();
            }
            if ($referer && strpos($referer, '/wp-admin/admin.php?page=timegrow-nexus-clock') === false) {
                wp_send_json_error(['message' => 'Invalid referer']);
                wp_die();
            }
            if (!isset($time_entry_id)) {
                wp_send_json_error(['message' => 'Invalid time entry']);
                wp_die();
            } 
            if (!isset($member_id)) {
                wp_send_json_error(['message' => 'Invalid member id']);
                wp_die();
            }  
            if (!isset($entry_type)) {
                wp_send_json_error(['message' => 'Invalid entry type']);
                wp_die();
            } 
            if ($clock_time 
                && (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $clock_time)
                    || preg_match('/^\d{4}-\d{2}-\d{2} (0[1-9]|1[0-2]):[0-5]\d:[0-5]\d (AM|PM)$/i', $clock_time))
                ) {
                // $clock_date is sanitized and valid
            } else {
                wp_send_json_error(['message' => 'Invalid Clock Date: '.$clock_time]);
                wp_die();
            }
            error_log('Verification Passed');

            $date_obj = DateTime::createFromFormat('Y-m-d h:i:s A', $clock_time);
            if ($date_obj) {
                $clock_time = $date_obj->format('Y-m-d H:i:s'); // "2025-08-20 16:50:34"
            }

            $entry_model = new TimeGrowTimeEntryModel();
            $project_model = new TimeGrowProjectModel();
            $isBillable = $project_model->is_billable($project_id);
            $isBillable =  intval($isBillable);

            $current_date = current_time('mysql');
            $data = [
                'project_id'      => $project_id,
                'member_id'       => $member_id,
                'billable'        => $isBillable,
                'billed'          => 0,
                'description'     => 'Entry is a Clocked Time ',
                'entry_type'      => 'CLOCK',
                'updated_at'      => $current_date
            ];
        
            $data['hours'] = null;

            $data['clock_in_date'] = $clock_time;
            

            $format = [
                '%d',   // project_id (integer)
                '%d',   // member_id (integer)
                '%d',   // billable ( boolean as integer 1/0)
                '%d',   // billed ( boolean as integer 1/0)
                '%s',   // description (string)
                '%s',   // entry_type (string)
                '%s',   // updated_at (datetime string)
                '%f',   // hours (float)
                '%s',   // clock_in_date (string)
            ];

            $id = 0;
            if ($time_entry_id == 0) {
                error_log('Adding Clock In.');
                $data['created_at'] = $current_date;
                $format[] = '%s';
                $id = $entry_model->create($data, $format);
            } else

            error_log("ID processed: {$id}");

            echo "<div class='notice notice-success is-dismissible'><p>{entry_type} added successfully!</p></div>";

        } catch (Exception $e) {
            wp_send_json_error('Clock In Error: ' . $e->getMessage());
            wp_die();
        }
    }

    public function save_clock_out() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        try {
            error_log('$_POST[]');
            error_log(print_r($_POST,true));
            $time_entry_id = isset($_POST['time_entry_id']) ? intval($_POST['time_entry_id']) : 0;
            $entry_type    = isset($_POST['entry_type']) ? sanitize_text_field($_POST['entry_type']) : '';
            $nonce         = isset($_POST['timegrow_time_nexus_nonce_field']) ? sanitize_text_field($_POST['timegrow_time_nexus_nonce_field']) : '';
            $referer       = isset($_POST['_wp_http_referer']) ? esc_url_raw($_POST['_wp_http_referer']) : '';
            $clock_time    = isset($_POST['clock_time']) ? sanitize_text_field($_POST['clock_time']) : '';

            if (
                !isset($nonce) ||
                !wp_verify_nonce($nonce, 'timegrow_time_nexus_nonce')
            ) {
                wp_send_json_error(['message' => 'Nonce verification failed: '.$nonce]);
                wp_die();
            }
            if ($referer && strpos($referer, '/wp-admin/admin.php?page=timegrow-nexus-clock') === false) {
                wp_send_json_error(['message' => 'Invalid referer']);
                wp_die();
            }
            if (!isset($time_entry_id)) {
                wp_send_json_error(['message' => 'Invalid time entry']);
                wp_die();
            } 
            if (!isset($entry_type)) {
                wp_send_json_error(['message' => 'Invalid entry type']);
                wp_die();
            } 
            if ($clock_time 
                && (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $clock_time)
                    || preg_match('/^\d{4}-\d{2}-\d{2} (0[1-9]|1[0-2]):[0-5]\d:[0-5]\d (AM|PM)$/i', $clock_time))
                ) {
                // $clock_date is sanitized and valid
            } else {
                wp_send_json_error(['message' => 'Invalid Clock Date: '.$clock_time]);
                wp_die();
            }
            error_log('Verification Passed');

            $date_obj = DateTime::createFromFormat('Y-m-d h:i:s A', $clock_time);
            if ($date_obj) {
                $clock_time = $date_obj->format('Y-m-d H:i:s'); // "2025-08-20 16:50:34"
            }

            $entry_model = new TimeGrowTimeEntryModel();

            $current_date = current_time('mysql');
            $data = [
                'entry_type'      => 'CLOCK',
                'clock_out_date'  => $clock_time,
                'updated_at'      => $current_date
            ];

            $format = [
                '%s',   // entry_type (datetime string)
                '%s',   // updated_at (datetime string)
                '%s'   // clock_out_date (string)
            ];

            error_log('Updating Clock Out.');
            error_log(print_r($data, true));
            $id = $entry_model->update($time_entry_id, $data, $format);
   
            error_log("ID processed: {$id}");

            echo "<div class='notice notice-success is-dismissible'><p>{entry_type} added successfully!</p></div>";

        } catch (Exception $e) {

            wp_send_json_error('Clock Out Error: ' . $e->getMessage());
                wp_die();

        }
    }

    public function enqueue_scripts_styles($hook) {
        if(WP_DEBUG) {
            error_log(__CLASS__.'::'.__FUNCTION__);
            error_log('Hook: ' . $hook);
            error_log('TIMEGROW_NEXUS_BASE_URI: ' . TIMEGROW_NEXUS_BASE_URI);
            error_log('TIMEGROW_CORE_BASE_URI: ' . TIMEGROW_CORE_BASE_URI);
        }
        $plugin_version = '1.0.0'; // Define appropriately

        // Always enqueue modern style for all Nexus pages (shared from core module)
        if (strpos($hook, 'timegrow-nexus') !== false || strpos($hook, 'timegrow_page_timegrow-nexus') !== false) {
            wp_enqueue_style('timegrow-modern-style', TIMEGROW_CORE_BASE_URI . 'assets/css/timegrow-modern.css', [], $plugin_version);
            if(WP_DEBUG) error_log('Enqueued timegrow-modern.css for hook: ' . $hook);
        }

        if ($hook == "admin_page_timegrow-nexus-clock" || $hook == "timegrow_page_timegrow-nexus-clock") {

            wp_enqueue_script(
                'timegrow-nexus-clock', // New handle, matches wp_localize_script
                TIMEGROW_NEXUS_BASE_URI . 'assets/js/clock.js', // Path to your new JS file
                [], // No React dependencies needed for vanilla JS
                $plugin_version,
                true // Load in footer
            );
            wp_enqueue_style('timeflies-nexus-project-bc-style', TIMEGROW_NEXUS_BASE_URI . 'assets/css/nexus_project_bc.css', [], $plugin_version);
            // CSS remains the same, as the class names in HTML are similar
            wp_enqueue_style(
                'timegrow-clock-style',
                TIMEGROW_NEXUS_BASE_URI . 'assets/css/clock.css',
                [],
                $plugin_version
            );
            wp_localize_script(
                    'timegrow-nexus-clock',
                    'timegrow_nexus_list',
                    [
                        'list_url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus'),
                        'nonce' => wp_create_nonce('timegrow_nexus_nonce') // Pass the nonce to JS
                    ]
                );

        } elseif ($hook == "admin_page_timegrow-nexus-manual" || $hook == "timegrow_page_timegrow-nexus-manual") {

            wp_enqueue_script(
                'timegrow-nexus-manual', // New handle, matches wp_localize_script
                TIMEGROW_NEXUS_BASE_URI . 'assets/js/manual.js', // Path to your new JS file
                [], // No React dependencies needed for vanilla JS
                $plugin_version,
                true // Load in footer
            );
            wp_enqueue_style('timeflies-nexus-client-bc-style', TIMEGROW_NEXUS_BASE_URI . 'assets/css/nexus_project_bc.css', [], $plugin_version);
            wp_localize_script(
                'timegrow-nexus-manual',
                'timegrow_nexus_list',
                [
                    'list_url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus'),
                    'nonce' => wp_create_nonce('timegrow_nexus_nonce') // Pass the nonce to JS
                ]
            );
             // CSS remains the same, as the class names in HTML are similar

            wp_enqueue_style(
                'timegrow-manual-style',
                TIMEGROW_NEXUS_BASE_URI . 'assets/css/manual.css',
                [],
                $plugin_version
            );

        } elseif ($hook == "admin_page_timegrow-nexus-expenses" || $hook == "timegrow_page_timegrow-nexus-expenses") {
            wp_enqueue_script(
                'timegrow-nexus-expense-script', // New handle, matches wp_localize_script
                TIMEGROW_NEXUS_BASE_URI . 'assets/js/expense-recorder.js', // Path to your new JS file
                [], // No React dependencies needed for vanilla JS
                $plugin_version,
                true // Load in footer
            );
            wp_enqueue_style('timeflies-nexus-project-bc-style', TIMEGROW_NEXUS_BASE_URI . 'assets/css/nexus_project_bc.css', [], $plugin_version);

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
                TIMEGROW_NEXUS_BASE_URI . 'assets/css/expense.css',
                [],
                $plugin_version
            );
          
        } elseif ($hook == "admin_page_timegrow-nexus-reports" || $hook == "timegrow_page_timegrow-nexus-reports") {
            // Modern style already enqueued above
        } elseif ($hook == "admin_page_timegrow-nexus-a-report" || $hook == "timegrow_page_timegrow-nexus-a-report") {
            // Enqueue forms.css for .timegrow-page wrapper styles (shared from core module)
            wp_enqueue_style(
                'timegrow-forms-style',
                TIMEGROW_CORE_BASE_URI . 'assets/css/forms.css',
                [],
                $plugin_version
            );
            // Enqueue reports.css for report-specific styles
            wp_enqueue_style(
                'timegrow-reports-style',
                TIMEGROW_NEXUS_BASE_URI . 'assets/css/reports.css',
                [],
                $plugin_version
            );
        } elseif ($hook == "admin_page_timegrow-nexus-settings" || $hook == "timegrow_page_timegrow-nexus-settings") {
            // Modern style already enqueued above
            wp_enqueue_style(
                'timegrow-nexus-settings-style',
                TIMEGROW_NEXUS_BASE_URI . 'assets/css/settings.css',
                [],
                $plugin_version
            );
        }

        // Dashboard - check if it's the main Nexus page
        if ($hook == "timegrow_page_timegrow-nexus" || strpos($hook, 'timegrow-nexus') !== false) {
            wp_enqueue_script('timeflies-nexus-script', TIMEGROW_NEXUS_BASE_URI . 'assets/js/nexus_dashboard.js', array('jquery'), '1.0', true);
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
        $view_dashboard = new TimeGrowNexusView();
        $view_clock = new TimeGrowNexusClockView();
        $view_manual = new TimeGrowNexusManualView();
        $view_expense = new TimeGrowNexusExpenseView();
        $view_report = new TimeGrowNexusReportView();
        $view_settings = new TimeGrowNexusSettingsView();
        $controller_reports = new TimeGrowReportsController($view_report);
        $team_member_model = new TimeGrowTeamMemberModel();
        $projects = []; // Default to empty array
        $reports = []; // Default to empty array
        $list = []; // Default to empty array
    
        if ( $screen == 'clock' or $screen == 'manual' or $screen == 'expenses' ) {
            $team_member_model = new TimeGrowTeamMemberModel();
            if (current_user_can('administrator') ) {
                print('<h2 class=""><p>Administrator Access</p></h2>');
                // User is an administrator
                $projects = $team_member_model->get_projects_for_member(-1); // -1 for admin means all projects
                $list = $model->select(); // -1 for admin means all projects
            } else {
                // User is not an administrator, get projects for the current user
                $projects = $team_member_model->get_projects_for_member(get_current_user_id());
                $list = $model->select(get_current_user_id()); // Get entries for the current user
            }
        } elseif ( $screen == 'reports' ) {
            $reports = $controller_reports->get_available_reports_for_user(wp_get_current_user()); 
        } elseif ($screen == 'process_time') {
            try {
                $time_entries = $model->get_time_entries_to_bill();
                if (empty($time_entries)) {
                    $message_text = 'No time entries found.';
                    print('<div class="notice notice-warning"><p>' . $message_text . '</p></div>');
                    exit;
                }
                $woo_order_creator = new TimeGrowWooOrderCreator();
                list($orders, $mark_time_entries_as_billed) = $woo_order_creator->create_woo_orders_and_products($time_entries);
                if (empty($orders)) {
                    $message_text = 'No orders created.';
                    print('<div class="notice notice-warning"><p>' . $message_text . '</p></div>');
                    exit;
                }

                if (empty($mark_time_entries_as_billed)) {
                    $message_text = 'No time entries to mark as billed.';
                    print('<div class="notice notice-warning"><p>' . $message_text . '</p></div>');
                    exit;
                }
                $entries_by_order = $model->mark_time_entries_as_billed($mark_time_entries_as_billed);

                // Display user-friendly success message
                echo '<div class="notice notice-success" style="padding: 15px; margin: 20px 0;">';
                echo '<h3 style="margin-top: 0;">✓ Time Entries Processed Successfully</h3>';
                echo '<p><strong>' . count($orders) . '</strong> order' . (count($orders) > 1 ? 's' : '') . ' created with <strong>' . count($mark_time_entries_as_billed) . '</strong> time ' . (count($mark_time_entries_as_billed) > 1 ? 'entries' : 'entry') . ':</p>';

                // Display orders with their time entries
                if ($entries_by_order && is_array($entries_by_order)) {
                    echo '<div style="margin-top: 15px;">';
                    foreach ($entries_by_order as $order_id => $entries) {
                        $order = wc_get_order($order_id);
                        if ($order) {
                            $order_edit_url = admin_url('post.php?post=' . $order_id . '&action=edit');
                            $customer = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                            $total_hours = 0;
                            foreach ($entries as $entry) {
                                $total_hours += floatval($entry->hours ?? 0);
                            }

                            echo '<div style="margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-left: 4px solid #46b450; border-radius: 3px;">';
                            echo '<strong>Order <a href="' . esc_url($order_edit_url) . '" target="_blank">#' . $order_id . '</a></strong> - ' . esc_html($customer);
                            echo '<br><span style="font-size: 0.9em; color: #666;">' . count($entries) . ' time ' . (count($entries) > 1 ? 'entries' : 'entry') . ' (' . number_format($total_hours, 2) . ' hours) - Total: ' . $order->get_formatted_order_total() . '</span>';
                            echo '</div>';
                        }
                    }
                    echo '</div>';
                }
                echo '</div>';

                // Add collapsible debug section
                $debug_output = $woo_order_creator->get_debug_output();
                if (!empty($debug_output)) {
                    echo '<details style="margin: 20px 0; padding: 15px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px;">';
                    echo '<summary style="cursor: pointer; font-weight: bold; padding: 10px;">Debug Information (Click to expand)</summary>';
                    echo '<div style="margin-top: 15px; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 500px; overflow: auto;">';
                    echo $debug_output;
                    echo '</div>';
                    echo '</details>';
                }

            } catch (Exception $e) {
                $message_text = 'Error processing time entries: ' . $e->getMessage();
                echo '<div class="notice notice-error"><p>' . $message_text . '</p></div>';
                if (WP_DEBUG) {
                    echo '<pre style="background: #f5f5f5; padding: 10px; border: 1px solid #ddd; overflow: auto;">';
                    echo esc_html($e->getTraceAsString());
                    echo '</pre>';
                }
                exit;
            }
        }

        $controller = new TimeGrowNexusController($model, $view_dashboard, $view_clock, $view_manual, $view_expense, $view_report, $view_settings, $projects, $reports, $team_member_model, $list);
        $controller->display_admin_page($screen);
    }

    
    public function get_open_order_for_client($client_id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
    }



}