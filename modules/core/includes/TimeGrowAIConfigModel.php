<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowAIConfigModel {

    private $wpdb;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        // Follow TimeGrow naming convention: wp_timegrow_ai_config_tracker
        $this->table_name = $this->wpdb->prefix . TIMEGROW_PREFIX . 'ai_config_tracker';
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__ . ' - Table: ' . $this->table_name);
    }

    /**
     * Create the AI configurations table
     */
    public function create_table() {
        
        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            config_name varchar(255) NOT NULL,
            ai_provider varchar(100) NOT NULL,
            ai_model varchar(100) NOT NULL,
            ai_api_key text NOT NULL,
            enable_auto_analysis tinyint(1) DEFAULT 0,
            confidence_threshold decimal(3,2) DEFAULT 0.70,
            is_active tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY is_active (is_active)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get all configurations
     */
    public function get_all() {
                return $this->wpdb->get_results("SELECT * FROM {$this->table_name} ORDER BY created_at DESC", ARRAY_A);
    }

    /**
     * Get active configuration
     */
    public function get_active() {
                return $this->wpdb->get_row("SELECT * FROM {$this->table_name} WHERE is_active = 1 LIMIT 1", ARRAY_A);
    }

    /**
     * Get configuration by ID
     */
    public function get_by_id($id) {
                return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d", $id), ARRAY_A);
    }

    /**
     * Create new configuration
     */
    public function create($data) {
        
        // If this config is set as active, deactivate all others
        if (!empty($data['is_active'])) {
            $this->wpdb->update($this->table_name, ['is_active' => 0], ['is_active' => 1]);
        }

        $this->wpdb->insert($this->table_name, [
            'config_name' => $data['config_name'],
            'ai_provider' => $data['ai_provider'],
            'ai_model' => $data['ai_model'],
            'ai_api_key' => $data['ai_api_key'],
            'enable_auto_analysis' => !empty($data['enable_auto_analysis']) ? 1 : 0,
            'confidence_threshold' => $data['confidence_threshold'] ?? 0.7,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ]);

        return $this->wpdb->insert_id;
    }

    /**
     * Update configuration
     */
    public function update($id, $data) {
        
        // If this config is set as active, deactivate all others
        if (!empty($data['is_active'])) {
            $this->wpdb->update($this->table_name, ['is_active' => 0], ['is_active' => 1]);
        }

        return $this->wpdb->update(
            $this->table_name,
            [
                'config_name' => $data['config_name'],
                'ai_provider' => $data['ai_provider'],
                'ai_model' => $data['ai_model'],
                'ai_api_key' => $data['ai_api_key'],
                'enable_auto_analysis' => !empty($data['enable_auto_analysis']) ? 1 : 0,
                'confidence_threshold' => $data['confidence_threshold'] ?? 0.7,
                'is_active' => !empty($data['is_active']) ? 1 : 0,
            ],
            ['id' => $id]
        );
    }

    /**
     * Delete configuration
     */
    public function delete($id) {
                return $this->wpdb->delete($this->table_name, ['id' => $id]);
    }

    /**
     * Set configuration as active
     */
    public function set_active($id) {

        // Deactivate all
        $this->wpdb->update($this->table_name, ['is_active' => 0], ['is_active' => 1]);

        // Activate the selected one
        return $this->wpdb->update($this->table_name, ['is_active' => 1], ['id' => $id]);
    }

    /**
     * Clean up old WordPress options from previous implementation
     */
    public static function cleanup_old_options() {
        $options_to_delete = [
            'aragrow_timegrow_ai_settings',
            'aragrow_timegrow_ai_configurations',
            'aragrow_timegrow_ai_form_temp',
        ];

        foreach ($options_to_delete as $option) {
            delete_option($option);
            if(WP_DEBUG) error_log('Deleted old AI option: ' . $option);
        }

        // Delete transients
        delete_transient('timegrow_api_key_saved');

        if(WP_DEBUG) error_log('Old AI configuration options cleaned up');

        return true;
    }
}
