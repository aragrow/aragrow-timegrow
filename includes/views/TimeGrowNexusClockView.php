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
    public function display($user, $args = []) {
        if (!$user || !$user->ID) {
            echo '<div class="wrap"><p>' . esc_html__('Error: User not found or not logged in.', 'timegrow') . '</p></div>';
            return;
        }

        $current_clock_status = $this->get_current_user_clock_status($user->ID);

        // Data to pass to JavaScript
        // Handle for JS file will be 'clock-js' (or similar)
        $js_data = [
            'userId'             => $user->ID,
            'userName'           => $user->display_name,
            'apiNonce'           => wp_create_nonce('wp_rest'),
            'timegrowApiEndpoint'=> rest_url('timegrow/v1/'),
            'initialStatus'      => $current_clock_status,
            'i18n'               => [
                'clockIn'             => __('Clock In', 'timegrow'),
                'clockOut'            => __('Clock Out', 'timegrow'),
                'loading'             => __('Loading...', 'timegrow'),
                'youAreClockedInAt'   => __('You are currently clocked in since:', 'timegrow'),
                'youAreClockedOut'    => __('You are currently clocked out.', 'timegrow'),
                'clockedInSuccess'    => __('Successfully clocked in.', 'timegrow'),
                'clockedOutSuccess'   => __('Successfully clocked out.', 'timegrow'),
                'clockInError'        => __('Error clocking in. Please try again.', 'timegrow'),
                'clockOutError'       => __('Error clocking out. Please try again.', 'timegrow'),
            ],
        ];

        // Initial state variables for direct PHP output
        $is_clocked_in = ($current_clock_status['status'] === 'clocked_in');
        $clock_in_timestamp = $current_clock_status['clockInTimestamp'];

        ?>
        <div class="wrap timegrow-page-container timegrow-clock-page-container">
            <h1><?php esc_html_e('Clock In / Clock Out', 'timegrow'); ?></h1>

            <?php settings_errors('timegrow_clock_messages'); ?>

            <div id="timegrow-clock-interface" class="timegrow-clock-container"> 

                <div class="timegrow-current-time-display">
                    <div id="timegrow-current-date" class="date">--</div>
                    <div id="timegrow-current-time" class="time">--:--:--</div>
                </div>

                <div id="timegrow-message-area" class="timegrow-message" style="display: none;"></div>

                <div id="timegrow-status-display-area" class="timegrow-status-display">
                    <?php if ($is_clocked_in && $clock_in_timestamp) : ?>
                        <p>
                            <?php echo esc_html($js_data['i18n']['youAreClockedInAt']); ?>
                            <strong>
                                <?php
                                echo esc_html(date_i18n(get_option('time_format'), $clock_in_timestamp));
                                echo ' (' . esc_html(date_i18n(get_option('date_format'), $clock_in_timestamp)) . ')';
                                ?>
                            </strong>
                        </p>
                    <?php else : ?>
                        <p><?php echo esc_html($js_data['i18n']['youAreClockedOut']); ?></p>
                    <?php endif; ?>
                </div>

                <div class="timegrow-actions">
                    <button id="timegrow-clock-in-btn"
                            class="timegrow-button clock-in-button <?php echo !$is_clocked_in ? 'active' : 'disabled'; ?>"
                            type="button"
                            <?php disabled($is_clocked_in); ?>>
                        <?php echo esc_html($js_data['i18n']['clockIn']); ?>
                    </button>

                    <button id="timegrow-clock-out-btn"
                            class="timegrow-button clock-out-button <?php echo $is_clocked_in ? 'active' : 'disabled'; ?>"
                            type="button"
                            <?php disabled(!$is_clocked_in); ?>>
                        <?php echo esc_html($js_data['i18n']['clockOut']); ?>
                    </button>
                </div>
            </div>

            <?php
            // Pass data to your plain JavaScript file.
            // Ensure 'clock-js' is the handle of the enqueued script.
            wp_localize_script('timegrow-clock-js', 'timegrowClockAppVanillaData', $js_data);
            ?>
        </div>
        <?php
    }

    // get_current_user_clock_status($user_id) method remains the same as before
    // (the one that queries your CPT or database)
    private function get_current_user_clock_status($user_id) {
        // ... (your existing implementation that queries the database/CPT) ...
        // For testing, you can still use the temporary return values:
        /*
        return [ // Example: Clocked IN
            'status' => 'clocked_in',
            'clockInTimestamp' => time() - (60 * 15), // Clocked in 15 mins ago
            'entryId' => 456
        ];
        */
        return [ // Example: Clocked OUT
            'status' => 'clocked_out',
            'clockInTimestamp' => null,
            'entryId' => null,
        ];
    }
}