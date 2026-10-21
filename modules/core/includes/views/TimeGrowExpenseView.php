<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowExpenseView {
    
    public function display_expenses($expenses, $filter_options = [], $current_filters = []) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        // Merge with defaults
        $current_filters = array_merge([
            'orderby' => 'expense_date',
            'order' => 'DESC',
            'filter_assigned_to' => '',
            'filter_date_from' => '',
            'filter_date_to' => '',
            'filter_search' => '',
            'filter_status' => '',
            'filter_category' => '',
        ], $current_filters);

        // Helper function for sortable column headers
        $sortable_link = function($column, $label) use ($current_filters) {
            $order = ($current_filters['orderby'] == $column && $current_filters['order'] == 'ASC') ? 'DESC' : 'ASC';
            $url = add_query_arg(array_merge($current_filters, ['orderby' => $column, 'order' => $order]));
            return sprintf('<a href="%s"><span>%s</span><span class="sorting-indicator"></span></a>', esc_url($url), esc_html($label));
        };

        // Check if any filters are active
        $has_filters = !empty($current_filters['filter_assigned_to']) ||
                       !empty($current_filters['filter_date_from']) || !empty($current_filters['filter_date_to']) ||
                       !empty($current_filters['filter_search']) || (string) $current_filters['filter_status'] !== '' ||
                       !empty($current_filters['filter_category']);
        // The quick ranges keep the other filters and the sort
        $range_base = array_filter([
            'page' => TIMEGROW_PARENT_MENU . '-expenses-list',
            's' => $current_filters['filter_search'],
            'filter_assigned_to' => $current_filters['filter_assigned_to'],
            'filter_status' => $current_filters['filter_status'],
            'filter_category' => (string) $current_filters['filter_category'],
            'orderby' => $current_filters['orderby'], 'order' => $current_filters['order'],
        ], 'strlen');
        ?>
        <div class="wrap timegrow-page">
        <!-- Modern Header -->
        <div class="timegrow-modern-header">
            <div class="timegrow-header-content">
                <h1><?php esc_html_e('Expenses', 'timegrow'); ?></h1>
                <p class="subtitle"><?php esc_html_e('Track and manage your business expenses and receipts', 'timegrow'); ?></p>
            </div>
            <div class="timegrow-header-illustration">
                <span class="dashicons dashicons-money-alt"></span>
            </div>
        </div>

        <div class="tablenav top">
            <div class="alignleft actions">
                <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expense-add'); ?>" class="button button-primary">Add New Expense</a>
            </div>
            <br class="clear">
        </div>

        <!-- Filters: quick ranges and dates, then the filters with the keyword last -->
        <div class="tg-te-filters">
            <div class="tg-te-range-row">
                <?php if (class_exists('TimeGrowDateRanges')) TimeGrowDateRanges::render($range_base, $current_filters['filter_date_from'], $current_filters['filter_date_to'], ['filter_date_from', 'filter_date_to']); ?>
                <div class="tg-te-dates">
                    <label for="filter_date_from" class="screen-reader-text"><?php esc_html_e('From', 'timegrow'); ?></label>
                    <input type="date" id="filter_date_from" name="filter_date_from" value="<?php echo esc_attr($current_filters['filter_date_from']); ?>" title="<?php esc_attr_e('From', 'timegrow'); ?>">
                    <span class="tg-te-to" aria-hidden="true">to</span>
                    <label for="filter_date_to" class="screen-reader-text"><?php esc_html_e('To', 'timegrow'); ?></label>
                    <input type="date" id="filter_date_to" name="filter_date_to" value="<?php echo esc_attr($current_filters['filter_date_to']); ?>" title="<?php esc_attr_e('To', 'timegrow'); ?>">
                </div>
            </div>
            <div class="tg-te-filter-row">
                <select id="filter_assigned_to" name="filter_assigned_to">
                    <option value=""><?php esc_html_e('All Types', 'timegrow'); ?></option>
                    <option value="client" <?php selected($current_filters['filter_assigned_to'], 'client'); ?>><?php esc_html_e('Client', 'timegrow'); ?></option>
                    <option value="project" <?php selected($current_filters['filter_assigned_to'], 'project'); ?>><?php esc_html_e('Project', 'timegrow'); ?></option>
                    <option value="general" <?php selected($current_filters['filter_assigned_to'], 'general'); ?>><?php esc_html_e('General', 'timegrow'); ?></option>
                </select>

                <select id="filter_category" name="filter_category">
                    <option value=""><?php esc_html_e('All Categories', 'timegrow'); ?></option>
                    <?php foreach ((array) ($filter_options['categories'] ?? []) as $heading) : ?>
                        <?php if (!empty($heading->children)) : ?>
                            <optgroup label="<?php echo esc_attr($heading->name . (empty($heading->is_active) ? ' ' . __('(inactive)', 'timegrow') : '')); ?>">
                                <option value="<?php echo (int) $heading->ID; ?>" <?php selected((int) $current_filters['filter_category'], (int) $heading->ID); ?>><?php echo esc_html(sprintf(__('All in %s', 'timegrow'), $heading->name)); ?></option>
                                <?php foreach ($heading->children as $child) : ?>
                                    <option value="<?php echo (int) $child->ID; ?>" <?php selected((int) $current_filters['filter_category'], (int) $child->ID); ?>><?php echo esc_html($child->name . (empty($child->is_active) ? ' ' . __('(inactive)', 'timegrow') : '')); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php else : ?>
                            <option value="<?php echo (int) $heading->ID; ?>" <?php selected((int) $current_filters['filter_category'], (int) $heading->ID); ?>><?php echo esc_html($heading->name . (empty($heading->is_active) ? ' ' . __('(inactive)', 'timegrow') : '')); ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>

                <select id="filter_status" name="filter_status">
                    <option value=""><?php esc_html_e('All Statuses', 'timegrow'); ?></option>
                    <option value="1" <?php selected($current_filters['filter_status'], '1'); ?>><?php esc_html_e('Active', 'timegrow'); ?></option>
                    <option value="0" <?php selected($current_filters['filter_status'], '0'); ?>><?php esc_html_e('Inactive', 'timegrow'); ?></option>
                </select>

                <label for="filter_search" class="screen-reader-text"><?php esc_html_e('Search expenses', 'timegrow'); ?></label>
                <input type="search" id="filter_search" name="s" value="<?php echo esc_attr($current_filters['filter_search']); ?>" placeholder="<?php esc_attr_e('Keyword...', 'timegrow'); ?>">

                <button type="button" id="filter_expenses" class="button button-primary"><?php esc_html_e('Filter', 'timegrow'); ?></button>

                <?php if ($has_filters) : ?>
                    <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expenses-list'); ?>" class="button"><?php esc_html_e('Clear Filters', 'timegrow'); ?></a>
                <?php endif; ?>
            </div>
        </div>
        <style>
            .tg-te-filters { margin: 10px 0 12px; padding: 10px 12px; background: #f9f9f9; border: 1px solid #dcdcde; border-radius: 4px; }
            .tg-te-range-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
            .tg-te-filters .timegrow-date-presets { margin: 0 !important; gap: 4px !important; }
            .tg-te-filters .timegrow-date-presets .button { min-height: 26px; line-height: 24px; padding: 0 8px; font-size: 12px; }
            .tg-te-dates, .tg-te-filter-row { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
            .tg-te-filters select, .tg-te-filters input[type="date"], .tg-te-filters input[type="search"] { min-height: 30px; height: 30px; font-size: 13px; line-height: 28px; margin: 0; }
            .tg-te-filters select { padding: 0 22px 0 8px; max-width: 140px; }
            .tg-te-filters input[type="date"] { padding: 0 6px; width: 130px; }
            .tg-te-filters input[type="search"] { padding: 0 8px; width: 170px; }
            .tg-te-filters .tg-te-filter-row .button { min-height: 30px; line-height: 28px; margin: 0; }
            .tg-te-to { color: #646970; font-size: 12px; }
        </style>

        <table class="wp-list-table widefat fixed striped table-view-list clients">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-name column-primary sortable <?php echo ($current_filters['orderby'] == 'expense_name') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('expense_name', __('Name', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-date sortable <?php echo ($current_filters['orderby'] == 'expense_date') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('expense_date', __('Date', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-amount sortable <?php echo ($current_filters['orderby'] == 'amount') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('amount', __('Amount', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-member"><?php esc_html_e('Team member', 'timegrow'); ?></th>
                    <th scope="col" class="manage-column column-assigned-to sortable <?php echo ($current_filters['orderby'] == 'assigned_to') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('assigned_to', __('Assigned To', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-category sortable <?php echo ($current_filters['orderby'] == 'category') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('category', __('Category', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-status sortable <?php echo ($current_filters['orderby'] == 'status') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('status', __('Status', 'timegrow')); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if ($expenses) : ?>
                    <?php foreach ($expenses as $item) : ?>
                        <?php $date_format = get_option('date_format');
                            $display_date = DateTime::createFromFormat('Y-m-d', $item->expense_date)->format($date_format); ?>
                        <tr>
                            <td class="column-name column-primary" data-colname="Name">
                                <strong><?php echo esc_html($item->expense_name); ?></strong>
                                <div class="row-actions visible">
                                    <span class="edit">
                                        <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expense-edit&id=' . $item->ID); ?>" >Edit</a>
                                    </span>
                                </div>
                            </td>
                            <td class="column-date" data-colname="Date"><?php echo esc_html($display_date); ?></td>
                            <td class="column-amount" data-colname="Amount">$<?php echo number_format($item->amount, 2); ?></td>
                            <td class="column-member" data-colname="<?php esc_attr_e('Team member', 'timegrow'); ?>"><?php echo esc_html($item->member_name ?? '') ?: '&mdash;'; ?></td>
                            <td class="column-assigned-to" data-colname="Assigned To">
                                <?php
                                if ($item->assigned_to == 'client') {
                                    echo esc_html($item->client_name);
                                } elseif ($item->assigned_to == 'project') {
                                    echo esc_html($item->project_name);
                                } else {
                                    echo 'General';
                                }
                                ?>
                            </td>
                            <td class="column-category" data-colname="Category"><?php echo esc_html($item->category_name ?? 'N/A'); ?></td>
                            <td class="column-status" data-colname="Status">
                                <?php if ((int) ($item->status ?? 1) === 1) : ?>
                                    <span class="timegrow-badge timegrow-badge-active"><?php esc_html_e('Active', 'timegrow'); ?></span>
                                <?php else : ?>
                                    <span class="timegrow-badge timegrow-badge-inactive"><?php esc_html_e('Inactive', 'timegrow'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="7">No expenses found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="col" class="manage-column column-name column-primary">Name</th>
                    <th scope="col" class="manage-column column-date">Date</th>
                    <th scope="col" class="manage-column column-amount">Amount</th>
                    <th scope="col" class="manage-column column-member"><?php esc_html_e('Team member', 'timegrow'); ?></th>
                    <th scope="col" class="manage-column column-assigned-to">Assigned To</th>
                    <th scope="col" class="manage-column column-category">Category</th>
                    <th scope="col" class="manage-column column-status">Status</th>
                </tr>
            </tfoot>
        </table>
    
        <div class="tablenav bottom">
            <div class="alignleft actions">
                <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expense-add'); ?>" class="button button-primary">Add New Expense</a>
            </div>
            <br class="clear">
        </div>
    </div>
    <?php
    }

    public function add_expense($clients) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        ?>
        <div class="wrap timegrow-page">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Add New Expense', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Record a new business expense and upload receipts', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-plus-alt"></span>
                </div>
            </div>
        
            <form id="timeflies-expense-form" name="expense_form" class="wp-core-ui" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="expense_id" value="0" />
                <input type="hidden" name="add_item" value="1" />
                <?php
                wp_nonce_field('timegrow_expense_nonce', 'timegrow_expense_nonce_field');
                // Add AI analysis status for JavaScript - use new database table
                $ai_enabled = TimeGrowSettings::receipt_settings()['auto_analysis'] === 'yes' && TimeGrowAI::is_configured('receipts');
                ?>
                <input type="hidden" id="ai_analysis_enabled" value="<?php echo $ai_enabled ? '1' : '0'; ?>" />
        
                <div class="metabox-holder columns-2">
                    <div class="postbox-container">
                        <div class="postbox">
                            <h3 class="hndle"><span>Receipts</span></h3>
                            <div class="inside">
                                <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="file_upload">Upload File</label></th>
                                    <td>
                                            <div id="file-dropzone" class="file-dropzone-div" style="border: 2px dashed #007cba; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; transition: background-color 0.3s ease;">
                                                <p style="margin: 0; font-size: 16px; color: #555;">Drag and drop a file here or <span style="color: #007cba; text-decoration: underline;">click to upload</span>.</p>
                                                <input type="file" name="file_upload" id="file_upload" style="display: none;" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                                            </div>
                                            <p id="file-upload-status"></p>

                                            <!-- AI Analysis Approval -->
                                            <div id="ai-analysis-approval" style="display: none; margin-top: 15px; padding: 15px; background: #f0f6fc; border-left: 4px solid #667eea; border-radius: 4px;">
                                                <label style="display: flex; align-items: center; cursor: pointer;">
                                                    <input type="checkbox" name="approve_ai_analysis" id="approve_ai_analysis" value="1" style="margin-right: 10px;">
                                                    <span style="font-weight: 600; color: #1e293b;">
                                                        <span class="dashicons dashicons-analytics" style="color: #667eea; margin-right: 5px;"></span>
                                                        Analyze this receipt with AI to auto-populate expense fields
                                                    </span>
                                                </label>
                                                <p style="margin: 10px 0 0 28px; font-size: 13px; color: #64748b;">
                                                    AI will extract amount, date, vendor, category, and description from your receipt image.
                                                    <?php
                                                    $ai_settings = TimeGrowAI::config('receipts');
                                                    $provider_name = TimeGrowAI::providers()[$ai_settings['ai_provider']]['name'] ?? 'AI';
                                                    $model_name = TimeGrowAI::model_name($ai_settings['ai_provider'], $ai_settings['ai_model']);
                                                    echo '<br><strong>Provider:</strong> ' . esc_html($provider_name);
                                                    if (!empty($model_name)) {
                                                        echo ' | <strong>Model:</strong> ' . esc_html($model_name);
                                                    }
                                                    ?>
                                                    <span id="ai-token-usage" style="display: none;"></span>
                                                </p>
                                            </div>
                                        </td>
                                </tr>
                                </table>
                            </div>
                        </div>
                        <div class="postbox">
                            <h3 class="hndle"><span>Expense Information</span></h3>
                            <div class="inside">
                                <table class="form-table">
                                <tr>
                                    <th scope="row"><label for="expense_name">Expense Name</label></th>
                                    <td><input type="text" name="expense_name" id="expense_name" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="expense_date">Expense Date</label></th>
                                    <td><input type="date" id="expense_date" name="expense_date" class="datepicker" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="amount">Amount</label></th>
                                    <td><input type="number" name="amount" id="amount" step="0.01" class="regular-text" required></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="expense_description">Expense Description</label></th>
                                    <td>
                                        <textarea name="expense_description" id="expense_description" rows="5" class="large-text" placeholder="Enter a detailed description of the expense" required></textarea>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="expense_category_id">Category</label></th>
                                    <td>
                                    <select name="expense_category_id" id="expense_category_id" required>
                                        <?php
                                        $category_model = new TimeGrowExpenseCategoryModel();
                                        $category_model->render_category_select_options(null, false);
                                        ?>
                                    </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="Assigned To">Assigned To</label></th>
                                    <td>
                                        <select name="assigned_to" id="assigned_to" required>
                                            <option value="general" selected>General</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr id="assigned_to_row">
                                    <th scope="row">
                                        <label for="assigned_to_id">Assigned To ID</label>
                                    </th>
                                    <td>
                                        <input type="hidden" name="assigned_to_id" value="0" />
                                    </td>
                                </tr>
                                <tr id="expense-payment-method">
                                    <th scope="row">
                                        <label for="expense_payment_method">Payment Method</label>
                                    </th>
                                    <td>
                                        <select name="expense_payment_method" id="expense_payment_method" required>
                                            <option value="personal_card">Personal Card (Reimbursable)</option>
                                            <option value="company_card">Company Card</option>
                                            <option value="cash">Cash</option>
                                            <option value="bank_transfer">Bank Transfer</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </td>
                                </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <br clear="all" />
                <?php submit_button('Add Expense'); ?>
            </form>
        </div>
        <?php
    }

    public function edit_expense($expense, $receipts, $clients) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        ?>
        <div class="wrap timegrow-page">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Edit Expense', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Update expense information and manage receipts', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-edit"></span>
                </div>
            </div>
        
            <form id="timeflies-expense-form" class="wp-core-ui" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="expense_id" value="<?php echo esc_attr($expense->ID); ?>">
                <input type="hidden" name="edit_item" value="1" />
                <?php wp_nonce_field('timegrow_expense_nonce', 'timegrow_expense_nonce_field'); ?>
        
                <div class="metabox-holder columns-2">
                    <div class="postbox-container">
                        <div class="postbox">
                            <h3 class="hndle"><span>Expense Information</span></h3>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="expense_name">Expense Name</label></th>
                                        <td><input type="text" name="expense_name" id="expense_name" class="regular-text" value="<?php echo esc_attr($expense->expense_name); ?>" required></td>
                                    </tr>
                                    <tr>
                                    <th scope="row"><label for="expense_date">Expense Date</label></th>
                                    <td><input type="date" id="expense_date" name="expense_date" value="<?php echo esc_attr($expense->expense_date); ?>" required></td>
                                </tr>
                                    <tr>
                                        <th scope="row"><label for="amount">Amount</label></th>
                                        <td><input type="number" name="amount" id="amount" step="0.01" class="regular-text" value="<?php echo esc_attr($expense->amount); ?>" required></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="expense_description">Expense Description</label></th>
                                        <td>
                                            <textarea name="expense_description" id="expense_description" rows="5" class="large-text" placeholder="Enter a detailed description of the expense" required><?php echo esc_textarea($expense->expense_description); ?></textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="expense_category_id">Category</label></th>
                                        <td>
                                            <select name="expense_category_id" id="expense_category_id" required>
                                                <?php
                                                $category_model = new TimeGrowExpenseCategoryModel();
                                                $category_model->render_category_select_options($expense->expense_category_id, false);
                                                ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="Assigned To">Assigned To</label></th>
                                        <td>
                                            <select name="assigned_to" id="assigned_to" required>
                                                <option value="general" <?php selected($expense->assigned_to, 'general'); ?>>General</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr id="assigned_to_row">
                                    <th scope="row">
                                        <label for="assigned_to_id">Assigned To ID</label>
                                    </th>
                                    <td>
                                        <input type="hidden" name="assigned_to_id" value="<?php echo esc_attr($expense->assigned_to_id); ?>" />
                                    </td>
                                </tr>
                                <tr id="expense-payment-method">
                                    <th scope="row">
                                        <label for="expense_payment_method">Payment Method</label>
                                    </th>
                                    <td>
                                        <select name="expense_payment_method" id="expense_payment_method" required>
                                            <?php foreach (['personal_card' => 'Personal Card (Reimbursable)', 'company_card' => 'Company Card', 'cash' => 'Cash', 'bank_transfer' => 'Bank Transfer', 'other' => 'Other'] as $method => $label) : ?>
                                                <option value="<?php echo esc_attr($method); ?>" <?php selected($expense->expense_payment_method ?? '', $method); ?>><?php echo esc_html($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="status">Status</label></th>
                                    <td>
                                        <select id="status" name="status">
                                            <option value="1" <?php selected((int) ($expense->status ?? 1), 1); ?>>Active</option>
                                            <option value="0" <?php selected((int) ($expense->status ?? 1), 0); ?>>Inactive</option>
                                        </select>
                                        <p class="description">Inactive expenses stay here but are left out of every report and total.</p>
                                    </td>
                                </tr>
                                </table>
                            </div>
                        </div>
                        <div class="postbox">
                            <h3 class="hndle"><span>Receipts</span></h3>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="file_upload">Upload File</label></th>
                                        <td>
                                            <div id="file-dropzone" class="file-dropzone-div" style="border: 2px dashed #007cba; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; transition: background-color 0.3s ease;">
                                                <p style="margin: 0; font-size: 16px; color: #555;">Drag and drop a file here or <span style="color: #007cba; text-decoration: underline;">click to upload</span>.</p>
                                                <input type="file" name="file_upload" id="file_upload" style="display: none;" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                                            </div>
                                            <p id="file-upload-status"></p>
                                        </td>
                                    </tr>
                                </table>
                                <table class="wp-list-table widefat fixed striped table-view-list clients">
                                    <thead>
                                        <tr>
                                            <th scope="col" colspan="2" class="manage-column column-name column-primary">Receipt</th>
                                            <th scope="col" class="manage-column column-name ">Upload Date</th>
                                            <th scope="col" class="manage-column column-actions">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($receipts as $item): ?>
                                            <tr>
                                                <th scope="col" colspan="2" class="manage-column column-name column-primary"><?php echo esc_html(sprintf(__('Receipt %d', 'timegrow'), (int) $item->ID)); ?></th>
                                                <th scope="col" class="manage-column column-name"><?php echo esc_attr($item->upload_date) ?></th>
                                                <th scope="col" class="manage-column column-actions">
                                                    <a href="<?php echo esc_url(TimeGrowExpenseReceiptModel::view_url($item->ID)); ?>" target="_view_receipt" rel="noopener" class="button button-small mr-2">View</a> | 
                                                    <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expense-receipt-delete&id=' . $item->ID); ?>" class="button button-danger button-small ml-2 delete-button">Delete</a>                   
                                                </th>
                                            </tr>
                                        <?php endforeach ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th scope="col" colspan="2" class="manage-column column-name column-primary">Receipt</th>
                                            <th scope="col" class="manage-column column-name ">Upload Date</th>
                                            <th scope="col" class="manage-column column-actions">Actions</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>           
                    </div>
                </div>
                <br clear="all" />
                <?php submit_button('Update Expense'); ?>
            </form>
        </div>
        <?php
    }

}
