<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowCompanyController{

    private $company_model;
    private $company_view;
    private $table_name; 
        
    public function __construct(TimeGrowCompanyModel $company_model,  TimeGrowCompanyView $company_view) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);   
        $this->company_model = $company_model;
        $this->company_view = $company_view;
    }

    public function handle_form_submission() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
     
        if (!isset($_POST['timegrow_company_nonce_field']) || 
            !wp_verify_nonce($_POST['timegrow_company_nonce_field'], 'timegrow_company_nonce')) {
            wp_die(__('Nonce verification failed.', 'text-domain'));
        }
        if (!isset($_POST['company_id'])) return; 

        $current_date = current_time('mysql');
        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'legal_name' => sanitize_text_field($_POST['legal_name']),
            'document_number' => sanitize_text_field($_POST['document_number']),
            'default_flat_fee' => floatval($_POST['default_flat_fee']),
            'contact_person' => sanitize_text_field($_POST['contact_person']),
            'email' => sanitize_email($_POST['email']), 
            'phone' => sanitize_text_field($_POST['phone']),
            'address_1' => wp_kses_post($_POST['address_1']), // Sanitize address (using wp_kses_post for HTML)
            'address_2' => wp_kses_post($_POST['address_2']), // Sanitize address (using wp_kses_post for HTML)
            'city' => sanitize_text_field($_POST['city']),
            'state' => sanitize_text_field($_POST['state']),
            'postal_code' => sanitize_text_field($_POST['postal_code']),
            'country' => sanitize_text_field($_POST['country']),
            'website' => esc_url_raw($_POST['website']), // Sanitize URL
            'notes' => wp_kses_post($_POST['notes']), // Sanitize notes
            'status' => isset($_POST['status']) ? 1 : 0,
            'updated_at' => $current_date
        ];

        $format = [
            '%s',   // name (string)
            '%s',   // legal_name (string)
            '%s',   // document_number (string)
            '%f',   // default_flat_fee (float)
            '%s',   // contact_person (string)
            '%s',   // email (string, sanitized as email)
            '%s',   // phone (string)
            '%s',   // address_1 (string, HTML sanitized)
            '%s',   // address_2 (string, HTML sanitized)
            '%s',   // city (string)
            '%s',   // state (string)
            '%s',   // postal_code (string)
            '%s',   // country (string)
            '%s',   // website (string, sanitized URL)
            '%s',   // notes (string, HTML sanitized)
            '%d',   // status (boolean as integer 1/0)
            '%s'    // updated_at (datetime string)
        ];

        $id = intval($_POST['company_id']);
        if ($id == 0) {
            $data['created_at'] = $current_date;
            $format[] = '%s';
            $id = $this->company_model->create($data, $format);

            if ($id) {
                echo '<div class="notice notice-success is-dismissible"><p>Expense added successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Error adding expense.</p></div>';
            }

        } else {

            $result = $this->company_model->update($id, $data, $format);

            if ($result) {
                echo '<div class="notice notice-success is-dismissible"><p>Expense added successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Error adding expense.</p></div>';
            }

        }

    }

    public function list() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $companies = $this->company_model->select(null); // Fetch all expenses
        $this->company_view->display($companies);
    }

    public function add() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $this->company_view->add();
    }

    public function edit($id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
         $company = $this->company_model->select($id)[0]; // Fetch all expenses
        $this->company_view->edit($company);
    }

    public function display_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        if ($screen != 'list' && ( isset($_POST['add_item']) || isset($_POST['edit_item']) )) {
            $this->handle_form_submission();
            $screen = 'list';
        }
   

        if ($screen == 'list')
            $this->list();
        elseif ($screen == 'add')
            $this->add();
        elseif ($screen == 'edit') {
            $id = 0;
            if ( isset( $_GET['id'] ) ) {
                $id = intval( $_GET['id'] ); // Sanitize the ID as an integer
            } else {
                wp_die( 'Error: Company ID not provided in the URL.', 'Missing Company ID', array( 'back_link' => true ) );
            }
            $this->edit($id);
        }
    }

}
