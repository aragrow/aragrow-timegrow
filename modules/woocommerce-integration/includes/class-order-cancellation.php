<?php
/**
 * Cancelling a TimeGrow invoice.
 *
 * A TimeGrow invoice (an order made by Process Time) is cancelled from your site: the order
 * status dropdown, a bulk action, or the "Cancel invoice" order action. Every way runs the same
 * checks first. It refuses when a payment is recorded, and for a PayPal invoice it first asks
 * PayPal: a paid invoice is never cancelled (the payment is recorded instead), an unpaid one is
 * cancelled in PayPal (a draft is deleted) before the order is. When a check fails, the order
 * keeps its status. Once cancelled, the order's time entries are released, so the next Process
 * Time bills them again.
 *
 * A full refund (status Refunded) releases the time too; a partial refund does not. A paid order
 * can only be refunded (see paid_move_allowed()).
 *
 * An invoice cancelled in PayPal does not cancel the order (the payment sync only adds a note).
 * Reopening a cancelled or refunded invoice bills its time again, unless that time is already on
 * another invoice: then it is refused.
 *
 * @package TimeGrow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Aragrow_TimeGrow_Order_Cancellation {

	const ACTION        = 'timegrow_cancel_invoice';
	const RELEASED_META = '_timegrow_released_entries';
	const PAID_STATUSES = [ 'partial_paid', 'invoice_paid' ];

	/** True while cancel() changes the status after its own checks, so the guard does not run them twice. */
	private static $cancelling = false;

	public static function init() {
		add_filter( 'woocommerce_order_actions', [ __CLASS__, 'add_order_action' ], 10, 2 );
		add_action( 'woocommerce_order_action_' . self::ACTION, [ __CLASS__, 'handle_order_action' ] );
		add_action( 'woocommerce_before_order_object_save', [ __CLASS__, 'guard_status_change' ] );
		add_action( 'woocommerce_order_status_cancelled', [ __CLASS__, 'handle_cancelled' ], 10, 2 );
		add_action( 'woocommerce_order_status_refunded', [ __CLASS__, 'handle_cancelled' ], 10, 2 ); // full refund: the work is billed again too
		add_action( 'woocommerce_order_status_changed', [ __CLASS__, 'handle_reopened' ], 10, 4 );
		add_action( 'woocommerce_create_refund', [ __CLASS__, 'require_paypal_refund' ], 10, 2 );
		add_filter( 'woocommerce_valid_order_statuses_for_cancel', [ __CLASS__, 'customer_cancel_statuses' ], 10, 2 );
		add_action( 'admin_notices', [ __CLASS__, 'show_notice' ] );
		// The order data box prints the status dropdown between these two hooks
		add_action( 'woocommerce_admin_order_data_after_payment_info', [ __CLASS__, 'start_limiting_dropdown' ] );
		add_action( 'woocommerce_admin_order_data_after_order_details', [ __CLASS__, 'stop_limiting_dropdown' ] );
	}

	/** The order whose status dropdown is being printed, or null. */
	private static $dropdown_order = null;

	public static function start_limiting_dropdown( $order ) {
		self::show_order_notice(); // a refused status change or refund, shown right above the dropdown
		self::$dropdown_order = $order instanceof WC_Order ? $order : null;
		add_filter( 'wc_order_statuses', [ __CLASS__, 'limit_status_dropdown' ], 20 );
	}

	public static function stop_limiting_dropdown() {
		self::$dropdown_order = null;
		remove_filter( 'wc_order_statuses', [ __CLASS__, 'limit_status_dropdown' ], 20 );
	}

	/**
	 * In the order edit screen's status dropdown, offer only the statuses the order may move to
	 * (plus its own). Active only while that dropdown is printed, so order counts and status names
	 * elsewhere keep the full list; the guard enforces the rule on save.
	 */
	public static function limit_status_dropdown( $statuses ) {
		$order = self::$dropdown_order;
		if ( ! $order ) {
			return $statuses;
		}

		$moves = self::moves_for( $order, $order->get_status() );
		if ( $moves === null ) {
			return $statuses;
		}
		$keep = array_map( function ( $status ) { return 'wc-' . $status; }, array_merge( [ $order->get_status() ], $moves ) );
		return array_intersect_key( $statuses, array_flip( $keep ) );
	}

	// ------------------------------------------------------------------
	// Rules (pure)
	// ------------------------------------------------------------------

	/**
	 * Why an invoice can't be cancelled, or '' when it can. Pure: no side effects.
	 *
	 * @param string $status         Current status (no "wc-" prefix).
	 * @param float  $payment_amount Payment recorded on the order.
	 */
	public static function cancel_refusal( $status, $payment_amount ) {
		if ( $status === 'cancelled' ) {
			return 'This invoice is already cancelled.';
		}
		if ( in_array( $status, self::PAID_STATUSES, true ) || (float) $payment_amount > 0 ) {
			return 'A payment is recorded on this invoice, so it can\'t be cancelled. Refund the payment instead.';
		}
		return '';
	}

	/** Paid money moves one way: Partial Paid / Invoice Paid -> Refunded -> Cancelled. */
	const LOCKED_MOVES = [
		'partial_paid' => [ 'invoice_paid', 'refunded' ],
		'invoice_paid' => [ 'refunded' ],
		'refunded'     => [ 'cancelled' ], // TimeGrow invoices only: closes out a refunded invoice
	];

	/**
	 * Statuses an order may move to from $from, or null when it is not limited. The paid statuses
	 * are limited for every order, Refunded only for TimeGrow invoices. Pure: no side effects.
	 */
	public static function allowed_moves( $from, $is_timegrow_invoice = true ) {
		if ( $from === 'refunded' && ! $is_timegrow_invoice ) {
			return null;
		}
		return self::LOCKED_MOVES[ $from ] ?? null;
	}

	/**
	 * Statuses this order may move to from $from, or null when not limited. Like allowed_moves(),
	 * plus: a TimeGrow invoice cancelled after a refund stays closed (no moves).
	 */
	public static function moves_for( $order, $from ) {
		$timegrow = self::is_timegrow_invoice( $order );
		if ( $from === 'cancelled' && $timegrow && (float) $order->get_total_refunded() > 0 ) {
			return [];
		}
		return self::allowed_moves( $from, $timegrow );
	}

	/**
	 * Whether a status change is allowed by the paid -> refunded -> cancelled rule. Pure.
	 */
	public static function paid_move_allowed( $from, $to, $is_timegrow_invoice = true ) {
		$moves = self::allowed_moves( $from, $is_timegrow_invoice );
		return $moves === null || in_array( $to, $moves, true ) || $to === 'trash';
	}

	/**
	 * Which checks a cancel (from the dropdown, a bulk action or REST) runs. Pure.
	 * full: a TimeGrow invoice (payment, PayPal, then its time is released) · paypal: another order
	 * with a PayPal invoice (payment and PayPal only) · none: no checks (cancel() already ran them,
	 * the order was refunded first, or it is a plain order with no PayPal invoice).
	 */
	public static function cancel_checks( $from, $is_timegrow_invoice, $has_paypal_invoice, $cancelling ) {
		if ( $cancelling || $from === 'refunded' ) {
			return 'none';
		}
		if ( $is_timegrow_invoice ) {
			return 'full';
		}
		return $has_paypal_invoice ? 'paypal' : 'none';
	}

	/**
	 * Statuses a client may cancel from My Account: none for a TimeGrow invoice (it holds billed time
	 * and may have a PayPal invoice; the business cancels it). Other orders keep WooCommerce's.
	 */
	public static function customer_cancel_statuses( $statuses, $order = null ) {
		return self::is_timegrow_invoice( $order ) ? [] : $statuses;
	}

	/**
	 * An order made by TimeGrow's Process Time.
	 */
	public static function is_timegrow_invoice( $order ) {
		return $order && (bool) $order->get_meta( '_timekeeping_invoice' );
	}

	// ------------------------------------------------------------------
	// The checks every cancel runs
	// ------------------------------------------------------------------

	/**
	 * Make sure the invoice can't be paid any more: refuses when a payment is recorded, and for a
	 * PayPal invoice cancels it in PayPal (or deletes a draft), or refuses when PayPal shows a
	 * payment or can't be reached. Adds an order note either way.
	 *
	 * @param string $from Status the order is in now (before the cancel).
	 * @return string '' when the order may be cancelled, otherwise why not.
	 */
	public static function prepare_cancel( $order, $from ) {
		$refusal = self::cancel_refusal( $from, $order->get_meta( '_payment_amount' ) );
		if ( $refusal !== '' ) {
			$order->add_order_note( 'Invoice not cancelled: ' . $refusal );
			return $refusal;
		}

		// Skipped when the PayPal invoice is already cancelled or deleted (cancelled before, then reopened)
		$paypal_done = in_array( $order->get_meta( '_paypal_invoice_status' ), [ 'CANCELLED', 'DELETED' ], true );
		if ( $paypal_done || ! class_exists( 'Aragrow_TimeGrow_PayPal_Payment_Sync' ) || Aragrow_TimeGrow_PayPal_Payment_Sync::invoice_id_for( $order ) === '' ) {
			return '';
		}

		$invoicer = Aragrow_WC_PayPal_Auto_Invoicer::instance();
		if ( ! $invoicer->has_credentials() ) {
			$refusal = 'It is a PayPal invoice and the PayPal keys are missing, so TimeGrow can\'t check it is unpaid. Add them in Settings > PayPal Integration.';
			$order->add_order_note( 'Invoice not cancelled: ' . $refusal );
			return $refusal;
		}

		$sync = new Aragrow_TimeGrow_PayPal_Payment_Sync( $invoicer );
		try {
			$paypal = $sync->cancel_invoice( $order );
		} catch ( Exception $e ) {
			timegrow_log( 'PayPal cancel failed for order #' . $order->get_id() . ': ' . $e->getMessage() );
			$refusal = 'PayPal could not be reached or refused the cancel. Try again later. (' . $e->getMessage() . ')';
			$order->add_order_note( 'Invoice not cancelled: ' . $refusal );
			return $refusal;
		}

		$order->add_order_note( $paypal['message'] );
		if ( ! $paypal['release'] ) {
			// Record the payment PayPal has, after this save is done (the status change is being undone now)
			$order_id = $order->get_id();
			add_action( 'shutdown', function () use ( $sync, $order_id ) {
				try {
					$sync->sync_order( wc_get_order( $order_id ) );
				} catch ( Exception $e ) {
					timegrow_log( 'PayPal sync failed for order #' . $order_id . ': ' . $e->getMessage() );
				}
			} );
			return $paypal['message'];
		}
		return '';
	}

	// ------------------------------------------------------------------
	// Refunds: a PayPal invoice is refunded in PayPal first
	// ------------------------------------------------------------------

	/**
	 * Why the order can't be refunded up to $needed yet, or '' when it can: an order with a PayPal
	 * invoice needs at least that much refunded in PayPal first. Orders without one are not limited, and
	 * neither is an order whose PayPal invoice is fully refunded (its total can differ from the
	 * order's by a rounding cent).
	 *
	 * @param float $needed Total refunded on the order once this refund is done.
	 */
	public static function paypal_refund_shortfall( $order, $needed ) {
		if ( ! class_exists( 'Aragrow_TimeGrow_PayPal_Payment_Sync' ) || Aragrow_TimeGrow_PayPal_Payment_Sync::invoice_id_for( $order ) === '' ) {
			return '';
		}
		$invoicer = Aragrow_WC_PayPal_Auto_Invoicer::instance();
		if ( ! $invoicer->has_credentials() ) {
			return 'This is a PayPal invoice and the PayPal keys are missing, so TimeGrow can\'t check the refund in PayPal. Add them in Settings > PayPal Integration.';
		}
		try {
			$paypal = ( new Aragrow_TimeGrow_PayPal_Payment_Sync( $invoicer ) )->paypal_refunded( $order );
		} catch ( Exception $e ) {
			timegrow_log( 'PayPal refund check failed for order #' . $order->get_id() . ': ' . $e->getMessage() );
			return 'PayPal could not be reached to check the refund. Try again later.';
		}
		$refunded = $paypal['refunded'];
		if ( $paypal['full'] || $refunded + 0.005 >= (float) $needed ) {
			return '';
		}
		$invoice_id = Aragrow_TimeGrow_PayPal_Payment_Sync::invoice_id_for( $order );
		if ( $refunded < 0.005 ) {
			return sprintf( 'The PayPal invoice %s is not refunded yet. Refund it in PayPal first, then refund it here.', $invoice_id );
		}
		return sprintf(
			'The PayPal invoice %s is only refunded %s so far, and this would bring the order to %s refunded. Refund the rest in PayPal first.',
			$invoice_id,
			number_format( $refunded, 2, '.', ',' ),
			number_format( (float) $needed, 2, '.', ',' )
		);
	}

	/**
	 * The order's refunds once this one is done: the others plus this one. WooCommerce has already
	 * saved the new refund when it asks (its totals are saved first), so it is left out of the others
	 * and counted once. Pure.
	 *
	 * @param object[] $refunds The order's refunds (get_id, get_amount), possibly including $refund.
	 * @param object   $refund  The refund being made.
	 */
	public static function refunded_with( array $refunds, $refund ) {
		$total = 0.0;
		foreach ( $refunds as $other ) {
			if ( (int) $other->get_id() !== (int) $refund->get_id() || ! $refund->get_id() ) {
				$total += abs( (float) $other->get_amount() );
			}
		}
		return round( $total + abs( (float) $refund->get_amount() ), 2 );
	}

	/**
	 * Before a WooCommerce refund is final: refuse it (the refund screen shows the message, and
	 * WooCommerce removes the refund) when PayPal hasn't refunded that much yet.
	 */
	public static function require_paypal_refund( $refund, $args = [] ) {
		$order = wc_get_order( $refund->get_parent_id() );
		if ( ! $order ) {
			return;
		}
		$shortfall = self::paypal_refund_shortfall( $order, self::refunded_with( $order->get_refunds(), $refund ) );
		if ( $shortfall !== '' ) {
			$order->add_order_note( 'Refund not made: ' . $shortfall );
			throw new Exception( $shortfall ); // wc_create_refund() turns this into an error and saves nothing
		}
	}

	// ------------------------------------------------------------------
	// "Cancel invoice" order action
	// ------------------------------------------------------------------

	public static function add_order_action( $actions, $order = null ) {
		if ( ! $order && isset( $GLOBALS['theorder'] ) ) {
			$order = $GLOBALS['theorder'];
		}
		if ( self::is_timegrow_invoice( $order ) && self::cancel_refusal( $order->get_status(), $order->get_meta( '_payment_amount' ) ) === '' ) {
			$actions[ self::ACTION ] = __( 'Cancel invoice (releases the time for billing again)', 'timegrow' );
		}
		return $actions;
	}

	public static function handle_order_action( $order ) {
		$result = self::cancel( $order );
		self::notice( $result['ok'] ? 'success' : 'error', $result['message'] );
	}

	/**
	 * Cancel a TimeGrow invoice: the checks, then the order (which releases the time).
	 *
	 * @return array { ok: bool, message: string }
	 */
	public static function cancel( $order ) {
		$number = $order->get_order_number();
		if ( ! self::is_timegrow_invoice( $order ) ) {
			return [ 'ok' => false, 'message' => sprintf( 'Order #%s is not a TimeGrow invoice.', $number ) ];
		}

		$refusal = self::prepare_cancel( $order, $order->get_status() );
		if ( $refusal !== '' ) {
			return [ 'ok' => false, 'message' => sprintf( 'Order #%s was not cancelled. %s', $number, $refusal ) ];
		}

		self::$cancelling = true;
		$order->update_status( 'cancelled', 'Invoice cancelled.' );
		self::$cancelling = false;

		return [ 'ok' => true, 'message' => self::cancelled_message( $order ) ];
	}

	private static function cancelled_message( $order ) {
		$released = count( (array) $order->get_meta( self::RELEASED_META ) );
		return sprintf( 'Order #%s cancelled. %s', $order->get_order_number(), $released
			? sprintf( _n( '%d time entry was released and goes on the next invoice.', '%d time entries were released and go on the next invoice.', $released, 'timegrow' ), $released )
			: 'It had no time entries to release.' );
	}

	// ------------------------------------------------------------------
	// After a cancel or a full refund: release the time
	// ------------------------------------------------------------------

	/** Statuses that release an invoice's time; leaving one bills the time to it again. */
	const RELEASING_STATUSES = [ 'cancelled', 'refunded' ];

	public static function handle_cancelled( $order_id, $order = null ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( self::is_timegrow_invoice( $order ) ) {
			self::release_time( $order );
		}
	}

	/**
	 * Unbill the order's time entries and remember which they were (for a reopen).
	 *
	 * @return int Number of entries released.
	 */
	public static function release_time( $order ) {
		$model = new TimeGrowTimeEntryModel();
		$ids   = $model->billed_entry_ids_for_order( $order->get_id() );
		if ( ! $ids ) {
			return 0;
		}
		if ( $model->unbill_entries_for_order( $order->get_id() ) === false ) {
			$order->add_order_note( 'The time entries could not be released. Check the TimeGrow log.' );
			return 0;
		}
		$order->update_meta_data( self::RELEASED_META, $ids );
		$order->save_meta_data();
		$order->add_order_note( sprintf( '%d time entries released (IDs %s). Process Time will put them on a new invoice.', count( $ids ), implode( ', ', $ids ) ) );
		return count( $ids );
	}

	// ------------------------------------------------------------------
	// Guard: any status change to Cancelled runs the checks; no reopen when the time is billed elsewhere
	// ------------------------------------------------------------------

	public static function guard_status_change( $order ) {
		if ( ! $order->get_id() ) {
			return;
		}
		$changes = $order->get_changes();
		if ( ! isset( $changes['status'] ) ) {
			return;
		}
		$data = $order->get_data(); // values as loaded, before this change
		$from = $data['status'];
		$to   = $changes['status'];
		if ( $from === $to ) {
			return;
		}

		// Paid -> refunded -> cancelled (paid statuses for any order, Refunded for TimeGrow invoices)
		$moves = self::moves_for( $order, $from );
		if ( $moves !== null && ! in_array( $to, $moves, true ) && $to !== 'trash' ) {
			self::keep_status( $order, $from );
			$reasons = [
				'partial_paid' => 'A partly paid invoice can only move to Invoice Paid or Refunded. To undo it, refund the payment.',
				'invoice_paid' => 'A paid invoice can only be refunded. Use Refund in the order items to refund it.',
				'refunded'     => 'A refunded invoice can only be cancelled.',
				'cancelled'    => 'This invoice was refunded and cancelled, so it stays closed. Process Time bills its work on a new invoice.',
			];
			$reason = $reasons[ $from ];
			$order->add_order_note( 'Status not changed: ' . $reason );
			self::notice( 'error', sprintf( 'Order #%s keeps its status. %s', $order->get_order_number(), $reason ) );
			return;
		}

		// Refunded by hand: a PayPal invoice must be fully refunded in PayPal first
		if ( $to === 'refunded' ) {
			$shortfall = self::paypal_refund_shortfall( $order, (float) $order->get_total() );
			if ( $shortfall !== '' ) {
				self::keep_status( $order, $from );
				$order->add_order_note( 'Status not changed: ' . $shortfall );
				self::notice( 'error', sprintf( 'Order #%s keeps its status. %s', $order->get_order_number(), $shortfall ) );
				return;
			}
		}

		$checks = $to === 'cancelled' ? self::cancel_checks(
			$from,
			self::is_timegrow_invoice( $order ),
			class_exists( 'Aragrow_TimeGrow_PayPal_Payment_Sync' ) && Aragrow_TimeGrow_PayPal_Payment_Sync::invoice_id_for( $order ) !== '',
			self::$cancelling
		) : '';

		if ( ! self::is_timegrow_invoice( $order ) ) {
			// Any order can be sent to PayPal: cancelling it cancels its PayPal invoice too, so the
			// client can't pay a cancelled order (it has no time to release)
			if ( $checks === 'paypal' ) {
				$refusal = self::prepare_cancel( $order, $from );
				if ( $refusal !== '' ) {
					self::keep_status( $order, $from );
					self::notice( 'error', sprintf( 'Order #%s was not cancelled. %s', $order->get_order_number(), $refusal ) );
				}
			}
			return;
		}

		if ( $to === 'cancelled' ) {
			if ( $checks === 'none' ) {
				return; // cancel() ran the checks already, or the money was refunded (and the time released)
			}
			$refusal = self::prepare_cancel( $order, $from );
			if ( $refusal === '' ) {
				self::notice_after_cancel( $order->get_id() );
				return;
			}
			self::keep_status( $order, $from );
			self::notice( 'error', sprintf( 'Order #%s was not cancelled. %s', $order->get_order_number(), $refusal ) );
			return;
		}

		if ( in_array( $from, self::RELEASING_STATUSES, true ) && ! in_array( $to, self::RELEASING_STATUSES, true ) ) {
			$billed = ( new TimeGrowTimeEntryModel() )->billed_among( (array) $order->get_meta( self::RELEASED_META ) );
			if ( $billed ) {
				self::keep_status( $order, $from );
				$reason = sprintf( 'Its time is on another invoice now (entries %s). Leave this one cancelled.', implode( ', ', $billed ) );
				$order->add_order_note( 'Invoice not reopened: ' . $reason );
				self::notice( 'error', sprintf( 'Order #%s keeps its status. %s', $order->get_order_number(), $reason ) );
			}
		}
	}

	/**
	 * Success notice for a cancel from the status dropdown or a bulk action, once the time is released.
	 */
	private static function notice_after_cancel( $order_id ) {
		add_action( 'woocommerce_order_status_cancelled', function ( $id, $order = null ) use ( $order_id ) {
			if ( (int) $id === (int) $order_id ) {
				self::notice( 'success', self::cancelled_message( $order instanceof WC_Order ? $order : wc_get_order( $id ) ) );
			}
		}, 20, 2 );
	}

	/**
	 * Undo a pending status change, without firing any status hooks or emails.
	 */
	private static function keep_status( $order, $status ) {
		$order->set_status( $status );
		try {
			$transition = new ReflectionProperty( $order, 'status_transition' );
			if ( PHP_VERSION_ID < 80100 ) {
				$transition->setAccessible( true ); // needed before PHP 8.1 only
			}
			$transition->setValue( $order, false );
		} catch ( ReflectionException $e ) {
			timegrow_log( 'Could not clear the status change on order #' . $order->get_id() . ': ' . $e->getMessage() );
		}
	}

	/**
	 * A cancelled invoice reopened (the guard let it through): bill its released time to it again.
	 */
	public static function handle_reopened( $order_id, $from, $to, $order = null ) {
		if ( ! in_array( $from, self::RELEASING_STATUSES, true ) || in_array( $to, self::RELEASING_STATUSES, true ) ) {
			return;
		}
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		$ids   = $order ? (array) $order->get_meta( self::RELEASED_META ) : [];
		if ( ! $ids ) {
			return;
		}

		$count = ( new TimeGrowTimeEntryModel() )->rebill_entries( $ids, $order->get_id() );
		$order->delete_meta_data( self::RELEASED_META );
		$order->save_meta_data();

		$note = sprintf( 'Invoice reopened: %d time entries billed to it again.', (int) $count );
		if ( class_exists( 'Aragrow_TimeGrow_PayPal_Payment_Sync' ) && Aragrow_TimeGrow_PayPal_Payment_Sync::invoice_id_for( $order ) !== '' ) {
			$note .= ' Its PayPal invoice stays cancelled.';
		}
		$order->add_order_note( $note );
	}

	// ------------------------------------------------------------------
	// Notices
	// ------------------------------------------------------------------

	private static function notice( $type, $message ) {
		if ( ! function_exists( 'get_current_user_id' ) || ! get_current_user_id() ) {
			return;
		}
		$key       = 'timegrow_cancel_notice_' . get_current_user_id();
		$notices   = (array) get_transient( $key );
		$notices[] = [ 'type' => $type, 'message' => $message ];
		set_transient( $key, array_filter( $notices ), 60 );
	}

	/**
	 * Top-of-page notices, for pages other than the order edit screen (orders list after a bulk
	 * action). On the order edit screen they show inside the order box instead (show_order_notice).
	 */
	public static function show_notice() {
		if ( self::is_order_edit_screen() ) {
			return;
		}
		self::print_notices( 'notice is-dismissible' );
	}

	/**
	 * Inside the order box, right above the Status dropdown, where they can't be missed.
	 */
	public static function show_order_notice() {
		self::print_notices( 'notice inline', 'margin: 10px 0 0;' );
	}

	private static function print_notices( $class, $style = '' ) {
		$key     = 'timegrow_cancel_notice_' . get_current_user_id();
		$notices = get_transient( $key );
		if ( ! $notices ) {
			return;
		}
		delete_transient( $key );
		foreach ( (array) $notices as $notice ) {
			printf(
				'<div class="%s notice-%s" style="%s"><p><strong>%s</strong></p></div>',
				esc_attr( $class ),
				esc_attr( $notice['type'] === 'error' ? 'error' : 'success' ),
				esc_attr( $style ),
				esc_html( $notice['message'] )
			);
		}
	}

	private static function is_order_edit_screen() {
		if ( ( $_GET['action'] ?? '' ) === 'edit' && ( $_GET['page'] ?? '' ) === 'wc-orders' ) {
			return true; // HPOS
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && $screen->id === 'shop_order' && $screen->base === 'post'; // legacy order storage
	}
}
