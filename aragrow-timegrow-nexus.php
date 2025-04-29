<?php
/**
 * Plugin Name: Aragrow - Timegrow - Nexus Custom Backend
 * Description: Adds custom REST API endpoints for the Nexus app.
 * Version: 1.0
 * Author: Your Name
 */

// Include your custom endpoint class file
require_once plugin_dir_path(__FILE__) . 'includes/routes/class-nexus-custom-endpoints.php';

// Register and run the endpoint class
function timegrow_run_nexus_custom_endpoints() {
    error_log(__CLASS__.'::'.__FUNCTION__);
    $plugin = new Nexus_Custom_Endpoints();
    $plugin->run();
    // Properly hook route registration here
    add_action('rest_api_init', [$plugin, 'register_routes']);
}
add_action('plugins_loaded', 'timegrow_run_nexus_custom_endpoints');

// Allow CORS for local development
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

// Handle preflight requests (for REST API)
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

// (Optional) If your Nexus_Custom_Endpoints class is already registering routes, you **don't** need this line separately:
// add_action('rest_api_init', array('Nexus_Custom_Endpoints', 'register_routes'), 10);

?>
