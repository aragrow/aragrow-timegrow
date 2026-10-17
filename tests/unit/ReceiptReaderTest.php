<?php
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $s ) { return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $s ) ) ); }
}
if ( ! interface_exists( 'TimeGrowReceiptAnalyzerInterface' ) ) {
    require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowReceiptAnalyzerInterface.php';
}
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowAI.php';
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowAIReceiptAnalyzer.php';

/**
 * The shared receipt reader: its prompt and how it reads the AI's answer.
 */
class ReceiptReaderTest extends TestCase {

    private $categories = [
        [ 'id' => 25, 'slug' => 'office_expense', 'name' => 'Office Expense', 'description' => 'supplies', 'section' => 'part_ii' ],
        [ 'id' => 31, 'slug' => 'online_web_fees', 'name' => 'Online and Web Fees', 'description' => '', 'section' => 'part_v' ],
    ];

    public function test_prompt_lists_the_real_category_ids() {
        $prompt = TimeGrowAIReceiptAnalyzer::prompt( $this->categories );

        $this->assertStringContainsString( '- 25|office_expense: Office Expense (supplies)', $prompt );
        $this->assertStringContainsString( '- 31|online_web_fees: Online and Web Fees', $prompt );
        $this->assertStringContainsString( 'category_id', TimeGrowAIReceiptAnalyzer::default_instructions(), 'the keys are asked for in the instructions' );
    }

    public function test_answer_with_a_valid_category_id() {
        $f = TimeGrowAIReceiptAnalyzer::fields_from_answer( [ 'amount' => '125.504', 'date' => '2026-10-01', 'vendor' => 'Office Depot', 'category_id' => 25, 'description' => 'Paper', 'confidence' => 0.93 ], $this->categories );

        $this->assertSame( 125.5, $f['amount'] );
        $this->assertSame( '2026-10-01', $f['expense_date'] );
        $this->assertSame( 'Office Depot', $f['expense_name'] );
        $this->assertSame( 25, $f['category_id'] );
        $this->assertSame( 0.93, $f['confidence'] );
    }

    public function test_a_slug_instead_of_an_id_still_finds_the_category() {
        // What the old OpenAI and Claude readers did: return the slug
        $this->assertSame( 31, TimeGrowAIReceiptAnalyzer::fields_from_answer( [ 'category' => 'online_web_fees' ], $this->categories )['category_id'] );
        $this->assertSame( 25, TimeGrowAIReceiptAnalyzer::fields_from_answer( [ 'category_id' => '25|office_expense' ], $this->categories )['category_id'] );
    }

    public function test_unknown_category_becomes_none() {
        $this->assertSame( 0, TimeGrowAIReceiptAnalyzer::fields_from_answer( [ 'category_id' => 999 ], $this->categories )['category_id'] );
        $this->assertSame( 0, TimeGrowAIReceiptAnalyzer::fields_from_answer( [ 'category_id' => 'made_up' ], $this->categories )['category_id'] );
    }

    public function test_handwritten_notes_bad_dates_and_confidence_range() {
        $f = TimeGrowAIReceiptAnalyzer::fields_from_answer( [ 'description' => 'Ink', 'handwritten_notes' => 'Q1 kickoff', 'date' => '2026-13-40', 'confidence' => 4, 'amount' => 'twelve' ], $this->categories );

        $this->assertStringContainsString( 'HANDWRITTEN: Q1 kickoff', $f['expense_description'] );
        $this->assertSame( '', $f['expense_date'] );
        $this->assertSame( 1.0, $f['confidence'] );
        $this->assertSame( 0.0, $f['amount'] );
    }

    public function test_headings_are_not_offered_as_categories() {
        $rows = [
            (object) [ 'ID' => 1, 'slug' => 'schedule_c_part_ii', 'name' => 'Schedule C - Part II', 'parent_id' => null, 'schedule_c_part' => 'Part II' ],
            (object) [ 'ID' => 13, 'slug' => 'office_expense', 'name' => 'Office Expense', 'parent_id' => '1', 'schedule_c_part' => 'Part II' ],
            (object) [ 'ID' => 24, 'slug' => 'schedule_c_part_v', 'name' => 'Schedule C - Part V', 'parent_id' => null, 'schedule_c_part' => 'Part V' ],
            (object) [ 'ID' => 25, 'slug' => 'online_web_fees', 'name' => 'Online Web Fees', 'parent_id' => '24', 'schedule_c_part' => 'Part V' ],
        ];
        $cats = TimeGrowAIReceiptAnalyzer::usable_categories( $rows );
        $this->assertSame( [ 13, 25 ], array_column( $cats, 'id' ) );
        $this->assertSame( [ 'part_ii', 'part_v' ], array_column( $cats, 'section' ), 'Part II and Part V are read from schedule_c_part' );
    }

    public function test_a_refund_leaves_the_amount_for_the_person() {
        $f = TimeGrowAIReceiptAnalyzer::fields_from_answer( [ 'amount' => null, 'vendor' => 'Best Buy', 'description' => 'USB-C Hub', 'refund' => true, 'confidence' => 0.95 ], $this->categories );
        $this->assertTrue( $f['refund'] );
        $this->assertSame( 0.0, $f['amount'] );
        $this->assertSame( 'Refund: USB-C Hub', $f['expense_description'] );
        $this->assertLessThanOrEqual( 0.3, $f['confidence'] );
        $this->assertTrue( TimeGrowAIReceiptAnalyzer::fields_from_answer( [ 'amount' => -53.61 ], $this->categories )['refund'], 'a negative total is a refund too' );
    }

    public function test_text_addressed_to_the_ai_is_flagged() {
        $f = TimeGrowAIReceiptAnalyzer::fields_from_answer( [ 'amount' => 19.84, 'vendor' => 'FedEx Office', 'instructions_found' => true, 'confidence' => 0.95 ], $this->categories );
        $this->assertTrue( $f['suspicious'] );
        $this->assertLessThanOrEqual( 0.3, $f['confidence'] );
        $this->assertSame( 19.84, $f['amount'], 'the real total is kept' );
    }
}
