<?php
// client-edit.php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$prefix = $wpdb->prefix;

$client_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$companies = $wpdb->get_results("SELECT ID, name FROM {$prefix}timeflies_companies", ARRAY_A);
if ($client_id > 0) {
    $client = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$prefix}timeflies_clients WHERE ID = %d", $client_id),
        ARRAY_A
    );
} else {
    $client = null;
}

if (!$client) {
    echo '<h2>Client not found. Please contact your administrator.</h2>';
    exit;
}
?>

<div class="wrap">
    <h2>Update Client</h2>

    <form id="timeflies-client-form" class="wp-core-ui" method="POST">
        <input type="hidden" name="client_id" value="<?php echo esc_attr($client_id); ?>">
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
                                            <option value="<?php echo esc_attr($company['ID']); ?>"
                                                <?php echo (isset($client) && $client['company_id'] == $company['ID']) ? 'selected' : ''; ?>>
                                                <?php echo esc_html($company['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="name">Client Name <span class="required">*</span></label></th>
                                <td>
                                    <input type="text" id="name" name="name" class="regular-text" value="<?php echo isset($client['name']) ? esc_attr($client['name']) : ''; ?>" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="document_number">Document Number</label></th>
                                <td>
                                    <input type="text" id="document_number" name="document_number" class="regular-text" value="<?php echo isset($client['document_number']) ? esc_attr($client['document_number']) : ''; ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="default_flat_fee">Default Flat Fee (€)</label></th>
                                <td>
                                    <input type="number" id="default_flat_fee" name="default_flat_fee" class="regular-text" step="0.01" min="0" value="<?php echo isset($client['default_flat_fee']) ? esc_attr($client['default_flat_fee']) : '0.00'; ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="currency">Currency</label></th>
                                <td>
                                <select id="currency" name="currency" class="regular-text" required>
                                        <option value="dollar" selected>$</option>
                                        <option value="euro" <?php selected($client['currency'], 'euro'); ?>>€</option>
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
                                    <input type="text" id="contact_person" name="contact_person" class="regular-text" value="<?php echo isset($client['contact_person']) ? esc_attr($client['contact_person']) : ''; ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="email">Email</label></th>
                                <td>
                                    <input type="email" id="email" name="email" class="regular-text" value="<?php echo isset($client['email']) ? esc_attr($client['email']) : ''; ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="phone">Phone</label></th>
                                <td>
                                    <input type="text" id="phone" name="phone" class="regular-text" value="<?php echo isset($client['phone']) ? esc_attr($client['phone']) : ''; ?>">
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
                                <td><?php echo esc_html($client['created_at']); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Updated At</th>
                                <td><?php echo esc_html($client['updated_at']); ?></td>
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
                                    <input type="text" id="address_1" name="address_1" class="regular-text" value="<?php echo isset($client['address_1']) ? esc_attr($client['address_1']) : ''; ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="address_1">Address 2</label></th>
                                <td>
                                    <input type="text" id="address_2" name="address_2" class="regular-text" value="<?php echo isset($client['address_2']) ? esc_attr($client['address_2']) : ''; ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="city">City</label></th>
                                <td>
                                    <input type="text" id="city" name="city" class="regular-text" value="<?php echo isset($client['city']) ? esc_attr($client['city']) : ''; ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="state">State</label></th>
                                <td>
                                    <input type="text" id="state" name="state" class="regular-text" value="<?php echo isset($client['state']) ? esc_attr($client['state']) : ''; ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="postal_code">Postal Code</label></th>
                                <td>
                                    <input type="text" id="postal_code" name="postal_code" class="regular-text" value="<?php echo isset($client['postal_code']) ? esc_attr($client['postal_code']) : ''; ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="country">Country</label></th>
                                <td>
                                    <input type="text" id="country" name="country" class="regular-text" value="<?php echo isset($client['country']) ? esc_attr($client['country']) : ''; ?>">
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
                                    <input type="url" id="website" name="website" class="regular-text" value="<?php echo isset($client['website']) ? esc_attr($client['website']) : ''; ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="status">Status</label></th>
                                <td>
                                    <select id="status" name="status" class="regular-text">
                                        <option value="1" <?php echo (isset($client) && $client['status'] == 1) ? 'selected' : ''; ?>>Active</option>
                                        <option value="0" <?php echo (isset($client) && $client['status'] == 0) ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php submit_button('Update Client'); ?>
    </form>
</div>