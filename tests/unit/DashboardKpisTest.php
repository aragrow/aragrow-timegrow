<?php
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/modules/nexus/includes/TimeGrowNexusKpis.php';

/** Dashboard KPIs: every day and week is on the chart, including the empty ones. */
class DashboardKpisTest extends TestCase {

    public function test_days_are_filled() {
        $d = TimeGrowNexusKpis::fill_days( [ '2026-10-16' => 3.5, '2026-10-18' => 2 ], '2026-10-15', '2026-10-18' );
        $this->assertSame( [ '2026-10-15' => 0.0, '2026-10-16' => 3.5, '2026-10-17' => 0.0, '2026-10-18' => 2.0 ], $d );
    }

    public function test_weeks_start_on_monday_and_are_filled() {
        $w = TimeGrowNexusKpis::fill_weeks( [ '2026-10-12' => 40, '2026-09-28' => 12.25 ], '2026-10-18', 4 ); // a Sunday
        $this->assertSame( [ '2026-09-21' => 0.0, '2026-09-28' => 12.25, '2026-10-05' => 0.0, '2026-10-12' => 40.0 ], $w );
    }

    public function test_percent_and_labels() {
        $this->assertSame( 72.5, TimeGrowNexusKpis::percent( 29, 40 ) );
        $this->assertSame( 0.0, TimeGrowNexusKpis::percent( 5, 0 ) );
        $this->assertSame( '7.5 h', TimeGrowNexusKpis::hours_label( 7.5 ) );
        $this->assertSame( '12 h', TimeGrowNexusKpis::hours_label( 12 ) );
        $this->assertSame( '0.25 h', TimeGrowNexusKpis::hours_label( 0.25 ) );
    }
}
