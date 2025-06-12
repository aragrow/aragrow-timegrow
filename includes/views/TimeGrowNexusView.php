<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowNexusView {
    
    public function display($user) {
?>
        <h2>Nexus Dashboard</h2>
        <div class="wrap timegrow-nexus-dashboard">
            <h1><?php esc_html_e('TimeGrow Nexus Dashboard', 'timegrow'); ?></h1>

            <?php settings_errors('timegrow_messages'); // Display any general admin notices ?>

            <div class="timegrow-tile-navigation">
                <a href="<?php echo esc_url("\?page=".TIMEGROW_PARENT_MENU."-nexus-clock"); ?>" class="timegrow-tile">
                    <div class="timegrow-tile-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="timegrow-tile-content">
                        <h2><?php esc_html_e('Clock In / Out', 'timegrow'); ?></h2>
                        <p><?php esc_html_e('Start or stop your timer for current tasks.', 'timegrow'); ?></p>
                    </div>
                </a>

                <a href="<?php echo esc_url("\?page=".TIMEGROW_PARENT_MENU."-nexus-manual"); ?>" class="timegrow-tile">
                    <div class="timegrow-tile-icon">
                        <span class="dashicons dashicons-edit-page"></span>
                    </div>
                    <div class="timegrow-tile-content">
                        <h2><?php esc_html_e('Manual Time Entry', 'timegrow'); ?></h2>
                        <p><?php esc_html_e('Add or edit past time entries.', 'timegrow'); ?></p>
                    </div>
                </a>

                <a href="<?php echo esc_url("\?page=".TIMEGROW_PARENT_MENU."-nexus-expenses"); ?>" class="timegrow-tile coming-soon">
                    <div class="timegrow-tile-icon">
                        <span class="dashicons dashicons-cart"></span>
                    </div>
                    <div class="timegrow-tile-content">
                        <h2><?php esc_html_e('Record Expenses', 'timegrow'); ?></h2>
                        <p><?php esc_html_e('Track project-related expenses.', 'timegrow'); ?> <span class="soon-badge"><?php esc_html_e('Coming Soon', 'timegrow'); ?></span></p>
                    </div>
                </a>

                <a href="<?php echo esc_url("\?page=".TIMEGROW_PARENT_MENU."-nexus-reports"); ?>" class="timegrow-tile coming-soon">
                    <div class="timegrow-tile-icon">
                        <span class="dashicons dashicons-chart-bar"></span>
                    </div>
                    <div class="timegrow-tile-content">
                        <h2><?php esc_html_e('View Reports', 'timegrow'); ?></h2>
                        <p><?php esc_html_e('Analyze your time and productivity.', 'timegrow'); ?> <span class="soon-badge"><?php esc_html_e('Coming Soon', 'timegrow'); ?></span></p>
                    </div>
                </a>

                <a href="<?php echo esc_url("\?page=".TIMEGROW_PARENT_MENU."nexus-settings"); ?>" class="timegrow-tile">
                    <div class="timegrow-tile-icon">
                        <span class="dashicons dashicons-admin-settings"></span>
                    </div>
                    <div class="timegrow-tile-content">
                        <h2><?php esc_html_e('Settings', 'timegrow'); ?></h2>
                        <p><?php esc_html_e('Configure plugin integrations and options.', 'timegrow'); ?> <span class="soon-badge"><?php esc_html_e('Coming Soon', 'timegrow'); ?></span></p>
                    </div>
                </a>

            </div><!-- .timegrow-tile-navigation -->
        </div><!-- .wrap -->
        <?php

    }

}