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
    private $namespace = 'nexus/v1';

    // The full table names with the WordPress prefix.
    private $company_table_name;
    private $expense_table_name; // For expense_tracker
    private $receipt_table_name; // For expense_receipt
    private $project_table_name; // For project_tracker
    private $time_entry_table_name; // For time_entry_tracker

    /**
     * Constructor.
     * Initializes table names with the global $wpdb prefix.
     */
    public function __construct() {
        global $wpdb;
        // Define the table names using the WordPress prefix.
        $this->company_table_name = $wpdb->prefix . 'company_tracker';
        $this->expense_table_name = $wpdb->prefix . 'expense_tracker';
        $this->receipt_table_name = $wpdb->prefix . 'expense_receipt';
        $this->project_table_name = $wpdb->prefix . 'project_tracker';
        $this->time_entry_table_name = $wpdb->prefix . 'time_entry_tracker';

         // Note: The 'rest_api_init' action hook is handled in the main plugin file (nexus-backend.php)
         // by calling the static register_routes method.
    }

    /**
     * Registers the custom REST API routes.
     * This method is called via the 'rest_api_init' action hook.
     * It must be static because actions are often registered before the object is instantiated.
     */
    public static function register_routes() {
        // Create an instance of the class to access non-static methods (like the table names and callbacks).
        $instance = new self();

        // --- Define Routes ---

        // --- Company Tracker Endpoints (/companies) ---

        // Route: GET /companies (List company tracker entries)
        register_rest_route( $instance->namespace, '/companies', array(
            'methods'             => 'GET',
            'callback'            => array( $instance, 'get_companies' ),
            'permission_callback' => array( $instance, 'get_companies_permissions_check' ),
            'args'                => array(
                 // Define expected query parameters for filtering, sorting, pagination.
                 'status' => array( // Example: Filter by status (0 or 1)
                     'validate_callback' => function($value) {
                         // Ensure the status value is either '0' or '1' (received as string from URL)
                         return in_array($value, array('0', '1'), true); // Use true for strict comparison
                     },
                     'required' => false, // Parameter is optional
                     'sanitize_callback' => 'absint', // Sanitize to an absolute integer
                 ),
                 // TODO: Add args for pagination (page, per_page), sorting (orderby, order), filtering (city, name search 's')
                 's' => array( // Example: simple search parameter
                      'validate_callback' => function($value){ return is_string($value); },
                      'required' => false,
                      'sanitize_callback' => 'sanitize_text_field',
                 ),
                 'per_page' => array( // Example: pagination limit
                      'validate_callback' => function($value){ return is_numeric($value) && $value > 0; },
                      'required' => false,
                      'sanitize_callback' => 'absint',
                      'default' => 10, // Default items per page
                 ),
                  'page' => array( // Example: pagination page number
                      'validate_callback' => function($value){ return is_numeric($value) && $value > 0; },
                      'required' => false,
                      'sanitize_callback' => 'absint',
                      'default' => 1, // Default page number
                 ),
            ),
        ));

        // Route: POST /companies (Create a new company tracker entry)
         register_rest_route( $instance->namespace, '/companies', array(
            'methods'             => 'POST',
            'callback'            => array( $instance, 'create_company' ),
            'permission_callback' => array( $instance, 'create_company_permissions_check' ),
            'args'                => array(
                // Define and validate/sanitize ALL fields expected in the request body for creation.
                'name' => array(
                     'validate_callback' => function($value) {
                         // Name is required and must be a non-empty string.
                         return ! empty( $value ) && is_string( $value );
                     },
                     'required' => true, // This parameter is mandatory for a valid request
                     'sanitize_callback' => 'sanitize_text_field', // Basic text sanitization
                 ),
                 'legal_name' => array(
                     'validate_callback' => function($value) { return is_string( $value ) || null === $value; },
                     'required' => false, // This field is optional
                     'sanitize_callback' => 'sanitize_text_field',
                 ),
                 'document_number' => array(
                     'validate_callback' => function($value) { return is_string( $value ) || null === $value; },
                     'required' => false, // This field is optional
                     'sanitize_callback' => 'sanitize_text_field',
                 ),
                 'default_flat_fee' => array(
                     'validate_callback' => function($value) { return is_numeric($value) || null === $value || $value === ''; }, // Allow empty string or null for number input
                     'required' => false,
                     'sanitize_callback' => 'floatval', // Convert to float
                 ),
                 'contact_person' => array( 'validate_callback' => function($value){ return is_string($value) || null === $value; }, 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
                 'email' => array(
                     'validate_callback' => function($value) { return empty($value) || is_email($value); }, // Allow empty or valid email format
                     'required' => false,
                     'sanitize_callback' => 'sanitize_email', // Email specific sanitization
                 ),
                 'phone' => array( 'validate_callback' => function($value){ return is_string($value) || null === $value; }, 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
                 'address_1' => array( 'validate_callback' => function($value){ return is_string($value) || null === $value; }, 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
                 'address_2' => array( 'validate_callback' => function($value){ return is_string($value) || null === $value; }, 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
                 'city' => array( 'validate_callback' => function($value){ return is_string($value) || null === $value; }, 'required' => false, 'sanitize_text_field' ),
                 'state' => array( 'validate_callback' => function($value){ return is_string($value) || null === $value; }, 'required' => false, 'sanitize_text_field' ),
                 'postal_code' => array( 'validate_callback' => function($value){ return is_string($value) || null === $value; }, 'required' => false, 'sanitize_text_field' ),
                 'country' => array( 'validate_callback' => function($value){ return is_string($value) || null === $value; }, 'required' => false, 'sanitize_text_field' ),
                 'website' => array(
                     'validate_callback' => function($value) { return empty($value) || filter_var($value, FILTER_VALIDATE_URL); }, // Allow empty or valid URL
                     'required' => false,
                     'sanitize_callback' => 'esc_url_raw', // URL sanitization
                 ),
                 'notes' => array(
                     'validate_callback' => function($value) { return is_string($value) || null === $value; },
                     'required' => false,
                     'sanitize_callback' => 'wp_kses_post', // Allows basic HTML, adjust if plain text only is needed
                 ),
                 'status' => array(
                      'validate_callback' => function($value) { return in_array($value, array(0, 1), true); }, // Must be exactly 0 or 1
                      'required' => false, // Status can be defaulted by the database
                      'sanitize_callback' => 'absint',
                 ),
                 // created_at and updated_at are set by the backend, not taken from request args.
            ),
        ));

        // TODO: Add routes for GET /companies/{id}, PUT /companies/{id}, DELETE /companies/{id}


        // --- Natural Language Query Endpoint ---

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
    public function get_companies_permissions_check( $request ) {
        // Ensure the user is logged in.
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
        }

        // Check if the user has a specific capability.
        // 'read' is a basic capability for any logged-in user. For managing data,
        // a more restrictive capability like 'list_users', 'edit_others_posts', or a custom capability ('list_companies') is recommended.
        // Adjust 'read' to the appropriate capability for your application's roles/permissions.
        if ( ! current_user_can( 'read' ) ) { // Example: check if user can read posts (basic access)
             // Example using a more specific capability:
             // if ( ! current_user_can( 'edit_users' ) ) { // Or a custom capability 'nexus_list_companies'
             return new WP_Error( 'rest_forbidden', __( 'You do not have permission to view company tracker entries.', 'your-plugin-textdomain' ), array( 'status' => 403 ) );
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
    public function create_company_permissions_check( $request ) {
         // Ensure the user is logged in.
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'rest_not_logged_in', __( 'You are not currently logged in.', 'your-plugin-textdomain' ), array( 'status' => 401 ) );
        }

        // Check for a capability that allows data modification.
        // 'edit_users' is a common capability for managing user-like data.
        // A custom capability like 'nexus_create_company' is best practice.
        if ( ! current_user_can( 'edit_users' ) ) { // Example: check if user can edit users
            return new WP_Error( 'rest_forbidden', __( 'You do not have permission to create company tracker entries.', 'your-plugin-textdomain' ), array( 'status' => 403 ) );
        }

        return true; // Permission granted.
    }

    /**
     * Check if a user has permission to submit natural language queries.
     * Requires authentication and a specific capability.
     *
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error True if permission is granted, WP_Error otherwise.
     */
    public function handle_natural_language_query_permissions_check( $request ) {
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

    // TODO: Add permission checks for GET /{id}, PUT /{id}, DELETE /{id} companies endpoints
    // public function get_company_permissions_check( $request ) { /* ... */ }
    // public function update_company_permissions_check( $request ) { /* ... */ }
    // public function delete_company_permissions_check( $request ) { /* ... */ }


    /**
     * --- Callback Methods ---
     *
     * These methods contain the actual logic for handling requests to the registered endpoints.
     * They receive a WP_REST_Request object and should return a WP_REST_Response or WP_Error.
     * They must interact with the database using secure methods ($wpdb->prepare, $wpdb->insert, etc.).
     */

    /**
     * Handles the GET /companies request.
     * Fetches company tracker entries from the database based on request parameters.
     *
     * @param WP_REST_Request $request The request object. Contains parameters like status, s, per_page, page.
     * @return WP_REST_Response|WP_Error The response object containing company data or an error.
     */
    public function get_companies( $request ) {
        global $wpdb;

        // Start building the SQL query. Use 1=1 to make adding WHERE clauses easier.
        $query = "SELECT * FROM {$this->company_table_name} WHERE 1=1";
        // Array to hold parameters for $wpdb->prepare.
        $params = array();

        // --- Build WHERE clauses based on validated and sanitized request parameters ---
        // Parameters are automatically sanitized by 'args' validation if defined there.
        // Additional validation/sanitization can happen here if needed.

        // Filter by status if the 'status' parameter was provided.
        if ( $request['status'] !== null ) {
             $query .= " AND status = %d"; // %d is for integer
             $params[] = $request['status']; // $request['status'] is already sanitized to integer by 'args'
        }

        // Example: Filter by search term 's' in name or other fields.
        $search_term = $request['s'];
        if ( ! empty( $search_term ) ) {
             // Sanitize the search term and escape LIKE wildcards.
             $sanitized_search = '%' . $wpdb->esc_like( $search_term ) . '%';
             $query .= " AND (name LIKE %s OR contact_person LIKE %s OR city LIKE %s)"; // Add more fields if needed
             $params[] = $sanitized_search;
             $params[] = $sanitized_search;
             $params[] = $sanitized_search;
        }

        // TODO: Add WHERE clauses for other filters (e.g., city, postal code range)

        // --- Add ORDER BY (Sorting) ---
        // Example: Order by created_at DESC by default, allow ordering by name or ID.
        $orderby = $request['orderby'] ? $request['orderby'] : 'created_at';
        $order = $request['order'] ? strtoupper($request['order']) : 'DESC';

        // Validate that the 'orderby' parameter is a valid column name or allowed alias.
        // This is CRITICAL for security; never use unsanitized input directly in ORDER BY.
        $allowed_orderby = array('ID', 'name', 'created_at', 'updated_at', 'city', 'status'); // List of allowed columns
        if (!in_array($orderby, $allowed_orderby)) {
            $orderby = 'created_at'; // Default to a safe column if invalid orderby is requested
        }

        // Validate that the 'order' parameter is ASC or DESC.
        if (!in_array($order, array('ASC', 'DESC'))) {
            $order = 'DESC'; // Default to DESC if invalid order is requested
        }

        // Add the ORDER BY clause. Column name and direction are now safe.
        $query .= " ORDER BY {$orderby} {$order}";


        // --- Add LIMIT and OFFSET (Pagination) ---
        $per_page = $request['per_page']; // Already sanitized to int, default 10
        $page = $request['page'];       // Already sanitized to int, default 1

        // Calculate the offset based on page number and items per page.
        $offset = ( $page - 1 ) * $per_page;

        // Add the LIMIT and OFFSET clauses. %d is for integers.
        $query .= " LIMIT %d, %d";
        $params[] = $offset;
        $params[] = $per_page;


        // --- Execute the query securely ---
        // Use $wpdb->prepare if there are any parameters to substitute.
        if ( ! empty( $params ) ) {
            // $wpdb->prepare handles escaping and quoting of parameters based on their format (%s, %d, %f).
            $sql = $wpdb->prepare( $query, $params );
        } else {
            // If there are no dynamic parameters (e.g., just SELECT * FROM table), prepare is not strictly needed
            // for the query string itself, but it's safer to get used to always using it if there's any user input involved.
            $sql = $query;
        }

        // Execute the query and get the results as an array of objects.
        $results = $wpdb->get_results( $sql );

        // Check for database query errors.
        if ( $results === false ) {
            // If $wpdb->get_results returns false, a database error occurred.
             return new WP_Error( 'nexus_company_query_error', __( 'Database error when fetching companies.', 'your-plugin-textdomain' ), array( 'status' => 500, 'error_details' => $wpdb->last_error ) );
        }

        // TODO: You might want to get the total number of results without LIMIT/OFFSET
        // to send back in the response headers for pagination UI on the frontend.
        // $total_query = "SELECT COUNT(ID) FROM {$this->company_table_name} WHERE 1=1 ... (same WHERE clauses)";
        // $total_companies = $wpdb->get_var( $wpdb->prepare( $total_query, $where_params ) );
        // $response = new WP_REST_Response( $results, 200 );
        // $response->header( 'X-WP-Total', (int) $total_companies );
        // $response->header( 'X-WP-TotalPages', (int) ceil( $total_companies / $per_page ) );
        // return $response;


        // Return a successful response with the fetched data.
        return new WP_REST_Response( $results, 200 ); // 200 OK status code
    }


    /**
     * Handles the POST /companies request to create a new company tracker entry.
     *
     * @param WP_REST_Request $request The request object. Contains the request body data.
     * @return WP_REST_Response|WP_Error The response object containing the new company data or an error.
     */
    public function create_company( $request ) {
        global $wpdb;

        // Get parameters from the request body using $request->get_param().
        // These values have ALREADY been validated and sanitized by the 'args'
        // definitions in the register_rest_route call.
        // We retrieve them here to prepare the data array for $wpdb->insert.
        $name = $request->get_param('name');
        $legal_name = $request->get_param('legal_name');
        $document_number = $request->get_param('document_number');
        $default_flat_fee = $request->get_param('default_flat_fee'); // Already floatval or null/empty handled
        $contact_person = $request->get_param('contact_person');
        $email = $request->get_param('email'); // Already sanitized_email or null/empty handled
        $phone = $request->get_param('phone');
        $address_1 = $request->get_param('address_1');
        $address_2 = $request->get_param('address_2');
        $city = $request->get_param('city');
        $state = $request->get_param('state');
        $postal_code = $request->get_param('postal_code');
        $country = $request->get_param('country');
        $website = $request->get_param('website'); // Already esc_url_raw or null/empty handled
        $notes = $request->get_param('notes'); // Already wp_kses_post or esc_textarea/null handled
        $status = $request->get_param('status'); // Already absint (0 or 1) or null/default handled


        // --- Additional Validation (Optional, but good for complex business logic) ---
        // Although 'args' handles basic type/format, you might add checks here like:
        // - Is this combination of fields valid?
        // - Does the email domain exist? (More advanced)
        // - Check uniqueness for fields not covered by a UNIQUE DB constraint (less efficient)

        // Validate uniqueness of document_number if provided and not empty.
        // 'args' validation checks type/format, but uniqueness requires a DB query.
        if ( ! empty( $document_number ) ) {
            $existing_company = $wpdb->get_row( $wpdb->prepare(
                "SELECT ID FROM {$this->company_table_name} WHERE document_number = %s",
                $document_number // Use the sanitized document_number from $request->get_param()
            ) );
            // If a row was returned, a company with this document number already exists.
            if ( $existing_company ) {
                return new WP_Error( 'nexus_company_document_number_exists', __( 'A company with this document number already exists.', 'your-plugin-textdomain' ), array( 'status' => 409, 'details' => array('document_number' => 'duplicate') ) ); // 409 Conflict status code
            }
        }

        // TODO: Add validation for foreign keys if needed (e.g., does a client ID exist?)
        // Not applicable for company_tracker create, but would be for project_tracker (client_id).


        // --- Prepare data array for insertion ---
        // This array should map database column names to the sanitized values received.
        // Only include columns you are inserting data into.
        // Keys must match database column names exactly.
        $data = array(
            'name'              => $name, // Required via 'args'
            'legal_name'        => $legal_name,
            'document_number'   => $document_number,
            'default_flat_fee'  => $default_flat_fee,
            'contact_person'    => $contact_person,
            'email'             => $email,
            'phone'             => $phone,
            'address_1'         => $address_1,
            'address_2'         => $address_2,
            'city'              => $city,
            'state'             => $state,
            'postal_code'       => $postal_code,
            'country'           => $country,
            'website'           => $website,
            'notes'             => $notes,
            'status'            => $status, // Defaulted to 1 in args if not provided
            'created_at'        => current_time('mysql', 1), // Set the creation timestamp securely on the backend
            'updated_at'        => current_time('mysql', 1), // Set the update timestamp initially to the creation time
        );

         // --- Define the format array for $wpdb->insert ---
         // This array tells $wpdb->insert the data type format for each value in the $data array.
         // The order of formats MUST correspond EXACTLY to the order of keys/values in the $data array.
         // Use %s for strings, %d for integers, %f for floats. Use null for NULL values.
         $format = array(
             '%s', // name
             '%s', // legal_name
             '%s', // document_number
             '%f', // default_flat_fee
             '%s', // contact_person
             '%s', // email
             '%s', // phone
             '%s', // address_1
             '%s', // address_2
             '%s', // city
             '%s', // state
             '%s', // postal_code
             '%s', // country
             '%s', // website
             '%s', // notes
             '%d', // status
             '%s', // created_at
             '%s', // updated_at
         );

        // --- Perform the insertion using $wpdb->insert ---
        // $wpdb->insert automatically uses $wpdb->prepare internally for security.
        $result = $wpdb->insert( $this->company_table_name, $data, $format );

        // Check if the insertion was successful. $wpdb->insert returns false on failure.
        if ( $result === false ) {
            // Handle database insertion error.
             return new WP_Error( 'nexus_company_insert_error', __( 'Database error when creating company.', 'your-plugin-textdomain' ), array( 'status' => 500, 'error_details' => $wpdb->last_error ) );
        }

        // Get the ID of the newly inserted row.
        $new_id = $wpdb->insert_id;

        // Fetch the newly created company data from the database.
        // This is good practice to return the full, canonical representation of the resource.
        $new_company = $wpdb->get_row( $wpdb->prepare(
             "SELECT * FROM {$this->company_table_name} WHERE ID = %d",
             $new_id
        ) );

        // Return a successful response. Use status code 201 Created.
        // The response body contains the data of the newly created resource.
        return new WP_REST_Response( $new_company, 201 ); // 201 Created status code
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
        global $wpdb;

        // Get the natural language query string. It's already sanitized by 'args' validation.
        $natural_language_query = $request->get_param('query');

        // --- Placeholder NLP and Mapping Logic ---
        // This is the core of your "Nexus" intelligence layer.
        // You would integrate an NLP library (like PHP-NLP, or send the query to a service like Google NLP, OpenAI, etc.)
        // to understand the intent (e.g., "list", "create", "update", "report")
        // and extract key information (e.g., entity type 'company', 'project'; filters 'city="New York"', 'status="active"'; data for creation/update).

        // Structure the response data. This should be flexible to indicate different result types.
        $response_data = array(
            'received_query' => $natural_language_query, // Echo back the query for confirmation
            'processed_intent' => null, // What action the system understood (e.g., 'list', 'create', 'update')
            'processed_parameters' => null, // Extracted parameters (e.g., filters, data for update/create)
            'result_type' => 'message', // Indicate the type of result: 'list', 'single', 'success', 'error', 'report', 'message', 'form_needed'
            'data' => null, // The actual data result (e.g., array of companies, single company object, report aggregation)
            'message' => 'Nexus received your query. Processing logic not yet implemented for this type.', // A human-readable message
             'error' => null, // Any specific errors during processing
        );

        // --- Example: VERY BASIC Keyword Matching (NOT REAL NLP) ---
        // This demonstrates how you might start mapping keywords to intents.
        // Real NLP would use tokenization, parsing, entity recognition, intent classification, etc.
        $query_lower = strtolower( $natural_language_query );

        if ( strpos( $query_lower, 'list companies' ) !== false || strpos( $query_lower, 'show me companies' ) !== false ) {
             $response_data['processed_intent'] = 'list';
             $response_data['processed_parameters'] = ['entity' => 'company_tracker']; // Indicate the target entity

             // Simulate fetching data using the secure method already defined in get_companies.
             // You might reuse the get_companies method internally here, or build the query dynamically.
             // For this placeholder, let's just fetch a few.
             $sql = $wpdb->prepare( "SELECT ID, name, city, status FROM {$this->company_table_name} LIMIT %d", 10 ); // Get first 10 companies
             $companies = $wpdb->get_results( $sql );

             if ( $companies !== false ) {
                 $response_data['result_type'] = 'list'; // Indicate that the result is a list of items
                 $response_data['data'] = $companies; // Include the fetched data
                 $response_data['message'] = sprintf( __( 'Found %d companies:', 'your-plugin-textdomain' ), count($companies) );
             } else {
                  $response_data['result_type'] = 'error';
                  $response_data['message'] = __( 'Could not fetch companies.', 'your-plugin-textdomain' );
                  $response_data['error'] = $wpdb->last_error;
             }

        } elseif ( strpos( $query_lower, 'add company' ) !== false || strpos( $query_lower, 'create company' ) !== false ) {
             $response_data['processed_intent'] = 'create';
             $response_data['processed_parameters'] = ['entity' => 'company_tracker'];
             // For creation via NL, you'd need to extract data like name, email etc.
             // This is complex. Often, NL for creation just confirms intent and might trigger showing a form.
             $response_data['result_type'] = 'message'; // Indicate a simple message response
             $response_data['message'] = __( 'I understand you want to add a company. Please use the form for now.', 'your-plugin-textdomain' );
             // Or, you could set result_type: 'form_needed', data: { entity: 'company_tracker' }
             // and your frontend could interpret this to display the CompanyForm.

        } elseif ( strpos( $query_lower, 'report' ) !== false && strpos( $query_lower, 'expenses by category' ) !== false ) {
             $response_data['processed_intent'] = 'report';
             $response_data['processed_parameters'] = ['entity' => 'expense_tracker', 'aggregation' => 'sum_by_category'];
             // Example: Simulate aggregation query (requires expense_tracker table)
             // global $wpdb; // Already declared
             // $expense_table = $wpdb->prefix . 'expense_tracker';
             // $report_sql = "SELECT category, SUM(amount) as total_amount FROM {$expense_table} GROUP BY category";
             // $report_results = $wpdb->get_results($report_sql);
             // if ($report_results !== false) {
             //     $response_data['result_type'] = 'report';
             //     $response_data['data'] = $report_results;
             //     $response_data['message'] = 'Here is the expense report by category:';
             // } else { /* handle error */ }
             $response_data['result_type'] = 'message';
             $response_data['message'] = __( 'Expense reporting is not yet implemented.', 'your-plugin-textdomain' );

        }
        // TODO: Add more comprehensive NLP/mapping logic here.
        // - Identify entities: companies, projects, expenses, clients, team members, time entries.
        // - Identify operations: list, show (single), add (create), update, delete, report (aggregate).
        // - Extract parameters: filters (status="active", city="London", assigned_to="client" AND assigned_to_id=123, date range), data for updates/creation (name="XYZ", amount=100, category="Travel").
        // - Map extracted info to secure `$wpdb` queries or WordPress API calls (WP_User_Query, etc.).
        // - Handle ambiguity or insufficient information (ask clarifying questions, return an error message).


        // --- End Placeholder Logic ---


        // Return the structured response to the frontend.
        return new WP_REST_Response( $response_data, 200 ); // 200 OK status code
    }


    // TODO: Implement callbacks for other endpoints (GET /{id}, PUT /{id}, DELETE /{id} for companies)
    // public function get_company( $request ) { /* ... */ }
    // public function update_company( $request ) { /* ... */ }
    // public function delete_company( $request ) { /* ... */ }

    // TODO: Implement permission checks and callbacks for project_tracker, expense_tracker, etc.
    // public function get_projects_permissions_check( $request ) { /* ... */ }
    // public function get_projects( $request ) { /* ... */ }
    // public function create_project_permissions_check( $request ) { /* ... */ }
    // public function create_project( $request ) { /* ... */ }
    // ... etc. for all entities and operations ...

    /**
     * Placeholder for the run method if the class needed dynamic hooks in the constructor.
     * For this REST API class using static registration, the run method is not strictly needed
     * if the static method is hooked directly (as done in nexus-backend.php).
     */
     public function run() {
         // Example: add_action( 'some_other_action', array( $this, 'some_method' ) );
     }

    /*
     * Example method for creating custom database tables on plugin activation:
     * This would ideally be in a separate class (e.g., Nexus_Table_Manager)
     * and called from the plugin's activation hook.
     *
     * public static function activate() {
     *     global $wpdb;
     *     require_once( ABSPATH . 'wp-admin/includes/upgrade.php' ); // Required for dbDelta
     *
     *     $charset_collate = $wpdb->get_charset_collate();
     *
     *     // company_tracker table SQL
     *     $sql_company = "CREATE TABLE {$wpdb->prefix}company_tracker (
     *         ID mediumint(9) NOT NULL AUTO_INCREMENT,
     *         name varchar(255) NOT NULL,
     *         legal_name varchar(255),
     *         document_number varchar(255) UNIQUE,
     *         default_flat_fee DECIMAL(10, 2) DEFAULT 0.00,
     *         contact_person varchar(255),
     *         email varchar(255),
     *         phone varchar(20),
     *         address_1 varchar(255),
     *         address_2 varchar(255),
     *         city varchar(50),
     *         state varchar(50),
     *         postal_code varchar(20),
     *         country varchar(50),
     *         website varchar(255),
     *         notes text,
     *         status smallint(1) NOT NULL DEFAULT 1,
     *         created_at timestamp DEFAULT CURRENT_TIMESTAMP, -- Use CURRENT_TIMESTAMP default
     *         updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Use CURRENT_TIMESTAMP default and ON UPDATE
     *         PRIMARY KEY  (ID),
     *         KEY document_number (document_number) -- Add index for unique/search field
     *     ) $charset_collate;";
     *     dbDelta( $sql_company );
     *
     *     // TODO: Add SQL for other tables (expense_tracker, expense_receipt, project_tracker, time_entry_tracker)
     *     // Pay attention to foreign key syntax and constraints ON DELETE CASCADE etc.
     *     // FOREIGN KEY (expense_id) REFERENCES {$wpdb->prefix}expense_tracker(id) ON DELETE CASCADE
     *     // Note: dbDelta handles FOREIGN KEY creation differently depending on DB engine and WP version.
     *     // Manual SQL might be needed for complex foreign key setups if dbDelta doesn't work as expected.
     *
     *     // Add default data or options if needed
     *     // add_option( 'nexus_db_version', '1.0' );
     * }
     *
     * // Example deactivation hook structure:
     * public static function deactivate() {
     *    // Code to run on plugin deactivation (optional, usually leave tables)
     * }
     *
     */
}