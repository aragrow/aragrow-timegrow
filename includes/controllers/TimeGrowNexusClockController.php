<?php

// ####TimeGrowNexusClockController.php ####

<?php
// #### TimeGrowNexusController.php ####
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowNexusClockController{

    private $view;

    public function __construct(TimeGrowNexusClockView $view) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);   
        $this->view = $view;
    }

    public function handle_form_submission() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
    }

    public function display($user) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $this->view->clock($user);
    }

    public function display_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        
        $user = wp_get_current_user();

        if ($screen != 'clock' && ( isset($_POST['add_item']) || isset($_POST['edit_item']) )) {
            var_dump('processing form');
            $this->handle_form_submission();
            $screen = 'dashboard';
        }

        if ($screen == 'dashboard')
            $this->display($user);
    }

}



// (This controller would be associated with the 'timegrow-time-entry' menu slug)

// Ensure the View class is available
// Adjust path as necessary
// require_once TIMEGROW_PLUGIN_DIR . 'includes/views/TimeGrowNexusClockView.php';

class TimeGrowTimeEntryController { // Or a more specific ClockController

    private $clock_view;

    public function __construct() {
        // Ensure TIMEGROW_PLUGIN_DIR is defined in your main plugin file
        if (defined('TIMEGROW_BASE_DIR') && !class_exists('TimeGrowNexusClockView')) {
             require_once TIMEGROW_BASE_DIR . 'includes/views/TimeGrowNexusClockView.php';
        }
        // Or use ARAGROW_TT_PLUGIN_DIR or other constant

        if (class_exists('TimeGrowNexusClockView')) {
            $this->clock_view = new TimeGrowNexusClockView();
        }
    }

    /**
     * Renders the Clock In/Out page.
     */
    public function render_clock_page() { // This method is your admin page callback
        if (WP_DEBUG) error_log(__CLASS__ . '::' . __FUNCTION__);

        if (!$this->clock_view) {
            echo '<div class="wrap"><p>' . esc_html__('Error: Clock view component not loaded.', 'timegrow') . '</p></div>';
            return;
        }

        $current_user = wp_get_current_user();
        if (!$current_user->ID) {
            echo '<div class="wrap"><p>' . esc_html__('You must be logged in.', 'timegrow') . '</p></div>';
            return;
        }
        $this->clock_view->display($current_user);
    }

    /**
     * Enqueues scripts and styles for the Clock page.
     */
    public function enqueue_clock_scripts($hook) {
        // Check if $hook matches the page hook for your clock page
        // global $timegrow_clock_page_hook; // Set this when calling add_submenu_page
        // if ($hook != $timegrow_clock_page_hook) return;
        $current_screen = get_current_screen();
        // Example: if slug is 'timegrow-time-entry' under parent 'timegrow-nexus'
        if ($current_screen && $current_screen->id !== 'timegrow_page_timegrow-time-entry' && $current_screen->id !== 'toplevel_page_timegrow-nexus_page_timegrow-time-entry') {
            // Check your actual screen ID in the admin area to confirm
            // error_log("Screen ID: " . $current_screen->id);
            // return; // Uncomment after confirming your screen ID
        }

        // Enqueue your compiled React app script FOR THE CLOCKING UI
        wp_enqueue_script(
            'timegrow-clock-app', // IMPORTANT: Match this handle in wp_localize_script
            $plugin_url . 'assets/js/time-entry-app.js', // Path to your compiled React JS
            ['wp-element', 'wp-api-fetch', 'wp-i18n', 'wp-hooks', 'wp-components'],
            $plugin_version,
            true // Load in footer
        );

        // Translations for your React app
        wp_set_script_translations('timegrow-clock-app', 'timegrow', TIMEGROW_PLUGIN_DIR . 'languages');

        // Enqueue the CSS for the clocking UI (from previous response)
        wp_enqueue_style(
            'timegrow-clock-style',
            $plugin_url . 'assets/css/time-entry-style.css', // Path to your CSS
            [],
            $plugin_version
        );
    }
}