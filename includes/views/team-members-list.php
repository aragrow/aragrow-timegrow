<div class="wrap">
    <h2>All Team Members</h2>

    <?php
    global $wpdb;
    $prefix = $wpdb->prefix;

    // Join with the companies table to get the company name
    $team_members = $wpdb->get_results("
        SELECT tm.*, comp.name AS company_name 
        FROM {$prefix}timeflies_team_members tm
        INNER JOIN {$prefix}timeflies_companies comp ON tm.company_id = comp.ID
    ", ARRAY_A);
    ?>

    <div class="tablenav top">
        <div class="alignleft actions">
            <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-team-member-add'); ?>" class="button button-primary">Add New Team Member</a>
        </div>
        <br class="clear">
    </div>

    <table class="wp-list-table widefat fixed striped table-view-list team-members">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-name column-primary">Name</th>
                <th scope="col" class="manage-column column-company">Company</th>
                <th scope="col" class="manage-column column-email">Email</th>
                <th scope="col" class="manage-column column-title">Title</th>
                <th scope="col" class="manage-column column-actions">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($team_members) : ?>
                <?php foreach ($team_members as $team_member) : ?>
                    <tr>
                        <td class="column-name column-primary" data-colname="Name">
                            <strong><?php echo esc_html($team_member['name']); ?></strong>
                            <button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>
                        </td>
                        <td class="column-company" data-colname="Company"><?php echo esc_html($team_member['company_name']); ?></td>
                        <td class="column-email" data-colname="Email"><?php echo esc_html($team_member['email']); ?></td>
                        <td class="column-title" data-colname="Title"><?php echo esc_html($team_member['title']); ?></td>
                        <td class="column-actions" data-colname="Actions">
                            <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-team-member-edit&id=' . $team_member['ID']); ?>" class="button button-small">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="5">No team members found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th scope="col" class="manage-column column-name column-primary">Name</th>
                <th scope="col" class="manage-column column-company">Company</th>
                <th scope="col" class="manage-column column-email">Email</th>
                <th scope="col" class="manage-column column-title">Title</th>
                <th scope="col" class="manage-column column-actions">Actions</th>
            </tr>
        </tfoot>
    </table>

    <div class="tablenav bottom">
        <div class="alignleft actions">
            <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-team-member-add'); ?>" class="button button-primary">Add New Team Member</a>
        </div>
        <br class="clear">
    </div>
</div>