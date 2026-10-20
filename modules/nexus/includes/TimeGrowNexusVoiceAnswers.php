<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Answers to the voice assistant's questions, from TimeGrow's own data (read-only; runs as the
 * logged-in person). Answers are written from templates: the data found is never sent to the AI,
 * apart from explain(), which goes through the privacy layer.
 *
 * Time entries and invoices / payments / refunds: the last AI_DATA_MONTHS months only; older
 * questions point to the reports.
 */
class TimeGrowNexusVoiceAnswers {

    const UNPAID_STATUSES = ['invoice_sent', 'inv_sent2paypal', 'inv_draft2paypal', 'partial_paid', 'to_be_mailed'];
    // "Billed" = invoices sent to the client (not drafts, pending, cancelled or refunded)
    const BILLED_STATUSES = ['invoice_sent', 'inv_sent2paypal', 'to_be_mailed', 'partial_paid', 'invoice_paid'];
    // Time entries and invoices / payments / refunds the assistant looks at: the last 3 months only,
    // so answers (which go to the AI with later messages) stay small
    const AI_DATA_MONTHS = 3;
    /** The period a question uses when none is said, so the remembered question matches the answer. */
    const DEFAULT_PERIODS = ['hours' => 'this_week', 'expenses' => 'this_month', 'billed_amount' => 'this_year'];
    /** Set while answering: the period was shortened to the last AI_DATA_MONTHS months (a note is added). */
    private static $clipped = false;
    /** Which reports the note links to: time_me, time_team, unbilled or invoices. */
    private static $clip_topic = 'time_me';

    /** The earliest date the assistant reads time and invoice data from. Pure. */
    public static function data_floor($today) {
        return date('Y-m-d', strtotime($today . ' -' . self::AI_DATA_MONTHS . ' months'));
    }

    /** Note added to an answer whose period was shortened to the last 3 months. */
    public static function window_note() {
        return sprintf(__('(The assistant only looks at the last %d months.)', 'timegrow'), self::AI_DATA_MONTHS);
    }

    /**
     * The reports that cover older data for a topic, as answer lines with links. Topics: "invoices"
     * (billing, payments, refunds), "time_team", "time_me". Only reports the person's role can open.
     */
    public static function reports_for($topic) {
        $owner = TimeGrowNexusAssistant::is_owner();
        $all = [
            'invoices'  => $owner ? ['client_activity_summary' => __('Client Activity Report', 'timegrow'), 'yearly_tax_report' => __('Yearly Tax Report', 'timegrow'), 'monthly_profit_loss' => __('Monthly Profit & Loss Report', 'timegrow')] : [],
            'time_team' => $owner ? ['team_summary_hours' => __('Team Hours Summary', 'timegrow'), 'time_entry_audit_log' => __('Time Entry Audit Log', 'timegrow')] : [],
            'unbilled'  => $owner ? ['unbilled_time' => __('Unbilled Time (WIP)', 'timegrow')] : [],
            'time_me'   => ['my_hours_by_project' => __('My Hours by Project', 'timegrow'), 'my_time_entries_detailed' => __('My Detailed Time Log', 'timegrow')],
        ];
        $rows = [];
        foreach ($all[$topic] ?? [] as $slug => $title) {
            $rows[] = ['title' => $title, 'meta' => __('Report: any period', 'timegrow'), 'url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-a-report&report_slug=' . $slug)];
        }
        return $rows;
    }

    /** The reply when everything asked for is older than the last 3 months: say so, and point to the reports. */
    public static function out_of_window_reply($topic) {
        $reports = self::reports_for($topic);
        $text = sprintf(__('I can only look at the last %d months, so I can\'t answer that.', 'timegrow'), self::AI_DATA_MONTHS)
            . ($reports ? ' ' . __('Use these reports for older data:', 'timegrow') : '');
        $spoken = sprintf('I can only look at the last %d months, so I can\'t answer that.', self::AI_DATA_MONTHS) . ($reports ? ' The reports listed below cover any period.' : '');
        return ['text' => $text, 'spoken' => $spoken, 'rows' => $reports];
    }

    /** The note and report links added when a period was shortened to the last 3 months. */
    public static function add_window_note(array $reply, $topic) {
        $reports = self::reports_for($topic);
        $reply['text']   .= ' ' . sprintf(__('(I can only look at the last %d months, so this covers %s to today.', 'timegrow'), self::AI_DATA_MONTHS, mysql2date(get_option('date_format'), self::data_floor(current_time('Y-m-d'))))
            . ($reports ? ' ' . __('For the full period, use the reports below.)', 'timegrow') : ')');
        $reply['spoken'] .= ' I can only look at the last ' . self::AI_DATA_MONTHS . ' months.' . ($reports ? ' The reports below cover the full period.' : '');
        $reply['rows']    = array_merge($reply['rows'], $reports);
        return $reply;
    }

    /**
     * Run an allowed question and write the answer.
     *
     * @return array { text, spoken, rows: [{ title, meta, url }] }
     */
    public static function answer(array $question, array $candidates) {
        self::$clipped = false;
        self::$clip_topic = ($question['who'] ?? 'me') === 'team' ? 'time_team' : 'time_me';
        $reply = self::answer_data($question, $candidates);
        if (self::$clipped) {
            $reply = self::add_window_note($reply, self::$clip_topic);
        }
        if (!empty($question['explain']) && !empty($reply['rows']) || (!empty($question['explain']) && $question['name'] !== 'lookup')) {
            $reply = self::explain((string) $question['explain'], $reply);
        }
        return $reply;
    }

    /**
     * The question as it is answered: a missing period filled with the one used. The chat keeps this
     * for follow-ups ("and last week?", "I mean the whole team"), so they build on a complete question. Pure.
     */
    public static function effective_question(array $q) {
        if (empty($q['period']) && isset(self::DEFAULT_PERIODS[$q['name'] ?? ''])) {
            $q['period'] = self::DEFAULT_PERIODS[$q['name']];
        }
        return $q;
    }

    /**
     * A short written explanation of an answer, by the AI. The answer's text and rows go through the
     * privacy layer first (names → stand-ins) and come back with the real names. On any problem the
     * plain answer is kept.
     */
    public static function explain($ask, array $reply) {
        $privacy = class_exists('TimeGrowNexusVoice') ? TimeGrowNexusVoice::privacy() : null;
        if (!$privacy || !class_exists('TimeGrowAI') || !TimeGrowAI::is_configured('voice')) {
            return $reply;
        }
        $data = ['answer' => $reply['text'], 'rows' => array_map(function ($r) { return ['title' => $r['title'], 'detail' => $r['meta']]; }, $reply['rows'])];
        $answer = TimeGrowAI::request([
            'feature'    => 'voice',
            'system'     => "Task: answer the person's question in 1 to 3 short sentences, using only the results given. Names are stand-ins like [Client 31] or [Person 14]: copy them exactly, never guess real names. No numbers that are not in the results, and never move a number from one line or from the overall total to another line. Return JSON: {\"answer\": \"...\"}.",
            'prompt'     => $privacy->mask("The person asked:\n" . TimeGrowAI::untrusted('question', $ask) . "\n\nResults from TimeGrow:\n" . TimeGrowAI::untrusted('results', $data) . "\n\nReturn the JSON object."),
            'json'       => true,
            'max_tokens' => 220,
            'timeout'    => 20,
        ]);
        if (is_wp_error($answer)) {
            return $reply;
        }
        $json = TimeGrowAI::json_from_text($answer['text']);
        $text = is_string($json['answer'] ?? null) ? trim(wp_strip_all_tags($json['answer'])) : '';
        if ($text === '') {
            return $reply;
        }
        $text = $privacy->unmask($text);
        return ['text' => $text, 'spoken' => $text, 'rows' => $reply['rows']];
    }

    /** The answer from TimeGrow's own data (templates). */
    private static function answer_data(array $question, array $candidates) {
        $name = $question['name'];
        if (!in_array($name, TimeGrowNexusAssistant::allowed_questions(), true)) {
            return self::reply(TimeGrowNexusAssistant::denied_text($name));
        }
        if ($name === 'help') {
            return TimeGrowNexusHelp::answer((string) ($question['help'] ?? ''), TimeGrowNexusAssistant::is_owner(), TimeGrowNexusAssistant::allowed_questions(), TimeGrowNexusAssistant::allowed_actions(),
                current_user_can('access_nexus_clock') || TimeGrowTimeEntryRules::is_admin());
        }
        $today  = current_time('Y-m-d');
        $member = TimeGrowTimeEntryRules::member_id(0);
        $needs_member = in_array($name, ['last_entry', 'recent_entries', 'clock_status'], true)
            || ($name === 'hours' && ($question['who'] ?? 'me') !== 'team');
        if ($needs_member && !$member) {
            return self::reply(__('Your user is not set up as a team member, so there is no time recorded for you.', 'timegrow'));
        }

        switch ($name) {
            case 'lookup':
                $owner = TimeGrowNexusAssistant::is_owner();
                $ok = [
                    'clients'    => $owner ? array_column(TimeGrowNexusAssistant::all_clients(), 'id') : array_column(TimeGrowNexusAssistant::my_clients($candidates), 'id'),
                    'projects'   => $owner ? array_map('intval', (array) $GLOBALS['wpdb']->get_col("SELECT ID FROM {$GLOBALS['wpdb']->prefix}" . TIMEGROW_PREFIX . 'project_tracker')) : array_map('intval', array_column($candidates, 'id')),
                    'members'    => $owner ? array_column(TimeGrowNexusAssistant::team_members(), 'id') : ($member ? [$member] : []),
                    'categories' => class_exists('TimeGrowAIReceiptAnalyzer') ? array_column(TimeGrowAIReceiptAnalyzer::active_categories(), 'id') : [],
                    'statuses'   => function_exists('wc_get_order_statuses') ? array_map(function ($x) { return preg_replace('/^wc-/', '', $x); }, array_keys(wc_get_order_statuses())) : [],
                    'me'         => (int) $member,
                ];
                $spec = TimeGrowNexusLookup::spec($question['lookup'] ?? null, $owner, $ok, $today);
                if ($spec === null) {
                    return self::reply(__('I couldn\'t work out what to look up. Try naming what you want, for example "hours by client this year".', 'timegrow'));
                }
                if (!empty($spec['denied'])) {
                    return self::reply(TimeGrowNexusAssistant::denied_text('lookup'));
                }
                return TimeGrowNexusLookup::run($spec, $candidates, $owner);

            case 'last_entry':
                $filters = array_filter(['client_id' => $question['client_id'] ?? null, 'project_id' => $question['project_id'] ?? null]);
                $rows = self::entries($member, '1970-01-01', $today, $filters, 1);
                if (!$rows) return self::reply($filters ? __('You have no entries for that yet.', 'timegrow') : __('You have not recorded any time yet.', 'timegrow'));
                $e = $rows[0];
                $h = TimeGrowWooOrderCreator::entry_hours($e);
                $when = TimeGrowNexusVoiceFormat::date_words(self::entry_day($e), $today);
                $text = sprintf(__('Your last entry: %1$s on %2$s (%3$s), %4$s.', 'timegrow'), $h === null ? __('still clocked in', 'timegrow') : TimeGrowNexusVoiceFormat::hours_text($h), $e->project_name, self::client_name($e->client_id), $when);
                $spoken = sprintf('Your last entry was %s on %s, %s.', $h === null ? 'a clock-in that is still open' : TimeGrowNexusVoiceFormat::hours_spoken($h), $e->project_name, $when);
                return self::reply($text, $spoken, [self::entry_row($e, $today)]);

            case 'recent_entries':
                [$from, $to] = !empty($question['period']) ? TimeGrowNexusVoiceFormat::period_range($question['period'], $today) : ['1970-01-01', $today];
                if ($to < self::data_floor($today)) {
                    return self::out_of_window_reply('time_me');
                }
                $filters = array_filter(['client_id' => $question['client_id'] ?? null, 'project_id' => $question['project_id'] ?? null]);
                $rows = self::entries($member, $from, $to, $filters, 5);
                $for  = !empty($filters['project_id']) ? ' ' . sprintf(__('on %s', 'timegrow'), self::project_name($filters['project_id'])) : (!empty($filters['client_id']) ? ' ' . sprintf(__('for %s', 'timegrow'), self::client_name($filters['client_id'])) : '');
                if (!$rows) return self::reply(sprintf(__('No entries found%s.', 'timegrow'), $for));
                return self::reply(sprintf(_n('Your last entry%2$s:', 'Your last %1$d entries%2$s:', count($rows), 'timegrow'), count($rows), $for),
                    sprintf('Here %s your last %s%s.', count($rows) === 1 ? 'is' : 'are', count($rows) === 1 ? 'entry' : count($rows) . ' entries', $for),
                    array_map(function ($e) use ($today) { return self::entry_row($e, $today); }, $rows));

            case 'hours':
                // The whole team's hours are for administrators; a team member always gets their own
                $own_only = ($question['who'] ?? 'me') === 'team' && !TimeGrowNexusAssistant::is_owner();
                $team     = ($question['who'] ?? 'me') === 'team' && !$own_only;
                if ($own_only && !$member) {
                    return self::reply(__('Your user is not set up as a team member, so there is no time recorded for you.', 'timegrow'));
                }
                $period = $question['period'] ?: 'this_week';
                [$from, $to] = TimeGrowNexusVoiceFormat::period_range($period, $today);
                if ($to < self::data_floor($today)) {
                    return self::out_of_window_reply($team ? 'time_team' : 'time_me');
                }
                $rows   = self::entries($team ? 0 : $member, $from, $to, array_filter(['client_id' => $question['client_id'] ?? null, 'project_id' => $question['project_id'] ?? null]));
                $total  = 0.0;
                $by     = [];
                foreach ($rows as $e) {
                    $h = TimeGrowWooOrderCreator::entry_hours($e);
                    if ($h === null) continue;
                    $total += $h;
                    $key = $team ? ($e->member_name ?: '?') : $e->project_name . ' (' . self::client_name($e->client_id) . ')';
                    $by[$key] = ($by[$key] ?? 0) + $h;
                }
                // In team answers the person always sees themselves, even with no hours
                $me = '';
                if ($team && $member) {
                    $row = (new TimeGrowTeamMemberModel())->get_by_user_id(get_current_user_id());
                    $me  = $row ? (string) $row->name : '';
                    if ($me !== '' && !isset($by[$me])) {
                        $by[$me] = 0.0;
                    }
                }
                arsort($by);
                $who      = $team ? __('The team', 'timegrow') : __('You', 'timegrow');
                $filtered = !empty($question['project_id']) || !empty($question['client_id']);
                $scope    = !empty($question['project_id']) ? ' ' . sprintf(__('on the project %s', 'timegrow'), self::project_name($question['project_id'])) : (!empty($question['client_id']) ? ' ' . sprintf(__('on the client %s', 'timegrow'), self::client_name($question['client_id'])) : '');
                $text     = sprintf(__('%1$s logged %2$s%3$s %4$s.', 'timegrow'), $who, $total > 0 ? TimeGrowNexusVoiceFormat::hours_text($total) : __('no time', 'timegrow'), $scope, TimeGrowNexusVoiceFormat::period_words($period))
                    . ($filtered ? ' ' . __('Say "all clients" to see everything.', 'timegrow') : '');
                $spoken   = sprintf('%s logged %s%s %s.', $team ? 'The team' : 'You', $total > 0 ? TimeGrowNexusVoiceFormat::hours_spoken($total) : 'no time', $scope, TimeGrowNexusVoiceFormat::period_words($period));
                if ($own_only) {
                    $text   = __('You can only see your own time.', 'timegrow') . ' ' . $text;
                    $spoken = 'You can only see your own time. ' . $spoken;
                }
                $list     = [];
                $shown    = array_slice($by, 0, 6, true);
                if ($me !== '' && !isset($shown[$me])) {
                    $shown[$me] = $by[$me];
                }
                foreach ($shown as $label => $h) {
                    $list[] = ['title' => $label === $me ? sprintf(__('You (%s)', 'timegrow'), $label) : $label, 'meta' => TimeGrowNexusVoiceFormat::hours_text($h), 'url' => ''];
                }
                return self::reply($text, $spoken, $list);

            case 'clients':
                $clients = TimeGrowNexusAssistant::my_clients($candidates);
                if (!$clients) return self::reply(__('You have no clients with projects you can use yet.', 'timegrow'));
                $counts = array_count_values(array_map(function ($c) { return (int) $c['client_id']; }, $candidates));
                return self::reply(sprintf(_n('You work with %d client:', 'You work with %d clients:', count($clients), 'timegrow'), count($clients)),
                    sprintf('You work with %d %s.', count($clients), count($clients) === 1 ? 'client' : 'clients'),
                    array_map(function ($c) use ($counts) {
                        return ['title' => $c['name'], 'meta' => sprintf(_n('%d project', '%d projects', $counts[$c['id']] ?? 0, 'timegrow'), $counts[$c['id']] ?? 0), 'url' => ''];
                    }, $clients));

            case 'projects_for_client':
                if (empty($question['client_id'])) {
                    $mine_c = TimeGrowNexusAssistant::my_clients($candidates);
                    return self::reply(!empty($question['client_name'])
                        ? sprintf(__('I couldn\'t find a client called %s among yours. Which one do you mean?', 'timegrow'), $question['client_name'])
                        : __('Which client?', 'timegrow'), '',
                        array_map(function ($c) { return ['title' => $c['name'], 'meta' => '', 'url' => '']; }, array_slice($mine_c, 0, 12)));
                }
                $mine = array_values(array_filter($candidates, function ($c) use ($question) { return (int) $c['client_id'] === (int) $question['client_id']; }));
                $name_c = self::client_name($question['client_id']);
                if (!$mine) return self::reply(sprintf(__('%s has no projects you can use.', 'timegrow'), $name_c));
                return self::reply(sprintf(_n('%1$s has %2$d project:', '%1$s has %2$d projects:', count($mine), 'timegrow'), $name_c, count($mine)),
                    sprintf('%s has %d %s: %s.', $name_c, count($mine), count($mine) === 1 ? 'project' : 'projects', implode(', ', array_slice(array_column($mine, 'name'), 0, 5))),
                    array_map(function ($c) { return ['title' => $c['name'], 'meta' => $c['client_name'], 'url' => self::project_url($c['id'])]; }, $mine));

            case 'latest_projects':
                $ids = array_map('intval', array_column($candidates, 'id'));
                $rows = self::latest_projects($ids, 5);
                if (!$rows) return self::reply(__('There are no projects you can use yet.', 'timegrow'));
                return self::reply(__('The newest projects:', 'timegrow') . (!empty($question['period']) ? ' ' . __('(newly created projects; to see the projects you worked on, ask "what did I work on this week")', 'timegrow') : ''),
                    sprintf('The newest is %s for %s.', $rows[0]->name, self::client_name($rows[0]->client_id)),
                    array_map(function ($p) { return ['title' => $p->name, 'meta' => self::client_name($p->client_id) . ($p->created_at ? ' · ' . mysql2date(get_option('date_format'), $p->created_at) : ''), 'url' => self::project_url($p->ID)]; }, $rows));

            case 'clock_status':
                $s = (new TimeGrowTeamMemberModel())->get_team_member_clock_status($member);
                if (($s['status'] ?? '') === 'clocked_in') {
                    $since = date_i18n(get_option('time_format'), (int) $s['clockInTimestamp']);
                    return self::reply(sprintf(__('You are clocked in on %1$s since %2$s.', 'timegrow'), $s['cloked_project'], $since),
                        sprintf('Yes. You have been clocked in on %s since %s.', $s['cloked_project'], $since));
                }
                return self::reply(__('You are not clocked in.', 'timegrow'), 'No, you are not clocked in.');

            case 'unbilled_hours':
                self::$clip_topic = 'unbilled';
                $rows = self::entries(0, '1970-01-01', $today, ['unbilled' => true]);
                $by = [];
                $total = 0.0;
                foreach ($rows as $e) {
                    $h = TimeGrowWooOrderCreator::entry_hours($e);
                    if ($h === null) continue;
                    $by[self::client_name($e->client_id)] = ($by[self::client_name($e->client_id)] ?? 0) + $h;
                    $total += $h;
                }
                if (!$by) return self::reply(__('There are no unbilled hours.', 'timegrow'), 'There are no unbilled hours. Everything has been invoiced.');
                arsort($by);
                return self::reply(sprintf(__('%1$s unbilled across %2$d clients:', 'timegrow'), TimeGrowNexusVoiceFormat::hours_text($total), count($by)),
                    sprintf('There are %s unbilled across %d %s. The most is for %s.', TimeGrowNexusVoiceFormat::hours_spoken($total), count($by), count($by) === 1 ? 'client' : 'clients', array_key_first($by)),
                    array_map(function ($name, $h) { return ['title' => $name, 'meta' => TimeGrowNexusVoiceFormat::hours_text($h), 'url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-process-time')]; }, array_keys($by), $by));

            case 'billed_amount':
                $period = $question['period'] ?: 'this_year';
                [$from, $to] = TimeGrowNexusVoiceFormat::period_range($period, $today);
                self::$clip_topic = 'invoices';
                if ($to < self::data_floor($today)) {
                    return self::out_of_window_reply('invoices');
                }
                if ($from < self::data_floor($today)) {
                    $from = self::data_floor($today);
                    self::$clipped = true;
                }
                $args = ['type' => 'shop_order', 'status' => self::BILLED_STATUSES, 'limit' => -1, 'date_created' => $from . '...' . $to . ' 23:59:59'];
                if (!empty($question['client_id'])) {
                    $args['customer_id'] = (int) $question['client_id'];
                }
                $orders  = wc_get_orders($args);
                $for     = !empty($question['client_id']) ? ' ' . sprintf(__('to the client %s', 'timegrow'), self::client_name($question['client_id'])) : '';
                if (!$orders) {
                    return self::reply(sprintf(__('Nothing was billed%1$s %2$s.', 'timegrow'), $for, TimeGrowNexusVoiceFormat::period_words($period)));
                }
                $billed = $paid = 0.0;
                $by     = [];
                foreach ($orders as $o) {
                    $cid   = (int) $o->get_customer_id();
                    $key   = $cid ?: ('guest-' . $o->get_id());
                    $name  = $cid ? self::client_name($cid) : ($o->get_formatted_billing_full_name() ?: __('No client', 'timegrow'));
                    $total = (float) $o->get_total();
                    $is_paid = $o->get_status() === 'invoice_paid';
                    $by[$key] = $by[$key] ?? ['name' => $name, 'id' => $cid, 'billed' => 0.0, 'paid' => 0.0, 'n' => 0, 'partial' => 0];
                    $by[$key]['billed'] += $total;
                    $by[$key]['paid']   += $is_paid ? $total : 0;
                    $by[$key]['n']++;
                    $by[$key]['partial'] += $o->get_status() === 'partial_paid' ? 1 : 0;
                    $billed += $total;
                    $paid   += $is_paid ? $total : 0;
                }
                uasort($by, function ($a, $b) { return $b['billed'] <=> $a['billed']; });
                $text = sprintf(_n('Billed %1$s%2$s %3$s across %4$d invoice; %5$s paid.', 'Billed %1$s%2$s %3$s across %4$d invoices; %5$s paid.', count($orders), 'timegrow'),
                    TimeGrowNexusVoiceFormat::money($billed), $for, TimeGrowNexusVoiceFormat::period_words($period), count($orders), TimeGrowNexusVoiceFormat::money($paid));
                $spoken = sprintf('You billed %s%s %s, and %s of it is paid.', TimeGrowNexusVoiceFormat::money($billed), $for, TimeGrowNexusVoiceFormat::period_words($period), TimeGrowNexusVoiceFormat::money($paid));
                $list = [];
                foreach ($by as $c) {
                    $meta = sprintf(_n('%1$s billed · %2$s paid · %3$d invoice', '%1$s billed · %2$s paid · %3$d invoices', $c['n'], 'timegrow'), TimeGrowNexusVoiceFormat::money($c['billed']), TimeGrowNexusVoiceFormat::money($c['paid']), $c['n'])
                        . ($c['partial'] ? ' · ' . sprintf(_n('%d partly paid', '%d partly paid', $c['partial'], 'timegrow'), $c['partial']) : '');
                    $list[] = ['title' => $c['name'], 'meta' => $meta, 'url' => $c['id'] ? admin_url('admin.php?page=wc-orders&_customer_user=' . $c['id']) : ''];
                }
                return self::reply($text, $spoken, $list);

            case 'unpaid_invoices':
                $orders = wc_get_orders(['type' => 'shop_order', 'status' => self::UNPAID_STATUSES, 'limit' => 20, 'orderby' => 'date', 'order' => 'DESC', 'date_created' => '>=' . self::data_floor($today)]);
                self::$clipped = true;
                self::$clip_topic = 'invoices';
                if (!$orders) return self::reply(__('There are no unpaid invoices.', 'timegrow'), 'There are no unpaid invoices.');
                $total = 0.0;
                $list = [];
                foreach ($orders as $o) {
                    $total += (float) $o->get_total();
                    $list[] = ['title' => '#' . $o->get_order_number() . ' · ' . $o->get_formatted_billing_full_name(), 'meta' => TimeGrowNexusVoiceFormat::money($o->get_total()) . ' · ' . wc_get_order_status_name($o->get_status()), 'url' => $o->get_edit_order_url()];
                }
                return self::reply(sprintf(_n('%1$d unpaid invoice, %2$s in total:', '%1$d unpaid invoices, %2$s in total:', count($orders), 'timegrow'), count($orders), TimeGrowNexusVoiceFormat::money($total)),
                    sprintf('There %s %d unpaid %s, %s in total.', count($orders) === 1 ? 'is' : 'are', count($orders), count($orders) === 1 ? 'invoice' : 'invoices', TimeGrowNexusVoiceFormat::money($total)),
                    $list);

            case 'expenses':
                $period = $question['period'] ?: 'this_month';
                [$from, $to] = TimeGrowNexusVoiceFormat::period_range($period, $today);
                global $wpdb;
                $table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
                $rows = $wpdb->get_results($wpdb->prepare("SELECT ID, expense_name, amount, expense_date FROM {$table} WHERE status = 1 AND expense_date BETWEEN %s AND %s ORDER BY expense_date DESC, ID DESC", $from, $to));
                $total = array_sum(array_map(function ($r) { return (float) $r->amount; }, (array) $rows));
                if (!$rows) return self::reply(sprintf(__('No expenses %s.', 'timegrow'), TimeGrowNexusVoiceFormat::period_words($period)));
                return self::reply(sprintf(_n('%1$d expense %2$s, %3$s in total:', '%1$d expenses %2$s, %3$s in total:', count($rows), 'timegrow'), count($rows), TimeGrowNexusVoiceFormat::period_words($period), TimeGrowNexusVoiceFormat::money($total)),
                    sprintf('You have %d %s %s, %s in total.', count($rows), count($rows) === 1 ? 'expense' : 'expenses', TimeGrowNexusVoiceFormat::period_words($period), TimeGrowNexusVoiceFormat::money($total)),
                    array_map(function ($r) { return ['title' => $r->expense_name, 'meta' => TimeGrowNexusVoiceFormat::money($r->amount) . ' · ' . mysql2date(get_option('date_format'), $r->expense_date), 'url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expense-edit&id=' . (int) $r->ID)]; }, array_slice($rows, 0, 6)));
        }
        return self::reply(__('I can\'t answer that yet.', 'timegrow'));
    }

    private static function reply($text, $spoken = '', array $rows = []) {
        return ['text' => $text, 'spoken' => $spoken !== '' ? $spoken : $text, 'rows' => $rows];
    }

    /** Time entries in a date range, newest first. $member_id 0 = everyone (owners only reach this). */
    private static function entries($member_id, $from, $to, array $filters = [], $limit = 0) {
        global $wpdb;
        $p     = $wpdb->prefix . TIMEGROW_PREFIX;
        $day   = 'COALESCE(e.date, e.clock_in_date)';
        $where = ["{$day} >= %s", "{$day} < %s", 'e.status = 1']; // inactive entries are not counted
        $floor = self::data_floor(current_time('Y-m-d'));
        if ($from < $floor) {
            $from = $floor; // the last 3 months only
            self::$clipped = self::$clipped || $limit !== 1; // "your last entry" needs no note
        }
        $args  = [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];
        if ($member_id) { $where[] = 'e.member_id = %d'; $args[] = (int) $member_id; }
        if (!empty($filters['client_id'])) { $where[] = 'p.client_id = %d'; $args[] = (int) $filters['client_id']; }
        if (!empty($filters['project_id'])) { $where[] = 'e.project_id = %d'; $args[] = (int) $filters['project_id']; }
        if (!empty($filters['unbilled'])) { $where[] = 'e.billable = 1 AND e.billed = 0'; }
        $sql = "SELECT e.*, p.name AS project_name, p.client_id, m.name AS member_name
                FROM {$p}time_entry_tracker e
                JOIN {$p}project_tracker p ON p.ID = e.project_id
                LEFT JOIN {$p}team_member_tracker m ON m.ID = e.member_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY {$day} DESC, e.ID DESC" . ($limit ? ' LIMIT ' . (int) $limit : '');
        return (array) $wpdb->get_results($wpdb->prepare($sql, $args));
    }

    private static function latest_projects(array $ids, $limit) {
        if (!$ids) return [];
        global $wpdb;
        $table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $in    = implode(',', array_fill(0, count($ids), '%d'));
        return (array) $wpdb->get_results($wpdb->prepare("SELECT ID, name, client_id, created_at FROM {$table} WHERE ID IN ({$in}) ORDER BY created_at DESC, ID DESC LIMIT " . (int) $limit, $ids));
    }

    private static function entry_day($e) {
        return substr((string) ($e->date ?: $e->clock_in_date), 0, 10);
    }

    private static function entry_row($e, $today) {
        $h = TimeGrowWooOrderCreator::entry_hours($e);
        return [
            'title' => $e->project_name . ' (' . self::client_name($e->client_id) . ')',
            'meta'  => ($h === null ? __('clocked in', 'timegrow') : TimeGrowNexusVoiceFormat::hours_text($h)) . ' · ' . TimeGrowNexusVoiceFormat::date_words(self::entry_day($e), $today) . ($e->description ? ' · ' . wp_trim_words(wp_strip_all_tags($e->description), 10) : ''),
            'url'   => '',
        ];
    }

    public static function client_name($id) {
        $name = TimeGrowProjectPicker::client_name($id);
        return $name !== '' ? $name : __('no client', 'timegrow');
    }

    private static function project_name($id) {
        global $wpdb;
        return (string) $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}" . TIMEGROW_PREFIX . 'project_tracker WHERE ID = %d', (int) $id));
    }

    private static function project_url($id) {
        return TimeGrowNexusAssistant::is_owner() ? admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-project-edit&id=' . (int) $id) : '';
    }
}
