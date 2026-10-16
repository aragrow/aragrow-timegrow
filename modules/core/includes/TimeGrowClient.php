<?php
// includes/companies.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class TimeGrowClient{

    public function __construct() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
   //     add_action('wp_ajax_save_expense', array($this, 'save_ajax'));
   //     add_action('wp_ajax_delete_expense', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('wp_ajax_save_client', array($this, 'save_ajax'));
        add_action('wp_ajax_delete_client', array($this, 'delete_ajax')); // Add delete action
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles'));
        add_action('load-user-new.php', array($this, 'lock_new_user_role'));
        add_action('edit_user_created_user', array($this, 'force_customer_role'));
    }

    /**
     * Add Client (user-new.php?customer=y): pre-select the Customer role and lock the dropdown.
     */
    public function lock_new_user_role() {
        if (empty($_GET['customer']) || !get_role('customer')) {
            return;
        }
        add_filter('pre_option_default_role', function () { return 'customer'; });
        add_action('user_new_form', function () {
            echo '<input type="hidden" name="timegrow_client" value="1" />';
        });
        add_action('admin_footer', function () {
            ?>
            <script>
            jQuery(function ($) {
                var $role = $('#createuser #role');
                $role.val('customer').prop('disabled', true)
                    .after('<input type="hidden" name="role" value="customer" />')
                    .after(' <span class="description"><?php echo esc_js(__('Clients are always created as Customers.', 'timegrow')); ?></span>');
                <?php if (empty($_POST)) : // first load only, so a form shown again after an error keeps your choice ?>
                $('#createuser #send_user_notification').prop('checked', false);
                $('#createuser #user_login').val(<?php echo wp_json_encode(self::unique_client_login()); ?>);
                <?php endif; ?>
            });
            </script>
            <?php
        });
    }

    /**
     * A username no one has yet, built like the e2e test's: client_1016_013331_7y3r
     * (month and day, time in UTC, then 4 random characters).
     */
    public static function unique_client_login() {
        do {
            $login = 'client_' . gmdate('md_His') . '_' . strtolower(wp_generate_password(4, false));
        } while (username_exists($login));
        return $login;
    }

    /**
     * Server side of the lock: a user created from Add Client is always a Customer.
     */
    public function force_customer_role($user_id) {
        if (empty($_POST['timegrow_client']) || !get_role('customer') || !current_user_can('create_users')) {
            return;
        }
        (new WP_User($user_id))->set_role('customer');
    }


    public function register_admin_menu() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);

        add_submenu_page(
            TIMEGROW_PARENT_MENU,
            'Clients',
            'Clients',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-clients-list',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'list' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Add Client',
            'Add Client',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-client-add',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'add' ); // Call the tracker_mvc method, passing the parameter
            },
        );

        add_submenu_page(
            null,
            'Edit Client',
            'Edit Client',
            TIMEGROW_OWNER_CAP,
            TIMEGROW_PARENT_MENU . '-client-edit',
            function() { // Define a closure
                $this->tracker_mvc_admin_page( 'edit' ); // Call the tracker_mvc method, passing the parameter
            },
        );

    }

/*************  ✨ Windsurf Command ⭐  *************/
    /**
     * Enqueue styles and scripts for the client admin pages.
     *
     * Hooked into admin_enqueue_scripts
     *
     * @return void
     */
/*******  bf5cae99-a42a-4000-a91e-2895ec5aea00  *******/
    public function enqueue_scripts_styles() {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        wp_enqueue_style('timegrow-modern-style', TIMEGROW_CORE_BASE_URI . 'assets/css/timegrow-modern.css');
        wp_enqueue_style('timegrow-forms-style', TIMEGROW_CORE_BASE_URI . 'assets/css/forms.css');
        wp_enqueue_style('timeflies-clients-style', TIMEGROW_CORE_BASE_URI . 'assets/css/company.css');
        wp_enqueue_script('timeflies-clients-script', TIMEGROW_CORE_BASE_URI . 'assets/js/company.js', array('jquery'), '1.0', true);
        wp_localize_script(
            'timegrow-clients-script',
            'timegrow_clients_list',
            [
                'list_url' => admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-clients-list'),
                'nonce' => wp_create_nonce('timegrow_client_nonce') // Pass the nonce to JS
            ]
        );
    }

    public function tracker_mvc_admin_page($screen) {
        timegrow_log(__CLASS__.'::'.__FUNCTION__);
        $controller = new TimeGrowClientController();
        $controller->display_admin_page($screen);
    }
}
