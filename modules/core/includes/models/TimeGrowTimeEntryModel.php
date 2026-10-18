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
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
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
                                'description', 'entry_type', 'billed_order_id', 'status',
                                'created_at', 'updated_at'];
    }

    public function initialize() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

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
            status tinyint(1) NOT NULL DEFAULT 1,
            created_at timestamp,
            updated_at timestamp,
            PRIMARY KEY  (ID),
            FOREIGN KEY (project_id) REFERENCES {$this->table_name2}(ID),
            FOREIGN KEY (member_id) REFERENCES {$this->table_name3}(ID),
            FOREIGN KEY (billed_order_id) REFERENCES {$this->table_order}(ID)
        ) $this->charset_collate;";

        dbDelta($sql);
        self::maybe_add_status_column();
    }

    /**
     * The status column (1 = active, 0 = inactive) on sites created before it: added once, every
     * existing entry active. Inactive entries stay in the database but are left out of everything
     * that counts time (reports, dashboard, invoicing, the Voice AI).
     */
    public static function maybe_add_status_column() {
        if (get_option('timegrow_time_entry_status_db') === '1') {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . TIMEGROW_PREFIX . 'time_entry_tracker';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return; // created later by initialize(), with the column
        }
        if (!$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'status'))) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN status tinyint(1) NOT NULL DEFAULT 1 AFTER billed_order_id");
        }
        if ($wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'status'))) {
            update_option('timegrow_time_entry_status_db', '1', false);
        }
    }

    /** Why the status can't be set, or ''. A billed entry stays active (unbill it first). Pure. */
    public static function status_problem($status, $billed) {
        return (int) $status === 0 && (int) $billed === 1
            ? __('A billed time entry can\'t be made inactive. Remove it from its invoice first.', 'timegrow')
            : '';
    }
    
    /**
     * Select expenses by ID or array of IDs.
     *
     * @param int|array $ids Single ID or array of IDs.
     * @return array|object|null Array of expense objects or null if no results.
     */
    public function select($ids = null) {
        timegrow_log(__CLASS__ . '::' . __FUNCTION__);
    
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
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        try {
            $id = intval($id); // Sanitize ID
            timegrow_log(print_r($data,true));
            // Whitelist allowed fields to prevent SQL injection
            $sanitized_data = [];
            timegrow_log(print_r($sanitized_data, true));
            foreach ($data as $key => $value) {
                if (in_array($key,  $this->allowed_fields , true)) {
                    $sanitized_data[$key] = sanitize_text_field($value); // Sanitize each field
                }
            }
            $id = intval($id); // Sanitize ID
            $before = TimeGrowTimeEntryAudit::row($id); // for the audit log
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
            if ($result !== false && $before) {
                $after = TimeGrowTimeEntryAudit::row($id);
                TimeGrowTimeEntryAudit::log($id, TimeGrowTimeEntryAudit::update_action($before, TimeGrowTimeEntryAudit::diff($before, (array) $after)), $before, $after);
            }

            return $id;

        } catch (Exception $e) {
            // Handle general exceptions
            timegrow_log("Exception: " . $e->getMessage());
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
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        try {
            // Whitelist allowed fields to prevent SQL injection
            $sanitized_data = [];

            timegrow_log(print_r($data,true));

            foreach ($data as $key => $value) {
                if (in_array($key,  $this->allowed_fields , true)) {
                    $sanitized_data[$key] = sanitize_text_field($value); // Sanitize each field
                }
            }
            timegrow_log('After Validating');
            timegrow_log(print_r($sanitized_data,true));

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
            timegrow_log($this->wpdb->last_query);
            
            if ($result === false) {
                return false;
            }

            $id = (int) $this->wpdb->insert_id;
            TimeGrowTimeEntryAudit::log($id, 'created', null, TimeGrowTimeEntryAudit::row($id));
            return $id;
        } catch (Exception $e) {
            // Handle general exceptions
            timegrow_log("Exception: " . $e->getMessage());
            echo "An error occurred: " . htmlspecialchars($e->getMessage());
            return new WP_Error(
                'An error occurred', // Error code
                __(htmlspecialchars($e->getMessage())), // Error message
                array('status' => 503) // Optional additional data
            );
        }
    }

    public function get_time_entries_to_bill($client_id = null, $start_date = null, $end_date = null) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;

        $where_clauses = ['t.billed = 0', 't.billable = 1', 't.status = 1']; // inactive entries are never billed
        $prepare_values = [];

        // Add client filter if provided
        if ($client_id && is_numeric($client_id)) {
            $where_clauses[] = 'p.client_id = %d';
            $prepare_values[] = (int) $client_id;
        }

        // Add date range filter if provided
        if ($start_date) {
            $where_clauses[] = 'COALESCE(t.date, t.clock_in_date) >= %s';
            $prepare_values[] = $start_date;
        }

        if ($end_date) {
            $where_clauses[] = 'COALESCE(t.date, t.clock_in_date) <= %s';
            $prepare_values[] = $end_date . ' 23:59:59';
        }

        $where_sql = implode(' AND ', $where_clauses);

        $sql = "SELECT t.*, p.name as project_name, m.name as member_name, p.client_id, u.display_name
        FROM {$this->table_name} t
        INNER JOIN {$this->table_name2} p ON t.project_id = p.ID
        INNER JOIN {$this->table_name3} m ON t.member_id = m.ID
        INNER JOIN {$this->table_user} u ON p.client_id = u.ID
        WHERE {$where_sql}
        ORDER BY p.client_id, t.project_id, t.ID";

        if (!empty($prepare_values)) {
            $sql = $wpdb->prepare($sql, $prepare_values);
        }

        $results = $this->wpdb->get_results($sql);

        return $results;

    }

    public function mark_time_entries_as_billed($time_entries) {
        global $wpdb;
        print('<br />>Marking time entries as billed');
        $entries_by_order = [];

        foreach ($time_entries as $entry) {
            // Validate inputs
            if (!is_numeric($entry->ID) || $entry->ID <= 0) {
                return new WP_Error('invalid_id', 'Invalid entry ID');
            }
            // Only entries that went onto an order are billed (not non-billable or skipped ones)
            if (empty($entry->billed_order_id)) continue;
            print('<br/>--->Marking entry ID: '.$entry->ID.' as billed (Order #'.$entry->billed_order_id.')');

            // Group entries by order for summary
            if (!isset($entries_by_order[$entry->billed_order_id])) {
                $entries_by_order[$entry->billed_order_id] = [];
            }
            $entries_by_order[$entry->billed_order_id][] = $entry;

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
            timegrow_log('Entry ID: ' . $entry->ID . ' marked as billed successfully.');
            TimeGrowTimeEntryAudit::log_many([(int) $entry->ID], 'billed', (int) $entry->billed_order_id);
        }

        return $entries_by_order;
    }

    /**
     * Get count of time entries associated with an order
     *
     * @param int $order_id The WooCommerce order ID
     * @return int Number of entries
     */
    public function get_entry_count_for_order($order_id) {
        global $wpdb;

        if (!is_numeric($order_id) || $order_id <= 0) {
            return 0;
        }

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE billed_order_id = %d",
            (int) $order_id
        ));

        return (int) $count;
    }

    /**
     * IDs of the time entries billed to an order.
     *
     * @param int $order_id The WooCommerce order ID
     * @return int[]
     */
    public function billed_entry_ids_for_order($order_id) {
        global $wpdb;

        if (!is_numeric($order_id) || $order_id <= 0) {
            return [];
        }

        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$this->table_name} WHERE billed_order_id = %d",
            (int) $order_id
        )));
    }

    /**
     * IDs among $ids that are billed right now (to any order).
     *
     * @param int[] $ids Time entry IDs
     * @return int[]
     */
    public function billed_among($ids) {
        global $wpdb;

        $ids = array_filter(array_map('intval', (array) $ids));
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$this->table_name} WHERE billed = 1 AND ID IN ($placeholders)",
            $ids
        )));
    }

    /**
     * Bill these entries to an order again (a cancelled order reopened). Only unbilled ones are touched.
     *
     * @param int[] $ids      Time entry IDs
     * @param int   $order_id The WooCommerce order ID
     * @return int|false Number of entries updated, or false on error
     */
    public function rebill_entries($ids, $order_id) {
        global $wpdb;

        $ids = array_filter(array_map('intval', (array) $ids));
        if (!$ids || !is_numeric($order_id) || $order_id <= 0) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table_name} SET billed = 1, billed_order_id = %d, updated_at = %s WHERE billed = 0 AND ID IN ($placeholders)",
            array_merge([(int) $order_id, current_time('mysql')], $ids)
        ));

        if ($result === false) {
            timegrow_log('Failed to bill time entries again for order #' . $order_id . ': ' . $wpdb->last_error);
        } elseif ($result > 0) {
            // Only the entries that are now on this order (some may have been billed elsewhere)
            $now = $wpdb->get_col($wpdb->prepare(
                "SELECT ID FROM {$this->table_name} WHERE billed_order_id = %d AND ID IN ($placeholders)",
                array_merge([(int) $order_id], $ids)
            ));
            TimeGrowTimeEntryAudit::log_many($now, 'rebilled', (int) $order_id);
        }
        return $result;
    }

    /**
     * Reset billed status for time entries when an order is trashed or deleted
     *
     * @param int $order_id The WooCommerce order ID
     * @return bool|int Number of entries updated, or false on error
     */
    public function unbill_entries_for_order($order_id) {
        global $wpdb;

        if (!is_numeric($order_id) || $order_id <= 0) {
            timegrow_log('Invalid order ID provided to unbill_entries_for_order: ' . $order_id);
            return false;
        }

        // The entries on this order, for the audit log
        $on_order = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$this->table_name} WHERE billed_order_id = %d", (int) $order_id));

        // Reset billed status for all time entries associated with this order
        $result = $wpdb->update(
            $this->table_name,
            [
                'billed' => 0,
                'billed_order_id' => null,
                'updated_at' => current_time('mysql')
            ],
            ['billed_order_id' => (int) $order_id],
            ['%d', '%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            timegrow_log('Failed to unbill time entries for order #' . $order_id . ': ' . $wpdb->last_error);
            return false;
        }

        if ($result > 0) {
            timegrow_log('Successfully unbilled ' . $result . ' time entries for deleted/trashed order #' . $order_id);
            TimeGrowTimeEntryAudit::log_many($on_order, 'unbilled', (int) $order_id);
        }

        return $result;
    }

}