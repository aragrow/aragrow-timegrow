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
    private $table_name2;
    private $table_name3;
    private $table_name4;
    private $table_name5;

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $this->wpdb->get_charset_collate();
        $this->table_name = $this->wpdb->prefix . TIMEGROW_PREFIX . 'team_member_tracker'; // Make sure this matches your table name
        $this->table_name2 = $this->wpdb->prefix . TIMEGROW_PREFIX . 'company_tracker'; // Make sure this matches your table name
        $this->table_name3 = $this->wpdb->prefix . 'users'; // Make sure this matches your table name
        $this->table_name4 = $this->wpdb->prefix . TIMEGROW_PREFIX . 'project_tracker'; // Make sure this matches your table name
        $this->table_name5 = $this->wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker'; // Make sure this matches your table name
        
        $this->allowed_fields = ['user_id', 'company_id',
                                'name', 'email',
                                'phone', 'title',
                                'bio', 'status',
                                'created_at', 'updated_at'];
    }

    public function initialize() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        $sql = "CREATE TABLE IF NOT EXISTS $this->table_name  (
            ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL UNIQUE,  
            company_id bigint(20) unsigned NOT NULL,  
            name varchar(25) NOT NULL,
            email varchar(255),
            phone varchar(20),
            title varchar(255),  
            bio text,           
            status smallint(1) NOT NULL DEFAULT 1,
            created_at timestamp,
            updated_at timestamp,
            PRIMARY KEY  (ID),
            FOREIGN KEY (company_id) REFERENCES {$this->table_name2}(ID),
            FOREIGN KEY (user_id) REFERENCES {$this->table_name3}(ID)
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
                "SELECT a.*, b.name AS company_name
                FROM {$this->table_name} a
                INNER JOIN {$this->table_name2} b ON a.company_id = b.ID
                WHERE a.ID IN ($placeholders) 
                ORDER BY a.name",
                $ids
            );
        }
        // If a single ID is provided
        elseif (intval($ids)) {
            $id = intval($ids); // Sanitize ID
            $sql = $this->wpdb->prepare(
                "SELECT a.*, b.name AS company_name 
                FROM {$this->table_name} a
                INNER JOIN {$this->table_name2} b ON a.company_id = b.ID
                WHERE a.ID = %d
                ORDER BY a.name",
                $id
            );
        }
        // If no IDs are provided, fetch all rows
        else {
            $sql = "SELECT a.*, b.name AS company_name
            FROM {$this->table_name} a
            INNER JOIN {$this->table_name2} b ON a.company_id = b.ID
            ORDER BY a.name";
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

     /**
     * Select projects by ID or array of IDs.
     *
     * @param int|array $ids Single ID or array of IDs.
     * @return array|object|null Array of expense objects or null if no results.
     */
    public function get_projects_for_member($ids = null) {
        if (WP_DEBUG) error_log(__CLASS__ . '::' . __FUNCTION__);
    
        //var_dump($ids);
        // If IDs are provided as an array
        if (is_array($ids)) {
            $ids = array_map('intval', $ids); // Sanitize IDs
            $placeholders = implode(',', array_fill(0, count($ids), '%d')); // Create placeholders for prepared statement
            $sql = $this->wpdb->prepare(
                "SELECT M.ID AS team_ID, M.name AS team_name, P.* 
                    FROM {$this->table_name} M  
                    JOIN {$this->table_name2} X ON M.ID = X.team_member_id
                    JOIN {$this->table_name4} P ON X.project_id = P.ID  
                    WHERE A.ID IN ($placeholders) 
                    ORDER BY M.name, P.name",
                $ids
            );
        }
        // If a single ID is provided
        elseif (intval($ids) and $ids == -1) {
            $id = intval($ids); // Sanitize ID
            $sql = $this->wpdb->prepare(
                "SELECT null AS team_ID, null AS team_name, P.* 
                    FROM {$this->table_name4} P 
                WHERE P.status = 1
                ORDER BY P.name",
                $id
            );
        }
        // If a single ID is provided
        elseif (intval($ids)) {
            $id = intval($ids); // Sanitize ID
            $sql = $this->wpdb->prepare(
                "SELECT M.ID AS team_ID, M.name AS team_name, P.* 
                    FROM {$this->table_name} M
                    JOIN {$this->table_name2} X ON M.ID = X.team_member_id
                    JOIN {$this->table_name4} P ON X.project_id = P.ID
                WHERE M.ID = %d
                AND P.status = 1
                ORDER BY P.name",
                $id
            );
        }
        // If no IDs are provided, fetch all rows
        else {
         
            $sql = "SELECT M.ID AS team_ID, M.name AS team_name, P.* 
            FROM {$this->table_name} M
            JOIN {$this->table_name2} X ON M.ID = X.team_member_id
            JOIN {$this->table_name4} P ON X.project_id = P.ID  
            WHERE M.status = 1
            AND P.status = 1
            ORDER BY M.name, P.name";
        }
    
        $results = $this->wpdb->get_results($sql);
        //var_dump($this->wpdb->last_query);
        if ($this->wpdb->last_error) {
            error_log("Database error: " . $this->wpdb->last_error);
            return new WP_Error('db_error', __('Database error occurred.', 'timegrow'));
        }
        if (empty($results)) {
            return null; // No results found
        }
        // Convert results to an array of objects
        return $results;
    }
    
    public function get_by_user_id($user_id) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $user_id = intval($user_id);
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE user_id = %d",
            $user_id
        );
        $result = $this->wpdb->get_row($sql);
        return $result ? $result : null; // Return the object or null if not found
    }

    public function get_team_member_clock_status($id) {
        $sql = $this->wpdb->prepare(
                "SELECT e.ID, e.clock_in_date, e.clock_out_date, p.name as project_name 
                    FROM {$this->table_name5} e
                    LEFT OUTER JOIN {$this->table_name4} p ON e.project_id = p.ID
                    WHERE member_id = %d
                    AND e.entry_type = 'CLOCK'
                    ORDER BY e.ID desc
                    LIMIT 1",
                $id
            );

        $result = $this->wpdb->get_results($sql);
        $item = $result[0];

        if (!empty($result)) {
            if (empty($item->clock_out_date) && !empty($item->clock_in_date)) {

                return [ // Example: Clocked IN
                    'status' => 'clocked_in',
                    'clockInTimestamp' => strtotime($item->clock_in_date),
                    'entryId' => $item->ID,
                    'cloked_project' => $item->project_name,
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