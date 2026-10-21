<?php
/**
 * PayPal payment sync: updates WooCommerce orders when a PayPal invoice is paid.
 *
 * Three ways in, one way out:
 * - Webhook (instant): PayPal calls /wp-json/timegrow/v1/paypal-webhook. The signature is
 *   verified with PayPal before anything happens.
 * - Scheduled check (backup): WP-Cron checks every open PayPal invoice (daily by default).
 * - "Check PayPal status" button on the order.
 * All three call sync_order(), which reads the invoice from PayPal's API (never trusting the
 * incoming message) and updates the order: Invoice Paid / Partial Paid with the payment date,
 * amount and transaction ID, or an order note for cancelled / refunded invoices (an invoice
 * cancelled in PayPal does not cancel the order).
 * The other way round, cancel_invoice() cancels (or deletes a draft) PayPal invoice when the
 * invoice is cancelled from TimeGrow (see Aragrow_TimeGrow_Order_Cancellation).
 *
 * @package TimeGrow
 * @subpackage PayPal_Invoicer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Aragrow_TimeGrow_PayPal_Payment_Sync {

	const CRON_HOOK  = 'timegrow_paypal_sync';
	const REST_ROUTE = '/paypal-webhook';
	const OPEN_STATUSES = [ 'inv_sent2paypal', 'inv_draft2paypal', 'partial_paid' ];

	/** @var Aragrow_WC_PayPal_Auto_Invoicer */
	private $invoicer;

	public function __construct( $invoicer ) {
		$this->invoicer = $invoicer;
	}

	public function init() {
		add_action( 'init', [ $this, 'schedule' ] );
		add_action( self::CRON_HOOK, [ $this, 'run_scheduled' ] );
		add_action( 'rest_api_init', [ $this, 'register_webhook_route' ] );
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'admin_post_timegrow_paypal_check', [ $this, 'handle_check_button' ] );
		add_action( 'admin_notices', [ $this, 'show_check_notice' ] );
	}

	// ------------------------------------------------------------------
	// Deciding what a PayPal invoice status means for the order
	// ------------------------------------------------------------------

	/**
	 * What to do with an order, given the PayPal invoice. Pure: no side effects.
	 *
	 * @param array  $invoice         Invoice from GET /v2/invoicing/invoices/{id}.
	 * @param string $order_status    Current WooCommerce status (no "wc-" prefix).
	 * @param float  $recorded_amount Payment amount already recorded on the order.
	 * @param string $last_status     PayPal status seen at the last check.
	 * @return array { action: paid|partial|note|none, paypal_status, amount, date, transaction_id, note }
	 */
	public static function decide( array $invoice, $order_status, $recorded_amount = 0.0, $last_status = '' ) {
		$status       = (string) ( $invoice['status'] ?? '' );
		$transactions = $invoice['payments']['transactions'] ?? [];
		$last         = $transactions ? end( $transactions ) : [];
		$paid_amount  = isset( $invoice['payments']['paid_amount']['value'] ) ? (float) $invoice['payments']['paid_amount']['value'] : null;

		$result = [
			'action'         => 'none',
			'paypal_status'  => $status,
			'amount'         => $paid_amount,
			'date'           => isset( $last['payment_date'] ) ? substr( (string) $last['payment_date'], 0, 10 ) : '',
			'transaction_id' => (string) ( $last['payment_id'] ?? '' ),
			'note'           => '',
		];

		switch ( $status ) {
			case 'PAID':
			case 'MARKED_AS_PAID':
				if ( $order_status === 'invoice_paid' ) break; // already recorded
				$result['action'] = 'paid';
				if ( $result['amount'] === null ) {
					$result['amount'] = isset( $invoice['amount']['value'] ) ? (float) $invoice['amount']['value'] : null;
				}
				$result['note'] = 'PayPal reports the invoice as paid.';
				break;

			case 'PARTIALLY_PAID':
				if ( $paid_amount === null || $paid_amount <= 0 ) break;
				if ( $order_status === 'partial_paid' && abs( $paid_amount - (float) $recorded_amount ) < 0.005 ) break;
				$result['action'] = 'partial';
				$result['note']   = sprintf( 'PayPal reports a partial payment: %s paid so far.', number_format( $paid_amount, 2, '.', '' ) );
				break;

			case 'CANCELLED':
			case 'REFUNDED':
			case 'PARTIALLY_REFUNDED':
			case 'MARKED_AS_REFUNDED':
				if ( $last_status === $status ) break; // already noted
				$result['action'] = 'note';
				$result['note']   = sprintf( 'PayPal reports the invoice as %s. Review this order.', strtolower( str_replace( '_', ' ', $status ) ) );
				break;
		}

		return $result;
	}

	// ------------------------------------------------------------------
	// Syncing one order
	// ------------------------------------------------------------------

	/**
	 * PayPal invoice ID saved on an order (order meta, or post meta on older orders).
	 */
	public static function invoice_id_for( $order ) {
		return (string) ( $order->get_meta( '_paypal_invoice_id' ) ?: get_post_meta( $order->get_id(), '_paypal_invoice_id', true ) );
	}

	/**
	 * Read the invoice from PayPal and update the order. Returns a short message for notes and notices.
	 */
	public function sync_order( $order ) {
		// One check per order at a time (the webhook and the scheduled check can arrive together), and
		// read the order again once it is ours, so a payment the other one just recorded is seen
		$order_id = $order->get_id();
		if ( ! self::lock_order( $order_id ) ) {
			return 'Another check of this order is running.';
		}
		try {
			$order = wc_get_order( $order_id ) ?: $order;
			return $this->sync_locked_order( $order );
		} finally {
			self::unlock_order( $order_id );
		}
	}

	/** Lock one order for a minute: a row added atomically (INSERT IGNORE); an older lock is taken over. */
	private static function lock_order( $order_id ) {
		global $wpdb;
		$name = 'timegrow_pp_sync_' . (int) $order_id;
		for ( $try = 0; $try < 2; $try++ ) {
			if ( (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, (string) time() ) ) === 1 ) {
				return true;
			}
			$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
			if ( $since > time() - MINUTE_IN_SECONDS ) {
				return false;
			}
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $name, (string) $since ) );
		}
		return false;
	}

	private static function unlock_order( $order_id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", 'timegrow_pp_sync_' . (int) $order_id ) );
	}

	private function sync_locked_order( $order ) {
		$invoice_id = self::invoice_id_for( $order );
		if ( $invoice_id === '' ) {
			return 'This order has no PayPal invoice.';
		}

		$invoice  = $this->invoicer->paypal_request( 'GET', '/v2/invoicing/invoices/' . rawurlencode( $invoice_id ) );
		$decision = self::decide(
			$invoice,
			$order->get_status(),
			(float) $order->get_meta( '_payment_amount' ),
			(string) $order->get_meta( '_paypal_invoice_status' )
		);

		$order->update_meta_data( '_paypal_invoice_status', $decision['paypal_status'] );
		$order->update_meta_data( '_paypal_last_checked', current_time( 'mysql' ) );

		if ( in_array( $decision['action'], [ 'paid', 'partial' ], true ) ) {
			// Record the payment first: the Invoice Paid / Partial Paid hooks read these fields
			if ( $decision['amount'] !== null ) {
				$order->update_meta_data( '_payment_amount', wc_format_decimal( $decision['amount'], 2 ) );
			}
			$order->update_meta_data( '_payment_date', $decision['date'] ?: current_time( 'Y-m-d' ) );
			$order->update_meta_data( '_payment_type', 'PayPal' );
			if ( $decision['transaction_id'] !== '' ) {
				$order->update_meta_data( '_payment_trans_id', sanitize_text_field( $decision['transaction_id'] ) );
			}
			$order->save();
			$order->update_status( $decision['action'] === 'paid' ? 'invoice_paid' : 'partial_paid', $decision['note'] );
			return $decision['note'];
		}

		$order->save();
		if ( $decision['action'] === 'note' ) {
			$order->add_order_note( $decision['note'] );
			return $decision['note'];
		}
		return sprintf( 'PayPal status: %s. Nothing to update.', $decision['paypal_status'] ?: 'unknown' );
	}

	// ------------------------------------------------------------------
	// Refunds: PayPal first
	// ------------------------------------------------------------------

	/**
	 * Amount refunded on a PayPal invoice. A refund status without an amount counts as the full
	 * invoice. Pure: no side effects.
	 */
	public static function refunded_amount( array $invoice ) {
		if ( isset( $invoice['refunds']['refund_amount']['value'] ) ) {
			return (float) $invoice['refunds']['refund_amount']['value'];
		}
		if ( in_array( (string) ( $invoice['status'] ?? '' ), [ 'REFUNDED', 'MARKED_AS_REFUNDED' ], true ) ) {
			return (float) ( $invoice['amount']['value'] ?? 0 );
		}
		return 0.0;
	}

	/**
	 * Read the order's PayPal invoice and return how much PayPal shows as refunded. Throws on API errors.
	 */
	public function paypal_refunded( $order ) {
		$invoice = $this->invoicer->paypal_request( 'GET', '/v2/invoicing/invoices/' . rawurlencode( self::invoice_id_for( $order ) ) );
		$order->update_meta_data( '_paypal_invoice_status', (string) ( $invoice['status'] ?? '' ) );
		$order->update_meta_data( '_paypal_last_checked', current_time( 'mysql' ) );
		$order->save_meta_data();
		return self::refunded_amount( $invoice );
	}

	// ------------------------------------------------------------------
	// Cancelling the PayPal invoice when the order is cancelled
	// ------------------------------------------------------------------

	/**
	 * What cancelling the order means for its PayPal invoice. Pure: no side effects.
	 *
	 * @param string $paypal_status Invoice status from PayPal.
	 * @return string delete (a draft the client never saw) | cancel (sent, so PayPal cancels it) |
	 *                done (already cancelled) | paid (money came in: leave it, keep the time billed)
	 */
	public static function cancel_plan( $paypal_status ) {
		switch ( (string) $paypal_status ) {
			case 'DRAFT':
				return 'delete';
			case 'CANCELLED':
				return 'done';
			case 'PAID':
			case 'MARKED_AS_PAID':
			case 'PARTIALLY_PAID':
			case 'REFUNDED':
			case 'PARTIALLY_REFUNDED':
			case 'MARKED_AS_REFUNDED':
				return 'paid';
			default: // SENT, UNPAID, SCHEDULED, PAYMENT_PENDING
				return 'cancel';
		}
	}

	/**
	 * Cancel (or, for a draft, delete) the order's PayPal invoice. Reads the invoice first. Throws on API errors.
	 *
	 * @return array { release: bool (true when nothing can be paid any more, so the order may be cancelled), message: string }
	 */
	public function cancel_invoice( $order ) {
		$invoice_id = self::invoice_id_for( $order );
		$path       = '/v2/invoicing/invoices/' . rawurlencode( $invoice_id );
		$invoice    = $this->invoicer->paypal_request( 'GET', $path );
		$status     = (string) ( $invoice['status'] ?? '' );

		switch ( self::cancel_plan( $status ) ) {
			case 'delete':
				$this->invoicer->paypal_request( 'DELETE', $path );
				$status  = 'DELETED';
				$message = sprintf( 'PayPal draft invoice %s deleted (the client never received it).', $invoice_id );
				$release = true;
				break;
			case 'cancel':
				$notify = $this->invoicer->settings()['cancel_notify'] === 'yes';
				$this->invoicer->paypal_request( 'POST', $path . '/cancel', [
					'subject'           => 'Invoice cancelled',
					'note'              => sprintf( 'Invoice for order #%s has been cancelled. No payment is due.', $order->get_order_number() ),
					'send_to_invoicer'  => false,
					'send_to_recipient' => $notify,
				] );
				$status  = 'CANCELLED';
				$message = sprintf( 'PayPal invoice %s cancelled%s.', $invoice_id, $notify ? ' and the client was told' : ' (the client was not told)' );
				$release = true;
				break;
			case 'done':
				$message = sprintf( 'PayPal invoice %s was already cancelled.', $invoice_id );
				$release = true;
				break;
			default: // paid
				$message = sprintf( 'PayPal invoice %s shows %s, so it was not cancelled and the time stays billed. Review this order.', $invoice_id, $status );
				$release = false;
		}

		$order->update_meta_data( '_paypal_invoice_status', $status );
		$order->update_meta_data( '_paypal_last_checked', current_time( 'mysql' ) );
		$order->save_meta_data();

		return [ 'release' => $release, 'message' => $message ];
	}

	// ------------------------------------------------------------------
	// Scheduled check
	// ------------------------------------------------------------------

	public function schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, $this->invoicer->settings()['sync_interval'], self::CRON_HOOK );
		}
	}

	/**
	 * Scheduled run: sync every open PayPal invoice.
	 */
	public function run_scheduled() {
		if ( ! $this->invoicer->has_credentials() ) return;
		$this->sync_open_orders();
	}

	/**
	 * Orders with a PayPal invoice still waiting for payment: Invoice Sent to PayPal,
	 * Invoice Draft in PayPal or Partial Paid. Oldest first.
	 */
	public function open_orders( $limit = 50 ) {
		$orders = wc_get_orders( [ 'status' => self::OPEN_STATUSES, 'limit' => $limit, 'orderby' => 'date', 'order' => 'ASC' ] );
		return array_values( array_filter( $orders, function( $order ) {
			return self::invoice_id_for( $order ) !== '';
		} ) );
	}

	/**
	 * Sync every open PayPal invoice and report what happened to each order.
	 *
	 * @return array[] One row per order: order, invoice_id, before, after, message, error.
	 */
	public function sync_open_orders( $limit = 50 ) {
		$results = [];
		foreach ( $this->open_orders( $limit ) as $order ) {
			$before = $order->get_status();
			$row    = [ 'order' => $order, 'invoice_id' => self::invoice_id_for( $order ), 'before' => $before, 'after' => $before, 'message' => '', 'error' => false ];
			try {
				$row['message'] = $this->sync_order( $order );
				$fresh          = wc_get_order( $order->get_id() );
				$row['after']   = $fresh ? $fresh->get_status() : $before;
			} catch ( Exception $e ) {
				$row['message'] = 'PayPal check failed: ' . $e->getMessage();
				$row['error']   = true;
				timegrow_log( 'PayPal sync error for order #' . $order->get_id() . ': ' . $e->getMessage() );
			}
			$results[] = $row;
		}
		return $results;
	}

	/**
	 * How payment sync is set up, for the Sync Payments page.
	 */
	public function status_summary() {
		$settings  = $this->invoicer->settings();
		$intervals = [ 'hourly' => 'Every hour', 'twicedaily' => 'Twice a day', 'daily' => 'Once a day' ];
		$next      = wp_next_scheduled( self::CRON_HOOK );
		return [
			'credentials' => $this->invoicer->has_credentials(),
			'environment' => $settings['env'] === 'live' ? 'Live' : 'Sandbox',
			'interval'    => $intervals[ $settings['sync_interval'] ] ?? $settings['sync_interval'],
			'next_run'    => $next ? get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next ), 'Y-m-d H:i' ) : '',
			'webhook'     => $settings['webhook_id'] !== '',
		];
	}

	// ------------------------------------------------------------------
	// "Check PayPal status" button
	// ------------------------------------------------------------------

	public function add_meta_box() {
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		add_meta_box( 'timegrow_paypal_status', __( 'PayPal Invoice', 'timegrow' ), [ $this, 'render_meta_box' ], $screen, 'side', 'default' );
	}

	public function render_meta_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) return;

		$invoice_id = self::invoice_id_for( $order );
		if ( $invoice_id === '' ) {
			echo '<p>' . esc_html__( 'Not sent to PayPal.', 'timegrow' ) . '</p>';
			return;
		}

		$status  = $order->get_meta( '_paypal_invoice_status' );
		$checked = $order->get_meta( '_paypal_last_checked' );
		$url     = wp_nonce_url(
			admin_url( 'admin-post.php?action=timegrow_paypal_check&order_id=' . $order->get_id() ),
			'timegrow_paypal_check_' . $order->get_id()
		);

		echo '<p><strong>' . esc_html__( 'Invoice:', 'timegrow' ) . '</strong> ' . esc_html( $invoice_id ) . '</p>';
		echo '<p><strong>' . esc_html__( 'PayPal status:', 'timegrow' ) . '</strong> ' . esc_html( $status ?: __( 'not checked yet', 'timegrow' ) ) . '</p>';
		if ( $checked ) {
			echo '<p class="description">' . esc_html( sprintf( __( 'Last checked: %s', 'timegrow' ), $checked ) ) . '</p>';
		}
		// A paid order is refunded in PayPal first (TimeGrow refuses the refund here until then)
		if ( in_array( $order->get_status(), [ 'invoice_paid', 'partial_paid' ], true ) && ! in_array( $status, [ 'REFUNDED', 'MARKED_AS_REFUNDED' ], true ) ) {
			$message = $status === 'PARTIALLY_REFUNDED'
				? __( 'The PayPal invoice is only partly refunded. To refund more here, refund it in PayPal first.', 'timegrow' )
				: __( 'The PayPal invoice is not refunded yet. To refund this order, refund it in PayPal first.', 'timegrow' );
			echo '<p style="color: #996800;"><span class="dashicons dashicons-warning" style="font-size: 16px; width: 16px; height: 16px;"></span> ' . esc_html( $message ) . '</p>';
		}
		echo '<p><a class="button" href="' . esc_url( $url ) . '">' . esc_html__( 'Check PayPal status', 'timegrow' ) . '</a></p>';
	}

	public function handle_check_button() {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;
		check_admin_referer( 'timegrow_paypal_check_' . $order_id );
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to update orders.', 'timegrow' ), 403 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'timegrow' ), 404 );
		}

		try {
			$message = $this->sync_order( $order );
			$type    = 'success';
		} catch ( Exception $e ) {
			$message = 'PayPal check failed: ' . $e->getMessage();
			$type    = 'error';
			$order->add_order_note( $message );
		}

		set_transient( 'timegrow_paypal_notice_' . get_current_user_id(), [ 'type' => $type, 'message' => $message ], 60 );
		wp_safe_redirect( $order->get_edit_order_url() );
		exit;
	}

	public function show_check_notice() {
		$key    = 'timegrow_paypal_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! $notice ) return;
		delete_transient( $key );
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $notice['type'] === 'error' ? 'error' : 'success' ),
			esc_html( $notice['message'] )
		);
	}

	// ------------------------------------------------------------------
	// Webhook
	// ------------------------------------------------------------------

	public static function webhook_url() {
		return rest_url( 'timegrow/v1' . self::REST_ROUTE );
	}

	public function register_webhook_route() {
		register_rest_route( 'timegrow/v1', self::REST_ROUTE, [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_webhook' ],
			'permission_callback' => '__return_true', // public: PayPal calls it; the signature is verified in the callback
		] );
	}

	public function handle_webhook( WP_REST_Request $request ) {
		$event      = $request->get_json_params();
		$webhook_id = $this->invoicer->settings()['webhook_id'];

		if ( ! is_array( $event ) || $webhook_id === '' || ! $this->invoicer->has_credentials() ) {
			return new WP_REST_Response( [ 'ok' => false ], 400 );
		}

		// Ask PayPal whether this message really came from PayPal for our webhook
		try {
			$check = $this->invoicer->paypal_request( 'POST', '/v1/notifications/verify-webhook-signature', [
				'auth_algo'         => $request->get_header( 'paypal_auth_algo' ),
				'cert_url'          => $request->get_header( 'paypal_cert_url' ),
				'transmission_id'   => $request->get_header( 'paypal_transmission_id' ),
				'transmission_sig'  => $request->get_header( 'paypal_transmission_sig' ),
				'transmission_time' => $request->get_header( 'paypal_transmission_time' ),
				'webhook_id'        => $webhook_id,
				'webhook_event'     => $event,
			] );
		} catch ( Exception $e ) {
			timegrow_log( 'PayPal webhook verification error: ' . $e->getMessage() );
			return new WP_REST_Response( [ 'ok' => false ], 500 ); // PayPal retries later
		}
		if ( ( $check['verification_status'] ?? '' ) !== 'SUCCESS' ) {
			timegrow_log( 'PayPal webhook rejected: invalid signature.' );
			return new WP_REST_Response( [ 'ok' => false ], 400 );
		}

		if ( strpos( (string) ( $event['event_type'] ?? '' ), 'INVOICING.INVOICE.' ) !== 0 ) {
			return new WP_REST_Response( [ 'ok' => true, 'ignored' => true ], 200 );
		}

		$order = $this->find_order( $event['resource'] ?? [] );
		if ( ! $order ) {
			return new WP_REST_Response( [ 'ok' => true, 'ignored' => 'no matching order' ], 200 );
		}

		try {
			$this->sync_order( $order );
		} catch ( Exception $e ) {
			timegrow_log( 'PayPal webhook sync error for order #' . $order->get_id() . ': ' . $e->getMessage() );
			return new WP_REST_Response( [ 'ok' => false ], 500 ); // PayPal retries later
		}
		return new WP_REST_Response( [ 'ok' => true ], 200 );
	}

	/**
	 * Order for a webhook resource: the invoice's reference is the order ID TimeGrow sent;
	 * it only counts when that order's saved PayPal invoice ID matches.
	 */
	private function find_order( array $resource ) {
		$invoice    = isset( $resource['invoice'] ) && is_array( $resource['invoice'] ) ? $resource['invoice'] : $resource;
		$invoice_id = (string) ( $invoice['id'] ?? '' );
		if ( $invoice_id === '' ) return null;

		$reference = absint( $invoice['detail']['reference'] ?? 0 );
		$order     = $reference ? wc_get_order( $reference ) : false;
		if ( $order && self::invoice_id_for( $order ) === $invoice_id ) {
			return $order;
		}

		$matches = wc_get_orders( [ 'meta_key' => '_paypal_invoice_id', 'meta_value' => $invoice_id, 'limit' => 1 ] );
		return $matches ? $matches[0] : null;
	}
}
