<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rules every expense recorded from Nexus (Record Expenses) follows.
 *
 * The browser only suggests whose expense it is and what it is for:
 * - A team member records only their own expenses (their team member, from the logged-in user). They
 *   assign it to themselves (a general expense), or to a project or client they may record time on.
 * - An administrator records for any team member, or none (a company expense), and assigns it to any
 *   project or client.
 * The category must be an active one that expenses use (not a heading), the date not in the future,
 * the amount above 0.
 */
class TimeGrowExpenseRules {

    const PAYMENT_METHODS = ['personal_card', 'company_card', 'bank_transfer', 'cash', 'other'];
    const MAX_AMOUNT      = 1000000;
    const MAX_RECEIPTS    = 5;

    /**
     * What may be chosen, for the logged-in person:
     * { is_admin, own_member, members: id => name (administrators), projects: id => [name, client_id],
     *   clients: id => name, categories: headings with ->children (active), category_ids: int[] }
     */
    public static function context() {
        $is_admin = TimeGrowTimeEntryRules::is_admin();
        $team     = new TimeGrowTeamMemberModel();
        $own      = $team->get_by_user_id(get_current_user_id());
        $own_id   = $own ? (int) $own->ID : 0;

        $members = [];
        if ($is_admin) {
            foreach ((array) $team->select() as $m) {
                $members[(int) $m->ID] = (string) $m->name;
            }
        }

        // Projects: any active one for administrators; the ones the member may record time on otherwise
        $allowed  = $is_admin ? null : array_flip(TimeGrowTimeEntryRules::project_ids($own_id));
        $projects = [];
        foreach ((array) $team->get_projects_for_member(-1) as $p) {
            if (($allowed === null || isset($allowed[(int) $p->ID])) && TimeGrowProjectPicker::client_exists($p->client_id)) {
                $projects[(int) $p->ID] = ['name' => (string) $p->name, 'client_id' => (int) $p->client_id];
            }
        }

        // Clients: every client for administrators; the clients of the member's projects otherwise
        $clients = [];
        if ($is_admin) {
            foreach ((array) (new TimeGrowClientModel())->select() as $u) {
                $clients[(int) $u->ID] = (string) $u->display_name;
            }
        } else {
            foreach ($projects as $p) {
                $clients[$p['client_id']] = TimeGrowProjectPicker::client_name($p['client_id']);
            }
        }
        asort($clients, SORT_NATURAL | SORT_FLAG_CASE);

        $tree = (new TimeGrowExpenseCategoryModel())->get_hierarchical(true);
        return [
            'is_admin'     => $is_admin,
            'own_member'   => $own_id,
            'own_name'     => $own ? (string) $own->name : '',
            'members'      => $members,
            'projects'     => $projects,
            'clients'      => $clients,
            'categories'   => $tree,
            'category_ids' => self::leaf_ids($tree),
        ];
    }

    /** The categories expenses use: children, and headings that have none. Pure. */
    public static function leaf_ids(array $tree) {
        $ids = [];
        foreach ($tree as $heading) {
            if (!empty($heading->children)) {
                foreach ($heading->children as $child) {
                    $ids[] = (int) $child->ID;
                }
            } else {
                $ids[] = (int) $heading->ID;
            }
        }
        return $ids;
    }

    /**
     * The expense to save, or why it can't be saved. Pure apart from wp_strip_all_tags().
     *
     * @param array  $in    What was posted: member_id, assigned_to, project_id, client_id, expense_name,
     *                      expense_date, amount, expense_category_id, expense_payment_method, expense_description.
     * @param array  $ctx   context().
     * @param string $today YYYY-MM-DD (site time).
     * @return array [data for TimeGrowExpenseModel::create(), problem ('' when it can be saved)]
     */
    public static function check(array $in, array $ctx, $today) {
        // Whose expense: a team member's own, always; an administrator's pick (0 = the company)
        if ($ctx['is_admin']) {
            $member = (int) ($in['member_id'] ?? 0);
            if ($member !== 0 && !isset($ctx['members'][$member])) {
                return [null, __('Pick a team member from the list.', 'timegrow')];
            }
        } else {
            $member = (int) $ctx['own_member'];
            if (!$member) {
                return [null, __('Your user is not set up as a team member, so you can\'t record expenses yet.', 'timegrow')];
            }
        }

        $assigned = (string) ($in['assigned_to'] ?? 'general');
        $assigned_id = 0;
        if ($assigned === 'project') {
            $assigned_id = (int) ($in['project_id'] ?? 0);
            if (!isset($ctx['projects'][$assigned_id])) {
                return [null, $ctx['is_admin'] ? __('Pick a project from the list.', 'timegrow') : __('Pick one of your projects.', 'timegrow')];
            }
        } elseif ($assigned === 'client') {
            $assigned_id = (int) ($in['client_id'] ?? 0);
            if (!isset($ctx['clients'][$assigned_id])) {
                return [null, $ctx['is_admin'] ? __('Pick a client from the list.', 'timegrow') : __('Pick one of your clients.', 'timegrow')];
            }
        } elseif ($assigned !== 'general') {
            return [null, __('Choose who the expense is for.', 'timegrow')];
        }

        $name = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) ($in['expense_name'] ?? ''))));
        if (strlen($name) < 2 || strlen($name) > 255) {
            return [null, __('Give the expense a name, for example the vendor.', 'timegrow')];
        }
        $date = (string) ($in['expense_date'] ?? '');
        $d = DateTime::createFromFormat('!Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            return [null, __('Enter the date of the expense.', 'timegrow')];
        }
        if ($date > $today) {
            return [null, __('The date can\'t be in the future.', 'timegrow')];
        }
        $amount = is_numeric($in['amount'] ?? null) ? round((float) $in['amount'], 2) : 0;
        if ($amount <= 0 || $amount > self::MAX_AMOUNT) {
            return [null, __('Enter an amount above 0.', 'timegrow')];
        }
        $category = (int) ($in['expense_category_id'] ?? 0);
        if (!in_array($category, $ctx['category_ids'], true)) {
            return [null, __('Pick a category.', 'timegrow')];
        }
        $method = (string) ($in['expense_payment_method'] ?? '');
        if (!in_array($method, self::PAYMENT_METHODS, true)) {
            return [null, __('Pick how it was paid.', 'timegrow')];
        }
        $description = trim(wp_strip_all_tags((string) ($in['expense_description'] ?? '')));
        $description = function_exists('mb_substr') ? mb_substr($description, 0, 1000) : substr($description, 0, 1000);

        return [[
            'member_id'              => $member ?: null,
            'expense_name'           => $name,
            'expense_date'           => $date,
            'amount'                 => $amount,
            'expense_category_id'    => $category,
            'assigned_to'            => $assigned,
            'assigned_to_id'         => $assigned_id ?: null,
            'expense_payment_method' => $method,
            'expense_description'    => $description !== '' ? $description : $name, // the column needs text
            'status'                 => 1,
        ], ''];
    }

    /**
     * Uploaded receipt files as a list of single files, or why they can't be used. Pure.
     *
     * @param array|null $files $_FILES['receipts'] (name[], type[], tmp_name[], error[], size[]).
     * @param int        $max_size Bytes per file (TimeGrowExpenseReceiptModel::upload_file's limit).
     * @return array [files, problem]
     */
    public static function receipt_files($files, $max_size) {
        if (!is_array($files) || !isset($files['name'])) {
            return [[], ''];
        }
        $list = [];
        foreach ((array) $files['name'] as $i => $name) {
            if ((int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $list[] = ['name' => (string) $name, 'type' => (string) ($files['type'][$i] ?? ''), 'tmp_name' => (string) ($files['tmp_name'][$i] ?? ''),
                       'error' => (int) ($files['error'][$i] ?? 0), 'size' => (int) ($files['size'][$i] ?? 0)];
        }
        if (count($list) > self::MAX_RECEIPTS) {
            return [[], sprintf(__('Attach at most %d receipts.', 'timegrow'), self::MAX_RECEIPTS)];
        }
        foreach ($list as $f) {
            if ($f['error'] !== UPLOAD_ERR_OK) {
                return [[], sprintf(__('%s could not be uploaded.', 'timegrow'), $f['name'])];
            }
            if (!preg_match('/\.(jpe?g|png|pdf)$/i', $f['name'])) {
                return [[], sprintf(__('%s is not a JPG, PNG or PDF.', 'timegrow'), $f['name'])];
            }
            if ($f['size'] > $max_size) {
                return [[], sprintf(__('%1$s is larger than %2$s.', 'timegrow'), $f['name'], size_format($max_size))];
            }
        }
        return [$list, ''];
    }
}
