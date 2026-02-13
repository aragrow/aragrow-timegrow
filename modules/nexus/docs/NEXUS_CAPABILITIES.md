# TimeGrow Nexus Capabilities Reference

**Plugin:** Aragrow - TimeGrow Nexus
**Version:** 1.0.2
**Last Updated:** 2026-02-12

---

## Overview

TimeGrow Nexus uses custom WordPress capabilities to control access to different features. Each feature/page has a corresponding capability that determines who can access it.

The dashboard is now organized by **functional buckets** that group related features together.

---

## Capability Mapping by Function

### Time Tracking Features

| Feature | Capability Name | Description |
|---------|----------------|-------------|
| **Clock In / Out** | `access_nexus_clock` | Start or stop timer for current tasks |
| **Manual Time Entry** | `access_nexus_manual_entry` | Add or edit past time entries |

### Expense Management Features

| Feature | Capability Name | Description |
|---------|----------------|-------------|
| **Record Expenses** | `access_nexus_record_expenses` | Track project-related expenses and receipts |

### Reporting Features

| Feature | Capability Name | Description |
|---------|----------------|-------------|
| **View Reports** | `access_nexus_view_reports` | Analyze time and productivity with reports |

### Administration Features (Admin Only)

| Feature | Capability Name | Description |
|---------|----------------|-------------|
| **Settings** | `access_nexus_settings` | Configure plugin integrations and options |
| **Process Time** | `access_nexus_process_time` | Process time and attach to WooCommerce products |
| **Nexus Dashboard** | `access_nexus_dashboard` | Access to Nexus dashboard home |

---

## Role Assignments

### Administrator Role
**Has access to ALL features:**

**Time Tracking:**
- ✅ `access_nexus_clock`
- ✅ `access_nexus_manual_entry`

**Expense Management:**
- ✅ `access_nexus_record_expenses`

**Reporting:**
- ✅ `access_nexus_view_reports`

**Administration:**
- ✅ `access_nexus_settings`
- ✅ `access_nexus_process_time`
- ✅ `access_nexus_dashboard`

**Total:** 7 capabilities

---

### Team Member Role
**Has access to operational features (no admin tools):**

**Time Tracking:**
- ✅ `access_nexus_clock`
- ✅ `access_nexus_manual_entry`

**Expense Management:**
- ✅ `access_nexus_record_expenses`

**Reporting:**
- ✅ `access_nexus_view_reports`

**Administration:**
- ❌ `access_nexus_settings` (Admin only)
- ❌ `access_nexus_process_time` (Admin only)
- ✅ `access_nexus_dashboard` (Team members can access dashboard)

**Total:** 5 capabilities

---

## Dashboard Organization by Function

The Nexus dashboard is now organized into **4 functional sections**:

### 1. Time Tracking
**Visible to:** Team Members & Administrators
**Features:**
- Clock In / Out
- Manual Time Entry

### 2. Expense Management
**Visible to:** Team Members & Administrators
**Features:**
- Record Expenses

### 3. Reporting & Analytics
**Visible to:** Team Members & Administrators
**Features:**
- View Reports

### 4. Administration
**Visible to:** Administrators only
**Features:**
- Settings
- Process Time

---

## Using Capabilities in Code

### Check if Current User Can Access Feature

```php
// Check specific Nexus capability
if (current_user_can('access_nexus_clock')) {
    // User can access Clock In/Out
}

// Check for any Nexus access
if (current_user_can('access_nexus_dashboard')) {
    // User can view Nexus dashboard
}
```

### Show/Hide Dashboard Sections

```php
// Show Time Tracking section if user has access to any time tracking feature
if (current_user_can('access_nexus_clock') || current_user_can('access_nexus_manual_entry')) {
    // Display Time Tracking section
}

// Show Administration section only for admins
if (current_user_can('access_nexus_settings') || current_user_can('access_nexus_process_time')) {
    // Display Administration section
}
```

---

## Menu Registration with Capabilities

Pages are registered with their corresponding capabilities:

```php
// Dashboard - accessible to team members and admins
add_submenu_page(
    TIMEGROW_PARENT_MENU,
    'Nexus Dashboard',
    'Nexus Dashboard',
    'access_nexus_dashboard',  // Capability requirement
    TIMEGROW_PARENT_MENU . '-nexus',
    $callback
);

// Clock page - hidden from menu but capability-controlled
add_submenu_page(
    null,  // Hidden from menu
    'Clock',
    'Clock',
    'access_nexus_clock',  // Capability requirement
    TIMEGROW_PARENT_MENU . '-nexus-clock',
    $callback
);

// Settings - admin only
add_submenu_page(
    null,  // Hidden from menu
    'Settings',
    'Settings',
    'access_nexus_settings',  // Admin-only capability
    TIMEGROW_PARENT_MENU . '-nexus-settings',
    $callback
);
```

---

## Capability Registration

Capabilities are automatically registered when:

1. **Plugin Activation** - The `timegrow_nexus_plugin_activate()` function registers all capabilities
2. **Admin Init Hook** - On every admin page load, the system checks if capabilities exist and registers them if missing

### Manual Registration

If you need to manually register capabilities:

```php
// Call the registration function
timegrow_nexus_register_capabilities();
```

### Check if Capabilities are Registered

```php
$admin = get_role('administrator');
if ($admin && $admin->has_cap('access_nexus_dashboard')) {
    // Capabilities are registered
} else {
    // Need to register capabilities
    timegrow_nexus_register_capabilities();
}
```

---

## Adding New Nexus Features

When adding a new feature to Nexus, follow these steps:

### 1. Define Capability

**File:** `/aragrow-timegrow-nexus.php`

Add to the appropriate capability array based on who should have access:

```php
// For team member features (operational)
$team_member_caps = [
    'access_nexus_clock',
    'access_nexus_manual_entry',
    'access_nexus_new_feature',  // Add here
];

// For admin-only features
$admin_only_caps = [
    'access_nexus_settings',
    'access_nexus_new_admin_feature',  // Add here
];
```

### 2. Register Menu Page

**File:** `/includes/TimeGrowNexus.php`

```php
add_submenu_page(
    null,  // Hidden from menu if you want card-based access
    'New Feature',
    'New Feature',
    'access_nexus_new_feature',  // Your capability
    TIMEGROW_PARENT_MENU . '-nexus-new-feature',
    function() {
        $this->tracker_mvc_admin_page('new_feature');
    }
);
```

### 3. Add Dashboard Card

**File:** `/includes/views/TimeGrowNexusView.php`

Add within the appropriate section (Time Tracking, Expense Management, Reporting, or Administration):

```php
<?php if (current_user_can('access_nexus_new_feature')) : ?>
<a href="<?php echo esc_url("\?page=".TIMEGROW_PARENT_MENU."-nexus-new-feature"); ?>" class="timegrow-card">
    <div class="timegrow-card-header">
        <div class="timegrow-icon timegrow-icon-primary">
            <span class="dashicons dashicons-star-filled"></span>
        </div>
        <div class="timegrow-card-title">
            <h2><?php esc_html_e('New Feature', 'timegrow'); ?></h2>
            <span class="timegrow-badge timegrow-badge-success">
                <?php esc_html_e('Active', 'timegrow'); ?>
            </span>
        </div>
    </div>
    <div class="timegrow-card-body">
        <p class="timegrow-card-description">
            <?php esc_html_e('Description of what this feature does.', 'timegrow'); ?>
        </p>
        <div class="timegrow-card-footer">
            <span class="timegrow-action-link">
                <?php esc_html_e('Open Feature', 'timegrow'); ?>
                <span class="dashicons dashicons-arrow-right-alt"></span>
            </span>
        </div>
    </div>
</a>
<?php endif; ?>
```

### 4. Trigger Registration

After adding the capability:
- Deactivate and reactivate the plugin, OR
- Visit any admin page (capabilities auto-register if missing), OR
- Run `timegrow_nexus_register_capabilities()` manually

---

## Functional Bucket Guidelines

When adding new features, assign them to the appropriate functional bucket:

### Time Tracking Bucket
- Features related to recording time
- Clock-based or manual entry mechanisms
- Time entry editing/management

### Expense Management Bucket
- Features related to recording expenses
- Receipt uploads
- Expense categorization

### Reporting & Analytics Bucket
- Features for viewing data
- Charts, graphs, summaries
- Export functionality

### Administration Bucket
- Configuration and settings
- System integrations (WooCommerce, etc.)
- Plugin management tools
- **Admin-only features**

---

## Quick Reference Table

| Capability | Bucket | Admin | Team Member |
|-----------|--------|-------|-------------|
| `access_nexus_clock` | Time Tracking | ✅ | ✅ |
| `access_nexus_manual_entry` | Time Tracking | ✅ | ✅ |
| `access_nexus_record_expenses` | Expense Management | ✅ | ✅ |
| `access_nexus_view_reports` | Reporting | ✅ | ✅ |
| `access_nexus_settings` | Administration | ✅ | ❌ |
| `access_nexus_process_time` | Administration | ✅ | ❌ |
| `access_nexus_dashboard` | Core | ✅ | ✅ |

---

## Troubleshooting

### Dashboard Shows No Cards

**Cause:** User doesn't have any Nexus capabilities.

**Solution:**
```php
// Check user's capabilities
$user = wp_get_current_user();
$caps = array_keys($user->allcaps);
print_r($caps);

// Re-register capabilities
timegrow_nexus_register_capabilities();
```

### Team Member Sees Admin Features

**Cause:** Team member role has admin capabilities assigned.

**Solution:**
```php
// Remove admin-only caps from team_member role
$team_role = get_role('team_member');
$team_role->remove_cap('access_nexus_settings');
$team_role->remove_cap('access_nexus_process_time');
```

### New Feature Not Showing

**Checklist:**
1. ✅ Capability added to capability array in `aragrow-timegrow-nexus.php`
2. ✅ Menu page registered with correct capability in `TimeGrowNexus.php`
3. ✅ Dashboard card wrapped in `current_user_can()` check
4. ✅ Capabilities re-registered (visit admin page or reactivate plugin)
5. ✅ User's role has the capability

---

**Document Version:** 1.0
**Last Updated:** 2026-02-12
**Maintained By:** ARAGROW, LLC
