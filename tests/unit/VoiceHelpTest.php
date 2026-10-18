<?php
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/modules/nexus/includes/TimeGrowNexusHelp.php';
if ( ! function_exists( 'add_action' ) ) { function add_action( ...$a ) {} }
if ( ! function_exists( 'add_filter' ) ) { function add_filter( ...$a ) {} }
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/admin-menu.php';

/** The Voice AI's help library: every topic reads, the right one is found, and admin help stays with admins. */
class VoiceHelpTest extends TestCase {

    private function topics() {
        $out = [];
        foreach ( glob( TimeGrowNexusHelp::dir() . '/*.md' ) as $file ) {
            $t = TimeGrowNexusHelp::parse( basename( $file, '.md' ), file_get_contents( $file ) );
            $this->assertNotNull( $t, basename( $file ) . ' has a header with a title' );
            $out[] = $t;
        }
        return $out;
    }

    public function test_every_help_file_reads() {
        $topics = $this->topics();
        $this->assertGreaterThanOrEqual( 30, count( $topics ) );
        foreach ( $topics as $t ) {
            $this->assertNotSame( '', $t['body'], $t['slug'] );
            $this->assertNotEmpty( $t['keywords'], $t['slug'] . ' has keywords' );
        }
    }

    public function test_the_right_topic_is_found() {
        $topics = $this->topics();
        $best = function ( $q, $admin = true ) use ( $topics ) {
            $r = TimeGrowNexusHelp::search( $q, $topics, $admin );
            return $r ? $r[0]['slug'] : null;
        };
        $this->assertSame( 'upload-receipt', $best( 'how do I upload a receipt?' ) );
        $this->assertSame( 'punch-in-out', $best( 'how do I punch in?' ) );
        $this->assertSame( 'unbilled-time', $best( 'what is the unbilled report?' ) );
        $this->assertSame( 'ai-setup', $best( 'where do I put my OpenAI API key' ) );
        $this->assertSame( 'deactivate-time-entry', $best( 'how do I deactivate a time entry' ) );
        $this->assertSame( 'manual-time-entry', $best( 'I forgot to log time yesterday, how do I add it?' ) );
        $this->assertNull( $best( 'what is the weather in Paris' ), 'nothing is made up' );
    }

    public function test_team_members_never_get_admin_help() {
        $topics = $this->topics();
        foreach ( [ 'how do I deactivate a time entry', 'how do I set up the AI key', 'how do I bill time' ] as $q ) {
            foreach ( TimeGrowNexusHelp::search( $q, $topics, false ) as $t ) {
                $this->assertSame( 'everyone', $t['roles'], "$q → {$t['slug']}" );
            }
        }
    }

    public function test_every_timegrow_page_has_help() {
        $pages = [];
        foreach ( $this->topics() as $t ) {
            $pages[] = strtok( $t['page'], '&' );
        }
        $menu = array_merge( ...array_values( TimeFlies_Admin_Menu::GROUPS ) );
        $more = [ 'nexus-clock', 'nexus-manual', 'nexus-expenses', 'nexus-process-time', 'nexus-sync-payments', 'nexus-print-mail' ];
        foreach ( array_merge( $menu, $more ) as $slug ) {
            $this->assertContains( 'timegrow-' . $slug, $pages, "no help topic for timegrow-$slug" );
        }
    }

    public function test_words_and_stems() {
        $this->assertSame( [ 'upload', 'receipt' ], TimeGrowNexusHelp::words( 'How do I upload receipts?' ) );
        $this->assertSame( 'invoice', TimeGrowNexusHelp::stem( 'invoices' ) );
        $this->assertSame( 'bill', TimeGrowNexusHelp::stem( 'billing' ) );
    }

    public function test_what_can_you_do_comes_from_what_the_person_may_do() {
        $this->assertTrue( TimeGrowNexusHelp::asks_capabilities( 'What can you do?' ) );
        $this->assertFalse( TimeGrowNexusHelp::asks_capabilities( 'how do I upload a receipt' ) );
        $member = TimeGrowNexusHelp::capabilities( 'Sam Spade', [ 'hours', 'last_entry', 'help' ], [], false );
        $this->assertStringContainsString( "I'm Sam Spade", $member );
        $this->assertStringNotContainsString( 'punch', $member, 'no clock permission' );
        $this->assertStringNotContainsString( 'unbilled', $member );
        $owner = TimeGrowNexusHelp::capabilities( 'Sam Spade', [ 'hours', 'unbilled_hours', 'lookup' ], [ 'add_client' ], true );
        $this->assertStringContainsString( 'punch you in and out', $owner );
        $this->assertStringContainsString( 'add clients and projects', $owner );
        $this->assertStringContainsString( 'unbilled hours', $owner );
    }

    public function test_only_close_matches_are_kept() {
        $r = TimeGrowNexusHelp::search( 'what is the unbilled report', $this->topics(), true );
        $this->assertSame( 'unbilled-time', $r[0]['slug'] );
        foreach ( $r as $t ) {
            $this->assertGreaterThanOrEqual( TimeGrowNexusHelp::score( $r[0], TimeGrowNexusHelp::words( 'what is the unbilled report' ) ) / 2,
                TimeGrowNexusHelp::score( $t, TimeGrowNexusHelp::words( 'what is the unbilled report' ) ), $t['slug'] );
        }
    }
}
