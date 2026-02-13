<?php
/**
 * TimeGrow Core Module
 *
 * Core time tracking functionality including:
 * - Companies, Clients, Projects, Team Members
 * - Time Entries (Manual & Clock In/Out)
 * - Expenses with AI-powered receipt analysis
 * - Reports and Settings
 *
 * @package TimeGrow
 * @subpackage Core
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Core Module Constants
defined( 'TIMEGROW_CORE_BASE_DIR' ) or define( 'TIMEGROW_CORE_BASE_DIR', plugin_dir_path( __FILE__ ) );
defined( 'TIMEGROW_CORE_BASE_URI' ) or define( 'TIMEGROW_CORE_BASE_URI', plugin_dir_url( __FILE__ ) );
defined( 'TIMEGROW_CORE_INCLUDES_DIR' ) or define( 'TIMEGROW_CORE_INCLUDES_DIR', TIMEGROW_CORE_BASE_DIR.'includes/' );

// Load admin menu and AJAX handler
require_once TIMEGROW_CORE_INCLUDES_DIR . 'admin-menu.php';
require_once TIMEGROW_CORE_INCLUDES_DIR . 'TimeGrowAjaxHandler.php';

// Autoload classes
function timegrow_core_load_mvc_classes($class) {
    // Check if the class name starts with "TimeGrow"
    if (strpos($class, 'TimeGrow') !== 0) return;

    if(WP_DEBUG) error_log('timegrow_core_load_mvc_classes - Class: ' . $class);

    if (file_exists( TIMEGROW_CORE_INCLUDES_DIR . $class . '.php' ) ) {
        require_once TIMEGROW_CORE_INCLUDES_DIR . $class . '.php';
    } elseif ( file_exists( TIMEGROW_CORE_INCLUDES_DIR . 'models/' . $class . '.php' ) ) {
        require_once TIMEGROW_CORE_INCLUDES_DIR . 'models/' . $class . '.php';
    } elseif ( file_exists( TIMEGROW_CORE_INCLUDES_DIR . 'views/' . $class . '.php' ) ) {
        require_once TIMEGROW_CORE_INCLUDES_DIR . 'views/' . $class . '.php';
    } elseif ( file_exists( TIMEGROW_CORE_INCLUDES_DIR . 'controllers/' . $class . '.php' ) ) {
        require_once TIMEGROW_CORE_INCLUDES_DIR . 'controllers/' . $class . '.php';
    } elseif ( file_exists( TIMEGROW_CORE_INCLUDES_DIR . 'helpers/' . $class . '.php' ) ) {
        require_once TIMEGROW_CORE_INCLUDES_DIR . 'helpers/' . $class . '.php';
    }
}

spl_autoload_register( 'timegrow_core_load_mvc_classes' );

// Initialize core components
if ( ! isset( $timegrow_integrations) ) $timegrow_integration = New TimeGrowIntegration();
if ( ! isset( $timegrow_company ) ) $timegrow_company = New TimeGrowCompany();
if ( ! isset( $timegrow_client ) ) $timegrow_client = New TimeGrowClient();
if ( ! isset( $timegrow_project ) ) $timegrow_project = New TimeGrowProject();
if ( ! isset( $timegrow_expense ) ) $timegrow_expense = New TimeGrowExpense();
if ( ! isset( $timegrow_expense_category ) ) $timegrow_expense_category = New TimeGrowExpenseCategory();
if ( ! isset( $timegrow_time_entry ) ) $timegrow_time_entry = New TimeGrowTimeEntry();
if ( ! isset( $timegrow_team_member ) ) $timegrow_team_member = New TimeGrowTeamMember();
if ( ! isset( $timegrow_ajax_handler ) ) $timegrow_ajax_handler = New TimeGrow_Ajax_Handler();
if ( ! isset( $timegrow_report ) ) $timegrow_report = New TimeGrowReport();
if ( ! isset( $timegrow_settings ) ) $timegrow_settings = New TimeGrowSettings();

// Enqueue scripts on admin pages
add_action('admin_enqueue_scripts', function() {
    $ajax_handler = new TimeGrow_Ajax_Handler();
    $ajax_handler->enqueue_ajax_scripts();
});

/**
 * Core module activation tasks
 */
function timegrow_core_module_activate() {
    // Include the WordPress upgrade file to use dbDelta()
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

    // Initialize database tables
    (new TimeGrowClientModel())->initialize();
    (new TimeGrowCompanyModel())->initialize();
    (new TimeGrowProjectModel())->initialize();
    (new TimeGrowExpenseModel())->initialize();
    (new TimeGrowExpenseReceiptModel())->initialize();
    (new TimeGrowTeamMemberModel())->initialize();
    (new TimeGrowTimeEntryModel())->initialize();

    // Initialize expense categories and populate with defaults
    $expense_category_model = new TimeGrowExpenseCategoryModel();
    $expense_category_model->initialize();
    $expense_category_model->populate_default_categories();
}

// Hook activation into main plugin activation
add_action('timegrow_activate', 'timegrow_core_module_activate');
