<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Voice time entry, from a mic icon on every admin page (when AI Voice Entry is on).
 *
 * The browser turns speech into text (Web Speech API) and runs the chat: it asks for the client,
 * project, day, hours, billable and description, one at a time (or the project and time to punch in or out). Each answer comes here
 * (AJAX timegrow_voice_parse) with the fields known so far. The active AI provider (Settings > AI,
 * the one used for receipts) reads the answer against the person's own projects and returns the
 * fields it heard; merge() checks every value before it goes back. Configuration and key: TimeGrowAI. Nothing is saved here: the
 * person confirms in the chat and the normal Manual form saves the entry.
 */
class TimeGrowNexusVoice {

    const NONCE  = 'timegrow_voice_nonce';
    /** What the person said in this request (after the assistant's name is removed). */
    private static $heard = '';
    /** The assistant's name when none is set (Settings > AI Voice Entry). */
    const AGENT_NAME = 'Sam Spade';

    /**
     * True when the AI read a message as a time entry but heard no detail and the words say nothing
     * about time or work ("tell me a joke"): not a time entry. "Log some time" still is one. Pure.
     */
    public static function off_topic_time($time, $text) {
        $heard = is_array($time) ? array_filter($time, function ($v) { return $v !== null && $v !== ''; }) : [];
        if ($heard) {
            return false;
        }
        return !preg_match('/\b(time|hours?|hrs?|minutes?|mins?|log|record|track|worked|work|working|entry|timesheet|billable|project|client|today|yesterday|morning|afternoon)\b/i', (string) $text);
    }

    // ------------------------------------------------------------------
    // A time entry read without AI (the names in it are checked by TimeGrowNexusVoiceNames)
    // ------------------------------------------------------------------

    /**
     * A time entry read without AI from a message the AI did not place: hours (and the day) when the
     * words clearly record time ("add 3 hours … today"); the names are checked as usual. Null otherwise. Pure.
     */
    public static function time_from_words($text, $today) {
        $t = strtolower((string) $text);
        if (!preg_match('/\b(add|log|record|enter|put|worked|spent|track)\b/', $t) || !preg_match('/(\d+(?:\.\d+)?)\s*(hours?|hrs?|h)\b|(\d+)\s*(minutes?|mins?)\b/', $t, $m)) {
            return null;
        }
        $hours = !empty($m[1]) ? (float) $m[1] : ((int) $m[3]) / 60;
        $date  = strpos($t, 'yesterday') !== false ? date('Y-m-d', strtotime($today . ' -1 day')) : (strpos($t, 'today') !== false ? $today : null);
        return ['hours' => $hours, 'date' => $date, 'unmatched' => array_filter(['client' => TimeGrowNexusVoiceNames::named_after($text, 'client'), 'project' => TimeGrowNexusVoiceNames::named_after($text, 'project')])];
    }

    /** The reply when a message tries to manipulate the assistant (found by the AI, or by the quick check before it). */
    public static function refusal_text() {
        return __("That looks like a prompt injection attempt, so I stopped and did nothing with it. It has been logged. I can only help with TimeGrow: recording your time, punching in and out, answering questions about your TimeGrow data and explaining how TimeGrow works.", 'timegrow');
    }

    /** Whether saving, punching in or out and creating need a click (Settings > AI Voice Entry). */
    public static function click_required() {
        return !class_exists('TimeGrowSettings') || (TimeGrowSettings::voice_settings()['confirm_click'] ?? 'yes') === 'yes';
    }

    /** How the person approved a save from the chat: click, spoken or typed ('' when not from the chat). Pure. */
    public static function approval(array $post) {
        $a = (string) ($post['approved'] ?? '');
        return in_array($a, ['click', 'spoken', 'typed'], true) ? $a : '';
    }

    /** Why a save from the chat is refused, or '': with "Saving needs a click" on, only a click approves. Pure. */
    public static function approval_problem(array $post, $click_required) {
        if (($post['source'] ?? '') !== 'voice' || !$click_required || self::approval($post) === 'click') {
            return '';
        }
        return __('Tap the button to approve it: saving by voice needs a click.', 'timegrow');
    }

    /** The assistant's name: the setting, else the default. */
    public static function agent_name() {
        $name = class_exists('TimeGrowSettings') ? (string) (TimeGrowSettings::voice_settings()['agent_name'] ?? '') : '';
        return $name !== '' ? $name : self::AGENT_NAME;
    }

    /** The last AI call's token use and time, for the conversation log. */
    private static $last_call = ['usage' => null, 'ms' => 0];
    const MAX_TRANSCRIPT = 500; // characters sent to the AI per answer
    const FIELDS = ['client_id', 'project_id', 'date', 'start_time', 'hours', 'billable', 'description'];

    public static function init() {
        add_action('wp_ajax_timegrow_voice_parse', [__CLASS__, 'handle_parse']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue']);
    }

    /** AI Voice Entry is on and the logged-in user may record time. */
    public static function available_for_current_user() {
        $voice = class_exists('TimeGrowSettings') ? TimeGrowSettings::voice_settings() : ['enabled' => 'no'];
        return $voice['enabled'] === 'yes'
            && (current_user_can('access_nexus_manual_entry') || current_user_can('administrator'))
            && self::mobile_allowed();
    }

    /** In the mobile app (PIN session), the voice assistant needs the AI Voice mobile capability. */
    public static function mobile_allowed() {
        return !class_exists('TimeGrowMobileAccess') || TimeGrowMobileAccess::mobile_allows('access_mobile_ai_voice');
    }

    /**
     * The mic icon and chat on every admin page, with the person's projects, so it works away
     * from the Manual Time Entry page too.
     */
    public static function enqueue() {
        if (!self::available_for_current_user()) {
            return;
        }
        $voice = TimeGrowSettings::voice_settings();
        wp_enqueue_style('timegrow-voice-entry', TIMEGROW_NEXUS_BASE_URI . 'assets/css/voice-entry.css', [], filemtime(TIMEGROW_NEXUS_BASE_DIR . 'assets/css/voice-entry.css'));
        // The chat (voice-entry.js) and its helpers: dates and hours, speaking and listening, approval, saving conversations
        $deps = ['jquery'];
        foreach (['text', 'speech', 'approval', 'log'] as $part) {
            wp_register_script('timegrow-voice-' . $part, TIMEGROW_NEXUS_BASE_URI . 'assets/js/voice-' . $part . '.js', [], filemtime(TIMEGROW_NEXUS_BASE_DIR . 'assets/js/voice-' . $part . '.js'), true);
            $deps[] = 'timegrow-voice-' . $part;
        }
        wp_enqueue_script('timegrow-nexus-voice', TIMEGROW_NEXUS_BASE_URI . 'assets/js/voice-entry.js', $deps, filemtime(TIMEGROW_NEXUS_BASE_DIR . 'assets/js/voice-entry.js'), true);
        wp_localize_script('timegrow-nexus-voice', 'timegrowVoice', [
            'ajaxUrl'      => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce(self::NONCE),
            'saveNonce'    => wp_create_nonce('timegrow_time_nexus_nonce'), // save_time_entry
            'today'        => current_time('Y-m-d'),
            'aiReady'      => $voice['use_ai'] === 'yes' && TimeGrowAI::is_configured('voice'),
            'speak'        => $voice['speak'] === 'yes',
            'now'          => current_time('mysql'), // site time, for punching in and out "now"
            'canClock'     => current_user_can('access_nexus_clock') || TimeGrowTimeEntryRules::is_admin(),
            'lang'         => TimeGrowSettings::voice_language(),
            'agentName'    => self::agent_name(),
            'confirmClick' => self::click_required(),
            'voiceName'    => (string) $voice['voice_name'], // the default voice; '' = the most natural one
            'projects'     => array_map(function ($c) {
                return ['id' => $c['id'], 'name' => $c['name'], 'clientId' => (string) $c['client_id'], 'clientName' => $c['client_name']];
            }, self::candidates_for_current_user()),
            // Questions and adding (TimeGrowNexusAssistant): only what this person may do
            'suggestions'   => TimeGrowNexusAssistant::suggestions(),
            'canAddClient'  => TimeGrowNexusAssistant::can_add_client(),
            'canAddProject' => TimeGrowNexusAssistant::can_add_project(),
            'allClients'    => TimeGrowNexusAssistant::all_clients(), // empty unless they may add projects
            'saveConversations' => class_exists('TimeGrowAILog') && TimeGrowAILog::enabled('voice'),
        ]);
    }

    // ------------------------------------------------------------------
    // Projects the person may use
    // ------------------------------------------------------------------

    /**
     * The current user's projects (all active ones for administrators), with their client. Only
     * projects whose client is in the database are offered, so every pick can be saved.
     *
     * @return array[] { id, name, client_id, client_name, billable }
     */
    public static function candidates_for_current_user() {
        // Same projects the save allows (TimeGrowTimeEntryRules)
        $team     = new TimeGrowTeamMemberModel();
        $allowed  = TimeGrowTimeEntryRules::project_ids(TimeGrowTimeEntryRules::member_id(0));
        $all      = $team->get_projects_for_member(-1);
        $projects = array_filter(is_array($all) ? $all : [], function ($p) use ($allowed) {
            return in_array((int) $p->ID, $allowed, true) && TimeGrowProjectPicker::client_exists($p->client_id);
        });

        $candidates = [];
        foreach ((array) $projects as $project) {
            $candidates[(int) $project->ID] = [
                'id'          => (int) $project->ID,
                'name'        => (string) $project->name,
                'client_id'   => (int) $project->client_id,
                'client_name' => TimeGrowProjectPicker::client_name($project->client_id),
                'billable'    => (int) ($project->billable ?? 1),
            ];
        }
        return array_values($candidates);
    }

    // ------------------------------------------------------------------
    // Checking what the AI heard (pure)
    // ------------------------------------------------------------------

    /**
     * Merge what the AI heard into the known fields, keeping only valid values. Pure: no side effects.
     *
     * - project_id must be one of the person's projects; it sets client_id to that project's client.
     * - client_id must be a client of one of their projects; a new client clears a project of another client.
     * - date is YYYY-MM-DD and not after $today. start_time is HH:MM (24 hour) or "skip".
     * - hours is above 0 and up to 24, rounded to the nearest quarter hour.
     * - billable becomes 1 or 0. description is trimmed to 1,000 characters.
     *
     * @param array  $known      Fields known so far.
     * @param array  $heard      Fields from the AI (null or missing = not heard).
     * @param array  $candidates From candidates_for_current_user().
     * @param string $today      Today, YYYY-MM-DD (site time).
     * @return array The merged fields (every key in FIELDS, null when unknown).
     */
    public static function merge(array $known, array $heard, array $candidates, $today) {
        $empty = array_fill_keys(self::FIELDS, null);
        // The known fields come from the browser: they get the same checks as the AI's answer
        $fields = self::apply($empty, $known, $candidates, $today);
        return self::apply($fields, $heard, $candidates, $today);
    }

    /** Copy each valid value in $heard over $fields (merge() rules). Pure. */
    private static function apply(array $fields, array $heard, array $candidates, $today) {
        $by_id   = [];
        $clients = [];
        foreach ($candidates as $c) {
            $by_id[(int) $c['id']]          = $c;
            $clients[(int) $c['client_id']] = true;
        }

        if (isset($heard['client_id']) && isset($clients[(int) $heard['client_id']])) {
            $fields['client_id'] = (int) $heard['client_id'];
            if ($fields['project_id'] && ($by_id[(int) $fields['project_id']]['client_id'] ?? null) !== $fields['client_id']) {
                $fields['project_id'] = null;
            }
        }
        if (isset($heard['project_id']) && isset($by_id[(int) $heard['project_id']])) {
            $fields['project_id'] = (int) $heard['project_id'];
            $fields['client_id']  = (int) $by_id[$fields['project_id']]['client_id'];
        }

        if (isset($heard['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $heard['date'])) {
            $d = DateTime::createFromFormat('!Y-m-d', $heard['date']);
            if ($d && $d->format('Y-m-d') === $heard['date'] && $heard['date'] <= $today) {
                $fields['date'] = $heard['date'];
            }
        }

        if (isset($heard['start_time'])) {
            $time = strtolower(trim((string) $heard['start_time']));
            if ($time === 'skip') {
                $fields['start_time'] = 'skip';
            } elseif (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
                $fields['start_time'] = sprintf('%02d:%02d', $m[1], $m[2]);
            }
        }

        if (isset($heard['hours']) && is_numeric($heard['hours'])) {
            $hours = round(((float) $heard['hours']) * 4) / 4;
            if ($hours > 0 && $hours <= 24) {
                $fields['hours'] = $hours;
            }
        }

        if (isset($heard['billable']) && $heard['billable'] !== '') {
            $fields['billable'] = filter_var($heard['billable'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        if (isset($heard['description'])) {
            $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $heard['description'])));
            if ($text !== '' && !TimeGrowAI::looks_like_injection($text)) {
                $fields['description'] = function_exists('mb_substr') ? mb_substr($text, 0, 1000) : substr($text, 0, 1000);
            }
        }

        return $fields;
    }

    // ------------------------------------------------------------------
    // Calling the AI
    // ------------------------------------------------------------------

    /** The privacy layer for this request (names of clients, team members and the company → stand-ins). */
    public static function privacy() {
        static $layer = null;
        if ($layer === null) {
            $layer = class_exists('TimeGrowAIPrivacy')
                ? TimeGrowAIPrivacy::for_site(class_exists('TimeGrowNexusAssistant') ? TimeGrowNexusAssistant::company_name() : '')
                : null;
            // A team member sees the real names of their own clients and themselves only
            if ($layer && class_exists('TimeGrowNexusAssistant') && !TimeGrowNexusAssistant::is_owner()) {
                $tokens = array_map(function ($c) { return '[Client ' . (int) $c['client_id'] . ']'; }, self::candidates_for_current_user());
                $member = class_exists('TimeGrowTimeEntryRules') ? TimeGrowTimeEntryRules::member_id(0) : 0;
                if ($member) {
                    $tokens[] = '[Person ' . (int) $member . ']';
                }
                $layer->restrict($tokens);
            }
        }
        return $layer;
    }

    /**
     * Send the prompt to the active AI (TimeGrowAI, with retries) and return the JSON object it answers with.
     *
     * @throws Exception "no_ai" when no AI is set up, otherwise the error.
     */
    public static function ask_ai($prompt, $system = null) {
        $started = microtime(true);
        // Identity data never reaches the AI: names become stand-ins like [Client 31], and come back after
        $privacy = self::privacy();
        if ($privacy) {
            $prompt = $privacy->mask($prompt);
        }
        $answer = TimeGrowAI::request(['feature' => 'voice', 'system' => $system ?? TimeGrowNexusVoicePrompt::time_instructions(), 'prompt' => $prompt, 'json' => true, 'max_tokens' => 300, 'timeout' => 20]);
        self::$last_call = ['usage' => is_wp_error($answer) ? null : ($answer['usage'] ?? null), 'ms' => (int) round((microtime(true) - $started) * 1000)];
        if (is_wp_error($answer)) {
            throw new Exception($answer->get_error_code() === 'no_ai' ? 'no_ai' : $answer->get_error_message());
        }
        $heard = self::json_from_text($answer['text']);
        return $privacy ? $privacy->unmask($heard) : $heard;
    }

    /**
     * The JSON object in an AI answer, also when wrapped in a code fence. Pure.
     */
    public static function json_from_text($text) {
        return TimeGrowAI::json_from_text($text);
    }

    // ------------------------------------------------------------------
    // AJAX
    // ------------------------------------------------------------------

    public static function handle_parse() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('access_nexus_manual_entry') && !current_user_can('administrator')) {
            wp_send_json_error(['message' => __('You are not allowed to record time.', 'timegrow')], 403);
        }
        if (!self::mobile_allowed()) {
            wp_send_json_error(['message' => __('The voice assistant is not turned on for you in the mobile app.', 'timegrow')], 403);
        }

        TimeGrowAILog::from_request(); // saves the messages sent with this request, when saving is on

        $transcript = sanitize_textarea_field(wp_unslash($_POST['transcript'] ?? ''));
        $transcript = function_exists('mb_substr') ? mb_substr($transcript, 0, self::MAX_TRANSCRIPT) : substr($transcript, 0, self::MAX_TRANSCRIPT);
        $asking     = sanitize_key($_POST['asking'] ?? '');
        if ($asking !== 'description') {
            $transcript = self::without_address($transcript); // "Sam, how many hours..." → "how many hours..."
        }
        self::$heard = $transcript; // for help questions the AI did not word
        $known_json = wp_unslash($_POST['known'] ?? '{}');
        $known_raw  = strlen($known_json) <= 4000 ? json_decode($known_json, true) : [];
        $known      = [];
        foreach (self::FIELDS as $key) {
            $known[$key] = is_array($known_raw) && isset($known_raw[$key]) ? sanitize_text_field((string) $known_raw[$key]) : null;
        }
        if ($transcript === '') {
            wp_send_json_error(['message' => __('Nothing was heard.', 'timegrow')]);
        }

        $voice = class_exists('TimeGrowSettings') ? TimeGrowSettings::voice_settings() : ['enabled' => 'yes', 'use_ai' => 'yes'];
        if ($voice['enabled'] !== 'yes') {
            wp_send_json_error(['code' => 'off', 'message' => __('Voice entry is turned off.', 'timegrow')]);
        }

        // Direct prompt injection: words that read like instructions to the AI are not sent to it.
        // The chat goes on without AI (it asks the questions and reads short answers itself).
        if (TimeGrowAI::looks_like_injection($transcript)) {
            TimeGrowAILog::injection('voice', $transcript, __('the quick check (not sent to the AI)', 'timegrow'));
            wp_send_json_error(['code' => 'blocked', 'message' => self::refusal_text()]);
        }

        // No deleting, and no changing or adding many records at once: refused before the AI is asked
        if ($asking !== 'description' && TimeGrowNexusAssistant::looks_like_bulk($transcript)) { // a description describes work, not a request
            timegrow_log(sprintf('Voice entry: bulk or delete request from user %d refused', get_current_user_id()));
            TimeGrowAILog::system(__('Refused before the AI: deleting, or changing or adding many records.', 'timegrow'), 'bulk');
            self::send_intent(['intent' => 'denied', 'question' => ['name' => 'bulk']], []);
        }

        $candidates = self::candidates_for_current_user();
        $today      = current_time('Y-m-d');
        $first      = $asking === ''; // the person's first request: time, a question, or adding something
        $questions  = TimeGrowNexusAssistant::allowed_questions();
        $actions    = TimeGrowNexusAssistant::allowed_actions();
        $clients    = self::clients_to_name($candidates);
        $ai_on      = $voice['use_ai'] === 'yes' && TimeGrowAI::is_configured('voice') && empty($_POST['local_only']);

        // With AI: a conversation. Every message is read with what came before.
        $conversation_json = wp_unslash($_POST['conversation'] ?? '');
        if ($ai_on && $conversation_json !== '' && strlen($conversation_json) <= 16000) {
            self::converse($transcript, json_decode($conversation_json, true), $candidates, $clients, $questions, $actions, $today);
        }

        // Without AI (or when it can't be reached): plain requests ("how many hours this week",
        // "add a client") are read here
        if ($first && !$ai_on) {
            $local = TimeGrowNexusAssistant::local_intent($transcript, $questions, $actions, $clients);
            if ($local) {
                TimeGrowAILog::system(sprintf(__('Read without AI: %s', 'timegrow'), $local['intent'] . (isset($local['question']['name']) ? ' ' . $local['question']['name'] : '')), 'local');
                self::send_intent($local, $candidates);
            }
        }

        if (!$ai_on) {
            if ($first) {
                TimeGrowAILog::system(__('No AI: the chat asks the questions.', 'timegrow'), 'no_ai');
                wp_send_json_success(['intent' => 'record_time', 'fields' => self::merge($known, [], $candidates, $today)]); // the chat asks the questions
            }
            wp_send_json_error(['code' => 'no_ai', 'message' => __('Reading sentences with AI is turned off.', 'timegrow')]);
        }

        if (!TimeGrowAI::rate_limit_ok(get_current_user_id(), 'voice')) {
            wp_send_json_error(['code' => 'rate_limit', 'message' => __('Too many voice requests this hour. Answer the questions instead, or try again later.', 'timegrow')]);
        }

        try {
            $heard = $first
                ? self::ask_ai(TimeGrowNexusVoicePrompt::first_prompt($transcript, $candidates, $clients, $questions, $actions, $today), TimeGrowNexusVoicePrompt::first_instructions())
                : self::ask_ai(TimeGrowNexusVoicePrompt::time_prompt($transcript, $known, $asking, $candidates, $today));
        } catch (Exception $e) {
            if ($e->getMessage() !== 'no_ai') {
                timegrow_log('Voice entry AI error: ' . $e->getMessage());
            }
            wp_send_json_error(['code' => $e->getMessage() === 'no_ai' ? 'no_ai' : 'ai_error', 'message' => __('The AI could not read that.', 'timegrow')]);
        }

        if ($first) {
            // The AI only picks from the allowed list; anything else is "unknown" or "denied"
            $intent = TimeGrowNexusAssistant::check_intent($heard, $questions, $actions, $clients, $candidates);
            if ($intent['intent'] === 'injection') {
                TimeGrowAILog::injection('voice', $transcript, __('the AI', 'timegrow'));
                wp_send_json_error(['code' => 'blocked', 'message' => self::refusal_text()]);
            }
            if ($intent['intent'] !== 'record_time') {
                TimeGrowAILog::ai($intent['intent'], $heard, $intent, self::$last_call['usage'], self::$last_call['ms']);
                self::send_intent($intent, $candidates);
            }
        }

        // A known start time of "skip" or a known field stays unless a new valid value came in
        $fields = self::merge($known, $heard, $candidates, $today);
        TimeGrowAILog::ai('time', $heard, ['intent' => 'time', 'fields' => $fields], self::$last_call['usage'], self::$last_call['ms']);
        wp_send_json_success(['intent' => 'record_time', 'fields' => $fields]);
    }

    /**
     * One message in the conversation: the AI reads it with the state and history, TimeGrow checks
     * what it picked and answers. Ends the request.
     */
    private static function converse($transcript, $conversation, array $candidates, array $clients, array $questions, array $actions, $today) {
        if (!TimeGrowAI::rate_limit_ok(get_current_user_id(), 'voice')) {
            TimeGrowAILog::system(__('Hourly AI limit reached.', 'timegrow'), 'rate_limit');
            wp_send_json_error(['code' => 'rate_limit', 'message' => __('Too many voice requests this hour. Answer the questions instead, or try again later.', 'timegrow')]);
        }
        $state = TimeGrowNexusAssistant::read_state($conversation, $candidates, $clients, $questions, $actions, $today);
        try {
            $heard = self::ask_ai(
                TimeGrowNexusVoicePrompt::conversation_prompt($transcript, $state, $candidates, $clients, $questions, $actions, $today, TimeGrowNexusAssistant::company_name(),
                    TimeGrowNexusAssistant::team_members(), TimeGrowNexusAssistant::is_owner() ? 'owner' : 'member'),
                TimeGrowNexusVoicePrompt::conversation_instructions()
            );
        } catch (Exception $e) {
            if ($e->getMessage() !== 'no_ai') {
                timegrow_log('Voice entry AI error: ' . $e->getMessage());
            }
            TimeGrowAILog::system(sprintf(__('AI error: %s', 'timegrow'), $e->getMessage()), 'ai_error');
            wp_send_json_error(['code' => 'ai_error', 'message' => __('The AI could not read that.', 'timegrow')]);
        }
        $intent = TimeGrowNexusAssistant::check_intent($heard, $questions, $actions, $clients, $candidates);
        if ($intent['intent'] === 'injection') {
            TimeGrowAILog::ai('injection', $heard, $intent, self::$last_call['usage'], self::$last_call['ms']);
            TimeGrowAILog::injection('voice', $transcript, __('the AI', 'timegrow'));
            wp_send_json_error(['code' => 'blocked', 'message' => self::refusal_text()]);
        }
        // "Punch in" the AI read as time without hours, or didn't place: it is a clock in or out
        $action = TimeGrowNexusAssistant::clock_action($transcript);
        if ($action && ($intent['intent'] === 'unknown' || ($intent['intent'] === 'record_time' && empty($heard['time']['hours'])))) {
            TimeGrowAILog::system(__('Read as punching in or out, without AI.', 'timegrow'), 'local');
            self::send_intent(['intent' => 'clock_' . $action, 'clock' => []], $candidates);
        }
        // Nothing in progress, no detail heard and no words about time: not a time entry ("tell me a joke")
        if ($intent['intent'] === 'record_time' && $state['mode'] === 'idle' && self::off_topic_time($heard['time'] ?? null, $transcript)) {
            TimeGrowAILog::system(__('Not about TimeGrow: no time entry was started.', 'timegrow'), 'off_topic');
            $intent = ['intent' => 'unknown'];
        }
        // A time entry the AI gave up on because the client or project is not in the lists: still a time entry
        if ($intent['intent'] === 'unknown' && $state['mode'] === 'idle' && ($guess = self::time_from_words($transcript, $today))) {
            TimeGrowAILog::system(__('Read as a time entry without AI (the AI did not place it).', 'timegrow'), 'local');
            $intent = ['intent' => 'record_time'];
            $heard  = ['time' => $guess];
        }
        if ($intent['intent'] === 'record_time') {
            $time = is_array($heard['time'] ?? null) ? $heard['time'] : [];
            // Never a client or project the person did not name: the AI's pick must match their words
            [$time, $not_found] = TimeGrowNexusVoiceNames::check_names($time, $transcript, $state, $candidates, $clients);
            $fields = self::merge($state['time'], $time, $candidates, $today);
            foreach (['client' => ['client_id', 'project_id'], 'project' => ['project_id']] as $what => $keys) {
                if (!empty($not_found[$what])) {
                    foreach ($keys as $k) {
                        $fields[$k] = null; // they named one that is not theirs: no other one is kept or chosen
                    }
                }
            }
            if ($not_found) {
                TimeGrowAILog::system(sprintf(__('Not found: %s. Nothing else was chosen.', 'timegrow'), implode(', ', array_map(function ($k, $v) { return "$k \"$v\""; }, array_keys($not_found), $not_found))), 'not_found');
            }
            TimeGrowAILog::ai('time', $heard, ['intent' => 'time', 'fields' => $fields, 'not_found' => $not_found], self::$last_call['usage'], self::$last_call['ms']);
            wp_send_json_success(['intent' => 'time', 'fields' => $fields, 'not_found' => (object) $not_found]);
        }
        TimeGrowAILog::ai($intent['intent'], $heard, $intent, self::$last_call['usage'], self::$last_call['ms']);
        // A clear follow-up the AI couldn't place ("I mean all the team members"): build it on the last question
        if ($intent['intent'] === 'unknown' && !empty($state['last_question'])) {
            $next = TimeGrowNexusAssistant::followup($transcript, $state['last_question'], TimeGrowNexusAssistant::company_name());
            if ($next) {
                TimeGrowAILog::system(__('Read as a follow-up to the last question, without AI.', 'timegrow'), 'local');
                self::send_intent(['intent' => 'question', 'question' => $next], $candidates);
            }
        }
        // A name that fits several clients ("Copperleaf"): ask which one instead of "I can't answer that"
        if ($intent['intent'] === 'unknown' && self::privacy() && ($groups = self::privacy()->ambiguous(self::privacy()->mask($transcript)))) {
            $names = $groups[0];
            wp_send_json_success(['intent' => 'question', 'answer' => [
                'text'   => __('Which one do you mean?', 'timegrow'),
                'spoken' => sprintf(__('Which one do you mean? I found %d.', 'timegrow'), count($names)),
                'rows'   => array_map(function ($n) { return ['title' => $n, 'meta' => '', 'url' => '']; }, $names),
            ]]);
        }
        self::send_intent($intent, $candidates);
    }

    /**
     * Whether the person is clocked in now: { clocked_in, entry_id, project, since (HH:MM), since_text }.
     * Read when they punch in or out, so the chat can say "you're already clocked in".
     */
    public static function clock_status() {
        $member = TimeGrowTimeEntryRules::member_id(0);
        $s = $member ? (new TimeGrowTeamMemberModel())->get_team_member_clock_status($member) : [];
        if (($s['status'] ?? '') !== 'clocked_in') {
            return ['clocked_in' => false, 'member' => (bool) $member];
        }
        $since = (int) $s['clockInTimestamp'];
        return [
            'clocked_in' => true,
            'member'     => true,
            'entry_id'   => (int) $s['entryId'],
            'project'    => (string) $s['cloked_project'],
            'since'      => date('Y-m-d H:i:s', $since),
            'since_text' => date_i18n(get_option('time_format'), $since),
        ];
    }

    /**
     * The words without the assistant's name in front ("Hey Sam,", "Sam Spade:", "OK Sam"), so the
     * request reads the same either way. Only a leading name is removed. Pure.
     */
    public static function without_address($text, $agent = null) {
        $parts = array_map(function ($w) { return preg_quote($w, '/'); }, preg_split('/\s+/', trim($agent ?? self::agent_name())));
        $name  = $parts[0] . (count($parts) > 1 ? '(?:\s+' . implode('\s+', array_slice($parts, 1)) . ')?' : '');
        $out   = preg_replace('/^\s*(?:(?:hey|hi|hello|ok|okay)[\s,]+)?' . $name . '\b[\s,:;.!-]*/iu', '', (string) $text);
        return preg_match('/[\p{L}\p{N}]/u', (string) $out) ? $out : (string) $text; // just "Sam?" stays as it is
    }

    /** Clients the person may name: their projects' clients, plus every client for owners adding projects. */
    private static function clients_to_name(array $candidates) {
        $clients = [];
        foreach (array_merge(TimeGrowNexusAssistant::my_clients($candidates), TimeGrowNexusAssistant::all_clients()) as $c) {
            $clients[$c['id']] = $c;
        }
        return array_values($clients);
    }

    /** Answer a question, refuse, or start adding a client or project; ends the request. */
    private static function send_intent(array $intent, array $candidates) {
        switch ($intent['intent']) {
            case 'question':
                if (($intent['question']['name'] ?? '') === 'help' && empty($intent['question']['help'])) {
                    $intent['question']['help'] = self::$heard; // search the help with their own words
                }
                $question = TimeGrowNexusVoiceAnswers::effective_question($intent['question']); // remembered as answered, period included
                wp_send_json_success(['intent' => 'question', 'question' => $question, 'answer' => TimeGrowNexusVoiceAnswers::answer($question, $candidates)]);
            case 'denied':
                $text = TimeGrowNexusAssistant::denied_text($intent['question']['name'] ?? '');
                wp_send_json_success(['intent' => 'denied', 'answer' => ['text' => $text, 'spoken' => $text, 'rows' => []]]);
            case 'add_client':
                wp_send_json_success(['intent' => 'add_client', 'draft' => $intent['new_client'] ?? []]);
            case 'add_project':
                wp_send_json_success(['intent' => 'add_project', 'draft' => $intent['new_project'] ?? []]);
            case 'clock_in':
            case 'clock_out':
                if (!current_user_can('access_nexus_clock') && !TimeGrowTimeEntryRules::is_admin()) {
                    $text = TimeGrowNexusAssistant::denied_text('clock');
                    wp_send_json_success(['intent' => 'denied', 'answer' => ['text' => $text, 'spoken' => $text, 'rows' => []]]);
                }
                wp_send_json_success(['intent' => $intent['intent'], 'clock' => $intent['clock'] ?? [], 'status' => self::clock_status()]);
            case 'confirm':
            case 'change':
            case 'cancel':
                wp_send_json_success(['intent' => $intent['intent']]);
        }
        wp_send_json_success(['intent' => 'unknown']);
    }
}
