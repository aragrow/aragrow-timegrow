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
        $time_entries_sql = $wpdb->last_query;

        // Fetch expenses for selected year
        $expenses = $this->get_yearly_expenses($selected_year);
        $expenses_sql = $wpdb->last_query;

        // Fetch WooCommerce invoices/orders for selected year
        $invoices = $this->get_yearly_invoices($selected_year);
        $invoices_sql = $wpdb->last_query;

        // Display SQL Debug (only when WP_DEBUG is enabled)
        if (WP_DEBUG): ?>
            <div style="background: #f8f9fa; border: 2px solid #0073aa; padding: 20px; margin: 20px 0; border-radius: 5px;">
                <h3 style="margin-top: 0; color: #0073aa;">📊 SQL Queries</h3>

                <h4 style="color: #0073aa; margin-top: 15px;">Time Entries Query:</h4>
                <pre style="background: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; line-height: 1.5;"><?php echo esc_html($time_entries_sql); ?></pre>
                <p><strong>Results:</strong> <?php echo count($time_entries); ?> time entries found</p>

                <h4 style="color: #0073aa; margin-top: 15px;">Expenses Query:</h4>
                <pre style="background: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; line-height: 1.5;"><?php echo esc_html($expenses_sql); ?></pre>
                <p><strong>Results:</strong> <?php echo count($expenses); ?> expenses found</p>

                <h4 style="color: #0073aa; margin-top: 15px;">Invoices Query:</h4>
                <pre style="background: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; line-height: 1.5;"><?php echo esc_html($invoices_sql); ?></pre>
                <p><strong>Results:</strong> <?php echo count($invoices); ?> invoices found</p>

                <?php if ($wpdb->last_error): ?>
                    <p style="color: red;"><strong>Last Error:</strong> <?php echo esc_html($wpdb->last_error); ?></p>
                <?php endif; ?>
            </div>
        <?php endif;

        // Calculate totals
        $total_hours = 0;
        $total_billable_hours = 0;
        $total_time_value = 0;

        foreach ($time_entries as $entry) {
            // Use calculated_hours which handles both manual entries and clock in/out
            $hours = floatval($entry->calculated_hours);
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
                echo '<td>' . esc_html($entry->entry_date) . '</td>';
                echo '<td>' . esc_html($entry->project_name) . '</td>';
                echo '<td>' . esc_html($entry->client_name) . '</td>';
                echo '<td>' . esc_html($entry->member_name) . '</td>';
                echo '<td>' . number_format(floatval($entry->calculated_hours), 2) . '</td>';
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
                    u.display_name as client_name,
                    COALESCE(t.date, DATE(t.clock_in_date)) as entry_date,
                    CASE
                        WHEN t.hours IS NOT NULL AND t.hours > 0 THEN t.hours
                        WHEN t.clock_in_date IS NOT NULL AND t.clock_out_date IS NOT NULL
                        THEN TIMESTAMPDIFF(SECOND, t.clock_in_date, t.clock_out_date) / 3600
                        ELSE 0
                    END as calculated_hours
             FROM {$time_entry_table} t
             INNER JOIN {$project_table} p ON t.project_id = p.ID
             INNER JOIN {$member_table} m ON t.member_id = m.ID
             INNER JOIN {$user_table} u ON p.client_id = u.ID
             WHERE YEAR(COALESCE(t.date, t.clock_in_date)) = %d
             ORDER BY COALESCE(t.date, t.clock_in_date) ASC",
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
             AND (o.status = 'wc-invoice_paid' OR o.status = 'invoice_paid' OR o.status = 'wc-partial_paid' OR o.status = 'partial_paid')
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

        $time_entry_table = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $client_table = $wpdb->prefix . 'users'; // Clients are stored in WordPress users table

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

        // Prepare the final SQL for display
        $final_sql = !empty($params) ? $wpdb->prepare($query, $params) : $query;

        $results = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);

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

        // Display the SQL Query (only when WP_DEBUG is enabled)
        ?>
            <?php if (WP_DEBUG): ?>
            <div style="background: #f8f9fa; border: 2px solid #0073aa; padding: 20px; margin: 20px 0; border-radius: 5px;">
                <h3 style="margin-top: 0; color: #0073aa;">📊 SQL Query</h3>
                <pre style="background: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; line-height: 1.5;"><?php echo esc_html($final_sql); ?></pre>
                <p><strong>Results:</strong> <?php echo count($results); ?> projects found</p>
                <?php if ($wpdb->last_error): ?>
                    <p style="color: red;"><strong>Error:</strong> <?php echo esc_html($wpdb->last_error); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <!-- Date Range Filter -->
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
                </form>
            </div>

            <?php
            // Build export URL with current filters (for use at bottom)
            $export_params = [
                'page' => $_GET['page'],
                'report_slug' => 'my_hours_by_project'
            ];
            if (!empty($start_date)) $export_params['start_date'] = $start_date;
            if (!empty($end_date)) $export_params['end_date'] = $end_date;
            ?>

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

            <!-- Export buttons at bottom -->
            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <a href="<?php echo add_query_arg(array_merge($export_params, ['export' => 'csv']), admin_url('admin.php')); ?>" class="button button-primary">📊 Export to CSV</a>
                <a href="<?php echo add_query_arg(array_merge($export_params, ['export' => 'pdf']), admin_url('admin.php')); ?>" class="button button-primary">📄 Export to PDF</a>
                <button type="button" class="button button-secondary" onclick="window.print();">🖨️ Print to PDF</button>
            </div>
        <?php
    }

    /**
     * Generate My Detailed Time Log Report
     * Shows all time entries (manual and clock) with full details
     */
    private function generate_my_time_entries_detailed($current_user) {
        global $wpdb;

        // Handle CSV export
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $this->export_my_time_entries_detailed_csv($current_user);
            return;
        }

        // Handle PDF export
        if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
            $this->export_my_time_entries_detailed_pdf($current_user);
            return;
        }

        $time_entry_table = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $member_table = $wpdb->prefix . TIMEGROW_PREFIX . 'team_member_tracker';
        $client_table = $wpdb->prefix . 'users'; // Clients are stored in WordPress users table

        // Get date range filters (optional)
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

        // Build query to get all time entries with full details
        $query = "SELECT
                    te.ID as entry_id,
                    te.date as manual_date,
                    te.hours as manual_hours,
                    te.clock_in_date,
                    te.clock_out_date,
                    te.entry_type,
                    te.billable,
                    te.billed,
                    te.description,
                    COALESCE(te.date, DATE(te.clock_in_date)) as entry_date,
                    CASE
                        WHEN te.hours IS NOT NULL AND te.hours > 0 THEN te.hours
                        WHEN te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL
                        THEN TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600
                        ELSE 0
                    END as calculated_hours,
                    p.ID as project_id,
                    p.name as project_name,
                    c.display_name as client_name,
                    m.name as member_name,
                    te.created_at,
                    te.updated_at
                FROM {$time_entry_table} te
                INNER JOIN {$project_table} p ON te.project_id = p.ID
                INNER JOIN {$member_table} m ON te.member_id = m.ID
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

        $query .= " ORDER BY COALESCE(te.date, te.clock_in_date) DESC, te.ID DESC";

        // Prepare the final SQL for display
        $final_sql = !empty($params) ? $wpdb->prepare($query, $params) : $query;

        $results = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);

        // Calculate totals
        $total_hours = 0;
        $total_billable_hours = 0;
        $total_non_billable_hours = 0;
        $total_billed_hours = 0;
        $manual_entries_count = 0;
        $clock_entries_count = 0;

        foreach ($results as $entry) {
            $hours = floatval($entry->calculated_hours);
            $total_hours += $hours;

            if ($entry->billable) {
                $total_billable_hours += $hours;
            } else {
                $total_non_billable_hours += $hours;
            }

            if ($entry->billed) {
                $total_billed_hours += $hours;
            }

            if ($entry->entry_type === 'MAN') {
                $manual_entries_count++;
            } else {
                $clock_entries_count++;
            }
        }

        // Display the SQL Query (only when WP_DEBUG is enabled)
        ?>
            <?php if (WP_DEBUG): ?>
            <div style="background: #f8f9fa; border: 2px solid #0073aa; padding: 20px; margin: 20px 0; border-radius: 5px;">
                <h3 style="margin-top: 0; color: #0073aa;">📊 SQL Query</h3>
                <pre style="background: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; line-height: 1.5;"><?php echo esc_html($final_sql); ?></pre>
                <p><strong>Results:</strong> <?php echo count($results); ?> time entries found</p>
                <?php if ($wpdb->last_error): ?>
                    <p style="color: red;"><strong>Error:</strong> <?php echo esc_html($wpdb->last_error); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Date Range Filter -->
            <div class="tax-report-controls" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>" />
                    <input type="hidden" name="report_slug" value="my_time_entries_detailed" />

                    <label for="start_date" style="font-weight: bold; margin: 0;">From:</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr($start_date); ?>" style="padding: 8px 12px;" />

                    <label for="end_date" style="font-weight: bold; margin: 0;">To:</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr($end_date); ?>" style="padding: 8px 12px;" />

                    <button type="submit" class="button button-primary">Generate Report</button>
                    <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-a-report&report_slug=my_time_entries_detailed'); ?>" class="button">Clear</a>
                </form>
            </div>

            <?php
            // Build export URL with current filters (for use at bottom)
            $export_params = [
                'page' => $_GET['page'],
                'report_slug' => 'my_time_entries_detailed'
            ];
            if (!empty($start_date)) $export_params['start_date'] = $start_date;
            if (!empty($end_date)) $export_params['end_date'] = $end_date;
            ?>

            <!-- Summary Cards -->
            <div class="tax-report-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px;">
                <div class="summary-card" style="background: #e3f2fd; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #1565c0;">Total Hours</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format($total_hours, 2); ?></p>
                </div>

                <div class="summary-card" style="background: #e8f5e9; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #2e7d32;">Billable Hours</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format($total_billable_hours, 2); ?></p>
                </div>

                <div class="summary-card" style="background: #fff3e0; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #e65100;">Non-Billable Hours</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format($total_non_billable_hours, 2); ?></p>
                </div>

                <div class="summary-card" style="background: #f3e5f5; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #7b1fa2;">Billed Hours</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format($total_billed_hours, 2); ?></p>
                </div>

                <div class="summary-card" style="background: #e1f5fe; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #0277bd;">Total Entries</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo count($results); ?></p>
                </div>

                <div class="summary-card" style="background: #fce4ec; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #c2185b;">Manual / Clock</h3>
                    <p style="font-size: 20px; font-weight: bold; margin: 0;"><?php echo $manual_entries_count; ?> / <?php echo $clock_entries_count; ?></p>
                </div>
            </div>

            <!-- Detailed Time Entries Table -->
            <?php if (!empty($results)) : ?>
                <table class="wp-list-table widefat fixed striped" style="margin-bottom: 30px;">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Date</th>
                            <th style="width: 80px;">Type</th>
                            <th>Project</th>
                            <th>Client</th>
                            <th style="width: 100px;">Clock In</th>
                            <th style="width: 100px;">Clock Out</th>
                            <th style="width: 80px;">Hours</th>
                            <th style="width: 80px;">Billable</th>
                            <th style="width: 80px;">Billed</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $entry) : ?>
                            <tr>
                                <td><?php echo esc_html($entry->entry_date); ?></td>
                                <td>
                                    <?php if ($entry->entry_type === 'MAN') : ?>
                                        <span style="background: #2196F3; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">MANUAL</span>
                                    <?php else : ?>
                                        <span style="background: #4CAF50; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">CLOCK</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($entry->project_name); ?></td>
                                <td><?php echo esc_html($entry->client_name); ?></td>
                                <td>
                                    <?php
                                    if ($entry->clock_in_date) {
                                        echo esc_html(date('g:i A', strtotime($entry->clock_in_date)));
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ($entry->clock_out_date) {
                                        echo esc_html(date('g:i A', strtotime($entry->clock_out_date)));
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td style="font-weight: bold;"><?php echo number_format($entry->calculated_hours, 2); ?></td>
                                <td style="text-align: center;">
                                    <?php echo $entry->billable ? '<span style="color: #2e7d32;">✓</span>' : '<span style="color: #999;">—</span>'; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php echo $entry->billed ? '<span style="color: #7b1fa2;">✓</span>' : '<span style="color: #999;">—</span>'; ?>
                                </td>
                                <td><?php echo esc_html($entry->description ? $entry->description : '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td colspan="6" style="text-align: right; padding-right: 10px;">TOTALS:</td>
                            <td style="font-weight: bold; font-size: 16px;"><?php echo number_format($total_hours, 2); ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else : ?>
                <div class="notice notice-warning inline">
                    <p>No time entries found for the selected date range.</p>
                </div>
            <?php endif; ?>

            <!-- Export buttons at bottom -->
            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <a href="<?php echo add_query_arg(array_merge($export_params, ['export' => 'csv']), admin_url('admin.php')); ?>" class="button button-primary">📊 Export to CSV</a>
                <a href="<?php echo add_query_arg(array_merge($export_params, ['export' => 'pdf']), admin_url('admin.php')); ?>" class="button button-primary">📄 Export to PDF</a>
                <button type="button" class="button button-secondary" onclick="window.print();">🖨️ Print to PDF</button>
            </div>
        <?php
    }

    /**
     * Generate My Expenses Report
     * Shows all expenses recorded by the user or assigned to their projects
     */
    private function generate_my_expenses_report($current_user) {
        global $wpdb;

        // Handle CSV export
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $this->export_my_expenses_report_csv($current_user);
            return;
        }

        // Handle PDF export
        if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
            $this->export_my_expenses_report_pdf($current_user);
            return;
        }

        $expense_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $client_table = $wpdb->prefix . 'users';

        // Get date range filters (optional)
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

        // Build query to get all expenses with project/client details
        $query = "SELECT
                    e.ID as expense_id,
                    e.expense_name,
                    e.expense_description,
                    e.expense_date,
                    e.expense_payment_method,
                    e.amount,
                    e.category,
                    e.assigned_to,
                    e.assigned_to_id,
                    e.created_at,
                    p.name as project_name,
                    c.display_name as client_name
                FROM {$expense_table} e
                LEFT JOIN {$project_table} p ON (e.assigned_to = 'project' AND e.assigned_to_id = p.ID)
                LEFT JOIN {$client_table} c ON (e.assigned_to = 'client' AND e.assigned_to_id = c.ID)
                WHERE 1=1";

        $params = [];

        // For non-administrators, show only expenses assigned to their projects
        // Note: Expense table doesn't have member_id, so we filter by projects they work on
        if (!current_user_can('administrator')) {
            // Get projects this user is assigned to
            $member_table = $wpdb->prefix . TIMEGROW_PREFIX . 'team_member_tracker';
            $time_entry_table = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';

            $user_projects_query = $wpdb->prepare(
                "SELECT DISTINCT project_id FROM {$time_entry_table} WHERE member_id = %d",
                $current_user->ID
            );
            $user_projects = $wpdb->get_col($user_projects_query);

            if (!empty($user_projects)) {
                $placeholders = implode(',', array_fill(0, count($user_projects), '%d'));
                $query .= " AND ((e.assigned_to = 'project' AND e.assigned_to_id IN ($placeholders)) OR e.assigned_to = 'general')";
                $params = array_merge($params, $user_projects);
            } else {
                // User has no projects, show only general expenses
                $query .= " AND e.assigned_to = 'general'";
            }
        }

        // Add date filters if provided
        if (!empty($start_date)) {
            $query .= " AND e.expense_date >= %s";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $query .= " AND e.expense_date <= %s";
            $params[] = $end_date;
        }

        $query .= " ORDER BY e.expense_date DESC, e.ID DESC";

        // Prepare the final SQL for display
        $final_sql = !empty($params) ? $wpdb->prepare($query, $params) : $query;

        $results = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);

        // Calculate totals by category and payment method
        $total_amount = 0;
        $by_category = [];
        $by_payment_method = [];
        $by_assigned_to = [
            'project' => 0,
            'client' => 0,
            'general' => 0
        ];

        foreach ($results as $expense) {
            $amount = floatval($expense->amount);
            $total_amount += $amount;

            // By category
            if (!isset($by_category[$expense->category])) {
                $by_category[$expense->category] = 0;
            }
            $by_category[$expense->category] += $amount;

            // By payment method
            if (!isset($by_payment_method[$expense->expense_payment_method])) {
                $by_payment_method[$expense->expense_payment_method] = 0;
            }
            $by_payment_method[$expense->expense_payment_method] += $amount;

            // By assigned to
            $by_assigned_to[$expense->assigned_to] += $amount;
        }

        // Display the SQL Query (only when WP_DEBUG is enabled)
        ?>
            <?php if (WP_DEBUG): ?>
            <div style="background: #f8f9fa; border: 2px solid #0073aa; padding: 20px; margin: 20px 0; border-radius: 5px;">
                <h3 style="margin-top: 0; color: #0073aa;">📊 SQL Query</h3>
                <pre style="background: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; line-height: 1.5;"><?php echo esc_html($final_sql); ?></pre>
                <p><strong>Results:</strong> <?php echo count($results); ?> expenses found</p>
                <?php if ($wpdb->last_error): ?>
                    <p style="color: red;"><strong>Error:</strong> <?php echo esc_html($wpdb->last_error); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Date Range Filter -->
            <div class="tax-report-controls" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>" />
                    <input type="hidden" name="report_slug" value="my_expenses_report" />

                    <label for="start_date" style="font-weight: bold; margin: 0;">From:</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr($start_date); ?>" style="padding: 8px 12px;" />

                    <label for="end_date" style="font-weight: bold; margin: 0;">To:</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr($end_date); ?>" style="padding: 8px 12px;" />

                    <button type="submit" class="button button-primary">Generate Report</button>
                    <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-a-report&report_slug=my_expenses_report'); ?>" class="button">Clear</a>
                </form>
            </div>

            <?php
            // Build export URL with current filters (for use at bottom)
            $export_params = [
                'page' => $_GET['page'],
                'report_slug' => 'my_expenses_report'
            ];
            if (!empty($start_date)) $export_params['start_date'] = $start_date;
            if (!empty($end_date)) $export_params['end_date'] = $end_date;
            ?>

            <!-- Summary Cards -->
            <div class="tax-report-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px;">
                <div class="summary-card" style="background: #fff3e0; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #e65100;">Total Expenses</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;">$<?php echo number_format($total_amount, 2); ?></p>
                </div>

                <div class="summary-card" style="background: #e3f2fd; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #1565c0;">Total Count</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo count($results); ?></p>
                </div>

                <div class="summary-card" style="background: #f3e5f5; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #7b1fa2;">Project Expenses</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;">$<?php echo number_format($by_assigned_to['project'], 2); ?></p>
                </div>

                <div class="summary-card" style="background: #e8f5e9; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #2e7d32;">Client Expenses</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;">$<?php echo number_format($by_assigned_to['client'], 2); ?></p>
                </div>

                <div class="summary-card" style="background: #fce4ec; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #c2185b;">General Expenses</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;">$<?php echo number_format($by_assigned_to['general'], 2); ?></p>
                </div>

                <div class="summary-card" style="background: #e1f5fe; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #0277bd;">Categories</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo count($by_category); ?></p>
                </div>
            </div>

            <!-- Detailed Expenses Table -->
            <?php if (!empty($results)) : ?>
                <table class="wp-list-table widefat fixed striped" style="margin-bottom: 30px;">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Date</th>
                            <th>Expense Name</th>
                            <th style="width: 120px;">Category</th>
                            <th style="width: 100px;">Amount</th>
                            <th style="width: 120px;">Payment Method</th>
                            <th style="width: 100px;">Assigned To</th>
                            <th>Project/Client</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $expense) : ?>
                            <tr>
                                <td><?php echo esc_html($expense->expense_date); ?></td>
                                <td style="font-weight: 600;"><?php echo esc_html($expense->expense_name); ?></td>
                                <td>
                                    <span style="background: #e3f2fd; color: #1565c0; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">
                                        <?php echo esc_html(strtoupper($expense->category)); ?>
                                    </span>
                                </td>
                                <td style="font-weight: bold; color: #e65100;">$<?php echo number_format($expense->amount, 2); ?></td>
                                <td><?php echo esc_html(ucwords(str_replace('_', ' ', $expense->expense_payment_method))); ?></td>
                                <td>
                                    <?php if ($expense->assigned_to === 'project') : ?>
                                        <span style="background: #f3e5f5; color: #7b1fa2; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">PROJECT</span>
                                    <?php elseif ($expense->assigned_to === 'client') : ?>
                                        <span style="background: #e8f5e9; color: #2e7d32; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">CLIENT</span>
                                    <?php else : ?>
                                        <span style="background: #fce4ec; color: #c2185b; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">GENERAL</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($expense->assigned_to === 'project' && $expense->project_name) {
                                        echo esc_html($expense->project_name);
                                    } elseif ($expense->assigned_to === 'client' && $expense->client_name) {
                                        echo esc_html($expense->client_name);
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                                <td><?php echo esc_html($expense->expense_description ? $expense->expense_description : '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td colspan="3" style="text-align: right; padding-right: 10px;">TOTAL:</td>
                            <td style="font-weight: bold; font-size: 16px; color: #e65100;">$<?php echo number_format($total_amount, 2); ?></td>
                            <td colspan="4"></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else : ?>
                <div class="notice notice-warning inline">
                    <p>No expenses found for the selected date range.</p>
                </div>
            <?php endif; ?>

            <!-- Export buttons at bottom -->
            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <a href="<?php echo add_query_arg(array_merge($export_params, ['export' => 'csv']), admin_url('admin.php')); ?>" class="button button-primary">📊 Export to CSV</a>
                <a href="<?php echo add_query_arg(array_merge($export_params, ['export' => 'pdf']), admin_url('admin.php')); ?>" class="button button-primary">📄 Export to PDF</a>
                <button type="button" class="button button-secondary" onclick="window.print();">🖨️ Print to PDF</button>
            </div>
        <?php
    }

    /**
     * Generate All Expenses Overview Report (Administrator only)
     * Shows all expenses system-wide with comprehensive breakdowns
     */
    private function generate_all_expenses_overview($current_user) {
        global $wpdb;

        // Handle CSV export
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $this->export_all_expenses_overview_csv();
            return;
        }

        // Handle PDF export
        if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
            $this->export_all_expenses_overview_pdf();
            return;
        }

        $expense_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $client_table = $wpdb->prefix . 'users';

        // Get filter parameters
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        $filter_category = isset($_GET['filter_category']) ? sanitize_text_field($_GET['filter_category']) : '';
        $filter_payment_method = isset($_GET['filter_payment_method']) ? sanitize_text_field($_GET['filter_payment_method']) : '';
        $filter_assigned_to = isset($_GET['filter_assigned_to']) ? sanitize_text_field($_GET['filter_assigned_to']) : '';

        // Build query to get all expenses with project/client details
        $query = "SELECT
                    e.ID as expense_id,
                    e.expense_name,
                    e.expense_description,
                    e.expense_date,
                    e.expense_payment_method,
                    e.amount,
                    e.category,
                    e.assigned_to,
                    e.assigned_to_id,
                    e.created_at,
                    p.name as project_name,
                    c.display_name as client_name
                FROM {$expense_table} e
                LEFT JOIN {$project_table} p ON (e.assigned_to = 'project' AND e.assigned_to_id = p.ID)
                LEFT JOIN {$client_table} c ON (e.assigned_to = 'client' AND e.assigned_to_id = c.ID)
                WHERE 1=1";

        $params = [];

        // Add date filters
        if (!empty($start_date)) {
            $query .= " AND e.expense_date >= %s";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $query .= " AND e.expense_date <= %s";
            $params[] = $end_date;
        }

        // Add category filter
        if (!empty($filter_category)) {
            $query .= " AND e.category = %s";
            $params[] = $filter_category;
        }

        // Add payment method filter
        if (!empty($filter_payment_method)) {
            $query .= " AND e.expense_payment_method = %s";
            $params[] = $filter_payment_method;
        }

        // Add assigned to filter
        if (!empty($filter_assigned_to)) {
            $query .= " AND e.assigned_to = %s";
            $params[] = $filter_assigned_to;
        }

        $query .= " ORDER BY e.expense_date DESC, e.ID DESC";

        // Prepare the final SQL for display
        $final_sql = !empty($params) ? $wpdb->prepare($query, $params) : $query;

        $results = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);

        // Get all unique categories and payment methods for filters
        $all_categories = $wpdb->get_col("SELECT DISTINCT category FROM {$expense_table} ORDER BY category ASC");
        $all_payment_methods = ['personal_card', 'company_card', 'bank_transfer', 'cash', 'other'];

        // Calculate comprehensive statistics
        $total_amount = 0;
        $by_category = [];
        $by_payment_method = [];
        $by_assigned_to = [
            'project' => ['count' => 0, 'amount' => 0],
            'client' => ['count' => 0, 'amount' => 0],
            'general' => ['count' => 0, 'amount' => 0]
        ];
        $by_month = [];

        foreach ($results as $expense) {
            $amount = floatval($expense->amount);
            $total_amount += $amount;

            // By category
            if (!isset($by_category[$expense->category])) {
                $by_category[$expense->category] = ['count' => 0, 'amount' => 0];
            }
            $by_category[$expense->category]['count']++;
            $by_category[$expense->category]['amount'] += $amount;

            // By payment method
            if (!isset($by_payment_method[$expense->expense_payment_method])) {
                $by_payment_method[$expense->expense_payment_method] = ['count' => 0, 'amount' => 0];
            }
            $by_payment_method[$expense->expense_payment_method]['count']++;
            $by_payment_method[$expense->expense_payment_method]['amount'] += $amount;

            // By assigned to
            $by_assigned_to[$expense->assigned_to]['count']++;
            $by_assigned_to[$expense->assigned_to]['amount'] += $amount;

            // By month
            $month_key = date('Y-m', strtotime($expense->expense_date));
            if (!isset($by_month[$month_key])) {
                $by_month[$month_key] = ['count' => 0, 'amount' => 0];
            }
            $by_month[$month_key]['count']++;
            $by_month[$month_key]['amount'] += $amount;
        }

        // Sort by_month
        ksort($by_month);

        // Display the SQL Query (only when WP_DEBUG is enabled)
        ?>
            <?php if (WP_DEBUG): ?>
            <div style="background: #f8f9fa; border: 2px solid #0073aa; padding: 20px; margin: 20px 0; border-radius: 5px;">
                <h3 style="margin-top: 0; color: #0073aa;">📊 SQL Query</h3>
                <pre style="background: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; line-height: 1.5;"><?php echo esc_html($final_sql); ?></pre>
                <p><strong>Results:</strong> <?php echo count($results); ?> expenses found</p>
                <?php if ($wpdb->last_error): ?>
                    <p style="color: red;"><strong>Error:</strong> <?php echo esc_html($wpdb->last_error); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="tax-report-controls" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>" />
                    <input type="hidden" name="report_slug" value="all_expenses_overview" />

                    <div>
                        <label for="start_date" style="font-weight: bold; display: block; margin-bottom: 5px;">From Date:</label>
                        <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr($start_date); ?>" style="width: 100%; padding: 8px 12px;" />
                    </div>

                    <div>
                        <label for="end_date" style="font-weight: bold; display: block; margin-bottom: 5px;">To Date:</label>
                        <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr($end_date); ?>" style="width: 100%; padding: 8px 12px;" />
                    </div>

                    <div>
                        <label for="filter_category" style="font-weight: bold; display: block; margin-bottom: 5px;">Category:</label>
                        <select name="filter_category" id="filter_category" style="width: 100%; padding: 8px 12px;">
                            <option value="">All Categories</option>
                            <?php foreach ($all_categories as $cat): ?>
                                <option value="<?php echo esc_attr($cat); ?>" <?php selected($filter_category, $cat); ?>>
                                    <?php echo esc_html(ucfirst($cat)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="filter_payment_method" style="font-weight: bold; display: block; margin-bottom: 5px;">Payment Method:</label>
                        <select name="filter_payment_method" id="filter_payment_method" style="width: 100%; padding: 8px 12px;">
                            <option value="">All Methods</option>
                            <?php foreach ($all_payment_methods as $method): ?>
                                <option value="<?php echo esc_attr($method); ?>" <?php selected($filter_payment_method, $method); ?>>
                                    <?php echo esc_html(ucwords(str_replace('_', ' ', $method))); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="filter_assigned_to" style="font-weight: bold; display: block; margin-bottom: 5px;">Assigned To:</label>
                        <select name="filter_assigned_to" id="filter_assigned_to" style="width: 100%; padding: 8px 12px;">
                            <option value="">All Types</option>
                            <option value="project" <?php selected($filter_assigned_to, 'project'); ?>>Project</option>
                            <option value="client" <?php selected($filter_assigned_to, 'client'); ?>>Client</option>
                            <option value="general" <?php selected($filter_assigned_to, 'general'); ?>>General</option>
                        </select>
                    </div>

                    <div style="display: flex; align-items: flex-end; gap: 10px;">
                        <button type="submit" class="button button-primary" style="flex: 1;">Generate Report</button>
                        <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-a-report&report_slug=all_expenses_overview'); ?>" class="button" style="flex: 1; text-align: center;">Clear</a>
                    </div>
                </form>
            </div>

            <?php
            // Build export URL with current filters
            $export_params = [
                'page' => $_GET['page'],
                'report_slug' => 'all_expenses_overview'
            ];
            if (!empty($start_date)) $export_params['start_date'] = $start_date;
            if (!empty($end_date)) $export_params['end_date'] = $end_date;
            if (!empty($filter_category)) $export_params['filter_category'] = $filter_category;
            if (!empty($filter_payment_method)) $export_params['filter_payment_method'] = $filter_payment_method;
            if (!empty($filter_assigned_to)) $export_params['filter_assigned_to'] = $filter_assigned_to;
            ?>

            <!-- Summary Cards -->
            <div class="tax-report-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px;">
                <div class="summary-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 5px; text-align: center; color: white;">
                    <h3 style="margin: 0 0 10px 0; color: white;">Total Expenses</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;">$<?php echo number_format($total_amount, 2); ?></p>
                </div>

                <div class="summary-card" style="background: #e3f2fd; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #1565c0;">Total Count</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo count($results); ?></p>
                </div>

                <div class="summary-card" style="background: #f3e5f5; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #7b1fa2;">Project</h3>
                    <p style="font-size: 20px; font-weight: bold; margin: 0;">$<?php echo number_format($by_assigned_to['project']['amount'], 2); ?></p>
                    <p style="font-size: 12px; margin: 5px 0 0 0;"><?php echo $by_assigned_to['project']['count']; ?> expenses</p>
                </div>

                <div class="summary-card" style="background: #e8f5e9; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #2e7d32;">Client</h3>
                    <p style="font-size: 20px; font-weight: bold; margin: 0;">$<?php echo number_format($by_assigned_to['client']['amount'], 2); ?></p>
                    <p style="font-size: 12px; margin: 5px 0 0 0;"><?php echo $by_assigned_to['client']['count']; ?> expenses</p>
                </div>

                <div class="summary-card" style="background: #fce4ec; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #c2185b;">General</h3>
                    <p style="font-size: 20px; font-weight: bold; margin: 0;">$<?php echo number_format($by_assigned_to['general']['amount'], 2); ?></p>
                    <p style="font-size: 12px; margin: 5px 0 0 0;"><?php echo $by_assigned_to['general']['count']; ?> expenses</p>
                </div>

                <div class="summary-card" style="background: #e1f5fe; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #0277bd;">Categories</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo count($by_category); ?></p>
                </div>
            </div>

            <!-- Breakdown by Category -->
            <?php if (!empty($by_category)): ?>
            <h3 style="margin-top: 30px;">Breakdown by Category</h3>
            <table class="wp-list-table widefat fixed striped" style="margin-bottom: 30px;">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th style="width: 150px; text-align: right;">Count</th>
                        <th style="width: 150px; text-align: right;">Total Amount</th>
                        <th style="width: 100px; text-align: right;">% of Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Sort by amount descending
                    uasort($by_category, function($a, $b) {
                        return $b['amount'] <=> $a['amount'];
                    });
                    foreach ($by_category as $category => $stats):
                        $percentage = $total_amount > 0 ? ($stats['amount'] / $total_amount) * 100 : 0;
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html(ucfirst($category)); ?></strong></td>
                            <td style="text-align: right;"><?php echo $stats['count']; ?></td>
                            <td style="text-align: right; font-weight: bold;">$<?php echo number_format($stats['amount'], 2); ?></td>
                            <td style="text-align: right;"><?php echo number_format($percentage, 1); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Breakdown by Payment Method -->
            <?php if (!empty($by_payment_method)): ?>
            <h3 style="margin-top: 30px;">Breakdown by Payment Method</h3>
            <table class="wp-list-table widefat fixed striped" style="margin-bottom: 30px;">
                <thead>
                    <tr>
                        <th>Payment Method</th>
                        <th style="width: 150px; text-align: right;">Count</th>
                        <th style="width: 150px; text-align: right;">Total Amount</th>
                        <th style="width: 100px; text-align: right;">% of Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Sort by amount descending
                    uasort($by_payment_method, function($a, $b) {
                        return $b['amount'] <=> $a['amount'];
                    });
                    foreach ($by_payment_method as $method => $stats):
                        $percentage = $total_amount > 0 ? ($stats['amount'] / $total_amount) * 100 : 0;
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $method))); ?></strong></td>
                            <td style="text-align: right;"><?php echo $stats['count']; ?></td>
                            <td style="text-align: right; font-weight: bold;">$<?php echo number_format($stats['amount'], 2); ?></td>
                            <td style="text-align: right;"><?php echo number_format($percentage, 1); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Monthly Trend -->
            <?php if (!empty($by_month) && count($by_month) > 1): ?>
            <h3 style="margin-top: 30px;">Monthly Trend</h3>
            <table class="wp-list-table widefat fixed striped" style="margin-bottom: 30px;">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th style="width: 150px; text-align: right;">Count</th>
                        <th style="width: 150px; text-align: right;">Total Amount</th>
                        <th style="width: 200px; text-align: right;">Avg per Expense</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($by_month as $month => $stats):
                        $avg = $stats['count'] > 0 ? $stats['amount'] / $stats['count'] : 0;
                    ?>
                        <tr>
                            <td><strong><?php echo date('F Y', strtotime($month . '-01')); ?></strong></td>
                            <td style="text-align: right;"><?php echo $stats['count']; ?></td>
                            <td style="text-align: right; font-weight: bold;">$<?php echo number_format($stats['amount'], 2); ?></td>
                            <td style="text-align: right;">$<?php echo number_format($avg, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Detailed Expenses Table -->
            <h3 style="margin-top: 30px;">All Expenses</h3>
            <?php if (!empty($results)) : ?>
                <table class="wp-list-table widefat fixed striped" style="margin-bottom: 30px;">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Date</th>
                            <th>Expense Name</th>
                            <th style="width: 120px;">Category</th>
                            <th style="width: 100px;">Amount</th>
                            <th style="width: 120px;">Payment Method</th>
                            <th style="width: 100px;">Assigned To</th>
                            <th>Project/Client</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $expense) : ?>
                            <tr>
                                <td><?php echo esc_html($expense->expense_date); ?></td>
                                <td style="font-weight: 600;"><?php echo esc_html($expense->expense_name); ?></td>
                                <td>
                                    <span style="background: #e3f2fd; color: #1565c0; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">
                                        <?php echo esc_html(strtoupper($expense->category)); ?>
                                    </span>
                                </td>
                                <td style="font-weight: bold; color: #e65100;">$<?php echo number_format($expense->amount, 2); ?></td>
                                <td><?php echo esc_html(ucwords(str_replace('_', ' ', $expense->expense_payment_method))); ?></td>
                                <td>
                                    <?php if ($expense->assigned_to === 'project') : ?>
                                        <span style="background: #f3e5f5; color: #7b1fa2; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">PROJECT</span>
                                    <?php elseif ($expense->assigned_to === 'client') : ?>
                                        <span style="background: #e8f5e9; color: #2e7d32; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">CLIENT</span>
                                    <?php else : ?>
                                        <span style="background: #fce4ec; color: #c2185b; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">GENERAL</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    if ($expense->assigned_to === 'project' && $expense->project_name) {
                                        echo esc_html($expense->project_name);
                                    } elseif ($expense->assigned_to === 'client' && $expense->client_name) {
                                        echo esc_html($expense->client_name);
                                    } else {
                                        echo '—';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td colspan="3" style="text-align: right; padding-right: 10px;">TOTAL:</td>
                            <td style="font-weight: bold; font-size: 16px; color: #e65100;">$<?php echo number_format($total_amount, 2); ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else : ?>
                <div class="notice notice-warning inline">
                    <p>No expenses found for the selected filters.</p>
                </div>
            <?php endif; ?>

            <!-- Export buttons at bottom -->
            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <a href="<?php echo add_query_arg(array_merge($export_params, ['export' => 'csv']), admin_url('admin.php')); ?>" class="button button-primary">📊 Export to CSV</a>
                <a href="<?php echo add_query_arg(array_merge($export_params, ['export' => 'pdf']), admin_url('admin.php')); ?>" class="button button-primary">📄 Export to PDF</a>
                <button type="button" class="button button-secondary" onclick="window.print();">🖨️ Print to PDF</button>
            </div>
        <?php
    }

    /**
     * Generate Client Activity Summary Report (Administrator only)
     * Shows hours and expenses for each client
     */
    private function generate_client_activity_summary($current_user) {
        global $wpdb;

        // Handle CSV export
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $this->export_client_activity_summary_csv();
            return;
        }

        // Handle PDF export
        if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
            $this->export_client_activity_summary_pdf();
            return;
        }

        $time_entry_table = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $expense_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
        $client_table = $wpdb->prefix . 'users';

        // Get all clients for filter dropdown
        $all_clients = $wpdb->get_results("
            SELECT DISTINCT c.ID, c.display_name
            FROM {$client_table} c
            INNER JOIN {$project_table} p ON c.ID = p.client_id
            ORDER BY c.display_name ASC
        ");

        // Get filter parameters
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        $filter_client = isset($_GET['filter_client']) ? intval($_GET['filter_client']) : 0;
        $view_detail = isset($_GET['view_detail']) ? intval($_GET['view_detail']) : 0;

        // If detail view is requested, show detailed breakdown
        if ($view_detail && $filter_client) {
            $this->generate_client_activity_detail($current_user, $filter_client, $start_date, $end_date);
            return;
        }

        // Build query to get client activity with hours and expenses
        $query = "SELECT
                    c.ID as client_id,
                    c.display_name as client_name,
                    c.user_email as client_email,
                    COUNT(DISTINCT p.ID) as project_count,
                    COUNT(DISTINCT te.ID) as time_entry_count,
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
                     WHERE p2.client_id = c.ID";

        $params = [];

        if (!empty($start_date)) {
            $query .= " AND e.expense_date >= %s";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $query .= " AND e.expense_date <= %s";
            $params[] = $end_date;
        }

        $query .= ") as expense_count,
                    (SELECT COALESCE(SUM(e.amount), 0) FROM {$expense_table} e
                     INNER JOIN {$project_table} p2 ON e.assigned_to = 'project' AND e.assigned_to_id = p2.ID
                     WHERE p2.client_id = c.ID";

        if (!empty($start_date)) {
            $query .= " AND e.expense_date >= %s";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $query .= " AND e.expense_date <= %s";
            $params[] = $end_date;
        }

        $query .= ") as total_expenses,
                    MIN(COALESCE(te.date, te.clock_in_date)) as first_activity,
                    MAX(COALESCE(te.date, te.clock_in_date)) as last_activity
                FROM {$client_table} c
                INNER JOIN {$project_table} p ON c.ID = p.client_id
                LEFT JOIN {$time_entry_table} te ON p.ID = te.project_id
                WHERE 1=1";

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

        // Prepare the final SQL for display
        $final_sql = !empty($params) ? $wpdb->prepare($query, $params) : $query;

        $results = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);

        // Calculate grand totals
        $grand_total_hours = 0;
        $grand_billable_hours = 0;
        $grand_total_expenses = 0;
        $grand_project_count = 0;
        $grand_time_entries = 0;
        $grand_expense_count = 0;

        foreach ($results as $row) {
            $grand_total_hours += floatval($row->total_hours);
            $grand_billable_hours += floatval($row->billable_hours);
            $grand_total_expenses += floatval($row->total_expenses);
            $grand_project_count += intval($row->project_count);
            $grand_time_entries += intval($row->time_entry_count);
            $grand_expense_count += intval($row->expense_count);
        }

        // Display the SQL Query (only when WP_DEBUG is enabled)
        ?>
            <?php if (WP_DEBUG): ?>
            <div style="background: #f8f9fa; border: 2px solid #0073aa; padding: 20px; margin: 20px 0; border-radius: 5px;">
                <h3 style="margin-top: 0; color: #0073aa;">📊 SQL Query</h3>
                <pre style="background: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; line-height: 1.5;"><?php echo esc_html($final_sql); ?></pre>
                <p><strong>Results:</strong> <?php echo count($results); ?> clients found</p>
                <?php if ($wpdb->last_error): ?>
                    <p style="color: red;"><strong>Error:</strong> <?php echo esc_html($wpdb->last_error); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="tax-report-controls" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>" />
                    <input type="hidden" name="report_slug" value="client_activity_summary" />

                    <label for="filter_client" style="font-weight: bold; margin: 0;">Client:</label>
                    <select name="filter_client" id="filter_client" style="padding: 8px 12px; min-width: 200px;">
                        <option value="">All Clients</option>
                        <?php foreach ($all_clients as $client): ?>
                            <option value="<?php echo esc_attr($client->ID); ?>" <?php selected($filter_client, $client->ID); ?>>
                                <?php echo esc_html($client->display_name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="start_date" style="font-weight: bold; margin: 0;">From:</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr($start_date); ?>" style="padding: 8px 12px;" />

                    <label for="end_date" style="font-weight: bold; margin: 0;">To:</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr($end_date); ?>" style="padding: 8px 12px;" />

                    <button type="submit" class="button button-primary">Generate Report</button>
                    <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-a-report&report_slug=client_activity_summary'); ?>" class="button">Clear</a>
                </form>
            </div>

            <?php
            // Build export URL with current filters
            $export_params = [
                'page' => $_GET['page'],
                'report_slug' => 'client_activity_summary'
            ];
            if (!empty($filter_client)) $export_params['filter_client'] = $filter_client;
            if (!empty($start_date)) $export_params['start_date'] = $start_date;
            if (!empty($end_date)) $export_params['end_date'] = $end_date;
            ?>

            <!-- Summary Cards -->
            <div class="tax-report-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px;">
                <div class="summary-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 5px; text-align: center; color: white;">
                    <h3 style="margin: 0 0 10px 0; color: white;">Active Clients</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo count($results); ?></p>
                </div>

                <div class="summary-card" style="background: #e3f2fd; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #1565c0;">Total Hours</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format($grand_total_hours, 2); ?></p>
                </div>

                <div class="summary-card" style="background: #e8f5e9; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #2e7d32;">Billable Hours</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format($grand_billable_hours, 2); ?></p>
                </div>

                <div class="summary-card" style="background: #fff3e0; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #e65100;">Total Expenses</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;">$<?php echo number_format($grand_total_expenses, 2); ?></p>
                </div>

                <div class="summary-card" style="background: #f3e5f5; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #7b1fa2;">Total Projects</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo $grand_project_count; ?></p>
                </div>

                <div class="summary-card" style="background: #fce4ec; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #c2185b;">Time Entries</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo $grand_time_entries; ?></p>
                </div>
            </div>

            <!-- Client Activity Table -->
            <?php if (!empty($results)) : ?>
                <table class="wp-list-table widefat fixed striped" style="margin-bottom: 30px;">
                    <thead>
                        <tr>
                            <th>Client Name</th>
                            <th>Email</th>
                            <th style="width: 80px; text-align: right;">Projects</th>
                            <th style="width: 100px; text-align: right;">Total Hours</th>
                            <th style="width: 100px; text-align: right;">Billable</th>
                            <th style="width: 100px; text-align: right;">Non-Billable</th>
                            <th style="width: 100px; text-align: right;">Expenses</th>
                            <th style="width: 120px; text-align: right;">Expense $</th>
                            <th style="width: 100px;">First Activity</th>
                            <th style="width: 100px;">Last Activity</th>
                            <th style="width: 120px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row) :
                            $non_billable = floatval($row->total_hours) - floatval($row->billable_hours);
                            // Build detail report URL - show detailed breakdown view
                            $detail_url = add_query_arg([
                                'page' => $_GET['page'],
                                'report_slug' => 'client_activity_summary',
                                'view_detail' => 1,
                                'filter_client' => $row->client_id,
                                'start_date' => $start_date,
                                'end_date' => $end_date
                            ], admin_url('admin.php'));
                        ?>
                            <tr>
                                <td style="font-weight: 600;"><?php echo esc_html($row->client_name); ?></td>
                                <td><?php echo esc_html($row->client_email); ?></td>
                                <td style="text-align: right;"><?php echo intval($row->project_count); ?></td>
                                <td style="text-align: right; font-weight: bold;"><?php echo number_format($row->total_hours, 2); ?></td>
                                <td style="text-align: right; color: #2e7d32;"><?php echo number_format($row->billable_hours, 2); ?></td>
                                <td style="text-align: right; color: #999;"><?php echo number_format($non_billable, 2); ?></td>
                                <td style="text-align: right;"><?php echo intval($row->expense_count); ?></td>
                                <td style="text-align: right; font-weight: bold; color: #e65100;">$<?php echo number_format($row->total_expenses, 2); ?></td>
                                <td><?php echo esc_html($row->first_activity ? date('Y-m-d', strtotime($row->first_activity)) : '—'); ?></td>
                                <td><?php echo esc_html($row->last_activity ? date('Y-m-d', strtotime($row->last_activity)) : '—'); ?></td>
                                <td style="text-align: center;">
                                    <a href="<?php echo esc_url($detail_url); ?>" class="button button-small" style="white-space: nowrap;">
                                        📋 View Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f0f0f0; font-weight: bold;">
                            <td colspan="2" style="text-align: right; padding-right: 10px;">TOTALS:</td>
                            <td style="text-align: right; font-size: 16px;"><?php echo $grand_project_count; ?></td>
                            <td style="text-align: right; font-size: 16px;"><?php echo number_format($grand_total_hours, 2); ?></td>
                            <td style="text-align: right; font-size: 16px;"><?php echo number_format($grand_billable_hours, 2); ?></td>
                            <td style="text-align: right; font-size: 16px;"><?php echo number_format($grand_total_hours - $grand_billable_hours, 2); ?></td>
                            <td style="text-align: right; font-size: 16px;"><?php echo $grand_expense_count; ?></td>
                            <td style="text-align: right; font-size: 16px; color: #e65100;">$<?php echo number_format($grand_total_expenses, 2); ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                </table>
            <?php else : ?>
                <div class="notice notice-warning inline">
                    <p>No client activity found for the selected date range.</p>
                </div>
            <?php endif; ?>

            <!-- Export buttons at bottom -->
            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <a href="<?php echo add_query_arg(array_merge($export_params, ['export' => 'csv']), admin_url('admin.php')); ?>" class="button button-primary">📊 Export to CSV</a>
                <a href="<?php echo add_query_arg(array_merge($export_params, ['export' => 'pdf']), admin_url('admin.php')); ?>" class="button button-primary">📄 Export to PDF</a>
                <button type="button" class="button button-secondary" onclick="window.print();">🖨️ Print to PDF</button>
            </div>
        <?php
    }

    /**
     * Generate Client Activity Detail Report
     * Shows detailed breakdown of all time entries and expenses for a specific client
     */
    private function generate_client_activity_detail($current_user, $client_id, $start_date, $end_date) {
        global $wpdb;

        // Table names
        $time_entry_table = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';
        $expense_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $member_table = $wpdb->prefix . TIMEGROW_PREFIX . 'team_member_tracker';
        $client_table = $wpdb->prefix . 'users';

        // Get client information
        $client = $wpdb->get_row($wpdb->prepare("SELECT ID, display_name, user_email FROM {$client_table} WHERE ID = %d", $client_id));

        if (!$client) {
            echo '<div class="notice notice-error"><p>Client not found.</p></div>';
            return;
        }

        // Query all time entries for this client's projects
        $time_query = "SELECT
                        te.*,
                        p.name as project_name,
                        m.name as member_name,
                        COALESCE(te.date, DATE(te.clock_in_date)) as entry_date,
                        CASE
                            WHEN te.hours IS NOT NULL AND te.hours > 0 THEN te.hours
                            WHEN te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL
                            THEN TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600
                            ELSE 0
                        END as calculated_hours,
                        CASE
                            WHEN te.hours IS NOT NULL AND te.hours > 0 THEN 'MANUAL'
                            ELSE 'CLOCK'
                        END as entry_type
                    FROM {$time_entry_table} te
                    INNER JOIN {$project_table} p ON te.project_id = p.ID
                    INNER JOIN {$member_table} m ON te.member_id = m.ID
                    WHERE p.client_id = %d";

        $time_params = [$client_id];

        if (!empty($start_date)) {
            $time_query .= " AND COALESCE(te.date, DATE(te.clock_in_date)) >= %s";
            $time_params[] = $start_date;
        }

        if (!empty($end_date)) {
            $time_query .= " AND COALESCE(te.date, DATE(te.clock_in_date)) <= %s";
            $time_params[] = $end_date;
        }

        $time_query .= " ORDER BY entry_date DESC, te.ID DESC";

        $time_entries = $wpdb->get_results($wpdb->prepare($time_query, $time_params));

        // Store the prepared SQL for debugging
        $time_final_sql = $wpdb->prepare($time_query, $time_params);

        // Query all expenses for this client's projects
        $expense_query = "SELECT
                            e.*,
                            p.name as project_name
                        FROM {$expense_table} e
                        INNER JOIN {$project_table} p ON e.assigned_to = 'project' AND e.assigned_to_id = p.ID
                        WHERE p.client_id = %d";

        $expense_params = [$client_id];

        if (!empty($start_date)) {
            $expense_query .= " AND e.expense_date >= %s";
            $expense_params[] = $start_date;
        }

        if (!empty($end_date)) {
            $expense_query .= " AND e.expense_date <= %s";
            $expense_params[] = $end_date;
        }

        $expense_query .= " ORDER BY e.expense_date DESC, e.ID DESC";

        $expenses = $wpdb->get_results($wpdb->prepare($expense_query, $expense_params));

        // Store the prepared SQL for debugging
        $expense_final_sql = $wpdb->prepare($expense_query, $expense_params);

        // Calculate totals
        $total_hours = 0;
        $billable_hours = 0;
        $total_expenses = 0;

        foreach ($time_entries as $entry) {
            $hours = $entry->hours ?? 0;
            if ($entry->clock_in_date && $entry->clock_out_date && $hours == 0) {
                $hours = (strtotime($entry->clock_out_date) - strtotime($entry->clock_in_date)) / 3600;
            }
            $total_hours += $hours;
            if ($entry->billable) {
                $billable_hours += $hours;
            }
        }

        foreach ($expenses as $expense) {
            $total_expenses += floatval($expense->amount);
        }

        // Build back URL
        $back_url = remove_query_arg('view_detail');
        $back_url = remove_query_arg('filter_client', $back_url);
        $back_url = add_query_arg('filter_client', $client_id, $back_url);

        ?>
            <div class="wrap timegrow-report">
                <h1 class="wp-heading-inline">Client Activity Detail: <?php echo esc_html($client->display_name); ?></h1>

                <!-- Back button -->
                <div style="margin: 20px 0;">
                    <a href="<?php echo esc_url($back_url); ?>" class="button">← Back to Summary</a>
                </div>

                <!-- Client Info -->
                <div style="background: #f0f0f1; padding: 15px; margin: 20px 0; border-radius: 5px;">
                    <p style="margin: 5px 0;"><strong>Client:</strong> <?php echo esc_html($client->display_name); ?></p>
                    <p style="margin: 5px 0;"><strong>Email:</strong> <?php echo esc_html($client->user_email); ?></p>
                    <?php if (!empty($start_date) || !empty($end_date)): ?>
                        <p style="margin: 5px 0;"><strong>Date Range:</strong>
                            <?php
                            if (!empty($start_date) && !empty($end_date)) {
                                echo esc_html(date('M j, Y', strtotime($start_date))) . ' - ' . esc_html(date('M j, Y', strtotime($end_date)));
                            } elseif (!empty($start_date)) {
                                echo 'From ' . esc_html(date('M j, Y', strtotime($start_date)));
                            } elseif (!empty($end_date)) {
                                echo 'Until ' . esc_html(date('M j, Y', strtotime($end_date)));
                            }
                            ?>
                        </p>
                    <?php endif; ?>
                </div>

                <!-- SQL Debug (only when WP_DEBUG is enabled) -->
                <?php if (WP_DEBUG): ?>
                <div style="background: #f8f9fa; border: 2px solid #0073aa; padding: 20px; margin: 20px 0; border-radius: 5px;">
                    <h3 style="margin-top: 0; color: #0073aa;">📊 SQL Queries - Client Activity Detail</h3>

                    <h4 style="color: #0073aa; margin-top: 15px;">Time Entries Query:</h4>
                    <pre style="background: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; line-height: 1.5;"><?php echo esc_html($time_final_sql); ?></pre>
                    <p><strong>Results:</strong> <?php echo count($time_entries); ?> time entries found</p>
                    <?php if ($wpdb->last_error): ?>
                        <p style="color: red;"><strong>Error:</strong> <?php echo esc_html($wpdb->last_error); ?></p>
                    <?php endif; ?>

                    <h4 style="color: #0073aa; margin-top: 15px;">Expenses Query:</h4>
                    <pre style="background: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; line-height: 1.5;"><?php echo esc_html($expense_final_sql); ?></pre>
                    <p><strong>Results:</strong> <?php echo count($expenses); ?> expenses found</p>
                    <?php if ($wpdb->last_error): ?>
                        <p style="color: red;"><strong>Error:</strong> <?php echo esc_html($wpdb->last_error); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Summary Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;">
                    <div style="background: #fff; padding: 20px; border-left: 4px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Total Hours</div>
                        <div style="font-size: 32px; font-weight: bold; color: #2271b1;"><?php echo number_format($total_hours, 2); ?></div>
                    </div>
                    <div style="background: #fff; padding: 20px; border-left: 4px solid #00a32a; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Billable Hours</div>
                        <div style="font-size: 32px; font-weight: bold; color: #00a32a;"><?php echo number_format($billable_hours, 2); ?></div>
                    </div>
                    <div style="background: #fff; padding: 20px; border-left: 4px solid #d63638; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Total Expenses</div>
                        <div style="font-size: 32px; font-weight: bold; color: #d63638;">$<?php echo number_format($total_expenses, 2); ?></div>
                    </div>
                    <div style="background: #fff; padding: 20px; border-left: 4px solid #996800; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <div style="font-size: 14px; color: #666; margin-bottom: 5px;">Time Entries</div>
                        <div style="font-size: 32px; font-weight: bold; color: #996800;"><?php echo count($time_entries); ?></div>
                    </div>
                </div>

                <!-- Time Entries Table -->
                <h2 style="margin-top: 30px;">Time Entries (<?php echo count($time_entries); ?>)</h2>
                <?php if (!empty($time_entries)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Project</th>
                                <th>Member</th>
                                <th>Description</th>
                                <th>Clock In</th>
                                <th>Clock Out</th>
                                <th style="text-align: right;">Hours</th>
                                <th style="text-align: center;">Billable</th>
                                <th style="text-align: center;">Billed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($time_entries as $entry): ?>
                                <?php
                                $hours = $entry->hours ?? 0;
                                if ($entry->clock_in_date && $entry->clock_out_date && $hours == 0) {
                                    $hours = (strtotime($entry->clock_out_date) - strtotime($entry->clock_in_date)) / 3600;
                                }
                                $entry_type = ($entry->hours && $entry->hours > 0) ? 'MANUAL' : 'CLOCK';
                                $type_color = $entry_type === 'MANUAL' ? '#2271b1' : '#00a32a';
                                ?>
                                <tr>
                                    <td><?php echo esc_html(date('M j, Y', strtotime($entry->entry_date))); ?></td>
                                    <td>
                                        <span style="background: <?php echo $type_color; ?>; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">
                                            <?php echo $entry_type; ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($entry->project_name); ?></td>
                                    <td><?php echo esc_html($entry->member_name); ?></td>
                                    <td><?php echo esc_html($entry->description ?: '-'); ?></td>
                                    <td><?php echo $entry->clock_in_date ? esc_html(date('g:i A', strtotime($entry->clock_in_date))) : '-'; ?></td>
                                    <td><?php echo $entry->clock_out_date ? esc_html(date('g:i A', strtotime($entry->clock_out_date))) : '-'; ?></td>
                                    <td style="text-align: right;"><?php echo number_format($hours, 2); ?></td>
                                    <td style="text-align: center;">
                                        <?php if ($entry->billable): ?>
                                            <span style="color: #00a32a; font-weight: bold;">✓</span>
                                        <?php else: ?>
                                            <span style="color: #dba617;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($entry->billed): ?>
                                            <span style="color: #2271b1; font-weight: bold;">✓</span>
                                        <?php else: ?>
                                            <span style="color: #dba617;">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f0f0f1; font-weight: bold;">
                                <td colspan="7" style="text-align: right; padding-right: 10px;">Total Hours:</td>
                                <td style="text-align: right;"><?php echo number_format($total_hours, 2); ?></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php else: ?>
                    <div class="notice notice-info inline">
                        <p>No time entries found for this client.</p>
                    </div>
                <?php endif; ?>

                <!-- Expenses Table -->
                <h2 style="margin-top: 40px;">Expenses (<?php echo count($expenses); ?>)</h2>
                <?php if (!empty($expenses)): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Project</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Payment Method</th>
                                <th style="text-align: right;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenses as $expense): ?>
                                <tr>
                                    <td><?php echo esc_html(date('M j, Y', strtotime($expense->expense_date))); ?></td>
                                    <td><?php echo esc_html($expense->project_name); ?></td>
                                    <td><?php echo esc_html($expense->expense_name); ?></td>
                                    <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $expense->category))); ?></td>
                                    <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $expense->expense_payment_method))); ?></td>
                                    <td style="text-align: right;">$<?php echo number_format($expense->amount, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f0f0f1; font-weight: bold;">
                                <td colspan="5" style="text-align: right; padding-right: 10px;">Total Expenses:</td>
                                <td style="text-align: right;">$<?php echo number_format($total_expenses, 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php else: ?>
                    <div class="notice notice-info inline">
                        <p>No expenses found for this client.</p>
                    </div>
                <?php endif; ?>

                <!-- Back button at bottom -->
                <div style="margin: 30px 0;">
                    <a href="<?php echo esc_url($back_url); ?>" class="button">← Back to Summary</a>
                </div>
            </div>
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

    /**
     * Export My Detailed Time Log to CSV
     */
    private function export_my_time_entries_detailed_csv($current_user) {
        global $wpdb;

        $time_entry_table = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $member_table = $wpdb->prefix . TIMEGROW_PREFIX . 'team_member_tracker';
        $client_table = $wpdb->prefix . 'users';

        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

        // Same query as main report
        $query = "SELECT
                    te.ID as entry_id,
                    te.date as manual_date,
                    te.hours as manual_hours,
                    te.clock_in_date,
                    te.clock_out_date,
                    te.entry_type,
                    te.billable,
                    te.billed,
                    te.description,
                    COALESCE(te.date, DATE(te.clock_in_date)) as entry_date,
                    CASE
                        WHEN te.hours IS NOT NULL AND te.hours > 0 THEN te.hours
                        WHEN te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL
                        THEN TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600
                        ELSE 0
                    END as calculated_hours,
                    p.name as project_name,
                    c.display_name as client_name,
                    m.name as member_name
                FROM {$time_entry_table} te
                INNER JOIN {$project_table} p ON te.project_id = p.ID
                INNER JOIN {$member_table} m ON te.member_id = m.ID
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

        $query .= " ORDER BY COALESCE(te.date, te.clock_in_date) DESC";

        $results = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);

        // Set headers for CSV download
        $filename = 'detailed_time_log_' . ($start_date ? $start_date . '_to_' . $end_date : date('Y-m-d')) . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        // Create output stream
        $output = fopen('php://output', 'w');

        // Add BOM for Excel UTF-8 support
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Add CSV headers
        fputcsv($output, [
            'Date',
            'Type',
            'Project',
            'Client',
            'Member',
            'Clock In',
            'Clock Out',
            'Hours',
            'Billable',
            'Billed',
            'Description'
        ]);

        // Add data rows
        foreach ($results as $entry) {
            fputcsv($output, [
                $entry->entry_date,
                $entry->entry_type === 'MAN' ? 'Manual' : 'Clock',
                $entry->project_name,
                $entry->client_name,
                $entry->member_name,
                $entry->clock_in_date ? date('g:i A', strtotime($entry->clock_in_date)) : '',
                $entry->clock_out_date ? date('g:i A', strtotime($entry->clock_out_date)) : '',
                number_format($entry->calculated_hours, 2),
                $entry->billable ? 'Yes' : 'No',
                $entry->billed ? 'Yes' : 'No',
                $entry->description
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Export My Detailed Time Log to PDF
     */
    private function export_my_time_entries_detailed_pdf($current_user) {
        global $wpdb;

        $time_entry_table = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $member_table = $wpdb->prefix . TIMEGROW_PREFIX . 'team_member_tracker';
        $client_table = $wpdb->prefix . 'users';

        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

        // Same query as main report
        $query = "SELECT
                    te.ID as entry_id,
                    te.entry_type,
                    te.billable,
                    te.billed,
                    te.description,
                    COALESCE(te.date, DATE(te.clock_in_date)) as entry_date,
                    CASE
                        WHEN te.hours IS NOT NULL AND te.hours > 0 THEN te.hours
                        WHEN te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL
                        THEN TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600
                        ELSE 0
                    END as calculated_hours,
                    p.name as project_name,
                    c.display_name as client_name,
                    m.name as member_name,
                    te.clock_in_date,
                    te.clock_out_date
                FROM {$time_entry_table} te
                INNER JOIN {$project_table} p ON te.project_id = p.ID
                INNER JOIN {$member_table} m ON te.member_id = m.ID
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

        $query .= " ORDER BY COALESCE(te.date, te.clock_in_date) DESC";

        $results = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);

        $total_hours = array_sum(array_map(function($e) { return floatval($e->calculated_hours); }, $results));

        // Generate HTML for PDF
        header('Content-Type: text/html; charset=utf-8');

        $date_range = $start_date && $end_date ? "$start_date to $end_date" : "All Time";

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Detailed Time Log - ' . esc_html($date_range) . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #333; border-bottom: 3px solid #0073aa; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background: #0073aa; color: white; padding: 10px; text-align: left; }
                td { padding: 8px; border-bottom: 1px solid #ddd; }
                tr:nth-child(even) { background: #f9f9f9; }
                .summary { background: #e3f2fd; padding: 15px; margin: 20px 0; border-radius: 5px; }
                @media print { body { margin: 0; } }
            </style>
        </head>
        <body>
            <h1>Detailed Time Log</h1>
            <p><strong>Date Range:</strong> ' . esc_html($date_range) . '</p>
            <p><strong>Generated:</strong> ' . date('F j, Y g:i A') . '</p>

            <div class="summary">
                <strong>Total Hours:</strong> ' . number_format($total_hours, 2) . ' |
                <strong>Total Entries:</strong> ' . count($results) . '
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Project</th>
                        <th>Client</th>
                        <th>Hours</th>
                        <th>Billable</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($results as $entry) {
            $html .= '<tr>';
            $html .= '<td>' . esc_html($entry->entry_date) . '</td>';
            $html .= '<td>' . ($entry->entry_type === 'MAN' ? 'Manual' : 'Clock') . '</td>';
            $html .= '<td>' . esc_html($entry->project_name) . '</td>';
            $html .= '<td>' . esc_html($entry->client_name) . '</td>';
            $html .= '<td>' . number_format($entry->calculated_hours, 2) . '</td>';
            $html .= '<td>' . ($entry->billable ? '✓' : '—') . '</td>';
            $html .= '<td>' . esc_html($entry->description ? $entry->description : '—') . '</td>';
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

    /**
     * Export My Expenses Report to CSV
     */
    private function export_my_expenses_report_csv($current_user) {
        global $wpdb;

        $expense_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $client_table = $wpdb->prefix . 'users';

        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

        // Same query as main report
        $query = "SELECT
                    e.expense_name,
                    e.expense_description,
                    e.expense_date,
                    e.expense_payment_method,
                    e.amount,
                    e.category,
                    e.assigned_to,
                    p.name as project_name,
                    c.display_name as client_name
                FROM {$expense_table} e
                LEFT JOIN {$project_table} p ON (e.assigned_to = 'project' AND e.assigned_to_id = p.ID)
                LEFT JOIN {$client_table} c ON (e.assigned_to = 'client' AND e.assigned_to_id = c.ID)
                WHERE 1=1";

        $params = [];

        if (!current_user_can('administrator')) {
            $time_entry_table = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';
            $user_projects_query = $wpdb->prepare(
                "SELECT DISTINCT project_id FROM {$time_entry_table} WHERE member_id = %d",
                $current_user->ID
            );
            $user_projects = $wpdb->get_col($user_projects_query);

            if (!empty($user_projects)) {
                $placeholders = implode(',', array_fill(0, count($user_projects), '%d'));
                $query .= " AND ((e.assigned_to = 'project' AND e.assigned_to_id IN ($placeholders)) OR e.assigned_to = 'general')";
                $params = array_merge($params, $user_projects);
            } else {
                $query .= " AND e.assigned_to = 'general'";
            }
        }

        if (!empty($start_date)) {
            $query .= " AND e.expense_date >= %s";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $query .= " AND e.expense_date <= %s";
            $params[] = $end_date;
        }

        $query .= " ORDER BY e.expense_date DESC";

        $results = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);

        // Set headers for CSV download
        $filename = 'expenses_report_' . ($start_date ? $start_date . '_to_' . $end_date : date('Y-m-d')) . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        // Create output stream
        $output = fopen('php://output', 'w');

        // Add BOM for Excel UTF-8 support
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Add CSV headers
        fputcsv($output, [
            'Date',
            'Expense Name',
            'Category',
            'Amount',
            'Payment Method',
            'Assigned To',
            'Project/Client',
            'Description'
        ]);

        // Add data rows
        foreach ($results as $expense) {
            $assigned_name = '';
            if ($expense->assigned_to === 'project' && $expense->project_name) {
                $assigned_name = $expense->project_name;
            } elseif ($expense->assigned_to === 'client' && $expense->client_name) {
                $assigned_name = $expense->client_name;
            }

            fputcsv($output, [
                $expense->expense_date,
                $expense->expense_name,
                $expense->category,
                number_format($expense->amount, 2),
                ucwords(str_replace('_', ' ', $expense->expense_payment_method)),
                ucfirst($expense->assigned_to),
                $assigned_name,
                $expense->expense_description
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Export My Expenses Report to PDF
     */
    private function export_my_expenses_report_pdf($current_user) {
        global $wpdb;

        $expense_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $client_table = $wpdb->prefix . 'users';

        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';

        // Same query as main report
        $query = "SELECT
                    e.expense_name,
                    e.expense_description,
                    e.expense_date,
                    e.expense_payment_method,
                    e.amount,
                    e.category,
                    e.assigned_to,
                    p.name as project_name,
                    c.display_name as client_name
                FROM {$expense_table} e
                LEFT JOIN {$project_table} p ON (e.assigned_to = 'project' AND e.assigned_to_id = p.ID)
                LEFT JOIN {$client_table} c ON (e.assigned_to = 'client' AND e.assigned_to_id = c.ID)
                WHERE 1=1";

        $params = [];

        if (!current_user_can('administrator')) {
            $time_entry_table = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';
            $user_projects_query = $wpdb->prepare(
                "SELECT DISTINCT project_id FROM {$time_entry_table} WHERE member_id = %d",
                $current_user->ID
            );
            $user_projects = $wpdb->get_col($user_projects_query);

            if (!empty($user_projects)) {
                $placeholders = implode(',', array_fill(0, count($user_projects), '%d'));
                $query .= " AND ((e.assigned_to = 'project' AND e.assigned_to_id IN ($placeholders)) OR e.assigned_to = 'general')";
                $params = array_merge($params, $user_projects);
            } else {
                $query .= " AND e.assigned_to = 'general'";
            }
        }

        if (!empty($start_date)) {
            $query .= " AND e.expense_date >= %s";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $query .= " AND e.expense_date <= %s";
            $params[] = $end_date;
        }

        $query .= " ORDER BY e.expense_date DESC";

        $results = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);

        $total_amount = array_sum(array_map(function($e) { return floatval($e->amount); }, $results));

        // Generate HTML for PDF
        header('Content-Type: text/html; charset=utf-8');

        $date_range = $start_date && $end_date ? "$start_date to $end_date" : "All Time";

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Expenses Report - ' . esc_html($date_range) . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #333; border-bottom: 3px solid #e65100; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background: #e65100; color: white; padding: 10px; text-align: left; font-size: 11px; }
                td { padding: 8px; border-bottom: 1px solid #ddd; font-size: 12px; }
                tr:nth-child(even) { background: #f9f9f9; }
                .summary { background: #fff3e0; padding: 15px; margin: 20px 0; border-radius: 5px; }
                @media print { body { margin: 0; } }
            </style>
        </head>
        <body>
            <h1>Expenses Report</h1>
            <p><strong>Date Range:</strong> ' . esc_html($date_range) . '</p>
            <p><strong>Generated:</strong> ' . date('F j, Y g:i A') . '</p>

            <div class="summary">
                <strong>Total Amount:</strong> $' . number_format($total_amount, 2) . ' |
                <strong>Total Expenses:</strong> ' . count($results) . '
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Expense</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Assigned To</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($results as $expense) {
            $assigned_name = '';
            if ($expense->assigned_to === 'project' && $expense->project_name) {
                $assigned_name = $expense->project_name . ' (Project)';
            } elseif ($expense->assigned_to === 'client' && $expense->client_name) {
                $assigned_name = $expense->client_name . ' (Client)';
            } else {
                $assigned_name = 'General';
            }

            $html .= '<tr>';
            $html .= '<td>' . esc_html($expense->expense_date) . '</td>';
            $html .= '<td>' . esc_html($expense->expense_name) . '</td>';
            $html .= '<td>' . esc_html($expense->category) . '</td>';
            $html .= '<td style="font-weight: bold;">$' . number_format($expense->amount, 2) . '</td>';
            $html .= '<td>' . esc_html(ucwords(str_replace('_', ' ', $expense->expense_payment_method))) . '</td>';
            $html .= '<td>' . esc_html($assigned_name) . '</td>';
            $html .= '<td>' . esc_html($expense->expense_description ? $expense->expense_description : '—') . '</td>';
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

    /**
     * Export All Expenses Overview to CSV
     */
    private function export_all_expenses_overview_csv() {
        global $wpdb;

        $expense_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $client_table = $wpdb->prefix . 'users';

        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        $filter_category = isset($_GET['filter_category']) ? sanitize_text_field($_GET['filter_category']) : '';
        $filter_payment_method = isset($_GET['filter_payment_method']) ? sanitize_text_field($_GET['filter_payment_method']) : '';
        $filter_assigned_to = isset($_GET['filter_assigned_to']) ? sanitize_text_field($_GET['filter_assigned_to']) : '';

        $query = "SELECT
                    e.expense_name,
                    e.expense_description,
                    e.expense_date,
                    e.expense_payment_method,
                    e.amount,
                    e.category,
                    e.assigned_to,
                    p.name as project_name,
                    c.display_name as client_name
                FROM {$expense_table} e
                LEFT JOIN {$project_table} p ON (e.assigned_to = 'project' AND e.assigned_to_id = p.ID)
                LEFT JOIN {$client_table} c ON (e.assigned_to = 'client' AND e.assigned_to_id = c.ID)
                WHERE 1=1";

        $params = [];

        if (!empty($start_date)) {
            $query .= " AND e.expense_date >= %s";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $query .= " AND e.expense_date <= %s";
            $params[] = $end_date;
        }

        if (!empty($filter_category)) {
            $query .= " AND e.category = %s";
            $params[] = $filter_category;
        }

        if (!empty($filter_payment_method)) {
            $query .= " AND e.expense_payment_method = %s";
            $params[] = $filter_payment_method;
        }

        if (!empty($filter_assigned_to)) {
            $query .= " AND e.assigned_to = %s";
            $params[] = $filter_assigned_to;
        }

        $query .= " ORDER BY e.expense_date DESC";

        $results = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);

        // Set headers for CSV download
        $filename = 'all_expenses_overview_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, [
            'Date',
            'Expense Name',
            'Category',
            'Amount',
            'Payment Method',
            'Assigned To',
            'Project/Client',
            'Description'
        ]);

        foreach ($results as $expense) {
            $assigned_name = '';
            if ($expense->assigned_to === 'project' && $expense->project_name) {
                $assigned_name = $expense->project_name;
            } elseif ($expense->assigned_to === 'client' && $expense->client_name) {
                $assigned_name = $expense->client_name;
            }

            fputcsv($output, [
                $expense->expense_date,
                $expense->expense_name,
                $expense->category,
                number_format($expense->amount, 2),
                ucwords(str_replace('_', ' ', $expense->expense_payment_method)),
                ucfirst($expense->assigned_to),
                $assigned_name,
                $expense->expense_description
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Export All Expenses Overview to PDF
     */
    private function export_all_expenses_overview_pdf() {
        global $wpdb;

        $expense_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $client_table = $wpdb->prefix . 'users';

        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        $filter_category = isset($_GET['filter_category']) ? sanitize_text_field($_GET['filter_category']) : '';
        $filter_payment_method = isset($_GET['filter_payment_method']) ? sanitize_text_field($_GET['filter_payment_method']) : '';
        $filter_assigned_to = isset($_GET['filter_assigned_to']) ? sanitize_text_field($_GET['filter_assigned_to']) : '';

        $query = "SELECT
                    e.expense_name,
                    e.expense_description,
                    e.expense_date,
                    e.expense_payment_method,
                    e.amount,
                    e.category,
                    e.assigned_to,
                    p.name as project_name,
                    c.display_name as client_name
                FROM {$expense_table} e
                LEFT JOIN {$project_table} p ON (e.assigned_to = 'project' AND e.assigned_to_id = p.ID)
                LEFT JOIN {$client_table} c ON (e.assigned_to = 'client' AND e.assigned_to_id = c.ID)
                WHERE 1=1";

        $params = [];

        if (!empty($start_date)) {
            $query .= " AND e.expense_date >= %s";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $query .= " AND e.expense_date <= %s";
            $params[] = $end_date;
        }

        if (!empty($filter_category)) {
            $query .= " AND e.category = %s";
            $params[] = $filter_category;
        }

        if (!empty($filter_payment_method)) {
            $query .= " AND e.expense_payment_method = %s";
            $params[] = $filter_payment_method;
        }

        if (!empty($filter_assigned_to)) {
            $query .= " AND e.assigned_to = %s";
            $params[] = $filter_assigned_to;
        }

        $query .= " ORDER BY e.expense_date DESC";

        $results = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, $params)) : $wpdb->get_results($query);

        $total_amount = array_sum(array_map(function($e) { return floatval($e->amount); }, $results));

        header('Content-Type: text/html; charset=utf-8');

        $date_range = $start_date && $end_date ? "$start_date to $end_date" : "All Time";

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>All Expenses Overview - ' . esc_html($date_range) . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                h1 { color: #333; border-bottom: 3px solid #e65100; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 11px; }
                th { background: #e65100; color: white; padding: 8px; text-align: left; }
                td { padding: 6px 8px; border-bottom: 1px solid #ddd; }
                tr:nth-child(even) { background: #f9f9f9; }
                .summary { background: #fff3e0; padding: 15px; margin: 20px 0; border-radius: 5px; }
                @media print { body { margin: 0; } }
            </style>
        </head>
        <body>
            <h1>All Expenses Overview</h1>
            <p><strong>Date Range:</strong> ' . esc_html($date_range) . '</p>
            <p><strong>Generated:</strong> ' . date('F j, Y g:i A') . '</p>

            <div class="summary">
                <strong>Total Amount:</strong> $' . number_format($total_amount, 2) . ' |
                <strong>Total Expenses:</strong> ' . count($results) . '
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Expense</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Type</th>
                        <th>Project/Client</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($results as $expense) {
            $assigned_name = '';
            if ($expense->assigned_to === 'project' && $expense->project_name) {
                $assigned_name = $expense->project_name;
            } elseif ($expense->assigned_to === 'client' && $expense->client_name) {
                $assigned_name = $expense->client_name;
            } else {
                $assigned_name = 'General';
            }

            $html .= '<tr>';
            $html .= '<td>' . esc_html($expense->expense_date) . '</td>';
            $html .= '<td>' . esc_html($expense->expense_name) . '</td>';
            $html .= '<td>' . esc_html($expense->category) . '</td>';
            $html .= '<td style="font-weight: bold;">$' . number_format($expense->amount, 2) . '</td>';
            $html .= '<td>' . esc_html(ucwords(str_replace('_', ' ', $expense->expense_payment_method))) . '</td>';
            $html .= '<td>' . esc_html(ucfirst($expense->assigned_to)) . '</td>';
            $html .= '<td>' . esc_html($assigned_name) . '</td>';
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

    /**
     * Export Client Activity Summary to CSV
     */
    private function export_client_activity_summary_csv() {
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
                    COUNT(DISTINCT te.ID) as time_entry_count,
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
                     WHERE p2.client_id = c.ID) as total_expenses,
                    MIN(COALESCE(te.date, te.clock_in_date)) as first_activity,
                    MAX(COALESCE(te.date, te.clock_in_date)) as last_activity
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

        // Set headers for CSV download
        $filename = 'client_activity_summary_' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, [
            'Client Name',
            'Email',
            'Projects',
            'Total Hours',
            'Billable Hours',
            'Non-Billable Hours',
            'Expense Count',
            'Total Expenses',
            'First Activity',
            'Last Activity'
        ]);

        foreach ($results as $row) {
            $non_billable = floatval($row->total_hours) - floatval($row->billable_hours);
            fputcsv($output, [
                $row->client_name,
                $row->client_email,
                $row->project_count,
                number_format($row->total_hours, 2),
                number_format($row->billable_hours, 2),
                number_format($non_billable, 2),
                $row->expense_count,
                number_format($row->total_expenses, 2),
                $row->first_activity,
                $row->last_activity
            ]);
        }

        fclose($output);
        exit;
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