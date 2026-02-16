<?php
// Temporary debug script to check expense categories
require_once(__DIR__ . '/../../../wp-load.php');

global $wpdb;
$table_name = $wpdb->prefix . 'timegrow_expense_categories';

$categories = $wpdb->get_results("SELECT slug, name, is_active FROM {$table_name} WHERE is_active = 1 ORDER BY sort_order LIMIT 20");

echo "<h2>Active Expense Categories</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Slug</th><th>Name</th><th>Active</th></tr>";

foreach ($categories as $cat) {
    echo "<tr>";
    echo "<td>{$cat->slug}</td>";
    echo "<td>{$cat->name}</td>";
    echo "<td>{$cat->is_active}</td>";
    echo "</tr>";
}

echo "</table>";
echo "<p>Total active categories: " . count($categories) . "</p>";
