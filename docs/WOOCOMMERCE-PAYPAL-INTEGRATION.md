# WooCommerce and PayPal integrations

How TimeGrow connects to WooCommerce for projects, invoices and payments, and how it sends invoices to PayPal. This reflects the code in branch `2.2.0`.

Both integrations are modules inside the TimeGrow plugin. Neither is a separate plugin.

| Module | Folder | Main file |
|---|---|---|
| WooCommerce integration | `modules/woocommerce-integration/` | `aragrow-woocommerce-intengration.php` |
| PayPal auto invoicer | `modules/paypal-invoicer/` | `aragrow-wc-paypal-auto-invoicer.php` |

The main plugin file loads both. WooCommerce must be active for either to work. Without it, neither module hooks in (both wait for `plugins_loaded` and check for WooCommerce), the Nexus dashboard leaves out the Print & Mail card, and Process Time refuses with "Process Time needs WooCommerce" before billing anything. The rest of TimeGrow keeps working.

## Order lifecycle

```
Time entries ──> Order creator ──> Pending payment ──> Invoice Sent to Client
                (real hours)              │
                                          ├──> Send Invoice to PayPal ──> Invoice Sent to PayPal
                                          │
                                          └──> Partial Paid / Invoice Paid ──> Refunded ──> Cancelled

Any unpaid invoice ──> Cancelled (PayPal invoice cancelled first, time released)
```

The order creator and the **hours invoice** editor both work in real hours. Quantity on a time invoice is the hours worked.

## Settings

TimeGrow > WooCommerce integration (`timegrow_integration_settings` option):

| Setting | Key | What it does |
|---|---|---|
| WooCommerce Clients | `wc_clients` | Syncs clients with WooCommerce customers. Blocks admin access for the `customer` role. |
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

With the setting off, projects save without a product (stored as NULL), and Process Time adds lines without a product, named "Client - Project". A project that already has a product keeps using it.

### Hourly rate order

Used for product prices and for hours invoice lines:

1. **Project:** `default_flat_fee` on the project.
2. **Client:** the user meta `timegrow_default_flat_fee` on the project's client. If no project matches the product, the order's customer is used.
3. **Company:** `default_flat_fee` on company 1.
4. **Default:** 75 an hour. Process Time adds an order note naming the projects billed at it.

A rate of 0 or empty counts as not set. One rule (`TimeGrowWooOrderCreator::resolve_rate()`) is used by Process Time, the hours editor, product prices and the reports. An hours invoice line keeps the rate it was billed at, so a later rate change never reprices it.

### Hours invoices

An hours invoice is an order built by the TimeGrow order creator. The creator sets the flag (`_timegrow_qty_unit = hours`) when it builds the order. The flag cannot be turned on or off later: the order editor shows "Hours invoice (created by TimeGrow, cannot be changed)" and has no checkbox. Orders you create by hand are never hours invoices.

In the order editor, on an hours invoice:

- The **Price** column is relabeled **Rate**, and **Qty** becomes **Hours**.
- Each line has an Hours box in place of the Quantity box. **The quantity stays 1.** The hours live on the line in `_timegrow_project_hours`, and the line total is hours x rate. A line added by hand (no hours stored) keeps its Quantity box and its price; the columns then read "Hours / Qty" and "Rate / Cost".
- **A line is repriced only when its hours change.** It keeps its own rate (subtotal / stored hours), so saving the order for any other reason (status, notes) never changes its totals, and a later rate change never reprices an old invoice. A line with no rate yet uses the project, client, then company rate.
- Any discount ratio on the line is kept. Taxes and order totals are recalculated, and an order note is added.
- The customer order page and WooCommerce emails show "3.75 hrs" in place of the quantity.

Stored data:

| Meta | Where | Meaning |
|---|---|---|
| `_timegrow_qty_unit` | order | `hours` on hours invoices. Set only by the creator. Read by the editor, PayPal and the PDF. |
| `_timekeeping_invoice` | order | Older flag, still set by the creator and read by time entry code and the PDF header check. |
| `_timegrow_project_hours` | line item | The hours billed on the line. Hidden in the editor. |
| `_timegrow_line_rate` | line item | The rate the line was priced at, kept even if the hours go to 0. A price edited by hand (hours unchanged) becomes the new rate. Hidden in the editor. |
| `_work_done` | line item | Time entry description, shown as "Work done". |
| `_display_message` | line item | Creator summary ("3.75 hrs. At a rate of ..."). Hidden in the editor. |

Files: `assets/hours-invoice.js` (editor behavior), the hours methods in the main module file. The script re-applies the labels after Recalculate, line saves and reloads by watching the items box.

### Order statuses

The module registers these statuses, with admin colors, emails and PDF Invoices support:

| Status | Slug |
|---|---|
| Invoice Sent to Client | `wc-invoice_sent` |
| Partial Paid | `wc-partial_paid` |
| Invoice Paid | `wc-invoice_paid` |
| To Be Mailed | `wc-to_be_mailed` (no client email, PDF allowed) |

Moving an order to Invoice Paid records the payment date (today) and sets the payment amount to the order total, if they are empty. Partial Paid needs a payment amount above 0.

### Invoice delivery

Code: `modules/woocommerce-integration/includes/class-invoice-delivery.php`. Each client has an **Invoice Delivery** field on their profile (`timegrow_invoice_delivery` user meta): `email` (default, also used for unknown values and guest orders), `paypal` or `mail`.

One order action, **Send invoice (<method>)**, sends the order the client's way. It is offered, and runs, only for an unpaid invoice that is not at PayPal: Pending payment, On hold, Processing (older orders), Invoice Sent to Client (send again) or To Be Mailed (`send_refusal()`). Sending changes the status quietly, without the status guard, so a cancelled, paid, refunded or PayPal order is never reopened this way.

| Method | What `send()` does | Order ends in |
|---|---|---|
| Email | Sends WooCommerce's Customer invoice email with the PDF attached (unless the PDF plugin already attaches it) | Invoice Sent to Client, set quietly (no second status email) |
| PayPal | Moves the order to Send Invoice to PayPal, so the auto invoicer takes over. Refused if the PayPal module is off | Invoice Sent to PayPal or Invoice Draft in PayPal |
| Print and mail | Moves the order to To Be Mailed. Nothing is emailed | To Be Mailed |

**Print & Mail** (`admin.php?page=timegrow-nexus-print-mail`, card on the Nexus dashboard, cap `access_nexus_process_time`) lists To Be Mailed orders with the billing address and a PDF link. **Mark as mailed** (nonce checked) moves the order quietly to Invoice Sent to Client with an order note.

Process Time leaves orders in Pending payment, so WooCommerce sends the client nothing until the invoice is sent one of the ways above. Orders made before 2.2.0 may still be in Processing.

The **Pay for this order** link in the invoice email keeps working once a TimeGrow invoice is Invoice Sent to Client or To Be Mailed (`payable_statuses()` on `woocommerce_valid_order_statuses_for_payment`). Clients can't cancel a TimeGrow invoice from My Account (`customer_cancel_statuses()` on `woocommerce_valid_order_statuses_for_cancel`); the business cancels it from the site, as below.

### Cancelling an invoice

Code: `modules/woocommerce-integration/includes/class-order-cancellation.php`. A TimeGrow invoice (an order with `_timekeeping_invoice`) is cancelled from the site: the **Cancel invoice** order action (offered while the invoice is unpaid), the status dropdown, a bulk action or the REST API. All of them run the same checks (the Send invoice action never moves a cancelled or paid order, see above): the action calls `cancel()`, the others are caught by a guard on `woocommerce_before_order_object_save` that calls `prepare_cancel()` and undoes the status change (no status hooks or emails) when a check fails.

The steps, in order. If a step refuses, nothing after it runs and the order keeps its status (with an order note and a notice). On the order edit screen the notice is printed inside the order data box, above the Status dropdown (`woocommerce_admin_order_data_after_payment_info`), because WooCommerce's own "Order updated" message still appears; elsewhere (orders list) it is a normal admin notice:

1. **Refuses when paid:** status Partial Paid or Invoice Paid, or any `_payment_amount` above 0. Refund the payment instead.
2. **PayPal first** (orders with a `_paypal_invoice_id`): reads the invoice from PayPal.

   | PayPal status | What happens |
   |---|---|
   | DRAFT, SCHEDULED | The invoice is deleted (`DELETE /v2/invoicing/invoices/{id}`; PayPal deletes only these). The client never saw it. |
   | SENT, UNPAID | The invoice is cancelled (`POST .../cancel`). PayPal tells the client when **Tell the client** is on in PayPal settings (on by default). |
   | CANCELLED | Nothing to do. |
   | PAID, MARKED_AS_PAID, PARTIALLY_PAID, PAYMENT_PENDING (money on its way), any refund status | Refused. The payment sync runs so the payment is recorded on the order. |

   Orders not made by TimeGrow get this PayPal step too when they have a PayPal invoice (any order can be sent to PayPal), so a cancelled order never leaves a payable PayPal invoice; they have no time to release. Missing PayPal keys or an API error also refuse, so an invoice the client can still pay is never left behind a cancelled order. `_paypal_invoice_status` becomes `DELETED` or `CANCELLED`; cancelling again later (after a reopen) skips PayPal.
3. **Cancels the order** (WooCommerce's Cancelled status and its hooks).
4. **Releases the time** (on `woocommerce_order_status_cancelled`): the order's time entries go back to unbilled, so the next Process Time bills them on a new invoice. Their IDs are kept in `_timegrow_released_entries`. The cancelled order keeps its invoice number and PDF.

**Paid money moves one way: paid, then Refunded, then Cancelled.** The same guard (rules in `LOCKED_MOVES` and `moves_for()`) undoes any other change, with an order note and a notice:

| From | May move to | Applies to |
|---|---|---|
| Partial Paid | Invoice Paid, Refunded | Every order |
| Invoice Paid | Refunded | Every order |
| Refunded | Cancelled | TimeGrow invoices |
| Cancelled after a refund | Nothing (closed) | TimeGrow invoices |

On the order edit screen the status dropdown lists only those statuses plus the current one (`wc_order_statuses` filter, page loads only, so saving still validates against the full list). A full refund from the order items moves the order to Refunded on its own (WooCommerce).

**A PayPal invoice is refunded in PayPal first.** For an order with a `_paypal_invoice_id`, TimeGrow reads the invoice from PayPal before:

- **a WooCommerce refund** (`woocommerce_create_refund`, before it is saved): refused when PayPal's refunded amount (`refunds.refund_amount`, or the whole invoice for a REFUNDED / MARKED_AS_REFUNDED status without one) is less than the order's refunds including this one (counted once: WooCommerce has already saved the new refund when it asks, `refunded_with()`). A PayPal invoice that is fully refunded (its status, or a refund of at least its total) never blocks the refund, since its total can be a cent off the order's. `wc_create_refund()` turns the exception into an error and deletes the refund, so the refund screen shows the message and nothing is kept.
- **Refunded set by hand:** refused unless PayPal shows the full order total, or the whole invoice, refunded.

The message names the invoice: "The PayPal invoice INV2-... is not refunded yet. Refund it in PayPal first, then refund it here." (or how much is refunded so far, for a partial one). On a paid order the **PayPal Invoice** box also warns that the PayPal invoice is not refunded yet, from the last PayPal status seen. Missing PayPal keys or an API error also refuse. Rule: `paypal_refund_shortfall()`; amount: `Aragrow_TimeGrow_PayPal_Payment_Sync::refunded_amount()` and `fully_refunded()`.

**A full refund releases the time** like a cancel (on `woocommerce_order_status_refunded`, TimeGrow invoices only). A partial refund leaves the order in its paid status and the time billed.

**Reopening** a cancelled invoice that was never paid (any other status) is refused by the same guard when any released entry is billed again on another invoice. Otherwise it is allowed, and the released entries are billed to it again. Its PayPal invoice stays cancelled.

An invoice cancelled in PayPal does not cancel the order. The payment sync only adds a note (see [When the client pays](#when-the-client-pays)).

### Trashing and deleting an invoice

`TimeGrowTimeEntry::on_order_removed()`, for TimeGrow invoices:

| What | Its time |
|---|---|
| Moved to the trash | Stays billed on the order. The trash can be undone, so restoring needs nothing. To bill the time again, cancel the invoice, or delete it permanently. |
| Restored from the trash | Nothing changes (it was never released). |
| Deleted permanently, no payment recorded | Released: the next Process Time bills it again. |
| Deleted permanently, a payment recorded (Partial Paid, Invoice Paid, or a manual payment amount) | Stays billed, and is never billed again; only the link to the deleted order (`billed_order_id`) is cleared. |

Before 2.2.0, trashing released the time even on a paid invoice, so the next Process Time billed paid work again.

### Manual payments

A **Manual Payment Details** box sits in the side column of the order editor. It stores `_payment_date`, `_payment_amount`, `_payment_type` and `_payment_trans_id` on the order.

- The type starts as "Select a type", so an order saved without payment details records nothing.
- A payment needs a date, an amount above 0 and a type, plus a transaction ID for ACH, Stripe, PayPal and credit card. A refused entry is not saved, and the reasons show after the save (they are kept for the next page, since WooCommerce redirects).
- Emptying every field removes the recorded payment (with an order note), so a mistaken amount no longer blocks Cancel.
- **Invoice Paid** sets the payment amount to the order total when less is recorded (after a partial payment: 500 of 1000 becomes 1000, with a note). A larger amount, an overpayment, is kept. The payment date is set only when empty.

### Other pieces

- **EIN/SSN field** on user profiles, with a column in the Users list (`_timegrow_ein_ssn` user meta).
- **PDF invoice template** for the PDF Invoices & Packing Slips plugin, in `modules/woocommerce-integration/templates/pdf/aragrow/`. TimeGrow registers that folder with the PDF plugin, where it is listed as **timegrow/aragrow** (PDF Invoices > Settings > Template). Hours invoices use the headers Project, Hours, Rate, Total, with hours from `_timegrow_project_hours` and rate = line total / hours. Regular orders use Product, Quantity, Price, Total, with the plain quantity and unit price. The old theme copies in `astra-child` and `aragrow-no-front-end-theme` are no longer used.

### Time-entry order creator

`modules/core/includes/models/TimeGrowWooOrderCreator.php` turns billable, unbilled time entries into orders, one per client.

- **Hours, not units.** Each project becomes one line. The hours worked (two decimals) go in `_timegrow_project_hours` and the quantity is 1. The line subtotal and total are hours x hourly rate. Earlier versions rounded up to 10-minute units and priced each unit at one sixth of the rate. That is gone.
- **Rate.** Project, then client (user meta), then company, then 75 as a last resort. The client step now reads the profile field. It used to read an unused column on the users table, so it was always 0.
- **Markers.** New orders get `_timekeeping_invoice` and `_timegrow_qty_unit = hours`. This is the only place an order becomes an hours invoice. Each line gets `_timegrow_project_hours` and a message such as "3.75 hrs. At a rate of 75 per hour."
- **Skipped projects.** A project with no billable, unbilled hours gets no line, and its entries are not marked billed. That includes time that rounds to 0.00 h (a few seconds clocked): it is unbilled again and waits for the next run. A new order that ends up with no lines is deleted again.
- **One client fails, the rest go on.** If a client's order can't be finished, its entries are unbilled again; a new order is deleted, and a reused order loses only the lines this run added (with an order note). The error is shown on the Process Time page.
- **One run at a time.** A lock in the options table (`timegrow_process_time_lock`, value "time:token") refuses a second run; a lock older than 10 minutes is taken over, and a run only ever removes its own lock.
- **Appending.** Entries are added to an existing Pending order for the client only if that order carries the `hours` marker and has not been issued yet (no PDF invoice number, no PayPal invoice ID). Otherwise a new order is created.
- It creates the project's product if it does not exist and **WooCommerce Products** is on (otherwise the line has no product), adds the customer note "Time entries for client ID: N", and leaves the order in Pending payment (not paid, no email to the client, lines still editable). The save is then checked: status, line count and total.

## PayPal auto invoicer

Settings page: **Settings > PayPal Integration**, stored in the `wc_pp_auto_invoicer_settings` option.

| Setting | Notes |
|---|---|
| Send orders to PayPal | On/off switch, on by default. When off, the **Send Invoice to PayPal** status is not offered (Invoice Sent / Draft in PayPal stay, for orders already in PayPal). |
| Environment | Sandbox or Live. |
| Client ID, Client Secret | PayPal REST credentials. Stored encrypted with AES-256-CBC. The page shows only the last 8 characters; click **Update** next to a field to replace it. Saving without Update keeps the saved value. |
| Invoice delivery | "Email the invoice to the client" or "Save as a draft in PayPal (I will send it myself)". |
| Merchant email | Optional. Left out of the invoice when empty, so PayPal uses the account's own email. |
| When an order is cancelled: Tell the client | `cancel_notify`, on by default. When **Cancel invoice** cancels a sent PayPal invoice, PayPal emails the client that no payment is due. Deleted drafts never notify. |

**Encryption key:** define `WC_PAYPAL_SECRET_KEY` in `wp-config.php`. Keep the key out of version control. Without it, a new Client ID or Secret is not saved (the saved one is kept) and the settings page says why. If the key is removed or changed after the keys were saved, the settings page shows an error, and every PayPal call stops with the same message instead of PayPal's "OAuth token error" (`credential_problem()`).

### What happens when an order is sent

1. Set the order status to **Send Invoice to PayPal** (`wc-inv_send2paypal`).
   - **An order goes to PayPal only once.** If it already has a PayPal invoice ID, the module refuses, adds a note, and puts the status back. Make changes to that invoice in PayPal. Exception: when that invoice was cancelled or deleted in PayPal (the order was cancelled, then reopened), a new invoice replaces it.
   - If **Send orders to PayPal** is off, the module adds a note and stops.
2. The module gets an OAuth token from PayPal.
3. It builds the invoice lines. Shipping and tax become separate "Shipping" and "Tax" lines. Each fee on the order becomes its own line. PayPal rounds each unit amount to the cent, so when hours (or a quantity) times the rounded rate wouldn't make the line total (2.33 h for 175.00 is 75.107 an hour, which would come to 175.01), the line goes as 1 x the line total, with "2.33 x 75.11" at the start of its description (`paypal_line_amounts()`). A credit (a fee below 0) can't be a PayPal line, so the credits are added up and applied as the invoice discount. The invoice number comes from the PDF Invoices plugin. If the order has no PDF invoice number yet, one is created at this point. If PayPal reports the number as a duplicate, the invoice is created again without it and PayPal assigns its own number (noted on the order).
4. For each line with a SKU, it creates a PayPal catalog product if needed: ID from the SKU, 6 to 50 characters (`catalog_product_id()`, a short SKU gets a `TG-` prefix), description cut to 256 characters. The mapping is cached in the `wc_pp_auto_invoicer_product_map` option. A failure here is logged and never stops the invoice.
5. It builds the recipient from the client (see below). Without a valid email it stops with an order note.
6. It first searches PayPal for an invoice this order already has (reference = order ID, recipient = the order's client, not cancelled). That covers a retry after a timeout: PayPal made the invoice but TimeGrow never heard back. When one is found it is used, not duplicated; if it was already sent, it is not sent again. A search error stops the send (retry later). Otherwise it creates the invoice. Either way it saves the PayPal invoice ID in `_paypal_invoice_id` on the order.
7. With "Email the invoice", it tells PayPal to send the invoice, adds a note, and emails the customer a short notice. With "Save as a draft", it adds a note that the invoice is a draft. No customer email goes out.

   Each call that changes something carries a `PayPal-Request-Id`, so a retry after a timeout is not done twice. The ID includes the order's invoice generation (`_paypal_invoice_generation`, raised when a cancelled invoice is replaced), so a new invoice after a cancel never gets PayPal's saved answer for the old one. GET calls carry none, and a search gets a fresh one.
8. Only after that does the order move on: to **Invoice Sent to PayPal** (`wc-inv_sent2paypal`) when emailed, or **Invoice Draft in PayPal** (`wc-inv_draft2paypal`) when saved as a draft. Send drafts from your PayPal account. Status resets are quiet: they skip that status's emails and payment recording. On an API error the module adds a note and moves the order out of Send Invoice to PayPal: back to its previous status when nothing reached PayPal (set it again to retry), or to Invoice Draft in PayPal when the invoice was created but not sent (finish it in PayPal).

### The client in PayPal

PayPal's invoicing API has no separate "create customer" call. The recipient on each invoice becomes a customer in PayPal. So the module makes sure each invoice carries the client's details, taking each field from the order's billing details first, then the client's WooCommerce customer profile, then the WordPress user:

- Email (required), first and last name (or display name), company as the business name.
- Address, sent only when it has a country, because PayPal rejects an address without one.

Phone numbers are not sent. PayPal needs them split into country code and number.

### When the client pays

Code: `modules/paypal-invoicer/includes/class-paypal-payment-sync.php`. Four ways in, one sync step:

| How | When | Setup |
|---|---|---|
| **Webhook** | Within seconds of the payment | In the PayPal developer dashboard, add a webhook for `https://your-site/wp-json/timegrow/v1/paypal-webhook` with the events `INVOICING.INVOICE.PAID`, `INVOICING.INVOICE.CANCELLED` and `INVOICING.INVOICE.REFUNDED`. Paste its Webhook ID in Settings > PayPal Integration. Needs a public HTTPS site. |
| **Scheduled check** | Every hour, twice a day, or daily (default) | Settings > PayPal Integration > **Check for payments**. Checks 50 orders per run in Invoice Sent to PayPal, Invoice Draft in PayPal or Partial Paid: the ones tried longest ago, never-tried first (`next_batch()`, `_paypal_sync_tried`), so with more than 50 open invoices every one is still checked in turn. WP-Cron runs on site visits; on a quiet site, add a real server cron job that loads `wp-cron.php`. |
| **Check PayPal status** button | On demand | The **PayPal Invoice** box on the order shows the invoice ID, the last PayPal status, and the button. |
| **Sync Payments** page | On demand, all open orders | Nexus Dashboard > **Sync Payments** (administrators). Lists open PayPal orders, runs `sync_open_orders()` (the next 50, as above) and shows the result per order, plus the schedule and webhook status. View: `modules/nexus/includes/views/TimeGrowNexusSyncPaymentsView.php`. |

The sync step always reads the invoice from PayPal's API (a webhook message is never trusted on its own; its signature is also verified with PayPal first, sending the event exactly as received, `verify_body()`), then:

| PayPal status | Order |
|---|---|
| PAID, MARKED_AS_PAID | Payment date, amount, transaction ID and type "PayPal" are recorded, then the order moves to **Invoice Paid** (the client gets the paid email). |
| PARTIALLY_PAID | Amount paid so far is recorded, order moves to **Partial Paid**. Skipped for an order already Invoice Paid, so a full payment recorded here is never lowered. |
| CANCELLED, REFUNDED, PARTIALLY_REFUNDED, MARKED_AS_REFUNDED | Order note only; the status is left for you to decide. A TimeGrow invoice is cancelled from the site, after a PayPal check (see [Cancelling an invoice](#cancelling-an-invoice)). |
| Anything else (DRAFT, SENT, UNPAID, ...) | Nothing changes. |

Each check saves `_paypal_invoice_status` and `_paypal_last_checked` on the order, so the same payment or note is never applied twice.

### Quantity and rate sent to PayPal

Hours invoice lines have a quantity of 1 and their hours in `_timegrow_project_hours`. The module sends those hours as the PayPal quantity, with the line total divided by the hours as the unit rate. So 3.75 hours at 75.00 goes to PayPal as 3.75 x 75.00. Every other line sends its plain quantity. There is no unit conversion.

Orders created before this change used 10-minute units, so their PayPal invoices would show those units at the per-unit rate. The totals stay correct.


## Known issues

None known. Not yet tried against the PayPal sandbox: an order with fees, a credit and an uneven rate; a scheduled invoice cancelled; a webhook with the raw-event check.

## Related files

- [DECIMAL-QUANTITIES.md](DECIMAL-QUANTITIES.md)
- `modules/core/includes/TimeGrowIntegration.php` (settings page)
- `modules/core/includes/controllers/TimeGrowProjectController.php` (product sync)
