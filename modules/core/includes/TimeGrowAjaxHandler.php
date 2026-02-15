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
        add_action('wp_ajax_list_gemini_models', array($this, 'handle_list_gemini_models'));

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
     * Handle real-time receipt analysis AJAX request
     * Analyzes receipt image immediately when uploaded and returns extracted data
     */
    public function handle_analyze_receipt_realtime() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'timegrow_ajax_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check if file was uploaded
        if (empty($_FILES['receipt_file'])) {
            wp_send_json_error('No file uploaded');
            return;
        }

        // Check AI configuration - use new database table
        $settings = class_exists('TimeGrowSettings') ? TimeGrowSettings::get_active_ai_config() : [];
        $ai_configured = !empty($settings['ai_api_key']) && !empty($settings['ai_provider']);

        if (!$ai_configured) {
            wp_send_json_error('AI is not configured. Please configure your AI provider in Settings.');
            return;
        }

        try {
            // Upload file temporarily
            $receipt_model = new TimeGrowExpenseReceiptModel();
            $upload_result = $receipt_model->upload_file($_FILES['receipt_file']);

            if (is_wp_error($upload_result)) {
                wp_send_json_error($upload_result->get_error_message());
                return;
            }

            // Analyze receipt with AI
            $analyzer = TimeGrowReceiptAnalyzerFactory::create();
            $analysis = $analyzer->analyze_receipt($upload_result['url']);

            if (is_wp_error($analysis)) {
                // Delete uploaded file if analysis fails
                @unlink($upload_result['path']);
                wp_send_json_error($analysis->get_error_message());
                return;
            }

            // Check confidence threshold
            $confidence_threshold = $settings['confidence_threshold'] ?? 0.7;
            $confidence = floatval($analysis['confidence'] ?? 0);

            if ($confidence < $confidence_threshold) {
                wp_send_json_success([
                    'low_confidence' => true,
                    'confidence' => $confidence,
                    'threshold' => $confidence_threshold,
                    'message' => sprintf(
                        'AI confidence is only %d%% (threshold: %d%%). Data may not be accurate.',
                        round($confidence * 100),
                        round($confidence_threshold * 100)
                    ),
                    'file_url' => $upload_result['url'],
                    'file_path' => $upload_result['path']
                ]);
                return;
            }

            // Category ID comes directly from LLM - no database lookup needed
            if(WP_DEBUG) {
                error_log('=== AJAX RESPONSE DATA ===');
                error_log('Category ID from LLM: ' . ($analysis['category_id'] ?? 'NOT SET'));
                error_log('Sending category_id directly to frontend: ' . intval($analysis['category_id'] ?? 0));
            }

            // Send success response with extracted data
            wp_send_json_success([
                'amount' => floatval($analysis['amount'] ?? 0),
                'expense_date' => sanitize_text_field($analysis['expense_date'] ?? ''),
                'expense_name' => sanitize_text_field($analysis['expense_name'] ?? ''),
                'expense_description' => sanitize_text_field($analysis['expense_description'] ?? ''),
                'category_id' => intval($analysis['category_id'] ?? 0),
                'assigned_to' => sanitize_text_field($analysis['assigned_to'] ?? 'general'),
                'assigned_to_id' => intval($analysis['assigned_to_id'] ?? 0),
                'confidence' => $confidence,
                'token_usage' => $analysis['token_usage'] ?? null,
                'model_used' => $analysis['model_used'] ?? null,
                'file_url' => $upload_result['url'],
                'file_path' => $upload_result['path'],
                'message' => sprintf('Receipt analyzed with %d%% confidence', round($confidence * 100))
            ]);

        } catch (Exception $e) {
            wp_send_json_error('Error analyzing receipt: ' . $e->getMessage());
        }
    }

    /**
     * Handle list Gemini models AJAX request
     * Returns all available Gemini models that support generateContent
     */
    public function handle_list_gemini_models() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'timegrow_ajax_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        try {
            // Create analyzer instance
            $analyzer = new TimeGrowGeminiReceiptAnalyzer();

            // Get available models
            $models = $analyzer->list_available_models();

            if (is_wp_error($models)) {
                wp_send_json_error($models->get_error_message());
                return;
            }

            // Add pricing information to each model
            foreach ($models as &$model) {
                $model_name = basename($model['name']);

                // Determine pricing tier based on model name and token limits
                // All are free tier but differ in token consumption
                if (strpos($model_name, '8b') !== false || strpos($model_name, '-8b-') !== false) {
                    $model['pricing'] = 'Free (Lightweight)';
                    $model['tier'] = 'low';
                } elseif (strpos($model_name, 'flash') !== false) {
                    $model['pricing'] = 'Free (Mid Tier)';
                    $model['tier'] = 'mid';
                } elseif (strpos($model_name, 'pro') !== false) {
                    $model['pricing'] = 'Free (Expensive - High quality)';
                    $model['tier'] = 'high';
                } else {
                    $model['pricing'] = 'Free';
                    $model['tier'] = 'unknown';
                }

                $model['model_id'] = $model_name;
            }

            wp_send_json_success([
                'models' => $models,
                'total' => count($models),
                'message' => 'Successfully retrieved ' . count($models) . ' vision-capable models'
            ]);

        } catch (Exception $e) {
            wp_send_json_error('Error listing models: ' . $e->getMessage());
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