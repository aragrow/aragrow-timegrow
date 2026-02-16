<?php
/**
 * Cleanup script - Remove mobile capabilities from administrator role
 * Run this once to fix the issue
 */

require_once __DIR__ . '/../../../wp-load.php';

$admin_role = get_role('administrator');

if ($admin_role) {
    $admin_role->remove_cap('access_mobile_only_mode');
    $admin_role->remove_cap('access_mobile_time_tracking');
    $admin_role->remove_cap('access_mobile_expenses');

    echo "✓ Removed mobile capabilities from administrator role\n";
    echo "✓ Administrators will no longer be restricted by mobile-only mode\n";
} else {
    echo "✗ Administrator role not found\n";
}

echo "\nDone! Please refresh your WordPress admin page.\n";
