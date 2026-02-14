<?php
/**
 * Plugin Name: Aragrow - TimeGrow
 * Plugin URI: https://example.com/aragrow-timegrow
 * Description: A comprehensive time tracking plugin for managing projects, team members, invoicing with AI-powered receipt analysis, WooCommerce integration, PayPal auto-invoicing, and REST API endpoints.
 * Version: 2.1.0
 * Author: David Arago - ARAGROW, LLC
 * Author URI: https://aragrow.me/wp-plugins/timegrow/
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// ==============================================
// CORE PLUGIN CONSTANTS
// ==============================================

defined( 'TIMEGROW' ) or define( 'TIMEGROW', 'TimeGrow' );
defined( 'TIMEGROW_VERSION' ) or define( 'TIMEGROW_VERSION', '2.0.0' );
defined( 'TIMEGROW_PREFIX' ) or define( 'TIMEGROW_PREFIX', strtolower(TIMEGROW). '_' );
defined( 'TIMEGROW_BASE_DIR' ) or define( 'ARAGROW_TIMEGROW_BASE_DIR', plugin_dir_path( __FILE__ ) );
defined( 'TIMEGROW_BASE_URI' ) or define( 'ARAGROW_TIMEGROW_BASE_URI', plugin_dir_url( __FILE__ ) );
defined( 'TIMEGROW_MODULES_DIR' ) or define( 'TIMEGROW_MODULES_DIR', ARAGROW_TIMEGROW_BASE_DIR.'modules/' );
defined( 'TIMEGROW_ADMIN_CAP' ) or define( 'TIMEGROW_ADMIN_CAP', 'timeflies_admin' );
defined( 'TIMEGROW_OWNER_CAP' ) or define( 'TIMEGROW_OWNER_CAP', 'timeflies_owner' );
defined( 'TIMEGROW_TEAM_MEMBER_CAP' ) or define( 'TIMEGROW_TEAM_MEMBER_CAP', 'timeflies_team_member' );
defined( 'TIMEGROW_PARENT_MENU' ) or define( 'TIMEGROW_PARENT_MENU', 'timegrow' );
defined( 'TIMEGROW_TEAM_MEMBER_MENU' ) or define( 'TIMEGROW_TEAM_MEMBER_MENU', TIMEGROW_PARENT_MENU.'-team-member' );

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

// ==============================================
// PLUGIN ACTIVATION & CAPABILITIES
// ==============================================

register_activation_hook(__FILE__, 'timegrow_plugin_activate');

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
        'manage_expense_categories',         // Manage Expense Categories
    ];

    // Team member reports (individual use)
    $team_member_reports = [
        'view_yearly_tax_report',            // Yearly Tax Report
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
    }
}

// Ensure capabilities are registered on every admin init (for existing installs)
add_action('admin_init', function() {
    // Check if capabilities have been registered
    $admin = get_role('administrator');
    if ($admin && (!$admin->has_cap('view_client_activity_report') || !$admin->has_cap('view_monthly_profit_loss') || !$admin->has_cap('manage_expense_categories'))) {
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

        // Management Capabilities - Admin Only
        'manage_expense_categories',

        // Report Capabilities - Team Member & Admin
        'view_yearly_tax_report',
        'view_my_detailed_time_log',
        'view_my_hours_by_project',
        'view_my_expenses_report',
    ];

    return $plugin_caps;
}
