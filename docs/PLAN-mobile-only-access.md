# Mobile-Only Access Mode for TimeGrow - Implementation Plan

## Context

The TimeGrow plugin currently provides comprehensive time tracking and expense management through both a traditional WordPress admin interface and a modern Nexus module. However, there's a need for field workers and mobile users to quickly record time and expenses on their phones without exposing them to the full administrative interface.

**Problem:** Business owners want employees to track time and expenses via mobile phones, but don't want them accessing reports, settings, client data, or other users' information.

**Solution:** Extend the existing Nexus module with a mobile-only access mode featuring:
- Separate PIN-based authentication (6-digit PIN) for quick mobile access
- Restricted UI showing only: Clock In/Out, Manual Time Entry, Expense Recording, and Own History
- Complete data isolation preventing access to other users' data or admin features
- Session-based authentication separate from WordPress login system

**User Requirements:**
- 6-digit PIN authentication (not WordPress password)
- Access to: Clock in/out, manual time entry, expense entry with receipts, view own history
- NO access to: settings, reports, other users' data, admin pages, WordPress dashboard

---

## Architecture Overview

### Modular Structure

The mobile-only access system will be built as an isolated module in `modules/mobile/` to maintain separation of concerns and enable independent development, testing, and deployment.

**Directory Structure:**
```
modules/mobile/
├── aragrow-timegrow-mobile.php          # Bootstrap file
├── includes/
│   ├── TimeGrowPINAuthenticator.php     # PIN authentication logic
│   ├── TimeGrowMobileAccessControl.php  # Access restriction middleware
│   ├── models/
│   │   └── TimeGrowMobilePINModel.php   # Database model for PINs
│   ├── views/
│   │   ├── TimeGrowMobileLoginView.php  # Mobile login page
│   │   └── TimeGrowMobileSettingsView.php # Admin PIN management
│   └── routes/
│       └── class-mobile-endpoints.php   # REST API endpoints
└── assets/
    ├── css/
    │   ├── mobile-only-mode.css         # Mobile UI styling
    │   └── mobile-login.css             # Login page styling
    └── js/
        └── mobile-navigation.js         # Mobile navigation UI
```

**Module Loading:**
The mobile module will be loaded from the main plugin file:
```php
// In aragrow-timegrow.php
require_once TIMEGROW_PLUGIN_DIR . 'modules/mobile/aragrow-timegrow-mobile.php';
```

**Integration Points:**
- Hooks into WordPress authentication system
- Extends Nexus module capabilities
- Adds custom URL rewrite rules
- Filters Nexus view access based on mobile capabilities
- Provides admin interface in Nexus settings

### Database Schema

**New Table: `wp_timegrow_mobile_pins`**

```sql
CREATE TABLE IF NOT EXISTS wp_timegrow_mobile_pins (
    ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    user_id bigint(20) unsigned NOT NULL UNIQUE,
    pin_hash varchar(255) NOT NULL,
    pin_salt varchar(64) NOT NULL,
    is_active tinyint(1) NOT NULL DEFAULT 1,
    has_time_tracking tinyint(1) NOT NULL DEFAULT 0,
    has_expenses tinyint(1) NOT NULL DEFAULT 0,
    failed_attempts tinyint(3) NOT NULL DEFAULT 0,
    locked_until datetime DEFAULT NULL,
    last_login_at datetime DEFAULT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (ID),
    FOREIGN KEY (user_id) REFERENCES wp_users(ID) ON DELETE CASCADE,
    KEY idx_user_active (user_id, is_active),
    KEY idx_capabilities (has_time_tracking, has_expenses)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Security Features:**
- Salted SHA-256 hashing for 6-digit PIN storage
- Failed attempt tracking (lock after 5 failed attempts)
- 15-minute temporary lockout period
- Cascade delete when user is removed

---

## Implementation Components

### 1. New Capability System

**New Capabilities:** Fine-grained access control for mobile users

**File to Create:** `/modules/mobile/aragrow-timegrow-mobile.php`

The mobile module will register the following capabilities:

```php
// Mobile-only mode capabilities
$mobile_role = add_role('mobile_user', 'Mobile User', [
    'read' => true,
    'access_mobile_only_mode' => true,        // Core restriction flag
    'access_mobile_time_tracking' => false,   // Time tracking capability (opt-in)
    'access_mobile_expenses' => false,        // Expense tracking capability (opt-in)
]);
```

**Capability Breakdown:**
- `access_mobile_only_mode` - Core capability that restricts user to mobile interface only
- `access_mobile_time_tracking` - Grants access to Clock In/Out and Manual Time Entry
- `access_mobile_expenses` - Grants access to Expense Recording with receipt uploads

**Admin Control:**
Admins can enable mobile access and selectively grant time tracking and/or expense capabilities per user:
- Mobile user with time only: Can clock in/out, add manual entries, view own time history
- Mobile user with expenses only: Can record expenses with receipts, view own expense history
- Mobile user with both: Full mobile access to all features
- Mobile user with neither: Can login but sees "Contact administrator" message

**Capability Assignment:**
```php
// Example: Enable mobile access with time tracking only
$user = get_user_by('id', $user_id);
$user->add_cap('access_mobile_only_mode');
$user->add_cap('access_mobile_time_tracking');

// Example: Add expenses capability later
$user->add_cap('access_mobile_expenses');

// Example: Remove time tracking but keep expenses
$user->remove_cap('access_mobile_time_tracking');
```

**Database Schema Integration:**
Add capability flags to the mobile PINs table for efficient queries:
```sql
ALTER TABLE wp_timegrow_mobile_pins ADD COLUMN has_time_tracking tinyint(1) DEFAULT 0;
ALTER TABLE wp_timegrow_mobile_pins ADD COLUMN has_expenses tinyint(1) DEFAULT 0;
```

These fields will be synchronized with user capabilities for faster admin UI display.

Also add table creation to the activation hook in the same file.

---

### 2. PIN Authentication System

**New File:** `/modules/mobile/includes/TimeGrowPINAuthenticator.php`

**Key Methods:**
- `create_pin_for_user($user_id, $pin)` - Hash and store 6-digit PIN
- `verify_pin($user_id, $pin)` - Validate PIN with rate limiting
- `create_mobile_session($user_id)` - Create isolated session cookie
- `validate_mobile_session()` - Check active mobile session
- `destroy_mobile_session()` - Logout
- `is_account_locked($user_id)` - Check lockout status
- `reset_failed_attempts($user_id)` - Clear failed login counter after successful login

**Session Management:**
- Custom session cookie: `timegrow_mobile_session` (httpOnly, secure, SameSite=Strict)
- 8-hour session timeout
- Separate from WordPress authentication cookies
- Store user_id and expiration in encrypted cookie value

**Rate Limiting:**
- 5 failed PIN attempts = 15-minute account lockout
- Track attempts in database
- Display lockout countdown to user

---

### 3. Mobile Login Page

**New File:** `/modules/mobile/includes/views/TimeGrowMobileLoginView.php`

**Custom WordPress Endpoint:**
Register rewrite rule for `/mobile-login` URL:

```php
// In /modules/mobile/aragrow-timegrow-mobile.php
add_action('init', function() {
    add_rewrite_rule('^mobile-login/?$', 'index.php?mobile_login=1', 'top');
    add_rewrite_tag('%mobile_login%', '1');
});

add_action('template_redirect', function() {
    if (get_query_var('mobile_login')) {
        require_once TIMEGROW_MOBILE_INCLUDES_DIR . 'views/TimeGrowMobileLoginView.php';
        $view = new TimeGrowMobileLoginView();
        $view->display();
        exit;
    }
});
```

**Login Form Features:**
- User ID or username input
- 6-digit PIN input (numeric keyboard on mobile)
- Large touch-friendly buttons (min 44px height)
- Error messages for invalid PIN or locked account
- Lockout countdown display
- "Forgot PIN? Contact your administrator" message
- Mobile-optimized responsive design

---

### 4. Access Restriction Middleware

**New File:** `/modules/mobile/includes/TimeGrowMobileAccessControl.php`

**Hook:** `admin_init` (priority 1) to intercept all wp-admin requests

**Functionality:**
```php
add_action('admin_init', function() {
    // Only apply to mobile-only users
    if (!current_user_can('access_mobile_only_mode')) {
        return;
    }

    // Don't interfere with AJAX
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }

    $current_page = $_GET['page'] ?? '';

    // Build allowed pages based on user capabilities
    $allowed_pages = [];

    if (current_user_can('access_mobile_time_tracking')) {
        $allowed_pages[] = 'timegrow-nexus-clock';
        $allowed_pages[] = 'timegrow-nexus-manual';
    }

    if (current_user_can('access_mobile_expenses')) {
        $allowed_pages[] = 'timegrow-nexus-expenses';
    }

    // Reports page shows filtered data based on what user has access to
    if (!empty($allowed_pages)) {
        $allowed_pages[] = 'timegrow-nexus-reports';
    }

    // Block access to wp-admin pages not in whitelist
    if (!in_array($current_page, $allowed_pages)) {
        // Redirect to first allowed page, or show access denied
        if (!empty($allowed_pages)) {
            wp_redirect(admin_url('admin.php?page=' . $allowed_pages[0]));
        } else {
            wp_die('No mobile access granted. Contact your administrator.');
        }
        exit;
    }
}, 1);

// Hide admin bar for mobile-only users
add_filter('show_admin_bar', function($show) {
    if (current_user_can('access_mobile_only_mode')) {
        return false;
    }
    return $show;
});
```

**Additional Blocks:**
- Prevent access to `profile.php` (user profile editing)
- Prevent access to `users.php` (user management)
- Block non-Nexus REST API endpoints
- Filter admin menu to hide non-allowed items

---

### 5. Data Isolation

**File to Modify:** `/modules/nexus/includes/TimeGrowNexus.php`

Modify the `tracker_mvc_admin_page()` method (around lines 576-587):

**Current Code:**
```php
if (current_user_can('administrator')) {
    $projects = $team_member_model->get_projects_for_member(-1);
    $list = $model->select();
} else {
    $projects = $team_member_model->get_projects_for_member(get_current_user_id());
    $list = $model->select(get_current_user_id());
}
```

**Enhanced Code:**
```php
// Mobile-only users ALWAYS see only their own data (highest priority)
if (current_user_can('access_mobile_only_mode')) {
    $projects = $team_member_model->get_projects_for_member(get_current_user_id());
    $list = $model->select(get_current_user_id());
    $view_all = false;
} elseif (current_user_can('administrator')) {
    $projects = $team_member_model->get_projects_for_member(-1);
    $list = $model->select();
    $view_all = true;
} else {
    $projects = $team_member_model->get_projects_for_member(get_current_user_id());
    $list = $model->select(get_current_user_id());
    $view_all = false;
}
```

Apply same pattern to all Nexus views with capability checks:

**Clock View (`TimeGrowNexusClockView.php`):**
```php
// Add at the beginning of display() method
if (current_user_can('access_mobile_only_mode') && !current_user_can('access_mobile_time_tracking')) {
    wp_die('You do not have permission to access time tracking. Contact your administrator.');
}

// Then apply data filtering
if (current_user_can('access_mobile_only_mode')) {
    // Force user to see only their own data
    $list = $model->select(get_current_user_id());
}
```

**Manual Entry View (`TimeGrowNexusManualView.php`):**
```php
// Same capability check as Clock view
if (current_user_can('access_mobile_only_mode') && !current_user_can('access_mobile_time_tracking')) {
    wp_die('You do not have permission to access time tracking. Contact your administrator.');
}
```

**Expense View (`TimeGrowNexusExpenseView.php`):**
```php
// Check expenses capability
if (current_user_can('access_mobile_only_mode') && !current_user_can('access_mobile_expenses')) {
    wp_die('You do not have permission to access expenses. Contact your administrator.');
}

// Apply data filtering
if (current_user_can('access_mobile_only_mode')) {
    $list = $model->select(get_current_user_id());
}
```

**Reports View (`TimeGrowNexusReportView.php`):**
```php
// Filter reports based on granted capabilities
if (current_user_can('access_mobile_only_mode')) {
    $allowed_report_types = [];

    if (current_user_can('access_mobile_time_tracking')) {
        $allowed_report_types[] = 'time';
        $allowed_report_types[] = 'hours';
    }

    if (current_user_can('access_mobile_expenses')) {
        $allowed_report_types[] = 'expenses';
    }

    // Filter report data to current user only
    $user_id = get_current_user_id();

    // Hide report type tabs user doesn't have access to
}
```

---

### 6. Mobile-Optimized UI

**New File:** `/modules/mobile/assets/css/mobile-only-mode.css`

**Features:**
- Large touch targets (minimum 44px tap areas)
- Simplified navigation with bottom tab bar
- Hide unnecessary UI elements (breadcrumbs, help text, advanced options)
- High contrast for outdoor visibility
- Optimized for portrait mobile screens
- Dynamic bottom navigation based on capabilities:
  - Time tracking enabled: Clock | Manual tabs
  - Expenses enabled: Expenses tab
  - Always available: Reports tab (shows only accessible data)

**Auto-load for mobile-only users:**
```php
// In /modules/mobile/aragrow-timegrow-mobile.php
add_action('admin_enqueue_scripts', function() {
    if (current_user_can('access_mobile_only_mode')) {
        wp_enqueue_style(
            'timegrow-mobile-only-mode',
            TIMEGROW_MOBILE_BASE_URI . 'assets/css/mobile-only-mode.css',
            [],
            TIMEGROW_VERSION
        );

        wp_enqueue_script(
            'timegrow-mobile-navigation',
            TIMEGROW_MOBILE_BASE_URI . 'assets/js/mobile-navigation.js',
            ['jquery'],
            TIMEGROW_VERSION,
            true
        );

        // Pass user capabilities to JavaScript
        wp_localize_script('timegrow-mobile-navigation', 'timegrowMobile', [
            'hasTimeTracking' => current_user_can('access_mobile_time_tracking'),
            'hasExpenses' => current_user_can('access_mobile_expenses'),
        ]);
    }
});
```

**New File:** `/modules/mobile/assets/js/mobile-navigation.js`

Bottom tab bar navigation that dynamically shows/hides tabs based on user capabilities.

---

### 7. Admin Management Interface

**New File:** `/modules/mobile/includes/views/TimeGrowMobileSettingsView.php`

**Location:** TimeGrow > Nexus > Settings > Mobile Access (new tab)

**Features:**
- List of all users with mobile access enabled
- Enable/Disable mobile access toggle per user
- **Capability Toggles per User:**
  - ☐ Time Tracking (Clock In/Out, Manual Entry)
  - ☐ Expenses (Record expenses with receipts)
- Generate/Reset 6-digit PIN button
- Display generated PIN to admin (one-time view)
- Show last login timestamp
- Show failed attempt count
- Unlock locked accounts button
- Force logout all mobile sessions button
- View active mobile sessions

**Add to Nexus Settings Menu:**
Hook into Nexus settings from mobile module bootstrap file:

```php
// In /modules/mobile/aragrow-timegrow-mobile.php
add_action('admin_menu', function() {
    if (current_user_can('administrator')) {
        add_submenu_page(
            'timegrow-nexus',
            'Mobile Access',
            'Mobile Access',
            'manage_options',
            'timegrow-mobile-settings',
            function() {
                require_once TIMEGROW_MOBILE_INCLUDES_DIR . 'views/TimeGrowMobileSettingsView.php';
                $view = new TimeGrowMobileSettingsView();
                $view->display();
            }
        );
    }
}, 11); // Priority 11 to load after Nexus menu
```

---

### 8. REST API Enhancement (Optional)

**New File:** `/modules/mobile/includes/routes/class-mobile-endpoints.php`

**New Endpoint:** `POST /timegrow/v1/mobile-auth`

For future PWA/native app support:

```php
register_rest_route('timegrow/v1', '/mobile-auth', [
    'methods' => 'POST',
    'callback' => [$this, 'handle_mobile_pin_login'],
    'permission_callback' => '__return_true',
    'args' => [
        'user_id' => ['required' => true, 'type' => 'integer'],
        'pin' => [
            'required' => true,
            'validate_callback' => function($value) {
                return preg_match('/^\d{6}$/', $value); // Exactly 6 digits
            }
        ],
    ],
]);
```

Returns JWT token for mobile API access with embedded capability claims.

---

## Security Implementation

### 1. PIN Security
- **Requirement:** Exactly 6 numeric digits
- **Validation:** `preg_match('/^\d{6}$/', $pin)`
- **Hashing:** SHA-256 with unique salt per user
- **Storage:** Hash + salt stored in database, never plain text
- **Generation:** Admin generates random 6-digit PIN for user
- **Reset:** Only admin can reset PIN (no self-service recovery)

### 2. Session Security
- **Cookie Name:** `timegrow_mobile_session`
- **Cookie Flags:** httpOnly, secure (HTTPS only), SameSite=Strict
- **Timeout:** 8 hours inactivity (28,800 seconds)
- **Value:** Encrypted JSON with user_id and expiration timestamp
- **Isolation:** Completely separate from WordPress authentication

### 3. Rate Limiting
- **Failed Attempts:** Max 5 per user
- **Lockout Duration:** 15 minutes (900 seconds)
- **Lockout Reset:** Automatic after duration, or manual by admin
- **Display:** Show countdown to user during lockout

### 4. Access Control
- **Whitelist Pages:** Only explicitly allowed Nexus pages accessible
- **Capability Check:** Every view checks `access_mobile_only_mode`
- **Data Filter:** All database queries filtered to current user's ID only
- **Menu Filter:** Hide non-allowed menu items from admin menu
- **Admin Bar:** Disabled for mobile-only users

### 5. Audit Logging
- Log all mobile login attempts (success and failure)
- Log PIN generation and reset events
- Log session creation and destruction
- Log access attempts to blocked pages

---

## Configuration Settings

**New Options:** (stored in `wp_options` table)

- `timegrow_mobile_pin_length` = 6 (fixed, not configurable by user)
- `timegrow_mobile_session_timeout` = 28800 (8 hours in seconds)
- `timegrow_mobile_lockout_duration` = 900 (15 minutes in seconds)
- `timegrow_mobile_max_failed_attempts` = 5
- `timegrow_mobile_require_https` = true (enforce HTTPS for mobile login)

---

## Critical Files Summary

### New Module: `/modules/mobile/`

**Bootstrap File:**
1. `/modules/mobile/aragrow-timegrow-mobile.php` - Module bootstrap with capability registration, table creation, hooks

**Core Classes:**
2. `/modules/mobile/includes/TimeGrowPINAuthenticator.php` - PIN authentication logic
3. `/modules/mobile/includes/TimeGrowMobileAccessControl.php` - Access restriction middleware
4. `/modules/mobile/includes/models/TimeGrowMobilePINModel.php` - Database model for PINs table

**Views:**
5. `/modules/mobile/includes/views/TimeGrowMobileLoginView.php` - Mobile login page
6. `/modules/mobile/includes/views/TimeGrowMobileSettingsView.php` - Admin PIN management

**Assets:**
7. `/modules/mobile/assets/css/mobile-only-mode.css` - Mobile-specific styling
8. `/modules/mobile/assets/css/mobile-login.css` - Login page styling
9. `/modules/mobile/assets/js/mobile-navigation.js` - Dynamic navigation based on capabilities

**REST API (Optional):**
10. `/modules/mobile/includes/routes/class-mobile-endpoints.php` - Mobile authentication endpoint

### Files to Modify:

**Main Plugin:**
1. `/aragrow-timegrow.php` - Add mobile module loader: `require_once TIMEGROW_PLUGIN_DIR . 'modules/mobile/aragrow-timegrow-mobile.php';`

**Nexus Module (Data Isolation):**
2. `/modules/nexus/includes/TimeGrowNexus.php` - Add mobile-only mode checks for data filtering
3. `/modules/nexus/includes/views/TimeGrowNexusClockView.php` - Filter to user's own data, check `access_mobile_time_tracking`
4. `/modules/nexus/includes/views/TimeGrowNexusManualView.php` - Filter to user's own data, check `access_mobile_time_tracking`
5. `/modules/nexus/includes/views/TimeGrowNexusExpenseView.php` - Filter to user's own data, check `access_mobile_expenses`
6. `/modules/nexus/includes/views/TimeGrowNexusReportView.php` - Filter to user's own data, show time/expense tabs based on capabilities

---

## User Experience Flow

### Admin Setup Process:
1. Admin navigates to TimeGrow > Nexus > Mobile Access
2. Finds employee in user list
3. Clicks "Enable Mobile Access"
4. **Selects capabilities:**
   - ☑ Time Tracking (Clock In/Out, Manual Entry)
   - ☑ Expenses (Record expenses with receipts)
5. System generates random 6-digit PIN
6. Admin views PIN (one-time display) and provides it to employee
7. Employee record shows "Mobile Access: Enabled | Time ✓ | Expenses ✓" with last login timestamp

### Mobile User Login:
1. Employee navigates to `yourdomain.com/mobile-login`
2. Enters their User ID or username
3. Enters 6-digit PIN
4. Clicks "Sign In" button
5. Session created, redirected to Clock In/Out page
6. Bottom navigation shows: Clock | Manual | Expenses | Reports

### Mobile User Session:
- Lands on first allowed page (Clock if time tracking enabled, Expenses if only expenses)
- Navigation tabs show only based on granted capabilities:
  - Time tracking enabled: Clock, Manual Entry tabs visible
  - Expenses enabled: Expenses tab visible
  - Always visible: Reports tab (filtered to show only accessible data)
- **Cannot** access: Settings, Dashboard, Other Users' Data, Any wp-admin pages
- Session expires after 8 hours of inactivity
- Can manually logout via "Sign Out" button

### Failed Login Handling:
1. User enters incorrect PIN
2. Error message: "Invalid PIN. X attempts remaining."
3. After 5 failed attempts: "Account locked for 15 minutes."
4. Lockout countdown displayed
5. After 15 minutes, account automatically unlocked

---

## Testing & Verification

### Functional Tests:
1. ✅ Mobile user can login with correct 6-digit PIN
2. ✅ Mobile user cannot login with incorrect PIN
3. ✅ Account locks after 5 failed attempts
4. ✅ Locked account unlocks after 15 minutes
5. ✅ Mobile user with time tracking can clock in/out successfully
6. ✅ Mobile user with time tracking can add manual time entries
7. ✅ Mobile user without time tracking cannot access clock/manual pages
8. ✅ Mobile user with expenses can record expenses with receipt photos
9. ✅ Mobile user without expenses cannot access expense page
10. ✅ Mobile user can view only their own reports (filtered by capabilities)
11. ✅ Navigation tabs show only for granted capabilities
12. ✅ Session expires after 8 hours
13. ✅ Logout works correctly
14. ✅ Admin can toggle time tracking capability independently
15. ✅ Admin can toggle expenses capability independently

### Security Tests:
1. ✅ Mobile user redirected away from wp-admin root
2. ✅ Mobile user cannot access other users' data
3. ✅ Mobile user cannot access Settings
4. ✅ Mobile user cannot access profile.php
5. ✅ Mobile user cannot access users.php
6. ✅ PIN is properly hashed in database (not plain text)
7. ✅ Session cookies are httpOnly and secure
8. ✅ Rate limiting prevents brute force
9. ✅ Admin can reset PIN for locked accounts
10. ✅ Admin can disable mobile access
11. ✅ Mobile user cannot access pages for capabilities they don't have
12. ✅ Capability checks prevent URL manipulation (e.g., manually typing timegrow-nexus-expenses URL when not authorized)

### Mobile UI Tests:
1. ✅ Mobile login page responsive on iPhone
2. ✅ Mobile login page responsive on Android
3. ✅ Touch targets are minimum 44px
4. ✅ Navigation is intuitive
5. ✅ Forms work with mobile keyboards
6. ✅ Works on iOS Safari
7. ✅ Works on Android Chrome
8. ✅ Works with mobile camera for receipt capture

---

## Implementation Phases

### Phase 1: Module Bootstrap & Database
- Create `/modules/mobile/` directory structure
- Create `aragrow-timegrow-mobile.php` bootstrap file
- Define constants: `TIMEGROW_MOBILE_BASE_DIR`, `TIMEGROW_MOBILE_BASE_URI`, `TIMEGROW_MOBILE_INCLUDES_DIR`
- Create `wp_timegrow_mobile_pins` table
- Register capabilities: `access_mobile_only_mode`, `access_mobile_time_tracking`, `access_mobile_expenses`
- Add module loader to main plugin file
- **Test:** Module loads correctly, table created, capabilities registered

### Phase 2: PIN Authentication Core
- Implement `TimeGrowPINAuthenticator` class
- Implement `TimeGrowMobilePINModel` class
- **Test:** PIN creation, validation, rate limiting

### Phase 3: Login Interface
- Create `TimeGrowMobileLoginView` class
- Add URL rewrite rules for `/mobile-login`
- Implement session management
- Create mobile login CSS styling
- **Test:** Login flow, session creation, logout

### Phase 4: Access Control & Data Isolation
- Implement `TimeGrowMobileAccessControl` middleware with capability-based page filtering
- Modify Nexus views to check capabilities:
  - Clock/Manual views: check `access_mobile_time_tracking`
  - Expense view: check `access_mobile_expenses`
  - Reports view: filter based on both capabilities
- Filter data queries to current user's ID only for mobile users
- Hide admin bar for mobile users
- **Test:** Access restrictions, capability enforcement, data isolation, URL manipulation prevention

### Phase 5: Mobile UI
- Create `mobile-only-mode.css` with touch-friendly styles
- Create `mobile-navigation.js` with dynamic tab bar (shows/hides based on capabilities)
- Enqueue assets for mobile users with capability data
- **Test:** UI on actual mobile devices, tab visibility matches capabilities

### Phase 6: Admin Management Interface
- Create `TimeGrowMobileSettingsView` class
- Add submenu page to Nexus menu
- Implement user list with mobile access status
- Add capability toggle checkboxes (Time Tracking, Expenses)
- Add PIN generation/reset functionality
- Add session management (unlock accounts, force logout)
- **Test:** Admin workflow, PIN generation, capability toggles, account management

### Phase 7: Final Testing & Launch
- Comprehensive security audit (capability bypass attempts, data leakage, brute force)
- Performance testing (session handling, database queries)
- User acceptance testing with real mobile devices
- Cross-browser mobile testing (iOS Safari, Android Chrome)
- Test all capability combinations:
  - Time only
  - Expenses only
  - Both capabilities
  - Neither capability
- Documentation for end users and administrators
- **Deploy to production**

---

## Rollback Plan

If critical issues arise:

1. **Quick Disable:**
   ```php
   // Add to wp-config.php
   define('TIMEGROW_MOBILE_ACCESS_DISABLED', true);
   ```

2. **Remove Capability:**
   ```php
   $role = get_role('mobile_user');
   if ($role) {
       $role->remove_cap('access_mobile_only_mode');
   }
   ```

3. **Clear Sessions:**
   Delete all cookies named `timegrow_mobile_session`

4. **Database Cleanup:**
   ```sql
   DROP TABLE IF EXISTS wp_timegrow_mobile_pins;
   ```

---

## Success Metrics

- Mobile users can login in < 5 seconds
- Zero unauthorized access incidents
- < 1% failed login rate (excluding forgotten PINs)
- 8-hour session duration sufficient for work shifts
- Mobile UI loads in < 2 seconds on 4G
- Zero data leakage between users

---

## Future Enhancements

1. **Biometric Authentication** - Fingerprint/Face ID via WebAuthn API
2. **QR Code Login** - Generate scannable QR codes for instant access
3. **Geofencing** - Restrict clock-in to specific GPS locations
4. **Offline Mode** - PWA support for offline time entry with sync
5. **Push Notifications** - Alert when clocked in > 8 hours
6. **Photo Time Stamps** - Attach photos to time entries (job site proof)
7. **Voice Notes** - Record audio descriptions for time/expense entries

---

## Summary

This plan provides a secure, user-friendly mobile-only access mode built as an isolated module (`modules/mobile/`) that integrates with the existing Nexus module while maintaining complete separation of concerns.

**Key Features:**
- ✅ Modular architecture for independent development and testing
- ✅ 6-digit PIN authentication separate from WordPress login
- ✅ Fine-grained capability control (time tracking and expenses independently toggleable)
- ✅ Complete data isolation (users see only their own data)
- ✅ Session-based authentication with rate limiting and account lockout
- ✅ Mobile-optimized UI with dynamic navigation based on capabilities
- ✅ Admin interface for PIN management and capability assignment
- ✅ Future-ready for PWA/native app support via REST API

**Security Highlights:**
- Salted SHA-256 PIN hashing
- httpOnly, secure, SameSite=Strict session cookies
- 5 failed attempt lockout with 15-minute timeout
- Multi-layer capability checks (middleware + view level)
- URL manipulation prevention
- Complete isolation from WordPress admin interface

**Benefits of Modular Design:**
- Easy to disable/enable without affecting Nexus module
- Independent testing and version control
- Can be packaged as optional add-on in future
- Clear separation of mobile-specific code from core time tracking
- Simplified rollback if issues arise
