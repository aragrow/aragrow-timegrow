<?php
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/modules/core/includes/controllers/reports/TimeGrowReportsTeamHoursTrait.php';
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/controllers/reports/TimeGrowReportsUnbilledTrait.php';

class TeamAndUnbilledReportsHost {
    use TimeGrowReportsTeamHoursTrait, TimeGrowReportsUnbilledTrait;
}

/** Team Hours Summary and Unbilled Time: totals, percentages, order and age. */
class TeamAndUnbilledReportsTest extends TestCase {

    private function o( array $a ) { return (object) $a; }

    public function test_team_totals_and_billable_percent() {
        $rows = [
            $this->o( [ 'total_hours' => 30, 'billable_hours' => 24, 'non_billable_hours' => 6, 'entry_count' => 12 ] ),
            $this->o( [ 'total_hours' => 10, 'billable_hours' => 5, 'non_billable_hours' => 5, 'entry_count' => 4 ] ),
            $this->o( [ 'total_hours' => 0, 'billable_hours' => 0, 'non_billable_hours' => 0, 'entry_count' => 0 ] ),
        ];
        $t = TeamAndUnbilledReportsHost::team_hours_totals( $rows );
        $this->assertSame( [ 40.0, 29.0, 11.0, 16, 2 ], [ $t['total'], $t['billable'], $t['non_billable'], $t['entries'], $t['active'] ], 'a member with no time is listed but not active' );
        $this->assertSame( 72.5, TeamAndUnbilledReportsHost::billable_percent( 29, 40 ) );
        $this->assertSame( 0.0, TeamAndUnbilledReportsHost::billable_percent( 0, 0 ), 'no hours: 0%, not a division error' );
    }

    public function test_unbilled_is_sorted_by_client_then_largest_amount() {
        $rows = TeamAndUnbilledReportsHost::sort_unbilled( [
            $this->o( [ 'client_name' => 'Copperleaf', 'project_name' => 'Small', 'amount' => 100 ] ),
            $this->o( [ 'client_name' => 'Birchway', 'project_name' => 'B', 'amount' => 50 ] ),
            $this->o( [ 'client_name' => 'Copperleaf', 'project_name' => 'Big', 'amount' => 900 ] ),
        ] );
        $this->assertSame( [ 'B', 'Big', 'Small' ], array_column( array_map( 'get_object_vars', $rows ), 'project_name' ) );
    }

    public function test_unbilled_totals_subtotals_and_oldest() {
        $t = TeamAndUnbilledReportsHost::unbilled_totals( [
            $this->o( [ 'client_id' => 31, 'hours' => 2.5, 'amount' => 250.10, 'entry_count' => 2, 'oldest_entry' => '2026-09-10' ] ),
            $this->o( [ 'client_id' => 31, 'hours' => 1, 'amount' => 75.20, 'entry_count' => 1, 'oldest_entry' => '2026-08-01 09:00:00' ] ),
            $this->o( [ 'client_id' => 40, 'hours' => 4, 'amount' => 400, 'entry_count' => 3, 'oldest_entry' => '2026-10-01' ] ),
        ] );
        $this->assertSame( 7.5, $t['hours'] );
        $this->assertSame( 725.3, $t['amount'] );
        $this->assertSame( 6, $t['entries'] );
        $this->assertSame( '2026-08-01', $t['oldest'], 'clock dates are cut to the day' );
        $this->assertEqualsWithDelta( 325.3, $t['clients'][31]['amount'], 0.001 );
        $this->assertSame( 4.0, $t['clients'][40]['hours'] );
    }

    public function test_days_waiting() {
        $this->assertSame( 78, TeamAndUnbilledReportsHost::days_waiting( '2026-08-01', '2026-10-18' ) );
        $this->assertSame( 0, TeamAndUnbilledReportsHost::days_waiting( '2026-10-18 08:00:00', '2026-10-18' ) );
        $this->assertNull( TeamAndUnbilledReportsHost::days_waiting( '', '2026-10-18' ) );
    }
}
