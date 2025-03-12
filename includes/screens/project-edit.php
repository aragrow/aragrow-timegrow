<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$prefix = $wpdb->prefix;

$project_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$integrations = get_option('timeflies_integration_settings');
if ($integrations['wc_clients'] && class_exists('WooCommerce')) {
    $integration = new Timeflies_Integration();
    $clients = $integration->get_wc_customers('all', ['ID','name'], ['name']);
} else {    
    $clients = $wpdb->get_results("SELECT ID, name FROM {$prefix}timeflies_clients order by name", ARRAY_A);
}

if ($project_id > 0) {
    $project = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM {$prefix}timeflies_projects WHERE ID = %d", $project_id),
        ARRAY_A
    );
} else {
    echo '<h2>Project not found. Please contact your administrator.</h2>';
    exit;
}
?>

<div class="wrap">
    <h2>Edit Project</h2>

    <form id="timeflies-project-form" class="wp-core-ui" method="POST">
        <input type="hidden" name="project_id" value="<?php echo esc_attr($project_id); ?>">
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
                                    <input type="text" id="name" name="name" class="regular-text" value="<?php echo esc_attr($project['name']); ?>" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="client_id">Client <span class="required">*</span></label></th>
                                <td>
                                    <select id="client_id" name="client_id" class="regular-text" required>
                                        <option value="">Select a Client</option>
                                        <?php foreach ($clients as $client) : ?>
                                            <option value="<?php echo esc_attr($client['ID']); ?>" <?php selected($project['client_id'], $client['ID']); ?>><?php echo esc_html($client['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="start_date">Start Date</label></th>
                                <td>
                                    <input type="date" id="start_date" name="start_date" class="regular-text" value="<?php echo esc_attr($project['start_date']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="end_date">End Date</label></th>
                                <td>
                                    <input type="date" id="end_date" name="end_date" class="regular-text" value="<?php echo esc_attr($project['end_date']); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="estimate_hours">Estimate Hours</label></th>
                                <td>
                                    <input type="text" id="estimate_hours" name="estimate_hours" class="regular-text" readonly value="<?php echo esc_attr($project['estimate_hours']); ?>">
                                    <div id="estimate_hours_slider" style="margin-top: 10px;"></div>
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
                                <th scope="row"><label for="description">Description</label></th>
                                <td>
                                    <textarea id="description" name="description" class="large-text" rows="5"><?php echo esc_textarea($project['description']); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="status">Status</label></th>
                                <td>
                                    <select id="status" name="status" class="regular-text">
                                        <option value="1" <?php selected($project['status'], 1); ?>>Active</option>
                                        <option value="8" <?php selected($project['status'], 8); ?>>Completed</option>
                                        <option value="5" <?php selected($project['status'], 5); ?>>On Hold</option>
                                        <option value="9" <?php selected($project['status'], 9); ?>>Cancelled</option>
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
                                <td><?php echo esc_html($project['created_at']); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Updated At</th>
                                <td><?php echo esc_html($project['updated_at']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php submit_button('Update Project'); ?>
    </form>
</div>
