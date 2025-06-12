<?php
// #### TimeGrowNexusController.php ####
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowNexusController{

    private $view_dashboard;
    private $view_clock;
    private $view_manual;
    private $view_expense;
    private $view_report;
    private $projects;
    private $reports;

    public function __construct(
        TimeGrowNexusView $view_dashboard,
        TimeGrowNexusClockView $view_clock,
        TimeGrowNexusManualView $view_manual,
        TimeGrowNexusExpenseView $view_expense,
        TimeGrowNexusReportView $view_report,
        $projects = [],
        $reports = []   
    ) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);   
        $this->view_dashboard = $view_dashboard;
        $this->view_clock = $view_clock;
        $this->view_manual = $view_manual;
        $this->view_expense = $view_expense;
        $this->view_report = $view_report;
        $this->projects = $projects;
        $this->reports = $reports;
    }

    public function handle_form_submission() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
    }

    public function display_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        
        $user = wp_get_current_user();

        if ($screen == 'dashboard')
            $this->view_dashboard->display($user);
        elseif ($screen == 'clock')
            $this->view_clock->display($user, $this->projects);
        elseif ($screen == 'manual')
            $this->view_manual->display($user, $this->projects);
        elseif ($screen == 'expenses')
            $this->view_expense->display($user, $this->projects);
        elseif ($screen == 'reports')
            $this->view_report->display($user, $this->reports);
    }

}
