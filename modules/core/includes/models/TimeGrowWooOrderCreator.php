<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

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
    
    public function create_woo_orders_and_products($time_entries) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        if (empty($time_entries)) return [];
        // Group entries by project_id

        // Start debug output buffer
        ob_start();

        $order_ids = [];
        $model_project = new TimeGrowProjectModel();

        $entries_by_clients = [];
        foreach ($time_entries as $entry) {
            if (!isset($entries_by_clients[$entry->client_id])) {
                $entries_by_clients[$entry->client_id] = [];
            }
            if (!isset($entries_by_clients[$entry->client_id][$entry->project_id])) {
                $entries_by_clients[$entry->client_id][$entry->project_id] = [];
            }
            $entries_by_clients[$entry->client_id][$entry->project_id][] = $entry;
        }

        print('<br />Creating WooCommerce Orders and Products for Manual Entries...<br />');

        foreach ($entries_by_clients as $client_id => $client_entries) {

            print('<br />---> ');print('Client Id: '.$client_id);

            // Reuse a pending hours invoice for this client, never an order built another way
            $order = wc_get_orders([
                'customer_id' => $client_id,
                'status' => 'pending',
                'meta_key' => '_timegrow_qty_unit',
                'meta_value' => 'hours',
                'limit' => -1 // Get all orders
            ]);

            // Skip invoices already issued (PDF invoice number or PayPal invoice): never add time to those
            $order = self::pick_reusable_order($order);

            $is_new_order = !$order;
            if ($order) {
                // If an order already exists, use it
                print('<br />---> Order already exists for Client Id: '.$client_id);
            } else {
                // Create a new order
                print('<br />---> Creating new Order for Client Id: '.$client_id);
                $order = wc_create_order(
                    [
                    'customer_id' => $client_id,
                    'status' => 'pending',
                    'customer_note' => 'Time entries for client ID: ' . $client_id,
                    ]   
                );
                $customer = new WC_Customer($client_id);    
                $billing_address = [
                    'first_name' => $customer->get_billing_first_name(),
                    'last_name'  => $customer->get_billing_last_name(),
                    'company'    => $customer->get_billing_company(),
                    'email'      => $customer->get_billing_email(),
                    'phone'      => $customer->get_billing_phone(),
                    'address_1'  => $customer->get_billing_address_1(),
                    'address_2'  => $customer->get_billing_address_2(),
                    'city'       => $customer->get_billing_city(),
                    'state'      => $customer->get_billing_state(),
                    'postcode'   => $customer->get_billing_postcode(),
                    'country'    => $customer->get_billing_country(),
                ];      
                $order->set_address($billing_address, 'billing');
                $order->add_meta_data('_timekeeping_invoice', true);
                $order->add_meta_data('_timegrow_qty_unit', 'hours'); // quantities are real hours
            }   



            foreach ($client_entries as $project_id => $entries) {
    
                //print_r($entries);
                $project = $model_project->select($project_id);
                if(!$project) {
                    timegrow_log('Order creation failed: ' . 'Woo Commerce for Product for Project not found: '. $project_id);
                    continue;
                }
                //var_dump($project);
                $product_id = $project[0]->product_id;
                $woo_product = wc_get_product($product_id);


                // Rate order: project, client, company, then 75
                $project_rate = $model_project->get_project_rate($project_id);
                $client_rate  = $model_project->get_client_rate($client_id);
                $the_rate = self::resolve_rate(
                    $project_rate[0]->default_flat_fee ?? null,
                    $client_rate[0]->default_flat_fee ?? null,
                    timegrow_company_default_rate()
                );


                print("<br />---------> Project Rate: $the_rate\n");

                $product_hours = 0;
                $product_total = 0;
                $work_done     = '';

                foreach ($entries as $entry) {
                    if (!self::is_billable($entry)) continue;
                    print("<br />------> Project Type: $entry->entry_type, Project ID: $project_id, WOO Product ID: $product_id\n");

                    $hours = self::entry_hours($entry);
                    if ($hours === null) continue; // clock entry still open
                    $product_hours += $hours;

                    $work_done = $entry->description;
                    $entry->billed_order_id = $order->get_id();
                
                } // End loop entries

                print('<br />---------> Product Hours: '.$product_hours);
        
                // Hours worked, priced at the hourly rate
                list($product_hours, $product_total) = self::price_line($product_hours, $the_rate);

                // Nothing billable for this project (all billed or non-billable): no line
                if ($product_hours <= 0) {
                    print('<br /> -------> No billable hours, project skipped');
                    // These entries were not billed on this order
                    foreach ($entries as $skipped) {
                        if (isset($skipped->billed_order_id) && $skipped->billed_order_id === $order->get_id()) unset($skipped->billed_order_id);
                    }
                    continue;
                }

                print('<br /> -------> Product Total Hours: '.$product_hours);
                print('<br /> -------> Product Total: '.$product_total);

                if (!$woo_product) {
                    $project = $model_project->select($project_id)[0];
                    // Product does not exist, create it
                    $product_name = $entry->display_name. ' - ' . $project->name;
                    $product = new WC_Product_Simple();
                    $product->set_name($product_name);
                    $product->set_regular_price($the_rate);
                    $product->set_virtual(true);
                    $term = get_term_by('slug', 'timegrow', 'product_cat');
                    if ($term) $product->set_category_ids([$term->term_id]);
                    $product->set_catalog_visibility('hidden');
                    $product->save();
                    $product_id = $product->get_id();

                    $model_project->set_woo_product($project_id, $product_id);

                } else {
                    $product_name = $woo_product->get_name();
                }

                print('<br /> ---------> Product Name: '.$product_name);

                // Add as line item
                $item = new WC_Order_Item_Product();
                
                $item->set_product_id($product_id);
                $item->set_name($product_name);
                $item->set_quantity(1); // hours live in _timegrow_project_hours; quantity stays 1
                $item->add_meta_data('_timegrow_project_hours', number_format($product_hours, 2, '.', ''));
                $item->add_meta_data('_timegrow_line_rate', wc_format_decimal($the_rate, 4));
                $item->add_meta_data('_display_message', $product_hours . ' hrs. At a rate of '. $the_rate. ' per hour.');
                $item->add_meta_data('_work_done', $work_done);

                $item->set_subtotal($product_total);
                $item->set_total($product_total);
                
                $order->add_item($item);

            } // End loop thru time for projects

            // A new order that got no lines (no billable hours for this client) is removed again
            if ($is_new_order && count($order->get_items('line_item')) === 0) {
                print('<br />---> No billable hours for Client Id: '.$client_id.', order not created');
                $order->delete(true);
                continue;
            }

            $order->calculate_totals();
            $order->update_status('processing');

            $order_ids[] = $order->get_id();

        } // End loop thru projects for clients

        // Capture debug output
        $debug_output = ob_get_clean();

        // Store debug output for later display
        $this->debug_output = $debug_output;

        return [$order_ids, $time_entries];
    }

    public function get_debug_output() {
        return $this->debug_output ?? '';
    }

    private $debug_output = '';

    /**
     * First pending hours invoice that has not been issued yet (no PDF invoice number,
     * no PayPal invoice). Returns null when there is none, so a new order is created.
     */
    public static function pick_reusable_order(array $orders) {
        foreach ($orders as $candidate) {
            if (!$candidate->get_meta('_wcpdf_invoice_number') && !$candidate->get_meta('_paypal_invoice_id')) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * A time entry is billed only when it is billable and not billed yet.
     */
    public static function is_billable($entry) {
        return $entry->billable == 1 && $entry->billed == 0;
    }

    /**
     * Hours for one time entry: manual entries use their hours; clock entries use the
     * time between clock in and clock out. Returns null for a clock entry still open.
     */
    public static function entry_hours($entry) {
        if ($entry->entry_type == 'MAN') {
            return (float) $entry->hours;
        }
        if (!isset($entry->clock_in_date) || !isset($entry->clock_out_date)) {
            return null;
        }
        return (strtotime($entry->clock_out_date) - strtotime($entry->clock_in_date)) / 3600;
    }

    /**
     * Hourly rate: the first of project, client and company rate that is above 0, else 75.
     */
    public static function resolve_rate(...$rates) {
        foreach ($rates as $rate) {
            if (is_numeric($rate) && (float) $rate > 0) {
                return (float) $rate;
            }
        }
        return 75.0;
    }

    /**
     * Hours rounded to 2 decimals, and the line total: rounded hours x rate, to the cent.
     *
     * @return array [ float $hours, float $total ]
     */
    public static function price_line($hours, $rate) {
        $hours = round((float) $hours, 2);
        return [$hours, round($hours * (float) $rate, 2)];
    }

}
