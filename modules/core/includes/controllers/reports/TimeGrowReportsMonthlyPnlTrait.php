<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Monthly Profit & Loss: page, data, CSV export.
 * Used by TimeGrowReportsController (methods moved as-is from the controller).
 */
trait TimeGrowReportsMonthlyPnlTrait {

    /**
     * Generate Monthly Profit & Loss Report
     * Shows monthly breakdown of income and expenses with year-to-date totals
     */
    private function generate_monthly_profit_loss() {
        global $wpdb;

        // Get selected year from request or default to current year
        $selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

        // Handle CSV export
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            $this->export_monthly_pnl_csv($selected_year);
            return;
        }

        // Get available years from database
        $expense_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
        $available_years_query = "
            SELECT DISTINCT YEAR(expense_date) as year FROM {$expense_table} WHERE expense_date IS NOT NULL
            ORDER BY year DESC
        ";
        $available_years = $wpdb->get_col($available_years_query);

        // Add print styles (similar to tax report)
        echo '<style>
            @media print {
                /* CRITICAL: Override WordPress responsive table behavior */
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

                table.wp-list-table td::before,
                table.wp-list-table td[data-colname]::before {
                    display: none !important;
                    content: none !important;
                }

                @page {
                    margin: 1cm;
                    size: A4 landscape;
                }

                body {
                    background: white !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    max-width: 100% !important;
                    width: 100% !important;
                }

                .wrap {
                    max-width: 100% !important;
                    width: 100% !important;
                    margin: 0 !important;
                    padding: 0 !important;
                }

                table.wp-list-table {
                    width: 100% !important;
                    max-width: 100% !important;
                    table-layout: auto !important;
                    border-collapse: collapse !important;
                }

                table.wp-list-table th,
                table.wp-list-table td {
                    border: 1px solid #000 !important;
                    padding: 4px 6px !important;
                    text-align: left !important;
                    font-size: 8pt !important;
                    width: auto !important;
                    max-width: none !important;
                    word-wrap: break-word !important;
                }

                table.wp-list-table thead th {
                    background-color: #f0f0f0 !important;
                    font-weight: bold !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }

                .no-print,
                #wpadminbar,
                #adminmenuback,
                #adminmenuwrap,
                .notice,
                button,
                input[type="submit"],
                .timegrow-debug,
                .timegrow-kpi-debug,
                .update-nag,
                .updated,
                .error,
                form,
                select {
                    display: none !important;
                }

                h1, h2, h3 {
                    page-break-after: avoid !important;
                    margin-top: 10px !important;
                    margin-bottom: 10px !important;
                    -webkit-print-color-adjust: exact !important;
                    print-color-adjust: exact !important;
                }

                .widefat {
                    page-break-inside: avoid !important;
                }
            }

            .pnl-table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
            }

            .pnl-table th,
            .pnl-table td {
                padding: 10px;
                text-align: right;
                border: 1px solid #ddd;
            }

            .pnl-table th {
                background-color: #f5f5f5;
                font-weight: bold;
            }

            .pnl-table td:first-child,
            .pnl-table th:first-child {
                text-align: left;
            }

            .pnl-table .category-row td {
                font-weight: 600;
                background-color: #fafafa;
            }

            .pnl-table .section-header td {
                font-weight: bold;
                background-color: #e8f4f8;
                font-size: 1.1em;
            }

            .pnl-table .income-header td {
                font-weight: bold;
                background-color: #d4edda;
                font-size: 1.1em;
            }

            .pnl-table .expense-header td {
                font-weight: bold;
                background-color: #f8d7da;
                font-size: 1.1em;
            }

            .pnl-table .total-row td {
                font-weight: bold;
                background-color: #d4edda;
                border-top: 2px solid #333;
            }

            .pnl-table .income-total td {
                font-weight: 600;
                background-color: #d4edda;
                border-top: 1px solid #666;
            }

            .pnl-table .expense-total td {
                font-weight: 600;
                background-color: #f8d7da;
                border-top: 1px solid #666;
            }

            .pnl-table .subtotal-row td {
                font-weight: 600;
                background-color: #f0f0f0;
                border-top: 1px solid #666;
            }

            .pnl-table .net-income-row td {
                font-weight: bold;
                background-color: #cce5ff;
                border-top: 3px double #000;
                font-size: 1.1em;
            }

            .negative {
                color: #d9534f;
            }

            .positive {
                color: #5cb85c;
            }
        </style>';

        // Get company info
        $company_name = get_bloginfo('name');
        $company_tagline = get_bloginfo('description');

        // Report Header
        echo '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; margin-bottom: 30px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">';
        echo '<h1 style="margin: 0 0 10px 0; font-size: 36px; color: white; text-align: center; font-weight: bold;">📊 Monthly Profit & Loss Report ' . esc_html($selected_year) . '</h1>';
        echo '<p style="margin: 0; font-size: 18px; text-align: center; color: #f0f0f0;">' . esc_html($company_name) . '</p>';
        if (!empty($company_tagline)) {
            echo '<p style="margin: 5px 0 0 0; font-size: 14px; text-align: center; color: #e0e0e0;">' . esc_html($company_tagline) . '</p>';
        }
        echo '<p style="margin: 10px 0 0 0; font-size: 12px; text-align: center; color: #d0d0d0;">Generated on ' . date('F j, Y') . '</p>';
        echo '</div>';

        // Year selector form
        echo '<div class="pnl-report-controls" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">';
        echo '<form method="get" action="" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">';
        echo '<input type="hidden" name="page" value="' . esc_attr($_GET['page']) . '" />';
        echo '<input type="hidden" name="report_slug" value="monthly_profit_loss" />';
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
        echo '<p style="margin: 0; font-size: 13px; color: #1565c0; line-height: 1.6;"><strong>📊 Cash Basis Accounting:</strong> This report uses the <strong>cash basis method</strong>. Income is recorded when payment is <strong>received</strong> (not when invoiced), and expenses are recorded when <strong>paid</strong> (not when incurred).</p>';
        echo '<p style="margin: 8px 0 0 0; font-size: 13px; color: #1565c0; line-height: 1.6;"><strong>💰 Monthly Breakdown:</strong> This P&L report shows your income and expenses broken down by month, with category-level detail for expenses.</p>';
        echo '</div>';
        echo '</div>';

        // Get monthly data
        $monthly_data = $this->get_monthly_pnl_data($selected_year);

        // Debug output
        if (timegrow_debug()):
            $orders_table = $wpdb->prefix . 'wc_orders';
            $ordermeta_table = $wpdb->prefix . 'wc_orders_meta';
            $expense_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
            $category_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_categories';

            $invoice_sql = $wpdb->prepare("
                SELECT
                    MONTH(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d')) as month,
                    SUM(COALESCE(
                        (SELECT meta_value FROM {$ordermeta_table} WHERE order_id = o.id AND meta_key = '_payment_amount' LIMIT 1),
                        o.total_amount
                    )) as total
                FROM {$orders_table} o
                INNER JOIN {$ordermeta_table} pm ON o.id = pm.order_id AND pm.meta_key = '_payment_date'
                WHERE o.type = 'shop_order'
                AND (o.status = 'wc-invoice_paid' OR o.status = 'invoice_paid' OR o.status = 'wc-partial_paid' OR o.status = 'partial_paid')
                AND pm.meta_value IS NOT NULL
                AND YEAR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d')) = %d
                GROUP BY MONTH(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d'))
            ", $selected_year);

            $expense_sql = $wpdb->prepare("
                SELECT
                    MONTH(e.expense_date) as month,
                    ec.ID as category_id,
                    ec.name as category_name,
                    ec.slug as category_slug,
                    ec.parent_id,
                    parent.name as parent_name,
                    parent.schedule_c_part,
                    SUM(e.amount) as total
                FROM {$expense_table} e
                LEFT JOIN {$category_table} ec ON e.expense_category_id = ec.ID
                LEFT JOIN {$category_table} parent ON ec.parent_id = parent.ID
                WHERE YEAR(e.expense_date) = %d
                GROUP BY MONTH(e.expense_date), e.expense_category_id, ec.ID, ec.name, ec.slug, ec.parent_id, parent.name, parent.schedule_c_part
                ORDER BY parent.sort_order, parent.name, ec.sort_order, ec.name, MONTH(e.expense_date)
            ", $selected_year);
        ?>
            <div class="timegrow-debug" style="background: #f8f9fa; border: 2px solid #0073aa; padding: 20px; margin: 20px 0; border-radius: 5px;">
                <h3 style="margin-top: 0; color: #0073aa;">📊 SQL Queries - Monthly P&L</h3>

                <h4 style="color: #0073aa; margin-top: 15px;">Invoice Query:</h4>
                <pre style="background: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; line-height: 1.5;"><?php echo esc_html($invoice_sql); ?></pre>
                <p><strong>Results:</strong> <?php echo count($monthly_data['income']['invoices']); ?> months with invoices</p>
                <pre style="background: #fff; padding: 10px; border: 1px solid #ddd; font-size: 11px;"><?php print_r($monthly_data['income']['invoices']); ?></pre>

                <h4 style="color: #0073aa; margin-top: 15px;">Expense Query:</h4>
                <pre style="background: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; line-height: 1.5;"><?php echo esc_html($expense_sql); ?></pre>
                <p><strong>Results:</strong> <?php echo count($monthly_data['expenses']); ?> expense categories found</p>
                <pre style="background: #fff; padding: 10px; border: 1px solid #ddd; font-size: 11px;"><?php print_r($monthly_data['expenses']); ?></pre>

                <?php if ($wpdb->last_error): ?>
                    <p style="color: red;"><strong>Last Error:</strong> <?php echo esc_html($wpdb->last_error); ?></p>
                <?php endif; ?>
            </div>
        <?php endif;

        // Display P&L Table
        echo '<table class="pnl-table wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th style="width: 200px;">Category</th>';
        echo '<th>Jan</th>';
        echo '<th>Feb</th>';
        echo '<th>Mar</th>';
        echo '<th>Apr</th>';
        echo '<th>May</th>';
        echo '<th>Jun</th>';
        echo '<th>Jul</th>';
        echo '<th>Aug</th>';
        echo '<th>Sep</th>';
        echo '<th>Oct</th>';
        echo '<th>Nov</th>';
        echo '<th>Dec</th>';
        echo '<th style="background-color: #d4edda;"><strong>Total</strong></th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        // INCOME SECTION
        echo '<tr class="income-header"><td colspan="14"><strong>INCOME</strong></td></tr>';

        // WooCommerce Invoices
        $invoice_row = $monthly_data['income']['invoices'];
        echo '<tr>';
        echo '<td>WooCommerce Invoices</td>';
        $year_total = 0;
        for ($month = 1; $month <= 12; $month++) {
            $amount = isset($invoice_row[$month]) ? $invoice_row[$month] : 0;
            $year_total += $amount;
            echo '<td>$' . number_format($amount, 2) . '</td>';
        }
        echo '<td><strong>$' . number_format($year_total, 2) . '</strong></td>';
        echo '</tr>';

        // Total Income
        echo '<tr class="income-total">';
        echo '<td><strong>Total Income</strong></td>';
        $total_income_year = 0;
        for ($month = 1; $month <= 12; $month++) {
            $amount = isset($invoice_row[$month]) ? $invoice_row[$month] : 0;
            $total_income_year += $amount;
            echo '<td><strong>$' . number_format($amount, 2) . '</strong></td>';
        }
        echo '<td><strong>$' . number_format($total_income_year, 2) . '</strong></td>';
        echo '</tr>';

        // EXPENSES SECTION
        echo '<tr class="expense-header"><td colspan="14"><strong>EXPENSES</strong></td></tr>';

        $total_expense_by_month = array_fill(1, 12, 0);
        $expense_parents = $monthly_data['expenses'];

        foreach ($expense_parents as $parent_name => $parent_data) {
            // Parent category header
            $schedule_part = isset($parent_data['schedule_c_part']) ? $parent_data['schedule_c_part'] : '';
            $header_color = ($schedule_part === 'Part II') ? '#e3f2fd' : '#fff3e0';
            echo '<tr style="background-color: ' . $header_color . ';">';
            echo '<td colspan="14"><strong>' . esc_html($parent_name) . '</strong></td>';
            echo '</tr>';

            // Track parent totals
            $parent_total_by_month = array_fill(1, 12, 0);

            // Child categories
            foreach ($parent_data['categories'] as $category => $months) {
                echo '<tr>';
                echo '<td style="padding-left: 20px;">' . esc_html($this->format_category_name($category)) . '</td>';
                $category_total = 0;
                for ($month = 1; $month <= 12; $month++) {
                    $amount = isset($months[$month]) ? $months[$month] : 0;
                    $category_total += $amount;
                    $parent_total_by_month[$month] += $amount;
                    $total_expense_by_month[$month] += $amount;
                    echo '<td>$' . number_format($amount, 2) . '</td>';
                }
                echo '<td><strong>$' . number_format($category_total, 2) . '</strong></td>';
                echo '</tr>';
            }

            // Parent subtotal
            echo '<tr style="background-color: ' . $header_color . ';">';
            echo '<td><strong>' . esc_html($parent_name) . ' Total</strong></td>';
            $parent_year_total = 0;
            for ($month = 1; $month <= 12; $month++) {
                $amount = $parent_total_by_month[$month];
                $parent_year_total += $amount;
                echo '<td><strong>$' . number_format($amount, 2) . '</strong></td>';
            }
            echo '<td><strong>$' . number_format($parent_year_total, 2) . '</strong></td>';
            echo '</tr>';
        }

        // Total Expenses
        echo '<tr class="expense-total">';
        echo '<td><strong>Total Expenses</strong></td>';
        $total_expenses_year = 0;
        for ($month = 1; $month <= 12; $month++) {
            $amount = $total_expense_by_month[$month];
            $total_expenses_year += $amount;
            echo '<td><strong>$' . number_format($amount, 2) . '</strong></td>';
        }
        echo '<td><strong>$' . number_format($total_expenses_year, 2) . '</strong></td>';
        echo '</tr>';

        // NET INCOME
        echo '<tr class="net-income-row">';
        echo '<td><strong>NET INCOME</strong></td>';
        $net_income_year = 0;
        for ($month = 1; $month <= 12; $month++) {
            $income = isset($invoice_row[$month]) ? $invoice_row[$month] : 0;
            $expense = $total_expense_by_month[$month];
            $net = $income - $expense;
            $net_income_year += $net;
            $class = $net < 0 ? 'negative' : 'positive';
            echo '<td class="' . $class . '"><strong>$' . number_format($net, 2) . '</strong></td>';
        }
        $class = $net_income_year < 0 ? 'negative' : 'positive';
        echo '<td class="' . $class . '"><strong>$' . number_format($net_income_year, 2) . '</strong></td>';
        echo '</tr>';

        echo '</tbody>';
        echo '</table>';

        // Export buttons
        echo '<div style="margin-top: 30px; display: flex; gap: 10px;">';
        echo '<a href="' . admin_url('admin.php?page=' . $_GET['page'] . '&report_slug=monthly_profit_loss&year=' . $selected_year . '&export=csv') . '" class="button button-primary">📊 Export to CSV</a>';
        echo '<button type="button" class="button button-primary" onclick="window.print();">🖨️ Print to PDF</button>';
        echo '</div>';
    }

    /**
     * Get monthly P&L data for a specific year
     */
    private function get_monthly_pnl_data($year) {
        global $wpdb;

        $data = [
            'income' => [
                'invoices' => []
            ],
            'expenses' => []
        ];

        // Get WooCommerce invoice data by month (cash basis - using payment date)
        $orders_table = $wpdb->prefix . 'wc_orders';
        $ordermeta_table = $wpdb->prefix . 'wc_orders_meta';
        $invoice_query = "
            SELECT
                MONTH(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d')) as month,
                SUM(COALESCE(
                    (SELECT meta_value FROM {$ordermeta_table} WHERE order_id = o.id AND meta_key = '_payment_amount' LIMIT 1),
                    o.total_amount
                )) as total
            FROM {$orders_table} o
            INNER JOIN {$ordermeta_table} pm ON o.id = pm.order_id AND pm.meta_key = '_payment_date'
            WHERE o.type = 'shop_order'
            AND (o.status = 'wc-invoice_paid' OR o.status = 'invoice_paid' OR o.status = 'wc-partial_paid' OR o.status = 'partial_paid')
            AND pm.meta_value IS NOT NULL
            AND YEAR(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d')) = %d
            GROUP BY MONTH(STR_TO_DATE(pm.meta_value, '%%Y-%%m-%%d'))
        ";
        $invoice_results = $wpdb->get_results($wpdb->prepare($invoice_query, $year));

        foreach ($invoice_results as $row) {
            $data['income']['invoices'][$row->month] = floatval($row->total);
        }

        // Get expense data by month and category with parent category info
        $expense_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker';
        $category_table = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_categories';
        $expense_query = "
            SELECT
                MONTH(e.expense_date) as month,
                ec.ID as category_id,
                ec.name as category_name,
                ec.slug as category_slug,
                ec.parent_id,
                parent.name as parent_name,
                parent.schedule_c_part,
                SUM(e.amount) as total
            FROM {$expense_table} e
            LEFT JOIN {$category_table} ec ON e.expense_category_id = ec.ID
            LEFT JOIN {$category_table} parent ON ec.parent_id = parent.ID
            WHERE YEAR(e.expense_date) = %d
            GROUP BY MONTH(e.expense_date), e.expense_category_id, ec.ID, ec.name, ec.slug, ec.parent_id, parent.name, parent.schedule_c_part
            ORDER BY parent.sort_order, parent.name, ec.sort_order, ec.name, MONTH(e.expense_date)
        ";
        $expense_results = $wpdb->get_results($wpdb->prepare($expense_query, $year));

        foreach ($expense_results as $row) {
            $parent_key = $row->parent_name ? $row->parent_name : 'Uncategorized';
            $category_key = $row->category_name ? $row->category_name : 'Uncategorized';

            if (!isset($data['expenses'][$parent_key])) {
                $data['expenses'][$parent_key] = [
                    'schedule_c_part' => $row->schedule_c_part,
                    'categories' => []
                ];
            }

            if (!isset($data['expenses'][$parent_key]['categories'][$category_key])) {
                $data['expenses'][$parent_key]['categories'][$category_key] = [];
            }

            $data['expenses'][$parent_key]['categories'][$category_key][$row->month] = floatval($row->total);
        }

        return $data;
    }

    /**
     * Format category name for display
     */
    private function format_category_name($category) {
        // Category names now come from database already formatted
        return $category ? $category : 'Uncategorized';
    }

    /**
     * Export monthly P&L to CSV
     */
    private function export_monthly_pnl_csv($year) {
        $filename = 'profit_loss_' . $year . '_' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Get data
        $monthly_data = $this->get_monthly_pnl_data($year);

        // Header row
        $header = ['Category', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Total'];
        fputcsv($output, $header);

        // INCOME Section
        fputcsv($output, ['INCOME']);

        // WooCommerce Invoices
        $invoice_row = $monthly_data['income']['invoices'];
        $row = ['WooCommerce Invoices'];
        $year_total = 0;
        for ($month = 1; $month <= 12; $month++) {
            $amount = isset($invoice_row[$month]) ? $invoice_row[$month] : 0;
            $year_total += $amount;
            $row[] = number_format($amount, 2);
        }
        $row[] = number_format($year_total, 2);
        fputcsv($output, $row);

        // Total Income
        $row = ['Total Income'];
        $total_income_year = 0;
        for ($month = 1; $month <= 12; $month++) {
            $amount = isset($invoice_row[$month]) ? $invoice_row[$month] : 0;
            $total_income_year += $amount;
            $row[] = number_format($amount, 2);
        }
        $row[] = number_format($total_income_year, 2);
        fputcsv($output, $row);

        // Empty row
        fputcsv($output, []);

        // EXPENSES Section
        fputcsv($output, ['EXPENSES']);

        $total_expense_by_month = array_fill(1, 12, 0);
        $expense_categories = $monthly_data['expenses'];

        foreach ($expense_categories as $category => $months) {
            $row = [$this->format_category_name($category)];
            $category_total = 0;
            for ($month = 1; $month <= 12; $month++) {
                $amount = isset($months[$month]) ? $months[$month] : 0;
                $category_total += $amount;
                $total_expense_by_month[$month] += $amount;
                $row[] = number_format($amount, 2);
            }
            $row[] = number_format($category_total, 2);
            fputcsv($output, $row);
        }

        // Total Expenses
        $row = ['Total Expenses'];
        $total_expenses_year = 0;
        for ($month = 1; $month <= 12; $month++) {
            $amount = $total_expense_by_month[$month];
            $total_expenses_year += $amount;
            $row[] = number_format($amount, 2);
        }
        $row[] = number_format($total_expenses_year, 2);
        fputcsv($output, $row);

        // Empty row
        fputcsv($output, []);

        // NET INCOME
        $row = ['NET INCOME'];
        $net_income_year = 0;
        for ($month = 1; $month <= 12; $month++) {
            $income = isset($invoice_row[$month]) ? $invoice_row[$month] : 0;
            $expense = $total_expense_by_month[$month];
            $net = $income - $expense;
            $net_income_year += $net;
            $row[] = number_format($net, 2);
        }
        $row[] = number_format($net_income_year, 2);
        fputcsv($output, $row);

        fclose($output);
        exit;
    }
}
