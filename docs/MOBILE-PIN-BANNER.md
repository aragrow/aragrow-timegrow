# Mobile PIN Generated Banner

## Overview

When a mobile PIN is generated for a user, a prominent success banner is displayed at the top of the user profile page showing the newly generated PIN.

## Banner Display

The banner appears after clicking "Generate PIN" (or "Generate New PIN") and submitting the form.

### Visual Example

```
┌─────────────────────────────────────────────────────────────────────────┐
│ ✓  Mobile PIN Successfully Generated!                                   │
│                                                                           │
│    ┌───────────────────────────────────────────────────────────────┐   │
│    │              6-Character PIN                                   │   │
│    │                                                                │   │
│    │                   A B C 1 2 3                                 │   │
│    │                                                                │   │
│    └───────────────────────────────────────────────────────────────┘   │
│                                                                           │
│    ⚠ Important: Save this PIN now. It will not be shown again.          │
│                                                                           │
│    📱 An SMS notification has been sent to +1 (555) 123-4567            │
│                                                                           │
│    🔗 Mobile Login URL: https://yourdomain.com/mobile-login              │
│                                                                           │
└─────────────────────────────────────────────────────────────────────────┘
```

## Banner Features

### 1. **Prominent PIN Display**
- Large, bold alphanumeric PIN (e.g., `ABC123`)
- Gradient purple background for visual emphasis
- Letter-spaced monospace font for clarity
- Easy to read and copy

### 2. **Important Warning**
- Red warning text: "Save this PIN now. It will not be shown again."
- ⚠ Warning icon
- Ensures admin saves the PIN before leaving the page

### 3. **SMS Confirmation** (if phone number set)
- Shows which phone number received the SMS
- 📱 Smartphone icon
- Example: "An SMS notification has been sent to +1 (555) 123-4567"

### 4. **Mobile Login URL**
- Direct link to mobile login page
- 🔗 Link icon
- Clickable URL that opens in new tab

### 5. **Dismissible**
- "X" button in top-right corner
- Click to dismiss after saving PIN
- Standard WordPress admin notice behavior

## User Workflow

### Admin Generating PIN for User

1. **Navigate to User Profile**
   - Go to Users → All Users
   - Click "Edit" on desired user

2. **Scroll to "Mobile Access PIN" Section**
   - Only visible if user has `access_mobile_time_tracking` or `access_mobile_expenses` capability

3. **Enter Phone Number (Optional)**
   - Add phone number for SMS notifications
   - Format: `+1 (555) 123-4567` or `15551234567`

4. **Click "Generate PIN"**
   - Button generates random 6-character alphanumeric PIN
   - Form submits automatically

5. **Page Reloads with Banner**
   - Green success banner appears at top
   - PIN displayed prominently: `ABC123`
   - SMS sent (if phone number provided)

6. **Save the PIN**
   - Copy PIN and share with user
   - Or send PIN via secure channel
   - Dismiss banner when done

### User Generating Own PIN

Same workflow, but on their own profile page (`/wp-admin/profile.php`).

## Technical Implementation

### Transient Storage

PIN is stored temporarily for display:

```php
// After PIN generation (TimeGrowMobileUserProfile.php:250)
set_transient('timegrow_pin_generated_' . $user_id, $new_pin, 60);
```

**Key Points:**
- Stored for 60 seconds only
- Automatically deleted after banner is shown once
- Not stored in database (only hashed version is stored)
- Deleted immediately after first display

### Admin Notice Hook

Banner is rendered via WordPress admin notice hooks:

```php
// TimeGrowMobileUserProfile.php:47-48
add_action('admin_notices', [__CLASS__, 'display_pin_generated_notice']);
add_action('user_admin_notices', [__CLASS__, 'display_pin_generated_notice']);
```

### Display Logic

```php
// Check if PIN was just generated
$generated_pin = get_transient('timegrow_pin_generated_' . $user_id);
if (!$generated_pin) {
    return; // No banner to show
}

// Delete transient so it only shows once
delete_transient('timegrow_pin_generated_' . $user_id);
```

## Security Considerations

### One-Time Display

- PIN shown only once (via transient)
- Transient deleted after first view
- Cannot refresh page to see PIN again
- Cannot navigate back to see PIN again

### Temporary Storage

- PIN stored in transient for 60 seconds max
- Automatically expires even if not viewed
- Not logged anywhere except error_log (for SMS debug)
- Hashed PIN stored in database, never plain text

### SMS Notification

- If phone number is set, PIN is texted to user
- Provides backup if admin doesn't save PIN from banner
- User can login immediately without waiting for admin

### Access Control

- Banner only visible to:
  - User editing their own profile
  - Admin editing another user's profile
- Not visible to other users
- Requires proper permissions

## Styling

The banner uses WordPress admin notice classes with custom inline styles:

```php
<div class="notice notice-success is-dismissible">
    <!-- Green left border, checkmark icon, gradient PIN display -->
</div>
```

**Colors:**
- Success green: `#46b450`
- Purple gradient: `#667eea` → `#764ba2`
- Warning red: `#d63638`
- Info blue: `#2271b1`

## Example Screenshots

### After Generating PIN

The banner appears at the very top of the page, above all profile fields:

```
WordPress Admin Header
─────────────────────────────────────────────────────────
[SUCCESS BANNER WITH PIN]
─────────────────────────────────────────────────────────
Edit User

Personal Options
  Visual Editor: [x] Disable...

Name
  First Name: [John]
  Last Name:  [Doe]

Mobile Access PIN
  Mobile Phone Number: [+1 (555) 123-4567]
  PIN Code: [PIN is active] [Last Login: Feb 15, 2026 2:45 PM]
            [Generate New PIN] [Disable PIN]
```

### After Dismissing Banner

User clicks "X" to dismiss, banner disappears:

```
WordPress Admin Header
─────────────────────────────────────────────────────────
Edit User

Personal Options
  ...
```

## Testing

### Test Scenarios

1. ✅ Generate PIN for user with phone number → Banner + SMS
2. ✅ Generate PIN for user without phone number → Banner only (no SMS line)
3. ✅ Refresh page after seeing banner → Banner gone
4. ✅ Navigate away and back → Banner gone
5. ✅ Wait 60 seconds, refresh → Banner gone (transient expired)
6. ✅ Dismiss banner with X → Banner hidden
7. ✅ Generate new PIN again → New banner appears with new PIN

## Future Enhancements

- Copy to clipboard button
- QR code generation for easy mobile scanning
- Email notification option (in addition to SMS)
- Print PIN option
- Download PIN as PDF
