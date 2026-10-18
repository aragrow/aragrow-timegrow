<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * My Hours by Project: page, CSV and PDF export.
 * Used by TimeGrowReportsController (methods moved as-is from the controller).
 */
trait TimeGrowReportsMyHoursTrait {

    /**
     * Generate My Hours by Project Report
     * Shows current user's hours breakdown by project
     */
    private function generate_my_hours_by_project($current_user) {
        global $wpdb;

        // Handle CSV export
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            timegrow_log('Exporting to CSV');
            $this->export_my_hours_by_project_csv($current_user);
            return;
        }

        // Handle PDF export
        if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
            timegrow_log('Exporting to PDF');
            $this->export_my_hours_by_project_pdf($current_user);
            return;
        }

        timegrow_log('Continuing with normal report display');

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
                WHERE te.status = 1"; // inactive entries are not counted

        $params = [];

        // Filter by member_id only if not administrator
        if (!current_user_can('administrator')) {
            $query .= " AND te.member_id = %d";
            $params[] = TimeGrowTimeEntryRules::member_id(0); // the team member's ID, not the WordPress user ID
        }

        // Add date filters if provided
        if (!empty($start_date)) {
            $query .= " AND COALESCE(DATE(te.date), DATE(te.clock_in_date)) >= %s";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $query .= " AND COALESCE(DATE(te.date), DATE(te.clock_in_date)) <= %s";
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

        // Display the SQL Query (only when timegrow_debug() is enabled)
        ?>
            <?php if (timegrow_debug()): ?>
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
                <?php TimeGrowDateRanges::render(['page' => sanitize_key($_GET['page']), 'report_slug' => 'my_hours_by_project'], $start_date, $end_date); ?>
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
     * Export My Hours by Project Report to CSV
     */
    private function export_my_hours_by_project_csv($current_user) {
        global $wpdb;

        $time_entry_table = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $client_table = $wpdb->prefix . 'users'; // Clients are stored in WordPress users table

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
                WHERE te.status = 1"; // inactive entries are not counted

        $params = [];

        if (!current_user_can('administrator')) {
            $query .= " AND te.member_id = %d";
            $params[] = TimeGrowTimeEntryRules::member_id(0); // the team member's ID, not the WordPress user ID
        }

        if (!empty($start_date)) {
            $query .= " AND COALESCE(DATE(te.date), DATE(te.clock_in_date)) >= %s";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $query .= " AND COALESCE(DATE(te.date), DATE(te.clock_in_date)) <= %s";
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

        $time_entry_table = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $client_table = $wpdb->prefix . 'users'; // Clients are stored in WordPress users table

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
                WHERE te.status = 1"; // inactive entries are not counted

        $params = [];

        if (!current_user_can('administrator')) {
            $query .= " AND te.member_id = %d";
            $params[] = TimeGrowTimeEntryRules::member_id(0); // the team member's ID, not the WordPress user ID
        }

        if (!empty($start_date)) {
            $query .= " AND COALESCE(DATE(te.date), DATE(te.clock_in_date)) >= %s";
            $params[] = $start_date;
        }

        if (!empty($end_date)) {
            $query .= " AND COALESCE(DATE(te.date), DATE(te.clock_in_date)) <= %s";
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
}
