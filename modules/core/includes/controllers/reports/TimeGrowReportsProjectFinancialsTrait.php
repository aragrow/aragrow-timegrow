<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Project Financials: per project, the hours and their value, what is billed and not yet billed,
 * the project's expenses and what is left, and how much of the hour estimate is used.
 *
 * Money columns cover the chosen period. Value = billable hours x the rate invoices use (project,
 * else client, else company, else 75; TimeGrowWooOrderCreator::resolve_rate). Net = value - expenses
 * assigned to the project. Budget used = all hours ever logged on the project / its estimate_hours
 * (not limited to the period). Inactive time entries and expenses are left out. Page and CSV export.
 * Administrators only (view_project_financials).
 */
trait TimeGrowReportsProjectFinancialsTrait {

    /** One row per project with time or expenses in the period (or an estimate, when no period is set). */
    public static function project_financial_rows($start_date, $end_date, $client_id) {
        global $wpdb;
        $p     = $wpdb->prefix . TIMEGROW_PREFIX;
        $day   = 'COALESCE(DATE(te.date), DATE(te.clock_in_date))';
        $hours = "CASE
                    WHEN te.hours IS NOT NULL AND te.hours > 0 THEN te.hours
                    WHEN te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL
                    THEN TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600
                    ELSE 0
                END";
        $in   = '';
        $args = [];
        if ($start_date !== '') {
            $in .= " AND {$day} >= %s";
            $args[] = $start_date;
        }
        if ($end_date !== '') {
            $in .= " AND {$day} <= %s";
            $args[] = $end_date;
        }
        // Period columns use the date filter; all_hours (for the budget) does not
        $sql = "SELECT p.ID AS project_id, p.name AS project_name, p.client_id, c.display_name AS client_name,
                    p.default_flat_fee AS project_rate, p.estimate_hours, p.status,
                    SUM(CASE WHEN 1=1{$in} THEN {$hours} ELSE 0 END) AS hours,
                    SUM(CASE WHEN te.billable = 1{$in} THEN {$hours} ELSE 0 END) AS billable_hours,
                    SUM(CASE WHEN te.billable = 1 AND te.billed = 1{$in} THEN {$hours} ELSE 0 END) AS billed_hours,
                    SUM(CASE WHEN te.billable = 1 AND te.billed = 0{$in} THEN {$hours} ELSE 0 END) AS unbilled_hours,
                    COALESCE(SUM({$hours}), 0) AS all_hours
                FROM {$p}project_tracker p
                LEFT JOIN {$wpdb->users} c ON c.ID = p.client_id
                LEFT JOIN {$p}time_entry_tracker te ON te.project_id = p.ID AND te.status = 1
                WHERE 1=1" . ($client_id > 0 ? $wpdb->prepare(' AND p.client_id = %d', $client_id) : '') . '
                GROUP BY p.ID, p.name, p.client_id, c.display_name, p.default_flat_fee, p.estimate_hours, p.status';
        // The period's placeholders appear once per period column (4 columns)
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, array_merge($args, $args, $args, $args))) : $wpdb->get_results($sql);

        // Expenses assigned to each project in the period
        $where = "e.status = 1 AND e.assigned_to = 'project'";
        $eargs = [];
        if ($start_date !== '') {
            $where  .= ' AND e.expense_date >= %s';
            $eargs[] = $start_date;
        }
        if ($end_date !== '') {
            $where  .= ' AND e.expense_date <= %s';
            $eargs[] = $end_date;
        }
        $esql = "SELECT e.assigned_to_id AS project_id, SUM(e.amount) AS amount FROM {$p}expense_tracker e WHERE {$where} GROUP BY e.assigned_to_id";
        $expenses = [];
        foreach ((array) ($eargs ? $wpdb->get_results($wpdb->prepare($esql, $eargs)) : $wpdb->get_results($esql)) as $e) {
            $expenses[(int) $e->project_id] = (float) $e->amount;
        }

        $company_rate = function_exists('timegrow_company_default_rate') ? timegrow_company_default_rate() : 0;
        $client_rates = [];
        $out = [];
        foreach ((array) $rows as $r) {
            $cid = (int) $r->client_id;
            if (!isset($client_rates[$cid])) {
                $client_rates[$cid] = get_user_meta($cid, 'timegrow_default_flat_fee', true);
            }
            $r->rate     = TimeGrowWooOrderCreator::resolve_rate($r->project_rate, $client_rates[$cid], $company_rate);
            $r->expenses = $expenses[(int) $r->project_id] ?? 0.0;
            $out[] = self::project_financials($r);
        }
        $period = $start_date !== '' || $end_date !== '';
        return array_values(array_filter($out, function ($r) use ($period) {
            // A project shows when it has time or expenses in the period; with no period, also when it has an estimate
            return $r->hours > 0 || $r->expenses > 0 || (!$period && $r->estimate > 0);
        }));
    }

    /** The money and budget figures for one project row. Pure. */
    public static function project_financials($r) {
        $r->hours          = round((float) $r->hours, 2);
        $r->billable_hours = round((float) $r->billable_hours, 2);
        $r->value          = round((float) $r->billable_hours * (float) $r->rate, 2);
        $r->billed         = round((float) $r->billed_hours * (float) $r->rate, 2);
        $r->unbilled       = round((float) $r->unbilled_hours * (float) $r->rate, 2);
        $r->expenses       = round((float) $r->expenses, 2);
        $r->net            = round($r->value - $r->expenses, 2);
        $r->estimate       = (float) $r->estimate_hours;
        $r->budget_used    = $r->estimate > 0 ? round((float) $r->all_hours / $r->estimate * 100, 1) : null;
        return $r;
    }

    /** "over" past 100% of the estimate, "near" from 90%, else "" (also with no estimate). Pure. */
    public static function budget_state($used) {
        if ($used === null) {
            return '';
        }
        return $used > 100 ? 'over' : ($used >= 90 ? 'near' : '');
    }

    /** Totals over the rows. Pure. */
    public static function project_financial_totals(array $rows) {
        $t = ['hours' => 0.0, 'billable_hours' => 0.0, 'value' => 0.0, 'billed' => 0.0, 'unbilled' => 0.0, 'expenses' => 0.0, 'net' => 0.0, 'over' => 0, 'near' => 0];
        foreach ($rows as $r) {
            foreach (['hours', 'billable_hours', 'value', 'billed', 'unbilled', 'expenses', 'net'] as $k) {
                $t[$k] += (float) $r->$k;
            }
            $state = self::budget_state($r->budget_used);
            if ($state !== '') {
                $t[$state]++;
            }
        }
        foreach (['value', 'billed', 'unbilled', 'expenses', 'net'] as $k) {
            $t[$k] = round($t[$k], 2);
        }
        return $t;
    }

    private function generate_project_profitability($current_user) {
        global $wpdb;
        $start_date = isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date']) ? $_GET['start_date'] : '';
        $end_date   = isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date']) ? $_GET['end_date'] : '';
        $client_id  = isset($_GET['filter_client']) ? intval($_GET['filter_client']) : 0;
        $sort       = in_array($_GET['sort'] ?? '', ['net', 'value', 'unbilled', 'budget', 'hours'], true) ? $_GET['sort'] : 'value';
        $rows   = self::project_financial_rows($start_date, $end_date, $client_id);
        $key    = $sort === 'budget' ? 'budget_used' : $sort;
        usort($rows, function ($a, $b) use ($key) { return ((float) $b->$key <=> (float) $a->$key) ?: strnatcasecmp($a->project_name, $b->project_name); });
        $totals = self::project_financial_totals($rows);

        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $this->export_project_profitability_csv($rows, $totals, $start_date, $end_date);
            return;
        }

        $clients = $wpdb->get_results("SELECT DISTINCT c.ID, c.display_name FROM {$wpdb->prefix}" . TIMEGROW_PREFIX . "project_tracker p
            INNER JOIN {$wpdb->users} c ON p.client_id = c.ID ORDER BY c.display_name");
        $base = array_filter(['page' => sanitize_key($_GET['page']), 'report_slug' => 'project_profitability', 'filter_client' => $client_id, 'sort' => $sort === 'value' ? '' : $sort]);
        $export_params = array_merge($base, array_filter(['start_date' => $start_date, 'end_date' => $end_date]));
        $money = function ($v) { return ($v < 0 ? '-$' : '$') . number_format(abs($v), 2); };
        $sort_link = function ($key, $label) use ($export_params, $sort) {
            $url = add_query_arg(array_merge($export_params, ['sort' => $key]), admin_url('admin.php'));
            return '<a href="' . esc_url($url) . '"' . ($sort === $key ? ' style="font-weight:700;text-decoration:underline"' : '') . '>' . esc_html($label) . '</a>';
        };
        ?>
            <!-- Filters -->
            <div class="tax-report-controls" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <?php TimeGrowDateRanges::render($base, $start_date, $end_date); // keeps the client filter and the sort ?>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="<?php echo esc_attr($base['page']); ?>" />
                    <input type="hidden" name="report_slug" value="project_profitability" />

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
                    <a href="<?php echo esc_url(add_query_arg(['page' => $base['page'], 'report_slug' => 'project_profitability'], admin_url('admin.php'))); ?>" class="button">Clear</a>
                </form>
            </div>

            <!-- Summary Cards -->
            <div class="tax-report-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px;">
                <div class="summary-card" style="background: #e3f2fd; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #1565c0;">Value of Work</h3>
                    <p style="font-size: 26px; font-weight: bold; margin: 0;"><?php echo esc_html($money($totals['value'])); ?></p>
                </div>
                <div class="summary-card" style="background: #e8f5e9; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #2e7d32;">Billed</h3>
                    <p style="font-size: 26px; font-weight: bold; margin: 0;"><?php echo esc_html($money($totals['billed'])); ?></p>
                </div>
                <div class="summary-card" style="background: #fff3e0; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #e65100;">Not Billed Yet</h3>
                    <p style="font-size: 26px; font-weight: bold; margin: 0;"><?php echo esc_html($money($totals['unbilled'])); ?></p>
                </div>
                <div class="summary-card" style="background: #fce4ec; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #c2185b;">Expenses</h3>
                    <p style="font-size: 26px; font-weight: bold; margin: 0;"><?php echo esc_html($money($totals['expenses'])); ?></p>
                </div>
                <div class="summary-card" style="background: #f3e5f5; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #7b1fa2;">Net</h3>
                    <p style="font-size: 26px; font-weight: bold; margin: 0;"><?php echo esc_html($money($totals['net'])); ?></p>
                </div>
                <div class="summary-card" style="background: #fff8e1; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #f57f17;">Over Budget</h3>
                    <p style="font-size: 26px; font-weight: bold; margin: 0;"><?php echo (int) $totals['over']; ?><?php if ($totals['near']) : ?><span style="font-size: 13px; font-weight: normal;"> + <?php echo (int) $totals['near']; ?> near</span><?php endif; ?></p>
                </div>
            </div>

            <!-- Projects Table -->
            <h2>Projects</h2>
            <?php if (!empty($rows)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 22%;">Project</th>
                            <th style="text-align: right;"><?php echo $sort_link('hours', 'Hours'); ?></th>
                            <th style="text-align: right;">Rate</th>
                            <th style="text-align: right;"><?php echo $sort_link('value', 'Value'); ?></th>
                            <th style="text-align: right;">Billed</th>
                            <th style="text-align: right;"><?php echo $sort_link('unbilled', 'Not Billed'); ?></th>
                            <th style="text-align: right;">Expenses</th>
                            <th style="text-align: right;"><?php echo $sort_link('net', 'Net'); ?></th>
                            <th style="width: 15%;"><?php echo $sort_link('budget', 'Budget Used'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r) :
                            $state = self::budget_state($r->budget_used);
                            $bar   = $state === 'over' ? '#c62828' : ($state === 'near' ? '#ef6c00' : '#2a78d6');
                            $share = $r->hours > 0 ? round($r->billable_hours / $r->hours * 100) : 0;
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($r->project_name); ?></strong><br />
                                    <small style="color: #666;"><?php echo esc_html($r->client_name ?: 'No Client'); ?><?php echo (string) $r->status !== '1' ? ' · inactive project' : ''; ?></small>
                                </td>
                                <td style="text-align: right;"><?php echo number_format($r->hours, 2); ?><br /><small style="color: #666;"><?php echo (int) $share; ?>% billable</small></td>
                                <td style="text-align: right;"><?php echo esc_html($money($r->rate)); ?></td>
                                <td style="text-align: right;"><strong><?php echo esc_html($money($r->value)); ?></strong></td>
                                <td style="text-align: right; color: #2e7d32;"><?php echo esc_html($money($r->billed)); ?></td>
                                <td style="text-align: right; color: #e65100;"><?php echo esc_html($money($r->unbilled)); ?></td>
                                <td style="text-align: right;"><?php echo esc_html($money($r->expenses)); ?></td>
                                <td style="text-align: right;"><strong style="<?php echo $r->net < 0 ? 'color:#c62828;' : ''; ?>"><?php echo esc_html($money($r->net)); ?></strong></td>
                                <td>
                                    <?php if ($r->budget_used === null) : ?>
                                        <span style="color: #999;">No estimate</span>
                                    <?php else : ?>
                                        <div style="background: #e0e0e0; height: 8px; border-radius: 4px;" title="<?php echo esc_attr(number_format((float) $r->all_hours, 2) . ' of ' . number_format($r->estimate, 2) . ' h'); ?>">
                                            <div style="background: <?php echo esc_attr($bar); ?>; height: 8px; border-radius: 4px; width: <?php echo esc_attr(min(100, $r->budget_used)); ?>%;"></div>
                                        </div>
                                        <small style="<?php echo $state ? 'color:' . esc_attr($bar) . ';font-weight:600;' : 'color:#666;'; ?>">
                                            <?php echo esc_html(number_format($r->budget_used, 0) . '% of ' . number_format($r->estimate, 0) . ' h' . ($state === 'over' ? ' · over' : '')); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #f5f5f5; font-weight: bold;">
                            <th>Total</th>
                            <th style="text-align: right;"><?php echo number_format($totals['hours'], 2); ?></th>
                            <th></th>
                            <th style="text-align: right;"><?php echo esc_html($money($totals['value'])); ?></th>
                            <th style="text-align: right;"><?php echo esc_html($money($totals['billed'])); ?></th>
                            <th style="text-align: right;"><?php echo esc_html($money($totals['unbilled'])); ?></th>
                            <th style="text-align: right;"><?php echo esc_html($money($totals['expenses'])); ?></th>
                            <th style="text-align: right;"><?php echo esc_html($money($totals['net'])); ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
                <p style="color: #666; font-size: 12px;">
                    Value is billable hours at the rate invoices use (the project's rate, else the client's, else the company's). Billed and Not Billed split that value by whether the time is on an invoice. Net is value minus the project's expenses. Budget Used is all hours ever logged on the project against its hour estimate, whatever the period: orange from 90%, red when over. Inactive time entries and expenses are not counted.
                </p>
            <?php else : ?>
                <div class="notice notice-info inline">
                    <p>No projects with time or expenses for the selected filters.</p>
                </div>
            <?php endif; ?>

            <!-- Export buttons at bottom -->
            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <a href="<?php echo esc_url(add_query_arg(array_merge($export_params, ['export' => 'csv']), admin_url('admin.php'))); ?>" class="button button-primary">📊 Export to CSV</a>
                <button type="button" class="button button-secondary" onclick="window.print();">🖨️ Print to PDF</button>
            </div>
        <?php
    }

    private function export_project_profitability_csv(array $rows, array $totals, $start_date, $end_date) {
        $filename = 'project-financials-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel

        fputcsv($output, ['Project Financials']);
        fputcsv($output, ['Generated:', date('F j, Y g:i A')]);
        if ($start_date !== '' || $end_date !== '') {
            fputcsv($output, ['Date Range: ' . ($start_date !== '' ? date('M j, Y', strtotime($start_date)) : 'Beginning') . ' to ' . ($end_date !== '' ? date('M j, Y', strtotime($end_date)) : 'Present')]);
        }
        fputcsv($output, []);
        fputcsv($output, ['Project', 'Client', 'Hours', 'Billable Hours', 'Rate', 'Value', 'Billed', 'Not Billed', 'Expenses', 'Net', 'Estimate (h)', 'All Hours', 'Budget Used %']);
        foreach ($rows as $r) {
            fputcsv($output, [
                $r->project_name, $r->client_name ?: 'No Client',
                number_format($r->hours, 2, '.', ''), number_format($r->billable_hours, 2, '.', ''), number_format($r->rate, 2, '.', ''),
                number_format($r->value, 2, '.', ''), number_format($r->billed, 2, '.', ''), number_format($r->unbilled, 2, '.', ''),
                number_format($r->expenses, 2, '.', ''), number_format($r->net, 2, '.', ''),
                $r->estimate > 0 ? number_format($r->estimate, 2, '.', '') : '', number_format((float) $r->all_hours, 2, '.', ''),
                $r->budget_used === null ? '' : number_format($r->budget_used, 1, '.', ''),
            ]);
        }
        fputcsv($output, []);
        fputcsv($output, ['TOTAL', '', number_format($totals['hours'], 2, '.', ''), number_format($totals['billable_hours'], 2, '.', ''), '',
            number_format($totals['value'], 2, '.', ''), number_format($totals['billed'], 2, '.', ''), number_format($totals['unbilled'], 2, '.', ''),
            number_format($totals['expenses'], 2, '.', ''), number_format($totals['net'], 2, '.', ''), '', '', '']);
        fclose($output);
        exit;
    }
}
