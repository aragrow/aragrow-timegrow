<?php
use PHPUnit\Framework\TestCase;

/**
 * Turning time entries into hours invoice lines.
 */
class OrderCreatorTest extends TestCase {

    private function entry(array $fields) {
        return (object) array_merge(
            [ 'billable' => 1, 'billed' => 0, 'entry_type' => 'MAN', 'hours' => 0, 'clock_in_date' => null, 'clock_out_date' => null ],
            $fields
        );
    }

    // ---- which entries count

    public function test_only_billable_unbilled_entries_count() {
        $this->assertTrue( TimeGrowWooOrderCreator::is_billable( $this->entry( [] ) ) );
        $this->assertFalse( TimeGrowWooOrderCreator::is_billable( $this->entry( [ 'billable' => 0 ] ) ) );
        $this->assertFalse( TimeGrowWooOrderCreator::is_billable( $this->entry( [ 'billed' => 1 ] ) ) );
    }

    // ---- hours per entry

    public function test_manual_entry_uses_its_hours() {
        $this->assertSame( 2.5, TimeGrowWooOrderCreator::entry_hours( $this->entry( [ 'hours' => '2.5' ] ) ) );
    }

    public function test_clock_entry_uses_time_between_in_and_out() {
        $entry = $this->entry( [ 'entry_type' => 'IN', 'clock_in_date' => '2026-10-15 09:00:00', 'clock_out_date' => '2026-10-15 12:45:00' ] );
        $this->assertSame( 3.75, TimeGrowWooOrderCreator::entry_hours( $entry ) );
    }

    public function test_open_clock_entry_is_skipped() {
        $entry = $this->entry( [ 'entry_type' => 'IN', 'clock_in_date' => '2026-10-15 09:00:00' ] );
        $this->assertNull( TimeGrowWooOrderCreator::entry_hours( $entry ) );
    }

    // ---- rate order

    public function test_rate_order_project_then_client_then_company() {
        $this->assertSame( 75.0, TimeGrowWooOrderCreator::resolve_rate( '75.00', '100', 150 ) );
        $this->assertSame( 100.0, TimeGrowWooOrderCreator::resolve_rate( '0.00', '100', 150 ) );
        $this->assertSame( 150.0, TimeGrowWooOrderCreator::resolve_rate( '0.00', '', 150 ) );
    }

    public function test_rate_falls_back_to_75() {
        $this->assertSame( 75.0, TimeGrowWooOrderCreator::resolve_rate( null, '0.00', 0 ) );
    }

    // ---- line price

    public function test_line_is_rounded_hours_times_rate() {
        // 3.749 hours rounds to 3.75; 3.75 x 75 = 281.25
        $this->assertSame( [ 3.75, 281.25 ], TimeGrowWooOrderCreator::price_line( 3.749, 75 ) );
    }

    public function test_line_total_rounds_to_the_cent() {
        // 0.33 x 62.5 = 20.625 -> 20.63
        $this->assertSame( [ 0.33, 20.63 ], TimeGrowWooOrderCreator::price_line( 0.33, 62.5 ) );
    }

    // ---- reusing an open invoice

    public function test_reuses_the_first_unissued_order() {
        $issued_pdf    = new TimeGrow_Fake_Order( 1, [], [ '_wcpdf_invoice_number' => '000091' ] );
        $issued_paypal = new TimeGrow_Fake_Order( 2, [], [ '_paypal_invoice_id' => 'INV2-XXXX' ] );
        $open          = new TimeGrow_Fake_Order( 3 );

        $this->assertSame( $open, TimeGrowWooOrderCreator::pick_reusable_order( [ $issued_pdf, $issued_paypal, $open ] ) );
    }

    public function test_new_order_when_every_candidate_was_issued() {
        $issued = new TimeGrow_Fake_Order( 1, [], [ '_wcpdf_invoice_number' => '000091' ] );

        $this->assertNull( TimeGrowWooOrderCreator::pick_reusable_order( [ $issued ] ) );
        $this->assertNull( TimeGrowWooOrderCreator::pick_reusable_order( [] ) );
    }

    public function test_line_name_is_plain_text() {
        $this->assertSame( 'Harbor & Vine - SEO Audit', TimeGrowWooOrderCreator::line_name( 'Harbor &amp; Vine', 'SEO Audit' ) );
        $this->assertSame( "O'Neil Co - Books", TimeGrowWooOrderCreator::line_name( 'O&#039;Neil Co', 'Books' ) );
        $this->assertSame( 'Website', TimeGrowWooOrderCreator::line_name( '', 'Website' ), 'no client name' );
    }
}
