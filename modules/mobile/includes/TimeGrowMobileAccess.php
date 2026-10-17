<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Who may use TimeGrow remotely, and the mobile capabilities.
 *
 * Only administrators and team members may log in remotely (mobile PIN login at /mobile-login and
 * the app token at /wp-json/jwt-auth/v2/auth) or be given a PIN. Customers, subscribers and any
 * other role are refused, even with a PIN created before this rule. The
 * "timegrow_mobile_access_allowed" filter can change the answer for one user.
 */
class TimeGrowMobileAccess {

    const ROLES = ['administrator', 'team_member'];

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

    /** True when this user (ID or WP_User) may use TimeGrow remotely. */
    public static function allowed($user) {
        $user = $user instanceof WP_User ? $user : get_userdata((int) $user);
        if (!$user) {
            return false;
        }
        return (bool) apply_filters('timegrow_mobile_access_allowed', self::roles_allowed((array) $user->roles), $user);
    }

    /** True during a mobile PIN session (the mobile app), where the mobile capabilities apply. */
    public static function in_mobile_session() {
        return isset($_COOKIE['timegrow_mobile_session']);
    }

    /**
     * In a mobile session, whether the person was given this mobile capability (AI Voice, AI
     * Receipt Reader). Outside one, true: the normal rules for that tool apply.
     */
    public static function mobile_allows($cap) {
        return !self::in_mobile_session() || current_user_can($cap);
    }

    /** Text for refusals; the same as a wrong PIN, so it doesn't tell who has access. */
    public static function refused_message() {
        return __('Invalid username or PIN.', 'timegrow');
    }
}
