<?php
/**
 * TimeGrow uninstall.
 *
 * Runs only when the plugin is deleted from the Plugins screen, and only removes data
 * when "Delete data on uninstall" is turned on (TimeGrow > Settings > General).
 * Otherwise everything is kept, so reinstalling the plugin picks up where it left off.
 *
 * Removed: TimeGrow tables, options, user meta, transients, the team_member and
 * mobile_user roles, and TimeGrow capabilities.
 * Kept: WooCommerce orders and products (financial records), including their
 * TimeGrow meta, and uploaded receipt files in the media library.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

function timegrow_uninstall_site() {
    global $wpdb;

    $settings = get_option( 'aragrow_timegrow_general_settings', [] );
    if ( ( $settings['delete_data_on_uninstall'] ?? 'no' ) !== 'yes' ) {
        return; // opt-in only
    }

    // Tables: wp_timegrow_* (foreign keys between them, so drop with checks off)
    $like   = $wpdb->esc_like( $wpdb->prefix . 'timegrow_' ) . '%';
    $tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $like ) );
    if ( $tables ) {
        $wpdb->query( 'SET FOREIGN_KEY_CHECKS = 0' );
        foreach ( $tables as $table ) {
            $wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $table ) . '`' );
        }
        $wpdb->query( 'SET FOREIGN_KEY_CHECKS = 1' );
    }

    // Options and transients
    $option_prefixes = [ 'timegrow_', 'aragrow_timegrow_', 'wc_pp_auto_invoicer_', 'timeflies_', '_transient_timegrow_', '_transient_timeout_timegrow_' ];
    foreach ( $option_prefixes as $prefix ) {
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like( $prefix ) . '%'
        ) );
    }
    delete_option( 'pp_capabilities_nexus2timegrow_role_option' );

    // User profile fields
    foreach ( [ 'timegrow_', '_timegrow_', 'timeflies_' ] as $prefix ) {
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
            $wpdb->esc_like( $prefix ) . '%'
        ) );
    }

    // Capabilities, from every role
    $caps = [
        'timeflies_admin', 'timeflies_owner', 'timeflies_team_member',
        'access_mobile_only_mode', 'access_mobile_time_tracking', 'access_mobile_expenses',
        'manage_expense_categories',
        'view_all_expenses_overview', 'view_client_activity_report', 'view_monthly_profit_loss',
        'view_my_detailed_time_log', 'view_my_expenses_report', 'view_my_hours_by_project',
        'view_project_financials', 'view_team_hours_summary', 'view_time_entry_audit_log',
        'view_yearly_tax_report',
    ];
    foreach ( wp_roles()->role_objects as $role ) {
        foreach ( $caps as $cap ) {
            $role->remove_cap( $cap );
        }
    }

    // Roles added by TimeGrow (users with them keep their account, without the role)
    remove_role( 'team_member' );
    remove_role( 'mobile_user' );
}

if ( is_multisite() ) {
    foreach ( get_sites( [ 'fields' => 'ids' ] ) as $site_id ) {
        switch_to_blog( $site_id );
        timegrow_uninstall_site();
        restore_current_blog();
    }
} else {
    timegrow_uninstall_site();
}
