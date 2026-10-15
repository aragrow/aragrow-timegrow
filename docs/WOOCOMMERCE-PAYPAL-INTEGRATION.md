# WooCommerce and PayPal integrations

How TimeGrow connects to WooCommerce for projects, invoices and payments, and how it sends invoices to PayPal. This reflects the code in branch `2.2.0`.

Both integrations are modules inside the TimeGrow plugin. Neither is a separate plugin.

| Module | Folder | Main file |
|---|---|---|
| WooCommerce integration | `modules/woocommerce-integration/` | `aragrow-woocommerce-intengration.php` |
| PayPal auto invoicer | `modules/paypal-invoicer/` | `aragrow-wc-paypal-auto-invoicer.php` |

The main plugin file loads both. WooCommerce must be active for either to work.

## Order lifecycle

```
Time entries ──> Order creator ──> Pending payment ──> Invoice Sent to Client
                (real hours)              │
                                          ├──> Send Invoice to PayPal ──> Invoice Sent to PayPal
                                          │
                                          └──> Partial Paid / Invoice Paid
```

The order creator and the **hours invoice** editor both work in real hours. Quantity on a time invoice is the hours worked.

## Settings

TimeGrow > WooCommerce integration (`timegrow_integration_settings` option):

| Setting | Key | What it does |
|---|---|---|
| WooCommerce Clients | `wc_clients` | Syncs clients with WooCommerce customers. Blocks admin access for the `customer` role. |
| WooCommerce Invoices | `wc_invoices` | Invoice synchronization. |
| WooCommerce Products | `wc_products` | Creates and updates a product for each project. |
| Allow decimal quantities | `wc_decimal_quantities` | Lets order lines use quantities like 3.75. On by default. See [DECIMAL-QUANTITIES.md](DECIMAL-QUANTITIES.md). |

## WooCommerce integration

### Projects and products

With **WooCommerce Products** on:

- Adding a project creates a product named "Client - Project", linked through `product_id` on the project.
- Saving a project updates that product's name and price. If the project has no product, one is created.
- The product is virtual, hidden from the catalog, published, and in the `timegrow` category.
- The price is the hourly rate: the project's rate, then the client's, then the company's.
- The add form has no product dropdown. The edit form keeps one, so a product can be relinked. Relinking renames the chosen product, so do not point two projects at one product.

With the setting off, projects save without a product (stored as NULL).

### Hourly rate order

Used for product prices and for hours invoice lines:

1. **Project:** `default_flat_fee` on the project.
2. **Client:** the user meta `timegrow_default_flat_fee` on the project's client. If no project matches the product, the order's customer is used.
3. **Company:** `default_flat_fee` on company 1.

A rate of 0 or empty counts as not set. If none is set, an order line keeps its current rate.

### Hours invoices

On an order, tick **Is Hours Invoice?** (under the billing address). In the order editor:

- The **Price** column is relabeled **Rate**, and **Qty** becomes **Hours**.
- Each line gets an Hours box in the quantity column. Typing hours updates the line subtotal and total from the rate.
- **The quantity stays 1.** The hours live on the line in `_timegrow_project_hours`, and the line total is hours x rate.
- Click **Update** (or the line's **Save**) to store it. The server sets the subtotal to rate x hours (any discount ratio is kept), sets the quantity to 1, recalculates taxes and totals, and adds an order note.
- Saving marks the order with `_timegrow_qty_unit = hours`. Unchecking the box and saving gives each line its hours back as a normal quantity and removes the marker.
- Order views and emails show "3.75 hrs" in place of "x 1".

Stored data:

| Meta | Where | Meaning |
|---|---|---|
| `_timekeeping_invoice` | order | The "Is Hours Invoice?" checkbox. Stored as `yes` in post meta and `1` in order meta. |
| `_timegrow_qty_unit` | order | `hours` when quantities are real hours. Read by the PayPal module. |
| `_timegrow_project_hours` | line item | The hours billed on the line. The quantity is 1 on hours invoices. Hidden in the editor. |

Files: `assets/hours-invoice.js` (editor behavior), the hours methods in the main module file. The script re-applies the labels after Recalculate, line saves and reloads by watching the items box.

**Old 10-minute orders are skipped.** Before this change, the order creator counted quantity in 10-minute units. The hours editor leaves those old orders alone, because it would reprice them. An order passes as an hours invoice when it has the `hours` marker, or a line the editor already handled (`_timegrow_project_hours`). Otherwise an order is treated as an old 10-minute order if its customer note starts "Time entries for client ID" or a line message contains "10 minutes increments".

### Order statuses

The module registers these statuses, with admin colors, emails and PDF Invoices support:

| Status | Slug |
|---|---|
| Invoice Sent to Client | `wc-invoice_sent` |
| Partial Paid | `wc-partial_paid` |
| Invoice Paid | `wc-invoice_paid` |

Moving an order to Invoice Paid records the payment date (today) and sets the payment amount to the order total, if they are empty. Partial Paid needs a payment amount above 0.

### Manual payments

A **Manual Payment Details** box sits in the side column of the order editor. It stores `_payment_date`, `_payment_amount`, `_payment_type` and `_payment_trans_id` on the order.

### Other pieces

- **EIN/SSN field** on user profiles, with a column in the Users list (`_timegrow_ein_ssn` user meta).
- **Unpaid Invoices** dashboard widget, listing orders in the Pending payment status.
- **PDF invoice headers** for the PDF Invoices & Packing Slips plugin. The theme template (`woocommerce/pdf/aragrow/invoice.php` in `astra-child` and `aragrow-no-front-end-theme`) reads hours from `_timegrow_project_hours` for hours lines and divides the line total by the hours for the rate. The `aragrow_time_invoice` template uses Project, Hours, Rate, Total. The `aragrow_invoice` template uses Product, Quantity, Price, Total.

### Time-entry order creator

`modules/core/includes/models/TimeGrowWooOrderCreator.php` turns billable, unbilled time entries into orders, one per client.

- **Hours, not units.** Each project becomes one line. The hours worked (two decimals) go in `_timegrow_project_hours` and the quantity is 1. The line subtotal and total are hours x hourly rate. Earlier versions rounded up to 10-minute units and priced each unit at one sixth of the rate. That is gone.
- **Rate.** Project, then client (user meta), then company, then 75 as a last resort. The client step now reads the profile field. It used to read an unused column on the users table, so it was always 0.
- **Markers.** New orders get `_timekeeping_invoice` and `_timegrow_qty_unit = hours`. Each line gets `_timegrow_project_hours` and a message such as "3.75 hrs. At a rate of 75 per hour."
- **Appending.** Entries are added to an existing Pending order for the client only if that order already carries the `hours` marker. Otherwise a new order is created.
- It creates the project's product if it does not exist, adds the customer note "Time entries for client ID: N", and sets the order to Processing.

## PayPal auto invoicer

Settings page: **Settings > PayPal Integration**, stored in the `wc_pp_auto_invoicer_settings` option.

| Setting | Notes |
|---|---|
| Environment | Sandbox or Live. |
| Client ID, Client Secret | PayPal REST credentials. Stored encrypted with AES-256-CBC. |
| Send behavior | "Create & Send invoice" or "Create only". |
| Merchant email | Optional. |

**Encryption key:** define `WC_PAYPAL_SECRET_KEY` in `wp-config.php`. Without it, saving the settings stores empty credentials. Keep the key out of version control.

### What happens when an order is sent

1. Set the order status to **Send Invoice to PayPal** (`wc-inv_send2paypal`).
2. The module gets an OAuth token from PayPal.
3. It builds a PayPal invoice from the order lines. Shipping and tax become separate "Shipping" and "Tax" lines. The recipient is the order's billing email and name. The invoice number comes from the PDF Invoices plugin.
4. For each line with a SKU, it creates a PayPal catalog product if needed. The mapping is cached in the `wc_pp_auto_invoicer_product_map` option.
5. It posts the invoice, saves the PayPal invoice ID in `_paypal_invoice_id`, and moves the order to **Invoice Sent to PayPal** (`wc-inv_sent2paypal`).
6. With "Create & Send", it also tells PayPal to email the invoice. The order gets a note either way.
7. The customer gets a short notice email. API errors are added as order notes.

### Quantity and rate sent to PayPal

Hours invoice lines have a quantity of 1 and their hours in `_timegrow_project_hours`. The module sends those hours as the PayPal quantity, with the line total divided by the hours as the unit rate. So 3.75 hours at 75.00 goes to PayPal as 3.75 x 75.00. Every other line sends its plain quantity. There is no unit conversion.

Orders created before this change used 10-minute units, so their PayPal invoices would show those units at the per-unit rate. The totals stay correct.


## Known issues

- The PDF filter and admin status color use `invoice_sent2paypal`, but the real status is `inv_sent2paypal`, so they do not apply to sent orders.
- The settings page pre-fills the decrypted client secret in the page source.
- The PayPal module does not check that WooCommerce is active.
- The module README says invoices go out when an order is Completed. The code uses the Send Invoice to PayPal status.

## Related files

- [DECIMAL-QUANTITIES.md](DECIMAL-QUANTITIES.md)
- `modules/core/includes/TimeGrowIntegration.php` (settings page)
- `modules/core/includes/controllers/TimeGrowProjectController.php` (product sync)
