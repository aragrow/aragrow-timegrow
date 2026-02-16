<?php
/**
 * Trigger rewrite rules flush
 * Simply sets a flag that will be checked on next page load
 */

// This sets a WordPress option that tells the plugin to flush rewrite rules
// You can run this by visiting: http://localhost:10003/wp-content/plugins/aragrow-timegrow/trigger-flush-rewrite.php

// Load WordPress
require_once __DIR__ . '/../../../wp-load.php';

// Set the flag
update_option('timegrow_mobile_flush_rewrite_rules', '1');

echo "✓ Rewrite flush triggered!\n\n";
echo "Now visit any WordPress admin page (after logging in) and the rewrite rules will be automatically flushed.\n\n";
echo "Then you can visit: " . home_url('/mobile-login') . "\n";
