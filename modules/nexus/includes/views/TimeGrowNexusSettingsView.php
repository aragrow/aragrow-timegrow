<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowNexusSettingsView {

    public function display($user) {
        // Check if WC PayPal Auto Invoicer module is loaded
        $paypal_plugin_active = class_exists('Aragrow_WC_PayPal_Auto_Invoicer');
?>
        <div class="wrap timegrow-modern-wrapper">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Settings & Configuration', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Manage integrations, customize your workflow, and fine-tune your experience', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-admin-settings"></span>
                </div>
            </div>

            <!-- Settings Cards -->
            <div class="timegrow-cards-container">

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

            <!-- Documentation Section -->
            <div class="timegrow-help-section">
                <div class="timegrow-help-icon">
                    <span class="dashicons dashicons-book"></span>
                </div>
                <div class="timegrow-help-content">
                    <h3><?php esc_html_e('Need Help?', 'timegrow'); ?></h3>
                    <p><?php esc_html_e('Check out our comprehensive integration guides located in the docs folder for detailed setup instructions.', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-help-links">
                    <a href="<?php echo esc_url(plugins_url('docs/INTEGRATIONS-OVERVIEW.md', dirname(dirname(__FILE__)))); ?>" class="timegrow-help-link" target="_blank">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php esc_html_e('Integration Guide', 'timegrow'); ?>
                    </a>
                </div>
            </div>

            <!-- Footer Navigation -->
            <div class="timegrow-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-dashboard')); ?>" class="button button-secondary large">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    <?php esc_html_e('Back to Dashboard', 'timegrow'); ?>
                </a>
            </div>
        </div><!-- .wrap -->
        <?php
    }
}
