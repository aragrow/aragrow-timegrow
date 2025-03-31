<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowExpenseModel {

    private $table_name;
    private $table_name2;
    private $wpdb;
    private $charset_collate;
    private $allowed_fields;

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        $this->table_name = $this->wpdb->prefix . TIMEGROW_PREFIX . 'expense_tracker'; // Make sure this matches your table name
        $this->allowed_fields = ['expense_name', 'amount', 'category', 'assigned_to', 'assigned_to_id', 'expense_description', 'updated_at', 'created_at'];
       
    }

    public function initialize() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (  -- Join table for many-to-many
            ID BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            expense_name VARCHAR(255) NOT NULL,
            expense_description text NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            category VARCHAR(255) NOT NULL,
            assigned_to ENUM('project', 'client', 'general') NOT NULL,
            assigned_to_id mediumint(9) NULL,
            created_at timestamp,
            updated_at timestamp,
            PRIMARY KEY (id)
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
                "SELECT * FROM {$this->table_name} WHERE id IN ($placeholders) ORDER BY name",
                $ids
            );
        }
        // If a single ID is provided
        elseif (intval($ids)) {
            $id = intval($ids); // Sanitize ID
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE id = %d ORDER BY name",
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
        if (empty($sanitized_data['expense_name']) || 
            empty($sanitized_data['amount']) || 
            empty($sanitized_data['category']) ||
            empty($sanitized_data['assigned_to_id']) ||
            empty($sanitized_data['expense_description']) ) {
            return false;
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
        // Whitelist allowed fields to prevent SQL injection
        $sanitized_data = [];
    
        foreach ($data as $key => $value) {
            if (in_array($key,  $this->allowed_fields , true)) {
                $sanitized_data[$key] = sanitize_text_field($value); // Sanitize each field
            }
        }
    
        // Ensure all required fields are present
        if (empty($sanitized_data['expense_name']) || 
            empty($sanitized_data['amount']) || 
            empty($sanitized_data['category']) ||
            empty($sanitized_data['assigned_to_id']) ||
            empty($sanitized_data['expense_description']) ) {
            return false;
        }

        $result = $this->wpdb->insert(
            $this->table_name,
            $sanitized_data,
            '%s' // string
        );

        if ($result === false) {
            return false;
        }

        return $this->wpdb->insert_id;
    }
}