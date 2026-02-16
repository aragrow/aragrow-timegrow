<?php
/**
 * TimeGrow Mobile Access Control
 *
 * Middleware to restrict mobile-only users to authorized pages only
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

    /**
     * Initialize access control hooks
     */
    public static function init() {
        // Intercept admin page access
        add_action('admin_init', [__CLASS__, 'enforce_access_restrictions'], 1);

        // Smart routing for time entry based on capabilities
        add_action('admin_init', [__CLASS__, 'smart_time_entry_routing'], 2);

        // Hide admin bar for mobile-only users
        add_filter('show_admin_bar', [__CLASS__, 'hide_admin_bar']);

        // Filter admin menu items
        add_action('admin_menu', [__CLASS__, 'filter_admin_menu'], 999);
    }

    /**
     * Smart routing for time entry based on user's time entry method setting
     * Routes users to clock or manual entry page based on their configured preference
     * This applies to ALL users (mobile and desktop), not just mobile sessions
     */
    public static function smart_time_entry_routing() {
        // Don't interfere with AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        // Only apply to logged-in users
        if (!is_user_logged_in()) {
            return;
        }

        // Get current page
        $current_page = isset($_GET['page']) ? $_GET['page'] : '';

        // Only intercept clock and manual pages
        if ($current_page !== 'timegrow-nexus-clock' && $current_page !== 'timegrow-nexus-manual') {
            return;
        }

        // Get user's time entry method preference
        $user_id = get_current_user_id();
        $time_entry_method = get_user_meta($user_id, 'timegrow_time_entry_method', true);

        // Default to clock if not set
        if (empty($time_entry_method)) {
            $time_entry_method = 'clock';
        }

        // Redirect based on user's preference
        if ($time_entry_method === 'clock' && $current_page === 'timegrow-nexus-manual') {
            // User is set to clock in/out, redirect from manual to clock
            wp_redirect(admin_url('admin.php?page=timegrow-nexus-clock'));
            exit;
        } elseif ($time_entry_method === 'manual' && $current_page === 'timegrow-nexus-clock') {
            // User is set to manual entry, redirect from clock to manual
            wp_redirect(admin_url('admin.php?page=timegrow-nexus-manual'));
            exit;
        }
    }

    /**
     * Enforce access restrictions for mobile-only users
     * Only applies if user logged in via mobile PIN session
     */
    public static function enforce_access_restrictions() {
        // Check if user is in a mobile session (logged in via PIN)
        if (!self::is_mobile_session()) {
            return; // Not a mobile session, allow full access
        }

        // User is in mobile session, apply restrictions

        // Don't interfere with AJAX requests
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        // Build allowed pages based on user capabilities
        $allowed_pages = self::get_allowed_pages();

        // Get current page
        $current_page = isset($_GET['page']) ? $_GET['page'] : '';

        // Allow if no page parameter (will be redirected below)
        if (empty($current_page)) {
            // Redirect to first allowed page
            if (!empty($allowed_pages)) {
                wp_redirect(admin_url('admin.php?page=' . $allowed_pages[0]));
                exit;
            } else {
                wp_die(
                    '<h1>No Mobile Access Granted</h1>' .
                    '<p>You do not have permission to access any features. Please contact your administrator.</p>',
                    'Access Denied',
                    ['response' => 403]
                );
            }
        }

        // Block access to wp-admin pages not in whitelist
        if (!in_array($current_page, $allowed_pages)) {
            // Redirect to first allowed page
            if (!empty($allowed_pages)) {
                wp_redirect(admin_url('admin.php?page=' . $allowed_pages[0]));
                exit;
            } else {
                wp_die(
                    '<h1>Access Denied</h1>' .
                    '<p>You do not have permission to access this page. Contact your administrator.</p>',
                    'Access Denied',
                    ['response' => 403]
                );
            }
        }
    }

    /**
     * Check if user is in a mobile session (logged in via PIN)
     *
     * @return bool True if mobile session active
     */
    private static function is_mobile_session() {
        // Check if mobile session cookie exists
        return isset($_COOKIE['timegrow_mobile_session']);
    }

    /**
     * Get allowed pages based on user capabilities
     *
     * @return array Array of allowed page slugs
     */
    private static function get_allowed_pages() {
        $allowed_pages = [];

        // Time tracking pages - allow both clock and manual if user has time tracking
        // The smart_time_entry_routing() will handle redirecting to the correct one
        if (current_user_can('access_mobile_time_tracking')) {
            $allowed_pages[] = 'timegrow-nexus-clock';
            $allowed_pages[] = 'timegrow-nexus-manual';
        }

        // Expenses page
        if (current_user_can('access_nexus_record_expenses') || current_user_can('access_mobile_expenses')) {
            $allowed_pages[] = 'timegrow-nexus-expenses';
        }

        // Reports page (always available if user has at least one capability)
        if (!empty($allowed_pages)) {
            $allowed_pages[] = 'timegrow-nexus-reports';
        }

        return $allowed_pages;
    }

    /**
     * Hide admin bar for mobile session users
     *
     * @param bool $show Whether to show admin bar
     * @return bool
     */
    public static function hide_admin_bar($show) {
        if (self::is_mobile_session()) {
            return false;
        }
        return $show;
    }

    /**
     * Filter admin menu to hide non-allowed items
     * Only applies to mobile sessions
     */
    public static function filter_admin_menu() {
        global $menu, $submenu;

        // Only apply to mobile sessions
        if (!self::is_mobile_session()) {
            return;
        }

        $allowed_pages = self::get_allowed_pages();

        // Remove all menu items except TimeGrow Nexus
        foreach ($menu as $key => $item) {
            // Keep only TimeGrow Nexus menu
            if (isset($item[2]) && $item[2] !== 'timegrow-nexus') {
                remove_menu_page($item[2]);
            }
        }

        // Filter TimeGrow Nexus submenu items
        if (isset($submenu['timegrow-nexus'])) {
            foreach ($submenu['timegrow-nexus'] as $key => $item) {
                if (isset($item[2]) && !in_array($item[2], $allowed_pages)) {
                    unset($submenu['timegrow-nexus'][$key]);
                }
            }
        }
    }
}
