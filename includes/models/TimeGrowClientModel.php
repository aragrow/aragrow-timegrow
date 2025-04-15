<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowClientModel {

    private $table_name;
    private $table_name2;
    private $wpdb;

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $this->wpdb->prefix . 'users'; // Make sure this matches your table name
        $this->table_name2 = $this->wpdb->prefix . 'usermeta'; // Make sure this matches your table name
    }

    public function initialize() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

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
                "SELECT *
                FROM {$this->table_name}
                WHERE ID IN ($placeholders) 
                ORDER BY display_name",
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
            $sql = "SELECT a.* 
                    FROM {$this->table_name} a 
                    INNER JOIN {$this->table_name2} b
                        ON a.ID = b.user_id
                        AND b.meta_key = 'wp_capabilities'
                        AND b.meta_value LIKE '%customer%' 
                    ORDER BY a.display_name";
        }
    
        $result = $this->wpdb->get_results($sql);
        // var_dump($this->wpdb->last_query);
        return $result;

    }
    
}