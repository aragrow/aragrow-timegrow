<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowCompanyView {
    
    public function display($companies) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        ?>
        <div class="wrap">
        <h2>All Companies</h2>
    
        <div class="tablenav top">
            <div class="alignleft actions">
                <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-company-add'); ?>" class="button button-primary">Add New Company</a>
            </div>
            <br class="clear">
        </div>
    
        <table class="wp-list-table widefat fixed striped table-view-list timeentries">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-name column-actions column-primary">Name</th>
                    <th scope="col" class="manage-column column-name">Legal Name</th>
                    <th scope="col" class="manage-column column-company">State</th>  
                    <th scope="col" class="manage-column column-document">City</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($companies) : ?>
                    <?php foreach ($companies as $item) : ?>
                        <tr>
                            <td class="column-name column-primary" data-colname="Name">
                                <strong><?php echo esc_html($item->name); ?></strong>
                                <div class="row-actions visible">
                                    <span class="edit">
                                         <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-company-edit&id=' . $item->ID); ?>" aria-label="Edit Company">Edit</a> | </span>
                                    </span>
                                </div>
                            </td>
                            <td class="column-amount" data-colname="legal_name"><?php echo esc_html($item->legal_name); ?></td>  
                            <td class="column-amount" data-colname="state"><?php echo esc_html($item->state); ?></td>  
                            <td class="column-document" data-colname="city"><?php echo esc_html($item->city); ?></td>
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
                    <th scope="col" class="manage-column column-name column-actions column-primary">Name</th>
                    <th scope="col" class="manage-column column-name">Legal Name</th>
                    <th scope="col" class="manage-column column-company">State</th>  
                    <th scope="col" class="manage-column column-document">City</th>
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
        <div class="wrap">
            <h2>Add New Company</h2>
        
            <form id="timeflies-company-form" class="wp-core-ui" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="company_id" value="0">
                <input type="hidden" name="acton" value="save_company">
                <?php wp_nonce_field('timeflies_company_nonce', 'timeflies_company_nonce_field'); ?>

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
                                                <option value="1">Active</option>
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
        <div class="wrap">
            <h2>Edit Company</h2>
        
            <form id="timeflies-company-form" class="wp-core-ui" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="company_id" value="<?php echo esc_attr($company->ID); ?>">
                <input type="hidden" name="action" value="save_company">
                <?php wp_nonce_field('timeflies_company_nonce', 'timeflies_company_nonce_field'); ?>

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
