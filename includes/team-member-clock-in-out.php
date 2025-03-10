<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Timeflies_Clock_In_Out {

    public static function display_clock_in_out_widget() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $current_user = wp_get_current_user();

        // Check if the user is logged in
        if (!$current_user->ID) {
            echo '<p>You must be logged in to view your projects.</p>';
            return;
        }

        // Fetch the projects for the current user
        $sql = $wpdb->prepare(
            "SELECT p.ID, p.name, c.name AS client_name
            FROM {$prefix}timeflies_team_members m
            JOIN {$prefix}timeflies_team_member_projects mp ON mp.team_member_id = m.ID
            JOIN {$prefix}timeflies_projects p ON mp.project_id = p.ID
            JOIN {$prefix}timeflies_clients c ON p.client_id = c.ID
            WHERE m.user_id = %d
            ORDER BY c.name, p.name",
            $current_user->ID
        );
        $projects = $wpdb->get_results($sql, ARRAY_A);

        // Fetch the projects for the current user
        $sql = $wpdb->prepare(
            "SELECT project_id, entry_type, clock_in_date, clock_out_date
            FROM {$prefix}timeflies_time_entries
            WHERE member_id = %d
            ORDER BY ID desc",
            $current_user->ID
        );
        $entries = $wpdb->get_results($sql, ARRAY_A);

        // User timezone
        $user_timezone = 'UTC';
        if (is_user_logged_in()) {
            $stored_tz = get_user_meta(get_current_user_id(), 'timeflies_timezone', true);
            $user_timezone = !empty($stored_tz) ? $stored_tz : 'UTC';
        }

        // Output buffering to capture HTML
        ob_start();
        ?>
        <div class="time-tracker-wrapper">
            <form id="timeflies-clock-in-out" class="wp-core-ui">
                <input type="hidden" id="action" name="action" value="timeflies_clock_action" readonly   />
                <?php wp_nonce_field('timeflies_time_entry_nonce', 'timeflies_time_entry_nonce_field'); ?>
                <input type="hidden" id="member_id" name="member_id" value="<?php echo get_current_user_id(); ?>" readonly   />
                <div class="time-tracker-card">
                    <?php if (!empty($projects)) : ?>
                    <h4>1st. Select the Project to Assign Time</h4>    
                    <div class="project-buttons">
                        <?php foreach ($projects as $project) : ?>
                            <?php if($entries[0]['entry_type'] == 'IN' && $project['ID'] != $entries[0]['project_id']) continue; ?>
                            <div class="project-item">
                                <label class="project-button" >
                                    <input type="radio" name="project_id" value="<?php echo esc_attr($project['ID']); ?>" <?php echo ($entries[0]['project_id'] == $project['ID'])? 'checked':'';?> > 
                                    <?php echo esc_html($project['client_name'] . ' - ' . $project['name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <h4>2nd. Clock In/Out</h4>  
                    <div class="timeflies-clock">
                        <div class="time-display">
                            <div class="gmt-time">
                                <h3>GMT Time</h3>
                                <div class="clock" id="gmt-clock">--:--:--</div>
                                <input type="hidden" id="gmt_clock_field" name="gmt_clock_field" value="" readonly   />
                            </div>
                            <div class="local-time">
                                <h3><?php echo esc_html($user_timezone); ?></h3>
                                <div class="clock" id="local-clock">--:--:--</div>
                            </div>
                        </div>
                        
                        <div class="time-controls">
                            <?php if($entries[0]['entry_type'] == 'OUT') { ?>
                            <button class="clock-btn clock-in" id="IN">Clock In</button>
                            <?php } else { ?>
                            <button class="clock-btn clock-out" id="OUT">Clock Out</button>
                            <?php } ?>
                            <input type="hidden" id="entry_type" name="entry_type" value="" readonly   />
                        </div>
                        <div class="timeflies-status"></div>
                    </div>
                </div>
            </form>
            <div class="recent-entries">
                <h2>Recent Entries</h2>
                <label class="recent-entry header">Project</label>
                <label class="recent-entry header">Type</label> 
                <label class="recent-entry header">GMT</label>
                <label class="recent-entry header">Local</label>  
                <?php foreach ($entries as $entry) : 
                    $date = ($entry['entry_type'] == 'IN') ? $entry['clock_in_date'] : $entry['clock_out_date'];
                    
                    // Create a DateTime object with the GMT/UTC timezone
                    $gmtDateTime = new DateTime($$date, new DateTimeZone('UTC'));

                    // Set the local timezone (e.g., America/New_York)
                    $localDateTime = clone $gmtDateTime; // Clone to avoid modifying the original object
                    $localDateTime->setTimezone(new DateTimeZone($user_timezone));

                    // Format and print the local time
                    $local =  $localDateTime->format('Y-m-d H:i:s');
                    ?>
                    <div class="recent-entries-list">
                    <label class="recent-entry entry"><?php echo $entry['project_id'];?></label>
                    <label class="recent-entry entry"><?php echo $entry['entry_type'];?></label> 
                    <label class="recent-entry entry"><?php echo ($entry['entry_type'] == 'IN') ? $entry['clock_in_date'] : $entry['clock_out_date'];?></label> 
                    <label class="recent-entry entry"><?php echo $local;?></label> 
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        echo ob_get_clean();
    }

    public function add_clock_in_out_widget() {
        wp_add_dashboard_widget(
            'clock_in_out_widget',
            'Clock In/Out',
            [ __CLASS__, 'display_clock_in_out_widget' ]
        );
    }

    // Enqueue styles and scripts
    public function enqueue_admin_scripts() {
        $timezone = get_user_meta(get_current_user_id(), 'timeflies_timezone');

        wp_enqueue_style( 'timeflies-clock-styles', ARAGROW_TIMEFLIES_BASE_URI . 'assets/css/clock.css' );
        wp_enqueue_script( 'timeflies-clock-script', ARAGROW_TIMEFLIES_BASE_URI . 'assets/js/clock.js', array( 'jquery' ), null, true );


        wp_localize_script(
            'timeflies-clock-script',
            'timeflies_clock',
            [ 
                'timezone' =>  $timezone, 
            ]
        );
    }

    public function __construct() {
        add_action( 'wp_dashboard_setup', [ $this, 'add_clock_in_out_widget' ], 999 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }
}
$Timeflies_Clock_In_Out = new Timeflies_Clock_In_Out;

// Hook for AJAX calls (assuming timeflies_clock_action is defined elsewhere)
add_action( 'wp_ajax_timeflies_clock_action', 'timeflies_handle_clock_action' );

function timeflies_handle_clock_action() {
    try {
        if(WP_DEBUG) error_log('Exec: Timeflies_TimeEntries_Admin.timeflies_handle_clock()');
        check_ajax_referer('timeflies_time_entry_nonce', 'timeflies_time_entry_nonce_field');
        if (WP_DEBUG)error_log('Exec: Timeflies_TimeEntries_Admin.timeflies_handle_clock()->Validation Passed.');

        global $wpdb;
        $table = $wpdb->prefix . 'timeflies_time_entries';

        $current_date = current_time('mysql');

        $member_id = sanitize_text_field($_POST['member_id']);
        $project_id = sanitize_text_field($_POST['project_id']);
        $entry_type = sanitize_text_field($_POST['entry_type']);
        $gmt_clock = sanitize_text_field($_POST['gmt_clock_field']);

        $params = [
            'member_id' => $member_id,
            'project_id' => $project_id,
            'entry_type' => $entry_type,
            'created_at' => $current_date,
            'updated_at' => $current_date
        ];
        if ($entry_type == 'IN') $params['clock_in_date'] = $gmt_clock;
        else $params['clock_out_date'] = $gmt_clock;
        
        $result = $wpdb->insert($table, $params, ['%d', '%d', '%s', '%s', '%s', '%s']);
        if ($result) {
            wp_send_json_success("Clocked $entry_type successfully!");
        } else {
            wp_send_json_error('Error saving time entry');
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
        wp_send_json_error(['message' => $e->getMessage()]);
    }
}