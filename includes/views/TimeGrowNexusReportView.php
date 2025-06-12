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

        // Group reports by a conceptual category if desired, or just list them.
        // For this example, we'll just display them based on roles passed.
        // A more advanced version might group them in PHP before passing to the view.

        ?>
        <div class="wrap timegrow-page-container timegrow-reports-dashboard-page">
            <h1><?php esc_html_e('Reports Dashboard', 'timegrow'); ?></h1>

            <?php if (empty($reports)) : ?>
                <div class="notice notice-info inline">
                    <p><?php esc_html_e('No reports are currently available for your role.', 'timegrow'); ?></p>
                </div>
            <?php else : ?>
                <div class="report-tiles-container">
                    <?php foreach ($reports as $report) : ?>
                        <?php
                        // Construct the URL for the report page
                        // The actual report generation would happen on the page identified by 'timegrow-report-view&report_slug=THE_SLUG'
                        // You'll need to register 'timegrow-report-view' as an admin page.
                        $report_url = admin_url('admin.php?page=timegrow-report-view&report_slug=' . esc_attr($report['slug']));
                        $icon_class = isset($report['icon']) ? esc_attr($report['icon']) : 'dashicons-chart-bar';
                        ?>
                        <a href="<?php echo esc_url($report_url); ?>" class="report-tile">
                            <div class="report-tile-icon">
                                <span class="dashicons <?php echo $icon_class; ?>"></span>
                            </div>
                            <div class="report-tile-content">
                                <h3><?php echo esc_html($report['title']); ?></h3>
                                <p><?php echo esc_html($report['description']); ?></p>
                            </div>
                            <?php if (isset($report['coming_soon']) && $report['coming_soon']) : ?>
                                <span class="soon-badge"><?php esc_html_e('Coming Soon', 'timegrow'); ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        // No specific JS needed for this static display unless you add filters/search
        // If you were to add JS for filtering, you'd localize data here.
        // wp_localize_script('timegrow-reports-js', 'timegrowReportsData', ['reports' => $reports, /* other data */]);
    }
}