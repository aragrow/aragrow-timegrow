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
        TimeGrowNexusProcessTimeView $view_process_time,
        $projects,
        $reports,
        TimeGrowTeamMemberModel $team_members_model,
        $list = [], // Default to empty array
        $clients = [] // Clients for process time filtering
    ) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        $this->model=$model;
        $this->view_dashboard = $view_dashboard;
        $this->view_clock = $view_clock;
        $this->view_manual = $view_manual;
        $this->view_expense = $view_expense;
        $this->view_report = $view_report;
        $this->view_process_time = $view_process_time;
        $this->projects = $projects;
        $this->reports = $reports;
        $this->team_members_model = $team_members_model;
        $this->list = $list;
        $this->clients = $clients;

    }

    public function display_admin_page($screen) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        $user = wp_get_current_user();
        $member = $this->team_members_model->get_by_user_id($user->ID);
        $member_id = $member ? $member->ID : 0;

        // Time entries and expenses are saved through the pages' Save buttons (AJAX save_time_entry and the
        // expense recorder), which check who may save what. A plain form post here saves nothing.
        if (( isset($_POST['time_entry_id']) || isset($_POST['expense_id']) )) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Nothing was saved. Use the Save button on the page (it needs JavaScript turned on).', 'timegrow') . '</p></div>';
        }
        if ($screen == 'dashboard')
            $this->view_dashboard->display($user);
        elseif ($screen == 'clock')
            $this->view_clock->display($user, $member_id, $this->projects, $this->list);
        elseif ($screen == 'manual')
            $this->view_manual->display($user, $member_id, $this->projects, $this->list);
        elseif ($screen == 'expenses')
            $this->view_expense->display($user, $this->projects);
        elseif ($screen == 'reports')
            $this->view_report->display($user, $this->reports);
        elseif ($screen == 'areport') {
            // Handle individual report rendering
            $report_controller = new TimeGrowReportsController();
            $report_controller->render_individual_report_page();
        }
        elseif ($screen == 'process_time')
            $this->view_process_time->display($user, $this->clients);
    }

}
