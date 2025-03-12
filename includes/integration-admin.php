<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Timeflies_Integration_Settings {

    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_filter('woocommerce_prevent_admin_access', [ $this, 'allow_team_member_admin_access' ], 10, 2);
        add_action('admin_init', [ $this, 'register_integration_settings' ]);
    }

    /**
     * It hooks into the 'woocommerce_prevent_admin_access' filter, which WooCommerce uses to determine whether to 
     * redirect users away from the admin area.
     * 
     * The function 'allow_team_member_admin_access' checks if the user has the 'team_member' role.
     * If the user is a team_member, it returns false, which tells WooCommerce allow admin access for this user.
     * For all other users, it returns the original $prevent_access value, maintaining WooCommerce's default behavior.
    */
    public function allow_team_member_admin_access($prevent_access, $redirect=null) {
          
            $user = wp_get_current_user();
        
            if (in_array('team_member', (array) $user->roles)) {
                return false; // Allow access
            }
        
        return $prevent_access; // Default WooCommerce behavior
    }

    // Register settings
    function register_integration_settings() {
        register_setting(
            'timeflies_integration_group',
            'timeflies_integration_settings',
            array(
                'sanitize_callback' => [ $this, 'sanitize_integration_settings' ]
            )
        );

        add_settings_section(
            'timeflies_wc_section',
            'WooCommerce Integration',
            [ $this, 'wc_section_callback' ],
            'timeflies-integrations'
        );

        add_settings_field(
            'wc_clients',
            'Integrate with WooCommerce Clients',
            [ $this, 'wc_clients_callback' ],
            'timeflies-integrations',
            'timeflies_wc_section'
        );

        add_settings_field(
            'wc_invoices',
            'Integrate with WooCommerce Invoices',
            [ $this, 'wc_invoices_callback' ],
            'timeflies-integrations',
            'timeflies_wc_section'
        );
    }
    

    // Sanitization callback
    function sanitize_integration_settings($input) {
        $output = array();
        
        $output['wc_clients'] = isset($input['wc_clients']) && $input['wc_clients'] ? 1 : 0;
        $output['wc_invoices'] = isset($input['wc_invoices']) && $input['wc_invoices'] ? 1 : 0;

        return $output;
    }

    // Section description
    function wc_section_callback() {
        echo '<p>Select which WooCommerce features you want to integrate with:</p>';
        
        if (!class_exists('WooCommerce')) {
            echo '<div class="x-notice warning"><p>WooCommerce is not installed or activated!</p></div>';
        }
    }

    // Clients integration checkbox
    function wc_clients_callback() {
        $options = get_option('timeflies_integration_settings');
        $value = isset($options['wc_clients']) ? $options['wc_clients'] : 0;
        ?>
        <label>
            <input type="checkbox" name="timeflies_integration_settings[wc_clients]" value="1" 
                <?php checked(1, $value); ?> 
                <?php echo !class_exists('WooCommerce') ? 'disabled' : ''; ?>>
            Enable client synchronization
        </label>
        <?php
    }

    // Invoices integration checkbox
    function wc_invoices_callback() {
        $options = get_option('timeflies_integration_settings');
        $value = isset($options['wc_invoices']) ? $options['wc_invoices'] : 0;
        ?>
        <label>
            <input type="checkbox" name="timeflies_integration_settings[wc_invoices]" value="1" 
                <?php checked(1, $value); ?> 
                <?php echo !class_exists('WooCommerce') ? 'disabled' : ''; ?>>
            Enable invoice synchronization
        </label>
        <?php
    }

}

Timeflies_Integration_Settings::get_instance(); 

class Timeflies_Integration {
    
    /**
     * Retrieve WooCommerce customer data
     * 
     * @param mixed $client_ids (int|array|string) Single ID, array of IDs, or 'all' for all customers
     * @param array $fields Specific fields to retrieve (default: all)
     * @return array Array of customer data
     */
    public function get_wc_customers($client_ids = 'all', $fields = [], $orderfields = []) {
        global $wpdb;
        
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_missing', 'WooCommerce is not active');
        }
    
        $customers = [];
        
        // Base query for customer data
        $base_query = [
            'role' => 'customer',
            'orderby' => 'ID',
            'order' => 'ASC',   
        ];
    
        // Handle different ID types
        if (is_numeric($client_ids)) {
            // Single customer ID
            $user = get_userdata($client_ids);
            if ($user && in_array('customer', $user->roles)) {
                $customers[] = $user;
            }
        } elseif (is_array($client_ids)) {
            // Array of customer IDs
            $base_query['include'] = $client_ids;
            $customers = get_users($base_query);
        } elseif ($client_ids === 'all') {
            // All customers
            $base_query['number'] = -1;
            $customers = get_users($base_query);
        }
    
        // Process results
        $results = [];
        foreach ($customers as $customer) {
            $customer_data = [];
            
            // Get basic user data
            $customer_data['ID'] = $customer->ID;
            $customer_data['email'] = $customer->user_email;
            
            // Get WooCommerce-specific data
            $customer_meta = get_user_meta($customer->ID);
            
            // Combine first and last name
            $first_name = $customer_meta['billing_first_name'][0] ?? '';
            $last_name = $customer_meta['billing_last_name'][0] ?? '';
            $customer_data['name'] = trim($first_name . ' ' . $last_name);
    
            $wc_fields = [
                'billing_company' => 'company', 
                'billing_address_1' => 'address_1',
                'billing_address_2' => 'address_2',
                'billing_city' => 'city', 
                'billing_postcode' => 'postal_code',
                'billing_country' => 'country', 
                'billing_state' => 'state',
                'billing_phone' => 'phone',
              //  'billing_flat_fee' => 'default_flat_fee',
              //  'billing_currency' => 'currency'
            ];
    
            foreach ($wc_fields as $meta_key => $custom_key) {
                $customer_data[$custom_key] = $customer_meta[$meta_key][0] ?? '';
            }
    
            // Filter fields if specified
            if (!empty($fields)) {
                $customer_data = array_intersect_key(
                    $customer_data,
                    array_flip($fields)
                );
            }
    
            $results[] = $customer_data;
        }
    
        // Add sorting functionality
        if (!empty($orderfields)) {
            $sort_params = [];
            
            // Parse order fields and directions
            foreach ($orderfields as $field) {
                $parts = explode(' ', strtoupper($field));
                $sort_field = strtolower($parts[0]);
                $direction = (isset($parts[1]) && $parts[1] === 'DESC') ? -1 : 1;
                $sort_params[] = [
                    'field' => $sort_field,
                    'direction' => $direction
                ];
            }
    
            usort($results, function ($a, $b) use ($sort_params) {
                foreach ($sort_params as $sort) {
                    $field = $sort['field'];
                    $dir = $sort['direction'];
    
                    $valA = $a[$field] ?? '';
                    $valB = $b[$field] ?? '';
    
                    // Natural case-insensitive comparison
                    $comparison = strnatcasecmp($valA, $valB);
                    
                    if ($comparison !== 0) {
                        return $comparison * $dir;
                    }
                }
                return 0;
            });
        }
    
        return $results;
    }

    public function get_projects_wc_customers($orderfields = []) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_missing', 'WooCommerce is not active');
        }

        // Get all customers
        $projects = $wpdb->get_results("
        SELECT p.*
        FROM {$prefix}timeflies_projects p
        ORDER BY p.name
        ", ARRAY_A);

        $project_ids = array_unique(array_column($projects, 'ID'));

        // var_dump($wpdb->last_query);
        // var_dump($wpdb->last_result);   

        // Initialize an array to store client data
        $clients = [];

        // Loop through projects and extract unique client IDs
        $client_ids = array_unique(array_column($projects, 'client_id'));

        // Use the get_wc_customers method to fetch client details
        $client_details = $this->get_wc_customers($client_ids, ['ID', 'name'], []);
        //var_dump($wpdb->last_query);
        //var_dump($wpdb->last_result);  

        // Create an associative array of clients with ID as key
        foreach ($client_details as $client) {
            $clients[$client['ID']] = $client['name'];
        }

        // Now $clients array contains client IDs as keys and names as values
        // You can use it like this:
        foreach ($projects as &$project) {
            // echo'<hr />';var_dump($project); 
            $client_id = $project['client_id'];
            $project['client_name'] = $clients[$client_id];
        }

        return $projects;

    }

}