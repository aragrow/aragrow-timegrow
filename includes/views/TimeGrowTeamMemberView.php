<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowTeamMemberView {
    
    public function display($items) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        ?>
        <div class="wrap">
        <h2>All Team Members</h2>
    
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
                <?php if ($items) : ?>
                    <?php foreach ($items as $item) : ?>
                        <tr>
                            <td class="column-name column-primary" data-colname="Name">
                                <strong><?php echo esc_html($item->name); ?></strong>
                                <button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>
                            </td>
                            <td class="column-company" data-colname="Company"><?php echo esc_html($item->company_name); ?></td>
                            <td class="column-email" data-colname="Email"><?php echo esc_html($item->email); ?></td>
                            <td class="column-title" data-colname="Title"><?php echo esc_html($item->title); ?></td>
                            <td class="column-actions" data-colname="Actions">
                                <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-team-member-edit&id=' . $item->ID); ?>" class="button button-small">Edit</a>
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
    <?php
    }

    public function add($users, $companies, $projects) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        ?>
        <div class="wrap">
            <h2>Add New Team Member</h2>
        
            <form id="timegrow-company-form" class="wp-core-ui" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="team_member_id" value="0">
                <input type="hidden" name="add_item" value="1" />
                <?php wp_nonce_field('timegrow_team_member_nonce', 'timegrow_team_member_nonce_field'); ?>

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
                                                    <option value="<?php echo esc_attr($user->ID); ?>" >
                                                        <?php echo esc_html($user->user_login.' - '.$user->user_email); ?>
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
                                                    <option value="<?php echo esc_attr($company->ID); ?>"><?php echo esc_html($company->name); ?></option>
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
                            <h3 class="hndle"><span>Project Assignment</span>
                            <br /><span class="description">Drag and Drop to Assign Projects to Team Member.</span></h3>
                            <div class="inside">
                                <div class="project-lists-container">
                                    <div class="assigned-projects">
                                        <h4>Assigned Projects</h4>
                                        <input type="text" id="assigned-projects-search" placeholder="Search Projects...">
                                        <ul id="assigned-projects-list" class="project-list assigned-projects-background "></ul>
                                    </div>
                                    <br clear="all" />
                                    <div class="available-projects">
                                        <h4>Available Projects</h4>
                                        <input type="text" id="available-projects-search" placeholder="Search Projects...">
                                        <ul id="available-projects-list" class="project-list">
                                            <?php
                                            foreach ($projects as $project) {
                                                echo '<li class="project-item available-projects" data-id="' . esc_attr($project->ID) . '">' 
                                                . esc_html($project->name).'<br />'.esc_html($project->client_name) . '</li>';
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
                <br clear="all" /><?php submit_button('Add Team Member', 'ml-2'); ?>
            </form>
        </div>
        <?php
    }

    public function edit($item, $users, $companies, $projects, $assigned) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        ?>
        <div class="wrap">
            <h2>Edit Team Member</h2>
        
            <form id="timegrow-company-form" class="wp-core-ui" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="team_member_id" value="<?php echo esc_attr($item->ID); ?>">
                <input type="hidden" name="edit_item" value="1" />
                <?php wp_nonce_field('timegrow_team_member_nonce', 'timegrow_team_member_nonce_field'); ?>

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
                                                    <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($item->user_id, $user->ID); ?>>
                                                        <?php echo esc_html($user->user_login.' - '.$user->user_email); ?>
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
                                                    <option value="<?php echo esc_attr($company->ID); ?>" <?php selected($item->company_id, $company->ID); ?>><?php echo esc_html($company->name); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="name">Name <span class="required">*</span></label></th>
                                        <td><input type="text" name="name" id="name" class="regular-text" value="<?php echo esc_attr($item->name); ?>" required></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="email">Email</label></th>
                                        <td><input type="email" name="email" id="email" class="regular-text" value="<?php echo esc_attr($item->email); ?>"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="phone">Phone</label></th>
                                        <td><input type="text" name="phone" id="phone" class="regular-text" value="<?php echo esc_attr($item->phone); ?>"></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="title">Title</label></th>
                                        <td><input type="text" name="title" id="title" class="regular-text" value="<?php echo esc_attr($item->title); ?>"></td>
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
                                        <td><textarea name="bio" id="bio" class="large-text" rows="5"><?php echo esc_textarea($item->bio); ?></textarea></td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="status">Status</label></th>
                                        <td><input type="checkbox" name="status" id="status" value="1" <?php checked($item->status, 1); ?>> Active</td>
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
                                        <td><?php echo esc_html($item->created_at); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Updated At</th>
                                        <td><?php echo esc_html($item->updated_at); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="postbox-container">
                        <div class="postbox">
                            <h3 class="hndle"><span>Project Assignment</span>
                            <br /><span class="description">Drag and Drop to Assign Projects to Team Member.</span></h3>
                            <div class="inside">
                                <div class="project-lists-container">
                                    <div class="assigned-projects">
                                        <h4>Assigned Projects</h4>
                                        <input type="text" id="assigned-projects-search" placeholder="Search Projects...">
                                        <ul id="assigned-projects-list" class="project-list assigned-projects-background ">
                                        <?php foreach ($assigned as $project) {
                                            echo '<li class="project-item assigned-projects" data-id="' . esc_attr($project->ID) . '">' 
                                            . esc_html($project->name).'<br />'.esc_html($project->client_name) . '</li>';
                                        } ?>
                                        </ul>
                                    </div>
                                    <br clear="all" />
                                    <div class="available-projects">
                                        <h4>Available Projects</h4>
                                        <input type="text" id="available-projects-search" placeholder="Search Projects...">
                                        <ul id="available-projects-list" class="project-list">
                                            <?php
                                            foreach ($projects as $project) {
                                                echo '<li class="project-item available-projects" data-id="' . esc_attr($project->ID) . '">' 
                                                . esc_html($project->name).'<br />'.esc_html($project->client_name) . '</li>';
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
                <br clear="all" /><?php submit_button('Update Team Member', 'ml-2'); ?>
            </form>
        </div>
        <?php
    }

}
