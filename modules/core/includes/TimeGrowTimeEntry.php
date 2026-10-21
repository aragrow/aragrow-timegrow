<?php
// #### TimeGrowTimeEntry.php ####

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class TimeGrowTimeEntry{

    public function __construct() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
   //     add_action('wp_ajax_save_expense', array($this, 'save_ajax'));
   //     add_action('wp_ajax_delete_expense', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('wp_ajax_save_company', array($this, 'save_ajax'));
        add_action('wp_ajax_delete_company', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles'));

        // An order's time when it is trashed, restored or deleted (on_order_removed())
        // WordPress post hooks (for legacy order storage)
        add_action('wp_trash_post', [$this, 'handle_order_trashed'], 10, 1);
        add_action('before_delete_post', [$this, 'handle_order_deleted'], 10, 1);
        add_action('untrashed_post', [$this, 'handle_order_restored'], 10, 1);

        // WooCommerce-specific hooks (for HPOS - High-Performance Order Storage)
        add_action('woocommerce_trash_order', [$this, 'handle_wc_order_trashed'], 10, 1);
        add_action('woocommerce_before_delete_order', [$this, 'handle_wc_order_deleted'], 10, 1);
        add_action('woocommerce_untrash_order', [$this, 'handle_wc_order_restored'], 10, 1);
        add_action('admin_notices', [__CLASS__, 'show_order_notices']);
    }

    /**
     * What happens to an order's time when the order is trashed, restored or deleted. Pure.
     *
     * - trash, restore: 'keep'. The trash can be undone, so the time stays billed on the order
     *   (cancel the invoice, or delete it for good, to bill the time again).
     * - delete with a payment recorded: 'detach'. The work was paid for: the time stays billed and is
     *   never billed again; only the link to the deleted order goes.
     * - delete with no payment: 'release'. The invoice never happened, so the time can be billed again.
     *
     * @param string $event          trash, restore or delete.
     * @param string $status         The order's status (no "wc-" prefix).
     * @param float  $payment_amount A payment recorded on the order (Manual Payments).
     */
    public static function on_order_removed($event, $status, $payment_amount) {
        if ($event !== 'delete') {
            return 'keep';
        }
        $paid_statuses = class_exists('Aragrow_TimeGrow_Order_Cancellation') ? Aragrow_TimeGrow_Order_Cancellation::PAID_STATUSES : ['partial_paid', 'invoice_paid'];
        return (in_array($status, $paid_statuses, true) || (float) $payment_amount > 0) ? 'detach' : 'release';
    }

    /** Order hooks for the posts table (legacy storage): only shop orders. */
    public function handle_order_trashed($post_id) {
        if (get_post_type($post_id) === 'shop_order') $this->order_removed('trash', $post_id);
    }

    public function handle_order_deleted($post_id) {
        if (get_post_type($post_id) === 'shop_order') $this->order_removed('delete', $post_id);
    }

    public function handle_order_restored($post_id) {
        if (get_post_type($post_id) === 'shop_order') $this->order_removed('restore', $post_id);
    }

    /** Order hooks for WooCommerce's order tables (HPOS). */
    public function handle_wc_order_trashed($order_id) {
        $this->order_removed('trash', $order_id);
    }

    public function handle_wc_order_deleted($order_id) {
        $this->order_removed('delete', $order_id);
    }

    public function handle_wc_order_restored($order_id) {
        $this->order_removed('restore', $order_id);
    }

    /** Apply on_order_removed() to a TimeGrow invoice, and tell the person what happened to its time. */
    private function order_removed($event, $order_id) {
        $order = wc_get_order($order_id);
        if (!$order || !$order->get_meta('_timekeeping_invoice')) {
            return;
        }
        $model = new TimeGrowTimeEntryModel();
        $count = (int) $model->get_entry_count_for_order($order_id);
        if ($count === 0) {
            return;
        }
        $status = $order->get_status() === 'trash' ? (string) $order->get_meta('_wp_trash_meta_status') : $order->get_status();
        $status = preg_replace('/^wc-/', '', $status);
        $action = self::on_order_removed($event, $status, $order->get_meta('_payment_amount'));
        $entries = sprintf(_n('%d time entry', '%d time entries', $count, 'timegrow'), $count);

        if ($action === 'release') {
            $model->unbill_entries_for_order($order_id);
            $message = sprintf(__('Order #%1$d was deleted. Its %2$s can be billed again.', 'timegrow'), (int) $order_id, $entries);
        } elseif ($action === 'detach') {
            $model->keep_billed_without_order($order_id);
            $message = sprintf(__('Order #%1$d was deleted. A payment was recorded on it, so its %2$s stay billed and won\'t be billed again.', 'timegrow'), (int) $order_id, $entries);
        } elseif ($event === 'trash') {
            $message = sprintf(__('Order #%1$d is in the trash. Its %2$s stay billed on it, so restoring it needs nothing else. To bill the time again, cancel the invoice instead, or delete it permanently.', 'timegrow'), (int) $order_id, $entries);
        } else {
            return; // restored: the time was never released
        }
        timegrow_log(sprintf('TimeGrow: order #%d %s: %s (%d entries)', (int) $order_id, $event, $action, $count));
        // Shown on the next page (the trash and delete actions redirect)
        if (get_current_user_id()) {
            $notes   = (array) get_transient('timegrow_order_notice_' . get_current_user_id());
            $notes[] = $message;
            set_transient('timegrow_order_notice_' . get_current_user_id(), array_slice(array_filter($notes), -5), 120);
        }
    }

    /** The messages from order_removed(), once. */
    public static function show_order_notices() {
        $key   = 'timegrow_order_notice_' . get_current_user_id();
        $notes = get_transient($key);
        if (!$notes) {
            return;
        }
        delete_transient($key);
        foreach ((array) $notes as $note) {
            echo '<div class="notice notice-info is-dismissible"><p><strong>TimeGrow:</strong> ' . esc_html($note) . '</p></div>';
        }
    }

    public function register_admin_menu() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

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
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        wp_enqueue_style('timegrow-modern-style', TIMEGROW_CORE_BASE_URI . 'assets/css/timegrow-modern.css');
        wp_enqueue_style('timegrow-forms-style', TIMEGROW_CORE_BASE_URI . 'assets/css/forms.css');
        wp_enqueue_style('timeflies-time-entries-style', TIMEGROW_CORE_BASE_URI . 'assets/css/time_entry.css');
        wp_enqueue_script('timeflies-time-entries-script', TIMEGROW_CORE_BASE_URI . 'assets/js/time_entry.js', array('jquery'), filemtime(TIMEGROW_CORE_BASE_DIR . 'assets/js/time_entry.js'), true);
        wp_enqueue_script('timegrow-project-picker', TIMEGROW_CORE_BASE_URI . 'assets/js/project-picker.js', array('jquery'), '1.0', true);
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
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        $model = new TimeGrowTimeEntryModel();
        $view = new TimeGrowTimeEntryView();
        $project_model = new TimeGrowProjectModel();
        $member_model = new TimeGrowTeamMemberModel();
        $controller = new TimeGrowTimeEntryController($model, $view, $project_model, $member_model);
        $controller->display_admin_page($screen);
    }
}
