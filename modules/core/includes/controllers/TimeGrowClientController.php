<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowClientController{


    public function __construct() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

    }
    public function display_admin_page($screen) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        $this->list();
    }

    /**
     * Clients list: WooCommerce customers when the clients integration is on,
     * otherwise the clients TimeGrow already uses (TimeGrowClientModel).
     */
    public function list() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        $integrations = get_option('timegrow_integration_settings', []);
        $woocommerce  = !empty($integrations['wc_clients']) && class_exists('WooCommerce');

        $users = $woocommerce
            ? get_users(['role' => 'customer', 'orderby' => 'display_name', 'order' => 'ASC'])
            : (new TimeGrowClientModel())->select();

        $clients = [];
        foreach ((array) $users as $user) {
            $id        = (int) $user->ID;
            $clients[] = [
                'id'       => $id,
                'name'     => $user->display_name,
                'company'  => get_user_meta($id, 'billing_company', true),
                'email'    => get_user_meta($id, 'billing_email', true) ?: $user->user_email,
                'phone'    => get_user_meta($id, 'billing_phone', true),
                'edit_url' => admin_url('user-edit.php?user_id=' . $id),
            ];
        }

        (new TimeGrowClientView())->display($clients, $woocommerce);
    }

}
