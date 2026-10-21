<?php
/**
 * TimeGrow Mobile Access Module
 *
 * Provides PIN-based mobile-only access mode with fine-grained capability control
 * for time tracking and expense management.
 *
 * @package TimeGrow
 * @subpackage Mobile
 * @since 2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define module constants
define('TIMEGROW_MOBILE_VERSION', '1.0.0');
define('TIMEGROW_MOBILE_BASE_DIR', plugin_dir_path(__FILE__));
define('TIMEGROW_MOBILE_BASE_URI', plugin_dir_url(__FILE__));
define('TIMEGROW_MOBILE_INCLUDES_DIR', TIMEGROW_MOBILE_BASE_DIR . 'includes/');
define('TIMEGROW_MOBILE_ASSETS_URI', TIMEGROW_MOBILE_BASE_URI . 'assets/');

/**
 * Mobile Module Class
 */
class TimeGrowMobile {

    /**
     * Initialize the mobile module
     */
    public static function init() {
        // Run activation on plugin load (checks if table exists)
        add_action('plugins_loaded', [__CLASS__, 'maybe_create_table']);

        // Register capabilities
        add_action('init', [__CLASS__, 'register_capabilities']);

        // Load dependencies
        self::load_dependencies();

        // Register URL rewrite rules
        add_action('init', [__CLASS__, 'register_rewrite_rules']);

        // Redirect mobile devices to mobile login (early hook)
        add_action('init', [__CLASS__, 'redirect_mobile_devices'], 5);

        // Handle mobile login page
        add_action('template_redirect', [__CLASS__, 'handle_mobile_login']);

        // Add admin menu
        add_action('admin_menu', [__CLASS__, 'add_admin_menu'], 11);

        // Enqueue mobile assets
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_mobile_assets']);

        // Once, on upgrade to 2.2.0: earlier PIN logins end, and administrators' PINs are switched off
        add_action('admin_init', [__CLASS__, 'upgrade_sessions'], 0);
    }

    /**
     * Before 2.2.0 a PIN login was a full 14-day WordPress login with a separate mobile cookie, so
     * removing the cookie gave full access. Once: team members with a PIN are logged out everywhere
     * (they log in again with their PIN), and administrators' PINs are switched off (administrators use
     * the normal login now).
     */
    public static function upgrade_sessions() {
        if (get_option('timegrow_mobile_sessions_v2') === '1') {
            return;
        }
        $model = new TimeGrowMobilePINModel();
        foreach ((array) $model->get_all_active() as $row) {
            if (!TimeGrowMobileAccess::pin_allowed((int) $row->user_id)) {
                $model->set_active((int) $row->user_id, false);
            } elseif ((int) $row->user_id !== get_current_user_id()) {
                WP_Session_Tokens::get_instance((int) $row->user_id)->destroy_all();
            }
        }
        update_option('timegrow_mobile_sessions_v2', '1', false);
    }

    /**
     * Maybe create database table (runs on every plugin load)
     * Only creates if table doesn't exist
     */
    public static function maybe_create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'timegrow_mobile_pins';

        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table_name)));

        if ($table_exists !== $table_name) {
            // Table doesn't exist, create it
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE {$table_name} (
                ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_id bigint(20) unsigned NOT NULL UNIQUE,
                pin_hash varchar(255) NOT NULL,
                pin_salt varchar(64) NOT NULL,
                is_active tinyint(1) NOT NULL DEFAULT 1,
                failed_attempts tinyint(3) NOT NULL DEFAULT 0,
                locked_until datetime DEFAULT NULL,
                last_login_at datetime DEFAULT NULL,
                created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (ID),
                KEY idx_user_active (user_id, is_active)
            ) {$charset_collate};";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            // Set flag to flush rewrite rules
            update_option('timegrow_mobile_flush_rewrite_rules', '1');
        }

        // Check if we need to flush rewrite rules
        if (get_option('timegrow_mobile_flush_rewrite_rules') === '1') {
            flush_rewrite_rules();
            delete_option('timegrow_mobile_flush_rewrite_rules');
        }
    }

    /**
     * Register mobile-only capabilities
     */
    public static function register_capabilities() {
        // Administrators hold the mobile capabilities (the app token uses them); the PIN login is for team members only
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('access_mobile_only_mode');
            $admin_role->add_cap('access_mobile_time_tracking');
            $admin_role->add_cap('access_mobile_expenses');
            $admin_role->add_cap('access_mobile_ai_voice');
            $admin_role->add_cap('access_mobile_ai_receipts');
        }
    }

    /**
     * Load required dependencies
     */
    private static function load_dependencies() {
        // Load model
        require_once TIMEGROW_MOBILE_INCLUDES_DIR . 'TimeGrowMobileAccess.php'; // who may use TimeGrow remotely
        require_once TIMEGROW_MOBILE_INCLUDES_DIR . 'models/TimeGrowMobilePINModel.php';

        // Load authenticator
        require_once TIMEGROW_MOBILE_INCLUDES_DIR . 'TimeGrowPINAuthenticator.php';

        // Load access control middleware
        require_once TIMEGROW_MOBILE_INCLUDES_DIR . 'TimeGrowMobileAccessControl.php';

        // Load user profile integration
        require_once TIMEGROW_MOBILE_INCLUDES_DIR . 'TimeGrowMobileUserProfile.php';

        // Load SMS notifications
        require_once TIMEGROW_MOBILE_INCLUDES_DIR . 'TimeGrowSMSNotifications.php';

        // Initialize access control
        TimeGrowMobileAccessControl::init();
    }

    /**
     * Register URL rewrite rules
     */
    public static function register_rewrite_rules() {
        add_rewrite_rule('^mobile-login/?$', 'index.php?mobile_login=1', 'top');
        add_rewrite_tag('%mobile_login%', '1');
    }

    /**
     * Detect mobile device and redirect to mobile login
     * Only redirects if:
     * - User is on a mobile device
     * - User is not already on mobile-login page
     * - User is not already logged in
     * - User is trying to access wp-login.php
     */
    public static function redirect_mobile_devices() {
        // Don't redirect if already on mobile login page
        if (get_query_var('mobile_login')) {
            return;
        }

        // Don't redirect if user is already logged in (regular WordPress login)
        if (is_user_logged_in()) {
            return;
        }

        // Don't redirect AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        // Only the login form itself (NOT wp-admin): lost password, password reset links, logout and
        // the other wp-login.php actions open as usual on a phone
        $is_login_page = (strpos($_SERVER['REQUEST_URI'] ?? '', 'wp-login.php') !== false);
        // Only showing the form (a password login posted from a phone goes through), and not when the
        // person chose the password login (administrators: the link on the mobile login page)
        $action = sanitize_key($_REQUEST['action'] ?? 'login');
        if (!$is_login_page || $action !== 'login' || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || isset($_GET['interim-login']) || isset($_GET['timegrow_password'])) {
            return;
        }

        // Detect mobile device
        if (self::is_mobile_device()) {
            wp_redirect(home_url('/mobile-login'));
            exit;
        }
    }

    /**
     * Detect if current request is from a mobile device
     *
     * @return bool True if mobile device detected
     */
    private static function is_mobile_device() {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }

        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        // Mobile device patterns
        $mobile_patterns = [
            '/Android/i',
            '/webOS/i',
            '/iPhone/i',
            '/iPad/i',
            '/iPod/i',
            '/BlackBerry/i',
            '/Windows Phone/i',
            '/Mobile/i',
        ];

        foreach ($mobile_patterns as $pattern) {
            if (preg_match($pattern, $user_agent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle mobile login page template
     */
    public static function handle_mobile_login() {
        if (get_query_var('mobile_login')) {
            // Start output buffering to prevent headers-already-sent issues
            ob_start();

            require_once TIMEGROW_MOBILE_INCLUDES_DIR . 'views/TimeGrowMobileLoginView.php';
            $view = new TimeGrowMobileLoginView();
            $view->display();

            ob_end_flush();
            exit;
        }
    }

    /**
     * Mobile Access page: not in the menu, opened from its card on TimeGrow > Settings.
     */
    public static function add_admin_menu() {
        if (current_user_can('administrator')) {
            add_submenu_page(
                null, // hidden from the menu: TimeGrow > Settings > Mobile Access card
                'Mobile Access',
                'Mobile Access',
                'manage_options',
                'timegrow-mobile-settings',
                [__CLASS__, 'render_settings_page']
            );
            // Keep TimeGrow > Settings highlighted while on this page
            add_filter('parent_file', function ($parent) {
                return (($_GET['page'] ?? '') === 'timegrow-mobile-settings') ? TIMEGROW_PARENT_MENU : $parent;
            });
            add_filter('submenu_file', function ($file) {
                return (($_GET['page'] ?? '') === 'timegrow-mobile-settings') ? TIMEGROW_PARENT_MENU . '-settings' : $file;
            });
        }
    }

    /**
     * Render mobile settings page
     */
    public static function render_settings_page() {
        require_once TIMEGROW_MOBILE_INCLUDES_DIR . 'views/TimeGrowMobileSettingsView.php';
        $view = new TimeGrowMobileSettingsView();
        $view->display();
    }

    /**
     * Enqueue mobile-only assets for mobile sessions
     */
    public static function enqueue_mobile_assets() {
        // Only in a mobile session (logged in with a PIN)
        if (!TimeGrowMobileAccess::in_mobile_session()) {
            return;
        }

        // Enqueue mobile CSS
        wp_enqueue_style(
            'timegrow-mobile-only-mode',
            TIMEGROW_MOBILE_ASSETS_URI . 'css/mobile-only-mode.css',
            [],
            TIMEGROW_MOBILE_VERSION
        );

        // Enqueue mobile navigation JS
        wp_enqueue_script(
            'timegrow-mobile-navigation',
            TIMEGROW_MOBILE_ASSETS_URI . 'js/mobile-navigation.js',
            ['jquery'],
            TIMEGROW_MOBILE_VERSION,
            true
        );

        // The menu: the person's pages, and logging out
        $labels = [
            TIMEGROW_PARENT_MENU . '-nexus-clock'    => [__('Clock In / Out', 'timegrow'), 'dashicons-clock'],
            TIMEGROW_PARENT_MENU . '-nexus-manual'   => [__('Manual Entry', 'timegrow'), 'dashicons-edit'],
            TIMEGROW_PARENT_MENU . '-nexus-expenses' => [__('Expenses & Receipts', 'timegrow'), 'dashicons-money-alt'],
        ];
        $method = get_user_meta(get_current_user_id(), 'timegrow_time_entry_method', true) ?: 'clock';
        $items  = [];
        foreach (TimeGrowMobileAccess::my_pages() as $page) {
            // Only the person's time entry method: the other page redirects to it
            if (($method === 'clock' && $page === TIMEGROW_PARENT_MENU . '-nexus-manual') || ($method === 'manual' && $page === TIMEGROW_PARENT_MENU . '-nexus-clock')) {
                continue;
            }
            $items[] = ['page' => $page, 'label' => $labels[$page][0], 'icon' => $labels[$page][1]];
        }
        wp_localize_script('timegrow-mobile-navigation', 'timegrowMobile', [
            'items'     => $items,
            'logoutUrl' => wp_logout_url(home_url('/mobile-login')),
            'title'     => get_bloginfo('name'),
        ]);
    }
}

// Initialize the mobile module
TimeGrowMobile::init();
