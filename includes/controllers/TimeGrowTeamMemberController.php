<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowTeamMemberController{

    private $model;
    private $view;
    private $model_company;
    private $model_user;
    private $model_project;
    private $table_name; 
    private $wpdb;
        
    public function __construct(TimeGrowTeamMemberModel $model,  TimeGrowTeamMemberView $view, TimeGrowUserModel $model_user, TimeGrowCompanyModel $model_company, TimeGrowProjectModel $model_project) {

        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);   
        $this->model = $model;
        $this->view = $view;
        $this->model_user = $model_user;
        $this->model_company = $model_company;
        $this->model_project = $model_project;
        global $wpdb;   
        $this->wpdb = $wpdb;
        $this->table_name = "{$this->wpdb->prefix}timegrow_team_member_projects_tracker";

    }

    public function handle_form_submission() {

        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        
        if (!isset($_POST['timegrow_team_member_nonce_field']) || 
            !wp_verify_nonce($_POST['timegrow_team_member_nonce_field'], 'timegrow_team_member_nonce')) {
            wp_die(__('Nonce verification failed.', 'text-domain'));
        }

        if (!isset($_POST['team_member_id'])) return; 
        print('Processing form submission for team member');

        $current_date = current_time('mysql');
        $team_member_id = intval($_POST['team_member_id']);
        $user_id = intval($_POST['user_id']);
        $company_id = intval($_POST['company_id']);
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $phone = sanitize_text_field($_POST['phone']);
        $title = sanitize_text_field($_POST['title']);
        $bio = wp_kses_post($_POST['bio']);
        $status = isset($_POST['status']) ? 1 : 0;
        
        if (isset($_POST['project_ids'])) {
            $project_ids = (is_array($_POST['project_ids']))
                ? array_map('intval', $_POST['project_ids']) 
                : [$_POST['project_ids']];
        } else {
            $project_ids = [0];
        }

        $current_date = current_time('mysql');
        
        $data = array(
            'company_id' => $company_id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'title' => $title,
            'bio' => $bio,
            'status' => $status,
            'updated_at' => $current_date
        );

        $format = [
            '%d',   // user_id (integer)
            '%d',   // company_id (integer)
            '%s',   // name (string)
            '%s',   // email (string)
            '%f',   // phone (float)
            '%s',   // title (string)
            '%s',   // bio (string, sanitized as email)
            '%s',   // status (string)
            '%s'    // updated_at (datetime string)
        ];

        $id = intval($_POST['team_member_id']);
        print('Team Member ID: ' . $id);   
        try {
            // Start transaction
            $this->wpdb->query('START TRANSACTION');

            if ($id == 0) {
                print('<div>Creating new team member</div>');
                $data['user_id'] = $user_id;
                $data['created_at'] = $current_date;
                $format[] = '%d';
                $format[] = '%s';
                $id = $this->model->create($data, $format);

                if ($id) {
                    echo '<div class="notice notice-success is-dismissible"><p>Team member added successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Error adding team member.</p></div>';
                    throw new Exception('Error adding team member');
                }

            } else {
                print('<div>Updating existing team member</div>');
                $result = $this->model->update($id, $data, $format);

                if ($result) {
                    echo '<div class="notice notice-success is-dismissible"><p>Team member updating successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Error updating team member.</p></div>';
                    throw new Exception('Error updating team member');
                }

            }
         
            // Update project assignments
            print('<div>Updating team member projects</div>');
            $team_member_id = $id; // Use the ID returned from create or update
            $this->update_team_member_projects($team_member_id, $project_ids);
            
            if(WP_DEBUG) error_log('Timeflies_Team_Members_Admin.save_ajax() -> Completed');

            //wp_send_json_success(array('message' => 'Team member saved.', 'team_member_id' => $team_member_id));
    
            // Commit if everything is OK
            $this->wpdb->query('COMMIT');
    
        } catch (Exception $e) {

             // Rollback on error
            $this->wpdb->query('ROLLBACK');

            if(WP_DEBUG) error_log('Error saving Team Member. '.$e->getMessage());

            wp_send_json_success(array('message' => $e->getMessage(), 'team_member_id' => $team_member_id));
            
        } finally {

            if(WP_DEBUG) error_log('Timeflies_Team_Members_Admin.save_ajax() -> Finalized');
            // Optional block of code that always executes
        }
    }

    private function update_team_member_projects($team_member_id, $project_ids) {
        if (WP_DEBUG) error_log('Exc: Timeflies_Team_Members_Admin.update_team_member_projects()');

        if(WP_DEBUG) {
            error_log('Team Member Project Data');
            error_log(print_r($project_ids,true));
        }
        
        // Decode the URL-encoded string
        $project_ids = explode(',', $project_ids[0]);

        try {
            // Remove existing assignments
            $this->wpdb->delete($this->table_name, array('team_member_id' => $team_member_id), array('%d'));

            // Add new assignments
            if (!empty($project_ids)) {
                foreach ($project_ids as $project_id) {
                    $this->wpdb->insert($this->table_name, array(
                        'team_member_id' => $team_member_id,
                        'project_id' => $project_id,
                    ), array('%d', '%d'));
                }
            }
            
            // Optionally, return a success message or status
            return true;

        } catch (Exception $e) {
            
            // Log the error or handle it as needed
            error_log("Transaction failed: " . $e->getMessage());

            // Optionally, return false or throw the exception again
            return false;
            // or: throw $e;
        }
    
    }

    public function list() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Get all team members first for filter options
        $all_items = $this->model->select(null);

        // Build WHERE clause for filtering
        global $wpdb;
        $where_conditions = [];
        $filter_company = isset($_GET['filter_company']) ? sanitize_text_field($_GET['filter_company']) : '';
        $filter_title = isset($_GET['filter_title']) ? sanitize_text_field($_GET['filter_title']) : '';
        $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : '';
        $filter_search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        if (!empty($filter_search)) {
            $search_term = '%' . $wpdb->esc_like($filter_search) . '%';
            $where_conditions[] = $wpdb->prepare(
                "(tm.name LIKE %s OR tm.email LIKE %s OR tm.title LIKE %s OR tm.phone LIKE %s OR c.name LIKE %s)",
                $search_term, $search_term, $search_term, $search_term, $search_term
            );
        }
        if (!empty($filter_company)) {
            $where_conditions[] = $wpdb->prepare("tm.company_id = %d", intval($filter_company));
        }
        if (!empty($filter_title)) {
            $where_conditions[] = $wpdb->prepare("tm.title = %s", $filter_title);
        }
        if (!empty($filter_status)) {
            $where_conditions[] = $wpdb->prepare("tm.status = %d", intval($filter_status));
        }

        // Get orderby and order parameters
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'name';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC';

        // Validate orderby column
        $allowed_orderby = ['name', 'company_name', 'email', 'title', 'status'];
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
        if ($orderby == 'name' || $orderby == 'email' || $orderby == 'title' || $orderby == 'status') {
            $orderby_column = 'tm.' . $orderby;
        } elseif ($orderby == 'company_name') {
            $orderby_column = 'c.name';
        }

        // Fetch team members with filtering and ordering
        $table_name = $wpdb->prefix . TIMEGROW_PREFIX . 'team_member_tracker';
        $company_table = $wpdb->prefix . TIMEGROW_PREFIX . 'company_tracker';
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $query = "SELECT tm.*, c.name as company_name
                  FROM {$table_name} tm
                  LEFT JOIN {$company_table} c ON tm.company_id = c.ID
                  {$where_clause}
                  ORDER BY {$orderby_column} {$order}";
        $items = $wpdb->get_results($query);

        // Get unique values for filters from all items
        $filter_options = [
            'companies' => [],
            'titles' => array_unique(array_filter(array_column($all_items, 'title')))
        ];

        // Get companies for filter
        $companies = $this->model_company->select(null);
        foreach ($companies as $company) {
            $filter_options['companies'][$company->ID] = $company->name;
        }

        sort($filter_options['titles']);

        $this->view->display($items, $filter_options, [
            'orderby' => $orderby,
            'order' => $order,
            'filter_company' => $filter_company,
            'filter_title' => $filter_title,
            'filter_status' => $filter_status,
            'filter_search' => $filter_search
        ]);
    }

    public function add() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $users = $this->model_user->select(null); // Fetch all team members
        $companies = $this->model_company->select(null); // Fetch all companies
        $projects = $this->model_project->select(null); // Fetch all projects
        $this->view->add($users, $companies, $projects);
    }

    public function edit($id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $item = $this->model->select($id)[0]; // Fetch  team member
        $users = $this->model_user->select(null); // Fetch all team members
        $companies = $this->model_company->select(null); // Fetch all team member
        $available = $this->model_project->available($item->user_id); // Fetch all team members
        $assigned = $this->model_project->assigned($item->user_id); // Fetch all team members
        $this->view->edit($item, $users, $companies, $available, $assigned);
    }

    public function display_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
           
        if ($screen != 'list' && ( isset($_POST['add_item']) || isset($_POST['edit_item']) )) {
            print('<div class="wrap timegrow-page-container timegrow-team-member-page">');
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
                wp_die( 'Error: Team Member ID not provided in the URL.', 'Missing Team Member ID', array( 'back_link' => true ) );
            }
            $this->edit($id);
        }
    }

}
