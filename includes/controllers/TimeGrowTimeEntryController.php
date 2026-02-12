<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowTimeEntryController{

    private $model;
    private $view;
    private $project_model;
    private $member_model;
    private $table_name; 
        
    public function __construct(TimeGrowTimeEntryModel $model,  
                                TimeGrowTimeEntryView $view, 
                                TimeGrowProjectModel $project_model, 
                                TimeGrowTeamMemberModel $member_model) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);   
        $this->model = $model;
        $this->view = $view;
        $this->project_model = $project_model;
        $this->member_model = $member_model;
    }

    public function handle_form_submission() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        if (!isset($_POST['timegrow_time_entry_nonce_field']) || 
            !wp_verify_nonce($_POST['timegrow_time_entry_nonce_field'], 'timegrow_time_entry_nonce')) {
            wp_die(__('Nonce verification failed.', 'text-domain'));
        }
        if (!isset($_POST['time_entry_id'])) return; 

        $current_date = current_time('mysql');
        $data = [
            'project_id'      => intval($_POST['project_id']),
            'member_id'       => intval($_POST['member_id']),
            'billable'        => isset($_POST['billable']) ? 1 : 0,
            'billed'          => isset($_POST['billed']) ? 1 : 0,
            'description'     => sanitize_textarea_field($_POST['description']),
            'entry_type'      => sanitize_text_field($_POST['entry_type']),
            'updated_at'      => current_time('mysql')
        ];
       
        if ($data['entry_type'] == 'MAN') {
            $data['date'] = sanitize_text_field($_POST['date']);
            $data['hours'] = floatval($_POST['hours']);
            $data['clock_in_date'] = null;  
            $data['clock_out_date'] = null;       
        } else {
            $data['date'] = null;
            $data['hours'] = null;
            $data['clock_in_date'] = sanitize_text_field($_POST['clock_in_date']);
            $data['clock_out_date'] = sanitize_text_field($_POST['clock_out_date']);
        }

        $format = [
            '%d',   // project_id (integer)
            '%d',   // member_id (integer)
            '%d',   // billable ( boolean as integer 1/0)
            '%d',   // billed ( boolean as integer 1/0)
            '%s',   // description (string)
            '%s',   // entry_type (string)
            '%s',   // updated_at (datetime string)
            '%s',   // date (string )
            '%f',   // hours (float)
            '%s',   // clock_in_date (string)
            '%s'   // clock_out_date (string)
        ];


        // Check if the ID is set and is a valid integer
        $id = intval($_POST['time_entry_id']);
        if ($id == 0) {
            $data['created_at'] = $current_date;
            $format[] = '%s';
            $id = $this->model->create($data, $format);

            if ($id) {
                echo '<div class="notice notice-success is-dismissible"><p>Time entry added successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Error adding expense.</p></div>';
            }

        } else {

            $result = $this->model->update($id, $data, $format);
        
            if ($result) {
                echo '<div class="notice notice-success is-dismissible"><p>Time entry updated successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Error adding expense.</p></div>';
            }

        }

    }

    public function list() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Get all time entries first for filter options
        $all_entries = $this->model->select(null);

        // Build WHERE clause for filtering
        global $wpdb;
        $where_conditions = [];
        $filter_project = isset($_GET['filter_project']) ? sanitize_text_field($_GET['filter_project']) : '';
        $filter_member = isset($_GET['filter_member']) ? sanitize_text_field($_GET['filter_member']) : '';
        $filter_billable = isset($_GET['filter_billable']) ? sanitize_text_field($_GET['filter_billable']) : '';
        $filter_billed = isset($_GET['filter_billed']) ? sanitize_text_field($_GET['filter_billed']) : '';
        $filter_entry_type = isset($_GET['filter_entry_type']) ? sanitize_text_field($_GET['filter_entry_type']) : '';
        $filter_search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        if (!empty($filter_search)) {
            $search_term = '%' . $wpdb->esc_like($filter_search) . '%';
            $where_conditions[] = $wpdb->prepare(
                "(te.description LIKE %s OR p.name LIKE %s OR tm.name LIKE %s)",
                $search_term, $search_term, $search_term
            );
        }
        if (!empty($filter_project)) {
            $where_conditions[] = $wpdb->prepare("te.project_id = %d", intval($filter_project));
        }
        if (!empty($filter_member)) {
            $where_conditions[] = $wpdb->prepare("te.member_id = %d", intval($filter_member));
        }
        if (!empty($filter_billable)) {
            $where_conditions[] = $wpdb->prepare("te.billable = %d", intval($filter_billable));
        }
        if (!empty($filter_billed)) {
            $where_conditions[] = $wpdb->prepare("te.billed = %d", intval($filter_billed));
        }
        if (!empty($filter_entry_type)) {
            $where_conditions[] = $wpdb->prepare("te.entry_type = %s", $filter_entry_type);
        }

        // Get orderby and order parameters
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'date';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';

        // Validate orderby column
        $allowed_orderby = ['date', 'project_name', 'member_name', 'hours', 'billable', 'billed'];
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'date';
        }

        // Validate order direction
        $order = strtoupper($order);
        if (!in_array($order, ['ASC', 'DESC'])) {
            $order = 'DESC';
        }

        // Map orderby to actual column names
        $orderby_column = $orderby;
        if ($orderby == 'date' || $orderby == 'hours' || $orderby == 'billable' || $orderby == 'billed') {
            $orderby_column = 'te.' . $orderby;
        } elseif ($orderby == 'project_name') {
            $orderby_column = 'p.name';
        } elseif ($orderby == 'member_name') {
            $orderby_column = 'tm.name';
        }

        // Fetch time entries with filtering and ordering
        $table_name = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $member_table = $wpdb->prefix . TIMEGROW_PREFIX . 'team_member_tracker';
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $query = "SELECT te.*, p.name as project_name, tm.name as member_name
                  FROM {$table_name} te
                  LEFT JOIN {$project_table} p ON te.project_id = p.ID
                  LEFT JOIN {$member_table} tm ON te.member_id = tm.ID
                  {$where_clause}
                  ORDER BY {$orderby_column} {$order}";
        $time_entries = $wpdb->get_results($query);

        // Get unique values for filters
        $filter_options = ['projects' => [], 'members' => []];

        // Get projects for filter
        $projects = $this->project_model->select(null);
        foreach ($projects as $project) {
            $filter_options['projects'][$project->ID] = $project->name;
        }

        // Get members for filter
        $members = $this->member_model->select(null);
        foreach ($members as $member) {
            $filter_options['members'][$member->ID] = $member->name;
        }

        $this->view->display($time_entries, $filter_options, [
            'orderby' => $orderby,
            'order' => $order,
            'filter_project' => $filter_project,
            'filter_member' => $filter_member,
            'filter_billable' => $filter_billable,
            'filter_billed' => $filter_billed,
            'filter_entry_type' => $filter_entry_type,
            'filter_search' => $filter_search
        ]);
    }

    public function add() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $projects = $this->project_model->select(null); 
        $members = $this->member_model->select(null); 
        $this->view->add($projects, $members);
    }

    public function edit($id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $time_entries = $this->model->select($id)[0]; // Fetch entry by ID
        $projects = $this->project_model->select(null); 
        $members = $this->member_model->select(null); //
        $this->view->edit($time_entries, $projects, $members);
    }

    public function display_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        if ($screen != 'list' && ( isset($_POST['add_item']) || isset($_POST['edit_item']) )) {
            var_dump('processing form');
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
                wp_die( 'Error: Entry ID not provided in the URL.', 'Missing Entry ID', array( 'back_link' => true ) );
            }
            $this->edit($id);
        }
    }

}
