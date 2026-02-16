<?php
/**
 * TimeGrow SMS Notifications
 *
 * Helper class for sending SMS notifications via mobile PIN login
 * Provides hooks for integration with SMS services (Twilio, AWS SNS, etc.)
 *
 * @package TimeGrow
 * @subpackage Mobile
 * @since 2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowSMSNotifications {

    /**
     * Initialize SMS notification hooks
     */
    public static function init() {
        // Hook into PIN SMS notification
        add_action('timegrow_send_pin_sms', [__CLASS__, 'send_sms'], 10, 4);

        // Hook into login SMS notification
        add_action('timegrow_send_login_sms', [__CLASS__, 'send_sms'], 10, 3);
    }

    /**
     * Send SMS via configured service
     *
     * @param string $phone Phone number (E.164 format recommended: +15551234567)
     * @param string $message Message content
     * @param int $user_id User ID (optional)
     * @param string $pin PIN code (optional, only for PIN notifications)
     */
    public static function send_sms($phone, $message, $user_id = null, $pin = null) {
        // Check if SMS is enabled
        $sms_enabled = get_option('timegrow_mobile_sms_enabled', false);
        if (!$sms_enabled) {
            return;
        }

        // Get configured SMS service
        $sms_service = get_option('timegrow_mobile_sms_service', 'none');

        switch ($sms_service) {
            case 'twilio':
                self::send_via_twilio($phone, $message);
                break;

            case 'aws_sns':
                self::send_via_aws_sns($phone, $message);
                break;

            case 'custom':
                // Allow custom SMS integration via filter
                do_action('timegrow_custom_sms_send', $phone, $message, $user_id, $pin);
                break;

            default:
                // Log only mode (for development/testing)
                error_log("TimeGrow SMS to {$phone}: {$message}");
                break;
        }
    }

    /**
     * Send SMS via Twilio
     *
     * @param string $phone Phone number
     * @param string $message Message content
     */
    private static function send_via_twilio($phone, $message) {
        $account_sid = get_option('timegrow_twilio_account_sid');
        $auth_token = get_option('timegrow_twilio_auth_token');
        $from_number = get_option('timegrow_twilio_from_number');

        if (empty($account_sid) || empty($auth_token) || empty($from_number)) {
            error_log('TimeGrow: Twilio credentials not configured');
            return false;
        }

        // Twilio API endpoint
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json";

        // Prepare request
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode("{$account_sid}:{$auth_token}"),
            ],
            'body' => [
                'From' => $from_number,
                'To' => $phone,
                'Body' => $message,
            ],
        ]);

        if (is_wp_error($response)) {
            error_log('TimeGrow Twilio SMS Error: ' . $response->get_error_message());
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($response_code !== 201) {
            error_log('TimeGrow Twilio SMS Failed: ' . $response_body);
            return false;
        }

        return true;
    }

    /**
     * Send SMS via AWS SNS
     *
     * @param string $phone Phone number
     * @param string $message Message content
     */
    private static function send_via_aws_sns($phone, $message) {
        // Placeholder for AWS SNS integration
        // Requires AWS SDK for PHP
        error_log('TimeGrow: AWS SNS integration not yet implemented');

        // Example implementation would use AWS SDK:
        /*
        use Aws\Sns\SnsClient;

        $client = new SnsClient([
            'version' => 'latest',
            'region' => get_option('timegrow_aws_region', 'us-east-1'),
            'credentials' => [
                'key' => get_option('timegrow_aws_access_key'),
                'secret' => get_option('timegrow_aws_secret_key'),
            ],
        ]);

        $result = $client->publish([
            'Message' => $message,
            'PhoneNumber' => $phone,
        ]);
        */

        return false;
    }

    /**
     * Validate phone number format
     *
     * @param string $phone Phone number
     * @return bool True if valid
     */
    public static function validate_phone_number($phone) {
        // Remove all non-digit characters except +
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);

        // Check if it looks like a valid phone number
        // E.164 format: +[country code][number] (max 15 digits)
        if (preg_match('/^\+[1-9]\d{1,14}$/', $cleaned)) {
            return true;
        }

        // US/Canada format: 10 digits
        if (preg_match('/^[2-9]\d{9}$/', $cleaned)) {
            return true;
        }

        return false;
    }

    /**
     * Format phone number to E.164 standard
     *
     * @param string $phone Phone number
     * @param string $default_country_code Default country code (e.g., '1' for US/Canada)
     * @return string Formatted phone number
     */
    public static function format_phone_number($phone, $default_country_code = '1') {
        // Remove all non-digit characters except +
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);

        // Already in E.164 format
        if (strpos($cleaned, '+') === 0) {
            return $cleaned;
        }

        // Add country code if missing (assume US/Canada)
        if (strlen($cleaned) === 10) {
            return '+' . $default_country_code . $cleaned;
        }

        // Add + if country code exists but no +
        if (strlen($cleaned) === 11 && $cleaned[0] === '1') {
            return '+' . $cleaned;
        }

        return $cleaned;
    }
}

// Initialize
TimeGrowSMSNotifications::init();
