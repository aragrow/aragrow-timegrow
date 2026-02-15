<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowGeminiReceiptAnalyzer implements TimeGrowReceiptAnalyzerInterface {

    private $api_key;
    private $model;
    private $settings;

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Load active configuration from settings
        if (class_exists('TimeGrowSettings')) {
            $this->settings = TimeGrowSettings::get_active_ai_config();
            if(WP_DEBUG) {
                error_log('=== ACTIVE CONFIG DEBUG ===');
                error_log('Config name: ' . ($this->settings['config_name'] ?? 'Not set'));
                error_log('Is active: ' . (isset($this->settings['is_active_config']) ? ($this->settings['is_active_config'] ? 'YES' : 'NO') : 'Not set'));
                error_log('Has API key field: ' . (isset($this->settings['ai_api_key']) ? 'YES' : 'NO'));
                error_log('API provider: ' . ($this->settings['ai_provider'] ?? 'Not set'));
                error_log('Model: ' . ($this->settings['ai_model'] ?? 'Not set'));
            }
        } else {
            // Fallback to legacy settings if TimeGrowSettings not loaded
            $this->settings = get_option('aragrow_timegrow_ai_settings', [
                'ai_api_key' => '',
                'ai_provider' => 'google_gemini',
                'ai_model' => 'gemini-2.0-flash-exp',
                'enable_auto_analysis' => true,
                'confidence_threshold' => 0.7,
            ]);
            if(WP_DEBUG) error_log('Using fallback settings (TimeGrowSettings class not found)');
        }

        // Decrypt API key if Voice AI Security class is available
        if (class_exists('\AraGrow\VoiceAI\Security')) {
            $encrypted_key = $this->settings['ai_api_key'] ?? '';
            if(WP_DEBUG) {
                error_log('=== API KEY DECRYPTION ===');
                error_log('Encrypted key present: ' . (!empty($encrypted_key) ? 'YES' : 'NO'));
                error_log('Encrypted key length: ' . strlen($encrypted_key));
            }

            $this->api_key = !empty($encrypted_key)
                ? \AraGrow\VoiceAI\Security::decrypt($encrypted_key)
                : '';

            if(WP_DEBUG) {
                error_log('Decryption successful: ' . (!empty($this->api_key) ? 'YES' : 'NO'));
                error_log('Decrypted key length: ' . strlen($this->api_key));
                if(!empty($this->api_key)) {
                    error_log('Decrypted key preview: ' . substr($this->api_key, 0, 15) . '...' . substr($this->api_key, -4));
                }
            }
        } else {
            $this->api_key = $this->settings['ai_api_key'] ?? '';
            if(WP_DEBUG) error_log('No encryption class, using raw key (length: ' . strlen($this->api_key) . ')');
        }

        // Check for wp-config constant as fallback
        if (empty($this->api_key) && defined('ARAGROW_AI_API_KEY')) {
            $this->api_key = ARAGROW_AI_API_KEY;
            if(WP_DEBUG) error_log('Using wp-config API key fallback');
        }

        $this->model = $this->settings['ai_model'] ?? 'gemini-2.0-flash-exp';
        if(WP_DEBUG) {
            error_log('=== FINAL CONFIG ===');
            error_log('Using model: ' . $this->model);
            error_log('API key configured: ' . (!empty($this->api_key) ? 'YES' : 'NO'));
        }
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

        if(WP_DEBUG) {
            error_log('Prompt length: ' . strlen($prompt) . ' characters');
            error_log('Prompt preview (first 500 chars): ' . substr($prompt, 0, 500));
        }

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

        $api_url = 'https://generativelanguage.googleapis.com/v1/models/' . $this->model . ':generateContent';

        if(WP_DEBUG) {
            error_log('=== GEMINI API CALL ===');
            error_log('API URL: ' . $api_url);
            error_log('API Key present: ' . (!empty($this->api_key) ? 'YES' : 'NO'));
            error_log('API Key length: ' . strlen($this->api_key));
            if(!empty($this->api_key)) {
                error_log('API Key preview: ' . substr($this->api_key, 0, 15) . '...' . substr($this->api_key, -4));
            }
            error_log('Image MIME type: ' . $image_data['mime_type']);
            error_log('Base64 data length: ' . strlen($image_data['base64_data']));
        }

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
            if(WP_DEBUG) error_log('WP_Error: ' . $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if(WP_DEBUG) {
            error_log('=== API RESPONSE ===');
            error_log('Status code: ' . $status_code);
            if($status_code !== 200) {
                error_log('Error response body: ' . $body);
            }
        }

        if ($status_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = $error_data['error']['message'] ?? 'Unknown API error';

            if(WP_DEBUG) {
                error_log('Full error data: ' . print_r($error_data, true));
            }

            return new WP_Error('gemini_api_error', sprintf(__('Gemini API error (code %d): %s', 'aragrow-timegrow'), $status_code, $error_message));
        }

        $api_response = json_decode($body, true);

        // Log usage metadata if available
        if(WP_DEBUG && isset($api_response['usageMetadata'])) {
            error_log('=== TOKEN USAGE ===');
            error_log('Prompt tokens: ' . ($api_response['usageMetadata']['promptTokenCount'] ?? 0));
            error_log('Response tokens: ' . ($api_response['usageMetadata']['candidatesTokenCount'] ?? 0));
            error_log('Total tokens: ' . ($api_response['usageMetadata']['totalTokenCount'] ?? 0));
        }

        return $api_response;
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
        // Fetch actual expense categories from database
        $categories_list = $this->get_available_categories();

        return 'Analyze this receipt image and extract the following information:

1. Total amount (number only, no currency symbols)
2. Date (in YYYY-MM-DD format)
3. Vendor/Business name
4. Category ID - Choose the MOST SPECIFIC category from the available expense categories below.

   **AVAILABLE EXPENSE CATEGORIES:**
   Each category is formatted as "ID|slug: Name (description)"
   You MUST return ONLY the ID (the number before the pipe |) in your JSON response.

' . $categories_list . '

5. Description/Items purchased - Include all printed items/products listed on the receipt
6. Handwritten notes - Check for ANY handwritten text on the receipt (notes, signatures, memos, etc.)
7. Look for text patterns "CLIENT: [name]" or "PROJECT: [name]" (case insensitive)

Return the data as JSON in this exact format:
{
  "amount": 125.50,
  "date": "2024-01-15",
  "vendor": "Office Depot",
  "category_id": 25,
  "description": "Printer paper and ink cartridges",
  "handwritten_notes": "Meeting supplies for Q1 planning",
  "client_pattern": "CLIENT: ABC Corp",
  "project_pattern": "PROJECT: Website Redesign",
  "confidence": 0.95
}

CRITICAL CATEGORY INSTRUCTIONS:
- The "category_id" field must be EXACTLY the ID (number before the pipe |) from the categories list above
- For example, if you see "25|office_expense: Office Expense (supplies, equipment)", return 25
- DO NOT return the slug, name, or description - ONLY the numeric ID
- Choose the MOST SPECIFIC category ID that matches this expense
- Use the vendor name and purchase description to determine the best matching category
- If uncertain between categories, choose the most specific one that applies

OTHER IMPORTANT RULES:
- For handwritten notes: carefully examine the ENTIRE receipt image for ANY handwritten text (pen, pencil, marker)
- If handwritten notes are found, include them verbatim in the "handwritten_notes" field
- If no handwritten text is found, omit the "handwritten_notes" field entirely
- Confidence should be 0.0-1.0 (0.9+ for clear receipts, 0.7-0.9 for decent quality, below 0.7 for poor/unclear)
- If any field cannot be determined, use null
- If no CLIENT or PROJECT pattern is found, omit those fields
- Return ONLY the JSON object, no additional text or markdown formatting';
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
            if(WP_DEBUG) error_log('=== GEMINI RESPONSE ERROR ===');
            if(WP_DEBUG) error_log('Full response: ' . print_r($response, true));
            return new WP_Error('invalid_response', __('Invalid response from Gemini API.', 'aragrow-timegrow'));
        }

        $gemini_text = $response['candidates'][0]['content']['parts'][0]['text'];

        // Log the raw LLM response for evaluation
        if(WP_DEBUG) {
            error_log('=== RAW GEMINI LLM RESPONSE ===');
            error_log('Response text: ' . $gemini_text);
            error_log('Response length: ' . strlen($gemini_text) . ' characters');
        }

        // Remove markdown code blocks if present
        $gemini_text = preg_replace('/```json\s*|\s*```/', '', $gemini_text);
        $gemini_text = trim($gemini_text);

        if(WP_DEBUG) {
            error_log('=== CLEANED GEMINI RESPONSE ===');
            error_log('Cleaned text: ' . $gemini_text);
        }

        // Parse JSON
        $extracted_data = json_decode($gemini_text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            if(WP_DEBUG) {
                error_log('=== JSON PARSE ERROR ===');
                error_log('Error: ' . json_last_error_msg());
                error_log('Failed to parse: ' . $gemini_text);
            }
            return new WP_Error('json_parse_error', sprintf(__('Failed to parse Gemini response: %s', 'aragrow-timegrow'), json_last_error_msg()));
        }

        if(WP_DEBUG) {
            error_log('=== PARSED GEMINI DATA ===');
            error_log('Extracted data: ' . print_r($extracted_data, true));
        }

        // Build description with handwritten notes if present
        $description = sanitize_text_field($extracted_data['description'] ?? '');

        // Append handwritten notes if they exist
        if (!empty($extracted_data['handwritten_notes'])) {
            $handwritten = sanitize_text_field($extracted_data['handwritten_notes']);
            if (!empty($description)) {
                $description .= "\n\nHANDWRITTEN: " . $handwritten;
            } else {
                $description = "HANDWRITTEN: " . $handwritten;
            }
        }

        // Extract token usage if available
        $token_usage = null;
        if (isset($response['usageMetadata'])) {
            $token_usage = [
                'prompt_tokens' => $response['usageMetadata']['promptTokenCount'] ?? 0,
                'completion_tokens' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
                'total_tokens' => $response['usageMetadata']['totalTokenCount'] ?? 0,
            ];
        }

        // Map to expense fields
        $expense_data = [
            'amount' => floatval($extracted_data['amount'] ?? 0),
            'expense_date' => sanitize_text_field($extracted_data['date'] ?? ''),
            'expense_name' => sanitize_text_field($extracted_data['vendor'] ?? ''),
            'category_id' => intval($extracted_data['category_id'] ?? 0),
            'expense_description' => $description,
            'confidence' => floatval($extracted_data['confidence'] ?? 0),
            'client_pattern' => $extracted_data['client_pattern'] ?? null,
            'project_pattern' => $extracted_data['project_pattern'] ?? null,
            'raw_gemini_response' => $gemini_text,
            'token_usage' => $token_usage,
            'model_used' => $this->model,
        ];

        if(WP_DEBUG) {
            error_log('=== FINAL EXPENSE DATA ===');
            error_log('Amount: ' . $expense_data['amount']);
            error_log('Date: ' . $expense_data['expense_date']);
            error_log('Vendor: ' . $expense_data['expense_name']);
            error_log('Category ID (from LLM): ' . ($extracted_data['category_id'] ?? 'NOT SET'));
            error_log('Category ID (final): ' . $expense_data['category_id']);
            error_log('Description: ' . $expense_data['expense_description']);
            error_log('Confidence: ' . $expense_data['confidence']);
            error_log('Client pattern: ' . ($expense_data['client_pattern'] ?? 'none'));
            error_log('Project pattern: ' . ($expense_data['project_pattern'] ?? 'none'));
        }

        return $expense_data;
    }

    /**
     * Get available expense categories from database
     *
     * @return string Formatted list of categories for the AI prompt
     */
    private function get_available_categories() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        if (!class_exists('TimeGrowExpenseCategoryModel')) {
            if(WP_DEBUG) error_log('TimeGrowExpenseCategoryModel class not found - using fallback');
            // Fallback to basic categories if model not available
            return "**Available Categories:**\n- office_expense\n- car_truck_expenses\n- meals\n- travel\n- utilities\n- other";
        }

        try {
            $category_model = new TimeGrowExpenseCategoryModel();
            $categories = $category_model->get_all(['is_active' => 1]); // Get only active categories

            if(WP_DEBUG) error_log('Retrieved ' . count($categories) . ' categories from database');
        } catch (Exception $e) {
            if(WP_DEBUG) error_log('Error fetching categories: ' . $e->getMessage());
            return "**Available Categories:**\n- office_expense\n- car_truck_expenses\n- meals\n- travel\n- utilities\n- other";
        }

        if (empty($categories)) {
            return "**Available Categories:**\n- other (default)";
        }

        // Group categories by IRS section
        $part_ii = [];
        $part_v = [];

        foreach ($categories as $category) {
            // wpdb->get_results() returns objects, not arrays
            $id = $category->ID;
            $slug = $category->slug;
            $name = $category->name;
            $description = $category->description ?? '';
            $section = $category->irs_section ?? 'part_v';

            // Include ID in the format: "ID|slug: name (description)"
            $line = "- {$id}|{$slug}: {$name}";
            if (!empty($description)) {
                $line .= " ({$description})";
            }

            if ($section === 'part_ii') {
                $part_ii[] = $line;
            } else {
                $part_v[] = $line;
            }
        }

        $output = '';

        if (!empty($part_ii)) {
            $output .= "**Part II Categories (Main Business Expenses):**\n";
            $output .= implode("\n", $part_ii) . "\n\n";
        }

        if (!empty($part_v)) {
            $output .= "**Part V Categories (Other Expenses):**\n";
            $output .= implode("\n", $part_v);
        }

        // Add common vendor examples
        $output .= "\n\n**Common Vendor Examples:**\n";
        $output .= "- Staples, Office Depot → office_expense\n";
        $output .= "- Shell, Chevron, BP → car_truck_expenses\n";
        $output .= "- Hotels, Airlines → travel\n";
        $output .= "- Restaurants, Cafes → meals\n";
        $output .= "- GoDaddy, Hostinger → online_web_fees\n";
        $output .= "- FedEx, UPS, USPS → shipping_postage\n";
        $output .= "- Square, Stripe → credit_card_fees";

        return $output;
    }

    /**
     * Get IRS-specific guidance for expense categories
     * Based on IRS Schedule C instructions and Publication 535
     *
     * @param string $slug Category slug
     * @return string IRS guidance description
     */
    private function get_irs_category_guidance($slug) {
        $guidance = [
            // Part II - Main Business Expenses
            'advertising' => 'Marketing costs to promote your business including ads, business cards, flyers, website costs, social media promotion',
            'car_truck_expenses' => 'Vehicle expenses for business use: gas, oil, repairs, insurance, depreciation, lease payments (keep mileage log)',
            'commissions_fees' => 'Commissions and fees paid to non-employees for services, reported on Form 1099-MISC',
            'contract_labor' => 'Payments to independent contractors and freelancers (reported on Form 1099-NEC if $600+)',
            'depletion' => 'Recovery of natural resource costs (minerals, timber, oil, gas wells)',
            'depreciation' => 'Section 179 deduction for equipment, computers, furniture, machinery purchased for business',
            'employee_benefit_programs' => 'Health insurance, life insurance, dependent care for employees (not owner)',
            'insurance' => 'Business insurance premiums: liability, malpractice, workers compensation, property insurance',
            'interest_mortgage' => 'Mortgage interest on business property and buildings',
            'interest_other' => 'Interest on business loans, credit cards, lines of credit used for business',
            'legal_professional' => 'Attorney fees, CPA services, tax preparation, business consultants, professional advisors',
            'office_expense' => 'Office supplies, stationery, printer paper, ink, pens, software subscriptions under $2,500',
            'pension_profit_sharing' => 'Employer contributions to employee retirement plans (SEP, SIMPLE, 401k)',
            'rent_vehicles' => 'Rental or lease payments for business vehicles, equipment, machinery',
            'rent_property' => 'Rent for office space, warehouse, retail location, storage units',
            'repairs_maintenance' => 'Repairs and maintenance that keep property in working condition (not improvements)',
            'supplies' => 'Materials and supplies consumed in business operations, inventory items',
            'taxes_licenses' => 'Business licenses, permits, property taxes, payroll taxes, sales tax paid',
            'travel' => 'Business travel: airfare, hotels, rental cars, taxis (meals are separate category)',
            'meals' => 'Business meals with clients or while traveling (50% deductible, keep receipts and business purpose)',
            'utilities' => 'Electricity, gas, water, phone, internet for business premises',
            'wages' => 'Salaries and wages paid to employees (not owner), subject to payroll taxes',

            // Part V - Other Expenses
            'online_web_fees' => 'Domain registration, web hosting, website platforms, online services, SaaS subscriptions',
            'business_telephone' => 'Business phone lines, mobile phone business portion, VoIP services',
            'education_training' => 'Courses, seminars, workshops, certifications to maintain or improve business skills',
            'membership_dues' => 'Professional associations, trade organizations, chamber of commerce, business clubs',
            'books_publications' => 'Professional journals, trade magazines, business books, research materials',
            'photography_stock' => 'Stock photos, business photography, product images, professional headshots',
            'marketing_materials' => 'Brochures, promotional items, branded merchandise, trade show materials',
            'shipping_postage' => 'Postage, shipping, delivery services, courier fees, freight charges',
            'bank_fees' => 'Business bank account fees, check fees, wire transfer fees, overdraft charges',
            'credit_card_fees' => 'Merchant processing fees, payment gateway fees, transaction charges',
            'other' => 'Other ordinary and necessary business expenses not fitting other categories'
        ];

        return $guidance[$slug] ?? '';
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
     * Map Gemini category to TimeGrow expense category slug
     *
     * @param string $category_text Category from Gemini
     * @return string Mapped category slug
     */
    private function map_category_to_expense_type($category_text) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__ . ' - Input: ' . $category_text);

        // Normalize the category text
        $category_text = strtolower(str_replace(' ', '_', trim($category_text)));

        if(WP_DEBUG) error_log('Normalized category: ' . $category_text);

        // Get valid categories from database
        if (class_exists('TimeGrowExpenseCategoryModel')) {
            try {
                $category_model = new TimeGrowExpenseCategoryModel();
                $categories = $category_model->get_all(['is_active' => 1]);

                $valid_slugs = [];
                foreach ($categories as $cat) {
                    $valid_slugs[] = $cat->slug;
                }

                if(WP_DEBUG) error_log('Valid slugs from database: ' . implode(', ', $valid_slugs));

                // Check if category exists in database
                if (in_array($category_text, $valid_slugs)) {
                    if(WP_DEBUG) error_log('Category matched in database: ' . $category_text);
                    return $category_text;
                }
            } catch (Exception $e) {
                if(WP_DEBUG) error_log('Error fetching categories for validation: ' . $e->getMessage());
            }
        }

        // Legacy mapping for backwards compatibility
        $legacy_map = [
            'office_supplies' => 'office_expense',
            'rent' => 'rent_property',
            'transportation' => 'car_truck_expenses',
            'marketing' => 'advertising',
            'equipment' => 'office_expense',
            'software' => 'online_web_fees',
            'professional_services' => 'legal_professional',
        ];

        if (isset($legacy_map[$category_text])) {
            if(WP_DEBUG) error_log('Category mapped via legacy map: ' . $legacy_map[$category_text]);
            return $legacy_map[$category_text];
        }

        // Default to 'other'
        if(WP_DEBUG) error_log('Category defaulting to: other');
        return 'other';
    }

    /**
     * Check if this analyzer supports PDF files
     *
     * @return bool True if PDF analysis is supported
     */
    public function supports_pdf() {
        // Currently only supports JPEG and PNG
        // PDF support can be added later with image conversion
        return false;
    }

    /**
     * List all available Gemini models
     *
     * @return array|WP_Error Array of available models or error
     */
    public function list_available_models() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Check if API key is configured
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('Google Gemini API key is not configured.', 'aragrow-timegrow'));
        }

        $api_url = 'https://generativelanguage.googleapis.com/v1/models';

        // Make API request
        $response = wp_remote_get($api_url . '?key=' . $this->api_key, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
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

        $data = json_decode($body, true);

        if (!isset($data['models']) || !is_array($data['models'])) {
            return new WP_Error('invalid_response', __('Invalid response from Gemini API.', 'aragrow-timegrow'));
        }

        // Filter models that support generateContent
        $vision_models = [];
        foreach ($data['models'] as $model) {
            $supported_methods = $model['supportedGenerationMethods'] ?? [];
            if (in_array('generateContent', $supported_methods)) {
                $vision_models[] = [
                    'name' => $model['name'] ?? '',
                    'display_name' => $model['displayName'] ?? '',
                    'description' => $model['description'] ?? '',
                    'input_token_limit' => $model['inputTokenLimit'] ?? 0,
                    'output_token_limit' => $model['outputTokenLimit'] ?? 0,
                    'supported_methods' => $supported_methods,
                ];
            }
        }

        return $vision_models;
    }

    /**
     * Get list of vision-capable model names for settings dropdown
     *
     * @return array Associative array of model_name => display_name
     */
    public static function get_model_options() {
        // Return list of current available vision models (as of February 2026)
        // This can be called statically without API key for settings page
        // Models are ordered by recommendation: Flash models are free with rate limits, Pro models are paid
        return [
            'gemini-2.5-flash' => 'Gemini 2.5 Flash - Recommended, stable, fast, multimodal (up to 1M tokens)',
            'gemini-2.0-flash' => 'Gemini 2.0 Flash - Fast and versatile multimodal',
            'gemini-2.0-flash-001' => 'Gemini 2.0 Flash 001 - Stable version (Jan 2025)',
            'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash-Lite - Lightweight, fastest (July 2025)',
            'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash-Lite - Lightweight option',
            'gemini-2.0-flash-lite-001' => 'Gemini 2.0 Flash-Lite 001 - Stable lightweight',
            'gemini-2.5-pro' => 'Gemini 2.5 Pro - Most capable, high quality (Paid)',
        ];
    }
}
