<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowSettings {

    private $general_option_name = 'aragrow_timegrow_general_settings';
    const VOICE_OPTION = 'timegrow_voice_settings';
    const RECEIPT_OPTION = 'timegrow_receipt_settings';
    private $ai_option_name = 'aragrow_timegrow_ai_settings';
    private $ai_configs_option_name = 'aragrow_timegrow_ai_configurations';
    private $ai_form_temp_option_name = 'aragrow_timegrow_ai_form_temp'; // Temporary form data (cleared unless editing)

    public function __construct() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'handle_config_actions']);
        add_action('admin_init', [$this, 'handle_form_submission']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_settings_styles']);

        // Create AI config table if it doesn't exist
        if (class_exists('TimeGrowAIConfigModel')) {
            $model = new TimeGrowAIConfigModel();
            $model->create_table();
            timegrow_log('AI config table check/creation completed');
        }

        // One-time cleanup of old options (will only run once)
        if (!get_option('timegrow_ai_options_cleaned')) {
            if (class_exists('TimeGrowAIConfigModel')) {
                TimeGrowAIConfigModel::cleanup_old_options();
                update_option('timegrow_ai_options_cleaned', true);
                timegrow_log('AI options cleanup completed');
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
            filemtime(dirname(__DIR__) . '/assets/css/timegrow-modern.css') // new styles reach browsers right away
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
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        add_submenu_page(
            TIMEGROW_PARENT_MENU,
            'TimeGrow Settings',
            'Settings', // the one settings hub: General, AI, Voice Entry, Mobile Access, WooCommerce, PayPal
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings, sections, and fields
     */
    public function register_settings() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

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

        add_settings_field(
            'team_project_access',
            'Team members can record time on',
            [$this, 'render_team_project_access_field'],
            'aragrow-timegrow-settings-general',
            'general_settings_section'
        );

        add_settings_field(
            'delete_data_on_uninstall',
            'Delete data on uninstall',
            [$this, 'render_delete_data_field'],
            'aragrow-timegrow-settings-general',
            'general_settings_section'
        );

        // ========================================
        // RECEIPT ANALYSIS SETTINGS
        // ========================================
        register_setting(
            'timegrow_receipt_settings_group',
            self::RECEIPT_OPTION,
            ['sanitize_callback' => [$this, 'sanitize_receipt_settings']]
        );

        // ========================================
        // VOICE ENTRY SETTINGS
        // ========================================
        register_setting(
            'timegrow_voice_settings_group',
            self::VOICE_OPTION,
            ['sanitize_callback' => [$this, 'sanitize_voice_settings'], 'default' => self::voice_defaults()]
        );

        // ========================================
        // AI PROVIDER SETTINGS
        // ========================================
        // Saved by handle_form_submission() into the AI configurations table (TimeGrowAIConfigModel)
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
            'Default Configuration',
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
        // Reading receipts automatically and the confidence threshold are Receipt Analysis settings (RECEIPT_OPTION)
    }

    /**
     * AI providers and their models (TimeGrowAI: current models, plus any the provider listed
     * when a key was last tested).
     */
    private function get_ai_providers() {
        $providers = [];
        foreach (TimeGrowAI::providers() as $id => $p) {
            $providers[$id] = $p + ['models' => TimeGrowAI::model_options($id)];
        }
        return $providers;
    }

    /**
     * Sanitize general settings
     */
    public function sanitize_general_settings($input) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

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

        // Opt-in: uninstall.php only removes data when this is 'yes'
        // Only "yes" (the checkbox value) is on: WordPress runs this twice when the option is first created
        $sanitized['delete_data_on_uninstall'] = ($input['delete_data_on_uninstall'] ?? '') === 'yes' ? 'yes' : 'no';

        $sanitized['team_project_access'] = ($input['team_project_access'] ?? 'all') === 'assigned' ? 'assigned' : 'all';

        return $sanitized;
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

        timegrow_log('=== AI CONFIG FORM SUBMISSION DETECTED ===');

        // Verify nonce
        if (!wp_verify_nonce($_POST['timegrow_ai_config_nonce'], 'timegrow_save_ai_config')) {
            timegrow_log('Nonce verification failed');
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can(TIMEGROW_OWNER_CAP)) {
            timegrow_log('Insufficient permissions');
            wp_die('Insufficient permissions');
        }

        timegrow_log('Processing AI config form submission');

        // Get form data
        $provider = sanitize_text_field($_POST[$this->ai_option_name]['ai_provider'] ?? '');
        $model = sanitize_text_field($_POST[$this->ai_option_name]['ai_model'] ?? '');

        // Validate required fields: a known provider, and a model id (from the list, or one the provider has)
        if (!isset(TimeGrowAI::providers()[$provider])) {
            $provider = '';
        }
        if ($model !== '' && !preg_match('/^[a-z0-9][a-z0-9.\-]{1,80}$/i', $model)) {
            $model = '';
        }
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
            'is_active' => isset($_POST[$this->ai_option_name]['is_active_config']),
        ];


        // Handle API key - only update if a new one is provided
        $api_key = sanitize_text_field($_POST[$this->ai_option_name]['ai_api_key'] ?? '');
        if (!empty($api_key)) {
            $config_data['ai_api_key'] = TimeGrowAI::encrypt($api_key); // stored encrypted
            set_transient('timegrow_api_key_saved', true, 30);
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
            timegrow_log('Updated config ID: ' . $config_id);

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
            timegrow_log('Created new config ID: ' . $new_id);

            // Clear the editing transient
            delete_transient('timegrow_editing_config_' . get_current_user_id());

            wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=ai&created=1'));
        }
        exit;
    }

    /**
     * Handle configuration actions (activate, load, delete, clear)
     */
    /**
     * Link for a configuration action (test, activate, load, delete), with its nonce.
     */
    private function config_action_url($action, $config_id) {
        return wp_nonce_url(
            admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=ai&action=' . $action . '&config_id=' . (int) $config_id),
            'timegrow_ai_config_' . (int) $config_id
        );
    }

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
        timegrow_log('Config action: ' . $action);

        // Handle clear action (doesn't need config_id)
        if ($action === 'clear') {
            // Clear the editing transient
            delete_transient('timegrow_editing_config_' . get_current_user_id());
            timegrow_log('Form cleared - transient deleted');

            wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=ai'));
            exit;
        }

        // Other actions require config_id
        if (!isset($_GET['config_id'])) {
            timegrow_log('No config_id provided');
            return;
        }

        $config_id = intval($_GET['config_id']);
        timegrow_log('Config ID: ' . $config_id);

        $model = new TimeGrowAIConfigModel();
        $config = $model->get_by_id($config_id);

        if (!$config) {
            timegrow_log('Config ID not found in database');
            return;
        }

        timegrow_log('Config found, proceeding with action: ' . $action);

        // Every config action link carries a nonce (see config_action_url)
        check_admin_referer('timegrow_ai_config_' . $config_id);

        switch ($action) {
            case 'test':
                $result = TimeGrowAI::test($config);
                set_transient('timegrow_ai_test_' . get_current_user_id(), $result + ['config_name' => $config['config_name']], 60);
                wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=ai&tested=1&t=' . time()));
                exit;

            case 'activate':
                $model->set_active($config_id);
                timegrow_log('Activated config ID: ' . $config_id);

                wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=ai&activated=1&t=' . time()));
                exit;
                break;

            case 'load':
                // Store the config data in a transient for form population
                set_transient('timegrow_editing_config_' . get_current_user_id(), $config, 300);
                timegrow_log('Loading config ID ' . $config_id . ' for editing');

                wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=ai&loaded=1&edit_id=' . $config_id . '&t=' . time()));
                exit;
                break;

            case 'delete':
                timegrow_log('Deleting config: ' . $config_id);

                $model->delete($config_id);

                timegrow_log('Config deleted from database');
                wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=ai&deleted=1&t=' . time()));
                exit;
                break;
        }
    }

    /**
     * Render settings page with modern card-based design
     */
    public function render_settings_page() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

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
        } elseif ($active_tab === 'voice') {
            $this->render_voice_settings_form();
        } elseif ($active_tab === 'receipts') {
            $this->render_receipt_settings_form();
        } elseif ($active_tab === 'prompts') {
            TimeGrowAIPromptsPage::render();
        } elseif ($active_tab === 'conversations') {
            TimeGrowAIReviewPage::render();
        }
    }

    /**
     * Render overview page with setting cards
     */
    private function render_overview_page() {
        // Check AI configuration from new database table
        $has_ai_configured = TimeGrowAI::is_configured();
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

                <!-- AI Card: the provider every AI feature uses -->
                <a href="?page=<?php echo TIMEGROW_PARENT_MENU; ?>-settings&tab=ai" class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon <?php echo $has_ai_configured ? 'timegrow-icon-primary' : 'timegrow-icon-disabled'; ?>">
                            <span class="dashicons dashicons-analytics"></span>
                        </div>
                        <div class="timegrow-card-title">
                            <h2><?php esc_html_e('AI', 'timegrow'); ?></h2>
                            <?php
                            $ai_cfg    = TimeGrowAI::config();
                            $ai_status = TimeGrowAI::api_key($ai_cfg) !== '' ? TimeGrowAI::key_status($ai_cfg) : null;
                            if ($ai_status && $ai_status['ok'] === false) {
                                [$badge_class, $badge_text] = ['timegrow-badge-warning', __('Key not working', 'timegrow')];
                            } elseif ($has_ai_configured) {
                                [$badge_class, $badge_text] = ['timegrow-badge-success', __('Configured', 'timegrow')];
                            } else {
                                [$badge_class, $badge_text] = ['timegrow-badge-inactive', __('Not configured', 'timegrow')];
                            }
                            ?>
                            <span class="timegrow-badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html($badge_text); ?></span>
                        </div>
                    </div>
                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            <?php esc_html_e('Your AI providers, models and keys. Each AI feature uses the default or picks its own.', 'timegrow'); ?>
                        </p>
                        <div class="timegrow-features">
                            <div class="timegrow-feature-badge"><span class="dashicons dashicons-cloud"></span><span>Google Gemini</span></div>
                            <div class="timegrow-feature-badge"><span class="dashicons dashicons-cloud"></span><span>OpenAI</span></div>
                            <div class="timegrow-feature-badge"><span class="dashicons dashicons-cloud"></span><span>Anthropic Claude</span></div>
                        </div>
                        <div class="timegrow-card-footer">
                            <span class="timegrow-action-link">
                                <?php echo $has_ai_configured ? esc_html__('Manage AI', 'timegrow') : esc_html__('Set up AI', 'timegrow'); ?>
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                            </span>
                        </div>
                    </div>
                </a>

                <!-- Receipt Analysis Card -->
                <?php $receipts_on = self::receipt_settings()['auto_analysis'] === 'yes'; ?>
                <a href="?page=<?php echo TIMEGROW_PARENT_MENU; ?>-settings&tab=receipts" class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon <?php echo $receipts_on ? 'timegrow-icon-primary' : 'timegrow-icon-disabled'; ?>">
                            <span class="dashicons dashicons-media-document"></span>
                        </div>
                        <div class="timegrow-card-title">
                            <h2><?php esc_html_e('AI Receipt Analysis', 'timegrow'); ?></h2>
                            <span class="timegrow-badge <?php echo $receipts_on ? 'timegrow-badge-success' : 'timegrow-badge-inactive'; ?>">
                                <?php echo $receipts_on ? esc_html__('On', 'timegrow') : esc_html__('Off', 'timegrow'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            <?php esc_html_e('Read uploaded receipts with AI and fill in the expense: amount, date, vendor and category.', 'timegrow'); ?>
                        </p>
                        <div class="timegrow-features">
                            <div class="timegrow-feature-badge"><span class="dashicons dashicons-yes-alt"></span><span><?php esc_html_e('Fills in expenses', 'timegrow'); ?></span></div>
                            <div class="timegrow-feature-badge"><span class="dashicons dashicons-chart-bar"></span><span><?php esc_html_e('Confidence check', 'timegrow'); ?></span></div>
                        </div>
                        <div class="timegrow-card-footer">
                            <span class="timegrow-action-link">
                                <?php esc_html_e('Configure AI Receipt Analysis', 'timegrow'); ?>
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                            </span>
                        </div>
                    </div>
                </a>

                <!-- Voice Entry Card -->
                <?php $voice = self::voice_settings(); $voice_on = $voice['enabled'] === 'yes'; ?>
                <a href="?page=<?php echo TIMEGROW_PARENT_MENU; ?>-settings&tab=voice" class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon <?php echo $voice_on ? 'timegrow-icon-primary' : 'timegrow-icon-disabled'; ?>">
                            <span class="dashicons dashicons-microphone"></span>
                        </div>
                        <div class="timegrow-card-title">
                            <h2><?php esc_html_e('AI Voice Entry', 'timegrow'); ?></h2>
                            <span class="timegrow-badge <?php echo $voice_on ? 'timegrow-badge-success' : 'timegrow-badge-inactive'; ?>">
                                <?php echo $voice_on ? esc_html__('On', 'timegrow') : esc_html__('Off', 'timegrow'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            <?php esc_html_e('A voice and chat assistant on every admin page: records time, answers questions about your data, adds clients and projects.', 'timegrow'); ?>
                        </p>
                        <div class="timegrow-features">
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-microphone"></span>
                                <span><?php esc_html_e('Speech to text', 'timegrow'); ?></span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-analytics"></span>
                                <span><?php echo $voice['use_ai'] === 'yes' && TimeGrowAI::is_configured('voice') ? esc_html__('Understands conversation', 'timegrow') : esc_html__('Asks each question', 'timegrow'); ?></span>
                            </div>
                        </div>
                        <div class="timegrow-card-footer">
                            <span class="timegrow-action-link">
                                <?php esc_html_e('Configure AI Voice Entry', 'timegrow'); ?>
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                            </span>
                        </div>
                    </div>
                </a>

                <!-- Mobile Access Card -->
                <?php if (current_user_can('manage_options')): $sms_on = get_option('timegrow_mobile_sms_enabled') ? true : false; ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=timegrow-mobile-settings')); ?>" class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon timegrow-icon-primary">
                            <span class="dashicons dashicons-smartphone"></span>
                        </div>
                        <div class="timegrow-card-title">
                            <h2><?php esc_html_e('Mobile Access', 'timegrow'); ?></h2>
                            <span class="timegrow-badge <?php echo $sms_on ? 'timegrow-badge-success' : 'timegrow-badge-inactive'; ?>">
                                <?php echo $sms_on ? esc_html__('Text messages on', 'timegrow') : esc_html__('Text messages off', 'timegrow'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            <?php esc_html_e('Mobile login with a PIN for administrators and team members, what each person can use on mobile, and text message alerts.', 'timegrow'); ?>
                        </p>
                        <div class="timegrow-features">
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-lock"></span>
                                <span><?php esc_html_e('PIN login', 'timegrow'); ?></span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-microphone"></span>
                                <span><?php esc_html_e('AI Voice and AI Receipt Reader', 'timegrow'); ?></span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-email"></span>
                                <span><?php esc_html_e('Text messages', 'timegrow'); ?></span>
                            </div>
                        </div>
                        <div class="timegrow-card-footer">
                            <span class="timegrow-action-link">
                                <?php esc_html_e('Configure Mobile Access', 'timegrow'); ?>
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                            </span>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

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
            timegrow_log('Form temp data cleared on page load (not editing)');
        }

        if(timegrow_debug()) {
            timegrow_log('=== RENDERING AI SETTINGS FORM ===');
            timegrow_log('Total configs to display: ' . count($all_configs));
            foreach($all_configs as $id => $cfg) {
                timegrow_log('  - ' . $id . ': ' . ($cfg['config_name'] ?? 'Unnamed'));
            }
        }
        ?>
        <div class="wrap timegrow-page">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('AI', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('The AI provider, model and key that AI Receipt Analysis and AI Voice Entry use', 'timegrow'); ?></p>
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

            <?php $ai_test = isset($_GET['tested']) ? get_transient('timegrow_ai_test_' . get_current_user_id()) : false; ?>
            <?php if ($ai_test): delete_transient('timegrow_ai_test_' . get_current_user_id()); ?>
                <div class="notice notice-<?php echo $ai_test['ok'] === true ? 'success' : ($ai_test['ok'] === null ? 'warning' : 'error'); ?> is-dismissible">
                    <p><strong><?php echo esc_html($ai_test['config_name']); ?>:</strong> <?php echo esc_html($ai_test['message']); ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['activated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong>Default configuration changed.</strong> AI Receipt Analysis and AI Voice Entry use it unless they picked another one under "AI to use".</p>
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
                        <?php if(timegrow_debug()): ?>
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
                                <th>Used by</th>
                                <th>Key</th>
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
                                        <span class="timegrow-badge timegrow-badge-success"><?php esc_html_e('Default', 'timegrow'); ?></span>
                                    <?php endif; ?>
                                    <?php
                                    $users = [];
                                    foreach (['receipts' => __('AI Receipt Analysis', 'timegrow'), 'voice' => __('AI Voice Entry', 'timegrow')] as $feature => $feature_name) {
                                        if ((int) (TimeGrowAI::config($feature)['id'] ?? 0) === (int) $config_id) {
                                            $users[] = $feature_name;
                                        }
                                    }
                                    ?>
                                    <br><span class="description"><?php echo $users ? esc_html(implode(', ', $users)) : esc_html__('Not used', 'timegrow'); ?></span>
                                </td>
                                <td>
                                    <?php $key_status = TimeGrowAI::key_status(['id' => $config_id] + $config); ?>
                                    <?php if ($key_status === null): ?>
                                        <span class="description"><?php esc_html_e('Not tested', 'timegrow'); ?></span>
                                    <?php elseif ($key_status['ok'] === null): ?>
                                        <span class="dashicons dashicons-clock" style="color: #996800;"></span> <?php esc_html_e('Could not check', 'timegrow'); ?>
                                        <br><span class="description" title="<?php echo esc_attr($key_status['message']); ?>"><?php echo esc_html($key_status['checked_at']); ?></span>
                                    <?php elseif ($key_status['ok']): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span> <?php esc_html_e('Works', 'timegrow'); ?>
                                        <br><span class="description"><?php echo esc_html($key_status['checked_at']); ?></span>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-warning" style="color: #d63638;"></span> <?php esc_html_e('Not working', 'timegrow'); ?>
                                        <br><span class="description" title="<?php echo esc_attr($key_status['message']); ?>"><?php echo esc_html($key_status['checked_at']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo esc_url($this->config_action_url('test', $config_id)); ?>"
                                       class="button button-small">
                                        Test
                                    </a>
                                    <?php if (!($config['is_active_config'] ?? false)): ?>
                                        <a href="<?php echo esc_url($this->config_action_url('activate', $config_id)); ?>"
                                           class="button button-small">
                                            Make default
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url($this->config_action_url('load', $config_id)); ?>"
                                       class="button button-small">
                                        Edit
                                    </a>
                                    <a href="<?php echo esc_url($this->config_action_url('delete', $config_id)); ?>"
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
            <?php if (timegrow_debug()):
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

    // ========================================
    // VOICE ENTRY
    // ========================================

    // ========================================
    // AI RECEIPT ANALYSIS
    // ========================================

    /**
     * Receipt settings: read uploaded receipts with AI, and the confidence below which the expense
     * is filled in with a warning. Until first saved, they carry over the values the active AI
     * configuration had (they used to live there).
     */
    public static function receipt_settings() {
        $saved = get_option(self::RECEIPT_OPTION, null);
        if (is_array($saved)) {
            return wp_parse_args($saved, ['auto_analysis' => 'yes', 'confidence_threshold' => 0.7, 'ai_config_id' => 0, 'save_for_review' => 'yes', 'keep_days' => 90]);
        }
        $row = class_exists('TimeGrowAIConfigModel') ? (new TimeGrowAIConfigModel())->get_active() : null;
        return [
            'auto_analysis'        => ($row === null || !empty($row['enable_auto_analysis'])) ? 'yes' : 'no',
            'confidence_threshold' => isset($row['confidence_threshold']) ? (float) $row['confidence_threshold'] : 0.7,
            'ai_config_id'         => 0, // 0 = the default AI configuration
            'save_for_review'      => 'yes', // readings saved for Settings > AI Conversations
            'keep_days'            => 90,
        ];
    }

    public function sanitize_receipt_settings($input) {
        $input = is_array($input) ? $input : [];
        return [
            // Only "yes" (the checkbox value) is on: WordPress runs this twice when the option is first created
            'auto_analysis'        => ($input['auto_analysis'] ?? '') === 'yes' ? 'yes' : 'no',
            'confidence_threshold' => round(max(0.0, min(1.0, (float) ($input['confidence_threshold'] ?? 0.7))), 2),
            'ai_config_id'         => self::valid_ai_config_id($input['ai_config_id'] ?? 0),
            'save_for_review'      => ($input['save_for_review'] ?? '') === 'yes' ? 'yes' : 'no',
            'keep_days'            => self::valid_keep_days($input['keep_days'] ?? 90),
        ];
    }

    /** Days to keep saved AI activity: 0 (keep) to 3650. */
    private static function valid_keep_days($days) {
        return max(0, min(3650, (int) $days));
    }

    /**
     * "Save for review" and "Keep for" for an AI tool, with a link to its saved sessions
     * (Settings > AI Conversations).
     */
    private function render_review_settings($feature, $option, array $settings) {
        $voice = $feature === 'voice';
        ?>
        <div class="timegrow-card">
            <div class="timegrow-card-header">
                <div class="timegrow-icon timegrow-icon-primary"><span class="dashicons dashicons-format-chat"></span></div>
                <div class="timegrow-card-title"><h2><?php esc_html_e('Review', 'timegrow'); ?></h2></div>
            </div>
            <div class="timegrow-card-body">
                <div class="timegrow-feature-item">
                    <label class="timegrow-toggle-switch">
                        <input type="checkbox" name="<?php echo esc_attr($option); ?>[save_for_review]" value="yes" <?php checked($settings['save_for_review'] ?? 'yes', 'yes'); ?>>
                        <span class="timegrow-toggle-slider"></span>
                    </label>
                    <div class="timegrow-feature-info">
                        <strong><?php echo $voice ? esc_html__('Save conversations for review', 'timegrow') : esc_html__('Save receipt readings for review', 'timegrow'); ?></strong>
                        <p><?php echo $voice
                            ? esc_html__('Each conversation is saved: what was said, how the AI read each message, tokens and time, and what was saved. Administrators can read and rate them under AI Conversations. The chat tells people their conversations are saved.', 'timegrow')
                            : esc_html__('Each receipt reading is saved: the file name, what the AI read, what was filled in, tokens and time. Administrators can read and rate them under AI Conversations. The image itself is not copied.', 'timegrow'); ?></p>
                    </div>
                </div>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="timegrow_<?php echo esc_attr($feature); ?>_keep_days"><?php esc_html_e('Keep for', 'timegrow'); ?></label></th>
                        <td>
                            <input type="number" id="timegrow_<?php echo esc_attr($feature); ?>_keep_days" name="<?php echo esc_attr($option); ?>[keep_days]" min="0" max="3650" step="1" value="<?php echo esc_attr((int) ($settings['keep_days'] ?? 90)); ?>" class="small-text"> <?php esc_html_e('days', 'timegrow'); ?>
                            <p class="description"><?php esc_html_e('Older ones are deleted each day. 0 keeps them until you delete them.', 'timegrow'); ?>
                                <a href="<?php echo esc_url(TimeGrowAIReviewPage::url(['feature' => $feature])); ?>"><?php esc_html_e('Open AI Conversations', 'timegrow'); ?></a></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }

    /** A saved AI configuration's ID, or 0 (use the default) for anything else. */
    private static function valid_ai_config_id($id) {
        $id = (int) $id;
        return ($id > 0 && class_exists('TimeGrowAIConfigModel') && (new TimeGrowAIConfigModel())->get_by_id($id)) ? $id : 0;
    }

    /**
     * The AI configuration a feature chose under "AI to use", or 0 for the default.
     *
     * @param string $feature "receipts" or "voice".
     */
    public static function feature_ai_config_id($feature) {
        $settings = $feature === 'receipts' ? self::receipt_settings() : ($feature === 'voice' ? self::voice_settings() : []);
        return (int) ($settings['ai_config_id'] ?? 0);
    }

    /**
     * The "AI to use" dropdown for a feature: Use the default, or any saved configuration, each with
     * its key status. A note shows when the feature's choice was deleted and the default is used.
     */
    private function render_ai_choice($feature, $option_name, $selected) {
        $configs = TimeGrowAI::configurations();
        $choice  = TimeGrowAI::choice($feature);
        $default = null;
        foreach ($configs as $c) {
            if ($c['is_default']) {
                $default = $c;
            }
        }
        $state = function ($status) {
            if ($status === null) {
                return __('not tested', 'timegrow');
            }
            return $status['ok'] === true ? __('key works', 'timegrow') : ($status['ok'] === false ? __('key not working', 'timegrow') : __('could not check', 'timegrow'));
        };
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="timegrow_<?php echo esc_attr($feature); ?>_ai_config"><?php esc_html_e('AI to use', 'timegrow'); ?></label></th>
                <td>
                    <select id="timegrow_<?php echo esc_attr($feature); ?>_ai_config" name="<?php echo esc_attr($option_name); ?>[ai_config_id]" class="regular-text" style="min-width: 420px;">
                        <option value="0" <?php selected((int) $selected, 0); ?>>
                            <?php echo esc_html($default
                                ? sprintf(__('Use the default (%1$s, %2$s)', 'timegrow'), $default['label'], $state($default['status']))
                                : __('Use the default', 'timegrow')); ?>
                        </option>
                        <?php foreach ($configs as $c): ?>
                            <option value="<?php echo esc_attr($c['id']); ?>" <?php selected((int) $selected, $c['id']); ?>>
                                <?php echo esc_html($c['label'] . ' (' . $state($c['status']) . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($choice['fell_back']): ?>
                        <p class="description" style="color: #996800;"><?php esc_html_e('The configuration chosen here was deleted, so the default is used. Pick another one and save.', 'timegrow'); ?></p>
                    <?php else: ?>
                        <p class="description"><?php esc_html_e('Pick any configuration saved under Settings > AI. Save to check the new choice below.', 'timegrow'); ?></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    private function render_receipt_settings_form() {
        $settings = self::receipt_settings();
        $ai       = TimeGrowAI::config('receipts');
        $status   = $this->check_ai_now('receipts'); // every time this page loads
        $ai_url   = admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=ai');
        $percent  = (int) round($settings['confidence_threshold'] * 100);
        ?>
        <div class="wrap timegrow-page">
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('AI Receipt Analysis', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Read uploaded receipts with AI and fill in the expense for you', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-media-document"></span>
                </div>
            </div>

            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Receipt analysis settings saved.', 'timegrow'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('timegrow_receipt_settings_group'); ?>

                <div class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon timegrow-icon-primary"><span class="dashicons dashicons-media-document"></span></div>
                        <div class="timegrow-card-title"><h2><?php esc_html_e('Receipts', 'timegrow'); ?></h2></div>
                    </div>
                    <div class="timegrow-card-body">
                        <div class="timegrow-feature-item">
                            <label class="timegrow-toggle-switch">
                                <input type="checkbox" name="<?php echo esc_attr(self::RECEIPT_OPTION); ?>[auto_analysis]" value="yes" <?php checked($settings['auto_analysis'], 'yes'); ?>>
                                <span class="timegrow-toggle-slider"></span>
                            </label>
                            <div class="timegrow-feature-info">
                                <strong><?php esc_html_e('Read receipts with AI', 'timegrow'); ?></strong>
                                <p><?php esc_html_e('When a receipt photo is uploaded with an expense, AI reads it and fills in the amount, date, vendor, category and description. Check them before saving.', 'timegrow'); ?></p>
                            </div>
                        </div>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="timegrow_receipt_threshold"><?php esc_html_e('Confidence threshold', 'timegrow'); ?></label></th>
                                <td>
                                    <input type="range" id="timegrow_receipt_threshold" min="0.5" max="1" step="0.05"
                                           name="<?php echo esc_attr(self::RECEIPT_OPTION); ?>[confidence_threshold]"
                                           value="<?php echo esc_attr($settings['confidence_threshold']); ?>"
                                           oninput="this.nextElementSibling.textContent = Math.round(this.value * 100) + '%'"
                                           style="width: 240px; vertical-align: middle;">
                                    <strong style="margin-left: 10px;"><?php echo esc_html($percent); ?>%</strong>
                                    <p class="description"><?php esc_html_e('When the AI is less sure than this, the details are still filled in, with a warning to check every field.', 'timegrow'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon timegrow-icon-primary"><span class="dashicons dashicons-analytics"></span></div>
                        <div class="timegrow-card-title"><h2><?php esc_html_e('AI', 'timegrow'); ?></h2></div>
                    </div>
                    <div class="timegrow-card-body">
                        <?php $this->render_ai_choice('receipts', self::RECEIPT_OPTION, $settings['ai_config_id']); ?>
                        <?php $this->render_ai_status('receipts', $settings['auto_analysis'] === 'yes', $ai, $status, $ai_url, admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=receipts')); ?>
                    </div>
                </div>

                <?php $this->render_review_settings('receipts', self::RECEIPT_OPTION, $settings); ?>

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

    // ========================================
    // AI VOICE ENTRY
    // ========================================

    public static function voice_defaults() {
        return [
            'enabled'        => 'yes', // show "Speak an entry" on the Manual Time Entry page
            'use_ai'         => 'yes', // read whole sentences with the active AI configuration
            'speak'          => 'yes', // speak the questions out loud by default
            'ask_start_time' => 'yes', // ask what time the work started
            'language'       => '',    // speech recognition language; '' = the site language
            'agent_name'     => 'Sam Spade', // the assistant's name, in the chat and known to the AI
            'voice_name'     => '',    // the voice it speaks with (a browser voice name); '' = the most natural one available
            'ai_config_id'   => 0,     // AI to use: 0 = the default AI configuration
            'save_for_review' => 'yes', // conversations saved for Settings > AI Conversations
            'keep_days'      => 90,
        ];
    }

    /** Speech languages offered. Keys are BCP 47 tags the browser understands. */
    public static function voice_languages() {
        return [
            ''      => __('Site language', 'timegrow'),
            'en-US' => 'English (United States)',
            'en-GB' => 'English (United Kingdom)',
            'es-US' => 'Español (Estados Unidos)',
            'es-MX' => 'Español (México)',
            'es-ES' => 'Español (España)',
            'fr-FR' => 'Français',
            'de-DE' => 'Deutsch',
            'pt-BR' => 'Português (Brasil)',
            'it-IT' => 'Italiano',
        ];
    }

    public static function voice_settings() {
        $saved = get_option(self::VOICE_OPTION, []);
        return wp_parse_args(is_array($saved) ? $saved : [], self::voice_defaults());
    }

    /** The speech language to use: the setting, or the site language (en_US becomes en-US). */
    public static function voice_language() {
        $lang = self::voice_settings()['language'];
        return $lang !== '' ? $lang : str_replace('_', '-', get_locale());
    }

    public function sanitize_voice_settings($input) {
        $input = is_array($input) ? $input : [];
        $out   = [];
        foreach (['enabled', 'use_ai', 'speak', 'ask_start_time', 'save_for_review'] as $key) {
            // Only "yes" (the checkbox value) is on: WordPress runs this twice when the option is first created
            $out[$key] = ($input[$key] ?? '') === 'yes' ? 'yes' : 'no';
        }
        $lang            = sanitize_text_field($input['language'] ?? '');
        $out['language'] = array_key_exists($lang, self::voice_languages()) ? $lang : '';
        $out['agent_name']   = self::valid_agent_name($input['agent_name'] ?? '');
        $out['voice_name']   = mb_substr(sanitize_text_field($input['voice_name'] ?? ''), 0, 200);
        $out['ai_config_id'] = self::valid_ai_config_id($input['ai_config_id'] ?? 0);
        $out['keep_days']    = self::valid_keep_days($input['keep_days'] ?? 90);
        return $out;
    }

    /** The assistant's name: letters, spaces, apostrophes, periods and hyphens, up to 40 characters; else the default. Pure. */
    public static function valid_agent_name($name) {
        $name = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $name)));
        if ($name === '' || mb_strlen($name) > 40 || !preg_match("/^[\p{L}][\p{L}\p{M} '.\-]*$/u", $name)) {
            return self::voice_defaults()['agent_name'];
        }
        return $name;
    }

    private function voice_toggle($key, $title, $description, $settings) {
        ?>
        <div class="timegrow-feature-item">
            <label class="timegrow-toggle-switch">
                <input type="checkbox" name="<?php echo esc_attr(self::VOICE_OPTION . '[' . $key . ']'); ?>" value="yes" <?php checked($settings[$key], 'yes'); ?>>
                <span class="timegrow-toggle-slider"></span>
            </label>
            <div class="timegrow-feature-info">
                <strong><?php echo esc_html($title); ?></strong>
                <p><?php echo esc_html($description); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * The status card for a feature that uses the AI from Settings > AI (receipts or voice):
     * headline, what it means for the feature, a small diagram, details and one fix button.
     * $status is the key test that ran when the page loaded (null when no key is saved).
     *
     * @param string $feature "receipts" or "voice".
     * @param bool   $use_ai  Whether the feature's own AI switch is on.
     */
    private function render_ai_status($feature, $use_ai, $ai, $status, $ai_url, $again_url) {
        $providers = TimeGrowAI::providers();
        $provider  = $providers[$ai['ai_provider']]['name'] ?? __('the AI provider', 'timegrow');
        $company   = ['google_gemini' => 'Google', 'openai' => 'OpenAI', 'anthropic' => 'Anthropic'][$ai['ai_provider']] ?? $provider;
        $model     = TimeGrowAI::model_name($ai['ai_provider'], $ai['ai_model']);

        $words = [
            'voice' => [
                'without'  => __('the chat asks each question one by one and only understands plain questions', 'timegrow'),
                'off'      => [__('Conversation with AI is off', 'timegrow'), __('The chat asks each question one by one and only understands plain questions like "how many hours this week". Turn the switch above on to talk to it naturally.', 'timegrow')],
                'ready'    => __('The voice assistant holds a conversation with %1$s (%2$s): it records time from what you say, answers questions about your TimeGrow data, and adds clients and projects for administrators, always after you confirm. Checked just now.', 'timegrow'),
                'uses'     => __('The voice assistant still uses AI.', 'timegrow'),
                'ai_ok'    => __('Understands the conversation', 'timegrow'),
                'ai_no'    => __('Asking each question', 'timegrow'),
                'steps'    => [['microphone', __('You talk or type', 'timegrow'), __('From any admin page', 'timegrow')],
                               ['yes-alt', __('Done for you', 'timegrow'), __('Time, answers, clients and projects', 'timegrow')]],
            ],
            'receipts' => [
                'without'  => __('receipts aren\'t read automatically, so expenses are filled in by hand', 'timegrow'),
                'off'      => [__('Reading receipts with AI is off', 'timegrow'), __('Expenses are filled in by hand. Turn the switch above on to have uploaded receipts read for you.', 'timegrow')],
                'ready'    => __('Uploaded receipts are read with %1$s (%2$s): amount, date, vendor and category fill in for you. Checked just now.', 'timegrow'),
                'uses'     => __('Receipts are still sent to AI.', 'timegrow'),
                'ai_ok'    => __('Reads amount, date, vendor', 'timegrow'),
                'ai_no'    => __('Not reading receipts', 'timegrow'),
                'steps'    => [['format-image', __('Receipt photo', 'timegrow'), __('Uploaded with the expense', 'timegrow')],
                               ['money-alt', __('Expense', 'timegrow'), __('Filled in for you to check', 'timegrow')]],
            ],
        ][$feature];
        $fallback = sprintf(__('Until then, %s.', 'timegrow'), $words['without']);
        $why      = ['', ''];

        if (!$use_ai) {
            $box = ['tone' => 'off', 'icon' => 'marker', 'title' => $words['off'][0], 'text' => $words['off'][1]];
        } elseif ($status === null) {
            $box = ['tone' => 'off', 'icon' => 'admin-plugins', 'title' => __('No AI set up yet', 'timegrow'),
                    'text' => ucfirst($words['without']) . '. ' . __('Add an AI provider and key in AI settings to turn this on.', 'timegrow'),
                    'button' => __('Set up AI', 'timegrow'), 'pill' => __('Not set up', 'timegrow')];
        } elseif ($status['ok'] === true) {
            $box = ['tone' => 'ok', 'icon' => 'yes-alt', 'title' => __('AI is ready', 'timegrow'),
                    'text' => sprintf($words['ready'], $provider, $model), 'link' => __('Open AI settings', 'timegrow')];
        } elseif ($status['ok'] === null) {
            $box = ['tone' => 'warn', 'icon' => 'clock', 'title' => __('Couldn\'t check AI right now', 'timegrow'),
                    'text' => sprintf(__('%s didn\'t answer just now, so the key wasn\'t checked.', 'timegrow'), $company) . ' ' . $words['uses'] . ' ' . __('This page checks again each time it opens.', 'timegrow'),
                    'link' => __('Open AI settings', 'timegrow')];
        } else {
            $reasons = [
                'key'      => [sprintf(__('%s rejected the API key', 'timegrow'), $company),
                               sprintf(__('The key may have been revoked or copied incorrectly. Create a new key with %s and save it in AI settings.', 'timegrow'), $company)],
                'model'    => [__('the chosen model isn\'t available', 'timegrow'),
                               sprintf(__('The key works, but %s isn\'t available to it. Pick another model in AI settings.', 'timegrow'), $model)],
                'no_key'   => [__('no API key is saved', 'timegrow'), __('Add the API key in AI settings.', 'timegrow')],
                'no_model' => [__('no model is chosen', 'timegrow'), __('Pick a model in AI settings.', 'timegrow')],
            ];
            $why = $reasons[$status['reason']] ?? [__('it needs attention', 'timegrow'), $status['message']];
            $box = ['tone' => 'bad', 'icon' => 'warning', 'title' => $why[0],
                    'text' => $why[1] . ' ' . $fallback, 'button' => $status['reason'] === 'key' ? __('Fix the API key', 'timegrow') : __('Open AI settings', 'timegrow')];
        }

        $pills   = ['ok' => __('Connected', 'timegrow'), 'warn' => __('Not checked', 'timegrow'), 'bad' => __('AI isn\'t working', 'timegrow'), 'off' => __('Off', 'timegrow')];
        $marks   = ['ok' => '&#10003;', 'warn' => '?', 'bad' => '!', 'off' => '&ndash;'];
        $ai_say  = ['ok' => $words['ai_ok'], 'warn' => __('Couldn\'t be checked', 'timegrow'), 'bad' => $words['ai_no'], 'off' => $words['ai_no']];
        $title   = $box['tone'] === 'bad' ? ucfirst($why[0]) : $box['title'];
        $checked = ($status && !empty($status['checked_at'])) ? date_i18n(get_option('time_format'), strtotime($status['checked_at'])) : '';
        [$first, $last] = $words['steps'];
        ?>
        <div class="tg-ai-status tg-ai-status--<?php echo esc_attr($box['tone']); ?>" role="status" aria-live="polite">
            <div class="tg-ai-status__hero">
                <div class="tg-ai-status__icon"><span class="dashicons dashicons-<?php echo esc_attr($box['icon']); ?>" aria-hidden="true"></span></div>
                <div>
                    <span class="tg-ai-status__pill"><?php echo esc_html($box['pill'] ?? $pills[$box['tone']]); ?></span>
                    <h3 class="tg-ai-status__title"><?php echo esc_html($title); ?></h3>
                    <p class="tg-ai-status__text"><?php echo esc_html($box['text']); ?></p>
                </div>
            </div>

            <div class="tg-ai-status__flow" aria-label="<?php esc_attr_e('How this uses AI', 'timegrow'); ?>">
                <div class="tg-ai-status__step">
                    <span class="tg-ai-status__step-icon"><span class="dashicons dashicons-<?php echo esc_attr($first[0]); ?>" aria-hidden="true"></span></span>
                    <span class="tg-ai-status__step-label"><strong><?php echo esc_html($first[1]); ?></strong><?php echo esc_html($first[2]); ?></span>
                </div>
                <span class="tg-ai-status__link" aria-hidden="true"></span>
                <div class="tg-ai-status__step tg-ai-status__step--ai">
                    <span class="tg-ai-status__step-icon"><span class="dashicons dashicons-analytics" aria-hidden="true"></span><span class="tg-ai-status__step-badge" aria-hidden="true"><?php echo $marks[$box['tone']]; ?></span></span>
                    <span class="tg-ai-status__step-label"><strong><?php echo esc_html($status === null && $use_ai ? __('AI', 'timegrow') : $model); ?></strong><?php echo esc_html($ai_say[$box['tone']]); ?></span>
                </div>
                <span class="tg-ai-status__link" aria-hidden="true"></span>
                <div class="tg-ai-status__step">
                    <span class="tg-ai-status__step-icon"><span class="dashicons dashicons-<?php echo esc_attr($last[0]); ?>" aria-hidden="true"></span></span>
                    <span class="tg-ai-status__step-label"><strong><?php echo esc_html($last[1]); ?></strong><?php echo esc_html($last[2]); ?></span>
                </div>
            </div>

            <?php if ($status !== null || $ai['config_name'] !== ''): ?>
            <div class="tg-ai-status__meta">
                <span class="tg-ai-status__chip"><span class="dashicons dashicons-cloud" aria-hidden="true"></span><?php esc_html_e('Provider', 'timegrow'); ?> <b><?php echo esc_html($provider); ?></b></span>
                <?php if ($ai['ai_model'] !== ''): ?>
                    <span class="tg-ai-status__chip"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span><?php esc_html_e('Model', 'timegrow'); ?> <b><?php echo esc_html($model); ?></b></span>
                <?php endif; ?>
                <?php if ($checked !== ''): ?>
                    <span class="tg-ai-status__chip"><span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e('Checked', 'timegrow'); ?> <b><?php echo esc_html($checked); ?></b></span>
                <?php endif; ?>
                <?php if ($ai['config_name'] !== ''): ?>
                    <span class="tg-ai-status__chip"><span class="dashicons dashicons-admin-settings" aria-hidden="true"></span><?php esc_html_e('Configuration', 'timegrow'); ?> <b><?php echo esc_html($ai['config_name']); ?></b></span>
                <?php endif; ?>
                <span class="tg-ai-status__chip"><span class="dashicons dashicons-editor-code" aria-hidden="true"></span><?php esc_html_e('Prompt', 'timegrow'); ?> <b><?php echo esc_html(TimeGrowAIPromptsPage::in_use_label($feature)); ?></b></span>
            </div>
            <?php endif; ?>

            <div class="tg-ai-status__actions">
                <?php if (!empty($box['button'])): ?>
                    <a href="<?php echo esc_url($ai_url); ?>" class="button button-primary"><span class="dashicons dashicons-admin-network" aria-hidden="true"></span><?php echo esc_html($box['button']); ?></a>
                <?php elseif (!empty($box['link'])): ?>
                    <a href="<?php echo esc_url($ai_url); ?>" class="button"><span class="dashicons dashicons-admin-settings" aria-hidden="true"></span><?php echo esc_html($box['link']); ?></a>
                <?php endif; ?>
                <?php if ($status !== null && $use_ai): ?>
                    <a href="<?php echo esc_url($again_url); ?>" class="button"><span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e('Check again', 'timegrow'); ?></a>
                <?php endif; ?>
                <a href="<?php echo esc_url(TimeGrowAIPromptsPage::url(['feature' => $feature])); ?>" class="button"><span class="dashicons dashicons-editor-code" aria-hidden="true"></span><?php esc_html_e('Edit prompt', 'timegrow'); ?></a>
                <a href="<?php echo esc_url(TimeGrowAIReviewPage::url(['feature' => $feature])); ?>" class="button"><span class="dashicons dashicons-format-chat" aria-hidden="true"></span><?php esc_html_e('Review conversations', 'timegrow'); ?></a>
            </div>
        </div>
        <?php
    }

    /**
     * Test the key of the configuration a feature uses (its own choice, else the default) now, a
     * tiny free request, and return the result, or null when no key is saved. Feature pages call
     * this each time they load.
     */
    private function check_ai_now($feature) {
        $row = TimeGrowAI::choice($feature)['row'];
        return $row && TimeGrowAI::api_key(TimeGrowAI::normalize($row)) !== '' ? TimeGrowAI::test($row) : null;
    }

    private function render_voice_settings_form() {
        $settings = self::voice_settings();
        $ai       = TimeGrowAI::config('voice');
        $status   = $this->check_ai_now('voice'); // every time this page loads
        $ai_url   = admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=ai');
        ?>
        <div class="wrap timegrow-page">
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('AI Voice Entry', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Talk to TimeGrow from any admin page: record time, ask about your data, add clients and projects', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-microphone"></span>
                </div>
            </div>

            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Voice entry settings saved.', 'timegrow'); ?></p></div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php settings_fields('timegrow_voice_settings_group'); ?>

                <div class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon timegrow-icon-primary"><span class="dashicons dashicons-microphone"></span></div>
                        <div class="timegrow-card-title"><h2><?php esc_html_e('Voice entry', 'timegrow'); ?></h2></div>
                    </div>
                    <div class="timegrow-card-body">
                        <?php
                        $this->voice_toggle('enabled', __('Turn voice entry on', 'timegrow'),
                            __('Shows a microphone icon in the corner of every admin page for people who can record time. The chat records time (client, project, day, hours, billable and description), punches people in and out, answers questions about TimeGrow data, and lets administrators add clients and projects. Nothing is saved until the person confirms. Speech is turned into text by the browser: in Chrome and Edge that is done by Google or Microsoft, in Safari by Apple. People can always type instead.', 'timegrow'), $settings);
                        $this->voice_toggle('speak', __('Speak the questions out loud', 'timegrow'),
                            __('The chat reads its questions aloud. Each person can still turn the sound off in the chat; their choice is remembered on their device.', 'timegrow'), $settings);
                        ?>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="timegrow_voice_agent_name"><?php esc_html_e('Assistant name', 'timegrow'); ?></label></th>
                                <td>
                                    <input type="text" id="timegrow_voice_agent_name" class="regular-text" maxlength="40" name="<?php echo esc_attr(self::VOICE_OPTION); ?>[agent_name]" value="<?php echo esc_attr($settings['agent_name']); ?>" />
                                    <p class="description"><?php esc_html_e('Shown in the chat and used when it introduces itself. People can call it by name ("Sam, how many hours this week?"). Letters only, up to 40 characters.', 'timegrow'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="timegrow_voice_voice_name"><?php esc_html_e('Voice', 'timegrow'); ?></label></th>
                                <td>
                                    <select id="timegrow_voice_voice_name" name="<?php echo esc_attr(self::VOICE_OPTION); ?>[voice_name]" data-lang="<?php echo esc_attr(self::voice_language()); ?>">
                                        <option value=""><?php esc_html_e('Automatic: the most natural voice on each device', 'timegrow'); ?></option>
                                        <?php if ($settings['voice_name'] !== '') : ?>
                                            <option value="<?php echo esc_attr($settings['voice_name']); ?>" selected><?php echo esc_html($settings['voice_name']); ?></option>
                                        <?php endif; ?>
                                    </select>
                                    <button type="button" class="button" id="timegrow_voice_try"><?php esc_html_e('Try it', 'timegrow'); ?></button>
                                    <p class="description"><?php esc_html_e('Voices come from each person\'s browser and device, so the list shows the voices on this one (★ = natural sounding). When someone\'s device doesn\'t have the chosen voice, the most natural one it has is used. Each person can still pick their own voice in the chat.', 'timegrow'); ?></p>
                                    <script>
                                    (function () {
                                        var sel = document.getElementById('timegrow_voice_voice_name'), synth = window.speechSynthesis;
                                        if (!sel || !synth) { return; }
                                        var saved = <?php echo wp_json_encode($settings['voice_name']); ?>, lang = (sel.getAttribute('data-lang') || 'en-US').toLowerCase().split('-')[0];
                                        var natural = function (v) { return /natural|neural|premium|enhanced|^google/i.test(v.name); };
                                        function fill() {
                                            var voices = (synth.getVoices() || []).filter(function (v) { return String(v.lang || '').toLowerCase().indexOf(lang) === 0; });
                                            if (!voices.length) { return; }
                                            voices.sort(function (a, b) { return (natural(b) - natural(a)) || a.name.localeCompare(b.name); });
                                            while (sel.options.length > 1) { sel.remove(1); }
                                            voices.forEach(function (v) { var o = new Option(v.name.replace(/\s*\(.*\)$/, '') + ' (' + v.lang + ')' + (natural(v) ? ' ★' : ''), v.name); sel.add(o); });
                                            if (saved && !voices.some(function (v) { return v.name === saved; })) { sel.add(new Option(saved + ' (not on this device)', saved)); }
                                            sel.value = saved || '';
                                        }
                                        fill();
                                        if ('onvoiceschanged' in synth) { synth.addEventListener('voiceschanged', fill); }
                                        sel.addEventListener('change', function () { saved = sel.value; });
                                        document.getElementById('timegrow_voice_try').addEventListener('click', function () {
                                            var name = (document.getElementById('timegrow_voice_agent_name').value || 'Sam Spade').split(' ')[0];
                                            var u = new SpeechSynthesisUtterance("Hi, I'm " + name + ". This is how I'll sound when I ask about your time.");
                                            var v = (synth.getVoices() || []).filter(function (x) { return x.name === sel.value; })[0];
                                            if (v) { u.voice = v; u.lang = v.lang; }
                                            u.rate = 1.03;
                                            synth.cancel(); synth.speak(u);
                                        });
                                    })();
                                    </script>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="timegrow_voice_language"><?php esc_html_e('Speech language', 'timegrow'); ?></label></th>
                                <td>
                                    <select id="timegrow_voice_language" name="<?php echo esc_attr(self::VOICE_OPTION); ?>[language]">
                                        <?php foreach (self::voice_languages() as $code => $label): ?>
                                            <option value="<?php echo esc_attr($code); ?>" <?php selected($settings['language'], $code); ?>><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description">
                                        <?php echo esc_html(sprintf(__('The language people speak in. Site language is %s here. The chat\'s questions are in English.', 'timegrow'), str_replace('_', '-', get_locale()))); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon timegrow-icon-primary"><span class="dashicons dashicons-analytics"></span></div>
                        <div class="timegrow-card-title"><h2><?php esc_html_e('AI', 'timegrow'); ?></h2></div>
                    </div>
                    <div class="timegrow-card-body">
                        <?php
                        $this->voice_toggle('use_ai', __('Hold a conversation with AI', 'timegrow'),
                            __('People talk naturally: several details at once ("two hours yesterday on the SEO audit for Copperleaf"), corrections ("actually three hours"), follow-ups ("and last week?") and questions in the middle of an entry. The AI only works out what they mean; TimeGrow checks their permissions and finds the answers, and the data found is not sent to the AI. Only the person\'s own projects, their clients and the conversation are sent. When off, or when the AI is not working, the chat asks each question.', 'timegrow'), $settings);
                        ?>
                        <?php $this->render_ai_choice('voice', self::VOICE_OPTION, $settings['ai_config_id']); ?>
                        <?php $this->render_ai_status('voice', $settings['use_ai'] === 'yes', $ai, $status, $ai_url, admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings&tab=voice')); ?>
                    </div>
                </div>

                <?php $this->render_review_settings('voice', self::VOICE_OPTION, $settings); ?>

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
     * Which projects team members (not administrators) may record time on: "all" active projects
     * (default) or only the projects "assigned" to them under Team Members.
     */
    public static function team_project_access() {
        $options = get_option('aragrow_timegrow_general_settings', []);
        return (is_array($options) && ($options['team_project_access'] ?? 'all') === 'assigned') ? 'assigned' : 'all';
    }

    public function render_team_project_access_field() {
        $value = self::team_project_access();
        $name  = esc_attr($this->general_option_name) . '[team_project_access]';
        ?>
        <fieldset>
            <label style="display: block; margin-bottom: 6px;">
                <input type="radio" name="<?php echo $name; ?>" value="all" <?php checked($value, 'all'); ?>>
                <?php esc_html_e('All active projects', 'timegrow'); ?>
            </label>
            <label style="display: block;">
                <input type="radio" name="<?php echo $name; ?>" value="assigned" <?php checked($value, 'assigned'); ?>>
                <?php esc_html_e('Only the projects assigned to them under Team Members', 'timegrow'); ?>
            </label>
        </fieldset>
        <p class="description"><?php esc_html_e('Applies to Clock In / Out, Manual Time Entry and voice entry. Administrators can always record time on any active project, for any team member.', 'timegrow'); ?></p>
        <?php
    }

    public function render_delete_data_field() {
        $options = get_option($this->general_option_name, []);
        $value   = $options['delete_data_on_uninstall'] ?? 'no';
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr($this->general_option_name); ?>[delete_data_on_uninstall]" value="yes" <?php checked($value, 'yes'); ?>>
            Delete all TimeGrow data when the plugin is deleted
        </label>
        <p class="description">Removes TimeGrow's tables (time entries, projects, clients, expenses, team members), settings, user profile fields, roles and capabilities. WooCommerce orders and products are kept. This cannot be undone.</p>
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
        <p class="description">Choose the AI provider for receipts and voice entry. <strong style="color: #d63638;">*Required</strong></p>
        <?php
    }

    public function render_ai_model_field() {
        $form_data = $this->get_form_data();
        $provider = $form_data['ai_provider'] ?? '';
        $model = $form_data['ai_model'] ?? '';
        $providers = $this->get_ai_providers();
        $models = !empty($provider) ? ($providers[$provider]['models'] ?? []) : [];
        if ($model !== '' && !isset($models[$model])) {
            $models[$model] = sprintf(__('%s (older model, kept because this configuration uses it)', 'timegrow'), $model);
        }
        ?>
        <select name="<?php echo esc_attr($this->ai_option_name); ?>[ai_model]" id="ai_model" class="regular-text" required>
            <option value="">-- Select a Model --</option>
            <?php foreach ($models as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($model, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Choose the model. It must be able to read images (for receipts). <strong style="color: #d63638;">*Required</strong></p>
        <?php
    }

    public function render_ai_api_key_field() {
        $form_data = $this->get_form_data();
        $provider = !empty($form_data['ai_provider']) && isset($this->get_ai_providers()[$form_data['ai_provider']]) ? $form_data['ai_provider'] : 'google_gemini';
        $has_key = !empty($form_data['ai_api_key']);
        $providers = $this->get_ai_providers();
        $docs_url = $providers[$provider]['docs_url'] ?? '';
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
        <span style="margin-left: 10px; font-weight: 600; color: #2271b1;">Make this the default</span>
        <p class="description">AI Receipt Analysis and AI Voice Entry use the default unless they pick another configuration under "AI to use". Only one configuration can be the default.</p>
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


}
