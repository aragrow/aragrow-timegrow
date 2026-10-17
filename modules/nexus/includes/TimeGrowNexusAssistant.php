<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * What the voice chat can do besides recording time: answer questions about TimeGrow data and add
 * clients and projects.
 *
 * The AI (or local matching, without AI) only picks an intent from a fixed list and fills in its
 * details. TimeGrow then checks the person may do it and runs its own read-only lookup, or, for
 * adding, creates the record after the person confirms. Answers are written from templates here:
 * the data found is never sent to the AI.
 *
 * Who may do what:
 * - Everyone who can record time: their own entries and hours, their clients and projects, clock status.
 * - Owners (TIMEGROW_OWNER_CAP): hours for the whole team, unbilled hours, unpaid invoices, expenses,
 *   add projects; add clients when they may also create users.
 */
class TimeGrowNexusAssistant {

    const NONCE    = 'timegrow_voice_nonce'; // same as TimeGrowNexusVoice
    const PERIODS  = ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'this_year'];
    const EVERYONE = ['last_entry', 'recent_entries', 'hours', 'clients', 'projects_for_client', 'latest_projects', 'clock_status'];
    const OWNERS   = ['unbilled_hours', 'expenses', 'unpaid_invoices'];
    const UNPAID_STATUSES = ['invoice_sent', 'inv_sent2paypal', 'inv_draft2paypal', 'partial_paid', 'to_be_mailed'];
    const MAX_RATE = 10000;

    public static function init() {
        add_action('wp_ajax_timegrow_voice_create', [__CLASS__, 'handle_create']);
    }

    // ------------------------------------------------------------------
    // Who may do what
    // ------------------------------------------------------------------

    public static function is_owner() {
        return current_user_can(TIMEGROW_OWNER_CAP) || current_user_can('administrator');
    }

    public static function can_add_client() {
        return self::is_owner() && current_user_can('create_users');
    }

    public static function can_add_project() {
        return self::is_owner();
    }

    /** The questions the logged-in person may ask. */
    public static function allowed_questions() {
        $questions = self::EVERYONE;
        if (self::is_owner()) {
            $questions[] = 'unbilled_hours';
            $questions[] = 'expenses';
            if (function_exists('wc_get_orders')) {
                $questions[] = 'unpaid_invoices';
            }
        }
        return $questions;
    }

    /** The adding actions the logged-in person may use. */
    public static function allowed_actions() {
        $actions = [];
        if (self::can_add_client()) {
            $actions[] = 'add_client';
        }
        if (self::can_add_project()) {
            $actions[] = 'add_project';
        }
        return $actions;
    }

    /** Example questions for the chat's buttons, only ones the person may ask. */
    public static function suggestions() {
        $examples = [
            'last_entry'      => 'What did I log last?',
            'hours'           => 'How many hours this week?',
            'latest_projects' => 'Newest projects',
            'unbilled_hours'  => 'Who has unbilled hours?',
            'unpaid_invoices' => 'Which invoices are unpaid?',
            'expenses'        => 'Expenses this month',
        ];
        $out = [];
        foreach (self::allowed_questions() as $q) {
            if (isset($examples[$q])) {
                $out[] = $examples[$q];
            }
        }
        if (self::can_add_client()) {
            $out[] = 'Add a client';
        }
        if (self::can_add_project()) {
            $out[] = 'Add a project';
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Clients the person can ask about or add projects for
    // ------------------------------------------------------------------

    /** Clients of the person's projects. @return array[] { id, name } */
    public static function my_clients(array $candidates) {
        $clients = [];
        foreach ($candidates as $c) {
            if ((int) $c['client_id'] > 0) {
                $clients[(int) $c['client_id']] = ['id' => (int) $c['client_id'], 'name' => (string) $c['client_name']];
            }
        }
        uasort($clients, function ($a, $b) { return strnatcasecmp($a['name'], $b['name']); });
        return array_values($clients);
    }

    /** Every client (WooCommerce customer), for owners adding a project. @return array[] { id, name } */
    public static function all_clients() {
        if (!self::can_add_project() || !class_exists('TimeGrowClientModel')) {
            return [];
        }
        return array_map(function ($u) {
            return ['id' => (int) $u->ID, 'name' => (string) $u->display_name];
        }, (array) (new TimeGrowClientModel())->select());
    }

    // ------------------------------------------------------------------
    // Understanding a request without AI (pure)
    // ------------------------------------------------------------------

    /** The period named in the text, or $default. Pure. */
    public static function period_in($text, $default = null) {
        $t = strtolower((string) $text);
        foreach (['last week' => 'last_week', 'this week' => 'this_week', 'last month' => 'last_month', 'this month' => 'this_month',
                  'this year' => 'this_year', 'yesterday' => 'yesterday', 'today' => 'today'] as $words => $period) {
            if (strpos($t, $words) !== false) {
                return $period;
            }
        }
        return $default;
    }

    /**
     * The one client whose name matches (exactly, else the only partial match), or null. Pure.
     *
     * @param array[] $clients { id, name }
     */
    public static function match_client($name, array $clients) {
        $norm = function ($s) { return trim(preg_replace('/[^a-z0-9 ]+/', ' ', strtolower((string) $s))); };
        $want = preg_replace('/\s+/', ' ', $norm($name));
        if ($want === '') {
            return null;
        }
        foreach ($clients as $c) {
            if (preg_replace('/\s+/', ' ', $norm($c['name'])) === $want) {
                return $c;
            }
        }
        $hits = array_values(array_filter($clients, function ($c) use ($want, $norm) {
            $have = preg_replace('/\s+/', ' ', $norm($c['name']));
            return $have !== '' && (strpos($have, $want) !== false || strpos($want, $have) !== false);
        }));
        return count($hits) === 1 ? $hits[0] : null;
    }

    /**
     * A plain request the chat can read without AI, or null. Pure.
     *
     * @param string  $text      What the person said.
     * @param string[] $questions Allowed questions.
     * @param string[] $actions   Allowed actions.
     * @param array[] $clients   Clients for "projects for <client>".
     * @return array|null { intent, question?, new_client?, new_project? }
     */
    public static function local_intent($text, array $questions, array $actions, array $clients) {
        $t = strtolower(trim(preg_replace('/\s+/', ' ', (string) $text)));
        $t = rtrim($t, ' ?.!');
        // "... for <client>" (period words removed first: "for this week" is not a client)
        $for_client = null;
        if (preg_match('/\bfor (.+)$/', preg_replace('/\b(today|yesterday|(this|last) (week|month|year))\b/', '', $t), $fm)) {
            $match = self::match_client(trim($fm[1]), $clients);
            $for_client = $match ? $match['id'] : null;
        }
        $q = function ($name, array $params = []) use ($questions) {
            return in_array($name, $questions, true)
                ? ['intent' => 'question', 'question' => array_merge(['name' => $name], $params)]
                : ['intent' => 'denied', 'question' => ['name' => $name]];
        };

        if (preg_match('/^(add|create|new|make)( a| an)? (new )?client\b(?: (?:called|named) (.+))?/', $t, $m)) {
            return in_array('add_client', $actions, true)
                ? ['intent' => 'add_client', 'new_client' => ['name' => isset($m[4]) ? self::title_case($m[4]) : null]]
                : ['intent' => 'denied', 'question' => ['name' => 'add_client']];
        }
        if (preg_match('/^(add|create|new|make)( a| an)? (new )?project\b(?: (?:called|named) (.+?))?(?: for (.+))?$/', $t, $m)) {
            if (!in_array('add_project', $actions, true)) {
                return ['intent' => 'denied', 'question' => ['name' => 'add_project']];
            }
            $client = !empty($m[5]) ? self::match_client($m[5], $clients) : null;
            return ['intent' => 'add_project', 'new_project' => ['name' => !empty($m[4]) ? self::title_case($m[4]) : null, 'client_id' => $client ? $client['id'] : null]];
        }
        if (preg_match('/\b(am i|are we) (clocked in|on the clock|clocked)\b|\bclock status\b/', $t)) {
            return $q('clock_status');
        }
        if (preg_match('/\b(last|latest|most recent) (time )?(entry|thing i (logged|recorded))\b|what did i (log|record) last\b/', $t)) {
            return $q('last_entry');
        }
        if (preg_match('/\b(my )?(recent|latest|last \d+) (time )?entries\b|what did i (log|record|work on)\b/', $t)) {
            return $q('recent_entries', ['period' => self::period_in($t), 'client_id' => $for_client]);
        }
        if (preg_match('/\b(how (many|much) (hours|time)|total hours|hours (did|have) (i|we))\b/', $t)) {
            $team = preg_match('/\b(team|everyone|we|all of us)\b/', $t) ? 'team' : 'me';
            return $q('hours', ['period' => self::period_in($t, 'this_week'), 'who' => $team, 'client_id' => $for_client]);
        }
        if (preg_match('/\bunbilled\b|\bnot (been )?billed\b/', $t)) {
            return $q('unbilled_hours');
        }
        if (preg_match('/\b(unpaid|open|outstanding) invoices?\b|\binvoices?\b.*\b(unpaid|outstanding|not (been )?paid)\b|\bwho owes\b/', $t)) {
            return $q('unpaid_invoices');
        }
        if (preg_match('/\bexpenses?\b/', $t)) {
            return $q('expenses', ['period' => self::period_in($t, 'this_month')]);
        }
        if (preg_match('/\b(latest|newest|new|recent) projects\b/', $t)) {
            return $q('latest_projects');
        }
        if (preg_match('/\bprojects? (for|of|with) (.+)$/', $t, $m)) {
            $client = self::match_client($m[2], $clients);
            return $q('projects_for_client', ['client_id' => $client ? $client['id'] : null, 'client_name' => $client ? null : self::title_case($m[2])]);
        }
        if (preg_match('/^(list |show |show me )?(my |our |all )?clients$|\bwhich clients\b|\bwho are (my|our) clients\b/', $t)) {
            return $q('clients');
        }
        return null;
    }

    private static function title_case($s) {
        return ucwords(trim((string) $s, " \t\n\r\0\x0B.\"'"));
    }

    // ------------------------------------------------------------------
    // Checking what the AI picked (pure)
    // ------------------------------------------------------------------

    /**
     * Keep only an allowed intent and valid details. Anything else becomes "unknown"; a question or
     * action the person may not use becomes "denied". Pure.
     *
     * @param array   $answer    The AI's JSON.
     * @param string[] $questions Allowed questions.
     * @param string[] $actions   Allowed actions.
     * @param array[] $clients   Clients the person may name (their own, plus all for owners adding).
     * @param array[] $candidates The person's projects.
     */
    public static function check_intent(array $answer, array $questions, array $actions, array $clients, array $candidates) {
        $intent    = is_string($answer['intent'] ?? null) ? $answer['intent'] : 'unknown';
        $client_ok = array_flip(array_map('intval', array_column($clients, 'id')));
        $project_ok = array_flip(array_map('intval', array_column($candidates, 'id')));

        if ($intent === 'question') {
            $raw  = is_array($answer['question'] ?? null) ? $answer['question'] : [];
            $name = is_string($raw['name'] ?? null) ? $raw['name'] : '';
            if (!in_array($name, array_merge(self::EVERYONE, self::OWNERS), true)) {
                return ['intent' => 'unknown'];
            }
            if (!in_array($name, $questions, true)) {
                return ['intent' => 'denied', 'question' => ['name' => $name]];
            }
            $period = in_array($raw['period'] ?? null, self::PERIODS, true) ? $raw['period'] : null;
            return ['intent' => 'question', 'question' => [
                'name'       => $name,
                'period'     => $period,
                'client_id'  => isset($client_ok[(int) ($raw['client_id'] ?? 0)]) ? (int) $raw['client_id'] : null,
                'project_id' => isset($project_ok[(int) ($raw['project_id'] ?? 0)]) ? (int) $raw['project_id'] : null,
                'who'        => ($raw['who'] ?? 'me') === 'team' ? 'team' : 'me',
            ]];
        }

        if ($intent === 'add_client' || $intent === 'add_project') {
            if (!in_array($intent, $actions, true)) {
                return ['intent' => 'denied', 'question' => ['name' => $intent]];
            }
            $text = function ($v, $max) {
                $v = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags(is_scalar($v) ? (string) $v : '')));
                return ($v === '' || TimeGrowAI::looks_like_injection($v)) ? null : (function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max));
            };
            if ($intent === 'add_client') {
                $raw   = is_array($answer['new_client'] ?? null) ? $answer['new_client'] : [];
                $email = is_string($raw['email'] ?? null) && filter_var(trim($raw['email']), FILTER_VALIDATE_EMAIL) ? strtolower(trim($raw['email'])) : null;
                return ['intent' => 'add_client', 'new_client' => ['name' => $text($raw['name'] ?? null, 100), 'email' => $email, 'company' => $text($raw['company'] ?? null, 100)]];
            }
            $raw  = is_array($answer['new_project'] ?? null) ? $answer['new_project'] : [];
            $rate = is_numeric($raw['rate'] ?? null) && (float) $raw['rate'] >= 0 && (float) $raw['rate'] <= self::MAX_RATE ? round((float) $raw['rate'], 2) : null;
            return ['intent' => 'add_project', 'new_project' => [
                'client_id' => isset($client_ok[(int) ($raw['client_id'] ?? 0)]) ? (int) $raw['client_id'] : null,
                'name'      => $text($raw['name'] ?? null, 255),
                'rate'      => $rate,
                'billable'  => isset($raw['billable']) && $raw['billable'] !== null && $raw['billable'] !== '' ? (filter_var($raw['billable'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : null,
            ]];
        }

        return ['intent' => $intent === 'record_time' ? 'record_time' : 'unknown'];
    }

    // ------------------------------------------------------------------
    // The AI prompt for a first request
    // ------------------------------------------------------------------

    /** System instructions for reading a first request (after TimeGrowAI's safety rules). Pure. */
    public static function instructions() {
        return TimeGrowNexusVoice::instructions() . "\n\n" . <<<'RULES'
This is the person's first request, so first work out what they want:
- "record_time": they describe work they did. Fill the time entry fields above.
- "question": they ask about their TimeGrow data. Set question.name to one of the allowed questions and fill its details (period, client_id, project_id, who).
- "add_client" or "add_project": they ask to create one. Fill new_client {name, email, company} or new_project {client_id, name, rate, billable} with what they said.
- "unknown": anything else, including anything not in the allowed lists. Never invent a question or action.
Return one JSON object with the key "intent", the keys "question", "new_client" and "new_project" (null when not used), and the time entry fields.
Periods: today, yesterday, this_week, last_week, this_month, last_month, this_year. "who" is "me", or "team" when they ask about everyone.
RULES;
    }

    /** The request text for a first request: what is allowed, then the data blocks. Pure. */
    public static function build_prompt($transcript, array $candidates, array $clients, array $questions, array $actions, $today) {
        $describe = [
            'last_entry'          => 'the last time entry they recorded',
            'recent_entries'      => 'their recent time entries (optional period)',
            'hours'               => 'how many hours were worked in a period (optional client_id, project_id; who = me or team)',
            'clients'             => 'the clients they work for',
            'projects_for_client' => 'the projects for one client (client_id)',
            'latest_projects'     => 'the newest projects',
            'clock_status'        => 'whether they are clocked in',
            'unbilled_hours'      => 'billable hours not yet invoiced, by client',
            'unpaid_invoices'     => 'invoices not paid yet',
            'expenses'            => 'expenses in a period',
        ];
        $lines = [];
        foreach ($questions as $q) {
            $lines[] = "- {$q}: " . ($describe[$q] ?? $q);
        }
        $weekday = date('l', strtotime($today));
        return "Today is {$today} ({$weekday}).\n"
            . 'Allowed intents: record_time, question' . ($actions ? ', ' . implode(', ', $actions) : '') . ", unknown.\n"
            . "Allowed questions:\n" . implode("\n", $lines) . "\n\n"
            . "The person's projects:\n" . TimeGrowAI::untrusted('projects', array_map(function ($c) {
                return ['project_id' => $c['id'], 'project' => $c['name'], 'client_id' => $c['client_id'], 'client' => $c['client_name']];
            }, $candidates)) . "\n\n"
            . "Clients:\n" . TimeGrowAI::untrusted('clients', array_map(function ($c) {
                return ['client_id' => $c['id'], 'client' => $c['name']];
            }, $clients)) . "\n\n"
            . "What the person said:\n" . TimeGrowAI::untrusted('what_the_person_said', (string) $transcript) . "\n\n"
            . 'Return the JSON object.';
    }

    // ------------------------------------------------------------------
    // Periods and wording (pure)
    // ------------------------------------------------------------------

    /** [from, to] dates (inclusive) for a period. Weeks start on Monday. Pure. */
    public static function period_range($period, $today) {
        $d = new DateTimeImmutable($today);
        switch ($period) {
            case 'today':      return [$d->format('Y-m-d'), $d->format('Y-m-d')];
            case 'yesterday':  $y = $d->modify('-1 day'); return [$y->format('Y-m-d'), $y->format('Y-m-d')];
            case 'last_week':  $m = $d->modify('monday this week')->modify('-7 days'); return [$m->format('Y-m-d'), $m->modify('+6 days')->format('Y-m-d')];
            case 'this_month': return [$d->format('Y-m-01'), $d->format('Y-m-d')];
            case 'last_month': $l = $d->modify('first day of last month'); return [$l->format('Y-m-d'), $l->modify('last day of this month')->format('Y-m-d')];
            case 'this_year':  return [$d->format('Y-01-01'), $d->format('Y-m-d')];
            case 'this_week':
            default:           return [$d->modify('monday this week')->format('Y-m-d'), $d->format('Y-m-d')];
        }
    }

    public static function period_words($period) {
        $words = ['today' => 'today', 'yesterday' => 'yesterday', 'this_week' => 'this week', 'last_week' => 'last week',
                  'this_month' => 'this month', 'last_month' => 'last month', 'this_year' => 'this year'];
        return $words[$period] ?? 'this week';
    }

    /** "2 h 30 min" for the screen. Pure. */
    public static function hours_text($hours) {
        $mins = (int) round($hours * 60);
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        return $h && $m ? "{$h} h {$m} min" : ($h ? "{$h} h" : "{$m} min");
    }

    /** "2 and a half hours" to be spoken. Pure. */
    public static function hours_spoken($hours) {
        $mins = (int) round($hours * 60);
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        $unit = $h === 1 ? 'hour' : 'hours';
        if (!$h) return $m . ' minutes';
        if ($m === 30) return "{$h} and a half {$unit}";
        if ($m) return "{$h} {$unit} and {$m} minutes";
        return "{$h} {$unit}";
    }

    public static function money($amount) {
        return function_exists('wc_price') ? html_entity_decode(wp_strip_all_tags(wc_price($amount)), ENT_QUOTES) : '$' . number_format((float) $amount, 2);
    }

    // ------------------------------------------------------------------
    // Answering a question (read-only; runs as the logged-in person)
    // ------------------------------------------------------------------

    /**
     * Run an allowed question and write the answer.
     *
     * @return array { text, spoken, rows: [{ title, meta, url }] }
     */
    public static function answer(array $question, array $candidates) {
        $name = $question['name'];
        if (!in_array($name, self::allowed_questions(), true)) {
            return self::reply(self::denied_text($name));
        }
        $today  = current_time('Y-m-d');
        $member = TimeGrowTimeEntryRules::member_id(0);
        $needs_member = in_array($name, ['last_entry', 'recent_entries', 'clock_status'], true)
            || ($name === 'hours' && ($question['who'] ?? 'me') !== 'team');
        if ($needs_member && !$member) {
            return self::reply(__('Your user is not set up as a team member, so there is no time recorded for you.', 'timegrow'));
        }

        switch ($name) {
            case 'last_entry':
                $filters = array_filter(['client_id' => $question['client_id'] ?? null, 'project_id' => $question['project_id'] ?? null]);
                $rows = self::entries($member, '1970-01-01', $today, $filters, 1);
                if (!$rows) return self::reply($filters ? __('You have no entries for that yet.', 'timegrow') : __('You have not recorded any time yet.', 'timegrow'));
                $e = $rows[0];
                $h = TimeGrowWooOrderCreator::entry_hours($e);
                $when = self::date_words(self::entry_day($e), $today);
                $text = sprintf(__('Your last entry: %1$s on %2$s (%3$s), %4$s.', 'timegrow'), $h === null ? __('still clocked in', 'timegrow') : self::hours_text($h), $e->project_name, self::client_name($e->client_id), $when);
                $spoken = sprintf('Your last entry was %s on %s, %s.', $h === null ? 'a clock-in that is still open' : self::hours_spoken($h), $e->project_name, $when);
                return self::reply($text, $spoken, [self::entry_row($e, $today)]);

            case 'recent_entries':
                [$from, $to] = !empty($question['period']) ? self::period_range($question['period'], $today) : ['1970-01-01', $today];
                $filters = array_filter(['client_id' => $question['client_id'] ?? null, 'project_id' => $question['project_id'] ?? null]);
                $rows = self::entries($member, $from, $to, $filters, 5);
                $for  = !empty($filters['project_id']) ? ' ' . sprintf(__('on %s', 'timegrow'), self::project_name($filters['project_id'])) : (!empty($filters['client_id']) ? ' ' . sprintf(__('for %s', 'timegrow'), self::client_name($filters['client_id'])) : '');
                if (!$rows) return self::reply(sprintf(__('No entries found%s.', 'timegrow'), $for));
                return self::reply(sprintf(_n('Your last entry%2$s:', 'Your last %1$d entries%2$s:', count($rows), 'timegrow'), count($rows), $for),
                    sprintf('Here %s your last %s%s.', count($rows) === 1 ? 'is' : 'are', count($rows) === 1 ? 'entry' : count($rows) . ' entries', $for),
                    array_map(function ($e) use ($today) { return self::entry_row($e, $today); }, $rows));

            case 'hours':
                $team   = ($question['who'] ?? 'me') === 'team';
                $period = $question['period'] ?: 'this_week';
                [$from, $to] = self::period_range($period, $today);
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
                arsort($by);
                $who    = $team ? __('The team', 'timegrow') : __('You', 'timegrow');
                $scope  = !empty($question['project_id']) ? ' ' . sprintf(__('on %s', 'timegrow'), self::project_name($question['project_id'])) : (!empty($question['client_id']) ? ' ' . sprintf(__('for %s', 'timegrow'), self::client_name($question['client_id'])) : '');
                $text   = sprintf(__('%1$s logged %2$s%3$s %4$s.', 'timegrow'), $who, $total > 0 ? self::hours_text($total) : __('no time', 'timegrow'), $scope, self::period_words($period));
                $spoken = sprintf('%s logged %s%s %s.', $team ? 'The team' : 'You', $total > 0 ? self::hours_spoken($total) : 'no time', $scope, self::period_words($period));
                $list   = [];
                foreach (array_slice($by, 0, 6, true) as $label => $h) {
                    $list[] = ['title' => $label, 'meta' => self::hours_text($h), 'url' => ''];
                }
                return self::reply($text, $spoken, $list);

            case 'clients':
                $clients = self::my_clients($candidates);
                if (!$clients) return self::reply(__('You have no clients with projects you can use yet.', 'timegrow'));
                $counts = array_count_values(array_map(function ($c) { return (int) $c['client_id']; }, $candidates));
                return self::reply(sprintf(_n('You work with %d client:', 'You work with %d clients:', count($clients), 'timegrow'), count($clients)),
                    sprintf('You work with %d %s.', count($clients), count($clients) === 1 ? 'client' : 'clients'),
                    array_map(function ($c) use ($counts) {
                        return ['title' => $c['name'], 'meta' => sprintf(_n('%d project', '%d projects', $counts[$c['id']] ?? 0, 'timegrow'), $counts[$c['id']] ?? 0), 'url' => ''];
                    }, $clients));

            case 'projects_for_client':
                if (empty($question['client_id'])) {
                    return self::reply(!empty($question['client_name'])
                        ? sprintf(__('I couldn\'t find a client called %s among yours.', 'timegrow'), $question['client_name'])
                        : __('Which client? Say "projects for" and the client\'s name.', 'timegrow'));
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
                return self::reply(__('The newest projects:', 'timegrow'),
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
                return self::reply(sprintf(__('%1$s unbilled across %2$d clients:', 'timegrow'), self::hours_text($total), count($by)),
                    sprintf('There are %s unbilled across %d %s. The most is for %s.', self::hours_spoken($total), count($by), count($by) === 1 ? 'client' : 'clients', array_key_first($by)),
                    array_map(function ($name, $h) { return ['title' => $name, 'meta' => self::hours_text($h), 'url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-process-time')]; }, array_keys($by), $by));

            case 'unpaid_invoices':
                $orders = wc_get_orders(['status' => self::UNPAID_STATUSES, 'limit' => 20, 'orderby' => 'date', 'order' => 'DESC']);
                if (!$orders) return self::reply(__('There are no unpaid invoices.', 'timegrow'), 'There are no unpaid invoices.');
                $total = 0.0;
                $list = [];
                foreach ($orders as $o) {
                    $total += (float) $o->get_total();
                    $list[] = ['title' => '#' . $o->get_order_number() . ' · ' . $o->get_formatted_billing_full_name(), 'meta' => self::money($o->get_total()) . ' · ' . wc_get_order_status_name($o->get_status()), 'url' => $o->get_edit_order_url()];
                }
                return self::reply(sprintf(_n('%1$d unpaid invoice, %2$s in total:', '%1$d unpaid invoices, %2$s in total:', count($orders), 'timegrow'), count($orders), self::money($total)),
                    sprintf('There %s %d unpaid %s, %s in total.', count($orders) === 1 ? 'is' : 'are', count($orders), count($orders) === 1 ? 'invoice' : 'invoices', self::money($total)),
                    $list);

            case 'expenses':
                $period = $question['period'] ?: 'this_month';
                [$from, $to] = self::period_range($period, $today);
                global $wpdb;
                $table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
                $rows = $wpdb->get_results($wpdb->prepare("SELECT ID, expense_name, amount, expense_date FROM {$table} WHERE expense_date BETWEEN %s AND %s ORDER BY expense_date DESC, ID DESC", $from, $to));
                $total = array_sum(array_map(function ($r) { return (float) $r->amount; }, (array) $rows));
                if (!$rows) return self::reply(sprintf(__('No expenses %s.', 'timegrow'), self::period_words($period)));
                return self::reply(sprintf(_n('%1$d expense %2$s, %3$s in total:', '%1$d expenses %2$s, %3$s in total:', count($rows), 'timegrow'), count($rows), self::period_words($period), self::money($total)),
                    sprintf('You have %d %s %s, %s in total.', count($rows), count($rows) === 1 ? 'expense' : 'expenses', self::period_words($period), self::money($total)),
                    array_map(function ($r) { return ['title' => $r->expense_name, 'meta' => self::money($r->amount) . ' · ' . mysql2date(get_option('date_format'), $r->expense_date), 'url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expense-edit&id=' . (int) $r->ID)]; }, array_slice($rows, 0, 6)));
        }
        return self::reply(__('I can\'t answer that yet.', 'timegrow'));
    }

    public static function denied_text($name) {
        return in_array($name, ['add_client', 'add_project'], true)
            ? __('Only administrators can add clients and projects.', 'timegrow')
            : __('That information is only available to administrators.', 'timegrow');
    }

    private static function reply($text, $spoken = '', array $rows = []) {
        return ['text' => $text, 'spoken' => $spoken !== '' ? $spoken : $text, 'rows' => $rows];
    }

    /** Time entries in a date range, newest first. $member_id 0 = everyone (owners only reach this). */
    private static function entries($member_id, $from, $to, array $filters = [], $limit = 0) {
        global $wpdb;
        $p     = $wpdb->prefix . TIMEGROW_PREFIX;
        $day   = 'COALESCE(e.date, e.clock_in_date)';
        $where = ["{$day} >= %s", "{$day} < %s"];
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

    private static function date_words($day, $today) {
        if ($day === $today) return __('today', 'timegrow');
        if ($day === date('Y-m-d', strtotime($today . ' -1 day'))) return __('yesterday', 'timegrow');
        return mysql2date('l, F j', $day . ' 00:00:00');
    }

    private static function entry_row($e, $today) {
        $h = TimeGrowWooOrderCreator::entry_hours($e);
        return [
            'title' => $e->project_name . ' (' . self::client_name($e->client_id) . ')',
            'meta'  => ($h === null ? __('clocked in', 'timegrow') : self::hours_text($h)) . ' · ' . self::date_words(self::entry_day($e), $today) . ($e->description ? ' · ' . wp_trim_words(wp_strip_all_tags($e->description), 10) : ''),
            'url'   => '',
        ];
    }

    private static function client_name($id) {
        $name = TimeGrowProjectPicker::client_name($id);
        return $name !== '' ? $name : __('no client', 'timegrow');
    }

    private static function project_name($id) {
        global $wpdb;
        return (string) $wpdb->get_var($wpdb->prepare("SELECT name FROM {$wpdb->prefix}" . TIMEGROW_PREFIX . 'project_tracker WHERE ID = %d', (int) $id));
    }

    private static function project_url($id) {
        return self::is_owner() ? admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-project-edit&id=' . (int) $id) : '';
    }

    // ------------------------------------------------------------------
    // Adding clients and projects (after the person confirms in the chat)
    // ------------------------------------------------------------------

    /**
     * Why a new client can't be created, or '' when it can. Pure apart from $exists callbacks.
     *
     * @param array    $c            { name, email, company }
     * @param callable $email_taken  fn(string $email): bool
     * @param callable $name_taken   fn(string $name): bool
     */
    public static function client_problem(array $c, callable $email_taken, callable $name_taken) {
        $name = trim((string) ($c['name'] ?? ''));
        if (strlen($name) < 2 || strlen($name) > 100) {
            return __('The client needs a name.', 'timegrow');
        }
        foreach (['name', 'email', 'company'] as $k) {
            if (!empty($c[$k]) && TimeGrowAI::looks_like_injection($c[$k])) {
                return __('That text can\'t be used for a client.', 'timegrow');
            }
        }
        $email = trim((string) ($c['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return __('That email address is not valid.', 'timegrow');
        }
        if ($email !== '' && $email_taken($email)) {
            return __('Someone on this site already uses that email address.', 'timegrow');
        }
        if ($name_taken($name)) {
            return sprintf(__('There is already a client named %s.', 'timegrow'), $name);
        }
        return '';
    }

    /**
     * Why a new project can't be created, or '' when it can. Pure apart from the callbacks.
     *
     * @param array    $p           { client_id, name, rate, billable }
     * @param callable $client_ok   fn(int $client_id): bool
     * @param callable $name_taken  fn(int $client_id, string $name): bool
     */
    public static function project_problem(array $p, callable $client_ok, callable $name_taken) {
        $name = trim((string) ($p['name'] ?? ''));
        if (empty($p['client_id']) || !$client_ok((int) $p['client_id'])) {
            return __('Pick a client for the project.', 'timegrow');
        }
        if (strlen($name) < 2 || strlen($name) > 255) {
            return __('The project needs a name.', 'timegrow');
        }
        if (TimeGrowAI::looks_like_injection($name)) {
            return __('That text can\'t be used for a project.', 'timegrow');
        }
        if (isset($p['rate']) && $p['rate'] !== null && $p['rate'] !== '' && (!is_numeric($p['rate']) || (float) $p['rate'] < 0 || (float) $p['rate'] > self::MAX_RATE)) {
            return sprintf(__('The rate must be between 0 and %d.', 'timegrow'), self::MAX_RATE);
        }
        if ($name_taken((int) $p['client_id'], $name)) {
            return __('That client already has a project with that name.', 'timegrow');
        }
        return '';
    }

    /** AJAX timegrow_voice_create: add a client or project the person confirmed in the chat. */
    public static function handle_create() {
        check_ajax_referer(self::NONCE, 'nonce');
        $type   = sanitize_key($_POST['type'] ?? '');
        $values = json_decode(wp_unslash($_POST['values'] ?? '{}'), true);
        $values = is_array($values) ? $values : [];

        if ($type === 'client') {
            if (!self::can_add_client()) {
                wp_send_json_error(['message' => self::denied_text('add_client')], 403);
            }
            $c = [
                'name'    => sanitize_text_field((string) ($values['name'] ?? '')),
                'email'   => sanitize_email((string) ($values['email'] ?? '')),
                'company' => sanitize_text_field((string) ($values['company'] ?? '')),
            ];
            $problem = self::client_problem($c, 'email_exists', function ($name) {
                foreach ((array) (new TimeGrowClientModel())->select() as $u) {
                    if (strcasecmp(trim($u->display_name), $name) === 0) return true;
                }
                return false;
            });
            if ($problem !== '') {
                wp_send_json_error(['message' => $problem]);
            }
            $parts = preg_split('/\s+/', $c['name'], 2);
            $id = wp_insert_user([
                'user_login'   => TimeGrowClient::unique_client_login(),
                'user_pass'    => wp_generate_password(24),
                'user_email'   => $c['email'],
                'display_name' => $c['name'],
                'first_name'   => $parts[0],
                'last_name'    => $parts[1] ?? '',
                'role'         => get_role('customer') ? 'customer' : get_option('default_role'),
            ]); // no notification email is sent
            if (is_wp_error($id)) {
                wp_send_json_error(['message' => $id->get_error_message()]);
            }
            foreach (['billing_first_name' => $parts[0], 'billing_last_name' => $parts[1] ?? '', 'billing_email' => $c['email'], 'billing_company' => $c['company']] as $k => $v) {
                if ($v !== '') update_user_meta($id, $k, $v);
            }
            timegrow_log(sprintf('Voice entry: user %d added client %d', get_current_user_id(), $id));
            wp_send_json_success([
                'message' => sprintf(__('Client %s added.', 'timegrow'), $c['name']),
                'client'  => ['id' => (int) $id, 'name' => $c['name']],
                'url'     => admin_url('user-edit.php?user_id=' . (int) $id),
            ]);
        }

        if ($type === 'project') {
            if (!self::can_add_project()) {
                wp_send_json_error(['message' => self::denied_text('add_project')], 403);
            }
            $p = [
                'client_id' => (int) ($values['client_id'] ?? 0),
                'name'      => sanitize_text_field((string) ($values['name'] ?? '')),
                'rate'      => isset($values['rate']) && $values['rate'] !== '' && $values['rate'] !== null ? $values['rate'] : null,
                'billable'  => !empty($values['billable']) ? 1 : 0,
            ];
            $customers = array_map(function ($u) { return (int) $u->ID; }, (array) (new TimeGrowClientModel())->select());
            $problem = self::project_problem($p,
                function ($id) use ($customers) { return in_array($id, $customers, true); },
                function ($client_id, $name) {
                    global $wpdb;
                    return (bool) $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->prefix}" . TIMEGROW_PREFIX . 'project_tracker WHERE client_id = %d AND name = %s', $client_id, $name));
                });
            if ($problem !== '') {
                wp_send_json_error(['message' => $problem]);
            }
            $now  = current_time('mysql');
            $data = [
                'client_id'        => $p['client_id'],
                'name'             => $p['name'],
                'description'      => '',
                'default_flat_fee' => $p['rate'] !== null ? round((float) $p['rate'], 2) : 0,
                'billable'         => $p['billable'],
                'status'           => 1,
                'product_id'       => 0,
                'updated_at'       => $now,
                'created_at'       => $now,
            ];
            $data['product_id'] = TimeGrowProjectController::sync_client_product($data); // its WooCommerce product, like the Add Project page
            $id = (new TimeGrowProjectModel())->create($data, ['%d', '%s', '%s', '%f', '%d', '%d', '%d', '%s', '%s']);
            if (!$id || is_wp_error($id)) {
                wp_send_json_error(['message' => __('The project could not be saved.', 'timegrow')]);
            }
            timegrow_log(sprintf('Voice entry: user %d added project %d', get_current_user_id(), $id));
            wp_send_json_success([
                'message' => sprintf(__('Project %1$s added for %2$s.', 'timegrow'), $p['name'], self::client_name($p['client_id'])),
                'project' => ['id' => (int) $id, 'name' => $p['name'], 'clientId' => (string) $p['client_id'], 'clientName' => self::client_name($p['client_id'])],
                'url'     => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-project-edit&id=' . (int) $id),
            ]);
        }

        wp_send_json_error(['message' => __('Unknown request.', 'timegrow')]);
    }
}
