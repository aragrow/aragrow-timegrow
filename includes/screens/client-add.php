<?php
// client-add.php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$prefix = $wpdb->prefix;

$companies = $wpdb->get_results("SELECT ID, name FROM {$prefix}timeflies_companies ORDER BY name", ARRAY_A);
?>

<div class="wrap">
    <h2>Add New Client</h2>

    <form id="timeflies-client-form" class="wp-core-ui" method="POST">
        <input type="hidden" name="client_id" value="0">
        <?php wp_nonce_field('timeflies_client_nonce', 'timeflies_client_nonce_field'); ?>

        <div class="metabox-holder columns-2">
            <div class="postbox-container">
                <div class="postbox">
                    <h3 class="hndle"><span>Company Information</span></h3>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="company_id">Company <span class="required">*</span></label></th>
                                <td>
                                    <select id="company_id" name="company_id" class="regular-text" required>
                                        <option value="">Select a Company</option>
                                        <?php foreach ($companies as $company) : ?>
                                            <option value="<?php echo esc_attr($company['ID']); ?>"><?php echo esc_html($company['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="name">Client Name <span class="required">*</span></label></th>
                                <td>
                                    <input type="text" id="name" name="name" class="regular-text" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="document_number">Document Number</label></th>
                                <td>
                                    <input type="text" id="document_number" name="document_number" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="default_flat_fee">Default Flat Fee</label></th>
                                <td>
                                    <input type="number" id="default_flat_fee" name="default_flat_fee" class="regular-text" step="0.01" min="0" value="0.00">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="currency">Currency</label></th>
                                <td>
                                <select id="currency" name="currency" class="regular-text" required>
                                        <option value="dollar" selected>$</option>
                                        <option value="euro">€</option>
                                    </select>
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
                                    <input type="text" id="phone" name="phone" class="regular-text">
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
                                    <input type="text" id="address_1" name="address_1" class="regular-text" value="">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="address_1">Address 2</label></th>
                                <td>
                                    <input type="text" id="address_2" name="address_2" class="regular-text" value="">
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
                                <th scope="row"><label for="website">Website</label></th>
                                <td>
                                    <input type="url" id="website" name="website" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="status">Status</label></th>
                                <td>
                                    <select id="status" name="status" class="regular-text">
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php submit_button('Add Client'); ?>
    </form>
</div>