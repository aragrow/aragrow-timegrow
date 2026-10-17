<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Saved AI prompt versions (Settings > AI Prompts): one row per version, for each AI tool.
 *
 * Status: draft (editable, can be deleted), active (in use; one per tool), archived (an earlier
 * active version, kept as history). Only drafts change; to change another version, duplicate it.
 */
class TimeGrowAIPromptModel {

    const DB_VERSION = '1';
    const STATUSES   = ['draft', 'active', 'archived'];

    private $wpdb;
    private $table;

    public function __construct() {
        global $wpdb;
        $this->wpdb  = $wpdb;
        $this->table = $wpdb->prefix . TIMEGROW_PREFIX . 'ai_prompt_tracker';
    }

    /** Create the table when it is missing or older (runs on admin_init; no reactivation needed). */
    public function maybe_create_table() {
        if (get_option('timegrow_ai_prompt_db') === self::DB_VERSION) {
            return;
        }
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $this->wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$this->table} (
            ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            feature varchar(20) NOT NULL,
            name varchar(190) NOT NULL,
            notes text,
            content longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'draft',
            created_by bigint(20) unsigned DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            activated_at datetime DEFAULT NULL,
            archived_at datetime DEFAULT NULL,
            PRIMARY KEY  (ID),
            KEY feature_status (feature, status)
        ) {$charset};");
        update_option('timegrow_ai_prompt_db', self::DB_VERSION);
    }

    /** All versions, newest first; one tool only when $feature is given. */
    public function all($feature = null) {
        if ($feature) {
            return (array) $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE feature = %s ORDER BY created_at DESC, ID DESC", $feature), ARRAY_A);
        }
        return (array) $this->wpdb->get_results("SELECT * FROM {$this->table} ORDER BY created_at DESC, ID DESC", ARRAY_A);
    }

    public function get($id) {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE ID = %d", (int) $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /** The active version for a tool, or null (then the built-in prompt is used). */
    public function active($feature) {
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table} WHERE feature = %s AND status = 'active' ORDER BY activated_at DESC LIMIT 1", $feature), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /** @return int New ID, or 0. */
    public function create($feature, $name, $content, $notes = '') {
        $now = current_time('mysql');
        $ok  = $this->wpdb->insert($this->table, [
            'feature'    => $feature,
            'name'       => $name,
            'notes'      => $notes,
            'content'    => $content,
            'status'     => 'draft',
            'created_by' => get_current_user_id() ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']);
        return $ok ? (int) $this->wpdb->insert_id : 0;
    }

    /** Change a draft. Active and archived versions are kept as they were. */
    public function update_draft($id, $name, $content, $notes = '') {
        return (bool) $this->wpdb->update($this->table,
            ['name' => $name, 'content' => $content, 'notes' => $notes, 'updated_at' => current_time('mysql')],
            ['ID' => (int) $id, 'status' => 'draft'],
            ['%s', '%s', '%s', '%s'], ['%d', '%s']);
    }

    /** Make a version the active one for its tool; the one active before is archived. */
    public function activate($id) {
        $row = $this->get($id);
        if (!$row || $row['status'] === 'active') {
            return (bool) $row;
        }
        $now = current_time('mysql');
        $this->wpdb->query($this->wpdb->prepare(
            "UPDATE {$this->table} SET status = 'archived', archived_at = %s, updated_at = %s WHERE feature = %s AND status = 'active'",
            $now, $now, $row['feature']
        ));
        return (bool) $this->wpdb->update($this->table,
            ['status' => 'active', 'activated_at' => $now, 'archived_at' => null, 'updated_at' => $now],
            ['ID' => (int) $id], ['%s', '%s', '%s', '%s'], ['%d']);
    }

    /** Stop using the active version; the built-in prompt is used until another is activated. */
    public function archive($id) {
        $now = current_time('mysql');
        return (bool) $this->wpdb->update($this->table,
            ['status' => 'archived', 'archived_at' => $now, 'updated_at' => $now],
            ['ID' => (int) $id, 'status' => 'active'], ['%s', '%s', '%s'], ['%d', '%s']);
    }

    /** Only drafts can be deleted: active and archived versions are the history. */
    public function delete_draft($id) {
        return (bool) $this->wpdb->delete($this->table, ['ID' => (int) $id, 'status' => 'draft'], ['%d', '%s']);
    }
}
