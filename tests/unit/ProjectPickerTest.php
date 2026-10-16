<?php
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowProjectPicker.php';

/**
 * Projects grouped by client for the time entry pickers.
 */
class ProjectPickerTest extends TestCase {

    private function project( $id, $name, $client_id, $client_name, $status = 1 ) {
        return (object) [ 'ID' => $id, 'name' => $name, 'client_id' => $client_id, 'client_name' => $client_name, 'status' => $status ];
    }

    public function test_same_project_name_stays_apart_under_each_client() {
        $groups = TimeGrowProjectPicker::group_by_client( [
            $this->project( 1, 'Website Redesign', 7, 'Copperleaf Studio' ),
            $this->project( 2, 'Website Redesign', 8, 'Aragrow LLC' ),
        ] );

        $this->assertSame( [ 8, 7 ], array_keys( $groups ) ); // sorted by client name
        $this->assertSame( 'Aragrow LLC', $groups[8]['name'] );
        $this->assertSame( 2, $groups[8]['projects'][0]->ID );
        $this->assertSame( 1, $groups[7]['projects'][0]->ID );
    }

    public function test_projects_sorted_by_name_within_a_client() {
        $groups = TimeGrowProjectPicker::group_by_client( [
            $this->project( 1, 'Website Redesign', 7, 'Copperleaf' ),
            $this->project( 2, 'SEO Audit', 7, 'Copperleaf' ),
        ] );

        $this->assertSame( [ 'SEO Audit', 'Website Redesign' ], array_map( function ( $p ) { return $p->name; }, $groups[7]['projects'] ) );
    }

    public function test_inactive_projects_are_left_out() {
        $groups = TimeGrowProjectPicker::group_by_client( [
            $this->project( 1, 'Active', 7, 'Copperleaf' ),
            $this->project( 2, 'Completed', 7, 'Copperleaf', 8 ),
            $this->project( 3, 'Cancelled', 9, 'Oakline', 9 ),
        ] );

        $this->assertSame( [ 7 ], array_keys( $groups ) );
        $this->assertCount( 1, $groups[7]['projects'] );
    }

    public function test_inactive_project_kept_when_the_entry_uses_it() {
        $groups = TimeGrowProjectPicker::group_by_client( [
            $this->project( 1, 'Active', 7, 'Copperleaf' ),
            $this->project( 2, 'Completed', 7, 'Copperleaf', 8 ),
        ], 2 );

        $this->assertCount( 2, $groups[7]['projects'] );
    }

    public function test_project_without_a_client_goes_under_no_client() {
        $groups = TimeGrowProjectPicker::group_by_client( [ $this->project( 1, 'Internal', 0, '' ) ] );

        $this->assertSame( 'No client', $groups[0]['name'] );
    }
}
