<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowClientController{

        
    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);   

    }

    public function list() {

        $integrations = get_option('timeflies_integration_settings');
        if ($integrations['wc_clients'] && class_exists('WooCommerce')) {
            echo '<div class="x-notice info"><p>Clients are integrated with WooCommerce Customers.</p></div>';
            exit();
        }

    }

}
