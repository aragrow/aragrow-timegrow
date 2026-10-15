<?php
/**
 * Decimal quantities for WooCommerce order lines.
 *
 * WooCommerce rounds quantities to whole numbers by adding intval() to the
 * woocommerce_stock_amount filter. Hours invoices need values like 3.75, so this
 * class removes that filter and lets the admin order editor accept two decimals.
 *
 * Controlled by the "Allow decimal quantities" setting on the TimeGrow
 * WooCommerce integration page (on by default).
 *
 * @package TimeGrow
 * @subpackage WooCommerce_Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Aragrow_TimeGrow_Decimal_Quantities {

    public static function init() {
        // WooCommerce adds its intval filter while it loads, so wait until all plugins are loaded.
        add_action('plugins_loaded', [__CLASS__, 'register'], 20);
    }

    public static function is_enabled() {
        $options = get_option('timegrow_integration_settings');
        // Missing key means the setting was never saved: default to on.
        return !is_array($options) || !array_key_exists('wc_decimal_quantities', $options) || !empty($options['wc_decimal_quantities']);
    }

    public static function register() {
        if (!class_exists('WooCommerce') || !self::is_enabled()) return;

        remove_filter('woocommerce_stock_amount', 'intval');

        // Admin order editor, edit mode only (refunds keep WooCommerce's defaults)
        add_filter('woocommerce_quantity_input_step_admin', [__CLASS__, 'admin_step'], 10, 3);
        add_filter('woocommerce_quantity_input_min_admin', [__CLASS__, 'admin_min'], 10, 3);
    }

    public static function admin_step($step, $product = null, $context = 'edit') {
        return $context === 'edit' ? '0.01' : $step;
    }

    public static function admin_min($min, $product = null, $context = 'edit') {
        return $context === 'edit' ? '0.01' : $min;
    }
}
