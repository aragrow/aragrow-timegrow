<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowTimeEntryModel {

    private $table_name;
    private $table_name2;
    private $table_name3;
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
            ID mediumint(9) NOT NULL AUTO_INCREMENT,
            project_id mediumint(9) NOT NULL,
            member_id mediumint(9) NOT NULL,
            clock_in_date datetime,
            clock_out_date datetime,
            date datetime,
            hours decimal(5,2),
            billable tinyint(1),
            billed tinyint(1) DEFAULT 0,
            description text,
            entry_type varchar(10),
            created_at timestamp,
            updated_at timestamp,
            PRIMARY KEY  (ID),
            FOREIGN KEY (project_id) REFERENCES {$this->table_name2}(ID),
            FOREIGN KEY (member_id) REFERENCES {$this->table_name3}(ID)
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
                "SELECT t.*, p.name as project_name, m.name as member_name, p.client_id
                    FROM {$this->table_name} t 
                    INNER JOIN {$this->table_name2} p ON t.project_id = p.ID
                    INNER JOIN {$this->table_name3} m ON t.member_id = m.ID
                    WHERE t.ID IN ($placeholders)  
                    ORDER BY t.date desc, m.name, p.name"
            );
        }
        // If a single ID is provided
        elseif (intval($ids)) {
            $id = intval($ids); // Sanitize ID
            $sql = $this->wpdb->prepare(
                "SELECT t.*, p.name as project_name, m.name as member_name, c.ID as client_id, c.name as client_name
                    FROM {$this->table_name} t
                    INNER JOIN {$this->table_name2} p ON t.project_id = p.ID
                    INNER JOIN {$this->table_name3} m ON t.member_id = m.ID
                    WHERE t.ID = %d",
                $id
            );
        }
        // If no IDs are provided, fetch all rows
        else {
            $sql = "SELECT t.*, p.name as project_name, m.name as member_name, , p.client_id 
                FROM {$this->table_name} t
                INNER JOIN {$this->table_name2} p ON t.project_id = p.ID
                INNER JOIN {$this->table_name3} m ON t.member_id = m.ID
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
        $id = intval($id); // Sanitize ID

        // Whitelist allowed fields to prevent SQL injection
        $sanitized_data = [];

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
            if ($sanitized_data['entry_type'] == 'IN' &&
                empty($sanitized_data['clock_in_date']) ) {
                    wp_die( 'Error: validation not passed.3', array( 'back_link' => true ) );
            } else if ( empty($sanitized_data['clock_out_date'])) {
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

        return $result;
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
            if (empty($sanitized_data['project_id']) || 
                empty($sanitized_data['member_id']) || 
                empty($sanitized_data['entry_type']) ) {
                wp_die( 'Error: validation not passed.1', array( 'back_link' => true ) );
            }
            if ($sanitized_data['entry_type'] == 'MAN' && (
                empty($sanitized_data['date']) || 
                empty($sanitized_data['hours']) )) {
                    wp_die( 'Error: validation not passed.2', array( 'back_link' => true ) );
            } else if ($sanitized_data['entry_type'] <> 'MAN')  {
                if ($sanitized_data['entry_type'] == 'IN' &&
                    empty($sanitized_data['clock_in_date']) ) {
                        wp_die( 'Error: validation not passed.3', array( 'back_link' => true ) );
                } else if ( empty($sanitized_data['clock_out_date'])) {
                    wp_die( 'Error: validation not passed.4', array( 'back_link' => true ) ); 
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
        $sql = "SELECT t.*, p.name as project_name, m.name as member_name, p.client_id 
        FROM {$this->table_name} t
        INNER JOIN {$this->table_name2} p ON t.project_id = p.ID
        INNER JOIN {$this->table_name3} m ON t.member_id = m.ID
        WHERE t.billed = 0 AND t.billable = 1
        ORDER BY t.client_id, t.project_id, t.date";
        
        $results = $this->wpdb->get_results($sql);

        return $results;

    }

    public static function mark_entries_as_billed($time_entries) {
        global $wpdb;
        $table = 'wp_time_entries'; // replace with your actual table

        foreach ($time_entries as $entry) {
            $wpdb->update(
                $table,
                ['billed' => 1, 'updated_at' => current_time('mysql')],
                ['ID' => $entry['ID']],
                ['%d', '%s'],
                ['%d']
            );
        }
    }

}