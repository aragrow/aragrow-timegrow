<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowExpenseView {
    
    public function display_expenses($expenses) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        ?>
        <div class="wrap">
        <h2>All Expenses</h2>
    
        <div class="tablenav top">
            <div class="alignleft actions">
                <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expense-add'); ?>" class="button button-primary">Add New Expense</a>
            </div>
            <br class="clear">
        </div>
    
        <table class="wp-list-table widefat fixed striped table-view-list clients">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-name column-primary">Name</th>
                    <th scope="col" class="manage-column column-name">Date</th>
                    <th scope="col" class="manage-column column-company">Amount</th>  
                    <th scope="col" class="manage-column column-document">Category</th>
                    <th scope="col" class="manage-column column-address">Assigned To</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($expenses) : ?>
                    <?php foreach ($expenses as $item) : ?>
                        <?php $display_date = DateTime::createFromFormat('Y-m-d', $item->expense_date)->format('d/m/Y'); ?>
                        <tr>
                            <td class="column-name column-primary" data-colname="Name">
                                <strong><?php echo esc_html($item->expense_name); ?></strong>
                                <div class="row-actions visible">
                                    <span class="edit">
                                        <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expense-edit&id=' . $item->ID); ?>" >Edit</a> 
                                    </span>
                                </div>
                            </td>
                            <td class="column-amount" data-colname="Date"><?php echo esc_html($display_date); ?></td>  
                            <td class="column-amount" data-colname="Amount"><?php echo esc_html($item->amount); ?></td>  
                            <td class="column-document" data-colname="Category"><?php echo esc_html($item->category); ?></td>
                            <td class="column-address" data-colname="Assigned To"><?php echo esc_html($item->assigned_to); ?></td>
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
                    <th scope="col" class="manage-column column-name">Date</th>
                    <th scope="col" class="manage-column column-company">Amount</th>  
                    <th scope="col" class="manage-column column-document">Category</th>
                    <th scope="col" class="manage-column column-address">Assigned To</th>
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
        <div class="wrap">
            <h2>Add New Expense</h2>
        
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
                                    <th scope="row"><label for="category">Category</label></th>
                                    <td>
                                    <select name="category" id="category" required>
                                        <optgroup label="Office Expenses">
                                            <option value="office_supplies">Office Supplies</option>
                                            <option value="utilities">Utilities</option>
                                            <option value="rent">Rent</option>
                                        </optgroup>
                                        <optgroup label="Travel Expenses">
                                            <option value="transportation">Transportation</option>
                                            <option value="lodging">Lodging</option>
                                            <option value="meals">Meals</option>
                                        </optgroup>
                                        <optgroup label="Marketing Expenses">
                                            <option value="advertising">Advertising</option>
                                            <option value="promotions">Promotions</option>
                                            <option value="branding">Branding</option>
                                        </optgroup>
                                        <optgroup label="Miscellaneous">
                                            <option value="general">General</option>
                                            <option value="other">Other</option>
                                        </optgroup>
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
        $display_date = DateTime::createFromFormat('Y-m-d', $expense->expense_date)->format('d/m/Y');

        ?>
        <div class="wrap">
            <h2>Edit Expense</h2>
        
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
                                    <td><input type="date" id="expense_date" name="expense_date" value="<?php echo esc_attr($display_date); ?>" required></td>
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
                                        <th scope="row"><label for="category">Category</label></th>
                                        <td>
                                            <select name="category" id="category" required>
                                                <optgroup label="Office Expenses">
                                                    <option value="office_supplies" <?php selected($expense->category, 'office_supplies'); ?>>Office Supplies</option>
                                                    <option value="utilities" <?php selected($expense->category, 'utilities'); ?>>Utilities</option>
                                                    <option value="rent" <?php selected($expense->category, 'rent'); ?>>Rent</option>
                                                </optgroup>
                                                <optgroup label="Travel Expenses">
                                                    <option value="transportation" <?php selected($expense->category, 'transportation'); ?>>Transportation</option>
                                                    <option value="lodging" <?php selected($expense->category, 'lodging'); ?>>Lodging</option>
                                                    <option value="meals" <?php selected($expense->category, 'meals'); ?>>Meals</option>
                                                </optgroup>
                                                <optgroup label="Marketing Expenses">
                                                    <option value="advertising" <?php selected($expense->category, 'advertising'); ?>>Advertising</option>
                                                    <option value="promotions" <?php selected($expense->category, 'promotions'); ?>>Promotions</option>
                                                    <option value="branding" <?php selected($expense->category, 'branding'); ?>>Branding</option>
                                                </optgroup>
                                                <optgroup label="Miscellaneous">
                                                    <option value="general" <?php selected($expense->category, 'general'); ?>>General</option>
                                                    <option value="other" <?php selected($expense->category, 'other'); ?>>Other</option>
                                                </optgroup>
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
                                                <th scope="col" colspan="2" class="manage-column column-name column-primary"><? echo esc_attr($item->file_url) ?></th>
                                                <th scope="col" class="manage-column column-name"><? echo esc_attr($item->upload_date) ?></th>
                                                <th scope="col" class="manage-column column-actions">
                                                    <a href="<? echo esc_attr($item->file_url) ?>" target="_view_receipt" class="button button-small mr-2">View</a> | 
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
