<div class="wrap">
    <h2>All Companies</h2>

    <?php
    global $wpdb;
    $prefix = $wpdb->prefix;

    $companies = $wpdb->get_results("SELECT * FROM {$prefix}timeflies_companies", ARRAY_A);
    ?>

    <div class="tablenav top">
        <div class="alignleft actions">
            <a href="<?php echo admin_url('admin.php?page=' . TIMEFLIES_PARENT_MENU . '-company-add'); ?>" class="button button-primary">Add New Company</a>
        </div>
        <br class="clear">
    </div>

    <table class="wp-list-table widefat fixed striped table-view-list companies">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-name column-primary">Name</th>
                <th scope="col" class="manage-column column-document">Document Number</th>
                <th scope="col" class="manage-column column-address">Address</th>
                <th scope="col" class="manage-column column-city">City</th>
                <th scope="col" class="manage-column column-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($companies) : ?>
                <?php foreach ($companies as $company) : ?>
                    <tr>
                        <td class="column-name column-primary" data-colname="Name">
                            <strong><?php echo esc_html($company['name']); ?></strong>
                            <button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>
                        </td>
                        <td class="column-document" data-colname="Document Number"><?php echo esc_html($company['document_number']); ?></td>
                        <td class="column-address" data-colname="Address"><?php echo esc_html($company['address']); ?></td>
                        <td class="column-city" data-colname="City"><?php echo esc_html($company['city']); ?></td>
                        <td class="column-actions" data-colname="Actions">
                            <a href="<?php echo admin_url('admin.php?page=' . TIMEFLIES_PARENT_MENU . '-company-edit&id=' . $company['ID']); ?>" class="button button-small">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="5">No companies found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th scope="col" class="manage-column column-name column-primary">Name</th>
                <th scope="col" class="manage-column column-document">Document Number</th>
                <th scope="col" class="manage-column column-address">Address</th>
                <th scope="col" class="manage-column column-city">City</th>
                <th scope="col" class="manage-column column-actions">Actions</th>
            </tr>
        </tfoot>
    </table>

    <div class="tablenav bottom">
        <div class="alignleft actions">
            <a href="<?php echo admin_url('admin.php?page=' . TIMEFLIES_PARENT_MENU . '-company-add'); ?>" class="button button-primary">Add New Company</a>
        </div>
        <br class="clear">
    </div>
</div>