<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowTeamMemberModel {

    private $table_name;
    private $wpdb;
    private $charset_collate;
    private $allowed_fields;

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $this->wpdb->get_charset_collate();
        $this->table_name = $this->wpdb->prefix . TIMEGROW_PREFIX . 'team_member_tracker'; // Make sure this matches your table name
        $this->allowed_fields = ['user_id', 'company_id', 
                                'name', 'email', 
                                'phone', 'title', 
                                'bio', 'status', 
                                'created_at', 'updated_at'];
    }

    public function initialize() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        $sql = "CREATE TABLE IF NOT EXISTS $this->table_name}  (
            ID mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL UNIQUE,  
            company_id mediumint(9) NOT NULL,  
            name varchar(25) NOT NULL,
            email varchar(255),
            phone varchar(20),
            title varchar(255),  
            bio text,           
            status smallint(1) NOT NULL DEFAULT 1,
            created_at timestamp,
            updated_at timestamp,
            PRIMARY KEY  (ID),
            FOREIGN KEY (company_id) REFERENCES {$this->wpdb->prefix}timegrow_companies(ID),
            FOREIGN KEY (user_id) REFERENCES {$this->wpdb->prefix}users(ID)
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
        if (empty($sanitized_data['company_id']) || 
            empty($sanitized_data['name']) || 
            empty($sanitized_data['email']) || 
            empty($sanitized_data['status'])  ) {
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

            // Ensure all required fields are present
            if (empty($sanitized_data['company_id']) || 
                empty($sanitized_data['name']) || 
                empty($sanitized_data['email']) || 
                empty($sanitized_data['status'])  ) {
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