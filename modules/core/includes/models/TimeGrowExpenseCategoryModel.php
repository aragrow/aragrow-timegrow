<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowExpenseCategoryModel {

    private $table_name;
    private $wpdb;
    private $charset_collate;
    private $allowed_fields;

    public function __construct() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        $this->table_name = $this->wpdb->prefix . TIMEGROW_PREFIX . 'expense_categories';
        $this->allowed_fields = [
            'name',
            'slug',
            'description',
            'parent_id',
            'schedule_c_part',
            'sort_order',
            'is_active',
            'updated_at',
            'created_at'
        ];
    }

    public function initialize() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            ID BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            description TEXT NULL,
            parent_id BIGINT(20) UNSIGNED NULL,
            schedule_c_part VARCHAR(50) NULL COMMENT 'Part II or Part V',
            sort_order INT(11) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (ID),
            UNIQUE KEY slug (slug),
            KEY parent_id (parent_id),
            KEY is_active (is_active),
            KEY sort_order (sort_order)
        ) {$this->charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get all categories
     */
    public function get_all($args = []) {
        $defaults = [
            'parent_id' => null,
            'is_active' => null,
            'orderby' => 'sort_order',
            'order' => 'ASC'
        ];
        $args = wp_parse_args($args, $defaults);

        $where = ['1=1'];

        if ($args['parent_id'] !== null) {
            if ($args['parent_id'] === 0) {
                $where[] = 'parent_id IS NULL';
            } else {
                $where[] = $this->wpdb->prepare('parent_id = %d', $args['parent_id']);
            }
        }

        if ($args['is_active'] !== null) {
            $where[] = $this->wpdb->prepare('is_active = %d', $args['is_active']);
        }

        $where_clause = implode(' AND ', $where);
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);

        $sql = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby}";

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get category by ID
     */
    public function get_by_id($id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE ID = %d",
            $id
        ));
    }

    /**
     * Get category by slug
     */
    public function get_by_slug($slug) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE slug = %s",
            $slug
        ));
    }

    /**
     * Get child categories
     */
    public function get_children($parent_id, $is_active = true) {
        $where = $this->wpdb->prepare('parent_id = %d', $parent_id);

        if ($is_active !== null) {
            $where .= $this->wpdb->prepare(' AND is_active = %d', $is_active);
        }

        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY sort_order ASC, name ASC"
        );
    }

    /**
     * Get hierarchical categories
     */
    public function get_hierarchical($is_active = true) {
        $categories = $this->get_all(['is_active' => $is_active]);
        return $this->build_tree($categories);
    }

    /**
     * Build tree structure
     */
    private function build_tree($categories, $parent_id = null) {
        $tree = [];

        foreach ($categories as $category) {
            if (($parent_id === null && $category->parent_id === null) ||
                ($parent_id !== null && $category->parent_id == $parent_id)) {
                $category->children = $this->build_tree($categories, $category->ID);
                $tree[] = $category;
            }
        }

        return $tree;
    }

    /**
     * Create new category
     */
    public function create($data) {
        $data = $this->sanitize_data($data);

        // Generate slug if not provided
        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = $this->generate_unique_slug($data['name']);
        }

        $result = $this->wpdb->insert(
            $this->table_name,
            $data,
            $this->get_format($data)
        );

        if ($result === false) {
            return false;
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Update category
     */
    public function update($id, $data) {
        $data = $this->sanitize_data($data);

        // Prevent category from being its own parent
        if (isset($data['parent_id']) && $data['parent_id'] == $id) {
            return false;
        }

        // Prevent circular reference
        if (isset($data['parent_id']) && $data['parent_id']) {
            if ($this->has_circular_reference($id, $data['parent_id'])) {
                return false;
            }
        }

        $result = $this->wpdb->update(
            $this->table_name,
            $data,
            ['ID' => $id],
            $this->get_format($data),
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete category
     */
    public function delete($id) {
        // Check if category has children
        $children = $this->get_children($id, null);
        if (!empty($children)) {
            return false; // Cannot delete category with children
        }

        return $this->wpdb->delete(
            $this->table_name,
            ['ID' => $id],
            ['%d']
        );
    }

    /**
     * Check for circular reference
     */
    private function has_circular_reference($category_id, $parent_id, $depth = 0) {
        if ($depth > 10) return true; // Prevent infinite loop

        $parent = $this->get_by_id($parent_id);

        if (!$parent) return false;
        if ($parent->ID == $category_id) return true;
        if ($parent->parent_id) {
            return $this->has_circular_reference($category_id, $parent->parent_id, $depth + 1);
        }

        return false;
    }

    /**
     * Generate unique slug
     */
    private function generate_unique_slug($name, $suffix = 0) {
        $slug = sanitize_title($name);

        if ($suffix > 0) {
            $slug .= '-' . $suffix;
        }

        $existing = $this->get_by_slug($slug);

        if ($existing) {
            return $this->generate_unique_slug($name, $suffix + 1);
        }

        return $slug;
    }

    /**
     * Sanitize data
     */
    private function sanitize_data($data) {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (!in_array($key, $this->allowed_fields)) {
                continue;
            }

            switch ($key) {
                case 'name':
                case 'schedule_c_part':
                    $sanitized[$key] = sanitize_text_field($value);
                    break;
                case 'slug':
                    $sanitized[$key] = sanitize_title($value);
                    break;
                case 'description':
                    $sanitized[$key] = sanitize_textarea_field($value);
                    break;
                case 'parent_id':
                case 'sort_order':
                    $sanitized[$key] = !empty($value) ? intval($value) : null;
                    break;
                case 'is_active':
                    $sanitized[$key] = (int) (bool) $value;
                    break;
            }
        }

        return $sanitized;
    }

    /**
     * Get format for wpdb operations
     */
    private function get_format($data) {
        $format = [];

        foreach ($data as $key => $value) {
            if ($key === 'name' || $key === 'slug' || $key === 'description' || $key === 'schedule_c_part') {
                $format[] = '%s';
            } else {
                $format[] = '%d';
            }
        }

        return $format;
    }

    /**
     * Populate with default Schedule C categories
     */
    public function populate_default_categories() {
        // Check if already populated
        $existing = $this->get_all();
        if (!empty($existing)) {
            return false;
        }

        // Part II - Main Expenses (Parent)
        $part_ii_id = $this->create([
            'name' => 'Schedule C - Part II (Main Expenses)',
            'slug' => 'schedule_c_part_ii',
            'description' => 'Main business expenses reported on Schedule C Part II',
            'schedule_c_part' => 'Part II',
            'sort_order' => 1,
            'is_active' => 1
        ]);

        // Part II Categories
        $part_ii_categories = [
            ['name' => 'Advertising', 'slug' => 'advertising'],
            ['name' => 'Car and Truck Expenses', 'slug' => 'car_truck_expenses'],
            ['name' => 'Commissions and Fees', 'slug' => 'commissions_fees'],
            ['name' => 'Contract Labor', 'slug' => 'contract_labor'],
            ['name' => 'Depletion', 'slug' => 'depletion'],
            ['name' => 'Depreciation', 'slug' => 'depreciation'],
            ['name' => 'Employee Benefit Programs', 'slug' => 'employee_benefit_programs'],
            ['name' => 'Insurance (Other than Health)', 'slug' => 'insurance'],
            ['name' => 'Interest - Mortgage', 'slug' => 'interest_mortgage'],
            ['name' => 'Interest - Other', 'slug' => 'interest_other'],
            ['name' => 'Legal and Professional Services', 'slug' => 'legal_professional'],
            ['name' => 'Office Expense', 'slug' => 'office_expense'],
            ['name' => 'Pension and Profit-Sharing Plans', 'slug' => 'pension_profit_sharing'],
            ['name' => 'Rent or Lease - Vehicles, Machinery, Equipment', 'slug' => 'rent_vehicles'],
            ['name' => 'Rent or Lease - Other Business Property', 'slug' => 'rent_property'],
            ['name' => 'Repairs and Maintenance', 'slug' => 'repairs_maintenance'],
            ['name' => 'Supplies', 'slug' => 'supplies'],
            ['name' => 'Taxes and Licenses', 'slug' => 'taxes_licenses'],
            ['name' => 'Travel', 'slug' => 'travel'],
            ['name' => 'Meals (50% Deductible)', 'slug' => 'meals'],
            ['name' => 'Utilities', 'slug' => 'utilities'],
            ['name' => 'Wages', 'slug' => 'wages'],
        ];

        $sort = 1;
        foreach ($part_ii_categories as $cat) {
            $this->create([
                'name' => $cat['name'],
                'slug' => $cat['slug'],
                'parent_id' => $part_ii_id,
                'schedule_c_part' => 'Part II',
                'sort_order' => $sort++,
                'is_active' => 1
            ]);
        }

        // Part V - Other Expenses (Parent)
        $part_v_id = $this->create([
            'name' => 'Schedule C - Part V (Other Expenses)',
            'slug' => 'schedule_c_part_v',
            'description' => 'Other business expenses reported on Schedule C Part V',
            'schedule_c_part' => 'Part V',
            'sort_order' => 2,
            'is_active' => 1
        ]);

        // Part V Categories
        $part_v_categories = [
            ['name' => 'Online Web Fees', 'slug' => 'online_web_fees'],
            ['name' => 'Business Telephone', 'slug' => 'business_telephone'],
            ['name' => 'Education and Training', 'slug' => 'education_training'],
            ['name' => 'Membership Dues', 'slug' => 'membership_dues'],
            ['name' => 'Books and Publications', 'slug' => 'books_publications'],
            ['name' => 'Photography/Stock Images', 'slug' => 'photography_stock'],
            ['name' => 'Marketing Materials', 'slug' => 'marketing_materials'],
            ['name' => 'Shipping and Postage', 'slug' => 'shipping_postage'],
            ['name' => 'Bank Fees', 'slug' => 'bank_fees'],
            ['name' => 'Credit Card Processing Fees', 'slug' => 'credit_card_fees'],
            ['name' => 'Other Expenses', 'slug' => 'other'],
        ];

        $sort = 1;
        foreach ($part_v_categories as $cat) {
            $this->create([
                'name' => $cat['name'],
                'slug' => $cat['slug'],
                'parent_id' => $part_v_id,
                'schedule_c_part' => 'Part V',
                'sort_order' => $sort++,
                'is_active' => 1
            ]);
        }

        return true;
    }

    /**
     * Render category select options for expense forms
     */
    public function render_category_select_options($selected_category_id = null, $include_inactive = false) {
        $hierarchical_categories = $this->get_hierarchical($include_inactive ? null : true);

        $this->render_category_options_recursive($hierarchical_categories, $selected_category_id);
    }

    /**
     * Recursively render category options
     */
    private function render_category_options_recursive($categories, $selected_id = null, $level = 0) {
        $current_part = null;

        foreach ($categories as $category) {
            // Render optgroup headers for Schedule C parts
            if ($category->parent_id === null && !empty($category->schedule_c_part)) {
                if ($current_part !== $category->schedule_c_part) {
                    if ($current_part !== null) {
                        echo '</optgroup>';
                    }
                    echo '<optgroup label="' . esc_attr($category->name) . '">';
                    $current_part = $category->schedule_c_part;
                }
                // Skip parent categories, only show children
                if (!empty($category->children)) {
                    $this->render_category_options_recursive($category->children, $selected_id, $level + 1);
                }
            } else {
                // Render actual option
                $indent = str_repeat('&nbsp;&nbsp;', $level);
                $selected = ($selected_id && $selected_id == $category->ID) ? 'selected' : '';
                $disabled = $category->is_active ? '' : 'disabled';

                echo '<option value="' . esc_attr($category->ID) . '" ' . $selected . ' ' . $disabled . '>';
                echo $indent . esc_html($category->name);
                if (!$category->is_active) {
                    echo ' (Inactive)';
                }
                echo '</option>';

                // Render children
                if (!empty($category->children)) {
                    $this->render_category_options_recursive($category->children, $selected_id, $level + 1);
                }
            }
        }

        if ($current_part !== null) {
            echo '</optgroup>';
        }
    }
}
