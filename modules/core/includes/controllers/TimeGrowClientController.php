<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowClientController{

        
    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);   

    }
    public function display_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        $this->list();
    }

    public function list() {

        $integrations = get_option('timegrow_integration_settings');
        if ($integrations['wc_clients'] && class_exists('WooCommerce')) {
            ?>
            <div class="wrap">
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

                <div class="timegrow-notice timegrow-notice-info">
                    <span class="dashicons dashicons-info"></span>
                    <div>
                        <strong><?php esc_html_e('WooCommerce Integration Active', 'timegrow'); ?></strong>
                        <p><?php esc_html_e('Clients are integrated with WooCommerce Customers.', 'timegrow'); ?></p>
                    </div>
                </div>
            </div>
            <?php
            exit();
        }

    }

}
