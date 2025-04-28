<?
/**
 * Plugin Name: Aragrow - Timegrow - Nexus Custom Backend
 * Description: Adds custom REST API endpoints for Nexus app.
 * Version: 1.0
 * Author: Your Name
 */

// Include your custom endpoint class file
require_once plugin_dir_path(__FILE__) . 'includes/routes/class-nexus-custom-endpoints.php';

// Register and run the endpoint class
function timegrow_run_nexus_custom_endpoints() {
    $plugin = new Nexus_Custom_Endpoints();
    $plugin->run();
}
add_action( 'plugins_loaded', 'timegrow_run_nexus_custom_endpoints' );



// Allow CORS for development
// WARNING: Using '*' is INSECURE for production. Restrict to specific origins.
// Add the REST API init action here, or inside the class if preferred
add_action( 'rest_api_init', array( 'Nexus_Custom_Endpoints', 'register_routes' ), 10 );
add_action( 'rest_api_init', 'aragrow_timegrow_nexus_allow_cors', 15 );


function aragrow_timegrow_nexus_allow_cors() {
    // Fixes error Error: NetworkError when attempting to fetch resource in the front end.
    // When your React app (e.g., http://localhost:5173) tries to fetch data from your WordPress API (e.g., http://localhot:999/wp-json/...), 
    //      the browser blocks the request by default because the origins are different.
    // Define the allowed origin (your React app's URL during development)
    // Replace 'http://localhost:5173' with the actual URL Vite is using
    $allowed_origin = '*';

    // Add the access control headers
    header( 'Access-Control-Allow-Origin: ' . $allowed_origin );
    header( 'Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS' );
    header( 'Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With' );
    header( 'Access-Control-Allow-Credentials: true' ); // If you need to send cookies/auth headers

    // Handle preflight OPTIONS requests
    if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
        status_header( 200 );
        exit();
    }
} // Use a priority later than default to ensure it runs after REST API init


?>