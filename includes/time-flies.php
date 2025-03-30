<?php

class TimeGrow {
    private static $instance;

    public static function get_instance() {
        /**
         * The get_instance() method checks if an instance already exists.
         * If not, it creates one and returns it.
         * The last line in the file, WC_Daily_Order_Export::get_instance();, triggers this process, 
         *  ensuring the class is instantiated and ready when the plugin is loaded.
         */
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('save_post', array($this, 'save_time_entry_data'));
        add_action('wp_ajax_generate_invoice', array($this, 'generate_invoice'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles')); // Enqueue scripts and styles
        
        // Add TimeZone to User's MetaData
        add_action('show_user_profile', array($this, 'user_timezone_field'));
        add_action('edit_user_profile', array($this, 'user_timezone_field'));
        add_action('personal_options_update', array($this, 'save_user_timezone'));
        add_action('edit_user_profile_update', array($this, 'save_user_timezone'));

        // Add Timecard to User's Metadata
        add_action('show_user_profile', array($this, 'user_timecard_field'));
        add_action('edit_user_profile', array($this, 'user_timecard_field'));
        add_action('personal_options_update', array($this, 'save_user_timecard'));
        add_action('edit_user_profile_update', array($this, 'save_user_timecard'));

    }

    public function enqueue_scripts_styles() {
        wp_enqueue_style('timeflies-style', ARAGROW_TIMEGROW_BASE_URI . 'assets/css/admin_styles.css', '', '1.0'); // Create this CSS file
        wp_enqueue_script('timeflies-script', ARAGROW_TIMEGROW_BASE_URI . 'assets/js/admin_script.js', array('jquery'), '1.0', true); // Create this JS file
        wp_localize_script('timeflies-script', 'timeflies_ajax', array('ajax_url' => admin_url('admin-ajax.php')));

        if (!wp_script_is('font-awesome-kit', 'enqueued')) {
            wp_enqueue_script( 'font-awesome-kit', 'https://kit.fontawesome.com/3d560e6a09.js', array(), null, false );
            wp_script_add_data( 'font-awesome-kit', 'crossorigin', 'anonymous' );
        }

    
    }


    public function save_time_entry_data($post_id) {
        if (isset($_POST['timeflies_date'])) {
            update_post_meta($post_id, '_timeflies_date', sanitize_text_field($_POST['timeflies_date']));
        }
        if (isset($_POST['timeflies_hours'])) {
            update_post_meta($post_id, '_timeflies_hours', sanitize_text_field($_POST['timeflies_hours']));
        }
        if (isset($_POST['timeflies_billable'])) {
            update_post_meta($post_id, '_timeflies_billable', 1);
        } else {
            delete_post_meta($post_id, '_timeflies_billable');
        }
    }

    public function generate_invoice() {
        // Fetch project, time entries, and format invoice.
        wp_send_json_success(['message' => 'Invoice generated.']);
    }

    // Add timezone to user profile
    function user_timezone_field($user) {
        $current = get_user_meta($user->ID, 'timeflies_timezone', true);
        if (empty($current)) {
            $current = 'UTC'; // Default to UTC
        }
        ?>
        <h3>Timekeeping Settings</h3>
        <table class="form-table">
            <tr>
                <th><label for="timezone">Timezone</label></th>
                <td>
                    <?php echo $this->timezone_dropdown([
                        'selected' => $current,
                        'name' => 'timeflies_timezone',
                        'class' => 'regular-text'
                    ]); ?>
                    <p class="description">Select your local timezone for accurate time tracking.</p>
                </td>
            </tr>
        </table>
        <?php
    }
    
    // Add timezone to user profile
    function user_timecard_field($user) {
        $current = get_user_meta($user->ID, 'timeflies_timecard', true);
        ?>
        <table class="form-table">
            <tr>
                <th><label for="timezone">Timecard Type</label></th>
                <td>
                    <?php echo $this->timecard_dropdown([
                        'selected' => $current,
                        'name' => 'timeflies_timecard',
                        'class' => 'regular-text'
                    ]); ?>
                    <p class="description">Select the type of time keeping.</p>
                </td>
            </tr>
        </table>
        <?php
    }


    public function save_user_timezone($user_id) {
        if (current_user_can('edit_user', $user_id)) {
            update_user_meta($user_id, 'timeflies_timezone', 
                sanitize_text_field($_POST['timeflies_timezone']));
        }
    }

    public function save_user_timecard($user_id) {
        if (current_user_can('edit_user', $user_id)) {
            update_user_meta($user_id, 'timeflies_timecard', 
                sanitize_text_field($_POST['timeflies_timecard']));
        }
    }

    // Add this to your plugin's main file
    public function timezone_dropdown($args = []) {
        $defaults = [
            'selected' => 'UTC',
            'name' => 'timeflies_timezone',
            'class' => ''
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $timezones = $this->time_zones_list();
        
        $output = '<select name="' . esc_attr($args['name']) . '" class="' . esc_attr($args['class']) . '">';
      
        foreach ($timezones as $timezone) {
            $zone = $timezone['zone']; 
            $selected = selected($args['selected'], $zone, false);
            $output .= "<option value='{$zone}' {$selected}>";
            $output .= esc_html($timezone['label']);
            $output .= '</option>';
        }
        $output .= '</select>';
        
        return $output;
    }

    public function time_zones_list() {
        $zones = timezone_identifiers_list();
        $locations = [];
        
        // Create timezone groups
        foreach ($zones as $zone) {
            $zone = explode('/', $zone);
            if ($zone[0] === 'UTC') continue;
            
            if (isset($zone[1])) {
                $locations[$zone[0]][$zone[0].'/'.$zone[1]] = str_replace('_', ' ', $zone[1]);
            }
        }
        
        // Build the formatted list
        $structure = [];
        
        // UTC first
        $structure[] = [
            'zone' => 'UTC',
            'label' => 'UTC'
        ];
        
        // Manual offsets
        $structure[] = [
            'zone' => 'UTC-12',
            'label' => 'UTC-12'
        ];
        // ... add other manual offsets as needed
        
        // Continent groups
        foreach ($locations as $continent => $cities) {
            $structure[] = [
                'zone' => '',
                'label' => '    '.$continent
            ];
            
            foreach ($cities as $zone => $city) {
                $structure[] = [
                    'zone' => $zone,
                    'label' => '  '.$city
                ];
            }
        }
        
        return $structure;
    }

    // Add this to your plugin's main file
    public function timecard_dropdown($args = []) {
        $defaults = [
            'selected' => 'punch',
            'name' => 'timeflies_timecard',
            'class' => ''
        ];
        
        $args = wp_parse_args($args, $defaults);

        $output = '<select name="' . esc_attr($args['name']) . '" class="' . esc_attr($args['class']) . '">';
            
        $selected = selected($args['selected'], 'manual', false);
        $output .= "<option value='punch' selected > Punch In/Out</option>";
        $output .= "<option value='manual' {$selected} > Manual</option>";

        $output .= '</select>';
        
        return $output;
    }

    // Autoload classes
    function load_mvc_classes($class) {
        $path  = dirname( __FILE__ ) . '/includes/';
        if ( file_exists( $path . 'models/' . $class . '.php' ) ) {
            require_once $path . 'models/' . $class . '.php';
        } elseif ( file_exists( $path . 'views/' . $class . '.php' ) ) {
            require_once $path . 'views/' . $class . '.php';
        } elseif ( file_exists( $path . 'controllers/' . $class . '.php' ) ) {
            require_once $path . 'controllers/' . $class . '.php';
        }
    }
}

// Instantiate the plugin class.
TimeGrow::get_instance();