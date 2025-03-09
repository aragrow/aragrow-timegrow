<?php

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$prefix = $wpdb->prefix;

$members = $wpdb->get_results("SELECT ID, name FROM {$prefix}timeflies_team_members", ARRAY_A);
$clients = $wpdb->get_results("SELECT ID, name FROM {$prefix}timeflies_clients", ARRAY_A);
$entry_type = 'manual';
?>

<div class="wrap">
    <h2>Add Time Entry</h2>

    <form id="timeflies-time-entry-form" class="wp-core-ui" method="POST">
        <input type="hidden" name="time_entry_id" value="0">
        <?php wp_nonce_field('timeflies_time_entry_nonce', 'timeflies_time_entry_nonce_field'); ?>

        <div class="metabox-holder columns-2">
            <div class="postbox-container">
                <div class="postbox">
                    <h3 class="hndle"><span>Entry Details</span></h3>
                    <div class="inside">
                        <table class="form-table">
                        <tr>
                                <th scope="row"><label for="member_id">Team <span class="required">*</span></label></th>
                                <td>
                                    <select id="member_id" name="member_id" class="regular-text" required>
                                        <option value="">Select a Team Member</option>
                                        <?php foreach ($members as $member) : ?>
                                            <option value="<?php echo esc_attr($member['ID']); ?>">
                                                <?php echo esc_html($member['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="client_id">Client <span class="required">*</span></label></th>
                                <td>
                                    <select id="client_id" name="client_id" class="regular-text" required>
                                        <option value="">Select a Client</option>
                                        <?php foreach ($clients as $client) : ?>
                                            <option value="<?php echo esc_attr($client['ID']); ?>">
                                                <?php echo esc_html($client['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="project_id">Project <span class="required">*</span></label></th>
                                <td>
                                    <select id="project_id" name="project_id" class="regular-text" required>
                                            <option value="">First select a Client</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="start_time">Start Time </label></th>
                                <td>
                                    <input type="datetime-local" id="start_time" name="start_time" value=""  class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="end_time">End Time </label></th>
                                <td>
                                    <input type="datetime-local" id="end_time" name="end_time" value="" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="end_time">Hours</label></th>
                                <td>
                                    <input type="text" id="hours" name="hours" value="" class="regular-text">
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="description">Description</label></th>
                                <td>
                                    <textarea id="description" name="description" class="large-text" rows="3"></textarea>
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

        <?php submit_button('Add Entry'); ?>
    </form>
</div>
