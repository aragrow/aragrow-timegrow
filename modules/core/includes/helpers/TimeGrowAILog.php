<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Saves AI activity for review (Settings > AI Conversations), for each AI tool whose settings
 * page has "Save for review" on:
 * - Voice AI: one session per conversation. The chat sends its messages (what the person said,
 *   what it showed) with each request, or on their own every few seconds and when it closes.
 * - Receipt Reader: one session per receipt read (start()).
 * The server adds how the AI read each message (its answer, what TimeGrow kept, tokens, time)
 * and what TimeGrow decided without it. A person can only write to their own sessions.
 */
class TimeGrowAILog {

    const MAX_TEXT      = 2000; // characters per message
    const MAX_PER_CALL  = 40;   // messages per request
    const MAX_TURNS     = 400;  // per session
    const PURGE_HOOK    = 'timegrow_ai_conversations_purge';

    private static $session = false; // this request's session row (false = not looked up yet)

    const VOICE_NONCE   = 'timegrow_voice_nonce'; // the voice chat's nonce (TimeGrowNexusVoice::NONCE)

    public static function init() {
        add_action('wp_ajax_timegrow_voice_log', [__CLASS__, 'handle_log']);
        add_action(self::PURGE_HOOK, [__CLASS__, 'purge']);
        add_action('admin_init', function () {
            if (!wp_next_scheduled(self::PURGE_HOOK)) {
                wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::PURGE_HOOK);
            }
        });
    }

    /** "Save for review" is on for this tool (on by default). */
    public static function enabled($feature) {
        if (!class_exists('TimeGrowSettings')) {
            return false;
        }
        $settings = $feature === 'receipts' ? TimeGrowSettings::receipt_settings() : TimeGrowSettings::voice_settings();
        return ($settings['save_for_review'] ?? 'yes') === 'yes';
    }

    /** Days to keep a tool's saved sessions (0 = keep them). */
    public static function keep_days($feature) {
        if (!class_exists('TimeGrowSettings')) {
            return 90;
        }
        $settings = $feature === 'receipts' ? TimeGrowSettings::receipt_settings() : TimeGrowSettings::voice_settings();
        return (int) ($settings['keep_days'] ?? 90);
    }

    // ------------------------------------------------------------------
    // Reading what the browser sent (pure)
    // ------------------------------------------------------------------

    /** A session key the chat made up: 8 to 64 letters, digits or dashes; '' otherwise. Pure. */
    public static function clean_key($key) {
        $key = (string) $key;
        return preg_match('/^[A-Za-z0-9-]{8,64}$/', $key) ? $key : '';
    }

    /**
     * Messages from the browser: [{who: "me"|"bot", text}] → [{who: "person"|"assistant", text}],
     * plain text, cut to MAX_TEXT, at most MAX_PER_CALL. Anything else is dropped. Pure apart
     * from wp_strip_all_tags().
     */
    public static function clean_turns($raw) {
        $list = is_string($raw) ? json_decode($raw, true) : $raw;
        $out  = [];
        foreach (array_slice(is_array($list) ? $list : [], 0, self::MAX_PER_CALL) as $t) {
            if (!is_array($t) || !in_array($t['who'] ?? '', ['me', 'bot'], true) || !is_scalar($t['text'] ?? null)) {
                continue;
            }
            $text = trim(wp_strip_all_tags((string) $t['text']));
            if ($text === '') {
                continue;
            }
            $out[] = ['who' => $t['who'] === 'me' ? 'person' : 'assistant', 'text' => function_exists('mb_substr') ? mb_substr($text, 0, self::MAX_TEXT) : substr($text, 0, self::MAX_TEXT)];
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Writing
    // ------------------------------------------------------------------

    private static $feature = 'voice'; // the tool of this request's session

    /** The prompt version and model a tool uses now, for the session record. */
    private static function context($feature = null) {
        $feature = $feature ?? self::$feature;
        $prompt = class_exists('TimeGrowAIPromptsPage') ? TimeGrowAIPromptsPage::in_use_label($feature) : '';
        $ai     = class_exists('TimeGrowAI') ? TimeGrowAI::config($feature) : ['ai_provider' => '', 'ai_model' => ''];
        $model  = $ai['ai_model'] !== '' ? TimeGrowAI::model_name($ai['ai_provider'], $ai['ai_model']) : '';
        return [$prompt, $model];
    }

    /**
     * The session named in this request (session_key), saving any messages sent with it
     * (log_turns). Null when saving is off, there is no key, or the key is someone else's.
     */
    public static function from_request() {
        if (self::$session !== false) {
            return self::$session;
        }
        self::$session = null;
        $key = self::clean_key($_POST['session_key'] ?? '');
        if (!$key || !self::enabled('voice') || !class_exists('TimeGrowAIConversationModel')) {
            return null;
        }
        self::$feature = 'voice';
        $model = new TimeGrowAIConversationModel();
        $model->maybe_create_tables();
        [$prompt, $ai_model] = self::context('voice');
        $row = $model->session_for($key, get_current_user_id(), $prompt, $ai_model, 'voice');
        if (!$row) {
            return null;
        }
        self::$session = $row;
        $turns = self::clean_turns(wp_unslash($_POST['log_turns'] ?? '[]'));
        $room  = self::MAX_TURNS - $model->turn_count($row['ID']);
        foreach (array_slice($turns, 0, max(0, $room)) as $t) {
            $model->add_turn($row['ID'], $t['who'], $t['text']);
        }
        return self::$session;
    }

    /**
     * Start a session made on the server (Receipt Reader: one per receipt read). Later ai(),
     * system() and outcome() calls in this request go to it. Null when saving is off.
     */
    public static function start($feature, $first_text = '') {
        self::$session = null;
        if (!self::enabled($feature) || !class_exists('TimeGrowAIConversationModel')) {
            return null;
        }
        self::$feature = $feature;
        $model = new TimeGrowAIConversationModel();
        $model->maybe_create_tables();
        [$prompt, $ai_model] = self::context($feature);
        $row = $model->session_for(wp_generate_uuid4(), get_current_user_id(), $prompt, $ai_model, $feature);
        if ($row && $first_text !== '') {
            $model->add_turn($row['ID'], 'person', $first_text);
        }
        return self::$session = $row;
    }

    /** End this request's session (Receipt Reader: after the reading). */
    public static function finish() {
        if (self::$session) {
            (new TimeGrowAIConversationModel())->end(self::$session['ID']);
        }
    }

    /** How the AI read a message: its answer, what TimeGrow kept, tokens and time. */
    public static function ai($intent, $raw, array $kept, ?array $usage = null, $ms = 0) {
        $s = self::current();
        if (!$s) {
            return;
        }
        [$prompt, $model] = self::context();
        (new TimeGrowAIConversationModel())->add_turn($s['ID'], 'ai', wp_json_encode($kept), substr((string) $intent, 0, 40), [
            'raw'    => $raw,
            'kept'   => $kept,
            'tokens' => (int) ($usage['total_tokens'] ?? 0),
            'in'     => (int) ($usage['prompt_tokens'] ?? 0),
            'out'    => (int) ($usage['completion_tokens'] ?? 0),
            'ms'     => (int) $ms,
            'prompt' => $prompt,
            'model'  => $model,
        ]);
    }

    /** Something TimeGrow decided without the AI (blocked, refused, read without AI, error). */
    public static function system($text, $intent = null) {
        $s = self::current();
        if ($s) {
            (new TimeGrowAIConversationModel())->add_turn($s['ID'], 'system', $text, $intent);
        }
    }

    /** A message from the person or what they were shown, said on the server's side. */
    public static function say($who, $text) {
        $s = self::current();
        if ($s && in_array($who, ['person', 'assistant'], true)) {
            (new TimeGrowAIConversationModel())->add_turn($s['ID'], $who, (string) $text);
        }
    }

    /** A result of the session ("Time saved", "Client added"). */
    public static function outcome($label) {
        $s = self::current();
        if ($s) {
            $m = new TimeGrowAIConversationModel();
            $m->add_turn($s['ID'], 'system', $label, 'saved');
            $m->add_outcome($s['ID'], $label);
        }
    }

    /** This request's session: one started on the server, else the one named in the request. */
    private static function current() {
        return self::$session ?: self::from_request();
    }

    /** AJAX timegrow_voice_log: messages sent on their own, and the end of a session. */
    public static function handle_log() {
        check_ajax_referer(self::VOICE_NONCE, 'nonce');
        if (!current_user_can('access_nexus_manual_entry') && !current_user_can('administrator')) {
            wp_send_json_error([], 403);
        }
        $s = self::current();
        if ($s && !empty($_POST['end'])) {
            (new TimeGrowAIConversationModel())->end($s['ID']);
        }
        wp_send_json_success();
    }

    /** Daily: delete each tool's sessions older than its "Keep for" days. */
    public static function purge() {
        if (!class_exists('TimeGrowAIConversationModel')) {
            return;
        }
        foreach (['voice', 'receipts'] as $feature) {
            $days = self::keep_days($feature);
            if ($days > 0) {
                (new TimeGrowAIConversationModel())->purge_older_than($days, $feature);
            }
        }
    }
}
