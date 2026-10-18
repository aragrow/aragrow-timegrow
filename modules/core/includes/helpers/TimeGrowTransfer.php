<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Moving Settings content between sites (local → production): export a JSON file, import it on the
 * other site after a preview. Used by Settings > Knowledge Base and Settings > AI Prompts.
 *
 * A file is { "type": "timegrow-knowledge-base" | "timegrow-ai-prompts", "version": 1, "site",
 * "exported_at", and the content }. An upload is checked, then kept for 15 minutes (per person) while
 * the preview shows what would change; nothing changes until the person confirms.
 */
class TimeGrowTransfer {

    const VERSION  = 1;
    const MAX_SIZE = 2097152; // 2 MB

    /** Send a JSON file to download and end the request. */
    public static function download($filename, $type, array $content) {
        $payload = array_merge(['type' => $type, 'version' => self::VERSION, 'site' => home_url(), 'exported_at' => current_time('c')], $content);
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '-' . current_time('Y-m-d') . '.json"');
        echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * The decoded file of the given type, or an error code: no_file, too_big, not_json, wrong_type,
     * new_version. Pure apart from reading the file.
     */
    public static function read($text, $type) {
        if (!is_string($text) || trim($text) === '') {
            return 'no_file';
        }
        if (strlen($text) > self::MAX_SIZE) {
            return 'too_big';
        }
        $data = json_decode($text, true);
        if (!is_array($data)) {
            return 'not_json';
        }
        if (($data['type'] ?? '') !== $type) {
            return 'wrong_type';
        }
        if ((int) ($data['version'] ?? 0) > self::VERSION) {
            return 'new_version';
        }
        return $data;
    }

    /** The uploaded file's text, or '' when there is none or it failed. */
    public static function uploaded($field) {
        $f = $_FILES[$field] ?? null;
        if (!$f || !empty($f['error']) || empty($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) {
            return '';
        }
        if ((int) $f['size'] > self::MAX_SIZE) {
            return str_repeat(' ', self::MAX_SIZE + 1); // read() reports too_big
        }
        return (string) file_get_contents($f['tmp_name']);
    }

    /** Keep an upload for the preview (15 minutes, this person only). */
    public static function stash($key, array $data) {
        set_transient('timegrow_import_' . $key . '_' . get_current_user_id(), $data, 15 * MINUTE_IN_SECONDS);
    }

    public static function stashed($key) {
        $data = get_transient('timegrow_import_' . $key . '_' . get_current_user_id());
        return is_array($data) ? $data : null;
    }

    public static function forget($key) {
        delete_transient('timegrow_import_' . $key . '_' . get_current_user_id());
    }

    /** Error texts for read() codes. */
    public static function error_text($code) {
        $texts = [
            'no_file'     => __('Choose a file to import.', 'timegrow'),
            'too_big'     => __('That file is too big (2 MB at most).', 'timegrow'),
            'not_json'    => __('That file is not a TimeGrow export (it is not JSON).', 'timegrow'),
            'wrong_type'  => __('That file is a different kind of TimeGrow export.', 'timegrow'),
            'new_version' => __('That file was exported by a newer TimeGrow. Update TimeGrow on this site first.', 'timegrow'),
            'expired'     => __('The import timed out. Upload the file again.', 'timegrow'),
        ];
        return $texts[$code] ?? $code;
    }

    // ------------------------------------------------------------------
    // Knowledge base
    // ------------------------------------------------------------------

    /**
     * What importing knowledge base topics would do, topic by topic. Only topics in the file change;
     * the others on this site are kept. Pure.
     *
     * @param array    $current  This site's saved changes (TimeGrowNexusHelp::overrides()).
     * @param string[] $builtin  The built-in topic slugs on this site.
     * @param array    $incoming The file's "topics": slug => saved fields.
     * @param callable $validate fn(array) => array|string (TimeGrowHelpTopicsPage::validate).
     * @return array { rows: [slug, title, change (new|changed|same|hidden|invalid), why], apply: slug => fields }
     */
    public static function plan_topics(array $current, array $builtin, array $incoming, callable $validate) {
        $rows  = [];
        $apply = [];
        foreach ($incoming as $slug => $t) {
            $slug = is_string($slug) ? preg_replace('/[^a-z0-9_\-]/', '', strtolower($slug)) : '';
            if ($slug === '' || !is_array($t)) {
                continue;
            }
            $known = in_array($slug, $builtin, true) || strpos($slug, 'custom-') === 0;
            $hide_only = !isset($t['body']);
            if (!$known) {
                $rows[] = [$slug, (string) ($t['title'] ?? $slug), 'invalid', __('not a topic on this site', 'timegrow')];
                continue;
            }
            if ($hide_only) {
                $fields = ['hidden' => !empty($t['hidden'])];
            } else {
                $fields = $validate($t);
                if (!is_array($fields)) {
                    $rows[] = [$slug, (string) ($t['title'] ?? $slug), 'invalid', self::error_text($fields)];
                    continue;
                }
                $fields['hidden'] = !empty($t['hidden']);
            }
            $before = $current[$slug] ?? null;
            $same   = is_array($before) && self::same_topic($before, $fields);
            $change = $same ? 'same' : ($before === null && !in_array($slug, $builtin, true) ? 'new' : ($hide_only ? 'hidden' : 'changed'));
            $rows[]  = [$slug, (string) ($fields['title'] ?? $t['title'] ?? $slug), $change, ''];
            if (!$same) {
                $apply[$slug] = $fields;
            }
        }
        return ['rows' => $rows, 'apply' => $apply];
    }

    private static function same_topic(array $a, array $b) {
        foreach (['title', 'roles', 'page', 'keywords', 'body', 'hidden'] as $k) {
            if ((string) ($a[$k] ?? '') !== (string) ($b[$k] ?? '')) {
                return false;
            }
        }
        return true;
    }

    // ------------------------------------------------------------------
    // AI prompts
    // ------------------------------------------------------------------

    /**
     * What importing prompt versions would do: each comes in as a new draft unless this site already
     * has a version of that tool with the same text. Pure.
     *
     * @param array[]  $existing This site's versions (TimeGrowAIPromptModel::all()).
     * @param array[]  $incoming The file's "prompts".
     * @param string[] $tools    Tool keys allowed (voice, receipts).
     * @return array[] [feature, name, status in the file, change (new|duplicate|invalid), the version]
     */
    public static function plan_prompts(array $existing, array $incoming, array $tools, $max_text) {
        $have = [];
        foreach ($existing as $row) {
            $have[$row['feature'] . ':' . md5(str_replace("\r\n", "\n", (string) $row['content']))] = true;
        }
        $out = [];
        foreach ($incoming as $p) {
            if (!is_array($p)) {
                continue;
            }
            $feature = (string) ($p['feature'] ?? '');
            $content = str_replace("\r\n", "\n", (string) ($p['content'] ?? ''));
            $name    = trim((string) ($p['name'] ?? ''));
            $status  = in_array($p['status'] ?? '', ['draft', 'active', 'archived'], true) ? $p['status'] : 'draft';
            if (!in_array($feature, $tools, true) || trim($content) === '' || strlen($content) > $max_text) {
                $out[] = [$feature, $name, $status, 'invalid', $p];
                continue;
            }
            $key = $feature . ':' . md5($content);
            $out[] = [$feature, $name, $status, isset($have[$key]) ? 'duplicate' : 'new', ['feature' => $feature, 'name' => $name, 'content' => $content, 'notes' => (string) ($p['notes'] ?? ''), 'status' => $status, 'created_at' => (string) ($p['created_at'] ?? '')]];
            $have[$key] = true; // the same text twice in the file comes in once
        }
        return $out;
    }
}
