<?php
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
}
if ( ! function_exists( 'size_format' ) ) {
    function size_format( $bytes ) { return round( $bytes / 1024 ) . ' KB'; }
}
if ( ! defined( 'UPLOAD_ERR_OK' ) ) { define( 'UPLOAD_ERR_OK', 0 ); }
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowExpenseRules.php';

/** Nexus Record Expenses: a team member records their own expenses only; an administrator anyone's. */
class ExpenseRulesTest extends TestCase {

    private function ctx( $is_admin ) {
        return [
            'is_admin'     => $is_admin,
            'own_member'   => 14,
            'members'      => $is_admin ? [ 13 => 'David', 14 => 'Ana', 15 => 'Ben' ] : [],
            'projects'     => [ 5 => [ 'name' => 'SEO Audit', 'client_id' => 29 ] ] + ( $is_admin ? [ 9 => [ 'name' => 'Books', 'client_id' => 31 ] ] : [] ),
            'clients'      => [ 29 => 'Copperleaf' ] + ( $is_admin ? [ 31 => 'Birchway' ] : [] ),
            'category_ids' => [ 3, 4 ],
        ];
    }

    private function input( array $over = [] ) {
        return $over + [ 'expense_name' => 'Staples', 'expense_date' => '2026-10-19', 'amount' => '25.5', 'expense_category_id' => '3',
                         'expense_payment_method' => 'personal_card', 'assigned_to' => 'general', 'expense_description' => '' ];
    }

    public function test_a_team_member_records_only_their_own_expenses() {
        // Another member posted: ignored, always their own
        [ $data, $problem ] = TimeGrowExpenseRules::check( $this->input( [ 'member_id' => 15 ] ), $this->ctx( false ), '2026-10-20' );
        $this->assertSame( '', $problem );
        $this->assertSame( 14, $data['member_id'] );
        $this->assertSame( 'general', $data['assigned_to'] );
        $this->assertNull( $data['assigned_to_id'] );
        $this->assertSame( 'Staples', $data['expense_description'], 'the description column needs text: the name stands in' );
        // No team member record: refused
        $ctx = $this->ctx( false );
        $ctx['own_member'] = 0;
        $this->assertStringContainsString( 'not set up as a team member', TimeGrowExpenseRules::check( $this->input(), $ctx, '2026-10-20' )[1] );
    }

    public function test_a_team_member_assigns_only_their_projects_and_clients() {
        [ $data ] = TimeGrowExpenseRules::check( $this->input( [ 'assigned_to' => 'project', 'project_id' => 5 ] ), $this->ctx( false ), '2026-10-20' );
        $this->assertSame( [ 'project', 5 ], [ $data['assigned_to'], $data['assigned_to_id'] ] );
        [ $data ] = TimeGrowExpenseRules::check( $this->input( [ 'assigned_to' => 'client', 'client_id' => 29 ] ), $this->ctx( false ), '2026-10-20' );
        $this->assertSame( [ 'client', 29 ], [ $data['assigned_to'], $data['assigned_to_id'] ] );
        // Someone else's project or client: refused, never swapped for another
        $this->assertSame( 'Pick one of your projects.', TimeGrowExpenseRules::check( $this->input( [ 'assigned_to' => 'project', 'project_id' => 9 ] ), $this->ctx( false ), '2026-10-20' )[1] );
        $this->assertSame( 'Pick one of your clients.', TimeGrowExpenseRules::check( $this->input( [ 'assigned_to' => 'client', 'client_id' => 31 ] ), $this->ctx( false ), '2026-10-20' )[1] );
        $this->assertNull( TimeGrowExpenseRules::check( $this->input( [ 'assigned_to' => 'project', 'project_id' => 9 ] ), $this->ctx( false ), '2026-10-20' )[0] );
    }

    public function test_an_administrator_records_for_anyone_or_the_company() {
        [ $data, $problem ] = TimeGrowExpenseRules::check( $this->input( [ 'member_id' => 15, 'assigned_to' => 'project', 'project_id' => 9 ] ), $this->ctx( true ), '2026-10-20' );
        $this->assertSame( '', $problem );
        $this->assertSame( [ 15, 'project', 9 ], [ $data['member_id'], $data['assigned_to'], $data['assigned_to_id'] ] );
        [ $data ] = TimeGrowExpenseRules::check( $this->input( [ 'member_id' => 0, 'assigned_to' => 'client', 'client_id' => 31 ] ), $this->ctx( true ), '2026-10-20' );
        $this->assertNull( $data['member_id'], 'no member = a company expense' );
        $this->assertSame( 'Pick a team member from the list.', TimeGrowExpenseRules::check( $this->input( [ 'member_id' => 99 ] ), $this->ctx( true ), '2026-10-20' )[1] );
    }

    public function test_the_details_are_checked() {
        $ctx = $this->ctx( false );
        $cases = [
            [ [ 'expense_name' => ' ' ], 'Give the expense a name' ],
            [ [ 'expense_date' => '2026-10-21' ], 'future' ],
            [ [ 'expense_date' => '2026-02-30' ], 'Enter the date' ],
            [ [ 'amount' => '0' ], 'above 0' ],
            [ [ 'amount' => 'abc' ], 'above 0' ],
            [ [ 'expense_category_id' => '1' ], 'Pick a category' ], // a heading, not a category
            [ [ 'expense_payment_method' => 'crypto' ], 'how it was paid' ],
            [ [ 'assigned_to' => 'everyone' ], 'who the expense is for' ],
        ];
        foreach ( $cases as [ $over, $want ] ) {
            $this->assertStringContainsString( $want, TimeGrowExpenseRules::check( $this->input( $over ), $ctx, '2026-10-20' )[1], json_encode( $over ) );
        }
        [ $data ] = TimeGrowExpenseRules::check( $this->input( [ 'expense_name' => '<b>Staples</b>  Inc', 'amount' => '25.555' ] ), $ctx, '2026-10-20' );
        $this->assertSame( [ 'Staples Inc', 25.56 ], [ $data['expense_name'], $data['amount'] ] );
    }

    public function test_categories_expenses_use_are_the_leaves() {
        $child = function ( $id ) { return (object) [ 'ID' => $id, 'children' => [] ]; };
        $tree  = [ (object) [ 'ID' => 1, 'children' => [ $child( 3 ), $child( 4 ) ] ], (object) [ 'ID' => 7, 'children' => [] ] ];
        $this->assertSame( [ 3, 4, 7 ], TimeGrowExpenseRules::leaf_ids( $tree ) );
    }

    public function test_receipt_files() {
        $files = function ( array $list ) {
            $out = [ 'name' => [], 'type' => [], 'tmp_name' => [], 'error' => [], 'size' => [] ];
            foreach ( $list as [ $name, $size, $error ] ) {
                $out['name'][] = $name; $out['type'][] = ''; $out['tmp_name'][] = '/tmp/x'; $out['error'][] = $error; $out['size'][] = $size;
            }
            return $out;
        };
        [ $list, $problem ] = TimeGrowExpenseRules::receipt_files( $files( [ [ 'a.jpg', 1000, 0 ], [ '', 0, 4 ], [ 'b.PDF', 2000, 0 ] ] ), 524288 );
        $this->assertSame( '', $problem );
        $this->assertSame( [ 'a.jpg', 'b.PDF' ], array_column( $list, 'name' ), 'empty slots (no file) are skipped' );
        $this->assertStringContainsString( 'not a JPG', TimeGrowExpenseRules::receipt_files( $files( [ [ 'x.exe', 10, 0 ] ] ), 524288 )[1] );
        $this->assertStringContainsString( 'larger than', TimeGrowExpenseRules::receipt_files( $files( [ [ 'big.png', 600000, 0 ] ] ), 524288 )[1] );
        $this->assertStringContainsString( 'at most 5', TimeGrowExpenseRules::receipt_files( $files( array_fill( 0, 6, [ 'r.jpg', 10, 0 ] ) ), 524288 )[1] );
        $this->assertSame( [ [], '' ], TimeGrowExpenseRules::receipt_files( null, 524288 ) );
    }
}
