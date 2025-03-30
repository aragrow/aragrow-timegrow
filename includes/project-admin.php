<?php
// includes/projects.php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Timeflies_Projects_Admin {
    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('wp_ajax_save_project', array($this, 'save_ajax'));
        add_action('wp_ajax_delete_project', array($this, 'delete_ajax'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles'));
    }

    public function enqueue_scripts_styles() {
        wp_enqueue_style('timeflies-projects-style', ARAGROW_TIMEGROW_BASE_URI . 'assets/css/project.css');
        wp_enqueue_script('timeflies-projects-script', ARAGROW_TIMEGROW_BASE_URI . 'assets/js/project.js', array('jquery'), '1.0', true);
        wp_localize_script(
            'timeflies-projects-script',
            'timeflies_projects_list',
            [
                'list_url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-projects-list'),
                'nonce' => wp_create_nonce('timeflies_project_nonce')
            ]
        );
        wp_enqueue_script('jquery-ui-slider');
        wp_enqueue_style('jquery-ui-style', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    }

    public function save_ajax() {
        try {
            if (WP_DEBUG) error_log('Exec: Timeflies_projects_Admin.save_ajax()');
            check_ajax_referer('timeflies_project_nonce', 'timeflies_project_nonce_field');
        
            if (WP_DEBUG) error_log('--> Validation Passed!!');
            
            $project_id = intval($_POST['project_id']);
            $client_id = intval($_POST['client_id']);
            $name = sanitize_text_field($_POST['name']);
            $description = wp_kses_post($_POST['description']);
            $default_flat_fee = floatval($_POST['default_flat_fee']);
            $start_date = !empty($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null;
            $end_date = !empty($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
            $estimate_hours = floatval($_POST['estimate_hours']);
            $billable = isset($_POST['billable']) ? $_POST['billable'] : 0;
            $status = isset($_POST['status']) ? $_POST['status'] : 0;

            $current_date = current_time('mysql');

            $project_data = array(
                'client_id' => $client_id,
                'name' => $name,
                'description' => $description,
                'default_flat_fee' => $default_flat_fee,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'estimate_hours' => $estimate_hours,
                'billable' => $billable,
                'status' => $status,
                'updated_at' => $current_date,
            );

            error_log(print_r($project_data,true));

            global $wpdb;
            $prefix = $wpdb->prefix;
            
            if ($project_id === 0) {
                $project_data['created_at'] = $current_date;
                $return = $wpdb->insert("{$prefix}timeflies_projects", $project_data, array(
                    '%d', '%s', '%s', '%f', '%s', '%s', '%f', '%d', '%d','%s', '%s'
                ));
                $project_id = $wpdb->insert_id;
            } else {
                $return = $wpdb->update("{$prefix}timeflies_projects", $project_data, array('ID' => $project_id), array(
                    '%d', '%s', '%s', '%f', '%s', '%s', '%f', '%d', '%d','%s'
                ));
            }
            error_log(print_r($return, true));
            error_log($wpdb->last_query);
        
            wp_send_json_success(array('message' => 'project Saved.', 'project_id' => $project_id));
        
        } catch (Exception $e) {
            if(WP_DEBUG) error_log('Timeflies_projects_Admin.save_ajax() -> '.$e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        } finally {
            if(WP_DEBUG) error_log('Timeflies_projects_Admin.save_ajax() -> Finalized');
        }
    }

    public function delete_ajax() {
        // Implement delete functionality here
    }
}

Timeflies_Projects_Admin::get_instance();
