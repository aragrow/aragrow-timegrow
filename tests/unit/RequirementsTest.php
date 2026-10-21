<?php
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowRequirements.php';

/**
 * The move of TimeGrow's base capabilities to the timegrow_ names.
 */
class RequirementsTest extends TestCase {

    public function test_old_capabilities_move_to_the_new_names() {
        $plan = TimeGrowRequirements::caps_to_rename( [ 'read' => true, 'timeflies_owner' => true, 'timeflies_admin' => true, 'timeflies_team_member' => true ] );

        $this->assertSame( [ 'timegrow_owner', 'timegrow_admin', 'timegrow_team_member' ], $plan['add'] );
        $this->assertSame( [ 'timeflies_owner', 'timeflies_admin', 'timeflies_team_member' ], $plan['remove'] );
    }

    public function test_a_denied_old_capability_is_removed_without_granting_the_new_one() {
        $plan = TimeGrowRequirements::caps_to_rename( [ 'timeflies_owner' => false ] );
        $this->assertSame( [], $plan['add'] );
        $this->assertSame( [ 'timeflies_owner' ], $plan['remove'] );
    }

    public function test_nothing_to_do_without_old_names() {
        $this->assertSame( [ 'add' => [], 'remove' => [] ], TimeGrowRequirements::caps_to_rename( [ 'read' => true, 'timegrow_owner' => true ] ) );
        // The new one already there: only the old one goes
        $this->assertSame( [ 'add' => [], 'remove' => [ 'timeflies_owner' ] ], TimeGrowRequirements::caps_to_rename( [ 'timeflies_owner' => true, 'timegrow_owner' => true ] ) );
    }
}
