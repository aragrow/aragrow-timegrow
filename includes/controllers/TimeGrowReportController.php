<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowReportController{

    private $report_view;

    public function __construct( TimeGrowReportView $report_view) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $this->report_view = $report_view;
    }

    public function list_expenses() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $this->report_view->display_reports();
    }

    public function load_the_report() {

        if (!isset($_GET['report'])) {
            echo "<p>Select a report from above.</p>";
            return;
        }

        $report = sanitize_text_field($_GET['report']);

        

        switch ($report) {
            case 'daily_hours':
                echo "<h3>Daily Hours Report</h3>";
                echo "<p>[Example: Show hours logged by employees today]</p>";
                break;

            case 'overtime':
                echo "<h3>Overtime Report</h3>";
                echo "<p>[Example: Show overtime hours for the selected period]</p>";
                break;

            case 'employee_breakdown':
                echo "<h3>Employee Breakdown</h3>";
                echo "<p>[Example: Show hours per employee]</p>";
                break;

            case 'yearly_totals':
                echo "<h3>Yearly Totals</h3>";
                echo "<p>[Example: Show total hours for the year per employee]</p>";
                break;

            case 'profit_loss_statement':
                $this->report_view->report_header("Profit &amp; Loss Statement");
                $this->report_view->profit_loss_statement_filters();
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
                    if (
                        !isset($_POST['report_nonce']) ||
                        !wp_verify_nonce($_POST['report_nonce'], 'profit_loss_statement_filter')
                    ) {
                        echo '<div class="notice notice-error"><p>Security check failed. Please try again.</p></div>';
                        return;
                    }
                    $data = $this->get_profit_lost_statement($_POST['pnl_year']);
                    $this->report_view->profit_loss_statement($data);
                }
                break;

            case 'export_csv':
                echo "<h3>Export to CSV</h3>";
                echo "<p>[Example: Export all timekeeping data for accounting]</p>";
                break;

            default:
                echo "<p>Unknown report selected.</p>";
        }

        $this->report_view->report_footer();

    }

    function get_profit_lost_statement($year) {

                // In a real case, you'd fetch this data from your timekeeping/employee DB tables.
        $revenue = [
            [ 'name' => 'Alice Johnson', 'hours' => 2080, 'gross' => 52000, 'year'=> 2024],
            [ 'name' => 'Bob Smith',     'hours' => 1950, 'gross' => 46800, 'year'=> 2024], 
            [ 'name' => 'Carol Lee',     'hours' => 2100, 'gross' => 54000, 'year'=> 2025],
        ];
        $expenses = [
            [ 'category' => 'Utilities',        'amount' => 3600,   'year'=> 2024],
            [ 'category' => 'Software Licenses','amount' => 1200,   'year'=> 2024],
            [ 'category' => 'Contractor Wages', 'amount' => 15000,  'year'=> 2024],
            [ 'category' => 'Supplies',         'amount' => 800,    'year'=> 2024],
        ];
        return ['year' => $year, 'revenue' => $revenue, 'expenses' => $expenses];
    }

    public function display_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        if ($screen == 'list') 
            $this->list_expenses();
        else 
            $this->load_the_report();
        
    }

}
