<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The client or project must be the one the person named (TimeGrowNexusVoice::converse).
 *
 * The AI's pick is checked against the person's words: a name they did not say (and did not refer
 * back to) is dropped and reported as not found. Under no circumstance is another client or project
 * chosen in its place. Pure.
 */
class TimeGrowNexusVoiceNames {

    /** Words that don't identify a client or project. */
    const NAME_STOP = ['the', 'and', 'for', 'llc', 'inc', 'ltd', 'corp', 'company', 'group', 'client', 'project', 'with', 'from'];

    /** True when the text names this client or project: the whole name, or one of its words (3+ letters). Pure. */
    public static function name_said($text, $name) {
        $norm = function ($s) { return trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower(function_exists('remove_accents') ? remove_accents((string) $s) : (string) $s))); };
        $t = ' ' . $norm($text) . ' ';
        $n = $norm($name);
        if ($n === '') {
            return false;
        }
        if (strpos($t, ' ' . $n . ' ') !== false) {
            return true;
        }
        foreach (explode(' ', $n) as $w) {
            if (strlen($w) >= 3 && !in_array($w, self::NAME_STOP, true) && strpos($t, ' ' . $w . ' ') !== false) {
                return true;
            }
        }
        return false;
    }

    /** True for a short reference to something already said: "the same client", "that project", "the second one". Pure. */
    public static function refers_back($text) {
        return (bool) preg_match('/^\s*(yes,? )?(the )?(same|that|this|first|second|third|last|previous|other)( one| client| project| as before| as last time)?\s*(please)?[.!]?\s*$|\b(same|that) (client|project)\b|\bthe (first|second|third|last|other|previous) one\b|\bas (before|last time)\b/i', (string) $text);
    }

    /**
     * Check the AI's client and project against the person's words. A pick they did not name (and did
     * not refer back to, and that was not already chosen) is dropped, and what they named is reported
     * as not found. Pure.
     *
     * @return array [the time fields to merge, not_found: ['client' => what they said, 'project' => …]]
     */
    public static function check_names(array $time, $text, array $state, array $candidates, array $clients) {
        $not_found = [];
        $unmatched = is_array($time['unmatched'] ?? null) ? $time['unmatched'] : [];
        unset($time['unmatched']);
        $known   = $state['time'] ?? [];
        $asking  = $state['asking'] ?? '';
        $back    = self::refers_back($text);
        $by_id   = [];
        foreach ($candidates as $c) {
            $by_id[(int) $c['id']] = $c;
        }
        $client_name = function ($id) use ($clients, $candidates) {
            foreach ($clients as $c) {
                if ((int) $c['id'] === (int) $id) {
                    return (string) $c['name'];
                }
            }
            foreach ($candidates as $c) {
                if ((int) $c['client_id'] === (int) $id) {
                    return (string) $c['client_name'];
                }
            }
            return '';
        };
        $short = function ($t) { // a short answer is itself the name they meant
            $t = trim(preg_replace('/\s+/', ' ', (string) $t), " .,!?\"'");
            return str_word_count($t) <= 6 ? $t : '';
        };

        // Project
        if (!empty($time['project_id'])) {
            $p = $by_id[(int) $time['project_id']] ?? null;
            $ok = $p && ((int) ($known['project_id'] ?? 0) === (int) $p['id'] || $back || self::name_said($text, $p['name']));
            if (!$ok) {
                $time['project_id'] = null;
                $not_found['project'] = trim((string) ($unmatched['project'] ?? '')) ?: ($asking === 'project_id' ? $short($text) : '') ?: self::named_after($text, 'project');
            }
        }
        // Client
        if (!empty($time['client_id'])) {
            $id = (int) $time['client_id'];
            $ok = (int) ($known['client_id'] ?? 0) === $id || $back || self::name_said($text, $client_name($id))
                || (!empty($time['project_id']) && (int) ($by_id[(int) $time['project_id']]['client_id'] ?? 0) === $id); // the client of a project they named
            if (!$ok) {
                $time['client_id'] = null;
                $not_found['client'] = trim((string) ($unmatched['client'] ?? '')) ?: ($asking === 'client_id' ? $short($text) : '') ?: self::named_after($text, 'client');
            }
        }
        // Named, and the AI left it empty
        foreach (['client' => 'client_id', 'project' => 'project_id'] as $what => $key) {
            if (empty($time[$key]) && empty($not_found[$what])) {
                $said = trim((string) ($unmatched[$what] ?? '')) ?: ($asking === $key && !$back ? $short($text) : '');
                if ($said !== '' && self::matches_none($said, $what === 'client' ? array_map(function ($c) { return $c['name']; }, $clients) : array_column($candidates, 'name'))) {
                    $not_found[$what] = $said;
                }
            }
        }
        return [$time, array_filter($not_found, function ($v) { return $v !== ''; })];
    }

    /** True when none of the names is said in the text. Pure. */
    private static function matches_none($text, array $names) {
        foreach ($names as $n) {
            if (self::name_said($text, $n)) {
                return false;
            }
        }
        return true;
    }

    /** "…for the client Latino Networking, …" → "Latino Networking" (the words after "client" or "project"). Pure. */
    public static function named_after($text, $what) {
        if (preg_match('/\b' . $what . '\s+(?:called\s+|named\s+)?([^,.;]+?)(?=\s*(?:,|\.|;|$|\bfor\b|\bon\b|\bproject\b|\bclient\b|\btoday\b|\byesterday\b))/i', (string) $text, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /**
     * The one client whose name matches (exactly, else the only partial match), or null. Pure.
     *
     * @param array[] $clients { id, name }
     */
    public static function match_client($name, array $clients) {
        $norm = function ($s) { return trim(preg_replace('/[^a-z0-9 ]+/', ' ', strtolower((string) $s))); };
        $want = preg_replace('/\s+/', ' ', $norm($name));
        if ($want === '') {
            return null;
        }
        foreach ($clients as $c) {
            if (preg_replace('/\s+/', ' ', $norm($c['name'])) === $want) {
                return $c;
            }
        }
        $hits = array_values(array_filter($clients, function ($c) use ($want, $norm) {
            $have = preg_replace('/\s+/', ' ', $norm($c['name']));
            return $have !== '' && (strpos($have, $want) !== false || strpos($want, $have) !== false);
        }));
        return count($hits) === 1 ? $hits[0] : null;
    }
}
