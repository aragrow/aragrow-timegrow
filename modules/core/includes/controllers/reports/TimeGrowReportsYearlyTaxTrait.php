<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Yearly Tax Report: page, data queries, PDF and CSV export.
 * Used by TimeGrowReportsController (methods moved as-is from the controller).
 */
trait TimeGrowReportsYearlyTaxTrait {

    /**
     * Generate Yearly Tax Report
     * Shows time entries and expenses for a selected year
     */
    private function generate_yearly_tax_report() {
        global $wpdb;

        // Get selected year from request or default to current year
        $selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

        // Get available years from database
        $expense_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';

        $available_years_query = "
            SELECT DISTINCT YEAR(expense_date) as year FROM {$expense_table} WHERE expense_date IS NOT NULL
            ORDER BY year DESC
        ";
        $available_years = $wpdb->get_col($available_years_query);

        // Add print styles
        echo '<style>
            @media print {
                /* CRITICAL: Override WordPress responsive table behavior - force normal table display */
                table.wp-list-table,
                table.wp-list-table thead,
                table.wp-list-table tbody,
                table.wp-list-table tr,
                table.wp-list-table th,
                table.wp-list-table td {
                    display: table !important;
                }

                table.wp-list-table thead {
                    display: table-header-group !important;
                }

                table.wp-list-table tbody {
                    display: table-row-group !important;
                }

                table.wp-list-table tr {
                    display: table-row !important;
                }

                table.wp-list-table th,
                table.wp-list-table td {
                    display: table-cell !important;
                }

                /* Remove responsive pseudo-element labels */
                table.wp-list-table td::before,
                table.wp-list-table td[data-colname]::before {
                    display: none !important;
                    content: none !important;
                }

                /* Hide controls and buttons when printing */
                .tax-report-controls,
                .button,
                .wp-admin #wpadminbar,
                #adminmenuback,
                #adminmenuwrap,
                .update-nag,
                .notice,
                .debug-info {
                    display: none !important;
                }

                /* Set page margins - landscape for wider tables */
                @page {
                    margin: 1cm;
                    size: A4 landscape;
                }

                /* Reset body for print */
                body {
                    background: white !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    font-size: 10pt;
                    max-width: 100% !important;
                    width: 100% !important;
                }

                /* Ensure main container uses full width */
                .wrap,
                .timegrow-page {
                    max-width: 100% !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 10px !important;
                }

                /* Report header with gradient */
                div[style*="background: linear-gradient"] {
                    background: #333 !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                    page-break-inside: avoid;
                    page-break-after: avoid;
                }

                div[style*="background: linear-gradient"] h1,
                div[style*="background: linear-gradient"] p {
                    color: white !important;
                }

                /* Make tables readable and tabulated - each row shows all columns */
                table.wp-list-table {
                    width: 100% !important;
                    max-width: 100% !important;
                    border-collapse: collapse;
                    margin: 0 0 15px 0 !important;
                    padding: 0 !important;
                    page-break-inside: auto;
                    font-size: 7pt;
                    table-layout: fixed;
                }

                table.wp-list-table th {
                    background: #333 !important;
                    color: white !important;
                    padding: 5px 3px !important;
                    font-size: 7pt !important;
                    font-weight: bold !important;
                    border: 1px solid #000 !important;
                    text-align: left !important;
                    white-space: nowrap !important;
                    overflow: hidden !important;
                    text-overflow: ellipsis !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                table.wp-list-table td {
                    padding: 4px 3px !important;
                    font-size: 6.5pt !important;
                    border: 1px solid #999 !important;
                    vertical-align: middle !important;
                    white-space: nowrap !important;
                    overflow: hidden !important;
                    text-overflow: ellipsis !important;
                    line-height: 1.3 !important;
                }

                /* Specific column widths for expense tables (6 columns) */
                /* Date column - compact */
                table.wp-list-table th:nth-child(1),
                table.wp-list-table td:nth-child(1) {
                    width: 9%;
                    text-align: left !important;
                }

                /* Name column - allow some wrapping if needed */
                table.wp-list-table th:nth-child(2),
                table.wp-list-table td:nth-child(2) {
                    width: 18%;
                    white-space: normal !important;
                }

                /* Amount column - right aligned for numbers */
                table.wp-list-table th:nth-child(3),
                table.wp-list-table td:nth-child(3) {
                    width: 10%;
                    text-align: right !important;
                }

                /* Payment Method column */
                table.wp-list-table th:nth-child(4),
                table.wp-list-table td:nth-child(4) {
                    width: 13%;
                }

                /* Assigned To column */
                table.wp-list-table th:nth-child(5),
                table.wp-list-table td:nth-child(5) {
                    width: 12%;
                }

                /* Description column - allow wrapping for longer text */
                table.wp-list-table th:nth-child(6),
                table.wp-list-table td:nth-child(6) {
                    width: 38%;
                    white-space: normal !important;
                    word-wrap: break-word !important;
                    overflow: visible !important;
                }

                /* For invoice tables with more columns */
                table.wp-list-table th:nth-child(7),
                table.wp-list-table td:nth-child(7) {
                    width: auto;
                }

                table.wp-list-table th:nth-child(8),
                table.wp-list-table td:nth-child(8) {
                    width: auto;
                }

                /* Alternating row colors for better readability */
                table.wp-list-table tbody tr:nth-child(even) {
                    background: #f5f5f5 !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                table.wp-list-table tbody tr:nth-child(odd) {
                    background: white !important;
                }

                /* Ensure rows stay together */
                table.wp-list-table tbody tr {
                    page-break-inside: avoid !important;
                }

                /* Make headings readable */
                h2, h3, h4 {
                    color: #000 !important;
                    page-break-after: avoid;
                    page-break-inside: avoid;
                }

                h2 {
                    font-size: 14pt !important;
                    margin-top: 15px !important;
                    margin-bottom: 10px !important;
                    background: #f0f0f0 !important;
                    padding: 10px !important;
                    border-left: 5px solid #000 !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                h3 {
                    font-size: 12pt !important;
                    margin-top: 12px !important;
                    margin-bottom: 8px !important;
                }

                h4 {
                    font-size: 10pt !important;
                    background: #f5f5f5 !important;
                    padding: 6px !important;
                    border-left: 3px solid #666 !important;
                    color: #000 !important;
                    margin: 8px 0 !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                /* Summary cards */
                .tax-report-summary {
                    display: flex !important;
                    flex-wrap: wrap !important;
                    gap: 10px !important;
                    margin-bottom: 15px !important;
                    page-break-inside: avoid;
                }

                .summary-card {
                    flex: 1 1 30% !important;
                    min-width: 150px !important;
                    padding: 8px !important;
                    border: 1.5px solid #333 !important;
                    background: #f9f9f9 !important;
                    text-align: center !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                .summary-card h3 {
                    font-size: 9pt !important;
                    margin: 0 0 4px 0 !important;
                    color: #000 !important;
                }

                .summary-card p {
                    font-size: 14pt !important;
                    font-weight: bold !important;
                    margin: 0 !important;
                    color: #000 !important;
                }

                /* Total boxes */
                div[style*="background: #1565c0"],
                div[style*="background: #e65100"],
                div[style*="background: #2e7d32"] {
                    background: #333 !important;
                    color: white !important;
                    padding: 8px !important;
                    margin: 8px 0 !important;
                    border: 2px solid #000 !important;
                    page-break-inside: avoid;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }

                div[style*="background: #1565c0"] h4,
                div[style*="background: #e65100"] h4,
                div[style*="background: #2e7d32"] h3,
                div[style*="background: #1565c0"] h3,
                div[style*="background: #e65100"] h3,
                div[style*="background: #2e7d32"] h4,
                div[style*="background: #1565c0"] p,
                div[style*="background: #e65100"] p,
                div[style*="background: #2e7d32"] p {
                    color: white !important;
                    margin: 0 !important;
                }

                /* Prevent page breaks inside tables when possible */
                tr {
                    page-break-inside: avoid;
                }

                /* Page break before major sections */
                h2[style*="Business Expenses"],
                h2[style*="Income"] {
                    page-break-before: always;
                }

                /* First section should not break */
                h2:first-of-type {
                    page-break-before: auto !important;
                }
            }
        </style>';

        // Year selector form
        echo '<div class="tax-report-controls" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<form method="get" action="" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">';
        echo '<input type="hidden" name="page" value="' . esc_attr($_GET['page']) . '" />';
        echo '<input type="hidden" name="report_slug" value="yearly_tax_report" />';
        echo '<label for="year" style="font-weight: bold; margin: 0;">Select Year:</label>';
        echo '<select name="year" id="year" onchange="this.form.submit()" style="padding: 8px 12px; min-width: 100px; font-size: 14px;">';
        foreach ($available_years as $year) {
            $selected = ($year == $selected_year) ? 'selected' : '';
            echo '<option value="' . esc_attr($year) . '" ' . $selected . '>' . esc_html($year) . '</option>';
        }
        echo '</select>';
        echo '<button type="submit" class="button button-primary">Generate Report</button>';
        echo '</form>';
        echo '<div style="padding: 10px; background: #e3f2fd; border-left: 4px solid #1565c0; border-radius: 4px;">';
        echo '<p style="margin: 0; font-size: 13px; color: #1565c0; line-height: 1.6;"><strong>📊 Cash Basis Accounting:</strong> This report uses the <strong>cash basis method</strong> for tax reporting. Income is recorded when payment is <strong>received</strong> (not when invoiced), and expenses are recorded when <strong>paid</strong> (not when incurred).</p>';
        echo '<p style="margin: 8px 0 0 0; font-size: 13px; color: #1565c0; line-height: 1.6;"><strong>💰 Payment Recording:</strong> Invoices are included in the year the payment was received (based on Payment Date), regardless of when the invoice was created. <strong>Partial payments</strong> are also included and counted towards your income for the year they were received.</p>';
        echo '</div>';
        echo '</div>';

        // Fetch expenses for selected year
        $expenses = $this->get_yearly_expenses($selected_year);
        $expenses_sql = $wpdb->last_query;

        // Fetch WooCommerce invoices/orders for selected year
        $invoices = $this->get_yearly_invoices($selected_year);
        $invoices_sql = $wpdb->last_query;

        // Display SQL Debug (only when timegrow_debug() is enabled)
        if (timegrow_debug()): ?>
            <div class="debug-info" style="background: #f8f9fa; border: 2px solid #0073aa; padding: 20px; margin: 20px 0; border-radius: 5px;">
                <h3 style="margin-top: 0; color: #0073aa;">📊 SQL Queries</h3>

                <h4 style="color: #0073aa; margin-top: 15px;">Expenses Query:</h4>
                <pre style="background: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; line-height: 1.5;"><?php echo esc_html($expenses_sql); ?></pre>
                <p><strong>Results:</strong> <?php echo count($expenses); ?> expenses found</p>

                <h4 style="color: #0073aa; margin-top: 15px;">Invoices Query:</h4>
                <pre style="background: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; line-height: 1.5;"><?php echo esc_html($invoices_sql); ?></pre>
                <p><strong>Results:</strong> <?php echo count($invoices); ?> invoices found</p>

                <?php if ($wpdb->last_error): ?>
                    <p style="color: red;"><strong>Last Error:</strong> <?php echo esc_html($wpdb->last_error); ?></p>
                <?php endif; ?>
            </div>
        <?php endif;

        // Calculate totals

        $total_expenses = 0;
        foreach ($expenses as $expense) {
            $total_expenses += floatval($expense->amount);
        }

        $total_invoices = 0;
        $total_invoice_amount = 0;
        $total_paid_amount = 0;
        foreach ($invoices as $invoice) {
            $total_invoices++;
            $total_invoice_amount += floatval($invoice->total_amount);
            // Add payment amount if available, otherwise fall back to total_amount
            $payment = floatval($invoice->payment_amount);
            $total_paid_amount += ($payment > 0) ? $payment : floatval($invoice->total_amount);
        }

        // Display Report Title and Company Info
        $company_name = get_bloginfo('name');
        $company_tagline = get_bloginfo('description');

        // Same layout as the Personal Productivity reports: who and when on one line (kept for printing)
        echo '<p class="timegrow-report-meta" style="margin: 0 0 20px 0; color: #555;"><strong>' . esc_html(sprintf(__('Tax year %s', 'timegrow'), $selected_year)) . '</strong> · '
            . esc_html($company_name) . (!empty($company_tagline) ? ' · ' . esc_html($company_tagline) : '')
            . ' · ' . esc_html(sprintf(__('Generated on %s', 'timegrow'), date_i18n('F j, Y g:i A'))) . '</p>';

        // Display summary
        echo '<div class="tax-report-summary" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">';

        echo '<div class="summary-card" style="background: #fff3e0; padding: 20px; border-radius: 5px; text-align: center;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #e65100;">Total Expenses</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 0;">$' . number_format($total_expenses, 2) . '</p>';
        echo '</div>';

        echo '<div class="summary-card" style="background: #e8f5e9; padding: 20px; border-radius: 5px; text-align: center;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #2e7d32;">Number of Paid Invoices</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 0;">' . $total_invoices . '</p>';
        echo '</div>';

        echo '<div class="summary-card" style="background: #e3f2fd; padding: 20px; border-radius: 5px; text-align: center;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #1565c0;">Total Amount Invoiced</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 0;">$' . number_format($total_invoice_amount, 2) . '</p>';
        echo '</div>';

        echo '<div class="summary-card" style="background: #e8f5e9; padding: 20px; border-radius: 5px; text-align: center;">';
        echo '<h3 style="margin: 0 0 10px 0; color: #388e3c;">Total Amount Paid</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 0;">$' . number_format($total_paid_amount, 2) . '</p>';
        echo '</div>';

        $profit_loss = $total_paid_amount - $total_expenses;
        $pnl_color = $profit_loss >= 0 ? '#2e7d32' : '#c62828';
        $pnl_bg = $profit_loss >= 0 ? '#e8f5e9' : '#ffebee';

        echo '<div class="summary-card" style="background: ' . $pnl_bg . '; padding: 20px; border-radius: 5px; text-align: center;">';
        echo '<h3 style="margin: 0 0 10px 0; color: ' . $pnl_color . ';">Net Profit/Loss</h3>';
        echo '<p style="font-size: 24px; font-weight: bold; margin: 0; color: ' . $pnl_color . ';">$' . number_format($profit_loss, 2) . '</p>';
        echo '</div>';

        echo '</div>';

        // Display Expenses Table
        echo '<h2 style="margin-top: 40px; font-size: 28px; color: #000; background: #f0f0f0; padding: 15px; border-left: 6px solid #e65100; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">📋 Business Expenses for ' . esc_html($selected_year) . '</h2>';

        if (!empty($expenses)) {
            // Group expenses by category slug
            $expenses_by_category = [];
            $category_totals = [];
            $category_info = []; // Store category name and schedule_c_part by slug

            foreach ($expenses as $expense) {
                $category_slug = $expense->category_slug ? $expense->category_slug : 'uncategorized';
                if (!isset($expenses_by_category[$category_slug])) {
                    $expenses_by_category[$category_slug] = [];
                    $category_totals[$category_slug] = 0;
                    $category_info[$category_slug] = [
                        'name' => $expense->category_name ? $expense->category_name : 'Uncategorized',
                        'part' => $expense->schedule_c_part
                    ];
                }
                $expenses_by_category[$category_slug][] = $expense;
                $category_totals[$category_slug] += floatval($expense->amount);
            }

            // Group categories by Schedule C Part
            $part_ii_categories = [];
            $part_v_categories = [];
            foreach ($category_info as $slug => $info) {
                if ($info['part'] === 'Part II') {
                    $part_ii_categories[$slug] = $info['name'];
                } elseif ($info['part'] === 'Part V') {
                    $part_v_categories[$slug] = $info['name'];
                }
            }

            // Display Schedule C - Part II Expenses
            echo '<h3 style="margin-top: 25px; color: #1565c0;">Schedule C - Part II (Main Expenses)</h3>';
            $part_ii_total = 0;

            foreach ($part_ii_categories as $cat_key => $cat_label) {
                if (isset($expenses_by_category[$cat_key]) && !empty($expenses_by_category[$cat_key])) {
                    echo '<h4 style="margin-top: 20px; margin-bottom: 10px; background: #e3f2fd; padding: 10px; border-left: 4px solid #1565c0;">';
                    echo esc_html($cat_label) . ' - <strong>$' . number_format($category_totals[$cat_key], 2) . '</strong>';
                    echo '</h4>';

                    echo '<table class="wp-list-table widefat fixed striped" style="margin-bottom: 20px;">';
                    echo '<thead><tr>';
                    echo '<th>Date</th>';
                    echo '<th>Name</th>';
                    echo '<th>Amount</th>';
                    echo '<th>Payment Method</th>';
                    echo '<th>Assigned To</th>';
                    echo '<th>Description</th>';
                    echo '</tr></thead>';
                    echo '<tbody>';

                    foreach ($expenses_by_category[$cat_key] as $expense) {
                        echo '<tr>';
                        echo '<td>' . esc_html($expense->expense_date) . '</td>';
                        echo '<td>' . esc_html($expense->expense_name) . '</td>';
                        echo '<td>$' . number_format(floatval($expense->amount), 2) . '</td>';
                        echo '<td>' . esc_html(ucwords(str_replace('_', ' ', $expense->expense_payment_method))) . '</td>';
                        echo '<td>' . esc_html(ucfirst($expense->assigned_to)) . '</td>';
                        echo '<td>' . esc_html($expense->expense_description) . '</td>';
                        echo '</tr>';
                    }

                    echo '</tbody></table>';
                    $part_ii_total += $category_totals[$cat_key];
                }
            }

            if ($part_ii_total > 0) {
                echo '<div style="background: #1565c0; color: white; padding: 15px; margin-bottom: 20px; border-radius: 5px;">';
                echo '<h4 style="margin: 0; color: white;">Part II Total: <strong>$' . number_format($part_ii_total, 2) . '</strong></h4>';
                echo '</div>';
            }

            // Display Schedule C - Part V Expenses
            echo '<h3 style="margin-top: 25px; color: #e65100;">Schedule C - Part V (Other Expenses)</h3>';
            $part_v_total = 0;

            foreach ($part_v_categories as $cat_key => $cat_label) {
                if (isset($expenses_by_category[$cat_key]) && !empty($expenses_by_category[$cat_key])) {
                    echo '<h4 style="margin-top: 20px; margin-bottom: 10px; background: #fff3e0; padding: 10px; border-left: 4px solid #e65100;">';
                    echo esc_html($cat_label) . ' - <strong>$' . number_format($category_totals[$cat_key], 2) . '</strong>';
                    echo '</h4>';

                    echo '<table class="wp-list-table widefat fixed striped" style="margin-bottom: 20px;">';
                    echo '<thead><tr>';
                    echo '<th>Date</th>';
                    echo '<th>Name</th>';
                    echo '<th>Amount</th>';
                    echo '<th>Payment Method</th>';
                    echo '<th>Assigned To</th>';
                    echo '<th>Description</th>';
                    echo '</tr></thead>';
                    echo '<tbody>';

                    foreach ($expenses_by_category[$cat_key] as $expense) {
                        echo '<tr>';
                        echo '<td>' . esc_html($expense->expense_date) . '</td>';
                        echo '<td>' . esc_html($expense->expense_name) . '</td>';
                        echo '<td>$' . number_format(floatval($expense->amount), 2) . '</td>';
                        echo '<td>' . esc_html(ucwords(str_replace('_', ' ', $expense->expense_payment_method))) . '</td>';
                        echo '<td>' . esc_html(ucfirst($expense->assigned_to)) . '</td>';
                        echo '<td>' . esc_html($expense->expense_description) . '</td>';
                        echo '</tr>';
                    }

                    echo '</tbody></table>';
                    $part_v_total += $category_totals[$cat_key];
                }
            }

            if ($part_v_total > 0) {
                echo '<div style="background: #e65100; color: white; padding: 15px; margin-bottom: 20px; border-radius: 5px;">';
                echo '<h4 style="margin: 0; color: white;">Part V Total: <strong>$' . number_format($part_v_total, 2) . '</strong></h4>';
                echo '</div>';
            }

            // Display Grand Total for All Expenses
            $grand_total = $part_ii_total + $part_v_total;
            if ($grand_total > 0) {
                echo '<div style="background: #2e7d32; color: white; padding: 20px; margin: 30px 0 20px 0; border-radius: 5px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">';
                echo '<h3 style="margin: 0; color: white; font-size: 24px;">Total Business Expenses: <strong>$' . number_format($grand_total, 2) . '</strong></h3>';
                echo '<p style="margin: 10px 0 0 0; font-size: 14px; color: #e8f5e9;">Part II: $' . number_format($part_ii_total, 2) . ' + Part V: $' . number_format($part_v_total, 2) . '</p>';
                echo '</div>';
            }

        } else {
            echo '<p>No expenses found for ' . esc_html($selected_year) . '.</p>';
        }

        // Display WooCommerce Invoices Table
        echo '<h2 style="margin-top: 40px; font-size: 28px; color: #000; background: #f0f0f0; padding: 15px; border-left: 6px solid #1565c0; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">💰 Income - WooCommerce Invoices for ' . esc_html($selected_year) . ' (Fully Paid & Partial Payments)</h2>';

        if (!empty($invoices)) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>Order ID</th>';
            echo '<th>Invoice Date</th>';
            echo '<th>Payment Date</th>';
            echo '<th>Client</th>';
            echo '<th>Status</th>';
            echo '<th>Total Amount</th>';
            echo '<th>Payment Amount</th>';
            echo '<th>Items</th>';
            echo '</tr></thead>';
            echo '<tbody>';

            $total_invoiced = 0;
            $total_paid = 0;

            foreach ($invoices as $invoice) {
                $payment_amount = floatval($invoice->payment_amount);
                $display_payment = ($payment_amount > 0) ? $payment_amount : floatval($invoice->total_amount);
                $payment_date = !empty($invoice->payment_date) ? $invoice->payment_date : 'N/A';

                $total_invoiced += floatval($invoice->total_amount);
                $total_paid += $display_payment;

                echo '<tr>';
                echo '<td><a href="' . admin_url('post.php?post=' . $invoice->order_id . '&action=edit') . '" target="_blank">#' . esc_html($invoice->order_id) . '</a></td>';
                echo '<td>' . esc_html($invoice->date_created) . '</td>';
                echo '<td><strong>' . esc_html($payment_date) . '</strong></td>';
                echo '<td>' . esc_html($invoice->client_name) . '</td>';
                echo '<td>' . esc_html(ucfirst(str_replace('wc-', '', $invoice->status))) . '</td>';
                echo '<td>$' . number_format(floatval($invoice->total_amount), 2) . '</td>';
                echo '<td>$' . number_format($display_payment, 2) . '</td>';
                echo '<td>' . intval($invoice->item_count) . ' items</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';

            // Display Invoice Totals Banner
            echo '<div style="background: #1565c0; color: white; padding: 20px; margin: 20px 0; border-radius: 5px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">';
            echo '<h3 style="margin: 0; color: white; font-size: 24px;">Total Invoices: <strong>' . count($invoices) . '</strong></h3>';
            echo '<div style="display: flex; justify-content: center; gap: 40px; margin-top: 15px;">';
            echo '<div>';
            echo '<p style="margin: 0; font-size: 14px; color: #e3f2fd;">Total Amount Invoiced</p>';
            echo '<p style="margin: 5px 0 0 0; font-size: 20px; font-weight: bold; color: white;">$' . number_format($total_invoiced, 2) . '</p>';
            echo '</div>';
            echo '<div>';
            echo '<p style="margin: 0; font-size: 14px; color: #e3f2fd;">Total Amount Paid</p>';
            echo '<p style="margin: 5px 0 0 0; font-size: 20px; font-weight: bold; color: white;">$' . number_format($total_paid, 2) . '</p>';
            echo '</div>';
            echo '</div>';
            echo '</div>';

        } else {
            echo '<p>No paid WooCommerce invoices found for ' . esc_html($selected_year) . '.</p>';
        }

        // Export buttons
        echo '<div style="margin-top: 30px; display: flex; gap: 10px;">';
        echo '<a href="' . admin_url('admin.php?page=' . $_GET['page'] . '&report_slug=yearly_tax_report&year=' . $selected_year . '&export=csv') . '" class="button button-primary">📊 Export to CSV</a>';
        echo '<button type="button" class="button button-primary" onclick="window.print();">🖨️ Print to PDF</button>';
        echo '</div>';

        // Handle exports
        if (isset($_GET['export'])) {
            if ($_GET['export'] === 'csv') {
                $this->export_yearly_tax_report_csv($selected_year, $expenses, $invoices);
            }
        }
    }

    /**
     * Get time entries for a specific year
     */
    private function get_yearly_time_entries($year) {
        global $wpdb;

        $time_entry_table = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';
        $project_table = $wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker';
        $member_table = $wpdb->prefix . TIMEGROW_PREFIX . 'team_member_tracker';
        $user_table = $wpdb->prefix . 'users';

        $query = $wpdb->prepare(
            "SELECT t.*,
                    p.name as project_name,
                    m.name as member_name,
                    u.display_name as client_name,
                    COALESCE(t.date, DATE(t.clock_in_date)) as entry_date,
                    CASE
                        WHEN t.hours IS NOT NULL AND t.hours > 0 THEN t.hours
                        WHEN t.clock_in_date IS NOT NULL AND t.clock_out_date IS NOT NULL
                        THEN TIMESTAMPDIFF(SECOND, t.clock_in_date, t.clock_out_date) / 3600
                        ELSE 0
                    END as calculated_hours
             FROM {$time_entry_table} t
             INNER JOIN {$project_table} p ON t.project_id = p.ID
             INNER JOIN {$member_table} m ON t.member_id = m.ID
             INNER JOIN {$user_table} u ON p.client_id = u.ID
             WHERE YEAR(COALESCE(t.date, t.clock_in_date)) = %d AND t.status = 1
             ORDER BY COALESCE(t.date, t.clock_in_date) ASC",
            $year
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get expenses for a specific year
     */
    private function get_yearly_expenses($year) {
        global $wpdb;

        $expense_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
        $category_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_categories';

        $query = $wpdb->prepare(
            "SELECT e.*,
                    ec.name as category_name,
                    ec.slug as category_slug,
                    ec.schedule_c_part
             FROM {$expense_table} e
             LEFT JOIN {$category_table} ec ON e.expense_category_id = ec.ID
             WHERE YEAR(e.expense_date) = %d AND e.status = 1
             ORDER BY e.expense_date ASC",
            $year
        );

        return $wpdb->get_results($query);
    }

    /**
     * Get WooCommerce invoices for a specific year (paid and partially paid invoices)
     * Uses payment date (cash basis) not invoice creation date
     */
    private function get_yearly_invoices($year) {
        global $wpdb;

        $orders_table = $wpdb->prefix . 'wc_orders';
        $user_table = $wpdb->prefix . 'users';
        $ordermeta_table = $wpdb->prefix . 'wc_orders_meta';

        $query = $wpdb->prepare(
            "SELECT o.id as order_id,
                    o.date_created_gmt as date_created,
                    o.customer_id,
                    u.display_name as client_name,
                    o.status,
                    o.total_amount,
                    o.payment_method,
                    o.payment_method_title,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_items WHERE order_id = o.id) as item_count,
                    (SELECT meta_value FROM {$ordermeta_table} WHERE order_id = o.id AND meta_key = '_payment_amount' LIMIT 1) as payment_amount,
                    (SELECT meta_value FROM {$ordermeta_table} WHERE order_id = o.id AND meta_key = '_payment_date' LIMIT 1) as payment_date
             FROM {$orders_table} o
             LEFT JOIN {$user_table} u ON o.customer_id = u.ID
             WHERE o.type = 'shop_order'
             AND (o.status = 'wc-invoice_paid' OR o.status = 'invoice_paid' OR o.status = 'wc-partial_paid' OR o.status = 'partial_paid')
             AND (
                 (SELECT meta_value FROM {$ordermeta_table} WHERE order_id = o.id AND meta_key = '_payment_date' LIMIT 1) IS NOT NULL
                 AND YEAR(STR_TO_DATE((SELECT meta_value FROM {$ordermeta_table} WHERE order_id = o.id AND meta_key = '_payment_date' LIMIT 1), '%%Y-%%m-%%d')) = %d
             )
             ORDER BY (SELECT meta_value FROM {$ordermeta_table} WHERE order_id = o.id AND meta_key = '_payment_date' LIMIT 1) DESC",
            $year
        );

        return $wpdb->get_results($query);
    }

    /**
     * Export yearly tax report to PDF
     */
    private function export_yearly_tax_report_pdf($year, $time_entries, $expenses, $total_hours, $total_billable_hours, $total_expenses, $invoices, $total_invoice_amount) {
        self::begin_download(); // this report prints its page before exporting
        // Check if DomPDF is available (optional, can be installed via composer)
        // For now, we'll use a simple HTML approach that works with wkhtmltopdf or browser print

        $filename = 'tax_report_' . $year . '_' . date('Y-m-d') . '.pdf';

        // Set headers for PDF download (browser will handle the conversion)
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . $filename . '"');

        // Generate HTML for PDF
        $html = $this->generate_pdf_html($year, $time_entries, $expenses, $total_hours, $total_billable_hours, $total_expenses, $invoices, $total_invoice_amount);

        echo $html;
        exit;
    }

    /**
     * Generate HTML for PDF export
     */
    private function generate_pdf_html($year, $time_entries, $expenses, $total_hours, $total_billable_hours, $total_expenses, $invoices, $total_invoice_amount) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Tax Report <?php echo esc_html($year); ?></title>
            <style>
                @page {
                    margin: 1.5cm;
                    size: A4 landscape;
                }
                body {
                    font-family: Arial, sans-serif;
                    font-size: 9pt;
                    line-height: 1.3;
                    color: #000;
                    margin: 0;
                    padding: 10px;
                }
                h1 {
                    font-size: 18pt;
                    margin-bottom: 5px;
                    color: #000;
                    text-align: center;
                    border-bottom: 2px solid #0073aa;
                    padding-bottom: 8px;
                }
                .report-meta {
                    text-align: center;
                    margin-bottom: 20px;
                    font-size: 8pt;
                    color: #666;
                }
                .summary-section {
                    display: table;
                    width: 100%;
                    margin-bottom: 20px;
                    page-break-inside: avoid;
                }
                .summary-card {
                    display: table-cell;
                    width: 33.33%;
                    padding: 10px;
                    text-align: center;
                    border: 1px solid #0073aa;
                    background: #f0f8ff;
                }
                .summary-card h3 {
                    margin: 0 0 5px 0;
                    font-size: 9pt;
                    color: #0073aa;
                }
                .summary-card .value {
                    font-size: 14pt;
                    font-weight: bold;
                    color: #000;
                }
                h2 {
                    font-size: 12pt;
                    margin-top: 20px;
                    margin-bottom: 10px;
                    color: #000;
                    border-bottom: 2px solid #333;
                    padding-bottom: 3px;
                    page-break-after: avoid;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                    font-size: 7pt;
                    page-break-inside: auto;
                    table-layout: fixed;
                }
                thead {
                    display: table-header-group;
                }
                tr {
                    page-break-inside: avoid;
                    page-break-after: auto;
                }
                th {
                    background-color: #0073aa;
                    color: #fff;
                    padding: 6px 4px;
                    text-align: left;
                    font-weight: bold;
                    border: 1px solid #005177;
                    font-size: 7pt;
                    word-wrap: break-word;
                }
                td {
                    padding: 4px;
                    border: 1px solid #ddd;
                    word-wrap: break-word;
                    overflow-wrap: break-word;
                    vertical-align: top;
                }
                tbody tr:nth-child(even) {
                    background-color: #f9f9f9;
                }

                /* Specific column widths for time entries table */
                table.time-entries th:nth-child(1),
                table.time-entries td:nth-child(1) { width: 8%; } /* Date */
                table.time-entries th:nth-child(2),
                table.time-entries td:nth-child(2) { width: 15%; } /* Project */
                table.time-entries th:nth-child(3),
                table.time-entries td:nth-child(3) { width: 12%; } /* Client */
                table.time-entries th:nth-child(4),
                table.time-entries td:nth-child(4) { width: 12%; } /* Team Member */
                table.time-entries th:nth-child(5),
                table.time-entries td:nth-child(5) { width: 6%; } /* Hours */
                table.time-entries th:nth-child(6),
                table.time-entries td:nth-child(6) { width: 6%; } /* Billed */
                table.time-entries th:nth-child(7),
                table.time-entries td:nth-child(7) { width: 41%; } /* Description */

                /* Specific column widths for expenses table */
                table.expenses th:nth-child(1),
                table.expenses td:nth-child(1) { width: 8%; } /* Date */
                table.expenses th:nth-child(2),
                table.expenses td:nth-child(2) { width: 12%; } /* Name */
                table.expenses th:nth-child(3),
                table.expenses td:nth-child(3) { width: 12%; } /* Category */
                table.expenses th:nth-child(4),
                table.expenses td:nth-child(4) { width: 8%; } /* Amount */
                table.expenses th:nth-child(5),
                table.expenses td:nth-child(5) { width: 10%; } /* Payment Method */
                table.expenses th:nth-child(6),
                table.expenses td:nth-child(6) { width: 10%; } /* Assigned To */
                table.expenses th:nth-child(7),
                table.expenses td:nth-child(7) { width: 40%; } /* Description */

                /* Specific column widths for invoices table */
                table.invoices th:nth-child(1),
                table.invoices td:nth-child(1) { width: 8%; } /* Order ID */
                table.invoices th:nth-child(2),
                table.invoices td:nth-child(2) { width: 10%; } /* Date */
                table.invoices th:nth-child(3),
                table.invoices td:nth-child(3) { width: 15%; } /* Client */
                table.invoices th:nth-child(4),
                table.invoices td:nth-child(4) { width: 10%; } /* Status */
                table.invoices th:nth-child(5),
                table.invoices td:nth-child(5) { width: 10%; } /* Total Amount */
                table.invoices th:nth-child(6),
                table.invoices td:nth-child(6) { width: 15%; } /* Payment Method */
                table.invoices th:nth-child(7),
                table.invoices td:nth-child(7) { width: 12%; } /* Items */

                .footer {
                    margin-top: 30px;
                    padding-top: 15px;
                    border-top: 1px solid #ccc;
                    text-align: center;
                    font-size: 7pt;
                    color: #666;
                }
                @media print {
                    body { padding: 0; }
                }
            </style>
        </head>
        <body>
            <h1>Yearly Tax Report - <?php echo esc_html($year); ?></h1>
            <div class="report-meta">
                Generated on <?php echo date('F j, Y \a\t g:i A'); ?><br>
                <?php echo esc_html(get_bloginfo('name')); ?>
            </div>

            <div class="summary-section">
                <div class="summary-card">
                    <h3>Billable Hours</h3>
                    <div class="value"><?php echo number_format($total_billable_hours, 2); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Total Expenses</h3>
                    <div class="value">$<?php echo number_format($total_expenses, 2); ?></div>
                </div>
                <div class="summary-card">
                    <h3>Paid Invoices</h3>
                    <div class="value">$<?php echo number_format($total_invoice_amount, 2); ?></div>
                </div>
            </div>

            <h2>Billable Time Entries for <?php echo esc_html($year); ?></h2>
            <?php
            // Filter only billable entries
            $billable_entries = array_filter($time_entries, function($entry) {
                return $entry->billable == 1;
            });
            ?>
            <?php if (!empty($billable_entries)) : ?>
                <table class="time-entries">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Project</th>
                            <th>Client</th>
                            <th>Team Member</th>
                            <th>Hours</th>
                            <th>Billed</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($billable_entries as $entry) : ?>
                            <tr>
                                <td><?php echo esc_html($entry->date); ?></td>
                                <td><?php echo esc_html($entry->project_name); ?></td>
                                <td><?php echo esc_html($entry->client_name); ?></td>
                                <td><?php echo esc_html($entry->member_name); ?></td>
                                <td><?php echo number_format(floatval($entry->hours), 2); ?></td>
                                <td><?php echo $entry->billed ? 'Yes' : 'No'; ?></td>
                                <td><?php echo esc_html($entry->description); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No billable time entries found for <?php echo esc_html($year); ?>.</p>
            <?php endif; ?>

            <h2>Expenses for <?php echo esc_html($year); ?></h2>
            <?php if (!empty($expenses)) : ?>
                <table class="expenses">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Name</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Assigned To</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense) : ?>
                            <tr>
                                <td><?php echo esc_html($expense->expense_date); ?></td>
                                <td><?php echo esc_html($expense->expense_name); ?></td>
                                <td><?php echo esc_html($expense->category_name); ?></td>
                                <td>$<?php echo number_format(floatval($expense->amount), 2); ?></td>
                                <td><?php echo esc_html(ucwords(str_replace('_', ' ', $expense->expense_payment_method))); ?></td>
                                <td><?php echo esc_html(ucfirst($expense->assigned_to)); ?></td>
                                <td><?php echo esc_html($expense->expense_description); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No expenses found for <?php echo esc_html($year); ?>.</p>
            <?php endif; ?>

            <h2>WooCommerce Invoices for <?php echo esc_html($year); ?> (Paid Only)</h2>
            <?php if (!empty($invoices)) : ?>
                <table class="invoices">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Status</th>
                            <th>Total Amount</th>
                            <th>Payment Method</th>
                            <th>Items</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice) : ?>
                            <tr>
                                <td>#<?php echo esc_html($invoice->order_id); ?></td>
                                <td><?php echo esc_html($invoice->date_created); ?></td>
                                <td><?php echo esc_html($invoice->client_name); ?></td>
                                <td><?php echo esc_html(ucfirst(str_replace('wc-', '', $invoice->status))); ?></td>
                                <td>$<?php echo number_format(floatval($invoice->total_amount), 2); ?></td>
                                <td><?php echo esc_html($invoice->payment_method_title); ?></td>
                                <td><?php echo intval($invoice->item_count); ?> items</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p>No paid WooCommerce invoices found for <?php echo esc_html($year); ?>.</p>
            <?php endif; ?>

            <div class="footer">
                <strong>End of Report</strong><br>
                This is an automatically generated tax report from <?php echo esc_html(get_bloginfo('name')); ?>
            </div>

            <script>
                // Auto-print when page loads (optional - can be removed)
                window.onload = function() {
                    window.print();
                }
            </script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Export yearly tax report to CSV
     */
    private function export_yearly_tax_report_csv($year, $expenses, $invoices) {
        self::begin_download(); // this report prints its page before exporting
        $filename = 'tax_report_' . $year . '_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Expenses Section
        fputcsv($output, ['EXPENSES - ' . $year]);
        fputcsv($output, ['Date', 'Name', 'Category', 'Amount', 'Payment Method', 'Assigned To', 'Description']);

        foreach ($expenses as $expense) {
            fputcsv($output, [
                $expense->expense_date,
                $expense->expense_name,
                $expense->category_name,
                number_format(floatval($expense->amount), 2),
                ucwords(str_replace('_', ' ', $expense->expense_payment_method)),
                ucfirst($expense->assigned_to),
                $expense->expense_description
            ]);
        }

        // Blank row
        fputcsv($output, []);

        // WooCommerce Invoices Section
        fputcsv($output, ['WOOCOMMERCE INVOICES - ' . $year]);
        fputcsv($output, ['Order ID', 'Date', 'Client', 'Status', 'Total Amount', 'Payment Method', 'Items']);

        foreach ($invoices as $invoice) {
            fputcsv($output, [
                $invoice->order_id,
                $invoice->date_created,
                $invoice->client_name,
                ucfirst($invoice->status),
                number_format(floatval($invoice->total_amount), 2),
                $invoice->payment_method_title,
                $invoice->item_count . ' items'
            ]);
        }

        fclose($output);
        exit;
    }
}
