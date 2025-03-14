<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$prefix = $wpdb->prefix;

$integrations = get_option('timeflies_integration_settings');
if ($integrations['wc_clients'] && class_exists('WooCommerce')) {
    $integration = new Timeflies_Integration();
    // Get all customers
    $clients = $integration->get_wc_customers('all', ['ID','name'], ['name']);
} else {
    $clients = $wpdb->get_results("SELECT ID, name FROM {$prefix}timeflies_clients order by name", ARRAY_A);
}
?>

<div class="wrap">
    <h2>Add New Project</h2>

    <form id="timeflies-project-form" class="wp-core-ui" method="POST">
        <input type="hidden" name="project_id" value="0">
        <input type="hidden" name="action" value="save_project">
        <?php wp_nonce_field('timeflies_project_nonce', 'timeflies_project_nonce_field'); ?>

        <div class="metabox-holder columns-2">
            <div class="postbox-container">
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
                                            <option value="<?php echo esc_attr($client['ID']); ?>"><?php echo esc_html($client['name']); ?></option>
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
                                <td>
                                    <select id="status" name="status" class="regular-text">
                                        <option value="1" selected>Active</option>
                                        <option value="8">Completed</option>
                                        <option value="5">On Hold</option>
                                        <option value="9">Cancelled</option>
                                    </select>
                                </td>
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

        <?php submit_button('Add Project'); ?>
    </form>
</div>
