<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * "Your latest entries" under Clock In / Out and Manual Time Entry: the person's own latest time
 * entries (manual and clocked), newest first, so they can see what they recorded (on the mobile
 * website too, which has no reports).
 */
class TimeGrowNexusRecentEntries {

    const LIMIT = 10;

    /** The member's latest active entries, with the project and client. @return object[] */
    public static function entries($member_id, $limit = self::LIMIT) {
        if (!$member_id) {
            return [];
        }
        global $wpdb;
        $p = $wpdb->prefix . TIMEGROW_PREFIX;
        return (array) $wpdb->get_results($wpdb->prepare(
            "SELECT e.*, COALESCE(e.date, e.clock_in_date) AS day, pr.name AS project_name, pr.client_id
             FROM {$p}time_entry_tracker e
             LEFT JOIN {$p}project_tracker pr ON pr.ID = e.project_id
             WHERE e.member_id = %d AND e.status = 1
             ORDER BY COALESCE(e.date, e.clock_in_date) DESC, e.ID DESC
             LIMIT %d",
            (int) $member_id, (int) $limit
        ));
    }

    /** "2 h 30 min", or "Clocked in" for an open clock-in. Pure. */
    public static function hours_text($hours) {
        if ($hours === null) {
            return __('Clocked in', 'timegrow');
        }
        $mins = (int) round($hours * 60);
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        return $h && $m ? sprintf(__('%1$d h %2$d min', 'timegrow'), $h, $m) : ($m && !$h ? sprintf(__('%d min', 'timegrow'), $m) : sprintf(__('%d h', 'timegrow'), $h));
    }

    public static function render($member_id) {
        $rows = self::entries($member_id);
        ?>
        <div class="timegrow-recent-entries">
            <h2 class="timegrow-section-title"><?php esc_html_e('Your latest entries', 'timegrow'); ?></h2>
            <?php if (!$member_id) : ?>
                <p><?php esc_html_e('Your user is not set up as a team member, so there is no time recorded for you.', 'timegrow'); ?></p>
            <?php elseif (!$rows) : ?>
                <p><?php esc_html_e('No entries yet.', 'timegrow'); ?></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead><tr>
                        <th><?php esc_html_e('Date', 'timegrow'); ?></th>
                        <th><?php esc_html_e('Project', 'timegrow'); ?></th>
                        <th class="num"><?php esc_html_e('Time', 'timegrow'); ?></th>
                        <th><?php esc_html_e('How', 'timegrow'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($rows as $e) :
                        $client = TimeGrowProjectPicker::client_name($e->client_id); ?>
                        <tr>
                            <td><?php echo esc_html(mysql2date(get_option('date_format'), $e->day)); ?></td>
                            <td><?php echo esc_html((string) $e->project_name); ?><?php if ($client !== '') : ?><br><small><?php echo esc_html($client); ?></small><?php endif; ?></td>
                            <td class="num"><?php echo esc_html(self::hours_text(TimeGrowWooOrderCreator::entry_hours($e))); ?></td>
                            <td><?php echo $e->entry_type === 'MAN' ? esc_html__('Manual', 'timegrow') : esc_html__('Clock', 'timegrow'); ?><?php if ((int) $e->billed === 1) : ?> &middot; <?php esc_html_e('Billed', 'timegrow'); ?><?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <style>
            .timegrow-recent-entries { margin: 24px 0; max-width: 900px; }
            .timegrow-recent-entries .num { text-align: right; white-space: nowrap; }
        </style>
        <?php
    }
}
