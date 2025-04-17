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
            echo '<div class="x-notice info"><h2>Clients are integrated with WooCommerce Customers.</h2></div>';
            exit();
        }

    }

}
