<?php
// team-member-add.php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$companies = $wpdb->get_results("SELECT id, name FROM timeflies_companies", ARRAY_A);
$projects = $wpdb->get_results("SELECT id, name FROM timeflies_projects", ARRAY_A);
$users = $wpdb->get_results("
    SELECT u.ID, u.user_login, u.user_email 
    FROM {$wpdb->users} u
    INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
    WHERE um.meta_key = '{$wpdb->prefix}capabilities'
    AND um.meta_value LIKE '%\"team_member\"%'
    AND u.ID not in (select user_id from timeflies_team_members)
", ARRAY_A);


?>

<div class="wrap">
    <h2>Add New Team Member</h2>
    <?php if (!$users) { ?>
        <div class="postbox">
            <h2 class="hndle"><span>Unable to Add Team Member</span></h2>
            <div class="inside">
                <p style="font-size: 14px; color: #555;">
                    <strong>No available users found.</strong> To add a team member, you first need to create a new user with the "Team Member" role. 
                    Once the user is created, you can assign them as a team member.
                </p>
                <p>
                    <a href="<?php echo admin_url('user-new.php'); ?>" class="button button-primary">Create New User</a>
                </p>
            </div>
        </div>
    <?php exit;} ?>

    <form id="timeflies-team-member-form" class="wp-core-ui">
        <input type="hidden" name="team_member_id" value="0">
        <?php wp_nonce_field('timeflies_team_member_nonce', 'timeflies_team_member_nonce_field'); ?>

        <div class="metabox-holder columns-2">
            <div class="postbox-container">
                <div class="postbox">
                    <h3 class="hndle"><span>Basic Information</span></h3>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="company_id">User <span class="required">*</span></label></th>
                                <td>      
                                    <select name="user_id" id="user_id">
                                        <option value="">Link to an User</option>
                                        <?php foreach ($users as $user) : ?>
                                            <option value="<?php echo esc_attr($user['ID']); ?>" <?php selected($member['user_id'], $user['ID']); ?>>
                                                <?php echo esc_html($user['user_login'].' - '.$user['user_email']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>  
                            <tr>
                                <th scope="row"><label for="company_id">Company <span class="required">*</span></label></th>
                                <td>
                                    <select id="company_id" name="company_id" class="regular-text" required>
                                        <option value="">Select a Company</option>
                                        <?php foreach ($companies as $company) : ?>
                                            <option value="<?php echo esc_attr($company['id']); ?>"><?php echo esc_html($company['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="name">Name <span class="required">*</span></label></th>
                                <td><input type="text" name="name" id="name" class="regular-text" required></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="email">Email</label></th>
                                <td><input type="email" name="email" id="email" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="phone">Phone</label></th>
                                <td><input type="text" name="phone" id="phone" class="regular-text"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="title">Title</label></th>
                                <td><input type="text" name="title" id="title" class="regular-text"></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <h3 class="hndle"><span>Additional Information</span></h3>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="bio">Bio</label></th>
                                <td><textarea name="bio" id="bio" class="large-text" rows="5"></textarea></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="status">Status</label></th>
                                <td><input type="checkbox" name="status" id="status" value="1" checked> Active</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="postbox">
                    <h3 class="hndle"><span>Timestamps</span></h3>
                    <div class="inside">
                        <p>Created At and Updated At timestamps will be automatically set when the company is added to the database.</p>
                    </div>
                </div>
            </div>

            <div class="postbox-container">
                <div class="postbox">
                    <h3 class="hndle"><span>Project Assignment</span></h3>
                    <div class="inside">
                        <div class="project-lists-container">
                            <div class="assigned-projects">
                                <h4>Assigned Projects</h4>
                                <input type="text" id="assigned-projects-search" placeholder="Search Projects...">
                                <ul id="assigned-projects-list" class="project-list"></ul>
                            </div>
                            <p class="description">Double Click to Assign or Remove.</p>
                            <div class="available-projects">
                                <h4>Available Projects</h4>
                                <input type="text" id="available-projects-search" placeholder="Search Projects...">
                                <ul id="available-projects-list" class="project-list">
                                    <?php
                                    $sql = "SELECT p.id, p.name, c.name AS client_name 
                                    FROM timeflies_projects p
                                    JOIN timeflies_clients c ON p.client_id = c.id";
                                    $projects = $wpdb->get_results($sql, ARRAY_A);
                                    foreach ($projects as $project) {
                                        echo '<li class="project-item" data-id="' . esc_attr($project['id']) . '">' . esc_html($project['name'].' - '.$project['client_name']) . '</li>';
                                    }
                                    ?>
                                </ul>
                            </div>
                        </div>
                        <input type="hidden" name="project_ids" id="project_ids_hidden" value="">
                    </div>
                </div>
            </div>
        </div>

        <?php submit_button('Add Team Member'); ?>
    </form>
</div>
