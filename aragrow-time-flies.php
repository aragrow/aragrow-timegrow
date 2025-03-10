<?php
/**
 * Plugin Name: Aragrow - TimeFlies
 * Plugin URI: https://example.com/wootime-tracker
 * Description: A time tracking plugin for managing projects, team members, and invoicing.
 * Version: 1.0.0
 * Author: David Arago - ARAGROW, LLC
 * Author URI: https://aragrow.me/wp-plugins/time-flies/
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define a constant for the plugin's base directory. This makes the code more readable and easier to maintain.
defined( 'ARAGROW_TIMEFLIES_BASE_DIR' ) or define( 'ARAGROW_TIMEFLIES_BASE_DIR', plugin_dir_path( __FILE__ ) );
defined( 'ARAGROW_TIMEFLIES_BASE_URI' ) or define( 'ARAGROW_TIMEFLIES_BASE_URI', plugin_dir_url( __FILE__ ) );
defined( 'ARAGROW_TIMEFLIES_INCLUDES_DIR' ) or define( 'ARAGROW_TIMEFLIES_INCLUDES_DIR', ARAGROW_TIMEFLIES_BASE_DIR.'includes/' );
defined( 'ARAGROW_TIMEFLIES_SCREENS_DIR' ) or define( 'ARAGROW_TIMEFLIES_SCREENS_DIR', ARAGROW_TIMEFLIES_INCLUDES_DIR.'screens/' );
defined( 'TIMEFLIES_ADMIN_CAP' ) or define( 'TIMEFLIES_ADMIN_CAP', 'timeflies_admin' );
defined( 'TIMEFLIES_OWNER_CAP' ) or define( 'TIMEFLIES_OWNER_CAP', 'timeflies_owner' );
defined( 'TIMEFLIES_TEAM_MEMBER_CAP' ) or define( 'TIMEFLIES_TEAM_MEMBER_CAP', 'timeflies_team_member' );
defined( 'TIMEFLIES_PARENT_MENU' ) or define( 'TIMEFLIES_PARENT_MENU', 'timeflies' );
defined( 'TIMEFLIES_TEAM_MEMBER_MENU' ) or define( 'TIMEFLIES_TEAM_MEMBER_MENU', TIMEFLIES_PARENT_MENU.'-team-member' );

require_once ARAGROW_TIMEFLIES_INCLUDES_DIR . 'time-flies.php';
require_once ARAGROW_TIMEFLIES_INCLUDES_DIR . 'model.php';
require_once ARAGROW_TIMEFLIES_INCLUDES_DIR . 'admin-menu.php';
require_once ARAGROW_TIMEFLIES_INCLUDES_DIR . 'company-admin.php';
require_once ARAGROW_TIMEFLIES_INCLUDES_DIR . 'client-admin.php';
require_once ARAGROW_TIMEFLIES_INCLUDES_DIR . 'project-admin.php';
require_once ARAGROW_TIMEFLIES_INCLUDES_DIR . 'team-member-admin.php';
require_once ARAGROW_TIMEFLIES_INCLUDES_DIR . 'time-entry-admin.php';
require_once ARAGROW_TIMEFLIES_INCLUDES_DIR . 'team-member-clock-in-out.php';