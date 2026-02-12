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
            [
                'slug' => 'yearly_tax_report',
                'title' => 'Yearly Tax Report',
                'description' => 'Comprehensive yearly report including time charges and expenses for tax purposes.',
                'icon' => 'dashicons-analytics',
                'roles' => ['administrator'],
                'category' => 'Financial & Project'
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

            if (isset($report_definition['coming_soon']) && $report_definition['coming_soon']) {
                 echo '<p class="notice notice-warning inline">This report is currently under development. Check back soon!</p>';
            } else {
                // Route to specific report generation methods
                switch($report_slug) {
                    case 'yearly_tax_report':
                        $this->generate_yearly_tax_report();
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
     * Generate Yearly Tax Report
     * Shows time entries and expenses for a selected year
     */
    private function generate_yearly_tax_report() {
        global $wpdb;

        // Get selected year from request or default to current year
        $selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

        // Get available years from database
        $time_entry_table = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';
        $expense_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';

        $available_years_query = "
            SELECT DISTINCT YEAR(date) as year FROM {$time_entry_table} WHERE date IS NOT NULL
            UNION
            SELECT DISTINCT YEAR(expense_date) as year FROM {$expense_table} WHERE expense_date IS NOT NULL
            ORDER BY year DESC
        ";
        $available_years = $wpdb->get_col($available_years_query);

        // Year selector form
        echo '<div class="tax-report-controls" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<form method="get" action="" style="display: flex; align-items: center; gap: 10px;">';
        echo '<input type="hidden" name="page" value="' . esc_attr($_GET['page']) . '" />';
        echo '<input type="hidden" name="report_slug" value="yearly_tax_report" />';
        echo '<label for="year" style="font-weight: bold; margin: 0;">Select Year:</label>';
        echo '<select name="year" id="year" onchange="this.form.submit()" style="padding: 8px 12px; min-width: 100px; font-size: 14px;">';
        foreach ($available_years as $year) {
            $selected = ($year == $selected_year) ? 'selected' : '';
            echo '<option value="' . esc_attr($year) . '" ' . $selected . '>' . esc_html($year) . '</option>';
        }
        echo '</select>';
        echo '<button type="submit" class="button button-primary">Generate Report</button>';
        echo '</form>';
        echo '</div>';

        // Fetch time entries for selected year
        $time_entries = $this->get_yearly_time_entries($selected_year);

        // Fetch expenses for selected year
        $expenses = $this->get_yearly_expenses($selected_year);

        // Fetch WooCommerce invoices/orders for selected year
        $invoices = $this->get_yearly_invoices($selected_year);

        // Calculate totals
        $total_hours = 0;
        $total_billable_hours = 0;
        $total_time_value = 0;

        foreach ($time_entries as $entry) {
            $hours = floatval($entry->hours);
            $total_hours += $hours;
            if ($entry->billable) {
                $total_billable_hours += $hours;
            }
        }

        $total_expenses = 0;
        foreach ($expenses as $expense) {
            $total_expenses += floatval($expense->amount);
        }

        $total_invoices = 0;
        $total_invoice_amount = 0;
        foreach ($invoices as $invoice) {
            $total_invoices++;
            $total_invoice_amount += floatval($invoice->total_amount);
        }

        // Display summary
        echo '<div class="tax-report-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px;">';

        echo '<div class="summary-card" style="background: #f3e5f5; padding: 20px; border-radius: 5px; text-align: center;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #7b1fa2;">Billable Hours</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 0;">' . number_format($total_billable_hours, 2) . '</p>';
        echo '</div>';

        echo '<div class="summary-card" style="background: #fff3e0; padding: 20px; border-radius: 5px; text-align: center;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #e65100;">Total Expenses</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 0;">$' . number_format($total_expenses, 2) . '</p>';
        echo '</div>';

        echo '<div class="summary-card" style="background: #e8f5e9; padding: 20px; border-radius: 5px; text-align: center;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #2e7d32;">Paid Invoices</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 0;">' . $total_invoices . '</p>';
        echo '<p style="font-size: 12px; margin: 5px 0 0 0; color: #666;">$' . number_format($total_invoice_amount, 2) . '</p>';
        echo '</div>';

        echo '</div>';

        // Display Time Entries Table (Billable Only)
        echo '<h2 style="margin-top: 30px;">Billable Time Entries for ' . esc_html($selected_year) . '</h2>';

        // Filter only billable entries
        $billable_entries = array_filter($time_entries, function($entry) {
            return $entry->billable == 1;
        });

        if (!empty($billable_entries)) {
            echo '<table class="wp-list-table widefat fixed striped" style="margin-bottom: 30px;">';
            echo '<thead><tr>';
            echo '<th>Date</th>';
            echo '<th>Project</th>';
            echo '<th>Client</th>';
            echo '<th>Team Member</th>';
            echo '<th>Hours</th>';
            echo '<th>Billed</th>';
            echo '<th>Description</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($billable_entries as $entry) {
                echo '<tr>';
                echo '<td>' . esc_html($entry->date) . '</td>';
                echo '<td>' . esc_html($entry->project_name) . '</td>';
                echo '<td>' . esc_html($entry->client_name) . '</td>';
                echo '<td>' . esc_html($entry->member_name) . '</td>';
                echo '<td>' . number_format(floatval($entry->hours), 2) . '</td>';
                echo '<td>' . ($entry->billed ? '✓' : '—') . '</td>';
                echo '<td>' . esc_html($entry->description) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } else {
            echo '<p>No billable time entries found for ' . esc_html($selected_year) . '.</p>';
        }

        // Display Expenses Table
        echo '<h2 style="margin-top: 30px;">Expenses for ' . esc_html($selected_year) . '</h2>';

        if (!empty($expenses)) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>Date</th>';
            echo '<th>Name</th>';
            echo '<th>Category</th>';
            echo '<th>Amount</th>';
            echo '<th>Payment Method</th>';
            echo '<th>Assigned To</th>';
            echo '<th>Description</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($expenses as $expense) {
                echo '<tr>';
                echo '<td>' . esc_html($expense->expense_date) . '</td>';
                echo '<td>' . esc_html($expense->expense_name) . '</td>';
                echo '<td>' . esc_html($expense->category) . '</td>';
                echo '<td>$' . number_format(floatval($expense->amount), 2) . '</td>';
                echo '<td>' . esc_html(ucwords(str_replace('_', ' ', $expense->expense_payment_method))) . '</td>';
                echo '<td>' . esc_html(ucfirst($expense->assigned_to)) . '</td>';
                echo '<td>' . esc_html($expense->expense_description) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } else {
            echo '<p>No expenses found for ' . esc_html($selected_year) . '.</p>';
        }

        // Display WooCommerce Invoices Table
        echo '<h2 style="margin-top: 30px;">WooCommerce Invoices for ' . esc_html($selected_year) . ' (Paid Only)</h2>';

        if (!empty($invoices)) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>Order ID</th>';
            echo '<th>Date</th>';
            echo '<th>Client</th>';
            echo '<th>Status</th>';
            echo '<th>Total Amount</th>';
            echo '<th>Payment Method</th>';
            echo '<th>Items</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($invoices as $invoice) {
                echo '<tr>';
                echo '<td><a href="' . admin_url('post.php?post=' . $invoice->order_id . '&action=edit') . '" target="_blank">#' . esc_html($invoice->order_id) . '</a></td>';
                echo '<td>' . esc_html($invoice->date_created) . '</td>';
                echo '<td>' . esc_html($invoice->client_name) . '</td>';
                echo '<td>' . esc_html(ucfirst(str_replace('wc-', '', $invoice->status))) . '</td>';
                echo '<td>$' . number_format(floatval($invoice->total_amount), 2) . '</td>';
                echo '<td>' . esc_html($invoice->payment_method_title) . '</td>';
                echo '<td>' . intval($invoice->item_count) . ' items</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
        } else {
            echo '<p>No paid WooCommerce invoices found for ' . esc_html($selected_year) . '.</p>';
        }

        // Export buttons
        echo '<div style="margin-top: 30px; display: flex; gap: 10px;">';
        echo '<a href="' . admin_url('admin.php?page=' . $_GET['page'] . '&report_slug=yearly_tax_report&year=' . $selected_year . '&export=csv') . '" class="button button-primary">Export to CSV</a>';
        echo '<a href="' . admin_url('admin.php?page=' . $_GET['page'] . '&report_slug=yearly_tax_report&year=' . $selected_year . '&export=pdf') . '" class="button button-primary">Export to PDF</a>';
        echo '<button type="button" class="button button-secondary" onclick="window.print();">Print to PDF</button>';
        echo '</div>';

        // Handle exports
        if (isset($_GET['export'])) {
            if ($_GET['export'] === 'csv') {
                $this->export_yearly_tax_report_csv($selected_year, $time_entries, $expenses, $invoices);
            } elseif ($_GET['export'] === 'pdf') {
                $this->export_yearly_tax_report_pdf($selected_year, $time_entries, $expenses, $total_hours, $total_billable_hours, $total_expenses, $invoices, $total_invoice_amount);
            }
        }
    }

    /**
     * Get time entries for a specific year
     */
    private function get_yearly_time_entries($year) {
        global $wpdb;

        $time_entry_table = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $member_table = $wpdb->prefix . TIMEGROW_PREFIX . 'team_member_tracker';
        $user_table = $wpdb->prefix . 'users';

        $query = $wpdb->prepare(
            "SELECT t.*,
                    p.name as project_name,
                    m.name as member_name,
                    u.display_name as client_name
             FROM {$time_entry_table} t
             INNER JOIN {$project_table} p ON t.project_id = p.ID
             INNER JOIN {$member_table} m ON t.member_id = m.ID
             INNER JOIN {$user_table} u ON p.client_id = u.ID
             WHERE YEAR(t.date) = %d
             ORDER BY t.date ASC",
            $year
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get expenses for a specific year
     */
    private function get_yearly_expenses($year) {
        global $wpdb;

        $expense_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';

        $query = $wpdb->prepare(
            "SELECT * FROM {$expense_table}
             WHERE YEAR(expense_date) = %d
             ORDER BY expense_date ASC",
            $year
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get WooCommerce invoices for a specific year (paid invoices only)
     */
    private function get_yearly_invoices($year) {
        global $wpdb;

        $orders_table = $wpdb->prefix . 'wc_orders';
        $user_table = $wpdb->prefix . 'users';

        $query = $wpdb->prepare(
            "SELECT o.id as order_id,
                    o.date_created_gmt as date_created,
                    o.customer_id,
                    u.display_name as client_name,
                    o.status,
                    o.total_amount,
                    o.payment_method,
                    o.payment_method_title,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = o.id) as item_count
             FROM {$orders_table} o
             LEFT JOIN {$user_table} u ON o.customer_id = u.ID
             WHERE YEAR(o.date_created_gmt) = %d
             AND o.type = 'shop_order'
             AND o.status IN ('wc-completed', 'wc-processing')
             ORDER BY o.date_created_gmt DESC",
            $year
        );

        return $wpdb->get_results($query);
    }

    /**
     * Export yearly tax report to PDF
     */
    private function export_yearly_tax_report_pdf($year, $time_entries, $expenses, $total_hours, $total_billable_hours, $total_expenses, $invoices, $total_invoice_amount) {
        // Check if DomPDF is available (optional, can be installed via composer)
        // For now, we'll use a simple HTML approach that works with wkhtmltopdf or browser print

        $filename = 'tax_report_' . $year . '_' . date('Y-m-d') . '.pdf';

        // Set headers for PDF download (browser will handle the conversion)
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $filename . '"');

        // Generate HTML for PDF
        $html = $this->generate_pdf_html($year, $time_entries, $expenses, $total_hours, $total_billable_hours, $total_expenses, $invoices, $total_invoice_amount);

        echo $html;
        exit;
    }

    /**
     * Generate HTML for PDF export
     */
    private function generate_pdf_html($year, $time_entries, $expenses, $total_hours, $total_billable_hours, $total_expenses, $invoices, $total_invoice_amount) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Tax Report <?php echo esc_html($year); ?></title>
            <style>
                @page {
                    margin: 2cm;
                    size: A4;
                }
                body {
                    font-family: Arial, sans-serif;
                    font-size: 11pt;
                    line-height: 1.4;
                    color: #000;
                    margin: 0;
                    padding: 20px;
                }
                h1 {
                    font-size: 24pt;
                    margin-bottom: 5px;
                    color: #000;
                    text-align: center;
                    border-bottom: 3px solid #0073aa;
                    padding-bottom: 10px;
                }
                .report-meta {
                    text-align: center;
                    margin-bottom: 30px;
                    font-size: 10pt;
                    color: #666;
                }
                .summary-section {
                    display: table;
                    width: 100%;
                    margin-bottom: 30px;
                    page-break-inside: avoid;
                }
                .summary-card {
                    display: table-cell;
                    width: 33.33%;
                    padding: 15px;
                    text-align: center;
                    border: 2px solid #0073aa;
                    background: #f0f8ff;
                }
                .summary-card h3 {
                    margin: 0 0 10px 0;
                    font-size: 12pt;
                    color: #0073aa;
                }
                .summary-card .value {
                    font-size: 20pt;
                    font-weight: bold;
                    color: #000;
                }
                h2 {
                    font-size: 16pt;
                    margin-top: 30px;
                    margin-bottom: 15px;
                    color: #000;
                    border-bottom: 2px solid #333;
                    padding-bottom: 5px;
                    page-break-after: avoid;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 30px;
                    font-size: 9pt;
                    page-break-inside: auto;
                }
                thead {
                    display: table-header-group;
                }
                tr {
                    page-break-inside: avoid;
                    page-break-after: auto;
                }
                th {
                    background-color: #0073aa;
                    color: #fff;
                    padding: 10px 8px;
                    text-align: left;
                    font-weight: bold;
                    border: 1px solid #005177;
                }
                td {
                    padding: 8px;
                    border: 1px solid #ddd;
                }
                tbody tr:nth-child(even) {
                    background-color: #f9f9f9;
                }
                .footer {
                    margin-top: 50px;
                    padding-top: 20px;
                    border-top: 1px solid #ccc;
                    text-align: center;
                    font-size: 9pt;
                    color: #666;
                }
                @media print {
                    body { padding: 0; }
                }
            </style>
        </head>
        <body>
            <h1>Yearly Tax Report - <?php echo esc_html($year); ?></h1>
            <div class="report-meta">
                Generated on <?php echo date('F j, Y \a\t g:i A'); ?><br>
                <?php echo esc_html(get_bloginfo('name')); ?>
            </div>

            <div class="summary-section">
                <div class="summary-card">
                    <h3>Billable Hours</h3>
                    <div class="value"><?php echo number_format($total_billable_hours, 2); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Total Expenses</h3>
                    <div class="value">$<?php echo number_format($total_expenses, 2); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Paid Invoices</h3>
                    <div class="value">$<?php echo number_format($total_invoice_amount, 2); ?></div>
                </div>
            </div>

            <h2>Billable Time Entries for <?php echo esc_html($year); ?></h2>
            <?php
            // Filter only billable entries
            $billable_entries = array_filter($time_entries, function($entry) {
                return $entry->billable == 1;
            });
            ?>
            <?php if (!empty($billable_entries)) : ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Project</th>
                            <th>Client</th>
                            <th>Team Member</th>
                            <th>Hours</th>
                            <th>Billed</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($billable_entries as $entry) : ?>
                            <tr>
                                <td><?php echo esc_html($entry->date); ?></td>
                                <td><?php echo esc_html($entry->project_name); ?></td>
                                <td><?php echo esc_html($entry->client_name); ?></td>
                                <td><?php echo esc_html($entry->member_name); ?></td>
                                <td><?php echo number_format(floatval($entry->hours), 2); ?></td>
                                <td><?php echo $entry->billed ? 'Yes' : 'No'; ?></td>
                                <td><?php echo esc_html($entry->description); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No billable time entries found for <?php echo esc_html($year); ?>.</p>
            <?php endif; ?>

            <h2>Expenses for <?php echo esc_html($year); ?></h2>
            <?php if (!empty($expenses)) : ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Assigned To</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense) : ?>
                            <tr>
                                <td><?php echo esc_html($expense->expense_date); ?></td>
                                <td><?php echo esc_html($expense->expense_name); ?></td>
                                <td><?php echo esc_html($expense->category); ?></td>
                                <td>$<?php echo number_format(floatval($expense->amount), 2); ?></td>
                                <td><?php echo esc_html(ucwords(str_replace('_', ' ', $expense->expense_payment_method))); ?></td>
                                <td><?php echo esc_html(ucfirst($expense->assigned_to)); ?></td>
                                <td><?php echo esc_html($expense->expense_description); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No expenses found for <?php echo esc_html($year); ?>.</p>
            <?php endif; ?>

            <h2>WooCommerce Invoices for <?php echo esc_html($year); ?> (Paid Only)</h2>
            <?php if (!empty($invoices)) : ?>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Status</th>
                            <th>Total Amount</th>
                            <th>Payment Method</th>
                            <th>Items</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice) : ?>
                            <tr>
                                <td>#<?php echo esc_html($invoice->order_id); ?></td>
                                <td><?php echo esc_html($invoice->date_created); ?></td>
                                <td><?php echo esc_html($invoice->client_name); ?></td>
                                <td><?php echo esc_html(ucfirst(str_replace('wc-', '', $invoice->status))); ?></td>
                                <td>$<?php echo number_format(floatval($invoice->total_amount), 2); ?></td>
                                <td><?php echo esc_html($invoice->payment_method_title); ?></td>
                                <td><?php echo intval($invoice->item_count); ?> items</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No paid WooCommerce invoices found for <?php echo esc_html($year); ?>.</p>
            <?php endif; ?>

            <div class="footer">
                <strong>End of Report</strong><br>
                This is an automatically generated tax report from <?php echo esc_html(get_bloginfo('name')); ?>
            </div>

            <script>
                // Auto-print when page loads (optional - can be removed)
                window.onload = function() {
                    window.print();
                }
            </script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Export yearly tax report to CSV
     */
    private function export_yearly_tax_report_csv($year, $time_entries, $expenses, $invoices) {
        $filename = 'tax_report_' . $year . '_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Filter only billable entries
        $billable_entries = array_filter($time_entries, function($entry) {
            return $entry->billable == 1;
        });

        // Time Entries Section (Billable Only)
        fputcsv($output, ['BILLABLE TIME ENTRIES - ' . $year]);
        fputcsv($output, ['Date', 'Project', 'Client', 'Team Member', 'Hours', 'Billed', 'Description']);

        foreach ($billable_entries as $entry) {
            fputcsv($output, [
                $entry->date,
                $entry->project_name,
                $entry->client_name,
                $entry->member_name,
                number_format(floatval($entry->hours), 2),
                $entry->billed ? 'Yes' : 'No',
                $entry->description
            ]);
        }

        // Blank row
        fputcsv($output, []);

        // Expenses Section
        fputcsv($output, ['EXPENSES - ' . $year]);
        fputcsv($output, ['Date', 'Name', 'Category', 'Amount', 'Payment Method', 'Assigned To', 'Description']);

        foreach ($expenses as $expense) {
            fputcsv($output, [
                $expense->expense_date,
                $expense->expense_name,
                $expense->category,
                number_format(floatval($expense->amount), 2),
                ucwords(str_replace('_', ' ', $expense->expense_payment_method)),
                ucfirst($expense->assigned_to),
                $expense->expense_description
            ]);
        }

        // Blank row
        fputcsv($output, []);

        // WooCommerce Invoices Section
        fputcsv($output, ['WOOCOMMERCE INVOICES - ' . $year]);
        fputcsv($output, ['Order ID', 'Date', 'Client', 'Status', 'Total Amount', 'Payment Method', 'Items']);

        foreach ($invoices as $invoice) {
            fputcsv($output, [
                $invoice->order_id,
                $invoice->date_created,
                $invoice->client_name,
                ucfirst($invoice->status),
                number_format(floatval($invoice->total_amount), 2),
                $invoice->payment_method_title,
                $invoice->item_count . ' items'
            ]);
        }

        fclose($output);
        exit;
    }

    // Enqueue scripts/styles if needed for the reports dashboard (e.g., if adding JS filters)
    // public function enqueue_reports_assets($hook) { /* ... */ }
}