<div class="wrap">
    <h2>All Clients</h2>

    <?php

    $integrations = get_option('timeflies_integration_settings');

    if ($integrations['wc_clients'] && class_exists('WooCommerce')) {
        echo '<div class="x-notice info"><p>Clients are integrated with WooCommerce Customers.</p></div>';
        exit();
    }

    global $wpdb;
    $prefix = $wpdb->prefix;

    // Join with the companies table to get the company name
    $clients = $wpdb->get_results("
        SELECT c.*, comp.name AS company_name 
        FROM {$prefix}timeflies_clients c
        INNER JOIN {$prefix}timeflies_companies comp ON c.company_id = comp.ID
    ", ARRAY_A);
    ?>

    <div class="tablenav top">
        <div class="alignleft actions">
            <a href="<?php echo admin_url('admin.php?page=' . TIMEFLIES_PARENT_MENU . '-client-add'); ?>" class="button button-primary">Add New Client</a>
        </div>
        <br class="clear">
    </div>

    <table class="wp-list-table widefat fixed striped table-view-list clients">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-name column-primary">Name</th>
                <th scope="col" class="manage-column column-company">Company</th>  
                <th scope="col" class="manage-column column-document">Document Number</th>
                <th scope="col" class="manage-column column-address">Address</th>
                <th scope="col" class="manage-column column-city">City</th>
                <th scope="col" class="manage-column column-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($clients) : ?>
                <?php foreach ($clients as $client) : ?>
                    <tr>
                        <td class="column-name column-primary" data-colname="Name">
                            <strong><?php echo esc_html($client['name']); ?></strong>
                            <button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>
                        </td>
                        <td class="column-company" data-colname="Company"><?php echo esc_html($client['company_name']); ?></td>  
                        <td class="column-document" data-colname="Document Number"><?php echo esc_html($client['document_number']); ?></td>
                        <td class="column-address" data-colname="Address"><?php echo esc_html($client['address_1']); ?></td>
                        <td class="column-city" data-colname="City"><?php echo esc_html($client['city']); ?></td>
                        <td class="column-actions" data-colname="Actions">
                            <a href="<?php echo admin_url('admin.php?page=' . TIMEFLIES_PARENT_MENU . '-client-edit&id=' . $client['ID']); ?>" class="button button-small">Edit</a> 
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="6">No clients found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th scope="col" class="manage-column column-name column-primary">Name</th>
                <th scope="col" class="manage-column column-company">Company</th>  
                <th scope="col" class="manage-column column-document">Document Number</th>
                <th scope="col" class="manage-column column-address">Address</th>
                <th scope="col" class="manage-column column-city">City</th>
                <th scope="col" class="manage-column column-actions">Actions</th>
            </tr>
        </tfoot>
    </table>

    <div class="tablenav bottom">
        <div class="alignleft actions">
            <a href="<?php echo admin_url('admin.php?page=' . TIMEFLIES_PARENT_MENU . '-client-add'); ?>" class="button button-primary">Add New Client</a>
        </div>
        <br class="clear">
    </div>
</div>