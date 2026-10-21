<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Nexus > Record Expenses: saving an expense and its receipts (AJAX timegrow_nexus_save_expense).
 *
 * Who may record what is decided by TimeGrowExpenseRules (a team member: their own expenses, for
 * themselves or their projects and clients; an administrator: anyone's, for any project or client).
 * Receipts go to the private receipts folder (TimeGrowExpenseReceiptModel) with the uploader recorded,
 * so the person who added them, and administrators, can open them.
 */
class TimeGrowNexusExpense {

    const NONCE = 'timegrow_nexus_expense';
    const RECENT = 10; // expenses listed under the form

    public static function init() {
        add_action('wp_ajax_timegrow_nexus_save_expense', [__CLASS__, 'handle_save']);
    }

    /** Record Expenses is for people with the Nexus capability (administrators always), and in the mobile app the mobile one. */
    public static function allowed() {
        return (current_user_can('access_nexus_record_expenses') || TimeGrowTimeEntryRules::is_admin())
            && (!class_exists('TimeGrowMobileAccess') || TimeGrowMobileAccess::mobile_allows('access_mobile_expenses'));
    }

    /** Whether the page may offer "Read with AI" (the same check the reader makes, and AI set up for receipts). */
    public static function can_read_with_ai() {
        $settings = class_exists('TimeGrowSettings') ? TimeGrowSettings::receipt_settings() : ['auto_analysis' => 'no'];
        return TimeGrow_Ajax_Handler::may_read_receipts() && ($settings['auto_analysis'] ?? 'no') === 'yes' && TimeGrowAI::is_configured('receipts');
    }

    /**
     * The latest expenses to list under the form: a team member's own; every expense for administrators.
     *
     * @return object[] { ID, expense_name, expense_date, amount, assigned_to, for_name, member_name, category_name, receipts: int[] }
     */
    public static function recent(array $ctx) {
        global $wpdb;
        $p     = $wpdb->prefix . TIMEGROW_PREFIX;
        $where = $ctx['is_admin'] ? '1=1' : $wpdb->prepare('e.member_id = %d', (int) $ctx['own_member']);
        if (!$ctx['is_admin'] && !$ctx['own_member']) {
            return [];
        }
        $rows = (array) $wpdb->get_results(
            "SELECT e.ID, e.expense_name, e.expense_date, e.amount, e.assigned_to, e.status,
                    CASE WHEN e.assigned_to = 'project' THEN pr.name WHEN e.assigned_to = 'client' THEN u.display_name ELSE NULL END AS for_name,
                    m.name AS member_name, c.name AS category_name
             FROM {$p}expense_tracker e
             LEFT JOIN {$p}project_tracker pr ON (e.assigned_to = 'project' AND pr.ID = e.assigned_to_id)
             LEFT JOIN {$wpdb->users} u ON (e.assigned_to = 'client' AND u.ID = e.assigned_to_id)
             LEFT JOIN {$p}team_member_tracker m ON m.ID = e.member_id
             LEFT JOIN {$p}expense_categories c ON c.ID = e.expense_category_id
             WHERE {$where}
             ORDER BY e.expense_date DESC, e.ID DESC
             LIMIT " . (int) self::RECENT
        );
        if ($rows) {
            $ids = implode(',', array_map(function ($r) { return (int) $r->ID; }, $rows));
            $receipts = [];
            foreach ((array) $wpdb->get_results("SELECT ID, expense_id FROM {$p}expense_receipt_tracker WHERE expense_id IN ({$ids}) ORDER BY ID") as $r) {
                $receipts[(int) $r->expense_id][] = (int) $r->ID;
            }
            foreach ($rows as $r) {
                $r->receipts = $receipts[(int) $r->ID] ?? [];
            }
        }
        return $rows;
    }

    /** AJAX timegrow_nexus_save_expense: check, upload the receipts, save the expense, link the receipts. */
    public static function handle_save() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!self::allowed()) {
            wp_send_json_error(['message' => __('You are not allowed to record expenses.', 'timegrow')], 403);
        }
        $ctx = TimeGrowExpenseRules::context();
        [$data, $problem] = TimeGrowExpenseRules::check(wp_unslash($_POST), $ctx, current_time('Y-m-d'));
        if ($problem !== '') {
            wp_send_json_error(['message' => $problem]);
        }
        [$files, $problem] = TimeGrowExpenseRules::receipt_files($_FILES['receipts'] ?? null, TimeGrowExpenseReceiptModel::MAX_SIZE);
        if ($problem !== '') {
            wp_send_json_error(['message' => $problem]);
        }

        // Receipts first: an expense is only saved when its receipts could be stored
        $receipt_model = new TimeGrowExpenseReceiptModel();
        $uploaded = [];
        foreach ($files as $file) {
            $up = $receipt_model->upload_file($file);
            if (is_wp_error($up)) {
                self::remove_files($uploaded);
                wp_send_json_error(['message' => sprintf(__('%1$s: %2$s', 'timegrow'), $file['name'], $up->get_error_message())]);
            }
            $uploaded[] = $up;
        }

        $now = current_time('mysql');
        // No member (a company expense) or no project or client: left out, so the columns stay NULL
        $row = array_filter($data, function ($v) { return $v !== null; }) + ['created_at' => $now, 'updated_at' => $now];
        $id  = (new TimeGrowExpenseModel())->create($row);
        if (!$id || is_wp_error($id)) {
            self::remove_files($uploaded);
            wp_send_json_error(['message' => __('The expense could not be saved.', 'timegrow')]);
        }
        foreach ($uploaded as $up) {
            $receipt_model->save_receipt_record($id, $up['url'], null, true);
        }
        timegrow_log(sprintf('Nexus expense %d saved by user %d for member %d (%d receipts)', $id, get_current_user_id(), (int) $data['member_id'], count($uploaded)));
        wp_send_json_success([
            'id'      => (int) $id,
            'message' => count($uploaded)
                ? sprintf(_n('Expense saved with %d receipt.', 'Expense saved with %d receipts.', count($uploaded), 'timegrow'), count($uploaded))
                : __('Expense saved.', 'timegrow'),
        ]);
    }

    private static function remove_files(array $uploaded) {
        foreach ($uploaded as $up) {
            if (!empty($up['file']) && is_file($up['file'])) {
                @unlink($up['file']);
            }
        }
    }
}
