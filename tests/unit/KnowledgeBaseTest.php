<?php
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/modules/nexus/includes/TimeGrowNexusHelp.php';
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/TimeGrowHelpTopicsPage.php';
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $s ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $s ) ) ); }
}

/** Settings > Knowledge Base: edits and new topics over the built-in ones, and what the form accepts. */
class KnowledgeBaseTest extends TestCase {

    private function builtin() {
        return [
            [ 'slug' => 'punch-in-out', 'title' => 'Punch in and out', 'roles' => 'everyone', 'page' => 'timegrow-nexus-clock', 'keywords' => [ 'punch' ], 'body' => 'Built-in text.', 'source' => 'built-in', 'hidden' => false ],
            [ 'slug' => 'clients', 'title' => 'Clients', 'roles' => 'admin', 'page' => 'timegrow-clients-list', 'keywords' => [ 'client' ], 'body' => 'Clients text.', 'source' => 'built-in', 'hidden' => false ],
        ];
    }

    public function test_edits_hidden_and_added_topics() {
        $topics = TimeGrowNexusHelp::merge( $this->builtin(), [
            'punch-in-out'     => [ 'title' => 'Clock in', 'roles' => 'everyone', 'page' => 'timegrow-nexus-clock', 'keywords' => 'clock, punch', 'body' => 'Our way to clock in.' ],
            'clients'          => [ 'hidden' => true ],
            'custom-vacation-1' => [ 'title' => 'Vacation days', 'roles' => 'admin', 'page' => '', 'keywords' => 'vacation, pto', 'body' => 'Ask Maria.' ],
            'not-a-topic'      => [ 'title' => 'x', 'body' => 'ignored: not built-in and not custom' ],
        ] );
        $by = array_column( $topics, null, 'slug' );
        $this->assertSame( [ 'punch-in-out', 'clients', 'custom-vacation-1' ], array_keys( $by ) );
        $this->assertSame( [ 'Clock in', 'edited', [ 'clock', 'punch' ], 'Our way to clock in.' ], [ $by['punch-in-out']['title'], $by['punch-in-out']['source'], $by['punch-in-out']['keywords'], $by['punch-in-out']['body'] ] );
        $this->assertTrue( $by['clients']['hidden'] );
        $this->assertSame( 'Clients text.', $by['clients']['body'], 'hiding keeps the built-in text' );
        $this->assertSame( [ 'custom', 'admin' ], [ $by['custom-vacation-1']['source'], $by['custom-vacation-1']['roles'] ] );
        $this->assertNotEmpty( TimeGrowNexusHelp::search( 'how many vacation days', $topics, true ) );
        $this->assertEmpty( TimeGrowNexusHelp::search( 'how many vacation days', $topics, false ), 'an admin-only custom topic' );
    }

    public function test_the_form_is_checked() {
        $this->assertSame( 'missing', TimeGrowHelpTopicsPage::validate( [ 'title' => ' ', 'body' => 'x' ] ) );
        $this->assertSame( 'too_long', TimeGrowHelpTopicsPage::validate( [ 'title' => 'T', 'body' => str_repeat( 'a', 5001 ) ] ) );
        $ok = TimeGrowHelpTopicsPage::validate( [ 'title' => 'Vacation', 'body' => "Line one.\r\nLine two.", 'roles' => 'admin',
            'page' => 'timegrow-settings&tab=ai"><script>', 'keywords' => ' pto ,, vacation ,' ] );
        $this->assertSame( [ 'Vacation', 'admin', 'timegrow-settings&tab=aiscript', 'pto, vacation', "Line one.\nLine two." ],
            [ $ok['title'], $ok['roles'], $ok['page'], $ok['keywords'], $ok['body'] ] );
        $this->assertSame( 'everyone', TimeGrowHelpTopicsPage::validate( [ 'title' => 'T', 'body' => 'B', 'roles' => 'root' ] )['roles'] );
    }
}
