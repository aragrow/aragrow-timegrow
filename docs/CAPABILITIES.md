# TimeGrow Report Capabilities Reference

**Plugin:** Aragrow - TimeGrow
**Version:** 1.1.1
**Last Updated:** 2026-02-12

---

## Overview

TimeGrow uses custom WordPress capabilities to control access to reports. Each report has a corresponding capability that determines who can view it.

**Capability Naming Convention:**
- All capabilities start with `view_`
- The rest of the capability name directly matches the report title (converted to lowercase with underscores)
- Example: "Team Hours Summary" → `view_team_hours_summary`

---

## Report Capabilities Mapping

### Admin-Only Reports

These capabilities are assigned ONLY to the Administrator role:

| Report Title | Capability Name | Description |
|-------------|-----------------|-------------|
| **Team Hours Summary** | `view_team_hours_summary` | Total hours worked by each team member across all projects |
| **Project Financials** | `view_project_financials` | Overview of hours, expenses, and billing for projects |
| **Client Activity Report** | `view_client_activity_report` | Summary of hours and expenses logged against each client |
| **All Expenses Overview** | `view_all_expenses_overview` | Detailed breakdown of all recorded expenses with filtering |
| **Time Entry Audit Log** | `view_time_entry_audit_log` | Detailed log of all time entries, edits, and deletions |

### Team Member Reports

These capabilities are assigned to BOTH Administrator and Team Member roles:

| Report Title | Capability Name | Description |
|-------------|-----------------|-------------|
| **Yearly Tax Report** | `view_yearly_tax_report` | Yearly report including time charges and expenses for tax purposes |
| **My Detailed Time Log** | `view_my_detailed_time_log` | Comprehensive log of all your clocked hours and manual entries |
| **My Hours by Project** | `view_my_hours_by_project` | Breakdown of your hours spent on different projects |
| **My Expenses Report** | `view_my_expenses_report` | List of all expenses you have recorded |

---

## Role Assignments

### Administrator Role
**Receives ALL capabilities:**
- ✅ `view_team_hours_summary`
- ✅ `view_project_financials`
- ✅ `view_client_activity_report`
- ✅ `view_all_expenses_overview`
- ✅ `view_time_entry_audit_log`
- ✅ `view_yearly_tax_report`
- ✅ `view_my_detailed_time_log`
- ✅ `view_my_hours_by_project`
- ✅ `view_my_expenses_report`

**Total:** 9 capabilities

### Team Member Role
**Receives ONLY individual/personal report capabilities:**
- ❌ `view_team_hours_summary` (Admin only)
- ❌ `view_project_financials` (Admin only)
- ❌ `view_client_activity_report` (Admin only)
- ❌ `view_all_expenses_overview` (Admin only)
- ❌ `view_time_entry_audit_log` (Admin only)
- ✅ `view_yearly_tax_report`
- ✅ `view_my_detailed_time_log`
- ✅ `view_my_hours_by_project`
- ✅ `view_my_expenses_report`

**Total:** 4 capabilities

---

## Using Capabilities in Code

### Check if Current User Can View a Report

```php
// Check specific capability
if (current_user_can('view_client_activity_report')) {
    // User can view Client Activity Report
}

// Check from report definition
$report = [
    'title' => 'Client Activity Report',
    'capability' => 'view_client_activity_report'
];

if (current_user_can($report['capability'])) {
    // User has access to this report
}
```

### Check for Any User

```php
$user = get_user_by('ID', 123);

if (user_can($user, 'view_my_hours_by_project')) {
    // This user can view My Hours by Project
}
```

### Get All Users with a Specific Capability

```php
$args = [
    'capability' => 'view_team_hours_summary'
];
$users = get_users($args);
// Returns all users who can view Team Hours Summary (typically admins)
```

---

## Capability Registration

Capabilities are automatically registered when:

1. **Plugin Activation** - The `timegrow_plugin_activate()` function registers all capabilities
2. **Admin Init Hook** - On every admin page load, the system checks if capabilities exist and registers them if missing

### Manual Registration

If you need to manually register capabilities (e.g., after adding a new report):

```php
// Call the registration function
timegrow_register_report_capabilities();
```

### Check if Capabilities are Registered

```php
$admin = get_role('administrator');
if ($admin && $admin->has_cap('view_client_activity_report')) {
    // Capabilities are registered
} else {
    // Need to register capabilities
    timegrow_register_report_capabilities();
}
```

---

## Adding New Report Capabilities

When adding a new report, follow these steps:

### 1. Add Report Definition

**File:** `/includes/controllers/TimeGrowReportsController.php`

```php
[
    'slug' => 'new_report_slug',
    'title' => 'My New Report Title',
    'description' => 'Description of what this report does.',
    'icon' => 'dashicons-chart-bar',
    'roles' => ['administrator'], // or ['team_member', 'administrator']
    'capability' => 'view_my_new_report_title', // Match the title!
    'category' => 'Appropriate Category'
]
```

### 2. Register Capability

**File:** `/aragrow-timegrow.php`

Add to either `$admin_reports` or `$team_member_reports` array:

```php
// For admin-only report
$admin_reports = [
    'view_team_hours_summary',
    'view_project_financials',
    'view_my_new_report_title',  // Add here
    // ... other capabilities
];

// For team member report
$team_member_reports = [
    'view_yearly_tax_report',
    'view_my_new_report_title',  // Add here
    // ... other capabilities
];
```

### 3. Trigger Registration

After adding the capability:
- Deactivate and reactivate the plugin, OR
- Visit any admin page (capabilities auto-register if missing), OR
- Run `timegrow_register_report_capabilities()` manually

---

## Troubleshooting

### Reports Not Showing for Administrator

**Cause:** Capabilities not registered for the admin role.

**Solution:**
```php
// Run this in WordPress admin or via wp-cli
timegrow_register_report_capabilities();
```

Or simply visit any admin page after saving changes to the plugin files.

### Team Member Can't See Individual Reports

**Cause:** Team member role doesn't exist or doesn't have capabilities.

**Solution:**
1. Check if `team_member` role exists:
```php
$role = get_role('team_member');
if (!$role) {
    // Role doesn't exist, registration will create it
    timegrow_register_report_capabilities();
}
```

2. Check capabilities:
```php
$role = get_role('team_member');
if ($role && !$role->has_cap('view_my_hours_by_project')) {
    // Missing capabilities, re-register
    timegrow_register_report_capabilities();
}
```

### New Capability Not Working

**Cause:** Report definition and capability registration don't match.

**Solution:**
1. Verify the capability name matches exactly in both files:
   - `TimeGrowReportsController.php` → `'capability' => 'view_example'`
   - `aragrow-timegrow.php` → `'view_example'` in capability array

2. Ensure capability follows naming convention:
   - Report Title: "My Example Report"
   - Capability: `view_my_example_report` (lowercase, underscores)

---

## Quick Reference Table

For easy lookup when managing capabilities in WordPress admin:

| Capability | Human-Readable Name | Admin | Team Member |
|------------|-------------------|-------|-------------|
| `view_team_hours_summary` | Team Hours Summary | ✅ | ❌ |
| `view_project_financials` | Project Financials | ✅ | ❌ |
| `view_client_activity_report` | Client Activity Report | ✅ | ❌ |
| `view_all_expenses_overview` | All Expenses Overview | ✅ | ❌ |
| `view_time_entry_audit_log` | Time Entry Audit Log | ✅ | ❌ |
| `view_yearly_tax_report` | Yearly Tax Report | ✅ | ✅ |
| `view_my_detailed_time_log` | My Detailed Time Log | ✅ | ✅ |
| `view_my_hours_by_project` | My Hours by Project | ✅ | ✅ |
| `view_my_expenses_report` | My Expenses Report | ✅ | ✅ |

---

## WordPress Core Integration

These capabilities integrate with WordPress's native roles and capabilities system:

- Stored in `wp_options` table under `wp_user_roles` option
- Managed via `get_role()`, `add_role()`, `remove_role()` functions
- Checked via `current_user_can()` and `user_can()` functions
- Compatible with role management plugins like:
  - User Role Editor
  - Members
  - PublishPress Capabilities

---

**Document Version:** 1.0
**Last Updated:** 2026-02-12
**Maintained By:** ARAGROW, LLC
