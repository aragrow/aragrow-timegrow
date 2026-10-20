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
 * details. TimeGrow then checks the person may do it (here) and runs its own read-only lookup
 * (TimeGrowNexusVoiceAnswers), or, for adding, creates the record after the person confirms (here).
 * The prompts are in TimeGrowNexusVoicePrompt.
 *
 * Who may do what:
 * - Everyone who can record time: their own entries and hours, their clients and projects, clock status.
 * - Owners (TIMEGROW_OWNER_CAP): hours for the whole team, unbilled hours, unpaid invoices, expenses,
 *   add projects; add clients when they may also create users.
 */
class TimeGrowNexusAssistant {

    const NONCE    = 'timegrow_voice_nonce'; // same as TimeGrowNexusVoice
    const PERIODS  = ['today', 'yesterday', 'this_week', 'last_week', 'this_month', 'last_month', 'this_year', 'last_year'];
    const EVERYONE = ['last_entry', 'recent_entries', 'hours', 'clients', 'projects_for_client', 'latest_projects', 'clock_status', 'lookup', 'help'];
    const OWNERS   = ['unbilled_hours', 'expenses', 'unpaid_invoices', 'billed_amount'];
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
                $questions[] = 'billed_amount';
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
            'billed_amount'   => 'Billed this year by client',
            'expenses'        => 'Expenses this month',
            'help'            => 'What can you do?',
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
            $match = TimeGrowNexusVoiceNames::match_client(trim($fm[1]), $clients);
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
            $client = !empty($m[5]) ? TimeGrowNexusVoiceNames::match_client($m[5], $clients) : null;
            return ['intent' => 'add_project', 'new_project' => ['name' => !empty($m[4]) ? self::title_case($m[4]) : null, 'client_id' => $client ? $client['id'] : null]];
        }
        if (preg_match('/\b(am i|are we) (clocked in|on the clock|clocked)\b|\bclock status\b/', $t)) {
            return $q('clock_status');
        }
        if (self::is_how_to($t)) {
            return $q('help', ['help' => $text]);
        }
        if ($action = self::clock_action($t)) {
            return ['intent' => 'clock_' . $action, 'clock' => []];
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
        if ((preg_match('/\b(billed|invoiced|billing|revenue|income)\b/', $t) || preg_match('/\bhow much\b.{0,30}\b(bill|invoice|charge)d?\b/', $t)) && !preg_match('/\bunbilled\b|\bnot (been )?billed\b/', $t)) {
            return $q('billed_amount', ['period' => self::period_in($t, 'this_year'), 'client_id' => $for_client]);
        }
        if (preg_match('/\b(unpaid|open|outstanding) invoices?\b|\binvoices?\b.*\b(unpaid|outstanding|not (been )?paid)\b|\bwho owes\b/', $t)) {
            return $q('unpaid_invoices');
        }
        if (preg_match('/\bexpenses?\b/', $t)) {
            return $q('expenses', ['period' => self::period_in($t, 'this_month')]);
        }
        if (preg_match('/\bprojects?\b.{0,30}\b(i|we)\b.{0,12}\b(worked|work|working)\b|\bwhere did (my|our) time go\b/', $t)) {
            return $q('hours', ['period' => self::period_in($t, 'this_week'), 'who' => preg_match('/\bwe\b/', $t) ? 'team' : 'me', 'client_id' => $for_client]);
        }
        if (preg_match('/\b(latest|newest|new|recent) projects\b/', $t)) {
            return $q('latest_projects');
        }
        if (preg_match('/\bprojects? (for|of|with) (.+)$/', $t, $m)) {
            $client = TimeGrowNexusVoiceNames::match_client($m[2], $clients);
            return $q('projects_for_client', ['client_id' => $client ? $client['id'] : null, 'client_name' => $client ? null : self::title_case($m[2])]);
        }
        if (preg_match('/^(list |show |show me )?(my |our |all )?clients$|\bwhich clients\b|\bwho are (my|our) clients\b/', $t)) {
            return $q('clients');
        }
        return null;
    }

    /**
     * "in" for "punch in", "clock me in", "start the clock"; "out" for "punch out", "clock out",
     * "stop the clock"; null otherwise (also for questions like "am I clocked in?"). Pure.
     */
    public static function clock_action($text) {
        $t = strtolower(trim((string) $text));
        if (preg_match('/\b(am i|are we|was i|is my|did i)\b|\?\s*$/', $t) || self::is_how_to($t)) {
            return null;
        }
        if (preg_match('/\b(punch|clock|check)(ing)?( me)? (in|out)\b|\b(start|stop|end)( the| my)? (clock|timer|shift)\b/', $t, $m)) {
            return (($m[4] ?? '') === 'out' || in_array($m[5] ?? '', ['stop', 'end'], true)) ? 'out' : 'in';
        }
        return null;
    }

    /** True for "how do I…", "how to…", "where do I…", "what does … do", "what can you do", "help". Pure. */
    public static function is_how_to($text) {
        $t = strtolower(trim((string) $text));
        // "What is the billed amount for…" is a data question: only "what does X do / mean" and "what is the X report/page…" are help
        return (bool) preg_match('/^(how (do|can|should|would) (i|we|you)|how to|where (do|can|is|are) (i|we|the)?|what does .{2,40} (do|mean)\b|what is (the |a |an )?[a-z& ]{2,30} (report|page|tab|button|feature|setting|card|menu)s?\b|what is timegrow|what (else )?can (you|i ask)|what are you able|who are you|help( me)?\b|i need help|explain how)/', $t)
            && !preg_match('/^how (many|much)\b/', $t);
    }

    private static function title_case($s) {
        return ucwords(trim((string) $s, " \t\n\r\0\x0B.\"'"));
    }

    /**
     * True for requests to delete saved records or to change or add many at once ("delete my last
     * entry", "mark all my entries billable", "add these five clients"). The voice chat never does
     * these, so they are refused before the AI is asked. Pure.
     */
    public static function looks_like_bulk($text) {
        $t = strtolower(preg_replace('/\s+/', ' ', (string) $text));
        $records = '(entr(y|ies)|time ?entr(y|ies)|clients?|projects?|invoices?|orders?|expenses?|records?|hours|everything)';
        return (bool) (
            preg_match('/\b(delete|remove|erase|wipe|purge|drop|clear out|get rid of)\b.{0,40}\b' . $records . '\b/', $t)
            || preg_match('/\b(add|create|update|change|edit|move|mark|set|bill|invoice|copy|duplicate|import|reassign|approve)\b.{0,40}\b(all|every|each|bulk|mass|multiple|several|these|those|\d{2,})\b.{0,30}\b' . $records . '\b/', $t)
            || preg_match('/\b(bulk|mass|batch) (update|delete|insert|edit|change|import|add)\b/', $t)
        );
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
        // One record at a time: a list of records (or list-shaped details) is refused, never trimmed to one
        foreach (['time', 'question', 'new_client', 'new_project'] as $key) {
            $v = $answer[$key] ?? null;
            if (is_array($v) && $v !== [] && array_keys($v) === range(0, count($v) - 1)) {
                return ['intent' => 'denied', 'question' => ['name' => 'bulk']];
            }
        }
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
                // A flexible lookup's description, checked by TimeGrowNexusLookup::spec() before it runs
                'lookup'     => $name === 'lookup' && is_array($raw['lookup'] ?? null) ? $raw['lookup'] : null,
                // "...and why?": a short written explanation of the results, by the AI, from data with names hidden
                'explain'    => is_string($raw['explain'] ?? null) && trim($raw['explain']) !== '' ? (function_exists('mb_substr') ? mb_substr(trim($raw['explain']), 0, 200) : substr(trim($raw['explain']), 0, 200)) : null,
                // A help question: what they want to know, in their words (the help topics are searched with it)
                'help'       => $name === 'help' && is_string($raw['help'] ?? null) && trim($raw['help']) !== '' ? (function_exists('mb_substr') ? mb_substr(trim(wp_strip_all_tags($raw['help'])), 0, 300) : substr(trim(wp_strip_all_tags($raw['help'])), 0, 300)) : null,
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

        if ($intent === 'injection') {
            return ['intent' => 'injection']; // the AI saw an attempt to manipulate it: refused, nothing else read
        }

        if ($intent === 'clock_in' || $intent === 'clock_out') {
            $raw  = is_array($answer['clock'] ?? null) ? $answer['clock'] : [];
            $time = strtolower(trim(is_scalar($raw['time'] ?? null) ? (string) $raw['time'] : ''));
            $time = $time === 'now' ? 'now' : (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $m) ? sprintf('%02d:%02d', $m[1], $m[2]) : null);
            $project = isset($project_ok[(int) ($raw['project_id'] ?? 0)]) ? (int) $raw['project_id'] : null;
            $client  = isset($client_ok[(int) ($raw['client_id'] ?? 0)]) ? (int) $raw['client_id'] : null;
            foreach ($candidates as $c) {
                if ($project && (int) $c['id'] === $project) {
                    $client = (int) $c['client_id']; // always the project's client
                }
            }
            return ['intent' => $intent, 'clock' => $intent === 'clock_in'
                ? ['client_id' => $client, 'project_id' => $project, 'time' => $time]
                : ['time' => $time]];
        }

        if (in_array($intent, ['confirm', 'change', 'cancel'], true)) {
            return ['intent' => $intent];
        }
        return ['intent' => in_array($intent, ['record_time', 'time'], true) ? 'record_time' : 'unknown'];
    }

    // ------------------------------------------------------------------
    // Conversation: every message read with what came before (AI on)
    // ------------------------------------------------------------------

    const MODES       = ['idle', 'time', 'confirm_time', 'adding_client', 'adding_project', 'confirm_new', 'clock_in', 'clock_out', 'confirm_clock'];
    const MAX_HISTORY = 8;   // messages kept for context
    const MAX_LINE    = 600; // characters per message kept (an answer's headline and its lines)

    /**
     * The conversation state sent by the browser, checked like everything else from it: time fields
     * through merge(), a client or project being added through check_intent(), the last question
     * through check_intent(), history as short plain lines. Pure apart from wp_strip_all_tags().
     *
     * The history holds what the person said and what the chat asked, never the data in answers.
     */
    public static function read_state($raw, array $candidates, array $clients, array $questions, array $actions, $today) {
        $raw   = is_array($raw) ? $raw : [];
        $mode  = in_array($raw['mode'] ?? '', self::MODES, true) ? $raw['mode'] : 'idle';
        $state = [
            'mode'          => $mode,
            'asking'        => preg_replace('/[^a-z_]/', '', (string) ($raw['asking'] ?? '')),
            'time'          => TimeGrowNexusVoice::merge([], is_array($raw['fields'] ?? null) ? $raw['fields'] : [], $candidates, $today),
            'adding'        => null,
            'clock'         => null,
            'last_question' => null,
            'history'       => [],
        ];
        $type = $raw['draftType'] ?? '';
        if (in_array($type, ['client', 'project'], true) && is_array($raw['draft'] ?? null)) {
            $checked = self::check_intent(['intent' => 'add_' . $type, 'new_' . $type => $raw['draft']], $questions, $actions, $clients, $candidates);
            if ($checked['intent'] === 'add_' . $type) {
                $state['adding'] = ['type' => $type, 'values' => $checked['new_' . $type]];
            }
        }
        $action = $raw['clockAction'] ?? '';
        if (in_array($action, ['in', 'out'], true) && is_array($raw['clock'] ?? null)) {
            $checked = self::check_intent(['intent' => 'clock_' . $action, 'clock' => $raw['clock']], $questions, $actions, $clients, $candidates);
            $state['clock'] = ['action' => $action, 'values' => $checked['clock']];
        }
        if (is_array($raw['lastQuestion'] ?? null)) {
            $checked = self::check_intent(['intent' => 'question', 'question' => $raw['lastQuestion']], $questions, $actions, $clients, $candidates);
            if ($checked['intent'] === 'question') {
                $state['last_question'] = $checked['question'];
            }
        }
        foreach (array_slice(is_array($raw['history'] ?? null) ? $raw['history'] : [], -self::MAX_HISTORY) as $line) {
            if (!is_array($line) || !in_array($line['who'] ?? '', ['me', 'bot'], true)) {
                continue;
            }
            $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) ($line['text'] ?? ''))));
            if ($text !== '') {
                $state['history'][] = ['who' => $line['who'] === 'me' ? 'person' : 'assistant', 'text' => function_exists('mb_substr') ? mb_substr($text, 0, self::MAX_LINE) : substr($text, 0, self::MAX_LINE)];
            }
        }
        return $state;
    }

    /** The person's company name (their team member's company, else the site name), for the AI. */
    public static function company_name() {
        global $wpdb;
        $member = class_exists('TimeGrowTeamMemberModel') ? (new TimeGrowTeamMemberModel())->get_by_user_id(get_current_user_id()) : null;
        $table  = $wpdb->prefix . TIMEGROW_PREFIX . 'company_tracker';
        $name   = $member ? $wpdb->get_var($wpdb->prepare("SELECT name FROM {$table} WHERE ID = %d", (int) $member->company_id)) : null;
        return (string) ($name ?: $wpdb->get_var("SELECT name FROM {$table} ORDER BY ID LIMIT 1") ?: get_bloginfo('name'));
    }

    // ------------------------------------------------------------------
    // Follow-ups and refusals
    // ------------------------------------------------------------------

    /**
     * A clear follow-up to the last question, read without AI, for when the AI couldn't place it:
     * "the team" / "everyone" / the company → the whole team; "what about me" → the person;
     * "not a client" / "all clients" → no client or project filter; "I'm part of the team" → the same
     * question; a period word → that period. Null when the text is not such a follow-up. Pure.
     */
    public static function followup($text, $last, $company = '') {
        if (!is_array($last) || empty($last['name'])) {
            return null;
        }
        $t = strtolower((string) $text);
        $q = $last;
        $changed = false;
        $company_words = array_filter([strtolower(trim((string) $company)), strtolower(trim(preg_replace('/[,.]?\s*(llc|inc|ltd|corp|co)\.?$/i', '', (string) $company)))]);
        $mentions_company = false;
        foreach ($company_words as $w) {
            if ($w !== '' && strpos($t, $w) !== false) {
                $mentions_company = true;
            }
        }
        if (preg_match('/\bnot (a |my |our )?client\b|\ball (the )?clients\b|\ball (the )?projects\b|\bno (client|filter)\b/', $t)) {
            $q['client_id'] = null;
            $q['project_id'] = null;
            $changed = true;
        }
        if (preg_match('/\b(part of the team|i am (on|in) the team|i\'m (on|in|part of) the team)\b/', $t) && !$mentions_company) {
            $changed = true; // the team answer already counts them: repeat it
        } elseif ($mentions_company || preg_match('/\b(the (whole |entire )?team|all (the )?team members|everyone|everybody|all of us|whole company|entire company)\b/', $t)) {
            $q['who'] = 'team';
            if ($mentions_company) {
                $q['client_id'] = null;
                $q['project_id'] = null;
            }
            $changed = true;
        } elseif (preg_match('/\b(what about me|and me|just me|only me|my own|mine)\b/', $t)) {
            $q['who'] = 'me';
            $changed = true;
        }
        $period = self::period_in($t);
        if ($period) {
            $q['period'] = $period;
            $changed = true;
        }
        return $changed ? $q : null;
    }

    /** Team members for owners (ID and name), so a lookup can name a person; empty for others. */
    public static function team_members() {
        if (!self::is_owner() || !class_exists('TimeGrowTeamMemberModel')) {
            return [];
        }
        return array_map(function ($m) { return ['id' => (int) $m->ID, 'name' => (string) $m->name]; }, (array) (new TimeGrowTeamMemberModel())->select());
    }

    public static function denied_text($name) {
        if ($name === 'bulk') {
            return __('I can only record or add one thing at a time, and only after you confirm it. Changing or deleting saved records, or many at once, isn\'t possible here.', 'timegrow');
        }
        if ($name === 'clock') {
            return __('You are not allowed to clock in or out.', 'timegrow');
        }
        return in_array($name, ['add_client', 'add_project'], true)
            ? __('Only administrators can add clients and projects.', 'timegrow')
            : __('That information is only available to administrators.', 'timegrow');
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
        if (!TimeGrowNexusVoice::mobile_allowed()) {
            wp_send_json_error(['message' => __('The voice assistant is not turned on for you in the mobile app.', 'timegrow')], 403);
        }
        $type   = sanitize_key($_POST['type'] ?? '');
        $values = json_decode(wp_unslash($_POST['values'] ?? '{}'), true);
        $values = is_array($values) ? $values : [];
        TimeGrowAILog::from_request(); // saves the messages sent with this request
        // One record per request: plain values only, never lists of records
        foreach ($values as $v) {
            if (is_array($v) || is_object($v)) {
                TimeGrowAILog::system(__('Refused: a list of records was sent.', 'timegrow'), 'bulk');
                wp_send_json_error(['message' => self::denied_text('bulk')]);
            }
        }
        $problem = TimeGrowNexusVoice::approval_problem(['source' => 'voice'] + $_POST, TimeGrowNexusVoice::click_required());
        if ($problem !== '') {
            TimeGrowAILog::system(__('Refused: not approved with a click.', 'timegrow'), 'approval');
            wp_send_json_error(['message' => $problem]);
        }
        TimeGrowAILog::system(sprintf(__('Approved by %s.', 'timegrow'), TimeGrowNexusVoice::approval($_POST) ?: __('the person', 'timegrow')), 'approval');
        if (!TimeGrowAI::rate_limit_ok(get_current_user_id(), 'voice_create')) {
            wp_send_json_error(['message' => __('Too many clients and projects added by voice this hour. Use the Clients or Projects page, or try again later.', 'timegrow')]);
        }

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
                TimeGrowAILog::system(sprintf(__('Client not added: %s', 'timegrow'), $problem), 'refused');
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
            TimeGrowAILog::outcome(sprintf(__('Client added: %s', 'timegrow'), $c['name']));
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
                TimeGrowAILog::system(sprintf(__('Project not added: %s', 'timegrow'), $problem), 'refused');
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
            TimeGrowAILog::outcome(sprintf(__('Project added: %s', 'timegrow'), $p['name']));
            wp_send_json_success([
                'message' => sprintf(__('Project %1$s added for %2$s.', 'timegrow'), $p['name'], TimeGrowNexusVoiceAnswers::client_name($p['client_id'])),
                'project' => ['id' => (int) $id, 'name' => $p['name'], 'clientId' => (string) $p['client_id'], 'clientName' => TimeGrowNexusVoiceAnswers::client_name($p['client_id'])],
                'url'     => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-project-edit&id=' . (int) $id),
            ]);
        }

        wp_send_json_error(['message' => __('Unknown request.', 'timegrow')]);
    }
}
