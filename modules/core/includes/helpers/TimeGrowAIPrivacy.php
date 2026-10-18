<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The privacy layer between TimeGrow's data and the AI provider.
 *
 * Before anything is sent to the AI, identity data is replaced with stand-ins:
 * - client names → "[Client 31]", team member names → "[Person 14]", other people → "[User 5]"
 *   (the number is the database ID, so a stand-in stays the same for a whole conversation),
 * - the person's own company → "[Your company]",
 * - email addresses and phone numbers → "[Email 1]", "[Phone 1]" (numbered per request).
 * Names are also found inside other text (project names, descriptions, what the person said): the
 * full name, or a distinctive word of it ("Copperleaf"); a word that fits several clients becomes
 * "[one of Client 23, Client 24]" so the AI knows to ask.
 *
 * The AI answers with the stand-ins; unmask() swaps them back before anything is shown or saved.
 * Pure: give it the names, it never reads the database.
 */
class TimeGrowAIPrivacy {

    /** Words too common to identify anyone on their own. */
    const COMMON = ['studio', 'studios', 'llc', 'inc', 'ltd', 'corp', 'company', 'co', 'group', 'design', 'legal', 'the', 'and',
                    'services', 'solutions', 'consulting', 'agency', 'media', 'digital', 'partners', 'team', 'labs', 'works', 'shop',
                    // everyday words that also appear in business names ("Content Cucumber"): not identifying on their own
                    'content', 'website', 'marketing', 'cloud', 'coffee', 'health', 'home', 'care', 'energy', 'global', 'north', 'south',
                    'east', 'west', 'city', 'capital', 'first', 'best', 'smart', 'green', 'blue', 'gold', 'star', 'tech', 'systems',
                    'network', 'data', 'software', 'finance', 'financial', 'office', 'house', 'club', 'center', 'central', 'world',
                    'international', 'national', 'american', 'creative', 'print', 'audit', 'project', 'projects', 'client', 'clients',
                    'hours', 'time', 'today', 'week', 'month', 'year', 'billable', 'redesign', 'support', 'training'];

    private $real = [];    // token => real text
    private $names = [];   // [real name, token], longest first
    private $words = [];   // distinctive word (lowercase) => [tokens]
    private $counter = ['Email' => 0, 'Phone' => 0];

    /**
     * @param array  $clients id => name, or id => [names] (display name, billing name, company)
     * @param array  $members id => name or [names] (team members)
     * @param array  $users   id => name or [names] (other people), optional
     * @param string $company The person's own company name
     */
    public function __construct(array $clients, array $members = [], array $users = [], $company = '') {
        if (trim((string) $company) !== '') {
            $this->add(trim($company), '[Your company]', false);
            // "Aragrow" for "Aragrow, LLC"
            $short = trim(preg_replace('/[,.]?\s*(llc|inc|ltd|corp|co)\.?$/i', '', trim($company)));
            if ($short !== '' && strcasecmp($short, trim($company)) !== 0) {
                $this->add($short, '[Your company]', false);
            }
        }
        // Team members first: a person who is also a client's billing contact keeps their own stand-in
        foreach (['Person' => $members, 'User' => $users, 'Client' => $clients] as $kind => $list) {
            foreach ($list as $id => $names) {
                foreach ((array) $names as $name) {
                    // A client with the company's own name ("Aragrow LLC") always means the company
                    if ($kind === 'Client' && self::same_as_company((string) $name, $company)) {
                        $this->add((string) $name, '[Your company]', false);
                        continue;
                    }
                    $this->add((string) $name, '[' . $kind . ' ' . (int) $id . ']', true);
                }
            }
        }
        usort($this->names, function ($a, $b) { return mb_strlen($b[0]) - mb_strlen($a[0]); });
    }

    /** True when a name is the company's own name, ignoring case, punctuation and LLC/Inc ("Aragrow LLC" = "Aragrow, LLC"). Pure. */
    public static function same_as_company($name, $company) {
        $key = function ($s) {
            $s = mb_strtolower(trim((string) $s));
            $s = preg_replace('/[,.]?\s*\b(llc|inc|ltd|corp|co|company)\b\.?$/u', '', $s);
            return preg_replace('/[^\p{L}\p{N}]+/u', '', $s);
        };
        return trim((string) $company) !== '' && $key($name) !== '' && $key($name) === $key($company);
    }

    private function add($name, $token, $words) {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        if ($name === '' || mb_strlen($name) < 2) {
            return;
        }
        foreach ($this->names as [$known]) {
            if (mb_strtolower($known) === mb_strtolower($name)) {
                return; // the first owner of a name keeps it
            }
        }
        $this->real[$token] = $this->real[$token] ?? $name;
        $this->names[] = [$name, $token];
        if (!$words) {
            return;
        }
        foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($name)) as $w) {
            if (mb_strlen($w) >= 4 && !in_array($w, self::COMMON, true) && !preg_match('/^\d+$/', $w)) {
                $this->words[$w] = array_values(array_unique(array_merge($this->words[$w] ?? [], [$token])));
            }
        }
    }

    /** Replace identity data in text (or in every string of an array) with stand-ins. */
    public function mask($value) {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->mask($v);
            }
            return $out;
        }
        if (!is_string($value) || $value === '') {
            return $value;
        }
        $t = $value;
        // Emails and phone numbers (reversible within this request)
        $t = preg_replace_callback('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', function ($m) { return $this->numbered('Email', $m[0]); }, $t);
        $t = preg_replace_callback('/(?<![\w\[])(\+?\d[\d\s().-]{7,}\d)(?![\w\]])/', function ($m) {
            $digits = preg_match_all('/\d/', $m[1]);
            // Dates and times are not phone numbers: 2026-10-17, 2026-10-17 16:20, 10/17/2026
            if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}|^\d{1,2}[\/.-]\d{1,2}[\/.-]\d{2,4}$/', trim($m[1]))) {
                return $m[0];
            }
            return $digits >= 8 && $digits <= 15 ? $this->numbered('Phone', $m[1]) : $m[0];
        }, $t);
        // Full names, longest first
        foreach ($this->names as [$name, $token]) {
            $t = preg_replace('/(?<![\p{L}\p{N}\[])' . preg_quote($name, '/') . '(?![\p{L}\p{N}\]])/iu', $token, $t);
        }
        // Distinctive words of names ("Copperleaf"), outside stand-ins already placed
        $parts = preg_split('/(\[[^\]]*\])/', $t, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $part) {
            if ($part === '' || $part[0] === '[') {
                continue;
            }
            $parts[$i] = preg_replace_callback('/[\p{L}\p{N}]+/u', function ($m) {
                $w = mb_strtolower($m[0]);
                if (!isset($this->words[$w])) {
                    return $m[0];
                }
                $tokens = $this->words[$w];
                return count($tokens) === 1 ? $tokens[0] : '[one of ' . implode(', ', array_map(function ($x) { return trim($x, '[]'); }, $tokens)) . ']';
            }, $part);
        }
        return implode('', $parts);
    }

    private function numbered($kind, $real) {
        $existing = array_search($real, $this->real, true);
        if ($existing !== false && strpos($existing, '[' . $kind . ' ') === 0) {
            return $existing;
        }
        $token = '[' . $kind . ' ' . (++$this->counter[$kind]) . ']';
        $this->real[$token] = $real;
        return $token;
    }

    /** Swap stand-ins back to the real data (text or every string of an array). Also reads them without brackets. */
    public function unmask($value) {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = $this->unmask($v);
            }
            return $out;
        }
        if (!is_string($value) || $value === '') {
            return $value;
        }
        $t = preg_replace_callback('/\[one of ([^\]]+)\]/', function ($m) {
            return implode(' / ', array_map(function ($x) { return $this->real['[' . trim($x) . ']'] ?? trim($x); }, explode(',', $m[1])));
        }, $value);
        $t = preg_replace_callback('/\[?\b(Client|Person|User|Email|Phone) (\d+)\b\]?|\[Your company\]/', function ($m) {
            $token = $m[0] === '[Your company]' ? '[Your company]' : '[' . $m[1] . ' ' . $m[2] . ']';
            return $this->real[$token] ?? $m[0];
        }, $t);
        return $t;
    }

    /**
     * The layer for the logged-in person: every client (display name, billing name and company),
     * every team member and the person's company. Never includes emails, phones, addresses or tax IDs:
     * those are not given to the AI at all.
     */
    public static function for_site($company = '') {
        $clients = [];
        if (class_exists('TimeGrowClientModel')) {
            foreach ((array) (new TimeGrowClientModel())->select() as $u) {
                $id = (int) $u->ID;
                $clients[$id] = array_filter([
                    $u->display_name,
                    trim(get_user_meta($id, 'billing_first_name', true) . ' ' . get_user_meta($id, 'billing_last_name', true)),
                    get_user_meta($id, 'billing_company', true),
                    trim(get_user_meta($id, 'first_name', true) . ' ' . get_user_meta($id, 'last_name', true)),
                ]);
            }
        }
        $members = [];
        if (class_exists('TimeGrowTeamMemberModel')) {
            foreach ((array) (new TimeGrowTeamMemberModel())->select() as $m) {
                $u = get_userdata((int) $m->user_id);
                $members[(int) $m->ID] = array_filter([$m->name, $u ? $u->display_name : '']);
            }
        }
        return new self($clients, $members, [], $company);
    }

    /**
     * The real names behind each "[one of ...]" in masked text (a name that fits several clients),
     * so the person can be asked which one they mean. @return string[][]
     */
    public function ambiguous($masked) {
        preg_match_all('/\[one of ([^\]]+)\]/', (string) $masked, $m);
        return array_map(function ($group) {
            return array_map(function ($x) { return $this->real['[' . trim($x) . ']'] ?? trim($x); }, explode(',', $group));
        }, $m[1]);
    }

    /** True when the text still holds a known real name (for tests and checks). */
    public function leaks($text) {
        foreach ($this->names as [$name]) {
            if (mb_stripos((string) $text, $name) !== false) {
                return true;
            }
        }
        return false;
    }
}
