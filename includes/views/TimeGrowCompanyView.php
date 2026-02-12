<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowCompanyView {
    
    public function display($companies, $filter_options = [], $current_filters = []) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Set default values for current filters
        $current_filters = array_merge([
            'orderby' => 'name',
            'order' => 'ASC',
            'filter_state' => '',
            'filter_city' => '',
            'filter_country' => '',
            'filter_status' => '',
            'filter_search' => ''
        ], $current_filters);

        // Set default values for filter options
        $filter_options = array_merge([
            'states' => [],
            'cities' => [],
            'countries' => []
        ], $filter_options);

        // Helper function to generate sortable column headers
        $get_sortable_link = function($column, $label) use ($current_filters) {
            $base_url = admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-companies-list');
            $current_orderby = isset($current_filters['orderby']) ? $current_filters['orderby'] : 'name';
            $current_order = isset($current_filters['order']) ? $current_filters['order'] : 'ASC';

            // Determine new order direction
            $new_order = ($current_orderby === $column && $current_order === 'ASC') ? 'DESC' : 'ASC';

            // Build URL with all current filters
            $url_params = [
                'page' => TIMEGROW_PARENT_MENU . '-companies-list',
                'orderby' => $column,
                'order' => $new_order
            ];

            // Preserve filter parameters
            if (!empty($current_filters['filter_state'])) $url_params['filter_state'] = $current_filters['filter_state'];
            if (!empty($current_filters['filter_city'])) $url_params['filter_city'] = $current_filters['filter_city'];
            if (!empty($current_filters['filter_country'])) $url_params['filter_country'] = $current_filters['filter_country'];
            if (!empty($current_filters['filter_status'])) $url_params['filter_status'] = $current_filters['filter_status'];

            $url = add_query_arg($url_params, admin_url('admin.php'));

            // Determine sort indicator
            $sorted_class = '';
            $sorted_indicator = '';
            if ($current_orderby === $column) {
                $sorted_class = 'sorted';
                $sorted_indicator = $current_order === 'ASC' ? 'asc' : 'desc';
            } else {
                $sorted_class = 'sortable';
                $sorted_indicator = 'desc';
            }

            return sprintf(
                '<a href="%s"><span>%s</span><span class="sorting-indicators"><span class="sorting-indicator %s" aria-hidden="true"></span></span></a>',
                esc_url($url),
                esc_html($label),
                esc_attr($sorted_indicator)
            );
        };
        ?>
        <div class="wrap timegrow-modern-wrapper">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Companies', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Manage your client companies and their information', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-building"></span>
                </div>
            </div>

            <div class="tablenav top">
                <div class="alignleft actions">
                    <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-company-add'); ?>" class="button button-primary">Add New Company</a>
                </div>
                <br class="clear">
            </div>

            <div class="tablenav top" style="border-top: none; padding-top: 0;">
                <div class="alignleft actions">
                    <input type="search" id="filter_search" name="s" placeholder="<?php esc_attr_e('Search companies...', 'timegrow'); ?>" value="<?php echo esc_attr($current_filters['filter_search']); ?>" />
                    <select name="filter_state" id="filter_state">
                        <option value=""><?php esc_html_e('All States', 'timegrow'); ?></option>
                        <?php foreach ($filter_options['states'] as $state) : ?>
                            <option value="<?php echo esc_attr($state); ?>" <?php selected($current_filters['filter_state'], $state); ?>>
                                <?php echo esc_html($state); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="filter_city" id="filter_city">
                        <option value=""><?php esc_html_e('All Cities', 'timegrow'); ?></option>
                        <?php foreach ($filter_options['cities'] as $city) : ?>
                            <option value="<?php echo esc_attr($city); ?>" <?php selected($current_filters['filter_city'], $city); ?>>
                                <?php echo esc_html($city); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="filter_country" id="filter_country">
                        <option value=""><?php esc_html_e('All Countries', 'timegrow'); ?></option>
                        <?php foreach ($filter_options['countries'] as $country) : ?>
                            <option value="<?php echo esc_attr($country); ?>" <?php selected($current_filters['filter_country'], $country); ?>>
                                <?php echo esc_html($country); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="filter_status" id="filter_status">
                        <option value=""><?php esc_html_e('All Statuses', 'timegrow'); ?></option>
                        <option value="1" <?php selected($current_filters['filter_status'], '1'); ?>><?php esc_html_e('Active', 'timegrow'); ?></option>
                        <option value="0" <?php selected($current_filters['filter_status'], '0'); ?>><?php esc_html_e('Inactive', 'timegrow'); ?></option>
                    </select>
                    <button type="button" id="filter_companies" class="button"><?php esc_html_e('Filter', 'timegrow'); ?></button>
                    <?php if (!empty($current_filters['filter_search']) || !empty($current_filters['filter_state']) || !empty($current_filters['filter_city']) || !empty($current_filters['filter_country']) || !empty($current_filters['filter_status'])) : ?>
                        <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-companies-list'); ?>" class="button"><?php esc_html_e('Clear Filters', 'timegrow'); ?></a>
                    <?php endif; ?>
                </div>
                <br class="clear">
            </div>

        <table class="wp-list-table widefat fixed striped table-view-list companies">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-name column-primary sortable <?php echo ($current_filters['orderby'] === 'name') ? 'sorted' : ''; ?> <?php echo ($current_filters['orderby'] === 'name') ? strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $get_sortable_link('name', __('Name', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-legal-name sortable <?php echo ($current_filters['orderby'] === 'legal_name') ? 'sorted' : ''; ?> <?php echo ($current_filters['orderby'] === 'legal_name') ? strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $get_sortable_link('legal_name', __('Legal Name', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-state sortable <?php echo ($current_filters['orderby'] === 'state') ? 'sorted' : ''; ?> <?php echo ($current_filters['orderby'] === 'state') ? strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $get_sortable_link('state', __('State', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-city sortable <?php echo ($current_filters['orderby'] === 'city') ? 'sorted' : ''; ?> <?php echo ($current_filters['orderby'] === 'city') ? strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $get_sortable_link('city', __('City', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-country sortable <?php echo ($current_filters['orderby'] === 'country') ? 'sorted' : ''; ?> <?php echo ($current_filters['orderby'] === 'country') ? strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $get_sortable_link('country', __('Country', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-status sortable <?php echo ($current_filters['orderby'] === 'status') ? 'sorted' : ''; ?> <?php echo ($current_filters['orderby'] === 'status') ? strtolower($current_filters['order']) : ''; ?>">
                        <?php echo $get_sortable_link('status', __('Status', 'timegrow')); ?>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($companies)) : ?>
                    <?php foreach ($companies as $item) : ?>
                        <tr>
                            <td class="column-name column-primary" data-colname="Name">
                                <strong><?php echo esc_html($item->name); ?></strong>
                                <div class="row-actions">
                                    <span class="edit">
                                        <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-company-edit&id=' . $item->ID); ?>" aria-label="Edit Company">Edit</a>
                                    </span>
                                </div>
                            </td>
                            <td class="column-legal-name" data-colname="Legal Name"><?php echo esc_html($item->legal_name); ?></td>
                            <td class="column-state" data-colname="State"><?php echo esc_html($item->state); ?></td>
                            <td class="column-city" data-colname="City"><?php echo esc_html($item->city); ?></td>
                            <td class="column-country" data-colname="Country"><?php echo esc_html($item->country); ?></td>
                            <td class="column-status" data-colname="Status">
                                <?php if ($item->status == 1) : ?>
                                    <span class="timegrow-badge timegrow-badge-success"><?php esc_html_e('Active', 'timegrow'); ?></span>
                                <?php else : ?>
                                    <span class="timegrow-badge timegrow-badge-inactive"><?php esc_html_e('Inactive', 'timegrow'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e('No companies found.', 'timegrow'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th scope="col" class="manage-column column-name column-primary sortable">
                        <?php echo $get_sortable_link('name', __('Name', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-legal-name sortable">
                        <?php echo $get_sortable_link('legal_name', __('Legal Name', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-state sortable">
                        <?php echo $get_sortable_link('state', __('State', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-city sortable">
                        <?php echo $get_sortable_link('city', __('City', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-country sortable">
                        <?php echo $get_sortable_link('country', __('Country', 'timegrow')); ?>
                    </th>
                    <th scope="col" class="manage-column column-status sortable">
                        <?php echo $get_sortable_link('status', __('Status', 'timegrow')); ?>
                    </th>
                </tr>
            </tfoot>
        </table>
    
        <div class="tablenav bottom">
            <div class="alignleft actions">
                <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-company-add'); ?>" class="button button-primary">Add New Company</a>
            </div>
            <br class="clear">
        </div>
    </div>
    <?php
    }

    public function add() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        ?>
        <div class="wrap timegrow-modern-wrapper">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Add New Company', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Create a new company profile with contact and address information', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-building"></span>
                </div>
            </div>

            <form id="timegrow-company-form" class="wp-core-ui" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="company_id" value="0">
                <input type="hidden" name="add_item" value="1" />
                <?php wp_nonce_field('timegrow_company_nonce', 'timegrow_company_nonce_field'); ?>

                <div class="metabox-holder columns-2">
                    <div class="postbox-container">
                        <div class="postbox">
                            <h3 class="hndle"><span>Company Information</span></h3>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="name">Company Name <span class="required">*</span></label></th>
                                        <td>
                                            <input type="text" id="name" name="name" class="regular-text" required>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="legal_name">Legal Name</label></th>
                                        <td>
                                            <input type="text" id="legal_name" name="legal_name" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="document_number">Document Number</label></th>
                                        <td>
                                            <input type="text" id="document_number" name="document_number" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="default_flat_fee">Default Flat Fee (€)</label></th>
                                        <td>
                                            <input type="number" id="default_flat_fee" name="default_flat_fee" class="regular-text" step="0.01" min="0" value="0.00">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="postbox">
                            <h3 class="hndle"><span>Contact Information</span></h3>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="contact_person">Contact Person</label></th>
                                        <td>
                                            <input type="text" id="contact_person" name="contact_person" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="email">Email</label></th>
                                        <td>
                                            <input type="email" id="email" name="email" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="phone">Phone</label></th>
                                        <td>
                                            <input type="tel" id="phone" name="phone" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="website">Website</label></th>
                                        <td>
                                            <input type="url" id="website" name="website" class="regular-text">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="postbox-container">
                        <div class="postbox">
                            <h3 class="hndle"><span>Address</span></h3>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="address_1">Address 1</label></th>
                                        <td>
                                            <input type="text" id="address_1" name="address_1" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="address_2">Address 2</label></th>
                                        <td>
                                            <input type="text" id="address_2" name="address_2" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="city">City</label></th>
                                        <td>
                                            <input type="text" id="city" name="city" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="state">State</label></th>
                                        <td>
                                            <input type="text" id="state" name="state" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="postal_code">Postal Code</label></th>
                                        <td>
                                            <input type="text" id="postal_code" name="postal_code" class="regular-text">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="country">Country</label></th>
                                        <td>
                                            <input type="text" id="country" name="country" class="regular-text">
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
                                        <th scope="row"><label for="notes">Notes</label></th>
                                        <td>
                                            <textarea id="notes" name="notes" class="large-text" rows="5"></textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="status">Status</label></th>
                                        <td>
                                            <select id="status" name="status">
                                                <option value="1" selected>Active</option>
                                                <option value="0">Inactive</option>
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

                <?php submit_button('Add Company'); ?>
            </form>
        </div>
        <?php
    }

    public function edit($company) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        ?>
        <div class="wrap timegrow-modern-wrapper">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Edit Company', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Update company profile, contact details, and address information', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-building"></span>
                </div>
            </div>

            <form id="timegrow-company-form" class="wp-core-ui" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="company_id" value="<?php echo esc_attr($company->ID); ?>">
                <input type="hidden" name="edit_item" value="1" />
                <?php wp_nonce_field('timegrow_company_nonce', 'timegrow_company_nonce_field'); ?>

                <div class="metabox-holder columns-2">
                    <div class="postbox-container">
                        <div class="postbox">
                            <h3 class="hndle"><span>Company Information</span></h3>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="name">Company Name <span class="required">*</span></label></th>
                                        <td>
                                            <input type="text" id="name" name="name" class="regular-text" value="<?php echo esc_attr($company->name); ?>" required>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="legal_name">Legal Name</label></th>
                                        <td>
                                            <input type="text" id="legal_name" name="legal_name" class="regular-text" value="<?php echo esc_attr($company->legal_name); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="document_number">Document Number</label></th>
                                        <td>
                                            <input type="text" id="document_number" name="document_number" class="regular-text" value="<?php echo esc_attr($company->document_number); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="default_flat_fee">Default Flat Fee</label></th>
                                        <td>
                                            <input type="number" id="default_flat_fee" name="default_flat_fee" class="regular-text" step="0.01" min="0" value="<?php echo esc_attr($company->default_flat_fee); ?>">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="postbox">
                            <h3 class="hndle"><span>Contact Information</span></h3>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="contact_person">Contact Person</label></th>
                                        <td>
                                            <input type="text" id="contact_person" name="contact_person" class="regular-text" value="<?php echo esc_attr($company->contact_person); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="email">Email</label></th>
                                        <td>
                                            <input type="email" id="email" name="email" class="regular-text" value="<?php echo esc_attr($company->email); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="phone">Phone</label></th>
                                        <td>
                                            <input type="tel" id="phone" name="phone" class="regular-text" value="<?php echo esc_attr($company->phone); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="website">Website</label></th>
                                        <td>
                                            <input type="url" id="website" name="website" class="regular-text" value="<?php echo esc_attr($company->website); ?>">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="postbox-container">
                        <div class="postbox">
                            <h3 class="hndle"><span>Address</span></h3>
                            <div class="inside">
                                <table class="form-table">
                                    <tr>
                                        <th scope="row"><label for="address_1">Address 1</label></th>
                                        <td>
                                            <input type="text" id="address_1" name="address_1" class="regular-text" value="<?php echo esc_attr($company->address_1); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="address_2">Address 2</label></th>
                                        <td>
                                            <input type="text" id="address_2" name="address_2" class="regular-text" value="<?php echo esc_attr($company->address_2); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="city">City</label></th>
                                        <td>
                                            <input type="text" id="city" name="city" class="regular-text" value="<?php echo esc_attr($company->city); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="state">State</label></th>
                                        <td>
                                            <input type="text" id="state" name="state" class="regular-text" value="<?php echo esc_attr($company->state); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="postal_code">Postal Code</label></th>
                                        <td>
                                            <input type="text" id="postal_code" name="postal_code" class="regular-text" value="<?php echo esc_attr($company->postal_code); ?>">
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="country">Country</label></th>
                                        <td>
                                            <input type="text" id="country" name="country" class="regular-text" value="<?php echo esc_attr($company->country); ?>">
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
                                        <th scope="row"><label for="notes">Notes</label></th>
                                        <td>
                                            <textarea id="notes" name="notes" class="large-text" rows="5"><?php echo esc_textarea($company->notes); ?></textarea>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="status">Status</label></th>
                                        <td>
                                            <select id="status" name="status">
                                                <option value="1" <?php selected($company->status, 1); ?>>Active</option>
                                                <option value="0" <?php selected($company->status, 0); ?>>Inactive</option>
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
                                        <td><?php echo esc_html($company->created_at); ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Updated At</th>
                                        <td><?php echo esc_html($company->updated_at); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <?php submit_button('Update Company'); ?>
            </form>
        </div>
        <?php
    }

}
