<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowTimeEntryModel {

    private $table_name;
    private $table_name2;
    private $table_name3;
    private $table_name4;
    private $table_user;
    private $table_order;
    private $wpdb;
    private $charset_collate;
    private $allowed_fields;

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        $this->table_name = $this->wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker'; // Make sure this matches your table name
        $this->table_name2 = $this->wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker'; // Make sure this matches your table name
        $this->table_name3 = $this->wpdb->prefix . TIMEGROW_PREFIX . 'team_member_tracker'; // Make sure this matches your table name
        $this->table_name4 = $this->wpdb->prefix . TIMEGROW_PREFIX . 'client_tracker'; // Make sure this matches your table name
        $this->table_user = $this->wpdb->prefix . 'users'; // Make sure this matches your table name
        $this->table_order = $this->wpdb->prefix . 'wc_orders'; // WooCommerce orders table
    
        $this->allowed_fields = ['project_id', 'member_id', 
                                'clock_in_date', 'clock_out_date', 
                                'date','hours', 'billable', 'billed',
                                'description', 'entry_type', 
                                'created_at', 'updated_at'];
    }

    public function initialize() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        global $wpdb;                  

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            project_id bigint(20) unsigned NOT NULL,
            member_id bigint(20) unsigned NOT NULL,
            clock_in_date datetime,
            clock_out_date datetime,
            date datetime,
            hours decimal(5,2),
            billable tinyint(1),
            billed tinyint(1) DEFAULT 0,
            description text,
            entry_type varchar(10),
            billed_order_id bigint(20) unsigned DEFAULT NULL,
            created_at timestamp,
            updated_at timestamp,
            PRIMARY KEY  (ID),
            FOREIGN KEY (project_id) REFERENCES {$this->table_name2}(ID),
            FOREIGN KEY (member_id) REFERENCES {$this->table_name3}(ID),
            FOREIGN KEY (billed_order_id) REFERENCES {$this->table_order}(ID)
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
                "SELECT t.*, p.name as project_name, m.name as member_name, p.client_id, u.display_name as client_name
                    FROM {$this->table_name} t
                    INNER JOIN {$this->table_name2} p ON t.project_id = p.ID
                    INNER JOIN {$this->table_name3} m ON t.member_id = m.ID
                    INNER JOIN {$this->table_user} u ON p.client_id = u.ID
                    WHERE t.ID IN ($placeholders)  
                    ORDER BY t.date desc, m.name, p.name"
            );
        }
        // If a single ID is provided
        elseif (intval($ids)) {
            $id = intval($ids); // Sanitize ID
            $sql = $this->wpdb->prepare(
                "SELECT t.*, p.name as project_name, m.name as member_name, p.client_id, u.display_name as client_name
                    FROM {$this->table_name} t
                    INNER JOIN {$this->table_name2} p ON t.project_id = p.ID
                    INNER JOIN {$this->table_name3} m ON t.member_id = m.ID
                    INNER JOIN {$this->table_user} u ON p.client_id = u.ID
                    WHERE t.ID = %d",
                $id
            );
        }
        // If no IDs are provided, fetch all rows
        else {
            $sql = "SELECT t.*, p.name as project_name, m.name as member_name, p.client_id, u.display_name as client_name
                FROM {$this->table_name} t
                INNER JOIN {$this->table_name2} p ON t.project_id = p.ID
                INNER JOIN {$this->table_name3} m ON t.member_id = m.ID
                INNER JOIN {$this->table_user} u ON p.client_id = u.ID
                ORDER BY t.date desc, m.name, p.name";
        }
        $results = $this->wpdb->get_results($sql);
        //var_dump($this->wpdb->last_query);
        return $results;
  
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
        try {
            $id = intval($id); // Sanitize ID
            error_log(print_r($data,true));
            // Whitelist allowed fields to prevent SQL injection
            $sanitized_data = [];
            error_log(print_r($sanitized_data, true));
            foreach ($data as $key => $value) {
                if (in_array($key,  $this->allowed_fields , true)) {
                    $sanitized_data[$key] = sanitize_text_field($value); // Sanitize each field
                }
            }
            $id = intval($id); // Sanitize ID
            // Ensure all required fields are present
            if (empty($sanitized_data['entry_type']) ) {
                wp_die( 'Error: validation not passed.1', array( 'back_link' => true ) );
            } 
            if ($sanitized_data['entry_type'] == 'MAN' && (
                empty($sanitized_data['date']) || 
                empty($sanitized_data['hours']) )) {
                    wp_die( 'Error: validation not passed.2', array( 'back_link' => true ) );
            } else if ($sanitized_data['entry_type'] <> 'MAN')  {
                if (empty($sanitized_data['clock_in_date']) 
                    && empty($sanitized_data['clock_out_date'])) {
                        wp_die( 'Error: validation not passed.3', array( 'back_link' => true ) );
                } elseif (empty($sanitized_data['clock_out_date'])) {
                    wp_die( 'Error: validation not passed.4', array( 'back_link' => true ) );
                }
            }
            
            $result = $this->wpdb->update(
                $this->table_name,
                $sanitized_data,
                ['id' => $id],
                $format, 
                '%d'  // integer
            );

            return $id;

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

            error_log(print_r($data,true));

            foreach ($data as $key => $value) {
                if (in_array($key,  $this->allowed_fields , true)) {
                    $sanitized_data[$key] = sanitize_text_field($value); // Sanitize each field
                }
            }
            error_log('After Validating');
            error_log(print_r($sanitized_data,true));

            // Ensure all required fields are present
            if (empty($sanitized_data['member_id']) || 
                empty($sanitized_data['entry_type']) ) {
                wp_die( 'Error: validation not passed.1', array( 'back_link' => true ) );
            }
            if ($sanitized_data['entry_type'] == 'MAN' && (
                empty($sanitized_data['date']) || 
                empty($sanitized_data['hours']) )) {
                    wp_die( 'Error: validation not passed.2', array( 'back_link' => true ) );
            } elseif ($sanitized_data['entry_type'] <> 'MAN')  {
            
                if ($sanitized_data['entry_type'] == 'IN' &&
                    empty($sanitized_data['clock_in_date']) ) {
                        wp_die( 'Error: validation not passed.3', array( 'back_link' => true ) );
                } 
                
            }

            $result = $this->wpdb->insert(
                $this->table_name,
                $sanitized_data,
                $format // string
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

    public function get_time_entries_to_bill() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $sql = "SELECT t.*, p.name as project_name, m.name as member_name, p.client_id, u.display_name
        FROM {$this->table_name} t
        INNER JOIN {$this->table_name2} p ON t.project_id = p.ID
        INNER JOIN {$this->table_name3} m ON t.member_id = m.ID
        INNER JOIN {$this->table_user} u ON p.client_id = u.ID
        WHERE t.billed = 0 AND t.billable = 1
        ORDER BY p.client_id, t.project_id, t.ID";
        
        $results = $this->wpdb->get_results($sql);

        return $results;

    }

    public function mark_time_entries_as_billed($time_entries) {
        global $wpdb;
        print('<br />>Marking time entries as billed');
        foreach ($time_entries as $entry) {
            // Validate inputs
            if (!is_numeric($entry->ID) || $entry->ID <= 0) {
                return new WP_Error('invalid_id', 'Invalid entry ID');
            }
            print('<br/>--->Marking entry ID: '.$entry->ID.' as billed');
            // Ensure the entry ID is an integer
            $result = $wpdb->update(
                $this->table_name,
                ['billed' => 1, 'billed_order_id' => $entry->billed_order_id, 'updated_at' => current_time('mysql')],
                ['ID' => (int) $entry->ID],
                ['%d', '%d', '%s'],
                ['%d']
            );
            if ($result === false) {
                // Handle error
                print('<br />Database update failed: ' . $wpdb->last_error);
                return false;
            }
            // Optionally, you can log the success or perform other actions
            error_log('Entry ID: ' . $entry->ID . ' marked as billed successfully.');   
        }
    }

}