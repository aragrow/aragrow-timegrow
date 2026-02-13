<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowExpenseCategoryView {

    public function display_categories($categories, $hierarchical_categories) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        ?>
        <div class="wrap timegrow-page timegrow-page-container">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Expense Categories', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Manage expense categories for better organization and tax reporting', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-category"></span>
                </div>
            </div>

            <div class="timegrow-two-column-layout">
                <!-- Left Column: Add New Category -->
                <div class="timegrow-column-left">
                    <div class="timegrow-card">
                        <h2><?php esc_html_e('Add New Category', 'timegrow'); ?></h2>
                        <form method="post" action="" id="add-category-form">
                            <?php wp_nonce_field('timegrow_add_category', 'timegrow_category_nonce'); ?>
                            <input type="hidden" name="action" value="add_category">

                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="category_name"><?php esc_html_e('Name', 'timegrow'); ?> <span class="required">*</span></label>
                                    </th>
                                    <td>
                                        <input type="text" name="name" id="category_name" class="regular-text" required>
                                        <p class="description"><?php esc_html_e('The name of the expense category.', 'timegrow'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="category_slug"><?php esc_html_e('Slug', 'timegrow'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="slug" id="category_slug" class="regular-text">
                                        <p class="description"><?php esc_html_e('The "slug" is the URL-friendly version of the name. Leave blank to auto-generate.', 'timegrow'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="category_parent"><?php esc_html_e('Parent Category', 'timegrow'); ?></label>
                                    </th>
                                    <td>
                                        <select name="parent_id" id="category_parent" class="regular-text">
                                            <option value=""><?php esc_html_e('None (Top Level)', 'timegrow'); ?></option>
                                            <?php $this->render_category_options($hierarchical_categories); ?>
                                        </select>
                                        <p class="description"><?php esc_html_e('Assign a parent category to create hierarchy.', 'timegrow'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="category_schedule_c_part"><?php esc_html_e('Schedule C Part', 'timegrow'); ?></label>
                                    </th>
                                    <td>
                                        <select name="schedule_c_part" id="category_schedule_c_part" class="regular-text">
                                            <option value=""><?php esc_html_e('None', 'timegrow'); ?></option>
                                            <option value="Part II"><?php esc_html_e('Part II (Main Expenses)', 'timegrow'); ?></option>
                                            <option value="Part V"><?php esc_html_e('Part V (Other Expenses)', 'timegrow'); ?></option>
                                        </select>
                                        <p class="description"><?php esc_html_e('IRS Schedule C classification for tax reporting.', 'timegrow'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="category_description"><?php esc_html_e('Description', 'timegrow'); ?></label>
                                    </th>
                                    <td>
                                        <textarea name="description" id="category_description" rows="5" class="large-text"></textarea>
                                        <p class="description"><?php esc_html_e('Optional description for this category.', 'timegrow'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="category_sort_order"><?php esc_html_e('Sort Order', 'timegrow'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" name="sort_order" id="category_sort_order" value="0" class="small-text">
                                        <p class="description"><?php esc_html_e('Lower numbers appear first.', 'timegrow'); ?></p>
                                    </td>
                                </tr>
                            </table>

                            <?php submit_button(__('Add New Category', 'timegrow')); ?>
                        </form>
                    </div>
                </div>

                <!-- Right Column: Categories List -->
                <div class="timegrow-column-right">
                    <div class="timegrow-card">
                        <h2><?php esc_html_e('All Categories', 'timegrow'); ?></h2>

                        <?php if (!empty($hierarchical_categories)): ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th scope="col" class="manage-column column-name column-primary"><?php esc_html_e('Name', 'timegrow'); ?></th>
                                        <th scope="col" class="manage-column column-schedule-c"><?php esc_html_e('Schedule C', 'timegrow'); ?></th>
                                        <th scope="col" class="manage-column column-slug"><?php esc_html_e('Slug', 'timegrow'); ?></th>
                                        <th scope="col" class="manage-column column-active"><?php esc_html_e('Active', 'timegrow'); ?></th>
                                        <th scope="col" class="manage-column column-actions"><?php esc_html_e('Actions', 'timegrow'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $this->render_category_rows($hierarchical_categories); ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="timegrow-notice timegrow-notice-info">
                                <span class="dashicons dashicons-info"></span>
                                <div>
                                    <strong><?php esc_html_e('No Categories Found', 'timegrow'); ?></strong>
                                    <p><?php esc_html_e('Get started by adding your first expense category.', 'timegrow'); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .timegrow-two-column-layout {
                display: grid;
                grid-template-columns: 400px 1fr;
                gap: 20px;
                margin-top: 20px;
            }

            @media screen and (max-width: 1200px) {
                .timegrow-two-column-layout {
                    grid-template-columns: 1fr;
                }
            }

            .timegrow-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                padding: 20px;
            }

            .timegrow-card h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #ddd;
            }

            .category-row {
                position: relative;
            }

            .category-row.child-category {
                background-color: #f9f9f9;
            }

            .category-indent {
                display: inline-block;
                width: 20px;
            }

            .category-name-col {
                font-weight: 600;
            }

            .category-row.child-category .category-name-col {
                font-weight: 400;
            }

            .category-actions {
                display: flex;
                gap: 10px;
            }

            .category-actions a {
                text-decoration: none;
            }

            .badge {
                display: inline-block;
                padding: 2px 8px;
                border-radius: 3px;
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
            }

            .badge-success {
                background-color: #d4edda;
                color: #155724;
            }

            .badge-warning {
                background-color: #fff3cd;
                color: #856404;
            }

            .badge-part-ii {
                background-color: #d1ecf1;
                color: #0c5460;
            }

            .badge-part-v {
                background-color: #e2e3e5;
                color: #383d41;
            }

            .required {
                color: #d63638;
            }
        </style>
        <?php
    }

    /**
     * Render category rows recursively
     */
    private function render_category_rows($categories, $level = 0) {
        foreach ($categories as $category) {
            $edit_url = add_query_arg([
                'page' => TIMEGROW_PARENT_MENU . '-expense-category-edit',
                'id' => $category->ID
            ], admin_url('admin.php'));

            $delete_url = add_query_arg([
                'action' => 'delete_category',
                'id' => $category->ID,
                '_wpnonce' => wp_create_nonce('timegrow_delete_category_' . $category->ID)
            ]);

            $toggle_url = add_query_arg([
                'action' => 'toggle_category',
                'id' => $category->ID,
                '_wpnonce' => wp_create_nonce('timegrow_toggle_category_' . $category->ID)
            ]);

            $child_class = $level > 0 ? 'child-category' : '';
            ?>
            <tr class="category-row <?php echo esc_attr($child_class); ?>">
                <td class="column-name column-primary category-name-col">
                    <?php
                    for ($i = 0; $i < $level; $i++) {
                        echo '<span class="category-indent">—</span>';
                    }
                    echo esc_html($category->name);
                    ?>
                    <button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>
                </td>
                <td class="column-schedule-c">
                    <?php if (!empty($category->schedule_c_part)): ?>
                        <span class="badge badge-part-<?php echo strtolower(str_replace(' ', '-', $category->schedule_c_part)); ?>">
                            <?php echo esc_html($category->schedule_c_part); ?>
                        </span>
                    <?php else: ?>
                        <span style="color: #999;">—</span>
                    <?php endif; ?>
                </td>
                <td class="column-slug">
                    <code><?php echo esc_html($category->slug); ?></code>
                </td>
                <td class="column-active">
                    <span class="badge <?php echo $category->is_active ? 'badge-success' : 'badge-warning'; ?>">
                        <?php echo $category->is_active ? esc_html__('Active', 'timegrow') : esc_html__('Inactive', 'timegrow'); ?>
                    </span>
                </td>
                <td class="column-actions category-actions">
                    <a href="<?php echo esc_url($edit_url); ?>" class="button button-small"><?php esc_html_e('Edit', 'timegrow'); ?></a>
                    <a href="<?php echo esc_url($toggle_url); ?>" class="button button-small">
                        <?php echo $category->is_active ? esc_html__('Deactivate', 'timegrow') : esc_html__('Activate', 'timegrow'); ?>
                    </a>
                    <?php if (empty($category->children)): ?>
                        <a href="<?php echo esc_url($delete_url); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this category?', 'timegrow'); ?>');">
                            <?php esc_html_e('Delete', 'timegrow'); ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php

            // Render children
            if (!empty($category->children)) {
                $this->render_category_rows($category->children, $level + 1);
            }
        }
    }

    /**
     * Render category options for select dropdown
     */
    private function render_category_options($categories, $level = 0, $selected = null) {
        foreach ($categories as $category) {
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $level);
            $selected_attr = ($selected && $selected == $category->ID) ? 'selected' : '';
            echo '<option value="' . esc_attr($category->ID) . '" ' . $selected_attr . '>';
            echo $indent . esc_html($category->name);
            echo '</option>';

            if (!empty($category->children)) {
                $this->render_category_options($category->children, $level + 1, $selected);
            }
        }
    }

    /**
     * Display edit category form
     */
    public function edit_category($category, $hierarchical_categories) {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);
        ?>
        <div class="wrap timegrow-page timegrow-page-container">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Edit Category', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Update expense category details', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-edit"></span>
                </div>
            </div>

            <div class="timegrow-card" style="max-width: 800px; margin: 20px auto;">
                <form method="post" action="">
                    <?php wp_nonce_field('timegrow_edit_category', 'timegrow_category_nonce'); ?>
                    <input type="hidden" name="action" value="edit_category">
                    <input type="hidden" name="category_id" value="<?php echo esc_attr($category->ID); ?>">

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="category_name"><?php esc_html_e('Name', 'timegrow'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" name="name" id="category_name" class="regular-text" value="<?php echo esc_attr($category->name); ?>" required>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="category_slug"><?php esc_html_e('Slug', 'timegrow'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="slug" id="category_slug" class="regular-text" value="<?php echo esc_attr($category->slug); ?>">
                                <p class="description"><?php esc_html_e('The "slug" is the URL-friendly version of the name.', 'timegrow'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="category_parent"><?php esc_html_e('Parent Category', 'timegrow'); ?></label>
                            </th>
                            <td>
                                <select name="parent_id" id="category_parent" class="regular-text">
                                    <option value=""><?php esc_html_e('None (Top Level)', 'timegrow'); ?></option>
                                    <?php $this->render_category_options($hierarchical_categories, 0, $category->parent_id); ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="category_schedule_c_part"><?php esc_html_e('Schedule C Part', 'timegrow'); ?></label>
                            </th>
                            <td>
                                <select name="schedule_c_part" id="category_schedule_c_part" class="regular-text">
                                    <option value=""><?php esc_html_e('None', 'timegrow'); ?></option>
                                    <option value="Part II" <?php selected($category->schedule_c_part, 'Part II'); ?>><?php esc_html_e('Part II (Main Expenses)', 'timegrow'); ?></option>
                                    <option value="Part V" <?php selected($category->schedule_c_part, 'Part V'); ?>><?php esc_html_e('Part V (Other Expenses)', 'timegrow'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="category_description"><?php esc_html_e('Description', 'timegrow'); ?></label>
                            </th>
                            <td>
                                <textarea name="description" id="category_description" rows="5" class="large-text"><?php echo esc_textarea($category->description); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="category_sort_order"><?php esc_html_e('Sort Order', 'timegrow'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="sort_order" id="category_sort_order" value="<?php echo esc_attr($category->sort_order); ?>" class="small-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="category_is_active"><?php esc_html_e('Status', 'timegrow'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="is_active" id="category_is_active" value="1" <?php checked($category->is_active, 1); ?>>
                                    <?php esc_html_e('Active', 'timegrow'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <?php submit_button(__('Update Category', 'timegrow'), 'primary', 'submit', false); ?>
                        <a href="<?php echo admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-expense-categories'); ?>" class="button button-secondary"><?php esc_html_e('Cancel', 'timegrow'); ?></a>
                    </p>
                </form>
            </div>
        </div>

        <style>
            .timegrow-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                padding: 20px;
            }

            .required {
                color: #d63638;
            }
        </style>
        <?php
    }
}
