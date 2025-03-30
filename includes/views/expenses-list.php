<div class="wrap">
    <h2>All Expenses</h2>

    <?php

    global $wpdb;
    $prefix = $wpdb->prefix;

    // Fetch all expenses from the database
    $expenses = $wpdb->get_results("
        SELECT * FROM {$prefix}timeflies_expenses ORDER BY date_created DESC
    ", ARRAY_A);
    ?>

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
                <th scope="col" class="manage-column column-company">Amount</th>  
                <th scope="col" class="manage-column column-document">Category</th>
                <th scope="col" class="manage-column column-address">Assigned To</th>
                <th scope="col" class="manage-column column-city">Date Created</th>
                <th scope="col" class="manage-column column-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($expenes) : ?>
                <?php foreach ($epenses as $item) : ?>
                    <tr>
                        <td class="column-name column-primary" data-colname="Name">
                            <strong><?php echo esc_html($item['expense_name']); ?></strong>
                        </td>
                        <td class="column-amount" data-colname="Amount"><?php echo esc_html($item['amount']); ?></td>  
                        <td class="column-document" data-colname="Category"><?php echo esc_html($item['category']); ?></td>
                        <td class="column-address" data-colname="Assigned To"><?php echo esc_html($item['assigned_to']); ?></td>
                        <td class="column-city" data-colname="Date Created"><?php echo esc_html($item['date_created']); ?></td>
                        <td class="column-actions" data-colname="Actions">
                            <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expense-edit&id=' . $item['ID']); ?>" class="button button-small">Edit</a> 
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="6">No expenses found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th scope="col" class="manage-column column-name column-primary">Name</th>
                <th scope="col" class="manage-column column-company">Amount</th>  
                <th scope="col" class="manage-column column-document">Category</th>
                <th scope="col" class="manage-column column-address">Assigned To</th>
                <th scope="col" class="manage-column column-city">Date Created</th>
                <th scope="col" class="manage-column column-actions">Actions</th>
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