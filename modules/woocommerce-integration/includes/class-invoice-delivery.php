<?php
/**
 * Invoice delivery: each client chooses how they get invoices (Email, PayPal, Print and mail),
 * and one "Send invoice" order action sends it that way.
 *
 * - Client profile field "Invoice Delivery" (user meta timegrow_invoice_delivery, default Email).
 * - Order action "Send invoice (<method>)" in the order's Order actions box:
 *     Email          -> WooCommerce's customer invoice email with the PDF attached, order to Invoice Sent to Client.
 *     PayPal         -> order to Send Invoice to PayPal (the PayPal module takes it from there).
 *     Print and mail -> order to To Be Mailed; the Print & Mail page lists it with its PDF and a
 *                       "Mark as mailed" button that moves it to Invoice Sent to Client.
 * Status moves to Invoice Sent to Client are quiet (no second "invoice sent" email to the client).
 *
 * @package TimeGrow
 * @subpackage WooCommerce_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Aragrow_TimeGrow_Invoice_Delivery {

	const META         = 'timegrow_invoice_delivery';
	const DEFAULT_METHOD = 'email';
	const ACTION       = 'timegrow_send_invoice';
	const MAILED_STATUS = 'to_be_mailed';
	const PAGE_SLUG    = 'timegrow-nexus-print-mail';

	public static function methods() {
		return [
			'email'  => __( 'Email', 'timegrow' ),
			'paypal' => __( 'PayPal', 'timegrow' ),
			'mail'   => __( 'Print and mail', 'timegrow' ),
		];
	}

	public static function init() {
		// Client profile field
		add_action( 'show_user_profile', [ __CLASS__, 'render_profile_field' ], 20 );
		add_action( 'edit_user_profile', [ __CLASS__, 'render_profile_field' ], 20 );
		add_action( 'personal_options_update', [ __CLASS__, 'save_profile_field' ] );
		add_action( 'edit_user_profile_update', [ __CLASS__, 'save_profile_field' ] );

		// Order action
		add_filter( 'woocommerce_order_actions', [ __CLASS__, 'add_order_action' ], 10, 2 );
		add_action( 'woocommerce_order_action_' . self::ACTION, [ __CLASS__, 'send' ] );
		add_filter( 'woocommerce_valid_order_statuses_for_payment', [ __CLASS__, 'payable_statuses' ], 10, 2 );

		// To Be Mailed status
		add_action( 'init', [ __CLASS__, 'register_status' ] );
		add_filter( 'wc_order_statuses', [ __CLASS__, 'add_status' ] );
		add_filter( 'wpo_wcpdf_document_is_allowed', [ __CLASS__, 'allow_pdf' ], 10, 2 );
		add_action( 'admin_head', [ __CLASS__, 'status_color' ] );

		// Print & Mail page
		add_action( 'admin_menu', [ __CLASS__, 'register_page' ] );
		add_action( 'admin_post_timegrow_mark_mailed', [ __CLASS__, 'handle_mark_mailed' ] );
		add_action( 'admin_notices', [ __CLASS__, 'show_notice' ] );
	}

	// ------------------------------------------------------------------
	// The client's choice
	// ------------------------------------------------------------------

	/**
	 * Delivery method saved on a client's profile: email, paypal or mail. Email when none is saved.
	 */
	public static function method_for_user( $user_id ) {
		$method = $user_id ? (string) get_user_meta( (int) $user_id, self::META, true ) : '';
		return array_key_exists( $method, self::methods() ) ? $method : self::DEFAULT_METHOD;
	}

	public static function method_for_order( $order ) {
		return self::method_for_user( $order->get_customer_id() );
	}

	public static function render_profile_field( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) return;
		$current = self::method_for_user( $user->ID );
		?>
		<h3><?php esc_html_e( 'TimeGrow Invoicing', 'timegrow' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><label for="timegrow_invoice_delivery"><?php esc_html_e( 'Invoice Delivery', 'timegrow' ); ?></label></th>
				<td>
					<select name="timegrow_invoice_delivery" id="timegrow_invoice_delivery">
						<?php foreach ( self::methods() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'How this client gets invoices when you use "Send invoice" on an order. Email sends the PDF; PayPal sends a PayPal invoice; Print and mail puts it on the Print & Mail list.', 'timegrow' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function save_profile_field( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) || ! isset( $_POST['timegrow_invoice_delivery'] ) ) return;
		$method = sanitize_key( wp_unslash( $_POST['timegrow_invoice_delivery'] ) );
		if ( array_key_exists( $method, self::methods() ) ) {
			update_user_meta( $user_id, self::META, $method );
		}
	}

	// ------------------------------------------------------------------
	// Sending
	// ------------------------------------------------------------------

	/**
	 * Statuses the client can pay from (the "Pay for this order" link in the invoice email): a
	 * TimeGrow invoice also once it is sent or waiting to be mailed. Not the PayPal statuses: those
	 * are paid on the PayPal invoice.
	 */
	public static function payable_statuses( $statuses, $order = null ) {
		if ( $order && $order->get_meta( '_timekeeping_invoice' ) ) {
			$statuses = array_values( array_unique( array_merge( (array) $statuses, [ 'invoice_sent', self::MAILED_STATUS ] ) ) );
		}
		return $statuses;
	}

	/** Statuses an invoice can be sent (or sent again) from: not paid, cancelled, refunded or at PayPal. */
	const SENDABLE_STATUSES = [ 'pending', 'on-hold', 'processing', 'invoice_sent', 'to_be_mailed' ];

	/**
	 * Why this order's invoice can't be sent, or '' when it can. Sending changes the status without
	 * the status checks (no second status email), so it is only offered where that is safe: a
	 * cancelled invoice whose time was billed again, or a paid one, is never reopened. Pure.
	 */
	public static function send_refusal( $status ) {
		if ( in_array( $status, self::SENDABLE_STATUSES, true ) ) {
			return '';
		}
		return sprintf( __( 'Not sent: the order is %s. Only an unpaid invoice that is not at PayPal can be sent.', 'timegrow' ), $status );
	}

	public static function add_order_action( $actions, $order = null ) {
		if ( ! $order ) {
			global $theorder;
			$order = $theorder;
		}
		if ( $order instanceof WC_Order && self::send_refusal( $order->get_status() ) === '' ) {
			$label = self::methods()[ self::method_for_order( $order ) ];
			$actions[ self::ACTION ] = sprintf( __( 'Send invoice (%s)', 'timegrow' ), $label );
		}
		return $actions;
	}

	/**
	 * Send an order's invoice the way its client chose. Every outcome leaves an order note.
	 */
	public static function send( $order ) {
		$refusal = self::send_refusal( $order->get_status() );
		if ( $refusal !== '' ) {
			$order->add_order_note( $refusal );
			return;
		}
		$method = self::method_for_order( $order );

		switch ( $method ) {
			case 'paypal':
				self::send_paypal( $order );
				break;
			case 'mail':
				$order->update_status( self::MAILED_STATUS, __( 'Invoice queued for print and mail (client\'s invoice delivery choice).', 'timegrow' ) );
				break;
			default:
				self::send_email( $order );
		}
	}

	private static function send_paypal( $order ) {
		if ( ! class_exists( 'Aragrow_WC_PayPal_Auto_Invoicer' ) ) {
			$order->add_order_note( __( 'Not sent: the client gets invoices by PayPal, but the PayPal module is not loaded.', 'timegrow' ) );
			return;
		}
		$settings = Aragrow_WC_PayPal_Auto_Invoicer::instance()->settings();
		if ( $settings['enabled'] !== 'yes' ) {
			$order->add_order_note( __( 'Not sent: the client gets invoices by PayPal, but "Send orders to PayPal" is turned off.', 'timegrow' ) );
			return;
		}
		// The PayPal module handles the rest when the order enters this status
		$order->update_status( 'inv_send2paypal', __( 'Sending by PayPal (client\'s invoice delivery choice).', 'timegrow' ) );
	}

	private static function send_email( $order ) {
		$email_address = $order->get_billing_email();
		if ( ! is_email( $email_address ) ) {
			$order->add_order_note( __( 'Not sent: the client has no valid billing email.', 'timegrow' ) );
			return;
		}

		$emails  = WC()->mailer()->get_emails();
		$invoice = $emails['WC_Email_Customer_Invoice'] ?? null;
		if ( ! $invoice ) {
			$order->add_order_note( __( 'Not sent: WooCommerce\'s customer invoice email is not available.', 'timegrow' ) );
			return;
		}

		// Attach the PDF invoice to this one email, unless the PDF plugin already attaches it
		$attach = function ( $attachments, $email_id, $object ) use ( $order ) {
			if ( $email_id !== 'customer_invoice' || ! ( $object instanceof WC_Order ) || $object->get_id() !== $order->get_id() ) {
				return $attachments;
			}
			foreach ( (array) $attachments as $file ) {
				if ( stripos( basename( (string) $file ), 'invoice' ) !== false ) return $attachments;
			}
			if ( function_exists( 'wcpdf_get_document' ) && function_exists( 'wcpdf_get_document_file' ) ) {
				$document = wcpdf_get_document( 'invoice', $order, true );
				$file     = $document ? wcpdf_get_document_file( $document, 'pdf', 'log' ) : '';
				if ( is_string( $file ) && $file !== '' && file_exists( $file ) ) {
					$attachments[] = $file;
				}
			}
			return $attachments;
		};

		add_filter( 'woocommerce_email_attachments', $attach, 99, 3 );
		$invoice->trigger( $order->get_id(), $order );
		remove_filter( 'woocommerce_email_attachments', $attach, 99 );

		self::set_status_quietly( $order, 'invoice_sent', sprintf( __( 'Invoice emailed to %s with the PDF attached (client\'s invoice delivery choice).', 'timegrow' ), $email_address ) );
	}

	/**
	 * Move an order to a status without running that status's hooks (no second "invoice sent" email).
	 */
	private static function set_status_quietly( $order, $status, $note ) {
		$order->set_status( $status );
		$order->get_data_store()->update( $order );
		$order->add_order_note( $note );
	}

	// ------------------------------------------------------------------
	// To Be Mailed status
	// ------------------------------------------------------------------

	public static function register_status() {
		register_post_status( 'wc-' . self::MAILED_STATUS, [
			'label'                     => _x( 'To Be Mailed', 'Order status', 'timegrow' ),
			'public'                    => true,
			'exclude_from_search'       => false,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'To Be Mailed (%s)', 'To Be Mailed (%s)', 'timegrow' ),
		] );
	}

	public static function add_status( $statuses ) {
		$statuses[ 'wc-' . self::MAILED_STATUS ] = _x( 'To Be Mailed', 'Order status', 'timegrow' );
		return $statuses;
	}

	public static function allow_pdf( $allowed, $document ) {
		if ( isset( $document->type ) && $document->type === 'invoice' && $document->order && $document->order->get_status() === self::MAILED_STATUS ) {
			return true;
		}
		return $allowed;
	}

	public static function status_color() {
		echo '<style>.order-status.status-' . esc_attr( self::MAILED_STATUS ) . ' { background: #c9a227; color: #fff; }</style>';
	}

	// ------------------------------------------------------------------
	// Print & Mail page
	// ------------------------------------------------------------------

	public static function register_page() {
		add_submenu_page( null, __( 'Print & Mail', 'timegrow' ), __( 'Print & Mail', 'timegrow' ), 'access_nexus_process_time', self::PAGE_SLUG, [ __CLASS__, 'render_page' ] );
	}

	public static function waiting_orders() {
		return wc_get_orders( [ 'status' => [ self::MAILED_STATUS ], 'limit' => -1, 'orderby' => 'date', 'order' => 'ASC' ] );
	}

	public static function pdf_link( $order ) {
		if ( function_exists( 'WPO_WCPDF' ) && isset( WPO_WCPDF()->endpoint ) ) {
			return WPO_WCPDF()->endpoint->get_document_link( $order, 'invoice' );
		}
		return '';
	}

	public static function render_page() {
		$orders    = self::waiting_orders();
		$dashboard = admin_url( 'admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus' );
		?>
		<div class="wrap timegrow-page">
			<div class="timegrow-modern-header">
				<div class="timegrow-header-content">
					<h1><?php esc_html_e( 'Print & Mail', 'timegrow' ); ?></h1>
					<p class="subtitle"><?php esc_html_e( 'Invoices for clients who get them by post', 'timegrow' ); ?></p>
				</div>
				<div class="timegrow-header-illustration">
					<span class="dashicons dashicons-email-alt"></span>
				</div>
			</div>

			<div class="timegrow-card">
				<div class="timegrow-card-header">
					<div class="timegrow-icon timegrow-icon-primary">
						<span class="dashicons dashicons-printer"></span>
					</div>
					<div class="timegrow-card-title">
						<h2><?php echo esc_html( sprintf( _n( '%d invoice to mail', '%d invoices to mail', count( $orders ), 'timegrow' ), count( $orders ) ) ); ?></h2>
					</div>
				</div>
				<div class="timegrow-card-body">
					<div class="timegrow-info-box">
						<span class="dashicons dashicons-info"></span>
						<div>
							<strong><?php esc_html_e( 'How it works:', 'timegrow' ); ?></strong>
							<ul>
								<li><?php esc_html_e( 'Orders land here when you use "Send invoice" for a client set to Print and mail', 'timegrow' ); ?></li>
								<li><?php esc_html_e( 'Open the PDF, print it, and post it to the address shown', 'timegrow' ); ?></li>
								<li><?php esc_html_e( 'Click "Mark as mailed": the order moves to Invoice Sent to Client (no email is sent)', 'timegrow' ); ?></li>
							</ul>
						</div>
					</div>

					<?php if ( empty( $orders ) ) : ?>
						<p><?php esc_html_e( 'Nothing to mail right now.', 'timegrow' ); ?></p>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped">
							<thead>
								<tr>
									<th style="width: 90px;"><?php esc_html_e( 'Order', 'timegrow' ); ?></th>
									<th><?php esc_html_e( 'Mail to', 'timegrow' ); ?></th>
									<th style="width: 110px; text-align: right;"><?php esc_html_e( 'Total', 'timegrow' ); ?></th>
									<th style="width: 110px;"><?php esc_html_e( 'Invoice', 'timegrow' ); ?></th>
									<th style="width: 150px;"></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $orders as $order ) : $pdf = self::pdf_link( $order ); ?>
									<tr>
										<td><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a></td>
										<td><?php echo wp_kses_post( $order->get_formatted_billing_address() ?: __( 'No billing address on the order', 'timegrow' ) ); ?></td>
										<td style="text-align: right;"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
										<td>
											<?php if ( $pdf ) : ?>
												<a class="button" href="<?php echo esc_url( $pdf ); ?>" target="_blank"><?php esc_html_e( 'Open PDF', 'timegrow' ); ?></a>
											<?php else : ?>
												<?php esc_html_e( 'PDF plugin needed', 'timegrow' ); ?>
											<?php endif; ?>
										</td>
										<td>
											<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
												<input type="hidden" name="action" value="timegrow_mark_mailed">
												<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">
												<?php wp_nonce_field( 'timegrow_mark_mailed_' . $order->get_id() ); ?>
												<button type="submit" class="button button-primary"><?php esc_html_e( 'Mark as mailed', 'timegrow' ); ?></button>
											</form>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>

					<p style="margin-top: 16px;">
						<a href="<?php echo esc_url( $dashboard ); ?>" class="button button-secondary">
							<span class="dashicons dashicons-arrow-left-alt" style="vertical-align: middle;"></span>
							<?php esc_html_e( 'Back to Dashboard', 'timegrow' ); ?>
						</a>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	public static function handle_mark_mailed() {
		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		check_admin_referer( 'timegrow_mark_mailed_' . $order_id );
		if ( ! current_user_can( 'access_nexus_process_time' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'timegrow' ), 403 );
		}

		$order = wc_get_order( $order_id );
		if ( $order && $order->get_status() === self::MAILED_STATUS ) {
			self::set_status_quietly( $order, 'invoice_sent', __( 'Invoice printed and mailed.', 'timegrow' ) );
			set_transient( 'timegrow_print_mail_notice_' . get_current_user_id(), sprintf( __( 'Order #%s marked as mailed.', 'timegrow' ), $order->get_order_number() ), 60 );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	public static function show_notice() {
		$key     = 'timegrow_print_mail_notice_' . get_current_user_id();
		$message = get_transient( $key );
		if ( ! $message ) return;
		delete_transient( $key );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}
}
