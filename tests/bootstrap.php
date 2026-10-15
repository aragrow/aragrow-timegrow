<?php
/**
 * Unit test bootstrap: no WordPress, no database.
 *
 * Small stand-ins for the WordPress / WooCommerce functions the billing code calls,
 * plus fake orders and line items (tests/fakes.php). Tests set the data they need
 * through TimeGrow_Test_State and reset it in setUp().
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'TIMEGROW_PREFIX', 'timegrow_' );
define( 'HOUR_IN_SECONDS', 3600 );

require_once __DIR__ . '/fakes.php';

// ---- WordPress hooks and i18n: hooks are not run in unit tests
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function apply_filters( $hook, $value, ...$args ) { return $value; }
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return $text; }
function _x( $text, $context, $domain = '' ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES ); }

// ---- Data lookups, answered from TimeGrow_Test_State
function get_user_meta( $user_id, $key = '', $single = false ) {
    return TimeGrow_Test_State::$user_meta[ $user_id ][ $key ] ?? '';
}
function get_option( $name, $default = false ) {
    return TimeGrow_Test_State::$options[ $name ] ?? $default;
}
function get_post_meta( $id, $key = '', $single = false ) { return ''; }
function wc_get_order( $id ) {
    return TimeGrow_Test_State::$orders[ $id ] ?? false;
}

// ---- WooCommerce formatting, matching WooCommerce's behavior for these inputs
function wc_get_price_decimals() { return 2; }
function wc_format_decimal( $number, $dp = false ) {
    $number = (float) $number;
    if ( $dp === false ) return (string) $number;
    $out = number_format( $number, (int) $dp, '.', '' );
    return strpos( $out, '.' ) !== false ? rtrim( rtrim( $out, '0' ), '.' ) : $out;
}
function wc_stock_amount( $amount ) {
    $amount = (float) $amount;
    return floor( $amount ) == $amount ? (int) $amount : $amount;
}

// ---- TimeGrow helpers from the main plugin file
function timegrow_log( $message ) {}
function timegrow_debug() { return false; }
function timegrow_company_default_rate() { return (float) TimeGrow_Test_State::$company_rate; } // real helper returns a float

// ---- $wpdb: only what get_hours_rate() uses (project row by product ID)
class TimeGrow_Fake_WPDB {
    public $prefix = 'wp_';
    public function prepare( $query, ...$args ) {
        return [ 'query' => $query, 'args' => $args ];
    }
    public function get_row( $prepared ) {
        $product_id = (int) ( $prepared['args'][0] ?? 0 );
        $project    = TimeGrow_Test_State::$projects_by_product[ $product_id ] ?? null;
        return $project ? (object) $project : null;
    }
    public function get_var( $query ) { return null; }
}
$GLOBALS['wpdb'] = new TimeGrow_Fake_WPDB();

// ---- Code under test
$plugin = dirname( __DIR__ );
require_once $plugin . '/modules/woocommerce-integration/aragrow-woocommerce-intengration.php';
require_once $plugin . '/modules/paypal-invoicer/aragrow-wc-paypal-auto-invoicer.php';
