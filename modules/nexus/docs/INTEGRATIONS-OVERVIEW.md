# TimeGrow Integrations Overview

## Quick Reference Guide

This document provides a high-level overview of how TimeGrow integrates with WooCommerce and PayPal to create a complete time tracking → invoicing → payment workflow.

## Three-Part Integration Chain

```
┌──────────────┐      ┌──────────────┐      ┌──────────────┐
│   TimeGrow   │ ───> │ WooCommerce  │ ───> │    PayPal    │
│ Time Tracking│      │   Invoices   │      │   Invoices   │
└──────────────┘      └──────────────┘      └──────────────┘
```

## Part 1: TimeGrow → WooCommerce

**Purpose**: Convert tracked time into WooCommerce orders/invoices

**Trigger**: Admin clicks "Process Time" in Nexus Dashboard

**What Happens**:
1. Fetches all unbilled time entries (where `billable = 1` and `billed = 0`)
2. Groups entries by client, then by project
3. Creates/updates WooCommerce orders
4. Links time entries to orders via `billed_order_id`
5. Marks entries as billed

**Key File**: `TimeGrowWooOrderCreator.php`

**Database Impact**:
```sql
UPDATE wp_tg_time_entry_tracker
SET billed = 1,
    billed_order_id = {new_order_id}
WHERE ID IN ({billable_entry_ids})
```

**Detailed Documentation**: See `timegrow-woocommerce-integration.md`

---

## Part 2: WooCommerce → PayPal

**Purpose**: Automatically create PayPal invoices from WooCommerce orders

**Trigger**: Admin changes order status to "Send Invoice to PayPal"

**What Happens**:
1. Plugin detects status change to `wc-inv_send2paypal`
2. Authenticates with PayPal REST API
3. Creates PayPal catalog products (if needed)
4. Builds invoice payload from order data
5. Creates PayPal invoice
6. Optionally sends invoice to customer
7. Updates order status to `wc-inv_sent2paypal`
8. Saves PayPal invoice ID in order meta

**Key File**: `aragrow-wc-paypal-auto-invoicer.php`

**Database Impact**:
```sql
INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
VALUES ({order_id}, '_paypal_invoice_id', '{paypal_invoice_id}')
```

**Detailed Documentation**: See `woocommerce-paypal-integration.md`

---

## Complete Workflow Example

### Scenario
A freelancer tracks 10 hours of work for Client A on Project X at $100/hour.

### Step-by-Step Flow

#### 1️⃣ Time Tracking (TimeGrow)
```
User logs time:
- Date: 2024-01-15
- Project: Website Development (Project X)
- Client: Acme Corp (Client A)
- Hours: 10
- Billable: Yes
- Rate: $100/hour
```

**Database**:
```sql
INSERT INTO wp_tg_time_entry_tracker (
    project_id, member_id, date, hours,
    billable, billed, description
) VALUES (
    5, 3, '2024-01-15', 10.00,
    1, 0, 'Website Development'
);
```

#### 2️⃣ Process Time (TimeGrow → WooCommerce)
```
Admin: Nexus Dashboard → Process Time

Action:
- Fetch unbilled entries for Client A
- Calculate total: 10 hours × $100 = $1,000
- Create WooCommerce order
```

**Database**:
```sql
-- Create WooCommerce order
INSERT INTO wp_wc_orders (
    customer_id, status, total_amount
) VALUES (
    42, 'wc-pending', 1000.00
);

-- Link time entry to order
UPDATE wp_tg_time_entry_tracker
SET billed = 1, billed_order_id = 789
WHERE ID = 123;
```

**Result**: Order #789 created with status `wc-pending`

#### 3️⃣ Create PayPal Invoice (WooCommerce → PayPal)
```
Admin: WooCommerce → Orders → #789
Action: Change status to "Send Invoice to PayPal"

Automatic Process:
1. Get PayPal OAuth token
2. Create PayPal product for "Website Development"
3. Build invoice:
   - Customer: Acme Corp <client@acme.com>
   - Item: Website Development (10 hours)
   - Unit Price: $100.00
   - Quantity: 1
   - Total: $1,000.00
4. Create PayPal invoice: INV2-ABCD-1234
5. Send to customer
```

**Database**:
```sql
-- Save PayPal invoice ID
INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
VALUES (789, '_paypal_invoice_id', 'INV2-ABCD-1234');

-- Update order status
UPDATE wp_wc_orders
SET status = 'wc-inv_sent2paypal'
WHERE id = 789;
```

#### 4️⃣ Customer Payment
```
Customer receives:
- Email from WooCommerce: "Invoice sent to PayPal"
- Email from PayPal: "You have an invoice from ..."

Customer actions:
- Clicks "View & Pay Invoice"
- Pays via PayPal
- PayPal processes payment
```

#### 5️⃣ Order Completion
```
Admin:
- Receives payment notification
- Updates WooCommerce order: wc-completed
```

**Database**:
```sql
UPDATE wp_wc_orders
SET status = 'wc-completed'
WHERE id = 789;
```

#### 6️⃣ Tax Reporting
```
Year-end tax report shows:
- Billable Time: 10 hours
- Invoice: Order #789
- Amount: $1,000.00
- Status: Paid (wc-completed)
```

---

## Key Data Flow

### Time Entry Record
```
┌─────────────────────────────────────────┐
│ wp_tg_time_entry_tracker                │
├─────────────────────────────────────────┤
│ ID: 123                                 │
│ project_id: 5                           │
│ member_id: 3                            │
│ date: 2024-01-15                        │
│ hours: 10.00                            │
│ billable: 1                             │
│ billed: 1                               │
│ billed_order_id: 789 ──────────┐        │
└─────────────────────────────────────────┘
                                 │
                                 ▼
         ┌─────────────────────────────────────────┐
         │ wp_wc_orders                            │
         ├─────────────────────────────────────────┤
         │ id: 789                                 │
         │ customer_id: 42                         │
         │ status: wc-completed                    │
         │ total_amount: 1000.00                   │
         └─────────────────────────────────────────┘
                                 │
                                 ▼
         ┌─────────────────────────────────────────┐
         │ wp_postmeta                             │
         ├─────────────────────────────────────────┤
         │ post_id: 789                            │
         │ meta_key: _paypal_invoice_id            │
         │ meta_value: INV2-ABCD-1234              │
         └─────────────────────────────────────────┘
                                 │
                                 ▼
                    ┌──────────────────────┐
                    │  PayPal Invoice      │
                    │  INV2-ABCD-1234      │
                    │  Status: PAID        │
                    └──────────────────────┘
```

---

## Status Progression

```
Time Entry:
billable=0, billed=0  →  Not tracked for invoicing
billable=1, billed=0  →  Ready to invoice (shown in "Process Time")
billable=1, billed=1  →  Invoiced (linked to WooCommerce order)

WooCommerce Order:
wc-pending            →  Created, not yet sent to PayPal
wc-inv_send2paypal    →  Trigger: Create PayPal invoice
wc-inv_sent2paypal    →  PayPal invoice created & sent
wc-completed          →  Payment received (manual update)

PayPal Invoice:
DRAFT                 →  Created, not sent
SENT                  →  Sent to customer
PAID                  →  Customer paid
```

---

## Configuration Requirements

### TimeGrow Setup
- [x] Projects linked to WooCommerce products
- [x] Clients must be WooCommerce customers
- [x] Team members assigned to projects
- [x] Billing rates set (project or client level)

### WooCommerce Setup
- [x] Products created for each project
- [x] Customers set up with billing details
- [x] PDF Invoices plugin installed (for invoice numbers)
- [x] Email notifications enabled

### PayPal Setup
- [x] PayPal Business account
- [x] REST API credentials (Client ID & Secret)
- [x] Sandbox for testing, Live for production
- [x] Invoice settings configured

---

## Admin Workflows

### Weekly Invoicing Workflow
```
Monday:
1. Review time entries in Nexus Dashboard
2. Verify all entries are marked billable correctly
3. Click "Process Time"
4. Review created WooCommerce orders

Tuesday:
5. Go to WooCommerce → Orders
6. Change each order status to "Send Invoice to PayPal"
7. Verify PayPal invoices were sent (check order notes)

Throughout Week:
8. Monitor for payment notifications
9. Update paid orders to "Completed"
```

### Month-End Workflow
```
End of Month:
1. Go to Nexus → Reports → Yearly Tax Report
2. Select current year
3. Review:
   - Total billable hours
   - Total invoiced amount
   - Paid vs unpaid invoices
4. Export to CSV/PDF for accounting
5. Follow up on unpaid invoices
```

---

## Troubleshooting

### Time entries not appearing in "Process Time"
- Check `billable = 1`
- Check `billed = 0`
- Verify project has valid product_id

### WooCommerce orders not creating
- Check project-to-product mapping
- Verify client is WooCommerce customer
- Check error logs

### PayPal invoices not creating
- Verify API credentials
- Check order status is exactly `wc-inv_send2paypal`
- Review order notes for error messages
- Check PayPal sandbox vs live environment

### Invoices not in tax report
- Verify order status is `wc-completed` or `wc-processing`
- Check year filter
- Ensure orders are marked as paid

---

## Quick Reference: Files & Functions

| Component | File | Key Function |
|-----------|------|--------------|
| Process Time | `TimeGrowNexus.php` | `tracker_mvc_admin_page('process_time')` |
| Create Orders | `TimeGrowWooOrderCreator.php` | `create_woo_orders_and_products()` |
| PayPal Trigger | `aragrow-wc-paypal-auto-invoicer.php` | Hook: `woocommerce_order_status_inv_send2paypal` |
| PayPal Invoice | Same file | `handle_order_to_pay()` |
| Tax Report | `TimeGrowReportsController.php` | `generate_yearly_tax_report()` |

---

## Support & Documentation

- **TimeGrow → WooCommerce**: See `timegrow-woocommerce-integration.md`
- **WooCommerce → PayPal**: See `woocommerce-paypal-integration.md`
- **Tax Reporting**: Part of TimeGrow Reports system

All documentation located in:
`.claude/projects/[project]/memory/`
