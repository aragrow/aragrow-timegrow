<?php
// #### TimeGrowTimeEntry.php ####

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class TimeGrowTimeEntry{

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
   //     add_action('wp_ajax_save_expense', array($this, 'save_ajax'));
   //     add_action('wp_ajax_delete_expense', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('wp_ajax_save_company', array($this, 'save_ajax'));
        add_action('wp_ajax_delete_company', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles'));

        // Hook into WooCommerce order deletion/trashing to reset billed status
        // WordPress post hooks (for legacy order storage)
        add_action('wp_trash_post', [$this, 'handle_order_trashed'], 10, 1);
        add_action('before_delete_post', [$this, 'handle_order_deleted'], 10, 1);
        add_action('untrashed_post', [$this, 'handle_order_restored'], 10, 1);

        // WooCommerce-specific hooks (for HPOS - High-Performance Order Storage)
        add_action('woocommerce_trash_order', [$this, 'handle_wc_order_trashed'], 10, 1);
        add_action('woocommerce_before_delete_order', [$this, 'handle_wc_order_deleted'], 10, 1);
        add_action('woocommerce_untrash_order', [$this, 'handle_wc_order_restored'], 10, 1);
    }

    /**
     * Handle when a WooCommerce order is trashed
     */
    public function handle_order_trashed($post_id) {
        // Check if this is a WooCommerce order
        if (get_post_type($post_id) === 'shop_order') {
            $order = wc_get_order($post_id);

            // Check if this is a TimeGrow order (has the meta flag)
            if ($order && $order->get_meta('_timekeeping_invoice')) {
                $model = new TimeGrowTimeEntryModel();

                // Get count of entries before unbilling
                $entry_count = $model->get_entry_count_for_order($post_id);

                if ($entry_count > 0) {
                    // Unbill the entries
                    $model->unbill_entries_for_order($post_id);

                    // Add admin notice
                    add_action('admin_notices', function() use ($post_id, $entry_count) {
                        echo '<div class="notice notice-warning is-dismissible">';
                        echo '<p><strong>TimeGrow Warning:</strong> Order #' . $post_id . ' has been moved to trash. ';
                        echo '<strong>' . $entry_count . '</strong> time ' . ($entry_count > 1 ? 'entries have' : 'entry has') . ' been unbilled and will be available for billing again.</p>';
                        echo '<p>If you restore this order from trash, you will need to re-process the time entries to bill them again.</p>';
                        echo '</div>';
                    });
                }
            }
        }
    }

    /**
     * Handle when a WooCommerce order is permanently deleted
     */
    public function handle_order_deleted($post_id) {
        // Check if this is a WooCommerce order
        if (get_post_type($post_id) === 'shop_order') {
            $order = wc_get_order($post_id);

            // Check if this is a TimeGrow order
            if ($order && $order->get_meta('_timekeeping_invoice')) {
                $model = new TimeGrowTimeEntryModel();

                // Get count before deleting
                $entry_count = $model->get_entry_count_for_order($post_id);

                if ($entry_count > 0) {
                    // Unbill the entries
                    $model->unbill_entries_for_order($post_id);

                    // Log the deletion
                    error_log('TimeGrow: Order #' . $post_id . ' permanently deleted. ' . $entry_count . ' time entries have been unbilled.');
                }
            }
        }
    }

    /**
     * Handle when a WooCommerce order is restored from trash
     * Note: This will re-bill the entries, but they won't be marked as billed
     * unless the order goes through the normal billing process again
     */
    public function handle_order_restored($post_id) {
        // We could optionally re-mark entries as billed here if needed
        // For now, we'll just log that an order was restored
        if (get_post_type($post_id) === 'shop_order') {
            error_log('WooCommerce order #' . $post_id . ' was restored from trash. Time entries remain unbilled.');
        }
    }

    /**
     * Handle WooCommerce order trashed (HPOS compatible)
     */
    public function handle_wc_order_trashed($order_id) {
        $order = wc_get_order($order_id);

        // Check if this is a TimeGrow order
        if ($order && $order->get_meta('_timekeeping_invoice')) {
            $model = new TimeGrowTimeEntryModel();

            // Get count of entries before unbilling
            $entry_count = $model->get_entry_count_for_order($order_id);

            if ($entry_count > 0) {
                // Unbill the entries
                $model->unbill_entries_for_order($order_id);

                // Add admin notice
                add_action('admin_notices', function() use ($order_id, $entry_count) {
                    echo '<div class="notice notice-warning is-dismissible">';
                    echo '<p><strong>TimeGrow Warning:</strong> Order #' . $order_id . ' has been moved to trash. ';
                    echo '<strong>' . $entry_count . '</strong> time ' . ($entry_count > 1 ? 'entries have' : 'entry has') . ' been unbilled and will be available for billing again.</p>';
                    echo '<p>If you restore this order from trash, you will need to re-process the time entries to bill them again.</p>';
                    echo '</div>';
                });
            }
        }
    }

    /**
     * Handle WooCommerce order deleted (HPOS compatible)
     */
    public function handle_wc_order_deleted($order_id) {
        $order = wc_get_order($order_id);

        // Check if this is a TimeGrow order
        if ($order && $order->get_meta('_timekeeping_invoice')) {
            $model = new TimeGrowTimeEntryModel();

            // Get count before deleting
            $entry_count = $model->get_entry_count_for_order($order_id);

            if ($entry_count > 0) {
                // Unbill the entries
                $model->unbill_entries_for_order($order_id);

                // Log the deletion
                error_log('TimeGrow: Order #' . $order_id . ' permanently deleted. ' . $entry_count . ' time entries have been unbilled.');
            }
        }
    }

    /**
     * Handle WooCommerce order restored (HPOS compatible)
     */
    public function handle_wc_order_restored($order_id) {
        error_log('WooCommerce order #' . $order_id . ' was restored from trash. Time entries remain unbilled.');
    }


    public function register_admin_menu() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        add_submenu_page(
            TIMEGROW_PARENT_MENU,
            'Time Entries',
            'Time Entries',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-time-entries-list',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'list' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null, // Hidden submenu for editing
            'Add New Time',
            'Add New Time',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-time-entry-add',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'add' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null, // Hidden submenu for editing
            'Edit Time',
            'Edit Time',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-time-entry-edit',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'edit' ); // Call the tracker_mvc method, passing the parameter
            },
        );

    }

    public function enqueue_scripts_styles() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        wp_enqueue_style('timegrow-modern-style', TIMEGROW_CORE_BASE_URI . 'assets/css/timegrow-modern.css');
        wp_enqueue_style('timegrow-forms-style', TIMEGROW_CORE_BASE_URI . 'assets/css/forms.css');
        wp_enqueue_style('timeflies-time-entries-style', TIMEGROW_CORE_BASE_URI . 'assets/css/time_entry.css');
        wp_enqueue_script('timeflies-time-entries-script', TIMEGROW_CORE_BASE_URI . 'assets/js/time_entry.js', array('jquery'), '1.0', true);
        wp_localize_script(
            'timeflies-companies-script',
            'timeflies_companies_list',
            [
                'list_url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-time-entries-list'),
                'nonce' => wp_create_nonce('timeflies_timeentry_nonce') // Pass the nonce to JS
            ]
        );
    }

    public function tracker_mvc_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $model = new TimeGrowTimeEntryModel();
        $view = new TimeGrowTimeEntryView();
        $project_model = new TimeGrowProjectModel();
        $member_model = new TimeGrowTeamMemberModel();
        $controller = new TimeGrowTimeEntryController($model, $view, $project_model, $member_model);
        $controller->display_admin_page($screen);
    }
}
