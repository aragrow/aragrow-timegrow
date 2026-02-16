<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowSettings {

    private $general_option_name = 'aragrow_timegrow_general_settings';
    private $ai_option_name = 'aragrow_timegrow_ai_settings';
    private $ai_configs_option_name = 'aragrow_timegrow_ai_configurations';
    private $ai_form_temp_option_name = 'aragrow_timegrow_ai_form_temp'; // Temporary form data (cleared unless editing)

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'handle_config_actions']);
        add_action('admin_init', [$this, 'handle_form_submission']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_settings_styles']);

        // Create AI config table if it doesn't exist
        if (class_exists('TimeGrowAIConfigModel')) {
            $model = new TimeGrowAIConfigModel();
            $model->create_table();
            if(WP_DEBUG) error_log('AI config table check/creation completed');
        }

        // One-time cleanup of old options (will only run once)
        if (!get_option('timegrow_ai_options_cleaned')) {
            if (class_exists('TimeGrowAIConfigModel')) {
                TimeGrowAIConfigModel::cleanup_old_options();
                update_option('timegrow_ai_options_cleaned', true);
                if(WP_DEBUG) error_log('AI options cleanup completed');
            }
        }
    }

    /**
     * Enqueue settings page styles
     */
    public function enqueue_settings_styles($hook) {
        // Only load on settings page
        if ($hook !== 'timegrow_page_' . TIMEGROW_PARENT_MENU . '-settings') {
            return;
        }

        wp_enqueue_style(
            'timegrow-modern-style',
            plugin_dir_url(__FILE__) . '../assets/css/timegrow-modern.css',
            [],
            '1.0.0'
        );
        wp_enqueue_style(
            'timegrow-forms-style',
            plugin_dir_url(__FILE__) . '../assets/css/forms.css',
            [],
            '1.0.0'
        );
    }

    /**
     * Register the settings page in WordPress admin menu
     */
    public function register_settings_page() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        add_submenu_page(
            TIMEGROW_PARENT_MENU,
            'TimeGrow Settings',
            'Settings',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings, sections, and fields
     */
    public function register_settings() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // ========================================
        // GENERAL SETTINGS
        // ========================================
        register_setting(
            'aragrow_timegrow_general_settings_group',
            $this->general_option_name,
            [$this, 'sanitize_general_settings']
        );

        add_settings_section(
            'general_settings_section',
            '',
            [$this, 'render_general_section_info'],
            'aragrow-timegrow-settings-general'
        );

        // Add general settings fields here (placeholder for now)
        add_settings_field(
            'timezone',
            'Timezone',
            [$this, 'render_timezone_field'],
            'aragrow-timegrow-settings-general',
            'general_settings_section'
        );

        add_settings_field(
            'currency',
            'Currency',
            [$this, 'render_currency_field'],
            'aragrow-timegrow-settings-general',
            'general_settings_section'
        );

        // ========================================
        // AI PROVIDER SETTINGS
        // ========================================
        register_setting(
            'aragrow_timegrow_ai_settings_group',
            $this->ai_option_name,
            [$this, 'sanitize_ai_settings']
        );

        add_settings_section(
            'ai_provider_section',
            '',
            [$this, 'render_ai_provider_section_info'],
            'aragrow-timegrow-settings-ai'
        );

        // Provider selection
        add_settings_field(
            'ai_provider',
            'AI Provider',
            [$this, 'render_ai_provider_field'],
            'aragrow-timegrow-settings-ai',
            'ai_provider_section'
        );

        // Model selection (dynamic based on provider)
        add_settings_field(
            'ai_model',
            'Model',
            [$this, 'render_ai_model_field'],
            'aragrow-timegrow-settings-ai',
            'ai_provider_section'
        );

        // API Key
        add_settings_field(
            'ai_api_key',
            'API Key',
            [$this, 'render_ai_api_key_field'],
            'aragrow-timegrow-settings-ai',
            'ai_provider_section'
        );

        // Set as active configuration
        add_settings_field(
            'is_active_config',
            'Active Configuration',
            [$this, 'render_is_active_config_field'],
            'aragrow-timegrow-settings-ai',
            'ai_provider_section'
        );

        // Configuration name
        add_settings_field(
            'config_name',
            'Configuration Name',
            [$this, 'render_config_name_field'],
            'aragrow-timegrow-settings-ai',
            'ai_provider_section'
        );

        // Enable auto-analysis
        add_settings_field(
            'enable_auto_analysis',
            'Enable Auto-Analysis',
            [$this, 'render_enable_auto_analysis_field'],
            'aragrow-timegrow-settings-ai',
            'ai_provider_section'
        );

        // Confidence threshold
        add_settings_field(
            'confidence_threshold',
            'Confidence Threshold',
            [$this, 'render_confidence_threshold_field'],
            'aragrow-timegrow-settings-ai',
            'ai_provider_section'
        );
    }

    /**
     * Get available AI providers and their models
     */
    private function get_ai_providers() {
        return [
            'google_gemini' => [
                'name' => 'Google Gemini',
                'models' => TimeGrowGeminiReceiptAnalyzer::get_model_options(),
                'api_url' => 'https://generativelanguage.googleapis.com/v1/models/{model}:generateContent',
                'docs_url' => 'https://aistudio.google.com/apikey',
            ],
            'openai' => [
                'name' => 'OpenAI',
                'models' => [
                    'gpt-4o' => 'GPT-4o (Best for vision)',
                    'gpt-4o-mini' => 'GPT-4o Mini (Faster, Cheaper)',
                    'gpt-4-turbo' => 'GPT-4 Turbo',
                ],
                'api_url' => 'https://api.openai.com/v1/chat/completions',
                'docs_url' => 'https://platform.openai.com/api-keys',
            ],
            'anthropic' => [
                'name' => 'Anthropic Claude',
                'models' => [
                    'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet (Best for vision)',
                    'claude-3-opus-20240229' => 'Claude 3 Opus (Most capable)',
                    'claude-3-haiku-20240307' => 'Claude 3 Haiku (Fastest)',
                ],
                'api_url' => 'https://api.anthropic.com/v1/messages',
                'docs_url' => 'https://console.anthropic.com/settings/keys',
            ],
        ];
    }

    /**
     * Sanitize general settings
     */
    public function sanitize_general_settings($input) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        $sanitized = [];

        // Timezone
        if (isset($input['timezone'])) {
            $sanitized['timezone'] = sanitize_text_field($input['timezone']);
        } else {
            $sanitized['timezone'] = 'UTC';
        }

        // Currency
        if (isset($input['currency'])) {
            $sanitized['currency'] = sanitize_text_field($input['currency']);
        } else {
            $sanitized['currency'] = 'USD';
        }

        return $sanitized;
    }

    /**
     * Sanitize AI settings
     */
    public function sanitize_ai_settings($input) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Check if this is an actual form submission or just WordPress retrieving the option
        $is_form_submission = isset($_POST['submit']) || isset($_POST['option_page']);

        // Check if this is a programmatic update (from our config actions like load/activate/clear)
        $is_programmatic_update = is_array($input) && !empty($input) && !$is_form_submission;

        if(WP_DEBUG) {
            error_log('Is form submission: ' . ($is_form_submission ? 'YES' : 'NO'));
            error_log('Is programmatic update: ' . ($is_programmatic_update ? 'YES' : 'NO'));
        }

        // If not a form submission AND not a programmatic update, just return input as-is
        if (!$is_form_submission && !$is_programmatic_update) {
            if(WP_DEBUG) error_log('Not a form submission or programmatic update - returning input as-is');
            return $input;
        }

        // If it's a programmatic update (load/activate/clear), return the input without processing
        if ($is_programmatic_update) {
            if(WP_DEBUG) error_log('Programmatic update - returning input without creating new config');
            return $input;
        }

        $sanitized = [];
        $providers = $this->get_ai_providers();

        // Preserve existing config_id if updating
        $existing = get_option($this->ai_option_name, []);
        if(WP_DEBUG) {
            error_log('Existing option has config_id: ' . (isset($existing['config_id']) ? $existing['config_id'] : 'NONE'));
            error_log('Input has config_id: ' . (isset($input['config_id']) ? $input['config_id'] : 'NONE'));
        }

        // Check if config_id is in the input (hidden field) OR in the existing option
        if (isset($input['config_id']) && !empty($input['config_id'])) {
            // Config ID from hidden form field (preferred)
            $sanitized['config_id'] = sanitize_text_field($input['config_id']);
            if(WP_DEBUG) error_log('Using config_id from input: ' . $sanitized['config_id']);
        } elseif (isset($existing['config_id'])) {
            // Config ID from existing option (fallback)
            $sanitized['config_id'] = $existing['config_id'];
            if(WP_DEBUG) error_log('Preserving config_id from existing: ' . $existing['config_id']);
        } else {
            if(WP_DEBUG) error_log('No config_id found - will create new configuration');
        }

        // Configuration name
        $sanitized['config_name'] = isset($input['config_name']) && !empty($input['config_name'])
            ? sanitize_text_field($input['config_name'])
            : 'Default Configuration';

        // AI Provider
        if (isset($input['ai_provider']) && array_key_exists($input['ai_provider'], $providers)) {
            $sanitized['ai_provider'] = $input['ai_provider'];
        } else {
            $sanitized['ai_provider'] = 'google_gemini';
        }

        // AI Model (validate against selected provider)
        $selected_provider = $sanitized['ai_provider'];
        if (isset($input['ai_model']) && array_key_exists($input['ai_model'], $providers[$selected_provider]['models'])) {
            $sanitized['ai_model'] = $input['ai_model'];
        } else {
            // Default to first model of selected provider
            $sanitized['ai_model'] = array_key_first($providers[$selected_provider]['models']);
        }

        // API Key - encrypt if Voice AI Security class is available
        $api_key_updated = false;
        if (isset($input['ai_api_key'])) {
            $api_key = sanitize_text_field($input['ai_api_key']);
            if (!empty($api_key)) {
                if (class_exists('\AraGrow\VoiceAI\Security')) {
                    // Check if this looks like it's already encrypted (base64 encoded, not starting with expected API key prefix)
                    // Google Gemini keys start with "AIza", OpenAI with "sk-", Anthropic with "sk-ant-"
                    $is_already_encrypted = !preg_match('/^(AIza|sk-|sk-ant-)/', $api_key);

                    if ($is_already_encrypted) {
                        // Already encrypted, don't encrypt again
                        $sanitized['ai_api_key'] = $api_key;
                        if(WP_DEBUG) error_log('API key appears already encrypted, skipping encryption');
                    } else {
                        // Plain text key, encrypt it
                        $sanitized['ai_api_key'] = \AraGrow\VoiceAI\Security::encrypt($api_key);
                        $api_key_updated = true;
                        if(WP_DEBUG) error_log('Encrypting new API key');
                    }
                } else {
                    $sanitized['ai_api_key'] = $api_key;
                    $api_key_updated = true;
                }
            } else {
                // Field is empty - keep existing encrypted value if it exists
                $sanitized['ai_api_key'] = $existing['ai_api_key'] ?? '';
            }
        } else {
            // Field not in input - keep existing value
            $sanitized['ai_api_key'] = $existing['ai_api_key'] ?? '';
        }

        // Store flag to show API key saved banner
        if ($api_key_updated) {
            set_transient('timegrow_api_key_saved', true, 30);
        }

        // Enable auto-analysis
        $sanitized['enable_auto_analysis'] = isset($input['enable_auto_analysis']) ? true : false;

        // Confidence threshold
        if (isset($input['confidence_threshold'])) {
            $threshold = floatval($input['confidence_threshold']);
            $sanitized['confidence_threshold'] = max(0.0, min(1.0, $threshold));
        } else {
            $sanitized['confidence_threshold'] = 0.7;
        }

        // Is active configuration
        $is_active = isset($input['is_active_config']) && $input['is_active_config'];
        $sanitized['is_active_config'] = $is_active;

        // Save or update configuration
        $this->save_configuration($sanitized, $is_active);

        // After saving, clear the form by returning empty defaults
        // This ensures the form is empty after save
        $cleared_form = [
            'ai_provider' => 'google_gemini',
            'ai_model' => 'gemini-2.0-flash-exp',
            'ai_api_key' => '',
            'enable_auto_analysis' => false,
            'confidence_threshold' => 0.7,
            'config_name' => '',
            'is_active_config' => false,
        ];

        if(WP_DEBUG) error_log('Clearing form after save');

        return $cleared_form;
    }

    /**
     * Save or update configuration
     *
     * @param array $config Configuration data
     * @param bool $is_active Whether this should be the active config
     */
    private function save_configuration($config, $is_active) {
        $configs = get_option($this->ai_configs_option_name, []);

        // If this is an update (has config_id), update existing
        if (isset($config['config_id']) && isset($configs[$config['config_id']])) {
            $config_id = $config['config_id'];
            if(WP_DEBUG) error_log('Updating existing config: ' . $config_id);

            // If setting as active, deactivate all others
            if ($is_active) {
                foreach ($configs as &$existing_config) {
                    $existing_config['is_active_config'] = false;
                }
            }

            // Update the config
            $config['is_active_config'] = $is_active;
            $configs[$config_id] = $config;
        } else {
            // Creating new config
            $config['config_id'] = uniqid('ai_config_');
            if(WP_DEBUG) error_log('Creating new config with ID: ' . $config['config_id']);

            // If setting as active, deactivate all others
            if ($is_active) {
                foreach ($configs as &$existing_config) {
                    $existing_config['is_active_config'] = false;
                }
            }

            $config['is_active_config'] = $is_active;
            $configs[$config['config_id']] = $config;
        }

        update_option($this->ai_configs_option_name, $configs);
        if(WP_DEBUG) error_log('Saved configurations, total count: ' . count($configs));
    }

    /**
     * Get active AI configuration
     * Static method that can be called from anywhere
     */
    public static function get_active_ai_config() {
        // Use the new table-based model
        if (class_exists('TimeGrowAIConfigModel')) {
            $model = new TimeGrowAIConfigModel();
            $active = $model->get_active();

            if ($active) {
                // Convert database format to expected format
                $active['is_active_config'] = (bool)$active['is_active'];
                $active['enable_auto_analysis'] = (bool)$active['enable_auto_analysis'];
                return $active;
            }
        }

        // Fallback if no active config found
        return [
            'ai_api_key' => '',
            'ai_provider' => 'google_gemini',
            'ai_model' => 'gemini-2.0-flash-exp',
            'enable_auto_analysis' => true,
            'confidence_threshold' => 0.7,
            'config_name' => 'Default Configuration',
            'is_active_config' => true,
        ];
    }

    /**
     * Get form data (either from editing transient or empty defaults)
     */
    private function get_form_data() {
        // Check if we're editing a configuration
        $editing_data = get_transient('timegrow_editing_config_' . get_current_user_id());

        if ($editing_data && isset($_GET['loaded']) && isset($_GET['edit_id'])) {
            return $editing_data;
        }

        // Return empty defaults (no provider/model selected)
        return [
            'config_name' => '',
            'ai_provider' => '',
            'ai_model' => '',
            'ai_api_key' => '',
            'enable_auto_analysis' => false,
            'confidence_threshold' => 0.7,
            'is_active' => false,
        ];
    }

    /**
     * Get all AI configurations
     */
    public static function get_all_ai_configs() {
        // Use the new table-based model
        if (class_exists('TimeGrowAIConfigModel')) {
            $model = new TimeGrowAIConfigModel();
            $configs = $model->get_all();

            // Convert database array format to match expected format
            if ($configs) {
                $formatted = [];
                foreach ($configs as $config) {
                    $formatted[$config['id']] = $config;
                    $formatted[$config['id']]['is_active_config'] = (bool)$config['is_active'];
                    $formatted[$config['id']]['enable_auto_analysis'] = (bool)$config['enable_auto_analysis'];
                }
                return $formatted;
            }
        }

        return [];
    }

    /**
     * Handle form submission to save AI configuration
     */
    public function handle_form_submission() {
        // Check if this is our form submission
        if (!isset($_POST['timegrow_ai_config_nonce'])) {
            return;
        }

        if(WP_DEBUG) error_log('=== AI CONFIG FORM SUBMISSION DETECTED ===');

        // Verify nonce
        if (!wp_verify_nonce($_POST['timegrow_ai_config_nonce'], 'timegrow_save_ai_config')) {
            if(WP_DEBUG) error_log('Nonce verification failed');
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can(TIMEGROW_OWNER_CAP)) {
            if(WP_DEBUG) error_log('Insufficient permissions');
            wp_die('Insufficient permissions');
        }

        if(WP_DEBUG) error_log('Processing AI config form submission');

        // Get form data
        $provider = sanitize_text_field($_POST[$this->ai_option_name]['ai_provider'] ?? '');
        $model = sanitize_text_field($_POST[$this->ai_option_name]['ai_model'] ?? '');

        // Validate required fields
        if (empty($provider)) {
            wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=ai&error=missing_provider'));
            exit;
        }

        if (empty($model)) {
            wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=ai&error=missing_model'));
            exit;
        }

        $config_data = [
            'config_name' => sanitize_text_field($_POST[$this->ai_option_name]['config_name'] ?? 'Default Configuration'),
            'ai_provider' => $provider,
            'ai_model' => $model,
            'enable_auto_analysis' => isset($_POST[$this->ai_option_name]['enable_auto_analysis']),
            'confidence_threshold' => floatval($_POST[$this->ai_option_name]['confidence_threshold'] ?? 0.7),
            'is_active' => isset($_POST[$this->ai_option_name]['is_active_config']),
        ];

        if(WP_DEBUG) error_log('Config data: ' . print_r($config_data, true));

        // Handle API key - only update if a new one is provided
        $api_key = sanitize_text_field($_POST[$this->ai_option_name]['ai_api_key'] ?? '');
        if (!empty($api_key)) {
            // Encrypt the API key
            if (class_exists('\AraGrow\VoiceAI\Security')) {
                $config_data['ai_api_key'] = \AraGrow\VoiceAI\Security::encrypt($api_key);
                if(WP_DEBUG) error_log('API key encrypted');
            } else {
                $config_data['ai_api_key'] = $api_key;
            }
        }

        // Check if we're updating or creating
        $config_id = isset($_POST[$this->ai_option_name]['config_id']) ? intval($_POST[$this->ai_option_name]['config_id']) : 0;

        $model = new TimeGrowAIConfigModel();

        if ($config_id > 0) {
            // Update existing configuration
            // If no new API key provided, get the existing one
            if (empty($api_key)) {
                $existing = $model->get_by_id($config_id);
                if ($existing) {
                    $config_data['ai_api_key'] = $existing['ai_api_key'];
                }
            }

            $model->update($config_id, $config_data);
            if(WP_DEBUG) error_log('Updated config ID: ' . $config_id);

            // Clear the editing transient
            delete_transient('timegrow_editing_config_' . get_current_user_id());

            wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=ai&updated=1'));
        } else {
            // Create new configuration
            // API key is required for new configs
            if (empty($api_key)) {
                wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=ai&error=missing_api_key'));
                exit;
            }

            $new_id = $model->create($config_data);
            if(WP_DEBUG) error_log('Created new config ID: ' . $new_id);

            // Clear the editing transient
            delete_transient('timegrow_editing_config_' . get_current_user_id());

            wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=ai&created=1'));
        }
        exit;
    }

    /**
     * Handle configuration actions (activate, load, delete, clear)
     */
    public function handle_config_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== TIMEGROW_PARENT_MENU . '-settings') {
            return;
        }

        if (!isset($_GET['tab']) || $_GET['tab'] !== 'ai') {
            return;
        }

        if (!isset($_GET['action'])) {
            return;
        }

        if (!current_user_can(TIMEGROW_OWNER_CAP)) {
            wp_die('Insufficient permissions');
        }

        $action = $_GET['action'];
        if(WP_DEBUG) error_log('Config action: ' . $action);

        // Handle clear action (doesn't need config_id)
        if ($action === 'clear') {
            // Clear the editing transient
            delete_transient('timegrow_editing_config_' . get_current_user_id());
            if(WP_DEBUG) error_log('Form cleared - transient deleted');

            wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=ai'));
            exit;
        }

        // Other actions require config_id
        if (!isset($_GET['config_id'])) {
            if(WP_DEBUG) error_log('No config_id provided');
            return;
        }

        $config_id = intval($_GET['config_id']);
        if(WP_DEBUG) error_log('Config ID: ' . $config_id);

        $model = new TimeGrowAIConfigModel();
        $config = $model->get_by_id($config_id);

        if (!$config) {
            if(WP_DEBUG) error_log('Config ID not found in database');
            return;
        }

        if(WP_DEBUG) error_log('Config found, proceeding with action: ' . $action);

        switch ($action) {
            case 'activate':
                $model->set_active($config_id);
                if(WP_DEBUG) error_log('Activated config ID: ' . $config_id);

                wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=ai&activated=1&t=' . time()));
                exit;
                break;

            case 'load':
                // Store the config data in a transient for form population
                set_transient('timegrow_editing_config_' . get_current_user_id(), $config, 300);
                if(WP_DEBUG) error_log('Loading config ID ' . $config_id . ' for editing');

                wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=ai&loaded=1&edit_id=' . $config_id . '&t=' . time()));
                exit;
                break;

            case 'delete':
                if(WP_DEBUG) error_log('Deleting config: ' . $config_id);

                $model->delete($config_id);

                if(WP_DEBUG) error_log('Config deleted from database');
                wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=ai&deleted=1&t=' . time()));
                exit;
                break;
        }
    }

    /**
     * Render settings page with modern card-based design
     */
    public function render_settings_page() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        if (!current_user_can(TIMEGROW_OWNER_CAP)) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

        if ($active_tab === 'overview') {
            $this->render_overview_page();
        } elseif ($active_tab === 'general') {
            $this->render_general_settings_form();
        } elseif ($active_tab === 'ai') {
            $this->render_ai_settings_form();
        }
    }

    /**
     * Render overview page with setting cards
     */
    private function render_overview_page() {
        // Check AI configuration from new database table
        $ai_settings = self::get_active_ai_config();
        $has_ai_configured = !empty($ai_settings['ai_api_key']);
        $paypal_plugin_active = class_exists('Aragrow_WC_PayPal_Auto_Invoicer');
        ?>
        <div class="wrap timegrow-page">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1>TimeGrow Settings</h1>
                    <p class="subtitle">Configure your time tracking, expenses, integrations, and AI automation</p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-admin-settings"></span>
                </div>
            </div>

            <!-- Settings Cards -->
            <div class="timegrow-cards-container">
                <!-- General Settings Card -->
                <a href="?page=<?php echo TIMEGROW_PARENT_MENU; ?>-settings&tab=general" class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon timegrow-icon-primary">
                            <span class="dashicons dashicons-admin-generic"></span>
                        </div>
                        <div class="timegrow-card-title">
                            <h2>General Settings</h2>
                            <span class="timegrow-badge timegrow-badge-primary">Core</span>
                        </div>
                    </div>
                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            Configure timezone, currency, and other global settings for your TimeGrow installation.
                        </p>
                        <div class="timegrow-features">
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-clock"></span>
                                <span>Timezone Configuration</span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-money-alt"></span>
                                <span>Currency Settings</span>
                            </div>
                        </div>
                        <div class="timegrow-card-footer">
                            <span class="timegrow-action-link">
                                Configure General Settings
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                            </span>
                        </div>
                    </div>
                </a>

                <!-- AI Provider Settings Card -->
                <a href="?page=<?php echo TIMEGROW_PARENT_MENU; ?>-settings&tab=ai" class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon <?php echo $has_ai_configured ? 'timegrow-icon-primary' : 'timegrow-icon-disabled'; ?>">
                            <span class="dashicons dashicons-analytics"></span>
                        </div>
                        <div class="timegrow-card-title">
                            <h2>AI Receipt Analysis</h2>
                            <span class="timegrow-badge <?php echo $has_ai_configured ? 'timegrow-badge-success' : 'timegrow-badge-inactive'; ?>">
                                <?php echo $has_ai_configured ? 'Configured' : 'Not Configured'; ?>
                            </span>
                        </div>
                    </div>
                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            Configure AI-powered automatic receipt analysis. Upload receipt images and let AI extract expense data automatically.
                        </p>
                        <div class="timegrow-features">
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-google"></span>
                                <span>Google Gemini</span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-welcome-learn-more"></span>
                                <span>OpenAI GPT-4</span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-superhero"></span>
                                <span>Claude AI</span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <span>Auto-Populate Fields</span>
                            </div>
                        </div>
                        <?php if (!$has_ai_configured): ?>
                        <div class="timegrow-info-box">
                            <span class="dashicons dashicons-info"></span>
                            <p><strong>Setup Required:</strong> Add your API key to enable automatic receipt analysis.</p>
                        </div>
                        <?php endif; ?>
                        <div class="timegrow-card-footer">
                            <span class="timegrow-action-link">
                                <?php echo $has_ai_configured ? 'Manage AI Settings' : 'Setup AI Analysis'; ?>
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                            </span>
                        </div>
                    </div>
                </a>

                <!-- WooCommerce Integration Card -->
                <a href="<?php echo esc_url(admin_url('options-general.php?page=woocommerce-integration')); ?>" class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon timegrow-icon-woocommerce">
                            <span class="dashicons dashicons-cart"></span>
                        </div>
                        <div class="timegrow-card-title">
                            <h2>WooCommerce Integration</h2>
                            <span class="timegrow-badge timegrow-badge-primary">Integration</span>
                        </div>
                    </div>
                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            Sync time tracking data with WooCommerce for seamless invoicing, client management, and product integration.
                        </p>
                        <div class="timegrow-features">
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-groups"></span>
                                <span>Client Sync</span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-media-document"></span>
                                <span>Invoice Sync</span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-products"></span>
                                <span>Product Sync</span>
                            </div>
                        </div>
                        <div class="timegrow-card-footer">
                            <span class="timegrow-action-link">
                                Configure WooCommerce
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                            </span>
                        </div>
                    </div>
                </a>

                <!-- PayPal Integration Card -->
                <?php if ($paypal_plugin_active): ?>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=paypal-integration')); ?>" class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon timegrow-icon-paypal">
                            <span class="dashicons dashicons-money-alt"></span>
                        </div>
                        <div class="timegrow-card-title">
                            <h2>PayPal Integration</h2>
                            <span class="timegrow-badge timegrow-badge-success">Active</span>
                        </div>
                    </div>
                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            Configure PayPal API credentials and automatic invoice generation settings.
                        </p>
                        <div class="timegrow-features">
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-money-alt"></span>
                                <span>Auto Invoicing</span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-admin-network"></span>
                                <span>API Integration</span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-email"></span>
                                <span>Email Delivery</span>
                            </div>
                        </div>
                        <div class="timegrow-card-footer">
                            <span class="timegrow-action-link">
                                Manage PayPal
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                            </span>
                        </div>
                    </div>
                </a>
                <?php else: ?>
                <div class="timegrow-card disabled">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon timegrow-icon-disabled">
                            <span class="dashicons dashicons-money-alt"></span>
                        </div>
                        <div class="timegrow-card-title">
                            <h2>PayPal Integration</h2>
                            <span class="timegrow-badge timegrow-badge-inactive">Not Loaded</span>
                        </div>
                    </div>
                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            PayPal module is included but not currently loaded.
                        </p>
                        <div class="timegrow-info-box">
                            <span class="dashicons dashicons-info"></span>
                            <p><strong>Module Info:</strong> The PayPal Auto Invoicer module is part of TimeGrow. If you're seeing this message, the module may not have loaded properly.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Help Section -->
            <div class="timegrow-help-section">
                <div class="timegrow-help-icon">
                    <span class="dashicons dashicons-sos"></span>
                </div>
                <div class="timegrow-help-content">
                    <h3>Need Help?</h3>
                    <p>Check out our documentation or contact support for assistance with TimeGrow settings.</p>
                </div>
                <div class="timegrow-help-links">
                    <a href="#" class="timegrow-help-link" target="_blank">
                        <span class="dashicons dashicons-book"></span>
                        Documentation
                    </a>
                    <a href="#" class="timegrow-help-link" target="_blank">
                        <span class="dashicons dashicons-email"></span>
                        Contact Support
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render general settings form
     */
    private function render_general_settings_form() {
        ?>
        <div class="wrap timegrow-page">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1>General Settings</h1>
                    <p class="subtitle">Configure timezone, currency, and other global settings</p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-admin-generic"></span>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('aragrow_timegrow_general_settings_group');
                do_settings_sections('aragrow-timegrow-settings-general');
                ?>

                <div class="timegrow-footer">
                    <?php submit_button(__('Save Settings', 'timegrow'), 'primary large', 'submit', false); ?>
                    <a href="?page=<?php echo TIMEGROW_PARENT_MENU; ?>-settings" class="button button-secondary large">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        <?php esc_html_e('Back to Settings', 'timegrow'); ?>
                    </a>
                </div>
            </form>
        </div>
        <?php
    }

    /**
     * Render AI settings form
     */
    private function render_ai_settings_form() {
        $all_configs = self::get_all_ai_configs();
        $active_config = self::get_active_ai_config();

        // Clear the form temp data on normal page load (not when coming from edit/load action)
        $is_loading_for_edit = isset($_GET['loaded']) && $_GET['loaded'] == '1';
        if (!$is_loading_for_edit) {
            // Delete the temp option to ensure form is empty by default
            delete_option($this->ai_form_temp_option_name);
            if(WP_DEBUG) error_log('Form temp data cleared on page load (not editing)');
        }

        if(WP_DEBUG) {
            error_log('=== RENDERING AI SETTINGS FORM ===');
            error_log('Total configs to display: ' . count($all_configs));
            foreach($all_configs as $id => $cfg) {
                error_log('  - ' . $id . ': ' . ($cfg['config_name'] ?? 'Unnamed'));
            }
        }
        ?>
        <div class="wrap timegrow-page">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1>AI Receipt Analysis</h1>
                    <p class="subtitle">Configure AI-powered automatic receipt analysis</p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-analytics"></span>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (get_transient('timegrow_api_key_saved')): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>API Key Encrypted and Saved!</strong> Your API key has been securely encrypted and saved to the database.</p>
                </div>
                <?php delete_transient('timegrow_api_key_saved'); ?>
            <?php endif; ?>

            <?php if (isset($_GET['activated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Configuration activated successfully!</strong> This configuration is now active and will be used for receipt analysis.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['loaded'])): ?>
                <div class="notice notice-info is-dismissible">
                    <p><strong>Configuration loaded!</strong> Make your changes and save to update this configuration. Note: The API key is not loaded for security - leave blank to keep existing key, or enter a new one to update it.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Configuration deleted successfully!</strong></p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['created'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Configuration created successfully!</strong> Your AI configuration has been saved.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Configuration updated successfully!</strong> Your changes have been saved.</p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <?php if ($_GET['error'] === 'missing_api_key'): ?>
                    <div class="notice notice-error is-dismissible">
                        <p><strong>Error:</strong> API key is required for new configurations.</p>
                    </div>
                <?php elseif ($_GET['error'] === 'missing_provider'): ?>
                    <div class="notice notice-error is-dismissible">
                        <p><strong>Error:</strong> Please select an AI provider.</p>
                    </div>
                <?php elseif ($_GET['error'] === 'missing_model'): ?>
                    <div class="notice notice-error is-dismissible">
                        <p><strong>Error:</strong> Please select an AI model.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Saved Configurations -->
            <div class="postbox" style="margin-bottom: 20px;">
                <h2 class="hndle" style="padding: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <span>
                        Saved AI Configurations
                        <?php if(WP_DEBUG): ?>
                            <small style="font-weight: normal; color: #d63638; margin-left: 10px;">
                                [DEBUG: <?php echo count($all_configs); ?> configs in DB]
                            </small>
                        <?php endif; ?>
                    </span>
                    <button type="button" id="timegrow-show-config-form" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt" style="margin-top: 3px;"></span>
                        Create New Configuration
                    </button>
                </h2>
                <div class="inside">
                    <?php if (empty($all_configs)): ?>
                        <p style="padding: 15px; margin: 0; color: #666;">
                            No configurations saved yet. Use the form below to create your first AI configuration.
                        </p>
                    <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Configuration Name</th>
                                <th>Provider</th>
                                <th>Model</th>
                                <th>Status</th>
                                <th>Auto-Analysis</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_configs as $config_id => $config): ?>
                            <tr>
                                <td><strong><?php echo esc_html($config['config_name'] ?? 'Unnamed Config'); ?></strong></td>
                                <td><?php echo esc_html($this->get_ai_providers()[$config['ai_provider']]['name'] ?? $config['ai_provider']); ?></td>
                                <td><?php echo esc_html($config['ai_model'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($config['is_active_config'] ?? false): ?>
                                        <span class="timegrow-badge timegrow-badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="timegrow-badge timegrow-badge-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($config['enable_auto_analysis'] ?? false): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> Enabled
                                    <?php else: ?>
                                        <span class="dashicons dashicons-dismiss" style="color: #d63638;"></span> Disabled
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!($config['is_active_config'] ?? false)): ?>
                                        <a href="?page=<?php echo TIMEGROW_PARENT_MENU; ?>-settings&tab=ai&action=activate&config_id=<?php echo esc_attr($config_id); ?>"
                                           class="button button-small">
                                            Activate
                                        </a>
                                    <?php endif; ?>
                                    <a href="?page=<?php echo TIMEGROW_PARENT_MENU; ?>-settings&tab=ai&action=load&config_id=<?php echo esc_attr($config_id); ?>"
                                       class="button button-small">
                                        Edit
                                    </a>
                                    <a href="?page=<?php echo TIMEGROW_PARENT_MENU; ?>-settings&tab=ai&action=delete&config_id=<?php echo esc_attr($config_id); ?>"
                                       class="button button-small button-link-delete"
                                       onclick="console.log('Delete clicked for config:', '<?php echo esc_js($config_id); ?>'); return confirm('Are you sure you want to delete this configuration?');">
                                        Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Debug Console Logging -->
            <?php if (WP_DEBUG):
                $current_form_values = get_option($this->ai_option_name, []);
                $has_config_id = !empty($current_form_values['config_id']);
            ?>
            <script>
                console.log('=== AI Settings Page Loaded ===');
                console.log('Total configurations displayed:', <?php echo count($all_configs); ?>);
                console.log('Configurations:', <?php echo json_encode(array_map(function($cfg) {
                    return [
                        'name' => $cfg['config_name'] ?? 'Unnamed',
                        'provider' => $cfg['ai_provider'] ?? 'N/A',
                        'model' => $cfg['ai_model'] ?? 'N/A',
                        'is_active' => $cfg['is_active_config'] ?? false
                    ];
                }, $all_configs)); ?>);
                console.log('URL Parameters:', window.location.search);
                console.log('Form State:', <?php echo $has_config_id ? '"EDITING - Config ID: ' . esc_js($current_form_values['config_id']) . '"' : '"EMPTY - Ready for new config"'; ?>);
                console.log('Form Values:', <?php echo json_encode([
                    'config_name' => $current_form_values['config_name'] ?? '',
                    'provider' => $current_form_values['ai_provider'] ?? 'google_gemini',
                    'model' => $current_form_values['ai_model'] ?? '',
                    'has_api_key' => !empty($current_form_values['ai_api_key'])
                ]); ?>);
            </script>
            <?php endif; ?>

            <!-- Configuration Form (hidden by default) -->
            <div id="timegrow-config-form-container" style="display: none;">
            <div class="postbox" style="margin-bottom: 20px;">
                <h2 class="hndle" style="padding: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <span id="timegrow-form-title">AI Configuration Form</span>
                    <button type="button" id="timegrow-hide-config-form" class="button button-secondary">
                        <span class="dashicons dashicons-no-alt" style="margin-top: 3px;"></span>
                        Cancel
                    </button>
                </h2>
                <div class="inside" style="padding: 15px;">

            <form method="post" action="" id="ai-settings-form">
                <?php wp_nonce_field('timegrow_save_ai_config', 'timegrow_ai_config_nonce'); ?>
                <?php do_settings_sections('aragrow-timegrow-settings-ai'); ?>

                <div class="timegrow-footer">
                    <?php submit_button(__('Save Configuration', 'timegrow'), 'primary large', 'submit', false); ?>
                    <button type="button" id="timegrow-cancel-form" class="button button-secondary large">
                        <span class="dashicons dashicons-no-alt"></span>
                        <?php esc_html_e('Cancel', 'timegrow'); ?>
                    </button>
                </div>
            </form>

                </div><!-- .inside -->
            </div><!-- .postbox -->
            </div><!-- #timegrow-config-form-container -->

            <div class="timegrow-footer" style="margin-top: 20px;">
                <a href="?page=<?php echo TIMEGROW_PARENT_MENU; ?>-settings" class="button button-secondary large">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    <?php esc_html_e('Back to Settings', 'timegrow'); ?>
                </a>
            </div>

            <script type="text/javascript">
            jQuery(document).ready(function($) {
                var $formContainer = $('#timegrow-config-form-container');
                var $formTitle = $('#timegrow-form-title');

                // Show form for new configuration
                $('#timegrow-show-config-form').on('click', function() {
                    console.log('Show config form clicked');
                    $formTitle.text('Create New Configuration');
                    // Make API key required for new configs
                    $('#ai_api_key').prop('required', true);
                    $formContainer.slideDown(300);
                    console.log('Form container display after slideDown:', $formContainer.css('display'));
                    $('html, body').animate({
                        scrollTop: $formContainer.offset().top - 50
                    }, 500);
                });

                // Hide form buttons
                $('#timegrow-hide-config-form, #timegrow-cancel-form').on('click', function() {
                    $formContainer.slideUp(300);
                    // Clear form by redirecting to the page without parameters
                    setTimeout(function() {
                        window.location.href = '?page=<?php echo TIMEGROW_PARENT_MENU; ?>-settings&tab=ai&action=clear';
                    }, 300);
                });

                // Show form if we're loading a config for editing
                <?php if (isset($_GET['loaded']) && $_GET['loaded'] == '1'): ?>
                $formTitle.text('Edit Configuration');
                // API key is optional when editing (keep existing if blank)
                $('#ai_api_key').prop('required', false);
                $formContainer.show();
                $('html, body').animate({
                    scrollTop: $formContainer.offset().top - 50
                }, 500);
                <?php endif; ?>

                // Hide form after successful save
                <?php if (isset($_GET['created']) || isset($_GET['updated'])): ?>
                $formContainer.hide();
                <?php endif; ?>

                // Update model dropdown when provider changes
                $('#ai_provider').on('change', function() {
                    var provider = $(this).val();
                    var models = <?php echo json_encode(array_map(function($p) { return $p['models']; }, $this->get_ai_providers())); ?>;

                    var modelSelect = $('#ai_model');
                    modelSelect.empty();

                    // Add placeholder option
                    modelSelect.append($('<option>', {
                        value: '',
                        text: '-- Select a Model --'
                    }));

                    $.each(models[provider], function(value, label) {
                        modelSelect.append($('<option>', {
                            value: value,
                            text: label
                        }));
                    });

                    // Update docs link
                    var docsUrls = <?php echo json_encode(array_map(function($p) { return $p['docs_url']; }, $this->get_ai_providers())); ?>;
                    $('#api-docs-link').attr('href', docsUrls[provider]);
                });

                // Form validation before submit
                $('#ai-settings-form').on('submit', function(e) {
                    var provider = $('#ai_provider').val();
                    var model = $('#ai_model').val();
                    var apiKey = $('#ai_api_key').val();
                    var isEditing = $('input[name="<?php echo esc_js($this->ai_option_name); ?>[config_id]"]').length > 0;

                    // Check required fields
                    if (!provider) {
                        e.preventDefault();
                        alert('Please select an AI provider.');
                        $('#ai_provider').focus();
                        return false;
                    }

                    if (!model) {
                        e.preventDefault();
                        alert('Please select an AI model.');
                        $('#ai_model').focus();
                        return false;
                    }

                    // API key is required for new configs
                    if (!isEditing && !apiKey) {
                        e.preventDefault();
                        alert('Please enter an API key. This is required for new configurations.');
                        $('#ai_api_key').focus();
                        return false;
                    }

                    return true;
                });
            });
            </script>
        </div>
        <?php
    }

    // ========================================
    // GENERAL SETTINGS RENDERERS
    // ========================================

    public function render_general_section_info() {
        // Section info is now in the modern header, no additional text needed
    }

    public function render_timezone_field() {
        $options = get_option($this->general_option_name, ['timezone' => 'UTC']);
        $timezone = $options['timezone'] ?? 'UTC';
        $timezones = timezone_identifiers_list();
        ?>
        <select name="<?php echo esc_attr($this->general_option_name); ?>[timezone]" class="regular-text">
            <?php foreach ($timezones as $tz): ?>
                <option value="<?php echo esc_attr($tz); ?>" <?php selected($timezone, $tz); ?>>
                    <?php echo esc_html($tz); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Timezone for time tracking and reporting.</p>
        <?php
    }

    public function render_currency_field() {
        $options = get_option($this->general_option_name, ['currency' => 'USD']);
        $currency = $options['currency'] ?? 'USD';
        $currencies = ['USD' => 'US Dollar ($)', 'EUR' => 'Euro (€)', 'GBP' => 'British Pound (£)', 'JPY' => 'Japanese Yen (¥)', 'CAD' => 'Canadian Dollar ($)', 'AUD' => 'Australian Dollar ($)'];
        ?>
        <select name="<?php echo esc_attr($this->general_option_name); ?>[currency]">
            <?php foreach ($currencies as $code => $label): ?>
                <option value="<?php echo esc_attr($code); ?>" <?php selected($currency, $code); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Default currency for expenses and invoicing.</p>
        <?php
    }

    // ========================================
    // AI PROVIDER SETTINGS RENDERERS
    // ========================================

    public function render_ai_provider_section_info() {
        // Section info is now in the modern header, no additional text needed
    }

    public function render_ai_provider_field() {
        $form_data = $this->get_form_data();
        $provider = $form_data['ai_provider'] ?? '';
        $providers = $this->get_ai_providers();
        ?>
        <select name="<?php echo esc_attr($this->ai_option_name); ?>[ai_provider]" id="ai_provider" class="regular-text" required>
            <option value="">-- Select a Provider --</option>
            <?php foreach ($providers as $key => $data): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($provider, $key); ?>>
                    <?php echo esc_html($data['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Choose which AI provider to use for receipt analysis. <strong style="color: #d63638;">*Required</strong></p>
        <?php
    }

    public function render_ai_model_field() {
        $form_data = $this->get_form_data();
        $provider = $form_data['ai_provider'] ?? '';
        $model = $form_data['ai_model'] ?? '';
        $providers = $this->get_ai_providers();
        $models = !empty($provider) ? ($providers[$provider]['models'] ?? []) : [];
        ?>
        <select name="<?php echo esc_attr($this->ai_option_name); ?>[ai_model]" id="ai_model" class="regular-text" required>
            <option value="">-- Select a Model --</option>
            <?php foreach ($models as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($model, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Choose which model to use for receipt analysis. <strong style="color: #d63638;">*Required</strong></p>
        <?php
    }

    public function render_ai_api_key_field() {
        $form_data = $this->get_form_data();
        $provider = $form_data['ai_provider'] ?? 'google_gemini';
        $has_key = !empty($form_data['ai_api_key']);
        $providers = $this->get_ai_providers();
        $docs_url = $providers[$provider]['docs_url'];
        ?>
        <input type="password"
               name="<?php echo esc_attr($this->ai_option_name); ?>[ai_api_key]"
               id="ai_api_key"
               value=""
               placeholder="<?php echo $has_key ? '••••••••••••••••' : 'Enter your API key'; ?>"
               class="regular-text"
               <?php echo !$has_key ? 'required' : ''; ?>>
        <p class="description">
            <?php if ($has_key): ?>
                <strong style="color: #00a32a;">✓ API key is configured and encrypted.</strong> Leave blank to keep current key, or enter a new key to update it.
            <?php else: ?>
                Enter your API key. It will be securely encrypted and stored. <strong style="color: #d63638;">*Required</strong>
            <?php endif; ?>
            <br>
            <strong>Get your API key:</strong> <a href="<?php echo esc_url($docs_url); ?>" target="_blank" id="api-docs-link">API Key Documentation</a>
        </p>
        <?php
    }

    public function render_is_active_config_field() {
        $form_data = $this->get_form_data();
        $is_active = isset($form_data['is_active']) && $form_data['is_active'];
        ?>
        <label class="timegrow-toggle-switch">
            <input type="checkbox"
                   name="<?php echo esc_attr($this->ai_option_name); ?>[is_active_config]"
                   value="1"
                   <?php checked($is_active, true); ?>>
            <span class="timegrow-toggle-slider"></span>
        </label>
        <span style="margin-left: 10px; font-weight: 600; color: #2271b1;">Set as Active Configuration</span>
        <p class="description">When enabled, this will be the active AI configuration used for receipt analysis. Only one configuration can be active at a time.</p>
        <?php
    }

    public function render_config_name_field() {
        $form_data = $this->get_form_data();
        $config_name = $form_data['config_name'] ?? '';
        $config_id = $form_data['id'] ?? ($_GET['edit_id'] ?? '');
        ?>
        <!-- Hidden field to preserve config_id when editing -->
        <?php if (!empty($config_id)): ?>
        <input type="hidden"
               name="<?php echo esc_attr($this->ai_option_name); ?>[config_id]"
               value="<?php echo esc_attr($config_id); ?>">
        <?php endif; ?>

        <input type="text"
               name="<?php echo esc_attr($this->ai_option_name); ?>[config_name]"
               value="<?php echo esc_attr($config_name); ?>"
               placeholder="e.g., Production Config, Testing Config"
               class="regular-text">
        <p class="description">Give this configuration a descriptive name to identify it later (e.g., "Production", "Backup", "Testing").</p>
        <?php
    }

    public function render_enable_auto_analysis_field() {
        $form_data = $this->get_form_data();
        $checked = isset($form_data['enable_auto_analysis']) && $form_data['enable_auto_analysis'];
        ?>
        <label class="timegrow-toggle-switch">
            <input type="checkbox"
                   name="<?php echo esc_attr($this->ai_option_name); ?>[enable_auto_analysis]"
                   value="1"
                   <?php checked($checked, true); ?>>
            <span class="timegrow-toggle-slider"></span>
        </label>
        <span style="margin-left: 10px;">Automatically analyze receipts when uploaded</span>
        <p class="description">When enabled, receipt images will be sent to your selected AI provider for automatic data extraction.</p>
        <?php
    }

    public function render_confidence_threshold_field() {
        $form_data = $this->get_form_data();
        $threshold = $form_data['confidence_threshold'] ?? 0.7;
        ?>
        <input type="number"
               name="<?php echo esc_attr($this->ai_option_name); ?>[confidence_threshold]"
               value="<?php echo esc_attr($threshold); ?>"
               min="0"
               max="1"
               step="0.1"
               class="small-text">
        <p class="description">Only auto-populate expense fields if AI confidence is above this threshold (0.0 to 1.0). Default: 0.7</p>
        <?php
    }
}
