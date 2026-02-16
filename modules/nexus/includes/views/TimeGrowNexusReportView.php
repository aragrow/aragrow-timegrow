<?php
// #### TimeGrowNexusReportsView.php ####

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowNexusReportView {

    /**
     * Displays the Reports Dashboard.
     *
     * @param WP_User $user The current WordPress user object.
     * @param array   $reports An array of report definitions accessible to the user.
     *                         Each report could be an array like:
     *                         ['slug' => 'project_summary', 'title' => 'Project Summary', 'description' => 'Overview of all projects.', 'icon' => 'dashicons-portfolio', 'roles' => ['administrator']]
     *                         ['slug' => 'my_time_entries', 'title' => 'My Time Entries', 'description' => 'Detailed log of your clocked hours.', 'icon' => 'dashicons-clock', 'roles' => ['team_member', 'administrator']]
     */
    public function display($user, $reports = []) {
        if (!$user || !$user->ID) {
            echo '<div class="wrap"><p>' . esc_html__('Error: User not found or not logged in.', 'timegrow') . '</p></div>';
            return;
        }

        // Check if user is in mobile session - show simplified dashboard
        if (isset($_COOKIE['timegrow_mobile_session'])) {
            $this->display_mobile_dashboard();
            return;
        }

        // Group reports by category
        $grouped_reports = [
            'Personal Productivity' => [],
            'Team & Performance' => [],
            'Financial & Project' => [],
            'Client & Project' => []
        ];

        foreach ($reports as $report) {
            $category = isset($report['category']) ? $report['category'] : 'Other';
            if (!isset($grouped_reports[$category])) {
                $grouped_reports[$category] = [];
            }
            $grouped_reports[$category][] = $report;
        }

        ?>
        <div class="wrap timegrow-modern-wrapper">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Reports Dashboard', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('View comprehensive reports and analytics for your time tracking data', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-chart-bar"></span>
                </div>
            </div>

            <?php if (empty($reports)) : ?>
                <div class="timegrow-notice timegrow-notice-info">
                    <span class="dashicons dashicons-info"></span>
                    <div>
                        <strong><?php esc_html_e('No Reports Available', 'timegrow'); ?></strong>
                        <p><?php esc_html_e('No reports are currently available for your role.', 'timegrow'); ?></p>
                    </div>
                </div>
            <?php else : ?>
                <?php foreach ($grouped_reports as $category => $category_reports) : ?>
                    <?php if (!empty($category_reports)) : ?>
                    <div class="timegrow-section">
                        <h2 class="timegrow-section-title">
                            <?php
                            // Icon based on category
                            $category_icon = 'dashicons-chart-bar';
                            switch ($category) {
                                case 'Personal Productivity':
                                    $category_icon = 'dashicons-admin-users';
                                    break;
                                case 'Team & Performance':
                                    $category_icon = 'dashicons-groups';
                                    break;
                                case 'Financial & Project':
                                    $category_icon = 'dashicons-money-alt';
                                    break;
                                case 'Client & Project':
                                    $category_icon = 'dashicons-id-alt';
                                    break;
                            }
                            ?>
                            <span class="dashicons <?php echo esc_attr($category_icon); ?>"></span>
                            <?php echo esc_html($category); ?>
                        </h2>
                        <div class="timegrow-cards-container">
                    <?php foreach ($category_reports as $report) : ?>
                        <?php
                        // Construct the URL for the report page
                        $report_url = admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-a-report&report_slug=' . esc_attr($report['slug']));
                        $icon_class = isset($report['icon']) ? esc_attr($report['icon']) : 'dashicons-chart-bar';
                        $is_coming_soon = isset($report['coming_soon']) && $report['coming_soon'];
                        ?>
                        <a href="<?php echo esc_url($report_url); ?>"
                           class="timegrow-card <?php echo $is_coming_soon ? 'disabled' : ''; ?>"
                           <?php echo $is_coming_soon ? 'onclick="return false;"' : ''; ?>>
                            <div class="timegrow-card-header">
                                <div class="timegrow-icon <?php echo $is_coming_soon ? 'timegrow-icon-disabled' : 'timegrow-icon-primary'; ?>">
                                    <span class="dashicons <?php echo $icon_class; ?>"></span>
                                </div>
                                <div class="timegrow-card-title">
                                    <h2><?php echo esc_html($report['title']); ?></h2>
                                    <?php if ($is_coming_soon) : ?>
                                        <span class="timegrow-badge timegrow-badge-warning">
                                            <?php esc_html_e('Coming Soon', 'timegrow'); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="timegrow-badge timegrow-badge-success">
                                            <?php esc_html_e('Available', 'timegrow'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="timegrow-card-body">
                                <p class="timegrow-card-description">
                                    <?php echo esc_html($report['description']); ?>
                                </p>
                                <?php if (!$is_coming_soon) : ?>
                                    <div class="timegrow-card-footer">
                                        <span class="timegrow-action-link">
                                            <?php esc_html_e('View Report', 'timegrow'); ?>
                                            <span class="dashicons dashicons-arrow-right-alt"></span>
                                        </span>
                                    </div>
                                <?php else : ?>
                                    <div class="timegrow-info-box">
                                        <span class="dashicons dashicons-info"></span>
                                        <p><?php esc_html_e('This report is currently under development.', 'timegrow'); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                        </div><!-- .timegrow-cards-container -->
                    </div><!-- .timegrow-section -->
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Footer Navigation -->
            <div class="timegrow-footer">
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-dashboard')); ?>" class="button button-secondary large">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    <?php esc_html_e('Back to Dashboard', 'timegrow'); ?>
                </a>
            </div>
        </div>

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
        // No specific JS needed for this static display unless you add filters/search
        // If you were to add JS for filtering, you'd localize data here.
        // wp_localize_script('timegrow-reports-js', 'timegrowReportsData', ['reports' => $reports, /* other data */]);
    }

    /**
     * Display simplified mobile dashboard
     * Shows only Enter Time and Enter Expenses links
     */
    private function display_mobile_dashboard() {
        $has_time_tracking = current_user_can('access_mobile_time_tracking');
        $has_expenses = current_user_can('access_mobile_expenses');
        ?>
        <div class="wrap timegrow-mobile-dashboard">
            <h1><?php esc_html_e('Dashboard', 'timegrow'); ?></h1>

            <div class="timegrow-mobile-dashboard-grid">
                <?php if ($has_time_tracking) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=timegrow-nexus-clock')); ?>" class="timegrow-mobile-dashboard-card">
                        <div class="timegrow-mobile-dashboard-icon">
                            <span class="dashicons dashicons-clock"></span>
                        </div>
                        <h2><?php esc_html_e('Enter Time', 'timegrow'); ?></h2>
                        <p><?php esc_html_e('Clock in/out or add manual time entries', 'timegrow'); ?></p>
                        <span class="timegrow-mobile-dashboard-arrow">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </span>
                    </a>
                <?php endif; ?>

                <?php if ($has_expenses) : ?>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=timegrow-nexus-expenses')); ?>" class="timegrow-mobile-dashboard-card">
                        <div class="timegrow-mobile-dashboard-icon">
                            <span class="dashicons dashicons-money-alt"></span>
                        </div>
                        <h2><?php esc_html_e('Enter Expenses', 'timegrow'); ?></h2>
                        <p><?php esc_html_e('Record expenses and upload receipts', 'timegrow'); ?></p>
                        <span class="timegrow-mobile-dashboard-arrow">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </span>
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!$has_time_tracking && !$has_expenses) : ?>
                <div class="timegrow-notice timegrow-notice-warning">
                    <span class="dashicons dashicons-warning"></span>
                    <div>
                        <strong><?php esc_html_e('No Access Granted', 'timegrow'); ?></strong>
                        <p><?php esc_html_e('You do not have permission to access any features. Please contact your administrator.', 'timegrow'); ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .timegrow-mobile-dashboard {
                padding: 20px;
                max-width: 100%;
            }

            .timegrow-mobile-dashboard h1 {
                font-size: 28px;
                font-weight: 600;
                margin-bottom: 24px;
                color: #1d2327;
            }

            .timegrow-mobile-dashboard-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 16px;
                max-width: 600px;
            }

            .timegrow-mobile-dashboard-card {
                display: block;
                background: white;
                border: 2px solid #e0e0e0;
                border-radius: 12px;
                padding: 24px;
                text-decoration: none;
                color: #1d2327;
                transition: all 0.3s;
                position: relative;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            }

            .timegrow-mobile-dashboard-card:hover {
                border-color: #2271b1;
                box-shadow: 0 4px 12px rgba(34, 113, 177, 0.15);
                transform: translateY(-2px);
            }

            .timegrow-mobile-dashboard-icon {
                width: 60px;
                height: 60px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 16px;
            }

            .timegrow-mobile-dashboard-icon .dashicons {
                font-size: 32px;
                width: 32px;
                height: 32px;
                color: white;
            }

            .timegrow-mobile-dashboard-card h2 {
                font-size: 20px;
                font-weight: 600;
                margin: 0 0 8px 0;
                color: #1d2327;
            }

            .timegrow-mobile-dashboard-card p {
                font-size: 14px;
                color: #666;
                margin: 0;
                line-height: 1.5;
            }

            .timegrow-mobile-dashboard-arrow {
                position: absolute;
                top: 24px;
                right: 24px;
                width: 32px;
                height: 32px;
                background: #f6f7f7;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s;
            }

            .timegrow-mobile-dashboard-card:hover .timegrow-mobile-dashboard-arrow {
                background: #2271b1;
            }

            .timegrow-mobile-dashboard-arrow .dashicons {
                font-size: 20px;
                width: 20px;
                height: 20px;
                color: #666;
            }

            .timegrow-mobile-dashboard-card:hover .timegrow-mobile-dashboard-arrow .dashicons {
                color: white;
            }

            .timegrow-notice {
                display: flex;
                gap: 12px;
                padding: 16px;
                border-left: 4px solid #dba617;
                background: #fcf8e3;
                border-radius: 4px;
                margin-top: 20px;
            }

            .timegrow-notice .dashicons {
                flex-shrink: 0;
                color: #dba617;
                font-size: 20px;
                width: 20px;
                height: 20px;
            }

            .timegrow-notice strong {
                display: block;
                margin-bottom: 4px;
            }

            .timegrow-notice p {
                margin: 0;
            }

            /* Mobile optimizations */
            @media (max-width: 782px) {
                .timegrow-mobile-dashboard {
                    padding: 16px;
                }

                .timegrow-mobile-dashboard-card {
                    padding: 20px;
                }
            }
        </style>
        <?php
    }
}