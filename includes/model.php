<?php
// includes/model.php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Aragrow_TimeFlies_Model {
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
        add_action('plugins_loaded', array($this, 'create_tables')); // Run table creation after plugins are loaded.
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        $sql_companies = "CREATE TABLE IF NOT EXISTS {$prefix}timeflies_companies (
            ID mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            legal_name varchar(255),  -- Legal name of the company (if different)
            document_number varchar(255) UNIQUE, -- Tax ID, registration number, etc.
            default_flat_fee DECIMAL(10, 2) DEFAULT 0.00,
            contact_person varchar(255),
            email varchar(255),
            phone varchar(20),
            address_1 varchar(255),
            address_2 varchar(255),
            city varchar(50),
            state varchar(50),
            postal_code varchar(20),
            country varchar(50),
            website varchar(255),
            notes text,       -- Any additional notes about the company
            status smallint(1) NOT NULL DEFAULT 1,
            created_at timestamp,
            updated_at timestamp,  -- Timestamp of the last update
            PRIMARY KEY  (ID)
        ) $charset_collate;";

        $sql_clients = "CREATE TABLE IF NOT EXISTS {$prefix}timeflies_clients (
            ID mediumint(9) NOT NULL AUTO_INCREMENT,
            company_id mediumint(9) NOT NULL,
            name varchar(255) NOT NULL,
            document_number varchar(255) NOT NULL UNIQUE,
            default_flat_fee DECIMAL(10, 2) DEFAULT 0.00,
            currency varchar(10),
            contact_person varchar(255),
            email varchar(255),
            phone varchar(20),
            address_1 varchar(255),
            address_2 varchar(255),
            city varchar(255),
            state varchar(255),
            postal_code varchar(20),
            country varchar(255),
            website varchar(255),
            status smallint(1) NOT NULL DEFAULT 1,
            created_at timestamp,
            updated_at timestamp,
            PRIMARY KEY  (ID),
            FOREIGN KEY (company_id) REFERENCES {$prefix}timeflies_companies(ID)
        ) $charset_collate;";

        $sql_projects = "CREATE TABLE IF NOT EXISTS {$prefix}timeflies_projects (
            ID mediumint(9) NOT NULL AUTO_INCREMENT,
            client_id mediumint(9) NOT NULL,
            name varchar(255) NOT NULL,
            description text,
            status varchar(50) DEFAULT 'active',
            default_flat_fee DECIMAL(10, 2) DEFAULT 0.00,
            start_date date,
            end_date date,
            status smallint(1) NOT NULL DEFAULT 1,
            estimate_hours smallint(4) NULL,
            created_by bigint(20) unsigned,
            created_at timestamp,
            updated_at timestamp,
            PRIMARY KEY  (ID),
            FOREIGN KEY (client_id) REFERENCES {$prefix}timeflies_clients(ID),
            FOREIGN KEY (created_by) REFERENCES {$prefix}users(ID)
        ) $charset_collate;";

        $sql_time_entries = "CREATE TABLE IF NOT EXISTS {$prefix}timeflies_time_entries (
            ID mediumint(9) NOT NULL AUTO_INCREMENT,
            project_id mediumint(9) NOT NULL,
            member_id mediumint(9) NOT NULL,
            clock_in_date datetime,
            clock_out_date datetime,
            hours decimal(5,2),
            billable tinyint(1),
            description text,
            created_at timestamp,
            updated_at timestamp,
            PRIMARY KEY  (ID),
            FOREIGN KEY (project_id) REFERENCES {$prefix}timeflies_projects(ID),
            FOREIGN KEY (member_id) REFERENCES {$prefix}timeflies_team_members(ID)
        ) $charset_collate;";

        $sql_invoices = "CREATE TABLE IF NOT EXISTS {$prefix}timeflies_invoices (
            ID mediumint(9) NOT NULL AUTO_INCREMENT,
            project_id mediumint(9) NOT NULL,
            invoice_number varchar(255),
            invoice_date date,
            due_date date,
            total_amount decimal(10, 2),
            status varchar(50) DEFAULT 'pending',
            created_at timestamp,
            updated_at timestamp,
            PRIMARY KEY  (ID),
            FOREIGN KEY (project_id) REFERENCES {$prefix}timeflies_projects(ID)
        ) $charset_collate;";

        $sql_team_members = "CREATE TABLE IF NOT EXISTS {$prefix}timeflies_team_members (
            ID mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id mediumint(9) NOT NULL UNIQUE,  
            company_id mediumint(9) NOT NULL,  
            name varchar(25) NOT NULL,
            email varchar(255),
            phone varchar(20),
            title varchar(255),  
            bio text,           
            status smallint(1) NOT NULL DEFAULT 1,
            created_at timestamp,
            updated_at timestamp,
            PRIMARY KEY  (ID),
            FOREIGN KEY (company_id) REFERENCES {$prefix}timeflies_companies(ID),
            FOREIGN KEY (user_id) REFERENCES {$prefix}users(ID)
        ) $charset_collate;";

        $sql_team_members_projects = "CREATE TABLE IF NOT EXISTS {$prefix}timeflies_team_member_projects (  -- Join table for many-to-many
            ID mediumint(9) NOT NULL AUTO_INCREMENT,
            team_member_id mediumint(9) NOT NULL,
            project_id mediumint(9) NOT NULL,
            created_at timestamp,
            updated_at timestamp,
            PRIMARY KEY (team_member_id, project_id), -- Composite key to prevent duplicates
            FOREIGN KEY (team_member_id) REFERENCES {$prefix}timeflies_team_members(ID),
            FOREIGN KEY (project_id) REFERENCES {$prefix}timeflies_projects(ID) -- Assuming you have a projects table
        ) $charset_collate;";


        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_companies);
        dbDelta($sql_clients);
        dbDelta($sql_projects);
        dbDelta($sql_time_entries);
        dbDelta($sql_invoices);
        dbDelta($sql_team_members);
        dbDelta($sql_team_members_projects);

        // Add company_id and default_flat_fee to wp_users (only needs to be done once):
        // $this->add_columns_to_wp_users();

    }

    private function add_columns_to_wp_users() {
        global $wpdb;

        // Check if company_id exists
        $company_id_exists = $wpdb->get_var("SHOW COLUMNS FROM wp_users LIKE 'company_id'");
        if (is_null($company_id_exists)) {
             $wpdb->query("ALTER TABLE wp_users ADD COLUMN company_id mediumint(9) AFTER ID");
             $wpdb->query("ALTER TABLE wp_users ADD CONSTRAINT fk_company FOREIGN KEY (company_id) REFERENCES timeflies_companies(ID)");
        }

        // Check if default_flat_fee exists
        $default_flat_fee_exists = $wpdb->get_var("SHOW COLUMNS FROM wp_users LIKE 'default_flat_fee'");
        if (is_null($default_flat_fee_exists)) {
            $wpdb->query("ALTER TABLE wp_users ADD COLUMN default_flat_fee DECIMAL(10, 2) DEFAULT 0.00 AFTER company_id");
        }


    }


    // ... (Other model methods for data access, retrieval, etc. will go here)

    public function get_effective_fee($project_id, $user_id) {
      // ... (as before)
    }

    // ... other functions as needed.

}

// Instantiate the plugin class.
Aragrow_TimeFlies_Model::get_instance();
