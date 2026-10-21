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

    /** @var string The code step's token after a right PIN, when the person has 2FA on ('' otherwise). */
    private $pending = '';

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
        // Already in a mobile session: straight to their first page
        if (is_user_logged_in() && TimeGrowMobileAccess::in_mobile_session()) {
            $this->go_to_first_page();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_login(); // redirects on success; otherwise sets the error shown below
        }

        $this->render();
    }

    /** Their first mobile page (time entry, else expenses); a session with none is refused there. */
    private function go_to_first_page() {
        $pages = TimeGrowMobileAccess::my_pages();
        wp_safe_redirect(admin_url('admin.php?page=' . ($pages[0] ?? TIMEGROW_PARENT_MENU . '-nexus-clock')));
        exit;
    }

    /**
     * Handle login form submission: a team member's username or email and their PIN
     * (TimeGrowPINAuthenticator), then, when they have the site's 2FA on, their code. Every failure
     * shows the same message.
     */
    private function handle_login() {
        if (!isset($_POST['timegrow_mobile_login_nonce']) ||
            !wp_verify_nonce($_POST['timegrow_mobile_login_nonce'], 'timegrow_mobile_login')) {
            $this->error_message = 'Security verification failed. Please try again.';
            return;
        }
        if (isset($_POST['pending'])) {
            $this->handle_code();
            return;
        }

        $username = isset($_POST['username']) ? sanitize_text_field(wp_unslash($_POST['username'])) : '';
        $pin      = isset($_POST['pin']) ? sanitize_text_field(wp_unslash($_POST['pin'])) : '';
        if ($username === '' || $pin === '') {
            $this->error_message = 'Please enter both username and PIN.';
            return;
        }

        $user = get_user_by('login', $username) ?: get_user_by('email', $username);
        $result = $this->authenticator->verify_pin($user ? (int) $user->ID : 0, $pin); // also counts failures for unknown names
        if (!$result['success']) {
            $this->error_message = $result['message'];
            return;
        }

        // The site's two-factor authentication (Wordfence Login Security), when they have it
        if (TimeGrowMobile2FA::setup_missing($user)) {
            $this->error_message = 'This site requires two-factor authentication. Set it up first: log in with your password on a computer, then use your PIN here.';
            return;
        }
        if (TimeGrowMobile2FA::enabled_for($user)) {
            $this->pending = $this->authenticator->start_pending($user->ID);
            return; // the code step is shown
        }
        $this->finish($user->ID);
    }

    /** The code step: the code from their authenticator app (or a recovery code). */
    private function handle_code() {
        $token   = sanitize_key(wp_unslash($_POST['pending']));
        $user_id = $this->authenticator->pending_user($token);
        $user    = $user_id ? get_userdata($user_id) : null;
        if (!$user) {
            $this->error_message = 'That took too long. Enter your username and PIN again.';
            return;
        }
        if ($this->authenticator->is_account_locked($user_id)) {
            $this->authenticator->end_pending($token);
            $this->error_message = 'Too many failed logins. Try again in 15 minutes.';
            return;
        }
        $code = sanitize_text_field(wp_unslash($_POST['code'] ?? ''));
        if (!TimeGrowMobile2FA::verify($user, $code)) {
            $this->authenticator->count_failure($user_id); // like a wrong PIN: 5 lock it
            $this->pending = $token;
            $this->error_message = 'That code is not right. Enter the current code from your authenticator app.';
            return;
        }
        $this->authenticator->end_pending($token);
        $this->finish($user_id);
    }

    /** Log in (8 hours, marked mobile) and open their first page. */
    private function finish($user_id) {
        $this->authenticator->login_succeeded($user_id);
        $this->authenticator->create_mobile_session($user_id);
        $this->go_to_first_page();
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

                <?php if ($this->pending !== '') : ?>
                <form method="post" action="">
                    <?php wp_nonce_field('timegrow_mobile_login', 'timegrow_mobile_login_nonce'); ?>
                    <input type="hidden" name="pending" value="<?php echo esc_attr($this->pending); ?>">
                    <div class="form-group">
                        <label for="code">Two-factor code</label>
                        <input type="text" id="code" name="code" class="pin-input" inputmode="numeric" autocomplete="one-time-code" maxlength="24" required autofocus placeholder="123456">
                    </div>
                    <p style="font-size: 13px; color: #666; margin-bottom: 10px;">Enter the code from your authenticator app, the same one you use for this site. A recovery code works too.</p>
                    <button type="submit" class="btn-login">Verify</button>
                </form>
                <?php else : ?>
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
                <?php endif; ?>

                <div class="login-footer">
                    Forgot your PIN? Contact your administrator.<br>
                    Administrators: <a href="<?php echo esc_url(add_query_arg('timegrow_password', '1', wp_login_url())); ?>">log in with your password</a>.
                </div>
            </div>

            <script>
                // Auto-submit when 6 characters are entered and auto-uppercase
                document.getElementById('pin') && document.getElementById('pin').addEventListener('input', function(e) {
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
}
