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

    public function __construct(TimeGrowProjectModel $model, TimeGrowProjectView $view, TimeGrowClientModel $model_client, TimeGrowWooProductModel $model_product) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);   
        $this->model = $model;
        $this->view = $view;
        $this->model_client = $model_client;
        $this->model_product = $model_product;
        $this->model_product = $model_product;
    }

    public function handle_form_submission() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        if (!isset($_POST['timegrow_project_nonce_field']) || 
            !wp_verify_nonce($_POST['timegrow_project_nonce_field'], 'timegrow_project_nonce')) {
            wp_die(__('Nonce verification failed.', 'text-domain'));
        }

        if (!isset($_POST['project_id'])) return; 

        $current_date = current_time('mysql');
        $data = [
            'client_id' => intval($_POST['client_id']),
            'name' => sanitize_text_field($_POST['name']),
            'description' => wp_kses_post($_POST['description']),
            'default_flat_fee' => floatval($_POST['default_flat_fee']),
            'start_date' => sanitize_text_field($_POST['start_date']),
            'end_date' => sanitize_text_field($_POST['end_date']),
            'estimate_hours' => floatval($_POST['estimate_hours']),
            'billable' => isset($_POST['billable'])?:0,
            'status' => isset($_POST['status'])?:0,
            'product_id' => sanitize_text_field($_POST['product_id']),
            'updated_at' => $current_date
            
        ];

        $format = [
            '%d',   // client_id (integer)
            '%s',   // name (string)
            '%s',   // description (string)
            '%f',   // default_flat_fee (float)
            '%s',   // start date (string)
            '%s',   // end date (string)
            '%d',   // estimate hours
            '%d',   // billable (integer)
            '%d',   // status (int)
            '%d',   // status (int)
            '%s',   // updated at
        ];

        $id = intval($_POST['project_id']);
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

        // Get all projects first for filter options
        $all_projects = $this->model->select(null);

        // Build WHERE clause for filtering
        global $wpdb;
        $where_conditions = [];
        $filter_client = isset($_GET['filter_client']) ? sanitize_text_field($_GET['filter_client']) : '';
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
        $filter_billable = isset($_GET['filter_billable']) ? sanitize_text_field($_GET['filter_billable']) : '';
        $filter_search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        if (!empty($filter_search)) {
            $search_term = '%' . $wpdb->esc_like($filter_search) . '%';
            $where_conditions[] = $wpdb->prepare(
                "(p.name LIKE %s OR p.description LIKE %s OR u.display_name LIKE %s)",
                $search_term, $search_term, $search_term
            );
        }
        if (!empty($filter_client)) {
            $where_conditions[] = $wpdb->prepare("p.client_id = %d", intval($filter_client));
        }
        if (!empty($filter_status)) {
            $where_conditions[] = $wpdb->prepare("p.status = %d", intval($filter_status));
        }
        if (!empty($filter_billable)) {
            $where_conditions[] = $wpdb->prepare("p.billable = %d", intval($filter_billable));
        }

        // Get orderby and order parameters
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'name';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC';

        // Validate orderby column
        $allowed_orderby = ['name', 'client_name', 'start_date', 'end_date', 'status', 'billable'];
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'name';
        }

        // Validate order direction
        $order = strtoupper($order);
        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'ASC';
        }

        // Map orderby to actual column names
        $orderby_column = $orderby;
        if ($orderby == 'name' || $orderby == 'start_date' || $orderby == 'end_date' || $orderby == 'status' || $orderby == 'billable') {
            $orderby_column = 'p.' . $orderby;
        } elseif ($orderby == 'client_name') {
            $orderby_column = 'u.display_name';
        }

        // Fetch projects with filtering and ordering
        $table_name = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $user_table = $wpdb->prefix . 'users';
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $query = "SELECT p.*, u.display_name as client_name
                  FROM {$table_name} p
                  LEFT JOIN {$user_table} u ON p.client_id = u.ID
                  {$where_clause}
                  ORDER BY {$orderby_column} {$order}";
        $projects = $wpdb->get_results($query);

        // Get unique values for filters
        $filter_options = ['clients' => []];

        // Get clients for filter
        $clients = $this->model_client->select();
        foreach ($clients as $client) {
            $filter_options['clients'][$client->ID] = $client->company_name;
        }

        $this->view->display($projects, $filter_options, [
            'orderby' => $orderby,
            'order' => $order,
            'filter_client' => $filter_client,
            'filter_status' => $filter_status,
            'filter_billable' => $filter_billable,
            'filter_search' => $filter_search
        ]);
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
        $clients = $this->model_client->select(); // Fetch all expenses
        $wc_products = $this->model_product->select(); // Fetch all products
        $this->view->edit($project, $clients, $wc_products);
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
