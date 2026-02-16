<?php
/**
 * TimeGrow Mobile User Profile Integration
 *
 * Adds mobile PIN management to user edit and profile pages
 *
 * @package TimeGrow
 * @subpackage Mobile
 * @since 2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowMobileUserProfile {

    /**
     * @var TimeGrowPINAuthenticator
     */
    private static $authenticator;

    /**
     * @var TimeGrowMobilePINModel
     */
    private static $model;

    /**
     * Initialize user profile hooks
     */
    public static function init() {
        error_log("TimeGrow PIN: TimeGrowMobileUserProfile::init() called");

        self::$authenticator = new TimeGrowPINAuthenticator();
        self::$model = new TimeGrowMobilePINModel();

        // Add mobile PIN section to user edit page (admin editing user)
        add_action('show_user_profile', [__CLASS__, 'render_mobile_pin_section']);
        add_action('edit_user_profile', [__CLASS__, 'render_mobile_pin_section']);

        // Save mobile PIN settings
        add_action('personal_options_update', [__CLASS__, 'save_mobile_pin_settings']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_mobile_pin_settings']);

        error_log("TimeGrow PIN: Hooks registered for personal_options_update and edit_user_profile_update");

        // Enqueue profile page scripts
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_profile_scripts']);

        // Display PIN generated notice
        add_action('admin_notices', [__CLASS__, 'display_pin_generated_notice']);
        add_action('user_admin_notices', [__CLASS__, 'display_pin_generated_notice']);

        // AJAX handler for PIN generation
        add_action('wp_ajax_timegrow_generate_pin', [__CLASS__, 'ajax_generate_pin']);
    }

    /**
     * Display PIN generated notice banner
     */
    public static function display_pin_generated_notice() {
        // Only show on user profile/edit pages
        $screen = get_current_screen();
        if (!$screen || ($screen->id !== 'profile' && $screen->id !== 'user-edit')) {
            return;
        }

        // Get user ID
        if ($screen->id === 'profile') {
            $user_id = get_current_user_id();
        } elseif ($screen->id === 'user-edit' && isset($_GET['user_id'])) {
            $user_id = intval($_GET['user_id']);
        } else {
            return;
        }

        // Check if PIN was just generated
        $generated_pin = get_transient('timegrow_pin_generated_' . $user_id);
        if (!$generated_pin) {
            return;
        }

        // Delete the transient so it only shows once
        delete_transient('timegrow_pin_generated_' . $user_id);

        // Get phone number to show if SMS was sent
        $phone = get_user_meta($user_id, 'timegrow_mobile_phone', true);

        // Display the banner
        ?>
        <div class="notice notice-success is-dismissible" style="padding: 15px 20px; border-left: 4px solid #46b450;">
            <div style="display: flex; align-items: center; gap: 15px;">
                <span class="dashicons dashicons-yes-alt" style="font-size: 40px; color: #46b450; flex-shrink: 0;"></span>
                <div style="flex-grow: 1;">
                    <h3 style="margin: 0 0 10px 0; font-size: 16px;">
                        <?php esc_html_e('Mobile PIN Successfully Generated!', 'timegrow'); ?>
                    </h3>
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; margin: 10px 0;">
                        <p style="margin: 0 0 5px 0; font-size: 12px; opacity: 0.9;">
                            <?php esc_html_e('6-Character PIN', 'timegrow'); ?>
                        </p>
                        <div style="font-size: 36px; font-weight: bold; letter-spacing: 10px; font-family: monospace;">
                            <?php echo esc_html($generated_pin); ?>
                        </div>
                    </div>
                    <p style="margin: 10px 0 5px 0; color: #d63638; font-weight: 600;">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('Important: Save this PIN now. It will not be shown again.', 'timegrow'); ?>
                    </p>
                    <?php if (!empty($phone)) : ?>
                        <p style="margin: 5px 0; color: #555; font-size: 13px;">
                            <span class="dashicons dashicons-smartphone"></span>
                            <?php printf(
                                esc_html__('An SMS notification has been sent to %s', 'timegrow'),
                                '<strong>' . esc_html($phone) . '</strong>'
                            ); ?>
                        </p>
                    <?php endif; ?>
                    <p style="margin: 5px 0; color: #555; font-size: 13px;">
                        <span class="dashicons dashicons-admin-links"></span>
                        <?php esc_html_e('Mobile Login URL:', 'timegrow'); ?>
                        <strong><a href="<?php echo esc_url(home_url('/mobile-login')); ?>" target="_blank">
                            <?php echo esc_html(home_url('/mobile-login')); ?>
                        </a></strong>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render mobile PIN management section
     *
     * @param WP_User $user The user object
     */
    public static function render_mobile_pin_section($user) {
        // Check permissions
        $can_edit = (current_user_can('administrator') || get_current_user_id() === $user->ID);
        if (!$can_edit) {
            return;
        }

        // Get capabilities from WordPress user meta
        $has_time_tracking = user_can($user->ID, 'access_mobile_time_tracking');
        $has_expenses = user_can($user->ID, 'access_mobile_expenses');

        // Check if user has any Nexus access (desktop time tracking)
        $has_nexus_access = user_can($user->ID, 'access_nexus_clock') ||
                           user_can($user->ID, 'access_nexus_manual_entry') ||
                           user_can($user->ID, 'access_nexus_record_expenses');

        // Only show if user has mobile OR nexus capabilities
        if (!$has_time_tracking && !$has_expenses && !$has_nexus_access) {
            return;
        }

        // Get current PIN data
        $pin_data = self::$model->get_by_user_id($user->ID);
        $has_pin = !empty($pin_data);
        $is_active = $has_pin ? (bool)$pin_data->is_active : false;

        $last_login = $has_pin && $pin_data->last_login_at ? $pin_data->last_login_at : null;
        $failed_attempts = $has_pin ? (int)$pin_data->failed_attempts : 0;
        $is_locked = $has_pin && $pin_data->locked_until && strtotime($pin_data->locked_until) > time();

        // Check if this is the current user or an admin
        $is_current_user = (get_current_user_id() === $user->ID);
        $is_admin = current_user_can('administrator');

        // Get phone number
        $mobile_phone = get_user_meta($user->ID, 'timegrow_mobile_phone', true);

        // Get time entry method
        $time_entry_method = get_user_meta($user->ID, 'timegrow_time_entry_method', true);
        if (empty($time_entry_method)) {
            $time_entry_method = 'clock'; // Default to clock in/out
        }

        ?>
        <h2 id="timegrow-settings"><?php esc_html_e('TimeGrow Settings', 'timegrow'); ?></h2>
        <table class="form-table" role="presentation">

            <?php if ($has_time_tracking || $has_nexus_access) : ?>
            <tr>
                <th scope="row"><?php esc_html_e('Time Entry Method', 'timegrow'); ?></th>
                <td>
                    <fieldset>
                        <label>
                            <input type="radio" name="timegrow_time_entry_method" value="clock"
                                   <?php checked($time_entry_method, 'clock'); ?>>
                            <strong><?php esc_html_e('Clock In/Out', 'timegrow'); ?></strong>
                            <p class="description" style="margin: 5px 0 15px 25px;">
                                <?php esc_html_e('User clocks in when starting work and clocks out when finished. Best for hourly workers.', 'timegrow'); ?>
                            </p>
                        </label>
                        <br>
                        <label>
                            <input type="radio" name="timegrow_time_entry_method" value="manual"
                                   <?php checked($time_entry_method, 'manual'); ?>>
                            <strong><?php esc_html_e('Manual Time Entry', 'timegrow'); ?></strong>
                            <p class="description" style="margin: 5px 0 0 25px;">
                                <?php esc_html_e('User manually enters start and end times after work is completed. Best for salaried workers or contractors.', 'timegrow'); ?>
                            </p>
                        </label>
                    </fieldset>
                </td>
            </tr>
            <?php endif; ?>

            <?php if ($has_time_tracking || $has_expenses) : ?>
            <!-- Mobile Access Section -->
            <tr>
                <th scope="row" colspan="2" style="padding-top: 20px;">
                    <h3 style="margin: 0; font-size: 14px; font-weight: 600; color: #23282d;">
                        <?php esc_html_e('Mobile Access', 'timegrow'); ?>
                    </h3>
                </th>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e('Mobile Phone Number', 'timegrow'); ?></th>
                <td>
                    <input type="tel" name="timegrow_mobile_phone" id="timegrow_mobile_phone"
                           value="<?php echo esc_attr($mobile_phone); ?>"
                           class="regular-text"
                           placeholder="+1 (555) 123-4567">
                    <p class="description">
                        <?php esc_html_e('Phone number for SMS notifications when logging in via mobile PIN.', 'timegrow'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row"><?php esc_html_e('Mobile PIN Code', 'timegrow'); ?></th>
                <td>
                    <div id="timegrow-mobile-pin-section">
                        <?php if ($has_pin && $is_active) : ?>
                            <!-- Active PIN Banner -->
                            <div style="background: linear-gradient(135deg, #46b450 0%, #399942 100%); color: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 15px; box-shadow: 0 2px 8px rgba(70, 180, 80, 0.2);">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <span class="dashicons dashicons-yes-alt" style="font-size: 24px; width: 24px; height: 24px;"></span>
                                    <div style="flex-grow: 1;">
                                        <p style="margin: 0; font-size: 15px; font-weight: 600;">
                                            <?php esc_html_e('Mobile PIN is Active', 'timegrow'); ?>
                                        </p>
                                        <?php if ($last_login) : ?>
                                            <p style="margin: 5px 0 0 0; font-size: 13px; opacity: 0.9;">
                                                <?php printf(
                                                    esc_html__('Last mobile login: %s', 'timegrow'),
                                                    wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_login))
                                                ); ?>
                                            </p>
                                        <?php else: ?>
                                            <p style="margin: 5px 0 0 0; font-size: 13px; opacity: 0.9;">
                                                <?php esc_html_e('User has not logged in via mobile yet', 'timegrow'); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <?php if ($is_locked) : ?>
                                <!-- Locked Account Warning -->
                                <div style="background: #d63638; color: white; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px;">
                                    <p style="margin: 0; font-size: 14px; font-weight: 600;">
                                        <span class="dashicons dashicons-lock" style="font-size: 18px; vertical-align: middle;"></span>
                                        <?php esc_html_e('Account Locked', 'timegrow'); ?>
                                    </p>
                                    <p style="margin: 5px 0 0 0; font-size: 13px; opacity: 0.95;">
                                        <?php printf(
                                            esc_html__('Due to failed login attempts. Unlocks at: %s', 'timegrow'),
                                            wp_date(get_option('time_format'), strtotime($pin_data->locked_until))
                                        ); ?>
                                    </p>
                                </div>
                            <?php elseif ($failed_attempts > 0) : ?>
                                <!-- Failed Attempts Warning -->
                                <div style="background: #dba617; color: white; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px;">
                                    <p style="margin: 0; font-size: 14px;">
                                        <span class="dashicons dashicons-warning" style="font-size: 18px; vertical-align: middle;"></span>
                                        <?php printf(esc_html__('Failed login attempts: %d', 'timegrow'), $failed_attempts); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($has_pin && !$is_active) : ?>
                            <!-- Disabled PIN Notice -->
                            <div style="background: #f0f0f1; color: #50575e; padding: 12px 15px; border-radius: 6px; margin-bottom: 15px; border-left: 4px solid #72aee6;">
                                <p style="margin: 0; font-size: 14px;">
                                    <span class="dashicons dashicons-info" style="font-size: 18px; vertical-align: middle;"></span>
                                    <?php esc_html_e('Mobile PIN is disabled', 'timegrow'); ?>
                                </p>
                            </div>
                        <?php endif; ?>

                        <p>
                            <button type="button" class="button button-primary" id="timegrow-generate-new-pin">
                                <?php $has_pin ? esc_html_e('Generate New PIN', 'timegrow') : esc_html_e('Generate PIN', 'timegrow'); ?>
                            </button>

                            <?php if ($has_pin && $is_active) : ?>
                                <button type="button" class="button" id="timegrow-disable-mobile-access" style="margin-left: 10px;">
                                    <?php esc_html_e('Disable PIN', 'timegrow'); ?>
                                </button>
                            <?php endif; ?>

                            <?php if ($is_locked && $is_admin) : ?>
                                <button type="button" class="button" id="timegrow-unlock-account" style="margin-left: 10px;">
                                    <?php esc_html_e('Unlock Account', 'timegrow'); ?>
                                </button>
                            <?php endif; ?>
                        </p>

                        <?php if (!$has_pin) : ?>
                            <p class="description">
                                <?php esc_html_e('Generate a 6-character PIN for mobile login.', 'timegrow'); ?>
                            </p>
                        <?php endif; ?>

                        <!-- Hidden input to store generated PIN -->
                        <input type="hidden" name="timegrow_new_pin" id="timegrow_new_pin" value="">
                        <input type="hidden" name="timegrow_mobile_action" id="timegrow_mobile_action" value="">
                        <?php wp_nonce_field('timegrow_mobile_pin_action', 'timegrow_mobile_pin_nonce'); ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>

        </table>

        <style>
            #timegrow-mobile-pin-section .dashicons {
                font-size: 18px;
                width: 18px;
                height: 18px;
                vertical-align: middle;
                margin-right: 5px;
            }
        </style>
        <?php
    }

    /**
     * Save mobile PIN settings
     *
     * @param int $user_id The user ID being saved
     */
    public static function save_mobile_pin_settings($user_id) {
        error_log("TimeGrow PIN: save_mobile_pin_settings HOOK FIRED for user {$user_id}");
        error_log("TimeGrow PIN: POST data keys: " . implode(', ', array_keys($_POST)));

        // Check permissions
        $can_edit = (current_user_can('administrator') || get_current_user_id() === $user_id);
        if (!$can_edit) {
            error_log("TimeGrow PIN: Permission denied for user {$user_id}");
            return;
        }

        // Verify nonce
        if (!isset($_POST['timegrow_mobile_pin_nonce'])) {
            error_log("TimeGrow PIN: Nonce not set in POST data");
            return;
        }

        if (!wp_verify_nonce($_POST['timegrow_mobile_pin_nonce'], 'timegrow_mobile_pin_action')) {
            error_log("TimeGrow PIN: Nonce verification failed");
            return;
        }

        error_log("TimeGrow PIN: save_mobile_pin_settings called for user {$user_id} - Nonce OK");

        // Save mobile phone number
        if (isset($_POST['timegrow_mobile_phone'])) {
            $phone = sanitize_text_field($_POST['timegrow_mobile_phone']);
            update_user_meta($user_id, 'timegrow_mobile_phone', $phone);
        }

        // Save time entry method
        if (isset($_POST['timegrow_time_entry_method'])) {
            $method = sanitize_text_field($_POST['timegrow_time_entry_method']);
            if (in_array($method, ['clock', 'manual'])) {
                update_user_meta($user_id, 'timegrow_time_entry_method', $method);
            }
        }

        $action = isset($_POST['timegrow_mobile_action']) ? sanitize_text_field($_POST['timegrow_mobile_action']) : '';
        error_log("TimeGrow PIN: Action = '{$action}'");

        // Handle PIN generation
        if ($action === 'generate' && isset($_POST['timegrow_new_pin'])) {
            $new_pin = sanitize_text_field($_POST['timegrow_new_pin']);
            error_log("TimeGrow PIN: Attempting to generate PIN: {$new_pin}");

            // Validate PIN format (6 alphanumeric characters)
            if (preg_match('/^[A-Z0-9]{6}$/i', $new_pin)) {
                error_log("TimeGrow PIN: PIN format valid");
                $result = self::$authenticator->create_pin_for_user($user_id, $new_pin);

                if ($result) {
                    error_log("TimeGrow PIN: PIN created successfully in database");
                    add_user_meta($user_id, '_timegrow_mobile_pin_updated', time(), true);

                    // Store PIN temporarily for display in admin notice
                    set_transient('timegrow_pin_generated_' . $user_id, $new_pin, 60); // Store for 60 seconds
                    error_log("TimeGrow PIN: Transient set for user {$user_id}: {$new_pin}");

                    // Send SMS notification if phone number is set
                    $phone = get_user_meta($user_id, 'timegrow_mobile_phone', true);
                    if (!empty($phone)) {
                        self::send_pin_sms_notification($user_id, $phone, $new_pin);
                    }
                } else {
                    error_log("TimeGrow PIN: Failed to create PIN in database");
                }
            } else {
                error_log("TimeGrow PIN: Invalid PIN format: {$new_pin}");
            }
        }

        // Handle disable mobile access
        if ($action === 'disable') {
            self::$model->deactivate($user_id);
        }

        // Handle unlock account
        if ($action === 'unlock' && current_user_can('administrator')) {
            self::$model->unlock_account($user_id);
        }
    }

    /**
     * Send SMS notification when PIN is generated
     *
     * @param int $user_id User ID
     * @param string $phone Phone number
     * @param string $pin The generated PIN
     */
    private static function send_pin_sms_notification($user_id, $phone, $pin) {
        // This is a placeholder for SMS integration
        // You can integrate with Twilio, SNS, or another SMS service here

        $user = get_userdata($user_id);
        $message = sprintf(
            "Your TimeGrow mobile PIN has been generated: %s. Login at %s",
            $pin,
            home_url('/mobile-login')
        );

        // Hook for SMS integration
        do_action('timegrow_send_pin_sms', $phone, $message, $user_id, $pin);

        // Log for debugging (remove in production)
        error_log("TimeGrow PIN SMS would be sent to {$phone}: {$message}");
    }

    /**
     * Enqueue profile page scripts
     */
    public static function enqueue_profile_scripts($hook) {
        // Only enqueue on user profile pages
        if ($hook !== 'profile.php' && $hook !== 'user-edit.php') {
            return;
        }

        wp_enqueue_script(
            'timegrow-mobile-user-profile',
            TIMEGROW_MOBILE_ASSETS_URI . 'js/user-profile.js',
            ['jquery'],
            TIMEGROW_MOBILE_VERSION . '.3', // Bumped version to force cache refresh
            true
        );

        // Get user ID from URL or current user
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : get_current_user_id();

        wp_localize_script('timegrow-mobile-user-profile', 'timegrowMobileProfile', [
            'mobileLoginUrl' => home_url('/mobile-login'),
            'currentUserId' => $user_id,
        ]);
    }

    /**
     * AJAX handler for PIN generation
     */
    public static function ajax_generate_pin() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'timegrow_mobile_pin_action')) {
            wp_send_json_error(['message' => 'Security verification failed']);
            return;
        }

        // Get user ID and PIN
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $pin = isset($_POST['pin']) ? sanitize_text_field($_POST['pin']) : '';

        // Check permissions
        $can_edit = (current_user_can('administrator') || get_current_user_id() === $user_id);
        if (!$can_edit) {
            wp_send_json_error(['message' => 'Permission denied']);
            return;
        }

        // Validate PIN format
        if (!preg_match('/^[A-Z0-9]{6}$/i', $pin)) {
            wp_send_json_error(['message' => 'Invalid PIN format']);
            return;
        }

        // Generate and save PIN
        $result = self::$authenticator->create_pin_for_user($user_id, $pin);

        if ($result) {
            // Get phone number
            $phone = get_user_meta($user_id, 'timegrow_mobile_phone', true);

            // Send SMS if phone is set
            if (!empty($phone)) {
                self::send_pin_sms_notification($user_id, $phone, $pin);
            }

            wp_send_json_success([
                'message' => 'PIN generated successfully',
                'phone' => $phone
            ]);
        } else {
            wp_send_json_error(['message' => 'Failed to save PIN']);
        }
    }
}

// Initialize
TimeGrowMobileUserProfile::init();
