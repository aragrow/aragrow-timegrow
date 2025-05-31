<?php
// #### TimeGrowNexusController.php ####
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowNexusController{

    private $view_dashboard;
    private $view_clock;

    public function __construct(
        TimeGrowNexusView $view_dashboard,
        TimeGrowNexusClockView $view_clock,
    
    ) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);   
        $this->view_dashboard = $view_dashboard;
        $this->view_clock = $view_clock;
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
            $this->view_clock->display($user);
    }

}
