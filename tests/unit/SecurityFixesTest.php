<?php
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowCsv.php';
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowDateRanges.php';
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowAIPrivacy.php';
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/models/TimeGrowExpenseReceiptModel.php';
if ( ! function_exists( 'wp_generate_password' ) ) {
    function wp_generate_password( $length = 12, $special = true, $extra = false ) { return substr( bin2hex( random_bytes( $length ) ), 0, $length ); }
}

/** Fixes from the security review: CSV formulas, report dates, and names a team member may see. */
class SecurityFixesTest extends TestCase {

    public function test_csv_cells_cannot_run_formulas() {
        $this->assertSame( "'=HYPERLINK(\"https://evil\",\"x\")", TimeGrowCsv::cell( '=HYPERLINK("https://evil","x")' ) );
        $this->assertSame( "'+cmd", TimeGrowCsv::cell( '+cmd' ) );
        $this->assertSame( "'@SUM(A1)", TimeGrowCsv::cell( '@SUM(A1)' ) );
        $this->assertSame( "'-2+3", TimeGrowCsv::cell( '-2+3' ), 'not a number: neutralized' );
        $this->assertSame( '-5.00', TimeGrowCsv::cell( '-5.00' ), 'a negative amount stays a number' );
        $this->assertSame( 'Copperleaf Studio', TimeGrowCsv::cell( 'Copperleaf Studio' ) );
        $this->assertSame( 12.5, TimeGrowCsv::cell( 12.5 ) );
        $out = fopen( 'php://memory', 'w+' );
        TimeGrowCsv::put( $out, [ '=1+1', 'ok', '-3' ] );
        rewind( $out );
        $this->assertSame( "'=1+1,ok,-3", trim( stream_get_contents( $out ) ) );
    }

    public function test_report_dates_must_be_real_dates() {
        $this->assertSame( '2026-10-19', TimeGrowDateRanges::valid_date( '2026-10-19' ) );
        $this->assertSame( '', TimeGrowDateRanges::valid_date( '9999-12-31" onfocus=alert(1) autofocus x="' ) );
        $this->assertSame( '', TimeGrowDateRanges::valid_date( '2026-02-30' ) );
        $this->assertSame( '', TimeGrowDateRanges::valid_date( [ 'x' ] ) );
    }

    public function test_a_team_member_only_sees_their_own_clients_names() {
        $layer = new TimeGrowAIPrivacy( [ 23 => 'Copperleaf Studio', 24 => 'Copperleaf Legal', 31 => 'Birchway' ], [ 14 => 'Maria Gomez', 15 => 'Eva Larsen' ], [], 'Aragrow, LLC' );
        $layer->restrict( [ '[Client 23]', '[Person 14]' ] );
        $masked = $layer->mask( 'Copperleaf' );
        $this->assertSame( [], $layer->ambiguous( $masked ), 'only one of the two Copperleaf clients is theirs: nothing to ask, no other name shown' );
        $this->assertSame( 'Copperleaf Studio and another client', $layer->unmask( '[Client 23] and [Client 24]' ) );
        $this->assertSame( 'Maria Gomez worked with another person', $layer->unmask( '[Person 14] worked with [Person 15]' ) );
        $this->assertSame( 'Aragrow, LLC', $layer->unmask( '[Your company]' ) );
        $this->assertFalse( $layer->leaks( $layer->mask( 'Birchway and Eva Larsen' ) ), 'every name is still hidden from the AI' );

        $admin = new TimeGrowAIPrivacy( [ 23 => 'Copperleaf Studio', 24 => 'Copperleaf Legal' ] );
        $this->assertSame( [ [ 'Copperleaf Studio', 'Copperleaf Legal' ] ], $admin->ambiguous( $admin->mask( 'Copperleaf' ) ), 'administrators see every match' );
    }

    public function test_receipts_go_to_a_private_folder_with_random_names() {
        $dirs = TimeGrowExpenseReceiptModel::private_dir( [ 'basedir' => '/x/uploads', 'baseurl' => 'https://site/wp-content/uploads', 'subdir' => '/2026/10',
            'path' => '/x/uploads/2026/10', 'url' => 'https://site/wp-content/uploads/2026/10' ] );
        $this->assertSame( [ '/timegrow-receipts/2026/10', '/x/uploads/timegrow-receipts/2026/10', 'https://site/wp-content/uploads/timegrow-receipts/2026/10' ],
            [ $dirs['subdir'], $dirs['path'], $dirs['url'] ] );
        $a = TimeGrowExpenseReceiptModel::random_name( '/x', 'My Receipt.JPG', '.JPG' );
        $b = TimeGrowExpenseReceiptModel::random_name( '/x', 'My Receipt.JPG', '.JPG' );
        $this->assertMatchesRegularExpression( '/^[a-z0-9]{32}\.jpg$/', $a, 'random, keeps the checked extension' );
        $this->assertNotSame( $a, $b );
        $this->assertStringNotContainsString( 'receipt', $a, 'not the original name' );
    }
}
