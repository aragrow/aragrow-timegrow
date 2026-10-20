<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * KPIs and charts at the top of the Nexus dashboard.
 *
 * - Everyone with a team member record: their own hours this week and month, billable share,
 *   unbilled hours, clock status, hours per day (last 14 days) and top projects this month.
 * - Administrators also: team hours, billable share, unbilled amount (the Unbilled Time report's
 *   number), invoiced this month, hours per week (last 12 weeks), hours by team member and top
 *   clients this month.
 *
 * Charts are plain HTML and CSS drawn on the server (no chart library): one hue for magnitude,
 * a tooltip on hover or focus, and a table view for each. Weeks start on Monday.
 */
class TimeGrowNexusKpis {

    /** Hours of one entry, in SQL: manual hours, or the clock time when clocked out. */
    const HOURS = "CASE
        WHEN te.hours IS NOT NULL AND te.hours > 0 THEN te.hours
        WHEN te.clock_in_date IS NOT NULL AND te.clock_out_date IS NOT NULL THEN TIMESTAMPDIFF(SECOND, te.clock_in_date, te.clock_out_date) / 3600
        ELSE 0 END";
    const DAY = 'COALESCE(DATE(te.date), DATE(te.clock_in_date))';

    // ------------------------------------------------------------------
    // Pure helpers
    // ------------------------------------------------------------------

    /** Every day from $from to $to (YYYY-MM-DD) with its hours, 0 when none. Pure. */
    public static function fill_days(array $hours_by_day, $from, $to) {
        $out = [];
        for ($d = new DateTimeImmutable($from); $d->format('Y-m-d') <= $to; $d = $d->modify('+1 day')) {
            $out[$d->format('Y-m-d')] = round((float) ($hours_by_day[$d->format('Y-m-d')] ?? 0), 2);
        }
        return $out;
    }

    /** The Mondays of the last $n weeks up to $today, oldest first, with their hours (0 when none). Pure. */
    public static function fill_weeks(array $hours_by_monday, $today, $n = 12) {
        $monday = (new DateTimeImmutable($today))->modify('monday this week');
        $out = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $w = $monday->modify('-' . (7 * $i) . ' days')->format('Y-m-d');
            $out[$w] = round((float) ($hours_by_monday[$w] ?? 0), 2);
        }
        return $out;
    }

    /** Billable share 0–100, one decimal. Pure. */
    public static function percent($part, $total) {
        return (float) $total > 0 ? round((float) $part / (float) $total * 100, 1) : 0.0;
    }

    /** "7.5 h", "12 h". Pure. */
    public static function hours_label($h) {
        $h = round((float) $h, 2);
        return rtrim(rtrim(number_format($h, 2), '0'), '.') . ' h';
    }

    // ------------------------------------------------------------------
    // Data
    // ------------------------------------------------------------------

    private static function table($name) {
        global $wpdb;
        return $wpdb->prefix . TIMEGROW_PREFIX . $name;
    }

    /** Total and billable hours in a range; $member 0 = everyone. */
    private static function totals($member, $from, $to) {
        global $wpdb;
        $sql = 'SELECT SUM(' . self::HOURS . ') AS total, SUM(CASE WHEN te.billable = 1 THEN ' . self::HOURS . ' ELSE 0 END) AS billable
                FROM ' . self::table('time_entry_tracker') . ' te WHERE te.status = 1 AND ' . self::DAY . ' BETWEEN %s AND %s' . ($member ? ' AND te.member_id = %d' : '');
        $row = $wpdb->get_row($member ? $wpdb->prepare($sql, $from, $to, $member) : $wpdb->prepare($sql, $from, $to));
        return ['total' => (float) ($row->total ?? 0), 'billable' => (float) ($row->billable ?? 0)];
    }

    /** Hours grouped by a column (label => hours), most first. */
    private static function grouped($member, $from, $to, $label_sql, $join, $limit) {
        global $wpdb;
        $sql = "SELECT {$label_sql} AS label, SUM(" . self::HOURS . ') AS hours
                FROM ' . self::table('time_entry_tracker') . " te {$join}
                WHERE te.status = 1 AND " . self::DAY . ' BETWEEN %s AND %s' . ($member ? ' AND te.member_id = %d' : '') . "
                GROUP BY label HAVING hours > 0 ORDER BY hours DESC LIMIT " . (int) $limit;
        $rows = $member ? $wpdb->get_results($wpdb->prepare($sql, $from, $to, $member)) : $wpdb->get_results($wpdb->prepare($sql, $from, $to));
        $out = [];
        foreach ((array) $rows as $r) {
            $out[(string) ($r->label ?: __('(none)', 'timegrow'))] = round((float) $r->hours, 2);
        }
        return $out;
    }

    /** Personal KPIs for a team member. */
    public static function personal($member, $today) {
        global $wpdb;
        $monday = (new DateTimeImmutable($today))->modify('monday this week')->format('Y-m-d');
        $first  = substr($today, 0, 8) . '01';
        $from14 = (new DateTimeImmutable($today))->modify('-13 days')->format('Y-m-d');
        $week   = self::totals($member, $monday, $today);
        $month  = self::totals($member, $first, $today);

        $days = [];
        $sql = 'SELECT ' . self::DAY . ' AS d, SUM(' . self::HOURS . ') AS hours FROM ' . self::table('time_entry_tracker') . ' te
                WHERE te.status = 1 AND te.member_id = %d AND ' . self::DAY . ' BETWEEN %s AND %s GROUP BY d';
        foreach ((array) $wpdb->get_results($wpdb->prepare($sql, $member, $from14, $today)) as $r) {
            $days[$r->d] = (float) $r->hours;
        }
        $unbilled = (float) $wpdb->get_var($wpdb->prepare('SELECT SUM(' . self::HOURS . ') FROM ' . self::table('time_entry_tracker') . ' te
            WHERE te.status = 1 AND te.member_id = %d AND te.billable = 1 AND te.billed = 0', $member));
        $clock = (new TimeGrowTeamMemberModel())->get_team_member_clock_status($member);

        return [
            'week'     => $week['total'],
            'month'    => $month['total'],
            'billable' => self::percent($month['billable'], $month['total']),
            'unbilled' => $unbilled,
            'clock'    => ($clock['status'] ?? '') === 'clocked_in'
                ? sprintf(__('Since %s', 'timegrow'), date_i18n(get_option('time_format'), (int) $clock['clockInTimestamp']))
                : '',
            'clock_project' => (string) ($clock['cloked_project'] ?? ''),
            'days'     => self::fill_days($days, $from14, $today),
            'projects' => self::grouped($member, $first, $today, 'p.name', 'LEFT JOIN ' . self::table('project_tracker') . ' p ON p.ID = te.project_id', 6),
        ];
    }

    /** Team KPIs (administrators). */
    public static function team($today) {
        global $wpdb;
        $first = substr($today, 0, 8) . '01';
        $month = self::totals(0, $first, $today);
        $from  = (new DateTimeImmutable($today))->modify('monday this week')->modify('-77 days')->format('Y-m-d');

        $weeks = [];
        $sql = 'SELECT DATE_SUB(' . self::DAY . ', INTERVAL WEEKDAY(' . self::DAY . ') DAY) AS monday, SUM(' . self::HOURS . ') AS hours
                FROM ' . self::table('time_entry_tracker') . ' te WHERE te.status = 1 AND ' . self::DAY . ' BETWEEN %s AND %s GROUP BY monday';
        foreach ((array) $wpdb->get_results($wpdb->prepare($sql, $from, $today)) as $r) {
            $weeks[$r->monday] = (float) $r->hours;
        }

        // The Unbilled Time report's number: the same entries and rates as invoicing
        $unbilled = class_exists('TimeGrowReportsController')
            ? TimeGrowReportsController::unbilled_totals(TimeGrowReportsController::unbilled_rows('', '', 0))['amount']
            : null;
        $invoiced = null;
        if (function_exists('wc_get_orders') && class_exists('TimeGrowNexusVoiceAnswers')) {
            $ids = wc_get_orders(['type' => 'shop_order', 'status' => TimeGrowNexusVoiceAnswers::BILLED_STATUSES, 'limit' => -1, 'return' => 'ids',
                'date_created' => $first . '...' . $today . ' 23:59:59']);
            $invoiced = 0.0;
            foreach ((array) $ids as $id) {
                $o = wc_get_order($id);
                $invoiced += $o ? (float) $o->get_total() : 0;
            }
        }
        $active = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(DISTINCT te.project_id) FROM ' . self::table('time_entry_tracker') . ' te
            WHERE te.status = 1 AND ' . self::DAY . ' BETWEEN %s AND %s', $first, $today));

        return [
            'month'    => $month['total'],
            'billable' => self::percent($month['billable'], $month['total']),
            'unbilled' => $unbilled,
            'invoiced' => $invoiced,
            'active'   => $active,
            'weeks'    => self::fill_weeks($weeks, $today, 12),
            'members'  => self::grouped(0, $first, $today, 'm.name', 'LEFT JOIN ' . self::table('team_member_tracker') . ' m ON m.ID = te.member_id', 8),
            'clients'  => self::grouped(0, $first, $today, 'c.display_name', 'LEFT JOIN ' . self::table('project_tracker') . " p ON p.ID = te.project_id LEFT JOIN {$wpdb->users} c ON c.ID = p.client_id", 6),
        ];
    }

    // ------------------------------------------------------------------
    // Rendering
    // ------------------------------------------------------------------

    private static function report_url($slug, array $args = []) {
        return add_query_arg(array_merge(['page' => TIMEGROW_PARENT_MENU . '-nexus-a-report', 'report_slug' => $slug], $args), admin_url('admin.php'));
    }

    private static function tile($label, $value, $sub = '', $url = '') {
        $inner = '<span class="tg-kpi-label">' . esc_html($label) . '</span><span class="tg-kpi-value">' . esc_html($value) . '</span>'
            . ($sub !== '' ? '<span class="tg-kpi-sub">' . esc_html($sub) . '</span>' : '');
        return $url ? '<a class="tg-kpi-tile" href="' . esc_url($url) . '">' . $inner . '</a>' : '<div class="tg-kpi-tile">' . $inner . '</div>';
    }

    /**
     * Columns over time (one series). $points: key => hours; $label: fn(key) => short axis label;
     * $tip: fn(key, hours) => tooltip text. Every few labels are shown so they don't collide.
     */
    private static function columns($title, array $points, callable $label, callable $tip, $every = 1) {
        $max = max(array_merge([0.0], array_values($points)));
        $top = $max > 0 ? $max : 1;
        $h = '<figure class="tg-kpi-chart"><figcaption>' . esc_html($title) . '</figcaption>';
        $h .= '<div class="tg-kpi-cols" role="img" aria-label="' . esc_attr($title) . '"><span class="tg-kpi-max">' . esc_html(self::hours_label($max)) . '</span>';
        $i = 0;
        foreach ($points as $key => $v) {
            $pct = $v > 0 ? max(2, $v / $top * 100) : 0;
            $h .= '<div class="tg-kpi-col" tabindex="0" data-tip="' . esc_attr($tip($key, $v)) . '">'
                . '<span class="tg-kpi-colbar" style="height:' . round($pct, 1) . '%"></span>'
                . '<span class="tg-kpi-collabel">' . ($i % $every === 0 ? esc_html($label($key)) : '&nbsp;') . '</span></div>';
            $i++;
        }
        $h .= '</div>' . self::table_view(array_map(function ($k, $v) use ($label) { return [$label($k), self::hours_label($v)]; }, array_keys($points), $points)) . '</figure>';
        return $h;
    }

    /** Horizontal bars, largest first, each labeled with its hours. */
    private static function bars($title, array $items, $empty) {
        $h = '<figure class="tg-kpi-chart"><figcaption>' . esc_html($title) . '</figcaption>';
        if (!$items) {
            return $h . '<p class="tg-kpi-empty">' . esc_html($empty) . '</p></figure>';
        }
        $max = max($items);
        $total = array_sum($items);
        $h .= '<div class="tg-kpi-bars">';
        foreach ($items as $name => $v) {
            $share = self::percent($v, $total);
            $h .= '<div class="tg-kpi-barrow" tabindex="0" data-tip="' . esc_attr($name . ': ' . self::hours_label($v) . ' (' . $share . '%)') . '">'
                . '<span class="tg-kpi-barname">' . esc_html($name) . '</span>'
                . '<span class="tg-kpi-bartrack"><span class="tg-kpi-bar" style="width:' . round(max(2, $v / $max * 100), 1) . '%"></span></span>'
                . '<span class="tg-kpi-barvalue">' . esc_html(self::hours_label($v)) . '</span></div>';
        }
        $h .= '</div>' . self::table_view(array_map(function ($k, $v) use ($total) { return [$k, self::hours_label($v) . ' (' . self::percent($v, $total) . '%)']; }, array_keys($items), $items)) . '</figure>';
        return $h;
    }

    private static function table_view(array $rows) {
        $h = '<details class="tg-kpi-table"><summary>' . esc_html__('Show as table', 'timegrow') . '</summary><table><tbody>';
        foreach ($rows as [$a, $b]) {
            $h .= '<tr><td>' . esc_html($a) . '</td><td>' . esc_html($b) . '</td></tr>';
        }
        return $h . '</tbody></table></details>';
    }

    /** The KPI sections for the logged-in person (nothing when they have no team member record and aren't an admin). */
    public static function render() {
        $today  = current_time('Y-m-d');
        $member = class_exists('TimeGrowTimeEntryRules') ? TimeGrowTimeEntryRules::member_id(0) : 0;
        $admin  = current_user_can('administrator');
        if (!$member && !$admin) {
            return;
        }
        $day_label = function ($d) { return date_i18n('D', strtotime($d)); };
        $day_tip   = function ($d, $v) { return date_i18n('D, M j', strtotime($d)) . ': ' . self::hours_label($v); };
        $week_label = function ($w) { return date_i18n('M j', strtotime($w)); };
        $week_tip   = function ($w, $v) { return sprintf(__('Week of %s', 'timegrow'), date_i18n('M j', strtotime($w))) . ': ' . self::hours_label($v); };
        $month_name = date_i18n('F', strtotime($today));

        $panels = [];
        if ($member) {
            $k = self::personal($member, $today);
            $panels['mine'] = [__('My Time', 'timegrow'), 'dashicons-chart-area', '<div class="tg-kpi-tiles">'
                . self::tile(__('This week', 'timegrow'), self::hours_label($k['week']), '', self::report_url('my_hours_by_project', ['start_date' => (new DateTimeImmutable($today))->modify('monday this week')->format('Y-m-d'), 'end_date' => $today]))
                . self::tile(sprintf(__('%s so far', 'timegrow'), $month_name), self::hours_label($k['month']), '', self::report_url('my_hours_by_project', ['start_date' => substr($today, 0, 8) . '01', 'end_date' => $today]))
                . self::tile(__('Billable', 'timegrow'), $k['billable'] . '%', sprintf(__('of %s hours', 'timegrow'), $month_name))
                . self::tile(__('Not billed yet', 'timegrow'), self::hours_label($k['unbilled']), __('billable hours', 'timegrow'))
                . self::tile(__('Clock', 'timegrow'), $k['clock'] !== '' ? __('Clocked in', 'timegrow') : __('Not clocked in', 'timegrow'),
                    $k['clock'] !== '' ? $k['clock'] . ($k['clock_project'] !== '' ? ' · ' . $k['clock_project'] : '') : '', admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-clock'))
                . '</div><div class="tg-kpi-charts">'
                . self::columns(__('Hours per day, last 14 days', 'timegrow'), $k['days'], $day_label, $day_tip)
                . self::bars(sprintf(__('Top projects in %s', 'timegrow'), $month_name), $k['projects'], __('No time this month yet.', 'timegrow'))
                . '</div>'];
        }
        if ($admin) {
            $t = self::team($today);
            $panels['team'] = [__('Team & Business', 'timegrow'), 'dashicons-groups', '<div class="tg-kpi-tiles">'
                . self::tile(sprintf(__('Team hours, %s', 'timegrow'), $month_name), self::hours_label($t['month']), '', self::report_url('team_summary_hours', ['start_date' => substr($today, 0, 8) . '01', 'end_date' => $today]))
                . self::tile(__('Billable', 'timegrow'), $t['billable'] . '%', sprintf(__('of %s team hours', 'timegrow'), $month_name))
                . ($t['unbilled'] !== null ? self::tile(__('Unbilled work', 'timegrow'), '$' . number_format($t['unbilled'], 2), __('ready to invoice', 'timegrow'), self::report_url('unbilled_time')) : '')
                . ($t['invoiced'] !== null ? self::tile(sprintf(__('Invoiced, %s', 'timegrow'), $month_name), '$' . number_format($t['invoiced'], 2), __('sent to clients', 'timegrow')) : '')
                . self::tile(__('Active projects', 'timegrow'), (string) $t['active'], sprintf(__('with time in %s', 'timegrow'), $month_name))
                . '</div><div class="tg-kpi-charts">'
                . self::columns(__('Team hours per week, last 12 weeks', 'timegrow'), $t['weeks'], $week_label, $week_tip, 2)
                . self::bars(sprintf(__('Hours by team member, %s', 'timegrow'), $month_name), $t['members'], __('No team time this month yet.', 'timegrow'))
                . self::bars(sprintf(__('Top clients by hours, %s', 'timegrow'), $month_name), $t['clients'], __('No client time this month yet.', 'timegrow'))
                . '</div>'];
        }
        echo self::styles() . self::tabs($panels);
    }

    /**
     * The panels as tabs (My Time first and open); a single panel is shown with its heading, no tabs.
     *
     * @param array $panels key => [label, dashicon, html]
     */
    private static function tabs(array $panels) {
        if (count($panels) === 1) {
            [$label, $icon, $html] = reset($panels);
            return '<div class="timegrow-section tg-kpis"><h2 class="timegrow-section-title"><span class="dashicons ' . esc_attr($icon) . '"></span>'
                . esc_html($label) . '</h2>' . $html . '</div>';
        }
        $tabs = '';
        $body = '';
        $first = true;
        foreach ($panels as $key => [$label, $icon, $html]) {
            $tabs .= '<button type="button" role="tab" class="tg-kpi-tab" id="tg-kpi-tab-' . esc_attr($key) . '" aria-controls="tg-kpi-panel-' . esc_attr($key) . '"'
                . ' aria-selected="' . ($first ? 'true' : 'false') . '" tabindex="' . ($first ? '0' : '-1') . '">'
                . '<span class="dashicons ' . esc_attr($icon) . '"></span>' . esc_html($label) . '</button>';
            $body .= '<div role="tabpanel" class="tg-kpi-panel" id="tg-kpi-panel-' . esc_attr($key) . '" aria-labelledby="tg-kpi-tab-' . esc_attr($key) . '"' . ($first ? '' : ' hidden') . '>' . $html . '</div>';
            $first = false;
        }
        // Click a tab, or use the arrow keys (Home / End) when a tab has focus
        $script = "<script>(function(){var r=document.currentScript.parentNode,t=[].slice.call(r.querySelectorAll('[role=tab]'));"
            . "function go(n,f){t.forEach(function(x,i){var on=i===n;x.setAttribute('aria-selected',on?'true':'false');x.tabIndex=on?0:-1;"
            . "document.getElementById(x.getAttribute('aria-controls')).hidden=!on;});if(f)t[n].focus();}"
            . "t.forEach(function(x,i){x.addEventListener('click',function(){go(i);});x.addEventListener('keydown',function(e){"
            . "var n={ArrowRight:i+1,ArrowLeft:i-1,Home:0,End:t.length-1}[e.key];if(n===undefined)return;e.preventDefault();go((n+t.length)%t.length,true);});});})();</script>";
        return '<div class="timegrow-section tg-kpis"><div class="tg-kpi-tablist" role="tablist" aria-label="' . esc_attr__('Dashboard KPIs', 'timegrow') . '">'
            . $tabs . '</div>' . $body . $script . '</div>';
    }

    private static function styles() {
        return '<style>
            .tg-kpis { --tg-surface: #ffffff; --tg-track: #ecebe8; --tg-series: #2a78d6; --tg-text: #1d2327; --tg-muted: #646970; --tg-border: #e0e0e0; }
            .tg-kpi-tablist { display: flex; gap: 4px; border-bottom: 2px solid var(--tg-border); margin-bottom: 16px; }
            .tg-kpi-tab { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; margin-bottom: -2px; background: none; border: 0; border-bottom: 2px solid transparent; font-size: 14px; font-weight: 600; color: var(--tg-muted); cursor: pointer; }
            .tg-kpi-tab:hover { color: var(--tg-text); }
            .tg-kpi-tab[aria-selected="true"] { color: var(--tg-text); border-bottom-color: var(--tg-series); }
            .tg-kpi-tab:focus-visible { outline: 2px solid var(--tg-series); outline-offset: 2px; }
            .tg-kpi-tab .dashicons { font-size: 18px; width: 18px; height: 18px; color: var(--tg-series); }
            .tg-kpi-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; margin-bottom: 16px; }
            .tg-kpi-tile { display: flex; flex-direction: column; gap: 4px; padding: 14px 16px; background: var(--tg-surface); border: 1px solid var(--tg-border); border-radius: 8px; color: var(--tg-text); text-decoration: none; }
            a.tg-kpi-tile:hover, a.tg-kpi-tile:focus { border-color: var(--tg-series); color: var(--tg-text); }
            .tg-kpi-label { font-size: 12px; color: var(--tg-muted); text-transform: uppercase; letter-spacing: .03em; }
            .tg-kpi-value { font-size: 26px; font-weight: 600; line-height: 1.2; font-variant-numeric: tabular-nums; }
            .tg-kpi-sub { font-size: 12px; color: var(--tg-muted); }
            .tg-kpi-charts { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px; }
            .tg-kpi-chart { margin: 0; padding: 14px 16px; background: var(--tg-surface); border: 1px solid var(--tg-border); border-radius: 8px; min-width: 0; }
            .tg-kpi-chart figcaption { font-weight: 600; margin-bottom: 12px; color: var(--tg-text); }
            .tg-kpi-cols { position: relative; display: flex; align-items: flex-end; gap: 2px; height: 150px; padding-top: 18px; border-bottom: 1px solid var(--tg-border); }
            .tg-kpi-max { position: absolute; top: 0; left: 0; font-size: 11px; color: var(--tg-muted); }
            .tg-kpi-col { position: relative; flex: 1 1 0; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; outline: none; }
            .tg-kpi-colbar { display: block; width: 100%; max-width: 24px; background: var(--tg-series); border-radius: 4px 4px 0 0; }
            .tg-kpi-collabel { position: absolute; bottom: -20px; font-size: 11px; color: var(--tg-muted); white-space: nowrap; }
            .tg-kpi-cols + .tg-kpi-table { margin-top: 26px; }
            .tg-kpi-col:hover .tg-kpi-colbar, .tg-kpi-col:focus .tg-kpi-colbar { filter: brightness(.85); }
            .tg-kpi-bars { display: flex; flex-direction: column; gap: 8px; }
            .tg-kpi-barrow { position: relative; display: grid; grid-template-columns: minmax(80px, 34%) 1fr auto; align-items: center; gap: 8px; outline: none; }
            .tg-kpi-barname { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; color: var(--tg-text); }
            .tg-kpi-bartrack { display: block; height: 14px; }
            .tg-kpi-bar { display: block; height: 100%; background: var(--tg-series); border-radius: 0 4px 4px 0; }
            .tg-kpi-barrow:hover .tg-kpi-bar, .tg-kpi-barrow:focus .tg-kpi-bar { filter: brightness(.85); }
            .tg-kpi-barvalue { font-size: 12px; color: var(--tg-muted); font-variant-numeric: tabular-nums; }
            .tg-kpi-col[data-tip]:hover::after, .tg-kpi-col[data-tip]:focus::after, .tg-kpi-barrow[data-tip]:hover::after, .tg-kpi-barrow[data-tip]:focus::after {
                content: attr(data-tip); position: absolute; z-index: 5; bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%);
                background: var(--tg-text); color: #fff; font-size: 12px; padding: 4px 8px; border-radius: 4px; white-space: nowrap; pointer-events: none; }
            .tg-kpi-col:first-of-type[data-tip]:hover::after, .tg-kpi-col:first-of-type[data-tip]:focus::after { left: 0; transform: none; }
            .tg-kpi-col:last-child[data-tip]:hover::after, .tg-kpi-col:last-child[data-tip]:focus::after { left: auto; right: 0; transform: none; }
            .tg-kpi-empty { color: var(--tg-muted); margin: 0; }
            .tg-kpi-table { margin-top: 10px; font-size: 12px; color: var(--tg-muted); }
            .tg-kpi-table table { margin-top: 6px; border-collapse: collapse; }
            .tg-kpi-table td { padding: 2px 12px 2px 0; }
            @media (max-width: 782px) { .tg-kpi-charts { grid-template-columns: 1fr; } }
        </style>';
    }
}
