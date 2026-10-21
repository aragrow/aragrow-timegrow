<?php
/**
 * TimeGrow Mobile Settings View
 *
 * Admin interface for managing mobile access and PINs
 *
 * @package TimeGrow
 * @subpackage Mobile
 * @since 2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowMobileSettingsView {

    /**
     * @var TimeGrowPINAuthenticator
     */
    private $authenticator;

    /**
     * @var TimeGrowMobilePINModel
     */
    private $model;

    /**
     * @var string Generated PIN to display
     */
    private $generated_pin = '';

    /**
     * @var string Success message
     */
    private $success_message = '';

    /**
     * @var string Error message
     */
    private $error_message = '';

    /**
     * Constructor
     */
    public function __construct() {
        $this->authenticator = new TimeGrowPINAuthenticator();
        $this->model = new TimeGrowMobilePINModel();
    }

    /**
     * Display settings page
     */
    public function display() {
        // Handle form submissions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_form_submission();
        }

        $this->render();
    }

    /**
     * Handle form submissions
     */
    private function handle_form_submission() {
        // Verify nonce
        if (!isset($_POST['timegrow_mobile_settings_nonce']) ||
            !wp_verify_nonce($_POST['timegrow_mobile_settings_nonce'], 'timegrow_mobile_settings')) {
            $this->error_message = 'Security verification failed.';
            return;
        }

        $action = isset($_POST['action']) ? $_POST['action'] : '';
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

        switch ($action) {
            case 'generate_pin':
                $this->handle_generate_pin($user_id);
                break;

            case 'toggle_capability':
                $this->handle_toggle_capability($user_id);
                break;

            case 'unlock_account':
                $this->handle_unlock_account($user_id);
                break;

            case 'disable_access':
                $this->handle_disable_access($user_id);
                break;

        }
    }

    /**
     * Generate new PIN for user
     */
    private function handle_generate_pin($user_id) {
        if (!TimeGrowMobileAccess::pin_allowed($user_id)) {
            $this->error_message = 'The mobile PIN login is for team members only. Administrators log in with their password.';
            return;
        }

        // Generate PIN
        $pin = $this->authenticator->generate_pin();

        // Create PIN for user
        if ($this->authenticator->create_pin_for_user($user_id, $pin)) {
            // The mobile capabilities ticked in the form (Time tracking, Expenses, AI Voice, AI Receipt Reader)
            $user = get_user_by('id', $user_id);
            if ($user) {
                $user->add_cap('access_mobile_only_mode');
                foreach (array_keys(TimeGrowMobileAccess::capabilities()) as $cap) {
                    if (($_POST['caps'][$cap] ?? '') === '1') {
                        $user->add_cap($cap);
                    } elseif (isset($_POST['caps'])) {
                        $user->remove_cap($cap);
                    }
                }
            }

            $this->generated_pin = $pin;
            $this->success_message = 'PIN generated successfully! Make sure to save it - it will only be shown once.';
        } else {
            $this->error_message = 'Failed to generate PIN. Please try again.';
        }
    }

    /**
     * Toggle user capability
     */
    private function handle_toggle_capability($user_id) {
        $capability = isset($_POST['capability']) ? sanitize_key($_POST['capability']) : '';
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';

        // Only the mobile capabilities can be changed here
        if (!TimeGrowMobileAccess::is_capability($capability)) {
            $this->error_message = 'Unknown capability.';
            return;
        }

        $user = get_user_by('id', $user_id);
        if (!$user || !TimeGrowMobileAccess::allowed($user)) {
            $this->error_message = 'Only administrators and team members can use TimeGrow remotely.';
            return;
        }

        // Update WordPress capability
        if ($enabled) {
            $user->add_cap($capability);
        } else {
            $user->remove_cap($capability);
        }

        // The capabilities live on the WordPress user only (the PIN table has no capability columns)
        $this->success_message = 'Capability updated successfully.';
    }

    /**
     * Unlock user account
     */
    private function handle_unlock_account($user_id) {
        if ($this->model->reset_failed_attempts($user_id)) {
            $this->success_message = 'Account unlocked successfully.';
        } else {
            $this->error_message = 'Failed to unlock account.';
        }
    }

    /**
     * Disable mobile access
     */
    private function handle_disable_access($user_id) {
        $user = get_user_by('id', $user_id);
        if ($user) {
            $user->remove_cap('access_mobile_only_mode');
            $user->remove_cap('access_mobile_time_tracking');
            $user->remove_cap('access_mobile_expenses');
            $user->remove_cap('access_mobile_ai_voice');
            $user->remove_cap('access_mobile_ai_receipts');
        }

        if ($this->model->set_active($user_id, false)) {
            $this->success_message = 'Mobile access disabled successfully.';
        } else {
            $this->error_message = 'Failed to disable mobile access.';
        }
    }

    /**
     * Render settings page
     */
    private function render() {
        // Get all users with mobile access
        $mobile_users = $this->get_mobile_users();
        // Only team members can have a PIN (administrators use the normal login)
        $all_users = array_values(array_filter(get_users(['orderby' => 'display_name', 'role__in' => ['team_member']]), ['TimeGrowMobileAccess', 'pin_allowed']));

        ?>
        <div class="wrap">
            <h1>Mobile Access Management</h1>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings')); ?>">&larr; <?php esc_html_e('Back to Settings', 'timegrow'); ?></a></p>

            <?php if ($this->error_message): ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php echo esc_html($this->error_message); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($this->success_message): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html($this->success_message); ?></p>
                    <?php if ($this->generated_pin): ?>
                        <p style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; font-size: 16px;">
                            <strong>Generated PIN:</strong>
                            <span style="font-size: 32px; font-weight: bold; letter-spacing: 8px; display: inline-block; margin-left: 10px; color: #d63384;">
                                <?php echo esc_html($this->generated_pin); ?>
                            </span>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width: none;">
                <h2>Enable Mobile Access</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('timegrow_mobile_settings', 'timegrow_mobile_settings_nonce'); ?>
                    <input type="hidden" name="action" value="generate_pin">

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="user_id">Select User</label></th>
                            <td>
                                <select name="user_id" id="user_id" required style="min-width: 300px;">
                                    <option value="">-- Select a user --</option>
                                    <?php foreach ($all_users as $user): ?>
                                        <option value="<?php echo esc_attr($user->ID); ?>">
                                            <?php echo esc_html($user->display_name . ' (' . $user->user_login . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Grant Capabilities</th>
                            <td>
                                <fieldset>
                                    <?php
                                    $hints = [
                                        'access_mobile_time_tracking' => 'Clock In/Out, Manual Entry',
                                        'access_mobile_expenses'      => 'Record expenses with receipts',
                                        'access_mobile_ai_voice'      => 'Talk to the voice assistant: record time, ask questions',
                                        'access_mobile_ai_receipts'   => 'Read receipts with AI to fill in expenses',
                                    ];
                                    foreach (TimeGrowMobileAccess::capabilities() as $cap => $label): ?>
                                        <label>
                                            <input type="checkbox" name="caps[<?php echo esc_attr($cap); ?>]" value="1" checked>
                                            <?php echo esc_html($label); ?> (<?php echo esc_html($hints[$cap]); ?>)
                                        </label><br>
                                    <?php endforeach; ?>
                                    <p class="description">Only team members are listed: administrators log in with their password.</p>
                                </fieldset>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">Generate PIN & Enable Access</button>
                    </p>
                </form>
            </div>

            <h2 style="margin-top: 30px;">Active Mobile Users</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Capabilities</th>
                        <th>Last Login</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($mobile_users)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 20px;">
                                No mobile users yet. Enable mobile access for a user above.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($mobile_users as $mu): ?>
                            <?php
                            $user = get_user_by('id', $mu->user_id);
                            if (!$user) continue;

                            $is_locked = $mu->locked_until && strtotime($mu->locked_until) > time();
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($user->display_name); ?></strong><br>
                                    <small><?php echo esc_html($user->user_login); ?></small>
                                    <?php if (TimeGrowMobile2FA::status_text($user) !== '') : ?><br><small><?php echo esc_html(TimeGrowMobile2FA::status_text($user)); ?></small><?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!TimeGrowMobileAccess::pin_allowed($user)): ?>
                                        <span style="color: #d63638;">PIN switched off: the mobile PIN login is for team members only</span>
                                    <?php else: ?>
                                        <?php
                                        $icons = ['access_mobile_time_tracking' => 'clock', 'access_mobile_expenses' => 'money-alt', 'access_mobile_ai_voice' => 'microphone', 'access_mobile_ai_receipts' => 'media-document'];
                                        $any   = false;
                                        foreach (TimeGrowMobileAccess::capabilities() as $cap => $label):
                                            if (!user_can($user, $cap)) continue;
                                            $any = true; ?>
                                            <span class="dashicons dashicons-<?php echo esc_attr($icons[$cap]); ?>" title="<?php echo esc_attr($label); ?>"></span> <?php echo esc_html($label); ?><br>
                                        <?php endforeach; ?>
                                        <?php if (!$any): ?><span style="color: #999;">No capabilities</span><?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $mu->last_login_at ? esc_html(date('M j, Y g:i A', strtotime($mu->last_login_at))) : 'Never'; ?>
                                </td>
                                <td>
                                    <?php if ($is_locked): ?>
                                        <span style="color: #d63384;">🔒 Locked</span>
                                    <?php elseif ($mu->failed_attempts > 0): ?>
                                        <span style="color: #856404;">⚠️ <?php echo esc_html($mu->failed_attempts); ?> failed attempts</span>
                                    <?php else: ?>
                                        <span style="color: #28a745;">✓ Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_locked): ?>
                                        <form method="post" style="display: inline;">
                                            <?php wp_nonce_field('timegrow_mobile_settings', 'timegrow_mobile_settings_nonce'); ?>
                                            <input type="hidden" name="action" value="unlock_account">
                                            <input type="hidden" name="user_id" value="<?php echo esc_attr($mu->user_id); ?>">
                                            <button type="submit" class="button button-small">Unlock</button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('timegrow_mobile_settings', 'timegrow_mobile_settings_nonce'); ?>
                                        <input type="hidden" name="action" value="generate_pin">
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr($mu->user_id); ?>">
                                        <?php foreach (array_keys(TimeGrowMobileAccess::capabilities()) as $cap): ?>
                                            <input type="hidden" name="caps[<?php echo esc_attr($cap); ?>]" value="<?php echo user_can($user, $cap) ? '1' : '0'; ?>">
                                        <?php endforeach; ?>
                                        <button type="submit" class="button button-small">Reset PIN</button>
                                    </form>

                                    <form method="post" style="display: inline;">
                                        <?php wp_nonce_field('timegrow_mobile_settings', 'timegrow_mobile_settings_nonce'); ?>
                                        <input type="hidden" name="action" value="disable_access">
                                        <input type="hidden" name="user_id" value="<?php echo esc_attr($mu->user_id); ?>">
                                        <button type="submit" class="button button-small button-link-delete" onclick="return confirm('Are you sure you want to disable mobile access for this user?');">Disable</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>



            <div style="margin-top: 30px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                <h3 style="margin-top: 0;">Mobile Login URL</h3>
                <p>Share this URL with mobile users:</p>
                <code style="font-size: 16px; padding: 10px; background: white; display: inline-block;">
                    <?php echo esc_url(home_url('/mobile-login')); ?>
                </code>
            </div>
        </div>
        <?php
    }

    /**
     * Get all mobile users
     *
     * @return array
     */
    private function get_mobile_users() {
        return $this->model->get_all_active();
    }
}
