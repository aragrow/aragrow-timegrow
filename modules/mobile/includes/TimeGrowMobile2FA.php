<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Two-factor authentication for the mobile PIN login: the site's own, from Wordfence Login Security
 * (the plugin, or the module inside Wordfence). Nothing to set up in TimeGrow: a person who has 2FA on
 * for the site enters the same authenticator (or recovery) code after their PIN.
 *
 * - Not active: the PIN login works as before.
 * - Active, and the person has 2FA on: their code is asked for, and checked by Wordfence (a code works
 *   once).
 * - Active, and their role must use 2FA but they haven't set it up (after Wordfence's grace period):
 *   the PIN login is refused until they set it up with the normal login.
 */
class TimeGrowMobile2FA {

    /** True when Wordfence Login Security is running. */
    public static function active() {
        return class_exists('\WordfenceLS\Controller_Users') && class_exists('\WordfenceLS\Controller_TOTP');
    }

    /** True when the person has 2FA on for the site. */
    public static function enabled_for($user) {
        return self::active() && \WordfenceLS\Controller_Users::shared()->has_2fa_active($user);
    }

    /** True when the site requires 2FA for this person and they haven't set it up (grace period over). */
    public static function setup_missing($user) {
        if (!self::active() || self::enabled_for($user)) {
            return false;
        }
        $grace = false;
        return \WordfenceLS\Controller_Users::shared()->requires_2fa($user, $grace) && !$grace;
    }

    /** True when the authenticator or recovery code is right (Wordfence marks it used). */
    public static function verify($user, $code) {
        if (!self::enabled_for($user)) {
            return false;
        }
        return \WordfenceLS\Controller_TOTP::shared()->validate_2fa($user, trim((string) $code)) === true;
    }

    /** "On", "Not set up" or "" (site without Wordfence Login Security), for the Mobile Access page. */
    public static function status_text($user) {
        if (!self::active()) {
            return '';
        }
        return self::enabled_for($user) ? __('2FA on', 'timegrow') : __('2FA not set up', 'timegrow');
    }
}
