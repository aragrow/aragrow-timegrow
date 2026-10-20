<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TimeGrow > User Guide: the knowledge base (TimeGrowNexusHelp) as a page people can read.
 *
 * The same topics the voice assistant answers "how do I…?" from, so the guide is always current;
 * edits in Settings > Knowledge Base show up here. Each person sees the topics for their role
 * (administrator topics only for administrators). Topics are grouped into sections, can be searched,
 * and link to the page they are about.
 */
class TimeGrowUserGuidePage {

    const SLUG = 'user-guide'; // the page is TIMEGROW_PARENT_MENU . '-user-guide'

    /** Sections, in order: title => topic slugs. Topics not listed (including ones added in Settings) go under "More". */
    const SECTIONS = [
        'Getting started'     => ['what-is-timegrow', 'timegrow-setup', 'dashboard', 'user-guide', 'privacy'],
        'Recording time'      => ['punch-in-out', 'manual-time-entry', 'edit-time-entries', 'deactivate-time-entry', 'time-entry-filters'],
        'Expenses'            => ['record-expense', 'upload-receipt', 'expenses-list', 'expense-categories'],
        'The voice assistant' => ['voice-assistant', 'voice-record-time', 'voice-questions', 'assistant-name-voice'],
        'Reports'             => ['reports', 'unbilled-time', 'team-hours', 'project-financials', 'audit-log', 'tax-and-pnl'],
        'Clients and projects' => ['clients', 'projects', 'team-members', 'companies'],
        'Invoicing'           => ['woocommerce-setup', 'process-time', 'paypal-setup', 'paypal-invoices', 'sync-payments', 'print-mail'],
        'Settings'            => ['ai-setup', 'voice-settings', 'receipt-settings', 'knowledge-base', 'ai-prompts', 'ai-review', 'mobile-access'],
    ];

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'register'], 30);
    }

    public static function url() {
        return admin_url('admin.php?page=' . TIMEGROW_PARENT_MENU . '-' . self::SLUG);
    }

    public static function register() {
        add_submenu_page(TIMEGROW_PARENT_MENU, __('User Guide', 'timegrow'), __('User Guide', 'timegrow'), 'access_nexus_dashboard', TIMEGROW_PARENT_MENU . '-' . self::SLUG, [__CLASS__, 'render']);
    }

    /**
     * The topics in their sections, for one person: [section title => topics]. Administrator topics only
     * for administrators; empty sections left out. Pure.
     */
    public static function grouped(array $topics, $is_admin) {
        $by_slug = [];
        foreach ($topics as $t) {
            if ($t['roles'] === 'admin' && !$is_admin) {
                continue;
            }
            $by_slug[$t['slug']] = $t;
        }
        $out = [];
        foreach (self::SECTIONS as $title => $slugs) {
            foreach ($slugs as $slug) {
                if (isset($by_slug[$slug])) {
                    $out[$title][] = $by_slug[$slug];
                    unset($by_slug[$slug]);
                }
            }
        }
        if ($by_slug) {
            usort($by_slug, function ($a, $b) { return strnatcasecmp($a['title'], $b['title']); });
            $out['More'] = array_values($by_slug);
        }
        return $out;
    }

    public static function render() {
        $is_admin = class_exists('TimeGrowNexusAssistant') ? TimeGrowNexusAssistant::is_owner() : current_user_can('administrator');
        $sections = class_exists('TimeGrowNexusHelp') ? self::grouped(TimeGrowNexusHelp::topics(), $is_admin) : [];
        $voice    = class_exists('TimeGrowNexusVoice') && TimeGrowNexusVoice::available_for_current_user();
        ?>
        <div class="wrap timegrow-modern-wrapper tg-guide">
            <div class="timegrow-modern-header">
                <div class="timegrow-header-content">
                    <h1><?php esc_html_e('User Guide', 'timegrow'); ?></h1>
                    <p class="subtitle"><?php esc_html_e('How to use TimeGrow: recording time and expenses, reports, invoicing and settings.', 'timegrow'); ?></p>
                </div>
                <div class="timegrow-header-illustration"><span class="dashicons dashicons-book-alt"></span></div>
            </div>

            <div class="tg-guide-bar">
                <label for="tg-guide-search" class="screen-reader-text"><?php esc_html_e('Search the guide', 'timegrow'); ?></label>
                <input type="search" id="tg-guide-search" placeholder="<?php esc_attr_e('Search the guide, for example "receipt" or "punch in"', 'timegrow'); ?>">
                <?php if ($voice) : ?>
                    <button type="button" class="button button-primary tg-open-assistant"><span class="dashicons dashicons-microphone" aria-hidden="true"></span> <?php esc_html_e('Ask the AI assistant', 'timegrow'); ?></button>
                <?php endif; ?>
            </div>

            <?php if (!$sections) : ?>
                <p><?php esc_html_e('The guide is not available right now.', 'timegrow'); ?></p>
            <?php else : ?>
                <nav class="tg-guide-toc" aria-label="<?php esc_attr_e('Sections', 'timegrow'); ?>">
                    <?php foreach (array_keys($sections) as $title) : ?>
                        <a href="#tg-guide-<?php echo esc_attr(sanitize_title($title)); ?>"><?php echo esc_html($title); ?></a>
                    <?php endforeach; ?>
                </nav>
                <p class="tg-guide-empty" hidden><?php esc_html_e('No topic matches. Try another word, or ask the AI assistant.', 'timegrow'); ?></p>

                <?php foreach ($sections as $title => $topics) : ?>
                    <section class="tg-guide-section" id="tg-guide-<?php echo esc_attr(sanitize_title($title)); ?>">
                        <h2 class="timegrow-section-title"><?php echo esc_html($title); ?></h2>
                        <?php foreach ($topics as $t) : ?>
                            <article class="tg-guide-topic" id="tg-topic-<?php echo esc_attr($t['slug']); ?>" data-search="<?php echo esc_attr(strtolower($t['title'] . ' ' . implode(' ', $t['keywords']) . ' ' . $t['body'])); ?>">
                                <h3><?php echo esc_html($t['title']); ?>
                                    <?php if ($t['roles'] === 'admin') : ?><span class="tg-guide-badge"><?php esc_html_e('Administrators', 'timegrow'); ?></span><?php endif; ?>
                                </h3>
                                <?php foreach (preg_split('/\n\s*\n/', trim($t['body'])) as $para) : ?>
                                    <p><?php echo esc_html(trim(preg_replace('/\s+/', ' ', $para))); ?></p>
                                <?php endforeach; ?>
                                <?php if ($t['page'] !== '') : ?>
                                    <p><a class="tg-guide-open" href="<?php echo esc_url(admin_url('admin.php?page=' . $t['page'])); ?>"><?php esc_html_e('Open this page', 'timegrow'); ?> &rarr;</a></p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <style>
            .tg-guide-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0 0 12px; }
            .tg-guide-bar input[type="search"] { flex: 1 1 320px; max-width: 520px; min-height: 34px; }
            .tg-guide-bar .button .dashicons, .timegrow-help-link .dashicons { vertical-align: text-bottom; }
            .tg-guide-toc { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 20px; }
            .tg-guide-toc a { padding: 4px 10px; border: 1px solid #dcdcde; border-radius: 999px; background: #fff; text-decoration: none; font-size: 13px; }
            .tg-guide-section { margin-bottom: 28px; }
            .tg-guide-topic { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 14px 18px; margin-bottom: 10px; max-width: 900px; }
            .tg-guide-topic h3 { margin: 0 0 8px; font-size: 15px; }
            .tg-guide-topic p { margin: 0 0 8px; line-height: 1.6; }
            .tg-guide-badge { margin-left: 6px; padding: 1px 8px; border-radius: 999px; background: #f0f0f1; color: #50575e; font-size: 11px; font-weight: 500; vertical-align: middle; }
        </style>
        <script>
        (function () {
            var input = document.getElementById('tg-guide-search');
            if (!input) { return; }
            var topics = [].slice.call(document.querySelectorAll('.tg-guide-topic'));
            var sections = [].slice.call(document.querySelectorAll('.tg-guide-section'));
            var empty = document.querySelector('.tg-guide-empty');
            input.addEventListener('input', function () {
                var words = input.value.toLowerCase().split(/\s+/).filter(Boolean);
                var shown = 0;
                topics.forEach(function (t) {
                    var text = t.getAttribute('data-search');
                    var hit = words.every(function (w) { return text.indexOf(w) !== -1; });
                    t.hidden = !hit; shown += hit ? 1 : 0;
                });
                sections.forEach(function (s) { s.hidden = !s.querySelector('.tg-guide-topic:not([hidden])'); });
                if (empty) { empty.hidden = shown > 0; }
            });
        })();
        </script>
        <?php self::assistant_script();
    }

    /** "Ask the AI assistant" buttons (class tg-open-assistant) open the voice chat that is on every admin page. */
    public static function assistant_script() {
        ?>
        <script>
        document.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('.tg-open-assistant') : null;
            if (!b) { return; }
            e.preventDefault();
            if (window.TimeGrowVoiceEntry && window.TimeGrowVoiceEntry.open) { window.TimeGrowVoiceEntry.open(); }
        });
        </script>
        <?php
    }
}
