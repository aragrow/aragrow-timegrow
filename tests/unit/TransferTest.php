<?php
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowTransfer.php';
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/TimeGrowHelpTopicsPage.php';
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $s ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $s ) ) ); }
}

/** Moving the knowledge base and AI prompts between sites: what a file would change, checked before saving. */
class TransferTest extends TestCase {

    public function test_files_are_checked() {
        $this->assertSame( 'no_file', TimeGrowTransfer::read( '', 'timegrow-knowledge-base' ) );
        $this->assertSame( 'not_json', TimeGrowTransfer::read( 'hello', 'timegrow-knowledge-base' ) );
        $this->assertSame( 'wrong_type', TimeGrowTransfer::read( '{"type":"timegrow-ai-prompts","version":1}', 'timegrow-knowledge-base' ) );
        $this->assertSame( 'new_version', TimeGrowTransfer::read( '{"type":"timegrow-knowledge-base","version":9}', 'timegrow-knowledge-base' ) );
        $this->assertSame( [], TimeGrowTransfer::read( '{"type":"timegrow-knowledge-base","version":1,"topics":{}}', 'timegrow-knowledge-base' )['topics'] );
    }

    public function test_knowledge_base_import_plan() {
        $current = [
            'punch-in-out' => [ 'title' => 'Clock in', 'roles' => 'everyone', 'page' => '', 'keywords' => 'clock', 'body' => 'Ours.', 'hidden' => false ],
            'custom-pto-1' => [ 'title' => 'PTO', 'roles' => 'everyone', 'page' => '', 'keywords' => 'pto', 'body' => 'Same text.', 'hidden' => false ],
        ];
        $incoming = [
            'punch-in-out' => [ 'title' => 'Clock in', 'roles' => 'everyone', 'page' => '', 'keywords' => 'clock', 'body' => 'Better text.' ],
            'custom-pto-1' => [ 'title' => 'PTO', 'roles' => 'everyone', 'page' => '', 'keywords' => 'pto', 'body' => 'Same text.' ],
            'custom-new-2' => [ 'title' => 'Holidays', 'body' => 'We close on July 4.', 'keywords' => 'holiday' ],
            'clients'      => [ 'hidden' => true ],
            'ghost'        => [ 'title' => 'Not here', 'body' => 'x' ],
            'custom-bad-3' => [ 'title' => '', 'body' => 'no title' ],
        ];
        $plan = TimeGrowTransfer::plan_topics( $current, [ 'punch-in-out', 'clients' ], $incoming, [ 'TimeGrowHelpTopicsPage', 'validate' ] );
        $what = array_combine( array_column( $plan['rows'], 0 ), array_column( $plan['rows'], 2 ) );
        $this->assertSame( [ 'punch-in-out' => 'changed', 'custom-pto-1' => 'same', 'custom-new-2' => 'new', 'clients' => 'hidden', 'ghost' => 'invalid', 'custom-bad-3' => 'invalid' ], $what );
        $this->assertSame( [ 'punch-in-out', 'custom-new-2', 'clients' ], array_keys( $plan['apply'] ), 'only real changes are applied' );
        $this->assertSame( [ 'hidden' => true ], $plan['apply']['clients'] );
        $this->assertSame( 'Better text.', $plan['apply']['punch-in-out']['body'] );
    }

    public function test_prompt_import_plan() {
        $existing = [ [ 'feature' => 'voice', 'content' => "Same prompt\nline 2" ] ];
        $plan = TimeGrowTransfer::plan_prompts( $existing, [
            [ 'feature' => 'voice', 'name' => 'Old', 'content' => "Same prompt\r\nline 2", 'status' => 'archived' ],
            [ 'feature' => 'voice', 'name' => '#19', 'content' => 'New prompt', 'status' => 'active' ],
            [ 'feature' => 'voice', 'name' => 'Twice', 'content' => 'New prompt', 'status' => 'draft' ],
            [ 'feature' => 'receipts', 'name' => 'R', 'content' => 'Receipt prompt', 'status' => 'weird' ],
            [ 'feature' => 'payroll', 'name' => 'X', 'content' => 'Nope', 'status' => 'active' ],
            [ 'feature' => 'voice', 'name' => 'Huge', 'content' => str_repeat( 'a', 31 ), 'status' => 'draft' ],
        ], [ 'voice', 'receipts' ], 30 );
        $this->assertSame( [ 'duplicate', 'new', 'duplicate', 'new', 'invalid', 'invalid' ], array_column( $plan, 3 ), 'same text (any line endings) is skipped, also twice in the file' );
        $this->assertSame( 'draft', $plan[3][2], 'an unknown status comes in as draft' );
        $this->assertSame( 'active', $plan[1][2] );
    }
}
