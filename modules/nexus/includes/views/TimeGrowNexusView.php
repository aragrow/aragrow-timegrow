<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowNexusView {

    public function display($user) {
        // Check if Nexus capabilities have been registered
        $admin_role = get_role('administrator');
        $caps_registered = $admin_role && $admin_role->has_cap('access_nexus_dashboard');

        // If capabilities not registered, show all cards (fallback for first-time access)
        $show_all = !$caps_registered;
?>
        <div class="wrap timegrow-modern-wrapper timegrow-nexus-dashboard">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('TimeGrow Nexus Dashboard', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Manage your time tracking, expenses, and productivity from one central hub', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-dashboard"></span>
                </div>
            </div>

            <?php settings_errors('timegrow_messages'); // Display any general admin notices ?>

            <?php if (class_exists('TimeGrowNexusKpis')) TimeGrowNexusKpis::render(); // My Time, and Team & Business for administrators ?>

            <!-- Time Tracking Section -->
            <?php if ($show_all || current_user_can('access_nexus_clock') || current_user_can('access_nexus_manual_entry')) : ?>
            <div class="timegrow-section">
                <h2 class="timegrow-section-title">
                    <span class="dashicons dashicons-clock"></span>
                    <?php esc_html_e('Time Tracking', 'timegrow'); ?>
                </h2>
                <div class="timegrow-cards-container">
                    <?php if ($show_all || current_user_can('access_nexus_clock')) : ?>
                    <a href="<?php echo esc_url("\?page=".TIMEGROW_PARENT_MENU."-nexus-clock"); ?>" class="timegrow-card">
                        <div class="timegrow-card-header">
                            <div class="timegrow-icon timegrow-icon-primary">
                                <span class="dashicons dashicons-clock"></span>
                            </div>
                            <div class="timegrow-card-title">
                                <h2><?php esc_html_e('Clock In / Out', 'timegrow'); ?></h2>
                                <span class="timegrow-badge timegrow-badge-success">
                                    <?php esc_html_e('Active', 'timegrow'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="timegrow-card-body">
                            <p class="timegrow-card-description">
                                <?php esc_html_e('Start or stop your timer for current tasks.', 'timegrow'); ?>
                            </p>
                            <div class="timegrow-card-footer">
                                <span class="timegrow-action-link">
                                    <?php esc_html_e('Start Tracking', 'timegrow'); ?>
                                    <span class="dashicons dashicons-arrow-right-alt"></span>
                                </span>
                            </div>
                        </div>
                    </a>
                    <?php endif; ?>

                    <?php if ($show_all || current_user_can('access_nexus_manual_entry')) : ?>
                    <a href="<?php echo esc_url("\?page=".TIMEGROW_PARENT_MENU."-nexus-manual"); ?>" class="timegrow-card">
                        <div class="timegrow-card-header">
                            <div class="timegrow-icon timegrow-icon-primary">
                                <span class="dashicons dashicons-edit-page"></span>
                            </div>
                            <div class="timegrow-card-title">
                                <h2><?php esc_html_e('Manual Time Entry', 'timegrow'); ?></h2>
                                <span class="timegrow-badge timegrow-badge-success">
                                    <?php esc_html_e('Active', 'timegrow'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="timegrow-card-body">
                            <p class="timegrow-card-description">
                                <?php esc_html_e('Add or edit past time entries.', 'timegrow'); ?>
                            </p>
                            <div class="timegrow-card-footer">
                                <span class="timegrow-action-link">
                                    <?php esc_html_e('Add Entry', 'timegrow'); ?>
                                    <span class="dashicons dashicons-arrow-right-alt"></span>
                                </span>
                            </div>
                        </div>
                    </a>
                    <?php endif; ?>
                </div><!-- .timegrow-cards-container -->
            </div><!-- .timegrow-section -->
            <?php endif; ?>

            <!-- Expenses and Reporting: one card each, side by side -->
            <div class="timegrow-section-row">
            <!-- Expense Management Section -->
            <?php if ($show_all || current_user_can('access_nexus_record_expenses')) : ?>
            <div class="timegrow-section">
                <h2 class="timegrow-section-title">
                    <span class="dashicons dashicons-cart"></span>
                    <?php esc_html_e('Expense Management', 'timegrow'); ?>
                </h2>
                <div class="timegrow-cards-container">
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expenses-list')); ?>" class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon timegrow-icon-primary">
                            <span class="dashicons dashicons-cart"></span>
                        </div>
                        <div class="timegrow-card-title">
                            <h2><?php esc_html_e('Record Expenses', 'timegrow'); ?></h2>
                            <span class="timegrow-badge timegrow-badge-success">
                                <?php esc_html_e('Active', 'timegrow'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            <?php esc_html_e('Track project-related expenses and receipts.', 'timegrow'); ?>
                        </p>
                        <div class="timegrow-card-footer">
                            <span class="timegrow-action-link">
                                <?php esc_html_e('Add Expense', 'timegrow'); ?>
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                            </span>
                        </div>
                    </div>
                </a>
                </div><!-- .timegrow-cards-container -->
            </div><!-- .timegrow-section -->
            <?php endif; ?>

            <!-- Reporting Section -->
            <?php if ($show_all || current_user_can('access_nexus_view_reports')) : ?>
            <div class="timegrow-section">
                <h2 class="timegrow-section-title">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <?php esc_html_e('Reporting & Analytics', 'timegrow'); ?>
                </h2>
                <div class="timegrow-cards-container">
                <a href="<?php echo esc_url("\?page=".TIMEGROW_PARENT_MENU."-nexus-reports"); ?>" class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon timegrow-icon-primary">
                            <span class="dashicons dashicons-chart-bar"></span>
                        </div>
                        <div class="timegrow-card-title">
                            <h2><?php esc_html_e('View Reports', 'timegrow'); ?></h2>
                            <span class="timegrow-badge timegrow-badge-success">
                                <?php esc_html_e('Active', 'timegrow'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            <?php esc_html_e('Analyze your time and productivity.', 'timegrow'); ?>
                        </p>
                        <div class="timegrow-card-footer">
                            <span class="timegrow-action-link">
                                <?php esc_html_e('View Analytics', 'timegrow'); ?>
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                            </span>
                        </div>
                    </div>
                </a>
                </div><!-- .timegrow-cards-container -->
            </div><!-- .timegrow-section -->
            <?php endif; ?>
            </div><!-- .timegrow-section-row -->

            <!-- Administration Section (Admin Only) -->
            <?php if ($show_all || current_user_can('access_nexus_settings') || current_user_can('access_nexus_process_time')) : ?>
            <div class="timegrow-section">
                <h2 class="timegrow-section-title">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e('Administration', 'timegrow'); ?>
                </h2>
                <div class="timegrow-cards-container">
                <?php if ($show_all || current_user_can('access_nexus_settings')) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings')); ?>" class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon timegrow-icon-primary">
                            <span class="dashicons dashicons-admin-settings"></span>
                        </div>
                        <div class="timegrow-card-title">
                            <h2><?php esc_html_e('Settings', 'timegrow'); ?></h2>
                            <span class="timegrow-badge timegrow-badge-primary">
                                <?php esc_html_e('Admin', 'timegrow'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            <?php esc_html_e('Configure plugin integrations and options.', 'timegrow'); ?>
                        </p>
                        <div class="timegrow-card-footer">
                            <span class="timegrow-action-link">
                                <?php esc_html_e('Manage Settings', 'timegrow'); ?>
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                            </span>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if ($show_all || current_user_can('access_nexus_process_time')) : ?>
                <a href="<?php echo esc_url("\?page=".TIMEGROW_PARENT_MENU."-nexus-process-time"); ?>" class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon timegrow-icon-woocommerce">
                            <span class="dashicons dashicons-paperclip"></span>
                        </div>
                        <div class="timegrow-card-title">
                            <h2><?php esc_html_e('Process Time', 'timegrow'); ?></h2>
                            <span class="timegrow-badge timegrow-badge-success">
                                <?php esc_html_e('Active', 'timegrow'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            <?php esc_html_e('Process time and attach to WooCommerce products.', 'timegrow'); ?>
                        </p>
                        <div class="timegrow-card-footer">
                            <span class="timegrow-action-link">
                                <?php esc_html_e('Process Entries', 'timegrow'); ?>
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                            </span>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if (($show_all || current_user_can('access_nexus_process_time')) && class_exists('Aragrow_TimeGrow_PayPal_Payment_Sync')) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-sync-payments')); ?>" class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon timegrow-icon-woocommerce">
                            <span class="dashicons dashicons-update"></span>
                        </div>
                        <div class="timegrow-card-title">
                            <h2><?php esc_html_e('Sync Payments', 'timegrow'); ?></h2>
                            <span class="timegrow-badge timegrow-badge-success">
                                <?php esc_html_e('PayPal', 'timegrow'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            <?php esc_html_e('Check PayPal for paid invoices and mark the orders as paid.', 'timegrow'); ?>
                        </p>
                        <div class="timegrow-card-footer">
                            <span class="timegrow-action-link">
                                <?php esc_html_e('Sync Payments', 'timegrow'); ?>
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                            </span>
                        </div>
                    </div>
                </a>
                <?php endif; ?>

                <?php if (($show_all || current_user_can('access_nexus_process_time')) && class_exists('WooCommerce') && class_exists('Aragrow_TimeGrow_Invoice_Delivery')) : $to_mail = count(Aragrow_TimeGrow_Invoice_Delivery::waiting_orders()); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . Aragrow_TimeGrow_Invoice_Delivery::PAGE_SLUG)); ?>" class="timegrow-card">
                    <div class="timegrow-card-header">
                        <div class="timegrow-icon timegrow-icon-woocommerce">
                            <span class="dashicons dashicons-printer"></span>
                        </div>
                        <div class="timegrow-card-title">
                            <h2><?php esc_html_e('Print & Mail', 'timegrow'); ?></h2>
                            <span class="timegrow-badge <?php echo $to_mail ? 'timegrow-badge-warning' : 'timegrow-badge-success'; ?>">
                                <?php echo esc_html(sprintf(_n('%d to mail', '%d to mail', $to_mail, 'timegrow'), $to_mail)); ?>
                            </span>
                        </div>
                    </div>
                    <div class="timegrow-card-body">
                        <p class="timegrow-card-description">
                            <?php esc_html_e('Invoices for clients who get them by post: print, mail, mark as mailed.', 'timegrow'); ?>
                        </p>
                        <div class="timegrow-card-footer">
                            <span class="timegrow-action-link">
                                <?php esc_html_e('Open Print & Mail', 'timegrow'); ?>
                                <span class="dashicons dashicons-arrow-right-alt"></span>
                            </span>
                        </div>
                    </div>
                </a>
                <?php endif; ?>
                </div><!-- .timegrow-cards-container -->
            </div><!-- .timegrow-section -->
            <?php endif; ?>

        </div><!-- .wrap -->

        <style>
            .timegrow-section {
                margin-bottom: 40px;
            }
            /* Two one-card sections on one row, lined up with the two-card rows above and below */
            .timegrow-section-row {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
                gap: 30px;
            }
            .timegrow-section-row .timegrow-cards-container {
                grid-template-columns: 1fr;
            }

            /* Compact cards on this dashboard: icon, title and a short description on one row */
            .timegrow-nexus-dashboard .timegrow-section { margin-bottom: 24px; }
            .timegrow-nexus-dashboard .timegrow-section-title { font-size: 16px; margin-bottom: 12px; padding-bottom: 8px; }
            .timegrow-nexus-dashboard .timegrow-section-title .dashicons { font-size: 20px; width: 20px; height: 20px; }
            .timegrow-nexus-dashboard .timegrow-cards-container { grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; margin-bottom: 0; }
            .timegrow-nexus-dashboard .timegrow-section-row { grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px; }
            .timegrow-nexus-dashboard .timegrow-card { display: grid; grid-template-columns: 36px 1fr; column-gap: 12px; align-items: center; padding: 12px 14px; border-radius: 8px; border-width: 1px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); animation: none; }
            .timegrow-nexus-dashboard .timegrow-card:hover { transform: none; box-shadow: 0 2px 8px rgba(0,0,0,0.10); }
            .timegrow-nexus-dashboard .timegrow-card-header { display: contents; } /* icon on the left, title and description on the right */
            .timegrow-nexus-dashboard .timegrow-icon { grid-column: 1; grid-row: 1 / span 2; width: 36px; height: 36px; border-radius: 8px; }
            .timegrow-nexus-dashboard .timegrow-icon .dashicons { font-size: 20px; width: 20px; height: 20px; }
            .timegrow-nexus-dashboard .timegrow-card-title { grid-column: 2; min-width: 0; }
            .timegrow-nexus-dashboard .timegrow-card-title h2 { font-size: 14px; margin: 0; }
            .timegrow-nexus-dashboard .timegrow-card-title .timegrow-badge { display: none; }
            .timegrow-nexus-dashboard .timegrow-card-body { grid-column: 2; padding: 0; min-width: 0; }
            .timegrow-nexus-dashboard .timegrow-card-description { margin: 2px 0 0; font-size: 12px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
            .timegrow-nexus-dashboard .timegrow-card-footer { display: none; } /* the whole card is the link */
            @media (max-width: 1024px) {
                .timegrow-section-row {
                    grid-template-columns: 1fr;
                    gap: 0;
                }
            }
            .timegrow-section-title {
                font-size: 20px;
                font-weight: 600;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 2px solid #e0e0e0;
                display: flex;
                align-items: center;
                gap: 10px;
                color: #1d2327;
            }
            .timegrow-section-title .dashicons {
                font-size: 24px;
                width: 24px;
                height: 24px;
                color: #2271b1;
            }
        </style>
        <?php

    }

}