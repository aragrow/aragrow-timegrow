<?php
/**
 * Plugin Name: Aragrow - TimeGrow
 * Plugin URI: https://example.com/aragrow-timegrow
 * Description: A time tracking plugin for managing projects, team members, and invoicing with AI-powered receipt analysis.
 * Version: 1.1.2
 * Author: David Arago - ARAGROW, LLC
 * Author URI: https://aragrow.me/wp-plugins/timegrow/
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define a constant for the plugin's base directory. This makes the code more readable and easier to maintain.
defined( 'TIMEGROW' ) or define( 'TIMEGROW', 'TimeGrow' );
defined( 'TIMEGROW_PREFIX' ) or define( 'TIMEGROW_PREFIX', strtolower(TIMEGROW). '_' );
defined( 'TIMEGROW_BASE_DIR' ) or define( 'ARAGROW_TIMEGROW_BASE_DIR', plugin_dir_path( __FILE__ ) );
defined( 'TIMEGROW_BASE_URI' ) or define( 'ARAGROW_TIMEGROW_BASE_URI', plugin_dir_url( __FILE__ ) );
defined( 'TIMEGROW_INCLUDES_DIR' ) or define( 'TIMEGROW_INCLUDES_DIR', ARAGROW_TIMEGROW_BASE_DIR.'includes/' );
defined( 'TIMEGROW_SCREENS_DIR' ) or define( 'ARAGROW_TIMEGROW_SCREENS_DIR', TIMEGROW_INCLUDES_DIR.'screens/' );
defined( 'TIMEGROW_ADMIN_CAP' ) or define( 'TIMEGROW_ADMIN_CAP', 'timeflies_admin' );
defined( 'TIMEGROW_OWNER_CAP' ) or define( 'TIMEGROW_OWNER_CAP', 'timeflies_owner' );
defined( 'TIMEGROW_TEAM_MEMBER_CAP' ) or define( 'TIMEGROW_TEAM_MEMBER_CAP', 'timeflies_team_member' );
defined( 'TIMEGROW_PARENT_MENU' ) or define( 'TIMEGROW_PARENT_MENU', 'timegrow' );
defined( 'TIMEGROW_TEAM_MEMBER_MENU' ) or define( 'TIMEGROW_TEAM_MEMBER_MENU', TIMEGROW_PARENT_MENU.'-team-member' );

require_once TIMEGROW_INCLUDES_DIR . 'admin-menu.php';
require_once TIMEGROW_INCLUDES_DIR . 'TimeGrowAjaxHandler.php';

// Autoload classes
function timegrow_load_mvc_classes($class) {

    // Check if the class name starts with "timegrow"
    if (strpos($class, 'TimeGrow') !== 0) return; // Exit the function, don't load the class
    error_log(  'timegrow_load_mvc_classes'. ' - Class: ' . $class );  //Best option for Classes
    //error_log(TIMEGROW_INCLUDES_DIR . $class . '.php' );
    if (file_exists( TIMEGROW_INCLUDES_DIR . $class . '.php' ) ) {
        //error_log(1);
        require_once TIMEGROW_INCLUDES_DIR . $class . '.php';
    } elseif ( file_exists( TIMEGROW_INCLUDES_DIR . 'models/' . $class . '.php' ) ) {
        //error_log(2);
        require_once TIMEGROW_INCLUDES_DIR . 'models/' . $class . '.php';
    } elseif ( file_exists( TIMEGROW_INCLUDES_DIR . 'views/' . $class . '.php' ) ) {
        //error_log(3);
        require_once TIMEGROW_INCLUDES_DIR . 'views/' . $class . '.php';
    } elseif ( file_exists( TIMEGROW_INCLUDES_DIR . 'controllers/' . $class . '.php' ) ) {
       // error_log(4);
        require_once TIMEGROW_INCLUDES_DIR . 'controllers/' . $class . '.php';
    } elseif ( file_exists( TIMEGROW_INCLUDES_DIR . 'helpers/' . $class . '.php' ) ) {
        //error_log(5);
        require_once TIMEGROW_INCLUDES_DIR . 'helpers/' . $class . '.php';
    }
}

spl_autoload_register( 'timegrow_load_mvc_classes' );

// Function to initialize the plugin
if ( ! isset( $timegrow_integrations) ) $timegrow_integration = New TimeGrowIntegration();
if ( ! isset( $timegrow_company ) ) $timegrow_company = New TimeGrowCompany();
if ( ! isset( $timegrow_client ) ) $timegrow_client = New TimeGrowClient();
if ( ! isset( $timegrow_project ) ) $timegrow_project = New TimeGrowProject();
if ( ! isset( $timegrow_expense ) ) $timegrow_expense = New TimeGrowExpense();
if ( ! isset( $timegrow_time_entry ) ) $timegrow_time_entry = New TimeGrowTimeEntry();
if ( ! isset( $timegrow_team_member ) ) $timegrow_team_member = New TimeGrowTeamMember();
if ( ! isset( $timegrow_ajax_handler ) ) $timegrow_ajax_handler = New TimeGrow_Ajax_Handler();
if ( ! isset( $timegrow_report ) ) $timegrow_report = New TimeGrowReport();
if ( ! isset( $timegrow_settings ) ) $timegrow_settings = New TimeGrowSettings();

register_activation_hook(__FILE__, 'timegrow_plugin_activate');

function timegrow_plugin_activate() {
    // Include the WordPress upgrade file to use dbDelta()
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    // Instantiate the plugin class.
    (new TimeGrowClientModel())->initialize();
    (new TimeGrowCompanyModel())->initialize();
    (new TimeGrowProjectModel())->initialize();
    (new TimeGrowExpenseModel())->initialize();
    (new TimeGrowExpenseReceiptModel())->initialize();
    (new TimeGrowTeamMemberModel())->initialize();
    (new TimeGrowTimeEntryModel())->initialize();

    // Register custom capabilities for reports
    timegrow_register_report_capabilities();
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
    if ($admin && !$admin->has_cap('view_client_activity_report')) {
        // Capabilities not registered yet, register them now
        timegrow_register_report_capabilities();
    }
});

// Enqueue scripts on admin pages
add_action('admin_enqueue_scripts', function() {
    $ajax_handler = new TimeGrow_Ajax_Handler();
    $ajax_handler->enqueue_ajax_scripts();
});

// Enqueue scripts on frontend (if needed)
//add_action('wp_enqueue_scripts', function() {
//    $ajax_handler = new TimeGrow_Ajax_Handler();
//    $ajax_handler->enqueue_ajax_scripts();
//});

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

        // Report Capabilities - Team Member & Admin
        'view_yearly_tax_report',
        'view_my_detailed_time_log',
        'view_my_hours_by_project',
        'view_my_expenses_report',
    ];

    return $plugin_caps;
}