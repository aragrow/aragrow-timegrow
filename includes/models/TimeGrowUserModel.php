<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowUserModel {

    private $table_name;
    private $table_entry;
    private $table_project;
    private $wpdb;

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $this->wpdb->prefix . 'users'; // Make sure this matches your table name
        $this->table_entry = $this->wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker e'; // Make sure this matches your table name
        $this->table_project = $this->wpdb->prefix . TIMEGROW_PREFIX . 'time_project_tracker p'; // Make sure this matches your table name
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
                "SELECT * FROM {$this->table_name} WHERE ID IN ($placeholders) ORDER BY display_name",
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
            $sql = "SELECT * FROM {$this->table_name} ORDER BY display_name";
        }
    
        return $this->wpdb->get_results($sql);
    }
    
    public function get_current_user_clock_status($id) {
        $sql = $this->wpdb->prepare(
                "SELECT e.ID, e.clock_in_date, e.clock_out_date, p.name as project_name 
                    FROM {$this->table_entry} 
                    INNER JOIN {$this->table_project} ON e.project_id = p.ID
                    WHERE member_id = %d
                    AND e.entry_type = 'CLOCK'
                    ORDER BY e.ID desc
                    LIMIT TO 1",
                $id
            );

        $result = $this->wpdb->get_results($sql);
        var_dump($this->wpdb->last_query);
        var_dump($result);

        if (!empty($result)) {
            if ($result->clock_out_date == '0000-00-00 00:00:00' && !empty($result->clock_in_date)) {

                return [ // Example: Clocked IN
                    'status' => 'clocked_in',
                    'clockInTimestamp' => strtotime($result->clock_in_date),
                    'entryId' => $result->ID,
                    'cloked_project' => $result->project_name,
                ];
                // You may need to join with project table to get project name if needed
        
            } else {
                return [ // Example: Clocked OUT
                    'status' => 'clocked_out',
                ];
            }
        } else {
            return [ // Example: Clocked OUT
                'status' => 'clocked_out',
            ];
        }

    }

}