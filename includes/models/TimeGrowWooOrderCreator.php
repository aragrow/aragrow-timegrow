<?php

class TimeGrowWooOrderCreator {

    /**
     * Get or create a WooCommerce product by name or SKU.
     *
     * @param string $product_name
     * @param float $price
     * @param string|null $sku
     * @return WC_Product|false
     */
    public static function get_or_create_product($product_name, $price = 0.0, $sku = null) {
        $product = null;

        if ($sku) {
            $product_id = wc_get_product_id_by_sku($sku);
            $product = wc_get_product($product_id);
        }

        if (!$product && $product_name) {
            $query = new WP_Query([
                'post_type'      => 'product',
                'posts_per_page' => 1,
                'title'          => $product_name,
                'post_status'    => 'publish',
                'fields'         => 'ids',
            ]);
            if (!empty($query->posts)) {
                $product = wc_get_product($query->posts[0]);
            }
        }

        if ($product) return $product;

        // Create new product
        $product = new WC_Product_Simple();
        $product->set_name($product_name);
        $product->set_regular_price($price);
        $product->set_sku($sku);
        $product->set_status('publish');
        $product->save();

        return $product;
    }

    /**
     * Create an order with a single product for a specific user.
     *
     * @param int $user_id
     * @param string $product_name
     * @param float $price
     * @param int $quantity
     * @param string|null $sku
     * @return int|WP_Error
     */
    public static function create_order_with_product($user_id, $product_name, $price, $quantity = 1, $sku = null) {
        $product = self::get_or_create_product($product_name, $price, $sku);
        if (!$product || !$product->get_id()) {
            return new WP_Error('product_creation_failed', 'Failed to create or retrieve the product.');
        }

        $order = wc_create_order(['customer_id' => $user_id]);

        $item = new WC_Order_Item_Product();
        $item->set_product($product);
        $item->set_quantity($quantity);
        $item->set_subtotal($price * $quantity);
        $item->set_total($price * $quantity);
        $order->add_item($item);

        $order->calculate_totals();
        $order->update_status('processing'); // or 'completed'

        return $order->get_id();
    }
}
