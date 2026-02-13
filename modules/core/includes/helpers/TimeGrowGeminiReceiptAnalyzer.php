<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowGeminiReceiptAnalyzer {

    private $api_key;
    private $model;
    private $settings;

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Load settings from new AI settings option
        $this->settings = get_option('aragrow_timegrow_ai_settings', [
            'ai_api_key' => '',
            'ai_provider' => 'google_gemini',
            'ai_model' => 'gemini-1.5-flash',
            'enable_auto_analysis' => true,
            'confidence_threshold' => 0.7,
        ]);

        // Decrypt API key if Voice AI Security class is available
        if (class_exists('\AraGrow\VoiceAI\Security')) {
            $this->api_key = !empty($this->settings['ai_api_key'])
                ? \AraGrow\VoiceAI\Security::decrypt($this->settings['ai_api_key'])
                : '';
        } else {
            $this->api_key = $this->settings['ai_api_key'] ?? '';
        }

        // Check for wp-config constant as fallback
        if (empty($this->api_key) && defined('ARAGROW_AI_API_KEY')) {
            $this->api_key = ARAGROW_AI_API_KEY;
        }

        $this->model = $this->settings['ai_model'] ?? 'gemini-1.5-flash';
    }

    /**
     * Analyze receipt image and extract expense data
     *
     * @param string $image_url URL of the uploaded receipt image
     * @param array $options Additional options
     * @return array|WP_Error Extracted data or error
     */
    public function analyze_receipt($image_url, $options = []) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Check if API key is configured
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Google Gemini API key is not configured.', 'aragrow-timegrow'));
        }

        // Check rate limiting if Voice AI Security class is available
        if (class_exists('\AraGrow\VoiceAI\Security')) {
            $user_id = get_current_user_id();
            if (!\AraGrow\VoiceAI\Security::check_rate_limit('gemini_analysis_' . $user_id)) {
                return new WP_Error('rate_limit', __('Too many receipt analysis requests. Please try again later.', 'aragrow-timegrow'));
            }
        }

        // Prepare image data for API
        $image_data = $this->prepare_image_for_api($image_url);
        if (is_wp_error($image_data)) {
            return $image_data;
        }

        // Get extraction prompt
        $prompt = $this->get_extraction_prompt();

        // Call Gemini Vision API
        $api_response = $this->call_gemini_vision_api($image_data, $prompt);
        if (is_wp_error($api_response)) {
            // Log the error if Security class available
            if (class_exists('\AraGrow\VoiceAI\Security')) {
                \AraGrow\VoiceAI\Security::log_security_event('gemini_analysis_failed', [
                    'error' => $api_response->get_error_message(),
                    'image_url' => $image_url,
                ]);
            }
            return $api_response;
        }

        // Parse response and extract structured data
        $parsed_data = $this->parse_gemini_response($api_response);
        if (is_wp_error($parsed_data)) {
            return $parsed_data;
        }

        // Match client/project patterns
        $assignment_data = $this->process_assignment_patterns($parsed_data);

        // Merge assignment data with parsed data
        $parsed_data = array_merge($parsed_data, $assignment_data);

        return $parsed_data;
    }

    /**
     * Call Google Gemini Vision API
     *
     * @param array $image_data Image data with mime_type and base64 content
     * @param string $prompt Extraction prompt
     * @return array|WP_Error API response or error
     */
    private function call_gemini_vision_api($image_data, $prompt) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent';

        // Build request body
        $request_body = [
            'contents' => [[
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inline_data' => [
                            'mime_type' => $image_data['mime_type'],
                            'data' => $image_data['base64_data']
                        ]
                    ]
                ]
            ]]
        ];

        // Make API request
        $response = wp_remote_post($api_url . '?key=' . $this->api_key, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($request_body),
            'timeout' => 30,
            'sslverify' => !defined('WP_DEBUG') || !WP_DEBUG,
        ]);

        // Check for errors
        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = $error_data['error']['message'] ?? 'Unknown API error';
            return new WP_Error('gemini_api_error', sprintf(__('Gemini API error (code %d): %s', 'aragrow-timegrow'), $status_code, $error_message));
        }

        return json_decode($body, true);
    }

    /**
     * Prepare image for API by downloading and converting to base64
     *
     * @param string $image_url URL of the image
     * @return array|WP_Error Image data or error
     */
    private function prepare_image_for_api($image_url) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Download image
        $response = wp_remote_get($image_url, [
            'timeout' => 30,
            'sslverify' => !defined('WP_DEBUG') || !WP_DEBUG,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $image_content = wp_remote_retrieve_body($response);
        $mime_type = wp_remote_retrieve_header($response, 'content-type');

        // Validate mime type
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!in_array($mime_type, $allowed_mime_types)) {
            return new WP_Error('invalid_mime_type', sprintf(__('Unsupported image type: %s. Only JPEG and PNG are supported.', 'aragrow-timegrow'), $mime_type));
        }

        // Convert to base64
        $base64_data = base64_encode($image_content);

        return [
            'mime_type' => $mime_type,
            'base64_data' => $base64_data
        ];
    }

    /**
     * Get the extraction prompt for Gemini
     *
     * @return string Prompt text
     */
    private function get_extraction_prompt() {
        return 'Analyze this receipt image and extract the following information:

1. Total amount (number only, no currency symbols)
2. Date (in YYYY-MM-DD format)
3. Vendor/Business name
4. Category (one of: office_supplies, travel, meals, utilities, rent, transportation, marketing, other)
5. Description/Items purchased
6. Look for text patterns "CLIENT: [name]" or "PROJECT: [name]" (case insensitive)

Return the data as JSON in this exact format:
{
  "amount": 125.50,
  "date": "2024-01-15",
  "vendor": "Office Depot",
  "category": "office_supplies",
  "description": "Printer paper and ink cartridges",
  "client_pattern": "CLIENT: ABC Corp",
  "project_pattern": "PROJECT: Website Redesign",
  "confidence": 0.95
}

If any field cannot be determined, use null. If no CLIENT or PROJECT pattern is found, omit those fields.
Return ONLY the JSON object, no additional text or markdown formatting.';
    }

    /**
     * Parse Gemini API response and extract structured data
     *
     * @param array $response API response
     * @return array|WP_Error Parsed data or error
     */
    private function parse_gemini_response($response) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Extract text from response
        if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            return new WP_Error('invalid_response', __('Invalid response from Gemini API.', 'aragrow-timegrow'));
        }

        $gemini_text = $response['candidates'][0]['content']['parts'][0]['text'];

        // Remove markdown code blocks if present
        $gemini_text = preg_replace('/```json\s*|\s*```/', '', $gemini_text);
        $gemini_text = trim($gemini_text);

        // Parse JSON
        $extracted_data = json_decode($gemini_text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_parse_error', sprintf(__('Failed to parse Gemini response: %s', 'aragrow-timegrow'), json_last_error_msg()));
        }

        // Map to expense fields
        $expense_data = [
            'amount' => floatval($extracted_data['amount'] ?? 0),
            'expense_date' => sanitize_text_field($extracted_data['date'] ?? ''),
            'expense_name' => sanitize_text_field($extracted_data['vendor'] ?? ''),
            'category' => $this->map_category_to_expense_type($extracted_data['category'] ?? ''),
            'expense_description' => sanitize_text_field($extracted_data['description'] ?? ''),
            'confidence' => floatval($extracted_data['confidence'] ?? 0),
            'client_pattern' => $extracted_data['client_pattern'] ?? null,
            'project_pattern' => $extracted_data['project_pattern'] ?? null,
            'raw_gemini_response' => $gemini_text,
        ];

        return $expense_data;
    }

    /**
     * Process CLIENT: and PROJECT: patterns from extracted data
     *
     * @param array $parsed_data Parsed data from Gemini
     * @return array Assignment data (assigned_to, assigned_to_id)
     */
    private function process_assignment_patterns($parsed_data) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Check for PROJECT pattern first (higher priority)
        if (!empty($parsed_data['project_pattern'])) {
            $project_match = $this->match_project_from_text($parsed_data['project_pattern']);
            if ($project_match) {
                return $project_match;
            }
        }

        // Check for CLIENT pattern
        if (!empty($parsed_data['client_pattern'])) {
            $client_match = $this->match_client_from_text($parsed_data['client_pattern']);
            if ($client_match) {
                return $client_match;
            }
        }

        // Default to general
        return [
            'assigned_to' => 'general',
            'assigned_to_id' => 0
        ];
    }

    /**
     * Match client from text pattern "CLIENT: Company Name"
     *
     * @param string $text Text containing client pattern
     * @return array|null Assignment data or null
     */
    private function match_client_from_text($text) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Extract pattern: "CLIENT: Company Name" (case insensitive)
        if (preg_match('/CLIENT:\s*(.+?)(?:\n|$)/i', $text, $matches)) {
            $client_name = trim($matches[1]);

            // Search in TimeGrowClientModel
            $client_model = new TimeGrowClientModel();
            $results = $client_model->search_by_name($client_name);

            // Return best match or null
            if (!empty($results)) {
                return [
                    'assigned_to' => 'client',
                    'assigned_to_id' => $results[0]->ID
                ];
            }
        }

        return null;
    }

    /**
     * Match project from text pattern "PROJECT: Project Name"
     *
     * @param string $text Text containing project pattern
     * @return array|null Assignment data or null
     */
    private function match_project_from_text($text) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Extract pattern: "PROJECT: Project Name" (case insensitive)
        if (preg_match('/PROJECT:\s*(.+?)(?:\n|$)/i', $text, $matches)) {
            $project_name = trim($matches[1]);

            // Search in TimeGrowProjectModel
            $project_model = new TimeGrowProjectModel();
            $results = $project_model->search_by_name($project_name);

            // Return best match or null
            if (!empty($results)) {
                return [
                    'assigned_to' => 'project',
                    'assigned_to_id' => $results[0]->ID
                ];
            }
        }

        return null;
    }

    /**
     * Map Gemini category to TimeGrow expense category
     *
     * @param string $category_text Category from Gemini
     * @return string Mapped category
     */
    private function map_category_to_expense_type($category_text) {
        // Valid categories from TimeGrowExpenseView.php
        $valid_categories = [
            'office_supplies',
            'utilities',
            'rent',
            'transportation',
            'meals',
            'travel',
            'marketing',
            'equipment',
            'software',
            'professional_services',
            'other'
        ];

        $category_text = strtolower(str_replace(' ', '_', trim($category_text)));

        // Return if valid
        if (in_array($category_text, $valid_categories)) {
            return $category_text;
        }

        // Default to 'other'
        return 'other';
    }
}
