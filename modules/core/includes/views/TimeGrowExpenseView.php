<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowExpenseView {
    
    public function display_expenses($expenses, $filter_options = [], $current_filters = []) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Merge with defaults
        $current_filters = array_merge([
            'orderby' => 'expense_date',
            'order' => 'DESC',
            'filter_assigned_to' => '',
            'filter_date_from' => '',
            'filter_date_to' => '',
            'filter_search' => ''
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
                       !empty($current_filters['filter_search']);
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

        <div class="tablenav top">
            <div class="alignleft actions">
                <input type="search" id="filter_search" name="s" value="<?php echo esc_attr($current_filters['filter_search']); ?>" placeholder="<?php esc_attr_e('Search expenses...', 'timegrow'); ?>">

                <select id="filter_assigned_to" name="filter_assigned_to">
                    <option value=""><?php esc_html_e('All Types', 'timegrow'); ?></option>
                    <option value="client" <?php selected($current_filters['filter_assigned_to'], 'client'); ?>><?php esc_html_e('Client', 'timegrow'); ?></option>
                    <option value="project" <?php selected($current_filters['filter_assigned_to'], 'project'); ?>><?php esc_html_e('Project', 'timegrow'); ?></option>
                    <option value="general" <?php selected($current_filters['filter_assigned_to'], 'general'); ?>><?php esc_html_e('General', 'timegrow'); ?></option>
                </select>

                <input type="date" id="filter_date_from" name="filter_date_from" value="<?php echo esc_attr($current_filters['filter_date_from']); ?>" placeholder="<?php esc_attr_e('From Date', 'timegrow'); ?>">
                <input type="date" id="filter_date_to" name="filter_date_to" value="<?php echo esc_attr($current_filters['filter_date_to']); ?>" placeholder="<?php esc_attr_e('To Date', 'timegrow'); ?>">

                <button type="button" id="filter_expenses" class="button"><?php esc_html_e('Filter', 'timegrow'); ?></button>

                <?php if ($has_filters) : ?>
                    <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expenses-list'); ?>" class="button"><?php esc_html_e('Clear Filters', 'timegrow'); ?></a>
                <?php endif; ?>
            </div>
            <br class="clear">
        </div>

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
                    <th scope="col" class="manage-column column-assigned-to sortable <?php echo ($current_filters['orderby'] == 'assigned_to') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('assigned_to', __('Assigned To', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-category sortable <?php echo ($current_filters['orderby'] == 'category') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('category', __('Category', 'timegrow')); ?>
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
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="5">No expenses found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="col" class="manage-column column-name column-primary">Name</th>
                    <th scope="col" class="manage-column column-date">Date</th>
                    <th scope="col" class="manage-column column-amount">Amount</th>
                    <th scope="col" class="manage-column column-assigned-to">Assigned To</th>
                    <th scope="col" class="manage-column column-category">Category</th>
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
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
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
        
            <form id="timeflies-expense-form" class="wp-core-ui" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="expense_id" value="0" />
                <input type="hidden" name="add_item" value="1" />
                <?php wp_nonce_field('timegrow_expense_nonce', 'timegrow_expense_nonce_field'); ?>
        
                <div class="metabox-holder columns-2">
                    <div class="postbox-container">
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
                                            <script>
                                                document.getElementById('file-dropzone').addEventListener('click', function() {
                                                    document.getElementById('file_upload').click();
                                                });

                                                document.getElementById('file_upload').addEventListener('change', function(event) {
                                                    const fileName = event.target.files[0]?.name || 'No file selected';
                                                    const statusElement = document.getElementById('file-upload-status');
                                                    statusElement.textContent = `Selected file: ${fileName}`;
                                                    statusElement.style.color = '#007cba';
                                                });
                                            </script>
                                            <p id="file-upload-status"></p>
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
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

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
                                            <script>
                                                document.getElementById('file-dropzone').addEventListener('click', function() {
                                                    document.getElementById('file_upload').click();
                                                });

                                                document.getElementById('file_upload').addEventListener('change', function(event) {
                                                    const fileName = event.target.files[0]?.name || 'No file selected';
                                                    const statusElement = document.getElementById('file-upload-status');
                                                    statusElement.textContent = `Selected file: ${fileName}`;
                                                    statusElement.style.color = '#007cba';
                                                });
                                            </script>
                                            <p id="file-upload-status"></p>
                                        </td>
                                    </tr>
                                </table>
                                <table class="wp-list-table widefat fixed striped table-view-list clients">
                                    <thead>
                                        <tr>
                                            <th scope="col" colspan="2" class="manage-column column-name column-primary">Receipt Url</th>
                                            <th scope="col" class="manage-column column-name ">Upload Date</th>
                                            <th scope="col" class="manage-column column-actions">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($receipts as $item): ?>
                                            <tr>
                                                <th scope="col" colspan="2" class="manage-column column-name column-primary"><?php echo esc_attr($item->file_url) ?></th>
                                                <th scope="col" class="manage-column column-name"><?php echo esc_attr($item->upload_date) ?></th>
                                                <th scope="col" class="manage-column column-actions">
                                                    <a href="<?php echo esc_attr($item->file_url) ?>" target="_view_receipt" class="button button-small mr-2">View</a> | 
                                                    <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expense-receipt-delete&id=' . $item->ID); ?>" class="button button-danger button-small ml-2 delete-button">Delete</a>                   
                                                </th>
                                            </tr>
                                        <?php endforeach ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th scope="col" colspan="2" class="manage-column column-name column-primary">Receipt Url</th>
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
