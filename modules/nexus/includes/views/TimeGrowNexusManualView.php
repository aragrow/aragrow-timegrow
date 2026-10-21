<?php
// #### TimeGrowManualView.php ####

if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowNexusManualView {

    /**
     * Displays the Manual Time Entry page.
     *
     * @param WP_User $user The current WordPress user object.
     * @param array $args Additional arguments.
     */
    public function display($user, $member_id, $projects = [], $list = []) {
        timegrow_log(__CLASS__ . '::' . __FUNCTION__);
        if (!$user || !$user->ID) {
            echo '<div class="wrap"><p>' . esc_html__('Error: User not found or not logged in.', 'timegrow') . '</p></div>';
            return;
        }

        // Hours in 15 min increments up to 12 hrs
        $hour_options = [];
        for ($i = 0.25; $i <= 12; $i += 0.25) {
            $minutes = $i * 60;
            $label = floor($minutes / 60) . 'h';
            $min = $minutes % 60;
            if ($min > 0) $label .= ' ' . $min . 'm';
            $hour_options[] = [
                'value' => $i,
                'label' => $label,
            ];
        }

        $js_data = [
            'userId'             => $user->ID,
            'userName'           => $user->display_name,
            'apiNonce'           => wp_create_nonce('wp_rest'),
            'timegrowApiEndpoint'=> rest_url('timegrow/v1/manual'),
        ];

        ?>

        <div class="wrap timegrow-modern-wrapper timegrow-page-container timegrow-manual-page-container">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Manual Time Entry', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Add and edit past time entries for your projects', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-edit-page"></span>
                </div>
            </div>

            <?php settings_errors('timegrow_manual_messages'); ?>

            <?php if (timegrow_debug()): ?>
            <!-- SQL Debug Panel -->
            <div class="timegrow-debug-panel">
                <details>
                    <summary>🔍 Debug Info</summary>
                    <div class="timegrow-debug-content">
                        <pre><?php
                        echo "User ID: " . esc_html($user->ID) . "\n";
                        echo "Member ID: " . esc_html($member_id) . "\n";
                        echo "Projects Count: " . esc_html(count($projects)) . "\n";
                        echo "Entries Count: " . esc_html(count($list)) . "\n";
                        if (isset($GLOBALS['wpdb']->last_query)) {
                            echo "\nLast Query:\n" . esc_html($GLOBALS['wpdb']->last_query);
                        }
                        ?></pre>
                    </div>
                </details>
            </div><!-- .timegrow-debug-panel -->
            <?php endif; ?>

            <div class="timegrow-manual-layout">
                <div class="timegrow-manual-grid <?php echo !empty($projects) ? 'has-projects' : 'no-projects'; ?>">

                    <!-- Left Column: Project Tiles -->
                    <?php if (!empty($projects)) : ?>
                    <div class="timegrow-projects-column">
                        <div class="timegrow-section">
                            <h2 class="timegrow-section-title">
                                <span class="dashicons dashicons-portfolio"></span>
                                <?php esc_html_e('Available Projects', 'timegrow'); ?>
                            </h2>
                            <p class="timegrow-section-description">
                                <?php esc_html_e('Drag a project to the entry form, or use the search below', 'timegrow'); ?>
                            </p>
                            <div id="project-tiles-container" class="timegrow-project-tiles-scrollable">
                                <?php foreach ($projects as $project) : ?>
                                <div class="timegrow-project-tile" draggable="true"
                                     data-project-id="<?php echo esc_attr($project->ID); ?>"
                                     data-project-name="<?php echo esc_attr($project->name); ?>"
                                     data-project-desc="<?php echo esc_attr($project->description); ?>"
                                     data-client-id="<?php echo esc_attr($project->client_id ?? 0); ?>"
                                     data-client-name="<?php echo esc_attr(TimeGrowProjectPicker::client_name($project->client_id ?? 0)); ?>">
                                    <div class="timegrow-project-tile-header">
                                        <span class="dashicons dashicons-move"></span>
                                        <h3><?php echo esc_html($project->name); ?></h3>
                                    </div>
                                    <p class="timegrow-project-tile-client"><?php echo esc_html(TimeGrowProjectPicker::client_name($project->client_id ?? 0)); ?></p>
                                    <p class="timegrow-project-tile-desc"><?php echo esc_html($project->description); ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div><!-- .timegrow-projects-column -->
                    <?php endif; ?>

                    <!-- Right Column: Entry Form -->
                    <div class="timegrow-manual-column">
                        <div class="timegrow-section">
                            <h2 class="timegrow-section-title">
                                <span class="dashicons dashicons-edit-page"></span>
                                <?php esc_html_e('Time Entry', 'timegrow'); ?>
                            </h2>

                            <div class="timegrow-nexus-container">
                                <form id="timegrow-nexus-entry-form" class="wp-core-ui" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="time_entry_id" value="0">
                                    <input type="hidden" name="action" value="save_time_entry">
                                    <input type="hidden" name="add_item" value="1">
                                    <input type="hidden" name="member_id" value="<?php echo $member_id ?>" />
                                    <input type="hidden" id="project_id" name="project_id" value="0" />
                                    <input type="hidden" name="entry_type" value="MAN" />
                                    <?php wp_nonce_field('timegrow_time_nexus_nonce', 'timegrow_time_nexus_nonce_field'); ?>

                                    <!-- Project Selection Feedback -->
                                    <div id="project-drop-section" class="timegrow-drop-section">
                                        <div id="drop-zone" class="timegrow-drop-zone">
                                            <span class="dashicons dashicons-download"></span>
                                            <p><?php esc_html_e('Drop Project Here or Search Below', 'timegrow'); ?></p>
                                        </div>
                                    </div>

                                    <?php $date_format = get_option('date_format', 'Y-m-d'); ?>
                                    <label for="manual-datetime"><?php esc_html_e('Select Date:', 'timegrow'); ?></label>
                                    <input type="date" id="manual-datetime" name="date" value="<?php echo esc_attr(date($date_format)); ?>" required>

                                    <label for="manual-hours"><?php esc_html_e('Hours Worked:', 'timegrow'); ?></label>
                                    <select id="manual-hours" name="hours">
                                        <?php foreach ($hour_options as $opt): ?>
                                            <option value="<?php echo esc_attr($opt['value']); ?>">
                                                <?php echo esc_html($opt['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <label for="description"><?php esc_html_e('Description:', 'timegrow'); ?></label>
                                    <textarea id="description" name="description" class="large-text" rows="5"></textarea>

                                    <label for="nexus-manual_billable"><?php esc_html_e('Billable:', 'timegrow'); ?></label>
                                    <input type="checkbox" id="nexus-manual_billable" name="billable" value="1" checked class="check">

                                    <div class="timegrow-actions">
                                        <button type="submit" class="timegrow-button disabled" id="timegrow-submit">
                                            <?php esc_html_e('Submit Entry', 'timegrow'); ?>
                                        </button>
                                    </div>
                                </form>
                            </div><!-- .timegrow-nexus-container -->

                        </div><!-- .timegrow-section -->
                    </div><!-- .timegrow-manual-column -->

                </div><!-- .timegrow-manual-grid -->
            </div><!-- .timegrow-manual-layout -->

            <?php
            // Pass data to JS
            wp_localize_script('timegrow-nexus-manual-js', 'timegrowManualAppData', $js_data);
            ?>
        </div>

        <?php TimeGrowNexusRecentEntries::render($member_id); // the person's latest entries (manual and clocked)
    }

}
