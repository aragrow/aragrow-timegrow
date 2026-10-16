<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowClientView {

    /**
     * @param array $clients     Rows with id, name, company, email, phone, edit_url.
     * @param bool  $woocommerce True when the list comes from WooCommerce customers.
     */
    public function display($clients, $woocommerce) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        ?>
        <div class="wrap timegrow-page">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Clients', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Manage your client information and relationships', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-groups"></span>
                </div>
            </div>

            <?php if ($woocommerce) : ?>
                <div class="timegrow-notice timegrow-notice-info">
                    <span class="dashicons dashicons-info"></span>
                    <div>
                        <strong><?php esc_html_e('WooCommerce Integration Active', 'timegrow'); ?></strong>
                        <p><?php esc_html_e('Clients are your WooCommerce Customers.', 'timegrow'); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="tablenav top">
                <div class="alignleft actions">
                    <a href="<?php echo esc_url(admin_url('user-new.php?customer=y')); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary"><?php esc_html_e('Add New Client', 'timegrow'); ?></a>
                </div>
                <br class="clear">
            </div>

            <table class="wp-list-table widefat fixed striped table-view-list clients">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-name column-primary"><?php esc_html_e('Name', 'timegrow'); ?></th>
                        <th scope="col" class="manage-column column-company"><?php esc_html_e('Company', 'timegrow'); ?></th>
                        <th scope="col" class="manage-column column-email"><?php esc_html_e('Email', 'timegrow'); ?></th>
                        <th scope="col" class="manage-column column-phone"><?php esc_html_e('Phone', 'timegrow'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($clients)) : ?>
                        <?php foreach ($clients as $client) : ?>
                            <tr>
                                <td class="column-name column-primary" data-colname="Name">
                                    <strong><?php echo esc_html($client['name']); ?></strong>
                                    <?php if ($client['edit_url']) : ?>
                                        <div class="row-actions">
                                            <span class="edit">
                                                <a href="<?php echo esc_url($client['edit_url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Edit Client', 'timegrow'); ?>"><?php esc_html_e('Edit', 'timegrow'); ?></a>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="column-company" data-colname="Company"><?php echo esc_html($client['company']); ?></td>
                                <td class="column-email" data-colname="Email"><?php echo esc_html($client['email']); ?></td>
                                <td class="column-phone" data-colname="Phone"><?php echo esc_html($client['phone']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e('No clients found.', 'timegrow'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
