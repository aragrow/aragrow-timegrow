<?php
// #### TimeGrowNexusController.php ####
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowNexusController{

    private $model;
    private $view_dashboard;
    private $view_clock;
    private $view_manual;
    private $view_expense;
    private $view_report;
    private $view_settings;
    private $view_process_time;
    private $projects;
    private $reports;
    private $team_members_model;
    private $list;
    private $clients;

    public function __construct(
        TimeGrowTimeEntryModel $model,
        TimeGrowNexusView $view_dashboard,
        TimeGrowNexusClockView $view_clock,
        TimeGrowNexusManualView $view_manual,
        TimeGrowNexusExpenseView $view_expense,
        TimeGrowNexusReportView $view_report,
        TimeGrowNexusSettingsView $view_settings,
        TimeGrowNexusProcessTimeView $view_process_time,
        $projects = [],
        $reports = [],
        TimeGrowTeamMemberModel $team_members_model,
        $list = [], // Default to empty array
        $clients = [] // Clients for process time filtering
    ) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        $this->model=$model;
        $this->view_dashboard = $view_dashboard;
        $this->view_clock = $view_clock;
        $this->view_manual = $view_manual;
        $this->view_expense = $view_expense;
        $this->view_report = $view_report;
        $this->view_settings = $view_settings;
        $this->view_process_time = $view_process_time;
        $this->projects = $projects;
        $this->reports = $reports;
        $this->team_members_model = $team_members_model;
        $this->list = $list;
        $this->clients = $clients;

    }

    public function handle_form_submission() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
       
        if (isset($_POST['time_entry_id']))  $this->handle_form_submission_time_entry(); 
        else if (isset($_POST['expense_id']))  $this->handle_form_submission_expense(); 

    }


    public function display_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        $user = wp_get_current_user();
        $member = $this->team_members_model->get_by_user_id($user->ID);
        $member_id = $member ? $member->ID : 0;

        if (( isset($_POST['time_entry_id']) || isset($_POST['expense_id']) )) {
            var_dump('processing form');
            $this->handle_form_submission();
            $screen = 'dashboard';
        }
        if ($screen == 'dashboard')
            $this->view_dashboard->display($user);
        elseif ($screen == 'clock')
            $this->view_clock->display($user, $member_id, $this->projects, $this->list);
        elseif ($screen == 'manual')
            $this->view_manual->display($user, $member_id, $this->projects, $this->list);
        elseif ($screen == 'expenses')
            $this->view_expense->display($user, $member_id, $this->projects);
        elseif ($screen == 'reports')
            $this->view_report->display($user, $this->reports);
        elseif ($screen == 'areport') {
            // Handle individual report rendering
            $report_controller = new TimeGrowReportsController();
            $report_controller->render_individual_report_page();
        }
        elseif ($screen == 'settings')
            $this->view_settings->display($user);
        elseif ($screen == 'process_time')
            $this->view_process_time->display($user, $this->clients);
    }

    private function handle_form_submission_time_entry() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        if (!isset($_POST['timegrow_time_nexus_nonce_field']) || 
            !wp_verify_nonce($_POST['timegrow_time_nexus_nonce_field'], 'timegrow_time_nexus_nonce')) {
            wp_die(__('Nonce verification failed.', 'text-domain'));
        }
        
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

     private function handle_form_submission_expense() {
    
        if (!isset($_POST['timegrow_time_nexus_nonce_field']) || 
            !wp_verify_nonce($_POST['timegrow_expense_nexus_nonce_field'], 'timegrow_expense_nexus_nonce')) {
            wp_die(__('Nonce verification failed.', 'text-domain'));
        }
        
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
}
