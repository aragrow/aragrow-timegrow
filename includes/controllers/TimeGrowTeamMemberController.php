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
        
        if (!isset($_POST['expense_id'])) return; 

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

        $id = intval($_POST['expense_id']);

        try {
            // Start transaction
            $this->wpdb->query('START TRANSACTION');

            if ($id == 0) {
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

                $result = $this->model->update($id, $data, $format);

                if ($result) {
                    echo '<div class="notice notice-success is-dismissible"><p>Team member updating successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>Error updating team member.</p></div>';
                    throw new Exception('Error updating team member');
                }

            }

            // Update project assignments
            $this->update_team_member_projects($team_member_id, $project_ids);

            if(WP_DEBUG) error_log('Timeflies_Team_Members_Admin.save_ajax() -> Completed');

            wp_send_json_success(array('message' => 'Team member saved.', 'team_member_id' => $team_member_id));
    
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
        $items = $this->model->select(null); // Fetch all team members
        $this->view->display($items);
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
                wp_die( 'Error: Team Member ID not provided in the URL.', 'Missing Team Member ID', array( 'back_link' => true ) );
            }
            $this->edit($id);
        }
    }

}
