<?php
// company-edit.php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$prefix = $wpdb->prefix;

$company_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($company_id > 0) {
    $company = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$prefix}timeflies_companies WHERE id = %d", $company_id),
        ARRAY_A
    );
} else {
    echo '<h2>Company not found. Please contact your administrator.</h2>';
    exit;
}
?>

<div class="wrap">
    <h2>Update Company</h2>

    <form id="timeflies-company-form" class="wp-core-ui" method="POST">
        <input type="hidden" name="company_id" value="<?php echo esc_attr($company_id); ?>">
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
                                    <input type="text" id="name" name="name" class="regular-text" value="<?php echo esc_attr($company['name']); ?>" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="legal_name">Legal Name</label></th>
                                <td>
                                    <input type="text" id="legal_name" name="legal_name" class="regular-text" value="<?php echo esc_attr($company['legal_name']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="document_number">Document Number</label></th>
                                <td>
                                    <input type="text" id="document_number" name="document_number" class="regular-text" value="<?php echo esc_attr($company['document_number']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="default_flat_fee">Default Flat Fee</label></th>
                                <td>
                                    <input type="number" id="default_flat_fee" name="default_flat_fee" class="regular-text" step="0.01" min="0" value="<?php echo esc_attr($company['default_flat_fee']); ?>">
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
                                    <input type="text" id="contact_person" name="contact_person" class="regular-text" value="<?php echo esc_attr($company['contact_person']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="email">Email</label></th>
                                <td>
                                    <input type="email" id="email" name="email" class="regular-text" value="<?php echo esc_attr($company['email']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="phone">Phone</label></th>
                                <td>
                                    <input type="tel" id="phone" name="phone" class="regular-text" value="<?php echo esc_attr($company['phone']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="website">Website</label></th>
                                <td>
                                    <input type="url" id="website" name="website" class="regular-text" value="<?php echo esc_attr($company['website']); ?>">
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
                                    <input type="text" id="address_1" name="address_1" class="regular-text" value="<?php echo esc_attr($company['address_1']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="address_2">Address 2</label></th>
                                <td>
                                    <input type="text" id="address_2" name="address_2" class="regular-text" value="<?php echo esc_attr($company['address_2']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="city">City</label></th>
                                <td>
                                    <input type="text" id="city" name="city" class="regular-text" value="<?php echo esc_attr($company['city']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="state">State</label></th>
                                <td>
                                    <input type="text" id="state" name="state" class="regular-text" value="<?php echo esc_attr($company['state']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="postal_code">Postal Code</label></th>
                                <td>
                                    <input type="text" id="postal_code" name="postal_code" class="regular-text" value="<?php echo esc_attr($company['postal_code']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="country">Country</label></th>
                                <td>
                                    <input type="text" id="country" name="country" class="regular-text" value="<?php echo esc_attr($company['country']); ?>">
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
                                    <textarea id="notes" name="notes" class="large-text" rows="5"><?php echo esc_textarea($company['notes']); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="status">Status</label></th>
                                <td>
                                    <select id="status" name="status">
                                        <option value="1" <?php selected($company['status'], 1); ?>>Active</option>
                                        <option value="0" <?php selected($company['status'], 0); ?>>Inactive</option>
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
                                <td><?php echo esc_html($company['created_at']); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Updated At</th>
                                <td><?php echo esc_html($company['updated_at']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php submit_button('Update Company'); ?>
    </form>
</div>
    