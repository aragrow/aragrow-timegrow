<?php
// Temporary debug script - DELETE THIS FILE AFTER DEBUGGING
require_once('../../../wp-load.php');

echo "=== AI Configurations Debug ===\n\n";

$configs = get_option('aragrow_timegrow_ai_configurations', []);
echo "Total configurations in database: " . count($configs) . "\n\n";

foreach ($configs as $id => $config) {
    echo "Config ID: " . $id . "\n";
    echo "Name: " . ($config['config_name'] ?? 'Not set') . "\n";
    echo "Active: " . (isset($config['is_active_config']) && $config['is_active_config'] ? 'YES' : 'NO') . "\n";
    echo "Provider: " . ($config['ai_provider'] ?? 'Not set') . "\n";
    echo "Model: " . ($config['ai_model'] ?? 'Not set') . "\n";
    echo "\n";
}
