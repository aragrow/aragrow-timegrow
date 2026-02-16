<?php
// ####TimeGrowNexusClockView.php ####

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class TimeGrowNexusExpenseView {

    /**
     * Displays the Clock In / Clock Out interface page using plain JavaScript.
     *
     * @param WP_User $user The current WordPress user object.
     * @param array $args Additional arguments.
     */
    public function display($user, $projects = []) {
        if (!$user || !$user->ID) {
            echo '<div class="wrap"><p>' . esc_html__('Error: User not found or not logged in.', 'timegrow') . '</p></div>';
            return;
        }

        // Data to pass to JavaScript
        // Handle for JS file will be 'clock-js' (or similar)
        $js_data = [
            'userId'             => $user->ID,
            'userName'           => $user->display_name,
            'apiNonce'           => wp_create_nonce('wp_rest'),
            'timegrowApiEndpoint'=> rest_url('timegrow/v1/'),
        ];
        // Pass data to your plain JavaScript file.
        // Ensure 'nexus-clock-script' is the handle of the enqueued script.
        wp_localize_script('timegrow-nexus-expense-script', 'timegrowClockAppVanillaData', $js_data);

        ?>
        <div class="wrap timegrow-modern-wrapper timegrow-page-container timegrow-clock-page-container">
            <!-- Modern Header -->
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Record Expenses', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Track project-related expenses and receipts', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration">
                    <span class="dashicons dashicons-cart"></span>
                </div>
            </div>

            <?php settings_errors('timegrow_expense_messages'); ?>

            <?php if (WP_DEBUG): ?>
            <!-- SQL Debug Panel -->
            <div class="timegrow-debug-panel">
                <details>
                    <summary>🔍 Debug Info</summary>
                    <div class="timegrow-debug-content">
                        <pre><?php
                        echo "User ID: " . esc_html($user->ID) . "\n";
                        echo "User Name: " . esc_html($user->display_name) . "\n";
                        echo "Projects Count: " . esc_html(count($projects)) . "\n";
                        if (isset($GLOBALS['wpdb']->last_query)) {
                            echo "\nLast Query:\n" . esc_html($GLOBALS['wpdb']->last_query);
                        }
                        ?></pre>
                    </div>
                </details>
            </div><!-- .timegrow-debug-panel -->
            <?php endif; ?>
            <!-- Project Tiles (hidden but needed for dropdown data source) -->
            <div id="project-tiles-container" class="timegrow-project-tiles" style="display:none">          
                <div class="project-list-container">                            
                    <?php foreach ($projects as $project) : ?>
                    <div class="timegrow-project-tile" draggable="true" data-project-id="<?php echo esc_attr($project->ID); ?>" data-project-name="<?php echo esc_attr($project->name) ?>" data-project-desc="<?php echo esc_attr($project->description) ?>">
                        <h3><?php echo esc_html($project->name); ?></h3>
                        <p><?php echo esc_html($project->description); ?></p>    
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="timegrow-nexus-container">
                <form id="expense-form">
                    <input type="hidden" id="expense_id" name="expense_id">
                    <input type="hidden" name="action" value="save_expense">
                    <input type="hidden" name="member_id" value="<?php echo $user->ID ?>" />

                            <!-- Receipt Upload - Moved to top -->
                            <div class="receipt-upload-stage">
                                <h2><?php esc_html_e('Attach Receipts (Optional)', 'timegrow'); ?></h2>
                                <div id="receipt-drop-zone" class="receipt-drop-area">
                                    <span class="dashicons dashicons-cloud-upload"></span>
                                    <p>Drag & drop receipt files here, or click to select files.</p>
                                    <small>(Max 5MB per file. Allowed: JPG, PNG, PDF)</small>
                                </div>
                                <input type="file" id="receipt-file-input" multiple hidden accept="image/*,.pdf">
                                <div id="receipt-preview-area" class="receipt-preview-list">
                                    <!-- JS will populate this with previews of uploaded/selected files -->
                                </div>
                            </div>

                            <!-- Project Assignment -->
                            <div id="expense-project-drop-section" class="timegrow-drop-section">
                                <p class="drop-zone-text"><?php esc_html_e('Assign to Project (Optional)', 'timegrow'); ?></p>
                                <div id="expense-drop-zone-display" class="selected-item-display" style="min-height: 60px; display: block; text-align: center;">
                                    <span class="project-drop-placeholder"><?php esc_html_e('Drop Project Here', 'timegrow'); ?></span>
                                    <div class="selected-project-details" style="display:none;">
                                        <!-- JS will fill this: Project selected: Project Name (ID) <br/> Description -->
                                    </div>
                                    <button type="button" id="clear-dropped-project-btn" class="clear-selection-btn" style="display:none; margin-top: 5px;">Clear Project</button>
                                </div>
                                <input type="hidden" id="selected-expense-project-id" name="expense_project_id">
                            </div>

                            <!-- Expense Details -->
                            <h2><?php esc_html_e('Expense Details', 'timegrow'); ?></h2>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="expense-date">Date of Expense <span class="required">*</span></label>
                                    <input type="date" id="expense-date" name="expense_date" required>
                                </div>

                                <div class="form-group">
                                    <label for="expense-category">Category <span class="required">*</span></label>
                                    <select id="expense-category" name="expense_category" required>
                                        <option value="">-- Select Category --</option>
                                        <option value="travel">Travel</option>
                                        <option value="meals">Meals & Entertainment</option>
                                        <option value="software">Software & Subscriptions</option>
                                        <option value="supplies">Office Supplies</option>
                                        <option value="training">Training & Development</option>
                                        <option value="utilities">Utilities</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>

                                <div class="form-group amount-group">
                                    <label for="expense-amount">Amount <span class="required">*</span></label>
                                    <input type="number" id="expense-amount" name="expense_amount" placeholder="e.g., 25.99" step="0.01" min="0" required>
                                </div>

                                <div class="form-group currency-group">
                                    <label for="expense-currency">Currency <span class="required">*</span></label>
                                    <select id="expense-currency" name="expense_currency" required>
                                        <option value="USD">USD ($)</option>
                                        <option value="EUR">EUR (€)</option>
                                        <option value="GBP">GBP (£)</option>
                                        <option value="CAD">CAD (C$)</option>
                                    </select>
                                </div>

                                <div class="form-group form-group-full">
                                    <label for="expense-description">Description / Notes</label>
                                    <textarea id="expense-description" name="expense_description" rows="3" placeholder="e.g., Lunch meeting with Client X"></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="expense-payment-method">Payment Method</label>
                                    <select id="expense-payment-method" name="expense_payment_method">
                                        <option value="">-- Select Method --</option>
                                        <option value="personal_card">Personal Card (Reimbursable)</option>
                                        <option value="company_card">Company Card</option>
                                        <option value="cash">Cash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" id="save-expense-btn" class="timegrow-button active">Save Expense</button>
                                <button type="button" id="clear-expense-form-btn" class="timegrow-button disabled">Clear Form</button>
                            </div>
                            <div id="expense-form-message" class="form-message" style="display:none;"></div>
                </form>
            </div>
        </div>

        <?php
    }

}