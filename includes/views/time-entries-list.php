<div class="wrap">
    <h2>All Time Entries</h2>

    <div class="tablenav top">
        <div class="alignleft actions">
            <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-time-entry-add'); ?>" class="button button-primary">Add New Time Entry</a>
        </div>
        <br class="clear">
    </div>

    <table class="wp-list-table widefat fixed striped table-view-list time-entries">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-client">Client</th>
                <th scope="col" class="manage-column column-project">Project</th>
                <th scope="col" class="manage-column column-date">Date</th>
                <th scope="col" class="manage-column column-time">Time</th>
                <th scope="col" class="manage-column column-duration">Duration</th>
                <th scope="col" class="manage-column column-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($time_entries) : ?>
                <?php foreach ($time_entries as $entry) : 
                    $duration = strtotime($entry['end_time']) - strtotime($entry['start_time']);
                ?>
                    <tr>
                        <td class="column-client"><?php echo esc_html($entry['client_name']); ?></td>
                        <td class="column-project"><?php echo esc_html($entry['project_name']); ?></td>
                        <td class="column-date"><?php echo date('M j, Y', strtotime($entry['start_time'])); ?></td>
                        <td class="column-time">
                            <?php echo date('g:i A', strtotime($entry['start_time'])) . ' - ' . 
                                  ($entry['end_time'] ? date('g:i A', strtotime($entry['end_time'])) : 'Ongoing'); ?>
                        </td>
                        <td class="column-duration"><?php echo gmdate('H:i', $duration); ?></td>
                        <td class="column-actions">
                            <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-time-entry-edit&id=' . $entry['id']); ?>" class="button button-small">Edit</a>
                            <a href="#" class="button button-small delete-time-entry" data-id="<?php echo $entry['id']; ?>">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="6">No time entries found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th scope="col" class="manage-column column-client">Client</th>
                <th scope="col" class="manage-column column-project">Project</th>
                <th scope="col" class="manage-column column-date">Date</th>
                <th scope="col" class="manage-column column-time">Time</th>
                <th scope="col" class="manage-column column-duration">Duration</th>
                <th scope="col" class="manage-column column-actions">Actions</th>
            </tr>
        </tfoot>
    </table>
    <div class="tablenav top">
        <div class="alignleft actions">
            <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-time-entry-add'); ?>" class="button button-primary">Add New Time Entry</a>
        </div>
        <br class="clear">
    </div>  
</div>
