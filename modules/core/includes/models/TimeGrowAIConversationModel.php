<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Saved AI activity, for reviewing how the AI behaves (Settings > AI Conversations).
 *
 * A session is one Voice AI conversation (from opening the chat until it is closed or the page
 * reloads) or one Receipt Reader reading. The feature column says which.
 * Its turns are, in order: what the person said ("person"), what the chat said ("assistant"), how
 * the AI read a message ("ai": the AI's answer, what TimeGrow kept, tokens, time) and what TimeGrow
 * decided without the AI ("system": blocked, refused, read without AI, errors, saved records).
 * Owners rate sessions (1 to 5 stars and a note) and each AI reading (thumbs up or down).
 */
class TimeGrowAIConversationModel {

    const DB_VERSION = '1';
    const WHO        = ['person', 'assistant', 'ai', 'system'];

    private $wpdb;
    private $sessions;
    private $turns;

    public function __construct() {
        global $wpdb;
        $this->wpdb     = $wpdb;
        $this->sessions = $wpdb->prefix . TIMEGROW_PREFIX . 'ai_session_tracker';
        $this->turns    = $wpdb->prefix . TIMEGROW_PREFIX . 'ai_turn_tracker';
    }

    /** Create the tables when missing or older (admin_init; no reactivation needed). */
    public function maybe_create_tables() {
        if (get_option('timegrow_ai_conversation_db') === self::DB_VERSION) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $this->wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$this->sessions} (
            ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_key varchar(64) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            feature varchar(20) NOT NULL DEFAULT 'voice',
            prompt_label varchar(190) DEFAULT NULL,
            model varchar(100) DEFAULT NULL,
            started_at datetime NOT NULL,
            last_at datetime NOT NULL,
            ended_at datetime DEFAULT NULL,
            turns int unsigned NOT NULL DEFAULT 0,
            ai_calls int unsigned NOT NULL DEFAULT 0,
            tokens int unsigned NOT NULL DEFAULT 0,
            outcome varchar(255) NOT NULL DEFAULT '',
            rating tinyint DEFAULT NULL,
            rating_note text,
            rated_by bigint(20) unsigned DEFAULT NULL,
            rated_at datetime DEFAULT NULL,
            PRIMARY KEY  (ID),
            UNIQUE KEY session_key (session_key),
            KEY started_at (started_at),
            KEY user_id (user_id)
        ) {$charset};");
        dbDelta("CREATE TABLE {$this->turns} (
            ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            who varchar(10) NOT NULL,
            text text NOT NULL,
            intent varchar(40) DEFAULT NULL,
            data longtext,
            thumbs tinyint DEFAULT NULL,
            PRIMARY KEY  (ID),
            KEY session_id (session_id)
        ) {$charset};");
        update_option('timegrow_ai_conversation_db', self::DB_VERSION);
    }

    /**
     * The person's session with this key, created on first use. Another person's key is never
     * reused: null is returned instead.
     */
    public function session_for($key, $user_id, $prompt_label, $model, $feature = 'voice') {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->sessions} WHERE session_key = %s", $key), ARRAY_A);
        if ($row) {
            return (int) $row['user_id'] === (int) $user_id ? $row : null;
        }
        $now = current_time('mysql');
        $this->wpdb->insert($this->sessions, [
            'session_key'  => $key,
            'user_id'      => (int) $user_id,
            'feature'      => $feature,
            'prompt_label' => $prompt_label,
            'model'        => $model,
            'started_at'   => $now,
            'last_at'      => $now,
        ], ['%s', '%d', '%s', '%s', '%s', '%s', '%s']);
        return $this->wpdb->insert_id ? $this->get((int) $this->wpdb->insert_id) : null;
    }

    public function get($id) {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->sessions} WHERE ID = %d", (int) $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function add_turn($session_id, $who, $text, $intent = null, ?array $data = null) {
        if (!in_array($who, self::WHO, true)) {
            return 0;
        }
        $now = current_time('mysql');
        $this->wpdb->insert($this->turns, [
            'session_id' => (int) $session_id,
            'created_at' => $now,
            'who'        => $who,
            'text'       => (string) $text,
            'intent'     => $intent,
            'data'       => $data ? wp_json_encode($data) : null,
        ], ['%d', '%s', '%s', '%s', '%s', '%s']);
        $tokens = $data && isset($data['tokens']) ? (int) $data['tokens'] : 0;
        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->sessions} SET turns = turns + 1, ai_calls = ai_calls + %d, tokens = tokens + %d, last_at = %s WHERE ID = %d",
            $who === 'ai' ? 1 : 0, $tokens, $now, (int) $session_id
        ));
        return (int) $this->wpdb->insert_id;
    }

    public function turn_count($session_id) {
        return (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT turns FROM {$this->sessions} WHERE ID = %d", (int) $session_id));
    }

    /** Add a result ("Time saved", "Client added") to the session's outcome. */
    public function add_outcome($session_id, $label) {
        $row = $this->get($session_id);
        if (!$row) {
            return;
        }
        $list = array_filter(array_map('trim', explode(',', $row['outcome'])));
        $list[] = $label;
        $this->wpdb->update($this->sessions, ['outcome' => substr(implode(', ', $list), 0, 255)], ['ID' => (int) $session_id], ['%s'], ['%d']);
    }

    public function end($session_id) {
        $this->wpdb->update($this->sessions, ['ended_at' => current_time('mysql')], ['ID' => (int) $session_id, 'ended_at' => null], ['%s'], ['%d']);
    }

    /** Turns of a session, in order. */
    public function turns($session_id) {
        return (array) $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->turns} WHERE session_id = %d ORDER BY ID", (int) $session_id), ARRAY_A);
    }

    /**
     * Sessions, newest first, with at least one message.
     *
     * @param array $filter { rated: ''|'yes'|'no', user_id, prompt_label, flagged: bool }
     */
    public function sessions(array $filter, $limit, $offset) {
        [$where, $args] = $this->where($filter);
        $args[] = (int) $limit;
        $args[] = (int) $offset;
        return (array) $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT s.*, (SELECT COUNT(*) FROM {$this->turns} t WHERE t.session_id = s.ID AND t.thumbs = -1) AS thumbs_down
             FROM {$this->sessions} s WHERE {$where} ORDER BY s.started_at DESC, s.ID DESC LIMIT %d OFFSET %d", $args), ARRAY_A);
    }

    public function count_sessions(array $filter) {
        [$where, $args] = $this->where($filter);
        $sql = "SELECT COUNT(*) FROM {$this->sessions} s WHERE {$where}";
        return (int) ($args ? $this->wpdb->get_var($this->wpdb->prepare($sql, $args)) : $this->wpdb->get_var($sql));
    }

    private function where(array $filter) {
        $where = ['s.turns > 0'];
        $args  = [];
        if (!empty($filter['feature'])) { $where[] = 's.feature = %s'; $args[] = (string) $filter['feature']; }
        if (($filter['rated'] ?? '') === 'yes') $where[] = 's.rating IS NOT NULL';
        if (($filter['rated'] ?? '') === 'no')  $where[] = 's.rating IS NULL';
        if (!empty($filter['user_id'])) { $where[] = 's.user_id = %d'; $args[] = (int) $filter['user_id']; }
        if (!empty($filter['prompt_label'])) { $where[] = 's.prompt_label = %s'; $args[] = (string) $filter['prompt_label']; }
        if (!empty($filter['flagged'])) $where[] = "EXISTS (SELECT 1 FROM {$this->turns} t WHERE t.session_id = s.ID AND t.thumbs = -1)";
        return [implode(' AND ', $where), $args];
    }

    /** People and prompt versions that have sessions, for the filters. */
    public function filter_options() {
        return [
            'users'   => array_map('intval', (array) $this->wpdb->get_col("SELECT DISTINCT user_id FROM {$this->sessions} WHERE turns > 0")),
            'prompts' => (array) $this->wpdb->get_col("SELECT DISTINCT prompt_label FROM {$this->sessions} WHERE turns > 0 AND prompt_label IS NOT NULL ORDER BY prompt_label"),
        ];
    }

    /**
     * Per prompt version: sessions, rated sessions, average stars, AI readings, readings marked
     * thumbs down, and average tokens per AI call.
     */
    public function stats_by_prompt() {
        return (array) $this->wpdb->get_results(
            "SELECT s.feature, s.prompt_label,
                    COUNT(*) AS sessions,
                    SUM(s.rating IS NOT NULL) AS rated,
                    AVG(s.rating) AS avg_rating,
                    SUM(s.ai_calls) AS ai_calls,
                    SUM(s.tokens) AS tokens,
                    (SELECT COUNT(*) FROM {$this->turns} t JOIN {$this->sessions} s2 ON s2.ID = t.session_id WHERE t.thumbs = -1 AND s2.feature = s.feature AND s2.prompt_label <=> s.prompt_label) AS thumbs_down,
                    (SELECT COUNT(*) FROM {$this->turns} t JOIN {$this->sessions} s2 ON s2.ID = t.session_id WHERE t.thumbs = 1 AND s2.feature = s.feature AND s2.prompt_label <=> s.prompt_label) AS thumbs_up,
                    MAX(s.started_at) AS last_used
             FROM {$this->sessions} s WHERE s.turns > 0
             GROUP BY s.feature, s.prompt_label ORDER BY s.feature, last_used DESC", ARRAY_A);
    }

    public function rate($session_id, $rating, $note, $user_id) {
        return (bool) $this->wpdb->update($this->sessions,
            ['rating' => $rating ?: null, 'rating_note' => $note, 'rated_by' => (int) $user_id, 'rated_at' => current_time('mysql')],
            ['ID' => (int) $session_id], ['%d', '%s', '%d', '%s'], ['%d']);
    }

    /** Thumbs up (1), down (-1) or cleared (0) on one AI reading. */
    public function thumbs($turn_id, $value) {
        return (bool) $this->wpdb->update($this->turns, ['thumbs' => $value ?: null], ['ID' => (int) $turn_id, 'who' => 'ai'], ['%d'], ['%d', '%s']);
    }

    public function turn($turn_id) {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->turns} WHERE ID = %d", (int) $turn_id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    public function delete_session($session_id) {
        $this->wpdb->delete($this->turns, ['session_id' => (int) $session_id], ['%d']);
        return (bool) $this->wpdb->delete($this->sessions, ['ID' => (int) $session_id], ['%d']);
    }

    /** Delete a tool's sessions started more than $days days ago. @return int Sessions deleted. */
    public function purge_older_than($days, $feature) {
        $cutoff = date('Y-m-d H:i:s', strtotime(current_time('mysql')) - (int) $days * DAY_IN_SECONDS);
        $ids    = array_map('intval', (array) $this->wpdb->get_col($this->wpdb->prepare("SELECT ID FROM {$this->sessions} WHERE feature = %s AND started_at < %s", $feature, $cutoff)));
        foreach ($ids as $id) {
            $this->delete_session($id);
        }
        return count($ids);
    }
}
