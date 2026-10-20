<?php
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'add_action' ) ) { function add_action( ...$a ) {} }
if ( ! function_exists( 'add_filter' ) ) { function add_filter( ...$a ) {} }
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/admin-menu.php';

/** The TimeGrow menu: grouped by Dashboard, Work, Setup and Settings, with headings on visible items. */
class AdminMenuTest extends TestCase {

    public function test_pages_are_matched_by_slug_whatever_the_menu_title() {
        foreach ( [ 'timegrow_page_timegrow-expenses-list', 'admin_page_timegrow-expenses-list', 'zeiterfassung_page_timegrow-expenses-list' ] as $hook ) {
            $this->assertTrue( TimeFlies_Admin_Menu::is_page( $hook, 'timegrow-expenses-list' ), $hook );
        }
        $this->assertFalse( TimeFlies_Admin_Menu::is_page( 'timegrow_page_timegrow-expenses-list-old', 'timegrow-expenses-list' ) );
        $this->assertFalse( TimeFlies_Admin_Menu::is_page( 'timegrow_page_timegrow-expense-add', 'timegrow-expenses' ) );
        $this->assertFalse( TimeFlies_Admin_Menu::is_page( 'toplevel_page_timegrow', '' ) );
    }

    private function item( $slug, $cap = 'owner' ) { return [ ucfirst( $slug ), $cap, $slug === '' ? 'timegrow' : 'timegrow-' . $slug, '' ]; }

    private function menu() {
        // The order WordPress builds today (by module load order)
        return array_map( [ $this, 'item' ], [ '', 'companies-list', 'clients-list', 'projects-list', 'expenses-list', 'expense-categories',
            'time-entries-list', 'team-member-list', 'reports-list', 'settings', 'nexus', 'nexus-reports' ] );
    }

    public function test_items_are_grouped_and_old_pages_left_out() {
        $out = TimeFlies_Admin_Menu::ordered( $this->menu(), function () { return true; } );
        $this->assertSame( [ 'timegrow-nexus', 'timegrow-time-entries-list', 'timegrow-expenses-list', 'timegrow-nexus-reports',
            'timegrow-clients-list', 'timegrow-projects-list', 'timegrow-team-member-list', 'timegrow-companies-list', 'timegrow-expense-categories',
            'timegrow-settings' ], array_column( $out, 2 ), 'no placeholder page, no second Reports' );
        $this->assertSame( 'Dashboard', $out[0][0] );
        $this->assertSame( 'tg-menu-work', $out[1][4] );
        $this->assertSame( 'tg-menu-setup', $out[4][4] );
        $this->assertSame( 'tg-menu-end', $out[9][4], 'a divider above Settings' );
    }

    public function test_headings_go_on_the_first_item_the_person_can_see() {
        $menu = $this->menu();
        $menu[] = [ 'Other plugin', 'owner', 'acme-page', '' ];
        $menu[2][1] = 'hidden'; // clients-list: not for this person
        $out = TimeFlies_Admin_Menu::ordered( $menu, function ( $cap ) { return $cap !== 'hidden'; } );
        $this->assertArrayNotHasKey( 4, $out[4], 'clients (hidden) carries no heading' );
        $this->assertSame( 'tg-menu-setup', $out[5][4], 'projects does' );
        $this->assertSame( [ 'acme-page', 'timegrow-settings' ], array_slice( array_column( $out, 2 ), -2 ), 'other items go before Settings' );
    }
}
