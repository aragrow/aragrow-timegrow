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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_settings_styles']);
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
        $ai_settings = get_option($this->ai_option_name, []);
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

            <form method="post" action="options.php" id="ai-settings-form">
                <?php
                settings_fields('aragrow_timegrow_ai_settings_group');
                do_settings_sections('aragrow-timegrow-settings-ai');
                ?>

                <div class="timegrow-footer">
                    <?php submit_button(__('Save AI Settings', 'timegrow'), 'primary large', 'submit', false); ?>
                    <a href="?page=<?php echo TIMEGROW_PARENT_MENU; ?>-settings" class="button button-secondary large">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        <?php esc_html_e('Back to Settings', 'timegrow'); ?>
                    </a>
                </div>
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
