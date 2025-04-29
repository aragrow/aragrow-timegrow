<?php

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
    private $namespace = 'jwt-auth/v1';

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
     * This method is called via the 'rest_api_init' action hook.
     * It must be static because actions are often registered before the object is instantiated.
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

    /**
     * Handles the POST /auth request.
     * Authenticates a user using their username/email and an Application Password,
     * and returns a JWT token if authentication is successful.
     *
     * @param WP_REST_Request $request The request object. Contains 'username' and 'application_password' from the request body.
     * @return WP_REST_Response|WP_Error The response object containing the JWT or an error.
     */
    function handle_app_password_login( WP_REST_Request $request ) {
        error_log(__CLASS__.'::'.__FUNCTION__);
        // Get parameters. These are automatically validated and sanitized
        // according to the 'args' defined in register_rest_route.
        $username = sanitize_text_field( $request->get_param('username') );
        $application_password = sanitize_text_field( $request->get_param('application_password') );

        // --- Authenticate the user using WordPress's built-in function ---
        // wp_authenticate() is the standard, secure way to verify credentials in WordPress.
        // It handles checking against main passwords, Application Passwords, etc.
        // Returns a WP_User object on success, or a WP_Error object on failure.
        $user = wp_authenticate( $username, $application_password );

        // Check the result of authentication.
        if ( is_wp_error( $user ) ) {
            // Authentication failed. Return a REST API error response.
            // Use 401 Unauthorized status code for login failures.
            // Pass the specific error code and message from wp_authenticate for debugging.
            return new WP_Error(
                $user->get_error_code(), // e.g., 'invalid_username', 'incorrect_password', 'application_password_errors'
                $user->get_error_message(),
                array( 'status' => 401 ) // HTTP status code for Unauthorized
            );
        }

        // Authentication successful if $user is a WP_User object.
        if ( ! $user instanceof WP_User ) {
            // This case should ideally not happen if is_wp_error check passes, but serves as a safeguard.
            return new WP_Error( 'nexus_auth_unknown_error', __( 'An unknown error occurred during authentication.', 'nexus-app-password-auth' ), array( 'status' => 500 ) );
        }

        // --- User successfully authenticated using Application Password ---
        // Now, generate a JWT token for this authenticated user.
        // This step requires using the functionality provided by the installed
        // JWT Authentication for WP-REST API plugin.

        $jwt_token = $this->generate_jwt_manually( $user );

        return new WP_REST_Response( $jwt_token, 200 );
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
    
        // Step 8: Base64Url encode the raw binary signature.
        $base64UrlSignature = $this->base64url_encode( $signature );
    
        // Step 9: Combine the parts to form the final JWT
        $jwt_token = $dataToSign . '.' . $base64UrlSignature;
    
        // Step 10: Return the generated token string.
        return ['token' => $jwt_token];
    }
}