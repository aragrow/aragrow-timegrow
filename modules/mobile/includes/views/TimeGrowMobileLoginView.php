<?php
/**
 * TimeGrow Mobile Login View
 *
 * Login page for mobile-only access with PIN authentication
 *
 * @package TimeGrow
 * @subpackage Mobile
 * @since 2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowMobileLoginView {

    /**
     * @var TimeGrowPINAuthenticator
     */
    private $authenticator;

    /**
     * @var string Error message
     */
    private $error_message = '';

    /**
     * @var string Success message
     */
    private $success_message = '';

    /**
     * Constructor
     */
    public function __construct() {
        $this->authenticator = new TimeGrowPINAuthenticator();
    }

    /**
     * Display login page
     */
    public function display() {
        // Session is started in handle_mobile_login() before this is called

        // Check if user is already logged in
        if (is_user_logged_in() && current_user_can('access_mobile_only_mode')) {
            wp_redirect(admin_url('admin.php?page=timegrow-nexus-reports'));
            exit;
        }

        // Handle login form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_login();
            // If we're still here, login failed - render the form again with error
            // handle_login() sets $this->error_message or redirects on success
        }

        // Check if we're in 2FA step
        if (isset($_SESSION['timegrow_2fa_user_id'])) {
            $this->render_2fa_form();
            return;
        }

        // Render login page
        $this->render();
    }

    /**
     * Handle login form submission
     */
    private function handle_login() {
        // Verify nonce
        if (!isset($_POST['timegrow_mobile_login_nonce']) ||
            !wp_verify_nonce($_POST['timegrow_mobile_login_nonce'], 'timegrow_mobile_login')) {
            $this->error_message = 'Security verification failed. Please try again.';
            return;
        }

        // Get form data
        $username = isset($_POST['username']) ? sanitize_text_field($_POST['username']) : '';
        $pin = isset($_POST['pin']) ? sanitize_text_field($_POST['pin']) : '';
        $totp_code = isset($_POST['totp_code']) ? sanitize_text_field($_POST['totp_code']) : '';

        // Validate inputs
        if (empty($username) || empty($pin)) {
            $this->error_message = 'Please enter both username and PIN.';
            return;
        }

        // Get user by username or email
        $user = get_user_by('login', $username);
        if (!$user) {
            $user = get_user_by('email', $username);
        }

        if (!$user) {
            $this->error_message = 'Invalid username or PIN.';
            return;
        }

        // Verify PIN first
        $result = $this->authenticator->verify_pin($user->ID, $pin);

        if (!$result['success']) {
            $this->error_message = $result['message'];
            return;
        }

        // PIN is valid - check if 2FA is required
        if (TimeGrowMobile2FA::has_wordfence_2fa($user->ID)) {
            // User has Wordfence 2FA enabled - require TOTP code
            if (empty($totp_code)) {
                // Show 2FA form
                $_SESSION['timegrow_2fa_user_id'] = $user->ID;
                $_SESSION['timegrow_2fa_username'] = $username;
                $this->render_2fa_form();
                return;
            }

            // Verify TOTP code
            $totp_result = TimeGrowMobile2FA::verify_code($user->ID, $totp_code);

            if (!$totp_result['success']) {
                $_SESSION['timegrow_2fa_user_id'] = $user->ID;
                $_SESSION['timegrow_2fa_username'] = $username;
                $this->error_message = $totp_result['message'];
                $this->render_2fa_form();
                return;
            }

            // Clear 2FA session data
            unset($_SESSION['timegrow_2fa_user_id']);
            unset($_SESSION['timegrow_2fa_username']);
        }

        // PIN (and 2FA if required) verified - create session
        if ($this->authenticator->create_mobile_session($user->ID)) {
            // Send SMS notification if phone number is set
            $phone = get_user_meta($user->ID, 'timegrow_mobile_phone', true);
            if (!empty($phone)) {
                $this->send_login_sms_notification($user->ID, $phone);
            }

            // Always redirect to dashboard (reports page) after successful login
            wp_redirect(admin_url('admin.php?page=timegrow-nexus-reports'));
            exit;
        } else {
            $this->error_message = 'Failed to create session. Please try again.';
        }
    }

    /**
     * Render login page
     */
    private function render() {
        $site_name = get_bloginfo('name');
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>Mobile Login - <?php echo esc_html($site_name); ?></title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }

                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }

                .login-container {
                    background: white;
                    border-radius: 20px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    padding: 40px 30px;
                    width: 100%;
                    max-width: 400px;
                }

                .login-header {
                    text-align: center;
                    margin-bottom: 30px;
                }

                .login-header h1 {
                    font-size: 28px;
                    color: #333;
                    margin-bottom: 8px;
                }

                .login-header p {
                    font-size: 14px;
                    color: #666;
                }

                .form-group {
                    margin-bottom: 20px;
                }

                .form-group label {
                    display: block;
                    margin-bottom: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    color: #444;
                }

                .form-group input {
                    width: 100%;
                    padding: 14px;
                    font-size: 16px;
                    border: 2px solid #e0e0e0;
                    border-radius: 10px;
                    transition: border-color 0.3s;
                }

                .form-group input:focus {
                    outline: none;
                    border-color: #667eea;
                }

                .form-group input[type="text"].pin-input {
                    letter-spacing: 8px;
                    text-align: center;
                    font-size: 24px;
                    font-weight: bold;
                    text-transform: uppercase;
                }

                .btn-login {
                    width: 100%;
                    padding: 16px;
                    font-size: 16px;
                    font-weight: 600;
                    color: white;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border: none;
                    border-radius: 10px;
                    cursor: pointer;
                    transition: transform 0.2s, box-shadow 0.2s;
                    margin-top: 10px;
                    min-height: 54px;
                }

                .btn-login:active {
                    transform: translateY(2px);
                }

                .alert {
                    padding: 14px;
                    border-radius: 10px;
                    margin-bottom: 20px;
                    font-size: 14px;
                }

                .alert-error {
                    background-color: #fee;
                    color: #c33;
                    border: 1px solid #fcc;
                }

                .alert-success {
                    background-color: #efe;
                    color: #3c3;
                    border: 1px solid #cfc;
                }

                .login-footer {
                    text-align: center;
                    margin-top: 20px;
                    font-size: 12px;
                    color: #999;
                }

                /* Improve touch targets for mobile */
                @media (max-width: 480px) {
                    .form-group input,
                    .btn-login {
                        min-height: 48px;
                    }
                }
            </style>
        </head>
        <body>
            <div class="login-container">
                <div class="login-header">
                    <h1><?php echo esc_html($site_name); ?></h1>
                    <p>Mobile Access</p>
                </div>

                <?php if ($this->error_message): ?>
                    <div class="alert alert-error">
                        <?php echo esc_html($this->error_message); ?>
                    </div>
                <?php endif; ?>

                <?php if ($this->success_message): ?>
                    <div class="alert alert-success">
                        <?php echo esc_html($this->success_message); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <?php wp_nonce_field('timegrow_mobile_login', 'timegrow_mobile_login_nonce'); ?>

                    <div class="form-group">
                        <label for="username">Username or Email</label>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            autocomplete="username"
                            required
                            autofocus
                            value="<?php echo isset($_POST['username']) ? esc_attr($_POST['username']) : ''; ?>"
                        >
                    </div>

                    <div class="form-group">
                        <label for="pin">6-Character PIN</label>
                        <input
                            type="text"
                            id="pin"
                            name="pin"
                            class="pin-input"
                            pattern="[A-Z0-9]{6}"
                            maxlength="6"
                            autocomplete="off"
                            required
                            placeholder="ABC123"
                        >
                    </div>

                    <button type="submit" class="btn-login">Sign In</button>
                </form>

                <div class="login-footer">
                    Forgot your PIN? Contact your administrator.
                </div>
            </div>

            <script>
                // Auto-submit when 6 characters are entered and auto-uppercase
                document.getElementById('pin').addEventListener('input', function(e) {
                    // Convert to uppercase and remove invalid characters
                    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');

                    // Prevent more than 6 characters
                    if (this.value.length > 6) {
                        this.value = this.value.slice(0, 6);
                    }

                    // Auto-submit when 6 characters are entered
                    if (this.value.length === 6) {
                        // Small delay to allow user to see the last character
                        setTimeout(() => {
                            this.form.submit();
                        }, 300);
                    }
                });
            </script>
        </body>
        </html>
        <?php
    }

    /**
     * Render 2FA verification form
     */
    private function render_2fa_form() {
        $site_name = get_bloginfo('name');
        $username = isset($_SESSION['timegrow_2fa_username']) ? $_SESSION['timegrow_2fa_username'] : '';
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <title>2FA Verification - <?php echo esc_html($site_name); ?></title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }

                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }

                .login-container {
                    background: white;
                    border-radius: 20px;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    padding: 40px 30px;
                    width: 100%;
                    max-width: 400px;
                }

                .login-header {
                    text-align: center;
                    margin-bottom: 30px;
                }

                .login-header h1 {
                    font-size: 24px;
                    color: #333;
                    margin-bottom: 8px;
                }

                .login-header p {
                    font-size: 14px;
                    color: #666;
                }

                .form-group {
                    margin-bottom: 20px;
                }

                .form-group label {
                    display: block;
                    margin-bottom: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    color: #444;
                }

                .form-group input {
                    width: 100%;
                    padding: 14px;
                    font-size: 16px;
                    border: 2px solid #e0e0e0;
                    border-radius: 10px;
                    transition: border-color 0.3s;
                }

                .form-group input:focus {
                    outline: none;
                    border-color: #667eea;
                }

                .form-group input.totp-input {
                    letter-spacing: 8px;
                    text-align: center;
                    font-size: 24px;
                    font-weight: bold;
                }

                .btn-login {
                    width: 100%;
                    padding: 16px;
                    font-size: 16px;
                    font-weight: 600;
                    color: white;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border: none;
                    border-radius: 10px;
                    cursor: pointer;
                    transition: transform 0.2s;
                }

                .btn-login:hover {
                    transform: translateY(-2px);
                }

                .btn-login:active {
                    transform: translateY(0);
                }

                .error-message {
                    background: #fee;
                    color: #c33;
                    padding: 12px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    border-left: 4px solid #c33;
                }

                .info-message {
                    background: #e7f3ff;
                    color: #0066cc;
                    padding: 12px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    border-left: 4px solid #0066cc;
                    font-size: 14px;
                }

                .login-footer {
                    text-align: center;
                    margin-top: 20px;
                    font-size: 13px;
                    color: #666;
                }

                .login-footer a {
                    color: #667eea;
                    text-decoration: none;
                }
            </style>
        </head>
        <body>
            <div class="login-container">
                <div class="login-header">
                    <h1>Two-Factor Authentication</h1>
                    <p>Enter your authentication code</p>
                </div>

                <?php if (!empty($this->error_message)) : ?>
                    <div class="error-message">
                        <?php echo esc_html($this->error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="info-message">
                    <strong>Logged in as:</strong> <?php echo esc_html($username); ?><br>
                    Enter the 6-digit code from your authenticator app (Google Authenticator, Authy, etc.)
                </div>

                <form method="POST" action="">
                    <?php wp_nonce_field('timegrow_mobile_login', 'timegrow_mobile_login_nonce'); ?>
                    <input type="hidden" name="username" value="<?php echo esc_attr($username); ?>">
                    <input type="hidden" name="pin" value="verified">
                    <input type="hidden" name="totp_code" id="totp_code_hidden" value="">

                    <div class="form-group">
                        <label for="totp_code">Authentication Code</label>
                        <input
                            type="text"
                            id="totp_code"
                            name="totp_code_display"
                            class="totp-input"
                            pattern="[0-9]{6}"
                            maxlength="6"
                            inputmode="numeric"
                            autocomplete="off"
                            required
                            autofocus
                            placeholder="000000"
                        >
                    </div>

                    <button type="submit" class="btn-login">Verify</button>
                </form>

                <div class="login-footer">
                    <a href="<?php echo esc_url(home_url('/mobile-login')); ?>">← Back to PIN login</a>
                </div>
            </div>

            <script>
                // Auto-submit when 6 digits are entered
                document.getElementById('totp_code').addEventListener('input', function(e) {
                    // Only allow digits
                    this.value = this.value.replace(/[^0-9]/g, '');

                    // Prevent more than 6 digits
                    if (this.value.length > 6) {
                        this.value = this.value.slice(0, 6);
                    }

                    // Update hidden field
                    document.getElementById('totp_code_hidden').value = this.value;

                    // Auto-submit when 6 digits are entered
                    if (this.value.length === 6) {
                        setTimeout(() => {
                            this.form.submit();
                        }, 300);
                    }
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Send SMS notification on successful login
     *
     * @param int $user_id User ID
     * @param string $phone Phone number
     */
    private function send_login_sms_notification($user_id, $phone) {
        $user = get_userdata($user_id);
        $timestamp = wp_date(get_option('time_format'));

        $message = sprintf(
            "TimeGrow mobile login at %s. If this wasn't you, contact your administrator immediately.",
            $timestamp
        );

        // Hook for SMS integration
        do_action('timegrow_send_login_sms', $phone, $message, $user_id);

        // Log for debugging (remove in production)
        error_log("TimeGrow Login SMS would be sent to {$phone}: {$message}");
    }
}
