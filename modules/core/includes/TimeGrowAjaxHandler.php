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
        add_action('wp_ajax_analyze_receipt_realtime', array($this, 'handle_analyze_receipt_realtime'));

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
     * Real-time receipt reading: the uploaded image is read by the active AI and the expense
     * fields come back to fill the form. Low-confidence results come back too, with a warning.
     */
    public function handle_analyze_receipt_realtime() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'timegrow_ajax_nonce')) {
            wp_send_json_error('Security check failed');
        }
        // Owners, or people given the AI Receipt Reader mobile capability; in the mobile app (PIN
        // session) that capability is always needed
        $mobile  = class_exists('TimeGrowMobileAccess') && TimeGrowMobileAccess::in_mobile_session();
        $allowed = $mobile
            ? current_user_can('access_mobile_ai_receipts')
            : (current_user_can(TIMEGROW_OWNER_CAP) || (current_user_can('access_mobile_ai_receipts') && class_exists('TimeGrowMobileAccess') && TimeGrowMobileAccess::allowed(get_current_user_id())));
        if (!$allowed) {
            wp_send_json_error('You are not allowed to read receipts with AI.', 403);
        }
        if (empty($_FILES['receipt_file'])) {
            wp_send_json_error('No file uploaded');
        }
        $receipts = TimeGrowSettings::receipt_settings(); // Settings > AI Receipt Analysis
        if ($receipts['auto_analysis'] !== 'yes') {
            wp_send_json_error('Reading receipts with AI is turned off in Settings > AI Receipt Analysis.');
        }
        if (!TimeGrowAI::is_configured('receipts')) {
            wp_send_json_error('AI is not configured, or its key is not working. Check TimeGrow > Settings > AI.');
        }

        $receipt_model = new TimeGrowExpenseReceiptModel();
        $upload        = $receipt_model->upload_file($_FILES['receipt_file']);
        if (is_wp_error($upload)) {
            wp_send_json_error($upload->get_error_message());
        }
        $file = $upload['file'] ?? ''; // wp_handle_upload() puts the path in "file"

        try {
            $analysis = TimeGrowReceiptAnalyzerFactory::create()->analyze_receipt($upload['url'], ['path' => $file]);
        } catch (Exception $e) {
            $analysis = new WP_Error('analysis_error', $e->getMessage());
        }
        if (is_wp_error($analysis)) {
            if ($file && is_file($file)) {
                @unlink($file); // nothing will use this upload
            }
            wp_send_json_error($analysis->get_error_message());
        }

        $threshold  = (float) $receipts['confidence_threshold'];
        $confidence = (float) ($analysis['confidence'] ?? 0);
        $low        = $confidence < $threshold;

        $message = !empty($analysis['suspicious'])
            ? 'The receipt has text that reads like instructions to the AI. That text was ignored. Check every field before saving.'
            : ($low
                ? sprintf('AI confidence is only %d%% (threshold %d%%). Check every field before saving.', round($confidence * 100), round($threshold * 100))
                : sprintf('Receipt analyzed with %d%% confidence', round($confidence * 100)));
        if (class_exists('TimeGrowAILog')) {
            // What the person was shown, then the reading is closed
            TimeGrowAILog::say('assistant', $message . sprintf(' Filled in: %s, %s, %s.', $analysis['expense_name'] ?? '', $analysis['amount'] ?? 0, $analysis['expense_date'] ?? ''));
            TimeGrowAILog::finish();
        }

        wp_send_json_success([
            'amount'              => (float) ($analysis['amount'] ?? 0),
            'expense_date'        => sanitize_text_field($analysis['expense_date'] ?? ''),
            'expense_name'        => sanitize_text_field($analysis['expense_name'] ?? ''),
            'expense_description' => sanitize_textarea_field($analysis['expense_description'] ?? ''),
            'category_id'         => (int) ($analysis['category_id'] ?? 0),
            'assigned_to'         => sanitize_text_field($analysis['assigned_to'] ?? 'general'),
            'assigned_to_id'      => (int) ($analysis['assigned_to_id'] ?? 0),
            'confidence'          => $confidence,
            'threshold'           => $threshold,
            'low_confidence'      => $low,
            'suspicious'          => !empty($analysis['suspicious']),
            'token_usage'         => $analysis['token_usage'] ?? null,
            'model_used'          => $analysis['model_used'] ?? null,
            'file_url'            => $upload['url'], // the file's location on the server is not sent
            'message'             => $message,
        ]);
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