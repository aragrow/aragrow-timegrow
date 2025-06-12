<?php
// #### TimeGrowReportsController.php ####

if (!defined('ABSPATH')) exit;

// Assume TimeGrowReportsDashboardView.php is included by the main plugin file or autoloader
// require_once TIMEGROW_PLUGIN_DIR . 'includes/views/TimeGrowReportsDashboardView.php';

class TimeGrowReportsController {

    private $view;
    private $all_reports_definitions;

    public function __construct() {
        if (class_exists('TimeGrowNexusReportsView')) {
            $this->view = new TimeGrowNexusReportView();
        }
        $this->define_all_reports();
    }

    private function define_all_reports() {
        // Define all possible reports in the system
        // 'roles' array indicates who can see/access this report by default.
        // 'slug' will be used to identify and generate the specific report.
        $this->all_reports_definitions = [
            // --- Admin Reports ---
            [
                'slug' => 'team_summary_hours',
                'title' => 'Team Hours Summary',
                'description' => 'Total hours worked by each team member across all projects.',
                'icon' => 'dashicons-groups',
                'roles' => ['administrator'],
                'category' => 'Team & Performance'
            ],
            [
                'slug' => 'project_profitability',
                'title' => 'Project Financials',
                'description' => 'Overview of hours, expenses, and (if applicable) billing for projects.',
                'icon' => 'dashicons-money-alt',
                'roles' => ['administrator'],
                'category' => 'Financial & Project',
                'coming_soon' => true
            ],
            [
                'slug' => 'client_activity_summary',
                'title' => 'Client Activity Report',
                'description' => 'Summary of hours and expenses logged against each client.',
                'icon' => 'dashicons-id-alt',
                'roles' => ['administrator'],
                'category' => 'Client & Project'
            ],
            [
                'slug' => 'all_expenses_overview',
                'title' => 'All Expenses Overview',
                'description' => 'Detailed breakdown of all recorded expenses with filtering.',
                'icon' => 'dashicons-cart',
                'roles' => ['administrator'],
                'category' => 'Financial & Project'
            ],
            [
                'slug' => 'time_entry_audit_log',
                'title' => 'Time Entry Audit Log',
                'description' => 'Detailed log of all time entries, edits, and deletions.',
                'icon' => 'dashicons-shield-alt',
                'roles' => ['administrator'],
                'category' => 'Team & Performance',
                'coming_soon' => true
            ],

            // --- Team Member Reports (also visible to Admin) ---
            [
                'slug' => 'my_time_entries_detailed',
                'title' => 'My Detailed Time Log',
                'description' => 'A comprehensive log of all your clocked hours and manual entries.',
                'icon' => 'dashicons-backup', // Using backup as a more detailed log icon
                'roles' => ['team_member', 'administrator'],
                'category' => 'Personal Productivity'
            ],
            [
                'slug' => 'my_hours_by_project',
                'title' => 'My Hours by Project',
                'description' => 'Breakdown of your hours spent on different projects.',
                'icon' => 'dashicons-chart-pie',
                'roles' => ['team_member', 'administrator'],
                'category' => 'Personal Productivity'
            ],
            [
                'slug' => 'my_expenses_report',
                'title' => 'My Expenses Report',
                'description' => 'List of all expenses you have recorded.',
                'icon' => 'dashicons-money',
                'roles' => ['team_member', 'administrator'],
                'category' => 'Personal Productivity'
            ],
        ];
    }

    /**
     * Get reports available for the given user.
     * @param WP_User $user
     * @return array
     */
    public function get_available_reports_for_user($user) {
        $available_reports = [];
        $user_roles = (array) $user->roles;

        foreach ($this->all_reports_definitions as $report) {
            // Check if any of the user's roles match any of the report's allowed roles
            if (!empty(array_intersect($user_roles, $report['roles']))) {
                $available_reports[] = $report;
            }
        }
        // You could add sorting by category or title here
        // uasort($available_reports, function($a, $b) { /* sort logic */ });
        return $available_reports;
    }


    /**
     * Renders the Reports Dashboard page.
     * This is the callback for the admin menu page.
     */
    public function render_reports_dashboard_page() {
        if (!$this->view) {
            echo '<div class="wrap"><p>Error: Reports view not loaded.</p></div>';
            return;
        }
        $current_user = wp_get_current_user();
        $reports_for_user = $this->get_available_reports_for_user($current_user);
        $this->view->display($current_user, $reports_for_user);
    }

    /**
     * Renders an individual report page (placeholder).
     * Callback for the 'timegrow-report-view' page.
     */
    public function render_individual_report_page() {
        $report_slug = isset($_GET['report_slug']) ? sanitize_text_field($_GET['report_slug']) : null;
        $current_user = wp_get_current_user();
        $report_definition = null;

        // Find the report definition and check permissions again
        foreach ($this->all_reports_definitions as $def) {
            if ($def['slug'] === $report_slug) {
                if (!empty(array_intersect((array)$current_user->roles, $def['roles']))) {
                    $report_definition = $def;
                }
                break;
            }
        }

        echo '<div class="wrap timegrow-page-container timegrow-individual-report-page">';
        if ($report_definition) {
            echo '<h1>' . esc_html($report_definition['title']) . '</h1>';
            echo '<p><em>' . esc_html($report_definition['description']) . '</em></p>';
            echo '<hr>';
            echo '<p><strong>Report Slug:</strong> ' . esc_html($report_slug) . '</p>';
            echo '<p>Report generation logic for "'. esc_html($report_slug) .'" would go here.</p>';
            echo '<p>This could involve querying the database for time entries, expenses, etc., based on the slug and user permissions, then displaying charts, tables, or data exports.</p>';

            if (isset($report_definition['coming_soon']) && $report_definition['coming_soon']) {
                 echo '<p class="notice notice-warning inline">This report is currently under development. Check back soon!</p>';
            }

            // Example: Based on slug, include a specific report generation file or call a method
            // switch($report_slug) {
            // case 'team_summary_hours':
            //     // $this->generate_team_summary_hours_report();
            //     break;
            // case 'my_time_entries_detailed':
            //     // $this->generate_my_time_entries_detailed_report($current_user->ID);
            //     break;
            // default:
            //     echo '<p>Unknown report or generation not implemented.</p>';
            // }

        } else {
            echo '<h1>' . esc_html__('Report Not Found', 'timegrow') . '</h1>';
            echo '<p>' . esc_html__('The requested report could not be found or you do not have permission to view it.', 'timegrow') . '</p>';
        }
        echo '<p><a href="' . admin_url('admin.php?page=timegrow-reports') . '">« Back to Reports Dashboard</a></p>';
        echo '</div>';
    }

    // Enqueue scripts/styles if needed for the reports dashboard (e.g., if adding JS filters)
    // public function enqueue_reports_assets($hook) { /* ... */ }
}