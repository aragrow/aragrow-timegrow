# Testing

TimeGrow has unit tests for the billing math. They run without WordPress or a database, in well under a second.

## Run the tests

From the plugin folder:

```bash
composer install     # first time only: installs PHPUnit into vendor/
composer test        # or: vendor/bin/phpunit
```

`vendor/` is not committed. `composer.lock` is, so everyone gets the same PHPUnit version.

## What is covered

| File | Covers |
|---|---|
| `tests/unit/HourlyRateTest.php` | Rate order: project, then client, then company. A line's own rate: stored rate, then price / hours, then the rate order, then unit price for a hand-added line. |
| `tests/unit/HoursLinesTest.php` | Saving hours invoice lines: repricing only when hours change, quantity stays 1, discounts kept, hand-edited prices become the line rate, rounding is not an edit, 0 hours keeps the rate, regular orders untouched. |
| `tests/unit/InvoiceLinesTest.php` | PayPal quantity and rate for hours and regular lines, shipping and tax lines, and the PDF invoice line numbers. |

Not covered yet: the time entry order creator, the PayPal API calls, and the reports.

## How it works

- `tests/bootstrap.php` defines small stand-ins for the WordPress and WooCommerce functions the billing code calls (hooks do nothing, formatting matches WooCommerce for these inputs), then loads the WooCommerce integration and PayPal modules.
- `tests/fakes.php` has fake orders and line items, and `TimeGrow_Test_State`, which holds the data the stand-ins return (projects, user meta, company rate, orders). Each test resets it in `setUp()`.

## Adding a test

1. Build the data with the fakes, for example:

   ```php
   $item  = new WC_Order_Item_Product( 1, 300, 300, 10, [ '_timegrow_project_hours' => '2.00' ] );
   $order = TimeGrow_Fake_Order::hours_invoice( 1, [ 101 => $item ] );
   ```

2. Call the method and assert on the result.
3. If the code needs a WordPress function that is not stubbed yet, add it to `tests/bootstrap.php`.

Keep billing math in small methods (like `invoice_line_values()` and `line_quantity_and_rate()`) so it can be tested this way.
