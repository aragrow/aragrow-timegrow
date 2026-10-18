<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * My Detailed Time Log: page, CSV and PDF export.
 * Used by TimeGrowReportsController (methods moved as-is from the controller).
 */
trait TimeGrowReportsMyTimeEntriesTrait {

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
                    COALESCE(DATE(te.date), DATE(te.clock_in_date)) as entry_date,
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

        // Display the SQL Query (only when timegrow_debug() is enabled)
        ?>
            <?php if (timegrow_debug()): ?>
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
                <?php TimeGrowDateRanges::render(['page' => sanitize_key($_GET['page']), 'report_slug' => 'my_time_entries_detailed'], $start_date, $end_date); ?>
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
                    COALESCE(DATE(te.date), DATE(te.clock_in_date)) as entry_date,
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
                    COALESCE(DATE(te.date), DATE(te.clock_in_date)) as entry_date,
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
}
