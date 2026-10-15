# Decimal quantities

Hours invoices need quantities like 3.75. WooCommerce rounds quantities to whole numbers, so TimeGrow turns that off.

## What it does

- Removes `intval()` from the `woocommerce_stock_amount` filter. WooCommerce adds it in `wc-core-functions.php`, and it truncates 3.75 to 3 whenever a line quantity is set.
- Sets the order editor's Quantity box to a 0.01 step and minimum (edit mode only, refunds keep WooCommerce's defaults).

The quantity is stored as a real number on the line item. There is no second copy of it.

## Where it lives

- Class: `modules/woocommerce-integration/includes/class-decimal-quantities.php`
- Loaded from: `modules/woocommerce-integration/aragrow-woocommerce-intengration.php`
- It registers on `plugins_loaded` (priority 20), after WooCommerce has added its own filter.

## Setting

TimeGrow > WooCommerce integration > **Allow decimal quantities**. On by default, including when the setting has never been saved. Stored as `wc_decimal_quantities` in the `timegrow_integration_settings` option.

Turning it off puts WooCommerce's whole-number rounding back.

## Things to know

- The change is site-wide. Stock amounts for every product can be fractional, not only hours products.
- Lines saved while the setting was off keep their rounded quantity. Re-save them after turning it on.
- This replaces the standalone "Aragrow WooCommerce Float Quantities" plugin. Deactivate that plugin when you use this one. Order lines it wrote may still carry a harmless `_precise_quantity` meta value.
