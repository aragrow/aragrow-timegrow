<?php
// includes/companies.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class TimeGrowIntegration{

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [ $this, 'register_integration_settings' ]);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles'));

         // Add Currency to User's Metadata
        add_action('show_user_profile', array($this, 'user_currency_field'));
        add_action('edit_user_profile', array($this, 'user_currency_field'));
        add_action('personal_options_update', array($this, 'save_user_currency'));
        add_action('edit_user_profile_update', array($this, 'save_user_currency'));
 
        // Add Default Flat Fee to User's Metadata
        add_action('show_user_profile', array($this, 'user_default_flat_fee_field'));
        add_action('edit_user_profile', array($this, 'user_default_flat_fee_field'));
        add_action('personal_options_update', array($this, 'save_user_default_flat_fee'));
        add_action('edit_user_profile_update', array($this, 'save_user_default_flat_fee'));

        add_filter('woocommerce_prevent_admin_access', [ $this, 'allow_team_member_admin_access' ], 10, 2);
    }

    public function register_admin_menu() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        add_options_page(
            'WooCommerce Integration',      // Page title
            'WooCommerce Integration',      // Menu title
            'manage_options',          // Capability
            'woocommerce-integration',   // Menu slug
            function() { // Define a closure
                $this->tracker_mvc_admin_page(); // Call the tracker_mvc method, passing the parameter
            }
        );
    }
    
    public function register_integration_settings() {

        register_setting(
            'timegrow_integration_group',
            'timegrow_integration_settings',
            array(
                'sanitize_callback' => [ $this, 'sanitize_integration_settings' ]
            )
        );
        if (class_exists('WooCommerce')) { 
            add_settings_section(
                'timegrow_wc_section',
                'WooCommerce Integration',
                [ $this, 'wc_section_callback' ],
                'timegrow-integrations'
            );
        
            add_settings_field(
                'wc_clients',
                'Integrate with WooCommerce Clients',
                [ $this, 'wc_clients_callback' ],
                'woocommerce-integration',
                'timegrow_wc_section'
            );

            add_settings_field(
                'wc_invoices',
                'Integrate with WooCommerce Invoices',
                [ $this, 'wc_invoices_callback' ],
                'woocommerce-integration',
                'timegrow_wc_section'
            );

            add_settings_field(
                'wc_products',
                'Integrate with WooCommerce Products',
                [ $this, 'wc_products_callback' ],
                'woocommerce-integration',
                'timegrow_wc_section'
            );
        }
    }
    // Section description
    function wc_section_callback() {
        echo '<p>Select which WooCommerce features you want to integrate with:</p>';
        
        if (!class_exists('WooCommerce')) {
            echo '<div class="x-notice warning"><p>WooCommerce is not installed or activated!</p></div>';
        }
    }

    // Clients integration checkbox
    function wc_clients_callback() {
        $options = get_option('timegrow_integration_settings');
        $value = isset($options['wc_clients']) ? $options['wc_clients'] : 0;
        ?>
        <label>
            <input type="checkbox" name="timegrow_integration_settings[wc_clients]" value="1" 
                <?php checked(1, $value); ?> 
                <?php echo !class_exists('WooCommerce') ? 'disabled' : ''; ?>>
            Enable WooCommerce Client synchronization
        </label>
        <?php
    }

    // Invoices integration checkbox
    function wc_invoices_callback() {
        $options = get_option('timegrow_integration_settings');
        $value = isset($options['wc_invoices']) ? $options['wc_invoices'] : 0;
        ?>
        <label>
            <input type="checkbox" name="timegrow_integration_settings[wc_invoices]" value="1" 
                <?php checked(1, $value); ?> 
                <?php echo !class_exists('WooCommerce') ? 'disabled' : ''; ?>>
            Enable WooCommerce Invoice synchronization
        </label>
        <?php
    }

    function wc_products_callback() {
        $options = get_option('timegrow_integration_settings');
        $value = isset($options['wc_products']) ? $options['wc_products'] : 0;
        ?>
        <label>
            <input type="checkbox" name="timegrow_integration_settings[wc_products]" value="1" 
                <?php checked(1, $value); ?> 
                <?php echo !class_exists('WooCommerce') ? 'disabled' : ''; ?>>
            Enable Woocommerce Product synchronization
        </label>
        <?php
    }

    // Sanitization callback
    function sanitize_integration_settings($input) {
        $output = array();
        
        $output['wc_clients'] = isset($input['wc_clients']) && $input['wc_clients'] ? 1 : 0;
        $output['wc_invoices'] = isset($input['wc_invoices']) && $input['wc_invoices'] ? 1 : 0;
        $output['wc_products'] = isset($input['wc_products']) && $input['wc_products'] ? 1 : 0;
        return $output;
    }



     /**
     * It hooks into the 'woocommerce_prevent_admin_access' filter, which WooCommerce uses to determine whether to 
     * redirect users away from the admin area.
     * 
     * The function 'allow_team_member_admin_access' checks if the user has the 'team_member' role.
     * If the user is a team_member, it returns false, which tells WooCommerce allow admin access for this user.
     * For all other users, it returns the original $prevent_access value, maintaining WooCommerce's default behavior.
    */
    public function allow_team_member_admin_access($prevent_access, $redirect=null) {
            
        $user = wp_get_current_user();
    
        if (in_array('team_member', (array) $user->roles)) {
            return false; // Allow access
        }

        $integrations = get_option('timegrow_integration_settings');
        if ($integrations['wc_clients'] && class_exists('WooCommerce')) {
            if (in_array('customer', (array) $user->roles)) {
                return true; // Revoke access, which is the default for future used.
            }
        }
        
        return $prevent_access; // Default WooCommerce behavior
    }

    public function enqueue_scripts_styles($hook) {
        if(WP_DEBUG) {
            error_log(__CLASS__.'::'.__FUNCTION__);
            error_log('Hook: ' . $hook);
        }

        // Only enqueue on the integrations settings page
        if ($hook !== 'settings_page_woocommerce-integration') {
            return;
        }

        wp_enqueue_style('timegrow-modern-style', TIMEGROW_CORE_BASE_URI . 'assets/css/timegrow-modern.css');
        wp_enqueue_style('timegrow-integration-style', TIMEGROW_CORE_BASE_URI . 'assets/css/integration.css');
        wp_enqueue_script('timegrow-integrations-script', TIMEGROW_CORE_BASE_URI . 'assets/js/integration.js', array('jquery'), '1.0', true);
    }

    public function tracker_mvc_admin_page() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $this->display_admin_page();
    }

    public function display_admin_page() {
        $wc_installed = class_exists('WooCommerce');
        $paypal_module_active = class_exists('Aragrow_WC_PayPal_Auto_Invoicer');
        $options = get_option('timegrow_integration_settings');
        ?>
        <div class="wrap timegrow-page">
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('WooCommerce Integration', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Sync time tracking data with WooCommerce for seamless invoicing, client management, and product integration', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-cart"></span>
                </div>
            </div>

            <?php if (!$wc_installed): ?>
            <div class="timegrow-notice timegrow-notice-warning">
                <span class="dashicons dashicons-warning"></span>
                <div>
                    <strong><?php esc_html_e('WooCommerce Not Detected', 'timegrow'); ?></strong>
                    <p><?php esc_html_e('Install and activate WooCommerce to unlock powerful integrations.', 'timegrow'); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <form method="post" action="options.php" class="timegrow-integration-form">
                <?php settings_fields('timegrow_integration_group'); ?>

                <div class="timegrow-cards-container">

                    <!-- WooCommerce Integration Card -->
                    <div class="timegrow-card <?php echo $wc_installed ? '' : 'disabled'; ?>">
                        <div class="timegrow-card-header">
                            <div class="timegrow-icon timegrow-icon-woocommerce">
                                <span class="dashicons dashicons-cart"></span>
                            </div>
                            <div class="timegrow-card-title">
                                <h2><?php esc_html_e('WooCommerce', 'timegrow'); ?></h2>
                                <span class="timegrow-badge <?php echo $wc_installed ? 'timegrow-badge-active' : 'timegrow-badge-inactive'; ?>">
                                    <?php echo $wc_installed ? esc_html__('Installed', 'timegrow') : esc_html__('Not Installed', 'timegrow'); ?>
                                </span>
                            </div>
                        </div>

                        <div class="timegrow-card-body">
                            <p class="timegrow-card-description">
                                <?php esc_html_e('Sync your time tracking data with WooCommerce for seamless invoicing and client management.', 'timegrow'); ?>
                            </p>

                            <div class="integration-features">
                                <div class="timegrow-feature-item">
                                    <label class="timegrow-toggle-switch">
                                        <input type="checkbox"
                                               name="timegrow_integration_settings[wc_clients]"
                                               value="1"
                                               <?php checked(1, isset($options['wc_clients']) ? $options['wc_clients'] : 0); ?>
                                               <?php echo !$wc_installed ? 'disabled' : ''; ?>>
                                        <span class="timegrow-toggle-slider"></span>
                                    </label>
                                    <div class="timegrow-feature-info">
                                        <strong><?php esc_html_e('Client Synchronization', 'timegrow'); ?></strong>
                                        <p><?php esc_html_e('Sync WooCommerce customers with TimeGrow clients', 'timegrow'); ?></p>
                                    </div>
                                </div>

                                <div class="timegrow-feature-item">
                                    <label class="timegrow-toggle-switch">
                                        <input type="checkbox"
                                               name="timegrow_integration_settings[wc_invoices]"
                                               value="1"
                                               <?php checked(1, isset($options['wc_invoices']) ? $options['wc_invoices'] : 0); ?>
                                               <?php echo !$wc_installed ? 'disabled' : ''; ?>>
                                        <span class="timegrow-toggle-slider"></span>
                                    </label>
                                    <div class="timegrow-feature-info">
                                        <strong><?php esc_html_e('Invoice Synchronization', 'timegrow'); ?></strong>
                                        <p><?php esc_html_e('Create WooCommerce invoices from tracked time', 'timegrow'); ?></p>
                                    </div>
                                </div>

                                <div class="timegrow-feature-item">
                                    <label class="timegrow-toggle-switch">
                                        <input type="checkbox"
                                               name="timegrow_integration_settings[wc_products]"
                                               value="1"
                                               <?php checked(1, isset($options['wc_products']) ? $options['wc_products'] : 0); ?>
                                               <?php echo !$wc_installed ? 'disabled' : ''; ?>>
                                        <span class="timegrow-toggle-slider"></span>
                                    </label>
                                    <div class="timegrow-feature-info">
                                        <strong><?php esc_html_e('Product Synchronization', 'timegrow'); ?></strong>
                                        <p><?php esc_html_e('Link TimeGrow projects to WooCommerce products', 'timegrow'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="timegrow-footer">
                    <?php submit_button(__('Save Integration Settings', 'timegrow'), 'primary large', 'submit', false); ?>
                    <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-nexus-settings'); ?>" class="button button-secondary large">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        <?php esc_html_e('Back to Settings', 'timegrow'); ?>
                    </a>
                </div>
            </form>
        </div>
        <?php
    }


    function user_currency_field($user) {
        $current = get_user_meta($user->ID, 'timegrow_currency', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="timegrow_currency">Currency</label></th>
                <td>
                    <?php
                        $args = [
                            'selected' => $current,
                        ];
                        echo $this->currency_dropdown($args);
                    ?>
                    <p class="description">Select the currency for time keeping.</p>                    
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_user_currency($user_id) {
        if (current_user_can('edit_user', $user_id)) {
            update_user_meta($user_id, 'timegrow_currency', 
                sanitize_text_field($_POST['timegrow_currency']));
        }
    }

    // Add this to your plugin's main file
    public function currency_dropdown($args = []) {
        $defaults = [
            'selected' => 'DOLLAR',
            'name' => 'timegrow_currency',
            'class' => 'timegrow-currency-dropdown'
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $output = '<select name="' . esc_attr($args['name']) . '" class="' . esc_attr($args['class']) . '">';
    
        $selected = selected($args['selected'], 'dollar', false);
        $output .= '<option value="dollar"' . $selected . '>';
        $output .= esc_html('$');
        $output .= '</option>';
        $selected = selected($args['selected'], 'euro', false);
        $output .= '<option value="euro"' . $selected . '>';
        $output .= esc_html('€');
        $output .= '</option>';
        
        $output .= '</select>';
        
        return $output;
    }

    // Add Default Flat Fee to user profile
    function user_default_flat_fee_field($user) {
        $current = get_user_meta($user->ID, 'timegrow_default_flat_fee', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="timegrow_default_flat_fee">Default Flat Fee</label></th>
                <td>
                    <input type="text" name="timegrow_default_flat_fee" id="timegrow_default_flat_fee" value="<?php echo esc_attr($current); ?>" class="regular-text" />
                    <p class="description">Enter the default flat fee for time keeping.</p>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_user_default_flat_fee($user_id) {
        if (current_user_can('edit_user', $user_id)) {
            update_user_meta($user_id, 'timegrow_default_flat_fee', 
                sanitize_text_field($_POST['timegrow_default_flat_fee']));
        }
    }

}
