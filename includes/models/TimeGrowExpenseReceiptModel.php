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
    
    public function update($expense_id, $file) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        global $wpdb;
        
         // Handle file upload
        if (empty($file)) return;
        
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
            return new WP_Error('invalid_file_type', 'Invalid file type. Allowed types are JPEG, PNG, PDF, DOC, and DOCX.');
        }

        // Validate file size (e.g., max 5MB)
        $max_file_size = .5 * 1024 * 1024; // 5 MB
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
        } else {
            $file_url = $upload_result['url'];

            // Insert file details into database
            $return = $wpdb->insert($this->table_name, 
            [
                'expense_id' => $expense_id, // Last inserted expense ID
                'file_url' => $file_url,
                'upload_date' => current_time('mysql'),
            ]);
            if ($return)
                echo '<div class="notice notice-success is-dismissible"><p>File uploaded successfully!</p></div>';
            else 
                echo '<div class="notice notice-error is-dismissible"><p>Error uploading file: ' . esc_html($upload_result['error']) . '</p></div>';
        }

    }

}