<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * What TimeGrow needs around it, and the move of its base capabilities to the timegrow_ names.
 *
 * - Activation is refused while a required plugin is not active (Aragrow Base, WooCommerce,
 *   PublishPress Capabilities). Deactivated later, they leave TimeGrow running, with a warning
 *   to administrators.
 * - The base capabilities were named timeflies_owner, timeflies_admin and timeflies_team_member.
 *   They are now timegrow_owner, timegrow_admin and timegrow_team_member: every role and user that
 *   had an old one gets the new one, and the old one is removed (once, on activation or the next
 *   admin page load).
 */
class TimeGrowRequirements {

    /** Plugin file => name. */
    const REQUIRED_PLUGINS = [
        'aragrow-base/aragrow-base.php'                     => 'Aragrow Base',
        'woocommerce/woocommerce.php'                       => 'WooCommerce',
        'capability-manager-enhanced/capsman-enhanced.php'  => 'PublishPress Capabilities',
    ];

    /** Old capability => new capability. */
    const RENAMED_CAPS = [
        'timeflies_owner'       => 'timegrow_owner',
        'timeflies_admin'       => 'timegrow_admin',
        'timeflies_team_member' => 'timegrow_team_member',
    ];

    /** TimeGrow's settings groups (Settings tabs, WooCommerce Integration, PayPal Integration). */
    const SETTINGS_GROUPS = [
        'aragrow_timegrow_general_settings_group',
        'timegrow_receipt_settings_group',
        'timegrow_voice_settings_group',
        'timegrow_integration_group',
        'wc_pp_auto_invoicer',
    ];

    /**
     * Saving a TimeGrow settings page goes through WordPress's options.php, which asks for
     * manage_options unless told otherwise: these groups ask for timegrow_owner instead, like the
     * pages that show them.
     */
    public static function register_settings_capability() {
        foreach (self::SETTINGS_GROUPS as $group) {
            add_filter('option_page_capability_' . $group, [__CLASS__, 'settings_capability']);
        }
    }

    public static function settings_capability() {
        return TIMEGROW_OWNER_CAP;
    }

    /** Option holding which capability names are in use (2 = timegrow_). */
    const CAPS_VERSION_OPTION = 'timegrow_caps_version';

    /**
     * The required plugins that are not active, by name. Pure when $is_active is given.
     *
     * @param callable|null $is_active fn(string $plugin_file) => bool; default is_plugin_active().
     * @return string[]
     */
    public static function missing_plugins($is_active = null) {
        if ($is_active === null) {
            if (!function_exists('is_plugin_active')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $is_active = 'is_plugin_active';
        }
        $missing = [];
        foreach (self::REQUIRED_PLUGINS as $file => $name) {
            if (!$is_active($file)) {
                $missing[] = $name;
            }
        }
        return $missing;
    }

    /** "TimeGrow needs A and B to be active." Pure. */
    public static function missing_message(array $missing) {
        $last = array_pop($missing);
        $list = $missing ? implode(', ', $missing) . ' ' . __('and', 'timegrow') . ' ' . $last : $last;
        return sprintf(__('TimeGrow needs %s to be active.', 'timegrow'), $list);
    }

    /** Activation: refuse it (the plugin stays inactive) while a required plugin is not active. */
    public static function check_on_activation() {
        $missing = self::missing_plugins();
        if (!$missing) {
            return;
        }
        wp_die(
            '<p>' . esc_html(self::missing_message($missing)) . '</p><p>' . esc_html__('Activate it first, then activate TimeGrow again.', 'timegrow') . '</p>',
            esc_html__('TimeGrow was not activated', 'timegrow'),
            ['back_link' => true, 'response' => 200]
        );
    }

    /** admin_notices: a required plugin was deactivated after TimeGrow was activated. */
    public static function missing_plugins_notice() {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        $missing = self::missing_plugins();
        if ($missing) {
            echo '<div class="notice notice-warning"><p><strong>TimeGrow:</strong> ' . esc_html(self::missing_message($missing)) . ' '
                . esc_html__('Some TimeGrow features will not work until it is activated again.', 'timegrow') . '</p></div>';
        }
    }

    /**
     * The changes that move a capability list to the new names. Pure.
     *
     * @param array $caps Capability => granted (a role's or user's capabilities).
     * @return array { add: string[], remove: string[] } add = new names for old ones that were granted
     */
    public static function caps_to_rename(array $caps) {
        $plan = ['add' => [], 'remove' => []];
        foreach (self::RENAMED_CAPS as $old => $new) {
            if (!array_key_exists($old, $caps)) {
                continue;
            }
            $plan['remove'][] = $old;
            if ($caps[$old] && empty($caps[$new])) {
                $plan['add'][] = $new;
            }
        }
        return $plan;
    }

    /** Rename the capabilities on every role and user, once. @return int roles + users changed */
    public static function rename_capabilities() {
        global $wpdb;
        if ((int) get_option(self::CAPS_VERSION_OPTION) >= 2) {
            return 0;
        }
        $changed = 0;
        foreach (wp_roles()->role_objects as $role) {
            $plan = self::caps_to_rename((array) $role->capabilities);
            foreach ($plan['add'] as $cap) $role->add_cap($cap);
            foreach ($plan['remove'] as $cap) $role->remove_cap($cap);
            if ($plan['remove']) $changed++;
        }
        // Capabilities given to one person (PublishPress Capabilities can do that) live in their user meta
        $like = '%' . $wpdb->esc_like('timeflies_') . '%';
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s",
            $wpdb->get_blog_prefix() . 'capabilities', $like
        ));
        foreach ($user_ids as $user_id) {
            $user = new WP_User((int) $user_id);
            $plan = self::caps_to_rename((array) $user->caps);
            foreach ($plan['add'] as $cap) $user->add_cap($cap);
            foreach ($plan['remove'] as $cap) $user->remove_cap($cap);
            if ($plan['remove']) $changed++;
        }
        update_option(self::CAPS_VERSION_OPTION, 2, false);
        return $changed;
    }
}
