<?php
// Exit if accessed directly

use phpseclib3\File\ASN1\Maps\Time;

if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowProjectController{

    private $model;
    private $view;
    private $model_client;
    private $model_product;

    public function __construct(TimeGrowProjectModel $model, TimeGrowProjectView $view, TimeGrowClientModel $model_client, TimeGrowWcProductModel $model_product) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);   
        $this->model = $model;
        $this->view = $view;
        $this->model_client = $model_client;
        $this->model_product = $model_product;
    }

    public function handle_form_submission() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        
        if (!isset($_POST['project_id'])) return; 

        $current_date = current_time('mysql');
        $data = [
            'project_id' => intval($_POST['project_id']),
            'client_id' => intval($_POST['client_id']),
            'name' => sanitize_text_field($_POST['name']),
            'description' => wp_kses_post($_POST['description']),
            'default_flat_fee' => floatval($_POST['default_flat_fee']),
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date']),
            'estimate_hours' => floatval($_POST['estimate_hours']),
            'billable' => isset($_POST['billable']),
            'status' => isset($_POST['status']),
            'updated_at' => $current_date
        ];

        $format = [
            '%d',   // project_id (integer)
            '%d',   // client_id (integer)
            '%s',   // name (string)
            '%s',   // description (string)
            '%f',   // default_flat_fee (float)
            '%s',   // start date (string)
            '%s',   // end date (string)
            '%f',   // estimate hours (float)
            '%d',   // billable (integer)
            '%d',   // status (int)
            '%s',   // updated at
        ];

        $id = intval($_POST['expense_id']);
        if ($id == 0) {
            $data['created_at'] = $current_date;
            $format[] = '%s';
            $id = $this->model->create($data, $format);

            if ($id) {
                echo '<div class="notice notice-success is-dismissible"><p>Expense added successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Error adding expense.</p></div>';
            }

        } else {

            $result = $this->model->update($id, $data, $format);

            if ($result) {
                echo '<div class="notice notice-success is-dismissible"><p>Expense added successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Error adding expense.</p></div>';
            }

        }

    }

    public function list() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $projects = $this->model->select(null); // Fetch all expenses
        $this->view->display($projects);
    }

    public function add() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $clients = $this->model_client->select(); // Fetch all expenses
        $wc_products = $this->model_product->select(); // Fetch all products
        $this->view->add($clients, $wc_products);
    }

    public function edit($id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $project = $this->model->select($id)[0]; // Fetch all expenses
        $wc_products = $this->model_product->select(); // Fetch all products
        $this->view->edit($project);
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
