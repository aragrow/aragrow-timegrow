# TimeGrow to WooCommerce Integration

## Overview

The TimeGrow plugin integrates with WooCommerce to automatically create invoices/orders from billable time entries and expenses. This allows you to seamlessly convert tracked time into invoices for clients.

## Architecture

### Key Components

1. **Time Entry Tracker** (`wp_tg_time_entry_tracker` table)
   - Stores all time entries (manual and clocked)
   - Links to projects, team members, and clients
   - Tracks billable status and billed status

2. **WooCommerce Order Creator** (`TimeGrowWooOrderCreator.php`)
   - Creates or updates WooCommerce orders from time entries
   - Groups time entries by client and project
   - Links time entries to WooCommerce orders via `billed_order_id`

3. **Process Time Functionality** (Nexus Dashboard)
   - Admin action to process unbilled time entries
   - Generates WooCommerce orders/invoices

## Database Schema

### Time Entry Table
```sql
wp_tg_time_entry_tracker (
    ID                  BIGINT(20) UNSIGNED PRIMARY KEY,
    project_id          BIGINT(20) UNSIGNED,
    member_id           BIGINT(20) UNSIGNED,
    date                DATETIME,
    hours               DECIMAL(5,2),
    billable            TINYINT(1),           -- Is this time billable?
    billed              TINYINT(1) DEFAULT 0, -- Has this been invoiced?
    billed_order_id     BIGINT(20) UNSIGNED,  -- Link to WooCommerce order
    description         TEXT,
    entry_type          VARCHAR(10),          -- 'MAN' or 'CLOCK'
    created_at          TIMESTAMP,
    updated_at          TIMESTAMP,

    FOREIGN KEY (project_id) REFERENCES wp_tg_project_tracker(ID),
    FOREIGN KEY (member_id) REFERENCES wp_tg_team_member_tracker(ID),
    FOREIGN KEY (billed_order_id) REFERENCES wp_wc_orders(ID)
)
```

### WooCommerce Orders Table
```sql
wp_wc_orders (
    id                  BIGINT(20) UNSIGNED PRIMARY KEY,
    customer_id         BIGINT(20) UNSIGNED,  -- Links to wp_users
    date_created_gmt    DATETIME,
    status              VARCHAR(20),          -- 'wc-pending', 'wc-completed', etc.
    total_amount        DECIMAL(10,2),
    payment_method      VARCHAR(100),
    payment_method_title VARCHAR(200),
    type                VARCHAR(20)           -- 'shop_order'
)
```

## Process Flow

### 1. Time Entry Creation

Users create time entries through:
- **Clock In/Out** - Automatic time tracking
- **Manual Entry** - Manually entered hours

```php
// Time entry is created
$data = [
    'project_id'  => $project_id,
    'member_id'   => $member_id,
    'date'        => $date,
    'hours'       => $hours,
    'billable'    => 1,           // Mark as billable
    'billed'      => 0,           // Not yet invoiced
    'entry_type'  => 'MAN',
    'description' => $description
];
```

### 2. Process Time (Create Invoices)

Admin navigates to: **Nexus Dashboard → Process Time**

File: `TimeGrowNexus.php::tracker_mvc_admin_page('process_time')`

```php
// Get unbilled time entries
$time_entries = $model->get_time_entries_to_bill();

// Create WooCommerce orders
$woo_order_creator = new TimeGrowWooOrderCreator();
list($orders, $mark_time_entries_as_billed) =
    $woo_order_creator->create_woo_orders_and_products($time_entries);

// Mark entries as billed
$model->mark_time_entries_as_billed($mark_time_entries_as_billed);
```

### 3. Group Time Entries by Client & Project

File: `TimeGrowWooOrderCreator.php::create_woo_orders_and_products()`

```php
// Group entries by client, then by project
$entries_by_clients = [];
foreach ($time_entries as $entry) {
    if (!isset($entries_by_clients[$entry->client_id])) {
        $entries_by_clients[$entry->client_id] = [];
    }
    if (!isset($entries_by_clients[$entry->client_id][$entry->project_id])) {
        $entries_by_clients[$entry->client_id][$entry->project_id] = [];
    }
    $entries_by_clients[$entry->client_id][$entry->project_id][] = $entry;
}
```

### 4. Create or Reuse WooCommerce Orders

For each client:

```php
// Check for existing pending order
$order = wc_get_orders([
    'customer_id' => $client_id,
    'status'      => 'pending',
    'limit'       => -1
]);

if (!empty($order)) {
    // Use existing pending order
    $order = $order[0];
} else {
    // Create new order
    $order = wc_create_order([
        'customer_id'   => $client_id,
        'status'        => 'pending',
        'customer_note' => 'Time entries for client ID: ' . $client_id
    ]);

    // Set billing address
    $customer = new WC_Customer($client_id);
    $order->set_address($billing_address, 'billing');
    $order->add_meta_data('_timekeeping_invoice', true);
}
```

### 5. Add Products to Order

For each project within the client's entries:

```php
// Get WooCommerce product linked to the project
$project = $model_project->select($project_id);
$product_id = $project[0]->product_id;
$woo_product = wc_get_product($product_id);

// Get billing rate (from project or client)
$rate = $model_project->get_project_rate($project_id);
if (!$rate || $rate == 0) {
    $rate = $model_project->get_client_rate($client_id);
}

// Calculate total hours for this project
$total_hours = 0;
foreach ($entries as $entry) {
    $total_hours += floatval($entry->hours);
}

// Add product to order
$order->add_product($woo_product, 1, [
    'subtotal' => $total_hours * $rate,
    'total'    => $total_hours * $rate
]);

// Link time entries to this order
foreach ($entries as $entry) {
    $entry->billed_order_id = $order->get_id();
    $mark_time_entries_as_billed[] = $entry->ID;
}
```

### 6. Save Order

```php
$order->calculate_totals();
$order->save();

// Return order IDs
$order_ids[] = $order->get_id();
```

### 7. Mark Time Entries as Billed

File: `TimeGrowTimeEntryModel.php::mark_time_entries_as_billed()`

```php
public function mark_time_entries_as_billed($entry_ids) {
    global $wpdb;

    $ids = implode(',', array_map('intval', $entry_ids));

    $query = "UPDATE {$this->table_name}
              SET billed = 1,
                  updated_at = CURRENT_TIMESTAMP
              WHERE ID IN ($ids)";

    $wpdb->query($query);
}
```

## Key Features

### Billable vs Non-Billable Time

- Only time entries marked as `billable = 1` are processed
- Non-billable time is tracked but never invoiced
- Projects determine default billable status

### Order Reuse Logic

- **Existing Pending Order**: Time is added to existing invoice
- **No Pending Order**: New invoice is created
- **Completed Orders**: Never modified, always create new

### Billing Rates

Priority order:
1. Project-specific rate (`default_flat_fee` in `wp_tg_project_tracker`)
2. Client-specific rate (if project rate = 0)
3. Default rate

### Order Metadata

```php
$order->add_meta_data('_timekeeping_invoice', true);
```

This flag identifies orders created from TimeGrow vs manual WooCommerce orders.

## Status Flow

```
Time Entry Created
    ↓
billable = 1, billed = 0
    ↓
Admin clicks "Process Time"
    ↓
WooCommerce Order Created (status: pending)
    ↓
billed = 1, billed_order_id = {order_id}
    ↓
Client receives invoice (WooCommerce email)
    ↓
Client pays
    ↓
Order status → 'wc-completed' or 'wc-processing'
    ↓
Shows in Tax Report as "Paid Invoice"
```

## Tax Report Integration

The yearly tax report shows:

1. **Billable Time Entries** - All time marked as billable
2. **Paid Invoices Only** - WooCommerce orders with status:
   - `wc-completed`
   - `wc-processing`

Query:
```php
SELECT o.*
FROM wp_wc_orders o
WHERE YEAR(o.date_created_gmt) = 2024
AND o.type = 'shop_order'
AND o.status IN ('wc-completed', 'wc-processing')
```

## Admin Actions

### Process Time Button
- Location: **Nexus Dashboard → Process Time**
- Action: Creates invoices from unbilled time
- Result: Displays created order IDs

### View in WooCommerce
- Orders appear in **WooCommerce → Orders**
- Can be edited, emailed, or processed normally
- Include all WooCommerce functionality (taxes, shipping, etc.)

## Important Notes

1. **One-Way Sync**: Time entries create orders, but editing orders doesn't update time entries
2. **No Deletion**: Deleting an order doesn't reset `billed` status on time entries
3. **Product Requirement**: Each project must have a linked WooCommerce product
4. **Customer Requirement**: Each client must be a WooCommerce customer (WordPress user)

## Files Reference

| File | Purpose |
|------|---------|
| `TimeGrowNexus.php` | Main controller, handles "Process Time" action |
| `TimeGrowWooOrderCreator.php` | Creates WooCommerce orders from time entries |
| `TimeGrowTimeEntryModel.php` | Database operations for time entries |
| `TimeGrowProjectModel.php` | Project and rate queries |
| `TimeGrowReportsController.php` | Tax report generation with invoices |

## Troubleshooting

### Orders Not Creating
- Check that time entries have `billable = 1`
- Verify project has a valid `product_id`
- Ensure client exists as WooCommerce customer
- Check error logs for SQL errors

### Orders Missing from Tax Report
- Verify order status is `wc-completed` or `wc-processing`
- Check `date_created_gmt` is within selected year
- Ensure order type is `shop_order`

### Duplicate Orders
- Check for multiple pending orders for same client
- Verify Process Time isn't run multiple times
- Clear pending orders before reprocessing
