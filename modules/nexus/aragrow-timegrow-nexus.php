<?php
/**
 * TimeGrow Nexus Module
 *
 * Custom REST API endpoints for the Nx-LCARS app.
 *
 * @package TimeGrow
 * @subpackage Nexus
 * @version 1.2.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Constants are defined in main plugin file, but provide fallback if module is loaded standalone
if (!defined('TIMEGROW_NEXUS_BASE_DIR')) {
    define( 'TIMEGROW_NEXUS_BASE_DIR', plugin_dir_path( __FILE__ ) );
    define( 'TIMEGROW_NEXUS_BASE_URI', plugin_dir_url( __FILE__ ) );
    define( 'TIMEGROW_NEXUS_INCLUDES_DIR', TIMEGROW_NEXUS_BASE_DIR.'includes/' );
}

// Debug: Log the paths (helpful for troubleshooting CSS/JS loading issues)
if (WP_DEBUG) {
    error_log('TIMEGROW_NEXUS_BASE_DIR: ' . TIMEGROW_NEXUS_BASE_DIR);
    error_log('TIMEGROW_NEXUS_BASE_URI: ' . TIMEGROW_NEXUS_BASE_URI);
}

// Include your custom endpoint class file
require_once plugin_dir_path(__FILE__) . 'includes/routes/class-nexus-custom-endpoints.php';

/**
 * Registers and initializes the custom endpoints for the Aragrow Timegrow Nexus plugin.
 *
 * This function is responsible for setting up the necessary endpoints
 * to handle custom API requests within the Aragrow Timegrow Nexus plugin.
 *
 * @return void
 */
function aragrow_timegrow_nexus_allow_cors() {
    error_log(__CLASS__.'::'.__FUNCTION__);
    if (isset($_SERVER['HTTP_ORIGIN']) && strpos($_SERVER['HTTP_ORIGIN'], 'localhost:5173') !== false) {
        header('Access-Control-Allow-Origin: http://localhost:5173');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE, PATCH');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
    }

    // Handle OPTIONS requests immediately
    if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
        status_header(200);
        exit();
    }
}
add_action('init', 'aragrow_timegrow_nexus_allow_cors', 15);


/**
 * Preflight handler for REST API requests in the Aragrow Timegrow Nexus plugin.
 *
 * This function is triggered before REST API requests are processed. It can be used
 * to perform custom logic, such as debugging, modifying requests, or short-circuiting
 * the request handling process.
 *
 * @param mixed           $result   Response object or null. Return non-null to short-circuit.
 * @param WP_REST_Server  $server   Server instance.
 * @param WP_REST_Request $request  Request instance.
 * @return mixed Original $result or null to continue processing.
 */
function aragrow_timegrow_nexus_rest_preflight($served, $result, $request, $server) {
    error_log(__CLASS__.'::'.__FUNCTION__);
    if (isset($_SERVER['HTTP_ORIGIN']) && strpos($_SERVER['HTTP_ORIGIN'], 'localhost:5173') !== false) {
        header('Access-Control-Allow-Origin: http://localhost:5173');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE, PATCH');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With');
    }
    return $served;
}
add_action('rest_api_init', function() {
    add_filter('rest_pre_serve_request', 'aragrow_timegrow_nexus_rest_preflight', 10, 4);
});
// Hook the register_routes method to the rest_api_init action
add_action('rest_api_init', ['Nexus_Custom_Endpoints', 'register_routes']);
/**
 * Modifies the response for the "users/me" endpoint in the WordPress REST API.
 *
 * This function customizes the data returned for the current user by adding or
 * modifying specific fields in the response. It is typically used to include
 * additional user-related information or to adjust the default behavior of the
 * REST API response.
 *
 * @param WP_REST_Response $response The original response object for the "users/me" endpoint.
 * @param WP_User          $user     The current user object.
 * @param WP_REST_Request  $request  The request object associated with the "users/me" endpoint.
 *
 * @return WP_REST_Response The modified response object.
 */
function aragrow_timegrow_nexus_modify_users_me_response( $response, $user, $request ) {
    error_log(__CLASS__.'::'.__FUNCTION__);
    // Check if it's the 'me' context or if you want to modify all user responses
    // The '/me' endpoint internally uses the 'edit' context for permissions,
    // but you might want to check the route specifically if needed.
    // Example: Check if the requested route contains '/users/me'
    $route = $request->get_route();
    if ( strpos( $route, '/users/me' ) === false ) {
        return $response; // Only modify /users/me
    }

    //$data = $response->get_data();

    // --- Example Modifications ---

    // Add a custom field
    //$data['my_custom_field'] = 'Some extra user data for user ID ' . $user->ID;

    // Remove a field (e.g., description)
    // unset( $data['description'] );

    // Modify an existing field (Use with caution!)
    // if ( isset( $data['name'] ) ) {
    //     $data['name'] = strtoupper( $data['name'] );
    // }

    // --- End Modifications ---

    //$response->set_data( $data );
    return $response;
}
// Add the filter with 3 arguments
add_filter( 'rest_prepare_user', 'aragrow_timegrow_nexus_modify_users_me_response', 10, 3 );

/**
 * Registers custom REST API permissions for the Aragrow Timegrow Nexus plugin.
 *
 * This function defines the permissions callback for custom REST API endpoints
 * used in the Aragrow Timegrow Nexus plugin. It ensures that only authorized
 * users can access the endpoints by validating their capabilities.
 *
 * @return bool True if the current user has the required permissions, false otherwise.
 */
 function aragrow_timegrow_nexus_custom_rest_permissions( $result ) {
    error_log(__CLASS__.'::'.__FUNCTION__);
     // Check if $result already indicates an error
     if ( ! empty( $result ) )  return $result;
     if ( ! isset($_SERVER['HTTP_AUTHORIZATION'])) return $result;

     preg_match( '/^Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches );   
     $token = $matches[1];
     $plugin = new Nexus_Custom_Endpoints();
     $result = $plugin->verify_and_get_jwt_details( $token );
     error_log(print_r($result, true));
     if( $result == false ) {
        return new WP_Error(
            'rest_imformation',
            $result,
            array( 'status' => 403 ) // Forbidden
        );
     }
 
     // If no error, return the original $result (likely null)
     return $result;
}
//add_filter( 'rest_authentication_errors', 'aragrow_timegrow_nexus_custom_rest_permissions' );

/**
 * Adds a custom field to the user profile editor screen.
 *
 * This function outputs the HTML for the custom field.
 * It's hooked into 'show_user_profile' (for user's own profile)
 * and 'edit_user_profile' (for admin editing another user).
 *
 * @param WP_User $user The user object being edited.
 */
function my_plugin_add_custom_user_profile_fields( $user ) {
    // Only show this field to users who can edit users (typically Admins)
    // Or if the user is editing their own profile (though you might restrict that too).
    // Adjust capability check as needed. For simplicity, let's allow admins editing anyone,
    // and users editing their own profile IF they have a basic capability like 'read'.
    if ( ! current_user_can( 'edit_users' ) && get_current_user_id() !== $user->ID ) {
         // return; // Uncomment this line if only admins should ever see/edit this
    }
     if ( get_current_user_id() === $user->ID && ! current_user_can('read') ) {
          return; // Don't show if user somehow can't even read their own profile
     }


    // Get the current value of the meta field for this user
    $can_access = get_user_meta( $user->ID, 'can_access_users_me_api', true );
    // Set default to false (0) if it doesn't exist or is empty
    $is_checked = ! empty( $can_access ) && $can_access == '1';

    ?>
    <h3><?php _e( 'API Access Settings', 'your-plugin-textdomain' ); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="can_access_users_me_api"><?php _e( 'Allow /users/me Access', 'your-plugin-textdomain' ); ?></label></th>
            <td>
                <input
                    type="checkbox"
                    name="can_access_users_me_api"
                    id="can_access_users_me_api"
                    value="1" <?php /* Value when checked */ ?>
                    <?php checked( $is_checked ); /* WordPress helper to output 'checked="checked"' if true */ ?>
                />
                <span class="description"><?php _e( 'Allow this user to authenticate and access their data via the /wp/v2/users/me REST API endpoint using methods like JWT.', 'your-plugin-textdomain' ); ?></span>
            </td>
        </tr>
    </table>
    <?php
    // Note: WordPress automatically includes a nonce in the user profile form.
    // We'll verify it during the save process.
}
// Add the field to user's own profile page
//add_action( 'show_user_profile', 'my_plugin_add_custom_user_profile_fields' );
// Add the field to admin editing another user's profile page
//add_action( 'edit_user_profile', 'my_plugin_add_custom_user_profile_fields' );


/**
 * Saves the custom field data when the user profile is updated.
 *
 * This function checks for the submitted value and updates the user meta.
 * It's hooked into 'personal_options_update' (for user's own profile)
 * and 'edit_user_profile_update' (for admin editing another user).
 *
 * @param int $user_id The ID of the user being updated.
 */
function my_plugin_save_custom_user_profile_fields( $user_id ) {

    // --- Security Checks ---
    // 1. Verify if the current user has permission to edit this user.
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false; // Exit if the current user doesn't have permission
    }

    // 2. Verify the nonce that WordPress includes in the form.
    // The action name depends on the context (profile.php vs user-edit.php)
    // but check_admin_referer handles variations if the field name matches.
    // Let's use the standard action for user editing.
    check_admin_referer( 'update-user_' . $user_id );
    // --- End Security Checks ---


    // Check if our checkbox was submitted
    // If checked, $_POST['can_access_users_me_api'] will be '1' (based on the value attribute).
    // If *not* checked, $_POST['can_access_users_me_api'] will *not* be set at all.
    $new_value = isset( $_POST['can_access_users_me_api'] ) && $_POST['can_access_users_me_api'] == '1' ? '1' : '0';

    // Update the user meta field. If the new value is '0', you could also
    // use delete_user_meta() but update_user_meta handles adding/updating/deleting implicitly
    // if the value is different or empty-like. Saving '0' is explicit.
    update_user_meta( $user_id, 'can_access_users_me_api', $new_value );

}
// Save the field when user updates their own profile
//add_action( 'personal_options_update', 'my_plugin_save_custom_user_profile_fields' );
// Save the field when admin updates another user's profile
//add_action( 'edit_user_profile_update', 'my_plugin_save_custom_user_profile_fields' );


/**
 * Intercepts REST API requests *before* the main callback runs
 * and dumps request data specifically for the /wp/v2/users/me route.
 *
 * WARNING: For debugging purposes ONLY. Remove or comment out in production.
 * Output goes to the PHP error log (check wp-content/debug.log if WP_DEBUG_LOG is enabled).
 *
 * @param mixed           $result   Response object or null. Return non-null to short-circuit.
 * @param WP_REST_Server  $server   Server instance.
 * @param WP_REST_Request $request  Request instance.
 * @return mixed Original $result or null to continue processing.
 */
function my_debug_dump_users_me_request( $result, $server, $request ) {

    // Check if the current route matches the one we want to inspect
    if ( $request->get_route() === '/wp/v2/users/me' ) {

        error_log(print_r($request, true));
        error_log(print_r($result, true));
        // Prepare data to dump
        $request_data_to_dump = array(
            'DEBUG_INFO'      => 'Dumping data for /wp/v2/users/me',
            'REQUEST_TIME'    => date('Y-m-d H:i:s'),
            'ROUTE'           => $request->get_route(),
            'METHOD'          => $request->get_method(),
            'HEADERS'         => $request->get_headers(), // Includes Authorization header if sent
            'QUERY_PARAMS'    => $request->get_query_params(), // Data from URL query string (?...)
            'BODY_PARAMS'     => $request->get_body_params(), // Parsed data from request body (POST/PUT form data or JSON)
            'BODY_RAW'        => $request->get_body(), // Raw request body content
            'ATTRIBUTES'      => $request->get_attributes(), // Route definition attributes
            // Optional: Current User Info (might be 0 if authentication hasn't fully run or failed)
            'CURRENT_USER_ID' => get_current_user_id(),
            'SERVER_VARS'     => array( // Include specific potentially relevant $_SERVER vars
                 'REQUEST_URI' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null,
                 'REMOTE_ADDR' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
                 'HTTP_AUTHORIZATION' => isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '(Not set directly)', // Double check where auth header is
                 'REDIRECT_HTTP_AUTHORIZATION' => isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : '(Not set directly)',
             ),
        );

        // Dump the data to the PHP error log
        // Use print_r with the second argument true to capture the output
       // error_log( "--- WP REST API Request Dump --- \n" . print_r( $request_data_to_dump, true ) . "\n--- End Dump ---" );

        // Optional: Dump specific parts if the above is too much
        // error_log( 'Authorization Header Found: ' . print_r( $request->get_header('Authorization'), true ) );
    }

    // IMPORTANT: Return the original $result (usually null) to allow the request to continue normally.
    // Returning anything else (like WP_Error or a WP_REST_Response) would hijack the request.
    return $result;
}

// Hook into rest_pre_dispatch with priority 10 and 3 arguments
add_filter( 'rest_pre_dispatch', 'my_debug_dump_users_me_request', 10, 3 );

// (Optional) If your Nexus_Custom_Endpoints class is already registering routes, you **don't** need this line separately:
//add_action('rest_api_init', array('Nexus_Custom_Endpoints', 'register_routes'), 10);

// Autoload classes
function timegrow_nexus_load_mvc_classes($class) {

   // $timegrow_includes = $GLOBALS['TIMEGROW_INCLUDES_DIR'];

    // Check if the class name starts with "timegrow"
    if (strpos($class, 'TimeGrowNexus') !== 0) return; // Exit the function, don't load the class


    $timegrow_includes = plugin_dir_path(__FILE__) . '../aragrow-timegrow/includes/';

    error_log(  'timegrow_nexus_load_mvc_classes'. ' - Class: ' . $class );  //Best option for Classes
    //error_log(TIMEGROW_INCLUDES_DIR . $class . '.php' );
    if (file_exists( TIMEGROW_NEXUS_INCLUDES_DIR . $class . '.php' ) ) {
        //error_log(1);
        require_once TIMEGROW_NEXUS_INCLUDES_DIR . $class . '.php';
    } elseif ( file_exists( TIMEGROW_NEXUS_INCLUDES_DIR . 'controllers/' . $class . '.php' ) ) {
        //error_log(2);
        require_once TIMEGROW_NEXUS_INCLUDES_DIR . 'controllers/' . $class . '.php';
    }elseif ( file_exists( TIMEGROW_NEXUS_INCLUDES_DIR . 'models/' . $class . '.php' ) ) {
        //error_log(2);
        require_once TIMEGROW_NEXUS_INCLUDES_DIR . 'models/' . $class . '.php';
    } elseif ( file_exists( TIMEGROW_NEXUS_INCLUDES_DIR . 'views/' . $class . '.php' ) ) {
        //error_log(3);
        require_once TIMEGROW_NEXUS_INCLUDES_DIR . 'views/' . $class . '.php';
    } 
    // Check if the class is from Timegrow
    elseif ( file_exists( $timegrow_includes . 'controllers/' . $class . '.php' ) ) {
       // error_log(4);
        require_once $timegrow_includes . 'controllers/' . $class . '.php';
    } elseif (file_exists( $timegrow_includes . $class . '.php' ) ) {
        //error_log(1);
        require_once $timegrow_includes . $class . '.php';
    } elseif ( file_exists( $timegrow_includes. 'models/' . $class . '.php' ) ) {
        //error_log(2);
        require_once $timegrow_includes . 'models/' . $class . '.php';
    } elseif ( file_exists( $timegrow_includes . 'views/' . $class . '.php' ) ) {
        //error_log(3);
        require_once $timegrow_includes . 'views/' . $class . '.php';
    } elseif ( file_exists( $timegrow_includes . 'controllers/' . $class . '.php' ) ) {
       // error_log(4);
        require_once $timegrow_includes . 'controllers/' . $class . '.php';
    }

}

spl_autoload_register( 'timegrow_nexus_load_mvc_classes' );

if ( ! isset( $timegrow_nexus ) ) $timegrow_nexus = New TimeGrowNexus();

// Register activation hook
register_activation_hook(__FILE__, 'timegrow_nexus_plugin_activate');

function timegrow_nexus_plugin_activate() {
    // Register custom capabilities for Nexus features
    timegrow_nexus_register_capabilities();
}

/**
 * Register custom capabilities for TimeGrow Nexus features
 */
function timegrow_nexus_register_capabilities() {
    // Get roles
    $admin_role = get_role('administrator');
    $team_member_role = get_role('team_member');

    // Create team_member role if it doesn't exist
    if (!$team_member_role) {
        $team_member_role = add_role('team_member', 'Team Member', [
            'read' => true,
        ]);
    }

    // Define Nexus feature capabilities (organized by function)
    // Time Tracking capabilities
    $time_tracking_caps = [
        'access_nexus_clock',              // Clock In / Out
        'access_nexus_manual_entry',       // Manual Time Entry
    ];

    // Expense Management capabilities
    $expense_management_caps = [
        'access_nexus_record_expenses',    // Record Expenses
    ];

    // Reporting capabilities
    $reporting_caps = [
        'access_nexus_view_reports',       // View Reports
    ];

    // Administration capabilities (admin only)
    $admin_only_caps = [
        'access_nexus_settings',           // Settings
        'access_nexus_process_time',       // Process Time (WooCommerce integration)
        'access_nexus_dashboard',          // Nexus Dashboard access
    ];

    // Team member gets time tracking, expenses, and reports
    $team_member_caps = array_merge(
        $time_tracking_caps,
        $expense_management_caps,
        $reporting_caps,
        ['access_nexus_dashboard']  // Team members can access dashboard
    );

    // Admin gets everything
    $admin_caps = array_merge(
        $time_tracking_caps,
        $expense_management_caps,
        $reporting_caps,
        $admin_only_caps
    );

    // Add capabilities to administrator
    if ($admin_role) {
        foreach ($admin_caps as $cap) {
            $admin_role->add_cap($cap);
        }
    }

    // Add capabilities to team_member role
    if ($team_member_role) {
        foreach ($team_member_caps as $cap) {
            $team_member_role->add_cap($cap);
        }
    }
}

// Ensure capabilities are registered on every admin init (for existing installs)
add_action('admin_init', function() {
    // Check if capabilities have been registered
    $admin = get_role('administrator');
    if ($admin && !$admin->has_cap('access_nexus_dashboard')) {
        // Capabilities not registered yet, register them now
        timegrow_nexus_register_capabilities();
    }
}, 11); // Priority 11 to run after TimeGrow's capability registration

/**
 * Register TimeGrow Nexus capabilities with PublishPress Capabilities
 * This groups all Nexus capabilities together in the capabilities manager
 */
add_filter('cme_plugin_capabilities', 'timegrow_nexus_publishpress_capabilities');

function timegrow_nexus_publishpress_capabilities($plugin_caps) {
    $plugin_caps['TimeGrow Nexus'] = [
        // Time Tracking Capabilities
        'access_nexus_clock',
        'access_nexus_manual_entry',

        // Expense Management Capabilities
        'access_nexus_record_expenses',

        // Reporting Capabilities
        'access_nexus_view_reports',

        // Administration Capabilities (Admin Only)
        'access_nexus_settings',
        'access_nexus_process_time',
        'access_nexus_dashboard',
    ];

    return $plugin_caps;
}

?>