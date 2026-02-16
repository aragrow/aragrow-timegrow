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
        $has_time = isset($_POST['has_time_tracking']) && $_POST['has_time_tracking'] === '1';
        $has_expenses = isset($_POST['has_expenses']) && $_POST['has_expenses'] === '1';

        // Generate PIN
        $pin = $this->authenticator->generate_pin();

        // Create PIN for user
        if ($this->authenticator->create_pin_for_user($user_id, $pin, $has_time, $has_expenses)) {
            // Also add WordPress capabilities
            $user = get_user_by('id', $user_id);
            if ($user) {
                $user->add_cap('access_mobile_only_mode');
                if ($has_time) {
                    $user->add_cap('access_mobile_time_tracking');
                }
                if ($has_expenses) {
                    $user->add_cap('access_mobile_expenses');
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
        $capability = isset($_POST['capability']) ? $_POST['capability'] : '';
        $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';

        $user = get_user_by('id', $user_id);
        if (!$user) {
            $this->error_message = 'User not found.';
            return;
        }

        // Update WordPress capability
        if ($enabled) {
            $user->add_cap($capability);
        } else {
            $user->remove_cap($capability);
        }

        // Update database flags
        $record = $this->model->get_by_user_id($user_id);
        if ($record) {
            $has_time = $capability === 'access_mobile_time_tracking' && $enabled
                ? true
                : ($record->has_time_tracking == 1);

            $has_expenses = $capability === 'access_mobile_expenses' && $enabled
                ? true
                : ($record->has_expenses == 1);

            if ($capability === 'access_mobile_time_tracking' && !$enabled) {
                $has_time = false;
            }
            if ($capability === 'access_mobile_expenses' && !$enabled) {
                $has_expenses = false;
            }

            $this->model->update_capabilities($user_id, $has_time, $has_expenses);
        }

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
        $all_users = get_users(['orderby' => 'display_name']);

        ?>
        <div class="wrap">
            <h1>Mobile Access Management</h1>

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
                                    <label>
                                        <input type="checkbox" name="has_time_tracking" value="1" checked>
                                        Time Tracking (Clock In/Out, Manual Entry)
                                    </label>
                                    <br>
                                    <label>
                                        <input type="checkbox" name="has_expenses" value="1" checked>
                                        Expenses (Record expenses with receipts)
                                    </label>
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
                                </td>
                                <td>
                                    <?php if ($mu->has_time_tracking): ?>
                                        <span class="dashicons dashicons-clock" title="Time Tracking"></span> Time
                                    <?php endif; ?>
                                    <?php if ($mu->has_expenses): ?>
                                        <span class="dashicons dashicons-money-alt" title="Expenses"></span> Expenses
                                    <?php endif; ?>
                                    <?php if (!$mu->has_time_tracking && !$mu->has_expenses): ?>
                                        <span style="color: #999;">No capabilities</span>
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
                                        <input type="hidden" name="has_time_tracking" value="<?php echo $mu->has_time_tracking ? '1' : '0'; ?>">
                                        <input type="hidden" name="has_expenses" value="<?php echo $mu->has_expenses ? '1' : '0'; ?>">
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
