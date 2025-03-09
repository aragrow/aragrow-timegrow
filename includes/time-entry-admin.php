<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Timeflies_TimeEntries_Admin {
    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('wp_ajax_save_time_entry', array($this, 'save_ajax'));
        add_action('wp_ajax_clock_id', array($this, 'clock_in'));
        add_action('wp_ajax_clock_out', array($this, 'clock_out'));
        add_action('wp_ajax_save_manual_entry', array($this, 'save_manual_entry'));
        add_action('wp_ajax_get_projects_by_client', array($this, 'get_projects_by_client'));
        add_action('wp_ajax_delete_team_member', array($this, 'delete_ajax'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles'));
    }

    public function enqueue_scripts_styles() {
       
        wp_enqueue_style('timeflies-time-entries-style', ARAGROW_TIMEFLIES_BASE_URI . 'assets/css/time_entry.css');
        wp_enqueue_script('timeflies-time-entries-script', ARAGROW_TIMEFLIES_BASE_URI. 'assets/js/time_entry.js', array('jquery'), '1.0', true);
        wp_localize_script(
            'timeflies-time-entries-script',
            'timeflies_time_entry_list',
            [
                'list_url' => admin_url('admin.php?page=' . TIMEFLIES_PARENT_MENU . '-time-entries-list'),
                'nonce' => wp_create_nonce('timeflies_time_entry_nonce')
            ]
        );
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_script('jquery-ui-slider');
        wp_enqueue_style('jquery-ui-style', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    }

    public function save_ajax() {
        try {
            if (WP_DEBUG) error_log('Exec: Timeflies_TimeEntries_Admin.save_ajax()');
            check_ajax_referer('timeflies_time_entry_nonce', 'timeflies_time_entry_nonce_field');
            if (WP_DEBUG)  error_log('Exec: Timeflies_TimeEntries_Admin.save_ajax()->Validation Passed.');

            $time_entry_id = intval($_POST['id']);

            $data = [
                'client_id' => intval($_POST['client_id']),
                'project_id' => intval($_POST['project_id']),
                'description' => sanitize_textarea_field($_POST['description']),
                'user_id' => get_current_user_id(),
            ];

            if ($_POST['entry_type'] === 'clock') {
                if (!empty($_POST['start_time'])) {
                    $data['start_time'] = date('Y-m-d H:i:s', strtotime($_POST['start_time']));
                }
                if (!empty($_POST['end_time'])) {
                    $data['end_time'] = date('Y-m-d H:i:s', strtotime($_POST['end_time']));
                }
            } else {
                $data['start_time'] = date('Y-m-d H:i:s', strtotime($_POST['start_time']));
                $data['end_time'] = date('Y-m-d H:i:s', strtotime($_POST['end_time']));
                
                if (strtotime($data['end_time']) <= strtotime($data['start_time'])) {
                    throw new Exception('End time must be after start time');
                }
            }

            global $wpdb;
            $prefix = $wpdb->prefix;

            if ($time_entry_id === 0) {
                $wpdb->insert("{$prefix}timeflies_time_entries", $data);
                $time_entry_id = $wpdb->insert_id;
            } else {
                $wpdb->update("{$prefix}timeflies_time_entries", $data, ['ID' => $time_entry_id]);
            }

            wp_send_json_success(['message' => 'Time entry saved']);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function get_projects_by_client() {
      
        if (WP_DEBUG) error_log('Exec: Timeflies_TimeEntries_Admin.get_projects_by_client()');
        check_ajax_referer('timeflies_time_entry_nonce', 'timeflies_time_entry_nonce_field');
        if (WP_DEBUG)error_log('Exec: Timeflies_TimeEntries_Admin.get_projects_by_client()->Validation Passed.');

        $client_id = intval($_POST['client_id']);
        $user_id = get_current_user_id();

        global $wpdb;
        $prefix = $wpdb->prefix;
        $projects = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.name 
            FROM {$prefix}timeflies_projects p
            INNER JOIN {$prefix}timeflies_team_member_projects tmp ON p.ID = tmp.project_id
            WHERE p.client_id = %d AND tmp.team_member_id = %d
        ", $client_id, $user_id), ARRAY_A);
        //error_log($wpdb->last_query);

        wp_send_json_success($projects);
    }

    public function clock_in() {
        try {
            check_ajax_referer('timeflies_check_in', 'nonce');
            // Get the current datetime object in the WordPress timezone
            $current_datetime = current_datetime();

            // Format the datetime using WordPress settings
            $formatted_datetime = $current_datetime->format(get_option('date_format') . ' ' . get_option('time_format'));

            $user_id = get_current_user_id();
            $data = [
                'member_id' => $user_id,
                'project_id' => intval($_POST['project_id']),
                'clock_in_date' => $formatted_datetime,
                'description' => 'Clock In',
                'entry_type' => 'clock_in'
            ];

            global $wpdb;
            $prefix = $wpdb->prefix;
            $wpdb->insert("{$prefix}timeflies_time_entries", $data);
            
            wp_send_json_success(['message' => 'Clocked In']);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function clock_out() {
        try {
            check_ajax_referer('timeflies_check_out', 'nonce');

            // Get the current datetime object in the WordPress timezone
            $current_datetime = current_datetime();

            // Format the datetime using WordPress settings
            $formatted_datetime = $current_datetime->format(get_option('date_format') . ' ' . get_option('time_format'));

            $user_id = get_current_user_id();
            $data = [
                'member_id' => $user_id,
                'project_id' => intval($_POST['project_id']),
                'clock_out_date' => $formatted_datetime,
                'description' => 'Clock In',
                'entry_type' => 'clock_in'
            ];

            global $wpdb;
            $prefix = $wpdb->prefix;
            $wpdb->insert("{$prefix}timeflies_time_entries{", $data);
            
            wp_send_json_success(['message' => 'Clocked Out']);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    public function save_manual_entry() {
        try {
            check_ajax_referer('timeflies_manual_entry', 'nonce');
            
            $user_id = get_current_user_id();
            $data = [
                'member_id' => $user_id,
                'project_id' => intval($_POST['project_id']),
                'hours' => floatval($_POST['hours']),
                'description' => sanitize_textarea_field($_POST['description']),
                'entry_type' => 'manual'
            ];
    
            // Check project assignment
            //if (!$this->is_project_assigned($data['project_id'], $user_id)) {
            //    throw new Exception('Invalid project selection');
            //}
    
            global $wpdb;
            $prefix = $wpdb->prefix;
            $wpdb->insert("{$prefix}timeflies_time_entries", $data);
            
            wp_send_json_success(['message' => 'Manual entry saved']);
    
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
}

Timeflies_TimeEntries_Admin::get_instance();