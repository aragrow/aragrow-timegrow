<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Team Hours Summary: hours per team member for a period (total, billable, non-billable, billable %),
 * with members who logged nothing listed too. Page and CSV export. Administrators only
 * (view_team_hours_summary).
 */
trait TimeGrowReportsTeamHoursTrait {

    /** Hours of one entry: manual hours, or the time between clock in and clock out (open clocks count 0). */
    private static function team_hours_sql($condition = '1=1') {
        return "SUM(CASE
                    WHEN NOT ({$condition}) THEN 0
                    WHEN te.hours IS NOT NULL AND te.hours > 0 THEN te.hours
                    WHEN te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL
                    THEN TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600
                    ELSE 0
                END)";
    }

    /** One row per team member, most hours first. */
    private function team_hours_rows($start_date, $end_date) {
        global $wpdb;
        $p    = $wpdb->prefix . TIMEGROW_PREFIX;
        $day  = 'COALESCE(DATE(te.date), DATE(te.clock_in_date))';
        $on   = '';
        $args = [];
        if ($start_date !== '') {
            $on .= " AND {$day} >= %s";
            $args[] = $start_date;
        }
        if ($end_date !== '') {
            $on .= " AND {$day} <= %s";
            $args[] = $end_date;
        }
        // The date filter is on the join, so a member with no time in the period is still listed (with 0)
        $sql = "SELECT m.ID AS member_id, m.name AS member_name, m.status,
                    " . self::team_hours_sql() . " AS total_hours,
                    " . self::team_hours_sql('te.billable = 1') . " AS billable_hours,
                    " . self::team_hours_sql('te.billable = 0') . " AS non_billable_hours,
                    COUNT(te.ID) AS entry_count,
                    COUNT(DISTINCT te.project_id) AS project_count,
                    SUM(CASE WHEN te.clock_in_date IS NOT NULL AND te.clock_out_date IS NULL AND (te.hours IS NULL OR te.hours = 0) THEN 1 ELSE 0 END) AS open_clocks,
                    MAX({$day}) AS last_entry
                FROM {$p}team_member_tracker m
                LEFT JOIN {$p}time_entry_tracker te ON te.member_id = m.ID AND te.status = 1{$on}
                GROUP BY m.ID, m.name, m.status
                ORDER BY total_hours DESC, m.name ASC";
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args)) : $wpdb->get_results($sql);
        // Inactive members only when they logged time in the period
        return array_values(array_filter((array) $rows, function ($r) {
            return (float) $r->total_hours > 0 || (int) $r->entry_count > 0 || !in_array(strtolower((string) $r->status), ['0', 'inactive'], true);
        }));
    }

    /** Totals over the rows: total, billable, non-billable, entries, members with time. Pure. */
    public static function team_hours_totals(array $rows) {
        $t = ['total' => 0.0, 'billable' => 0.0, 'non_billable' => 0.0, 'entries' => 0, 'active' => 0];
        foreach ($rows as $r) {
            $t['total']        += (float) $r->total_hours;
            $t['billable']     += (float) $r->billable_hours;
            $t['non_billable'] += (float) $r->non_billable_hours;
            $t['entries']      += (int) $r->entry_count;
            $t['active']       += (float) $r->total_hours > 0 ? 1 : 0;
        }
        return $t;
    }

    /** Billable share of the hours, 0–100. Pure. */
    public static function billable_percent($billable, $total) {
        return (float) $total > 0 ? round((float) $billable / (float) $total * 100, 1) : 0.0;
    }

    private function generate_team_summary_hours($current_user) {
        $start_date = TimeGrowDateRanges::valid_date($_GET['start_date'] ?? '');
        $end_date   = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        $rows   = $this->team_hours_rows($start_date, $end_date);
        $totals = self::team_hours_totals($rows);

        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $this->export_team_summary_hours_csv($rows, $totals, $start_date, $end_date);
            return;
        }

        $base = ['page' => sanitize_key($_GET['page']), 'report_slug' => 'team_summary_hours'];
        $export_params = array_merge($base, array_filter(['start_date' => $start_date, 'end_date' => $end_date]));
        ?>
            <!-- Date Range Filter -->
            <div class="tax-report-controls" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <?php TimeGrowDateRanges::render($base, $start_date, $end_date); ?>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="<?php echo esc_attr($base['page']); ?>" />
                    <input type="hidden" name="report_slug" value="team_summary_hours" />

                    <label for="start_date" style="font-weight: bold; margin: 0;">From:</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr($start_date); ?>" style="padding: 8px 12px;" />

                    <label for="end_date" style="font-weight: bold; margin: 0;">To:</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr($end_date); ?>" style="padding: 8px 12px;" />

                    <button type="submit" class="button button-primary">Generate Report</button>
                    <a href="<?php echo esc_url(add_query_arg($base, admin_url('admin.php'))); ?>" class="button">Clear</a>
                </form>
            </div>

            <!-- Summary Cards -->
            <div class="tax-report-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
                <div class="summary-card" style="background: #e3f2fd; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #1565c0;">Total Hours</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format($totals['total'], 2); ?></p>
                </div>
                <div class="summary-card" style="background: #e8f5e9; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #2e7d32;">Billable Hours</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format($totals['billable'], 2); ?></p>
                </div>
                <div class="summary-card" style="background: #fff3e0; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #e65100;">Non-Billable Hours</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format($totals['non_billable'], 2); ?></p>
                </div>
                <div class="summary-card" style="background: #f3e5f5; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #7b1fa2;">Billable %</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format(self::billable_percent($totals['billable'], $totals['total']), 1); ?>%</p>
                </div>
                <div class="summary-card" style="background: #fce4ec; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #c2185b;">Members With Time</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo (int) $totals['active']; ?> / <?php echo count($rows); ?></p>
                </div>
            </div>

            <!-- Team Member Table -->
            <h2>Hours by Team Member</h2>
            <?php if (!empty($rows)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Team Member</th>
                            <th style="text-align: right;">Total Hours</th>
                            <th style="text-align: right;">Billable</th>
                            <th style="text-align: right;">Non-Billable</th>
                            <th style="text-align: right;">Billable %</th>
                            <th style="text-align: center;">Projects</th>
                            <th style="text-align: center;">Entries</th>
                            <th>Last Entry</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) :
                            $share = $totals['total'] > 0 ? (float) $row->total_hours / $totals['total'] * 100 : 0;
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($row->member_name); ?></strong>
                                    <?php if ((int) $row->open_clocks > 0) : ?>
                                        <span style="color: #e65100; font-size: 12px;"> · <?php echo (int) $row->open_clocks; ?> open clock-in<?php echo (int) $row->open_clocks > 1 ? 's' : ''; ?></span>
                                    <?php endif; ?>
                                    <br />
                                    <small style="color: #666;">
                                        <div style="background: #e0e0e0; height: 8px; border-radius: 4px; margin-top: 5px;">
                                            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); height: 8px; border-radius: 4px; width: <?php echo number_format($share, 1); ?>%;"></div>
                                        </div>
                                        <?php echo (float) $row->total_hours > 0 ? number_format($share, 1) . '% of team time' : 'No time in this period'; ?>
                                    </small>
                                </td>
                                <td style="text-align: right;"><strong><?php echo number_format($row->total_hours, 2); ?></strong></td>
                                <td style="text-align: right; color: #2e7d32;"><?php echo number_format($row->billable_hours, 2); ?></td>
                                <td style="text-align: right; color: #e65100;"><?php echo number_format($row->non_billable_hours, 2); ?></td>
                                <td style="text-align: right;"><?php echo number_format(self::billable_percent($row->billable_hours, $row->total_hours), 1); ?>%</td>
                                <td style="text-align: center;"><?php echo (int) $row->project_count; ?></td>
                                <td style="text-align: center;"><?php echo (int) $row->entry_count; ?></td>
                                <td><?php echo esc_html($row->last_entry ? date('M j, Y', strtotime($row->last_entry)) : '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f5f5f5; font-weight: bold;">
                            <th>Total</th>
                            <th style="text-align: right;"><?php echo number_format($totals['total'], 2); ?></th>
                            <th style="text-align: right;"><?php echo number_format($totals['billable'], 2); ?></th>
                            <th style="text-align: right;"><?php echo number_format($totals['non_billable'], 2); ?></th>
                            <th style="text-align: right;"><?php echo number_format(self::billable_percent($totals['billable'], $totals['total']), 1); ?>%</th>
                            <th></th>
                            <th style="text-align: center;"><?php echo (int) $totals['entries']; ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
                <p style="color: #666; font-size: 12px;">Clock entries count when clocked out; open clock-ins are shown next to the name and not counted yet.</p>
            <?php else : ?>
                <div class="notice notice-warning inline">
                    <p>No team members found.</p>
                </div>
            <?php endif; ?>

            <!-- Export buttons at bottom -->
            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <a href="<?php echo esc_url(add_query_arg(array_merge($export_params, ['export' => 'csv']), admin_url('admin.php'))); ?>" class="button button-primary">📊 Export to CSV</a>
                <button type="button" class="button button-secondary" onclick="window.print();">🖨️ Print to PDF</button>
            </div>
        <?php
    }

    private function export_team_summary_hours_csv(array $rows, array $totals, $start_date, $end_date) {
        $filename = 'team-hours-summary-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel

        TimeGrowCsv::put($output, ['Team Hours Summary']);
        TimeGrowCsv::put($output, ['Generated:', date('F j, Y g:i A')]);
        if ($start_date !== '' || $end_date !== '') {
            TimeGrowCsv::put($output, ['Date Range: ' . ($start_date !== '' ? date('M j, Y', strtotime($start_date)) : 'Beginning') . ' to ' . ($end_date !== '' ? date('M j, Y', strtotime($end_date)) : 'Present')]);
        }
        TimeGrowCsv::put($output, []);
        TimeGrowCsv::put($output, ['Team Member', 'Total Hours', 'Billable Hours', 'Non-Billable Hours', 'Billable %', 'Projects', 'Entries', 'Open Clock-ins', 'Last Entry']);
        foreach ($rows as $row) {
            TimeGrowCsv::put($output, [
                $row->member_name,
                number_format($row->total_hours, 2),
                number_format($row->billable_hours, 2),
                number_format($row->non_billable_hours, 2),
                number_format(self::billable_percent($row->billable_hours, $row->total_hours), 1),
                (int) $row->project_count,
                (int) $row->entry_count,
                (int) $row->open_clocks,
                $row->last_entry ? date('M j, Y', strtotime($row->last_entry)) : '',
            ]);
        }
        TimeGrowCsv::put($output, []);
        TimeGrowCsv::put($output, ['TOTAL', number_format($totals['total'], 2), number_format($totals['billable'], 2), number_format($totals['non_billable'], 2),
            number_format(self::billable_percent($totals['billable'], $totals['total']), 1), '', (int) $totals['entries'], '', '']);
        fclose($output);
        exit;
    }
}
