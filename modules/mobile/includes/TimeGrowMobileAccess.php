<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Who may use TimeGrow remotely, and what a mobile session may do.
 *
 * - The app token (/wp-json/jwt-auth/v2/auth): administrators and team members (allowed()).
 * - The mobile website (PIN login at /mobile-login): team members only (pin_allowed()); administrators
 *   use the normal login, with its two-factor authentication. Customers and other roles: neither.
 * The "timegrow_mobile_access_allowed" filter can change allowed() for one user.
 *
 * The mobile website is for the daily tasks: Clock In / Out, Manual Time Entry and Record Expenses
 * (receipts), plus the voice assistant and the receipt reader when the person was given them. A mobile
 * session is marked in the WordPress login session itself (stored on the server), so it can't be
 * removed in the browser and ends with the login.
 */
class TimeGrowMobileAccess {

    const ROLES = ['administrator', 'team_member'];
    const SESSION_KEY = 'timegrow_mobile'; // set on the WordPress session token by a PIN login

    /** AJAX actions a mobile session may call; anything else is refused. */
    const AJAX = ['save_time_entry', 'check_is_billable', 'timegrow_nexus_save_expense', 'analyze_receipt_realtime',
                  'timegrow_voice_parse', 'timegrow_voice_create', 'timegrow_voice_log', 'heartbeat'];

    /** Mobile capabilities an administrator can turn on per person (Mobile Access page, profile). */
    public static function capabilities() {
        return [
            'access_mobile_time_tracking' => __('Time tracking', 'timegrow'),
            'access_mobile_expenses'      => __('Expenses', 'timegrow'),
            'access_mobile_ai_voice'      => __('AI Voice', 'timegrow'),
            'access_mobile_ai_receipts'   => __('AI Receipt Reader', 'timegrow'),
        ];
    }

    public static function is_capability($cap) {
        return array_key_exists((string) $cap, self::capabilities());
    }

    /** True when the roles include administrator or team member. Pure. */
    public static function roles_allowed(array $roles) {
        return (bool) array_intersect(self::ROLES, $roles);
    }

    /** True for a team member who is not an administrator: the people who may use a PIN. Pure. */
    public static function pin_roles_allowed(array $roles) {
        return in_array('team_member', $roles, true) && !in_array('administrator', $roles, true);
    }

    /** True when this user (ID or WP_User) may use TimeGrow remotely (the app). */
    public static function allowed($user) {
        $user = $user instanceof WP_User ? $user : get_userdata((int) $user);
        if (!$user) {
            return false;
        }
        return (bool) apply_filters('timegrow_mobile_access_allowed', self::roles_allowed((array) $user->roles), $user);
    }

    /** True when this user (ID or WP_User) may have a PIN and log in to the mobile website. */
    public static function pin_allowed($user) {
        $user = $user instanceof WP_User ? $user : get_userdata((int) $user);
        return $user && self::allowed($user) && self::pin_roles_allowed((array) $user->roles);
    }

    /** True during a mobile session: the current login was made with a PIN. */
    public static function in_mobile_session() {
        static $cache = [];
        $user  = get_current_user_id();
        $token = function_exists('wp_get_session_token') ? wp_get_session_token() : '';
        if (!$user || $token === '') {
            return false;
        }
        if (!isset($cache[$user . $token])) {
            $session = WP_Session_Tokens::get_instance($user)->get($token);
            $cache[$user . $token] = is_array($session) && !empty($session[self::SESSION_KEY]);
        }
        return $cache[$user . $token];
    }

    /**
     * In a mobile session, whether the person was given this mobile capability. Outside one, true:
     * the normal rules for that tool apply.
     */
    public static function mobile_allows($cap) {
        return !self::in_mobile_session() || current_user_can($cap);
    }

    /**
     * The pages this person may open in a mobile session, in menu order (full slugs). Time tracking
     * opens their time entry method first (Clock In / Out, or Manual Time Entry).
     *
     * @param bool   $time     They have the Time tracking capability.
     * @param bool   $expenses They have the Expenses capability.
     * @param string $method   'clock' or 'manual' (profile: Time Entry Method).
     */
    public static function pages_for($time, $expenses, $method, $prefix = 'timegrow') {
        $pages = [];
        if ($time) {
            $pages = $method === 'manual' ? ['nexus-manual', 'nexus-clock'] : ['nexus-clock', 'nexus-manual'];
        }
        if ($expenses) {
            $pages[] = 'nexus-expenses';
        }
        return array_map(function ($p) use ($prefix) { return $prefix . '-' . $p; }, $pages);
    }

    /** pages_for() for the logged-in person. */
    public static function my_pages() {
        return self::pages_for(
            current_user_can('access_mobile_time_tracking'),
            current_user_can('access_mobile_expenses'),
            (string) get_user_meta(get_current_user_id(), 'timegrow_time_entry_method', true),
            TIMEGROW_PARENT_MENU
        );
    }

    /** Text for refusals; the same for every failed login, so it doesn't tell who has access. */
    public static function refused_message() {
        return __('Invalid username or PIN.', 'timegrow');
    }
}
