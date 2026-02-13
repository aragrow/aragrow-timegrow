# WooCommerce to PayPal Integration

## Overview

The Aragrow WC PayPal Auto Invoicer plugin automatically creates PayPal invoices from WooCommerce orders. When a WooCommerce order (created from TimeGrow time entries) reaches a specific status, the plugin generates a corresponding PayPal invoice and sends it to the customer.

## Architecture

### Key Components

1. **WooCommerce Orders** (Created from TimeGrow time entries)
2. **PayPal Auto Invoicer Plugin** (`aragrow-wc-paypal-auto-invoicer`)
3. **PayPal REST API v2** (Invoicing API)
4. **Custom Order Status**: `wc-inv_send2paypal` / `wc-inv_sent2paypal`

## Integration Flow

```
TimeGrow Time Entries
    ↓
Process Time (creates WooCommerce Order)
    ↓
Order Status: wc-pending
    ↓
Admin changes status to: wc-inv_send2paypal
    ↓
PayPal Invoice Created & Sent
    ↓
Order Status: wc-inv_sent2paypal
    ↓
Customer receives PayPal invoice email
    ↓
Customer pays via PayPal
    ↓
Order Status: wc-completed (manual update)
```

## Custom Order Statuses

### 1. Send Invoice to PayPal (`wc-inv_send2paypal`)

**Purpose**: Trigger status - when an order is set to this status, the PayPal invoice creation process starts.

**Registration**:
```php
register_post_status('wc-inv_send2paypal', array(
    'label'                     => 'Send Invoice to PayPal',
    'public'                    => true,
    'show_in_admin_all_list'    => true,
    'show_in_admin_status_list' => true,
));
```

**Hook**:
```php
add_action('woocommerce_order_status_inv_send2paypal',
    [$this, 'handle_order_to_pay'], 10, 1);
```

### 2. Invoice Sent to PayPal (`wc-inv_sent2paypal`)

**Purpose**: Confirmation status - indicates the PayPal invoice was successfully created and sent.

**Visual Indicator**: Purple background in WooCommerce admin

```php
echo '<style>
    .order-status.status-inv_send2paypal { background: purple; color: #fff; }
    .order-status.status-invoice_sent2paypal { background: purple; color: #fff; }
</style>';
```

## PayPal API Configuration

### Settings Page

Location: **WordPress Admin → Settings → WC PayPal Auto Invoicer**

Required fields:
- **Environment**: Sandbox (testing) or Live (production)
- **Client ID**: PayPal REST API Client ID
- **Client Secret**: PayPal REST API Secret
- **Send Behavior**:
  - `send` - Create and send invoice immediately
  - `save` - Create invoice as draft (don't send)
- **Merchant Email**: Optional invoicer email address

### API Endpoints Used

```
OAuth Token:  POST /v1/oauth2/token
Products:     GET  /v1/catalogs/products/{id}
              POST /v1/catalogs/products
Invoices:     POST /v2/invoicing/invoices
              POST /v2/invoicing/invoices/{id}/send
```

## Process Flow - Detailed

### Step 1: Order Status Change

Admin changes order from `pending` to `Send Invoice to PayPal`:

```php
// Triggered automatically by WooCommerce
do_action('woocommerce_order_status_inv_send2paypal', $order_id);
```

### Step 2: Get OAuth Token

File: `aragrow-wc-paypal-auto-invoicer.php::get_oauth_token()`

```php
// Base URL determination
$base = $settings['env'] === 'live'
    ? 'https://api.paypal.com'
    : 'https://api.sandbox.paypal.com';

// Get OAuth token
POST {base}/v1/oauth2/token
Headers:
    Authorization: Basic {base64(client_id:client_secret)}
    Content-Type: application/x-www-form-urlencoded
Body:
    grant_type=client_credentials

Response:
{
    "access_token": "A21AAL...",
    "token_type": "Bearer",
    "expires_in": 32400
}
```

### Step 3: Create/Get PayPal Catalog Products

For each WooCommerce order item:

```php
// Check if product already exists in PayPal catalog
GET /v1/catalogs/products/{product_id}

// If not found, create it
POST /v1/catalogs/products
{
    "id": "{wc_product_sku}",
    "name": "{product_name}",
    "description": "{product_description}",
    "type": "PHYSICAL"
}
```

Product mapping is cached in WordPress option: `wc_pp_auto_invoicer_product_map`

### Step 4: Build Invoice Items Array

```php
$items = [];

// Add each order line item
foreach ($order->get_items() as $item) {
    $product = $item->get_product();
    $pp_product_id = $this->ensure_paypal_product(
        $token,
        $settings,
        $product->get_sku(),
        $product->get_name(),
        $product->get_description()
    );

    $pp_item = [
        'name'        => $item->get_name(),
        'description' => wp_strip_all_tags($product->get_short_description()),
        'quantity'    => (string) $item->get_quantity(),
        'unit_amount' => [
            'currency_code' => $order->get_currency(),
            'value'         => number_format($item->get_total() / $item->get_quantity(), 2, '.', '')
        ],
    ];

    if ($pp_product_id) {
        $pp_item['id'] = $pp_product_id;
    }

    $items[] = $pp_item;
}

// Add shipping as separate line item
if ($shipping_total > 0) {
    $items[] = [
        'name'        => 'Shipping',
        'quantity'    => '1',
        'unit_amount' => [
            'currency_code' => $currency,
            'value'         => number_format($shipping_total, 2, '.', '')
        ],
    ];
}

// Add tax as separate line item
if ($tax_total > 0) {
    $items[] = [
        'name'        => 'Tax',
        'quantity'    => '1',
        'unit_amount' => [
            'currency_code' => $currency,
            'value'         => number_format($tax_total, 2, '.', '')
        ],
    ];
}
```

### Step 5: Build Invoice Payload

```php
$invoice_number = $order->get_meta('_wcpdf_invoice_number'); // From PDF Invoices plugin

$invoice_payload = [
    'detail' => [
        'currency_code'        => $order->get_currency(),
        'invoice_number'       => (string) $invoice_number,
        'reference'            => (string) $order->get_id(),
        'note'                 => 'Thank you for your business!',
        'terms_and_conditions' => 'Payment due upon receipt.',
    ],
    'invoicer' => [
        'email_address' => $settings['merchant_email'] ?: null,
    ],
    'primary_recipients' => [
        [
            'billing_info' => [
                'email_address' => $order->get_billing_email(),
                'name'          => [
                    'full_name' => $order->get_formatted_billing_full_name()
                ],
                'address'       => [
                    'address_line_1' => $order->get_billing_address_1(),
                    'address_line_2' => $order->get_billing_address_2(),
                    'admin_area_2'   => $order->get_billing_city(),
                    'admin_area_1'   => $order->get_billing_state(),
                    'postal_code'    => $order->get_billing_postcode(),
                    'country_code'   => $order->get_billing_country(),
                ],
            ],
        ],
    ],
    'items' => $items,
    'configuration' => [
        'tax_calculated_after_discount' => true,
        'tax_inclusive'                 => false,
    ],
];
```

### Step 6: Create PayPal Invoice

```php
POST /v2/invoicing/invoices
Headers:
    Authorization: Bearer {access_token}
    Content-Type: application/json
    PayPal-Request-Id: {idempotency_key}
Body:
    {invoice_payload}

Response:
{
    "id": "INV2-XXXX-XXXX-XXXX-XXXX",
    "href": "https://api.paypal.com/v2/invoicing/invoices/INV2-XXXX-XXXX-XXXX-XXXX",
    "status": "DRAFT",
    ...
}
```

### Step 7: Store Invoice ID & Update Order

```php
// Extract invoice ID from response
preg_match('#/invoices/([^/?]+)#', $invoice['href'], $m);
$invoice_id = $m[1]; // e.g., "INV2-XXXX-XXXX-XXXX-XXXX"

// Save to order meta
update_post_meta($order_id, '_paypal_invoice_id', $invoice_id);

// Update order status
$order->update_status('wc-inv_sent2paypal');
```

### Step 8: Send Invoice (Optional)

If `send_action` setting is `'send'`:

```php
POST /v2/invoicing/invoices/{invoice_id}/send
Headers:
    Authorization: Bearer {access_token}
    Content-Type: application/json
Body:
{
    "send_to_invoicer": false
}

// Add order note
$order->add_order_note(
    sprintf('PayPal invoice %s created and sent to %s',
        $invoice_id,
        $recipient_email
    )
);
```

If `send_action` is `'save'`:
```php
// Invoice created as DRAFT, not sent
$order->add_order_note(
    sprintf('PayPal invoice %s created (not sent).', $invoice_id)
);
```

### Step 9: Customer Notification

When `send_action = 'send'`, customer receives:

1. **WooCommerce Email** - "Invoice Sent to PayPal" notification
2. **PayPal Email** - PayPal invoice with payment link

Email notification code:
```php
public function send_custom_inv_send2paypal_email_notification($order_id) {
    $order = wc_get_order($order_id);
    $to = $order->get_billing_email();

    $subject = 'Invoice Sent to PayPal Notification';
    $message = sprintf(
        'Your invoice #%s has been sent to PayPal.',
        $order->get_order_number()
    );

    $mailer = WC()->mailer();
    $message = $mailer->wrap_message($subject, wpautop(wp_kses_post($message)));
    $mailer->send($to, $subject, $message);
}
```

## Security Features

### Encrypted Credentials

Client ID and Secret are encrypted before storage:

```php
private function encrypt_data($data) {
    if (!defined('AUTH_KEY') || !AUTH_KEY) {
        return $data; // Fallback if no encryption key
    }

    $key = hash('sha256', AUTH_KEY, true);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);

    return base64_encode($iv . $encrypted);
}

private function decrypt_data($encrypted) {
    if (!defined('AUTH_KEY') || !AUTH_KEY) {
        return $encrypted;
    }

    $data = base64_decode($encrypted);
    $key = hash('sha256', AUTH_KEY, true);
    $iv = substr($data, 0, 16);
    $encrypted_text = substr($data, 16);

    return openssl_decrypt($encrypted_text, 'AES-256-CBC', $key, 0, $iv);
}
```

### Idempotency Keys

Prevents duplicate invoice creation:

```php
private function idempotency_key($method, $path, $payload) {
    $str = $method . '|' . $path . '|' . wp_json_encode($payload);
    return substr(md5($str), 0, 22);
}
```

## Integration with PDF Invoices Plugin

Enables PDF invoice generation for custom statuses:

```php
public function enable_invoice_for_custom_inv_send2paypal_status($allowed, $document) {
    if ($document->type == 'invoice') {
        $order = $document->order;
        if ($order->get_status() == 'inv_send2paypal' ||
            $order->get_status() == 'inv_sent2paypal') {
            $allowed = true;
        }
    }
    return $allowed;
}
```

## Complete Integration Chain

```
┌─────────────────────────────────────────────────────────────────┐
│                       COMPLETE FLOW                              │
└─────────────────────────────────────────────────────────────────┘

1. User tracks time in TimeGrow
   - Clock in/out or manual entry
   - Mark as billable

2. Admin clicks "Process Time" in Nexus Dashboard
   - Groups billable entries by client & project
   - Creates WooCommerce orders
   - Status: wc-pending

3. Admin changes order status to "Send Invoice to PayPal"
   - Status: wc-inv_send2paypal

4. PayPal Auto Invoicer plugin activates
   - Gets OAuth token from PayPal
   - Creates/retrieves catalog products
   - Builds invoice payload
   - Creates PayPal invoice
   - Sends to customer (if configured)
   - Updates order status: wc-inv_sent2paypal
   - Saves PayPal invoice ID in order meta

5. Customer receives emails
   - WooCommerce notification
   - PayPal invoice with payment link

6. Customer pays via PayPal
   - (Payment processed by PayPal)
   - Admin manually updates order: wc-completed

7. Tax Report shows completed invoice
   - Appears in "Paid Invoices" section
   - Linked to original time entries
```

## Database Relationships

```sql
-- Time Entry → WooCommerce Order
wp_tg_time_entry_tracker.billed_order_id → wp_wc_orders.id

-- WooCommerce Order → PayPal Invoice
wp_postmeta.meta_key = '_paypal_invoice_id'
wp_postmeta.post_id = order_id
wp_postmeta.meta_value = PayPal Invoice ID
```

## API Response Examples

### PayPal Invoice Creation Response

```json
{
  "id": "INV2-ABCD-1234-EFGH-5678",
  "href": "https://api.sandbox.paypal.com/v2/invoicing/invoices/INV2-ABCD-1234-EFGH-5678",
  "status": "DRAFT",
  "detail": {
    "invoice_number": "INV-12345",
    "reference": "789",
    "currency_code": "USD"
  },
  "invoicer": {
    "email_address": "merchant@example.com"
  },
  "primary_recipients": [{
    "billing_info": {
      "email_address": "customer@example.com"
    }
  }],
  "items": [...],
  "amount": {
    "currency_code": "USD",
    "value": "150.00"
  }
}
```

## Error Handling

```php
try {
    // Invoice creation process
} catch (Exception $e) {
    error_log('PayPal Invoicer error: ' . $e->getMessage());
    $order->add_order_note('PayPal Invoicer error: ' . $e->getMessage());
}
```

Common errors:
- **401 Unauthorized**: Invalid Client ID/Secret
- **422 Unprocessable**: Invalid invoice payload
- **500 Server Error**: PayPal API issues
- **Network timeout**: Connection issues

## Testing with Sandbox

1. Create PayPal Sandbox account: https://developer.paypal.com
2. Create REST API app
3. Get Sandbox Client ID & Secret
4. Set Environment to "Sandbox"
5. Use sandbox test accounts for orders
6. PayPal invoices appear in sandbox dashboard

## Production Checklist

- [ ] Switch to Live environment
- [ ] Use Production Client ID & Secret
- [ ] Test with real customer email
- [ ] Verify email notifications work
- [ ] Check invoice branding/logo
- [ ] Test payment processing
- [ ] Monitor error logs

## File References

| File | Purpose |
|------|---------|
| `aragrow-wc-paypal-auto-invoicer.php` | Main plugin file, all PayPal logic |
| WooCommerce order meta `_paypal_invoice_id` | Stores PayPal invoice ID |
| WooCommerce PDF Invoices meta `_wcpdf_invoice_number` | Used as PayPal invoice number |
| WordPress option `wc_pp_auto_invoicer_product_map` | Cached PayPal product IDs |
| WordPress option `wc_pp_auto_invoicer_settings` | Plugin settings |
