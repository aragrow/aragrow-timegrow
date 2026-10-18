<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The time entry audit trail (Reports > Time Entry Audit Log): every change to a time entry, who
 * made it, from where, and what changed.
 *
 * Actions: created, updated, clocked_out, billed (put on an invoice), unbilled (its invoice was
 * cancelled or deleted), rebilled (the invoice was reopened), deleted. TimeGrowTimeEntryModel calls
 * log() after each write; "changes" holds { field: [before, after] } and "snapshot" the entry after
 * the change (before it, for deleted). The trail starts when this is installed: older entries have no
 * history. Rows are never edited or removed by TimeGrow.
 */
class TimeGrowTimeEntryAudit {

    const DB_VERSION = '1';
    const ACTIONS    = ['created', 'updated', 'clocked_out', 'billed', 'unbilled', 'rebilled', 'deleted'];
    /** Fields not worth showing as a change. */
    const IGNORED    = ['ID', 'created_at', 'updated_at'];

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_audit_tracker';
    }

    /** Create the table when missing or older (no reactivation needed). */
    public static function maybe_create_table() {
        if (get_option('timegrow_time_entry_audit_db') === self::DB_VERSION) {
            return;
        }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta('CREATE TABLE ' . self::table() . " (
            ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entry_id bigint(20) unsigned NOT NULL,
            action varchar(20) NOT NULL,
            changes longtext,
            snapshot longtext,
            order_id bigint(20) unsigned DEFAULT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            source varchar(20) NOT NULL DEFAULT 'web',
            created_at datetime NOT NULL,
            PRIMARY KEY  (ID),
            KEY entry_id (entry_id),
            KEY created_at (created_at),
            KEY action (action)
        ) " . $wpdb->get_charset_collate() . ';');
        update_option('timegrow_time_entry_audit_db', self::DB_VERSION, false);
    }

    /**
     * What changed between two versions of an entry: { field: [before, after] }. Numbers that are
     * equal ("2" and "2.00") and empty values (null and "") are not changes. Pure.
     */
    public static function diff(array $before, array $after) {
        $norm = function ($v) {
            if ($v === null || $v === '') {
                return '';
            }
            return is_numeric($v) ? (string) (0 + $v) : (string) $v;
        };
        $out = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            if (in_array($key, self::IGNORED, true) || !array_key_exists($key, $after)) {
                continue; // a field not in the new version was not written
            }
            $old = $before[$key] ?? null;
            $new = $after[$key];
            if ($norm($old) !== $norm($new)) {
                $out[$key] = [$old, $new];
            }
        }
        return $out;
    }

    /** "updated", or "clocked_out" when the change is the clock out of an open clock entry. Pure. */
    public static function update_action(array $before, array $changes) {
        return isset($changes['clock_out_date']) && empty($before['clock_out_date']) ? 'clocked_out' : 'updated';
    }

    /** Where the change came from: voice, mobile, system (no one logged in, or scheduled), or web. */
    public static function source() {
        if (($_POST['source'] ?? '') === 'voice') {
            return 'voice';
        }
        if (class_exists('TimeGrowMobileAccess') && TimeGrowMobileAccess::in_mobile_session()) {
            return 'mobile';
        }
        if ((function_exists('wp_doing_cron') && wp_doing_cron()) || !get_current_user_id()) {
            return 'system';
        }
        return 'web';
    }

    /** An entry as an array (null when it doesn't exist). */
    public static function row($entry_id) {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker WHERE ID = %d', (int) $entry_id), ARRAY_A);
        return $row ?: null;
    }

    /**
     * Record one change. Never stops the save: a failure is only written to the debug log.
     *
     * @param array|null $before The entry before the change (null for created).
     * @param array|null $after  The entry after it (null for deleted).
     */
    public static function log($entry_id, $action, $before = null, $after = null, $order_id = null) {
        try {
            if (!in_array($action, self::ACTIONS, true) || (int) $entry_id <= 0) {
                return;
            }
            self::maybe_create_table();
            $changes = ($before !== null && $after !== null) ? self::diff($before, $after) : [];
            if (in_array($action, ['updated', 'clocked_out'], true) && !$changes) {
                return; // saved without changing anything
            }
            global $wpdb;
            $wpdb->insert(self::table(), [
                'entry_id'   => (int) $entry_id,
                'action'     => $action,
                'changes'    => $changes ? wp_json_encode($changes) : null,
                'snapshot'   => wp_json_encode($after ?? $before),
                'order_id'   => $order_id ? (int) $order_id : null,
                'user_id'    => get_current_user_id(),
                'source'     => self::source(),
                'created_at' => current_time('mysql'),
            ]);
        } catch (Throwable $e) {
            timegrow_log('Time entry audit: could not record ' . $action . ' for entry ' . (int) $entry_id . ': ' . $e->getMessage());
        }
    }

    /** Record the same billing action for several entries (one row each). */
    public static function log_many(array $entry_ids, $action, $order_id = null) {
        foreach (array_unique(array_map('intval', $entry_ids)) as $id) {
            $after = self::row($id);
            self::log($id, $action, null, $after, $order_id);
        }
    }

    /** "hours 2 → 3; billable yes → no", for the report. Pure. */
    public static function describe(array $changes) {
        $labels = ['project_id' => 'project', 'member_id' => 'team member', 'clock_in_date' => 'clock in', 'clock_out_date' => 'clock out',
                   'billed_order_id' => 'invoice', 'entry_type' => 'type'];
        $parts = [];
        foreach ($changes as $field => $pair) {
            [$old, $new] = array_pad((array) $pair, 2, null);
            $show = function ($v) use ($field) {
                if ($v === null || $v === '') {
                    return '(empty)';
                }
                if (in_array($field, ['billable', 'billed'], true)) {
                    return $v ? 'yes' : 'no';
                }
                if ($field === 'hours') {
                    return (string) (0 + $v);
                }
                $v = (string) $v;
                return mb_strlen($v) > 60 ? mb_substr($v, 0, 57) . '…' : $v;
            };
            $parts[] = ($labels[$field] ?? str_replace('_', ' ', $field)) . ' ' . $show($old) . ' → ' . $show($new);
        }
        return implode('; ', $parts);
    }
}
