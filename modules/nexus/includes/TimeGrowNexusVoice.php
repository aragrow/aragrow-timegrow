<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Voice entry for the Manual Time Entry page.
 *
 * The browser turns speech into text (Web Speech API) and runs the chat: it asks for the client,
 * project, day, start time, hours, billable and description, one at a time. Each answer comes here
 * (AJAX timegrow_voice_parse) with the fields known so far. The active AI provider (Settings > AI,
 * the one used for receipts) reads the answer against the person's own projects and returns the
 * fields it heard; merge() checks every value before it goes back. Configuration and key: TimeGrowAI. Nothing is saved here: the
 * person confirms in the chat and the normal Manual form saves the entry.
 */
class TimeGrowNexusVoice {

    const NONCE  = 'timegrow_voice_nonce';
    const MAX_TRANSCRIPT = 500; // characters sent to the AI per answer
    const FIELDS = ['client_id', 'project_id', 'date', 'start_time', 'hours', 'billable', 'description'];

    public static function init() {
        add_action('wp_ajax_timegrow_voice_parse', [__CLASS__, 'handle_parse']);
    }

    // ------------------------------------------------------------------
    // Projects the person may use
    // ------------------------------------------------------------------

    /**
     * The current user's projects (all active ones for administrators), with their client.
     *
     * @return array[] { id, name, client_id, client_name, billable }
     */
    public static function candidates_for_current_user() {
        // Same projects the save allows (TimeGrowTimeEntryRules)
        $team     = new TimeGrowTeamMemberModel();
        $allowed  = TimeGrowTimeEntryRules::project_ids(TimeGrowTimeEntryRules::member_id(0));
        $all      = $team->get_projects_for_member(-1);
        $projects = array_filter(is_array($all) ? $all : [], function ($p) use ($allowed) {
            return in_array((int) $p->ID, $allowed, true);
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
        $fields = [];
        foreach (self::FIELDS as $key) {
            $fields[$key] = $known[$key] ?? null;
        }
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
            $text = trim(preg_replace('/\s+/', ' ', (string) $heard['description']));
            if ($text !== '') {
                $fields['description'] = function_exists('mb_substr') ? mb_substr($text, 0, 1000) : substr($text, 0, 1000);
            }
        }

        return $fields;
    }

    /**
     * Instructions for the AI. Pure.
     */
    public static function build_prompt($transcript, array $known, $asking, array $candidates, $today) {
        $weekday  = date('l', strtotime($today));
        $projects = wp_json_encode(array_map(function ($c) {
            return ['project_id' => $c['id'], 'project' => $c['name'], 'client_id' => $c['client_id'], 'client' => $c['client_name']];
        }, $candidates));
        $known_json = wp_json_encode($known);
        $asked      = $asking ? "The assistant just asked for: {$asking}." : 'This is the first thing the person said.';

        return <<<PROMPT
You read what a person said while recording their work time, and return the fields you heard.
Today is {$today} ({$weekday}).
The person's projects (only these may be used): {$projects}
Fields already known: {$known_json}
{$asked}
The person said: "{$transcript}"

Return only a JSON object with these keys, using null for anything not said:
- client_id: a client_id from the list when the person names a client (a close or partial name is fine).
- project_id: a project_id from the list. If several clients have a project with that name and the client is not known or said, use null.
- date: the day worked as YYYY-MM-DD. Work out words like "today", "yesterday" or "last Monday" from today's date.
- start_time: the start time as HH:MM in 24 hour time, or "skip" if they do not want to give one.
- hours: hours worked as a number, such as 2.5 for "two and a half hours" or 0.75 for "45 minutes". If they give a start and end time, use the difference.
- billable: true or false (yes / no answers to a billable question count).
- description: a short description of the work in the person's own words, starting with a capital letter. When the assistant asked for the description, use the whole answer.
PROMPT;
    }

    // ------------------------------------------------------------------
    // Calling the AI
    // ------------------------------------------------------------------

    /**
     * Send the prompt to the active AI (TimeGrowAI, with retries) and return the JSON object it answers with.
     *
     * @throws Exception "no_ai" when no AI is set up, otherwise the error.
     */
    public static function ask_ai($prompt) {
        $answer = TimeGrowAI::request(['feature' => 'voice', 'prompt' => $prompt, 'json' => true, 'max_tokens' => 300, 'timeout' => 20]);
        if (is_wp_error($answer)) {
            throw new Exception($answer->get_error_code() === 'no_ai' ? 'no_ai' : $answer->get_error_message());
        }
        return self::json_from_text($answer['text']);
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

        $transcript = sanitize_textarea_field(wp_unslash($_POST['transcript'] ?? ''));
        $transcript = function_exists('mb_substr') ? mb_substr($transcript, 0, self::MAX_TRANSCRIPT) : substr($transcript, 0, self::MAX_TRANSCRIPT);
        $asking     = sanitize_key($_POST['asking'] ?? '');
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
        if ($voice['enabled'] !== 'yes' || $voice['use_ai'] !== 'yes') {
            wp_send_json_error(['code' => 'no_ai', 'message' => __('Reading sentences with AI is turned off.', 'timegrow')]);
        }

        if (!TimeGrowAI::rate_limit_ok(get_current_user_id(), 'voice')) {
            wp_send_json_error(['code' => 'rate_limit', 'message' => __('Too many voice requests this hour. Answer the questions instead, or try again later.', 'timegrow')]);
        }

        $candidates = self::candidates_for_current_user();
        $today      = current_time('Y-m-d');

        try {
            $heard = self::ask_ai(self::build_prompt($transcript, $known, $asking, $candidates, $today));
        } catch (Exception $e) {
            if ($e->getMessage() !== 'no_ai') {
                timegrow_log('Voice entry AI error: ' . $e->getMessage());
            }
            wp_send_json_error(['code' => $e->getMessage() === 'no_ai' ? 'no_ai' : 'ai_error', 'message' => __('The AI could not read that.', 'timegrow')]);
        }

        // A known start time of "skip" or a known field stays unless a new valid value came in
        wp_send_json_success(['fields' => self::merge($known, $heard, $candidates, $today)]);
    }
}
