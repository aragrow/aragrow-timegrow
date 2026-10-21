<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Sync Payments: shows the open PayPal invoices and runs the PayPal payment sync on demand.
 * The sync itself lives in Aragrow_TimeGrow_PayPal_Payment_Sync (PayPal module); this page
 * only shows its status and results.
 */
class TimeGrowNexusSyncPaymentsView {

    /**
     * @param array|null $summary Setup summary from status_summary(), null when PayPal is not available.
     * @param array      $orders  Open PayPal orders.
     * @param array|null $results Results of a sync just run, null when none ran.
     */
    public function display($summary, $orders, $results = null) {
        $settings_url  = admin_url('options-general.php?page=paypal-integration');
        $dashboard_url = admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus');
        $statuses      = wc_get_order_statuses();
        ?>
        <div class="wrap timegrow-page">
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Sync Payments', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Check PayPal for paid invoices and update the matching orders', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-update"></span>
                </div>
            </div>

            <?php if ($summary === null) : ?>
                <div class="notice notice-warning"><p>
                    <?php esc_html_e('PayPal invoicing is not available. It needs WooCommerce and the PayPal module.', 'timegrow'); ?>
                </p></div>
                <?php return; ?>
            <?php endif; ?>

            <?php if ($results !== null) : $this->render_results($results, $statuses); endif; ?>

            <div class="timegrow-card">
                <div class="timegrow-card-header">
                    <div class="timegrow-icon timegrow-icon-primary">
                        <span class="dashicons dashicons-update"></span>
                    </div>
                    <div class="timegrow-card-title">
                        <h2><?php esc_html_e('Sync Now', 'timegrow'); ?></h2>
                    </div>
                </div>
                <div class="timegrow-card-body">
                    <?php if (!$summary['credentials']) : ?>
                        <p><?php esc_html_e('Add your PayPal Client ID and Client Secret first.', 'timegrow'); ?>
                            <a href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Open PayPal settings', 'timegrow'); ?></a></p>
                    <?php else : ?>
                        <div class="timegrow-info-box">
                            <span class="dashicons dashicons-info"></span>
                            <div>
                                <strong><?php esc_html_e('How it works:', 'timegrow'); ?></strong>
                                <ul>
                                    <li><?php esc_html_e('Checks every order in Invoice Sent to PayPal, Invoice Draft in PayPal or Partial Paid (50 at a time, the ones checked longest ago first, so each run moves on to the next)', 'timegrow'); ?></li>
                                    <li><?php esc_html_e('Paid invoices move to Invoice Paid, with the payment date, amount and PayPal transaction number recorded', 'timegrow'); ?></li>
                                    <li><?php esc_html_e('Partly paid invoices move to Partial Paid; cancelled or refunded ones get an order note', 'timegrow'); ?></li>
                                    <li><?php esc_html_e('Running it again never records the same payment twice', 'timegrow'); ?></li>
                                </ul>
                            </div>
                        </div>
                        <form method="post" action="">
                            <?php wp_nonce_field('timegrow_sync_payments', 'timegrow_sync_payments_nonce'); ?>
                            <div class="timegrow-form-actions">
                                <button type="submit" name="timegrow_sync_payments" value="1" class="button button-primary button-large" <?php disabled(empty($orders)); ?>>
                                    <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                                    <?php $batch = min(count($orders), Aragrow_TimeGrow_PayPal_Payment_Sync::BATCH_SIZE); // one run checks a batch
                                    echo esc_html(sprintf(_n('Sync %d Order', 'Sync %d Orders', $batch, 'timegrow'), $batch)); ?>
                                </button>
                                <a href="<?php echo esc_url($dashboard_url); ?>" class="button button-secondary button-large">
                                    <span class="dashicons dashicons-arrow-left-alt" style="vertical-align: middle;"></span>
                                    <?php esc_html_e('Back to Dashboard', 'timegrow'); ?>
                                </a>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="timegrow-card">
                <div class="timegrow-card-header">
                    <div class="timegrow-icon timegrow-icon-primary">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="timegrow-card-title">
                        <h2><?php esc_html_e('Waiting for Payment', 'timegrow'); ?></h2>
                    </div>
                </div>
                <div class="timegrow-card-body">
                    <?php if (empty($orders)) : ?>
                        <p><?php esc_html_e('No PayPal invoices are waiting for payment.', 'timegrow'); ?></p>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Order', 'timegrow'); ?></th>
                                    <th><?php esc_html_e('Client', 'timegrow'); ?></th>
                                    <th style="text-align: right;"><?php esc_html_e('Total', 'timegrow'); ?></th>
                                    <th><?php esc_html_e('Order status', 'timegrow'); ?></th>
                                    <th><?php esc_html_e('PayPal invoice', 'timegrow'); ?></th>
                                    <th><?php esc_html_e('Last PayPal status', 'timegrow'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order) : ?>
                                    <tr>
                                        <td><a href="<?php echo esc_url($order->get_edit_order_url()); ?>">#<?php echo esc_html($order->get_order_number()); ?></a></td>
                                        <td><?php echo esc_html($order->get_formatted_billing_full_name() ?: $order->get_billing_email()); ?></td>
                                        <td style="text-align: right;"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
                                        <td><?php echo esc_html($statuses['wc-' . $order->get_status()] ?? $order->get_status()); ?></td>
                                        <td><?php echo esc_html(Aragrow_TimeGrow_PayPal_Payment_Sync::invoice_id_for($order)); ?></td>
                                        <td>
                                            <?php echo esc_html($order->get_meta('_paypal_invoice_status') ?: __('Not checked yet', 'timegrow')); ?>
                                            <?php if ($order->get_meta('_paypal_last_checked')) : ?>
                                                <br><span class="description"><?php echo esc_html($order->get_meta('_paypal_last_checked')); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="timegrow-card">
                <div class="timegrow-card-header">
                    <div class="timegrow-icon timegrow-icon-primary">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="timegrow-card-title">
                        <h2><?php esc_html_e('Automatic Sync', 'timegrow'); ?></h2>
                    </div>
                </div>
                <div class="timegrow-card-body">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('PayPal', 'timegrow'); ?></th>
                            <td><?php echo esc_html($summary['environment']); ?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Scheduled check', 'timegrow'); ?></th>
                            <td>
                                <?php echo esc_html($summary['interval']); ?>
                                <?php if ($summary['next_run']) : ?>
                                    <span class="description"><?php echo esc_html(sprintf(__('(next run %s)', 'timegrow'), $summary['next_run'])); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Instant updates (webhook)', 'timegrow'); ?></th>
                            <td><?php echo $summary['webhook'] ? esc_html__('Set up', 'timegrow') : esc_html__('Not set up', 'timegrow'); ?></td>
                        </tr>
                    </table>
                    <p><a href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Change these in PayPal settings', 'timegrow'); ?></a></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Notice listing what happened to each order in the sync just run.
     */
    private function render_results(array $results, array $statuses) {
        $errors  = count(array_filter($results, function ($r) { return $r['error']; }));
        $updated = count(array_filter($results, function ($r) { return !$r['error'] && $r['after'] !== $r['before']; }));
        $class   = $errors ? 'notice-warning' : 'notice-success';
        ?>
        <div class="notice <?php echo esc_attr($class); ?>" style="padding: 15px;">
            <h3 style="margin-top: 0;">
                <?php
                if (empty($results)) {
                    esc_html_e('No PayPal invoices were waiting for payment.', 'timegrow');
                } else {
                    echo esc_html(sprintf(__('Checked %1$d orders: %2$d updated, %3$d with errors.', 'timegrow'), count($results), $updated, $errors));
                }
                ?>
            </h3>
            <?php foreach ($results as $row) : $order = $row['order']; ?>
                <p>
                    <strong><a href="<?php echo esc_url($order->get_edit_order_url()); ?>">#<?php echo esc_html($order->get_order_number()); ?></a></strong>
                    <?php if ($row['after'] !== $row['before']) : ?>
                        <?php echo ': '; ?><?php echo esc_html(($statuses['wc-' . $row['before']] ?? $row['before']) . ' to ' . ($statuses['wc-' . $row['after']] ?? $row['after'])); ?>
                    <?php endif; ?>
                    <br><span style="color: <?php echo $row['error'] ? '#b32d2e' : '#50575e'; ?>;"><?php echo esc_html($row['message']); ?></span>
                </p>
            <?php endforeach; ?>
        </div>
        <?php
    }
}
