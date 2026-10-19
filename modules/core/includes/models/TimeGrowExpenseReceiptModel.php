<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Expense receipts: uploaded files and their records.
 *
 * Receipts are private. They are stored in uploads/timegrow-receipts/YYYY/MM/ with a random,
 * unguessable file name, the folder refuses direct web access (.htaccess; on nginx the random names
 * are the protection), and they are opened only through view_url(), which checks the person may see
 * expenses. Receipts uploaded before this were moved there once by migrate().
 */
class TimeGrowExpenseReceiptModel {

    const DIR   = 'timegrow-receipts';
    const NONCE = 'timegrow_view_receipt';

    private $table_name;

    private $wpdb;
    private $charset_collate;

    public function __construct() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        $this->table_name = $this->wpdb->prefix . TIMEGROW_PREFIX . 'expense_receipt_tracker'; // Make sure this matches your table name
    }

    public function initialize() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
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
        timegrow_log(__CLASS__ . '::' . __FUNCTION__);
    
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
        timegrow_log(__CLASS__ . '::' . __FUNCTION__);
    
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
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

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

        // Into the private receipts folder, with a random file name
        $upload_overrides = ['test_form' => false, 'unique_filename_callback' => [__CLASS__, 'random_name']];
        add_filter('upload_dir', [__CLASS__, 'private_dir']);
        $upload_result = wp_handle_upload($file, $upload_overrides);
        remove_filter('upload_dir', [__CLASS__, 'private_dir']);
        if (!isset($upload_result['error'])) {
            self::protect(trailingslashit(wp_get_upload_dir()['basedir']) . self::DIR);
        }

        // Check for upload errors
        if (isset($upload_result['error'])) {
            return new WP_Error('upload_error', $upload_result['error']);
        }

        return $upload_result;
    }

    // ------------------------------------------------------------------
    // Private storage
    // ------------------------------------------------------------------

    /** upload_dir filter: uploads/timegrow-receipts/YYYY/MM. */
    public static function private_dir($dirs) {
        $dirs['subdir'] = '/' . self::DIR . $dirs['subdir'];
        $dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
        $dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
        return $dirs;
    }

    /** A random, unguessable file name that keeps the (checked) extension. */
    public static function random_name($dir, $name, $ext) {
        return strtolower(wp_generate_password(32, false, false)) . strtolower($ext);
    }

    /** Refuse direct web access to the receipts folder (Apache), and no folder listing. */
    public static function protect($dir) {
        if (!wp_mkdir_p($dir)) {
            return;
        }
        $htaccess = trailingslashit($dir) . '.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "# TimeGrow receipts are opened through WordPress only\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
        }
        if (!file_exists(trailingslashit($dir) . 'index.php')) {
            @file_put_contents(trailingslashit($dir) . 'index.php', "<?php // Silence is golden.\n");
        }
    }

    /** The link to open a receipt: through WordPress, with a permission check. */
    public static function view_url($receipt_id) {
        return wp_nonce_url(add_query_arg(['action' => 'timegrow_view_receipt', 'id' => (int) $receipt_id], admin_url('admin-post.php')), self::NONCE . '_' . (int) $receipt_id);
    }

    /** admin-post timegrow_view_receipt: send the receipt to someone who may see expenses. */
    public static function handle_view() {
        $id = (int) ($_GET['id'] ?? 0);
        if (!current_user_can(TIMEGROW_OWNER_CAP)) {
            wp_die(esc_html__('You are not allowed to see receipts.', 'timegrow'), 403);
        }
        check_admin_referer(self::NONCE . '_' . $id);
        $row  = (new self())->select($id);
        $row  = is_array($row) ? ($row[0] ?? null) : $row;
        $path = $row && class_exists('TimeGrowAIReceiptAnalyzer') ? TimeGrowAIReceiptAnalyzer::path_from_url($row->file_url) : '';
        if (!$path || !is_readable($path)) {
            wp_die(esc_html__('That receipt was not found.', 'timegrow'), 404);
        }
        $type = wp_check_filetype($path);
        if (!in_array($type['type'], ['image/jpeg', 'image/png', 'application/pdf'], true)) {
            wp_die(esc_html__('That receipt was not found.', 'timegrow'), 404);
        }
        nocache_headers();
        header('Content-Type: ' . $type['type']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="receipt-' . $id . '.' . $type['ext'] . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /**
     * Once: move receipts uploaded before private storage into the receipts folder, with a random
     * name. Each file is copied, checked, its record updated, and only then the old file removed.
     */
    public static function migrate() {
        if (get_option('timegrow_receipts_private') === '1' || !class_exists('TimeGrowAIReceiptAnalyzer')) {
            return;
        }
        global $wpdb;
        $table   = $wpdb->prefix . TIMEGROW_PREFIX . 'expense_receipt_tracker';
        $uploads = wp_get_upload_dir();
        $private = trailingslashit($uploads['basedir']) . self::DIR;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
            update_option('timegrow_receipts_private', '1', false);
            return;
        }
        self::protect($private);
        $left = 0;
        foreach ((array) $wpdb->get_results("SELECT ID, file_url FROM {$table}") as $r) {
            $path = TimeGrowAIReceiptAnalyzer::path_from_url($r->file_url);
            if (!$path || strpos($path, realpath($private) ?: $private) === 0) {
                continue; // missing, or already private
            }
            $sub  = '/' . self::DIR . '/' . gmdate('Y/m', filemtime($path) ?: time());
            $dir  = $uploads['basedir'] . $sub;
            wp_mkdir_p($dir);
            $name = self::random_name($dir, basename($path), '.' . strtolower(pathinfo($path, PATHINFO_EXTENSION)));
            $dest = $dir . '/' . $name;
            if (!@copy($path, $dest) || filesize($dest) !== filesize($path)) {
                @unlink($dest);
                $left++;
                continue;
            }
            $updated = $wpdb->update($table, ['file_url' => $uploads['baseurl'] . $sub . '/' . $name], ['ID' => (int) $r->ID], ['%s'], ['%d']);
            if ($updated === false) {
                @unlink($dest);
                $left++;
                continue;
            }
            @unlink($path);
        }
        if ($left === 0) {
            update_option('timegrow_receipts_private', '1', false);
        }
        timegrow_log(sprintf('Receipts moved to private storage%s', $left ? sprintf(' (%d could not be moved yet)', $left) : ''));
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
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

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
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        $upload_result = $this->upload_file($file);

        if (is_wp_error($upload_result)) {
            echo '<div class="notice notice-error is-dismissible"><p>Error uploading file: ' . esc_html($upload_result->get_error_message()) . '</p></div>';
            return;
        }

        $this->save_receipt_record($expense_id, $upload_result['url']);
    }

}