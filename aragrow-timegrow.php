<?php
/**
 * Plugin Name: Aragrow - TimeGrow
 * Plugin URI: https://aragrow.me/wp-plugins/timegrow/
 * Description: A comprehensive time tracking plugin for managing projects, team members, invoicing with AI-powered receipt analysis, WooCommerce integration, PayPal auto-invoicing, and REST API endpoints.
 * Version: 2.2.0
 * Author: David Arago - ARAGROW, LLC
 * Author URI: https://aragrow.me/wp-plugins/timegrow/
 * License: GPL2
 * Text Domain: timegrow
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// ==============================================
// CORE PLUGIN CONSTANTS
// ==============================================

defined( 'TIMEGROW' ) or define( 'TIMEGROW', 'TimeGrow' );
defined( 'TIMEGROW_VERSION' ) or define( 'TIMEGROW_VERSION', '2.2.0' );
defined( 'TIMEGROW_PREFIX' ) or define( 'TIMEGROW_PREFIX', strtolower(TIMEGROW). '_' );
defined( 'TIMEGROW_BASE_DIR' ) or define( 'ARAGROW_TIMEGROW_BASE_DIR', plugin_dir_path( __FILE__ ) );
defined( 'TIMEGROW_BASE_URI' ) or define( 'ARAGROW_TIMEGROW_BASE_URI', plugin_dir_url( __FILE__ ) );
defined( 'TIMEGROW_MODULES_DIR' ) or define( 'TIMEGROW_MODULES_DIR', ARAGROW_TIMEGROW_BASE_DIR.'modules/' );
defined( 'TIMEGROW_ADMIN_CAP' ) or define( 'TIMEGROW_ADMIN_CAP', 'timeflies_admin' );
defined( 'TIMEGROW_OWNER_CAP' ) or define( 'TIMEGROW_OWNER_CAP', 'timeflies_owner' );
defined( 'TIMEGROW_TEAM_MEMBER_CAP' ) or define( 'TIMEGROW_TEAM_MEMBER_CAP', 'timeflies_team_member' );
defined( 'TIMEGROW_PARENT_MENU' ) or define( 'TIMEGROW_PARENT_MENU', 'timegrow' );
defined( 'TIMEGROW_TEAM_MEMBER_MENU' ) or define( 'TIMEGROW_TEAM_MEMBER_MENU', TIMEGROW_PARENT_MENU.'-team-member' );

/**
 * TimeGrow logging, used instead of timegrow_log() everywhere in the plugin.
 * Errors (messages with "error", "fail", "exception" or "invalid") are always logged.
 * Everything else is debug detail and is only logged when TIMEGROW_DEBUG is true,
 * so WP_DEBUG alone no longer fills debug.log. To turn it on, add to wp-config.php:
 *     define( 'TIMEGROW_DEBUG', true );
 * Never pass secrets, tokens, keys or passwords to this function.
 */
/**
 * True when TimeGrow debug mode is on (TIMEGROW_DEBUG in wp-config.php).
 * Controls debug logging and the on-screen debug panels (SQL, raw data) in reports and views.
 */
if ( ! function_exists( 'timegrow_debug' ) ) {
    function timegrow_debug() {
        return defined( 'TIMEGROW_DEBUG' ) && TIMEGROW_DEBUG;
    }
}

if ( ! function_exists( 'timegrow_log' ) ) {
    function timegrow_log( $message ) {
        if ( ! is_string( $message ) ) {
            $message = print_r( $message, true );
        }
        $is_error = (bool) preg_match( '/error|fail|exception|invalid/i', $message );
        if ( ! $is_error && ! timegrow_debug() ) {
            return;
        }
        error_log( '[TimeGrow] ' . $message );
    }
}

/**
 * Company default hourly rate: the rate of the first company record (lowest ID).
 * Used instead of assuming the company row has ID 1. Returns 0 when none is set.
 */
if ( ! function_exists( 'timegrow_company_default_rate' ) ) {
    function timegrow_company_default_rate() {
        global $wpdb;
        $table = $wpdb->prefix . TIMEGROW_PREFIX . 'company_tracker';
        return (float) $wpdb->get_var( "SELECT default_flat_fee FROM {$table} ORDER BY ID LIMIT 1" );
    }
}

// Translations for the 'timegrow' text domain (languages/ folder)
add_action( 'init', function() {
    load_plugin_textdomain( 'timegrow', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
} );

// Backward compatibility constants
defined( 'TIMEGROW_INCLUDES_DIR' ) or define( 'TIMEGROW_INCLUDES_DIR', TIMEGROW_MODULES_DIR.'core/includes/' );
defined( 'TIMEGROW_SCREENS_DIR' ) or define( 'ARAGROW_TIMEGROW_SCREENS_DIR', TIMEGROW_INCLUDES_DIR.'screens/' );

// Core Module Constants (for backward compatibility)
defined( 'TIMEGROW_CORE_BASE_DIR' ) or define( 'TIMEGROW_CORE_BASE_DIR', TIMEGROW_MODULES_DIR . 'core/' );
defined( 'TIMEGROW_CORE_BASE_URI' ) or define( 'TIMEGROW_CORE_BASE_URI', ARAGROW_TIMEGROW_BASE_URI . 'modules/core/' );

// Nexus Module Constants (define early so the module file can use them)
defined( 'TIMEGROW_NEXUS_BASE_DIR' ) or define( 'TIMEGROW_NEXUS_BASE_DIR', TIMEGROW_MODULES_DIR . 'nexus/' );
defined( 'TIMEGROW_NEXUS_BASE_URI' ) or define( 'TIMEGROW_NEXUS_BASE_URI', ARAGROW_TIMEGROW_BASE_URI . 'modules/nexus/' );
defined( 'TIMEGROW_NEXUS_INCLUDES_DIR' ) or define( 'TIMEGROW_NEXUS_INCLUDES_DIR', TIMEGROW_NEXUS_BASE_DIR . 'includes/' );

// ==============================================
// LOAD MODULES
// ==============================================

/**
 * Load Core Module (Time Tracking, Projects, Team Members, Expenses)
 */
if ( file_exists( TIMEGROW_MODULES_DIR . 'core/aragrow-timegrow-core.php' ) ) {
    require_once TIMEGROW_MODULES_DIR . 'core/aragrow-timegrow-core.php';
}

/**
 * Load Nexus Module (REST API for Nx-LCARS app)
 */
if ( file_exists( TIMEGROW_MODULES_DIR . 'nexus/aragrow-timegrow-nexus.php' ) ) {
    require_once TIMEGROW_MODULES_DIR . 'nexus/aragrow-timegrow-nexus.php';
}

/**
 * Load WooCommerce Integration Module
 */
if ( file_exists( TIMEGROW_MODULES_DIR . 'woocommerce-integration/aragrow-woocommerce-intengration.php' ) ) {
    require_once TIMEGROW_MODULES_DIR . 'woocommerce-integration/aragrow-woocommerce-intengration.php';
}

/**
 * Load PayPal Auto Invoicer Module
 */
if ( file_exists( TIMEGROW_MODULES_DIR . 'paypal-invoicer/aragrow-wc-paypal-auto-invoicer.php' ) ) {
    require_once TIMEGROW_MODULES_DIR . 'paypal-invoicer/aragrow-wc-paypal-auto-invoicer.php';
}

/**
 * Load Mobile Access Module (PIN-based mobile-only access)
 */
if ( file_exists( TIMEGROW_MODULES_DIR . 'mobile/aragrow-timegrow-mobile.php' ) ) {
    require_once TIMEGROW_MODULES_DIR . 'mobile/aragrow-timegrow-mobile.php';
}

// ==============================================
// PLUGIN ACTIVATION & CAPABILITIES
// ==============================================

register_activation_hook(__FILE__, 'timegrow_plugin_activate');

// Stop the PayPal payment check when the plugin is deactivated (it is recreated on the next load)
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('timegrow_paypal_sync');
});

function timegrow_plugin_activate() {
    // Trigger core module activation
    do_action('timegrow_activate');

    // Register custom capabilities for reports
    timegrow_register_report_capabilities();

    // Trigger Nexus module activation (if available)
    if (function_exists('timegrow_nexus_plugin_activate')) {
        timegrow_nexus_plugin_activate();
    }
}

/**
 * Register custom capabilities for TimeGrow reports
 */
function timegrow_register_report_capabilities() {
    // Get roles
    $admin_role = get_role('administrator');
    $team_member_role = get_role('team_member');

    // Create team_member role if it doesn't exist
    if (!$team_member_role) {
        $team_member_role = add_role('team_member', 'Team Member', [
            'read' => true,
        ]);
    }

    // Define report capabilities
    // Admin-only reports (capability names match report titles)
    $admin_reports = [
        'view_team_hours_summary',           // Team Hours Summary
        'view_project_financials',           // Project Financials
        'view_client_activity_report',       // Client Activity Report
        'view_all_expenses_overview',        // All Expenses Overview
        'view_time_entry_audit_log',         // Time Entry Audit Log
        'view_monthly_profit_loss',          // Monthly Profit & Loss Report
        'view_unbilled_time',                // Unbilled Time (WIP)
        'view_yearly_tax_report',            // Yearly Tax Report: the whole company's income and expenses
        'manage_expense_categories',         // Manage Expense Categories
    ];

    // Team member reports (individual use)
    $team_member_reports = [
        'view_my_detailed_time_log',         // My Detailed Time Log
        'view_my_hours_by_project',          // My Hours by Project
        'view_my_expenses_report',           // My Expenses Report
    ];

    // Add all capabilities to administrator
    if ($admin_role) {
        foreach (array_merge($admin_reports, $team_member_reports) as $cap) {
            $admin_role->add_cap($cap);
        }
    }

    // Add team member capabilities to team_member role
    if ($team_member_role) {
        foreach ($team_member_reports as $cap) {
            $team_member_role->add_cap($cap);
        }
        // The Yearly Tax Report shows company-wide finances: administrators only (team members had it before)
        $team_member_role->remove_cap('view_yearly_tax_report');
    }
}

// The time entry audit trail's table (Reports > Time Entry Audit Log), and the time entry status column
add_action('admin_init', function() {
    if (class_exists('TimeGrowTimeEntryModel')) {
        TimeGrowTimeEntryModel::maybe_add_status_column();
    }
    if (class_exists('TimeGrowExpenseModel')) {
        TimeGrowExpenseModel::maybe_add_status_column();
    }
    if (class_exists('TimeGrowTimeEntryAudit')) {
        TimeGrowTimeEntryAudit::maybe_create_table();
    }
});

// Report CSV / PDF exports: sent before WordPress prints the admin page (TimeGrowReportsController::maybe_export)
add_action('admin_init', function() {
    if (class_exists('TimeGrowReportsController')) {
        TimeGrowReportsController::maybe_export();
    }
}, 20); // after the capabilities below are registered

// Ensure capabilities are registered on every admin init (for existing installs)
add_action('admin_init', function() {
    // Check if capabilities have been registered
    $admin = get_role('administrator');
    if ($admin && (!$admin->has_cap('view_client_activity_report') || !$admin->has_cap('view_monthly_profit_loss') || !$admin->has_cap('manage_expense_categories') || !$admin->has_cap('view_unbilled_time'))
        || (($tm = get_role('team_member')) && $tm->has_cap('view_yearly_tax_report'))) {
        // Capabilities not registered yet, register them now
        timegrow_register_report_capabilities();
    }
});

/**
 * Register TimeGrow capabilities with PublishPress Capabilities
 * This groups all TimeGrow capabilities together in the capabilities manager
 */
add_filter('cme_plugin_capabilities', 'timegrow_publishpress_capabilities');

function timegrow_publishpress_capabilities($plugin_caps) {
    $plugin_caps['TimeGrow'] = [
        // Report Capabilities - Admin Only
        'view_team_hours_summary',
        'view_project_financials',
        'view_client_activity_report',
        'view_all_expenses_overview',
        'view_time_entry_audit_log',
        'view_monthly_profit_loss',
        'view_unbilled_time',

        // Management Capabilities - Admin Only
        'manage_expense_categories',

        'view_yearly_tax_report',

        // Report Capabilities - Team Member & Admin
        'view_my_detailed_time_log',
        'view_my_hours_by_project',
        'view_my_expenses_report',
    ];

    return $plugin_caps;
}

/**
 * Set the admin page title for hidden TimeGrow pages.
 *
 * Submenu pages registered with a null parent leave the global $title empty,
 * which triggers a strip_tags() deprecation in wp-admin/admin-header.php.
 */
add_action( 'admin_init', function() {
    global $title, $submenu;

    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( ! empty( $title ) || strpos( $page, TIMEGROW_PARENT_MENU ) !== 0 ) {
        return;
    }

    foreach ( (array) $submenu as $items ) {
        foreach ( (array) $items as $item ) {
            if ( isset( $item[2], $item[3] ) && $item[2] === $page ) {
                $title = $item[3];
                return;
            }
        }
    }

    $title = 'TimeGrow';
} );
