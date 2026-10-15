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

An hours invoice is an order built by the TimeGrow order creator. The creator sets the flag (`_timegrow_qty_unit = hours`) when it builds the order. The flag cannot be turned on or off later: the order editor shows "Hours invoice (created by TimeGrow, cannot be changed)" and has no checkbox. Orders you create by hand are never hours invoices.

In the order editor, on an hours invoice:

- The **Price** column is relabeled **Rate**, and **Qty** becomes **Hours**.
- Each line has an Hours box in place of the Quantity box. **The quantity stays 1.** The hours live on the line in `_timegrow_project_hours`, and the line total is hours x rate.
- **A line is repriced only when its hours change.** It keeps its own rate (subtotal / stored hours), so saving the order for any other reason (status, notes) never changes its totals, and a later rate change never reprices an old invoice. A line with no rate yet uses the project, client, then company rate.
- Any discount ratio on the line is kept. Taxes and order totals are recalculated, and an order note is added.
- The customer order page shows "3.75 hrs" in place of "x 1". WooCommerce emails still show the quantity (1).

Stored data:

| Meta | Where | Meaning |
|---|---|---|
| `_timegrow_qty_unit` | order | `hours` on hours invoices. Set only by the creator. Read by the editor, PayPal and the PDF. |
| `_timekeeping_invoice` | order | Older flag, still set by the creator and read by time entry code and the PDF header check. |
| `_timegrow_project_hours` | line item | The hours billed on the line. Hidden in the editor. |

Files: `assets/hours-invoice.js` (editor behavior), the hours methods in the main module file. The script re-applies the labels after Recalculate, line saves and reloads by watching the items box.

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
- **PDF invoice template** for the PDF Invoices & Packing Slips plugin, in `modules/woocommerce-integration/templates/pdf/aragrow/`. TimeGrow registers that folder with the PDF plugin, where it is listed as **timegrow/aragrow** (PDF Invoices > Settings > Template). Hours invoices use the headers Project, Hours, Rate, Total, with hours from `_timegrow_project_hours` and rate = line total / hours. Regular orders use Product, Quantity, Price, Total, with the plain quantity and unit price. The old theme copies in `astra-child` and `aragrow-no-front-end-theme` are no longer used.

### Time-entry order creator

`modules/core/includes/models/TimeGrowWooOrderCreator.php` turns billable, unbilled time entries into orders, one per client.

- **Hours, not units.** Each project becomes one line. The hours worked (two decimals) go in `_timegrow_project_hours` and the quantity is 1. The line subtotal and total are hours x hourly rate. Earlier versions rounded up to 10-minute units and priced each unit at one sixth of the rate. That is gone.
- **Rate.** Project, then client (user meta), then company, then 75 as a last resort. The client step now reads the profile field. It used to read an unused column on the users table, so it was always 0.
- **Markers.** New orders get `_timekeeping_invoice` and `_timegrow_qty_unit = hours`. This is the only place an order becomes an hours invoice. Each line gets `_timegrow_project_hours` and a message such as "3.75 hrs. At a rate of 75 per hour."
- **Appending.** Entries are added to an existing Pending order for the client only if that order carries the `hours` marker and has not been issued yet (no PDF invoice number, no PayPal invoice ID). Otherwise a new order is created.
- It creates the project's product if it does not exist, adds the customer note "Time entries for client ID: N", and sets the order to Processing.

## PayPal auto invoicer

Settings page: **Settings > PayPal Integration**, stored in the `wc_pp_auto_invoicer_settings` option.

| Setting | Notes |
|---|---|
| Send orders to PayPal | On/off switch. When off, setting an order to Send Invoice to PayPal only adds an order note. On by default. |
| Environment | Sandbox or Live. |
| Client ID, Client Secret | PayPal REST credentials. Stored encrypted with AES-256-CBC. |
| Send behavior | "Create & Send invoice" or "Create only". |
| Merchant email | Optional. |

**Encryption key:** define `WC_PAYPAL_SECRET_KEY` in `wp-config.php`. Without it, saving the settings stores empty credentials. Keep the key out of version control.

### What happens when an order is sent

1. Set the order status to **Send Invoice to PayPal** (`wc-inv_send2paypal`). If **Send orders to PayPal** is off, the module adds a note and stops.
2. The module gets an OAuth token from PayPal.
3. It builds the invoice lines. Shipping and tax become separate "Shipping" and "Tax" lines. The invoice number comes from the PDF Invoices plugin.
4. For each line with a SKU, it creates a PayPal catalog product if needed. The mapping is cached in the `wc_pp_auto_invoicer_product_map` option.
5. It builds the recipient from the client (see below). Without a valid email it stops with an order note.
6. It creates the invoice and saves the PayPal invoice ID in `_paypal_invoice_id` on the order. If the order already has an ID (for example, an earlier send failed), it reuses that invoice instead of creating a second one.
7. With "Create & Send", it tells PayPal to send the invoice, adds a note, and emails the customer a short notice. With "Create only", it adds a note that the invoice is a draft. No customer email goes out.
8. Only after that does the order move to **Invoice Sent to PayPal** (`wc-inv_sent2paypal`). API errors are added as order notes and the status stays where it was.

### The client in PayPal

PayPal's invoicing API has no separate "create customer" call. The recipient on each invoice becomes a customer in PayPal. So the module makes sure each invoice carries the client's details, taking each field from the order's billing details first, then the client's WooCommerce customer profile, then the WordPress user:

- Email (required), first and last name (or display name), company as the business name.
- Address, sent only when it has a country, because PayPal rejects an address without one.

Phone numbers are not sent. PayPal needs them split into country code and number.

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
