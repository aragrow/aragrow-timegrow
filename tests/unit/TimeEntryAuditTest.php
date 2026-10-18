<?php
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowTimeEntryAudit.php';

/** The time entry audit trail: what counts as a change, and how it reads. */
class TimeEntryAuditTest extends TestCase {

    public function test_only_real_changes_are_recorded() {
        $before = [ 'ID' => '7', 'hours' => '2.00', 'billable' => '1', 'description' => 'Audit', 'clock_out_date' => null, 'updated_at' => '2026-10-01 10:00:00' ];
        $after  = [ 'ID' => '7', 'hours' => '2', 'billable' => '0', 'description' => 'Audit', 'clock_out_date' => '', 'updated_at' => '2026-10-18 10:00:00' ];
        $this->assertSame( [ 'billable' => [ '1', '0' ] ], TimeGrowTimeEntryAudit::diff( $before, $after ),
            '"2.00" = "2", null = "", and updated_at is not a change' );
        $this->assertSame( [], TimeGrowTimeEntryAudit::diff( $before, [ 'hours' => '2' ] ), 'a field not written is not a change' );
    }

    public function test_clocking_out_is_its_own_action() {
        $open = [ 'clock_in_date' => '2026-10-18 09:00:00', 'clock_out_date' => null ];
        $this->assertSame( 'clocked_out', TimeGrowTimeEntryAudit::update_action( $open, [ 'clock_out_date' => [ null, '2026-10-18 17:00:00' ] ] ) );
        $closed = [ 'clock_in_date' => '2026-10-18 09:00:00', 'clock_out_date' => '2026-10-18 17:00:00' ];
        $this->assertSame( 'updated', TimeGrowTimeEntryAudit::update_action( $closed, [ 'clock_out_date' => [ '2026-10-18 17:00:00', '2026-10-18 17:30:00' ] ] ), 'fixing a clock out is an edit' );
        $this->assertSame( 'updated', TimeGrowTimeEntryAudit::update_action( $open, [ 'hours' => [ '1', '2' ] ] ) );
    }

    public function test_changes_read_plainly() {
        $this->assertSame( 'hours 2 → 3.5; billable yes → no; project 14 → 20',
            TimeGrowTimeEntryAudit::describe( [ 'hours' => [ '2.00', '3.50' ], 'billable' => [ '1', '0' ], 'project_id' => [ '14', '20' ] ] ) );
        $this->assertSame( 'description (empty) → Keyword research', TimeGrowTimeEntryAudit::describe( [ 'description' => [ null, 'Keyword research' ] ] ) );
        $long = str_repeat( 'a', 80 );
        $this->assertSame( 58, mb_strlen( explode( ' → ', TimeGrowTimeEntryAudit::describe( [ 'description' => [ '', $long ] ] ) )[1] ), 'long text is shortened' );
    }

    public function test_actions() {
        $this->assertSame( [ 'created', 'updated', 'clocked_out', 'billed', 'unbilled', 'rebilled', 'deleted' ], TimeGrowTimeEntryAudit::ACTIONS );
    }
}
