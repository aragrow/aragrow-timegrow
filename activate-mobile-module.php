<?php
/**
 * Manual activation script for mobile module
 * Run this once to create the mobile_pins table
 */

require_once __DIR__ . '/../../../wp-load.php';

global $wpdb;

$table_name = $wpdb->prefix . 'timegrow_mobile_pins';
$charset_collate = $wpdb->get_charset_collate();

$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
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
    KEY idx_user_active (user_id, is_active),
    KEY idx_capabilities (has_time_tracking, has_expenses)
) {$charset_collate};";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
dbDelta($sql);

// Flush rewrite rules to register /mobile-login URL
flush_rewrite_rules();

echo "Mobile module activated successfully!\n";
echo "Table created: {$table_name}\n";
echo "Rewrite rules flushed.\n";
echo "\nYou can now access the mobile settings at:\n";
echo admin_url('admin.php?page=timegrow-mobile-settings') . "\n";
echo "\nMobile login URL:\n";
echo home_url('/mobile-login') . "\n";
