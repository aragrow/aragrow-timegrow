<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The voice assistant's flexible, read-only data lookup (question "lookup").
 *
 * The AI only describes what to look up: an entity, a measure, a grouping, filters. TimeGrow checks
 * the description against an allowed list (spec()), applies the role rules, builds the query
 * itself and writes the answer. Nothing here writes or deletes, and the data found is never sent
 * to the AI.
 *
 * Role rules (enforced here, whatever the AI asks for):
 * - Owners (administrators): all TimeGrow and WooCommerce entities, for everyone.
 * - Team members: their own time entries, their projects and clients only; never expenses,
 *   invoices or other people.
 */
class TimeGrowNexusLookup {

    const MAX_ROWS  = 25;
    const MAX_YEARS = 5;

    /** What can be looked up: measures, groupings and filters per entity, and who may. */
    public static function entities() {
        return [
            'time_entries' => ['owner' => false, 'metrics' => ['hours', 'count', 'list'], 'group_by' => ['none', 'client', 'project', 'person', 'month', 'billable'],
                               'filters' => ['client_id', 'project_id', 'member_id', 'billable', 'billed']],
            'projects'     => ['owner' => false, 'metrics' => ['count', 'list'], 'group_by' => ['none', 'client'], 'filters' => ['client_id', 'active']],
            'clients'      => ['owner' => false, 'metrics' => ['count', 'list'], 'group_by' => ['none'], 'filters' => []],
            'expenses'     => ['owner' => true, 'metrics' => ['amount', 'count', 'list'], 'group_by' => ['none', 'category', 'client', 'project', 'month'],
                               'filters' => ['client_id', 'project_id', 'category_id', 'min_amount', 'max_amount']],
            'invoices'     => ['owner' => true, 'metrics' => ['amount', 'count', 'list'], 'group_by' => ['none', 'client', 'status', 'month'],
                               'filters' => ['client_id', 'status', 'min_amount', 'max_amount']],
        ];
    }

    /** Invoice status groups a person can name, besides a single WooCommerce status. */
    const STATUS_GROUPS = [
        'billed' => ['invoice_sent', 'inv_sent2paypal', 'to_be_mailed', 'partial_paid', 'invoice_paid'],
        'unpaid' => ['invoice_sent', 'inv_sent2paypal', 'inv_draft2paypal', 'partial_paid', 'to_be_mailed'],
        'paid'   => ['invoice_paid'],
        'all'    => [], // every status except cancelled, refunded, failed and drafts
    ];

    // ------------------------------------------------------------------
    // Checking what the AI described (pure)
    // ------------------------------------------------------------------

    /**
     * The lookup to run, with the role rules applied, or ['denied' => true] when the person may not,
     * or null when it isn't a valid lookup. Pure.
     *
     * @param mixed $raw   The AI's question.lookup object.
     * @param bool  $owner Administrator (owner) or not.
     * @param array $ok    { clients: int[], projects: int[], members: int[], categories: int[], statuses: string[], me: int (member ID) }
     * @param string $today YYYY-MM-DD.
     */
    public static function spec($raw, $owner, array $ok, $today) {
        if (!is_array($raw)) {
            return null;
        }
        $entities = self::entities();
        $entity   = is_string($raw['entity'] ?? null) ? $raw['entity'] : '';
        if (!isset($entities[$entity])) {
            return null;
        }
        $def = $entities[$entity];
        if ($def['owner'] && !$owner) {
            return ['denied' => true];
        }
        $metric   = in_array($raw['metric'] ?? null, $def['metrics'], true) ? $raw['metric'] : $def['metrics'][0];
        $group_by = in_array($raw['group_by'] ?? null, $def['group_by'], true) ? $raw['group_by'] : 'none';
        $f        = is_array($raw['filters'] ?? null) ? $raw['filters'] : [];
        $in       = function ($key, $list) use ($f) {
            return isset($f[$key]) && is_numeric($f[$key]) && in_array((int) $f[$key], array_map('intval', $list), true) ? (int) $f[$key] : null;
        };

        $filters = [];
        if (in_array('client_id', $def['filters'], true))   $filters['client_id']   = $in('client_id', $ok['clients'] ?? []);
        if (in_array('project_id', $def['filters'], true))  $filters['project_id']  = $in('project_id', $ok['projects'] ?? []);
        if (in_array('member_id', $def['filters'], true))   $filters['member_id']   = $in('member_id', $ok['members'] ?? []);
        if (in_array('category_id', $def['filters'], true)) $filters['category_id'] = $in('category_id', $ok['categories'] ?? []);
        foreach (['billable', 'billed', 'active'] as $flag) {
            if (in_array($flag, $def['filters'], true) && isset($f[$flag]) && $f[$flag] !== null && $f[$flag] !== '') {
                $filters[$flag] = filter_var($f[$flag], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }
        }
        foreach (['min_amount', 'max_amount'] as $m) {
            if (in_array($m, $def['filters'], true) && isset($f[$m]) && is_numeric($f[$m]) && (float) $f[$m] >= 0) {
                $filters[$m] = round((float) $f[$m], 2);
            }
        }
        if (in_array('status', $def['filters'], true) && is_string($f['status'] ?? null)) {
            $st = strtolower(preg_replace('/^wc-/', '', trim($f['status'])));
            if (isset(self::STATUS_GROUPS[$st]) || in_array($st, $ok['statuses'] ?? [], true)) {
                $filters['status'] = $st;
            }
        }

        // Dates: a named period, or from/to (not in the future, at most MAX_YEARS)
        $period = in_array($raw['period'] ?? null, TimeGrowNexusAssistant::PERIODS, true) ? $raw['period'] : null;
        $valid  = function ($d) use ($today) {
            $x = DateTime::createFromFormat('!Y-m-d', (string) $d);
            return ($x && $x->format('Y-m-d') === $d && $d <= $today) ? $d : null;
        };
        $from = $valid($raw['from'] ?? null);
        $to   = $valid($raw['to'] ?? null) ?: ($from ? $today : null);
        if ($from && $to && ($from > $to || (strtotime($to) - strtotime($from)) > self::MAX_YEARS * 366 * DAY_IN_SECONDS)) {
            $from = $to = null;
        }
        if (!$from && $period) {
            [$from, $to] = TimeGrowNexusAssistant::period_range($period, $today);
        }
        // Time entries and invoices: the last 3 months only (by default, and as the earliest date)
        $windowed = in_array($entity, ['time_entries', 'invoices'], true);
        $clipped  = false;
        $out_of_window = false;
        if ($windowed) {
            $floor = TimeGrowNexusAssistant::data_floor($today);
            if (!$from) {
                [$from, $to, $period] = [$floor, $today, null];
            } elseif ($to < $floor) {
                $out_of_window = true; // everything asked for is older than the last 3 months
            } elseif ($from < $floor) {
                [$from, $clipped] = [$floor, true];
                $period = null;
                if ($to < $from) {
                    $to = $from;
                }
            }
        } elseif (!$from && $entity === 'expenses') {
            [$from, $to] = TimeGrowNexusAssistant::period_range('this_year', $today); // a sensible default
            $period = 'this_year';
        }

        $limit = isset($raw['limit']) && is_numeric($raw['limit']) ? max(1, min(self::MAX_ROWS, (int) $raw['limit'])) : 10;

        // Team members: their own data only
        $own_only = false;
        if (!$owner) {
            if ($entity === 'time_entries') {
                $asked = isset($f['member_id']) && is_numeric($f['member_id']) ? (int) $f['member_id'] : 0;
                $own_only = ($asked && $asked !== (int) ($ok['me'] ?? 0)) || $group_by === 'person';
                $filters['member_id'] = (int) ($ok['me'] ?? 0); // always theirs, whatever was asked
                if ($group_by === 'person') {
                    $group_by = 'none';
                }
            }
        }
        if ($entity === 'time_entries' && !$owner && empty($ok['me'])) {
            return ['denied' => true]; // not a team member: no time of their own
        }

        return [
            'entity'   => $entity,
            'metric'   => $metric,
            'group_by' => $group_by,
            'filters'  => array_filter($filters, function ($v) { return $v !== null; }),
            'period'   => $period,
            'from'     => $from,
            'to'       => $to,
            'limit'    => $limit,
            'own_only' => $own_only, // a team member asked about someone else: they are told they see their own time
            'clipped'  => $clipped,  // the period asked for went back further than 3 months
            'out_of_window' => $out_of_window, // all of it is older than 3 months: not run, the reports are suggested
        ];
    }

    // ------------------------------------------------------------------
    // Running it (read-only)
    // ------------------------------------------------------------------

    /**
     * Run a checked lookup and write the answer.
     *
     * @param array $spec       From spec().
     * @param array $candidates The person's projects (team members see only these).
     * @param bool  $owner
     * @return array { text, spoken, rows }
     */
    public static function run(array $spec, array $candidates, $owner) {
        $topic = $spec['entity'] === 'invoices' ? 'invoices' : ($owner && empty($spec['filters']['member_id']) ? 'time_team' : 'time_me');
        if (!empty($spec['out_of_window'])) {
            return TimeGrowNexusAssistant::out_of_window_reply($topic);
        }
        $reply = self::run_entity($spec, $candidates, $owner);
        return !empty($spec['clipped']) ? TimeGrowNexusAssistant::add_window_note($reply, $topic) : $reply;
    }

    private static function run_entity(array $spec, array $candidates, $owner) {
        switch ($spec['entity']) {
            case 'time_entries':
                $reply = self::run_time($spec);
                if (!empty($spec['own_only'])) {
                    $note = __('You can only see your own time. Your hours:', 'timegrow');
                    $reply['text']   = $note . ' ' . $reply['text'];
                    $reply['spoken'] = 'You can only see your own time. ' . $reply['spoken'];
                }
                return $reply;
            case 'projects':     return self::run_projects($spec, $candidates, $owner);
            case 'clients':      return self::run_clients($spec, $candidates, $owner);
            case 'expenses':     return self::run_expenses($spec);
            case 'invoices':     return self::run_invoices($spec);
        }
        return self::reply(__('I can\'t look that up.', 'timegrow'));
    }

    private static function reply($text, $spoken = '', array $rows = []) {
        return ['text' => $text, 'spoken' => $spoken !== '' ? $spoken : $text, 'rows' => $rows];
    }

    private static function when(array $spec) {
        if ($spec['period']) {
            return TimeGrowNexusAssistant::period_words($spec['period']);
        }
        if ($spec['from']) {
            return sprintf(__('from %1$s to %2$s', 'timegrow'), mysql2date(get_option('date_format'), $spec['from']), mysql2date(get_option('date_format'), $spec['to']));
        }
        return __('so far', 'timegrow');
    }

    private static function client_name($id) {
        $n = TimeGrowProjectPicker::client_name($id);
        return $n !== '' ? $n : __('No client', 'timegrow');
    }

    /**
     * Group rows (each with a 'key' label and a numeric 'value') and keep the top ones. With $count,
     * each line also shows how many items it holds ("$3,240.00 · 1 invoice"), so totals are never
     * read across lines.
     */
    private static function grouped(array $items, $limit, $format, $count = null) {
        $by = [];
        $n  = [];
        foreach ($items as $it) {
            $by[$it['key']] = ($by[$it['key']] ?? 0) + $it['value'];
            $n[$it['key']]  = ($n[$it['key']] ?? 0) + 1;
        }
        arsort($by);
        $rows = [];
        foreach (array_slice($by, 0, $limit, true) as $k => $v) {
            $rows[] = ['title' => (string) $k, 'meta' => $format($v) . ($count ? ' · ' . $count($n[$k]) : ''), 'url' => ''];
        }
        return [$rows, count($by)];
    }

    private static function run_time(array $spec) {
        global $wpdb;
        $p     = $wpdb->prefix . TIMEGROW_PREFIX;
        $day   = 'COALESCE(e.date, e.clock_in_date)';
        $where = ["{$day} >= %s", "{$day} < %s", 'e.status = 1']; // inactive entries are not counted
        $args  = [$spec['from'] . ' 00:00:00', date('Y-m-d', strtotime($spec['to'] . ' +1 day')) . ' 00:00:00'];
        $f     = $spec['filters'];
        if (!empty($f['member_id']))  { $where[] = 'e.member_id = %d';  $args[] = $f['member_id']; }
        if (!empty($f['client_id']))  { $where[] = 'p.client_id = %d';  $args[] = $f['client_id']; }
        if (!empty($f['project_id'])) { $where[] = 'e.project_id = %d'; $args[] = $f['project_id']; }
        if (isset($f['billable']))    { $where[] = 'e.billable = %d';   $args[] = $f['billable']; }
        if (isset($f['billed']))      { $where[] = 'e.billed = %d';     $args[] = $f['billed']; }
        $rows = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, p.name AS project_name, p.client_id, m.name AS member_name
             FROM {$p}time_entry_tracker e JOIN {$p}project_tracker p ON p.ID = e.project_id
             LEFT JOIN {$p}team_member_tracker m ON m.ID = e.member_id
             WHERE " . implode(' AND ', $where) . " ORDER BY {$day} DESC, e.ID DESC", $args));

        $items = [];
        $total = 0.0;
        foreach ($rows as $e) {
            $h = TimeGrowWooOrderCreator::entry_hours($e);
            if ($h === null) continue; // still clocked in
            $total += $h;
            $key = [
                'client'   => self::client_name($e->client_id),
                'project'  => $e->project_name . ' (' . self::client_name($e->client_id) . ')',
                'person'   => $e->member_name ?: '?',
                'month'    => mysql2date('F Y', substr((string) ($e->date ?: $e->clock_in_date), 0, 10)),
                'billable' => $e->billable ? __('Billable', 'timegrow') : __('Not billable', 'timegrow'),
                'none'     => '',
            ][$spec['group_by']];
            $items[] = ['key' => $key, 'value' => $spec['metric'] === 'count' ? 1 : $h, 'entry' => $e, 'hours' => $h];
        }
        $when = self::when($spec);
        if (!$items) {
            return self::reply(sprintf(__('No time entries found %s.', 'timegrow'), $when));
        }
        if ($spec['metric'] === 'list') {
            $list = array_map(function ($it) {
                $e = $it['entry'];
                return ['title' => $e->project_name . ' (' . self::client_name($e->client_id) . ')',
                        'meta' => TimeGrowNexusAssistant::hours_text($it['hours']) . ' · ' . mysql2date(get_option('date_format'), $e->date ?: $e->clock_in_date) . ($e->member_name ? ' · ' . $e->member_name : '') . ($e->description ? ' · ' . wp_trim_words(wp_strip_all_tags($e->description), 8) : ''),
                        'url' => ''];
            }, array_slice($items, 0, $spec['limit']));
            return self::reply(sprintf(_n('%1$d time entry %2$s, %3$s in total:', '%1$d time entries %2$s, %3$s in total:', count($items), 'timegrow'), count($items), $when, TimeGrowNexusAssistant::hours_text($total)),
                sprintf('I found %d %s %s, %s in total.', count($items), count($items) === 1 ? 'entry' : 'entries', $when, TimeGrowNexusAssistant::hours_spoken($total)), $list);
        }
        $fmt = $spec['metric'] === 'count' ? function ($v) { return sprintf(_n('%d entry', '%d entries', (int) $v, 'timegrow'), (int) $v); } : [TimeGrowNexusAssistant::class, 'hours_text'];
        $head = $spec['metric'] === 'count'
            ? sprintf(_n('%1$d time entry %2$s', '%1$d time entries %2$s', count($items), 'timegrow'), count($items), $when)
            : sprintf(__('%1$s logged %2$s', 'timegrow'), TimeGrowNexusAssistant::hours_text($total), $when);
        if ($spec['group_by'] === 'none') {
            return self::reply($head . '.');
        }
        [$list, $groups] = self::grouped($items, $spec['limit'], $fmt);
        return self::reply($head . ', ' . sprintf(_n('%1$d %2$s:', '%1$d %2$ss:', $groups, 'timegrow'), $groups, $spec['group_by']), $head . '.', $list);
    }

    private static function run_projects(array $spec, array $candidates, $owner) {
        global $wpdb;
        $table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $f     = $spec['filters'];
        $rows  = (array) $wpdb->get_results("SELECT ID, name, client_id, status FROM {$table} ORDER BY name");
        $mine  = array_map('intval', array_column($candidates, 'id'));
        $rows  = array_values(array_filter($rows, function ($p) use ($f, $owner, $mine) {
            if (!$owner && !in_array((int) $p->ID, $mine, true)) return false; // team members: their projects only
            if (!empty($f['client_id']) && (int) $p->client_id !== (int) $f['client_id']) return false;
            if (isset($f['active']) && ((int) $p->status === 1) !== (bool) $f['active']) return false;
            return true;
        }));
        if (!$rows) {
            return self::reply(__('No projects found.', 'timegrow'));
        }
        if ($spec['group_by'] === 'client' && $spec['metric'] !== 'list') {
            [$list, $groups] = self::grouped(array_map(function ($p) { return ['key' => self::client_name($p->client_id), 'value' => 1]; }, $rows), $spec['limit'],
                function ($v) { return sprintf(_n('%d project', '%d projects', (int) $v, 'timegrow'), (int) $v); });
            return self::reply(sprintf(__('%1$d projects across %2$d clients:', 'timegrow'), count($rows), $groups), '', $list);
        }
        $list = array_map(function ($p) use ($owner) {
            return ['title' => $p->name, 'meta' => self::client_name($p->client_id) . ((int) $p->status === 1 ? '' : ' · ' . __('inactive', 'timegrow')),
                    'url' => $owner ? admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-project-edit&id=' . (int) $p->ID) : ''];
        }, array_slice($rows, 0, $spec['limit']));
        return self::reply(sprintf(_n('%d project:', '%d projects:', count($rows), 'timegrow'), count($rows)), sprintf('I found %d %s.', count($rows), count($rows) === 1 ? 'project' : 'projects'), $spec['metric'] === 'count' ? [] : $list);
    }

    private static function run_clients(array $spec, array $candidates, $owner) {
        $clients = $owner ? array_map(function ($u) { return ['id' => (int) $u->ID, 'name' => $u->display_name]; }, (array) (new TimeGrowClientModel())->select())
                          : TimeGrowNexusAssistant::my_clients($candidates);
        if (!$clients) {
            return self::reply(__('No clients found.', 'timegrow'));
        }
        $list = array_map(function ($c) use ($owner) {
            return ['title' => $c['name'], 'meta' => '', 'url' => $owner ? admin_url('user-edit.php?user_id=' . (int) $c['id']) : ''];
        }, array_slice($clients, 0, $spec['limit']));
        return self::reply(sprintf(_n('%d client:', '%d clients:', count($clients), 'timegrow'), count($clients)), sprintf('There are %d clients.', count($clients)), $spec['metric'] === 'count' ? [] : $list);
    }

    private static function run_expenses(array $spec) {
        global $wpdb;
        $t     = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
        $where = ['expense_date BETWEEN %s AND %s'];
        $args  = [$spec['from'], $spec['to']];
        $f     = $spec['filters'];
        if (!empty($f['category_id'])) { $where[] = 'expense_category_id = %d'; $args[] = $f['category_id']; }
        if (!empty($f['project_id']))  { $where[] = "assigned_to = 'project' AND assigned_to_id = %d"; $args[] = $f['project_id']; }
        if (!empty($f['client_id']))   { $where[] = "assigned_to = 'client' AND assigned_to_id = %d"; $args[] = $f['client_id']; }
        if (isset($f['min_amount']))   { $where[] = 'amount >= %f'; $args[] = $f['min_amount']; }
        if (isset($f['max_amount']))   { $where[] = 'amount <= %f'; $args[] = $f['max_amount']; }
        $rows = (array) $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t} WHERE " . implode(' AND ', $where) . ' ORDER BY expense_date DESC, ID DESC', $args));
        $when = self::when($spec);
        if (!$rows) {
            return self::reply(sprintf(__('No expenses found %s.', 'timegrow'), $when));
        }
        $cats = [];
        foreach (TimeGrowAIReceiptAnalyzer::active_categories() as $c) {
            $cats[$c['id']] = $c['name'];
        }
        $total = array_sum(array_map(function ($r) { return (float) $r->amount; }, $rows));
        $head  = sprintf(_n('%1$d expense %2$s, %3$s in total', '%1$d expenses %2$s, %3$s in total', count($rows), 'timegrow'), count($rows), $when, TimeGrowNexusAssistant::money($total));
        if ($spec['metric'] === 'list' || $spec['group_by'] === 'none') {
            $list = $spec['metric'] === 'count' ? [] : array_map(function ($r) use ($cats) {
                return ['title' => $r->expense_name, 'meta' => TimeGrowNexusAssistant::money($r->amount) . ' · ' . mysql2date(get_option('date_format'), $r->expense_date) . (isset($cats[(int) $r->expense_category_id]) ? ' · ' . $cats[(int) $r->expense_category_id] : ''),
                        'url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expense-edit&id=' . (int) $r->ID)];
            }, array_slice($rows, 0, $spec['limit']));
            return self::reply($head . ($list ? ':' : '.'), $head . '.', $list);
        }
        $items = array_map(function ($r) use ($spec, $cats) {
            $key = [
                'category' => $cats[(int) $r->expense_category_id] ?? __('No category', 'timegrow'),
                'client'   => $r->assigned_to === 'client' ? self::client_name($r->assigned_to_id) : __('Not assigned to a client', 'timegrow'),
                'project'  => $r->assigned_to === 'project' ? (string) $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare("SELECT name FROM {$GLOBALS['wpdb']->prefix}" . TIMEGROW_PREFIX . 'project_tracker WHERE ID = %d', (int) $r->assigned_to_id)) : __('Not assigned to a project', 'timegrow'),
                'month'    => mysql2date('F Y', $r->expense_date),
            ][$spec['group_by']];
            return ['key' => $key, 'value' => $spec['metric'] === 'count' ? 1 : (float) $r->amount];
        }, $rows);
        $exp_n = function ($v) { return sprintf(_n('%d expense', '%d expenses', (int) $v, 'timegrow'), (int) $v); };
        [$list] = self::grouped($items, $spec['limit'], $spec['metric'] === 'count' ? $exp_n : [TimeGrowNexusAssistant::class, 'money'], $spec['metric'] === 'count' ? null : $exp_n);
        return self::reply($head . ', ' . sprintf(__('by %s:', 'timegrow'), $spec['group_by']), $head . '.', $list);
    }

    private static function run_invoices(array $spec) {
        if (!function_exists('wc_get_orders')) {
            return self::reply(__('WooCommerce is not active.', 'timegrow'));
        }
        $f      = $spec['filters'];
        $status = $f['status'] ?? 'billed'; // no status said: invoices sent to the client, as billed amount counts them
        $list_s = isset(self::STATUS_GROUPS[$status]) ? self::STATUS_GROUPS[$status] : [$status];
        if (!$list_s) {
            $list_s = array_values(array_diff(array_map(function ($s) { return preg_replace('/^wc-/', '', $s); }, array_keys(wc_get_order_statuses())), ['cancelled', 'refunded', 'failed', 'checkout-draft']));
        }
        $args = ['type' => 'shop_order', 'status' => $list_s, 'limit' => -1, 'date_created' => $spec['from'] . '...' . $spec['to'] . ' 23:59:59', 'orderby' => 'date', 'order' => 'DESC']; // orders only, not refunds
        if (!empty($f['client_id'])) {
            $args['customer_id'] = (int) $f['client_id'];
        }
        $orders = array_values(array_filter(wc_get_orders($args), function ($o) use ($f) {
            $t = (float) $o->get_total();
            return (!isset($f['min_amount']) || $t >= $f['min_amount']) && (!isset($f['max_amount']) || $t <= $f['max_amount']);
        }));
        $when = self::when($spec);
        $what = $status === 'all' ? __('invoices', 'timegrow') : ($status === 'billed' ? __('invoices sent to clients', 'timegrow') : sprintf(__('%s invoices', 'timegrow'), $status));
        if (!$orders) {
            return self::reply(sprintf(__('No %1$s found %2$s.', 'timegrow'), $what, $when));
        }
        $total = array_sum(array_map(function ($o) { return (float) $o->get_total(); }, $orders));
        $head  = sprintf(__('%1$d %2$s %3$s, %4$s in total', 'timegrow'), count($orders), $what, $when, TimeGrowNexusAssistant::money($total));
        if ($spec['metric'] === 'list' || $spec['group_by'] === 'none') {
            $list = $spec['metric'] === 'count' ? [] : array_map(function ($o) {
                return ['title' => '#' . $o->get_order_number() . ' · ' . ($o->get_customer_id() ? self::client_name($o->get_customer_id()) : $o->get_formatted_billing_full_name()),
                        'meta' => TimeGrowNexusAssistant::money($o->get_total()) . ' · ' . wc_get_order_status_name($o->get_status()) . ' · ' . $o->get_date_created()->date_i18n(get_option('date_format')),
                        'url' => $o->get_edit_order_url()];
            }, array_slice($orders, 0, $spec['limit']));
            return self::reply($head . ($list ? ':' : '.'), $head . '.', $list);
        }
        $items = array_map(function ($o) use ($spec) {
            $key = [
                'client' => $o->get_customer_id() ? self::client_name($o->get_customer_id()) : ($o->get_formatted_billing_full_name() ?: __('No client', 'timegrow')),
                'status' => wc_get_order_status_name($o->get_status()),
                'month'  => $o->get_date_created()->date_i18n('F Y'),
            ][$spec['group_by']];
            return ['key' => $key, 'value' => $spec['metric'] === 'count' ? 1 : (float) $o->get_total()];
        }, $orders);
        $inv_n = function ($v) { return sprintf(_n('%d invoice', '%d invoices', (int) $v, 'timegrow'), (int) $v); };
        [$list] = self::grouped($items, $spec['limit'], $spec['metric'] === 'count' ? $inv_n : [TimeGrowNexusAssistant::class, 'money'], $spec['metric'] === 'count' ? null : $inv_n);
        return self::reply($head . ', ' . sprintf(__('by %s:', 'timegrow'), $spec['group_by']), $head . '.', $list);
    }
}
