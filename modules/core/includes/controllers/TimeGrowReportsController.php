<?php
// #### TimeGrowReportsController.php ####

if (!defined('ABSPATH')) exit;

// Assume TimeGrowReportsDashboardView.php is included by the main plugin file or autoloader
// require_once TIMEGROW_PLUGIN_DIR . 'includes/views/TimeGrowReportsDashboardView.php';

// Each report family lives in its own trait (controllers/reports/)
require_once __DIR__ . '/reports/TimeGrowReportsYearlyTaxTrait.php';
require_once __DIR__ . '/reports/TimeGrowReportsMonthlyPnlTrait.php';
require_once __DIR__ . '/reports/TimeGrowReportsMyHoursTrait.php';
require_once __DIR__ . '/reports/TimeGrowReportsMyTimeEntriesTrait.php';
require_once __DIR__ . '/reports/TimeGrowReportsMyExpensesTrait.php';
require_once __DIR__ . '/reports/TimeGrowReportsAllExpensesTrait.php';
require_once __DIR__ . '/reports/TimeGrowReportsClientActivityTrait.php';

class TimeGrowReportsController {
    use TimeGrowReportsYearlyTaxTrait,
        TimeGrowReportsMonthlyPnlTrait,
        TimeGrowReportsMyHoursTrait,
        TimeGrowReportsMyTimeEntriesTrait,
        TimeGrowReportsMyExpensesTrait,
        TimeGrowReportsAllExpensesTrait,
        TimeGrowReportsClientActivityTrait;


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
                'capability' => 'view_team_hours_summary',
                'category' => 'Team & Performance'
            ],
            [
                'slug' => 'project_profitability',
                'title' => 'Project Financials',
                'description' => 'Overview of hours, expenses, and (if applicable) billing for projects.',
                'icon' => 'dashicons-money-alt',
                'roles' => ['administrator'],
                'capability' => 'view_project_financials',
                'category' => 'Financial & Project',
                'coming_soon' => true
            ],
            [
                'slug' => 'client_activity_summary',
                'title' => 'Client Activity Report',
                'description' => 'Summary of hours and expenses logged against each client.',
                'icon' => 'dashicons-id-alt',
                'roles' => ['administrator'],
                'capability' => 'view_client_activity_report',
                'category' => 'Client & Project'
            ],
            [
                'slug' => 'all_expenses_overview',
                'title' => 'All Expenses Overview',
                'description' => 'Detailed breakdown of all recorded expenses with filtering.',
                'icon' => 'dashicons-cart',
                'roles' => ['administrator'],
                'capability' => 'view_all_expenses_overview',
                'category' => 'Financial & Project'
            ],
            [
                'slug' => 'time_entry_audit_log',
                'title' => 'Time Entry Audit Log',
                'description' => 'Detailed log of all time entries, edits, and deletions.',
                'icon' => 'dashicons-shield-alt',
                'roles' => ['administrator'],
                'capability' => 'view_time_entry_audit_log',
                'category' => 'Team & Performance',
                'coming_soon' => true
            ],
            [
                'slug' => 'yearly_tax_report',
                'title' => 'Yearly Tax Report',
                'description' => 'Comprehensive yearly report including time charges and expenses for tax purposes.',
                'icon' => 'dashicons-analytics',
                'roles' => ['administrator', 'team_member'],
                'capability' => 'view_yearly_tax_report',
                'category' => 'Financial & Project'
            ],
            [
                'slug' => 'monthly_profit_loss',
                'title' => 'Monthly Profit & Loss Report',
                'description' => 'Monthly breakdown of income and expenses with year-to-date totals.',
                'icon' => 'dashicons-chart-line',
                'roles' => ['administrator'],
                'capability' => 'view_monthly_profit_loss',
                'category' => 'Financial & Project'
            ],

            // --- Team Member Reports (also visible to Admin) ---
            [
                'slug' => 'my_time_entries_detailed',
                'title' => 'My Detailed Time Log',
                'description' => 'A comprehensive log of all your clocked hours and manual entries.',
                'icon' => 'dashicons-backup', // Using backup as a more detailed log icon
                'roles' => ['team_member', 'administrator'],
                'capability' => 'view_my_detailed_time_log',
                'category' => 'Personal Productivity'
            ],
            [
                'slug' => 'my_hours_by_project',
                'title' => 'My Hours by Project',
                'description' => 'Breakdown of your hours spent on different projects.',
                'icon' => 'dashicons-chart-pie',
                'roles' => ['team_member', 'administrator'],
                'capability' => 'view_my_hours_by_project',
                'category' => 'Personal Productivity'
            ],
            [
                'slug' => 'my_expenses_report',
                'title' => 'My Expenses Report',
                'description' => 'List of all expenses you have recorded.',
                'icon' => 'dashicons-money',
                'roles' => ['team_member', 'administrator'],
                'capability' => 'view_my_expenses_report',
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
            // First check if user has the required capability (if defined)
            if (isset($report['capability']) && !empty($report['capability'])) {
                if (user_can($user, $report['capability'])) {
                    $available_reports[] = $report;
                }
            }
            // Fallback to role-based check if no capability is defined
            elseif (!empty(array_intersect($user_roles, $report['roles']))) {
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
                // Check capability first, then fallback to role check
                $has_permission = false;
                if (isset($def['capability']) && !empty($def['capability'])) {
                    $has_permission = current_user_can($def['capability']);
                } else {
                    $has_permission = !empty(array_intersect((array)$current_user->roles, $def['roles']));
                }

                if ($has_permission) {
                    $report_definition = $def;
                }
                break;
            }
        }

        echo '<div class="wrap timegrow-page timegrow-page-container timegrow-individual-report-page">';
        if ($report_definition) {
            echo '<h1>' . esc_html($report_definition['title']) . '</h1>';
            echo '<p><em>' . esc_html($report_definition['description']) . '</em></p>';
            echo '<hr>';

            if (isset($report_definition['coming_soon']) && $report_definition['coming_soon']) {
                 echo '<p class="notice notice-warning inline">This report is currently under development. Check back soon!</p>';
            } else {
                // Route to specific report generation methods
                switch($report_slug) {
                    case 'yearly_tax_report':
                        $this->generate_yearly_tax_report();
                        break;
                    case 'monthly_profit_loss':
                        $this->generate_monthly_profit_loss();
                        break;
                    case 'my_hours_by_project':
                        $this->generate_my_hours_by_project($current_user);
                        break;
                    case 'my_time_entries_detailed':
                        $this->generate_my_time_entries_detailed($current_user);
                        break;
                    case 'my_expenses_report':
                        $this->generate_my_expenses_report($current_user);
                        break;
                    case 'all_expenses_overview':
                        $this->generate_all_expenses_overview($current_user);
                        break;
                    case 'client_activity_summary':
                        $this->generate_client_activity_summary($current_user);
                        break;
                    default:
                        echo '<p><strong>Report Slug:</strong> ' . esc_html($report_slug) . '</p>';
                        echo '<p>Report generation logic for "'. esc_html($report_slug) .'" would go here.</p>';
                        echo '<p>This could involve querying the database for time entries, expenses, etc., based on the slug and user permissions, then displaying charts, tables, or data exports.</p>';
                }
            }

        } else {
            echo '<h1>' . esc_html__('Report Not Found', 'timegrow') . '</h1>';
            echo '<p>' . esc_html__('The requested report could not be found or you do not have permission to view it.', 'timegrow') . '</p>';
        }
        echo '<p><a href="' . admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-reports') . '">« Back to Reports Dashboard</a></p>';
        echo '</div>';
    }

    /**
     * Export Client Activity Summary to PDF
     */
    private function export_client_activity_summary_pdf() {
        global $wpdb;

        $time_entry_table = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $expense_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
        $client_table = $wpdb->prefix . 'users';

        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        $filter_client = isset($_GET['filter_client']) ? intval($_GET['filter_client']) : 0;

        // Same query as main report
        $query = "SELECT
                    c.display_name as client_name,
                    c.user_email as client_email,
                    COUNT(DISTINCT p.ID) as project_count,
                    SUM(CASE
                        WHEN te.hours IS NOT NULL AND te.hours > 0 THEN te.hours
                        WHEN te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL
                        THEN TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600
                        ELSE 0
                    END) as total_hours,
                    SUM(CASE
                        WHEN te.billable = 1 AND te.hours IS NOT NULL AND te.hours > 0 THEN te.hours
                        WHEN te.billable = 1 AND te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL
                        THEN TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600
                        ELSE 0
                    END) as billable_hours,
                    (SELECT COUNT(*) FROM {$expense_table} e
                     INNER JOIN {$project_table} p2 ON e.assigned_to = 'project' AND e.assigned_to_id = p2.ID
                     WHERE p2.client_id = c.ID) as expense_count,
                    (SELECT COALESCE(SUM(e.amount), 0) FROM {$expense_table} e
                     INNER JOIN {$project_table} p2 ON e.assigned_to = 'project' AND e.assigned_to_id = p2.ID
                     WHERE p2.client_id = c.ID) as total_expenses
                FROM {$client_table} c
                INNER JOIN {$project_table} p ON c.ID = p.client_id
                LEFT JOIN {$time_entry_table} te ON p.ID = te.project_id
                WHERE 1=1";

        $params = [];

        // Add client filter
        if (!empty($filter_client)) {
            $query .= " AND c.ID = %d";
            $params[] = $filter_client;
        }

        if (!empty($start_date) || !empty($end_date)) {
            $query .= " AND (1=1";
            if (!empty($start_date)) {
                $query .= " AND COALESCE(te.date, DATE(te.clock_in_date)) >= %s";
                $params[] = $start_date;
            }
            if (!empty($end_date)) {
                $query .= " AND COALESCE(te.date, DATE(te.clock_in_date)) <= %s";
                $params[] = $end_date;
            }
            $query .= ")";
        }

        $query .= " GROUP BY c.ID, c.display_name, c.user_email
                   HAVING total_hours > 0 OR expense_count > 0
                   ORDER BY total_hours DESC, total_expenses DESC";

        $results = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);

        $total_hours = array_sum(array_map(function($r) { return floatval($r->total_hours); }, $results));
        $total_expenses = array_sum(array_map(function($r) { return floatval($r->total_expenses); }, $results));

        header('Content-Type: text/html; charset=utf-8');

        $date_range = $start_date && $end_date ? "$start_date to $end_date" : "All Time";

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Client Activity Summary - ' . esc_html($date_range) . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #333; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 11px; }
                th { background: #667eea; color: white; padding: 8px; text-align: left; }
                td { padding: 6px 8px; border-bottom: 1px solid #ddd; }
                tr:nth-child(even) { background: #f9f9f9; }
                .summary { background: #e3f2fd; padding: 15px; margin: 20px 0; border-radius: 5px; }
                @media print { body { margin: 0; } }
            </style>
        </head>
        <body>
            <h1>Client Activity Summary</h1>
            <p><strong>Date Range:</strong> ' . esc_html($date_range) . '</p>
            <p><strong>Generated:</strong> ' . date('F j, Y g:i A') . '</p>

            <div class="summary">
                <strong>Active Clients:</strong> ' . count($results) . ' |
                <strong>Total Hours:</strong> ' . number_format($total_hours, 2) . ' |
                <strong>Total Expenses:</strong> $' . number_format($total_expenses, 2) . '
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Projects</th>
                        <th>Hours</th>
                        <th>Billable</th>
                        <th>Expenses</th>
                        <th>Expense $</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($results as $row) {
            $html .= '<tr>';
            $html .= '<td>' . esc_html($row->client_name) . '</td>';
            $html .= '<td>' . intval($row->project_count) . '</td>';
            $html .= '<td>' . number_format($row->total_hours, 2) . '</td>';
            $html .= '<td>' . number_format($row->billable_hours, 2) . '</td>';
            $html .= '<td>' . intval($row->expense_count) . '</td>';
            $html .= '<td>$' . number_format($row->total_expenses, 2) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>
            </table>
        </body>
        </html>';

        echo '<script>window.print();</script>';
        echo $html;
        exit;
    }

    // Enqueue scripts/styles if needed for the reports dashboard (e.g., if adding JS filters)
    // public function enqueue_reports_assets($hook) { /* ... */ }
}