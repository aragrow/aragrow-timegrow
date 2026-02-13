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
        <div class="wrap timegrow-modern-wrapper">
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

            <!-- Administration Section (Admin Only) -->
            <?php if ($show_all || current_user_can('access_nexus_settings') || current_user_can('access_nexus_process_time')) : ?>
            <div class="timegrow-section">
                <h2 class="timegrow-section-title">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php esc_html_e('Administration', 'timegrow'); ?>
                </h2>
                <div class="timegrow-cards-container">
                <?php if ($show_all || current_user_can('access_nexus_settings')) : ?>
                <a href="<?php echo esc_url("\?page=".TIMEGROW_PARENT_MENU."-nexus-settings"); ?>" class="timegrow-card">
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
                </div><!-- .timegrow-cards-container -->
            </div><!-- .timegrow-section -->
            <?php endif; ?>

        </div><!-- .wrap -->

        <style>
            .timegrow-section {
                margin-bottom: 40px;
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