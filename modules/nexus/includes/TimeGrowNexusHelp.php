<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The Voice AI's help library: how to use TimeGrow and what it does.
 *
 * Help topics are short Markdown files in modules/nexus/help/, each with a header:
 *   ---
 *   title: Upload a receipt
 *   roles: everyone            (or "admin": only administrators get it)
 *   page: timegrow-nexus-expenses   (the admin page it is about, optional; may include &tab=...)
 *   keywords: receipt, photo, scan
 *   ---
 *   The text.
 *
 * A "help" question finds the best topics by the words in it (search()), and the AI answers from
 * those topics only (answer()); without AI, the best topic's first paragraph is shown. "What can you
 * do?" is answered from what this person is allowed to ask and do (capabilities()), so it is always
 * right for their role.
 *
 * The files are the built-in topics. Changes made in Settings > Knowledge Base are saved in the
 * option OPTION (they survive plugin updates): an edited copy or a hidden flag for a built-in
 * topic, and topics added there ("custom-…"). merge() puts the two together.
 */
class TimeGrowNexusHelp {

    /** Words that don't help find a topic. */
    const STOP = ['the', 'and', 'for', 'how', 'can', 'what', 'where', 'when', 'why', 'does', 'did', 'you', 'your', 'are', 'with', 'this',
                  'that', 'there', 'have', 'has', 'into', 'from', 'about', 'want', 'need', 'would', 'should', 'could', 'use', 'using',
                  'timegrow', 'please', 'tell', 'show', 'help', 'way', 'get', 'make', 'some', 'any', 'all', 'its', 'it', 'they', 'them',
                  'sam', 'spade', 'hey', 'hello', 'which', 'who', 'way', 'work', 'works'];

    /** Settings > Knowledge Base changes: slug => [title, roles, page, keywords, body, hidden, updated_at, updated_by]. */
    const OPTION = 'timegrow_help_topics';

    /** The help files' folder. */
    public static function dir() {
        return dirname(__DIR__) . '/help';
    }

    /**
     * One help file's text as a topic: [slug, title, roles, page, keywords[], body]. Pure.
     *
     * @return array|null Null when the header is missing.
     */
    public static function parse($slug, $text) {
        if (!preg_match('/^---\s*\n(.*?)\n---\s*\n(.*)$/s', str_replace("\r\n", "\n", (string) $text), $m)) {
            return null;
        }
        $head = [];
        foreach (explode("\n", $m[1]) as $line) {
            if (preg_match('/^([a-z_]+):\s*(.*)$/', trim($line), $kv)) {
                $head[$kv[1]] = trim($kv[2]);
            }
        }
        if (empty($head['title'])) {
            return null;
        }
        return [
            'slug'     => (string) $slug,
            'title'    => $head['title'],
            'roles'    => ($head['roles'] ?? 'everyone') === 'admin' ? 'admin' : 'everyone',
            'page'     => $head['page'] ?? '',
            'keywords' => array_values(array_filter(array_map('trim', explode(',', strtolower($head['keywords'] ?? ''))))),
            'body'     => trim($m[2]),
        ];
    }

    /** The built-in topics: the help files, each with source "built-in". */
    public static function builtin() {
        static $topics = null;
        if ($topics !== null) {
            return $topics;
        }
        $topics = [];
        foreach ((array) glob(self::dir() . '/*.md') as $file) {
            $topic = self::parse(basename($file, '.md'), (string) file_get_contents($file));
            if ($topic) {
                $topics[] = $topic + ['source' => 'built-in', 'hidden' => false];
            }
        }
        return $topics;
    }

    /** The changes saved in Settings > Knowledge Base. */
    public static function overrides() {
        $saved = function_exists('get_option') ? get_option(self::OPTION, []) : [];
        return is_array($saved) ? $saved : [];
    }

    /**
     * The built-in topics with the saved changes applied: an edited topic replaces the built-in one
     * (source "edited"), a hidden one is marked hidden, custom ones are added (source "custom"). Pure.
     */
    public static function merge(array $builtin, array $overrides) {
        $out = [];
        foreach ($builtin as $t) {
            $o = $overrides[$t['slug']] ?? null;
            if (is_array($o)) {
                $hidden_only = !isset($o['body']);
                $t = $hidden_only ? $t : array_merge($t, self::clean($o), ['source' => 'edited']);
                $t['hidden'] = !empty($o['hidden']);
            }
            $out[] = $t;
        }
        foreach ($overrides as $slug => $o) {
            if (strpos((string) $slug, 'custom-') === 0 && is_array($o) && isset($o['body'])) {
                $out[] = array_merge(['slug' => (string) $slug], self::clean($o), ['source' => 'custom', 'hidden' => !empty($o['hidden'])]);
            }
        }
        return $out;
    }

    /** A saved topic's fields, in the shape parse() gives. Pure. */
    private static function clean(array $o) {
        return [
            'title'    => (string) ($o['title'] ?? ''),
            'roles'    => ($o['roles'] ?? 'everyone') === 'admin' ? 'admin' : 'everyone',
            'page'     => (string) ($o['page'] ?? ''),
            'keywords' => array_values(array_filter(array_map('trim', explode(',', strtolower((string) ($o['keywords'] ?? '')))))),
            'body'     => trim((string) ($o['body'] ?? '')),
        ];
    }

    /** Every topic, including hidden ones (for Settings > Knowledge Base). */
    public static function all_topics() {
        return self::merge(self::builtin(), self::overrides());
    }

    /** The topics the assistant uses: everything not hidden. */
    public static function topics() {
        return array_values(array_filter(self::all_topics(), function ($t) { return empty($t['hidden']); }));
    }

    /** A word in its plain form, so "receipts", "uploading" and "uploaded" match "receipt" and "upload". Pure. */
    public static function stem($word) {
        $w = strtolower($word);
        if (strlen($w) <= 4) {
            return $w;
        }
        if (preg_match('/ies$/', $w)) {
            return substr($w, 0, -3) . 'y';           // categories → category
        }
        if (preg_match('/(ss|x|ch|sh)es$/', $w)) {
            return substr($w, 0, -2);                 // boxes → box
        }
        if (preg_match('/[^s]s$/', $w)) {
            return substr($w, 0, -1);                 // invoices → invoice, receipts → receipt
        }
        foreach (['ing', 'ed'] as $end) {             // billing → bill, uploaded → upload
            if (strlen($w) > strlen($end) + 3 && substr($w, -strlen($end)) === $end) {
                return substr($w, 0, -strlen($end));
            }
        }
        return $w;
    }

    /** The words of a text worth searching for. Pure. */
    public static function words($text) {
        preg_match_all('/[a-z0-9]+/', strtolower((string) $text), $m);
        $out = [];
        foreach ($m[0] as $w) {
            if (strlen($w) >= 3 && !in_array($w, self::STOP, true)) {
                $out[self::stem($w)] = true;
            }
        }
        return array_keys($out);
    }

    /** How well a topic fits the words: keywords count most, then the title, then the text. Pure. */
    public static function score(array $topic, array $words) {
        if (!$words) {
            return 0;
        }
        $kw    = self::words(implode(' ', $topic['keywords']));
        $title = self::words($topic['title']);
        $body  = self::words($topic['body']);
        $score = 0;
        foreach ($words as $w) {
            $score += (in_array($w, $kw, true) ? 3 : 0) + (in_array($w, $title, true) ? 2 : 0) + (in_array($w, $body, true) ? 1 : 0);
        }
        return $score;
    }

    /**
     * The best topics for a question, best first; administrator topics only for administrators. Pure.
     *
     * @param array[] $topics From topics().
     */
    public static function search($question, array $topics, $is_admin, $limit = 3) {
        $words  = self::words($question);
        $scored = [];
        foreach ($topics as $i => $topic) {
            if ($topic['roles'] === 'admin' && !$is_admin) {
                continue;
            }
            $s = self::score($topic, $words);
            if ($s >= 3) { // at least a keyword, or a title word and a text word
                $scored[] = [$s, $i, $topic];
            }
        }
        usort($scored, function ($a, $b) { return $b[0] <=> $a[0] ?: $a[1] <=> $b[1]; });
        // Only close matches: at least half as good as the best one
        $top    = $scored ? $scored[0][0] : 0;
        $scored = array_filter($scored, function ($x) use ($top) { return $x[0] * 2 >= $top; });
        return array_map(function ($x) { return $x[2]; }, array_slice(array_values($scored), 0, $limit));
    }

    /** True for "what can you do", "what are you able to do", "what can I ask you". Pure. */
    public static function asks_capabilities($text) {
        return (bool) preg_match('/\bwhat (else )?(can|could) (you|i ask|i say)|\bwhat are you able\b|\bwhat do you do\b|\bwho are you\b|\bwhat can i do here\b/i', (string) $text);
    }

    /**
     * "What can you do?" for this person: from the questions and actions they are allowed. Pure.
     *
     * @param string[] $questions TimeGrowNexusAssistant::allowed_questions().
     * @param string[] $actions   TimeGrowNexusAssistant::allowed_actions().
     */
    public static function capabilities($name, array $questions, array $actions, $can_clock) {
        $asks = [];
        $map  = [
            'hours' => 'how many hours you worked', 'recent_entries' => 'your recent entries', 'last_entry' => 'your last entry',
            'clients' => 'your clients', 'projects_for_client' => "a client's projects", 'latest_projects' => 'the newest projects',
            'clock_status' => 'whether you are clocked in', 'unbilled_hours' => 'unbilled hours', 'expenses' => 'expenses',
            'unpaid_invoices' => 'unpaid invoices', 'billed_amount' => 'how much was billed',
        ];
        foreach ($map as $q => $words) {
            if (in_array($q, $questions, true)) {
                $asks[] = $words;
            }
        }
        $do = ['record your time ("two hours yesterday on the audit")'];
        if ($can_clock) {
            $do[] = 'punch you in and out';
        }
        if (in_array('add_client', $actions, true) || in_array('add_project', $actions, true)) {
            $do[] = 'add clients and projects';
        }
        $do[] = 'explain how to use TimeGrow';
        $text = sprintf("I'm %s. I can %s. You can also ask me about %s.", $name, self::join_list($do), self::join_list($asks));
        if (in_array('lookup', $questions, true)) {
            $text .= ' Other questions about your TimeGrow data work too, like hours by client for a month.';
        }
        return $text . ' I always read back what I will save and wait for you to confirm.';
    }

    private static function join_list(array $items) {
        if (count($items) < 2) {
            return (string) reset($items);
        }
        $last = array_pop($items);
        return implode(', ', $items) . ' and ' . $last;
    }

    /** The first paragraph of a topic, for answers without AI. Pure. */
    public static function summary(array $topic, $max = 400) {
        $para = trim(preg_split('/\n\s*\n/', $topic['body'])[0]);
        $para = trim(preg_replace('/\s+/', ' ', preg_replace('/[*_`#>]+/', '', $para)));
        return mb_strlen($para) > $max ? rtrim(mb_substr($para, 0, $max - 1)) . '…' : $para;
    }

    /** A link to the page a topic is about (only pages the person can open). */
    private static function rows(array $topics) {
        $rows = [];
        foreach ($topics as $t) {
            if ($t['page'] === '') {
                continue;
            }
            $rows[] = ['title' => sprintf(__('Open: %s', 'timegrow'), $t['title']), 'meta' => __('Help', 'timegrow'),
                       'url' => admin_url('admin.php?page=' . $t['page'])];
        }
        return $rows;
    }

    /**
     * The answer to a help question. The AI answers from the topics found, and only from them; without
     * AI, or if it can't be reached, the best topic's first paragraph is the answer.
     */
    public static function answer($question, $is_admin, array $questions = [], array $actions = [], $can_clock = false) {
        $agent = class_exists('TimeGrowNexusVoice') ? TimeGrowNexusVoice::agent_name() : 'Sam Spade';
        if (self::asks_capabilities($question)) {
            $text = self::capabilities($agent, $questions, $actions, $can_clock);
            return ['text' => $text, 'spoken' => $text, 'rows' => []];
        }
        $all    = self::topics();
        $topics = self::search($question, $all, $is_admin);
        // An administrator task asked by a team member: say so, instead of a near miss
        $admin_best = $is_admin ? [] : self::search($question, $all, true, 1);
        if ($admin_best && $admin_best[0]['roles'] === 'admin' && (!$topics || self::score($admin_best[0], self::words($question)) > self::score($topics[0], self::words($question)))) {
            $text = sprintf(__('"%s" is something an administrator does. Ask your administrator for help with it.', 'timegrow'), $admin_best[0]['title']);
            return ['text' => $text, 'spoken' => $text, 'rows' => []];
        }
        if (!$topics) {
            $text = __("I couldn't find that in the TimeGrow help. Try asking another way, for example \"how do I upload a receipt?\"", 'timegrow');
            return ['text' => $text, 'spoken' => $text, 'rows' => []];
        }
        $fallback = ['text' => self::summary($topics[0]), 'spoken' => self::summary($topics[0]), 'rows' => self::rows($topics)];
        if (!class_exists('TimeGrowAI') || !TimeGrowAI::is_configured('voice')) {
            return $fallback;
        }
        $privacy = class_exists('TimeGrowNexusVoice') ? TimeGrowNexusVoice::privacy() : null;
        $prompt  = TimeGrowNexusVoicePrompt::help_prompt($question, $topics);
        $system  = TimeGrowNexusVoicePrompt::help_instructions($agent);
        $answer = TimeGrowAI::request([
            'feature'    => 'voice',
            'system'     => $system,
            'prompt'     => $privacy ? $privacy->mask($prompt) : $prompt,
            'json'       => true,
            'max_tokens' => 260,
            'timeout'    => 20,
        ]);
        if (is_wp_error($answer)) {
            return $fallback;
        }
        $json = TimeGrowAI::json_from_text($answer['text']);
        [$text, $linked] = self::reply_text(is_array($json) ? $json : [], $topics, $system);
        if ($text === '') {
            return $fallback;
        }
        $text = $privacy ? $privacy->unmask($text) : $text;
        return ['text' => $text, 'spoken' => $text, 'rows' => self::rows($linked)];
    }

    /**
     * The reply from the AI's help answer, and the topics to link: ['', …] to use the best topic's
     * summary instead. When the help does not answer the question, the reply is written here: it names
     * a related topic the AI picked from those sent, or says the help doesn't cover it (no links). An
     * answer that copies the instructions it was given is not shown. Pure apart from wp_strip_all_tags().
     *
     * @param array   $json         { answered, answer, topic }
     * @param array[] $topics       The topics sent, best first.
     * @param string  $instructions The system instructions sent.
     * @return array [text, topics to link]
     */
    public static function reply_text(array $json, array $topics, $instructions = '') {
        if (!$topics) {
            return ['', []];
        }
        // false, or "false" / "no" from a model that sends text
        $answered = isset($json['answered']) ? filter_var($json['answered'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
        if ($answered === false) {
            foreach ($topics as $t) {
                if (is_string($json['topic'] ?? null) && strcasecmp(trim($json['topic']), $t['title']) === 0) {
                    return [sprintf(__('I don\'t know that one. The closest help topic is "%s".', 'timegrow'), $t['title']), [$t]];
                }
            }
            return [__('I don\'t know that one: the TimeGrow help doesn\'t cover it. Try asking another way, or ask your administrator.', 'timegrow'), []];
        }
        $text = is_string($json['answer'] ?? null) ? trim(wp_strip_all_tags($json['answer'])) : '';
        if (self::copies($text, $instructions)) {
            return ['', $topics];
        }
        return [$text, $topics];
    }

    /** Words in a row that count as copying: an answer never needs this many of the instructions' words. */
    const COPY_RUN = 6;

    /** True when the text has COPY_RUN words in a row that are also in a row in the source. Pure. */
    public static function copies($text, $source) {
        $words = function ($s) { return preg_split('/[^a-z0-9\']+/', strtolower((string) $s), -1, PREG_SPLIT_NO_EMPTY); };
        $src = $words($source);
        $runs = [];
        for ($i = 0; $i + self::COPY_RUN <= count($src); $i++) {
            $runs[implode(' ', array_slice($src, $i, self::COPY_RUN))] = true;
        }
        $t = $words($text);
        for ($i = 0; $i + self::COPY_RUN <= count($t); $i++) {
            if (isset($runs[implode(' ', array_slice($t, $i, self::COPY_RUN))])) {
                return true;
            }
        }
        return false;
    }
}
