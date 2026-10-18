<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Unbilled Time (WIP): billable time not invoiced yet, by client and project, with the amount it
 * would bill at. The same entries and rate as invoicing (TimeGrowWooOrderCreator): billable = 1 and
 * billed = 0; rate = project, else client, else company, else 75. Open clock-ins are not counted.
 * Page and CSV export. Administrators only (view_unbilled_time).
 */
trait TimeGrowReportsUnbilledTrait {

    /** Unbilled hours per project, with the client and the project rate. */
    public static function unbilled_rows($start_date, $end_date, $client_id) {
        global $wpdb;
        $p     = $wpdb->prefix . TIMEGROW_PREFIX;
        $day   = 'COALESCE(DATE(te.date), DATE(te.clock_in_date))';
        $hours = "CASE
                    WHEN te.hours IS NOT NULL AND te.hours > 0 THEN te.hours
                    WHEN te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL
                    THEN TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600
                    ELSE 0
                END";
        $sql = "SELECT p.ID AS project_id, p.name AS project_name, p.client_id, c.display_name AS client_name,
                    p.default_flat_fee AS project_rate,
                    SUM({$hours}) AS hours,
                    SUM(CASE WHEN ({$hours}) > 0 THEN 1 ELSE 0 END) AS entry_count,
                    MIN({$day}) AS oldest_entry,
                    MAX({$day}) AS newest_entry
                FROM {$p}time_entry_tracker te
                INNER JOIN {$p}project_tracker p ON te.project_id = p.ID
                LEFT JOIN {$wpdb->users} c ON p.client_id = c.ID
                WHERE te.billable = 1 AND te.billed = 0";
        $args = [];
        if ($start_date !== '') {
            $sql .= " AND {$day} >= %s";
            $args[] = $start_date;
        }
        if ($end_date !== '') {
            $sql .= " AND {$day} <= %s";
            $args[] = $end_date;
        }
        if ($client_id > 0) {
            $sql .= ' AND p.client_id = %d';
            $args[] = $client_id;
        }
        $sql .= ' GROUP BY p.ID, p.name, p.client_id, c.display_name, p.default_flat_fee HAVING hours > 0';
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, $args)) : $wpdb->get_results($sql);

        $company_rate = function_exists('timegrow_company_default_rate') ? timegrow_company_default_rate() : 0;
        $client_rates = [];
        foreach ((array) $rows as $r) {
            $cid = (int) $r->client_id;
            if (!isset($client_rates[$cid])) {
                $client_rates[$cid] = get_user_meta($cid, 'timegrow_default_flat_fee', true);
            }
            $r->rate   = TimeGrowWooOrderCreator::resolve_rate($r->project_rate, $client_rates[$cid], $company_rate);
            $r->amount = TimeGrowWooOrderCreator::price_line($r->hours, $r->rate)[1];
        }
        return self::sort_unbilled((array) $rows);
    }

    /** Clients A–Z, and within a client the largest amount first. Pure. */
    public static function sort_unbilled(array $rows) {
        usort($rows, function ($a, $b) {
            return strnatcasecmp((string) $a->client_name, (string) $b->client_name) ?: ((float) $b->amount <=> (float) $a->amount);
        });
        return $rows;
    }

    /** Totals, a subtotal per client and the oldest entry. Pure. */
    public static function unbilled_totals(array $rows) {
        $t = ['hours' => 0.0, 'amount' => 0.0, 'entries' => 0, 'oldest' => '', 'clients' => []];
        foreach ($rows as $r) {
            $t['hours']   += (float) $r->hours;
            $t['amount']  += (float) $r->amount;
            $t['entries'] += (int) $r->entry_count;
            if ($r->oldest_entry && ($t['oldest'] === '' || $r->oldest_entry < $t['oldest'])) {
                $t['oldest'] = substr((string) $r->oldest_entry, 0, 10);
            }
            $cid = (int) $r->client_id;
            $t['clients'][$cid] = [
                'hours'  => ($t['clients'][$cid]['hours'] ?? 0) + (float) $r->hours,
                'amount' => ($t['clients'][$cid]['amount'] ?? 0) + (float) $r->amount,
            ];
        }
        $t['amount'] = round($t['amount'], 2);
        return $t;
    }

    /** Days between the oldest unbilled entry and today, or null. Pure. */
    public static function days_waiting($oldest, $today) {
        if (!$oldest) {
            return null;
        }
        return max(0, (int) ((strtotime($today) - strtotime(substr($oldest, 0, 10))) / DAY_IN_SECONDS));
    }

    private function generate_unbilled_time($current_user) {
        global $wpdb;
        $start_date = isset($_GET['start_date']) ? sanitize_text_field($_GET['start_date']) : '';
        $end_date   = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        $client_id  = isset($_GET['filter_client']) ? intval($_GET['filter_client']) : 0;
        $rows   = self::unbilled_rows($start_date, $end_date, $client_id);
        $totals = self::unbilled_totals($rows);
        $waited = self::days_waiting($totals['oldest'], current_time('Y-m-d'));

        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $this->export_unbilled_time_csv($rows, $totals, $start_date, $end_date);
            return;
        }

        // Open clock-ins on billable entries: not counted until clocked out
        $open = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}" . TIMEGROW_PREFIX . "time_entry_tracker
            WHERE billable = 1 AND billed = 0 AND clock_in_date IS NOT NULL AND clock_out_date IS NULL AND (hours IS NULL OR hours = 0)");
        $clients = $wpdb->get_results("SELECT DISTINCT c.ID, c.display_name FROM {$wpdb->prefix}" . TIMEGROW_PREFIX . "project_tracker p
            INNER JOIN {$wpdb->users} c ON p.client_id = c.ID ORDER BY c.display_name");

        $base = ['page' => sanitize_key($_GET['page']), 'report_slug' => 'unbilled_time'];
        $export_params = array_merge($base, array_filter(['start_date' => $start_date, 'end_date' => $end_date, 'filter_client' => $client_id]));
        $process_url = admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-process-time');
        ?>
            <!-- Filters -->
            <div class="tax-report-controls" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <?php TimeGrowDateRanges::render(array_filter(array_merge($base, ['filter_client' => $client_id])), $start_date, $end_date); // keeps the client filter ?>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="<?php echo esc_attr($base['page']); ?>" />
                    <input type="hidden" name="report_slug" value="unbilled_time" />

                    <label for="filter_client" style="font-weight: bold; margin: 0;">Client:</label>
                    <select name="filter_client" id="filter_client" style="padding: 8px 12px; min-width: 200px;">
                        <option value="">All Clients</option>
                        <?php foreach ((array) $clients as $c) : ?>
                            <option value="<?php echo (int) $c->ID; ?>" <?php selected($client_id, (int) $c->ID); ?>><?php echo esc_html($c->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>

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
                <div class="summary-card" style="background: #e8f5e9; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #2e7d32;">Unbilled Amount</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;">$<?php echo number_format($totals['amount'], 2); ?></p>
                </div>
                <div class="summary-card" style="background: #e3f2fd; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #1565c0;">Unbilled Hours</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo number_format($totals['hours'], 2); ?></p>
                </div>
                <div class="summary-card" style="background: #f3e5f5; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #7b1fa2;">Clients</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo count($totals['clients']); ?></p>
                </div>
                <div class="summary-card" style="background: #fff3e0; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #e65100;">Projects</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo count($rows); ?></p>
                </div>
                <div class="summary-card" style="background: #fce4ec; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #c2185b;">Oldest Unbilled</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo $waited === null ? '—' : esc_html($waited . ' days'); ?></p>
                </div>
            </div>

            <!-- Unbilled by Client and Project -->
            <h2>Unbilled Time by Client and Project</h2>
            <?php if (!empty($rows)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Project</th>
                            <th style="text-align: right;">Hours</th>
                            <th style="text-align: right;">Rate</th>
                            <th style="text-align: right;">Amount</th>
                            <th style="text-align: center;">Entries</th>
                            <th>Oldest Entry</th>
                            <th>Newest Entry</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $last = null;
                        foreach ($rows as $i => $row) :
                            $cid = (int) $row->client_id;
                            $old = self::days_waiting($row->oldest_entry, current_time('Y-m-d'));
                        ?>
                            <tr>
                                <td><?php echo $cid !== $last ? '<strong>' . esc_html($row->client_name ?: 'No Client') . '</strong>' : ''; ?></td>
                                <td><?php echo esc_html($row->project_name); ?></td>
                                <td style="text-align: right;"><?php echo number_format($row->hours, 2); ?></td>
                                <td style="text-align: right;">$<?php echo number_format($row->rate, 2); ?></td>
                                <td style="text-align: right;"><strong>$<?php echo number_format($row->amount, 2); ?></strong></td>
                                <td style="text-align: center;"><?php echo (int) $row->entry_count; ?></td>
                                <td style="<?php echo $old !== null && $old > 30 ? 'color: #c2185b; font-weight: bold;' : ''; ?>"><?php echo esc_html(date('M j, Y', strtotime($row->oldest_entry))); ?></td>
                                <td><?php echo esc_html(date('M j, Y', strtotime($row->newest_entry))); ?></td>
                            </tr>
                            <?php
                            $last = $cid;
                            $next = $rows[$i + 1] ?? null;
                            if (!$next || (int) $next->client_id !== $cid) : ?>
                                <tr style="background: #f5f5f5;">
                                    <td colspan="2" style="text-align: right;"><em>Subtotal <?php echo esc_html($row->client_name ?: 'No Client'); ?></em></td>
                                    <td style="text-align: right;"><em><?php echo number_format($totals['clients'][$cid]['hours'], 2); ?></em></td>
                                    <td></td>
                                    <td style="text-align: right;"><em>$<?php echo number_format($totals['clients'][$cid]['amount'], 2); ?></em></td>
                                    <td colspan="3"></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f5f5f5; font-weight: bold;">
                            <th colspan="2">Total</th>
                            <th style="text-align: right;"><?php echo number_format($totals['hours'], 2); ?></th>
                            <th></th>
                            <th style="text-align: right;">$<?php echo number_format($totals['amount'], 2); ?></th>
                            <th style="text-align: center;"><?php echo (int) $totals['entries']; ?></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
                <p style="color: #666; font-size: 12px;">
                    Billable time not on an invoice yet, priced like an invoice: the project rate, else the client rate, else the company rate. Entries older than 30 days are in red.
                    <?php if ($open > 0) : ?>
                        <?php echo (int) $open; ?> open clock-in<?php echo $open > 1 ? 's are' : ' is'; ?> not counted until clocked out.
                    <?php endif; ?>
                </p>
            <?php else : ?>
                <div class="notice notice-success inline">
                    <p>No unbilled billable time for the selected filters.</p>
                </div>
            <?php endif; ?>

            <!-- Actions -->
            <div style="margin-top: 30px; display: flex; gap: 10px; flex-wrap: wrap;">
                <?php if (current_user_can('access_nexus_process_time') || current_user_can('administrator')) : ?>
                    <a href="<?php echo esc_url($process_url); ?>" class="button button-primary">🧾 Bill This Time (Process Time)</a>
                <?php endif; ?>
                <a href="<?php echo esc_url(add_query_arg(array_merge($export_params, ['export' => 'csv']), admin_url('admin.php'))); ?>" class="button button-primary">📊 Export to CSV</a>
                <button type="button" class="button button-secondary" onclick="window.print();">🖨️ Print to PDF</button>
            </div>
        <?php
    }

    private function export_unbilled_time_csv(array $rows, array $totals, $start_date, $end_date) {
        $filename = 'unbilled-time-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel

        fputcsv($output, ['Unbilled Time (WIP)']);
        fputcsv($output, ['Generated:', date('F j, Y g:i A')]);
        if ($start_date !== '' || $end_date !== '') {
            fputcsv($output, ['Date Range: ' . ($start_date !== '' ? date('M j, Y', strtotime($start_date)) : 'Beginning') . ' to ' . ($end_date !== '' ? date('M j, Y', strtotime($end_date)) : 'Present')]);
        }
        fputcsv($output, []);
        fputcsv($output, ['Client', 'Project', 'Hours', 'Rate', 'Amount', 'Entries', 'Oldest Entry', 'Newest Entry']);
        foreach ($rows as $row) {
            fputcsv($output, [
                $row->client_name ?: 'No Client',
                $row->project_name,
                number_format($row->hours, 2, '.', ''),
                number_format($row->rate, 2, '.', ''),
                number_format($row->amount, 2, '.', ''),
                (int) $row->entry_count,
                date('Y-m-d', strtotime($row->oldest_entry)),
                date('Y-m-d', strtotime($row->newest_entry)),
            ]);
        }
        fputcsv($output, []);
        fputcsv($output, ['TOTAL', '', number_format($totals['hours'], 2, '.', ''), '', number_format($totals['amount'], 2, '.', ''), (int) $totals['entries'], '', '']);
        fclose($output);
        exit;
    }
}
