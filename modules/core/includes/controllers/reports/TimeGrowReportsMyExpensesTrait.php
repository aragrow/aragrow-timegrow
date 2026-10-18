<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * My Expenses Report: page, CSV and PDF export.
 * Used by TimeGrowReportsController (methods moved as-is from the controller).
 */
trait TimeGrowReportsMyExpensesTrait {

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

        // Build query to get all expenses with project/client details and category name
        $category_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_categories';
        $query = "SELECT
                    e.ID as expense_id,
                    e.expense_name,
                    e.expense_description,
                    e.expense_date,
                    e.expense_payment_method,
                    e.amount,
                    e.expense_category_id,
                    ec.name as category_name,
                    ec.slug as category_slug,
                    ec.schedule_c_part,
                    e.assigned_to,
                    e.assigned_to_id,
                    e.created_at,
                    p.name as project_name,
                    c.display_name as client_name
                FROM {$expense_table} e
                LEFT JOIN {$category_table} ec ON e.expense_category_id = ec.ID
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
                TimeGrowTimeEntryRules::member_id(0) // the team member's ID, not the WordPress user ID
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
            if (!isset($by_category[$expense->category_slug])) {
                $by_category[$expense->category_slug] = 0;
            }
            $by_category[$expense->category_slug] += $amount;

            // By payment method
            if (!isset($by_payment_method[$expense->expense_payment_method])) {
                $by_payment_method[$expense->expense_payment_method] = 0;
            }
            $by_payment_method[$expense->expense_payment_method] += $amount;

            // By assigned to
            $by_assigned_to[$expense->assigned_to] += $amount;
        }

        // Display the SQL Query (only when timegrow_debug() is enabled)
        ?>
            <?php if (timegrow_debug()): ?>
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
                <?php TimeGrowDateRanges::render(['page' => sanitize_key($_GET['page']), 'report_slug' => 'my_expenses_report'], $start_date, $end_date); ?>
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
                                        <?php echo esc_html(strtoupper($expense->category_name)); ?>
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
        $category_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_categories';
        $query = "SELECT
                    e.expense_name,
                    e.expense_description,
                    e.expense_date,
                    e.expense_payment_method,
                    e.amount,
                    ec.name as category_name,
                    e.assigned_to,
                    p.name as project_name,
                    c.display_name as client_name
                FROM {$expense_table} e
                LEFT JOIN {$category_table} ec ON e.expense_category_id = ec.ID
                LEFT JOIN {$project_table} p ON (e.assigned_to = 'project' AND e.assigned_to_id = p.ID)
                LEFT JOIN {$client_table} c ON (e.assigned_to = 'client' AND e.assigned_to_id = c.ID)
                WHERE 1=1";

        $params = [];

        if (!current_user_can('administrator')) {
            $time_entry_table = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';
            $user_projects_query = $wpdb->prepare(
                "SELECT DISTINCT project_id FROM {$time_entry_table} WHERE member_id = %d",
                TimeGrowTimeEntryRules::member_id(0) // the team member's ID, not the WordPress user ID
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
                $expense->category_name,
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
        $category_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_categories';
        $query = "SELECT
                    e.expense_name,
                    e.expense_description,
                    e.expense_date,
                    e.expense_payment_method,
                    e.amount,
                    ec.name as category_name,
                    e.assigned_to,
                    p.name as project_name,
                    c.display_name as client_name
                FROM {$expense_table} e
                LEFT JOIN {$category_table} ec ON e.expense_category_id = ec.ID
                LEFT JOIN {$project_table} p ON (e.assigned_to = 'project' AND e.assigned_to_id = p.ID)
                LEFT JOIN {$client_table} c ON (e.assigned_to = 'client' AND e.assigned_to_id = c.ID)
                WHERE 1=1";

        $params = [];

        if (!current_user_can('administrator')) {
            $time_entry_table = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';
            $user_projects_query = $wpdb->prepare(
                "SELECT DISTINCT project_id FROM {$time_entry_table} WHERE member_id = %d",
                TimeGrowTimeEntryRules::member_id(0) // the team member's ID, not the WordPress user ID
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
            $html .= '<td>' . esc_html($expense->category_name) . '</td>';
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
}
