<?php
/**
 * AJAX Handler Class
 * Handles all AJAX requests for the plugin
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class TimeGrow_Ajax_Handler {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize AJAX hooks
     */
    private function init_hooks() {
        // Hook for logged-in users
        add_action('wp_ajax_check_is_billable', array($this, 'handle_is_billable_check'));
        
        // Hook for non-logged-in users (if needed)
        //add_action('wp_ajax_nopriv_check_is_billable', array($this, 'handle_is_billable_check'));
        
        // Add more AJAX actions here as needed
        // add_action('wp_ajax_another_function', array($this, 'handle_another_function'));
    }
    
    /**
     * Handle is_billable AJAX request
     */
    public function handle_is_billable_check() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'timegrow_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check user permissions (optional)
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Get the data from the AJAX request
        $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        
        if (!$project_id) {
            wp_send_json_error('Invalid project ID');
            return;
        }
        
        try {
            // Create instance of your model class
            $project_model = new TimeGrowProjectModel();
            
            // Call the is_billable function
            $is_billable = $project_model->is_billable($project_id);
            
            // Send success response
            wp_send_json_success(array(
                'is_billable' => $is_billable,
                'project_id' => $project_id,
                'message' => $is_billable ? 'Project is billable' : 'Project is not billable'
            ));
            
        } catch (Exception $e) {
            // Send error response
            wp_send_json_error('Error checking billable status: ' . $e->getMessage());
        }
    }
    
    /**
     * Example: Handle another AJAX function
     */
    public function handle_another_function() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'timegrow_ajax_nonce')) {
            wp_die('Security check failed');
        }
        
        // Your logic here
        wp_send_json_success('Another function executed successfully');
    }
    
    /**
     * Enqueue AJAX scripts
     */
    public function enqueue_ajax_scripts() {
        wp_enqueue_script('jquery');
        
        // Enqueue your custom AJAX script
        wp_enqueue_script(
            'timegrow-ajax',
            plugin_dir_url(__FILE__) . '../assets/js/ajax.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        // Localize script with AJAX URL and nonce
        wp_localize_script('timegrow-ajax', 'timegrow_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('timegrow_ajax_nonce')
        ));
    }
}

// Initialize the AJAX handler
new TimeGrow_Ajax_Handler();