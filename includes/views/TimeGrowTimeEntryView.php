<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowTimeEntryView {

    public function display($time_entries, $filter_options = [], $current_filters = []) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Merge with defaults
        $current_filters = array_merge([
            'orderby' => 'date',
            'order' => 'DESC',
            'filter_project' => '',
            'filter_member' => '',
            'filter_billable' => '',
            'filter_billed' => '',
            'filter_entry_type' => '',
            'filter_search' => ''
        ], $current_filters);

        // Helper function for sortable column headers
        $sortable_link = function($column, $label) use ($current_filters) {
            $order = ($current_filters['orderby'] == $column && $current_filters['order'] == 'ASC') ? 'DESC' : 'ASC';
            $url = add_query_arg(array_merge($current_filters, ['orderby' => $column, 'order' => $order]));
            return sprintf('<a href="%s"><span>%s</span><span class="sorting-indicator"></span></a>', esc_url($url), esc_html($label));
        };

        // Check if any filters are active
        $has_filters = !empty($current_filters['filter_project']) || !empty($current_filters['filter_member']) ||
                       !empty($current_filters['filter_billable']) || !empty($current_filters['filter_billed']) ||
                       !empty($current_filters['filter_entry_type']) || !empty($current_filters['filter_search']);
        ?>
        <div class="wrap">
        <!-- Modern Header -->
        <div class="timegrow-modern-header">
            <div class="timegrow-header-content">
                <h1><?php esc_html_e('Time Entries', 'timegrow'); ?></h1>
                <p class="subtitle"><?php esc_html_e('Track manual and clocked time entries for projects', 'timegrow'); ?></p>
            </div>
            <div class="timegrow-header-illustration">
                <span class="dashicons dashicons-clock"></span>
            </div>
        </div>

        <div class="tablenav top">
            <div class="alignleft actions">
                <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-time-entry-add'); ?>" class="button button-primary">Add New Entry</a>
            </div>
            <br class="clear">
        </div>

        <div class="tablenav top">
            <div class="alignleft actions">
                <input type="search" id="filter_search" name="s" value="<?php echo esc_attr($current_filters['filter_search']); ?>" placeholder="<?php esc_attr_e('Search time entries...', 'timegrow'); ?>">

                <select id="filter_project" name="filter_project">
                    <option value=""><?php esc_html_e('All Projects', 'timegrow'); ?></option>
                    <?php foreach ($filter_options['projects'] as $id => $name) : ?>
                        <option value="<?php echo esc_attr($id); ?>" <?php selected($current_filters['filter_project'], $id); ?>>
                            <?php echo esc_html($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select id="filter_member" name="filter_member">
                    <option value=""><?php esc_html_e('All Members', 'timegrow'); ?></option>
                    <?php foreach ($filter_options['members'] as $id => $name) : ?>
                        <option value="<?php echo esc_attr($id); ?>" <?php selected($current_filters['filter_member'], $id); ?>>
                            <?php echo esc_html($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select id="filter_billable" name="filter_billable">
                    <option value=""><?php esc_html_e('All Billable', 'timegrow'); ?></option>
                    <option value="1" <?php selected($current_filters['filter_billable'], '1'); ?>><?php esc_html_e('Billable', 'timegrow'); ?></option>
                    <option value="0" <?php selected($current_filters['filter_billable'], '0'); ?>><?php esc_html_e('Non-Billable', 'timegrow'); ?></option>
                </select>

                <select id="filter_billed" name="filter_billed">
                    <option value=""><?php esc_html_e('All Billed', 'timegrow'); ?></option>
                    <option value="1" <?php selected($current_filters['filter_billed'], '1'); ?>><?php esc_html_e('Billed', 'timegrow'); ?></option>
                    <option value="0" <?php selected($current_filters['filter_billed'], '0'); ?>><?php esc_html_e('Not Billed', 'timegrow'); ?></option>
                </select>

                <select id="filter_entry_type" name="filter_entry_type">
                    <option value=""><?php esc_html_e('All Types', 'timegrow'); ?></option>
                    <option value="MAN" <?php selected($current_filters['filter_entry_type'], 'MAN'); ?>><?php esc_html_e('Manual', 'timegrow'); ?></option>
                    <option value="IN" <?php selected($current_filters['filter_entry_type'], 'IN'); ?>><?php esc_html_e('Clock In', 'timegrow'); ?></option>
                    <option value="OUT" <?php selected($current_filters['filter_entry_type'], 'OUT'); ?>><?php esc_html_e('Clock Out', 'timegrow'); ?></option>
                </select>

                <button type="button" id="filter_time_entries" class="button"><?php esc_html_e('Filter', 'timegrow'); ?></button>

                <?php if ($has_filters) : ?>
                    <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-time-entries-list'); ?>" class="button"><?php esc_html_e('Clear Filters', 'timegrow'); ?></a>
                <?php endif; ?>
            </div>
            <br class="clear">
        </div>

        <table class="wp-list-table widefat fixed striped table-view-list clients">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-name column-actions column-primary sortable <?php echo ($current_filters['orderby'] == 'date') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('date', __('Date', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-company sortable <?php echo ($current_filters['orderby'] == 'project_name') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('project_name', __('Project', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-name sortable <?php echo ($current_filters['orderby'] == 'member_name') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('member_name', __('Member', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-company">Type</th>
                    <th scope="col" class="manage-column column-document sortable <?php echo ($current_filters['orderby'] == 'billable') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('billable', __('Billable', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-document sortable <?php echo ($current_filters['orderby'] == 'billed') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('billed', __('Billed', 'timegrow')); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if ($time_entries) : ?>
                    <?php foreach ($time_entries as $item) : ?>
                        <tr>
                            <td class="column-name column-primary" data-colname="created_at">
                                <strong>
                                        <?php
                                            if($item->entry_type=='MAN') {
                                                echo esc_html($item->date);
                                            } else if($item->entry_type=='IN') {
                                                echo esc_html($item->clock_in_date);
                                            } else if($item->entry_type=='OUT') {
                                                echo esc_html($item->clock_out_date);
                                            }
                                        ?>
                                </strong>
                                <div class="row-actions visible">
                                    <span class="edit">
                                        <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-time-entry-edit&id=' . $item->ID); ?>" aria-label="Edit Company">Edit</a> | </span>
                                    </span>
                                </div>
                            </td>
                            <td class="column-document" data-colname="project_name"><?php echo esc_html($item->project_name); ?></td>
                            <td class="column-amount" data-colname="team_member"><?php echo esc_html($item->member_name); ?></td>
                            <td class="column-amount" data-colname="entry_type"><?php echo esc_html($item->entry_type); ?></td>
                            <td class="column-document" data-colname="billable">
                                <?php if ($item->billable == 1) : ?>
                                    <span class="timegrow-badge timegrow-badge-primary"><?php esc_html_e('Yes', 'timegrow'); ?></span>
                                <?php else : ?>
                                    <span class="timegrow-badge timegrow-badge-warning"><?php esc_html_e('No', 'timegrow'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-document" data-colname="billed">
                                <?php if ($item->billed == 1) : ?>
                                    <span class="timegrow-badge timegrow-badge-success"><?php esc_html_e('Yes', 'timegrow'); ?></span>
                                <?php else : ?>
                                    <span class="timegrow-badge timegrow-badge-inactive"><?php esc_html_e('No', 'timegrow'); ?></span>
                                <?php endif; ?>
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
                    <th scope="col" class="manage-column column-name column-actions column-primary">Date</th>
                    <th scope="col" class="manage-column column-company">Project</th>
                    <th scope="col" class="manage-column column-name">Member</th>
                    <th scope="col" class="manage-column column-company">Type</th>  
                    <th scope="col" class="manage-column column-document">Billable</th>
                    <th scope="col" class="manage-column column-document">Billed</th>
                </tr>
            </tfoot>
        </table>
    
        <div class="tablenav bottom">
            <div class="alignleft actions">
            <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-time-entry-add'); ?>" class="button button-primary">Add New Entry</a>
            </div>
            <br class="clear">
        </div>
    </div>
    <?php
    }

    public function add($projects, $members) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        ?>
        <div class="wrap">
            <h2>Add New Entry</h2>
        
            <form id="timegrow-company-form" class="wp-core-ui" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="time_entry_id" value="0">
                <input type="hidden" name="action" value="save_time_entry">
                <input type="hidden" name="add_item" value="1">
                <?php wp_nonce_field('timegrow_time_entry_nonce', 'timegrow_time_entry_nonce_field'); ?>

                <div class="metabox-holder columns-2">
                    <div class="postbox-container">
                        <div class="postbox">
                            <h3 class="hndle"><span>General Information</span></h3>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="project_id">Project <span class="required">*</span></label></th>
                                        <td>
                                        <select name="project_id" class="large-text" required>
                                            <option>Select a Project</option>
                                            <?php foreach ($projects as $project): ?>
                                                <option value="<?= esc_attr($project->ID); ?>">
                                                    <?= esc_html($project->name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="member_id">Member <span class="required">*</span></label></th>
                                        <td>
                                            <select name="member_id" class="large_text" required>
                                                <option>Select a Team Member</option>
                                                <?php foreach ($members as $member): ?>
                                                    <option value="<?= esc_attr($member->ID); ?>" >
                                                        <?= esc_html($member->name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="entry_type">Type <span class="required">*</span></label></th>
                                        <td>
                                            <select name="entry_type" required>
                                                    <option value="MAN" selected>Manual</option>
                                                    <option value="IN">Clock In</option>
                                                    <option value="OUT" >Clock Out</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="status">Status</label></th>
                                        <td>
                                            <select id="status" name="status">
                                                <option value="1" >Active</option>
                                                <option value="0" >Inactive</option>
                                            </select>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row"><label for="billable">Billable</label></th>
                                        <th>
                                            <input type="checkbox" name="billable" value="1" checked>
                                        </th>
                                    </tr>
                                    
                                </table>
                            </div>
                        </div>
                        <div class="postbox">
                            <h3 class="hndle"><span>Time Information</span></h3>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="clock_in_date">Clock In</label></th>
                                        <td>
                                            <input type="datetime-local" id="clock_in_date" name="clock_in_date" class="large-text conditional-field-hidden" value="">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="clock_out_date">Clock Out</label></th>
                                        <td>
                                            <input type="datetime-local" id="clock_out_date" name="clock_out_date" class="large-text conditional-field-hidden" value="">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="date">Date</label></th>
                                        <td>
                                            <input type="date" id="date" name="date" class="large-text conditional-field-hidden" value="">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="hours">Hours</label></th>
                                        <td>
                                            <input type="range" id="hours" name="hours" class="large-text conditional-field-hidden" min="0" max="24" step="0.25" value="0" oninput="this.nextElementSibling.value = this.value;this.nextElementSibling.nextElementSibling.value = this.value;">
                                            <output>0</output>
                                            <input type="hidden" id="hours" name="hours" class="large-text conditional-field-hidden" value="">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="postbox">
                            <h3 class="hndle"><span>Additional Information</span></h3>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="billable">Billed</label></th>
                                        <th>
                                            <label>No</label>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="notes">Description</label></th>
                                        <td>
                                            <textarea id="description" name="description" class="large-text" rows="5"></textarea>
                                        </td>
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
                
                </div>
                <br clear="all" />
                <?php submit_button('Add Entry'); ?>
            </form>
        </div>
        <?php
    }

    public function edit($time_entry, $projects, $members) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        ?>
        <div class="wrap">
            <h2>Edit Time Entry</h2>
        
            <form id="timegrow-company-form" class="wp-core-ui" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="time_entry_id" value="<?php echo esc_attr($time_entry->ID); ?>">
                <input type="hidden" name="action" value="save_time_entry">
                <input type="hidden" name="edit_item" value="1">
                <?php wp_nonce_field('timegrow_time_entry_nonce', 'timegrow_time_entry_nonce_field'); ?>
                <?php var_dump($time_entry); ?>
                <div class="metabox-holder columns-2">
                    <div class="postbox-container">
                        <div class="postbox">
                            <h3 class="hndle"><span>General Information</span></h3>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="project_id">Project <span class="required">*</span></label></th>
                                        <td>
                                        <select name="project_id" class="large-text" required>
                                            <?php foreach ($projects as $project): ?>
                                                <option value="<?= esc_attr($project->ID); ?>" <?= selected($project->ID, $time_entry->project_id); ?>>
                                                    <?= esc_html($project->name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="member_id">Member <span class="required">*</span></label></th>
                                        <td>
                                            <select name="member_id" class="large_text" required>
                                                <?php foreach ($members as $member): ?>
                                                    <option value="<?= esc_attr($member->ID); ?>" <?= selected($member->ID, $time_entry->member_id); ?>>
                                                        <?= esc_html($member->name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="entry_type">Type <span class="required">*</span></label></th>
                                        <td>
                                            <select name="entry_type" required>
                                                    <option value="MAN" selected>Manual</option>
                                                    <option value="IN" <?= selected('IN', $time_entry->entry_type); ?>>Clock In</option>
                                                    <option value="OUT" <?= selected('OUT', $time_entry->entry_type); ?>>Clock Out</option>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="status">Status</label></th>
                                        <td>
                                            <select id="status" name="status">
                                                <option value="1" <?php selected($time_entry->status, 1); ?>>Active</option>
                                                <option value="0" <?php selected($time_entry->status, 0); ?>>Inactive</option>
                                            </select>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th scope="row"><label for="billable">Billable</label></th>
                                        <th>
                                            <input type="checkbox" name="billable"  value="1" <?= checked($time_entry->billable, 1, false); ?>>
                                        </th>
                                    </tr>
                                    
                                </table>
                            </div>
                        </div>
                        <div class="postbox">
                            <h3 class="hndle"><span>Time Information</span></h3>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="clock_in_date">Clock In</label></th>
                                        <td>
                                            <input type="datetime-local" id="clock_in_date" name="clock_in_date" class="large-text conditional-field-hidden" value="<?php echo esc_attr($time_entry->clock_in_date); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="clock_out_date">Clock Out</label></th>
                                        <td>
                                            <input type="datetime-local" id="clock_out_date" name="clock_out_date" class="large-text conditional-field-hidden" value="<?php echo esc_attr($time_entry->clock_out_date); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="date">Date</label></th>
                                        <td>  <?php $date_format = get_option('date_format');
                                                $display_date = DateTime::createFromFormat('Y-m-d H:i:s', sanitize_text_field($time_entry->date))->format($date_format);
                                            ?>
                                            <input type="date" id="date" name="date" class="large-text conditional-field-hidden" value="<?php echo esc_attr($display_date); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="hours">Hours</label></th>
                                        <td>
                                            <input type="range" id="hours" name="hours" class="large-text conditional-field-hidden" min="0" max="24" step="0.01" value="<?php echo esc_attr($time_entry->hours); ?>" oninput="this.nextElementSibling.value = this.value">
                                            <output><?php echo esc_attr($time_entry->hours); ?></output>
                                            <input type="number" step="0.25" id="hours" name="hours" class="large-text conditional-field-hidden" value="<?php echo esc_attr($time_entry->hours); ?>">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <div class="postbox">
                            <h3 class="hndle"><span>Additional Information</span></h3>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="billable">Billed</label></th>
                                        <th>
                                            <label><?php echo ($time_entry->billed) ? 'Yes' : 'No'; ?></label>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="notes">Description</label></th>
                                        <td>
                                            <textarea id="description" name="description" class="large-text" rows="5"><?php echo esc_textarea($time_entry->description); ?></textarea>
                                        </td>
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
                                        <td><?php echo esc_html($time_entry->created_at); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Updated At</th>
                                        <td><?php echo esc_html($time_entry->updated_at); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                   
                </div>
                <br clear="all" />
                <?php submit_button('Update Entry'); ?>
                
            </form>
        </div>
        <?php
    }

}
