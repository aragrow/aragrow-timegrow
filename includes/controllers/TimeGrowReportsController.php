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
                    case 'my_hours_by_project':
                        $this->generate_my_hours_by_project($current_user);
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
        echo '<form method="get" action="" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">';
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
        echo '<div style="padding: 10px; background: #e3f2fd; border-left: 4px solid #1565c0; border-radius: 4px;">';
        echo '<p style="margin: 0; font-size: 13px; color: #1565c0; line-height: 1.6;"><strong>📊 Cash Basis Accounting:</strong> This report uses the <strong>cash basis method</strong> for tax reporting. Income is recorded when payment is <strong>received</strong> (not when invoiced), and expenses are recorded when <strong>paid</strong> (not when incurred).</p>';
        echo '<p style="margin: 8px 0 0 0; font-size: 13px; color: #1565c0; line-height: 1.6;"><strong>💰 Payment Recording:</strong> Invoices are included in the year the payment was received (based on Payment Date), regardless of when the invoice was created. <strong>Partial payments</strong> are also included and counted towards your income for the year they were received.</p>';
        echo '</div>';
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
        $total_paid_amount = 0;
        foreach ($invoices as $invoice) {
            $total_invoices++;
            $total_invoice_amount += floatval($invoice->total_amount);
            // Add payment amount if available, otherwise fall back to total_amount
            $payment = floatval($invoice->payment_amount);
            $total_paid_amount += ($payment > 0) ? $payment : floatval($invoice->total_amount);
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
        echo '</div>';

        echo '<div class="summary-card" style="background: #e3f2fd; padding: 20px; border-radius: 5px; text-align: center;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #1565c0;">Total Invoice Amount</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 0;">$' . number_format($total_invoice_amount, 2) . '</p>';
        echo '</div>';

        echo '<div class="summary-card" style="background: #e8f5e9; padding: 20px; border-radius: 5px; text-align: center;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #388e3c;">Total Paid</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 0;">$' . number_format($total_paid_amount, 2) . '</p>';
        echo '</div>';

        $profit_loss = $total_paid_amount - $total_expenses;
        $pnl_color = $profit_loss >= 0 ? '#2e7d32' : '#c62828';
        $pnl_bg = $profit_loss >= 0 ? '#e8f5e9' : '#ffebee';

        echo '<div class="summary-card" style="background: ' . $pnl_bg . '; padding: 20px; border-radius: 5px; text-align: center;">';
        echo '<h3 style="margin: 0 0 10px 0; color: ' . $pnl_color . ';">Profit & Loss</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 0; color: ' . $pnl_color . ';">$' . number_format($profit_loss, 2) . '</p>';
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
        echo '<h2 style="margin-top: 30px;">WooCommerce Invoices for ' . esc_html($selected_year) . ' (Fully Paid & Partial Payments)</h2>';

        if (!empty($invoices)) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>Order ID</th>';
            echo '<th>Invoice Date</th>';
            echo '<th>Payment Date</th>';
            echo '<th>Client</th>';
            echo '<th>Status</th>';
            echo '<th>Total Amount</th>';
            echo '<th>Payment Amount</th>';
            echo '<th>Items</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            foreach ($invoices as $invoice) {
                $payment_amount = floatval($invoice->payment_amount);
                $display_payment = ($payment_amount > 0) ? $payment_amount : floatval($invoice->total_amount);
                $payment_date = !empty($invoice->payment_date) ? $invoice->payment_date : 'N/A';

                echo '<tr>';
                echo '<td><a href="' . admin_url('post.php?post=' . $invoice->order_id . '&action=edit') . '" target="_blank">#' . esc_html($invoice->order_id) . '</a></td>';
                echo '<td>' . esc_html($invoice->date_created) . '</td>';
                echo '<td><strong>' . esc_html($payment_date) . '</strong></td>';
                echo '<td>' . esc_html($invoice->client_name) . '</td>';
                echo '<td>' . esc_html(ucfirst(str_replace('wc-', '', $invoice->status))) . '</td>';
                echo '<td>$' . number_format(floatval($invoice->total_amount), 2) . '</td>';
                echo '<td>$' . number_format($display_payment, 2) . '</td>';
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
     * Get WooCommerce invoices for a specific year (paid and partially paid invoices)
     * Uses payment date (cash basis) not invoice creation date
     */
    private function get_yearly_invoices($year) {
        global $wpdb;

        $orders_table = $wpdb->prefix . 'wc_orders';
        $user_table = $wpdb->prefix . 'users';
        $ordermeta_table = $wpdb->prefix . 'wc_orders_meta';

        $query = $wpdb->prepare(
            "SELECT o.id as order_id,
                    o.date_created_gmt as date_created,
                    o.customer_id,
                    u.display_name as client_name,
                    o.status,
                    o.total_amount,
                    o.payment_method,
                    o.payment_method_title,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = o.id) as item_count,
                    (SELECT meta_value FROM {$ordermeta_table} WHERE order_id = o.id AND meta_key = '_payment_amount' LIMIT 1) as payment_amount,
                    (SELECT meta_value FROM {$ordermeta_table} WHERE order_id = o.id AND meta_key = '_payment_date' LIMIT 1) as payment_date
             FROM {$orders_table} o
             LEFT JOIN {$user_table} u ON o.customer_id = u.ID
             WHERE o.type = 'shop_order'
             AND (o.status = 'wc-invoice_paid' OR o.status = 'wc-partial_paid')
             AND (
                 (SELECT meta_value FROM {$ordermeta_table} WHERE order_id = o.id AND meta_key = '_payment_date' LIMIT 1) IS NOT NULL
                 AND YEAR(STR_TO_DATE((SELECT meta_value FROM {$ordermeta_table} WHERE order_id = o.id AND meta_key = '_payment_date' LIMIT 1), '%%Y-%%m-%%d')) = %d
             )
             ORDER BY (SELECT meta_value FROM {$ordermeta_table} WHERE order_id = o.id AND meta_key = '_payment_date' LIMIT 1) DESC",
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

    /**
     * Generate My Hours by Project Report
     * Shows current user's hours breakdown by project
     */
    private function generate_my_hours_by_project($current_user) {
        global $wpdb;

        // Debug logging
        if (WP_DEBUG) {
            error_log('=== MY HOURS BY PROJECT REPORT START ===');
            error_log('User ID: ' . $current_user->ID);
            error_log('GET params: ' . print_r($_GET, true));
        }

        // Handle CSV export
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            if (WP_DEBUG) error_log('Exporting to CSV');
            $this->export_my_hours_by_project_csv($current_user);
            return;
        }

        // Handle PDF export
        if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
            if (WP_DEBUG) error_log('Exporting to PDF');
            $this->export_my_hours_by_project_pdf($current_user);
            return;
        }

        if (WP_DEBUG) error_log('Continuing with normal report display');

        $time_entry_table = $wpdb->prefix . 'timegrow_time_entries';
        $project_table = $wpdb->prefix . 'timegrow_projects';
        $client_table = $wpdb->prefix . 'timegrow_clients';

        // Get date range filters (optional)
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

        // Build query to get hours by project for current user
        // For administrators, show all data. For team members, show only their data.
        $query = "SELECT
                    p.ID as project_id,
                    p.name as project_name,
                    c.display_name as client_name,
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
                    SUM(CASE
                        WHEN te.billable = 0 AND te.hours IS NOT NULL AND te.hours > 0 THEN te.hours
                        WHEN te.billable = 0 AND te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL
                        THEN TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600
                        ELSE 0
                    END) as non_billable_hours,
                    COUNT(te.ID) as entry_count,
                    MIN(COALESCE(te.date, te.clock_in_date)) as first_entry,
                    MAX(COALESCE(te.date, te.clock_in_date)) as last_entry
                FROM {$time_entry_table} te
                INNER JOIN {$project_table} p ON te.project_id = p.ID
                LEFT JOIN {$client_table} c ON p.client_id = c.ID
                WHERE 1=1";

        $params = [];

        // Filter by member_id only if not administrator
        if (!current_user_can('administrator')) {
            $query .= " AND te.member_id = %d";
            $params[] = $current_user->ID;
        }

        // Add date filters if provided
        if (!empty($start_date)) {
            $query .= " AND COALESCE(te.date, DATE(te.clock_in_date)) >= %s";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $query .= " AND COALESCE(te.date, DATE(te.clock_in_date)) <= %s";
            $params[] = $end_date;
        }

        $query .= " GROUP BY p.ID, p.name, c.display_name
                   HAVING total_hours > 0
                   ORDER BY total_hours DESC";

        $results = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);

        // Debug output
        if (WP_DEBUG) {
            error_log('My Hours by Project Query: ' . (!empty($params) ? $wpdb->prepare($query, $params) : $query));
            error_log('Results count: ' . count($results));
            error_log('Last error: ' . $wpdb->last_error);
        }

        // Calculate totals
        $grand_total_hours = 0;
        $grand_billable_hours = 0;
        $grand_non_billable_hours = 0;
        $grand_entry_count = 0;

        foreach ($results as $row) {
            $grand_total_hours += floatval($row->total_hours);
            $grand_billable_hours += floatval($row->billable_hours);
            $grand_non_billable_hours += floatval($row->non_billable_hours);
            $grand_entry_count += intval($row->entry_count);
        }

        // Display the report
        // NOTE: Don't wrap in .timegrow-page here because render_individual_report_page already has a wrapper
        ?>
            <?php
                // Run diagnostic query without filters - ALWAYS SHOW FOR DEBUGGING
                $diagnostic_query = "SELECT
                    COUNT(*) as total_entries,
                    MIN(COALESCE(te.date, te.clock_in_date)) as earliest_date,
                    MAX(COALESCE(te.date, te.clock_in_date)) as latest_date,
                    COUNT(DISTINCT te.project_id) as project_count,
                    COUNT(DISTINCT te.member_id) as member_count
                    FROM {$time_entry_table} te
                    WHERE 1=1";

                if (!current_user_can('administrator')) {
                    $diagnostic_query .= $wpdb->prepare(" AND te.member_id = %d", $current_user->ID);
                }

                $diagnostic = $wpdb->get_row($diagnostic_query);
            ?>
                <div class="notice notice-info" style="margin: 20px 0; display: block !important; visibility: visible !important; opacity: 1 !important; position: static !important; background: #fff3cd !important; border: 3px solid #ff6600 !important; padding: 20px !important;">
                    <h3 style="display: block !important; visibility: visible !important;">🔍 Debug Information - SQL Query & Database Stats</h3>
                    <p><strong>User Info:</strong></p>
                    <ul>
                        <li>User ID: <?php echo $current_user->ID; ?></li>
                        <li>Username: <?php echo $current_user->user_login; ?></li>
                        <li>Is Admin: <?php echo current_user_can('administrator') ? 'Yes' : 'No'; ?></li>
                    </ul>

                    <p><strong>Filter Settings:</strong></p>
                    <ul>
                        <li>Start Date: <?php echo esc_html($start_date ? $start_date : 'Not set'); ?></li>
                        <li>End Date: <?php echo esc_html($end_date ? $end_date : 'Not set'); ?></li>
                    </ul>

                    <p><strong>Database Statistics (All Available Data):</strong></p>
                    <ul>
                        <li>Total Time Entries: <?php echo intval($diagnostic->total_entries); ?></li>
                        <li>Date Range in DB: <?php echo esc_html($diagnostic->earliest_date); ?> to <?php echo esc_html($diagnostic->latest_date); ?></li>
                        <li>Projects with Entries: <?php echo intval($diagnostic->project_count); ?></li>
                        <li>Members with Entries: <?php echo intval($diagnostic->member_count); ?></li>
                    </ul>

                    <p><strong>Query Results:</strong></p>
                    <ul>
                        <li>Results Found: <?php echo count($results); ?></li>
                        <?php if (empty($results)) : ?>
                            <li style="color: red; font-weight: bold;">⚠️ No results match your filter criteria</li>
                        <?php endif; ?>
                    </ul>

                    <?php if ($wpdb->last_error) : ?>
                        <p style="color: red;"><strong>Database Error:</strong> <?php echo esc_html($wpdb->last_error); ?></p>
                    <?php endif; ?>

                    <details open style="display: block !important; visibility: visible !important; opacity: 1 !important;">
                        <summary style="cursor: pointer; font-weight: bold; display: block !important; visibility: visible !important;">SQL Query (Click to collapse)</summary>
                        <pre style="white-space: pre-wrap; font-size: 11px; background: #f5f5f5; padding: 10px; margin-top: 10px; display: block !important; visibility: visible !important; opacity: 1 !important;"><?php echo esc_html(!empty($params) ? $wpdb->prepare($query, $params) : $query); ?></pre>
                    </details>
                </div>
            <!-- Date Range Filter and Export Buttons -->
            <div class="tax-report-controls" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>" />
                    <input type="hidden" name="report_slug" value="my_hours_by_project" />

                    <label for="start_date" style="font-weight: bold; margin: 0;">From:</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr($start_date); ?>" style="padding: 8px 12px;" />

                    <label for="end_date" style="font-weight: bold; margin: 0;">To:</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr($end_date); ?>" style="padding: 8px 12px;" />

                    <button type="submit" class="button button-primary">Generate Report</button>
                    <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-a-report&report_slug=my_hours_by_project'); ?>" class="button">Clear</a>

                    <?php
                    // Build export URL with current filters
                    $export_params = [
                        'page' => $_GET['page'],
                        'report_slug' => 'my_hours_by_project'
                    ];
                    if (!empty($start_date)) $export_params['start_date'] = $start_date;
                    if (!empty($end_date)) $export_params['end_date'] = $end_date;
                    ?>

                    <div style="margin-left: auto; display: flex; gap: 10px;">
                        <a href="<?php echo add_query_arg(array_merge($export_params, ['export' => 'csv']), admin_url('admin.php')); ?>" class="button">
                            📊 Export CSV
                        </a>
                        <a href="<?php echo add_query_arg(array_merge($export_params, ['export' => 'pdf']), admin_url('admin.php')); ?>" class="button">
                            📄 Export PDF
                        </a>
                    </div>
                </form>
            </div>

            <!-- Summary Cards -->
            <div class="tax-report-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
                <div class="summary-card" style="background: #e3f2fd; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #1565c0;">Total Hours</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format($grand_total_hours, 2); ?></p>
                </div>

                <div class="summary-card" style="background: #e8f5e9; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #2e7d32;">Billable Hours</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format($grand_billable_hours, 2); ?></p>
                </div>

                <div class="summary-card" style="background: #fff3e0; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #e65100;">Non-Billable Hours</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format($grand_non_billable_hours, 2); ?></p>
                </div>

                <div class="summary-card" style="background: #f3e5f5; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #7b1fa2;">Total Projects</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo count($results); ?></p>
                </div>

                <div class="summary-card" style="background: #fce4ec; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #c2185b;">Time Entries</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo $grand_entry_count; ?></p>
                </div>
            </div>

            <!-- Project Breakdown Table -->
            <h2>Hours by Project</h2>
            <?php if (!empty($results)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Client</th>
                            <th style="text-align: right;">Total Hours</th>
                            <th style="text-align: right;">Billable</th>
                            <th style="text-align: right;">Non-Billable</th>
                            <th style="text-align: center;">Entries</th>
                            <th>First Entry</th>
                            <th>Last Entry</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row) :
                            $percentage = ($grand_total_hours > 0) ? ($row->total_hours / $grand_total_hours * 100) : 0;
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($row->project_name); ?></strong>
                                    <br />
                                    <small style="color: #666;">
                                        <div style="background: #e0e0e0; height: 8px; border-radius: 4px; margin-top: 5px;">
                                            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); height: 8px; border-radius: 4px; width: <?php echo number_format($percentage, 1); ?>%;"></div>
                                        </div>
                                        <?php echo number_format($percentage, 1); ?>% of total time
                                    </small>
                                </td>
                                <td><?php echo esc_html($row->client_name ? $row->client_name : 'No Client'); ?></td>
                                <td style="text-align: right;"><strong><?php echo number_format($row->total_hours, 2); ?></strong></td>
                                <td style="text-align: right; color: #2e7d32;"><?php echo number_format($row->billable_hours, 2); ?></td>
                                <td style="text-align: right; color: #e65100;"><?php echo number_format($row->non_billable_hours, 2); ?></td>
                                <td style="text-align: center;"><?php echo intval($row->entry_count); ?></td>
                                <td><?php echo esc_html($row->first_entry ? date('M j, Y', strtotime($row->first_entry)) : 'N/A'); ?></td>
                                <td><?php echo esc_html($row->last_entry ? date('M j, Y', strtotime($row->last_entry)) : 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f5f5f5; font-weight: bold;">
                            <th colspan="2">Total</th>
                            <th style="text-align: right;"><?php echo number_format($grand_total_hours, 2); ?></th>
                            <th style="text-align: right;"><?php echo number_format($grand_billable_hours, 2); ?></th>
                            <th style="text-align: right;"><?php echo number_format($grand_non_billable_hours, 2); ?></th>
                            <th style="text-align: center;"><?php echo $grand_entry_count; ?></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
            <?php else : ?>
                <div class="notice notice-warning inline">
                    <p>No time entries found for the selected date range.</p>
                </div>
            <?php endif; ?>
        <?php
    }

    /**
     * Export My Hours by Project Report to CSV
     */
    private function export_my_hours_by_project_csv($current_user) {
        global $wpdb;

        $time_entry_table = $wpdb->prefix . 'timegrow_time_entries';
        $project_table = $wpdb->prefix . 'timegrow_projects';
        $client_table = $wpdb->prefix . 'timegrow_clients';

        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

        // Same query as main report
        $query = "SELECT
                    p.ID as project_id,
                    p.name as project_name,
                    c.display_name as client_name,
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
                    SUM(CASE
                        WHEN te.billable = 0 AND te.hours IS NOT NULL AND te.hours > 0 THEN te.hours
                        WHEN te.billable = 0 AND te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL
                        THEN TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600
                        ELSE 0
                    END) as non_billable_hours,
                    COUNT(te.ID) as entry_count,
                    MIN(COALESCE(te.date, te.clock_in_date)) as first_entry,
                    MAX(COALESCE(te.date, te.clock_in_date)) as last_entry
                FROM {$time_entry_table} te
                INNER JOIN {$project_table} p ON te.project_id = p.ID
                LEFT JOIN {$client_table} c ON p.client_id = c.ID
                WHERE 1=1";

        $params = [];

        if (!current_user_can('administrator')) {
            $query .= " AND te.member_id = %d";
            $params[] = $current_user->ID;
        }

        if (!empty($start_date)) {
            $query .= " AND COALESCE(te.date, DATE(te.clock_in_date)) >= %s";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $query .= " AND COALESCE(te.date, DATE(te.clock_in_date)) <= %s";
            $params[] = $end_date;
        }

        $query .= " GROUP BY p.ID, p.name, c.display_name
                   HAVING total_hours > 0
                   ORDER BY total_hours DESC";

        $results = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);

        // Set headers for CSV download
        $filename = 'my-hours-by-project-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Report header
        fputcsv($output, ['My Hours by Project Report']);
        fputcsv($output, ['Generated:', date('F j, Y g:i A')]);
        if (!empty($start_date) || !empty($end_date)) {
            $date_range = 'Date Range: ';
            $date_range .= !empty($start_date) ? date('M j, Y', strtotime($start_date)) : 'Beginning';
            $date_range .= ' to ';
            $date_range .= !empty($end_date) ? date('M j, Y', strtotime($end_date)) : 'Present';
            fputcsv($output, [$date_range]);
        }
        fputcsv($output, []);

        // Column headers
        fputcsv($output, ['Project', 'Client', 'Total Hours', 'Billable Hours', 'Non-Billable Hours', 'Entries', 'First Entry', 'Last Entry']);

        // Calculate totals
        $total_hours = 0;
        $total_billable = 0;
        $total_non_billable = 0;
        $total_entries = 0;

        // Data rows
        foreach ($results as $row) {
            fputcsv($output, [
                $row->project_name,
                $row->client_name ? $row->client_name : 'No Client',
                number_format($row->total_hours, 2),
                number_format($row->billable_hours, 2),
                number_format($row->non_billable_hours, 2),
                $row->entry_count,
                $row->first_entry ? date('M j, Y', strtotime($row->first_entry)) : 'N/A',
                $row->last_entry ? date('M j, Y', strtotime($row->last_entry)) : 'N/A'
            ]);

            $total_hours += floatval($row->total_hours);
            $total_billable += floatval($row->billable_hours);
            $total_non_billable += floatval($row->non_billable_hours);
            $total_entries += intval($row->entry_count);
        }

        // Totals row
        fputcsv($output, []);
        fputcsv($output, [
            'TOTAL',
            '',
            number_format($total_hours, 2),
            number_format($total_billable, 2),
            number_format($total_non_billable, 2),
            $total_entries,
            '',
            ''
        ]);

        fclose($output);
        exit;
    }

    /**
     * Export My Hours by Project Report to PDF
     */
    private function export_my_hours_by_project_pdf($current_user) {
        global $wpdb;

        $time_entry_table = $wpdb->prefix . 'timegrow_time_entries';
        $project_table = $wpdb->prefix . 'timegrow_projects';
        $client_table = $wpdb->prefix . 'timegrow_clients';

        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

        // Same query as main report
        $query = "SELECT
                    p.ID as project_id,
                    p.name as project_name,
                    c.display_name as client_name,
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
                    SUM(CASE
                        WHEN te.billable = 0 AND te.hours IS NOT NULL AND te.hours > 0 THEN te.hours
                        WHEN te.billable = 0 AND te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL
                        THEN TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600
                        ELSE 0
                    END) as non_billable_hours,
                    COUNT(te.ID) as entry_count,
                    MIN(COALESCE(te.date, te.clock_in_date)) as first_entry,
                    MAX(COALESCE(te.date, te.clock_in_date)) as last_entry
                FROM {$time_entry_table} te
                INNER JOIN {$project_table} p ON te.project_id = p.ID
                LEFT JOIN {$client_table} c ON p.client_id = c.ID
                WHERE 1=1";

        $params = [];

        if (!current_user_can('administrator')) {
            $query .= " AND te.member_id = %d";
            $params[] = $current_user->ID;
        }

        if (!empty($start_date)) {
            $query .= " AND COALESCE(te.date, DATE(te.clock_in_date)) >= %s";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $query .= " AND COALESCE(te.date, DATE(te.clock_in_date)) <= %s";
            $params[] = $end_date;
        }

        $query .= " GROUP BY p.ID, p.name, c.display_name
                   HAVING total_hours > 0
                   ORDER BY total_hours DESC";

        $results = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);

        // Calculate totals
        $total_hours = 0;
        $total_billable = 0;
        $total_non_billable = 0;
        $total_entries = 0;

        foreach ($results as $row) {
            $total_hours += floatval($row->total_hours);
            $total_billable += floatval($row->billable_hours);
            $total_non_billable += floatval($row->non_billable_hours);
            $total_entries += intval($row->entry_count);
        }

        // Generate HTML for PDF
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>My Hours by Project Report</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    margin: 20px;
                }
                h1 {
                    color: #333;
                    font-size: 24px;
                    margin-bottom: 10px;
                }
                .report-info {
                    color: #666;
                    margin-bottom: 20px;
                    font-size: 11px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                }
                th {
                    background: #667eea;
                    color: white;
                    padding: 10px;
                    text-align: left;
                    font-size: 11px;
                    text-transform: uppercase;
                }
                td {
                    padding: 8px 10px;
                    border-bottom: 1px solid #ddd;
                }
                tr:nth-child(even) {
                    background: #f9f9f9;
                }
                .number {
                    text-align: right;
                }
                .total-row {
                    font-weight: bold;
                    background: #f0f0f0 !important;
                    border-top: 2px solid #333;
                }
                .summary {
                    display: grid;
                    grid-template-columns: repeat(4, 1fr);
                    gap: 15px;
                    margin: 20px 0;
                }
                .summary-card {
                    background: #f5f5f5;
                    padding: 15px;
                    border-left: 4px solid #667eea;
                    text-align: center;
                }
                .summary-card h3 {
                    margin: 0 0 5px 0;
                    font-size: 11px;
                    color: #666;
                    text-transform: uppercase;
                }
                .summary-card .value {
                    font-size: 20px;
                    font-weight: bold;
                    color: #333;
                }
            </style>
        </head>
        <body>
            <h1>My Hours by Project Report</h1>
            <div class="report-info">
                <strong>Generated:</strong> <?php echo date('F j, Y g:i A'); ?><br>
                <?php if (!empty($start_date) || !empty($end_date)) : ?>
                    <strong>Date Range:</strong>
                    <?php echo !empty($start_date) ? date('M j, Y', strtotime($start_date)) : 'Beginning'; ?>
                    to
                    <?php echo !empty($end_date) ? date('M j, Y', strtotime($end_date)) : 'Present'; ?>
                <?php endif; ?>
            </div>

            <div class="summary">
                <div class="summary-card">
                    <h3>Total Hours</h3>
                    <div class="value"><?php echo number_format($total_hours, 2); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Billable Hours</h3>
                    <div class="value"><?php echo number_format($total_billable, 2); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Non-Billable Hours</h3>
                    <div class="value"><?php echo number_format($total_non_billable, 2); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Projects</h3>
                    <div class="value"><?php echo count($results); ?></div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Client</th>
                        <th class="number">Total Hours</th>
                        <th class="number">Billable</th>
                        <th class="number">Non-Billable</th>
                        <th class="number">Entries</th>
                        <th>First Entry</th>
                        <th>Last Entry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row) : ?>
                        <tr>
                            <td><strong><?php echo esc_html($row->project_name); ?></strong></td>
                            <td><?php echo esc_html($row->client_name ? $row->client_name : 'No Client'); ?></td>
                            <td class="number"><?php echo number_format($row->total_hours, 2); ?></td>
                            <td class="number"><?php echo number_format($row->billable_hours, 2); ?></td>
                            <td class="number"><?php echo number_format($row->non_billable_hours, 2); ?></td>
                            <td class="number"><?php echo $row->entry_count; ?></td>
                            <td><?php echo $row->first_entry ? date('M j, Y', strtotime($row->first_entry)) : 'N/A'; ?></td>
                            <td><?php echo $row->last_entry ? date('M j, Y', strtotime($row->last_entry)) : 'N/A'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td colspan="2"><strong>TOTAL</strong></td>
                        <td class="number"><strong><?php echo number_format($total_hours, 2); ?></strong></td>
                        <td class="number"><strong><?php echo number_format($total_billable, 2); ?></strong></td>
                        <td class="number"><strong><?php echo number_format($total_non_billable, 2); ?></strong></td>
                        <td class="number"><strong><?php echo $total_entries; ?></strong></td>
                        <td colspan="2"></td>
                    </tr>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        $html = ob_get_clean();

        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="my-hours-by-project-' . date('Y-m-d') . '.pdf"');

        // Use browser's print-to-PDF functionality by sending HTML that will trigger print dialog
        // This is a simple approach that works without external PDF libraries
        echo '<script>window.print();</script>';
        echo $html;
        exit;
    }

    // Enqueue scripts/styles if needed for the reports dashboard (e.g., if adding JS filters)
    // public function enqueue_reports_assets($hook) { /* ... */ }
}