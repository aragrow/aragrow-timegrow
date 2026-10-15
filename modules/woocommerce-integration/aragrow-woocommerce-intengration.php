<?php
/**
 * TimeGrow WooCommerce Integration Module
 *
 * Integration settings for WooCommerce and PDF Invoice plugins.
 *
 * @package TimeGrow
 * @subpackage WooCommerce_Integration
 * @version 1.2.0
*/

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

require_once __DIR__ . '/includes/class-decimal-quantities.php';
Aragrow_TimeGrow_Decimal_Quantities::init();

class Aragrow_WOO_Integration_Plugins
{

    public function __construct()
    {

        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        //  add_filter('woocommerce_before_order_itemmeta', [$this, 'custom_admin_order_item_quantity_input'], 10, 2);
        // add_action('woocommerce_before_order_item_quantity_save', [$this, 'allow_decimal_quantities_in_orders'], 10, 2);
        //  add_filter('woocommerce_order_item_quantity_html', [$this, 'display_decimal_quantities_in_admin'], 10, 2);
        //  add_action('admin_menu', [$this, 'add_manual_payments_submenu']);
        add_action('add_meta_boxes', [ $this, 'add_manual_payment_meta_box' ]);
        // Save meta box data
        add_action('woocommerce_process_shop_order_meta', [ $this,'save_manual_payment_data' ], 10, 2);
        //  add_action('woocommerce_admin_order_data_after_order_details', [$this, 'display_manual_payment_details']);

        // Add Custom Field
        add_action('show_user_profile', [ $this, 'add_custom_field_ein_ssn' ]);  // Profile
        add_action('edit_user_profile', [ $this, 'add_custom_field_ein_ssn' ]);  // Admin
        
        add_action('woocommerce_admin_order_data_after_billing_address' , [ $this, 'add_timekeeping_invoice_field_to_order' ]);

        // Save Custom Field
        add_action('personal_options_update', [ $this, 'ein_ssn_save_user_field' ]); // Profile
        add_action('edit_user_profile_update', [ $this, 'ein_ssn_save_user_field' ]); // Admin

        // Per-line Project Hours input, applied to the quantity after the order items are saved
        add_action('woocommerce_after_order_itemmeta', [ $this, 'render_line_project_hours_input' ], 20, 3);
        add_action('woocommerce_process_shop_order_meta', [ $this, 'apply_project_hours_to_order_lines' ], 30);
        add_action('woocommerce_saved_order_items', [ $this, 'apply_hours_after_items_save' ], 20, 2);
        add_filter('woocommerce_order_item_quantity_html', [ $this, 'show_hours_for_hours_lines' ], 10, 2);
        add_filter('woocommerce_email_order_item_quantity', [ $this, 'email_hours_for_hours_lines' ], 10, 2);
        add_action('admin_enqueue_scripts', [ $this, 'enqueue_hours_invoice_script' ]);
        add_filter('woocommerce_hidden_order_itemmeta', [ $this, 'hide_internal_item_meta' ]);
        add_filter('woocommerce_order_item_display_meta_key', [ $this, 'label_item_meta_keys' ], 10, 3);
        // PDF Invoices & Packing Slips: offer TimeGrow's own template (listed as timegrow/aragrow)
        add_filter('wpo_wcpdf_template_paths', [ $this, 'add_pdf_template_path' ]);

        // Mostrar el campo en la lista de usuarios
        add_filter('manage_users_columns', [ $this, 'ein_ssn_add_user_column' ]);
        add_action('manage_users_custom_column', [ $this, 'ein_ssn_show_user_column_value' ], 10, 3 );

        // Modify the order line headers
        add_filter('wpo_wcpdf_get_aragrow_time_invoice_template_table_headers', [$this, 'custom_aragrow_time_invoice_table_headers'], 10, 2);
        add_filter('wpo_wcpdf_get_aragrow_invoice_template_table_headers', [$this, 'custom_aragrow_invoice_table_headers'], 10, 2);
    
        // Register the custom order status
        add_action('init', [$this, 'register_custom_invoice_paid_status' ]);
        // Add the custom status to WooCommerce order statuses
        add_filter('wc_order_statuses', [$this, 'add_custom_invoice_paid_to_order_statuses'], 10, 1);
        // For WooCommerce PDF Invoices & Packing Slips Plugin
        add_filter('wpo_wcpdf_document_is_allowed', [$this, 'enable_invoice_for_custom_invoice_paid_status'], 10, 2);
        // Add Email Notifications for Invoice Paid
        add_action('woocommerce_order_status_invoice_paid', [$this, 'send_custom_invoice_paid_email_notification'], 10, 1);
        // Add custom color for Invoice Paid status in admin panel
        add_action('admin_head', [$this, 'custom_invoice_paid_admin_styles']);

        // Register the custom order status
        add_action('init', [$this, 'register_custom_invoice_sent_status' ]);
        // Add the custom status to WooCommerce order statuses
        add_filter('wc_order_statuses', [$this, 'add_custom_invoice_sent_to_order_statuses'], 10, 1);
        // For WooCommerce PDF Invoices & Packing Slips Plugin
        add_filter('wpo_wcpdf_document_is_allowed', [$this, 'enable_invoice_for_custom_invoice_sent_status'], 10, 2);
        // Add Email Notifications for Invoice Sent
        add_action('woocommerce_order_status_invoice_sent', [$this, 'send_custom_invoice_sent_email_notification'], 10, 1);
       // Add custom color for Invoice Sent status in admin panel
        add_action('admin_head', [$this, 'custom_invoice_sent_admin_styles']);

        // Register the custom order status - Partial Paid
        add_action('init', [$this, 'register_custom_partial_paid_status' ]);
        // Add the custom status to WooCommerce order statuses
        add_filter('wc_order_statuses', [$this, 'add_custom_partial_paid_to_order_statuses'], 10, 1);
        // For WooCommerce PDF Invoices & Packing Slips Plugin
        add_filter('wpo_wcpdf_document_is_allowed', [$this, 'enable_invoice_for_custom_partial_paid_status'], 10, 2);
        // Add Email Notifications for Partial Paid
        add_action('woocommerce_order_status_partial_paid', [$this, 'send_custom_partial_paid_email_notification'], 10, 1);
       // Add custom color for Partial Paid status in admin panel
        add_action('admin_head', [$this, 'custom_partial_paid_admin_styles']);

        // Auto-record payment when status changes to invoice_paid
        add_action('woocommerce_order_status_invoice_paid', [$this, 'auto_record_full_payment'], 10, 1);
        // Auto-record payment when status changes to partial_paid
        add_action('woocommerce_order_status_partial_paid', [$this, 'auto_record_partial_payment'], 10, 1);

     /*   // Display in admin order details
        add_action('woocommerce_after_order_itemmeta', [$this, 'display_precise_quantity_admin'], 10, 3);
        add_filter('woocommerce_order_item_quantity_html', [$this, 'display_precise_quantity_customer'], 10, 2);
   */
    }

    // Helper function to get all product IDs in the TIMEGROW category
    function is_timegrow_product_id($id) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        $args = array(
            'ID' => $id,
            'category' => array('timegrow'),
            'limit' => 1, // Retrieve all products
        );
        $product = wc_get_products($args);
        if ($product) return true;
        else return false;
    }

    function custom_admin_order_item_quantity_input($item_id, $item) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        // Only run this on the order edit page
        if (!is_admin() || !isset($_GET['post']) || get_post_type($_GET['post']) !== 'shop_order') {
            return;
        }
    
        // Remove the default quantity field
        remove_action('woocommerce_before_order_itemmeta', array('WC_Meta_Box_Order_Items', 'output_item_quantity_input'), 10);
    
        // Output our custom quantity field
        ?>
        <div class="edit" style="display: none;">
            <input type="number"
                step="any"
                min="0"
                name="order_item_qty[<?php echo esc_attr($item_id); ?>]"
                placeholder="0"
                value="<?php echo esc_attr($item->get_quantity()); ?>"
                data-qty="<?php echo esc_attr($item->get_quantity()); ?>"
                size="8"
                class="quantity"
                style="width: 80px; padding: 5px;"
            />
        </div>
        <?php
    }   

    function allow_decimal_quantities_in_orders($item_id, $item) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        if (isset($_POST['order_item_qty'][$item_id])) {
            $quantity = floatval($_POST['order_item_qty'][$item_id]);
            $item->set_quantity($quantity); // Save as a decimal
        }
    }
    
    function display_decimal_quantities_in_admin($quantity_html, $item) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        $quantity = $item->get_quantity();
        return number_format((float) $quantity, 2, '.', ''); // Display with 2 decimal places
    }
    
    function add_manual_payments_submenu() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        add_submenu_page(
            'woocommerce',
            'Manual Payments',
            'Manual Payments',
            'manage_woocommerce',
            'manual-payments',
            [$this, 'manual_payments_page_callback']
        );
    }

    function manual_payments_page_callback() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        echo '<div class="wrap">';
        echo '<h1>Manual Payments</h1>';
        echo '<p>This is the Manual Payments page content.</p>';
        echo '</div>';
    }

    function add_manual_payment_meta_box() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        $screen = wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';
    
        timegrow_log('Adding manual payment meta box to screen: ' . $screen);
        add_meta_box(
            'manual_payment_meta_box',
            __('Manual Payment Details', 'timegrow'),
            [ $this, 'render_manual_payment_meta_box' ],
            $screen,
            'side',
            'high'
        );
    }

    function render_manual_payment_meta_box($post_or_order_object) {

        // Get the order object whether HPOS is enabled or not
        $order = ($post_or_order_object instanceof WP_Post)
            ? wc_get_order($post_or_order_object->ID)
            : $post_or_order_object;
    
        if (!$order) return;
    
        // Retrieve existing values
        $payment_date = $order->get_meta('_payment_date');
        $payment_amount = $order->get_meta('_payment_amount');
        $payment_type = $order->get_meta('_payment_type');
        $payment_trans_id = $order->get_meta('_payment_trans_id');
    
        // Display fields
        echo '<div class="manual-payment-fields">';
        
        // Payment Date
        echo '<p><label for="payment_date">' . __('Payment Date:', 'timegrow') . '</label>';
        echo '<input type="date" id="payment_date" name="payment_date" value="' . esc_attr($payment_date) . '" /></p>';
        
        // Payment Amount
        echo '<p><label for="payment_amount">' . __('Amount:', 'timegrow') . '</label>';
        echo '<input type="number" step="0.01" id="payment_amount" name="payment_amount" value="' . esc_attr($payment_amount) . '" /></p>';
        
        // Payment Type
        echo '<p><label for="payment_type">' . __('Type:', 'timegrow') . '</label>';
        echo '<select id="payment_type" name="payment_type">';
        echo '<option value="ACH"' . selected($payment_type, 'ACH', false) . '>ACH</option>';
        echo '<option value="Transference"' . selected($payment_type, 'Transference', false) . '>Transference</option>';
        echo '<option value="Cash"' . selected($payment_type, 'Cash', false) . '>Cash</option>';
        echo '<option value="PayPal"' . selected($payment_type, 'PayPal', false) . '>PayPal</option>';
        echo '<option value="Stripe"' . selected($payment_type, 'Stripe', false) . '>Stripe</option>';
        echo '<option value="Credit Card"' . selected($payment_type, 'Credit Card', false) . '>Credit Card</option>';
        echo '</select></p>';

        // Payment Transaction Id
        echo '<p><label for="payment_trans_id">' . __('Transaction Id:', 'timegrow') . '</label>';
        echo '<input type="text" id="payment_trans_id" name="payment_trans_id" value="' . esc_attr($payment_trans_id) . '" /></p>';

        echo '</div>';
    }

    function save_manual_payment_data($order_id, $post) {
        $order = wc_get_order($order_id);

        // Get submitted values
        $payment_date = isset($_POST['payment_date']) ? sanitize_text_field($_POST['payment_date']) : '';
        $payment_amount = isset($_POST['payment_amount']) ? floatval($_POST['payment_amount']) : 0;
        $payment_type = isset($_POST['payment_type']) ? sanitize_text_field($_POST['payment_type']) : '';
        $payment_trans_id = isset($_POST['payment_trans_id']) ? sanitize_text_field($_POST['payment_trans_id']) : '';

        // Validation: Only validate if any payment field is provided
        $has_payment_data = !empty($payment_date) || !empty($payment_amount) || !empty($payment_type) || !empty($payment_trans_id);

        if ($has_payment_data) {
            $errors = [];

            // Required: Payment Date
            if (empty($payment_date)) {
                $errors[] = 'Payment Date is required when recording a payment.';
            }

            // Required: Payment Amount (must be greater than 0)
            if (empty($payment_amount) || $payment_amount <= 0) {
                $errors[] = 'Payment Amount is required and must be greater than 0.';
            }

            // Required: Payment Type
            if (empty($payment_type)) {
                $errors[] = 'Payment Type is required when recording a payment.';
            }

            // Required: Transaction ID for specific payment types
            $requires_trans_id = ['ACH', 'Stripe', 'PayPal', 'Credit Card'];
            if (in_array($payment_type, $requires_trans_id) && empty($payment_trans_id)) {
                $errors[] = 'Transaction ID is required for ' . esc_html($payment_type) . ' payments.';
            }

            // If there are validation errors, display them and prevent save
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    add_action('admin_notices', function() use ($error) {
                        echo '<div class="notice notice-error is-dismissible"><p><strong>Payment Recording Error:</strong> ' . esc_html($error) . '</p></div>';
                    });
                }

                // Log validation failure
                if (timegrow_debug()) {
                    timegrow_log('Manual payment validation failed for Order #' . $order_id . ': ' . implode(' | ', $errors));
                }

                // Do not save invalid data
                return;
            }
        }

        // Save validated data
        if (!empty($payment_date)) {
            $order->update_meta_data('_payment_date', $payment_date);
        }

        if (!empty($payment_amount)) {
            $order->update_meta_data('_payment_amount', $payment_amount);
        }

        if (!empty($payment_type)) {
            $order->update_meta_data('_payment_type', $payment_type);
        }

        if (!empty($payment_trans_id)) {
            $order->update_meta_data('_payment_trans_id', $payment_trans_id);
        }

        $order->save();
    }

    function display_manual_payment_details($order) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        $order_id = $order->get_id();
        
        $payment_date = get_post_meta($order_id, '_payment_date', true);
        $payment_amount = get_post_meta($order_id, '_payment_amount', true);
        $payment_type = get_post_meta($order_id, '_payment_type', true);
        $payment_trans_id = get_post_meta($order_id, '_payment_trans_id', true);

        echo '<div style="margin-top:10px;">';
        echo '<h3>' . __('Manual Payment Details') . '</h3>';
        echo '<p><strong>' . __('Payment Date:') . '</strong> ' . esc_html($payment_date) .'<br />';
        echo ' <strong>' . __('Payment Amount:') . '</strong> $' . esc_html(number_format((float)$payment_amount, 2)) .'<br />';
        echo ' <strong>' . __('Payment Type:') . '</strong> ' . esc_html($payment_type) . '</p>';
        echo '<p><strong>' . __('Transaction ID:') . '</strong> ' . esc_html($payment_trans_id) . '</p>';
        echo '</div>';
    }

    function add_custom_field_ein_ssn($user) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        $timegrow_ein_ssn = get_user_meta($user->ID, 'timegrow_ein_ssn', true);
        ?>
        <h3><?php _e('INE/SSN'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="billing_dni_nie_CIF"><?php _e('Number'); ?></label></th>
                <td>
                    <input type="text" name="timegrow_ein_ssn" id="billing_dni_nie_CIF" 
                        value="<?php echo esc_attr($timegrow_ein_ssn); ?>" class="regular-text">
                    <p class="description"><?php _e('Introduce the EIN or SSN of the Client.'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    // Guardar el campo DNI/NIE/CIF cuando se actualiza el usuario
    function ein_ssn_save_user_field($user_id) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        if (!current_user_can('edit_user', $user_id)) {
            return false;
        }

        if (isset($_POST['timegrow_ein_ssn'])) {
            update_user_meta($user_id, '_timegrow_ein_ssn', sanitize_text_field($_POST['timegrow_ein_ssn']));
        }
    }

    // Mostrar el campo en la lista de usuarios
    function ein_ssn_add_user_column($columns) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        $columns['_timegrow_ein_ssn'] = __('EIN SSN');
        return $columns;
    }

    function ein_ssn_show_user_column_value($value, $column_name, $user_id) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        if ($column_name == 'timegrow_ein_ssn') {
            return get_user_meta($user_id, '_timegrow_ein_ssn', true);
        }
        return $value;
    }

    /**
     * Hours invoices are created by the TimeGrow order creator and cannot be switched
     * on or off later. The order editor only shows the status, read-only.
     */
    public function add_timekeeping_invoice_field_to_order($order) {
        if (!self::is_hours_invoice($order)) return;
        echo '<p class="form-field form-field-wide"><strong>' . esc_html__('Hours invoice', 'timegrow') . '</strong> '
            . esc_html__('(created by TimeGrow, cannot be changed)', 'timegrow') . '</p>';
    }

    /**
     * True for hours invoices: set only by the TimeGrow order creator.
     */
    public static function is_hours_invoice($order) {
        return $order && $order->get_meta('_timegrow_qty_unit') === 'hours';
    }

    /**
     * Register TimeGrow's PDF templates folder with PDF Invoices & Packing Slips.
     */
    public function add_pdf_template_path($paths) {
        $paths['timegrow'] = __DIR__ . '/templates/pdf/';
        return $paths;
    }

    /**
     * Hide TimeGrow's internal line item meta in the admin order editor.
     */
    public function hide_internal_item_meta($hidden) {
        $hidden[] = '_timegrow_project_hours';
        $hidden[] = '_timegrow_line_rate';
        $hidden[] = '_display_message';  // creator summary; hours and rate are shown in their columns
        $hidden[] = '_precise_quantity'; // written by the old Float Quantities plugin
        return $hidden;
    }

    /**
     * Show the time entry description as "Work done" instead of its meta key.
     */
    public function label_item_meta_keys($display_key, $meta, $item) {
        return $meta->key === '_work_done' ? __('Work done', 'timegrow') : $display_key;
    }

    /**
     * Load the hours invoice script on the order edit screen.
     */
    public function enqueue_hours_invoice_script($hook) {
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, ['woocommerce_page_wc-orders', 'shop_order'], true)) return;

        wp_enqueue_script(
            'timegrow-hours-invoice',
            plugin_dir_url(__FILE__) . 'assets/hours-invoice.js',
            ['jquery'],
            filemtime(__DIR__ . '/assets/hours-invoice.js'),
            true
        );

        // Rate per order line (the line's own rate, else project, client, company), keyed by line item ID
        $order_id = isset($_GET['id']) ? absint($_GET['id']) : (isset($_GET['post']) ? absint($_GET['post']) : 0);
        $order    = $order_id ? wc_get_order($order_id) : false;
        $rates    = [];
        if (self::is_hours_invoice($order)) {
            foreach ($order->get_items('line_item') as $item_id => $item) {
                $rate = $this->get_line_rate($order, $item);
                if ($rate > 0) $rates[$item_id] = $rate;
            }
        }
        wp_localize_script('timegrow-hours-invoice', 'timegrowHoursInvoice', [
            'enabled' => self::is_hours_invoice($order),
            'rates'   => (object) $rates,
        ]);
    }

    /**
     * Hourly rate for an order line, in this order:
     * 1. the project's rate
     * 2. the client's (user) default rate
     * 3. the company default rate
     * Returns 0 when none is set.
     *
     * @param int $product_id  Product on the order line (linked to a project).
     * @param int $customer_id Order customer, used when no project matches the product.
     */
    public function get_hours_rate($product_id, $customer_id = 0) {
        global $wpdb;

        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';

        $project = $wpdb->get_row($wpdb->prepare(
            "SELECT client_id, default_flat_fee FROM {$project_table} WHERE product_id = %d ORDER BY ID LIMIT 1",
            (int) $product_id
        ));

        // 1. Project rate
        if ($project && (float) $project->default_flat_fee > 0) return (float) $project->default_flat_fee;

        // 2. Client rate
        $client_id = $project && $project->client_id ? (int) $project->client_id : (int) $customer_id;
        if ($client_id) {
            $client_rate = (float) get_user_meta($client_id, 'timegrow_default_flat_fee', true);
            if ($client_rate > 0) return $client_rate;
        }

        // 3. Company rate
        return max(0.0, (float) timegrow_company_default_rate());
    }

    /**
     * Rate for a line on an hours invoice: the rate the line already carries
     * (subtotal / stored hours), else project, client, then company rate.
     * Keeping the line's own rate means a later rate change never reprices an old invoice.
     */
    public function get_line_rate($order, $item) {
        // Rate saved on the line when it was priced (kept even if the hours go to 0)
        $stored = (float) $item->get_meta('_timegrow_line_rate');
        if ($stored > 0) return $stored;

        $hours    = (float) $item->get_meta('_timegrow_project_hours');
        $subtotal = (float) $item->get_subtotal();
        if ($hours > 0 && $subtotal > 0) return $subtotal / $hours;

        $rate = $this->get_hours_rate($item->get_product_id(), $order->get_customer_id());
        if ($rate > 0) return $rate;

        // A line added by hand: its price per unit
        $qty = (float) $item->get_quantity();
        return $qty > 0 ? $subtotal / $qty : 0;
    }

    /**
     * Hours input on each line of an hours invoice. Stored as _timegrow_project_hours.
     */
    public function render_line_project_hours_input($item_id, $item, $product) {
        if (!is_admin() || !($item instanceof WC_Order_Item_Product)) return;
        if (!self::is_hours_invoice($item->get_order())) return;

        $hours = $item->get_meta('_timegrow_project_hours');
        if ($hours === '') $hours = $item->get_quantity();

        printf(
            '<div class="timegrow-line-hours" style="margin-top:6px;"><label><strong>%s</strong> <input type="number" step="0.01" min="0" style="width:90px;" name="timegrow_line_hours[%d]" value="%s"></label></div>',
            esc_html__('Hours:', 'timegrow'),
            (int) $item_id,
            esc_attr(number_format((float) $hours, 2, '.', ''))
        );
    }

    /**
     * Order editor "Update": apply each line's hours.
     */
    public function apply_project_hours_to_order_lines($order_id) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        if (empty($_POST['timegrow_line_hours']) || !is_array($_POST['timegrow_line_hours'])) return;

        $order = wc_get_order($order_id);
        if (!self::is_hours_invoice($order)) return;

        $this->apply_hours_to_lines($order, wp_unslash($_POST['timegrow_line_hours']));
    }

    /**
     * Order editor line "Save" (ajax).
     */
    public function apply_hours_after_items_save($order_id, $items) {
        if (empty($items['timegrow_line_hours']) || !is_array($items['timegrow_line_hours'])) return;

        $order = wc_get_order($order_id);
        if (!self::is_hours_invoice($order)) return;

        $this->apply_hours_to_lines($order, $items['timegrow_line_hours']);
    }

    /**
     * Hours invoice lines keep the hours in _timegrow_project_hours and a quantity of 1.
     * A line is repriced only when its hours change, at the line's own rate, so saving an
     * order for any other reason (status, notes) never changes its totals.
     */
    private function apply_hours_to_lines($order, array $posted) {
        $decimals = wc_get_price_decimals();
        $changed  = false;

        foreach ($order->get_items('line_item') as $item_id => $item) {
            if (!isset($posted[$item_id]) || !is_numeric($posted[$item_id])) continue;

            $hours = round((float) $posted[$item_id], 2);
            if ($hours < 0) continue;

            $prev_hours = $item->get_meta('_timegrow_project_hours');
            $hours_changed = $prev_hours === '' || abs((float) $prev_hours - $hours) >= 0.005;

            if ($hours_changed) {
                $rate         = $this->get_line_rate($order, $item); // read before the hours are updated
                $old_subtotal = (float) $item->get_subtotal();
                $ratio        = $old_subtotal > 0 ? (float) $item->get_total() / $old_subtotal : 1; // keep any discount
                $new_subtotal = round($rate * $hours, $decimals);

                $item->update_meta_data('_timegrow_project_hours', number_format($hours, 2, '.', ''));
                if ($rate > 0) $item->update_meta_data('_timegrow_line_rate', wc_format_decimal($rate, 4));
                $item->set_subtotal($new_subtotal);
                $item->set_total(round($new_subtotal * $ratio, $decimals));
                $changed = true;
            }

            // Hours unchanged but the price was edited by hand: the new price sets the line's rate
            // (compared with the rounded price the stored rate gives, so normal rounding is not an edit)
            $stored_rate = (float) $item->get_meta('_timegrow_line_rate');
            if (!$hours_changed && $hours > 0 && $stored_rate > 0
                && abs((float) $item->get_subtotal() - round($stored_rate * $hours, $decimals)) >= 0.005) {
                $item->update_meta_data('_timegrow_line_rate', wc_format_decimal((float) $item->get_subtotal() / $hours, 4));
            }

            if ((float) $item->get_quantity() != 1) {
                $item->set_quantity(1);
                $changed = true;
            }
            $item->save();
        }

        if ($changed) {
            $order->calculate_totals(true);
            $order->add_order_note('Line hours updated. Totals recalculated at each line\'s rate.');
        }
    }

    /**
     * Show hours instead of "x 1" for hours invoice lines in order views and emails.
     */
    public function show_hours_for_hours_lines($qty_html, $item) {
        if (!($item instanceof WC_Order_Item_Product)) return $qty_html;

        $hours = $item->get_meta('_timegrow_project_hours');
        if ($hours === '' || !self::is_hours_invoice($item->get_order())) return $qty_html;

        return '<strong class="product-quantity">' . esc_html(number_format((float) $hours, 2, '.', '')) . ' hrs</strong>';
    }

    /**
     * Numbers for one invoice line (used by the PDF template):
     * hours lines (on an hours invoice) show the stored hours and total / hours;
     * regular lines show their quantity and unit price.
     *
     * @param WC_Order           $order
     * @param WC_Order_Item|null $item         The order item (null falls back to $fallback_qty).
     * @param float|string       $fallback_qty Quantity from the PDF plugin's item data.
     * @return array { qty_display: string|float, unit_price: float, is_hours_line: bool }
     */
    public static function invoice_line_values($order, $item, $fallback_qty = 0) {
        $line_total    = $item ? (float) $item->get_total() : 0;
        $quantity      = $item ? (float) $item->get_quantity() : (float) $fallback_qty;
        $line_hours    = $item ? (float) $item->get_meta('_timegrow_project_hours') : 0;
        $is_hours_line = self::is_hours_invoice($order) && $line_hours > 0;

        if ($is_hours_line) {
            return ['qty_display' => number_format($line_hours, 2), 'unit_price' => $line_total / $line_hours, 'is_hours_line' => true];
        }
        return [
            'qty_display'   => wc_stock_amount($quantity),
            'unit_price'    => $quantity > 0 ? $line_total / $quantity : $line_total,
            'is_hours_line' => false,
        ];
    }

    /**
     * Same as above for WooCommerce emails (HTML and plain text): "3.75 hrs" instead of "1".
     */
    public function email_hours_for_hours_lines($qty_display, $item) {
        if (!($item instanceof WC_Order_Item_Product)) return $qty_display;

        $hours = $item->get_meta('_timegrow_project_hours');
        if ($hours === '' || !self::is_hours_invoice($item->get_order())) return $qty_display;

        return number_format((float) $hours, 2, '.', '') . ' hrs';
    }

    public function custom_aragrow_time_invoice_table_headers($headers, $document) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        $headers = array(
            'product'  => __('Project', 'woocommerce'),
            'quantity' => __('Hours', 'woocommerce'),
            'price'    => __('Rate', 'woocommerce'),
            'total'    => __('Total', 'woocommerce'),
        );
        return $headers;
    }

    public function custom_aragrow_invoice_table_headers($headers, $document) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        $headers = array(
            'product'  => __('Product', 'woocommerce'),
            'quantity' => __('Quantity', 'woocommerce'),
            'price'    => __('Price', 'woocommerce'),
            'total'    => __('Total', 'woocommerce'),
        );
        return $headers;
    }

    public function register_custom_invoice_paid_status() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        register_post_status('wc-invoice_paid', array(
            'label'                     => _x('Invoice Paid', 'Order status', 'woocommerce'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Invoice Paid (%s)', 'Invoice Paid (%s)', 'woocommerce'),
        ));
    }

    public function add_custom_invoice_paid_to_order_statuses($order_statuses) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        $order_statuses['wc-invoice_paid'] = _x('Invoice Paid', 'Order status', 'woocommerce');
        return $order_statuses;
    }

    public function enable_invoice_for_custom_invoice_paid_status($allowed, $document) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        if ($document->type == 'invoice') {
            $order = $document->order;
            if ($order->get_status() == 'invoice_paid') {
                $allowed = true;
            }
        }
        return $allowed;
    }

    public function register_custom_invoice_sent_status() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        register_post_status('wc-invoice_sent', array(
            'label'                     => _x('Invoice Sent to Client`', 'Order status', 'woocommerce'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Invoice Sent to Client (%s)', 'Invoice Sent to Client (%s)', 'woocommerce'),
        ));
    }

    public function add_custom_invoice_sent_to_order_statuses($order_statuses) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        $order_statuses['wc-invoice_sent'] = _x('Invoice Sent to Client', 'Order status', 'woocommerce');
        return $order_statuses;
    }

    public function enable_invoice_for_custom_invoice_sent_status($allowed, $document) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        if ($document->type == 'invoice') {
            $order = $document->order;
            if ($order->get_status() == 'invoice_sent') {
                $allowed = true;
            }
        }
        return $allowed;
    }

    public function send_custom_invoice_paid_email_notification($order_id) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        $order = wc_get_order($order_id);
    
        // Send an email to the customer
        $mailer = WC()->mailer();
        $email_content = sprintf(
            __('Your invoice #%s has been marked as paid.', 'woocommerce'),
            $order->get_order_number()
        );
        $email_heading = __('Invoice Paid Notification', 'woocommerce');
        
        $mailer->send(
            $order->get_billing_email(),
            $email_heading,
            $email_content
        );
    }

    public function send_custom_invoice_sent_email_notification($order_id) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        return; // This function is already defined above, so we can skip this duplicate definition.
        $order = wc_get_order($order_id);

        // Send an email to the customer
        $mailer = WC()->mailer();
        $email_content = sprintf(
            __('Your invoice #%s has been marked as sent to Client.', 'woocommerce'),
            $order->get_order_number()
        );
        $email_heading = __('Invoice Sent to Client Notification', 'woocommerce');

        $mailer->send(
            $order->get_billing_email(),
            $email_heading,
            $email_content
        );
    }

    public function register_custom_partial_paid_status() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        register_post_status('wc-partial_paid', array(
            'label'                     => _x('Partial Paid', 'Order status', 'woocommerce'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Partial Paid (%s)', 'Partial Paid (%s)', 'woocommerce'),
        ));
    }

    public function add_custom_partial_paid_to_order_statuses($order_statuses) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        $order_statuses['wc-partial_paid'] = _x('Partial Paid', 'Order status', 'woocommerce');
        return $order_statuses;
    }

    public function enable_invoice_for_custom_partial_paid_status($allowed, $document) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        if ($document->type == 'invoice') {
            $order = $document->order;
            if ($order->get_status() == 'partial_paid') {
                $allowed = true;
            }
        }
        return $allowed;
    }

    public function send_custom_partial_paid_email_notification($order_id) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        $order = wc_get_order($order_id);

        // Send an email to the customer
        $mailer = WC()->mailer();
        $email_content = sprintf(
            __('Your invoice #%s has been marked as partially paid.', 'woocommerce'),
            $order->get_order_number()
        );
        $email_heading = __('Partial Payment Notification', 'woocommerce');

        $mailer->send(
            $order->get_billing_email(),
            $email_heading,
            $email_content
        );
    }

    public function custom_invoice_paid_admin_styles() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        
        echo '<style>
            .order-status.status-invoice_paid { background: #28a745; color: #fff; }
        </style>';
    }
    
    public function custom_invoice_sent_admin_styles() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        echo '<style>
            .order-status.status-invoice_sent { background: #007bff; color: #fff; }
        </style>';
    }

    public function custom_partial_paid_admin_styles() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        echo '<style>
            .order-status.status-partial_paid { background: #ff9800; color: #fff; }
        </style>';
    }

    /**
     * Auto-record full payment when order status changes to invoice_paid
     */
    public function auto_record_full_payment($order_id) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        $order = wc_get_order($order_id);
        if (!$order) return;

        // Get existing payment data
        $existing_payment_date = $order->get_meta('_payment_date');
        $existing_payment_amount = $order->get_meta('_payment_amount');

        // Only auto-set if payment date is not already set
        if (empty($existing_payment_date)) {
            $order->update_meta_data('_payment_date', current_time('Y-m-d'));
        }

        // Only auto-set if payment amount is not already set
        // When invoice is marked as paid, payment amount should equal total
        if (empty($existing_payment_amount) || floatval($existing_payment_amount) == 0) {
            $order->update_meta_data('_payment_amount', $order->get_total());
        }

        $order->save();

        if(timegrow_debug()) {
            timegrow_log('Auto-recorded full payment for order #' . $order_id . ': $' . $order->get_total());
        }
    }

    /**
     * Auto-record partial payment when order status changes to partial_paid
     * Validates that a payment amount has been entered
     */
    public function auto_record_partial_payment($order_id) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        $order = wc_get_order($order_id);
        if (!$order) return;

        // Get existing payment data
        $existing_payment_date = $order->get_meta('_payment_date');
        $existing_payment_amount = $order->get_meta('_payment_amount');

        // Validate that payment amount exists and is greater than 0
        if (empty($existing_payment_amount) || floatval($existing_payment_amount) <= 0) {
            // Add admin notice
            if (is_admin()) {
                add_action('admin_notices', function() use ($order_id) {
                    echo '<div class="notice notice-error is-dismissible">';
                    echo '<p><strong>Warning:</strong> Order #' . $order_id . ' marked as Partial Paid but no payment amount was recorded. Please enter the payment amount in the Manual Payment Details section.</p>';
                    echo '</div>';
                });
            }

            if(timegrow_debug()) {
                timegrow_log('WARNING: Order #' . $order_id . ' marked as partial_paid but no payment amount set');
            }
            return;
        }

        // Validate that partial payment is less than total
        $payment_amount = floatval($existing_payment_amount);
        $order_total = $order->get_total();

        if ($payment_amount >= $order_total) {
            // If payment equals or exceeds total, this should be marked as fully paid instead
            if (is_admin()) {
                add_action('admin_notices', function() use ($order_id, $payment_amount, $order_total) {
                    echo '<div class="notice notice-warning is-dismissible">';
                    echo '<p><strong>Notice:</strong> Order #' . $order_id . ' payment amount ($' . number_format($payment_amount, 2) . ') equals or exceeds the order total ($' . number_format($order_total, 2) . '). Consider marking as "Invoice Paid" instead.</p>';
                    echo '</div>';
                });
            }
        }

        // Only auto-set date if not already set
        if (empty($existing_payment_date)) {
            $order->update_meta_data('_payment_date', current_time('Y-m-d'));
            $order->save();
        }

        if(timegrow_debug()) {
            timegrow_log('Validated partial payment for order #' . $order_id . ': $' . $payment_amount . ' of $' . $order_total);
        }
    }

    // Add a custom field to the order item meta for precise quantity

    
    // Display in admin order details
    function display_precise_quantity_admin($item_id, $item, $product) {
        if ($item->get_type() === 'line_item') {
            $precise_qty = $item->get_meta('_precise_quantity');
            if ($precise_qty) {
                echo '<div><strong>Precise Quantity:</strong> ' . $precise_qty . '</div>';
            }
        }
    }

    // Display in customer order details
     function display_precise_quantity_customer($qty_display, $item) {
        $precise_qty = $item->get_meta('_precise_quantity');
        if ($precise_qty) {
            return $precise_qty;
        }
        return $qty_display;
    }

}

class Aragrow_MyWooUnpaidInvoiceDashboardWidget {
    
    public function __construct() {
        
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        add_action('wp_dashboard_setup', [$this, 'woocommerce_unpaid_invoices_widget'],999);

    }

    public function woocommerce_unpaid_invoices_widget() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        $return = wp_add_dashboard_widget(
            'aragrow_woocommerce_unpaid_invoices', 
            'Unpaid Invoices', 
            [$this, 'woocommerce_unpaid_invoices_callback']
        );
    }

    public function woocommerce_unpaid_invoices_callback() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        // Get unpaid invoices
        $unpaid_invoices = $this->get_unpaid_invoices();
        if(empty($unpaid_invoices)) {
            echo '<p>No unpaid invoices found</p>';
            return;
        }

        // Sort invoices by client and invoice date
        usort($unpaid_invoices, function($a, $b) {
            if ($a['client'] == $b['client']) {
                return $a['invoice_date']->getTimestamp() <=> $b['invoice_date']->getTimestamp();
            } else {
                return $a['client'] <=> $b['client'];
            }
        });

        // Display invoices
        echo '<table class="aragrow-invoices">';
        echo '<tr><th>Client</th><th>Invoice Date</th><th>No.</th><th>Amount</th></tr>';
        foreach ($unpaid_invoices as $invoice) {

            echo '<tr>';
            echo '<td>' . esc_html($invoice['client']) . '</td>';
            echo '<td>' . esc_html($invoice['invoice_date']->date('m/d/Y')) . '</td>';
            echo '<td><a href="' . esc_url('admin.php?page=wc-orders&action=edit&id=' . $invoice['invoice_number'])  .'">' . esc_html($invoice['invoice_number']) . '</a></td>';
            echo '<td>' . $invoice['amount'] . '</td>';
            echo '</tr>';
        }   
        echo '</table>';
        echo '<style>.aragrow-invoices td{padding:8px 12px;}</style>';
    }

    public function get_unpaid_invoices() {
        $unpaid_invoices = [];
        global $wpdb;
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        if (!function_exists('wc_get_orders')) {
            timegrow_log('Error: WooCommerce is not loaded, unpaid invoices skipped.');
            return $unpaid_invoices;
        }

        $args = [
            'type' => 'shop_order',
            'status' => 'wc-pending'
        ];
        $orders = wc_get_orders($args);
        if (!$orders) return $unpaid_invoices;
        foreach ($orders as $order_data) {
            $invoice_number = $order_data->get_order_number();
            $client = $order_data->get_billing_first_name() . ' ' . $order_data->get_billing_last_name();
            $invoice_date = $order_data->get_date_created();
            $amount = $order_data->get_formatted_order_total();
            $unpaid_invoices[] = array(
                'client' => $client,
                'invoice_date' => $invoice_date,
                'invoice_number' => $invoice_number,
                'amount' => $amount
            );
        }
        return $unpaid_invoices;
    }
}
// In your plugin main file:
add_action('plugins_loaded', function() {
    new Aragrow_WOO_Integration_Plugins();
    // new Aragrow_MyWooUnpaidInvoiceDashboardWidget();
});