<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Preset date ranges for report filters ("Last week", "Last month"...), and the buttons that set them.
 * Weeks start on Monday, like the Voice AI's periods. Pure apart from render().
 */
class TimeGrowDateRanges {

    /**
     * @param string $today YYYY-MM-DD (site time).
     * @return array key => [label, from, to] (YYYY-MM-DD)
     */
    public static function presets($today) {
        $d      = new DateTimeImmutable($today);
        $monday = $d->modify('monday this week');
        $last   = $d->modify('first day of last month');
        $year   = (int) $d->format('Y');
        return [
            'today'         => [__('Today', 'timegrow'), $d->format('Y-m-d'), $d->format('Y-m-d')],
            'yesterday'     => [__('Yesterday', 'timegrow'), $d->modify('-1 day')->format('Y-m-d'), $d->modify('-1 day')->format('Y-m-d')],
            'this_week'     => [__('This week', 'timegrow'), $monday->format('Y-m-d'), $d->format('Y-m-d')],
            'last_week'     => [__('Last week', 'timegrow'), $monday->modify('-7 days')->format('Y-m-d'), $monday->modify('-1 day')->format('Y-m-d')],
            'this_month'    => [__('This month', 'timegrow'), $d->format('Y-m-01'), $d->format('Y-m-d')],
            'last_month'    => [__('Last month', 'timegrow'), $last->format('Y-m-d'), $last->format('Y-m-t')],
            'last_3_months' => [__('Last 3 months', 'timegrow'), $d->modify('first day of -2 months')->format('Y-m-d'), $d->format('Y-m-d')],
            'this_year'     => [__('This year', 'timegrow'), $d->format('Y-01-01'), $d->format('Y-m-d')],
            'last_year'     => [__('Last year', 'timegrow'), ($year - 1) . '-01-01', ($year - 1) . '-12-31'],
        ];
    }

    /** A YYYY-MM-DD date from the request, or '' (anything else, including injected markup, is dropped). Pure. */
    public static function valid_date($value) {
        $v = is_string($value) ? trim($value) : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return '';
        }
        [$y, $m, $d] = array_map('intval', explode('-', $v));
        return checkdate($m, $d, $y) ? $v : '';
    }

    /** The preset whose dates are exactly $from to $to, or ''. Pure. */
    public static function active($from, $to, $today) {
        foreach (self::presets($today) as $key => $p) {
            if ($p[1] === $from && $p[2] === $to) {
                return $key;
            }
        }
        return '';
    }

    /**
     * One button per preset, linking to the report with its start_date and end_date. The current one is highlighted.
     *
     * @param array $base Query arguments of the report (page, report_slug...).
     * @param array $keys The two query parameters for the dates (start_date / end_date unless the page uses others).
     */
    public static function render(array $base, $from, $to, array $keys = ['start_date', 'end_date']) {
        $today  = current_time('Y-m-d');
        $active = self::active($from, $to, $today);
        echo '<div class="timegrow-date-presets" style="display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px;">';
        echo '<span style="font-weight: bold; align-self: center; margin-right: 4px;">' . esc_html__('Quick range:', 'timegrow') . '</span>';
        foreach (self::presets($today) as $key => [$label, $start, $end]) {
            $url = add_query_arg(array_merge($base, [$keys[0] => $start, $keys[1] => $end]), admin_url('admin.php'));
            printf('<a href="%s" class="button%s"%s>%s</a>', esc_url($url), $key === $active ? ' button-primary' : '',
                $key === $active ? ' aria-current="true"' : '', esc_html($label));
        }
        echo '</div>';
    }
}
