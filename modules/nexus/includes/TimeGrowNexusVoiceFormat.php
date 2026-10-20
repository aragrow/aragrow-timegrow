<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Wording for the voice assistant's answers: periods as dates and words, hours and money. Pure.
 */
class TimeGrowNexusVoiceFormat {

    /** [from, to] dates (inclusive) for a period. Weeks start on Monday. Pure. */
    public static function period_range($period, $today) {
        $d = new DateTimeImmutable($today);
        switch ($period) {
            case 'today':      return [$d->format('Y-m-d'), $d->format('Y-m-d')];
            case 'yesterday':  $y = $d->modify('-1 day'); return [$y->format('Y-m-d'), $y->format('Y-m-d')];
            case 'last_week':  $m = $d->modify('monday this week')->modify('-7 days'); return [$m->format('Y-m-d'), $m->modify('+6 days')->format('Y-m-d')];
            case 'this_month': return [$d->format('Y-m-01'), $d->format('Y-m-d')];
            case 'last_month': $l = $d->modify('first day of last month'); return [$l->format('Y-m-d'), $l->modify('last day of this month')->format('Y-m-d')];
            case 'this_year':  return [$d->format('Y-01-01'), $d->format('Y-m-d')];
            case 'last_year':  $y = (int) $d->format('Y') - 1; return ["{$y}-01-01", "{$y}-12-31"];
            case 'this_week':
            default:           return [$d->modify('monday this week')->format('Y-m-d'), $d->format('Y-m-d')];
        }
    }

    public static function period_words($period) {
        $words = ['today' => 'today', 'yesterday' => 'yesterday', 'this_week' => 'this week', 'last_week' => 'last week',
                  'this_month' => 'this month', 'last_month' => 'last month', 'this_year' => 'this year', 'last_year' => 'last year'];
        return $words[$period] ?? 'this week';
    }

    /** "2 h 30 min" for the screen. Pure. */
    public static function hours_text($hours) {
        $mins = (int) round($hours * 60);
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        return $h && $m ? "{$h} h {$m} min" : ($h || !$m ? "{$h} h" : "{$m} min"); // 0 shows as "0 h"
    }

    /** "2 and a half hours" to be spoken. Pure. */
    public static function hours_spoken($hours) {
        $mins = (int) round($hours * 60);
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        $unit = $h === 1 ? 'hour' : 'hours';
        if (!$h) return $m . ' minutes';
        if ($m === 30) return "{$h} and a half {$unit}";
        if ($m) return "{$h} {$unit} and {$m} minutes";
        return "{$h} {$unit}";
    }

    public static function money($amount) {
        return function_exists('wc_price') ? html_entity_decode(wp_strip_all_tags(wc_price($amount)), ENT_QUOTES) : '$' . number_format((float) $amount, 2);
    }

    public static function date_words($day, $today) {
        if ($day === $today) return __('today', 'timegrow');
        if ($day === date('Y-m-d', strtotime($today . ' -1 day'))) return __('yesterday', 'timegrow');
        return mysql2date('l, F j', $day . ' 00:00:00');
    }
}
