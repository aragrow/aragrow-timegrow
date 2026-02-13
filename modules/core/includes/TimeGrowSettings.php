<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowSettings {

    private $general_option_name = 'aragrow_timegrow_general_settings';
    private $ai_option_name = 'aragrow_timegrow_ai_settings';

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
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
            'General Settings',
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
            'AI Provider Configuration',
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
                'models' => [
                    'gemini-1.5-flash' => 'Gemini 1.5 Flash (Faster, Cheaper)',
                    'gemini-1.5-pro' => 'Gemini 1.5 Pro (More Accurate)',
                    'gemini-2.0-flash-exp' => 'Gemini 2.0 Flash (Experimental)',
                ],
                'api_url' => 'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent',
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

        $sanitized = [];
        $providers = $this->get_ai_providers();

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
        if (isset($input['ai_api_key'])) {
            $api_key = sanitize_text_field($input['ai_api_key']);
            if (!empty($api_key)) {
                if (class_exists('\AraGrow\VoiceAI\Security')) {
                    $sanitized['ai_api_key'] = \AraGrow\VoiceAI\Security::encrypt($api_key);
                } else {
                    $sanitized['ai_api_key'] = $api_key;
                }
            } else {
                // Keep existing value if empty
                $existing = get_option($this->ai_option_name, []);
                $sanitized['ai_api_key'] = $existing['ai_api_key'] ?? '';
            }
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

        return $sanitized;
    }

    /**
     * Render settings page with tabs
     */
    public function render_settings_page() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        if (!current_user_can(TIMEGROW_OWNER_CAP)) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
        ?>
        <div class="wrap">
            <h1>TimeGrow Settings</h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=<?php echo TIMEGROW_PARENT_MENU; ?>-settings&tab=general"
                   class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>">
                    General
                </a>
                <a href="?page=<?php echo TIMEGROW_PARENT_MENU; ?>-settings&tab=ai"
                   class="nav-tab <?php echo $active_tab == 'ai' ? 'nav-tab-active' : ''; ?>">
                    AI Provider
                </a>
            </h2>

            <?php if ($active_tab == 'general'): ?>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('aragrow_timegrow_general_settings_group');
                    do_settings_sections('aragrow-timegrow-settings-general');
                    submit_button();
                    ?>
                </form>
            <?php elseif ($active_tab == 'ai'): ?>
                <form method="post" action="options.php" id="ai-settings-form">
                    <?php
                    settings_fields('aragrow_timegrow_ai_settings_group');
                    do_settings_sections('aragrow-timegrow-settings-ai');
                    submit_button();
                    ?>
                </form>

                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Update model dropdown when provider changes
                    $('#ai_provider').on('change', function() {
                        var provider = $(this).val();
                        var models = <?php echo json_encode(array_map(function($p) { return $p['models']; }, $this->get_ai_providers())); ?>;

                        var modelSelect = $('#ai_model');
                        modelSelect.empty();

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
                });
                </script>
            <?php endif; ?>
        </div>
        <?php
    }

    // ========================================
    // GENERAL SETTINGS RENDERERS
    // ========================================

    public function render_general_section_info() {
        echo '<p>Configure general TimeGrow settings.</p>';
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
        echo '<p>Configure AI provider for automatic receipt analysis. When enabled, uploaded receipt images will be automatically analyzed to extract expense data.</p>';
    }

    public function render_ai_provider_field() {
        $options = get_option($this->ai_option_name, ['ai_provider' => 'google_gemini']);
        $provider = $options['ai_provider'] ?? 'google_gemini';
        $providers = $this->get_ai_providers();
        ?>
        <select name="<?php echo esc_attr($this->ai_option_name); ?>[ai_provider]" id="ai_provider" class="regular-text">
            <?php foreach ($providers as $key => $data): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($provider, $key); ?>>
                    <?php echo esc_html($data['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Choose which AI provider to use for receipt analysis.</p>
        <?php
    }

    public function render_ai_model_field() {
        $options = get_option($this->ai_option_name, ['ai_provider' => 'google_gemini', 'ai_model' => 'gemini-1.5-flash']);
        $provider = $options['ai_provider'] ?? 'google_gemini';
        $model = $options['ai_model'] ?? 'gemini-1.5-flash';
        $providers = $this->get_ai_providers();
        $models = $providers[$provider]['models'];
        ?>
        <select name="<?php echo esc_attr($this->ai_option_name); ?>[ai_model]" id="ai_model" class="regular-text">
            <?php foreach ($models as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($model, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Choose which model to use for receipt analysis.</p>
        <?php
    }

    public function render_ai_api_key_field() {
        $options = get_option($this->ai_option_name, []);
        $provider = $options['ai_provider'] ?? 'google_gemini';
        $has_key = !empty($options['ai_api_key']);
        $providers = $this->get_ai_providers();
        $docs_url = $providers[$provider]['docs_url'];
        ?>
        <input type="password"
               name="<?php echo esc_attr($this->ai_option_name); ?>[ai_api_key]"
               value=""
               placeholder="<?php echo $has_key ? '••••••••••••••••' : 'Enter your API key'; ?>"
               class="regular-text">
        <p class="description">
            <?php if ($has_key): ?>
                API key is configured and encrypted. Leave blank to keep current key.
            <?php else: ?>
                Enter your API key. It will be stored encrypted.
            <?php endif; ?>
            <br>
            <strong>Get your API key:</strong> <a href="<?php echo esc_url($docs_url); ?>" target="_blank" id="api-docs-link">API Key Documentation</a>
        </p>
        <?php
    }

    public function render_enable_auto_analysis_field() {
        $options = get_option($this->ai_option_name, ['enable_auto_analysis' => true]);
        $checked = isset($options['enable_auto_analysis']) && $options['enable_auto_analysis'];
        ?>
        <label>
            <input type="checkbox"
                   name="<?php echo esc_attr($this->ai_option_name); ?>[enable_auto_analysis]"
                   value="1"
                   <?php checked($checked, true); ?>>
            Automatically analyze receipts when uploaded
        </label>
        <p class="description">When enabled, receipt images will be sent to your selected AI provider for automatic data extraction.</p>
        <?php
    }

    public function render_confidence_threshold_field() {
        $options = get_option($this->ai_option_name, ['confidence_threshold' => 0.7]);
        $threshold = $options['confidence_threshold'] ?? 0.7;
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
