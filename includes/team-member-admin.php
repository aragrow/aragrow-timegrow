<?php
// includes/team_members.php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Timeflies_Team_Members_Admin {
    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('wp_ajax_save_team_member', array($this, 'save_ajax'));
        add_action('wp_ajax_delete_team_member', array($this, 'delete_ajax'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts_styles'));
    }

    public function render_team_members_page() {
        include plugin_dir_path(__FILE__) . '../templates/team_member_page.php';
    }

    public function enqueue_scripts_styles() {
        wp_enqueue_style('timeflies-team-members-style', ARAGROW_TIMEFLIES_BASE_URI . 'assets/css/team_member.css');
        wp_enqueue_script('timeflies-team-members-script', ARAGROW_TIMEFLIES_BASE_URI. 'assets/js/team_member.js', array('jquery'), '1.0', true);
        wp_localize_script(
            'timeflies-team-members-script',
            'timeflies_team_member_list',
            [
                'list_url' => admin_url('admin.php?page=' . TIMEFLIES_PARENT_MENU . '-team-members-list'),
                'nonce' => wp_create_nonce('timeflies_team_member_nonce')
            ]
        );
    }

    public function save_ajax() {

        try {

            if (WP_DEBUG) error_log('Exc: Timeflies_Team_Members_Admin.save_ajax()');

            check_ajax_referer('timeflies_team_member_nonce', 'timeflies_team_member_nonce_field');

            if (WP_DEBUG) error_log('--> Validation Passed!!');

            $team_member_id = intval($_POST['team_member_id']);
            $user_id = intval($_POST['user_id']);
            $company_id = intval($_POST['company_id']);
            $name = sanitize_text_field($_POST['name']);
            $email = sanitize_email($_POST['email']);
            $phone = sanitize_text_field($_POST['phone']);
            $title = sanitize_text_field($_POST['title']);
            $bio = wp_kses_post($_POST['bio']);
            $status = isset($_POST['status']) ? 1 : 0;
            
            if (isset($_POST['project_ids'])) {
                $project_ids = (is_array($_POST['project_ids']))
                    ? array_map('intval', $_POST['project_ids']) 
                    : [$_POST['project_ids']];
            } else {
                $project_ids = [0];
            }

            $current_date = current_time('mysql');
            
            $team_member_data = array(
                'user_id' => $user_id,
                'company_id' => $company_id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'title' => $title,
                'bio' => $bio,
                'status' => $status,
                'updated_at' => $current_date
            );

            if(WP_DEBUG) {
                error_log('Team Member Data');
                error_log(print_r($team_member_data,true));
            }

            global $wpdb;
            $prefix = $wpdb->prefix;
            
            if ($team_member_id === 0) {
                $team_member_data['created_at'] = $current_date;
                $wpdb->insert("{$prefix}timeflies_team_members", $team_member_data, array(
                    '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'
                ));
                $team_member_id = $wpdb->insert_id;
            } else {
                $wpdb->update("{$prefix}timeflies_team_members", $team_member_data, array('ID' => $team_member_id), array(
                    '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s'    
                ));
            }

            // Update project assignments
            $this->update_team_member_projects($team_member_id, $project_ids);

            if(WP_DEBUG) error_log('Timeflies_Team_Members_Admin.save_ajax() -> Completed');

            wp_send_json_success(array('message' => 'Team member saved.', 'team_member_id' => $team_member_id));

        } catch (Exception $e) {

            if(WP_DEBUG) error_log('Timeflies_Team_Members_Admin.save_ajax() -> '.$e->getMessage());

            wp_send_json_success(array('message' => $e->getMessage(), 'team_member_id' => $team_member_id));
            
        } finally {

            if(WP_DEBUG) error_log('Timeflies_Team_Members_Admin.save_ajax() -> Finalized');
            // Optional block of code that always executes
        }


    }

    private function update_team_member_projects($team_member_id, $project_ids) {
        if (WP_DEBUG) error_log('Exc: Timeflies_Team_Members_Admin.update_team_member_projects()');

        if(WP_DEBUG) {
            error_log('Team Member Project Data');
            error_log(print_r($project_ids,true));
        }
        
        global $wpdb;
        $prefix = $wpdb->prefix;
        // Decode the URL-encoded string
        $project_ids = explode(',', $project_ids[0]);

        $wpdb->query('START TRANSACTION');

        try {
            // Remove existing assignments
            $wpdb->delete("{$prefix}timeflies_team_member_projects", array('team_member_id' => $team_member_id), array('%d'));

            // Add new assignments
            if (!empty($project_ids)) {
                foreach ($project_ids as $project_id) {
                    $wpdb->insert("{$prefix}timeflies_team_member_projects", array(
                        'team_member_id' => $team_member_id,
                        'project_id' => $project_id,
                    ), array('%d', '%d'));
                }
            }
           // If we've made it this far without exceptions, commit the transaction
            $wpdb->query('COMMIT');
            
            // Optionally, return a success message or status
            return true;

        } catch (Exception $e) {
            // An error occurred, rollback the transaction
            $wpdb->query('ROLLBACK');
            
            // Log the error or handle it as needed
            error_log("Transaction failed: " . $e->getMessage());
            
            // Optionally, return false or throw the exception again
            return false;
            // or: throw $e;
        }
       
    }

    public function delete_ajax() {
        check_ajax_referer('timeflies_team_member_nonce', 'timeflies_team_member_nonce_field');

        $team_member_id = intval($_POST['team_member_id']);

        global $wpdb;
        $wpdb->delete('timeflies_team_members', array('id' => $team_member_id), array('%d'));
        $wpdb->delete('timeflies_team_member_projects', array('team_member_id' => $team_member_id), array('%d'));

        wp_send_json_success(array('message' => 'Team member deleted.'));
    }
}

Timeflies_Team_Members_Admin::get_instance();
?>