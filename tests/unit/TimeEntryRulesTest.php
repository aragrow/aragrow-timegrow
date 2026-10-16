<?php
use PHPUnit\Framework\TestCase;

defined( 'MINUTE_IN_SECONDS' ) || define( 'MINUTE_IN_SECONDS', 60 );
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowTimeEntryRules.php';

/**
 * Rules every time entry save follows (hours and dates; who and what is checked against the site).
 */
class TimeEntryRulesTest extends TestCase {

    public function test_a_normal_entry_is_fine() {
        $this->assertSame( '', TimeGrowTimeEntryRules::manual_problem( '2026-10-15', 2.5, '2026-10-16' ) );
        $this->assertSame( '', TimeGrowTimeEntryRules::manual_problem( '2026-10-16', 24, '2026-10-16' ) );
    }

    public function test_hours_must_be_above_zero_and_at_most_24() {
        $this->assertNotSame( '', TimeGrowTimeEntryRules::manual_problem( '2026-10-15', 0, '2026-10-16' ) );
        $this->assertNotSame( '', TimeGrowTimeEntryRules::manual_problem( '2026-10-15', 99, '2026-10-16' ) );
        $this->assertNotSame( '', TimeGrowTimeEntryRules::manual_problem( '2026-10-15', 'lots', '2026-10-16' ) );
    }

    public function test_dates_must_be_real_and_not_in_the_future() {
        $this->assertStringContainsString( 'future', TimeGrowTimeEntryRules::manual_problem( '2026-10-17', 1, '2026-10-16' ) );
        $this->assertStringContainsString( 'not valid', TimeGrowTimeEntryRules::manual_problem( '2026-02-30', 1, '2026-10-16' ) );
        $this->assertStringContainsString( 'not valid', TimeGrowTimeEntryRules::manual_problem( '10/15/2026', 1, '2026-10-16' ) );
    }

    public function test_clock_times_allow_a_slightly_fast_phone_only() {
        $now = '2026-10-16 09:00:00';
        $this->assertSame( '', TimeGrowTimeEntryRules::clock_problem( '2026-10-16 09:05:00', $now ) );
        $this->assertStringContainsString( 'future', TimeGrowTimeEntryRules::clock_problem( '2026-10-16 10:00:00', $now ) );
        $this->assertStringContainsString( 'not valid', TimeGrowTimeEntryRules::clock_problem( '2026-10-16 9am', $now ) );
    }
}
