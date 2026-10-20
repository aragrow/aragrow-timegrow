<?php
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'add_action' ) ) { function add_action( ...$a ) {} }
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/TimeGrowUserGuidePage.php';

/** TimeGrow > User Guide: topics in sections, by role. */
class UserGuideTest extends TestCase {

    private function t( $slug, $roles = 'everyone', $title = null ) {
        return [ 'slug' => $slug, 'title' => $title ?: ucfirst( $slug ), 'roles' => $roles, 'page' => '', 'keywords' => [], 'body' => 'x' ];
    }

    public function test_sections_and_roles() {
        $topics = [ $this->t( 'punch-in-out' ), $this->t( 'what-is-timegrow' ), $this->t( 'clients', 'admin' ), $this->t( 'custom-vacation-1', 'everyone', 'Vacation days' ) ];
        $admin  = TimeGrowUserGuidePage::grouped( $topics, true );
        $this->assertSame( [ 'Getting started', 'Recording time', 'Clients and projects', 'More' ], array_keys( $admin ), 'sections in order; added topics under More' );
        $member = TimeGrowUserGuidePage::grouped( $topics, false );
        $this->assertSame( [ 'Getting started', 'Recording time', 'More' ], array_keys( $member ), 'no administrator topics for team members' );
    }

    public function test_every_built_in_topic_has_a_section() {
        require_once dirname( __DIR__, 2 ) . '/modules/nexus/includes/TimeGrowNexusHelp.php';
        $listed = array_merge( ...array_values( TimeGrowUserGuidePage::SECTIONS ) );
        foreach ( glob( TimeGrowNexusHelp::dir() . '/*.md' ) as $f ) {
            $slug = basename( $f, '.md' );
            $this->assertContains( $slug, $listed, "$slug is not in a User Guide section" );
        }
    }
}
