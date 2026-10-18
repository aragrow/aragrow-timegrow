<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings > Knowledge Base: the help topics the Voice AI answers "how do I…?" questions from
 * (TimeGrowNexusHelp).
 *
 * Built-in topics come with TimeGrow (modules/nexus/help/*.md). Here they can be edited (the edited
 * copy is used instead, and can be reset to the built-in text) or hidden. New topics can be added
 * and deleted. Everything changed here is saved in the TimeGrowNexusHelp::OPTION option, so plugin
 * updates never overwrite it. "Try a question" shows which topics would answer, without the AI.
 *
 * Export / Import moves these changes between sites (TimeGrowTransfer): the import preview shows each
 * topic as new, changed, hidden or the same; only topics in the file change, and the changes before
 * the import are kept in BACKUP so "Undo last import" can put them back.
 */
class TimeGrowHelpTopicsPage {

    const NONCE     = 'timegrow_help_topic';
    const MAX_TITLE = 100;
    const MAX_BODY  = 5000;
    const TYPE      = 'timegrow-knowledge-base';
    const BACKUP    = 'timegrow_help_topics_backup';

    public static function init() {
        add_action('admin_post_timegrow_help_topic_save', [__CLASS__, 'handle_save']);
        add_action('admin_post_timegrow_help_topic_do', [__CLASS__, 'handle_action']);
        add_action('admin_post_timegrow_help_export', [__CLASS__, 'handle_export']);
        add_action('admin_post_timegrow_help_import', [__CLASS__, 'handle_import']);
        add_action('admin_post_timegrow_help_import_apply', [__CLASS__, 'handle_import_apply']);
        add_action('admin_post_timegrow_help_import_undo', [__CLASS__, 'handle_import_undo']);
    }

    private static function require_owner() {
        if (!current_user_can(TIMEGROW_OWNER_CAP)) {
            wp_die(esc_html__('You are not allowed to change the knowledge base.', 'timegrow'), 403);
        }
    }

    /** Download this site's knowledge base changes (edited, hidden and added topics). */
    public static function handle_export() {
        self::require_owner();
        check_admin_referer(self::NONCE . '_export');
        if (!self::available()) {
            self::back(['msg' => 'not_found']);
        }
        TimeGrowTransfer::download('timegrow-knowledge-base', self::TYPE, ['topics' => TimeGrowNexusHelp::overrides()]);
    }

    /** Check the uploaded file and show what it would change. */
    public static function handle_import() {
        self::require_owner();
        check_admin_referer(self::NONCE . '_import');
        $data = TimeGrowTransfer::read(TimeGrowTransfer::uploaded('file'), self::TYPE);
        if (!is_array($data)) {
            self::back(['msg' => 'import_' . $data]);
        }
        TimeGrowTransfer::stash('help', ['site' => (string) ($data['site'] ?? ''), 'exported_at' => (string) ($data['exported_at'] ?? ''), 'topics' => is_array($data['topics'] ?? null) ? $data['topics'] : []]);
        self::back(['import' => 'preview']);
    }

    /** Apply the previewed import: back up this site's changes, then add or replace the topics in the file. */
    public static function handle_import_apply() {
        self::require_owner();
        check_admin_referer(self::NONCE . '_import_apply');
        $file = TimeGrowTransfer::stashed('help');
        if (!$file || !self::available()) {
            self::back(['msg' => 'import_expired']);
        }
        $current = TimeGrowNexusHelp::overrides();
        $plan    = TimeGrowTransfer::plan_topics($current, array_column(TimeGrowNexusHelp::builtin(), 'slug'), $file['topics'], [__CLASS__, 'validate']);
        update_option(self::BACKUP, ['at' => current_time('mysql'), 'by' => get_current_user_id(), 'from' => $file['site'], 'topics' => $current], false);
        $now = current_time('mysql');
        foreach ($plan['apply'] as $slug => $fields) {
            $current[$slug] = array_merge($current[$slug] ?? [], $fields, ['updated_at' => $now, 'updated_by' => get_current_user_id()]);
        }
        update_option(TimeGrowNexusHelp::OPTION, $current, false);
        TimeGrowTransfer::forget('help');
        timegrow_log(sprintf('Knowledge base import from %s by user %d: %d topics changed', $file['site'], get_current_user_id(), count($plan['apply'])));
        self::back(['msg' => 'imported', 'n' => count($plan['apply'])]);
    }

    /** Put back the knowledge base changes as they were before the last import. */
    public static function handle_import_undo() {
        self::require_owner();
        check_admin_referer(self::NONCE . '_import_undo');
        $backup = get_option(self::BACKUP);
        if (!is_array($backup) || !is_array($backup['topics'] ?? null)) {
            self::back(['msg' => 'not_found']);
        }
        update_option(TimeGrowNexusHelp::OPTION, $backup['topics'], false);
        delete_option(self::BACKUP);
        timegrow_log(sprintf('Knowledge base import undone by user %d', get_current_user_id()));
        self::back(['msg' => 'undone']);
    }

    public static function url(array $args = []) {
        return add_query_arg(array_merge(['page' => TIMEGROW_PARENT_MENU . '-settings', 'tab' => 'help'], $args), admin_url('admin.php'));
    }

    private static function action_url($do, $slug) {
        return wp_nonce_url(add_query_arg(['action' => 'timegrow_help_topic_do', 'do' => $do, 'slug' => $slug], admin_url('admin-post.php')), self::NONCE . '_' . $do . '_' . $slug);
    }

    private static function available() {
        return class_exists('TimeGrowNexusHelp');
    }

    private static function back(array $args) {
        wp_safe_redirect(self::url($args));
        exit;
    }

    private static function find($slug) {
        foreach (TimeGrowNexusHelp::all_topics() as $t) {
            if ($t['slug'] === $slug) {
                return $t;
            }
        }
        return null;
    }

    private static function is_builtin($slug) {
        foreach (TimeGrowNexusHelp::builtin() as $t) {
            if ($t['slug'] === $slug) {
                return true;
            }
        }
        return false;
    }

    /**
     * A topic from the form, checked: title and text required and limited, page only the characters
     * of an admin page address. Pure.
     *
     * @return array|string The fields, or an error code.
     */
    public static function validate(array $in) {
        $title = trim(sanitize_text_field((string) ($in['title'] ?? '')));
        $body  = trim(str_replace("\r\n", "\n", sanitize_textarea_field((string) ($in['body'] ?? ''))));
        if ($title === '' || $body === '') {
            return 'missing';
        }
        if (mb_strlen($title) > self::MAX_TITLE || mb_strlen($body) > self::MAX_BODY) {
            return 'too_long';
        }
        return [
            'title'    => $title,
            'roles'    => ($in['roles'] ?? '') === 'admin' ? 'admin' : 'everyone',
            'page'     => preg_replace('/[^a-z0-9_&=\-]/', '', strtolower((string) ($in['page'] ?? ''))),
            'keywords' => mb_substr(implode(', ', array_filter(array_map('trim', explode(',', sanitize_text_field((string) ($in['keywords'] ?? '')))))), 0, 300),
            'body'     => $body,
        ];
    }

    // ------------------------------------------------------------------
    // Actions
    // ------------------------------------------------------------------

    /** Save an edited built-in topic, or a new or changed custom one. */
    public static function handle_save() {
        if (!current_user_can(TIMEGROW_OWNER_CAP)) {
            wp_die(esc_html__('You are not allowed to change the knowledge base.', 'timegrow'), 403);
        }
        check_admin_referer(self::NONCE . '_save');
        if (!self::available()) {
            self::back(['msg' => 'not_found']);
        }
        $slug   = sanitize_key(wp_unslash($_POST['slug'] ?? ''));
        $fields = self::validate(wp_unslash($_POST));
        if (!is_array($fields)) {
            self::back(['topic' => $slug ?: 'new', 'msg' => $fields]);
        }
        $saved = TimeGrowNexusHelp::overrides();
        if ($slug === '' || $slug === 'new') {
            $slug = 'custom-' . substr(sanitize_title($fields['title']), 0, 50) . '-' . substr(md5(uniqid('', true)), 0, 6);
        } elseif (!self::is_builtin($slug) && !isset($saved[$slug])) {
            self::back(['msg' => 'not_found']);
        }
        $saved[$slug] = $fields + ['hidden' => !empty($saved[$slug]['hidden']), 'updated_at' => current_time('mysql'), 'updated_by' => get_current_user_id()];
        update_option(TimeGrowNexusHelp::OPTION, $saved, false);
        timegrow_log(sprintf('Knowledge base topic %s saved by user %d', $slug, get_current_user_id()));
        self::back(['msg' => 'saved']);
    }

    /** Reset a built-in topic, hide or show a topic, or delete a custom one. */
    public static function handle_action() {
        if (!current_user_can(TIMEGROW_OWNER_CAP)) {
            wp_die(esc_html__('You are not allowed to change the knowledge base.', 'timegrow'), 403);
        }
        $do   = sanitize_key($_GET['do'] ?? '');
        $slug = sanitize_key($_GET['slug'] ?? '');
        check_admin_referer(self::NONCE . '_' . $do . '_' . $slug);
        if (!self::available() || !self::find($slug)) {
            self::back(['msg' => 'not_found']);
        }
        $saved   = TimeGrowNexusHelp::overrides();
        $builtin = self::is_builtin($slug);
        switch ($do) {
            case 'reset': // back to the built-in text (keeps it hidden if it was)
                if (!$builtin) {
                    self::back(['msg' => 'not_found']);
                }
                if (!empty($saved[$slug]['hidden'])) {
                    $saved[$slug] = ['hidden' => true];
                } else {
                    unset($saved[$slug]);
                }
                $msg = 'reset';
                break;
            case 'hide':
            case 'show':
                $saved[$slug] = array_merge($saved[$slug] ?? [], ['hidden' => $do === 'hide']);
                if ($builtin && $do === 'show' && !isset($saved[$slug]['body'])) {
                    unset($saved[$slug]); // nothing else changed: back to plain built-in
                }
                $msg = $do === 'hide' ? 'hidden' : 'shown';
                break;
            case 'delete':
                if ($builtin) {
                    self::back(['msg' => 'not_found']); // built-in topics are hidden, not deleted
                }
                unset($saved[$slug]);
                $msg = 'deleted';
                break;
            default:
                self::back(['msg' => 'not_found']);
        }
        update_option(TimeGrowNexusHelp::OPTION, $saved, false);
        timegrow_log(sprintf('Knowledge base topic %s: %s by user %d', $slug, $do, get_current_user_id()));
        self::back(['msg' => $msg]);
    }

    // ------------------------------------------------------------------
    // Page
    // ------------------------------------------------------------------

    public static function render() {
        ?>
        <div class="wrap timegrow-page">
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('Knowledge Base', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('What the voice assistant knows about using TimeGrow. It answers "how do I…?" questions from these topics only.', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration"><span class="dashicons dashicons-book-alt"></span></div>
            </div>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings')); ?>">&larr; <?php esc_html_e('Back to Settings', 'timegrow'); ?></a></p>
            <?php
            if (!self::available()) {
                echo '<div class="notice notice-warning inline"><p>' . esc_html__('The knowledge base is part of the Nexus module, which is not loaded.', 'timegrow') . '</p></div></div>';
                return;
            }
            self::notice();
            $topic = sanitize_key($_GET['topic'] ?? '');
            if (($_GET['import'] ?? '') === 'preview') {
                self::render_import_preview();
            } elseif ($topic !== '') {
                self::render_editor($topic === 'new' ? null : self::find($topic));
            } else {
                self::render_try();
                self::render_list();
            }
            ?>
        </div>
        <?php
    }

    private static function notice() {
        $messages = [
            'saved'     => ['success', __('Topic saved. The assistant uses it from now on.', 'timegrow')],
            'reset'     => ['success', __('Topic reset to the built-in text.', 'timegrow')],
            'hidden'    => ['success', __('Topic hidden. The assistant no longer uses it.', 'timegrow')],
            'shown'     => ['success', __('Topic shown again.', 'timegrow')],
            'deleted'   => ['success', __('Topic deleted.', 'timegrow')],
            'missing'   => ['error', __('A topic needs a title and text.', 'timegrow')],
            'too_long'  => ['error', sprintf(__('The title can be up to %1$d characters and the text up to %2$s.', 'timegrow'), self::MAX_TITLE, number_format_i18n(self::MAX_BODY))],
            'not_found' => ['error', __('That topic was not found.', 'timegrow')],
            'imported'  => ['success', sprintf(__('Imported: %d topics changed. Your earlier version is kept: use Undo last import to go back.', 'timegrow'), (int) ($_GET['n'] ?? 0))],
            'undone'    => ['success', __('The last import was undone.', 'timegrow')],
        ];
        foreach (['no_file', 'too_big', 'not_json', 'wrong_type', 'new_version', 'expired'] as $code) {
            $messages['import_' . $code] = ['error', TimeGrowTransfer::error_text($code)];
        }
        $msg = sanitize_key($_GET['msg'] ?? '');
        if (isset($messages[$msg])) {
            printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($messages[$msg][0]), esc_html($messages[$msg][1]));
        }
    }

    /** "Try a question": the topics that would answer it, best first, as an administrator or a team member sees them. */
    private static function render_try() {
        $q     = sanitize_text_field(wp_unslash($_GET['try'] ?? ''));
        $as    = ($_GET['as'] ?? '') === 'member' ? 'member' : 'admin';
        ?>
        <div class="tg-kb-try">
            <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                <input type="hidden" name="page" value="<?php echo esc_attr(TIMEGROW_PARENT_MENU . '-settings'); ?>">
                <input type="hidden" name="tab" value="help">
                <label for="tg-kb-try-q"><strong><?php esc_html_e('Try a question', 'timegrow'); ?></strong></label>
                <input type="search" id="tg-kb-try-q" name="try" value="<?php echo esc_attr($q); ?>" placeholder="<?php esc_attr_e('How do I upload a receipt?', 'timegrow'); ?>">
                <select name="as" aria-label="<?php esc_attr_e('Asked by', 'timegrow'); ?>">
                    <option value="admin" <?php selected($as, 'admin'); ?>><?php esc_html_e('as an administrator', 'timegrow'); ?></option>
                    <option value="member" <?php selected($as, 'member'); ?>><?php esc_html_e('as a team member', 'timegrow'); ?></option>
                </select>
                <button type="submit" class="button"><?php esc_html_e('Find topics', 'timegrow'); ?></button>
            </form>
            <?php if ($q !== '') :
                $found = TimeGrowNexusHelp::search($q, TimeGrowNexusHelp::topics(), $as === 'admin');
                $words = TimeGrowNexusHelp::words($q);
                ?>
                <?php if (!$found) : ?>
                    <p><?php esc_html_e('No topic matches. The assistant would say it couldn\'t find that in the help. Add a topic, or add keywords to an existing one.', 'timegrow'); ?></p>
                <?php else : ?>
                    <p><?php esc_html_e('The assistant would answer from these topics (best first):', 'timegrow'); ?></p>
                    <ol>
                        <?php foreach ($found as $t) : ?>
                            <li><a href="<?php echo esc_url(self::url(['topic' => $t['slug']])); ?>"><?php echo esc_html($t['title']); ?></a>
                                <span class="tg-kb-muted">(<?php echo esc_html(sprintf(__('score %d', 'timegrow'), TimeGrowNexusHelp::score($t, $words))); ?>)</span></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_list() {
        $topics = TimeGrowNexusHelp::all_topics();
        usort($topics, function ($a, $b) { return strnatcasecmp($a['title'], $b['title']); });
        $sources = ['built-in' => __('Built-in', 'timegrow'), 'edited' => __('Edited', 'timegrow'), 'custom' => __('Added here', 'timegrow')];
        ?>
        <p><a class="button button-primary" href="<?php echo esc_url(self::url(['topic' => 'new'])); ?>"><?php esc_html_e('Add a topic', 'timegrow'); ?></a></p>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 34%;"><?php esc_html_e('Topic', 'timegrow'); ?></th>
                    <th><?php esc_html_e('For', 'timegrow'); ?></th>
                    <th><?php esc_html_e('Source', 'timegrow'); ?></th>
                    <th style="width: 30%;"><?php esc_html_e('Keywords', 'timegrow'); ?></th>
                    <th><?php esc_html_e('Status', 'timegrow'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topics as $t) : ?>
                    <tr<?php echo !empty($t['hidden']) ? ' style="opacity:.6"' : ''; ?>>
                        <td>
                            <strong><a href="<?php echo esc_url(self::url(['topic' => $t['slug']])); ?>"><?php echo esc_html($t['title']); ?></a></strong>
                            <div class="row-actions visible">
                                <a href="<?php echo esc_url(self::url(['topic' => $t['slug']])); ?>"><?php esc_html_e('Edit', 'timegrow'); ?></a>
                                <?php if ($t['source'] === 'edited') : ?>
                                    | <a href="<?php echo esc_url(self::action_url('reset', $t['slug'])); ?>" onclick="return confirm('<?php echo esc_js(__('Go back to the built-in text? Your changes to this topic are removed.', 'timegrow')); ?>');"><?php esc_html_e('Reset to built-in', 'timegrow'); ?></a>
                                <?php endif; ?>
                                | <a href="<?php echo esc_url(self::action_url(empty($t['hidden']) ? 'hide' : 'show', $t['slug'])); ?>"><?php echo empty($t['hidden']) ? esc_html__('Hide', 'timegrow') : esc_html__('Show', 'timegrow'); ?></a>
                                <?php if ($t['source'] === 'custom') : ?>
                                    | <a class="submitdelete" href="<?php echo esc_url(self::action_url('delete', $t['slug'])); ?>" onclick="return confirm('<?php echo esc_js(__('Delete this topic?', 'timegrow')); ?>');"><?php esc_html_e('Delete', 'timegrow'); ?></a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo $t['roles'] === 'admin' ? esc_html__('Administrators', 'timegrow') : esc_html__('Everyone', 'timegrow'); ?></td>
                        <td><?php echo esc_html($sources[$t['source']] ?? $t['source']); ?></td>
                        <td class="tg-kb-muted"><?php echo esc_html(implode(', ', $t['keywords'])); ?></td>
                        <td>
                            <?php if (!empty($t['hidden'])) : ?>
                                <span class="timegrow-badge timegrow-badge-inactive"><?php esc_html_e('Hidden', 'timegrow'); ?></span>
                            <?php else : ?>
                                <span class="timegrow-badge timegrow-badge-active"><?php esc_html_e('In use', 'timegrow'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="tg-kb-muted"><?php echo esc_html(sprintf(__('%d topics. Built-in topics come with TimeGrow; edits and topics added here are kept when TimeGrow is updated.', 'timegrow'), count($topics))); ?></p>
        <?php self::render_transfer(); ?>
        <?php self::styles();
    }

    /** Export, import and undo: moving the knowledge base between sites. */
    private static function render_transfer() {
        $backup = get_option(self::BACKUP);
        ?>
        <div class="tg-kb-transfer">
            <h2><?php esc_html_e('Move to another site', 'timegrow'); ?></h2>
            <p class="tg-kb-muted"><?php esc_html_e('Export downloads the topics edited, hidden or added on this site. Import them on another site (for example, from your local site to production); you see what changes before anything is saved.', 'timegrow'); ?></p>
            <div class="tg-kb-transfer-row">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field(self::NONCE . '_export'); ?>
                    <input type="hidden" name="action" value="timegrow_help_export">
                    <button type="submit" class="button"><span class="dashicons dashicons-download" aria-hidden="true"></span> <?php esc_html_e('Export', 'timegrow'); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                    <?php wp_nonce_field(self::NONCE . '_import'); ?>
                    <input type="hidden" name="action" value="timegrow_help_import">
                    <label for="tg-kb-file" class="screen-reader-text"><?php esc_html_e('Knowledge base file', 'timegrow'); ?></label>
                    <input type="file" id="tg-kb-file" name="file" accept=".json,application/json" required>
                    <button type="submit" class="button"><span class="dashicons dashicons-upload" aria-hidden="true"></span> <?php esc_html_e('Import…', 'timegrow'); ?></button>
                </form>
                <?php if (is_array($backup) && isset($backup['topics'])) : ?>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Put the knowledge base back as it was before the last import?', 'timegrow')); ?>');">
                        <?php wp_nonce_field(self::NONCE . '_import_undo'); ?>
                        <input type="hidden" name="action" value="timegrow_help_import_undo">
                        <button type="submit" class="button-link"><?php echo esc_html(sprintf(__('Undo last import (%s)', 'timegrow'), mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $backup['at']))); ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /** The import preview: what each topic in the file would do here, then Import or Cancel. */
    private static function render_import_preview() {
        $file = TimeGrowTransfer::stashed('help');
        if (!$file) {
            echo '<div class="notice notice-error inline"><p>' . esc_html(TimeGrowTransfer::error_text('expired')) . '</p></div>';
            return;
        }
        $plan   = TimeGrowTransfer::plan_topics(TimeGrowNexusHelp::overrides(), array_column(TimeGrowNexusHelp::builtin(), 'slug'), $file['topics'], [__CLASS__, 'validate']);
        $labels = ['new' => __('New', 'timegrow'), 'changed' => __('Changed', 'timegrow'), 'hidden' => __('Hidden / shown', 'timegrow'), 'same' => __('Same, skipped', 'timegrow'), 'invalid' => __('Skipped', 'timegrow')];
        ?>
        <h2><?php esc_html_e('Import knowledge base: review', 'timegrow'); ?></h2>
        <p><?php echo esc_html(sprintf(__('From %1$s, exported %2$s. Only the topics below change; every other topic on this site stays as it is.', 'timegrow'), $file['site'] ?: __('another site', 'timegrow'), $file['exported_at'] ? mysql2date(get_option('date_format'), $file['exported_at']) : '?')); ?></p>
        <?php if (!$plan['rows']) : ?>
            <p><?php esc_html_e('The file has no topic changes.', 'timegrow'); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr><th><?php esc_html_e('Topic', 'timegrow'); ?></th><th><?php esc_html_e('What happens', 'timegrow'); ?></th></tr></thead>
                <tbody>
                    <?php foreach ($plan['rows'] as [$slug, $title, $change, $why]) : ?>
                        <tr><td><?php echo esc_html($title); ?></td>
                            <td><?php echo esc_html($labels[$change] . ($why !== '' ? ': ' . $why : '')); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top: 16px;">
            <?php wp_nonce_field(self::NONCE . '_import_apply'); ?>
            <input type="hidden" name="action" value="timegrow_help_import_apply">
            <?php if ($plan['apply']) : ?>
                <button type="submit" class="button button-primary"><?php echo esc_html(sprintf(_n('Import %d topic', 'Import %d topics', count($plan['apply']), 'timegrow'), count($plan['apply']))); ?></button>
            <?php endif; ?>
            <a class="button" href="<?php echo esc_url(self::url()); ?>"><?php esc_html_e('Cancel', 'timegrow'); ?></a>
        </form>
        <p class="tg-kb-muted"><?php esc_html_e('Before importing, the knowledge base as it is now is saved, so you can undo the import.', 'timegrow'); ?></p>
        <?php
    }

    private static function render_editor($t) {
        $new = $t === null;
        if (!$new && !$t) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__('That topic was not found.', 'timegrow') . '</p></div>';
            return;
        }
        $t = $t ?: ['slug' => 'new', 'title' => '', 'roles' => 'everyone', 'page' => '', 'keywords' => [], 'body' => '', 'source' => 'custom'];
        ?>
        <h2><?php echo $new ? esc_html__('Add a topic', 'timegrow') : esc_html(sprintf(__('Edit: %s', 'timegrow'), $t['title'])); ?></h2>
        <?php if ($t['source'] === 'built-in') : ?>
            <p class="tg-kb-muted"><?php esc_html_e('This is a built-in topic. Saving keeps your version instead; you can reset it to the built-in text later.', 'timegrow'); ?></p>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field(self::NONCE . '_save'); ?>
            <input type="hidden" name="action" value="timegrow_help_topic_save">
            <input type="hidden" name="slug" value="<?php echo esc_attr($t['slug']); ?>">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="tg-kb-title"><?php esc_html_e('Title', 'timegrow'); ?></label></th>
                    <td><input type="text" id="tg-kb-title" name="title" class="regular-text" maxlength="<?php echo (int) self::MAX_TITLE; ?>" required value="<?php echo esc_attr($t['title']); ?>">
                        <p class="description"><?php esc_html_e('What the topic is about, like "Upload a receipt". Shown in the assistant\'s links.', 'timegrow'); ?></p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tg-kb-roles"><?php esc_html_e('For', 'timegrow'); ?></label></th>
                    <td><select id="tg-kb-roles" name="roles">
                            <option value="everyone" <?php selected($t['roles'], 'everyone'); ?>><?php esc_html_e('Everyone', 'timegrow'); ?></option>
                            <option value="admin" <?php selected($t['roles'], 'admin'); ?>><?php esc_html_e('Administrators only', 'timegrow'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Team members never get administrator topics; when they ask about one, they are told to ask an administrator.', 'timegrow'); ?></p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tg-kb-keywords"><?php esc_html_e('Keywords', 'timegrow'); ?></label></th>
                    <td><input type="text" id="tg-kb-keywords" name="keywords" class="large-text" value="<?php echo esc_attr(implode(', ', $t['keywords'])); ?>">
                        <p class="description"><?php esc_html_e('Words people use when they ask about it, separated by commas: "receipt, photo, scan". Keywords count most when finding a topic.', 'timegrow'); ?></p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tg-kb-page"><?php esc_html_e('Page', 'timegrow'); ?></label></th>
                    <td><input type="text" id="tg-kb-page" name="page" class="regular-text" value="<?php echo esc_attr($t['page']); ?>" placeholder="timegrow-nexus-expenses">
                        <p class="description"><?php esc_html_e('Optional: the admin page it is about (the part after "page=" in the address, like timegrow-settings&tab=ai). The assistant links to it.', 'timegrow'); ?></p></td>
                </tr>
                <tr>
                    <th scope="row"><label for="tg-kb-body"><?php esc_html_e('Text', 'timegrow'); ?></label></th>
                    <td><textarea id="tg-kb-body" name="body" rows="12" class="large-text" maxlength="<?php echo (int) self::MAX_BODY; ?>" required><?php echo esc_textarea($t['body']); ?></textarea>
                        <p class="description"><?php esc_html_e('Plain sentences, a few short paragraphs. The assistant answers only from this text, and without AI it shows the first paragraph, so start with the most useful part.', 'timegrow'); ?></p></td>
                </tr>
            </table>
            <p>
                <?php submit_button(__('Save topic', 'timegrow'), 'primary', 'submit', false); ?>
                <a class="button" href="<?php echo esc_url(self::url()); ?>"><?php esc_html_e('Cancel', 'timegrow'); ?></a>
            </p>
        </form>
        <?php
    }

    private static function styles() {
        ?>
        <style>
            .tg-kb-try { margin: 0 0 16px; padding: 12px 14px; background: #f9f9f9; border: 1px solid #dcdcde; border-radius: 4px; }
            .tg-kb-try form { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
            .tg-kb-try input[type="search"] { width: 320px; max-width: 100%; }
            .tg-kb-try ol { margin: 6px 0 0 20px; }
            .tg-kb-muted { color: #646970; }
            .tg-kb-transfer { margin-top: 24px; padding: 12px 14px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px; }
            .tg-kb-transfer h2 { margin: 0 0 4px; font-size: 15px; }
            .tg-kb-transfer-row { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
            .tg-kb-transfer-row form { display: flex; align-items: center; gap: 6px; margin: 0; }
            .tg-kb-transfer .button .dashicons { font-size: 16px; width: 16px; height: 16px; vertical-align: text-bottom; }
        </style>
        <?php
    }
}
