<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowNexusSettingsView {

    public function display($user) {
        // Check if modules are loaded
        $paypal_plugin_active = class_exists('Aragrow_WC_PayPal_Auto_Invoicer');

        // Check AI configuration
        $ai_settings = get_option('aragrow_timegrow_ai_settings', []);
        $has_ai_configured = !empty($ai_settings['ai_api_key']);
?>
        <div class="wrap timegrow-page">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('TimeGrow Settings', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Configure your time tracking, expenses, integrations, and AI automation', 'timegrow'); ?></p>
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
                            <h2><?php esc_html_e('General Settings', 'timegrow'); ?></h2>
                            <span class="timegrow-badge timegrow-badge-primary">
                                <?php esc_html_e('Core', 'timegrow'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            <?php esc_html_e('Configure timezone, currency, and other global settings for your TimeGrow installation.', 'timegrow'); ?>
                        </p>

                        <div class="timegrow-features">
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-clock"></span>
                                <span><?php esc_html_e('Timezone Configuration', 'timegrow'); ?></span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-money-alt"></span>
                                <span><?php esc_html_e('Currency Settings', 'timegrow'); ?></span>
                            </div>
                        </div>

                        <div class="timegrow-card-footer">
                            <span class="timegrow-action-link">
                                <?php esc_html_e('Configure General Settings', 'timegrow'); ?>
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                            </span>
                        </div>
                    </div>
                </a>

                <!-- AI Receipt Analysis Card -->
                <a href="?page=<?php echo TIMEGROW_PARENT_MENU; ?>-settings&tab=ai" class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon <?php echo $has_ai_configured ? 'timegrow-icon-primary' : 'timegrow-icon-disabled'; ?>">
                            <span class="dashicons dashicons-analytics"></span>
                        </div>
                        <div class="timegrow-card-title">
                            <h2><?php esc_html_e('AI Receipt Analysis', 'timegrow'); ?></h2>
                            <span class="timegrow-badge <?php echo $has_ai_configured ? 'timegrow-badge-success' : 'timegrow-badge-inactive'; ?>">
                                <?php echo $has_ai_configured ? esc_html__('Configured', 'timegrow') : esc_html__('Not Configured', 'timegrow'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            <?php esc_html_e('Configure AI-powered automatic receipt analysis. Upload receipt images and let AI extract expense data automatically.', 'timegrow'); ?>
                        </p>

                        <div class="timegrow-features">
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-google"></span>
                                <span><?php esc_html_e('Google Gemini', 'timegrow'); ?></span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-welcome-learn-more"></span>
                                <span><?php esc_html_e('OpenAI GPT-4', 'timegrow'); ?></span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-superhero"></span>
                                <span><?php esc_html_e('Claude AI', 'timegrow'); ?></span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <span><?php esc_html_e('Auto-Populate Fields', 'timegrow'); ?></span>
                            </div>
                        </div>

                        <?php if (!$has_ai_configured): ?>
                        <div class="timegrow-info-box">
                            <span class="dashicons dashicons-info"></span>
                            <p><?php esc_html_e('Setup Required: Add your API key to enable automatic receipt analysis.', 'timegrow'); ?></p>
                        </div>
                        <?php endif; ?>

                        <div class="timegrow-card-footer">
                            <span class="timegrow-action-link">
                                <?php echo $has_ai_configured ? esc_html__('Manage AI Settings', 'timegrow') : esc_html__('Setup AI Analysis', 'timegrow'); ?>
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
                            <h2><?php esc_html_e('WooCommerce Integration', 'timegrow'); ?></h2>
                            <span class="timegrow-badge timegrow-badge-primary">
                                <?php esc_html_e('Settings', 'timegrow'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            <?php esc_html_e('Sync time tracking data with WooCommerce for seamless invoicing, client management, and product integration.', 'timegrow'); ?>
                        </p>

                        <div class="timegrow-features">
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-groups"></span>
                                <span><?php esc_html_e('Client Sync', 'timegrow'); ?></span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-media-document"></span>
                                <span><?php esc_html_e('Invoice Sync', 'timegrow'); ?></span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-products"></span>
                                <span><?php esc_html_e('Product Sync', 'timegrow'); ?></span>
                            </div>
                        </div>

                        <div class="timegrow-card-footer">
                            <span class="timegrow-action-link">
                                <?php esc_html_e('Configure WooCommerce', 'timegrow'); ?>
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
                            <h2><?php esc_html_e('PayPal Integration', 'timegrow'); ?></h2>
                            <span class="timegrow-badge timegrow-badge-success">
                                <?php esc_html_e('Active', 'timegrow'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            <?php esc_html_e('Configure PayPal API credentials and automatic invoice generation settings.', 'timegrow'); ?>
                        </p>

                        <div class="timegrow-features">
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-money-alt"></span>
                                <span><?php esc_html_e('Auto Invoicing', 'timegrow'); ?></span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-admin-network"></span>
                                <span><?php esc_html_e('API Integration', 'timegrow'); ?></span>
                            </div>
                            <div class="timegrow-feature-badge">
                                <span class="dashicons dashicons-email"></span>
                                <span><?php esc_html_e('Email Delivery', 'timegrow'); ?></span>
                            </div>
                        </div>

                        <div class="timegrow-card-footer">
                            <span class="timegrow-action-link">
                                <?php esc_html_e('Manage PayPal', 'timegrow'); ?>
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
                            <h2><?php esc_html_e('PayPal Integration', 'timegrow'); ?></h2>
                            <span class="timegrow-badge timegrow-badge-inactive">
                                <?php esc_html_e('Not Loaded', 'timegrow'); ?>
                            </span>
                        </div>
                    </div>

                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            <?php esc_html_e('PayPal module is included but not currently loaded.', 'timegrow'); ?>
                        </p>
                        <div class="timegrow-info-box">
                            <span class="dashicons dashicons-info"></span>
                            <p><?php esc_html_e('The PayPal Auto Invoicer module is part of TimeGrow. If you\'re seeing this message, the module may not have loaded properly.', 'timegrow'); ?></p>
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
                    <h3><?php esc_html_e('Need Help?', 'timegrow'); ?></h3>
                    <p><?php esc_html_e('Check out our documentation or contact support for assistance with TimeGrow settings.', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-help-links">
                    <a href="#" class="timegrow-help-link" target="_blank">
                        <span class="dashicons dashicons-book"></span>
                        <?php esc_html_e('Documentation', 'timegrow'); ?>
                    </a>
                    <a href="#" class="timegrow-help-link" target="_blank">
                        <span class="dashicons dashicons-email"></span>
                        <?php esc_html_e('Contact Support', 'timegrow'); ?>
                    </a>
                </div>
            </div>
        </div><!-- .wrap -->
        <?php
    }
}
