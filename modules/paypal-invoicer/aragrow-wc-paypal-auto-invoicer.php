<?php
/**
 * TimeGrow PayPal Auto Invoicer Module
 *
 * Automatically creates and (optionally) sends a PayPal invoice when a WooCommerce
 * order is marked Completed. Creates PayPal Catalog Products for WC products.
 *
 * @package TimeGrow
 * @subpackage PayPal_Invoicer
 * @version 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Aragrow_WC_PayPal_Auto_Invoicer {
	private static $instance = null;
	private $option_key = 'wc_pp_auto_invoicer_settings';
	private $last_sanitized = null; // see sanitize_settings()

	public static function instance() {
		return self::$instance ?: ( self::$instance = new self() );
	}

	private function __construct() {
		add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_settings_styles' ] );
		add_action( 'woocommerce_order_status_inv_send2paypal', [ $this, 'handle_order_to_pay' ], 10, 3 );

                // Register the custom order status
        add_action('init', [$this, 'register_custom_invoice_paypal_status' ]);
        // Add the custom status to WooCommerce order statuses
        add_filter('wc_order_statuses', [$this, 'add_custom_invoice_paypal_to_order_statuses'], 10, 1);
        // For WooCommerce PDF Invoices & Packing Slips Plugin
        add_filter('wpo_wcpdf_document_is_allowed', [$this, 'enable_invoice_for_custom_inv_send2paypal_status'], 10, 2);
		add_filter('wpo_wcpdf_document_is_allowed', [$this, 'enable_invoice_for_custom_invoice_sent2paypal_status'], 10, 2);
        // Add Email Notifications for Invoice Sent
        // The customer notice is sent from handle_order_to_pay() only after PayPal sends the invoice
       // Add custom color for Invoice Paid status in admin panel
        add_action('admin_head', [$this, 'custom_invoice_paypal_admin_styles']);

        // Ensure your custom status appears in the wc-orders filters dropdown
        add_filter('woocommerce_admin_order_statuses', function( $statuses ) {
            if ( $this->offer_send_status() ) {
                $statuses['wc-inv_send2paypal'] = _x('Send Invoice to PayPal', 'Order status', 'woocommerce');
            }
            $statuses['wc-inv_draft2paypal'] = _x('Invoice Draft in PayPal', 'Order status', 'woocommerce');
            $statuses['wc-inv_sent2paypal']  = _x('Invoice Sent to PayPal', 'Order status', 'woocommerce');
            return $statuses;
        });

	}   

	/**
	 * Settings page
	 */
	/**
	 * Plugin styles (cards, toggle switches) on the PayPal settings page.
	 */
	public function enqueue_settings_styles( $hook ) {
		if ( $hook !== 'settings_page_paypal-integration' ) return;
		wp_enqueue_style( 'timegrow-modern-style', TIMEGROW_CORE_BASE_URI . 'assets/css/timegrow-modern.css' );
		wp_enqueue_script( 'timegrow-paypal-settings', plugin_dir_url( __FILE__ ) . 'assets/paypal-settings.js', [ 'jquery' ], filemtime( __DIR__ . '/assets/paypal-settings.js' ), true );
	}

	public function register_settings_page() {
		add_options_page(
			'PayPal Integration',
			'PayPal Integration',
			'manage_options',
			'paypal-integration',
			[ $this, 'render_settings_page' ]
		);
	}

 public function register_custom_invoice_paypal_status() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        register_post_status('wc-inv_send2paypal', array(
            'label'                     => _x('Send Invoice to PayPal', 'Order status', 'woocommerce'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Invoice Send to PayPal (%s)', 'Invoice Send to PayPal (%s)', 'woocommerce'),
        ));

        register_post_status('wc-inv_draft2paypal', array(
            'label'                     => _x('Invoice Draft in PayPal', 'Order status', 'woocommerce'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Invoice Draft in PayPal (%s)', 'Invoice Draft in PayPal (%s)', 'woocommerce'),
        ));

        register_post_status('wc-inv_sent2paypal', array(
            'label'                     => _x('Invoice Sent to PayPal', 'Order status', 'woocommerce'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Invoice Sent to PayPal (%s)', 'Invoice Sent to PayPal (%s)', 'woocommerce'),
        ));

    }

    /**
     * "Send Invoice to PayPal" is only offered while Send orders to PayPal is on.
     * It stays in the list for an order that already holds it, because WooCommerce
     * resets an order to Pending when its status is not in the list.
     * Invoice Sent / Draft in PayPal always stay, for orders already in PayPal.
     */
    private function offer_send_status() {
        if ( $this->get_settings()['enabled'] === 'yes' ) return true;

        $order_id = absint( $_REQUEST['id'] ?? $_REQUEST['post'] ?? $_REQUEST['post_ID'] ?? 0 );
        $order    = $order_id && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
        return $order && $order->get_status() === 'inv_send2paypal';
    }

    public function add_custom_invoice_paypal_to_order_statuses($order_statuses) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        if ($this->offer_send_status()) {
            $order_statuses['wc-inv_send2paypal'] = _x('Send Invoice to PayPal', 'Order status', 'woocommerce');
        }
		$order_statuses['wc-inv_draft2paypal'] = _x('Invoice Draft in PayPal', 'Order status', 'woocommerce');
		$order_statuses['wc-inv_sent2paypal'] = _x('Invoice Sent to PayPal', 'Order status', 'woocommerce');	
        return $order_statuses;
    }       	


    public function custom_invoice_paypal_admin_styles() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        
        echo '<style>
            .order-status.status-inv_send2paypal { background: purple; color: #fff; }
			.order-status.status-inv_sent2paypal { background: purple; color: #fff; }
			.order-status.status-inv_draft2paypal { background: #8a6d3b; color: #fff; }
        </style>';
    }

    public function enable_invoice_for_custom_inv_send2paypal_status($allowed, $document) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        if ($document->type == 'invoice') {
            $order = $document->order;
            if ($order->get_status() == 'inv_send2paypal') {
                $allowed = true;
            }
        }
        return $allowed;
    }           

	public function enable_invoice_for_custom_invoice_sent2paypal_status($allowed, $document) {
    if ($document->type === 'invoice') {
        $order = $document->order;
        if ($order && in_array($order->get_status(), ['inv_sent2paypal', 'inv_draft2paypal'], true)) {
            $allowed = true;
        }
    }
    return $allowed;
}

    public function send_custom_inv_send2paypal_email_notification( $order_id ) {
        if ( WP_DEBUG ) error_log(__CLASS__.'::'.__FUNCTION__);

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $to       = $order->get_billing_email();
        if ( ! $to ) {
            return;
        }

        $subject  = __( 'Invoice Sent to PayPal Notification', 'woocommerce' );
        $message  = sprintf(
            __( 'Your invoice #%s has been send to PayPal.', 'woocommerce' ),
            $order->get_order_number()
        );

        // Ensure Woo mailer is initialized and use its send() method
        $mailer   = WC()->mailer();
        $headers  = array( 'Content-Type: text/html; charset=UTF-8' );

        // Optional: wrap message in Woo template
        $message  = $mailer->wrap_message( $subject, wpautop( wp_kses_post( $message ) ) );

        $mailer->send( $to, $subject, $message, $headers, array() );
    }

	public function register_settings() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

		register_setting( 'wc_pp_auto_invoicer', $this->option_key, ['sanitize_callback' => [$this, 'sanitize_settings']]);

		add_settings_section( 'wc_pp_section_main', 'PayPal API Settings', function() {
			echo '<p>Provide your PayPal REST credentials. Use Sandbox while testing.</p>';
		}, 'paypal-integration' );

		add_settings_field( 'enabled', 'Send orders to PayPal', [ $this, 'render_checkbox_enabled' ], 'paypal-integration', 'wc_pp_section_main' );
		add_settings_field( 'env', 'Environment', [ $this, 'render_select_env' ], 'paypal-integration', 'wc_pp_section_main' );
		add_settings_field( 'client_id', 'Client ID', [ $this, 'render_text_client' ], 'paypal-integration', 'wc_pp_section_main' );
		add_settings_field( 'client_secret', 'Client Secret', [ $this, 'render_text_secret' ], 'paypal-integration', 'wc_pp_section_main' );
		add_settings_field( 'send_action', 'Invoice Delivery', [ $this, 'render_select_send' ], 'paypal-integration', 'wc_pp_section_main' );
		add_settings_field( 'merchant_email', 'Merchant Email (optional)', [ $this, 'render_text_email' ], 'paypal-integration', 'wc_pp_section_main' );
	}

    public function sanitize_settings($input) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // WordPress runs this filter twice when the option is saved for the first time.
        // The second pass gets our own output back: return it as is, or the credentials get encrypted twice.
        if ($this->last_sanitized !== null && $input === $this->last_sanitized) {
            return $input;
        }

        $out = [];
        $out['enabled']        = ! empty( $input['enabled'] ) ? 'yes' : 'no';
        $out['env']            = in_array($input['env'] ?? 'sandbox', ['sandbox','live'], true) ? $input['env'] : 'sandbox';
        $out['send_action']    = in_array($input['send_action'] ?? 'send', ['send','save'], true) ? $input['send_action'] : 'send';
        $out['merchant_email'] = isset($input['merchant_email']) ? sanitize_email($input['merchant_email']) : '';

        // Credentials are encrypted. An empty or missing value means "keep the saved one"
        // (the field stays locked until Update is clicked).
        $saved = get_option($this->option_key, []);
        foreach (['client_id', 'client_secret'] as $key) {
            $new_value  = isset($input[$key]) ? trim((string) $input[$key]) : '';
            $out[$key]  = $new_value !== '' ? $this->encrypt_data($new_value) : ($saved[$key] ?? '');
        }

        $this->last_sanitized = $out;

        return $out;
    }

	private function get_settings() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
		$defaults = [
			'enabled'       => 'yes', // yes|no; on by default so existing setups keep working
			'env'           => 'sandbox',
			'client_id'     => '',
			'client_secret' => '',
			'send_action'   => 'send', // send|save
			'merchant_email'=> ''
		];
		return wp_parse_args( get_option( $this->option_key, [] ), $defaults );
	}

	public function render_settings_page() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
		?>
		<div class="wrap timegrow-page">
			<!-- Modern Header -->
			<div class="timegrow-modern-header">
				<div class="timegrow-header-content">
					<h1><?php esc_html_e('PayPal Integration', 'timegrow'); ?></h1>
					<p class="subtitle"><?php esc_html_e('Configure PayPal API credentials and automatic invoice generation settings', 'timegrow'); ?></p>
				</div>
				<div class="timegrow-header-illustration">
					<span class="dashicons dashicons-money-alt"></span>
				</div>
			</div>

			<form method="post" action="options.php" class="timegrow-integration-form">
				<?php settings_fields( 'wc_pp_auto_invoicer' ); ?>

				<div class="timegrow-cards-container">

					<!-- API Credentials Card -->
					<div class="timegrow-card">
						<div class="timegrow-card-header">
							<div class="timegrow-icon timegrow-icon-primary">
								<span class="dashicons dashicons-admin-network"></span>
							</div>
							<div class="timegrow-card-title">
								<h2><?php esc_html_e('API Credentials', 'timegrow'); ?></h2>
								<span class="timegrow-badge timegrow-badge-primary">
									<?php esc_html_e('Required', 'timegrow'); ?>
								</span>
							</div>
						</div>

						<div class="timegrow-card-body">
							<p class="timegrow-card-description">
								<?php esc_html_e('Enter your PayPal REST API credentials. You can obtain these from the PayPal Developer Dashboard.', 'timegrow'); ?>
							</p>

							<div class="form-table-wrapper">
								<table class="form-table">
									<?php do_settings_sections( 'paypal-integration' ); ?>
								</table>
							</div>
						</div>
					</div>

					<!-- Information Card -->
					<div class="timegrow-card info-card">
						<div class="timegrow-card-header">
							<div class="timegrow-icon timegrow-icon-info">
								<span class="dashicons dashicons-info"></span>
							</div>
							<div class="timegrow-card-title">
								<h2><?php esc_html_e('How It Works', 'timegrow'); ?></h2>
							</div>
						</div>

						<div class="timegrow-card-body">
							<div class="timegrow-info-box">
								<span class="dashicons dashicons-yes-alt"></span>
								<div>
									<p><strong><?php esc_html_e('Automatic Invoice Creation', 'timegrow'); ?></strong></p>
									<p><?php esc_html_e('PayPal invoices are automatically created when WooCommerce orders are marked as "Send to PayPal".', 'timegrow'); ?></p>
								</div>
							</div>

							<div class="timegrow-info-box">
								<span class="dashicons dashicons-admin-users"></span>
								<div>
									<p><strong><?php esc_html_e('Automatic Client Creation', 'timegrow'); ?></strong></p>
									<p><?php esc_html_e('Each invoice carries the client\'s name, email, company and address, taken from the order or the client\'s profile. PayPal adds the client to your PayPal customers when the invoice is created.', 'timegrow'); ?></p>
								</div>
							</div>

							<div class="timegrow-info-box">
								<span class="dashicons dashicons-email"></span>
								<div>
									<p><strong><?php esc_html_e('Customer Email', 'timegrow'); ?></strong></p>
									<p><?php esc_html_e('Invoices are sent to the customer\'s billing email from WooCommerce. No pre-created PayPal customers required.', 'timegrow'); ?></p>
								</div>
							</div>

							<div class="timegrow-info-box">
								<span class="dashicons dashicons-products"></span>
								<div>
									<p><strong><?php esc_html_e('Product Sync', 'timegrow'); ?></strong></p>
									<p><?php esc_html_e('PayPal Catalog Products are automatically created for each WooCommerce SKU if they don\'t exist.', 'timegrow'); ?></p>
								</div>
							</div>
						</div>
					</div>

				</div>

				<div class="timegrow-footer">
					<?php submit_button(__('Save PayPal Settings', 'timegrow'), 'primary large', 'submit', false); ?>
					<a href="<?php echo esc_url(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-settings')); ?>" class="button button-secondary large">
						<span class="dashicons dashicons-arrow-left-alt"></span>
						<?php esc_html_e('Back to Settings', 'timegrow'); ?>
					</a>
				</div>
			</form>
		</div>
		<?php
	}

	public function render_checkbox_enabled() {
		$settings = $this->get_settings();
		?>
		<div class="timegrow-feature-item">
			<label class="timegrow-toggle-switch">
				<input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[enabled]" value="yes" <?php checked( $settings['enabled'], 'yes' ); ?>>
				<span class="timegrow-toggle-slider"></span>
			</label>
			<div class="timegrow-feature-info">
				<strong><?php esc_html_e( 'Send orders to PayPal', 'timegrow' ); ?></strong>
				<p><?php esc_html_e( 'Create PayPal invoices when an order is set to "Send Invoice to PayPal". When off, the status change only adds an order note.', 'timegrow' ); ?></p>
			</div>
		</div>
		<?php
	}

	public function render_select_env() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
		$settings = $this->get_settings();
		?>
		<select name="<?php echo esc_attr( $this->option_key ); ?>[env]">
			<option value="sandbox" <?php selected( $settings['env'], 'sandbox' ); ?>>Sandbox</option>
			<option value="live" <?php selected( $settings['env'], 'live' ); ?>>Live</option>
		</select>
		<?php
	}
	public function render_text_client() {
		$this->render_masked_credential( 'client_id', 'text', __( 'Enter the new client ID', 'timegrow' ) );
	}

	public function render_text_secret() {
		$this->render_masked_credential( 'client_secret', 'password', __( 'Enter the new client secret', 'timegrow' ) );
	}

	/**
	 * A saved credential is never printed. Only its last 8 characters are shown;
	 * "Update" opens an empty field for a new value, "Cancel" keeps the saved one.
	 */
	private function render_masked_credential( $key, $type, $placeholder ) {
		$settings = $this->get_settings();
		$value    = $settings[ $key ] ? (string) $this->decrypt_data( $settings[ $key ] ) : '';
		$name     = $this->option_key . '[' . $key . ']';

		if ( $value === '' ) {
			printf( '<input type="%s" style="width: 480px" name="%s" value="" autocomplete="off" />', esc_attr( $type ), esc_attr( $name ) );
			return;
		}
		?>
		<div class="timegrow-secret-field">
			<?php // Same look as the other fields; read-only, no name, so it is never submitted ?>
			<input type="text" class="timegrow-secret-mask" style="width: 480px" value="<?php echo esc_attr( str_repeat( "\u{2022}", 8 ) . substr( $value, -8 ) ); ?>" readonly />
			<input type="<?php echo esc_attr( $type ); ?>" class="timegrow-secret-input" style="width: 480px" name="<?php echo esc_attr( $name ); ?>" value="" autocomplete="off" placeholder="<?php echo esc_attr( $placeholder ); ?>" disabled hidden />
			<button type="button" class="button timegrow-secret-update" data-update="<?php esc_attr_e( 'Update', 'timegrow' ); ?>" data-cancel="<?php esc_attr_e( 'Cancel', 'timegrow' ); ?>"><?php esc_html_e( 'Update', 'timegrow' ); ?></button>
		</div>
		<?php
	}

	public function render_select_send() {
		$settings = $this->get_settings();
		?>
		<select name="<?php echo esc_attr( $this->option_key ); ?>[send_action]">
			<option value="send" <?php selected( $settings['send_action'], 'send' ); ?>><?php esc_html_e( 'Email the invoice to the client', 'timegrow' ); ?></option>
			<option value="save" <?php selected( $settings['send_action'], 'save' ); ?>><?php esc_html_e( 'Save as a draft in PayPal (I will send it myself)', 'timegrow' ); ?></option>
		</select>
		<p class="description"><?php esc_html_e( 'What happens when an order is set to "Send Invoice to PayPal". Email: PayPal emails the client the invoice with a payment link. Draft: the invoice waits in your PayPal account and the client gets nothing until you send it there.', 'timegrow' ); ?></p>
		<?php
	}
	public function render_text_email() {
		$settings = $this->get_settings();
		printf('<input type="email" style="width: 320px" name="%s[merchant_email]" value="%s" placeholder="you@yourcompany.com"/>', esc_attr($this->option_key), esc_attr($settings['merchant_email']) );
	}

	/**
	 * Woo: when an order becomes Completed, build & send a PayPal invoice
	 */
	public function handle_order_to_pay( $order_id, $order_obj = null, $transition = [] ) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

		if ( ! function_exists( 'wc_get_order' ) ) return;
		$order = wc_get_order( $order_id );
		if ( ! $order ) return;

		// An order goes to PayPal once. If it already has a PayPal invoice, refuse and put the status back.
		$previous    = ! empty( $transition['from'] ) && $transition['from'] !== 'inv_send2paypal' ? $transition['from'] : 'pending';
		$existing_id = $order->get_meta( '_paypal_invoice_id' ) ?: get_post_meta( $order_id, '_paypal_invoice_id', true );
		if ( $existing_id ) {
			if ( empty( $transition['from'] ) ) $previous = 'inv_sent2paypal';
			$this->restore_status_quietly( $order, $previous, sprintf( 'Not sent: this order is already in PayPal (invoice %s). Make any changes in PayPal.', $existing_id ) );
			return;
		}

		$settings = $this->get_settings();
		if ( $settings['enabled'] !== 'yes' ) {
			$this->restore_status_quietly( $order, $previous, 'Not sent: PayPal invoicing is turned off (Settings > PayPal Integration).' );
			return;
		}

		try {
			$token = $this->get_oauth_token( $settings );
			$pp_products_map = get_option( 'wc_pp_auto_invoicer_product_map', [] );

			$items = [];
			$is_hours_order = $order->get_meta( '_timegrow_qty_unit' ) === 'hours';
            
			foreach ( $order->get_items() as $item_id => $item ) {
				$product      = $item->get_product();
				$name         = $item->get_name();
				$quantity     = (float) $item->get_quantity();
				$line_total   = (float) $item->get_total();
				$unit_amount  = $quantity > 0 ? $line_total / $quantity : $line_total;
				$currency     = $order->get_currency();

				// Time invoice lines keep their hours on the line and a quantity of 1: send hours and the hourly rate.
				// Every other line sends its plain quantity.
				$line_hours = (float) $item->get_meta( '_timegrow_project_hours' );
				if ( $is_hours_order && $line_hours > 0 ) {
					$quantity    = $line_hours;
					$unit_amount = $line_total / $line_hours;
				}

				$sku = '';
				$desc = '';
				if ( $product ) {
					$sku = $product->get_sku();
					$desc = $product->get_short_description() ?: wp_strip_all_tags( $product->get_description() );
				}

				// Ensure a PayPal Catalog Product (optional)
				$pp_product_id = '';
				if ( $sku ) {
					$pp_product_id = isset( $pp_products_map[ $sku ] ) ? $pp_products_map[ $sku ] : $this->ensure_paypal_product( $token, $settings, $sku, $name, $desc );
					if ( $pp_product_id ) {
						$pp_products_map[ $sku ] = $pp_product_id;
					}
				}

				$pp_item = [
					'name'        => $name,
					'quantity'    => (string) $quantity,
					'unit_amount' => [ 'currency_code' => $currency, 'value' => number_format( $unit_amount, 2, '.', '' ) ],
				];
				if ( $sku ) { $pp_item['sku'] = $sku; }
				if ( $pp_product_id ) { $pp_item['item_id'] = $pp_product_id; }
				if ( $desc ) { $pp_item['description'] = wp_strip_all_tags( wp_trim_words( $desc, 50 ) ); }

				$items[] = $pp_item;
			}


			// Taxes & shipping as separate items (simple approach)
			$currency = $order->get_currency();
			$shipping_total = (float) $order->get_shipping_total();
			if ( $shipping_total > 0 ) {
				$items[] = [
					'name' => 'Shipping',
					'quantity' => '1',
					'unit_amount' => [ 'currency_code' => $currency, 'value' => number_format( $shipping_total, 2, '.', '' ) ],
				];
			}


			$tax_total = (float) $order->get_total_tax();
			if ( $tax_total > 0 ) {
				$items[] = [
					'name' => 'Tax',
					'quantity' => '1',
					'unit_amount' => [ 'currency_code' => $currency, 'value' => number_format( $tax_total, 2, '.', '' ) ],
				];
			}


			// Client details for PayPal: the order's billing info, with gaps filled from the client's profile.
			// PayPal has no separate customer API for invoices; it records the recipient as a customer.
			$billing_info    = $this->build_recipient( $order );
			$recipient_email = $billing_info['email_address'];

			$invoice_number = $this->get_invoice_number( $order );

			$invoice_payload = [
				'detail' => [
					'currency_code'        => $currency,
					'reference'            => (string) $order->get_id(),
					'note'                 => 'Thank you for your business!',
					'terms_and_conditions' => 'Payment due upon receipt.',
				],
				'invoicer' => array_filter([
					'email_address' => $settings['merchant_email'] ?: null,
				]),
				'primary_recipients' => [
					[ 'billing_info' => $billing_info ],
				],
				'items' => $items,
				'configuration' => [
					'tax_calculated_after_discount' => true,
					'tax_inclusive' => false,
				],
			];

			// Use the PDF invoice number when there is one; otherwise PayPal assigns its own
			if ( $invoice_number !== '' ) {
				$invoice_payload['detail']['invoice_number'] = $invoice_number;
			}

			try {
				$invoice = $this->api_request( $token, $settings, 'POST', '/v2/invoicing/invoices', $invoice_payload );
			} catch ( Exception $e ) {
				// PayPal refuses an invoice number it has seen before: let PayPal number this one instead
				if ( $invoice_number === '' || strpos( $e->getMessage(), 'DUPLICATE_INVOICE_NUMBER' ) === false ) {
					throw $e;
				}
				unset( $invoice_payload['detail']['invoice_number'] );
				$invoice = $this->api_request( $token, $settings, 'POST', '/v2/invoicing/invoices', $invoice_payload );
				$order->add_order_note( sprintf( 'PayPal already has an invoice numbered %s, so PayPal assigned its own number to this one.', $invoice_number ) );
			}

			// PayPal returns a link to the new invoice by default; the invoice ID is its last path part
			$pp_invoice_id = $invoice['id'] ?? '';
			if ( ! $pp_invoice_id && ! empty( $invoice['href'] ) && preg_match( '#/invoices/([^/?]+)#', $invoice['href'], $m ) ) {
				$pp_invoice_id = $m[1];
			}
			if ( ! $pp_invoice_id ) {
				throw new Exception( 'PayPal did not return an invoice ID.' );
			}

			$order->update_meta_data( '_paypal_invoice_id', sanitize_text_field( $pp_invoice_id ) );
			$order->save();

			if ( $settings['send_action'] === 'send' ) {
				$this->api_request( $token, $settings, 'POST', sprintf( '/v2/invoicing/invoices/%s/send', rawurlencode( $pp_invoice_id ) ), [ 'send_to_invoicer' => false ] );
				$order->add_order_note( sprintf( 'PayPal invoice %s created and sent to %s.', $pp_invoice_id, $recipient_email ) );
				$this->send_custom_inv_send2paypal_email_notification( $order_id );
			} else {
				$order->add_order_note( sprintf( 'PayPal invoice %s created as a draft (not sent).', $pp_invoice_id ) );
			}

			// Draft mode: the client has not received anything yet, so say so in the status
			$order->update_status( $settings['send_action'] === 'send' ? 'wc-inv_sent2paypal' : 'wc-inv_draft2paypal' );

			update_option( 'wc_pp_auto_invoicer_product_map', $pp_products_map );


		} catch ( Exception $e ) {
            if(WP_DEBUG) error_log( 'PayPal Invoicer error: ' . $e->getMessage());
			$order->add_order_note( 'PayPal Invoicer error: ' . $e->getMessage() );

			// Leave the order in a status that says where it stands, so it is never stuck on "Send Invoice to PayPal"
			if ( $order->get_meta( '_paypal_invoice_id' ) ) {
				// The invoice exists in PayPal but was not sent: finish it there
				$order->update_status( 'inv_draft2paypal', 'The invoice was created in PayPal but not sent. Send it from your PayPal account.' );
			} else {
				// Nothing reached PayPal: put the status back so it can simply be set again to retry
				$this->restore_status_quietly( $order, $previous, 'Not sent to PayPal. Fix the problem above, then set the status to Send Invoice to PayPal again.' );
			}
		}
	}

	/**
	 * Put an order back to a status without running that status's hooks again
	 * (no repeat "invoice sent"/"invoice paid" emails, no payment recording).
	 * Saving through the data store skips WooCommerce's status-change actions.
	 */
	private function restore_status_quietly( $order, $status, $note ) {
		$order->set_status( $status );
		$order->get_data_store()->update( $order );
		$order->add_order_note( $note );
	}

	/**
	 * Invoice number for PayPal: the PDF Invoices number, created now if the order has none yet.
	 * Returns '' when no number can be made, so PayPal numbers the invoice itself.
	 */
	private function get_invoice_number( $order ) {
		$number = (string) $order->get_meta( '_wcpdf_invoice_number' );
		if ( $number === '' && function_exists( 'wcpdf_get_document' ) ) {
			$document = wcpdf_get_document( 'invoice', $order, true ); // true: create the invoice number if missing
			if ( $document && $document->get_number() ) {
				$number = (string) $document->get_number()->get_formatted();
			}
		}
		return $number;
	}

	/**
	 * PayPal recipient (billing_info) for an order. Each field comes from the order's billing
	 * details first, then the client's WooCommerce customer profile, then the WordPress user.
	 * Throws when no email can be found, since PayPal needs one to create the invoice.
	 */
	private function build_recipient( $order ) {
		$customer_id = (int) $order->get_customer_id();
		$customer    = $customer_id ? new WC_Customer( $customer_id ) : null;
		$user        = $customer_id ? get_userdata( $customer_id ) : null;

		$pick = function( $field ) use ( $order, $customer ) {
			$getter = 'get_billing_' . $field;
			$value  = trim( (string) $order->$getter() );
			if ( $value === '' && $customer ) $value = trim( (string) $customer->$getter() );
			return $value;
		};

		$email = $pick( 'email' );
		if ( $email === '' && $user ) $email = $user->user_email;
		if ( ! is_email( $email ) ) {
			throw new Exception( 'The client has no valid email address. Add one to the order billing details or the client profile.' );
		}

		$first = $pick( 'first_name' );
		$last  = $pick( 'last_name' );
		if ( $first === '' && $last === '' && $user ) {
			$first = (string) $user->first_name;
			$last  = (string) $user->last_name;
		}
		if ( $first !== '' || $last !== '' ) {
			$name = array_filter( [ 'given_name' => $first, 'surname' => $last ] );
		} else {
			$name = $user && $user->display_name ? [ 'full_name' => $user->display_name ] : null;
		}

		$address = array_filter( [
			'address_line_1' => $pick( 'address_1' ),
			'address_line_2' => $pick( 'address_2' ),
			'admin_area_2'   => $pick( 'city' ),
			'admin_area_1'   => $pick( 'state' ),
			'postal_code'    => $pick( 'postcode' ),
			'country_code'   => $pick( 'country' ),
		] );
		// PayPal rejects an address without a country
		if ( empty( $address['country_code'] ) ) $address = [];

		return array_filter( [
			'email_address' => $email,
			'name'          => $name,
			'business_name' => $pick( 'company' ) ?: null,
			'address'       => $address ?: null,
		] );
	}

	/**
	 * Ensure a PayPal Catalog Product exists for SKU (optional convenience)
	 * Returns PayPal product id or empty string.
	 */
	private function ensure_paypal_product( $token, $settings, $sku, $name, $description = '' ) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

		$map = get_option( 'wc_pp_auto_invoicer_product_map', [] );
		if ( isset( $map[ $sku ] ) && $map[ $sku ] ) { return $map[ $sku ]; }

		// PayPal Catalog Product IDs must be unique; we can derive from sku (sanitize to max 127 chars)
		$product_id = substr( preg_replace( '/[^A-Za-z0-9-_.]/', '-', $sku ), 0, 120 );
		if ( ! $product_id ) { return ''; }

		// Try to GET first; if not found, create
		$response = $this->api_request( $token, $settings, 'GET', '/v1/catalogs/products/' . rawurlencode( $product_id ), null, true );
		if ( isset( $response['status_code'] ) && (int) $response['status_code'] === 200 ) {
			return $product_id;
		}

		$payload = [
			'id'          => $product_id,
			'name'        => $name ?: $sku,
			'description' => wp_strip_all_tags( $description ),
			'type'        => 'PHYSICAL',
		];
		$created = $this->api_request( $token, $settings, 'POST', '/v1/catalogs/products', $payload );
		if ( isset( $created['id'] ) ) { return $created['id']; }
		return '';
	}

	/**
	 * OAuth2 token
	 */
	private function get_oauth_token( $settings ) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

		$base = $settings['env'] === 'live' ? 'https://api.paypal.com' : 'https://api.sandbox.paypal.com';

		$decrypted_id = $this->decrypt_data($settings['client_id']);
		$decrypted_secret = $this->decrypt_data($settings['client_secret']);

		$auth = base64_encode( $decrypted_id . ':' . $decrypted_secret );
		$args = [
			'headers' => [
				'Authorization' => 'Basic ' . $auth,
				'Content-Type'  => 'application/x-www-form-urlencoded'
			],
			'body'    => http_build_query( [ 'grant_type' => 'client_credentials' ] ),
			'timeout' => 30
		];
		$response = wp_remote_post( $base . '/v1/oauth2/token', $args );
		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 400 || empty( $data['access_token'] ) ) {
			throw new Exception( 'OAuth token error: ' . wp_remote_retrieve_body( $response ) );
		}
		return [ 'token' => $data['access_token'], 'base' => $base ];
	}

	/**
	 * Generic API request (JSON)
	 */
	private function api_request( $token, $settings, $method, $path, $payload = null, $return_raw = false ) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

		$url = $token['base'] . $path;
		$args = [
			'headers' => [
				'Authorization' => 'Bearer ' . $token['token'],
				'Content-Type'  => 'application/json',
				'PayPal-Request-Id' => $this->idempotency_key( $method, $path, $payload ),
			],
			'timeout' => 30,
			'method'  => $method,
		];
		if ( $payload !== null ) {
			$args['body'] = wp_json_encode( $payload );
		}
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $return_raw ) {
			return [ 'status_code' => $code, 'body' => $body, 'data' => $data ];
		}

		if ( $code >= 400 ) {
			throw new Exception( sprintf( 'PayPal API %s %s failed [%s]: %s', $method, $path, $code, $body ) );
		}
		return is_array( $data ) ? $data : [];
	}

	private function idempotency_key( $method, $path, $payload ) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
		return substr( sha1( $method . '|' . $path . '|' . wp_json_encode( $payload ) . '|' . home_url() ), 0, 36 );
	}

    function encrypt_data($data) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        $key = defined('WC_PAYPAL_SECRET_KEY') ? WC_PAYPAL_SECRET_KEY : '';
        if (empty($key)) return '';

        $iv_length = openssl_cipher_iv_length('AES-256-CBC');
        $iv = openssl_random_pseudo_bytes($iv_length);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    function decrypt_data($encrypted_base64) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        $key = defined('WC_PAYPAL_SECRET_KEY') ? WC_PAYPAL_SECRET_KEY : '';
        if (empty($key)) return '';

        $encrypted_data = base64_decode($encrypted_base64);
        $iv_length = openssl_cipher_iv_length('AES-256-CBC');
        $iv = substr($encrypted_data, 0, $iv_length);
        $encrypted = substr($encrypted_data, $iv_length);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }
}

// WooCommerce loads after TimeGrow, so wait until all plugins are loaded before checking for it
add_action( 'plugins_loaded', function() {
	if ( class_exists( 'WooCommerce' ) ) {
		Aragrow_WC_PayPal_Auto_Invoicer::instance();
	}
} );
