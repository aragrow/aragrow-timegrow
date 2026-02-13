<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowExpenseCategoryController {

    private $model;
    private $view;

    public function __construct(TimeGrowExpenseCategoryModel $model, TimeGrowExpenseCategoryView $view) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        $this->model = $model;
        $this->view = $view;
    }

    /**
     * Display admin page
     */
    public function display_admin_page($screen) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        if ($screen == 'list') {
            $this->list_categories();
        } else if ($screen == 'edit') {
            $this->edit_category();
        }
    }

    /**
     * List all categories
     */
    private function list_categories() {
        // Handle form submissions
        $this->handle_form_submission();

        // Get all categories
        $categories = $this->model->get_all();
        $hierarchical_categories = $this->model->get_hierarchical(null); // Get all including inactive

        $this->view->display_categories($categories, $hierarchical_categories);
    }

    /**
     * Edit category
     */
    private function edit_category() {
        // Handle form submissions
        $this->handle_form_submission();

        // Get category ID
        $category_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if (!$category_id) {
            wp_die(__('Invalid category ID', 'timegrow'));
        }

        $category = $this->model->get_by_id($category_id);

        if (!$category) {
            wp_die(__('Category not found', 'timegrow'));
        }

        $hierarchical_categories = $this->model->get_hierarchical(null);

        // Filter out current category and its descendants to prevent circular reference
        $hierarchical_categories = $this->filter_category_tree($hierarchical_categories, $category_id);

        $this->view->edit_category($category, $hierarchical_categories);
    }

    /**
     * Filter category tree to remove category and its descendants
     */
    private function filter_category_tree($categories, $exclude_id) {
        $filtered = [];

        foreach ($categories as $category) {
            if ($category->ID == $exclude_id) {
                continue;
            }

            if (!empty($category->children)) {
                $category->children = $this->filter_category_tree($category->children, $exclude_id);
            }

            $filtered[] = $category;
        }

        return $filtered;
    }

    /**
     * Handle form submissions
     */
    private function handle_form_submission() {
        // Add category
        if (isset($_POST['action']) && $_POST['action'] === 'add_category') {
            check_admin_referer('timegrow_add_category', 'timegrow_category_nonce');

            $data = [
                'name' => $_POST['name'] ?? '',
                'slug' => $_POST['slug'] ?? '',
                'description' => $_POST['description'] ?? '',
                'parent_id' => !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null,
                'schedule_c_part' => $_POST['schedule_c_part'] ?? '',
                'sort_order' => isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0,
                'is_active' => 1
            ];

            $result = $this->model->create($data);

            if ($result) {
                $this->add_notice('success', __('Category added successfully.', 'timegrow'));
            } else {
                $this->add_notice('error', __('Failed to add category. Please try again.', 'timegrow'));
            }

            wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expense-categories'));
            exit;
        }

        // Edit category
        if (isset($_POST['action']) && $_POST['action'] === 'edit_category') {
            check_admin_referer('timegrow_edit_category', 'timegrow_category_nonce');

            $category_id = intval($_POST['category_id']);

            $data = [
                'name' => $_POST['name'] ?? '',
                'slug' => $_POST['slug'] ?? '',
                'description' => $_POST['description'] ?? '',
                'parent_id' => !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null,
                'schedule_c_part' => $_POST['schedule_c_part'] ?? '',
                'sort_order' => isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0,
                'is_active' => isset($_POST['is_active']) ? 1 : 0
            ];

            $result = $this->model->update($category_id, $data);

            if ($result !== false) {
                $this->add_notice('success', __('Category updated successfully.', 'timegrow'));
                wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expense-categories'));
                exit;
            } else {
                $this->add_notice('error', __('Failed to update category. Please check for circular references.', 'timegrow'));
            }
        }

        // Delete category
        if (isset($_GET['action']) && $_GET['action'] === 'delete_category') {
            $category_id = intval($_GET['id']);
            check_admin_referer('timegrow_delete_category_' . $category_id);

            $result = $this->model->delete($category_id);

            if ($result !== false) {
                $this->add_notice('success', __('Category deleted successfully.', 'timegrow'));
            } else {
                $this->add_notice('error', __('Failed to delete category. Categories with children cannot be deleted.', 'timegrow'));
            }

            wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expense-categories'));
            exit;
        }

        // Toggle category status
        if (isset($_GET['action']) && $_GET['action'] === 'toggle_category') {
            $category_id = intval($_GET['id']);
            check_admin_referer('timegrow_toggle_category_' . $category_id);

            $category = $this->model->get_by_id($category_id);

            if ($category) {
                $result = $this->model->update($category_id, [
                    'is_active' => $category->is_active ? 0 : 1
                ]);

                if ($result !== false) {
                    $message = $category->is_active ?
                        __('Category deactivated successfully.', 'timegrow') :
                        __('Category activated successfully.', 'timegrow');
                    $this->add_notice('success', $message);
                }
            }

            wp_redirect(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expense-categories'));
            exit;
        }
    }

    /**
     * Add admin notice
     */
    private function add_notice($type, $message) {
        add_settings_error(
            'timegrow_expense_categories',
            'timegrow_expense_category_message',
            $message,
            $type
        );

        set_transient('timegrow_expense_category_notices', get_settings_errors('timegrow_expense_categories'), 30);
    }
}
