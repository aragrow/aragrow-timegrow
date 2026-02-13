<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowExpenseReceiptModel {

    private $table_name;

    private $wpdb;
    private $charset_collate;

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        $this->table_name = $this->wpdb->prefix . TIMEGROW_PREFIX . 'expense_receipt_tracker'; // Make sure this matches your table name
    }

    public function initialize() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $sql = "CREATE TABLE IF NOT EXISTS  {$this->table_name} (
            ID BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            expense_id BIGINT(20) UNSIGNED NOT NULL,
            file_url TEXT NOT NULL,
            upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            extracted_data LONGTEXT,
            gemini_confidence DECIMAL(3,2),
            analyzed_at DATETIME,
            PRIMARY KEY (id),
            FOREIGN KEY (expense_id) REFERENCES {$this->table_name}(id) ON DELETE CASCADE
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
                "SELECT * FROM {$this->table_name} WHERE ID IN ($placeholders) ORDER BY file_url",
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
            $sql = "SELECT * FROM {$this->table_name} ORDER BY file_url";
        }
    
        return $this->wpdb->get_results($sql);
    }
    

     /**
     * Select expenses by ID or array of IDs.
     *
     * @param int|array $ids Single ID or array of IDs.
     * @return array|object|null Array of expense objects or null if no results.
     */
    public function select_by_expense($ids = null) {
        if (WP_DEBUG) error_log(__CLASS__ . '::' . __FUNCTION__);
    
        // If IDs are provided as an array
        if (is_array($ids)) {
            $ids = array_map('intval', $ids); // Sanitize IDs
            $placeholders = implode(',', array_fill(0, count($ids), '%d')); // Create placeholders for prepared statement
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE expense_id IN ($placeholders) ORDER BY file_url",
                $ids
            );
        }
        // If a single ID is provided
        elseif (intval($ids)) {
            $id = intval($ids); // Sanitize ID
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE expense_id = %d",
                $id
            );
        }
    
        return $this->wpdb->get_results($sql);
    }

    /**
     * Upload file to WordPress uploads directory
     * Separated from database insertion to allow Gemini analysis before saving
     *
     * @param array $file File array from $_FILES
     * @return array|WP_Error Upload result with 'url', 'file', 'type' or error
     */
    public function upload_file($file) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Handle file upload
        if (empty($file)) {
            return new WP_Error('empty_file', 'No file provided.');
        }

        // Define allowed MIME types
        $allowed_mime_types = [
            'image/jpeg',
            'image/png',
            'application/pdf',
        ];

        // Check if the file was uploaded
        if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'No file uploaded or upload error occurred.');
        }

        // Validate file type
        $file_type = wp_check_filetype($file['name']);
        if (!in_array($file_type['type'], $allowed_mime_types, true)) {
            return new WP_Error('invalid_file_type', 'Invalid file type. Allowed types are JPEG, PNG, and PDF.');
        }

        // Validate file size (max 512KB)
        $max_file_size = .5 * 1024 * 1024; // 512KB
        if ($file['size'] > $max_file_size) {
            return new WP_Error('file_too_large', 'File size exceeds the maximum limit of 512K.');
        }

        // Use WordPress functions to handle uploads
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Handle the upload using WordPress function
        $upload_overrides = ['test_form' => false];
        $upload_result = wp_handle_upload($file, $upload_overrides);

        // Check for upload errors
        if (isset($upload_result['error'])) {
            return new WP_Error('upload_error', $upload_result['error']);
        }

        return $upload_result;
    }

    /**
     * Save receipt record to database
     * Separated from file upload to allow Gemini analysis in between
     *
     * @param int $expense_id Expense ID to link receipt to
     * @param string $file_url URL of uploaded file
     * @param array $gemini_data Optional Gemini analysis data
     * @return int|false Receipt ID on success, false on failure
     */
    public function save_receipt_record($expense_id, $file_url, $gemini_data = null) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        global $wpdb;

        $data = [
            'expense_id' => intval($expense_id),
            'file_url' => esc_url_raw($file_url),
            'upload_date' => current_time('mysql'),
        ];

        // Add Gemini data if provided (optional columns)
        if ($gemini_data && is_array($gemini_data)) {
            if (isset($gemini_data['raw_gemini_response'])) {
                $data['extracted_data'] = $gemini_data['raw_gemini_response'];
            }
            if (isset($gemini_data['confidence'])) {
                $data['gemini_confidence'] = floatval($gemini_data['confidence']);
            }
            if (isset($gemini_data['analyzed_at'])) {
                $data['analyzed_at'] = $gemini_data['analyzed_at'];
            } else {
                $data['analyzed_at'] = current_time('mysql');
            }
        }

        $result = $wpdb->insert($this->table_name, $data);

        if ($result) {
            echo '<div class="notice notice-success is-dismissible"><p>File uploaded successfully!</p></div>';
            return $wpdb->insert_id;
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>Error saving file record to database.</p></div>';
            return false;
        }
    }

    /**
     * Legacy method for backward compatibility
     * Calls upload_file() and save_receipt_record() in sequence
     *
     * @param int $expense_id Expense ID
     * @param array $file File array from $_FILES
     * @return void
     */
    public function update($expense_id, $file) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        $upload_result = $this->upload_file($file);

        if (is_wp_error($upload_result)) {
            echo '<div class="notice notice-error is-dismissible"><p>Error uploading file: ' . esc_html($upload_result->get_error_message()) . '</p></div>';
            return;
        }

        $this->save_receipt_record($expense_id, $upload_result['url']);
    }

}