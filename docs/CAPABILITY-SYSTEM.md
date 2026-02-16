# Mobile Capability System

## Overview

Mobile access capabilities (`access_mobile_time_tracking` and `access_mobile_expenses`) are now managed through WordPress's native capability system, not a separate database table.

## Changes Made

### Database Schema
The `wp_timegrow_mobile_pins` table now only stores:
- PIN authentication data (hash, salt)
- Security data (failed attempts, lockout times)
- Session data (last login)

**Removed columns:**
- `has_time_tracking` (now a WordPress capability)
- `has_expenses` (now a WordPress capability)

### Capability Management

#### How Capabilities Work:
```php
// Grant time tracking to a user
$user = new WP_User($user_id);
$user->add_cap('access_mobile_time_tracking');

// Grant expenses to a user
$user->add_cap('access_mobile_expenses');

// Remove capabilities
$user->remove_cap('access_mobile_time_tracking');
$user->remove_cap('access_mobile_expenses');

// Check if user has capability
if (current_user_can('access_mobile_time_tracking')) {
    // User can track time via mobile
}
```

#### Where Capabilities Are Set:
1. **User Profile Page** (`/Users → Edit User`)
   - Admins can check/uncheck "Time Tracking" and "Expenses"
   - Saves directly to user capabilities
   - No database table update needed

2. **Mobile Access Settings** (deprecated - use user profile instead)

## Benefits of WordPress Capabilities

✅ **Standard WordPress Approach**: Uses built-in user meta system
✅ **Role-Based**: Can be assigned to entire roles, not just individual users
✅ **Persistent**: Capabilities survive PIN resets
✅ **Queryable**: Can use `WP_User_Query` to find users with specific capabilities
✅ **No Extra Tables**: Leverages existing `wp_usermeta` table
✅ **Flexible**: Can be managed by plugins, themes, or custom code

## Migration Note

Existing mobile users with capabilities in the old table columns will need to have their capabilities reassigned via the user profile page. The old columns are removed from the database schema.

## Code Examples

### Granting Capabilities Programmatically
```php
// When creating a new mobile user
$user_id = 123;
$user = new WP_User($user_id);

// Grant both capabilities
$user->add_cap('access_mobile_time_tracking');
$user->add_cap('access_mobile_expenses');
```

### Checking Capabilities in Views
```php
// In mobile dashboard
if (current_user_can('access_mobile_time_tracking')) {
    // Show "Enter Time" button
}

if (current_user_can('access_mobile_expenses')) {
    // Show "Enter Expenses" button
}
```

### Querying Users with Mobile Access
```php
// Find all users who can track time via mobile
$args = array(
    'meta_query' => array(
        array(
            'key' => 'wp_capabilities',
            'value' => 'access_mobile_time_tracking',
            'compare' => 'LIKE'
        )
    )
);
$users = new WP_User_Query($args);
```

## Capability Definitions

### `access_mobile_time_tracking`
- **Purpose**: Grants access to time tracking features via mobile PIN login
- **Includes**:
  - Clock In/Out page
  - Manual time entry page
  - Time reports (own data only)

### `access_mobile_expenses`
- **Purpose**: Grants access to expense management via mobile PIN login
- **Includes**:
  - Expense entry page
  - Receipt upload
  - Expense reports (own data only)

### `access_mobile_only_mode`
- **Purpose**: Identifies users who can use mobile PIN login
- **Note**: This is automatically granted to all users (including admins) for flexibility
- **Restrictions**: Only applied when logged in via mobile PIN session (not regular WordPress login)

## Security Considerations

- Capabilities persist even if PIN is reset/disabled
- Removing a user's PIN (`is_active = 0`) doesn't remove their capabilities
- To fully revoke mobile access: disable PIN AND remove capabilities
- Admins always have full access when logged in via desktop (capabilities only restrict mobile PIN sessions)

## Admin Workflow

### Granting Mobile Access to a User:
1. Go to **Users → Edit User**
2. Scroll to **"Mobile Access"** section
3. Click **"Generate New PIN"** (PIN displays once)
4. Check **"Time Tracking"** and/or **"Expenses"**
5. Click **"Update Profile"**
6. Give PIN to user

### Revoking Mobile Access:
1. Go to **Users → Edit User**
2. Scroll to **"Mobile Access"** section
3. Uncheck **"Time Tracking"** and **"Expenses"**
4. Click **"Disable Mobile Access"** to deactivate PIN
5. Click **"Update Profile"**

## Database Queries

### Before (Old Schema):
```sql
-- Get users with time tracking enabled
SELECT user_id FROM wp_timegrow_mobile_pins WHERE has_time_tracking = 1;
```

### After (New Schema):
```sql
-- Get users with time tracking capability
SELECT user_id FROM wp_usermeta
WHERE meta_key = 'wp_capabilities'
AND meta_value LIKE '%access_mobile_time_tracking%';
```

## Backward Compatibility

The old `has_time_tracking` and `has_expenses` columns have been removed from the database schema. If you have existing mobile users, you'll need to:

1. Note which users had which capabilities
2. Run the database migration (table structure updates automatically)
3. Manually reassign capabilities via user profile pages

Or create a migration script:
```php
// Example migration script
$pins = $wpdb->get_results("SELECT user_id, has_time_tracking, has_expenses FROM wp_timegrow_mobile_pins_backup");

foreach ($pins as $pin) {
    $user = new WP_User($pin->user_id);

    if ($pin->has_time_tracking) {
        $user->add_cap('access_mobile_time_tracking');
    }

    if ($pin->has_expenses) {
        $user->add_cap('access_mobile_expenses');
    }
}
```
