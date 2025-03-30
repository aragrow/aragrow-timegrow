<?php
// includes/companies.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Timeflies_Expenses_Admin {
    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('wp_ajax_save_expense', array($this, 'save_ajax'));
        add_action('wp_ajax_delete_expense', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles'));
    }

    public function enqueue_scripts_styles() {
        wp_enqueue_style('timeflies-expenses-style', ARAGROW_TIMEGROW_BASE_URI . 'assets/css/expense.css');
        wp_enqueue_script('timeflies-expenses-script', ARAGROW_TIMEGROW_BASE_URI . 'assets/js/expense.js', array('jquery'), '1.0', true);
        wp_localize_script(
            'timeflies-expenses-script',
            'timeflies_expenses_list',
            [
                'list_url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expenses-list'),
                'nonce' => wp_create_nonce('timeflies_expense_nonce') // Pass the nonce to JS
            ]
        );
    }

    public function save_ajax() {

        try {

            if (WP_DEBUG) error_log('Exc: Timeflies_Expenses_Admin.save_ajax()');
            check_ajax_referer('timeflies_expense_nonce', 'timeflies_expense_nonce_field');
        
            if (WP_DEBUG) error_log('--> Validation Passed!!');

            $expense_id = intval($_POST['expense_id']);
            $expense_name = sanitize_text_field($_POST['expense_name']);
            $amount = floatval($_POST['amount']);
            $category = sanitize_text_field($_POST['category']);
            $assigned_to = sanitize_text_field($_POST['assigned_to']);

            $wpdb->insert($this->table_name, [
                'expense_name' => $expense_name,
                'amount' => $amount,
                'category' => $category,
                'assigned_to' => ($category !== 'general') ? $assigned_to : null,
            ]);

            $current_date = current_time('mysql');

            $expense_data = array(
                'expense_name' => $expense_name,
                'amount' => $amount,
                'category' => $category,
                'assinged_to' => $assigned_to,
                'updated_at' => $current_date, // Add updated_at timestamp
            );


            global $wpdb;
            $prefix = $wpdb->prefix;

            if ($expense_id === 0) {
                $expense_data['created_at'] = $current_date; // Add created_at timestamp
                $wpdb->insert("{$prefix}timeflies_clients", $expense_data, array(
                    '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'
                ));
                $expense_id = $wpdb->insert_id;
            } else {
                $wpdb->update("{$prefix}timeflies_clients", $expense_data, array('ID' => $expense_id), array(
                    '%d', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s'
                ));
            }

            $this->process_receipts($expense_id,$_FILES['file_upload']);
        
            wp_send_json_success(array('message' => 'Client Saved.', 'client_id' => $client_id));
        
        } catch (Exception $e) {

            if(WP_DEBUG) error_log('Timeflies_Clients_Admin.save_ajax() -> '.$e->getMessage());

            wp_send_json_success(array('message' => $e->getMessage(), 'client_id' => $client_id));
            
        } finally {

            if(WP_DEBUG) error_log('Timeflies_ClientsAdmin.save_ajax() -> Finalized');
            // Optional block of code that always executes
        }

    }

    public function process_receipts($expense_id, $uploaded_file) {
        // Handle file upload
        if (!empty($uploaded_file)) {
            
            // Use WordPress functions to handle uploads
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');

            $upload_overrides = ['test_form' => false];
            $upload_result = wp_handle_upload($uploaded_file, $upload_overrides);

            if (!isset($upload_result['error'])) {
                $file_url = $upload_result['url'];

                // Insert file details into database
                $wpdb->insert($wpdb->prefix . 'expense_files', [
                    'expense_id' => $expense_id, // Last inserted expense ID
                    'file_url' => $file_url,
                    'upload_date' => current_time('mysql'),
                ]);
                
                echo '<div class="notice notice-success is-dismissible"><p>File uploaded successfully!</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>Error uploading file: ' . esc_html($upload_result['error']) . '</p></div>';
            }
        }
    }

}

Timeflies_Expenses_Admin::get_instance();