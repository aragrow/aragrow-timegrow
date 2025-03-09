<?php

class Aragrow_TimeFlies {
    private static $instance;

    public static function get_instance() {
        /**
         * The get_instance() method checks if an instance already exists.
         * If not, it creates one and returns it.
         * The last line in the file, WC_Daily_Order_Export::get_instance();, triggers this process, 
         *  ensuring the class is instantiated and ready when the plugin is loaded.
         */
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('save_post', array($this, 'save_time_entry_data'));
        add_action('wp_ajax_generate_invoice', array($this, 'generate_invoice'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles')); // Enqueue scripts and styles

    }

    public function enqueue_scripts_styles() {
        wp_enqueue_style('timeflies-style', ARAGROW_TIMEFLIES_BASE_URI . 'assets/css/admin_styles.css', '', '1.0'); // Create this CSS file
        wp_enqueue_script('timeflies-script', ARAGROW_TIMEFLIES_BASE_URI . 'assets/js/admin_script.js', array('jquery'), '1.0', true); // Create this JS file
        wp_localize_script('timeflies-script', 'timeflies_ajax', array('ajax_url' => admin_url('admin-ajax.php')));
 
    }


    public function save_time_entry_data($post_id) {
        if (isset($_POST['timeflies_date'])) {
            update_post_meta($post_id, '_timeflies_date', sanitize_text_field($_POST['timeflies_date']));
        }
        if (isset($_POST['timeflies_hours'])) {
            update_post_meta($post_id, '_timeflies_hours', sanitize_text_field($_POST['timeflies_hours']));
        }
        if (isset($_POST['timeflies_billable'])) {
            update_post_meta($post_id, '_timeflies_billable', 1);
        } else {
            delete_post_meta($post_id, '_timeflies_billable');
        }
    }

    public function generate_invoice() {
        // Fetch project, time entries, and format invoice.
        wp_send_json_success(['message' => 'Invoice generated.']);
    }
}

// Instantiate the plugin class.
Aragrow_TimeFlies::get_instance();