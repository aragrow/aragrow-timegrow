<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowCompanyModel {

    private $table_name;
    private $wpdb;
    private $charset_collate;
    private $allowed_fields;

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        $this->table_name = $this->wpdb->prefix . TIMEGROW_PREFIX . 'company_tracker'; // Make sure this matches your table name
        $this->allowed_fields = ['name', 'legal_name', 
                                'document_number', 'default_flat_fee', 
                                'contact_person', 'email', 
                                'phone', 'address_1', 
                                'address_2', 'city', 
                                'state', 'postal_code', 
                                'country', 'website', 
                                'note', 'status', 
                                'created_at', 'updated_at'];

    }

    public function initialize() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            ID BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            legal_name varchar(255),  -- Legal name of the company (if different)
            document_number varchar(255) UNIQUE, -- Tax ID, registration number, etc.
            default_flat_fee DECIMAL(10, 2) DEFAULT 0.00,
            contact_person varchar(255),
            email varchar(255),
            phone varchar(20),
            address_1 varchar(255),
            address_2 varchar(255),
            city varchar(50),
            state varchar(50),
            postal_code varchar(20),
            country varchar(50),
            website varchar(255),
            notes text,       -- Any additional notes about the company
            status smallint(1) NOT NULL DEFAULT 1,
            created_at timestamp,
            updated_at timestamp,  -- Timestamp of the last update
            PRIMARY KEY  (ID)
        ) $this->charset_collate;";

        dbDelta($sql);

    }
    
    /**
     * Select expenses by ID or array of IDs.
     *
     * @param int|array $ids Single ID or array of IDs.
     * @return array|object|null Array of expense objects or null if no results.
     */
    public function select($ids = null) {
        if (WP_DEBUG) error_log(__CLASS__ . '::' . __FUNCTION__);
    
        // If IDs are provided as an array
        if (is_array($ids)) {
            $ids = array_map('intval', $ids); // Sanitize IDs
            $placeholders = implode(',', array_fill(0, count($ids), '%d')); // Create placeholders for prepared statement
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE ID IN ($placeholders) ORDER BY name",
                $ids
            );
        }
        // If a single ID is provided
        elseif (intval($ids)) {
            $id = intval($ids); // Sanitize ID
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE ID = %d",
                $id
            );
        }
        // If no IDs are provided, fetch all rows
        else {
            $sql = "SELECT * FROM {$this->table_name} ORDER BY name";
        }
    
        return $this->wpdb->get_results($sql);
    }
    

    /**
     * Update an existing expense.
     *
     * @param int $id Expense ID.
     * @param array $data Array of data to update.
     * @return bool|int False on error, or the number of rows updated.
     */
    public function update($id, $data) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $id = intval($id); // Sanitize ID

        // Whitelist allowed fields to prevent SQL injection
         $sanitized_data = [];

        foreach ($data as $key => $value) {
            if (in_array($key,  $this->allowed_fields , true)) {
                $sanitized_data[$key] = sanitize_text_field($value); // Sanitize each field
            }
        }

        // Ensure all required fields are present
        if (empty($sanitized_data['name']) || 
            empty($sanitized_data['legal_name']) || 
            empty($sanitized_data['document_number']) ||
            empty($sanitized_data['default_flat_fee']) ||
            empty($sanitized_data['contact_person']) || 
            empty($sanitized_data['email']) || 
            empty($sanitized_data['phone']) ||   
            empty($sanitized_data['address_1']) ||
            empty($sanitized_data['city']) ||
            empty($sanitized_data['state']) ||
            empty($sanitized_data['postal_code']) ||
            empty($sanitized_data['country']) ||
            empty($sanitized_data['status'])) {
            wp_die( 'Error: validation not passed', array( 'back_link' => true ) );
        }

        return $this->wpdb->update(
            $this->table_name,
            $sanitized_data,
            ['id' => $id],
            '%s', // string
            '%d'  // integer
        );
    }

    /**
     * Create a new expense.
     *
     * @param array $data Array of data to insert.
     * @return int|false The ID of the newly created row, or false on error.
     */
    public function create($data) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        try {
            // Whitelist allowed fields to prevent SQL injection
            $sanitized_data = [];

            foreach ($data as $key => $value) {
                if (in_array($key,  $this->allowed_fields , true)) {
                    $sanitized_data[$key] = sanitize_text_field($value); // Sanitize each field
                }
            }
            var_dump($sanitized_data);

            // Ensure all required fields are present
            if (empty($sanitized_data['name']) || 
                empty($sanitized_data['legal_name']) || 
                empty($sanitized_data['document_number']) ||
                empty($sanitized_data['default_flat_fee']) ||
                empty($sanitized_data['contact_person']) || 
                empty($sanitized_data['email']) || 
                empty($sanitized_data['phone']) ||   
                empty($sanitized_data['address_1']) ||
                empty($sanitized_data['city']) ||
                empty($sanitized_data['state']) ||
                empty($sanitized_data['postal_code']) ||
                empty($sanitized_data['country']) ||
                empty($sanitized_data['status'])) {
                wp_die( 'Error: validation not passed', array( 'back_link' => true ) );
            }

            $result = $this->wpdb->insert(
                $this->table_name,
                $sanitized_data,
                '%s' // string
            );
            error_log($this->wpdb->last_query);
            
            if ($result === false) {
                return false;
            }

            return $this->wpdb->insert_id;
        } catch (Exception $e) {
            // Handle general exceptions
            error_log("Exception: " . $e->getMessage());
            echo "An error occurred: " . htmlspecialchars($e->getMessage());
            return new WP_Error(
                'An error occurred', // Error code
                __(htmlspecialchars($e->getMessage())), // Error message
                array('status' => 503) // Optional additional data
            );
        }
    }
}