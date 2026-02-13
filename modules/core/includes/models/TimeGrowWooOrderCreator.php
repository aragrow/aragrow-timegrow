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
    
    public function create_woo_orders_and_products($time_entries) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        if (empty($time_entries)) return [];
        // Group entries by project_id

        // Start debug output buffer
        ob_start();

        var_dump($time_entries);
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

            $order = wc_get_orders([
                'customer_id' => $client_id,
                'status' => 'pending',
                'limit' => -1 // Get all orders
            ]);

            if (!empty($order)) {
                // If an order already exists, use it
                $order = $order[0]; // Get the first order
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
            }   



            foreach ($client_entries as $project_id => $entries) {
    
                //print_r($entries);
                $project = $model_project->select($project_id);
                if(!$project) {
                    error_log('Order creation failed: ' . 'Woo Commerce for Product for Project not found: '. $project_id);
                    continue;
                }
                //var_dump($project);
                $product_id = $project[0]->product_id;
                $woo_product = wc_get_product($product_id);


                $rate = $model_project->get_project_rate($project_id);
                $the_rate = $rate[0]->default_flat_fee ?? null;

                if (!$rate || bccomp($the_rate, '0.00', 2) === 0) {
                    $rate = $model_project->get_client_rate($client_id);
                    $the_rate = $rate[0]->default_flat_fee ?? null;
                }
                if (!$the_rate || bccomp($the_rate, '0.00', 2) === 0){
                    $rate = $model_project->get_company_rate(1);
                    $the_rate = $rate[0]->default_flat_fee ?? null;
                }
                if (!$the_rate || bccomp($the_rate, '0.00', 2) === 0){
                    $the_rate = 75;
                }

                $the_rate_10_min = $the_rate / 6; // Convert to 10 minute rate

                print("<br />---------> Project Rate: $the_rate\n");
                print("<br />---------> Project Rate (10 min): $the_rate_10_min\n");

                $product_hours = 0;
                $product_total = 0;

                foreach ($entries as $entry) {
                    if ($entry->billable != 1) continue;
                    if ($entry->billed != 0) continue;
                    print("<br />------> Project Type: $entry->entry_type, Project ID: $project_id, WOO Product ID: $product_id\n");
                    $project_id = (int) $entry->project_id;
                    

                    if ($entry->entry_type == "MAN") { 
                        $product_hours += $entry->hours;
                    } else {
                        $clock_IN = $entry->clock_in_date;
                        $clock_OUT = $entry->clock_out_date;
                        var_dump($clock_IN);
                        var_dump($clock_OUT);

                        if (!isset($clock_IN) || !isset($clock_OUT)) continue;

                       // 1. Convert to epoch seconds
                        $in  = strtotime($clock_IN);
                        $out = strtotime($clock_OUT);

                        // 2. Minutes between the two times
                        $hours = ($out - $in) / 3600;       // float
          
                        var_dump('Hours: '.$hours);
                        $product_hours += $hours;
                    }
       
                    $work_done = $entry->description;
                    $entry->billed_order_id = $order->get_id();
                
                } // End loop entries

                print('<br />---------> Product Hours: '.$product_hours);
        
                $product_quantity = $this->hours_to_10min_units($product_hours);

                $product_total = round($product_quantity * $the_rate_10_min, 2);

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
                    $product->set_category_ids([21]);
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
                $item->set_quantity($product_quantity);
                $item->add_meta_data('_display_message', $product_hours . ' hrs in 10 minutes increments. At a rate of '. $the_rate. ' per hour.');
                $item->add_meta_data('_work_done', $work_done);

                $item->set_total($product_total);
                
                $order->add_item($item);

            } // End loop thru time for projects

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

    public function hours_to_10min_units($hours) {
        $minutes = $hours * 60;
        return (int) ceil($minutes / 10);
    }
}
