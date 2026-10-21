<?php
use PHPUnit\Framework\TestCase;

/**
 * The Manual Payment box on the order editor: what a save records, refuses or removes.
 */
class ManualPaymentTest extends TestCase {

    private function plan( array $posted, $recorded = false ) {
        return Aragrow_WOO_Integration_Plugins::manual_payment_plan( $posted + [ 'submitted' => true ], $recorded );
    }

    public function test_an_order_saved_without_payment_details_records_nothing() {
        // The type starts on "Select a type" (empty), so a plain order save posts nothing
        $plan = $this->plan( [ 'date' => '', 'amount' => 0, 'type' => '', 'trans_id' => '' ] );
        $this->assertSame( 'none', $plan['action'] );
        $this->assertSame( [], $plan['meta'] );
    }

    public function test_a_complete_payment_is_saved() {
        $plan = $this->plan( [ 'date' => '2026-10-20', 'amount' => 500.0, 'type' => 'ACH', 'trans_id' => 'TX-1' ] );
        $this->assertSame( 'save', $plan['action'] );
        $this->assertSame( [ '_payment_date' => '2026-10-20', '_payment_amount' => 500.0, '_payment_type' => 'ACH', '_payment_trans_id' => 'TX-1' ], $plan['meta'] );
    }

    public function test_cash_needs_no_transaction_id() {
        $plan = $this->plan( [ 'date' => '2026-10-20', 'amount' => 80.0, 'type' => 'Cash', 'trans_id' => '' ] );
        $this->assertSame( 'save', $plan['action'] );
        $this->assertArrayNotHasKey( '_payment_trans_id', $plan['meta'] );
    }

    public function test_a_payment_with_missing_details_is_refused_with_every_reason() {
        $plan = $this->plan( [ 'date' => '', 'amount' => 500.0, 'type' => 'ACH', 'trans_id' => '' ] );
        $this->assertSame( 'refuse', $plan['action'] );
        $this->assertCount( 2, $plan['errors'] );
        $this->assertStringContainsString( 'Payment Date', $plan['errors'][0] );
        $this->assertStringContainsString( 'Transaction ID is required for ACH', $plan['errors'][1] );
        $this->assertSame( [], $plan['meta'] );

        $this->assertSame( 'refuse', $this->plan( [ 'date' => '2026-10-20', 'amount' => -5.0, 'type' => 'Cash' ] )['action'], 'amount above 0' );
        $this->assertSame( 'refuse', $this->plan( [ 'date' => '2026-10-20', 'amount' => 50.0, 'type' => '' ] )['action'], 'a type is needed' );
    }

    public function test_emptying_every_field_removes_a_recorded_payment() {
        $empty = [ 'date' => '', 'amount' => 0, 'type' => '', 'trans_id' => '' ];
        $this->assertSame( 'clear', $this->plan( $empty, true )['action'] );
        $this->assertSame( 'none', $this->plan( $empty, false )['action'], 'nothing recorded: nothing to remove' );
        // A save without the box on the page (another screen) never clears anything
        $this->assertSame( 'none', Aragrow_WOO_Integration_Plugins::manual_payment_plan( $empty + [ 'submitted' => false ], true )['action'] );
    }
}
