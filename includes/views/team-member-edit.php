<?php
// team-member-edit.php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$prefix = $wpdb->prefix;

$team_member_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($team_member_id > 0) {
    $member = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$prefix}timeflies_team_members WHERE ID = %d", $team_member_id),
        ARRAY_A
    );
} else {
    $member = null;
}

if (!$member) {
    echo '<h2>Team member not found. Please contact your administrator.</h2>';
    exit;
}

$companies = $wpdb->get_results("SELECT ID, name FROM {$prefix}timeflies_companies", ARRAY_A);

$projects = $wpdb->get_results("SELECT ID, name FROM {$prefix}timeflies_projects", ARRAY_A);

// Prepare the SQL query safely
$sql = $wpdb->prepare(
    "SELECT u.ID, u.user_login, u.user_email 
    FROM {$wpdb->users} u
    INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id
    WHERE um.meta_key = '{$prefix}capabilities'
    AND um.meta_value LIKE %s
    AND u.ID NOT IN (
        SELECT user_id 
        FROM {$prefix}timeflies_team_members
        WHERE user_id != %d
    )",
    '%"team_member"%',
    $member['user_id']
);
// Execute the query
$users = $wpdb->get_results($sql, ARRAY_A);


$assigned_projects = $wpdb->get_results(
    $wpdb->prepare("SELECT project_id FROM {$prefix}timeflies_team_member_projects WHERE team_member_id = %d", $team_member_id),
    ARRAY_A
);
    
$assigned_project_ids = array_map(function ($project) {
    return $project['project_id'];
}, $assigned_projects);

?>

<div class="wrap">
    <h2>Edit Team Member</h2>

    <form id="timeflies-team-member-form" class="wp-core-ui">
        <input type="hidden" name="team_member_id" value="<?php echo esc_attr($team_member_id); ?>">
        <?php wp_nonce_field('timeflies_team_member_nonce', 'timeflies_team_member_nonce_field'); ?>

        <div class="metabox-holder columns-2">
            <div class="postbox-container">
     
                <div class="postbox">
                    <h3 class="hndle"><span>Basic Information</span></h3>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="user_id">User <span class="required">*</span></label></th>
                                <td>
                                    <select name="user_id" id="user_id">
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
                                    <select id="company_id" name="company_id" required>
                                        <option value="">Select a Company</option>
                                        <?php foreach ($companies as $company) : ?>
                                            <option value="<?php echo esc_attr($company['ID']); ?>" <?php selected($member['company_id'], $company['ID']); ?>><?php echo esc_html($company['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="name">Name <span class="required">*</span></label></th>
                                <td><input type="text" name="name" id="name" class="regular-text" value="<?php echo esc_attr($member['name']); ?>" required></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="email">Email</label></th>
                                <td><input type="email" name="email" id="email" class="regular-text" value="<?php echo esc_attr($member['email']); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="phone">Phone</label></th>
                                <td><input type="text" name="phone" id="phone" class="regular-text" value="<?php echo esc_attr($member['phone']); ?>"></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="title">Title</label></th>
                                <td><input type="text" name="title" id="title" class="regular-text" value="<?php echo esc_attr($member['title']); ?>"></td>
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
                                <td><textarea name="bio" id="bio" class="large-text" rows="5"><?php echo esc_textarea($member['bio']); ?></textarea></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="status">Status</label></th>
                                <td><input type="checkbox" name="status" id="status" value="1" <?php checked($member['status'], 1); ?>> Active</td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="postbox">
                    <h3 class="hndle"><span>Timestamps</span></h3>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Created At</th>
                                <td><?php echo esc_html($member['created_at']); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Updated At</th>
                                <td><?php echo esc_html($member['updated_at']); ?></td>
                            </tr>
                        </table>
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
                                <ul id="assigned-projects-list" class="project-list">
                                    <?php
                                    $sql = "SELECT p.ID, p.name, c.name AS client_name
                                    FROM {$prefix}timeflies_projects p
                                    JOIN {$prefix}timeflies_clients c ON p.client_id = c.ID
                                    WHERE p.ID IN (" . implode(',', array_map('intval', $assigned_project_ids)) . ")";
                                    $assigned_project_items = $wpdb->get_results($sql, ARRAY_A);
                                    if ($assigned_project_items) {
                                        foreach ($assigned_project_items as $project) {
                                            echo '<li class="project-item assigned" data-id="' . esc_attr($project['ID']) . '">' . esc_html($project['name'].' - '.$project['client_name']) . '</li>';
                                        }
                                    }
                                    ?>
                                </ul>
                            </div>
                            <p class="description">Double Click to Assign or Remove.</p>
                            <div class="available-projects">
                                <h4>Available Projects</h4>
                                <input type="text" id="available-projects-search" placeholder="Search Projects...">
                                <ul id="available-projects-list" class="project-list">
                                    <?php
                                    $sql = "SELECT p.ID, p.name, c.name AS client_name
                                    FROM {$prefix}timeflies_projects p
                                    JOIN {$prefix}timeflies_clients c ON p.client_id = c.ID
                                    WHERE p.ID NOT IN (" . (empty($assigned_project_ids) ? '-1' : implode(',', array_map('intval', $assigned_project_ids))) . ")";
                                    $projects = $wpdb->get_results($sql, ARRAY_A);
                                    foreach ($projects as $project) {
                                        echo '<li class="project-item" data-id="' . esc_attr($project['ID']) . '">' . esc_html($project['name'].' - '.$project['client_name']) . '</li>';
                                    }
                                    ?>
                                </ul>
                            </div>
                        </div>
                        <input type="hidden" name="project_ids" id="project_ids_hidden" value="<?php echo implode(',', $assigned_project_ids); ?>" readonly>
                    </div>
                </div>
            </div>
        </div>

        <?php submit_button('Update Team Member'); ?>
    </form>
</div>