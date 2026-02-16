<?php
/**
 * TimeGrow PIN Authenticator
 *
 * Handles PIN-based authentication and session management for mobile users
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

    /**
     * @var string Session cookie name
     */
    const COOKIE_NAME = 'timegrow_mobile_session';

    /**
     * @var int Session timeout in seconds (8 hours)
     */
    const SESSION_TIMEOUT = 28800;

    /**
     * @var int Max failed attempts before lockout
     */
    const MAX_FAILED_ATTEMPTS = 5;

    /**
     * @var int Lockout duration in seconds (15 minutes)
     */
    const LOCKOUT_DURATION = 900;

    /**
     * @var TimeGrowMobilePINModel
     */
    private $model;

    /**
     * Constructor
     */
    public function __construct() {
        $this->model = new TimeGrowMobilePINModel();
    }

    /**
     * Generate a random 6-character alphanumeric PIN
     *
     * @return string 6-character PIN
     */
    public function generate_pin() {
        $chars = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // Removed I and O to avoid confusion
        $pin = '';
        for ($i = 0; $i < 6; $i++) {
            $pin .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $pin;
    }

    /**
     * Create PIN for user
     *
     * @param int $user_id User ID
     * @param string $pin 6-character alphanumeric PIN
     * @return bool Success status
     */
    public function create_pin_for_user($user_id, $pin) {
        // Validate PIN format (6 alphanumeric characters)
        if (!preg_match('/^[A-Z0-9]{6}$/i', $pin)) {
            return false;
        }

        // Normalize to uppercase
        $pin = strtoupper($pin);

        // Generate salt
        $salt = bin2hex(random_bytes(32));

        // Hash PIN with salt
        $pin_hash = hash('sha256', $pin . $salt);

        // Store in database
        return $this->model->upsert($user_id, $pin_hash, $salt);
    }

    /**
     * Verify PIN for user
     *
     * @param int $user_id User ID
     * @param string $pin 6-character alphanumeric PIN to verify
     * @return array Result with 'success' boolean and 'message' string
     */
    public function verify_pin($user_id, $pin) {
        // Validate PIN format (6 alphanumeric characters)
        if (!preg_match('/^[A-Z0-9]{6}$/i', $pin)) {
            return [
                'success' => false,
                'message' => 'Invalid PIN format. Must be 6 characters (letters and numbers).',
            ];
        }

        // Normalize to uppercase
        $pin = strtoupper($pin);

        // Get PIN record
        $record = $this->model->get_by_user_id($user_id);

        if (!$record) {
            return [
                'success' => false,
                'message' => 'Mobile access not enabled for this user.',
            ];
        }

        // Check if account is active
        if (!$record->is_active) {
            return [
                'success' => false,
                'message' => 'Mobile access has been disabled.',
            ];
        }

        // Check if account is locked
        if ($this->is_account_locked($user_id, $record)) {
            $locked_until = strtotime($record->locked_until);
            $remaining = $locked_until - time();
            $minutes = ceil($remaining / 60);

            return [
                'success' => false,
                'message' => "Account locked for {$minutes} more minute(s). Too many failed attempts.",
                'locked_until' => $record->locked_until,
            ];
        }

        // Hash provided PIN with stored salt
        $pin_hash = hash('sha256', $pin . $record->pin_salt);

        // Verify PIN
        if (hash_equals($record->pin_hash, $pin_hash)) {
            // PIN is correct - reset failed attempts and update last login
            $this->model->reset_failed_attempts($user_id);
            $this->model->update_last_login($user_id);

            return [
                'success' => true,
                'message' => 'Login successful.',
            ];
        } else {
            // PIN is incorrect - increment failed attempts
            $this->model->increment_failed_attempts($user_id);

            $failed_attempts = $record->failed_attempts + 1;
            $remaining_attempts = self::MAX_FAILED_ATTEMPTS - $failed_attempts;

            // Lock account if max attempts reached
            if ($failed_attempts >= self::MAX_FAILED_ATTEMPTS) {
                $this->model->lock_account($user_id, self::LOCKOUT_DURATION);

                return [
                    'success' => false,
                    'message' => 'Account locked for 15 minutes due to too many failed attempts.',
                ];
            }

            return [
                'success' => false,
                'message' => "Invalid PIN. {$remaining_attempts} attempt(s) remaining.",
            ];
        }
    }

    /**
     * Check if account is locked
     *
     * @param int $user_id User ID
     * @param object|null $record PIN record (optional, will fetch if not provided)
     * @return bool True if locked
     */
    public function is_account_locked($user_id, $record = null) {
        if (!$record) {
            $record = $this->model->get_by_user_id($user_id);
        }

        if (!$record || !$record->locked_until) {
            return false;
        }

        $locked_until = strtotime($record->locked_until);
        $now = time();

        // If lockout period has expired, unlock account
        if ($now >= $locked_until) {
            $this->model->reset_failed_attempts($user_id);
            return false;
        }

        return true;
    }

    /**
     * Create mobile session
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function create_mobile_session($user_id) {
        // Create session data
        $session_data = [
            'user_id' => $user_id,
            'expires' => time() + self::SESSION_TIMEOUT,
            'nonce' => wp_create_nonce('timegrow_mobile_session_' . $user_id),
        ];

        // Encrypt session data
        $session_value = base64_encode(json_encode($session_data));

        // Set cookie
        $secure = is_ssl();
        $result = setcookie(
            self::COOKIE_NAME,
            $session_value,
            time() + self::SESSION_TIMEOUT,
            COOKIEPATH,
            COOKIE_DOMAIN,
            $secure,
            true // httpOnly
        );

        // Also log the user into WordPress
        if ($result) {
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);

            // Trigger WordPress login action
            do_action('wp_login', get_userdata($user_id)->user_login, get_userdata($user_id));
        }

        return $result;
    }

    /**
     * Validate mobile session
     *
     * @return array|false Session data if valid, false otherwise
     */
    public function validate_mobile_session() {
        if (!isset($_COOKIE[self::COOKIE_NAME])) {
            return false;
        }

        // Decrypt session data
        $session_data = json_decode(base64_decode($_COOKIE[self::COOKIE_NAME]), true);

        if (!$session_data || !isset($session_data['user_id'], $session_data['expires'], $session_data['nonce'])) {
            return false;
        }

        // Check expiration
        if (time() > $session_data['expires']) {
            $this->destroy_mobile_session();
            return false;
        }

        // Verify nonce
        if (!wp_verify_nonce($session_data['nonce'], 'timegrow_mobile_session_' . $session_data['user_id'])) {
            $this->destroy_mobile_session();
            return false;
        }

        return $session_data;
    }

    /**
     * Destroy mobile session
     *
     * @return bool Success status
     */
    public function destroy_mobile_session() {
        // Remove cookie
        $secure = is_ssl();
        setcookie(
            self::COOKIE_NAME,
            '',
            time() - 3600,
            COOKIEPATH,
            COOKIE_DOMAIN,
            $secure,
            true
        );

        // Also log out from WordPress
        wp_logout();

        return true;
    }
}
