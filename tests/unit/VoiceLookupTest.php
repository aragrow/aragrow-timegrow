<?php
use PHPUnit\Framework\TestCase;

defined( 'DAY_IN_SECONDS' ) || define( 'DAY_IN_SECONDS', 86400 );
if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
}
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowProjectPicker.php';
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowAI.php';
require_once dirname( __DIR__, 2 ) . '/modules/nexus/includes/TimeGrowNexusVoice.php';
require_once dirname( __DIR__, 2 ) . '/modules/nexus/includes/TimeGrowNexusVoiceFormat.php';
require_once dirname( __DIR__, 2 ) . '/modules/nexus/includes/TimeGrowNexusVoiceNames.php';
require_once dirname( __DIR__, 2 ) . '/modules/nexus/includes/TimeGrowNexusVoicePrompt.php';
require_once dirname( __DIR__, 2 ) . '/modules/nexus/includes/TimeGrowNexusVoiceAnswers.php';
require_once dirname( __DIR__, 2 ) . '/modules/nexus/includes/TimeGrowNexusAssistant.php';
require_once dirname( __DIR__, 2 ) . '/modules/nexus/includes/TimeGrowNexusLookup.php';

/**
 * The flexible lookup: what the AI describes is checked, and team members only ever get their own data.
 */
class VoiceLookupTest extends TestCase {

    const TODAY = '2026-10-17';
    private $ok = [ 'clients' => [ 23, 40 ], 'projects' => [ 14, 20 ], 'members' => [ 13, 14 ], 'categories' => [ 13, 25 ], 'statuses' => [ 'invoice_paid', 'invoice_sent' ], 'me' => 13 ];

    private function spec( $raw, $owner = true, ?array $ok = null ) {
        return TimeGrowNexusLookup::spec( $raw, $owner, $ok ?? $this->ok, self::TODAY );
    }

    public function test_an_admin_lookup_is_kept_as_described() {
        $s = $this->spec( [ 'entity' => 'time_entries', 'metric' => 'hours', 'group_by' => 'person', 'filters' => [ 'client_id' => 40, 'member_id' => 14, 'billable' => true ], 'from' => '2026-08-01', 'to' => '2026-09-30' ] );
        $this->assertSame( 'person', $s['group_by'] );
        $this->assertSame( [ 'client_id' => 40, 'member_id' => 14, 'billable' => 1 ], $s['filters'] );
        $this->assertSame( [ '2026-08-01', '2026-09-30' ], [ $s['from'], $s['to'] ] );
    }

    public function test_team_members_only_get_their_own_time() {
        $s = $this->spec( [ 'entity' => 'time_entries', 'metric' => 'hours', 'group_by' => 'person', 'filters' => [ 'member_id' => 14 ] ], false );
        $this->assertSame( 13, $s['filters']['member_id'], 'always their own, whatever was asked' );
        $this->assertSame( 'none', $s['group_by'], 'no breakdown by person' );
        $this->assertTrue( $s['own_only'], 'they are told they see their own time' );
        $this->assertFalse( $this->spec( [ 'entity' => 'time_entries', 'filters' => [ 'member_id' => 13 ] ], false )['own_only'], 'asking about themselves needs no notice' );
        $this->assertSame( [ 'denied' => true ], $this->spec( [ 'entity' => 'invoices' ], false ) );
        $this->assertSame( [ 'denied' => true ], $this->spec( [ 'entity' => 'expenses' ], false ) );
        $this->assertSame( [ 'denied' => true ], $this->spec( [ 'entity' => 'time_entries' ], false, [ 'me' => 0 ] + $this->ok ), 'no team member record: no time of their own' );
    }

    public function test_anything_not_on_the_list_is_dropped() {
        $s = $this->spec( [ 'entity' => 'invoices', 'metric' => 'drop_table', 'group_by' => 'password', 'filters' => [ 'client_id' => 999, 'status' => 'trash', 'email' => 'x@y.com', 'min_amount' => -5 ] ] );
        $this->assertSame( 'amount', $s['metric'] );
        $this->assertSame( 'none', $s['group_by'] );
        $this->assertSame( [], $s['filters'] );
        $this->assertNull( $this->spec( [ 'entity' => 'users' ] ) );
        $this->assertNull( $this->spec( 'not an object' ) );
    }

    public function test_dates() {
        $this->assertSame( [ '2026-01-01', '2026-10-17' ], [ $this->spec( [ 'entity' => 'expenses' ] )['from'], $this->spec( [ 'entity' => 'expenses' ] )['to'] ], 'expenses: this year by default' );
        $s = $this->spec( [ 'entity' => 'time_entries', 'from' => '2026-11-01', 'to' => '2026-12-01' ] );
        $this->assertSame( '2026-07-17', $s['from'], 'future dates are not used; the last 3 months instead' );
        $this->assertSame( 25, $this->spec( [ 'entity' => 'invoices', 'limit' => 500 ] )['limit'] );
        $this->assertSame( 'unpaid', $this->spec( [ 'entity' => 'invoices', 'filters' => [ 'status' => 'unpaid' ] ] )['filters']['status'] );
    }

    public function test_time_and_invoices_go_back_three_months_at_most() {
        $s = $this->spec( [ 'entity' => 'invoices', 'period' => 'this_year' ] );
        $this->assertSame( [ '2026-07-17', '2026-10-17', true ], [ $s['from'], $s['to'], $s['clipped'] ], 'this year is shortened, and the answer says so' );
        $s = $this->spec( [ 'entity' => 'time_entries', 'period' => 'this_month' ] );
        $this->assertSame( [ '2026-10-01', false ], [ $s['from'], $s['clipped'] ], 'periods inside the 3 months stay' );
        $this->assertSame( '2026-07-17', $this->spec( [ 'entity' => 'invoices' ] )['from'], 'no period: the last 3 months' );
        $this->assertSame( '2026-07-17', TimeGrowNexusVoiceAnswers::data_floor( '2026-10-17' ) );
    }

    public function test_a_request_entirely_older_than_three_months_is_not_run() {
        $s = $this->spec( [ 'entity' => 'invoices', 'metric' => 'amount', 'from' => '2025-01-01', 'to' => '2025-12-31' ] );
        $this->assertTrue( $s['out_of_window'], 'last year: the person is told and pointed to the reports' );
        $this->assertFalse( $this->spec( [ 'entity' => 'invoices', 'period' => 'this_year' ] )['out_of_window'], 'this year: answered for the last 3 months' );
        $this->assertSame( [ '2025-01-01', '2025-12-31' ], TimeGrowNexusVoiceFormat::period_range( 'last_year', self::TODAY ) );
        $this->assertContains( 'last_year', TimeGrowNexusAssistant::PERIODS );
    }
}
