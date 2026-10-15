<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<?php
// The PDF plugin passes the order in, so this works for downloads, bulk downloads and email attachments
$manual_payment_data = [
	'payment_date'   => $this->order->get_meta( '_payment_date' ),
	'payment_amount' => $this->order->get_meta( '_payment_amount' ),
	'payment_type'   => $this->order->get_meta( '_payment_type' ),
];
?>

	<?php do_action( 'wpo_wcpdf_before_document', $this->get_type(), $this->order ); ?>

	<table class="head container">
		<tr>
			<td class="header">
			<?php
				if ( $this->has_header_logo() ) {
					do_action( 'wpo_wcpdf_before_shop_logo', $this->get_type(), $this->order );
					$this->header_logo();
					do_action( 'wpo_wcpdf_after_shop_logo', $this->get_type(), $this->order );
				} else {
					$this->title();
				}
			?>
			</td>
			<td class="shop-info">
				<?php do_action( 'wpo_wcpdf_before_shop_name', $this->get_type(), $this->order ); ?>
				<div class="shop-name"><h3><?php $this->shop_name(); ?></h3></div>
				<?php do_action( 'wpo_wcpdf_after_shop_name', $this->get_type(), $this->order ); ?>
				<?php do_action( 'wpo_wcpdf_before_shop_address', $this->get_type(), $this->order ); ?>
				<div class="shop-address"><?php $this->shop_address(); ?></div>
				<?php do_action( 'wpo_wcpdf_after_shop_address', $this->get_type(), $this->order ); ?>
			</td>
		</tr>
	</table>

	<?php do_action( 'wpo_wcpdf_before_document_label', $this->get_type(), $this->order ); ?>

	<?php if ( $this->has_header_logo() ) : ?>
		<h1 class="document-type-label"><?php $this->title(); ?></h1>
	<?php endif; ?>

	<?php do_action( 'wpo_wcpdf_after_document_label', $this->get_type(), $this->order ); ?>

	<table class="order-data-addresses">
		<tr>
			<td class="address billing-address">
				<?php do_action( 'wpo_wcpdf_before_billing_address', $this->get_type(), $this->order ); ?>
				<p><?php $this->billing_address(); ?></p>
				<?php do_action( 'wpo_wcpdf_after_billing_address', $this->get_type(), $this->order ); ?>
				<?php if ( isset( $this->settings['display_email'] ) ) : ?>
					<div class="billing-email"><?php $this->billing_email(); ?></div>
				<?php endif; ?>
				<?php if ( isset( $this->settings['display_phone'] ) ) : ?>
					<div class="billing-phone"><?php $this->billing_phone(); ?></div>
				<?php endif; ?>
			</td>
			<td class="address shipping-address">
				<?php if ( $this->show_shipping_address() ) : ?>
					<h3><?php $this->shipping_address_title(); ?></h3>
					<?php do_action( 'wpo_wcpdf_before_shipping_address', $this->get_type(), $this->order ); ?>
					<p><?php $this->shipping_address(); ?></p>
					<?php do_action( 'wpo_wcpdf_after_shipping_address', $this->get_type(), $this->order ); ?>
					<?php if ( isset( $this->settings['display_phone'] ) ) : ?>
						<div class="shipping-phone"><?php $this->shipping_phone(); ?></div>
					<?php endif; ?>
				<?php endif; ?>
			</td>
			<td class="order-data">
				<table>
					<?php do_action( 'wpo_wcpdf_before_order_data', $this->get_type(), $this->order ); ?>
					<tr>
						<td><strong><?php _e('Invoice Status:', 'woocommerce-pdf-invoices'); ?></strong></td>
						<td class="invoice_status"><?php echo esc_html($this->order->get_status()); ?></td>
					</tr>
					<?php if ( isset( $this->settings['display_number'] ) ) : ?>
						<tr class="invoice-number">
							<th><?php $this->number_title(); ?></th>
							<td><?php $this->number( $this->get_type() ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( isset( $this->settings['display_date'] ) ) : ?>
						<tr class="invoice-date">
							<th><?php $this->date_title(); ?></th>
							<td><?php $this->date( $this->get_type() ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( $this->get_payment_method() ) : ?>
						<tr class="payment-method">
							<th><?php $this->payment_method_title(); ?></th>
							<td><?php $this->payment_method(); ?></td>
						</tr>
					<?php endif; ?>
					<?php do_action( 'wpo_wcpdf_after_order_data', $this->get_type(), $this->order ); ?>
				</table>
			</td>
		</tr>
	</table>

	<?php do_action( 'wpo_wcpdf_before_order_details', $this->get_type(), $this->order ); ?>

	<table class="order-details">
		<?php 
			// Hours invoices are flagged by the TimeGrow order creator; everything else is a regular invoice
			$is_hours_invoice = class_exists( 'Aragrow_WOO_Integration_Plugins' ) && Aragrow_WOO_Integration_Plugins::is_hours_invoice( $order );
			if ( $is_hours_invoice )
				$headers = apply_filters('wpo_wcpdf_get_aragrow_time_invoice_template_table_headers', [
					'product' => __('Project', 'woocommerce'), 
					'quantity' => __('Hours', 'woocommerce'), 
					'price' => __('Rate', 'woocommerce'), 
					'total' => __('Total', 'woocommerce')
				], $this);
			else
				$headers = apply_filters('wpo_wcpdf_get_aragrow_invoice_template_table_headers', [
					'product'  => __('Product', 'woocommerce'),
					'quantity' => __('Quantity', 'woocommerce'),
					'price'    => __('Price', 'woocommerce'),
					'total'    => __('Total', 'woocommerce'),
				], $this);
		?>
		<thead>
			<tr>
				<?php
					
					foreach ( $headers as $column_class => $column_title ) {
						printf( '<th class="%s">%s</th>', esc_attr( $column_class ), esc_html( $column_title ) );
					}
				?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $this->get_order_items() as $item_id => $item ) : ?>
				<?php
					// Hours lines: hours and total / hours. Regular lines: quantity and unit price.
					$line_values = Aragrow_WOO_Integration_Plugins::invoice_line_values( $order, $order->get_item( $item_id ), $item['quantity'] );
					$qty_display = $line_values['qty_display'];
					$unit_price  = $line_values['unit_price'];
				?>
				<tr class="<?php echo esc_html( $item['row_class'] ); ?>">
					<td class="product">
						<p class="item-name"><?php echo esc_html( $item['name'] ); ?></p>
						<?php do_action( 'wpo_wcpdf_before_item_meta', $this->get_type(), $item, $this->order ); ?>
						<div class="item-meta">
							<?php if ( ! empty( $item['sku'] ) ) : ?>
								<p class="sku"><span class="label"><?php $this->sku_title(); ?></span> <?php echo esc_attr( $item['sku'] ); ?></p>
							<?php endif; ?>
							<?php if ( ! empty( $item['weight'] ) ) : ?>
								<p class="weight"><span class="label"><?php $this->weight_title(); ?></span> <?php echo esc_attr( $item['weight'] ); ?><?php echo esc_attr( get_option( 'woocommerce_weight_unit' ) ); ?></p>
							<?php endif; ?>
							<!-- ul.wc-item-meta -->
							<?php
								$work_done = wc_get_order_item_meta($item_id, '_work_done', true);
								if ( ! empty( $work_done ) ) : ?>
									Description: <?php echo wp_kses_post( $work_done ); ?>
								<?php endif; ?>
							<!-- / ul.wc-item-meta -->
						</div>
						<?php do_action( 'wpo_wcpdf_after_item_meta', $this->get_type(), $item, $this->order ); ?>
					</td>
					<td class="quantity"><?php echo esc_html( $qty_display ); ?></td>
					<td class="price"><?php echo wp_kses_post( wc_price( $unit_price, [ 'currency' => $order->get_currency() ] ) ); ?></td>
					<td class="price"><?php echo esc_html( $item['order_price'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<table class="notes-totals">
		<tbody>
			<tr class="no-borders">
				<td class="no-borders notes-cell">
					<?php do_action( 'wpo_wcpdf_before_document_notes', $this->get_type(), $this->order ); ?>
					<?php if ( $this->get_document_notes() ) : ?>
						<div class="document-notes">
							<h3><?php $this->notes_title(); ?></h3>
							<?php $this->document_notes(); ?>
						</div>
					<?php endif; ?>
					<?php do_action( 'wpo_wcpdf_after_document_notes', $this->get_type(), $this->order ); ?>
					<?php do_action( 'wpo_wcpdf_before_customer_notes', $this->get_type(), $this->order ); ?>
					<?php if ( $this->get_shipping_notes() ) : ?>
						<div class="customer-notes">
							<h3><?php $this->customer_notes_title(); ?></h3>
							<?php $this->shipping_notes(); ?>
						</div>
					<?php endif; ?>
					<?php do_action( 'wpo_wcpdf_after_customer_notes', $this->get_type(), $this->order ); ?>
				</td>
				<td class="no-borders totals-cell">
					<table class="totals">
						<tfoot>
							<?php foreach ( $this->get_woocommerce_totals() as $key => $total ) : ?>
								<tr class="<?php echo esc_attr( $key ); ?>">
									<th class="description"><?php echo esc_html( $total['label'] ); ?></th>
									<td class="price"><span class="totals-price"><?php echo esc_html( $total['value'] ); ?></span></td>
								</tr>
							<?php endforeach; ?>
						</tfoot>
					</table>
				</td>
			</tr>
		</tbody>
	</table>
	<?php if( $this->order->get_status() == "invoice_paid" &&
			isset($manual_payment_data) && 
			is_array($manual_payment_data) &&
			!empty($manual_payment_data['payment_date'])) { ?>
		<div class="invoice-payment-stamp">
			<p><strong>INVOICE PAID</strong></p>
			<br />Payment Date: <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $manual_payment_data['payment_date'] ) ) ); ?><br>
			<br />Amount: $<?php echo esc_html($manual_payment_data['payment_amount']); ?><br>
			<br />Type: <?php echo esc_html($manual_payment_data['payment_type']); ?>
		</div>
	<?php } ?>
	<?php do_action( 'wpo_wcpdf_after_order_details', $this->get_type(), $this->order ); ?>

	<div class="bottom-spacer"></div>

	<?php if ( $this->get_footer() ) : ?>
		<htmlpagefooter name="docFooter"><!-- required for mPDF engine -->
			<div id="footer">
				<!-- hook available: wpo_wcpdf_before_footer -->
				<?php $this->footer(); ?>
				<!-- hook available: wpo_wcpdf_after_footer -->
			</div>
		</htmlpagefooter><!-- required for mPDF engine -->
	<?php endif; ?>

	<?php do_action( 'wpo_wcpdf_after_document', $this->get_type(), $this->order ); ?>
