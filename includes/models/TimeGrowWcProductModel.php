<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowWcProductModel {

    private $table_name;
    private $wpdb;

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $this->wpdb->prefix . 'posts'; // Make sure this matches your table name
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
                "SELECT ID, post_title 
                FROM {$this->table_name}
                WHERE ID IN ($placeholders) 
                ORDER BY post_title",
                $ids
            );
        }
        // If a single ID is provided
        elseif (intval($ids)) {
            $id = intval($ids); // Sanitize ID
            $sql = $this->wpdb->prepare(
                "SELECT ID, post_title FROM {$this->table_name} WHERE ID = %d",
                $id
            );
        }
        // If no IDs are provided, fetch all rows
        else {
            $sql = "SELECT ID, post_title 
                    FROM {$this->table_name} 
                    WHERE post_type IN ('product')
                    AND post_status = 'publish'
                    ORDER BY post_title";
        }
    
        $results = $this->wpdb->get_results($sql);
        return $results;

    }
    
}