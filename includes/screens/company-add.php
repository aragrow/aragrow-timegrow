<?php
// company-add.php

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h2>Add New Company</h2>

    <form id="timeflies-company-form" class="wp-core-ui" method="POST">
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
