<?php
/**
 * File: class-nexus-custom-endpoints.php
 * Description: Handles custom REST API endpoints for the Nexus plugin.
 * This file contains the class Nexus_Custom_Endpoints which defines custom routes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Handles registration and logic for custom Nexus REST API endpoints.
 *
 * This class defines the custom routes under the 'nexus/v1' namespace.
 * It includes callback methods for handling requests (GET, POST, etc.)
 * and permission check methods to ensure only authorized users can access the endpoints.
 * It interacts with the database securely using the $wpdb global object.
 */
class Nexus_Custom_Endpoints {

    // The namespace for the custom REST API endpoints.
    private $namespace = 'jwt-auth/v2';

    // The full table names with the WordPress prefix.
    private $company_table_name; // For company_tracker
    private $expense_table_name; // For expense_tracker
    private $receipt_table_name; // For expense_receipt
    private $project_table_name; // For project_tracker
    private $time_entry_table_name; // For time_entry_tracker
    private $client_table_name; // For wp_users
    private $clientmeta_table_name; // For wp_usermeta    
    private $team_member_table_name; // For team_member_tracker
    private $team_member_meta_table_name; // For team_member_meta_tracker

    /**
     * Constructor.
     * Initializes table names with the global $wpdb prefix.
     */
    public function __construct() {
        error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        // Define the table names using the WordPress prefix.
        $this->client_table_name = $wpdb->prefix . 'users';
        $this->clientmeta_table_name  = $wpdb->prefix . 'usermeta';
        $this->team_member_table_name = $wpdb->prefix . 'users';
        $this->team_member_meta_table_name= $wpdb->prefix . 'usermeta';
        $this->company_table_name = $wpdb->prefix . 'company_tracker';
        $this->expense_table_name = $wpdb->prefix . 'expense_tracker';
        $this->receipt_table_name = $wpdb->prefix . 'expense_receipt';
        $this->project_table_name = $wpdb->prefix . 'project_tracker';
        $this->time_entry_table_name = $wpdb->prefix . 'time_entry_tracker';

         // Note: The 'rest_api_init' action hook is handled in the main plugin file (nexus-backend.php)
         // by calling the static register_routes method.
    }

        /**
     * Placeholder for the run method if the class needed dynamic hooks in the constructor.
     * For this REST API class using static registration, the run method is not strictly needed
     * if the static method is hooked directly (as done in nexus-backend.php).
     */
    public function run() {
        error_log(__CLASS__.'::'.__FUNCTION__);
        // Example: add_action( 'some_other_action', array( $this, 'some_method' ) );
    }

    /**
     * Registers the custom REST API routes.
     * This method is called in the main plugin file (nexus-backend.php)
     * to register the routes when the 'rest_api_init' action is triggered.
     * This method is static to allow direct access without instantiating the class.
     * It is called in the main plugin file (nexus-backend.php) to register the routes.
     * The static method is hooked to the 'rest_api_init' action in the main plugin file.
     * This allows the routes to be registered when the REST API is initialized.
     * This is the entry point for registering all custom routes.
     * It is called in the main plugin file (nexus-backend.php) to register the routes.
     * The static method is hooked to the 'rest_api_init' action in the main plugin file.
     * This allows the routes to be registered when the REST API is initialized.
     * This is the entry point for registering all custom routes.
     * It is called in the main plugin file (nexus-backend.php) to register the routes.
     * The static method is hooked to the 'rest_api_init' action in the main plugin file.
     * This allows the routes to be registered when the REST API is initialized.
     * This is the entry point for registering all custom routes.
     * It is called in the main plugin file (nexus-backend.php) to register the routes.
     * The static method is hooked to the 'rest_api_init' action in the main plugin file.
     * This allows the routes to be registered when the REST API is initialized.
     * This is the entry point for registering all custom routes.   
    */        
    public static function register_routes() {
        error_log(__CLASS__.'::'.__FUNCTION__); 
        // Create an instance of the class to access non-static methods (like the table names and callbacks).
        $instance = new self();

        // Route: POST /auth (Login using Application Password)
        register_rest_route( $instance->namespace, '/auth', array(
            'methods'             => 'POST',
            'callback'            => array( $instance, 'handle_app_password_login' ),
            // Permission check is not needed for a public login endpoint.
            // Authentication is handled within the callback.
            'permission_callback' => '__return_true', // Allows public access, authentication happens in callback
            'args'                => array(
                'username' => array( // Username or email
                    'validate_callback' => function($value){ return !empty($value) && is_string($value); },
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field', // Sanitize input
                ),
                'application_password' => array( // The Application Password
                    'validate_callback' => function($value){ return !empty($value) && is_string($value); },
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field', // Sanitize input
                ),
            ),
        ));

        /**
         * Registers a custom REST API route for retrieving the current authenticated user details.
         *
         * This function uses `register_rest_route` to define a new endpoint `/user/me` under a custom namespace.
         * The endpoint is accessible via the GET method and is designed to return details of the currently
         * authenticated user. The callback function `auth_me` is responsible for handling the request and
         * returning the appropriate response.
         *
         * @see register_rest_route() Registers a new REST API route.
         * @see auth_me() Callback function that processes the request and returns user details.
         *
         * Route Details:
         * - Namespace: Custom namespace (e.g., 'aragrow/v1')
         * - Route: /user/me
         * - Method: GET
         * - Permissions: Requires the user to be authenticated.
         *
         * Example Usage:
         * - Endpoint: GET /wp-json/aragrow/v2/auth-me
         * - Headers: Include a valid authentication token (e.g., via cookies or Authorization header).
         *
         * @return void
         */

        // Route: GET /user/me (Retrieve current authenticated user details)
 // Route: GET /auth-me (Retrieve current authenticated user details)
        register_rest_route( $instance->namespace, '/auth-me', array(
            'methods'             => 'GET',
            'callback'            => array( $instance, 'get_current_user_details' ),
            'permission_callback' => function( WP_REST_Request $request ) use ( $instance ) { // Added WP_REST_Request type hint
                error_log('AUTH-ME: Permission callback started.');
                $auth_header = $request->get_header('authorization');

                if ( empty( $auth_header ) ) {
                    error_log('AUTH-ME: Permission callback - Authorization header is missing.');
                    // Attempt to get from $_SERVER as a fallback (e.g. for some server configs)
                    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                        $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
                        error_log('AUTH-ME: Permission callback - Authorization header found in $_SERVER.');
                    } else {
                        return new WP_Error( 'rest_forbidden_no_header', __( 'Authorization header is missing.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
                    }
                }

                if ( stripos( $auth_header, 'Bearer ' ) !== 0 ) { // Use stripos for case-insensitive check
                    error_log('AUTH-ME: Permission callback - Authorization header invalid format: ' . $auth_header);
                    return new WP_Error( 'rest_forbidden_invalid_header', __( 'Authorization header is invalid.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
                }

                $jwt_token = trim( substr( $auth_header, 7 ) ); // Get token part after "Bearer "

                if (empty($jwt_token)) {
                    error_log('AUTH-ME: Permission callback - Extracted token is empty.');
                    return new WP_Error( 'rest_forbidden_empty_token', __( 'Token is empty after Bearer.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
                }
                error_log('AUTH-ME: Permission callback - Token extracted: ' . $jwt_token);


                $jwt_validation = $instance->verify_and_get_jwt_details( $jwt_token );

                if ( is_wp_error( $jwt_validation ) ) {
                    error_log('AUTH-ME: Permission callback - JWT validation FAILED: ' . $jwt_validation->get_error_message());
                    return $jwt_validation;
                }

                $user_id = isset( $jwt_validation->sub ) ? (int) $jwt_validation->sub : 0;
                if ($user_id <= 0) {
                    error_log('AUTH-ME: Permission callback - Invalid user ID from token payload.');
                    return new WP_Error( 'rest_invalid_user_in_token', __( 'Invalid user identifier in token payload.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
                }

                error_log('AUTH-ME: Permission callback - JWT validation SUCCESS. User ID from token: ' . $user_id);
                $request->set_param('validated_user_id', $user_id); // Store for the main callback
                return true;
            },
        ));


        // Route: POST /query (Handle natural language input)
        register_rest_route( $instance->namespace, '/query', array(
            'methods'             => 'POST',
            'callback'            => array( $instance, 'handle_natural_language_query' ),
            'permission_callback' => array( $instance, 'handle_natural_language_query_permissions_check' ),
            'args'                => array(
                // The 'query' parameter is expected in the POST body (the natural language string)
                'query' => array(
                    'validate_callback' => function($value) {
                         // Ensure the query is a non-empty string.
                        return ! empty( $value ) && is_string( $value );
                    },
                    'required' => true, // The query string is required
                     // Use a basic sanitizer for raw natural language input.
                     // Avoid aggressive sanitizers like sanitize_text_field if you need to process complex phrases,
                     // but be extremely careful if you relax sanitization here. Validation/sanitization of EXTRACTED parameters is CRITICAL.
                    'sanitize_callback' => 'sanitize_text_field', // Basic sanitation, might need refinement based on expected input complexity
                ),
            ),
        ));

        // TODO: Add routes for other entities (projects, expenses, etc.)
        // Example: register_rest_route( $instance->namespace, '/projects', ... );
        // Example: register_rest_route( $instance->namespace, '/expenses', ... );
        // ... and their permission checks and callbacks.
    }

    public function get_current_user_details( WP_REST_Request $request ) {
        error_log(__CLASS__.'::'.__FUNCTION__ . ' - STARTED');

        $user_id = $request->get_param('validated_user_id'); // Get from permission callback

        if ( empty($user_id) || $user_id <= 0 ) {
            // This should ideally not happen if permission callback worked.
            // Could add a fallback to re-validate token from header if needed, but let's keep it clean.
            error_log(__CLASS__.'::'.__FUNCTION__ . ' - Invalid or missing validated_user_id param.');
            return new WP_Error( 'rest_invalid_user_param', __( 'Validated user ID not found in request.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
        }

        error_log(__CLASS__.'::'.__FUNCTION__ . ' - User ID to fetch: ' . $user_id);
        $user = get_userdata( $user_id );

        if ( ! $user || ! ( $user instanceof WP_User ) ) {
            error_log(__CLASS__.'::'.__FUNCTION__ . ' - User not found for ID: ' . $user_id);
            return new WP_Error( 'rest_user_not_found', __( 'User not found.', 'your-plugin-textdomain' ), array( 'status' => 404 ) );
        }

        // Prepare user details for response - matching React's AuthContext User interface
        $user_data = array(
            'id'            => $user->ID,
            'name'          => $user->display_name, // React AuthContext uses 'name'
            // You can add more fields if your React User interface needs them
            // 'username'      => $user->user_login,
            // 'email'         => $user->user_email,
            // 'roles'         => $user->roles,
        );
        error_log(__CLASS__.'::'.__FUNCTION__ . ' - Successfully fetched user data: ' . print_r($user_data, true));
        return new WP_REST_Response( $user_data, 200 );
    }

    /**
     * --- Permission Check Callbacks ---
     *
     * These methods determine if the currently authenticated user has permission
     * to perform the requested action on the endpoint.
     * They should return true if access is granted, or a WP_Error object if denied.
     * Authentication is handled automatically by WordPress for REST API if a token is present.
     * We only need to check if a user is logged in and has the necessary capabilities.
     */

    /**
     * Check if a user has permission to list company tracker entries.
     * Requires authentication and a specific capability.
     *
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error True if permission is granted, WP_Error otherwise.
     */
    public function get_permissions_check( $request ) {
        error_log(__CLASS__.'::'.__FUNCTION__);
        // Ensure the user is logged in.
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
        }

        // Check if the user has a specific capability.
        // 'read' is a basic capability for any logged-in user. For managing data,
        // a more restrictive capability like 'list_users', 'edit_others_posts', or a custom capability ('list_companies') is recommended.
        // Adjust 'read' to the appropriate capability for your application's roles/permissions.
        if ( ! current_user_can( 'nexus_api' ) ) { // Example: check if user can read posts (basic access)
             // Example using a more specific capability:
             // if ( ! current_user_can( 'edit_users' ) ) { // Or a custom capability 'nexus_list_companies'
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to continue.', 'your-plugin-textdomain' ), array( 'status' => 403 ) );
        }

        return true; // Permission granted.
    }

    /**
     * Check if a user has permission to create a company tracker entry.
     * Requires authentication and a specific capability.
     *
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error True if permission is granted, WP_Error otherwise.
     */
    public function get_company_description() {
        error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $table_name = $wpdb->prefix . 'company_tracker';    

        $fields = array(
                // Define and validate/sanitize ALL fields expected in the request body for creation.
                'ID' => array(
                    'required' => true,  
                    'sanitize_callback' => 'sanitize_text_field',
                    'format' => '%d'  ),
                'name' => array(
                    'required' => true,  
                    'sanitize_callback' => 'sanitize_text_field',
                    'format' => '%s'  ),
                'legal_name' => array(
                    'required' => false,  
                    'sanitize_callback' => 'sanitize_text_field',
                    'format' => '%s' ),
                'document_number' => array(
                    'required' => false,  
                    'sanitize_callback' => 'sanitize_text_field',
                    'format' => '%s' ),
                'default_flat_fee' => array(
                    'required' => false,
                    'sanitize_callback' => 'floatval',
                    'format' => '%f' ),
                'contact_person' => array( 
                    'required' => false, 
                    'sanitize_callback' => 'sanitize_text_field',
                    'format' => '%s' ),
                'email' => array(
                    'required' => false,
                    'sanitize_callback' => 'sanitize_email',
                    'format' => '%s' ),
                'phone' => array( 
                    'required' => false, 
                    'sanitize_callback' => 'sanitize_text_field',
                    'format' => '%s' ),
                'address_1' => array( 
                    'required' => false, 
                    'sanitize_callback' => 'sanitize_text_field',
                    'format' => '%s' ),
                'address_2' => array( 
                    'required' => false, 
                    'sanitize_callback' => 'sanitize_text_field',
                    'format' => '%s' ),
                'city' => array( 
                    'required' => false, 
                    'sanitize_callback' => 'sanitize_text_field',
                    'format' => '%s' ),
                'state' => array( 
                    'required' => false, 
                    'sanitize_callback' => 'sanitize_text_field',
                    'format' => '%s' ),
                'postal_code' => array( 
                    'required' => false, 
                    'sanitize_callback' => 'sanitize_text_field',
                    'format' => '%s' ),
                'country' => array( 
                    'required' => false, 
                    'sanitize_callback' => 'sanitize_text_field',
                    'format' => '%s' ),
                'website' => array(
                    'required' => false,
                    'sanitize_callback' => 'esc_url_raw',
                    'format' => '%s' ),
                'notes' => array(
                    'required' => false,
                    'sanitize_callback' => 'wp_kses_post',
                    'format' => '%s' ),
                'status' => array(
                    'required' => false, 
                    'sanitize_callback' => 'absint',
                    'format' => '%d' ),
                 // created_at and updated_at are set by the backend, not taken from request args.
            );
            $return = [
                'table_name' => $table_name,
                'fields' => $fields
            ];

        return $return; 
    }

    /**
     * Check if a user has permission to submit natural language queries.
     * Requires authentication and a specific capability.
     *
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error True if permission is granted, WP_Error otherwise.
     */
    public function handle_natural_language_query_permissions_check( $request ) {
        error_log(__CLASS__.'::'.__FUNCTION__);
         // Ensure the user is logged in.
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
        }

        // Check for a capability that allows submitting queries.
        // 'read' might be sufficient if queries are read-only, but use a custom capability
        // if queries can trigger modifications ('nexus_query' or specific action capabilities).
        if ( ! current_user_can( 'read' ) ) { // Example: any logged-in user can submit read queries
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to submit queries.', 'your-plugin-textdomain' ), array( 'status' => 403 ) );
        }

        return true; // Permission granted.
    }

    /**
     * Handles the POST /query request for natural language input.
     * This method receives the natural language query string and acts as the
     * entry point for the NLP processing and mapping logic.
     * For now, it contains placeholder logic.
     *
     * @param WP_REST_Request $request The request object. Contains the 'query' parameter in the body.
     * @return WP_REST_Response|WP_Error The response object containing processed query info/results or an error.
     */
    public function handle_natural_language_query( $request ) {
        error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;


        $response_data = [];
        $response_data['company'] = $this->get_company_description();
        // Return the structured response to the frontend.
        return new WP_REST_Response( $response_data, 200 ); // 200 OK status code
    }

    function handle_app_password_login( WP_REST_Request $request ) {
        error_log(__CLASS__.'::'.__FUNCTION__);
        // Sanitize username, but application_password should be passed raw to wp_authenticate
        $username = $request->get_param('username'); // Already sanitized by 'args'
        $application_password = $request->get_param('application_password'); // NOT sanitized by args, pass raw

        // Basic check if params are there, though 'required' => true should handle this.
        if (empty($username) || empty($application_password)) {
            return new WP_Error(
                'missing_credentials',
                __('Username or Application Password missing.', 'your-plugin-textdomain'),
                array( 'status' => 400 ) // Bad Request
            );
        }
        error_log("Attempting login for user: " . $username);

        $user = wp_authenticate( $username, $application_password );

        if ( is_wp_error( $user ) ) {
            error_log("Login failed for user: " . $username . ". Error: " . $user->get_error_code() . " - " . $user->get_error_message());
            return new WP_Error(
                $user->get_error_code(),
                $user->get_error_message(),
                array( 'status' => 401 )
            );
        }

        if ( ! $user instanceof WP_User ) {
            error_log("Login for user: " . $username . " - Unknown error, wp_authenticate returned non-WP_User, non-WP_Error.");
            return new WP_Error( 'nexus_auth_unknown_error', __( 'An unknown error occurred during authentication.', 'your-plugin-textdomain' ), array( 'status' => 500 ) );
        }

        error_log("Login successful for user: " . $user->user_login . " (ID: " . $user->ID . ")");
        $jwt_response = $this->generate_jwt_manually( $user ); // This returns ['token' => $jwt_token] or WP_Error

        if (is_wp_error($jwt_response)) {
            error_log("JWT generation failed for user: " . $user->user_login . ". Error: " . $jwt_response->get_error_message());
            return $jwt_response; // Propagate the WP_Error
        }
        
        error_log("JWT generated successfully for user: " . $user->user_login);
        return new WP_REST_Response( $jwt_response, 200 ); // $jwt_response is like ['token' => 'actual_token_string']
    }

    
    /**
     * Helper function to Base64Url encode a string.
     * Replaces '+' with '-', '/' with '_', and removes '=' padding.
     * @param string $data The data to encode.
     * @return string The Base64Url encoded string.
     */
    private function base64url_encode( $data ) {
        error_log(__CLASS__.'::'.__FUNCTION__);
        $base64 = base64_encode( $data ); // Standard base64 encode
        $base64url = strtr( $base64, '+/', '-_' ); // Replace URL-unsafe characters
        return rtrim( $base64url, '=' ); // Remove padding
    }

    /**
     * Helper function to Base64Url decode a string.
     * Adds padding back and decodes the Base64Url string.
     * @param string $data The Base64Url encoded data to decode.
     * @return string|false The decoded data or false on failure.
     */
    function base64url_decode( $data ) { // Renamed to avoid potential global conflicts
        error_log(__CLASS__.'::'.__FUNCTION__);
        // Add back padding characters ('=') needed for standard base64 decoding
        $b64 = strtr( $data, '-_', '+/' );
        $padded = $b64 . str_repeat('=', strlen($b64) % 4);
    
        // Decode the standard Base64 string
        // Use strict mode (true) if available (PHP 7+) to catch invalid characters
        if ( version_compare( PHP_VERSION, '7.0.0', '>=' ) ) {
            return base64_decode( $padded, true );
        } else {
             // Fallback for older PHP versions (less strict)
            return base64_decode( $padded );
        }
    }


    /**
     * Generates a JSON Web Token (JWT) manually.
     *
     * This function creates a JWT by encoding the header, payload, and signature
     * using the specified algorithm and secret key. It is useful for scenarios
     * where you need to generate a JWT without relying on external libraries.
     *
     * @param array $header An associative array representing the JWT header.
     *                       Typically includes the algorithm ('alg') and token type ('typ').
     * @param array $payload An associative array representing the JWT payload.
     *                        Contains the claims or data to be included in the token.
     * @param string $secret The secret key used to sign the JWT.
     * @param string $algorithm The hashing algorithm to use for signing (e.g., 'HS256').
     * 
     * @return string The generated JWT as a string.
     *
     * @throws InvalidArgumentException If required parameters are missing or invalid.
     * @throws RuntimeException If the JWT generation process fails.
     */
    private function generate_jwt_manually( WP_User $user ) {
        error_log(__CLASS__.'::'.__FUNCTION__);
        // Step 1: Define Header
        $header_array = array(
            'alg' => 'HS256', // Algorithm: HMAC-SHA256
            'typ' => 'JWT'    // Type: JWT
        );
        $header = json_encode( $header_array );
        // Handle json_encode errors
        if ( $header === false ) {
            error_log('Nexus Auth Manual JWT: Header json_encode error: ' . json_last_error_msg());
            return new WP_Error( 'nexus_jwt_encode_header', __( 'Failed to encode JWT header.', 'your-plugin-textdomain' ), array( 'status' => 500, 'details' => json_last_error_msg() ) );
        }
    
    
        // Step 2: Define Payload (Claims)
        $issued_at = time(); // Timestamp of token issuance
        // Set expiration time (e.g., 7 days from now). Adjust as needed.
        $expiration_time = $issued_at + ( DAY_IN_SECONDS * 7 ); // DAY_IN_SECONDS is a WP constant
    
        $payload_array = array(
            'iss' => get_bloginfo('url'), // Issuer (your site URL)
            'iat' => $issued_at,        // Issued At timestamp
            'exp' => $expiration_time,  // Expiration timestamp
            'sub' => (string) $user->ID, // Subject (User ID as string)
            // Custom data about the user if needed by the frontend
            'data' => array(
                'user' => array(
                    'id' => $user->ID,
                    'display_name' => $user->display_name,
                    'email' => $user->user_email,
                )
            )
        );
        $payload = json_encode( $payload_array );
         // Handle json_encode errors
        if ( $payload === false ) {
            error_log('Nexus Auth Manual JWT: Payload json_encode error: ' . json_last_error_msg());
            return new WP_Error( 'nexus_jwt_encode_payload', __( 'Failed to encode JWT payload.', 'your-plugin-textdomain' ), array( 'status' => 500, 'details' => json_last_error_msg() ) );
        }
    
    
        // Step 3 & 4: Base64Url Encode Header and Payload
        $base64UrlHeader = $this->base64url_encode( $header );
        $base64UrlPayload = $this->base64url_encode( $payload );
    
    
        // Step 5: Prepare the data to be signed
        $dataToSign = $base64UrlHeader . '.' . $base64UrlPayload;
    
        // Step 6: Get the Secret Key
        if ( ! defined( 'JWT_AUTH_SECRET_KEY' ) || empty( JWT_AUTH_SECRET_KEY ) ) {
            error_log('Nexus Auth Manual JWT: JWT_AUTH_SECRET_KEY is not defined.');
            return new WP_Error( 'nexus_jwt_secret_missing', __( 'JWT secret key is not defined on the backend.', 'your-plugin-textdomain' ), array( 'status' => 500 ) );
        }
        $secret_key = JWT_AUTH_SECRET_KEY;
    
    
        // Step 7: Calculate the Signature (using HMAC-SHA256)
        // !!! THIS IS THE MOST CRITICAL AND RISKY STEP TO IMPLEMENT MANUALLY. !!!
        // hash_hmac returns the calculated HMAC. The third argument 'true'
        // makes it return the raw binary output, which is needed BEFORE Base64Url encoding.
        $signature = hash_hmac('sha256', $dataToSign, $secret_key, true);
        error_log('secret_key: ' . $secret_key);
        error_log('Nexus Auth Manual JWT: Signature: ' . bin2hex($signature));
        // Step 8: Base64Url encode the raw binary signature.
        $base64UrlSignature = $this->base64url_encode( $signature );
    
        // Step 9: Combine the parts to form the final JWT
        $jwt_token = $dataToSign . '.' . $base64UrlSignature;
        
        $token_ok = $this->verify_and_get_jwt_details( $jwt_token ) ;  
        // Step 10: Return the generated token string.
        return ['token' => $jwt_token];
    }

    private static function verify_and_get_jwt( $token ) {
        error_log(__CLASS__.'::'.__FUNCTION__);
        // This function is a wrapper for the verify_and_get_jwt_details method.
        // It can be used to validate the JWT token and return the decoded payload.
        $instance = new self();
        $result = $instance->verify_and_get_jwt_details( $token );
        if ( is_wp_error( $result ) ) {
            // Handle the error case
            return $result; // Return the error object
        }
        error_log('Result of Verification: ' . print_r($result, true));
        return $result;
    }

    function verify_and_get_jwt_details( $token ) { // Renamed to avoid potential global conflicts
        error_log(__CLASS__.'::'.__FUNCTION__);
    
        if ( empty( $token ) || ! is_string( $token ) ) {
            error_log('JWT Verify: Invalid or empty token provided.');
            return new WP_Error( 'your_prefix_jwt_invalid_token_input', __( 'Invalid token provided.', 'your-plugin-textdomain' ), array( 'status' => 400 ) );
        }
    
        // 1. Split the token into parts
        $parts = explode( '.', $token );
        if ( count( $parts ) !== 3 ) {
            error_log('JWT Verify: Token structure incorrect (expected 3 parts).');
            return new WP_Error( 'your_prefix_jwt_invalid_structure', __( 'Token structure is invalid.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
        }
        list( $base64UrlHeader, $base64UrlPayload, $base64UrlSignatureProvided ) = $parts;
    
        // 2. Decode Header and Payload using the helper function
        $header_json = $this->base64url_decode( $base64UrlHeader );
        $payload_json = $this->base64url_decode( $base64UrlPayload );
    
        if ( $header_json === false || $payload_json === false ) {
            error_log('JWT Verify: Failed to Base64Url decode header or payload.');
            return new WP_Error( 'your_prefix_jwt_decode_error', __( 'Failed to decode token parts.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
        }
    
        $header_decoded = json_decode( $header_json );
        $payload_decoded = json_decode( $payload_json ); // Keep as object for easier access
    
        if ( $header_decoded === null || $payload_decoded === null ) {
            error_log('JWT Verify: Failed to JSON decode header or payload: ' . json_last_error_msg());
            return new WP_Error( 'your_prefix_jwt_json_decode_error', __( 'Failed to parse token JSON.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
        }
    
        // Optional: Check algorithm
        if ( ! isset( $header_decoded->alg ) || $header_decoded->alg !== 'HS256' ) {
            error_log('JWT Verify: Invalid or missing algorithm in header.');
            return new WP_Error( 'your_prefix_jwt_invalid_alg', __( 'Invalid algorithm specified.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
        }
    
        // 3. Get Secret Key (Ensure this constant is defined elsewhere, e.g., wp-config.php)
        if ( ! defined( 'JWT_AUTH_SECRET_KEY' ) || empty( JWT_AUTH_SECRET_KEY ) ) {
            error_log('JWT Verify: JWT_AUTH_SECRET_KEY is not defined for verification.');
            return new WP_Error( 'your_prefix_jwt_verification_failed', __( 'Token verification failed.', 'your-plugin-textdomain' ), array( 'status' => 500 ) );
        }
        $secret_key = JWT_AUTH_SECRET_KEY;
    
        // 4. Verify Signature
        $dataToSign = $base64UrlHeader . '.' . $base64UrlPayload;
        $signature_expected_raw = hash_hmac('sha256', $dataToSign, $secret_key, true);
        // Need base64url_encode function (or equivalent) for this step:
        // Assuming you have the `base64url_encode` function from the previous example available too.
        // If not, you'll need to add it. Let's assume it's `your_prefix_base64url_encode`.
        $base64UrlSignatureExpected = $this->base64url_encode( $signature_expected_raw ); // You need the encode function too!
    
        // error_log('JWT Verify: Expected Signature: ' . $base64UrlSignatureExpected);
        // error_log('JWT Verify: Provided Signature: ' . $base64UrlSignatureProvided);
        // error_log('JWT Verify: Hash Equals: ' . hash_equals( $base64UrlSignatureExpected, $base64UrlSignatureProvided ) );

        if ( ! hash_equals( $base64UrlSignatureExpected, $base64UrlSignatureProvided ) ) {
            error_log('JWT Verify: Signature verification failed.');
            return new WP_Error( 'your_prefix_jwt_invalid_signature', __( 'Token signature is invalid.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
        }
        error_log('JWT Verify: Signature verified successfully.');
    
        // 5. Check Expiry ('exp' claim)
        if ( ! isset( $payload_decoded->exp ) ) {
            error_log('JWT Verify: Expiration claim (exp) missing.');
            return new WP_Error( 'your_prefix_jwt_missing_claim_exp', __( 'Token is missing expiration claim.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
        }
        if ( time() > $payload_decoded->exp ) {
            error_log('JWT Verify: Token has expired. Current time: ' . time() . ', Exp time: ' . $payload_decoded->exp);
            return new WP_Error( 'your_prefix_jwt_token_expired', __( 'Token has expired.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
        }
        error_log('JWT Verify: Token is not expired.');
    
        // Optional: Check Not Before ('nbf' claim) if used
        if ( isset( $payload_decoded->nbf ) && time() < $payload_decoded->nbf ) {
            error_log('JWT Verify: Token is not yet valid (nbf).');
            return new WP_Error( 'your_prefix_jwt_token_not_yet_valid', __( 'Token is not yet valid.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
        }
    
        // Optional: Check Issued At ('iat' claim) sanity
        if ( isset( $payload_decoded->iat ) && $payload_decoded->iat > time() ) {
            error_log('JWT Verify: Issued At (iat) claim is in the future.');
            return new WP_Error( 'your_prefix_jwt_invalid_iat', __( 'Token issue time is invalid.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
        }
    
        // 6. Validate User ('sub' claim)
        if ( ! isset( $payload_decoded->sub ) || empty( $payload_decoded->sub ) ) {
            error_log('JWT Verify: Subject claim (sub) missing or empty.');
            return new WP_Error( 'your_prefix_jwt_missing_claim_sub', __( 'Token is missing user identifier.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
        }
    
        $user_id = (int) $payload_decoded->sub;
        if ( $user_id <= 0 ) {
            error_log('JWT Verify: Invalid user ID in subject claim (sub).');
            return new WP_Error( 'your_prefix_jwt_invalid_sub_format', __( 'Invalid user identifier format in token.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
        }
    
        // Check if user exists in WordPress
        $user = get_userdata( $user_id );
        if ( ! $user || ! ( $user instanceof WP_User ) || $user->ID !== $user_id ) {
            error_log('JWT Verify: User specified in token (ID: ' . $user_id . ') not found or invalid in WordPress.');
            return new WP_Error( 'your_prefix_jwt_invalid_user', __( 'Token validation failed.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
        }
        error_log('JWT Verify: User (ID: ' . $user_id . ') validated successfully.');
    
        // 7. All checks passed! Return the decoded payload object.
        error_log('JWT Verify: Token verified successfully for user ID: ' . $user_id);
        error_log('JWT Verify: Payload: ' . print_r($payload_decoded, true));
        return $payload_decoded;
    }

}