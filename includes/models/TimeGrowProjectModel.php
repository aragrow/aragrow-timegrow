<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowProjectModel {

    private $table_name;
    private $table_name2;
    private $table_name3;
    private $table_name4;
    private $wpdb;
    private $charset_collate;
    private $allowed_fields;

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        $this->table_name = $this->wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker'; // Make sure this matches your table name
        $this->table_name2 = $this->wpdb->prefix . 'users'; // Make sure this matches your table name
        $this->table_name3 = $this->wpdb->prefix . 'posts'; // Make sure this matches your table name
        $this->table_name4 = $this->wpdb->prefix . TIMEGROW_PREFIX . 'team_member_projects_tracker'; // Make sure this matches your table name
        $this->allowed_fields = ['client_id', 'name', 
                                'status', 'description',
                                'billable', 'default_flat_fee',
                                'start_date', 'end_date',
                                'estimate_hours','product_id',
                                'created_at', 'updated_at'];
    }

    public function initialize() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        $sql = "CREATE TABLE IF NOT EXISTS table_name}(
            ID mediumint(9) NOT NULL AUTO_INCREMENT,
            client_id mediumint(9) NOT NULL,
            product_id mediumint(9) NULL,
            name varchar(255) NOT NULL,
            description text,
            status varchar(50) DEFAULT 'active',
            default_flat_fee DECIMAL(10, 2) DEFAULT 0.00,
            start_date date,
            end_date date,
            status smallint(1) NOT NULL DEFAULT 1,
            billable smallint(1) NOT NULL DEFAULT 1,
            estimate_hours smallint(4) NULL,
            created_by bigint(20) unsigned,
            created_at timestamp,
            updated_at timestamp,
            PRIMARY KEY  (ID),
            FOREIGN KEY (client_id) REFERENCES {$this->table_name2}(ID),
            FOREIGN KEY (project_id) REFERENCES {$this->table_name3}(ID),
            FOREIGN KEY (created_by) REFERENCES {$this->table_name2}users(ID)
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
                "SELECT a.*, b.display_name as client_name
                FROM {$this->table_name} a 
                INNER JOIN  $this->table_name2 b ON a.client_id = b.ID
                WHERE a.ID IN ($placeholders) 
                ORDER BY a.name",
                $ids
            );
        }
        // If a single ID is provided
        elseif (intval($ids)) {
            $id = intval($ids); // Sanitize ID
            $sql = $this->wpdb->prepare(
                "SELECT a.*, b.display_name as client_name 
                FROM {$this->table_name} a 
                INNER JOIN  $this->table_name2 b ON a.client_id = b.ID
                WHERE a.ID = %d",
                $id
            );
        }
        // If no IDs are provided, fetch all rows
        else {
            $sql = "SELECT a.*, b.display_name as client_name 
                    FROM {$this->table_name} a 
                    INNER JOIN  $this->table_name2 b ON a.client_id = b.ID
                    ORDER BY a.name";
        }
    
        return $this->wpdb->get_results($sql);
    }
    
    public function available($ids = null) {
        if (WP_DEBUG) error_log(__CLASS__ . '::' . __FUNCTION__);
    
        $id = intval($ids); // Sanitize ID
        $sql = $this->wpdb->prepare(
            "SELECT a.*, b.display_name as client_name 
            FROM {$this->table_name} a 
            INNER JOIN  $this->table_name2 b ON a.client_id = b.ID
            WHERE a.ID not in (SELECT project_id FROM {$this->table_name4} WHERE team_member_id = %d)",
            $id
        );
        $return = $this->wpdb->get_results($sql);
        return $return;
    }

    public function assigned($ids = null) {
        if (WP_DEBUG) error_log(__CLASS__ . '::' . __FUNCTION__);
    
        $id = intval($ids); // Sanitize ID
        $sql = $this->wpdb->prepare(
            "SELECT a.*, b.display_name as client_name 
            FROM {$this->table_name} a 
            INNER JOIN  $this->table_name2 b ON a.client_id = b.ID
            WHERE a.ID in (SELECT project_id FROM {$this->table_name4} WHERE team_member_id = %d)",
            $id
        );
    
        return $this->wpdb->get_results($sql);
    }
    

    /**
     * Update an existing expense.
     *
     * @param int $id Expense ID.
     * @param array $data Array of data to update.
     * @return bool|int False on error, or the number of rows updated.
     */
    public function update($id, $data, $format) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $id = intval($id); // Sanitize ID

        // Whitelist allowed fields to prevent SQL injection
        $sanitized_data = [];

        foreach ($data as $key => $value) {
            if (in_array($key,  $this->allowed_fields , true)) {
                if (strpos($key, 'date') !== false && $value === '') 
                    $sanitized_data[$key] = null;
                else
                    $sanitized_data[$key] = $value; // Sanitize each field
            }
        }

        // Ensure all required fields are present
        if (empty($sanitized_data['client_id']) || 
            empty($sanitized_data['name']) || 
            empty($sanitized_data['status']) ) {
            wp_die( 'Error: validation not passed', array( 'back_link' => true ) );
        }

        $return = $this->wpdb->update(
            $this->table_name,
            $sanitized_data,
            ['ID' => $id],
            $format,
            '%d'  // integer
        );

        // var_dump($this->wpdb->last_query);

        return $return;

    }

    /**
     * Create a new expense.
     *
     * @param array $data Array of data to insert.
     * @return int|false The ID of the newly created row, or false on error.
     */
    public function create($data, $format) {
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
            if (empty($sanitized_data['client_id']) || 
                empty($sanitized_data['name']) || 
                empty($sanitized_data['status']) ) {
                wp_die( 'Error: validation not passed', array( 'back_link' => true ) );
            }

            $result = $this->wpdb->insert(
                $this->table_name,
                $sanitized_data,
                $format
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