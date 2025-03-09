<?php
// includes/companies.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Timeflies_Clients_Admin {
    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('wp_ajax_save_client', array($this, 'save_ajax'));
        add_action('wp_ajax_delete_client', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles'));
    }

    public function enqueue_scripts_styles() {
        wp_enqueue_style('timeflies-clients-style', ARAGROW_TIMEFLIES_BASE_URI . 'assets/css/client.css');
        wp_enqueue_script('timeflies-clients-script', ARAGROW_TIMEFLIES_BASE_URI . 'assets/js/client.js', array('jquery'), '1.0', true);
        wp_localize_script(
            'timeflies-clients-script',
            'timeflies_clients_list',
            [
                'list_url' => admin_url('admin.php?page=' . TIMEFLIES_PARENT_MENU . '-clients-list'),
                'nonce' => wp_create_nonce('timeflies_client_nonce') // Pass the nonce to JS
            ]
        );
    }

    public function save_ajax() {

        try {

            if (WP_DEBUG) error_log('Exc: Timeflies_Clients_Admin.save_ajax()');
            check_ajax_referer('timeflies_client_nonce', 'timeflies_client_nonce_field');
        
            if (WP_DEBUG) error_log('--> Validation Passed!!');

            $client_id = intval($_POST['client_id']);
            $company_id = intval($_POST['company_id']);
            $name = sanitize_text_field($_POST['name']);
            $document_number = sanitize_text_field($_POST['document_number']);
            $default_flat_fee = floatval($_POST['default_flat_fee']);
            $currency = sanitize_text_field($_POST['currency']);
            $contact_person = sanitize_text_field($_POST['contact_person']);
            $email = sanitize_email($_POST['email']);
            $phone = sanitize_text_field($_POST['phone']);
            $address_1 = wp_kses_post($_POST['address_1']);
            $address_2 = wp_kses_post($_POST['address_2']);
            $city = sanitize_text_field($_POST['city']);
            $state = sanitize_text_field($_POST['state']);
            $postal_code = sanitize_text_field($_POST['postal_code']);
            $country = sanitize_text_field($_POST['country']);
            $website = esc_url_raw($_POST['website']);
            $status = isset($_POST['status']) ? 1 : 0;

            $current_date = current_time('mysql');

            $client_data = array(
                'company_id' => $company_id,
                'name' => $name,
                'document_number' => $document_number,
                'default_flat_fee' => $default_flat_fee,
                'currency' => $currency,
                'contact_person' => $contact_person,
                'email' => $email,
                'phone' => $phone,
                'address_1' => $address_1,
                'address_2' => $address_2,
                'city' => $city,
                'state' => $state,
                'postal_code' => $postal_code,
                'country' => $country,
                'website' => $website,
                'status' => $status,
                'updated_at' => $current_date, // Add updated_at timestamp
            );


            global $wpdb;
            $prefix = $wpdb->prefix;

            if ($client_id === 0) {
                $client_data['created_at'] = $current_date; // Add created_at timestamp
                $wpdb->insert("{$prefix}timeflies_clients", $client_data, array(
                    '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'
                ));
                $client_id = $wpdb->insert_id;
            } else {
                $wpdb->update("{$prefix}timeflies_clients", $client_data, array('ID' => $client_id), array(
                    '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'
                ));
            }
        
            wp_send_json_success(array('message' => 'Client Saved.', 'client_id' => $client_id));
        
        } catch (Exception $e) {

            if(WP_DEBUG) error_log('Timeflies_Clients_Admin.save_ajax() -> '.$e->getMessage());

            wp_send_json_success(array('message' => $e->getMessage(), 'client_id' => $client_id));
            
        } finally {

            if(WP_DEBUG) error_log('Timeflies_ClientsAdmin.save_ajax() -> Finalized');
            // Optional block of code that always executes
        }

    }

}

Timeflies_Clients_Admin::get_instance();