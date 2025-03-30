<div class="wrap">
    <h2>All Projects</h2>

    <?php
    global $wpdb;
    $prefix = $wpdb->prefix;


    $integrations = get_option('timeflies_integration_settings');
    if ($integrations['wc_clients'] && class_exists('WooCommerce')) {
        $integration = new Timeflies_Integration();
        $projects = $integration->get_projects_wc_customers('all', ['ID', 'name'], ['name']);
       
    } else {

        $projects = $wpdb->get_results("
        SELECT p.*, c.name AS client_name 
        FROM {$prefix}timeflies_projects p
        INNER JOIN {$prefix}timeflies_clients c ON p.client_id = c.ID
        ORDER BY p.name
        ", ARRAY_A);
    }

    // $projects now contains client names along with other project details

    ?>

    <div class="tablenav top">
        <div class="alignleft actions">
            <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-project-add'); ?>" class="button button-primary">Add New Project</a>
        </div>
        <br class="clear">
    </div>

    <table class="wp-list-table widefat fixed striped table-view-list projects">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-name column-primary">Project Name</th>
                <th scope="col" class="manage-column column-client">Client</th>
                <th scope="col" class="manage-column column-start-date">Start Date</th>
                <th scope="col" class="manage-column column-end-date">End Date</th>
                <th scope="col" class="manage-column column-status">Status</th>
                <th scope="col" class="manage-column column-status">Billable</th>
                <th scope="col" class="manage-column column-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($projects) :?>
                <?php foreach ($projects as &$project) :?>
                    <tr>
                        <td class="column-name column-primary" data-colname="Project Name">
                            <strong><?php echo esc_html($project['name']); echo $project['ID'];?></strong>
                            <button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>
                        </td>
                        <td class="column-client" data-colname="Client"><?php echo esc_html($project['client_name']); echo $project['client_id']; ?></td>
                        <td class="column-start-date" data-colname="Start Date"><?php echo esc_html($project['start_date']); ?></td>
                        <td class="column-end-date" data-colname="End Date"><?php echo esc_html($project['end_date']); ?></td>
                        <td class="column-status" data-colname="Status"><?php echo esc_html($project['status']); ?></td>
                        <td class="column-billable" data-colname="Billable"><?php echo ($project['billable']) ? 'YES' : 'NO';   ?></td>
                        <td class="column-actions" data-colname="Actions">
                            <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-project-edit&id=' . $project['ID']); ?>" class="button button-small">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="6">No projects found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th scope="col" class="manage-column column-name column-primary">Project Name</th>
                <th scope="col" class="manage-column column-client">Client</th>
                <th scope="col" class="manage-column column-start-date">Start Date</th>
                <th scope="col" class="manage-column column-end-date">End Date</th>
                <th scope="col" class="manage-column column-status">Status</th>
                <th scope="col" class="manage-column column-actions">Actions</th>
            </tr>
        </tfoot>
    </table>

    <div class="tablenav bottom">
        <div class="alignleft actions">
            <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-project-add'); ?>" class="button button-primary">Add New Project</a>
        </div>
        <br class="clear">
    </div>
</div>
