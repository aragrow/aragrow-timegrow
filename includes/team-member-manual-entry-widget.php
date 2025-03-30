<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Timeflies_Manual_Entry_Widget {

    public static function display_widget() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $current_user = wp_get_current_user();

        // Check if the user is logged in
        if (!$current_user->ID) {
            echo '<p>You must be logged in to view your projects.</p>';
            return;
        }
        $integrations = get_option('timeflies_integration_settings');
        if ($integrations['wc_clients'] && class_exists('WooCommerce')) {
            $integration = new Timeflies_Integration();

            // Fetch the projects for the current user
            $sql = $wpdb->prepare(
                "SELECT p.ID, p.name, p.client_id
                FROM {$prefix}timeflies_team_members m
                JOIN {$prefix}timeflies_team_member_projects mp ON mp.team_member_id = m.ID
                JOIN {$prefix}timeflies_projects p ON mp.project_id = p.ID
                WHERE m.user_id = %d",
                $current_user->ID
            );

            $projects = $wpdb->get_results($sql, ARRAY_A);

            $client_ids = array_unique(array_column($projects, 'client_id'));

             // Use the get_wc_customers method to fetch client details
            $client_details = $integration->get_wc_customers($client_ids, ['ID', 'name'], []);
            //var_dump($wpdb->last_query);
            //var_dump($wpdb->last_result);  

            // Create an associative array of clients with ID as key
            foreach ($client_details as $client) {
                $clients[$client['ID']] = $client['name'];
            }

            // Now $clients array contains client IDs as keys and names as values
            // You can use it like this:
            foreach ($projects as &$project) {
                // echo'<hr />';var_dump($project); 
                $client_id = $project['client_id'];
                $project['client_name'] = $clients[$client_id];
            }


        } else {
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

        }

        // Fetch the entries for the current user
        $sql = $wpdb->prepare(
            "SELECT te.project_id, te.entry_type, te.clock_in_date, te.hours, p.name
            FROM {$prefix}timeflies_time_entries te
            INNER JOIN {$prefix}timeflies_projects p ON te.project_id = p.ID
            WHERE te.member_id = %d
            AND te.entry_type = ('MAN')
            ORDER BY te.clock_in_date DESC, p.name desc",
            $current_user->ID
        );
        $entries = $wpdb->get_results($sql, ARRAY_A);
        error_log($wpdb->last_query);
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
            <form id="timeflies-manual-entry" class="wp-core-ui">
                <input type="hidden" id="action" name="action" value="timeflies_manual_action" readonly   />
                <?php wp_nonce_field('timeflies_time_entry_nonce', 'timeflies_time_entry_nonce_field'); ?>
                <input type="hidden" id="member_id" name="member_id" value="<?php echo get_current_user_id(); ?>" readonly   />
                <input type="hidden" id="entry_type" name="entry_type" value="MAN" readonly   />

                <div class="time-tracker-card">
                    <h4>1st. Select the Project to Assign Time</h4>    
                    <div class="project-buttons">
                        <?php foreach ($projects as  &$project) : ?>
                            <div class="project-item">
                                <label class="project-button" >
                                    <input type="radio" name="project_id" value="<?php echo esc_attr($project['ID']); ?>" > 
                                    <?php echo esc_html($project['client_name'] . ' - ' . $project['name']); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <h4>2nd. Select Date</h4>  
                    <div class="time-display">
                        <div class="gmt-time">
                            <input type="date" id="date" name="date" value=""  class="regular-text" required>
                        </div>
                    </div>
                    <h4>3rd. Enter Time</h4>  
                    <div class="time-display">
                        <div class="gmt-time">
                            <select id="time" name="time" class="regular-text" required>
                                <?php for ($hour = 0; $hour <= 12; $hour++) : ?>
                                    <?php foreach (['00', '15', '30', '45'] as $minute) : ?>
                                        <?php if($hour == 0 && $minute == '00') continue; ?>
                                        <option value="<?php echo $hour . ':' . $minute; ?>">
                                            <?php echo $hour . ' hours ' . $minute . ' minutes'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="time-controls">
                    <button class="save-btn" id="save-btn">Save Time</button>
                </div>
            </form>
            <div class="recent-entries">
                <h2>Recent Entries</h2>
                <label class="recent-entry header project">Project</label>
                <label class="recent-entry header date">GMT</label>
                <label class="recent-entry header date">Local</label>  
                <label class="recent-entry header hour">Hours</label>  
                <?php $class = 'alternate' ?>
                <?php foreach ($entries as $entry) : 
                    $class = ($class == 'alternate')? '': 'alternate';
                    $date = $entry['clock_in_date'];
                    error_log($date);
                    // Create a DateTime object with the GMT/UTC timezone
                    $gmtDateTime = new DateTime($date, new DateTimeZone('UTC'));
                    // Set the local timezone (e.g., America/New_York)
                    $localDateTime = clone $gmtDateTime; // Clone to avoid modifying the original object
                    $localDateTime->setTimezone(new DateTimeZone($user_timezone));
                    // Format and print the local time
                    $local =  $localDateTime->format('Y-m-d H:i:s');
                    
                    ?>
                    <div class="recent-entries-list <? echo $class; ?>">
                    <label class="recent-entry entry project"><?php echo $entry['name'];?></label>
                    <label class="recent-entry entry date"><?php echo date("m/d/Y", strtotime($date)); ?></label> 
                    <label class="recent-entry entry date"><?php echo date("m/d/Y", strtotime($local)); ?></label> 
                    <label class="recent-entry entry hour"><?php echo $entry['hours'];?></label> 
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        echo ob_get_clean();
    }

    public function add_manual_widget() {
        $current_user_id = get_current_user_id();
        $timecard = get_user_meta($current_user_id, 'timeflies_timecard', true); 
        if ($timecard != 'manual') return;
        wp_add_dashboard_widget(
            'manual_erntry_widget',
            'Manual Entry',
            [ __CLASS__, 'display_widget' ]
        );
    }

    // Enqueue styles and scripts
    public function enqueue_admin_scripts() {
        $timezone = get_user_meta(get_current_user_id(), 'timeflies_timezone');

        wp_enqueue_style( 'timeflies-manual-entry-widget-styles', ARAGROW_TIMEGROW_BASE_URI . 'assets/css/manual_entry_widget.css' );
        wp_enqueue_script( 'timeflies-manual-entry-widget-script', ARAGROW_TIMEGROW_BASE_URI . 'assets/js/manual_entry_widget.js', array( 'jquery' ), null, true );


        wp_localize_script(
            'timeflies-clock-script',
            'timeflies_clock',
            [ 
                'timezone' =>  $timezone, 
            ]
        );
    }

    public function __construct() {
        add_action( 'wp_dashboard_setup', [ $this, 'add_manual_widget' ], 999 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

    }
}
$Timeflies_Manual_Entry = new Timeflies_Manual_Entry_Widget;

// Hook for AJAX calls (assuming timeflies_clock_action is defined elsewhere)
add_action( 'wp_ajax_TIMEGROW_manual_action', 'timeflies_handle_manual_action' );

function timeflies_handle_manual_action() {
    try {
        if(WP_DEBUG) error_log('Exec: Timeflies_Manual_Entry.timeflies_handle_manual_action()');
        check_ajax_referer('timeflies_time_entry_nonce', 'timeflies_time_entry_nonce_field');
        if (WP_DEBUG)error_log('Exec: Timeflies_Manual_Entry.timeflies_handle_manual_action()->Validation Passed.');

        global $wpdb;
        $table = $wpdb->prefix . 'timeflies_time_entries';
        $member_id = sanitize_text_field($_POST['member_id']);
        $project_id = sanitize_text_field($_POST['project_id']);
        $entry_type = sanitize_text_field($_POST['entry_type']);
        $hours = timeflies_convert_to_decimal_hours(sanitize_text_field($_POST['time']));
        $date = sanitize_text_field($_POST['date']);

        $stored_tz = get_user_meta(get_current_user_id(), 'timeflies_timezone', true);
        $timezone = !empty($stored_tz) ? $stored_tz : 'UTC';
    
        // Create a DateTime object with the local time
        $localDate = new DateTime($date, new DateTimeZone($timezone));
        // Set the timezone to GMT
        $localDate->setTimezone(new DateTimeZone('GMT'));
        $localDate->setTime(0, 0, 0); // Set time to 00:00:00
        // Format and output the GMT date
        $formattedDate=$localDate->format('Y-m-d');

        $current_date = current_time('mysql');
        
        $params = [
            'member_id' => $member_id,
            'project_id' => $project_id,
            'entry_type' => $entry_type,
            'clock_in_date' => $formattedDate,
            'hours' => $hours,
            'created_at' => $current_date,
            'updated_at' => $current_date,
        ];
        error_log(print_r($params,true));
        $result = $wpdb->insert($table, $params, ['%d', '%d', '%s', '%s', '%f', '%s', '%s']);
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

function timeflies_convert_to_decimal_hours($time) {
    error_log($time);
    // Split the time into hours and minutes
    list($hours, $minutes) = explode(':', $time);

    // Convert to decimal hours
    $decimalHours = $hours + ($minutes / 60);

    // Return the result as a float with two decimal places
    return number_format($decimalHours, 2);
}