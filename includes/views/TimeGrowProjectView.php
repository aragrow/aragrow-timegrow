<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowProjectView {
    
    public function display($projects, $filter_options = [], $current_filters = []) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Merge with defaults
        $current_filters = array_merge([
            'orderby' => 'name',
            'order' => 'ASC',
            'filter_client' => '',
            'filter_status' => '',
            'filter_billable' => '',
            'filter_search' => ''
        ], $current_filters);

        // Helper function for sortable column headers
        $sortable_link = function($column, $label) use ($current_filters) {
            $order = ($current_filters['orderby'] == $column && $current_filters['order'] == 'ASC') ? 'DESC' : 'ASC';
            $url = add_query_arg(array_merge($current_filters, ['orderby' => $column, 'order' => $order]));
            $class = ($current_filters['orderby'] == $column) ? 'sorted ' . strtolower($current_filters['order']) : 'sortable';
            return sprintf('<a href="%s"><span>%s</span><span class="sorting-indicator"></span></a>', esc_url($url), esc_html($label));
        };

        // Check if any filters are active
        $has_filters = !empty($current_filters['filter_client']) || !empty($current_filters['filter_status']) ||
                       !empty($current_filters['filter_billable']) || !empty($current_filters['filter_search']);
        ?>
        <div class="wrap">
        <!-- Modern Header -->
        <div class="timegrow-modern-header">
            <div class="timegrow-header-content">
                <h1><?php esc_html_e('Projects', 'timegrow'); ?></h1>
                <p class="subtitle"><?php esc_html_e('Manage your client projects and track their progress', 'timegrow'); ?></p>
            </div>
            <div class="timegrow-header-illustration">
                <span class="dashicons dashicons-portfolio"></span>
            </div>
        </div>

        <div class="tablenav top">
            <div class="alignleft actions">
                <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-project-add'); ?>" class="button button-primary">Add New Project</a>
            </div>
            <br class="clear">
        </div>

        <div class="tablenav top">
            <div class="alignleft actions">
                <input type="search" id="filter_search" name="s" value="<?php echo esc_attr($current_filters['filter_search']); ?>" placeholder="<?php esc_attr_e('Search projects...', 'timegrow'); ?>">

                <select id="filter_client" name="filter_client">
                    <option value=""><?php esc_html_e('All Clients', 'timegrow'); ?></option>
                    <?php foreach ($filter_options['clients'] as $id => $name) : ?>
                        <option value="<?php echo esc_attr($id); ?>" <?php selected($current_filters['filter_client'], $id); ?>>
                            <?php echo esc_html($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select id="filter_status" name="filter_status">
                    <option value=""><?php esc_html_e('All Statuses', 'timegrow'); ?></option>
                    <option value="1" <?php selected($current_filters['filter_status'], '1'); ?>><?php esc_html_e('Active', 'timegrow'); ?></option>
                    <option value="0" <?php selected($current_filters['filter_status'], '0'); ?>><?php esc_html_e('Inactive', 'timegrow'); ?></option>
                </select>

                <select id="filter_billable" name="filter_billable">
                    <option value=""><?php esc_html_e('All Types', 'timegrow'); ?></option>
                    <option value="1" <?php selected($current_filters['filter_billable'], '1'); ?>><?php esc_html_e('Billable', 'timegrow'); ?></option>
                    <option value="0" <?php selected($current_filters['filter_billable'], '0'); ?>><?php esc_html_e('Non-Billable', 'timegrow'); ?></option>
                </select>

                <button type="button" id="filter_projects" class="button"><?php esc_html_e('Filter', 'timegrow'); ?></button>

                <?php if ($has_filters) : ?>
                    <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-projects-list'); ?>" class="button"><?php esc_html_e('Clear Filters', 'timegrow'); ?></a>
                <?php endif; ?>
            </div>
            <br class="clear">
        </div>

        <table class="wp-list-table widefat fixed striped table-view-list projects">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-name column-primary sortable <?php echo ($current_filters['orderby'] == 'name') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('name', __('Project Name', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-client sortable <?php echo ($current_filters['orderby'] == 'client_name') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('client_name', __('Client', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-start-date sortable <?php echo ($current_filters['orderby'] == 'start_date') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('start_date', __('Start Date', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-end-date sortable <?php echo ($current_filters['orderby'] == 'end_date') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('end_date', __('End Date', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-status sortable <?php echo ($current_filters['orderby'] == 'status') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('status', __('Status', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-billable sortable <?php echo ($current_filters['orderby'] == 'billable') ? 'sorted ' . strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $sortable_link('billable', __('Billable', 'timegrow')); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if ($projects) :?>
                    <?php foreach ($projects as &$item) :?>
                        <tr>
                            <td class="column-name column-primary" data-colname="Project Name">
                                <strong><?php echo esc_html($item->name); ?></strong>
                                <div class="row-actions visible">
                                    <span class="edit">
                                        <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-project-edit&id=' . $item->ID); ?>" >Edit</a>
                                    </span>
                                </div>
                            </td>
                            <td class="column-client" data-colname="Client"><?php echo esc_html($item->client_name); ?></td>
                            <td class="column-start-date" data-colname="Start Date"><?php echo esc_html($item->start_date); ?></td>
                            <td class="column-end-date" data-colname="End Date"><?php echo esc_html($item->end_date); ?></td>
                            <td class="column-status" data-colname="Status">
                                <?php if ($item->status == 1) : ?>
                                    <span class="timegrow-badge timegrow-badge-success"><?php esc_html_e('Active', 'timegrow'); ?></span>
                                <?php else : ?>
                                    <span class="timegrow-badge timegrow-badge-inactive"><?php esc_html_e('Inactive', 'timegrow'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="column-billable" data-colname="Billable">
                                <?php if ($item->billable == 1) : ?>
                                    <span class="timegrow-badge timegrow-badge-primary"><?php esc_html_e('Yes', 'timegrow'); ?></span>
                                <?php else : ?>
                                    <span class="timegrow-badge timegrow-badge-warning"><?php esc_html_e('No', 'timegrow'); ?></span>
                                <?php endif; ?>
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
                    <th scope="col" class="manage-column column-billable">Billable</th>
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
    <?php
    }

    public function add($clients, $woocommerce_products = null) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        ?>
        <div class="wrap">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Add New Project', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Create a new project for your client', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-plus-alt"></span>
                </div>
            </div>
        
            <form id="timegrow-project-form" class="wp-core-ui" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="project_id" value="0">
            <input type="hidden" name="add_item" value="1">
            
                <?php wp_nonce_field('timegrow_project_nonce', 'timegrow_project_nonce_field'); ?>

                <div class="metabox-holder columns-2">
                    <div class="postbox-container">
                    <?php $integrations = get_option('timegrow_integration_settings')?: false;
                            if ($integrations['wc_products'] && class_exists('WooCommerce')) { ?>
                            <div class="postbox">
                                <h3 class="hndle"><span>WooCommerce Integraton</span></h3>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row"><label for="product_id">WooCommerce Product <span class="required">*</span></label></th>
                                            <td>
                                                <select id="product_id" name="product_id" class="regular-text">
                                                    <option value="">Select a WooCommerce Product</option>
                                                    <?php foreach ($woocommerce_products as $item) : ?>
                                                        <option value="<?php echo esc_attr($item->ID); ?>"><?php echo esc_html($item->post_title); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="postbox">
                            <h3 class="hndle"><span>Project Information</span></h3>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="name">Project Name <span class="required">*</span></label></th>
                                        <td>
                                            <input type="text" id="name" name="name" class="regular-text" required>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="client_id">Client <span class="required">*</span></label></th>
                                        <td>
                                            <select id="client_id" name="client_id" class="regular-text" required>
                                                <option value="">Select a Client</option>
                                                <?php foreach ($clients as $client) : ?>
                                                    <option value="<?php echo esc_attr($client->ID); ?>"><?php echo esc_html($client->display_name); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="start_date">Start Date</label></th>
                                        <td>
                                            <input type="date" id="start_date" name="start_date" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="end_date">End Date</label></th>
                                        <td>
                                            <input type="date" id="end_date" name="end_date" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="estimate_hours">Estimate Hours</label></th>
                                        <td>
                                            <input type="text" id="estimate_hours" name="estimate_hours" class="regular-text" readonly value="">
                                            <div id="estimate_hours_slider" style="margin-top: 10px;"></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="default_flat_fee">Flat Fee</label></th>
                                        <td>
                                            <input type="number" id="default_flat_fee" name="default_flat_fee" class="regular-text" step="0.01" min="0" value="0.00">
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
                                        <th scope="row"><label for="description">Billable</label></th>
                                        <td>
                                            <input value="1" type="checkbox" id="billable" name="billable" checked>
                                        </td>
                                    </tr> 
                                    <tr>
                                        <th scope="row"><label for="description">Description</label></th>
                                        <td>
                                            <textarea id="description" name="description" class="large-text" rows="5"></textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="status">Status</label></th>
                                        <td>
                                            <select id="status" name="status" class="regular-text">
                                                <option value="1" selected>Active</option>
                                                <option value="8">Completed</option>
                                                <option value="5">On Hold</option>
                                                <option value="9">Cancelled</option>
                                            </select>
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
                <?php submit_button('Add Project'); ?>
            </form>
        </div>
        <?php
    }

    public function edit($project, $clients, $woocommerce_products) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        ?>
        <div class="wrap">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Edit Project', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Update project information and settings', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-edit"></span>
                </div>
            </div>
        
            <form id="timegrow-project-form" class="wp-core-ui" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="project_id" value="<?php echo esc_attr($project->ID); ?>">
                <input type="hidden" name="edit_item" value="1">
                <?php wp_nonce_field('timegrow_project_nonce', 'timegrow_project_nonce_field'); ?>
                <div class="metabox-holder columns-2">
                    <div class="postbox-container">
                    <?php $integrations = get_option('timegrow_integration_settings')?: false;
                            if ($integrations['wc_products'] && class_exists('WooCommerce')) { ?>
                            <div class="postbox">
                                <h3 class="hndle"><span>WooCommerce Integraton</span></h3>
                                <div class="inside">
                                    <table class="form-table">
                                        <tr>
                                            <th scope="row"><label for="product_id">WooCommerce Product <span class="required">*</span></label></th>
                                            <td>
                                                <select id="product_id" name="product_id" class="regular-text">
                                                    <option value="">Select a WooCommerce Product</option>
                                                    <?php foreach ($woocommerce_products as $item) : ?>
                                                        <option value="<?php echo esc_attr($item->ID); ?>" <?php selected($item->ID, $project->product_id); ?>><?php echo esc_html($item->post_title);?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="postbox">
                            <h3 class="hndle"><span>Project Information</span></h3>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="name">Project Name <span class="required">*</span></label></th>
                                        <td>
                                            <input type="text" id="name" name="name" class="regular-text" value ="<?php echo esc_html($project->name); ?>" required>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="client_id">Client <span class="required">*</span></label></th>
                                        <td>
                                            <select id="client_id" name="client_id" class="regular-text" required>
                                                <option value="">Select a Client</option>
                                                <?php foreach ($clients as $item) : ?>
                                                    <option value="<?php echo esc_attr($item->ID); ?>" <?php selected($item->ID, $project->client_id); ?>><?php echo esc_html($item->display_name); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="start_date">Start Date</label></th>
                                        <td>
                                            <input type="date" id="start_date" name="start_date" value="<?php echo esc_html($project->start_date); ?>" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="end_date">End Date</label></th>
                                        <td>
                                            <input type="date" id="end_date" name="end_date" value="<?php echo esc_html($project->end_date); ?>" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="estimate_hours">Estimate Hours</label></th>
                                        <td>
                                            <input type="text" id="estimate_hours" name="estimate_hours" class="regular-text" readonly value ="<?php echo esc_html($project->estimate_hours); ?>">
                                            <div id="estimate_hours_slider" style="margin-top: 10px;"></div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="default_flat_fee">Flat Fee</label></th>
                                        <td>
                                            <input type="number" id="default_flat_fee" name="default_flat_fee" class="regular-text" step="0.01" min="0" value="<?php echo esc_html($project->default_flat_fee); ?>">
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
                                        <th scope="row"><label for="description">Billable</label></th>
                                        <td>
                                            <input value="1" type="checkbox" id="billable" name="billable" checked>
                                        </td>
                                    </tr> 
                                    <tr>
                                        <th scope="row"><label for="description">Description</label></th>
                                        <td>
                                            <textarea id="description" name="description" class="large-text" rows="5"><?php echo esc_html($project->description); ?></textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="status">Status</label></th>
                                        <td>
                                            <select id="status" name="status" class="regular-text">
                                                <option value="1" selected>Active</option>
                                                <option value="8" <?php selected("8", $project->status); ?>>Completed</option>
                                                <option value="5" <?php selected("5", $project->status); ?>>On Hold</option>
                                                <option value="9" <?php selected("9", $project->status); ?>>Cancelled</option>
                                            </select>
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
                                        <td><?php echo esc_html($project->created_at); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Updated At</th>
                                        <td><?php echo esc_html($project->updated_at); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <br clear="all" />                                         
                <?php submit_button('Edit Project'); ?>
            </form>
        </div>
        <?php
    }

}
