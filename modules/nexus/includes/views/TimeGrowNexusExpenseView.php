<?php
// ####TimeGrowNexusExpenseView.php ####

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Nexus > Record Expenses: the form (saved by AJAX timegrow_nexus_save_expense, TimeGrowNexusExpense)
 * and the latest expenses. What can be picked comes from TimeGrowExpenseRules::context(): a team member
 * records their own expenses, for themselves or their projects and clients; an administrator records
 * for any team member, project or client.
 */
class TimeGrowNexusExpenseView {

    /**
     * @param WP_User $user     The current WordPress user object.
     * @param array   $projects Not used: the projects come from TimeGrowExpenseRules::context().
     */
    public function display($user, $projects = []) {
        if (!$user || !$user->ID) {
            echo '<div class="wrap"><p>' . esc_html__('Error: User not found or not logged in.', 'timegrow') . '</p></div>';
            return;
        }
        $ctx    = TimeGrowExpenseRules::context();
        $recent = TimeGrowNexusExpense::recent($ctx);
        $ai     = TimeGrowNexusExpense::can_read_with_ai();
        $max    = TimeGrowExpenseReceiptModel::MAX_SIZE;

        wp_localize_script('timegrow-nexus-expense-script', 'timegrowExpense', [
            'ajaxUrl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce(TimeGrowNexusExpense::NONCE),
            'aiNonce'     => wp_create_nonce('timegrow_ajax_nonce'), // analyze_receipt_realtime
            'canReadAi'   => $ai,
            'maxSize'     => $max,
            'maxFiles'    => TimeGrowExpenseRules::MAX_RECEIPTS,
            'today'       => current_time('Y-m-d'),
        ]);

        $by_client = [];
        foreach ($ctx['projects'] as $id => $p) {
            $by_client[$p['client_id']][$id] = $p['name'];
        }
        $methods = [
            'personal_card' => __('Personal card (reimbursable)', 'timegrow'),
            'company_card'  => __('Company card', 'timegrow'),
            'cash'          => __('Cash', 'timegrow'),
            'bank_transfer' => __('Bank transfer', 'timegrow'),
            'other'         => __('Other', 'timegrow'),
        ];
        $can_record = $ctx['is_admin'] || $ctx['own_member'];
        ?>
        <div class="wrap timegrow-modern-wrapper timegrow-page-container timegrow-expense-page">
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Record Expenses', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php echo $ctx['is_admin']
                        ? esc_html__('Record an expense for any team member, project or client, with its receipts.', 'timegrow')
                        : esc_html__('Record your expenses, for yourself or your projects and clients, with their receipts.', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration"><span class="dashicons dashicons-cart"></span></div>
            </div>

            <?php if (!$can_record) : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e('Your user is not set up as a team member, so you can\'t record expenses yet. Ask an administrator to add you.', 'timegrow'); ?></p></div>
            <?php else : ?>
            <div class="timegrow-nexus-container">
                <form id="expense-form" novalidate>
                    <div class="receipt-upload-stage">
                        <h2><?php esc_html_e('Receipts (optional)', 'timegrow'); ?></h2>
                        <div id="receipt-drop-zone" class="receipt-drop-area" role="button" tabindex="0">
                            <span class="dashicons dashicons-cloud-upload" aria-hidden="true"></span>
                            <p><?php esc_html_e('Drag and drop receipts here, or click to choose files.', 'timegrow'); ?></p>
                            <small><?php echo esc_html(sprintf(__('Up to %1$d files, %2$s each. JPG, PNG or PDF.', 'timegrow'), TimeGrowExpenseRules::MAX_RECEIPTS, size_format($max))); ?></small>
                        </div>
                        <input type="file" id="receipt-file-input" multiple hidden accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf">
                        <div id="receipt-preview-area" class="receipt-preview-list"></div>
                        <?php if ($ai) : ?>
                            <p><button type="button" id="read-receipt-ai" class="button" disabled><span class="dashicons dashicons-superhero" aria-hidden="true"></span> <?php esc_html_e('Read the first receipt with AI', 'timegrow'); ?></button>
                            <span class="description"><?php esc_html_e('Fills in the fields below. Check them before saving.', 'timegrow'); ?></span></p>
                        <?php endif; ?>
                    </div>

                    <h2><?php esc_html_e('Whose expense, and what it is for', 'timegrow'); ?></h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <?php if ($ctx['is_admin']) : ?>
                                <label for="expense-member"><?php esc_html_e('Team member', 'timegrow'); ?></label>
                                <select id="expense-member" name="member_id">
                                    <option value="0"><?php esc_html_e('No team member (company expense)', 'timegrow'); ?></option>
                                    <?php foreach ($ctx['members'] as $id => $name) : ?>
                                        <option value="<?php echo (int) $id; ?>" <?php selected($id, $ctx['own_member']); ?>><?php echo esc_html($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else : ?>
                                <label><?php esc_html_e('Team member', 'timegrow'); ?></label>
                                <p class="expense-fixed-value"><?php echo esc_html($ctx['own_name']); ?></p>
                            <?php endif; ?>
                        </div>
                        <fieldset class="form-group expense-assign">
                            <legend><?php esc_html_e('For', 'timegrow'); ?></legend>
                            <label><input type="radio" name="assigned_to" value="general" checked> <?php echo $ctx['is_admin'] ? esc_html__('General (no project or client)', 'timegrow') : esc_html__('Myself (general expense)', 'timegrow'); ?></label>
                            <label><input type="radio" name="assigned_to" value="project" <?php disabled(!$ctx['projects']); ?>> <?php esc_html_e('A project', 'timegrow'); ?></label>
                            <label><input type="radio" name="assigned_to" value="client" <?php disabled(!$ctx['clients']); ?>> <?php esc_html_e('A client', 'timegrow'); ?></label>
                        </fieldset>
                        <div class="form-group" id="expense-project-group" hidden>
                            <label for="expense-project"><?php esc_html_e('Project', 'timegrow'); ?> <span class="required">*</span></label>
                            <select id="expense-project" name="project_id">
                                <option value=""><?php esc_html_e('Choose a project', 'timegrow'); ?></option>
                                <?php foreach ($by_client as $client_id => $list) : ?>
                                    <optgroup label="<?php echo esc_attr(TimeGrowProjectPicker::client_name($client_id)); ?>">
                                        <?php foreach ($list as $id => $name) : ?>
                                            <option value="<?php echo (int) $id; ?>"><?php echo esc_html($name); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group" id="expense-client-group" hidden>
                            <label for="expense-client"><?php esc_html_e('Client', 'timegrow'); ?> <span class="required">*</span></label>
                            <select id="expense-client" name="client_id">
                                <option value=""><?php esc_html_e('Choose a client', 'timegrow'); ?></option>
                                <?php foreach ($ctx['clients'] as $id => $name) : ?>
                                    <option value="<?php echo (int) $id; ?>"><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <h2><?php esc_html_e('Expense details', 'timegrow'); ?></h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="expense-name"><?php esc_html_e('Name', 'timegrow'); ?> <span class="required">*</span></label>
                            <input type="text" id="expense-name" name="expense_name" maxlength="255" placeholder="<?php esc_attr_e('For example the vendor: Staples', 'timegrow'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="expense-date"><?php esc_html_e('Date', 'timegrow'); ?> <span class="required">*</span></label>
                            <input type="date" id="expense-date" name="expense_date" max="<?php echo esc_attr(current_time('Y-m-d')); ?>" value="<?php echo esc_attr(current_time('Y-m-d')); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="expense-amount"><?php esc_html_e('Amount', 'timegrow'); ?> <span class="required">*</span></label>
                            <input type="number" id="expense-amount" name="amount" placeholder="25.99" step="0.01" min="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="expense-category"><?php esc_html_e('Category', 'timegrow'); ?> <span class="required">*</span></label>
                            <select id="expense-category" name="expense_category_id" required>
                                <option value=""><?php esc_html_e('Choose a category', 'timegrow'); ?></option>
                                <?php foreach ($ctx['categories'] as $heading) : ?>
                                    <?php if (!empty($heading->children)) : ?>
                                        <optgroup label="<?php echo esc_attr($heading->name); ?>">
                                            <?php foreach ($heading->children as $child) : ?>
                                                <option value="<?php echo (int) $child->ID; ?>"><?php echo esc_html($child->name); ?></option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php else : ?>
                                        <option value="<?php echo (int) $heading->ID; ?>"><?php echo esc_html($heading->name); ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="expense-payment-method"><?php esc_html_e('Paid with', 'timegrow'); ?> <span class="required">*</span></label>
                            <select id="expense-payment-method" name="expense_payment_method" required>
                                <?php foreach ($methods as $value => $label) : ?>
                                    <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group form-group-full">
                            <label for="expense-description"><?php esc_html_e('Description', 'timegrow'); ?></label>
                            <textarea id="expense-description" name="expense_description" rows="3" maxlength="1000" placeholder="<?php esc_attr_e('For example: lunch meeting with the client', 'timegrow'); ?>"></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" id="save-expense-btn" class="timegrow-button active"><?php esc_html_e('Save expense', 'timegrow'); ?></button>
                        <button type="button" id="clear-expense-form-btn" class="timegrow-button disabled"><?php esc_html_e('Clear form', 'timegrow'); ?></button>
                    </div>
                    <div id="expense-form-message" class="form-message" role="status" hidden></div>
                </form>
            </div>

            <h2 class="timegrow-section-title"><?php echo $ctx['is_admin'] ? esc_html__('Latest expenses', 'timegrow') : esc_html__('Your latest expenses', 'timegrow'); ?></h2>
            <?php if (!$recent) : ?>
                <p><?php esc_html_e('No expenses yet.', 'timegrow'); ?></p>
            <?php else : ?>
                <table class="widefat striped timegrow-expense-recent">
                    <thead><tr>
                        <th><?php esc_html_e('Date', 'timegrow'); ?></th>
                        <th><?php esc_html_e('Name', 'timegrow'); ?></th>
                        <?php if ($ctx['is_admin']) : ?><th><?php esc_html_e('Team member', 'timegrow'); ?></th><?php endif; ?>
                        <th><?php esc_html_e('For', 'timegrow'); ?></th>
                        <th><?php esc_html_e('Category', 'timegrow'); ?></th>
                        <th class="num"><?php esc_html_e('Amount', 'timegrow'); ?></th>
                        <th><?php esc_html_e('Receipts', 'timegrow'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($recent as $r) : ?>
                        <tr>
                            <td><?php echo esc_html(mysql2date(get_option('date_format'), $r->expense_date)); ?></td>
                            <td><?php echo esc_html($r->expense_name); ?><?php if ((int) $r->status === 0) : ?> <span class="timegrow-badge timegrow-badge-inactive"><?php esc_html_e('Inactive', 'timegrow'); ?></span><?php endif; ?></td>
                            <?php if ($ctx['is_admin']) : ?><td><?php echo esc_html($r->member_name ?: __('Company', 'timegrow')); ?></td><?php endif; ?>
                            <td><?php echo esc_html($r->assigned_to === 'general' ? __('General', 'timegrow') : (string) $r->for_name); ?></td>
                            <td><?php echo esc_html((string) $r->category_name); ?></td>
                            <td class="num"><?php echo esc_html(TimeGrowNexusVoiceFormat::money($r->amount)); ?></td>
                            <td>
                                <?php foreach ($r->receipts as $i => $rid) : ?>
                                    <a href="<?php echo esc_url(TimeGrowExpenseReceiptModel::view_url($rid)); ?>" target="_blank" rel="noopener"><?php echo esc_html(sprintf(__('Receipt %d', 'timegrow'), $i + 1)); ?></a>
                                <?php endforeach; ?>
                                <?php if (!$r->receipts) : ?>&mdash;<?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <style>
            .timegrow-expense-page .expense-assign { border: 0; padding: 0; margin: 0; }
            .timegrow-expense-page .expense-assign legend { font-weight: 600; margin-bottom: 6px; }
            .timegrow-expense-page .expense-assign label { display: block; margin: 4px 0; font-weight: normal; }
            .timegrow-expense-page .expense-fixed-value { margin: 6px 0 0; font-size: 15px; }
            .timegrow-expense-page .timegrow-expense-recent { max-width: 1100px; margin-bottom: 24px; }
            .timegrow-expense-page .timegrow-expense-recent .num { text-align: right; }
            .timegrow-expense-page .receipt-preview-item { position: relative; }
            .timegrow-expense-page .receipt-preview-item .receipt-name { display: block; max-width: 120px; font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        </style>
        <?php
    }
}
