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
        <div class="wrap timegrow-page-container timegrow-clock-page-container">
            <h1><?php esc_html_e('Record Expenses', 'timegrow'); ?></h1>

            <?php settings_errors('timegrow_expense_messages'); ?>
            <!-- Client Tiles (conditionally shown when clocked out) -->
            <div id="project-tiles-container" class="timegrow-project-tiles">          
                <div class="project-list-container">                            
                    <?php foreach ($projects as $project) : ?>
                    <div class="timegrow-project-tile" draggable="true" data-project-id="<?php echo esc_attr($project->ID); ?>" data-project-name="<?php echo esc_attr($project->name) ?>" data-project-desc="<?php echo esc_attr($project->description) ?>">
                        <h3><?php echo esc_html($project->name); ?></h3>
                        <p><?php echo esc_html($project->description); ?></p>    
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="wrap timegrow-page-container timegrow-expense-recorder-page">

                <div id="timegrow-expense-recorder" class="timegrow-expense-container">


                    <!-- Stage 2: Expense Details Form -->
                    <div class="expense-form-stage">
                        <h2>3. Expense Details</h2>
                        <form id="expense-form">
                            <input type="hidden" id="expense_id" name="expense_id">
                            <input type="hidden" name="action" value="save_expense">
                            <input type="hidden" name="member_id" value="<?php echo $user->ID ?>" />
                            <!-- Project Drop Section -->
                            <!-- Inside your <form id="expense-form"> in TimeGrowNexusExpenseView.php -->
                            <div id="expense-project-drop-section" class="timegrow-drop-section">
                                <p class="drop-zone-text"><?php esc_html_e('Assign to Project (Optional - Drag Project Below)', 'timegrow'); ?></p>
                                <div id="expense-drop-zone-display" class="selected-item-display" style="min-height: 60px; /* Ensure space */ display: block; /* Make it always visible */ text-align: center;">
                                    <!-- This will be populated by JS -->
                                    <span class="project-drop-placeholder"><?php esc_html_e('Drop Project Here', 'timegrow'); ?></span>
                                    <div class="selected-project-details" style="display:none;">
                                        <!-- JS will fill this: Project selected: Project Name (ID) <br/> Description -->
                                    </div>
                                    <button type="button" id="clear-dropped-project-btn" class="clear-selection-btn" style="display:none; margin-top: 5px;">Clear Project</button>
                                </div>
                                <!-- Hidden input to store the selected project ID -->
                                <input type="hidden" id="selected-expense-project-id" name="expense_project_id">
                            </div>
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
                                        <!-- Add more common currencies -->
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


                            <!-- Stage 3: Receipt Upload -->
                            <div class="receipt-upload-stage"> <!-- You had this -->
                                <h2>4. Attach Receipts (Optional)</h2>
                                <div id="receipt-drop-zone" class="receipt-drop-area">
                                    <span class="dashicons dashicons-cloud-upload"></span>
                                    <p>Drag & drop receipt files here, or click to select files.</p>
                                    <!-- INPUT IS HIDDEN AND OUTSIDE THE CLICKABLE PROMPT'S DIRECT FLOW -->
                                    <small>(Max 5MB per file. Allowed: JPG, PNG, PDF)</small>
                                </div>
                                <input type="file" id="receipt-file-input" multiple hidden accept="image/*,.pdf">
                                <div id="receipt-preview-area" class="receipt-preview-list"> <!-- ADD THIS IF MISSING -->
                                    <!-- JS will populate this with previews of uploaded/selected files -->
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
            </div>
        
        <?php
    }

}