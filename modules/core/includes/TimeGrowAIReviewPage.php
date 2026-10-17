<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings > AI Conversations: saved Voice AI conversations and Receipt Reader readings, to
 * review how the AI behaves. Owners read each one as a chat with "how the AI read this" under each
 * message, rate sessions (1 to 5 stars and a note) and each AI reading (thumbs up or down), and
 * compare prompt versions by rating, readings marked wrong and tokens.
 */
class TimeGrowAIReviewPage {

    const NONCE    = 'timegrow_ai_review';
    const PER_PAGE = 25;

    public static function init() {
        add_action('admin_init', function () {
            if (current_user_can(TIMEGROW_OWNER_CAP)) {
                (new TimeGrowAIConversationModel())->maybe_create_tables();
            }
        });
        add_action('admin_post_timegrow_ai_review_rate', [__CLASS__, 'handle_rate']);
        add_action('admin_post_timegrow_ai_review_thumbs', [__CLASS__, 'handle_thumbs']);
        add_action('admin_post_timegrow_ai_review_delete', [__CLASS__, 'handle_delete']);
    }

    public static function url(array $args = []) {
        return add_query_arg(array_merge(['page' => TIMEGROW_PARENT_MENU . '-settings', 'tab' => 'conversations'], $args), admin_url('admin.php'));
    }

    private static function tools() {
        return ['voice' => __('Voice AI', 'timegrow'), 'receipts' => __('Receipt Reader', 'timegrow')];
    }

    // ------------------------------------------------------------------
    // Actions (owners only)
    // ------------------------------------------------------------------

    private static function guard($nonce) {
        if (!current_user_can(TIMEGROW_OWNER_CAP)) {
            wp_die(esc_html__('You are not allowed to review AI conversations.', 'timegrow'), 403);
        }
        check_admin_referer($nonce);
    }

    public static function handle_rate() {
        $id = (int) ($_POST['session'] ?? 0);
        self::guard(self::NONCE . '_rate_' . $id);
        $rating = (int) ($_POST['rating'] ?? 0);
        (new TimeGrowAIConversationModel())->rate($id, ($rating >= 1 && $rating <= 5) ? $rating : null, sanitize_textarea_field(wp_unslash($_POST['note'] ?? '')), get_current_user_id());
        wp_safe_redirect(self::url(['session' => $id, 'msg' => 'rated']));
        exit;
    }

    public static function handle_thumbs() {
        $turn  = (int) ($_GET['turn'] ?? 0);
        $value = (int) ($_GET['value'] ?? 0);
        self::guard(self::NONCE . '_thumbs_' . $turn);
        $model = new TimeGrowAIConversationModel();
        $row   = $model->turn($turn);
        if ($row) {
            $model->thumbs($turn, in_array($value, [-1, 1], true) && (int) $row['thumbs'] !== $value ? $value : 0); // the same choice again clears it
        }
        wp_safe_redirect(self::url(['session' => $row ? (int) $row['session_id'] : 0]) . '#turn-' . $turn);
        exit;
    }

    public static function handle_delete() {
        $id = (int) ($_GET['session'] ?? 0);
        self::guard(self::NONCE . '_delete_' . $id);
        $session = (new TimeGrowAIConversationModel())->get($id);
        (new TimeGrowAIConversationModel())->delete_session($id);
        wp_safe_redirect(self::url(['feature' => $session['feature'] ?? '', 'msg' => 'deleted']));
        exit;
    }

    // ------------------------------------------------------------------
    // Screens
    // ------------------------------------------------------------------

    public static function render() {
        $model = new TimeGrowAIConversationModel();
        $model->maybe_create_tables();
        ?>
        <div class="wrap timegrow-page">
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('AI Conversations', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('Review how the Voice AI and Receipt Reader behave, and rate them', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration"><span class="dashicons dashicons-format-chat"></span></div>
            </div>
            <p><a href="<?php echo esc_url(admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-settings')); ?>">&larr; <?php esc_html_e('Back to Settings', 'timegrow'); ?></a></p>
            <?php
            $msg = sanitize_key($_GET['msg'] ?? '');
            if ($msg === 'rated') echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Rating saved.', 'timegrow') . '</p></div>';
            if ($msg === 'deleted') echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Conversation deleted.', 'timegrow') . '</p></div>';
            $session = (int) ($_GET['session'] ?? 0);
            $session ? self::render_session($model, $session) : self::render_list($model);
            ?>
        </div>
        <?php
    }

    private static function stars($rating) {
        if (!$rating) {
            return '<span class="description">' . esc_html__('Not rated', 'timegrow') . '</span>';
        }
        return '<span class="tg-review-stars" aria-label="' . esc_attr(sprintf(__('%d of 5', 'timegrow'), $rating)) . '">' . str_repeat('&#9733;', (int) $rating) . '<span>' . str_repeat('&#9733;', 5 - (int) $rating) . '</span></span>';
    }

    private static function person($user_id) {
        $u = get_userdata((int) $user_id);
        return $u ? $u->display_name : sprintf(__('User %d', 'timegrow'), $user_id);
    }

    private static function render_list(TimeGrowAIConversationModel $model) {
        $tools  = self::tools();
        $filter = [
            'feature'      => array_key_exists($_GET['feature'] ?? '', $tools) ? $_GET['feature'] : '',
            'rated'        => in_array($_GET['rated'] ?? '', ['yes', 'no'], true) ? $_GET['rated'] : '',
            'flagged'      => !empty($_GET['flagged']),
            'user_id'      => (int) ($_GET['person'] ?? 0),
            'prompt_label' => sanitize_text_field(wp_unslash($_GET['prompt'] ?? '')),
        ];
        $page    = max(1, (int) ($_GET['paged'] ?? 1));
        $total   = $model->count_sessions($filter);
        $rows    = $model->sessions($filter, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        $options = $model->filter_options();
        $stats   = $model->stats_by_prompt();
        ?>
        <?php if ($stats): ?>
        <div class="timegrow-card">
            <div class="timegrow-card-header">
                <div class="timegrow-icon timegrow-icon-primary"><span class="dashicons dashicons-chart-bar"></span></div>
                <div class="timegrow-card-title"><h2><?php esc_html_e('By prompt version', 'timegrow'); ?></h2></div>
            </div>
            <div class="timegrow-card-body">
                <table class="wp-list-table widefat fixed striped">
                    <thead><tr>
                        <th><?php esc_html_e('Tool', 'timegrow'); ?></th>
                        <th style="width: 24%;"><?php esc_html_e('Prompt', 'timegrow'); ?></th>
                        <th><?php esc_html_e('Sessions', 'timegrow'); ?></th>
                        <th><?php esc_html_e('Average rating', 'timegrow'); ?></th>
                        <th><?php esc_html_e('AI readings', 'timegrow'); ?></th>
                        <th><?php esc_html_e('Marked right / wrong', 'timegrow'); ?></th>
                        <th><?php esc_html_e('Tokens per reading', 'timegrow'); ?></th>
                        <th><?php esc_html_e('Last used', 'timegrow'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($stats as $st): ?>
                        <tr>
                            <td><?php echo esc_html($tools[$st['feature']] ?? $st['feature']); ?></td>
                            <td><a href="<?php echo esc_url(self::url(['feature' => $st['feature'], 'prompt' => $st['prompt_label']])); ?>"><?php echo esc_html($st['prompt_label'] ?: __('(unknown)', 'timegrow')); ?></a></td>
                            <td><?php echo (int) $st['sessions']; ?></td>
                            <td><?php echo $st['rated'] ? esc_html(number_format_i18n((float) $st['avg_rating'], 1)) . ' &#9733; <span class="description">(' . esc_html(sprintf(_n('%d rated', '%d rated', (int) $st['rated'], 'timegrow'), (int) $st['rated'])) . ')</span>' : '&ndash;'; ?></td>
                            <td><?php echo (int) $st['ai_calls']; ?></td>
                            <td>&#128077; <?php echo (int) $st['thumbs_up']; ?> &nbsp; &#128078; <?php echo (int) $st['thumbs_down']; ?></td>
                            <td><?php echo $st['ai_calls'] ? esc_html(number_format_i18n((int) $st['tokens'] / (int) $st['ai_calls'])) : '&ndash;'; ?></td>
                            <td><?php echo esc_html(mysql2date(get_option('date_format'), $st['last_used'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <form method="get" class="tg-review-filters">
            <input type="hidden" name="page" value="<?php echo esc_attr(TIMEGROW_PARENT_MENU . '-settings'); ?>">
            <input type="hidden" name="tab" value="conversations">
            <select name="feature">
                <option value=""><?php esc_html_e('All tools', 'timegrow'); ?></option>
                <?php foreach ($tools as $k => $label): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($filter['feature'], $k); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
            </select>
            <select name="rated">
                <option value=""><?php esc_html_e('Rated or not', 'timegrow'); ?></option>
                <option value="no" <?php selected($filter['rated'], 'no'); ?>><?php esc_html_e('Not rated yet', 'timegrow'); ?></option>
                <option value="yes" <?php selected($filter['rated'], 'yes'); ?>><?php esc_html_e('Rated', 'timegrow'); ?></option>
            </select>
            <select name="person">
                <option value="0"><?php esc_html_e('Everyone', 'timegrow'); ?></option>
                <?php foreach ($options['users'] as $uid): ?><option value="<?php echo esc_attr($uid); ?>" <?php selected($filter['user_id'], $uid); ?>><?php echo esc_html(self::person($uid)); ?></option><?php endforeach; ?>
            </select>
            <select name="prompt">
                <option value=""><?php esc_html_e('All prompts', 'timegrow'); ?></option>
                <?php foreach ($options['prompts'] as $label): ?><option value="<?php echo esc_attr($label); ?>" <?php selected($filter['prompt_label'], $label); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?>
            </select>
            <label><input type="checkbox" name="flagged" value="1" <?php checked($filter['flagged']); ?>> <?php esc_html_e('With readings marked wrong', 'timegrow'); ?></label>
            <button type="submit" class="button"><?php esc_html_e('Filter', 'timegrow'); ?></button>
        </form>

        <table class="wp-list-table widefat fixed striped">
            <thead><tr>
                <th style="width: 14%;"><?php esc_html_e('Started', 'timegrow'); ?></th>
                <th><?php esc_html_e('Tool', 'timegrow'); ?></th>
                <th><?php esc_html_e('Person', 'timegrow'); ?></th>
                <th style="width: 24%;"><?php esc_html_e('Result', 'timegrow'); ?></th>
                <th><?php esc_html_e('Messages', 'timegrow'); ?></th>
                <th><?php esc_html_e('Tokens', 'timegrow'); ?></th>
                <th><?php esc_html_e('Prompt', 'timegrow'); ?></th>
                <th><?php esc_html_e('Rating', 'timegrow'); ?></th>
            </tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8"><?php esc_html_e('Nothing saved yet. Conversations and receipt readings show up here once "Save for review" is on and the AI tools are used.', 'timegrow'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?php echo esc_url(self::url(['session' => $r['ID']])); ?>"><strong><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $r['started_at'])); ?></strong></a></td>
                    <td><?php echo esc_html($tools[$r['feature']] ?? $r['feature']); ?></td>
                    <td><?php echo esc_html(self::person($r['user_id'])); ?></td>
                    <td><?php echo $r['outcome'] !== '' ? esc_html($r['outcome']) : '<span class="description">' . esc_html__('Nothing saved', 'timegrow') . '</span>'; ?></td>
                    <td><?php echo (int) $r['turns']; ?><?php if ((int) $r['thumbs_down']): ?> <span class="tg-review-flag">&#128078; <?php echo (int) $r['thumbs_down']; ?></span><?php endif; ?></td>
                    <td><?php echo esc_html(number_format_i18n((int) $r['tokens'])); ?></td>
                    <td><?php echo esc_html($r['prompt_label'] ?: '–'); ?><br><span class="description"><?php echo esc_html($r['model'] ?: ''); ?></span></td>
                    <td><?php echo self::stars($r['rating']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $pages = (int) ceil($total / self::PER_PAGE);
        if ($pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">' . paginate_links(['base' => add_query_arg('paged', '%#%'), 'format' => '', 'current' => $page, 'total' => $pages]) . '</div></div>';
        }
    }

    /** One AI reading: what TimeGrow kept, shown as short "key: value" items. */
    private static function kept_summary(array $kept) {
        $flat = [];
        $walk = function ($value, $prefix) use (&$walk, &$flat) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $walk($v, $prefix === '' ? (string) $k : $prefix . '.' . $k);
                }
            } elseif ($value !== null && $value !== '') {
                $flat[] = $prefix . ': ' . (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
            }
        };
        $walk($kept, '');
        return $flat;
    }

    private static function render_session(TimeGrowAIConversationModel $model, $id) {
        $s = $model->get($id);
        if (!$s) {
            echo '<div class="notice notice-error"><p>' . esc_html__('That conversation was not found.', 'timegrow') . '</p></div>';
            return;
        }
        $tools = self::tools();
        $turns = $model->turns($id);
        ?>
        <p><a href="<?php echo esc_url(self::url(['feature' => $s['feature']])); ?>">&larr; <?php esc_html_e('All conversations', 'timegrow'); ?></a></p>
        <div class="tg-review-layout">
            <div class="timegrow-card tg-review-chat">
                <div class="timegrow-card-header">
                    <div class="timegrow-icon timegrow-icon-primary"><span class="dashicons dashicons-<?php echo $s['feature'] === 'receipts' ? 'media-document' : 'microphone'; ?>"></span></div>
                    <div class="timegrow-card-title">
                        <h2><?php echo esc_html(sprintf(__('%1$s · %2$s', 'timegrow'), $tools[$s['feature']] ?? $s['feature'], self::person($s['user_id']))); ?></h2>
                        <span class="description"><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $s['started_at'])); ?></span>
                    </div>
                </div>
                <div class="timegrow-card-body">
                    <?php foreach ($turns as $t): $data = $t['data'] ? json_decode($t['data'], true) : []; ?>
                        <?php if ($t['who'] === 'person' || $t['who'] === 'assistant'): ?>
                            <div class="tg-review-msg tg-review-msg--<?php echo esc_attr($t['who']); ?>"><?php echo nl2br(esc_html($t['text'])); ?></div>
                        <?php elseif ($t['who'] === 'system'): ?>
                            <div class="tg-review-system tg-review-system--<?php echo esc_attr($t['intent'] ?: 'info'); ?>"><span class="dashicons dashicons-<?php echo $t['intent'] === 'saved' ? 'yes-alt' : 'info-outline'; ?>" aria-hidden="true"></span> <?php echo esc_html($t['text']); ?></div>
                        <?php else: ?>
                            <div class="tg-review-ai<?php echo (int) $t['thumbs'] === -1 ? ' is-wrong' : ((int) $t['thumbs'] === 1 ? ' is-right' : ''); ?>" id="turn-<?php echo (int) $t['ID']; ?>">
                                <div class="tg-review-ai__head">
                                    <strong><?php esc_html_e('How the AI read this', 'timegrow'); ?></strong>
                                    <span class="tg-review-intent"><?php echo esc_html($t['intent']); ?></span>
                                    <span class="description"><?php echo esc_html(sprintf(__('%1$s tokens · %2$s ms · %3$s', 'timegrow'), number_format_i18n((int) ($data['tokens'] ?? 0)), number_format_i18n((int) ($data['ms'] ?? 0)), $data['prompt'] ?? '')); ?></span>
                                    <span class="tg-review-thumbs">
                                        <?php foreach ([1 => '&#128077;', -1 => '&#128078;'] as $v => $icon): ?>
                                            <a class="<?php echo (int) $t['thumbs'] === $v ? 'is-on' : ''; ?>" title="<?php echo $v === 1 ? esc_attr__('Read correctly', 'timegrow') : esc_attr__('Read wrong', 'timegrow'); ?>"
                                               href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'timegrow_ai_review_thumbs', 'turn' => (int) $t['ID'], 'value' => $v], admin_url('admin-post.php')), self::NONCE . '_thumbs_' . (int) $t['ID'])); ?>"><?php echo $icon; ?></a>
                                        <?php endforeach; ?>
                                    </span>
                                </div>
                                <ul class="tg-review-kept">
                                    <?php foreach (self::kept_summary(is_array($data['kept'] ?? null) ? $data['kept'] : []) as $item): ?><li><?php echo esc_html($item); ?></li><?php endforeach; ?>
                                </ul>
                                <details><summary><?php esc_html_e('What the AI answered (raw)', 'timegrow'); ?></summary>
                                    <pre><?php echo esc_html(is_string($data['raw'] ?? null) ? $data['raw'] : wp_json_encode($data['raw'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
                                </details>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (!$turns): ?><p class="description"><?php esc_html_e('No messages were saved.', 'timegrow'); ?></p><?php endif; ?>
                </div>
            </div>

            <div class="tg-review-side">
                <div class="timegrow-card">
                    <div class="timegrow-card-body">
                        <h3><?php esc_html_e('Rate this conversation', 'timegrow'); ?></h3>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="timegrow_ai_review_rate">
                            <input type="hidden" name="session" value="<?php echo (int) $s['ID']; ?>">
                            <?php wp_nonce_field(self::NONCE . '_rate_' . (int) $s['ID']); ?>
                            <div class="tg-review-rate">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" id="tg-star-<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" <?php checked((int) $s['rating'], $i); ?>>
                                    <label for="tg-star-<?php echo $i; ?>" title="<?php echo esc_attr(sprintf(_n('%d star', '%d stars', $i, 'timegrow'), $i)); ?>">&#9733;</label>
                                <?php endfor; ?>
                            </div>
                            <textarea name="note" rows="4" class="large-text" placeholder="<?php esc_attr_e('What went well, what went wrong', 'timegrow'); ?>"><?php echo esc_textarea($s['rating_note'] ?? ''); ?></textarea>
                            <p><button type="submit" class="button button-primary"><?php esc_html_e('Save rating', 'timegrow'); ?></button>
                                <?php if ($s['rated_at']): ?><span class="description"><?php echo esc_html(sprintf(__('Rated by %1$s, %2$s', 'timegrow'), self::person($s['rated_by']), mysql2date(get_option('date_format'), $s['rated_at']))); ?></span><?php endif; ?></p>
                        </form>
                        <hr>
                        <dl class="tg-review-facts">
                            <dt><?php esc_html_e('Result', 'timegrow'); ?></dt><dd><?php echo esc_html($s['outcome'] !== '' ? $s['outcome'] : __('Nothing saved', 'timegrow')); ?></dd>
                            <dt><?php esc_html_e('Prompt', 'timegrow'); ?></dt><dd><?php echo esc_html($s['prompt_label'] ?: '–'); ?></dd>
                            <dt><?php esc_html_e('Model', 'timegrow'); ?></dt><dd><?php echo esc_html($s['model'] ?: '–'); ?></dd>
                            <dt><?php esc_html_e('AI readings', 'timegrow'); ?></dt><dd><?php echo (int) $s['ai_calls']; ?> · <?php echo esc_html(sprintf(__('%s tokens', 'timegrow'), number_format_i18n((int) $s['tokens']))); ?></dd>
                            <dt><?php esc_html_e('Ended', 'timegrow'); ?></dt><dd><?php echo $s['ended_at'] ? esc_html(mysql2date(get_option('time_format'), $s['ended_at'])) : esc_html__('Not closed (page left or reloaded)', 'timegrow'); ?></dd>
                        </dl>
                        <p><a class="tg-prompt-delete" href="<?php echo esc_url(wp_nonce_url(add_query_arg(['action' => 'timegrow_ai_review_delete', 'session' => (int) $s['ID']], admin_url('admin-post.php')), self::NONCE . '_delete_' . (int) $s['ID'])); ?>" onclick="return confirm('<?php echo esc_js(__('Delete this conversation?', 'timegrow')); ?>');"><?php esc_html_e('Delete conversation', 'timegrow'); ?></a></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
