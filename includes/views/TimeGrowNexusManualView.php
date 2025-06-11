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
    public function display($user, $projects = []) {
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
    
        <div class="wrap timegrow-page-container timegrow-manual-page-container">
            <h1><?php esc_html_e('Manual Time Entry', 'timegrow'); ?></h1>
            <h3><?php esc_html_e('Select a Project to Enter Time', 'timegrow'); ?></h3>
            <div id="project-tiles-container" class="timegrow-project-tiles">
                <div class="project-list-container">
            
                    <?php foreach ($projects as $project) : ?>
                    <div class="timegrow-project-tile" draggable="true" data-project-id="<?php echo esc_attr($project->ID); ?>" data-project-name="<?php echo esc_attr($project->name)?>" data-project-desc="<?php echo esc_attr($project->description) ?>">
                        <h3><?php echo esc_html($project->name); ?></h3>
                        <p><?php echo esc_html($project->description); ?></p>    
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="timegrow-manual-container">     
                <!-- Manual Entry Form -->
                <form id="manual-entry-form">
                    <!-- Client Drop Section -->
                    <div id="manual-project-drop-section" class="timegrow-drop-section">
                        <p class="drop-zone-text"><?php esc_html_e('Drop a Project here to assign', 'timegrow'); ?></p>
                        <div id="manual-drop-zone" class="timegrow-drop-zone"><?php esc_html_e('Drop Project Here', 'timegrow'); ?></div>
                    </div>
                    <!-- Drop Zone Section (conditionally shown) -->
                    <div id="project-drop-section" class="timegrow-drop-section" style="display: none;">
                        <p class="drop-zone-text">Drop a project here to clock in</p>
                        <div id="drop-zone" class="timegrow-drop-zone"></div>
                    </div>

                    <label for="manual-datetime"><?php esc_html_e('Select Date & Time:', 'timegrow'); ?></label>
                    <input type="datetime-local" id="manual-datetime" name="datetime" required>

                    <label for="manual-hours"><?php esc_html_e('Hours Worked:', 'timegrow'); ?></label>
                    <select id="manual-hours" name="hours">
                        <?php foreach ($hour_options as $opt): ?>
                            <option value="<?php echo esc_attr($opt['value']); ?>">
                                <?php echo esc_html($opt['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="hidden" id="manual-project-id" name="project_id">
                    <button type="submit" class="timegrow-button disabled" id="timegrow-submit">
                        <?php esc_html_e('Submit Entry', 'timegrow'); ?>
                    </button>


        
                </form>
            </div>

            <?php
            // Pass data to JS
            wp_localize_script('timegrow-manual-js', 'timegrowManualAppData', $js_data);
            ?>
        </div>
        <?php
    }

    /**
     * Gets projects for a user. Replace with your actual implementation.
     */
    private function get_projects_for_user($user_id) {
        // Example hard-coded; replace with CPT, user_meta, etc.
        return [
            ['id' => 'project_1', 'name' => 'Client A'],
            ['id' => 'project_2', 'name' => 'Client B'],
            ['id' => 'project_3', 'name' => 'Client C'],
        ];
    }
}
