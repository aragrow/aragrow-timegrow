<?php
/**
 * TimeGrow Mobile 2FA Integration (Wordfence)
 *
 * Integrates with Wordfence 2FA for mobile PIN logins
 * Uses existing Wordfence 2FA setup - no separate configuration needed
 *
 * @package TimeGrow
 * @subpackage Mobile
 * @since 2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowMobile2FA {

    /**
     * @var TimeGrowMobilePINModel
     */
    private static $model;

    /**
     * Initialize 2FA support
     */
    public static function init() {
        self::$model = new TimeGrowMobilePINModel();
    }

    /**
     * Check if Wordfence is active and available
     *
     * @return bool True if Wordfence is active
     */
    public static function is_wordfence_active() {
        return class_exists('wfConfig') && class_exists('wfUtils');
    }

    /**
     * Check if 2FA is required for mobile login
     * Uses Wordfence 2FA if available and enabled for user
     *
     * @param int $user_id User ID
     * @return bool True if 2FA is required
     */
    public static function is_2fa_required($user_id) {
        // Check if mobile PIN requires 2FA
        $require_mobile_2fa = get_user_meta($user_id, 'timegrow_mobile_require_2fa', true);

        if ($require_mobile_2fa !== '1') {
            return false;
        }

        // Check if Wordfence is available
        if (!self::is_wordfence_active()) {
            return false;
        }

        // Check if user has Wordfence 2FA enabled
        return self::has_wordfence_2fa($user_id);
    }

    /**
     * Check if user has Wordfence 2FA enabled
     *
     * @param int $user_id User ID
     * @return bool True if Wordfence 2FA is enabled
     */
    public static function has_wordfence_2fa($user_id) {
        if (!self::is_wordfence_active()) {
            return false;
        }

        // Check if user has Wordfence TOTP enabled
        $totp_secret = get_user_meta($user_id, 'wf_2fa_totp', true);

        if (!empty($totp_secret)) {
            return true;
        }

        // Check if user has Wordfence backup codes
        $recovery_codes = get_user_meta($user_id, 'wf_2fa_recovery', true);

        if (!empty($recovery_codes)) {
            return true;
        }

        return false;
    }

    /**
     * Verify TOTP code using Wordfence
     *
     * @param int $user_id User ID
     * @param string $code 6-digit TOTP code or recovery code
     * @return array Result with 'success' and 'message'
     */
    public static function verify_code($user_id, $code) {
        if (!self::is_wordfence_active()) {
            return [
                'success' => false,
                'message' => 'Wordfence 2FA is not available.',
            ];
        }

        // Try verifying as TOTP code first
        if (self::verify_totp_code($user_id, $code)) {
            return [
                'success' => true,
                'message' => '2FA code verified.',
            ];
        }

        // Try as recovery code
        if (self::verify_recovery_code($user_id, $code)) {
            return [
                'success' => true,
                'message' => 'Recovery code accepted.',
            ];
        }

        return [
            'success' => false,
            'message' => 'Invalid 2FA code. Please try again.',
        ];
    }

    /**
     * Verify TOTP code
     *
     * @param int $user_id User ID
     * @param string $code 6-digit TOTP code
     * @return bool True if valid
     */
    private static function verify_totp_code($user_id, $code) {
        // Get Wordfence TOTP secret
        $totp_secret = get_user_meta($user_id, 'wf_2fa_totp', true);

        if (empty($totp_secret) || !is_array($totp_secret)) {
            return false;
        }

        // Wordfence stores TOTP data as array with 'secret' key
        $secret = isset($totp_secret['secret']) ? $totp_secret['secret'] : '';

        if (empty($secret)) {
            return false;
        }

        // Use Wordfence's TOTP verification if method exists
        if (class_exists('wfTOTP')) {
            try {
                $totp = new wfTOTP($secret);
                return $totp->verify($code);
            } catch (Exception $e) {
                // Fallback to manual verification
            }
        }

        // Manual TOTP verification (30-second window)
        return self::verify_totp_manual($secret, $code);
    }

    /**
     * Manual TOTP verification
     *
     * @param string $secret Base32 encoded secret
     * @param string $code 6-digit code
     * @return bool True if valid
     */
    private static function verify_totp_manual($secret, $code) {
        $time = floor(time() / 30);

        // Check current, previous, and next time windows
        for ($i = -1; $i <= 1; $i++) {
            if (self::generate_totp($secret, $time + $i) === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate TOTP code
     *
     * @param string $secret Base32 secret
     * @param int $time Time counter
     * @return string 6-digit code
     */
    private static function generate_totp($secret, $time) {
        $key = self::base32_decode($secret);
        $time = pack('N*', 0) . pack('N*', $time);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0xf;

        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Base32 decode
     *
     * @param string $secret Base32 string
     * @return string Decoded binary
     */
    private static function base32_decode($secret) {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $decoded = '';

        for ($i = 0; $i < strlen($secret); $i++) {
            $decoded .= str_pad(base_convert(strpos($chars, $secret[$i]), 10, 2), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        for ($i = 0; $i < strlen($decoded); $i += 8) {
            $binary .= chr(base_convert(substr($decoded, $i, 8), 2, 10));
        }

        return $binary;
    }

    /**
     * Verify recovery code
     *
     * @param int $user_id User ID
     * @param string $code Recovery code
     * @return bool True if valid
     */
    private static function verify_recovery_code($user_id, $code) {
        $recovery_codes = get_user_meta($user_id, 'wf_2fa_recovery', true);

        if (empty($recovery_codes) || !is_array($recovery_codes)) {
            return false;
        }

        // Check if code matches any unused recovery code
        foreach ($recovery_codes as $index => $stored_code) {
            if (hash_equals($stored_code, $code)) {
                // Remove used recovery code
                unset($recovery_codes[$index]);
                update_user_meta($user_id, 'wf_2fa_recovery', $recovery_codes);
                return true;
            }
        }

        return false;
    }

    /**
     * Enable 2FA requirement for mobile
     *
     * @param int $user_id User ID
     * @return bool Success
     */
    public static function enable_mobile_2fa($user_id) {
        return update_user_meta($user_id, 'timegrow_mobile_require_2fa', '1');
    }

    /**
     * Disable 2FA requirement for mobile
     *
     * @param int $user_id User ID
     * @return bool Success
     */
    public static function disable_mobile_2fa($user_id) {
        return delete_user_meta($user_id, 'timegrow_mobile_require_2fa');
    }

    /**
     * Get Wordfence 2FA status for display
     *
     * @param int $user_id User ID
     * @return array Status information
     */
    public static function get_2fa_status($user_id) {
        if (!self::is_wordfence_active()) {
            return [
                'available' => false,
                'enabled' => false,
                'message' => 'Wordfence plugin not active',
            ];
        }

        $has_2fa = self::has_wordfence_2fa($user_id);
        $requires_mobile_2fa = get_user_meta($user_id, 'timegrow_mobile_require_2fa', true) === '1';

        return [
            'available' => true,
            'wordfence_enabled' => $has_2fa,
            'mobile_requires_2fa' => $requires_mobile_2fa,
            'message' => $has_2fa ? 'Wordfence 2FA is active' : 'Wordfence 2FA not configured',
        ];
    }
}

// Initialize
TimeGrowMobile2FA::init();
