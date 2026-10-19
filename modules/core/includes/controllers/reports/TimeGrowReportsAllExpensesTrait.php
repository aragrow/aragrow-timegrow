<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * All Expenses Overview: page, CSV and PDF export.
 * Used by TimeGrowReportsController (methods moved as-is from the controller).
 */
trait TimeGrowReportsAllExpensesTrait {

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
        $start_date = TimeGrowDateRanges::valid_date($_GET['start_date'] ?? '');
        $end_date = TimeGrowDateRanges::valid_date($_GET['end_date'] ?? '');
        $filter_category = isset($_GET['filter_category']) ? sanitize_text_field($_GET['filter_category']) : '';
        $filter_payment_method = isset($_GET['filter_payment_method']) ? sanitize_text_field($_GET['filter_payment_method']) : '';
        $filter_assigned_to = isset($_GET['filter_assigned_to']) ? sanitize_text_field($_GET['filter_assigned_to']) : '';

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
                WHERE e.status = 1"; // inactive expenses are not counted

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
            $query .= " AND ec.slug = %s";
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
        $all_categories = $wpdb->get_results("
            SELECT DISTINCT ec.slug, ec.name
            FROM {$expense_table} e
            LEFT JOIN {$category_table} ec ON e.expense_category_id = ec.ID
            WHERE ec.slug IS NOT NULL
            ORDER BY ec.name ASC
        ");
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
            if (!isset($by_category[$expense->category_slug])) {
                $by_category[$expense->category_slug] = ['count' => 0, 'amount' => 0];
            }
            $by_category[$expense->category_slug]['count']++;
            $by_category[$expense->category_slug]['amount'] += $amount;

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

            <!-- Filters -->
            <div class="tax-report-controls" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <?php TimeGrowDateRanges::render(array_filter(['page' => sanitize_key($_GET['page']), 'report_slug' => 'all_expenses_overview',
                    'filter_category' => $filter_category, 'filter_payment_method' => $filter_payment_method, 'filter_assigned_to' => $filter_assigned_to], 'strlen'), $start_date, $end_date); // keeps the other filters ?>
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
                                <option value="<?php echo esc_attr($cat->slug); ?>" <?php selected($filter_category, $cat->slug); ?>>
                                    <?php echo esc_html($cat->name); ?>
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
                <div class="summary-card" style="background: #ede7f6; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #512da8;">Total Expenses</h3>
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
                <a href=\"<?php echo esc_url(add_query_arg(array_merge($export_params, ['export' => 'csv']), admin_url('admin.php'))); ?>\" class="button button-primary">📊 Export to CSV</a>
                <a href=\"<?php echo esc_url(add_query_arg(array_merge($export_params, ['export' => 'pdf']), admin_url('admin.php'))); ?>\" class="button button-primary">📄 Export to PDF</a>
                <button type="button" class="button button-secondary" onclick="window.print();">🖨️ Print to PDF</button>
            </div>
        <?php
    }

    /**
     * Export All Expenses Overview to CSV
     */
    private function export_all_expenses_overview_csv() {
        global $wpdb;

        $expense_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $client_table = $wpdb->prefix . 'users';

        $start_date = TimeGrowDateRanges::valid_date($_GET['start_date'] ?? '');
        $end_date = TimeGrowDateRanges::valid_date($_GET['end_date'] ?? '');
        $filter_category = isset($_GET['filter_category']) ? sanitize_text_field($_GET['filter_category']) : '';
        $filter_payment_method = isset($_GET['filter_payment_method']) ? sanitize_text_field($_GET['filter_payment_method']) : '';
        $filter_assigned_to = isset($_GET['filter_assigned_to']) ? sanitize_text_field($_GET['filter_assigned_to']) : '';

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
                WHERE e.status = 1"; // inactive expenses are not counted

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
            $query .= " AND ec.slug = %s";
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

        TimeGrowCsv::put($output, [
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

            TimeGrowCsv::put($output, [
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
     * Export All Expenses Overview to PDF
     */
    private function export_all_expenses_overview_pdf() {
        global $wpdb;

        $expense_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $client_table = $wpdb->prefix . 'users';

        $start_date = TimeGrowDateRanges::valid_date($_GET['start_date'] ?? '');
        $end_date = TimeGrowDateRanges::valid_date($_GET['end_date'] ?? '');
        $filter_category = isset($_GET['filter_category']) ? sanitize_text_field($_GET['filter_category']) : '';
        $filter_payment_method = isset($_GET['filter_payment_method']) ? sanitize_text_field($_GET['filter_payment_method']) : '';
        $filter_assigned_to = isset($_GET['filter_assigned_to']) ? sanitize_text_field($_GET['filter_assigned_to']) : '';

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
                WHERE e.status = 1"; // inactive expenses are not counted

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
            $query .= " AND ec.slug = %s";
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
            $html .= '<td>' . esc_html($expense->category_name) . '</td>';
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
}
