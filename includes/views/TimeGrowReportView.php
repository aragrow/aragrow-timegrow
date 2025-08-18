<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowReportView {
    
    private $reports;

    public function display_reports() {
        $reports = [];
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        ?>
    
        <div class="wrap">
            <h1>Timekeeping Reports</h1>

            <h2>Day-to-Day Reports</h2>
            <ul>
                <li><a href="<?php echo admin_url('admin.php?page=timegrow-reports&report=daily_hours'); ?>">Daily Hours Report</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=timegrow-reports&report=overtime'); ?>">Overtime Report</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=timegrow-reports&report=employee_breakdown'); ?>">Employee Breakdown</a></li>
            </ul>

            <h2>End-of-Year & Tax Reports</h2>
            <ul>
                <li><a href="<?php echo admin_url('admin.php?page=timegrow-reports&report=yearly_totals'); ?>">Yearly Totals</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=timegrow-reports&report=profit_loss_statement'); ?>">Profit &amp; Loss Statement</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=timegrow-reports&report=export_csv'); ?>">Export to CSV</a></li>
            </ul>

        </div>
        <?php
    
    }

    /**
     * Report Header
     *
     * @param string $title Report title
     */
    function report_header($title) {
        ?>
        <div class="tk-report-header" style="border-bottom: 2px solid #ccc; margin-bottom: 20px; padding-bottom: 10px;">
            <h1 style="margin: 0;"><?php echo esc_html($title); ?></h1>
            <p><em>Generated on: <?php echo date('F j, Y, g:i a'); ?></em></p>
        </div>
        <?php
    }

    /**
     * Report Footer
     */
    function report_footer() {
        ?>
        <div class="tk-report-footer" style="border-top: 2px solid #ccc; margin-top: 20px; padding-top: 10px; font-size: 12px; color: #666;">
            <p>Timekeeping System &copy; <?php echo date('Y'); ?> | Confidential Report</p>
        </div>
        <?php
    }

    function profit_loss_statement_filters() {

        // Available years (in real use, you might fetch distinct years from DB)
        $years = [2023, 2024, 2025];

        // Get current selected year from query (default = current year)
        $selected_year = isset($_GET['pnl_year']) ? intval($_GET['pnl_year']) : date('Y');
        ?>
        <form method="POST" action="" style="margin-bottom: 20px;">
            <?php wp_nonce_field('profit_loss_statement_filter', 'report_nonce'); ?>
            <input type="hidden" name="page" value="timekeeping-reports">
            <input type="hidden" name="report" value="pnl">
            
            <label for="pnl_year"><strong>Select Year:</strong></label>
            <select name="pnl_year" id="pnl_year">
                <?php foreach ($years as $year): ?>
                    <option value="<?php echo esc_attr($year); ?>" <?php selected($year, $selected_year); ?>>
                        <?php echo esc_html($year); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="button button-primary">Filter</button>
        </form>
        <?php
        return $selected_year;

    }

    /**
     * Tax Summary Report Example
     */
    function profit_loss_statement() {
        // In a real case, you'd fetch this data from your timekeeping/employee DB tables.
        $clients = [
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
        $total_revenue = 0;
        $total_expenses = 0;
        ?>
        <table class="widefat striped" style="margin-top:20px;">
            <thead>
                <tr><th  style="background-color:#f0f0f0;" colspan="5" class="header">Revenue</th></tr>
                <tr>
                    <th />
                    <th>Clients</th>
                    <th style="text-align:right;">Total Hours</th>
                    <th style="text-align:right;">Gross Pay ($)</th>
                    <th style="text-align:right;">Year</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $item) : ?>
                    <tr>
                        <td />
                        <td><?php echo esc_html($item['name']); ?></td>
                        <td style="text-align:right;"><?php echo esc_html(number_format($item['hours'])); ?></td>
                        <td style="text-align:right;"><?php echo esc_html(number_format($item['gross'], 2)); ?></td>
                        <td style="text-align:right;"><?php echo esc_html($item['year']); ?></td>
                    </tr>
                    <?php $total_revenue += $item['gross']; ?>
                <?php endforeach; ?>
                <tr>
                    <th colspan="3" style="text-align:right;background-color:#fff;margin-bottom:0.5rem;">Total Revenue</th>
                    <th class="total" style="background-color:#fff;text-align:right;margin-bottom:0.5rem;text-align:right;"><?php echo esc_html(number_format($total_revenue, 2)); ?></th>
                </tr>
            </tbody>
       
        <?php
        ?>

            <thead>
                <tr style="background-color:#f0f0f0;"><th colspan="5" class="header">Expenses</th></tr>
                <tr>
                    <th />
                    <th>Category</th>
                    <th></th>
                    <th style="text-align:right;">Amount ($)</th>
                    <th style="text-align:right;">Year</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses as $item) : ?>
                    <tr>
                        <td />
                        <td><?php echo esc_html($item['category']); ?></td>
                        <td></td>
                        <td style="text-align:right;"><?php echo esc_html(number_format($item['amount'], 2)); ?></td>
                        <td style="text-align:right;"><?php echo esc_html($item['year']); ?></td>
                    </tr>
                    <?php $total_expenses += $item['amount']; ?>
                <?php endforeach; ?>
                <tr>
                    <th colspan="3" style="background-color:#fff;text-align:right;margin-bottom:0.5rem;">Total Expenses</th>
                    <th class="total" style="background-color:#fff;text-align:right;margin-bottom:0.5rem;text-align:right;"><?php echo esc_html(number_format($total_expenses, 2)); ?></th>
                </tr>
                <tr>
                    <th colspan="3" style="background-color:#fff;text-align:right;">Gross Net Gain/Loss</th>
                    <th class="total" style="background-color:#fff;text-align:right;margin-bottom:0.5rem;text-align:right;"><?php echo esc_html(number_format(($total_revenue - $total_expenses), 2)); ?></th>
                </tr>
            </tbody>
        </table>
        <?php
    }

}