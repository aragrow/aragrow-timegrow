<?php
// includes/admin-menu.php

if (!defined('ABSPATH')) {
    exit;
}

class TimeFlies_Admin_Menu {
    private static $instance;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_menu', array($this, 'register_admin_menu'));
    }

    public function register_admin_menu() {
        add_menu_page(
            'Time Flies Tracker',
            'Time Flies',
            TIMEFLIES_OWNER_CAP,
            TIMEFLIES_PARENT_MENU,
            array($this, 'dashboard_page'),
            'dashicons-clock',
            25
        );

        add_submenu_page(
            TIMEFLIES_PARENT_MENU,
            'Companies',
            'Companies',
            TIMEFLIES_OWNER_CAP,
            TIMEFLIES_PARENT_MENU . '-company-list',
            array($this, 'companies_list_page')
        );

        add_submenu_page(
            null, // Hidden submenu for editing
            'Add New Company',
            'Add New Company',
            TIMEFLIES_OWNER_CAP,
            TIMEFLIES_PARENT_MENU . '-company-add',
            array($this, 'company_add_page')
        );

        add_submenu_page(
            null, // Hidden submenu for editing
            'Edit Company',
            'Edit Company',
            TIMEFLIES_OWNER_CAP,
            TIMEFLIES_PARENT_MENU . '-company-edit',
            array($this, 'company_edit_page')
        );

        add_submenu_page(
            TIMEFLIES_PARENT_MENU,
            'Team Members',
            'Team Member',
            TIMEFLIES_OWNER_CAP,
            TIMEFLIES_PARENT_MENU . '-team-members-list',
            array($this, 'team_members_list_page')
        );

        add_submenu_page(
            null, // Hidden submenu for editing
            'Add New Team Member',
            'Add New Team Member',
            TIMEFLIES_OWNER_CAP,
            TIMEFLIES_PARENT_MENU . '-team-member-add',
            array($this, 'team_member_add_page')
        );

        add_submenu_page(
            null, // Hidden submenu for editing
            'Edit Team Member',
            'Edit Team Member',
            TIMEFLIES_OWNER_CAP,
            TIMEFLIES_PARENT_MENU . '-team-member-edit',
            array($this, 'team_member_edit_page')
        );

        add_submenu_page(
            TIMEFLIES_PARENT_MENU,
            'Clients',
            'Clients',
            TIMEFLIES_OWNER_CAP,
            TIMEFLIES_PARENT_MENU . '-clients-list',
            array($this, 'clients_list_page')
        );

        add_submenu_page(
            null, // Hidden submenu for editing
            'Add New Client',
            'Add New Client',
            TIMEFLIES_OWNER_CAP,
            TIMEFLIES_PARENT_MENU . '-client-add',
            array($this, 'client_add_page')
        );

        add_submenu_page(
            null, // Hidden submenu for editing
            'Edit Client',
            'Edit Client',
            TIMEFLIES_OWNER_CAP,
            TIMEFLIES_PARENT_MENU . '-client-edit',
            array($this, 'client_edit_page')
        );

        add_submenu_page(
            TIMEFLIES_PARENT_MENU,
            'Projects',
            'Projects',
            TIMEFLIES_OWNER_CAP,
            TIMEFLIES_PARENT_MENU . '-projects-list',
            array($this, 'projects_list_page')
        );

        add_submenu_page(
            null, // Hidden submenu for editing
            'Add New project',
            'Add New project',
            TIMEFLIES_OWNER_CAP,
            TIMEFLIES_PARENT_MENU . '-project-add',
            array($this, 'project_add_page')
        );

        add_submenu_page(
            null, // Hidden submenu for editing
            'Edit project',
            'Edit project',
            TIMEFLIES_OWNER_CAP,
            TIMEFLIES_PARENT_MENU . '-project-edit',
            array($this, 'project_edit_page')
        );

        add_submenu_page(
            TIMEFLIES_PARENT_MENU,
            'Time Entries',
            'Time Entries',
            TIMEFLIES_OWNER_CAP,
            TIMEFLIES_PARENT_MENU . '-time-entries-list',
            array($this, 'time_entries_list_page')
        );

        add_submenu_page(
            null, // Hidden submenu for editing
            'Add New Time',
            'Add New Time',
            TIMEFLIES_OWNER_CAP,
            TIMEFLIES_PARENT_MENU . '-time-entry-add',
            array($this, 'time_entry_add_page')
        );

        add_submenu_page(
            null, // Hidden submenu for editing
            'Edit Time',
            'Edit Time',
            TIMEFLIES_OWNER_CAP,
            TIMEFLIES_PARENT_MENU . '-time-entry-edit',
            array($this, 'time_entry_edit_page')
        );

        // ... other submenus
    }

    public function dashboard_page() {
        echo '<h2>Time Flies Dashboard</h2>';
    }

    public function companies_list_page() {
        require_once ARAGROW_TIMEFLIES_SCREENS_DIR . 'companies-list.php';
    }

    public function company_add_page() {
        require_once ARAGROW_TIMEFLIES_SCREENS_DIR . 'company-add.php';
    }

    public function company_edit_page() {
        require_once ARAGROW_TIMEFLIES_SCREENS_DIR . 'company-edit.php';
    }

    public function team_members_list_page() {
        require_once ARAGROW_TIMEFLIES_SCREENS_DIR . 'team-members-list.php';
    }

    public function team_member_add_page() {
        require_once ARAGROW_TIMEFLIES_SCREENS_DIR . 'team-member-add.php';
    }

    public function team_member_edit_page() {
        require_once ARAGROW_TIMEFLIES_SCREENS_DIR . 'team-member-edit.php';
    }

    public function clients_list_page() {
        require_once ARAGROW_TIMEFLIES_SCREENS_DIR . 'clients-list.php';
    }

    public function client_add_page() {
        require_once ARAGROW_TIMEFLIES_SCREENS_DIR . 'client-add.php';
    }

    public function client_edit_page() {
        require_once ARAGROW_TIMEFLIES_SCREENS_DIR . 'client-edit.php';
    }

    public function projects_list_page() {
        require_once ARAGROW_TIMEFLIES_SCREENS_DIR . 'projects-list.php';
    }

    public function project_add_page() {
        require_once ARAGROW_TIMEFLIES_SCREENS_DIR . 'project-add.php';
    }

    public function project_edit_page() {
        require_once ARAGROW_TIMEFLIES_SCREENS_DIR . 'project-edit.php';
    }

    public function time_entries_list_page() {
        require_once ARAGROW_TIMEFLIES_SCREENS_DIR . 'time-entries-list.php';
    }

    public function time_entry_add_page() {
        require_once ARAGROW_TIMEFLIES_SCREENS_DIR . 'time-entry-add.php';
    }

    public function time_entry_edit_page() {
        require_once ARAGROW_TIMEFLIES_SCREENS_DIR . 'time-entry-edit.php';
    }

    // ... other page callbacks
}

TimeFlies_Admin_Menu::get_instance();