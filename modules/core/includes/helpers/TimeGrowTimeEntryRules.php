<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rules every time entry save follows (Nexus Manual Time Entry, Clock In / Out, voice entry).
 *
 * The browser only suggests who and what: the team member is worked out from the logged-in user
 * (administrators may record for any team member), the project must be one that member may use
 * (any active project, or only assigned ones: Settings > General),
 * hours stay between a quarter hour and 24, and dates can't be in the future.
 */
class TimeGrowTimeEntryRules {

    const MAX_HOURS = 24;

    /** Settings > General > "Team members can record time on": All active projects (the default). */
    public static function everyone_on_all_projects() {
        return !class_exists('TimeGrowSettings') || TimeGrowSettings::team_project_access() === 'all';
    }

    /** Administrators may record time for anyone, on any active project. */
    public static function is_admin() {
        return current_user_can('administrator');
    }

    /**
     * The team member to record for: the posted one for administrators (when it exists),
     * otherwise the logged-in user's own team member. 0 when there is none.
     */
    public static function member_id($posted_member_id = 0) {
        $team = new TimeGrowTeamMemberModel();
        if (self::is_admin() && (int) $posted_member_id > 0 && $team->select((int) $posted_member_id)) {
            return (int) $posted_member_id;
        }
        $member = $team->get_by_user_id(get_current_user_id());
        return $member ? (int) $member->ID : 0;
    }

    /**
     * Projects the member may record time on: all active ones for administrators, and for everyone
     * when Settings > General says so; otherwise the member's assigned projects.
     *
     * @return int[]
     */
    public static function project_ids($member_id) {
        $team     = new TimeGrowTeamMemberModel();
        $projects = (self::is_admin() || self::everyone_on_all_projects())
            ? $team->get_projects_for_member(-1)
            : ($member_id ? $team->get_projects_for_member($member_id) : []);
        if (!is_array($projects)) {
            return []; // none, or a database error (already logged)
        }
        return array_values(array_unique(array_map(function ($p) { return (int) $p->ID; }, $projects)));
    }

    /**
     * Why a manual entry can't be saved, or '' when it can. Pure: no side effects.
     *
     * @param string $date  YYYY-MM-DD.
     * @param float  $hours Hours worked.
     * @param string $today Today, YYYY-MM-DD (site time).
     */
    public static function manual_problem($date, $hours, $today) {
        $d = DateTime::createFromFormat('!Y-m-d', (string) $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            return __('The date is not valid.', 'timegrow');
        }
        if ($date > $today) {
            return __('The date is in the future.', 'timegrow');
        }
        if (!is_numeric($hours) || (float) $hours <= 0 || (float) $hours > self::MAX_HOURS) {
            return sprintf(__('Hours must be more than 0 and no more than %d.', 'timegrow'), self::MAX_HOURS);
        }
        return '';
    }

    /**
     * Why a clock time can't be used, or '' when it can: valid, and not more than 10 minutes ahead
     * of the server (a phone's clock can run a little fast). Pure.
     *
     * @param string $time YYYY-MM-DD HH:MM:SS.
     * @param string $now  Now, same format (site time).
     */
    public static function clock_problem($time, $now) {
        $t = DateTime::createFromFormat('Y-m-d H:i:s', (string) $time);
        if (!$t || $t->format('Y-m-d H:i:s') !== $time) {
            return __('The clock time is not valid.', 'timegrow');
        }
        if (strtotime($time) > strtotime($now) + 10 * MINUTE_IN_SECONDS) {
            return __('The clock time is in the future.', 'timegrow');
        }
        return '';
    }

    /**
     * Check who and what for a save, and stop the request with an error when it isn't allowed.
     *
     * @param string $capability    The Nexus page capability (access_nexus_manual_entry or access_nexus_clock).
     * @param int    $posted_member Team member the browser sent.
     * @param int    $project_id    Project the browser sent.
     * @return int The team member to save for.
     */
    public static function require_member_and_project($capability, $posted_member, $project_id) {
        if (!current_user_can($capability) && !self::is_admin()) {
            wp_send_json_error(['message' => __('You are not allowed to record time.', 'timegrow')], 403);
        }
        $member_id = self::member_id($posted_member);
        if (!$member_id) {
            wp_send_json_error(['message' => __('Your user is not set up as a team member, so time can\'t be recorded for you.', 'timegrow')]);
        }
        if (!in_array((int) $project_id, self::project_ids($member_id), true)) {
            wp_send_json_error(['message' => __('That project is not one you can record time on.', 'timegrow')]);
        }
        return $member_id;
    }

    /**
     * True when the time entry belongs to the logged-in user's team member (any entry for administrators).
     */
    public static function owns_entry($entry_id) {
        if (self::is_admin()) {
            return true;
        }
        global $wpdb;
        $member_id = self::member_id(0);
        $owner     = $wpdb->get_var($wpdb->prepare(
            "SELECT member_id FROM {$wpdb->prefix}" . TIMEGROW_PREFIX . "time_entry_tracker WHERE ID = %d",
            (int) $entry_id
        ));
        return $member_id && (int) $owner === $member_id;
    }
}
