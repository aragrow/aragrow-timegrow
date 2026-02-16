# Mobile SMS Notifications

## Overview

TimeGrow mobile PIN login can send SMS notifications to users when:
1. A new PIN is generated for their account
2. They successfully log in via mobile PIN

This adds an extra layer of security by alerting users to mobile access activity.

## User Profile Setup

### Adding Phone Number

1. Go to **Users → Edit User**
2. Scroll to **"Mobile Access PIN"** section
3. Enter phone number in **"Mobile Phone Number"** field
4. Click **"Update Profile"**

**Phone Number Format:**
- Recommended: E.164 format (e.g., `+15551234567`)
- Also accepts: US format (e.g., `(555) 123-4567` or `555-123-4567`)
- Numbers are automatically formatted to E.164 standard

## SMS Notifications

### PIN Generation Notification

When an admin generates a new PIN for a user:

```
Your TimeGrow mobile PIN has been generated: ABC123.
Login at https://yourdomain.com/mobile-login
```

### Login Notification

When a user successfully logs in via mobile PIN:

```
TimeGrow mobile login at 2:45 PM.
If this wasn't you, contact your administrator immediately.
```

## SMS Service Integration

### Built-in Support

TimeGrow includes integration hooks for popular SMS services:

- **Twilio** (ready to configure)
- **AWS SNS** (coming soon)
- **Custom integration** via WordPress hooks

### Twilio Setup

1. Sign up for Twilio account at https://www.twilio.com
2. Get your credentials:
   - Account SID
   - Auth Token
   - Twilio Phone Number
3. Add to WordPress options:

```php
update_option('timegrow_mobile_sms_enabled', true);
update_option('timegrow_mobile_sms_service', 'twilio');
update_option('timegrow_twilio_account_sid', 'your_account_sid');
update_option('timegrow_twilio_auth_token', 'your_auth_token');
update_option('timegrow_twilio_from_number', '+15551234567');
```

### Custom SMS Integration

You can integrate any SMS service using WordPress hooks:

```php
// Custom SMS handler
add_action('timegrow_custom_sms_send', function($phone, $message, $user_id, $pin) {
    // Your custom SMS API integration here
    // Example: using your company's SMS gateway

    $api_url = 'https://api.yoursms.com/send';
    wp_remote_post($api_url, [
        'body' => [
            'to' => $phone,
            'message' => $message,
            'api_key' => 'your_api_key',
        ],
    ]);
}, 10, 4);

// Enable custom SMS
update_option('timegrow_mobile_sms_enabled', true);
update_option('timegrow_mobile_sms_service', 'custom');
```

## Development/Testing Mode

By default, SMS notifications are **logged only** (not actually sent).

Check your PHP error log to see what would be sent:

```
[15-Feb-2026 14:30:15 UTC] TimeGrow SMS to +15551234567: Your TimeGrow mobile PIN has been generated: ABC123...
```

To enable actual SMS sending:

```php
update_option('timegrow_mobile_sms_enabled', true);
update_option('timegrow_mobile_sms_service', 'twilio'); // or 'aws_sns' or 'custom'
```

## Security Considerations

### Phone Number Privacy

- Phone numbers are stored in WordPress user meta (`timegrow_mobile_phone`)
- Only visible to administrators and the user themselves
- Not exposed in API responses or public-facing pages

### SMS Message Content

- **PIN notifications**: Include the actual PIN (one-time display)
- **Login notifications**: Do NOT include PIN, only timestamp
- All messages are transactional (not marketing)

### Rate Limiting

Consider implementing rate limiting for SMS to prevent abuse:

```php
// Example: Limit to 5 SMS per user per day
add_action('timegrow_send_pin_sms', function($phone, $message, $user_id, $pin) {
    $count_today = get_transient("timegrow_sms_count_{$user_id}");

    if ($count_today >= 5) {
        error_log("TimeGrow: SMS rate limit exceeded for user {$user_id}");
        return; // Don't send
    }

    set_transient("timegrow_sms_count_{$user_id}", $count_today + 1, DAY_IN_SECONDS);
}, 1, 4); // Priority 1 to run before actual SMS send
```

## Code Examples

### Check if User Has Phone Number

```php
$phone = get_user_meta($user_id, 'timegrow_mobile_phone', true);
if (!empty($phone)) {
    // User has SMS enabled
}
```

### Send Custom SMS to User

```php
$phone = get_user_meta($user_id, 'timegrow_mobile_phone', true);
if (!empty($phone)) {
    do_action('timegrow_send_login_sms', $phone, 'Your custom message', $user_id);
}
```

### Validate Phone Number Format

```php
$phone = $_POST['phone'];
if (TimeGrowSMSNotifications::validate_phone_number($phone)) {
    // Valid phone number
    $formatted = TimeGrowSMSNotifications::format_phone_number($phone);
    update_user_meta($user_id, 'timegrow_mobile_phone', $formatted);
}
```

## Available Hooks

### Actions

**`timegrow_send_pin_sms`** - Send PIN generation SMS
- `@param string $phone` - Phone number (E.164 format)
- `@param string $message` - Message content
- `@param int $user_id` - User ID
- `@param string $pin` - Generated PIN

**`timegrow_send_login_sms`** - Send login notification SMS
- `@param string $phone` - Phone number (E.164 format)
- `@param string $message` - Message content
- `@param int $user_id` - User ID

**`timegrow_custom_sms_send`** - Custom SMS integration hook
- `@param string $phone` - Phone number
- `@param string $message` - Message content
- `@param int $user_id` - User ID
- `@param string|null $pin` - PIN (if applicable)

### Filters

Currently no filters are available, but can be added if needed.

## Costs

SMS services typically charge per message:

- **Twilio**: ~$0.0075 per SMS (US)
- **AWS SNS**: ~$0.00645 per SMS (US)

With 10 employees logging in twice per day:
- 20 login SMS/day
- 600 login SMS/month
- ~$4-5/month in SMS costs

PIN generation SMS are rare (only when creating/resetting PINs).

## Troubleshooting

### SMS Not Being Sent

1. Check if SMS is enabled:
   ```php
   get_option('timegrow_mobile_sms_enabled'); // Should be true
   ```

2. Check if phone number is set:
   ```php
   get_user_meta($user_id, 'timegrow_mobile_phone', true);
   ```

3. Check PHP error logs for SMS errors

4. Verify Twilio credentials (if using Twilio)

### Invalid Phone Number Format

Use the validator to check:

```php
$is_valid = TimeGrowSMSNotifications::validate_phone_number($phone);
if (!$is_valid) {
    // Show error to user
}
```

### Twilio "From" Number Not Verified

Twilio requires phone numbers to be verified:
1. Purchase a phone number in Twilio console
2. Or verify your existing number

## Future Enhancements

- Admin settings page for SMS configuration
- SMS delivery status tracking
- Support for international numbers beyond US/Canada
- MMS support for sending QR codes
- Opt-out mechanism for users who don't want SMS
- SMS templates customization
- Delivery reports and logs
