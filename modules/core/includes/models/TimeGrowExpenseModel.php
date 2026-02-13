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
        $this->allowed_fields = ['expense_name',
                                'expense_date',
                                'amount',
                                'expense_category_id',
                                'assigned_to',
                                'assigned_to_id',
                                'expense_description',
                                'expense_payment_method',
                                'updated_at',
                                'created_at'];
    }

    public function initialize() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (  -- Join table for many-to-many
            ID BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            expense_name VARCHAR(255) NOT NULL,
            expense_description text NOT NULL,
            expense_date date NOT NULL,
            expense_payment_method ENUM('personal_card', 'company_card', 'bank_transfer', 'cash', 'other') NOT NULL DEFAULT 'company_card',
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
     * Migrate category slugs to expense_category_id
     * Adds expense_category_id column, migrates data, then drops old category column
     * Safe to run multiple times - checks at each step
     */
    public function migrate_to_category_id() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Step 1: Check if new column already exists
        $new_column_exists = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SHOW COLUMNS FROM {$this->table_name} LIKE %s",
                'expense_category_id'
            )
        );

        // Step 2: Check if old column exists
        $old_column_exists = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SHOW COLUMNS FROM {$this->table_name} LIKE %s",
                'category'
            )
        );

        // If new column exists and old column is gone, migration is complete
        if (!empty($new_column_exists) && empty($old_column_exists)) {
            if(WP_DEBUG) error_log('Migration already completed - expense_category_id exists and category column removed');
            return true;
        }

        // If new column doesn't exist but old column does, start migration
        if (empty($new_column_exists) && !empty($old_column_exists)) {
            if(WP_DEBUG) error_log('Starting migration: creating expense_category_id column');

            // Add expense_category_id column
            $this->wpdb->query(
                "ALTER TABLE {$this->table_name}
                ADD COLUMN expense_category_id BIGINT(20) UNSIGNED NULL AFTER category,
                ADD INDEX idx_expense_category_id (expense_category_id)"
            );

            if(WP_DEBUG) error_log('Added expense_category_id column to expense_tracker table');
        }

        // If both columns exist, we need to migrate data
        if (!empty($new_column_exists) && !empty($old_column_exists)) {
            // Check if there are any expenses that need migration
            $needs_migration = $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_name}
                WHERE expense_category_id IS NULL AND category IS NOT NULL AND category != ''"
            );

            if ($needs_migration > 0) {
                if(WP_DEBUG) error_log("Found {$needs_migration} expenses that need migration");

                // Get category mapping
                $category_model = new TimeGrowExpenseCategoryModel();
                $categories = $category_model->get_all();

                if (empty($categories)) {
                    if(WP_DEBUG) error_log('ERROR: No expense categories found - cannot migrate');
                    return false;
                }

                // Create slug to ID mapping
                $slug_to_id = [];
                foreach ($categories as $cat) {
                    $slug_to_id[$cat->slug] = $cat->ID;
                }

                // Update expenses with category IDs
                $migrated_count = 0;
                foreach ($slug_to_id as $slug => $id) {
                    $result = $this->wpdb->update(
                        $this->table_name,
                        ['expense_category_id' => $id],
                        ['category' => $slug, 'expense_category_id' => null],
                        ['%d'],
                        ['%s', '%s']
                    );
                    if ($result !== false && $result > 0) {
                        $migrated_count += $result;
                    }
                }

                if(WP_DEBUG) error_log("Migrated {$migrated_count} expense records to use category IDs");
            } else {
                if(WP_DEBUG) error_log('All expenses already have category IDs - skipping data migration');
            }

            // Verify all expenses have been migrated
            $unmigrated = $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table_name}
                WHERE expense_category_id IS NULL"
            );

            if ($unmigrated > 0) {
                if(WP_DEBUG) error_log("WARNING: {$unmigrated} expenses still without category_id - keeping old category column");
                return false;
            }

            // All expenses migrated successfully - drop old column
            if(WP_DEBUG) error_log('All expenses migrated successfully - dropping old category column');

            $this->wpdb->query(
                "ALTER TABLE {$this->table_name} DROP COLUMN category"
            );

            if(WP_DEBUG) error_log('Migration completed successfully - old category column removed');
        }

        return true;
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
                "SELECT * FROM {$this->table_name} WHERE ID IN ($placeholders) ORDER BY expense_name",
                $ids
            );
        }
        // If a single ID is provided
        elseif (intval($ids)) {
            $id = intval($ids); // Sanitize ID
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE ID = %d ORDER BY expense_name",
                $id
            );
        }
        // If no IDs are provided, fetch all rows
        else {
            $sql = "SELECT * FROM {$this->table_name} ORDER BY expense_name";
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
            empty($sanitized_data['expense_date']) || 
            empty($sanitized_data['amount']) || 
            empty($sanitized_data['category']) ||   
            empty($sanitized_data['assigned_to']) ||
            empty($sanitized_data['expense_description']) ||
            empty($sanitized_data['expense_payment_method']) ) {
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
            if (empty($sanitized_data['expense_name']) || 
                empty($sanitized_data['expense_date']) || 
                empty($sanitized_data['amount']) || 
                empty($sanitized_data['category']) ||   
                empty($sanitized_data['assigned_to']) ||
                empty($sanitized_data['expense_description']) ||
                empty($sanitized_data['expense_payment_method']) ) {
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