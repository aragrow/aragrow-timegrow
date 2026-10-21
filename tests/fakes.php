<?php
/**
 * Test data and fake WooCommerce objects for the unit tests.
 */

/**
 * Shared data the stand-in functions read. Reset in each test's setUp().
 */
class TimeGrow_Test_State {
    public static $user_meta           = [];
    public static $options             = [];
    public static $orders              = [];
    public static $projects_by_product = [];
    public static $company_rate        = 0.0;

    public static function reset() {
        self::$user_meta           = [];
        self::$options             = [];
        self::$orders              = [];
        self::$projects_by_product = [];
        self::$company_rate        = 0.0;
    }
}

/**
 * Base class name the code checks with instanceof.
 */
class WC_Order_Item {}

/**
 * A line item with just the getters and setters the billing code uses.
 */
class WC_Order_Item_Product extends WC_Order_Item {
    public $meta = [];
    public $quantity;
    public $subtotal;
    public $total;
    public $product_id;
    public $order;
    public $saved = 0;

    public function __construct( $quantity, $subtotal, $total = null, $product_id = 0, array $meta = [] ) {
        $this->quantity   = $quantity;
        $this->subtotal   = $subtotal;
        $this->total      = $total ?? $subtotal;
        $this->product_id = $product_id;
        $this->meta       = $meta;
    }

    public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
    public function update_meta_data( $key, $value ) { $this->meta[ $key ] = (string) $value; }
    public function get_quantity() { return $this->quantity; }
    public function set_quantity( $value ) { $this->quantity = $value; }
    public function get_subtotal() { return $this->subtotal; }
    public function set_subtotal( $value ) { $this->subtotal = $value; }
    public function get_total() { return $this->total; }
    public function set_total( $value ) { $this->total = $value; }
    public function get_product_id() { return $this->product_id; }
    public function get_order() { return $this->order; }
    public function save() { $this->saved++; }
}

/** A fee line on an order: a surcharge (above 0) or a credit (below 0). */
class TimeGrow_Fake_Fee {
    public $name;
    public $total;
    public function __construct( $name, $total ) { $this->name = $name; $this->total = $total; }
    public function get_name() { return $this->name; }
    public function get_total() { return $this->total; }
}

/**
 * An order holding fake line items, keyed by line item ID.
 */
class TimeGrow_Fake_Order {
    public $id;
    public $meta = [];
    public $items = [];
    public $customer_id = 0;
    public $currency = 'USD';
    public $shipping_total = 0;
    public $total_tax = 0;
    public $fees = [];
    public $notes = [];
    public $totals_calculated = 0;

    public function __construct( $id, array $items = [], array $meta = [] ) {
        $this->id   = $id;
        $this->meta = $meta;
        foreach ( $items as $item_id => $item ) {
            $item->order = $this;
            $this->items[ $item_id ] = $item;
        }
        TimeGrow_Test_State::$orders[ $id ] = $this;
    }

    public static function hours_invoice( $id, array $items = [] ) {
        return new self( $id, $items, [ '_timegrow_qty_unit' => 'hours' ] );
    }

    public function get_id() { return $this->id; }
    public function get_meta( $key ) { return $this->meta[ $key ] ?? ''; }
    public function get_items( $type = 'line_item' ) { return $this->items; }
    public function get_item( $item_id ) { return $this->items[ $item_id ] ?? null; }
    public function get_customer_id() { return $this->customer_id; }
    public function get_currency() { return $this->currency; }
    public function get_shipping_total() { return $this->shipping_total; }
    public function get_total_tax() { return $this->total_tax; }
    public function get_fees() { return $this->fees; }
    public function calculate_totals( $and_taxes = true ) { $this->totals_calculated++; }
    public function add_order_note( $note ) { $this->notes[] = $note; }
}
