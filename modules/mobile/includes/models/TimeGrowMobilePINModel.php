<?php
/**
 * TimeGrow Mobile PIN Model
 *
 * Database model for managing mobile PIN records
 *
 * @package TimeGrow
 * @subpackage Mobile
 * @since 2.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowMobilePINModel {

    /**
     * @var wpdb WordPress database object
     */
    private $wpdb;

    /**
     * @var string Table name
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'timegrow_mobile_pins';
    }

    /**
     * Get PIN record by user ID
     *
     * @param int $user_id User ID
     * @return object|null PIN record or null
     */
    public function get_by_user_id($user_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE user_id = %d",
                $user_id
            )
        );
    }

    /**
     * Create or update PIN record
     *
     * @param int $user_id User ID
     * @param string $pin_hash Hashed PIN
     * @param string $pin_salt Salt used for hashing
     * @return bool Success status
     */
    public function upsert($user_id, $pin_hash, $pin_salt) {
        $existing = $this->get_by_user_id($user_id);

        $data = [
            'user_id' => $user_id,
            'pin_hash' => $pin_hash,
            'pin_salt' => $pin_salt,
            'is_active' => 1,
            'failed_attempts' => 0,
            'locked_until' => null,
        ];

        if ($existing) {
            // Update existing record
            return $this->wpdb->update(
                $this->table_name,
                $data,
                ['user_id' => $user_id],
                ['%d', '%s', '%s', '%d', '%d', '%s'],
                ['%d']
            ) !== false;
        } else {
            // Insert new record
            return $this->wpdb->insert(
                $this->table_name,
                $data,
                ['%d', '%s', '%s', '%d', '%d', '%s']
            ) !== false;
        }
    }

    /**
     * Increment failed login attempts
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function increment_failed_attempts($user_id) {
        return $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->table_name}
                SET failed_attempts = failed_attempts + 1
                WHERE user_id = %d",
                $user_id
            )
        ) !== false;
    }

    /**
     * Lock account for specified duration
     *
     * @param int $user_id User ID
     * @param int $duration Duration in seconds (default 900 = 15 minutes)
     * @return bool Success status
     */
    public function lock_account($user_id, $duration = 900) {
        $locked_until = gmdate('Y-m-d H:i:s', time() + $duration);

        return $this->wpdb->update(
            $this->table_name,
            ['locked_until' => $locked_until],
            ['user_id' => $user_id],
            ['%s'],
            ['%d']
        ) !== false;
    }

    /**
     * Reset failed attempts and unlock account
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function reset_failed_attempts($user_id) {
        return $this->wpdb->update(
            $this->table_name,
            [
                'failed_attempts' => 0,
                'locked_until' => null,
            ],
            ['user_id' => $user_id],
            ['%d', '%s'],
            ['%d']
        ) !== false;
    }

    /**
     * Update last login timestamp
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function update_last_login($user_id) {
        return $this->wpdb->update(
            $this->table_name,
            ['last_login_at' => current_time('mysql')],
            ['user_id' => $user_id],
            ['%s'],
            ['%d']
        ) !== false;
    }


    /**
     * Activate or deactivate mobile access
     *
     * @param int $user_id User ID
     * @param bool $is_active Active status
     * @return bool Success status
     */
    public function set_active($user_id, $is_active) {
        return $this->wpdb->update(
            $this->table_name,
            ['is_active' => $is_active ? 1 : 0],
            ['user_id' => $user_id],
            ['%d'],
            ['%d']
        ) !== false;
    }

    /**
     * Get all active mobile users
     *
     * @return array Array of PIN records
     */
    public function get_all_active() {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE is_active = 1 ORDER BY created_at DESC"
        );
    }

    /**
     * Delete PIN record
     *
     * @param int $user_id User ID
     * @return bool Success status
     */
    public function delete($user_id) {
        return $this->wpdb->delete(
            $this->table_name,
            ['user_id' => $user_id],
            ['%d']
        ) !== false;
    }
}
