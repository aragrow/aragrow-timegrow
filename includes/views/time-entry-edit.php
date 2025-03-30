<div class="wrap">
    <h2><?php echo $time_entry_id ? 'Edit' : 'Add'; ?> Time Entry</h2>

    <form id="timeflies-time-entry-form" class="wp-core-ui" method="POST">
        <input type="hidden" name="time_entry_id" value="<?php echo esc_attr($time_entry_id); ?>">
        <?php wp_nonce_field('timeflies_time_entry_nonce', 'timeflies_time_entry_nonce_field'); ?>

        <div class="metabox-holder columns-2">
            <div class="postbox-container">
                <div class="postbox">
                    <h3 class="hndle"><span>Entry Details</span></h3>
                    <div class="inside">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="client_id">Client <span class="required">*</span></label></th>
                                <td>
                                    <select id="client_id" name="client_id" class="regular-text" required>
                                        <option value="">Select a Client</option>
                                        <?php foreach ($clients as $client) : ?>
                                            <option value="<?php echo esc_attr($client['id']); ?>" <?php selected($time_entry['client_id'], $client['id']); ?>>
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
                                        <?php if ($time_entry_id) : ?>
                                            <option value="<?php echo esc_attr($time_entry['project_id']); ?>">
                                                <?php echo esc_html($time_entry['project_name']); ?>
                                            </option>
                                        <?php else : ?>
                                            <option value="">First select a Client</option>
                                        <?php endif; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Entry Type</th>
                                <td>
                                    <label>
                                        <input type="radio" name="entry_type" value="clock" <?php checked($entry_type, 'clock'); ?>> Clock In/Out
                                    </label>
                                    <label>
                                        <input type="radio" name="entry_type" value="manual" <?php checked($entry_type, 'manual'); ?>> Manual Entry
                                    </label>
                                </td>
                            </tr>
                            <tr class="clock-entry" style="<?php echo $entry_type == 'manual' ? 'display:none;' : ''; ?>">
                                <td colspan="2">
                                    <button type="button" id="clock-in-btn" class="button button-primary">Clock In</button>
                                    <button type="button" id="clock-out-btn" class="button" disabled>Clock Out</button>
                                    <p class="description">Current Session: <span id="current-session"></span></p>
                                </td>
                            </tr>
                            <tr class="manual-entry" style="<?php echo $entry_type == 'clock' ? 'display:none;' : ''; ?>">
                                <th scope="row"><label for="start_time">Start Time <span class="required">*</span></label></th>
                                <td>
                                    <input type="datetime-local" id="start_time" name="start_time" 
                                           value="<?php echo esc_attr($time_entry['start_time'] ?? ''); ?>" 
                                           class="regular-text" <?php echo $entry_type == 'clock' ? 'disabled' : ''; ?>>
                                </td>
                            </tr>
                            <tr class="manual-entry" style="<?php echo $entry_type == 'clock' ? 'display:none;' : ''; ?>">
                                <th scope="row"><label for="end_time">End Time <span class="required">*</span></label></th>
                                <td>
                                    <input type="datetime-local" id="end_time" name="end_time" 
                                           value="<?php echo esc_attr($time_entry['end_time'] ?? ''); ?>" 
                                           class="regular-text" <?php echo $entry_type == 'clock' ? 'disabled' : ''; ?>>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="description">Description</label></th>
                                <td>
                                    <textarea id="description" name="description" class="large-text" rows="3"><?php echo esc_textarea($time_entry['description'] ?? ''); ?></textarea>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php submit_button($time_entry_id ? 'Update Entry' : 'Add Entry'); ?>
    </form>
</div>
