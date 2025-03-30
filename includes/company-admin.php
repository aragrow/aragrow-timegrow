<?php
// includes/companies.php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Timeflies_Companies_Admin {
    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('wp_ajax_save_company', array($this, 'save_ajax'));
        add_action('wp_ajax_delete_company', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles'));
    }

    public function enqueue_scripts_styles() {
        wp_enqueue_style('timeflies-companies-style', ARAGROW_TIMEGROW_BASE_URI . 'assets/css/company.css');
        wp_enqueue_script('timeflies-companies-script', ARAGROW_TIMEGROW_BASE_URI . 'assets/js/company.js', array('jquery'), '1.0', true);
        wp_localize_script(
            'timeflies-companies-script',
            'timeflies_company_list',
            [
                'list_url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-companies-list'),
                'nonce' => wp_create_nonce('timeflies_company_nonce') // Pass the nonce to JS
            ]
        );
    }

    public function save_ajax() {
        try {

            if (WP_DEBUG) error_log('Exc: Timeflies_Companies_Admin.save_ajax()');

            check_ajax_referer('timeflies_company_nonce', 'timeflies_company_nonce_field');
        
            if (WP_DEBUG) error_log('--> Validation Passed!!');

            $company_id = intval($_POST['company_id']);
            $name = sanitize_text_field($_POST['name']);
            $legal_name = sanitize_text_field($_POST['legal_name']);
            $document_number = sanitize_text_field($_POST['document_number']);
            $default_flat_fee = floatval($_POST['default_flat_fee']);
            $contact_person = sanitize_text_field($_POST['contact_person']);
            $email = sanitize_email($_POST['email']); // Sanitize email
            $phone = sanitize_text_field($_POST['phone']);
            $address_1 = wp_kses_post($_POST['address_1']); // Sanitize address (using wp_kses_post for HTML)
            $address_2 = wp_kses_post($_POST['address_2']); // Sanitize address (using wp_kses_post for HTML)
            $city = sanitize_text_field($_POST['city']);
            $state = sanitize_text_field($_POST['state']);
            $postal_code = sanitize_text_field($_POST['postal_code']);
            $country = sanitize_text_field($_POST['country']);
            $website = esc_url_raw($_POST['website']); // Sanitize URL
            $notes = wp_kses_post($_POST['notes']); // Sanitize notes
            $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        
            $company_data = array(
                'name' => $name,
                'legal_name' => $legal_name,
                'document_number' => $document_number,
                'default_flat_fee' => $default_flat_fee,
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
                'notes' => $notes,
                'is_active' => $is_active
            );
        
            global $wpdb;
            $prefix = $wpdb->prefix;

            if ($company_id === 0) {
                $wpdb->insert("{$prefix}timeflies_companies", $company_data, array(
                    '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d'
                ));
        
                $company_id = $wpdb->insert_id;
            } else {
                $wpdb->update("{$prefix}timeflies_companies", $company_data, array('ID' => $company_id), array(
                    '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d'
                ));
            }
        
            wp_send_json_success(array('message' => 'Company saved.', 'company_id' => $company_id));

        } catch (Exception $e) {

            if(WP_DEBUG) error_log('Timeflies_Clients_Admin.save_ajax() -> '.$e->getMessage());

            wp_send_json_success(array('message' => $e->getMessage(), 'company_id' => $company_id));
            
        } finally {

            if(WP_DEBUG) error_log('Timeflies_Companies_Admin.save_ajax() -> Finalized');
            // Optional block of code that always executes
        }

    }

}

Timeflies_Companies_Admin::get_instance();