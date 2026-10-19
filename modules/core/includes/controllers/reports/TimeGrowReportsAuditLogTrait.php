<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Time Entry Audit Log: every change to a time entry (created, edited, clocked out, billed,
 * unbilled, rebilled, deleted), who made it, from where, and what changed. Reads the trail kept by
 * TimeGrowTimeEntryAudit. Page (100 changes per page) and CSV export. Administrators only
 * (view_time_entry_audit_log).
 */
trait TimeGrowReportsAuditLogTrait {

    /** Labels and colors for the actions. */
    private static function audit_actions() {
        return [
            'created'     => ['Created', '#2e7d32'],
            'updated'     => ['Edited', '#1565c0'],
            'clocked_out' => ['Clocked out', '#00838f'],
            'billed'      => ['Billed', '#7b1fa2'],
            'unbilled'    => ['Unbilled', '#e65100'],
            'rebilled'    => ['Rebilled', '#6a1b9a'],
            'deleted'     => ['Deleted', '#c62828'],
        ];
    }

    /** The WHERE clause and its values for the filters. */
    private function audit_where($start_date, $end_date, $action, $member_id, $entry_id) {
        $where = ['1=1'];
        $args  = [];
        if ($start_date !== '') {
            $where[] = 'a.created_at >= %s';
            $args[]  = $start_date . ' 00:00:00';
        }
        if ($end_date !== '') {
            $where[] = 'a.created_at <= %s';
            $args[]  = $end_date . ' 23:59:59';
        }
        if ($action !== '' && in_array($action, TimeGrowTimeEntryAudit::ACTIONS, true)) {
            $where[] = 'a.action = %s';
            $args[]  = $action;
        }
        if ($member_id > 0) {
            // The entry's member now, or in the snapshot (for a deleted entry)
            $where[] = '(te.member_id = %d OR (te.ID IS NULL AND a.snapshot LIKE %s))';
            $args[]  = $member_id;
            $args[]  = '%"member_id":"' . $member_id . '"%';
        }
        if ($entry_id > 0) {
            $where[] = 'a.entry_id = %d';
            $args[]  = $entry_id;
        }
        return [implode(' AND ', $where), $args];
    }

    private function audit_query($select, $where, $args, $tail = '') {
        global $wpdb;
        $p   = $wpdb->prefix . TIMEGROW_PREFIX;
        $sql = "SELECT {$select}
                FROM " . TimeGrowTimeEntryAudit::table() . " a
                LEFT JOIN {$p}time_entry_tracker te ON te.ID = a.entry_id
                LEFT JOIN {$p}team_member_tracker m ON m.ID = te.member_id
                LEFT JOIN {$p}project_tracker p ON p.ID = te.project_id
                LEFT JOIN {$wpdb->users} u ON u.ID = a.user_id
                WHERE {$where} {$tail}";
        return $args ? $wpdb->prepare($sql, $args) : $sql;
    }

    private function generate_time_entry_audit_log($current_user) {
        global $wpdb;
        TimeGrowTimeEntryAudit::maybe_create_table();

        $start_date = TimeGrowDateRanges::valid_date($_GET['start_date'] ?? '');
        $end_date   = isset($_GET['end_date']) ? sanitize_text_field($_GET['end_date']) : '';
        $action     = isset($_GET['filter_action']) ? sanitize_key($_GET['filter_action']) : '';
        $member_id  = isset($_GET['filter_member']) ? intval($_GET['filter_member']) : 0;
        $entry_id   = isset($_GET['entry_id']) ? intval($_GET['entry_id']) : 0;
        $paged      = max(1, isset($_GET['paged']) ? intval($_GET['paged']) : 1);
        $per_page   = 100;

        [$where, $args] = $this->audit_where($start_date, $end_date, $action, $member_id, $entry_id);
        $select = 'a.*, u.display_name AS user_name, m.name AS member_name, p.name AS project_name, te.ID AS still_there';

        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $rows = $wpdb->get_results($this->audit_query($select, $where, $args, 'ORDER BY a.created_at DESC, a.ID DESC LIMIT 20000'));
            $this->export_time_entry_audit_log_csv((array) $rows, $start_date, $end_date);
            return;
        }

        $counts = [];
        foreach ((array) $wpdb->get_results($this->audit_query('a.action, COUNT(*) AS n, COUNT(DISTINCT a.user_id) AS people', $where, $args, 'GROUP BY a.action')) as $c) {
            $counts[$c->action] = (int) $c->n;
        }
        $people = (int) $wpdb->get_var($this->audit_query('COUNT(DISTINCT a.user_id)', $where, $args));
        $total  = array_sum($counts);
        $pages  = max(1, (int) ceil($total / $per_page));
        $paged  = min($paged, $pages);
        $rows   = $wpdb->get_results($this->audit_query($select, $where, $args,
            $wpdb->prepare('ORDER BY a.created_at DESC, a.ID DESC LIMIT %d OFFSET %d', $per_page, ($paged - 1) * $per_page)));
        $since  = $wpdb->get_var('SELECT MIN(created_at) FROM ' . TimeGrowTimeEntryAudit::table());
        $members = (array) (new TimeGrowTeamMemberModel())->select();

        $base = ['page' => sanitize_key($_GET['page']), 'report_slug' => 'time_entry_audit_log'];
        $keep = array_filter(['filter_action' => $action, 'filter_member' => $member_id, 'entry_id' => $entry_id]);
        $export_params = array_merge($base, $keep, array_filter(['start_date' => $start_date, 'end_date' => $end_date]));
        $labels = self::audit_actions();
        ?>
            <!-- Filters -->
            <div class="tax-report-controls" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
                <?php TimeGrowDateRanges::render(array_merge($base, $keep), $start_date, $end_date); // keeps the other filters ?>
                <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="<?php echo esc_attr($base['page']); ?>" />
                    <input type="hidden" name="report_slug" value="time_entry_audit_log" />

                    <label for="filter_action" style="font-weight: bold; margin: 0;">Change:</label>
                    <select name="filter_action" id="filter_action" style="padding: 8px 12px;">
                        <option value="">All Changes</option>
                        <?php foreach ($labels as $key => [$label]) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($action, $key); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="filter_member" style="font-weight: bold; margin: 0;">Team Member:</label>
                    <select name="filter_member" id="filter_member" style="padding: 8px 12px;">
                        <option value="">Everyone</option>
                        <?php foreach ($members as $m) : ?>
                            <option value="<?php echo (int) $m->ID; ?>" <?php selected($member_id, (int) $m->ID); ?>><?php echo esc_html($m->name); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="entry_id" style="font-weight: bold; margin: 0;">Entry #:</label>
                    <input type="number" min="1" name="entry_id" id="entry_id" value="<?php echo $entry_id ? (int) $entry_id : ''; ?>" style="padding: 8px 12px; width: 100px;" />

                    <label for="start_date" style="font-weight: bold; margin: 0;">From:</label>
                    <input type="date" name="start_date" id="start_date" value="<?php echo esc_attr($start_date); ?>" style="padding: 8px 12px;" />

                    <label for="end_date" style="font-weight: bold; margin: 0;">To:</label>
                    <input type="date" name="end_date" id="end_date" value="<?php echo esc_attr($end_date); ?>" style="padding: 8px 12px;" />

                    <button type="submit" class="button button-primary">Generate Report</button>
                    <a href="<?php echo esc_url(add_query_arg($base, admin_url('admin.php'))); ?>" class="button">Clear</a>
                </form>
            </div>

            <!-- Summary Cards -->
            <div class="tax-report-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 30px;">
                <div class="summary-card" style="background: #e3f2fd; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #1565c0;">Changes</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo (int) $total; ?></p>
                </div>
                <div class="summary-card" style="background: #e8f5e9; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #2e7d32;">Created</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo (int) ($counts['created'] ?? 0); ?></p>
                </div>
                <div class="summary-card" style="background: #fff3e0; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #e65100;">Edited</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo (int) (($counts['updated'] ?? 0) + ($counts['clocked_out'] ?? 0)); ?></p>
                </div>
                <div class="summary-card" style="background: #f3e5f5; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #7b1fa2;">Billing Changes</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo (int) (($counts['billed'] ?? 0) + ($counts['unbilled'] ?? 0) + ($counts['rebilled'] ?? 0)); ?></p>
                </div>
                <div class="summary-card" style="background: #fce4ec; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #c2185b;">Deleted</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo (int) ($counts['deleted'] ?? 0); ?></p>
                </div>
                <div class="summary-card" style="background: #e0f7fa; padding: 20px; border-radius: 5px; text-align: center;">
                    <h3 style="margin: 0 0 10px 0; color: #00838f;">People</h3>
                    <p style="font-size: 28px; font-weight: bold; margin: 0;"><?php echo (int) $people; ?></p>
                </div>
            </div>

            <!-- Changes Table -->
            <h2>Changes</h2>
            <?php if (!empty($rows)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 140px;">When</th>
                            <th style="width: 110px;">Change</th>
                            <th style="width: 70px;">Entry</th>
                            <th>Team Member / Project</th>
                            <th style="width: 30%;">What Changed</th>
                            <th>By</th>
                            <th style="width: 70px;">From</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) :
                            $snap    = json_decode((string) $row->snapshot, true) ?: [];
                            $changes = json_decode((string) $row->changes, true) ?: [];
                            [$label, $color] = $labels[$row->action] ?? [ucfirst($row->action), '#555'];
                            $what = $changes ? TimeGrowTimeEntryAudit::describe($changes) : '';
                            if ($what === '' && $row->action === 'created') {
                                $what = TimeGrowTimeEntryAudit::describe(array_filter([
                                    'hours'       => isset($snap['hours']) && $snap['hours'] !== null ? [null, $snap['hours']] : null,
                                    'date'        => !empty($snap['date']) ? [null, substr($snap['date'], 0, 10)] : null,
                                    'clock_in_date' => empty($snap['date']) && !empty($snap['clock_in_date']) ? [null, $snap['clock_in_date']] : null,
                                    'billable'    => isset($snap['billable']) ? [null, $snap['billable']] : null,
                                ]));
                                $what = str_replace('(empty) → ', '', $what);
                            }
                            if ($row->order_id) {
                                $what = trim($what . ($what !== '' ? '; ' : '') . 'invoice #' . (int) $row->order_id);
                            }
                        ?>
                            <tr>
                                <td><?php echo esc_html(mysql2date('M j, Y g:i A', $row->created_at)); ?></td>
                                <td><strong style="color: <?php echo esc_attr($color); ?>;"><?php echo esc_html($label); ?></strong></td>
                                <td>
                                    <a href="<?php echo esc_url(add_query_arg(array_merge($base, ['entry_id' => (int) $row->entry_id]), admin_url('admin.php'))); ?>" title="History of this entry">#<?php echo (int) $row->entry_id; ?></a>
                                    <?php if (!$row->still_there) : ?><br /><small style="color: #c62828;">deleted</small><?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html($row->member_name ?: '—'); ?><br />
                                    <small style="color: #666;"><?php echo esc_html($row->project_name ?: ''); ?></small>
                                </td>
                                <td><?php echo esc_html($what ?: '—'); ?></td>
                                <td><?php echo esc_html($row->user_name ?: ($row->user_id ? 'User #' . (int) $row->user_id : 'System')); ?></td>
                                <td><?php echo esc_html(ucfirst($row->source) . (!empty($row->approval) ? ' · ' . ['click' => 'clicked to approve', 'spoken' => 'said yes', 'typed' => 'typed yes'][$row->approval] : '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if ($pages > 1) : ?>
                    <div style="margin-top: 15px; display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                        <span>Page <?php echo (int) $paged; ?> of <?php echo (int) $pages; ?>:</span>
                        <?php if ($paged > 1) : ?>
                            <a class="button" href="<?php echo esc_url(add_query_arg(array_merge($export_params, ['paged' => $paged - 1]), admin_url('admin.php'))); ?>">‹ Newer</a>
                        <?php endif; ?>
                        <?php if ($paged < $pages) : ?>
                            <a class="button" href="<?php echo esc_url(add_query_arg(array_merge($export_params, ['paged' => $paged + 1]), admin_url('admin.php'))); ?>">Older ›</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <div class="notice notice-info inline">
                    <p>No changes found for the selected filters.</p>
                </div>
            <?php endif; ?>
            <p style="color: #666; font-size: 12px;">
                <?php if ($since) : ?>
                    The audit log started on <?php echo esc_html(mysql2date('M j, Y', $since)); ?>; changes before that were not recorded.
                <?php else : ?>
                    The audit log records changes from now on; changes made before it was installed were not recorded.
                <?php endif; ?>
                "From" is where the change was made: Web, Voice, Mobile, or System (automatic, like an invoice being cancelled). For Voice it also shows how the person approved it.
            </p>

            <!-- Export buttons at bottom -->
            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <a href="<?php echo esc_url(add_query_arg(array_merge($export_params, ['export' => 'csv']), admin_url('admin.php'))); ?>" class="button button-primary">📊 Export to CSV</a>
                <button type="button" class="button button-secondary" onclick="window.print();">🖨️ Print to PDF</button>
            </div>
        <?php
    }

    private function export_time_entry_audit_log_csv(array $rows, $start_date, $end_date) {
        $filename = 'time-entry-audit-log-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM for Excel

        TimeGrowCsv::put($output, ['Time Entry Audit Log']);
        TimeGrowCsv::put($output, ['Generated:', date('F j, Y g:i A')]);
        if ($start_date !== '' || $end_date !== '') {
            TimeGrowCsv::put($output, ['Date Range: ' . ($start_date !== '' ? date('M j, Y', strtotime($start_date)) : 'Beginning') . ' to ' . ($end_date !== '' ? date('M j, Y', strtotime($end_date)) : 'Present')]);
        }
        TimeGrowCsv::put($output, []);
        TimeGrowCsv::put($output, ['When', 'Change', 'Entry', 'Team Member', 'Project', 'What Changed', 'Invoice', 'By', 'From']);
        $labels = self::audit_actions();
        foreach ($rows as $row) {
            $changes = json_decode((string) $row->changes, true) ?: [];
            TimeGrowCsv::put($output, [
                $row->created_at,
                $labels[$row->action][0] ?? $row->action,
                (int) $row->entry_id,
                $row->member_name ?: '',
                $row->project_name ?: '',
                TimeGrowTimeEntryAudit::describe($changes),
                $row->order_id ? (int) $row->order_id : '',
                $row->user_name ?: ($row->user_id ? 'User #' . (int) $row->user_id : 'System'),
                ucfirst($row->source) . (!empty($row->approval) ? ' (approved: ' . $row->approval . ')' : ''),
            ]);
        }
        fclose($output);
        exit;
    }
}
