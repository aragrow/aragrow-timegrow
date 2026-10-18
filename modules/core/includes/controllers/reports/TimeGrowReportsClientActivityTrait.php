<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Client Activity: summary, detail, CSV export.
 * Used by TimeGrowReportsController (methods moved as-is from the controller).
 */
trait TimeGrowReportsClientActivityTrait {

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
        $company_table = $wpdb->prefix . TIMEGROW_PREFIX . 'company_tracker';
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
                        WHEN te.billable = 1 AND te.billed = 1 AND te.hours IS NOT NULL AND te.hours > 0 THEN te.hours
                        WHEN te.billable = 1 AND te.billed = 1 AND te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL
                        THEN TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600
                        ELSE 0
                    END) as billed_hours,
                    SUM(CASE
                        WHEN te.billable = 1 AND (te.billed = 0 OR te.billed IS NULL) AND te.hours IS NOT NULL AND te.hours > 0 THEN te.hours
                        WHEN te.billable = 1 AND (te.billed = 0 OR te.billed IS NULL) AND te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL
                        THEN TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600
                        ELSE 0
                    END) as unbilled_hours,
                    SUM(CASE
                        WHEN te.billable = 1 AND te.billed = 1 AND te.hours IS NOT NULL AND te.hours > 0 THEN te.hours * COALESCE(
                            NULLIF(p.default_flat_fee, 0),
                            (SELECT NULLIF(CAST(um.meta_value AS DECIMAL(10,2)), 0) FROM {$wpdb->usermeta} um WHERE um.user_id = c.ID AND um.meta_key = 'timegrow_default_flat_fee' LIMIT 1),
                            (SELECT NULLIF(ct.default_flat_fee, 0) FROM {$company_table} ct ORDER BY ct.ID LIMIT 1),
                            75)
                        WHEN te.billable = 1 AND te.billed = 1 AND te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL
                        THEN (TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600) * COALESCE(
                            NULLIF(p.default_flat_fee, 0),
                            (SELECT NULLIF(CAST(um.meta_value AS DECIMAL(10,2)), 0) FROM {$wpdb->usermeta} um WHERE um.user_id = c.ID AND um.meta_key = 'timegrow_default_flat_fee' LIMIT 1),
                            (SELECT NULLIF(ct.default_flat_fee, 0) FROM {$company_table} ct ORDER BY ct.ID LIMIT 1),
                            75)
                        ELSE 0
                    END) as billed_amount,
                    (SELECT COUNT(*) FROM {$expense_table} e
                     INNER JOIN {$project_table} p2 ON e.assigned_to = 'project' AND e.assigned_to_id = p2.ID
                     WHERE p2.client_id = c.ID AND e.status = 1";

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
                     WHERE p2.client_id = c.ID AND e.status = 1";

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
                LEFT JOIN {$time_entry_table} te ON p.ID = te.project_id AND te.status = 1
                WHERE 1=1";

        // Add client filter
        if (!empty($filter_client)) {
            $query .= " AND c.ID = %d";
            $params[] = $filter_client;
        }

        if (!empty($start_date) || !empty($end_date)) {
            $query .= " AND (1=1";
            if (!empty($start_date)) {
                $query .= " AND COALESCE(DATE(te.date), DATE(te.clock_in_date)) >= %s";
                $params[] = $start_date;
            }
            if (!empty($end_date)) {
                $query .= " AND COALESCE(DATE(te.date), DATE(te.clock_in_date)) <= %s";
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
        $grand_billed_hours = 0;
        $grand_unbilled_hours = 0;
        $grand_billed_amount = 0;
        $grand_total_expenses = 0;
        $grand_project_count = 0;
        $grand_time_entries = 0;
        $grand_expense_count = 0;

        foreach ($results as $row) {
            $grand_total_hours += floatval($row->total_hours);
            $grand_billed_hours += floatval($row->billed_hours);
            $grand_unbilled_hours += floatval($row->unbilled_hours);
            $grand_billed_amount += floatval($row->billed_amount);
            $grand_total_expenses += floatval($row->total_expenses);
            $grand_project_count += intval($row->project_count);
            $grand_time_entries += intval($row->time_entry_count);
            $grand_expense_count += intval($row->expense_count);
        }

        // Count total clients in wp_users that have at least one project (no activity filter)
        $all_clients_with_projects_count = $wpdb->get_var("
            SELECT COUNT(DISTINCT c.ID)
            FROM {$client_table} c
            INNER JOIN {$project_table} p ON c.ID = p.client_id
        ");

        // Count total clients in wp_users (all users flagged as clients)
        $all_clients_total = count($all_clients);

        // Display diagnostics panel (always visible to admins, not gated by timegrow_debug())
        ?>
        <details style="background: #fff8e1; border: 2px solid #f9a825; padding: 15px; margin: 0 0 20px 0; border-radius: 5px;">
            <summary style="cursor: pointer; font-weight: bold; color: #f57f17; font-size: 14px;">&#128269; Report Diagnostics (click to expand)</summary>
            <div style="margin-top: 12px; font-size: 13px; line-height: 1.8;">
                <strong>Active Filters:</strong><br>
                &bull; Client filter: <?php echo $filter_client ? esc_html($filter_client) . ' (ID)' : '<em>none — all clients</em>'; ?><br>
                &bull; Start date: <?php echo $start_date ? esc_html($start_date) : '<em>none</em>'; ?><br>
                &bull; End date: <?php echo $end_date ? esc_html($end_date) : '<em>none</em>'; ?><br>
                <br>
                <strong>Counts:</strong><br>
                &bull; Clients in dropdown (have at least 1 project): <strong><?php echo intval($all_clients_total); ?></strong><br>
                &bull; Clients with at least 1 project (raw DB count): <strong><?php echo intval($all_clients_with_projects_count); ?></strong><br>
                &bull; Clients in report results (hours &gt; 0 OR expenses &gt; 0): <strong><?php echo count($results); ?></strong><br>
                <?php
                $excluded = intval($all_clients_with_projects_count) - count($results);
                if ($excluded > 0):
                ?>
                &bull; Clients excluded by HAVING (no hours &amp; no expenses): <strong style="color:#c62828;"><?php echo $excluded; ?></strong><br>
                <?php endif; ?>
                <?php if ($wpdb->last_error): ?>
                <br><span style="color:red;"><strong>DB Error:</strong> <?php echo esc_html($wpdb->last_error); ?></span>
                <?php endif; ?>
            </div>
            <?php if (timegrow_debug()): ?>
            <div style="margin-top: 12px;">
                <strong>SQL:</strong>
                <pre style="background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto; font-size: 11px; line-height: 1.4; margin: 5px 0 0 0;"><?php echo esc_html($final_sql); ?></pre>
            </div>
            <?php endif; ?>
        </details>

            <!-- Filters -->
            <div class="tax-report-controls" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <?php TimeGrowDateRanges::render(array_filter(['page' => sanitize_key($_GET['page']), 'report_slug' => 'client_activity_summary', 'filter_client' => $filter_client]), $start_date, $end_date); // keeps the client filter ?>
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
                <div class="summary-card" style="background: #ede7f6; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #512da8;">Active Clients</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo count($results); ?></p>
                </div>

                <div class="summary-card" style="background: #e3f2fd; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #1565c0;">Total Hours</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format($grand_total_hours, 2); ?></p>
                </div>

                <div class="summary-card" style="background: #e8f5e9; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #2e7d32;">Billed Hours</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format($grand_billed_hours, 2); ?></p>
                </div>

                <div class="summary-card" style="background: #fff8e1; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #f57f17;">Unbilled Hours</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format($grand_unbilled_hours, 2); ?></p>
                </div>

                <div class="summary-card" style="background: #e0f7fa; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #00695c;">Total Billed Amount</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;">$<?php echo number_format($grand_billed_amount, 2); ?></p>
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
                            <th style="width: 100px; text-align: right;">Billed Hrs</th>
                            <th style="width: 110px; text-align: right;">Unbilled Hrs</th>
                            <th style="width: 120px; text-align: right;">Amount Billed</th>
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
                            $non_billable = floatval($row->total_hours) - floatval($row->billed_hours) - floatval($row->unbilled_hours);
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
                                <td style="text-align: right; color: #2e7d32;"><?php echo number_format($row->billed_hours, 2); ?></td>
                                <td style="text-align: right; color: #f57f17; font-weight: bold;"><?php echo number_format($row->unbilled_hours, 2); ?></td>
                                <td style="text-align: right; font-weight: bold; color: #00695c;">$<?php echo number_format($row->billed_amount, 2); ?></td>
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
                            <td style="text-align: right; font-size: 16px; color: #2e7d32;"><?php echo number_format($grand_billed_hours, 2); ?></td>
                            <td style="text-align: right; font-size: 16px; color: #f57f17;"><?php echo number_format($grand_unbilled_hours, 2); ?></td>
                            <td style="text-align: right; font-size: 16px; color: #00695c;">$<?php echo number_format($grand_billed_amount, 2); ?></td>
                            <td style="text-align: right; font-size: 16px;"><?php echo number_format($grand_total_hours - $grand_billed_hours - $grand_unbilled_hours, 2); ?></td>
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

        $woo_posts_table = $wpdb->posts;

        // Query all time entries for this client's projects
        $time_query = "SELECT
                        te.*,
                        p.name as project_name,
                        m.name as member_name,
                        COALESCE(DATE(te.date), DATE(te.clock_in_date)) as entry_date,
                        CASE
                            WHEN te.hours IS NOT NULL AND te.hours > 0 THEN te.hours
                            WHEN te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL
                            THEN TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600
                            ELSE 0
                        END as calculated_hours,
                        CASE
                            WHEN te.hours IS NOT NULL AND te.hours > 0 THEN 'MANUAL'
                            ELSE 'CLOCK'
                        END as entry_type,
                        woo.post_title as woo_order_number
                    FROM {$time_entry_table} te
                    INNER JOIN {$project_table} p ON te.project_id = p.ID
                    INNER JOIN {$member_table} m ON te.member_id = m.ID
                    LEFT JOIN {$woo_posts_table} woo ON te.billed_order_id = woo.ID AND woo.post_type = 'shop_order'
                    WHERE p.client_id = %d AND te.status = 1";

        $time_params = [$client_id];

        if (!empty($start_date)) {
            $time_query .= " AND COALESCE(DATE(te.date), DATE(te.clock_in_date)) >= %s";
            $time_params[] = $start_date;
        }

        if (!empty($end_date)) {
            $time_query .= " AND COALESCE(DATE(te.date), DATE(te.clock_in_date)) <= %s";
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
                        WHERE p.client_id = %d AND e.status = 1";

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
        $total_hours       = 0;
        $billed_hours      = 0;
        $unbilled_hours    = 0;
        $non_billable_hours = 0;
        $total_expenses    = 0;

        foreach ($time_entries as $entry) {
            $hours = floatval($entry->hours ?? 0);
            if ($entry->clock_in_date && $entry->clock_out_date && $hours == 0) {
                $hours = (strtotime($entry->clock_out_date) - strtotime($entry->clock_in_date)) / 3600;
            }
            $total_hours += $hours;
            if ($entry->billable && $entry->billed) {
                $billed_hours += $hours;
            } elseif ($entry->billable && !$entry->billed) {
                $unbilled_hours += $hours;
            } else {
                $non_billable_hours += $hours;
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

                <!-- SQL Debug (only when timegrow_debug() is enabled) -->
                <?php if (timegrow_debug()): ?>
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
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(175px, 1fr)); gap: 15px; margin: 20px 0;">
                    <div style="background: #fff; padding: 20px; border-left: 4px solid #2271b1; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <div style="font-size: 13px; color: #666; margin-bottom: 5px;">Total Hours</div>
                        <div style="font-size: 28px; font-weight: bold; color: #2271b1;"><?php echo number_format($total_hours, 2); ?></div>
                    </div>
                    <div style="background: #fff; padding: 20px; border-left: 4px solid #00a32a; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <div style="font-size: 13px; color: #666; margin-bottom: 5px;">Billed Hours</div>
                        <div style="font-size: 28px; font-weight: bold; color: #00a32a;"><?php echo number_format($billed_hours, 2); ?></div>
                    </div>
                    <div style="background: #fff; padding: 20px; border-left: 4px solid #f0a402; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <div style="font-size: 13px; color: #666; margin-bottom: 5px;">Billable (Not Billed)</div>
                        <div style="font-size: 28px; font-weight: bold; color: #f0a402;"><?php echo number_format($unbilled_hours, 2); ?></div>
                    </div>
                    <div style="background: #fff; padding: 20px; border-left: 4px solid #999; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <div style="font-size: 13px; color: #666; margin-bottom: 5px;">Non-Billable Hours</div>
                        <div style="font-size: 28px; font-weight: bold; color: #999;"><?php echo number_format($non_billable_hours, 2); ?></div>
                    </div>
                    <div style="background: #fff; padding: 20px; border-left: 4px solid #d63638; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <div style="font-size: 13px; color: #666; margin-bottom: 5px;">Total Expenses</div>
                        <div style="font-size: 28px; font-weight: bold; color: #d63638;">$<?php echo number_format($total_expenses, 2); ?></div>
                    </div>
                    <div style="background: #fff; padding: 20px; border-left: 4px solid #996800; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <div style="font-size: 13px; color: #666; margin-bottom: 5px;">Time Entries</div>
                        <div style="font-size: 28px; font-weight: bold; color: #996800;"><?php echo count($time_entries); ?></div>
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
                                <th style="text-align: center;">Order #</th>
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
                                    <td style="text-align: center;">
                                        <?php if ($entry->billed && $entry->billed_order_id): ?>
                                            <?php
                                            $order_edit_url = admin_url('post.php?post=' . intval($entry->billed_order_id) . '&action=edit');
                                            ?>
                                            <a href="<?php echo esc_url($order_edit_url); ?>" target="_blank" style="font-weight: bold;">
                                                #<?php echo intval($entry->billed_order_id); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #999;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: #f0f0f1; font-weight: bold;">
                                <td colspan="7" style="text-align: right; padding-right: 10px;">Total Hours:</td>
                                <td style="text-align: right;"><?php echo number_format($total_hours, 2); ?></td>
                                <td colspan="3"></td>
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
                                    <td><?php echo esc_html($expense->category_name); ?></td>
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
                     WHERE p2.client_id = c.ID AND e.status = 1) as expense_count,
                    (SELECT COALESCE(SUM(e.amount), 0) FROM {$expense_table} e
                     INNER JOIN {$project_table} p2 ON e.assigned_to = 'project' AND e.assigned_to_id = p2.ID
                     WHERE p2.client_id = c.ID AND e.status = 1) as total_expenses,
                    MIN(COALESCE(te.date, te.clock_in_date)) as first_activity,
                    MAX(COALESCE(te.date, te.clock_in_date)) as last_activity
                FROM {$client_table} c
                INNER JOIN {$project_table} p ON c.ID = p.client_id
                LEFT JOIN {$time_entry_table} te ON p.ID = te.project_id AND te.status = 1
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
                $query .= " AND COALESCE(DATE(te.date), DATE(te.clock_in_date)) >= %s";
                $params[] = $start_date;
            }
            if (!empty($end_date)) {
                $query .= " AND COALESCE(DATE(te.date), DATE(te.clock_in_date)) <= %s";
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
}
