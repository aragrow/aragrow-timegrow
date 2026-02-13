<?php
// ####TimeGrowNexusClockView.php ####

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowNexusClockView {

    /**
     * Displays the Clock In / Clock Out interface page using plain JavaScript.
     *
     * @param WP_User $user The current WordPress user object.
     * @param array $args Additional arguments.
     */
    public function display($user, $member_id, $projects = [], $list = []) {
        if (!$user || !$user->ID) {
            echo '<div class="wrap"><p>' . esc_html__('Error: User not found or not logged in.', 'timegrow') . '</p></div>';
            return;
        }
        $member_model = new TimeGrowTeamMemberModel();
        $current_clock_status = $member_model->get_team_member_clock_status($member_id);

        // Data to pass to JavaScript
        // Handle for JS file will be 'clock-js' (or similar)
        $js_data = [
            'userId'             => $user->ID,
            'userName'           => $user->display_name,
            'apiNonce'           => wp_create_nonce('wp_rest'),
            'timegrowApiEndpoint'=> rest_url('timegrow/v1/clock'),
            'initialStatus'      => $current_clock_status
        ];
        // Pass data to your plain JavaScript file.
        // Ensure 'nexus-clock-script' is the handle of the enqueued script.
        wp_localize_script('timegrow-nexus-clock', 'timegrowClockAppData', $js_data);

        // Initial state variables for direct PHP output
        $is_clocked_in = ($current_clock_status['status'] === 'clocked_in')?true:false;
        $cloked_project = ($current_clock_status['cloked_project'])?:'None';
        $clock_in_timestamp = $current_clock_status['clockInTimestamp'];
        $entry_id = ($current_clock_status['entryId'])?:0;
        ?>
        <div class="wrap timegrow-modern-wrapper">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Clock In / Clock Out', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Track your time by clocking in and out of projects', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-clock"></span>
                </div>
            </div>

            <?php settings_errors('timegrow_clock_messages'); ?>

            <?php if (WP_DEBUG && !empty($current_clock_status['_debug']['query'])): ?>
            <!-- SQL Debug Panel -->
            <div class="timegrow-debug-panel">
                <details>
                    <summary>🔍 SQL Debug</summary>
                    <div class="timegrow-debug-content">
                        <pre><?php echo esc_html($current_clock_status['_debug']['query']); ?></pre>
                    </div>
                </details>
            </div><!-- .timegrow-debug-panel -->
            <?php endif; ?>

            <div class="timegrow-clock-layout">
                <!-- Two Column Layout -->
                <div class="timegrow-clock-grid <?php echo (!$is_clocked_in && !empty($projects)) ? 'has-projects' : 'no-projects'; ?>">
                    <!-- Left Column: Projects -->
                    <?php if(!$is_clocked_in && !empty($projects)) : ?>
                    <div class="timegrow-projects-column">
                        <div class="timegrow-section">
                            <h2 class="timegrow-section-title">
                                <span class="dashicons dashicons-portfolio"></span>
                                <?php esc_html_e('Available Projects', 'timegrow'); ?>
                            </h2>
                            <p class="timegrow-section-description">
                                <?php esc_html_e('Drag a project to the clock zone', 'timegrow'); ?>
                            </p>
                            <div id="project-tiles-container" class="timegrow-project-tiles-scrollable">
                                <?php foreach ($projects as $project) : ?>
                                <div class="timegrow-project-tile" draggable="true"
                                     data-project-id="<?php echo esc_attr($project->ID); ?>"
                                     data-project-name="<?php echo esc_attr($project->name); ?>"
                                     data-project-desc="<?php echo esc_attr($project->description); ?>">
                                    <div class="timegrow-project-tile-header">
                                        <span class="dashicons dashicons-move"></span>
                                        <h3><?php echo esc_html($project->name); ?></h3>
                                    </div>
                                    <p class="timegrow-project-tile-desc"><?php echo esc_html($project->description); ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div><!-- .timegrow-projects-column -->
                    <?php endif; ?>

                    <!-- Right Column: Clock Interface -->
                    <div class="timegrow-clock-column">
                        <div class="timegrow-section">
                    <h2 class="timegrow-section-title">
                        <span class="dashicons dashicons-clock"></span>
                        <?php esc_html_e('Time Clock', 'timegrow'); ?>
                    </h2>

                    <form id="timegrow-nexus-entry-form" class="wp-core-ui" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="time_entry_id" value="<?php echo $entry_id ?>">
                        <input type="hidden" name="action" value="save_time_entry">
                        <input type="hidden" name="add_item" value="1">
                        <input type="hidden" name="member_id" value="<?php echo $member_id ?>" />
                        <input type="hidden" id="project_id" name="project_id" value="0" />
                        <input type="hidden" id="entry_type" name="entry_type" value="CLOCK_IN" />
                        <input type="hidden" id="clock_time" name="clock_time" value="" />
                        <?php wp_nonce_field('timegrow_time_nexus_nonce', 'timegrow_time_nexus_nonce_field'); ?>

                        <!-- Project Drop Zone -->
                        <?php if(!$is_clocked_in) : ?>
                        <div id="project-drop-section" class="timegrow-drop-section">
                            <div id="drop-zone" class="timegrow-drop-zone">
                                <span class="dashicons dashicons-download"></span>
                                <p><?php esc_html_e('Drop Project Here to Clock In', 'timegrow'); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Clock Status Display -->
                        <div id="timegrow-clock-interface" class="timegrow-clock-container">
                            <div class="timegrow-clock-status">
                                <div class="timegrow-status-badge <?php echo $is_clocked_in ? 'clocked-in' : 'clocked-out'; ?>">
                                    <?php if ($is_clocked_in) : ?>
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <?php esc_html_e('Clocked In', 'timegrow'); ?>
                                    <?php else : ?>
                                        <span class="dashicons dashicons-minus"></span>
                                        <?php esc_html_e('Clocked Out', 'timegrow'); ?>
                                    <?php endif; ?>
                                </div>

                                <?php if ($is_clocked_in) : ?>
                                <div class="timegrow-current-project">
                                    <strong><?php esc_html_e('Current Project:', 'timegrow'); ?></strong>
                                    <span class="project-name"><?php echo esc_html($cloked_project); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="timegrow-current-time-display">
                                <div id="timegrow-current-date" class="date">--</div>
                                <div id="timegrow-current-time" class="time">--:--:--</div>
                            </div>

                            <?php if ($is_clocked_in && $clock_in_timestamp) : ?>
                            <div class="timegrow-clock-in-info">
                                <div class="timegrow-info-box">
                                    <span class="dashicons dashicons-clock"></span>
                                    <div>
                                        <strong><?php esc_html_e('Clocked In At:', 'timegrow'); ?></strong>
                                        <p>
                                            <?php echo esc_html(date_i18n(get_option('date_format'), $clock_in_timestamp)); ?>
                                            <?php echo esc_html(date_i18n(get_option('time_format'), $clock_in_timestamp)); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="timegrow-actions">
                                <button id="timegrow-clock-in-btn"
                                        class="timegrow-button clock-in-button <?php echo !$is_clocked_in ? 'active' : 'disabled'; ?>"
                                        type="submit"
                                        <?php disabled($is_clocked_in); ?>>
                                    <span class="dashicons dashicons-controls-play"></span>
                                    <?php esc_html_e('Clock In', 'timegrow'); ?>
                                </button>

                                <button id="timegrow-clock-out-btn"
                                        class="timegrow-button clock-out-button <?php echo $is_clocked_in ? 'active' : 'disabled'; ?>"
                                        type="submit"
                                        <?php disabled(!$is_clocked_in); ?>>
                                    <span class="dashicons dashicons-controls-pause"></span>
                                    <?php esc_html_e('Clock Out', 'timegrow'); ?>
                                </button>
                            </div>
                        </div><!-- .timegrow-clock-container -->
                    </form>
                        </div><!-- .timegrow-section -->
                    </div><!-- .timegrow-clock-column -->
                </div><!-- .timegrow-clock-grid -->
            </div><!-- .timegrow-clock-layout -->
        </div><!-- .wrap -->
        <?php
    }

}