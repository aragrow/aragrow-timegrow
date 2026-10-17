<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings > AI Prompts: the prompt versions for each AI tool (Voice AI, Receipt Reader).
 *
 * Each version is dated and has a status: Draft (can be edited or deleted), Active (in use, one
 * per tool) or Archived (used before, kept as history). Activating a version archives the one in
 * use. To change an active or archived version, duplicate it as a new draft. With no active
 * version, the tool uses the prompt built into TimeGrow.
 */
class TimeGrowAIPromptsPage {

    const NONCE    = 'timegrow_ai_prompt';
    const MAX_TEXT = 30000;

    public static function init() {
        add_action('admin_init', [__CLASS__, 'maybe_create_table']);
        add_action('admin_post_timegrow_ai_prompt_save', [__CLASS__, 'handle_save']);
        add_action('admin_post_timegrow_ai_prompt_do', [__CLASS__, 'handle_action']);
    }

    public static function maybe_create_table() {
        if (current_user_can(TIMEGROW_OWNER_CAP)) {
            (new TimeGrowAIPromptModel())->maybe_create_table();
        }
    }

    public static function url(array $args = []) {
        return add_query_arg(array_merge(['page' => TIMEGROW_PARENT_MENU . '-settings', 'tab' => 'prompts'], $args), admin_url('admin.php'));
    }

    private static function action_url($do, $id, $feature = '') {
        return wp_nonce_url(add_query_arg(array_filter(['action' => 'timegrow_ai_prompt_do', 'do' => $do, 'id' => (int) $id, 'feature' => $feature]), admin_url('admin-post.php')), self::NONCE . '_' . $do . '_' . (int) $id);
    }

    /** What a tool uses now: the active version's name and date, or the built-in prompt. */
    public static function in_use_label($feature) {
        $row = TimeGrowAIPrompts::active($feature);
        if ($row && !TimeGrowAIPrompts::missing_keys($feature, $row['content'])) {
            return sprintf(__('%1$s (since %2$s)', 'timegrow'), $row['name'], mysql2date(get_option('date_format'), $row['activated_at']));
        }
        return __('Built-in prompt', 'timegrow');
    }

    // ------------------------------------------------------------------
    // Actions
    // ------------------------------------------------------------------

    private static function back($args) {
        wp_safe_redirect(self::url($args));
        exit;
    }

    /** Save a draft (new or changed), and activate it when asked. */
    public static function handle_save() {
        if (!current_user_can(TIMEGROW_OWNER_CAP)) {
            wp_die(esc_html__('You are not allowed to change AI prompts.', 'timegrow'), 403);
        }
        check_admin_referer(self::NONCE . '_save');
        $model   = new TimeGrowAIPromptModel();
        $id      = (int) ($_POST['id'] ?? 0);
        $row     = $id ? $model->get($id) : null;
        $feature = $row ? $row['feature'] : sanitize_key($_POST['feature'] ?? '');
        if (!TimeGrowAIPrompts::is_tool($feature) || ($id && (!$row || $row['status'] !== 'draft'))) {
            self::back(['msg' => 'not_draft']);
        }
        $content = str_replace("\r\n", "\n", (string) wp_unslash($_POST['content'] ?? ''));
        $name    = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $notes   = sanitize_textarea_field(wp_unslash($_POST['notes'] ?? ''));
        if ($name === '') {
            $name = sprintf('%s %s', TimeGrowAIPrompts::tools()[$feature]['name'], current_time('Y-m-d H:i'));
        }
        if (trim($content) === '' || strlen($content) > self::MAX_TEXT) {
            self::back(['prompt' => $id ?: 'new', 'feature' => $feature, 'msg' => 'bad_text']);
        }
        if ($id) {
            $model->update_draft($id, $name, $content, $notes);
        } else {
            $id = $model->create($feature, $name, $content, $notes);
        }
        if (!$id) {
            self::back(['msg' => 'not_saved']);
        }
        if (!empty($_POST['activate'])) {
            if (TimeGrowAIPrompts::missing_keys($feature, $content)) {
                self::back(['prompt' => $id, 'msg' => 'missing_keys']);
            }
            $model->activate($id);
            timegrow_log(sprintf('AI prompt %d activated for %s by user %d', $id, $feature, get_current_user_id()));
            self::back(['feature' => $feature, 'msg' => 'activated']);
        }
        self::back(['prompt' => $id, 'msg' => 'saved']);
    }

    /** Activate, archive, delete (drafts only) or duplicate a version; duplicate id 0 = the built-in prompt. */
    public static function handle_action() {
        if (!current_user_can(TIMEGROW_OWNER_CAP)) {
            wp_die(esc_html__('You are not allowed to change AI prompts.', 'timegrow'), 403);
        }
        $do = sanitize_key($_GET['do'] ?? '');
        $id = (int) ($_GET['id'] ?? 0);
        check_admin_referer(self::NONCE . '_' . $do . '_' . $id);
        $model = new TimeGrowAIPromptModel();
        $row   = $id ? $model->get($id) : null;

        if ($do === 'duplicate') {
            $feature = $row ? $row['feature'] : sanitize_key($_GET['feature'] ?? '');
            if (!TimeGrowAIPrompts::is_tool($feature)) {
                self::back(['msg' => 'not_found']);
            }
            $content = $row ? $row['content'] : TimeGrowAIPrompts::default_instructions($feature);
            $name    = $row ? sprintf(__('Copy of %s', 'timegrow'), $row['name']) : sprintf(__('%s (from built-in)', 'timegrow'), TimeGrowAIPrompts::tools()[$feature]['name']);
            $new     = $model->create($feature, $name, $content, '');
            self::back($new ? ['prompt' => $new, 'msg' => 'duplicated'] : ['msg' => 'not_saved']);
        }
        if (!$row) {
            self::back(['msg' => 'not_found']);
        }
        if ($do === 'activate') {
            if (TimeGrowAIPrompts::missing_keys($row['feature'], $row['content'])) {
                self::back(['prompt' => $id, 'msg' => 'missing_keys']);
            }
            $model->activate($id);
            timegrow_log(sprintf('AI prompt %d activated for %s by user %d', $id, $row['feature'], get_current_user_id()));
            self::back(['feature' => $row['feature'], 'msg' => 'activated']);
        }
        if ($do === 'archive') {
            $model->archive($id);
            self::back(['feature' => $row['feature'], 'msg' => 'archived']);
        }
        if ($do === 'delete') {
            self::back(['feature' => $row['feature'], 'msg' => $model->delete_draft($id) ? 'deleted' : 'not_draft']);
        }
        self::back(['msg' => 'not_found']);
    }

    // ------------------------------------------------------------------
    // Screens
    // ------------------------------------------------------------------

    public static function render() {
        $model = new TimeGrowAIPromptModel();
        $model->maybe_create_table();
        ?>
        <div class="wrap timegrow-page">
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('AI Prompts', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('What each AI tool is told to do. Every version is kept, with its dates.', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration"><span class="dashicons dashicons-editor-code"></span></div>
            </div>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings')); ?>">&larr; <?php esc_html_e('Back to Settings', 'timegrow'); ?></a></p>
            <?php
            self::notice();
            $prompt = $_GET['prompt'] ?? '';
            if ($prompt !== '') {
                self::render_editor($model, $prompt === 'new' ? null : $model->get((int) $prompt), sanitize_key($_GET['feature'] ?? 'voice'));
            } else {
                self::render_list($model, sanitize_key($_GET['feature'] ?? ''));
            }
            ?>
        </div>
        <?php
    }

    private static function notice() {
        $messages = [
            'saved'        => ['success', __('Draft saved.', 'timegrow')],
            'activated'    => ['success', __('Prompt activated. The one used before was archived.', 'timegrow')],
            'archived'     => ['success', __('Prompt archived. The built-in prompt is used until you activate another.', 'timegrow')],
            'deleted'      => ['success', __('Draft deleted.', 'timegrow')],
            'duplicated'   => ['success', __('Copied to a new draft. Change it, then save or activate it.', 'timegrow')],
            'missing_keys' => ['error', __('This prompt can\'t be activated: it no longer asks for every answer TimeGrow reads. Add the missing keys listed below.', 'timegrow')],
            'bad_text'     => ['error', sprintf(__('The prompt can\'t be empty or longer than %s characters.', 'timegrow'), number_format_i18n(self::MAX_TEXT))],
            'not_draft'    => ['error', __('Only drafts can be changed or deleted. Duplicate this version to change it.', 'timegrow')],
            'not_saved'    => ['error', __('The prompt could not be saved.', 'timegrow')],
            'not_found'    => ['error', __('That prompt was not found.', 'timegrow')],
        ];
        $msg = sanitize_key($_GET['msg'] ?? '');
        if (isset($messages[$msg])) {
            printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($messages[$msg][0]), esc_html($messages[$msg][1]));
        }
    }

    private static function status_badge($status) {
        $labels = ['draft' => __('Draft', 'timegrow'), 'active' => __('Active', 'timegrow'), 'archived' => __('Archived', 'timegrow')];
        return '<span class="tg-prompt-status tg-prompt-status--' . esc_attr($status) . '">' . esc_html($labels[$status] ?? $status) . '</span>';
    }

    private static function when($datetime) {
        return $datetime ? esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $datetime)) : '&ndash;';
    }

    private static function render_list(TimeGrowAIPromptModel $model, $filter) {
        $tools = TimeGrowAIPrompts::tools();
        ?>
        <div class="tg-prompt-tools">
            <?php foreach ($tools as $feature => $tool): ?>
                <div class="tg-prompt-tool">
                    <span class="dashicons dashicons-<?php echo esc_attr($tool['icon']); ?>" aria-hidden="true"></span>
                    <div>
                        <strong><?php echo esc_html($tool['name']); ?></strong>
                        <span><?php esc_html_e('Uses:', 'timegrow'); ?> <?php echo esc_html(self::in_use_label($feature)); ?></span>
                    </div>
                    <a class="button" href="<?php echo esc_url(self::url(['prompt' => 'new', 'feature' => $feature])); ?>"><?php esc_html_e('New version', 'timegrow'); ?></a>
                </div>
            <?php endforeach; ?>
        </div>

        <ul class="subsubsub">
            <li><a href="<?php echo esc_url(self::url()); ?>" class="<?php echo $filter === '' ? 'current' : ''; ?>"><?php esc_html_e('All', 'timegrow'); ?></a> |</li>
            <?php $i = 0; foreach ($tools as $feature => $tool): ?>
                <li><a href="<?php echo esc_url(self::url(['feature' => $feature])); ?>" class="<?php echo $filter === $feature ? 'current' : ''; ?>"><?php echo esc_html($tool['name']); ?></a><?php echo ++$i < count($tools) ? ' |' : ''; ?></li>
            <?php endforeach; ?>
        </ul>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 26%;"><?php esc_html_e('Name', 'timegrow'); ?></th>
                    <th><?php esc_html_e('Tool', 'timegrow'); ?></th>
                    <th><?php esc_html_e('Status', 'timegrow'); ?></th>
                    <th><?php esc_html_e('Created', 'timegrow'); ?></th>
                    <th><?php esc_html_e('Activated', 'timegrow'); ?></th>
                    <th><?php esc_html_e('Archived', 'timegrow'); ?></th>
                    <th style="width: 22%;"><?php esc_html_e('Actions', 'timegrow'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tools as $feature => $tool): if ($filter !== '' && $filter !== $feature) continue; $active = TimeGrowAIPrompts::active($feature); ?>
                    <tr>
                        <td><strong><?php esc_html_e('Built-in prompt', 'timegrow'); ?></strong><br><span class="description"><?php esc_html_e('Comes with TimeGrow. Used when no version is active.', 'timegrow'); ?></span></td>
                        <td><?php echo esc_html($tool['name']); ?></td>
                        <td><?php echo $active ? '<span class="tg-prompt-status">' . esc_html__('Standby', 'timegrow') . '</span>' : self::status_badge('active'); ?></td>
                        <td>&ndash;</td><td>&ndash;</td><td>&ndash;</td>
                        <td>
                            <a href="<?php echo esc_url(self::url(['prompt' => 'builtin', 'feature' => $feature])); ?>"><?php esc_html_e('View', 'timegrow'); ?></a> |
                            <a href="<?php echo esc_url(self::action_url('duplicate', 0, $feature)); ?>"><?php esc_html_e('Copy to new draft', 'timegrow'); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php foreach ($model->all($filter ?: null) as $row): ?>
                    <tr>
                        <td>
                            <a href="<?php echo esc_url(self::url(['prompt' => $row['ID']])); ?>"><strong><?php echo esc_html($row['name']); ?></strong></a>
                            <?php if ($row['notes'] !== ''): ?><br><span class="description"><?php echo esc_html(wp_trim_words($row['notes'], 16)); ?></span><?php endif; ?>
                        </td>
                        <td><?php echo esc_html($tools[$row['feature']]['name'] ?? $row['feature']); ?></td>
                        <td><?php echo self::status_badge($row['status']); ?></td>
                        <td><?php echo self::when($row['created_at']); ?><?php $by = $row['created_by'] ? get_userdata($row['created_by']) : null; if ($by): ?><br><span class="description"><?php echo esc_html($by->display_name); ?></span><?php endif; ?></td>
                        <td><?php echo self::when($row['activated_at']); ?></td>
                        <td><?php echo self::when($row['archived_at']); ?></td>
                        <td>
                            <a href="<?php echo esc_url(self::url(['prompt' => $row['ID']])); ?>"><?php echo $row['status'] === 'draft' ? esc_html__('Edit', 'timegrow') : esc_html__('View', 'timegrow'); ?></a>
                            | <a href="<?php echo esc_url(self::action_url('duplicate', $row['ID'])); ?>"><?php esc_html_e('Duplicate', 'timegrow'); ?></a>
                            <?php if ($row['status'] !== 'active'): ?>
                                | <a href="<?php echo esc_url(self::action_url('activate', $row['ID'])); ?>" onclick="return confirm('<?php echo esc_js(__('Activate this prompt? The one in use now will be archived.', 'timegrow')); ?>');"><?php esc_html_e('Activate', 'timegrow'); ?></a>
                            <?php else: ?>
                                | <a href="<?php echo esc_url(self::action_url('archive', $row['ID'])); ?>" onclick="return confirm('<?php echo esc_js(__('Stop using this prompt? The built-in prompt will be used until you activate another.', 'timegrow')); ?>');"><?php esc_html_e('Archive', 'timegrow'); ?></a>
                            <?php endif; ?>
                            <?php if ($row['status'] === 'draft'): ?>
                                | <a class="tg-prompt-delete" href="<?php echo esc_url(self::action_url('delete', $row['ID'])); ?>" onclick="return confirm('<?php echo esc_js(__('Delete this draft?', 'timegrow')); ?>');"><?php esc_html_e('Delete', 'timegrow'); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * @param array|null $row     The version (null for new or the built-in prompt).
     * @param string     $feature The tool, for new versions and the built-in prompt.
     */
    private static function render_editor(TimeGrowAIPromptModel $model, $row, $feature) {
        $builtin  = ($_GET['prompt'] ?? '') === 'builtin';
        $feature  = $row ? $row['feature'] : (TimeGrowAIPrompts::is_tool($feature) ? $feature : 'voice');
        $tool     = TimeGrowAIPrompts::tools()[$feature];
        $editable = !$builtin && (!$row || $row['status'] === 'draft');
        $content  = $row ? $row['content'] : TimeGrowAIPrompts::default_instructions($feature);
        $missing  = TimeGrowAIPrompts::missing_keys($feature, $content);
        if (($_GET['prompt'] ?? '') !== 'new' && !$builtin && !$row) {
            echo '<div class="notice notice-error"><p>' . esc_html__('That prompt was not found.', 'timegrow') . '</p></div>';
            return;
        }
        ?>
        <div class="timegrow-card">
            <div class="timegrow-card-header">
                <div class="timegrow-icon timegrow-icon-primary"><span class="dashicons dashicons-<?php echo esc_attr($tool['icon']); ?>"></span></div>
                <div class="timegrow-card-title">
                    <h2><?php echo esc_html($builtin ? sprintf(__('%s: built-in prompt', 'timegrow'), $tool['name']) : ($row ? $row['name'] : sprintf(__('New %s prompt', 'timegrow'), $tool['name']))); ?></h2>
                    <?php if ($row) echo self::status_badge($row['status']); ?>
                </div>
            </div>
            <div class="timegrow-card-body">
                <?php if ($row): ?>
                    <p class="description">
                        <?php printf(esc_html__('Created %1$s · Updated %2$s · Activated %3$s · Archived %4$s', 'timegrow'), self::when($row['created_at']), self::when($row['updated_at']), self::when($row['activated_at']), self::when($row['archived_at'])); ?>
                    </p>
                <?php endif; ?>
                <?php if (!$editable): ?>
                    <p><?php echo $builtin ? esc_html__('This prompt comes with TimeGrow and can\'t be changed. Copy it to a new draft to make your own version.', 'timegrow') : esc_html__('Active and archived versions are kept as they were. Duplicate this one to change it.', 'timegrow'); ?></p>
                <?php endif; ?>

                <details class="tg-prompt-fixed">
                    <summary><?php esc_html_e('Always sent first, and can\'t be changed: the safety rules', 'timegrow'); ?></summary>
                    <pre><?php echo esc_html(TimeGrowAI::SAFETY_RULES); ?></pre>
                </details>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="timegrow_ai_prompt_save">
                    <input type="hidden" name="id" value="<?php echo esc_attr($row ? $row['ID'] : 0); ?>">
                    <input type="hidden" name="feature" value="<?php echo esc_attr($feature); ?>">
                    <?php wp_nonce_field(self::NONCE . '_save'); ?>
                    <table class="form-table" role="presentation">
                        <?php if (!$builtin): ?>
                        <tr>
                            <th scope="row"><label for="tg_prompt_name"><?php esc_html_e('Name', 'timegrow'); ?></label></th>
                            <td><input type="text" id="tg_prompt_name" name="name" class="regular-text" maxlength="190" value="<?php echo esc_attr($row ? $row['name'] : ''); ?>" placeholder="<?php echo esc_attr(sprintf('%s %s', $tool['name'], current_time('Y-m-d'))); ?>" <?php disabled(!$editable); ?>></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="tg_prompt_notes"><?php esc_html_e('Notes', 'timegrow'); ?></label></th>
                            <td><textarea id="tg_prompt_notes" name="notes" rows="2" class="large-text" placeholder="<?php esc_attr_e('What changed in this version, and why', 'timegrow'); ?>" <?php disabled(!$editable); ?>><?php echo esc_textarea($row ? $row['notes'] : ''); ?></textarea></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <th scope="row"><label for="tg_prompt_content"><?php esc_html_e('Prompt', 'timegrow'); ?></label></th>
                            <td>
                                <textarea id="tg_prompt_content" name="content" rows="26" class="large-text code tg-prompt-text" <?php echo $editable ? '' : 'readonly'; ?>><?php echo esc_textarea($content); ?></textarea>
                                <p class="description">
                                    <?php printf(esc_html__('After this prompt, TimeGrow adds %s, each marked as data the AI must not obey.', 'timegrow'), esc_html($tool['adds'])); ?>
                                    <?php printf(esc_html__('The answer must stay a JSON object with these keys: %s.', 'timegrow'), '<code>' . esc_html(implode(', ', TimeGrowAIPrompts::required_keys($feature))) . '</code>'); ?>
                                </p>
                                <?php if ($missing): ?>
                                    <p class="tg-prompt-missing"><span class="dashicons dashicons-warning" aria-hidden="true"></span>
                                        <?php printf(esc_html__('Missing keys: %s. This version can\'t be activated until it asks for them.', 'timegrow'), '<code>' . esc_html(implode(', ', $missing)) . '</code>'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    <p class="submit">
                        <?php if ($editable): ?>
                            <button type="submit" class="button button-primary" name="activate" value="0"><?php esc_html_e('Save draft', 'timegrow'); ?></button>
                            <button type="submit" class="button" name="activate" value="1" onclick="return confirm('<?php echo esc_js(__('Save and activate this prompt? The one in use now will be archived.', 'timegrow')); ?>');"><?php esc_html_e('Save and activate', 'timegrow'); ?></button>
                        <?php endif; ?>
                        <?php if ($row): ?>
                            <a class="button" href="<?php echo esc_url(self::action_url('duplicate', $row['ID'])); ?>"><?php esc_html_e('Duplicate as new draft', 'timegrow'); ?></a>
                            <?php if ($row['status'] === 'archived'): ?>
                                <a class="button" href="<?php echo esc_url(self::action_url('activate', $row['ID'])); ?>" onclick="return confirm('<?php echo esc_js(__('Activate this version again? The one in use now will be archived.', 'timegrow')); ?>');"><?php esc_html_e('Activate again', 'timegrow'); ?></a>
                            <?php elseif ($row['status'] === 'active'): ?>
                                <a class="button" href="<?php echo esc_url(self::action_url('archive', $row['ID'])); ?>" onclick="return confirm('<?php echo esc_js(__('Stop using this prompt? The built-in prompt will be used until you activate another.', 'timegrow')); ?>');"><?php esc_html_e('Archive', 'timegrow'); ?></a>
                            <?php endif; ?>
                        <?php elseif ($builtin): ?>
                            <a class="button button-primary" href="<?php echo esc_url(self::action_url('duplicate', 0, $feature)); ?>"><?php esc_html_e('Copy to new draft', 'timegrow'); ?></a>
                        <?php endif; ?>
                        <a class="button-link" href="<?php echo esc_url(self::url(['feature' => $feature])); ?>"><?php esc_html_e('Back to all versions', 'timegrow'); ?></a>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
}
