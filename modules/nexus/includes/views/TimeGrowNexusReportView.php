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
}