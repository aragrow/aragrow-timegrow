<?php
/**
 * TimeGrow Mobile Access Control
 *
 * Keeps a mobile session (a PIN login, TimeGrowMobileAccess::in_mobile_session()) to the daily tasks:
 * the person's Clock In / Out, Manual Time Entry and Record Expenses pages, opening their receipts,
 * and the AJAX actions those pages and the voice assistant use. Everything else in wp-admin is closed.
 *
 * @package TimeGrow
 * @subpackage Mobile
 * @since 2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowMobileAccessControl {

    public static function init() {
        add_action('admin_init', [__CLASS__, 'enforce_access_restrictions'], 1);
        add_action('admin_init', [__CLASS__, 'smart_time_entry_routing'], 2);
        add_filter('show_admin_bar', [__CLASS__, 'hide_admin_bar']);
        add_action('admin_menu', [__CLASS__, 'filter_admin_menu'], 999);
    }

    /**
     * Clock In / Out or Manual Time Entry, by the person's Time Entry Method (profile). For everyone
     * but administrators, on desktop too.
     */
    public static function smart_time_entry_routing() {
        if (wp_doing_ajax() || !is_user_logged_in() || current_user_can('administrator')) {
            return;
        }
        $page   = sanitize_key($_GET['page'] ?? '');
        $method = get_user_meta(get_current_user_id(), 'timegrow_time_entry_method', true) ?: 'clock';
        if ($method === 'clock' && $page === TIMEGROW_PARENT_MENU . '-nexus-manual') {
            wp_safe_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-clock'));
            exit;
        }
        if ($method === 'manual' && $page === TIMEGROW_PARENT_MENU . '-nexus-clock') {
            wp_safe_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-manual'));
            exit;
        }
    }

    /**
     * The answer for one admin request in a mobile session: 'allow', 'redirect' (to the first allowed
     * page) or 'refuse'. Pure.
     *
     * @param string   $script  The wp-admin file: admin.php, admin-ajax.php, admin-post.php, …
     * @param array    $get     The query ($_GET), and for AJAX the action from $_REQUEST.
     * @param string[] $pages   The person's allowed pages (TimeGrowMobileAccess::my_pages()).
     * @param bool     $time    They have the Time tracking capability.
     */
    public static function decide($script, array $get, array $pages, $time) {
        if ($script === 'admin-ajax.php') {
            $action = (string) ($get['action'] ?? '');
            if (!in_array($action, TimeGrowMobileAccess::AJAX, true)) {
                return 'refuse';
            }
            // Saving time needs the Time tracking capability (punching in and out by voice too)
            return ($action === 'save_time_entry' && !$time) ? 'refuse' : 'allow';
        }
        if ($script === 'admin-post.php') {
            return (($get['action'] ?? '') === 'timegrow_view_receipt') ? 'allow' : 'refuse'; // receipts check who may open them
        }
        if ($script === 'admin.php' && in_array((string) ($get['page'] ?? ''), $pages, true)) {
            return 'allow';
        }
        return 'redirect';
    }

    /** admin_init: apply decide() to a mobile session; sign out a mobile login made before 2.2.0. */
    public static function enforce_access_restrictions() {
        if (!TimeGrowMobileAccess::in_mobile_session()) {
            // Before 2.2.0 a mobile login was only a cookie beside a full login: that login is ended once
            if (isset($_COOKIE[TimeGrowPINAuthenticator::COOKIE_NAME]) && is_user_logged_in() && !wp_doing_ajax()) {
                (new TimeGrowPINAuthenticator())->destroy_mobile_session();
                wp_safe_redirect(home_url('/mobile-login'));
                exit;
            }
            return;
        }
        $pages  = TimeGrowMobileAccess::my_pages();
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'admin.php'));
        $get    = $_GET;
        if (wp_doing_ajax()) {
            $get['action'] = (string) ($_REQUEST['action'] ?? '');
        }
        switch (self::decide($script, $get, $pages, current_user_can('access_mobile_time_tracking'))) {
            case 'allow':
                return;
            case 'refuse':
                if (wp_doing_ajax()) {
                    wp_send_json_error(['message' => __('That is not available in the mobile app.', 'timegrow')], 403);
                }
                wp_die(esc_html__('That is not available in the mobile app.', 'timegrow'), esc_html__('Not available', 'timegrow'), ['response' => 403, 'back_link' => true]);
            default:
                if ($pages) {
                    wp_safe_redirect(admin_url('admin.php?page=' . $pages[0]));
                    exit;
                }
                wp_die(
                    '<h1>' . esc_html__('No mobile access', 'timegrow') . '</h1><p>' . esc_html__('You have not been given time tracking or expenses in the mobile app. Contact your administrator.', 'timegrow') . '</p>'
                    . '<p><a href="' . esc_url(wp_logout_url(home_url('/mobile-login'))) . '">' . esc_html__('Log out', 'timegrow') . '</a></p>',
                    esc_html__('No mobile access', 'timegrow'),
                    ['response' => 403]
                );
        }
    }

    public static function hide_admin_bar($show) {
        return TimeGrowMobileAccess::in_mobile_session() ? false : $show;
    }

    /** In a mobile session the wp-admin menu shows only the allowed pages (the mobile header has its own menu). */
    public static function filter_admin_menu() {
        global $menu, $submenu;
        if (!TimeGrowMobileAccess::in_mobile_session()) {
            return;
        }
        $pages = TimeGrowMobileAccess::my_pages();
        foreach ((array) $menu as $item) {
            if (isset($item[2]) && $item[2] !== TIMEGROW_PARENT_MENU) {
                remove_menu_page($item[2]);
            }
        }
        foreach ((array) ($submenu[TIMEGROW_PARENT_MENU] ?? []) as $key => $item) {
            if (isset($item[2]) && !in_array($item[2], $pages, true)) {
                unset($submenu[TIMEGROW_PARENT_MENU][$key]);
            }
        }
    }
}
