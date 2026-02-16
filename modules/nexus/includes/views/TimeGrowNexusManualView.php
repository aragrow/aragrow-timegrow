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
        if (WP_DEBUG) error_log(__CLASS__ . '::' . __FUNCTION__);
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

            <?php if (WP_DEBUG): ?>
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
                        echo "Is Administrator: " . (current_user_can('administrator') ? 'Yes' : 'No') . "\n";
                        if (isset($GLOBALS['wpdb']->last_query)) {
                            echo "\nLast Query:\n" . esc_html($GLOBALS['wpdb']->last_query);
                        }
                        ?></pre>
                    </div>
                </details>
            </div><!-- .timegrow-debug-panel -->
            <?php endif; ?>

            <div id="project-tiles-container" class="timegrow-project-tiles" style="float:left">
                <div class="project-list-container">
                    <?php foreach ($projects as $project) : ?>
                    <div class="timegrow-project-tile" draggable="true" data-project-id="<?php echo esc_attr($project->ID); ?>" data-project-name="<?php echo esc_attr($project->name)?>" data-project-desc="<?php echo esc_attr($project->description) ?>">
                        <h3><?php echo esc_html($project->name); ?></h3>
                        <p><?php echo esc_html($project->description); ?></p>    
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>



            <div class="timegrow-nexus-container"  style="float:left">
                <!-- Manual Entry Form -->
                    <form id="timegrow-nexus-entry-form" class="wp-core-ui" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="time_entry_id" value="0">
                    <input type="hidden" name="action" value="save_time_entry">
                    <input type="hidden" name="add_item" value="1">
                    <input type="hidden" name="member_id" value="<?php echo $member_id ?>" />
                    <input type="hidden" id="project_id" name="project_id" value="0" />
                    <input type="hidden" name="entry_type" value="MAN" />
                    <?php wp_nonce_field('timegrow_time_nexus_nonce', 'timegrow_time_nexus_nonce_field'); ?>
                    <!-- Project Drop Section -->
                    <div id="project-drop-section" class="timegrow-drop-section">
                        <p class="drop-zone-text"><?php esc_html_e('Drop a Project here to assign', 'timegrow'); ?></p>
                        <div id="drop-zone" class="timegrow-drop-zone"><?php esc_html_e('Drop Project Here', 'timegrow'); ?></div>
                    </div>
                    <!-- Drop Zone Section (conditionally shown) -->
                    <div id="project-drop-section" class="timegrow-drop-section" style="display: none;">
                        <p class="drop-zone-text">Drop a project here</p>
                        <div id="drop-zone" class="timegrow-drop-zone"></div>
                    </div>

                    <?php
                    $date_format = get_option('date_format', 'Y-m-d');
                    ?>
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

                    <label for="description">Description</label></th>
                    <textarea id="description" name="description" class="large-text" rows="5"></textarea>

                    <label for="manual-hours"><?php esc_html_e('Billable:', 'timegrow'); ?></label>
                    <input type="checkbox" id="nexus-manual_billable" name="billable" value="1" checked class="check">

                    <br /><br />
                    <button type="submit" class="timegrow-button disabled" id="timegrow-submit">
                        <?php esc_html_e('Submit Entry', 'timegrow'); ?>
                    </button>
        
                </form>
            </div>

            <?php
            // Pass data to JS
            wp_localize_script('timegrow-nexus-manual-js', 'timegrowManualAppData', $js_data);
            ?>
        </div>

        <?php
        // Section to display existing manual entries
        
        // Handle GET parameters
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $sort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
        $order = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'desc' : 'asc';
        $filter_project = isset($_GET['project']) ? sanitize_text_field($_GET['project']) : '';

        $entries_per_page = 15;

        // Filter by project
        if (!empty($filter_project)) {
            $time_entries = array_filter($list, function($entry) use ($filter_project) {
                return $entry['project_id'] === $filter_project;
            });
        }

        // Paginate
        $total_entries = count($list);
        $total_pages = ceil($total_entries / $entries_per_page);
        $offset = ($page - 1) * $entries_per_page;
        $current_entries = array_slice($list, $offset, $entries_per_page);

        //var_dump($current_entries);
        // URL helper
        function build_entry_query($overrides = []) {
            $params = array_merge($_GET, $overrides);
            return '?' . http_build_query($params);
        }
        ?>

        <br clear="all" />
        <h2><?php esc_html_e('Existing Manual Entries', 'timegrow'); ?></h2>
        <p><?php esc_html_e('You can edit or delete entries by clicking on them.', 'timegrow'); ?></p>
        <div class="time-entries">
            <form method="get" class="filter-form" style="margin-bottom: 1rem;" action="">
                <select name="project" onchange="this.form.submit()">
                    <option value="">All Projects</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?= esc_attr($project->ID) ?>" <?= selected($filter_project, $project->ID, false) ?>>
                            <?= esc_html($project->name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Date</th>
                        <!-- Add more columns if needed -->
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($current_entries)): ?>
                        <tr><td colspan="2">No entries found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($current_entries as $entry): ?>
                            <tr>
                                <td><?php echo esc_html($entry->project_name); ?></td>
                                <td><?php echo esc_html(date('Y-m-d', strtotime($entry->date))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination" style="margin-top: 1rem;">
                <?php if ($page > 1): ?>
                    <a href="<?= build_query(['page' => $page - 1]) ?>">« Prev</a>
                <?php endif; ?>
                <span>Page <?= $page ?> of <?= $total_pages ?></span>
                <?php if ($page < $total_pages): ?>
                    <a href="<?= build_query(['page' => $page + 1]) ?>">Next »</a>
                <?php endif; ?>
            </div>
        </div>


        <?php
    }

}
