<?php
/**
 * TimeGrow PIN Authenticator
 *
 * PIN login for the mobile website (team members only, TimeGrowMobileAccess::pin_allowed()).
 *
 * - PINs are 6 characters, made on the server, and stored with WordPress's password hash. PINs saved
 *   before that (salted SHA-256) still work and are re-hashed at their next login.
 * - 5 wrong PINs lock that person's PIN for 15 minutes; 20 failed logins from one address in 15
 *   minutes pause logins from it, so PINs can't be tried across many people.
 * - Every failure shows the same message, so the page doesn't tell who has a PIN.
 * - A login is a normal WordPress login of 8 hours, marked as mobile in its session on the server
 *   (TimeGrowMobileAccess::in_mobile_session()), so it can't be widened by removing a cookie.
 *
 * @package TimeGrow
 * @subpackage Mobile
 * @since 2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowPINAuthenticator {

    /** The cookie mobile logins used before 2.2.0 (only its presence was checked); cleared when seen. */
    const COOKIE_NAME = 'timegrow_mobile_session';

    const SESSION_TIMEOUT     = 28800; // 8 hours
    const MAX_FAILED_ATTEMPTS = 5;
    const LOCKOUT_DURATION    = 900;   // 15 minutes
    const MAX_ADDRESS_FAILURES = 20;   // per address, per LOCKOUT_DURATION
    const PIN_CHARS = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // no I or O

    /**
     * @var TimeGrowMobilePINModel
     */
    private $model;

    public function __construct() {
        $this->model = new TimeGrowMobilePINModel();
    }

    /** A random 6-character PIN (letters and numbers, no I or O). */
    public function generate_pin() {
        $pin = '';
        for ($i = 0; $i < 6; $i++) {
            $pin .= self::PIN_CHARS[random_int(0, strlen(self::PIN_CHARS) - 1)];
        }
        return $pin;
    }

    /** Save a PIN for a team member (stored hashed). False for anyone else or a malformed PIN. */
    public function create_pin_for_user($user_id, $pin) {
        if (!TimeGrowMobileAccess::pin_allowed($user_id) || !preg_match('/^[A-Z0-9]{6}$/i', $pin)) {
            return false;
        }
        return $this->model->upsert($user_id, wp_hash_password(strtoupper($pin)), '');
    }

    /** True when the PIN matches the record: the WordPress hash, or the salted SHA-256 of older PINs. */
    public static function pin_matches($pin, $record) {
        if ((string) $record->pin_salt !== '') {
            return hash_equals((string) $record->pin_hash, hash('sha256', $pin . $record->pin_salt));
        }
        return wp_check_password($pin, (string) $record->pin_hash);
    }

    /**
     * Check a PIN for a user. Every refusal has the same message, except a pause for too many tries.
     *
     * @return array { success, message }
     */
    public function verify_pin($user_id, $pin) {
        $refused = ['success' => false, 'message' => TimeGrowMobileAccess::refused_message()];
        if ($this->address_blocked()) {
            return ['success' => false, 'message' => __('Too many failed logins. Try again in 15 minutes.', 'timegrow')];
        }
        $pin = strtoupper(trim((string) $pin));
        $record = $user_id ? $this->model->get_by_user_id($user_id) : null;
        if (!preg_match('/^[A-Z0-9]{6}$/', $pin) || !$record || !$record->is_active || !TimeGrowMobileAccess::pin_allowed($user_id)) {
            $this->count_address_failure();
            return $refused;
        }
        if ($this->is_account_locked($user_id, $record)) {
            $this->count_address_failure();
            return ['success' => false, 'message' => __('Too many failed logins. Try again in 15 minutes.', 'timegrow')];
        }

        if (self::pin_matches($pin, $record)) {
            $this->model->reset_failed_attempts($user_id);
            $this->model->update_last_login($user_id);
            if ((string) $record->pin_salt !== '') {
                $this->model->upsert($user_id, wp_hash_password($pin), ''); // older PIN: re-hashed
            }
            return ['success' => true, 'message' => ''];
        }

        $this->model->increment_failed_attempts($user_id);
        $this->count_address_failure();
        if ((int) $record->failed_attempts + 1 >= self::MAX_FAILED_ATTEMPTS) {
            $this->model->lock_account($user_id, self::LOCKOUT_DURATION);
        }
        return $refused;
    }

    /** Whether the PIN is locked; an expired lock is cleared. */
    public function is_account_locked($user_id, $record = null) {
        $record = $record ?: $this->model->get_by_user_id($user_id);
        if (!$record || !$record->locked_until) {
            return false;
        }
        if (time() >= strtotime($record->locked_until . ' UTC')) {
            $this->model->reset_failed_attempts($user_id);
            return false;
        }
        return true;
    }

    // ------------------------------------------------------------------
    // Failed logins per address
    // ------------------------------------------------------------------

    private function address_key() {
        return 'timegrow_pin_fail_' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    }

    private function address_blocked() {
        return (int) get_transient($this->address_key()) >= self::MAX_ADDRESS_FAILURES;
    }

    private function count_address_failure() {
        set_transient($this->address_key(), (int) get_transient($this->address_key()) + 1, self::LOCKOUT_DURATION);
    }

    // ------------------------------------------------------------------
    // The session
    // ------------------------------------------------------------------

    /**
     * Log the person in to WordPress for 8 hours, with the session marked as mobile on the server.
     * Not "remember me": the login ends when the browser closes, or after 8 hours.
     */
    public function create_mobile_session($user_id) {
        $mark   = function ($info) { $info[TimeGrowMobileAccess::SESSION_KEY] = 1; return $info; };
        $length = function () { return self::SESSION_TIMEOUT; };
        add_filter('attach_session_information', $mark);
        add_filter('auth_cookie_expiration', $length);
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, false, is_ssl());
        remove_filter('attach_session_information', $mark);
        remove_filter('auth_cookie_expiration', $length);
        self::clear_legacy_cookie();

        $user = get_userdata($user_id);
        do_action('wp_login', $user->user_login, $user);
        return true;
    }

    /** End the session: the WordPress logout ends the login and its mobile mark. */
    public function destroy_mobile_session() {
        self::clear_legacy_cookie();
        wp_logout();
        return true;
    }

    /** Remove the cookie older mobile logins set (it no longer means anything). */
    public static function clear_legacy_cookie() {
        if (isset($_COOKIE[self::COOKIE_NAME]) && !headers_sent()) {
            setcookie(self::COOKIE_NAME, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }
        unset($_COOKIE[self::COOKIE_NAME]);
    }
}
