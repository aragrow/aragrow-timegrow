<?php
// client-add.php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$prefix = $wpdb->prefix;

$clients = $wpdb->get_results("SELECT ID, name FROM {$prefix}timeflies_clients ORDER BY name", ARRAY_A);

?>

<div class="wrap">
    <h2>Add New Expense</h2>

    <form id="timeflies-expense-form" class="wp-core-ui" method="POST">
        <input type="hidden" name="expense_id" value="0">
        <?php wp_nonce_field('timeflies_expense_nonce', 'timeflies_expense_nonce_field'); ?>

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
                            <th scope="row"><label for="amount">Amount</label></th>
                            <td><input type="number" name="amount" id="amount" step="0.01" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="expense_description">Expense Description</label></th>
                            <td>
                                <textarea name="expense_description" id="expense_description" rows="5" class="large-text" placeholder="Enter a detailed description of the expense"></textarea>
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
                            <th scope="row"><label for="assigned_to_id">Assigned To ID</label></th>
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
                                <div id="file-dropzone" class="file-dropzone-div">
                                    Drag and drop a file here or click to upload.
                                    <input type="file" name="file_upload" id="file_upload" style="display: none;" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                                </div>
                                <p id="file-upload-status"></p>
                            </td>
                        </tr>
                        </table>
                    </div>
                </div>           
            </div>
        </div>

        <?php submit_button('Add Expense'); ?>
    </form>
</div>