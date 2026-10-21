<?php
use PHPUnit\Framework\TestCase;

/**
 * Cancelling a TimeGrow invoice: when it is refused, and what happens to the PayPal invoice.
 */
class OrderCancellationTest extends TestCase {

    private function refund( $id, $amount ) {
        return new class( $id, $amount ) {
            private $id; private $amount;
            public function __construct( $id, $amount ) { $this->id = $id; $this->amount = $amount; }
            public function get_id() { return $this->id; }
            public function get_amount() { return $this->amount; }
        };
    }

    public function test_new_refund_is_counted_once_even_when_already_saved() {
        $new = $this->refund( 30, '40.00' );
        // WooCommerce already saved the new refund: it is in the order's refunds
        $this->assertSame( 60.0, Aragrow_TimeGrow_Order_Cancellation::refunded_with( [ $this->refund( 21, '20.00' ), $new ], $new ) );
        // Not saved yet (no ID): the others plus this one
        $this->assertSame( 60.0, Aragrow_TimeGrow_Order_Cancellation::refunded_with( [ $this->refund( 21, '20.00' ) ], $this->refund( 0, '40.00' ) ) );
        $this->assertSame( 40.0, Aragrow_TimeGrow_Order_Cancellation::refunded_with( [], $this->refund( 0, '40' ) ) );
    }

    public function test_which_checks_a_cancel_runs() {
        // A TimeGrow invoice: the full checks (payment, PayPal, then the time is released)
        $this->assertSame( 'full', Aragrow_TimeGrow_Order_Cancellation::cancel_checks( 'invoice_sent', true, true, false ) );
        $this->assertSame( 'full', Aragrow_TimeGrow_Order_Cancellation::cancel_checks( 'pending', true, false, false ) );
        // An order made by hand and sent to PayPal: its PayPal invoice is cancelled too
        $this->assertSame( 'paypal', Aragrow_TimeGrow_Order_Cancellation::cancel_checks( 'inv_sent2paypal', false, true, false ) );
        // A plain store order: WooCommerce alone
        $this->assertSame( 'none', Aragrow_TimeGrow_Order_Cancellation::cancel_checks( 'processing', false, false, false ) );
        // Already checked by cancel(), or refunded first
        $this->assertSame( 'none', Aragrow_TimeGrow_Order_Cancellation::cancel_checks( 'invoice_sent', true, true, true ) );
        $this->assertSame( 'none', Aragrow_TimeGrow_Order_Cancellation::cancel_checks( 'refunded', true, true, false ) );
        $this->assertSame( 'none', Aragrow_TimeGrow_Order_Cancellation::cancel_checks( 'refunded', false, true, false ) );
    }

    public function test_unpaid_invoices_can_be_cancelled() {
        foreach ( [ 'processing', 'pending', 'invoice_sent', 'to_be_mailed', 'inv_sent2paypal', 'inv_draft2paypal' ] as $status ) {
            $this->assertSame( '', Aragrow_TimeGrow_Order_Cancellation::cancel_refusal( $status, 0 ), $status );
        }
    }

    public function test_paid_or_partly_paid_invoices_are_refused() {
        $this->assertNotSame( '', Aragrow_TimeGrow_Order_Cancellation::cancel_refusal( 'invoice_paid', 0 ) );
        $this->assertNotSame( '', Aragrow_TimeGrow_Order_Cancellation::cancel_refusal( 'partial_paid', 0 ) );
    }

    public function test_a_recorded_payment_is_refused_whatever_the_status() {
        $this->assertNotSame( '', Aragrow_TimeGrow_Order_Cancellation::cancel_refusal( 'invoice_sent', '150.00' ) );
    }

    public function test_cancelled_invoice_is_not_cancelled_again() {
        $this->assertSame( 'This invoice is already cancelled.', Aragrow_TimeGrow_Order_Cancellation::cancel_refusal( 'cancelled', 0 ) );
    }

    public function test_paid_invoice_can_only_be_refunded() {
        $this->assertTrue( Aragrow_TimeGrow_Order_Cancellation::paid_move_allowed( 'invoice_paid', 'refunded' ) );
        foreach ( [ 'cancelled', 'processing', 'pending', 'invoice_sent', 'partial_paid', 'completed', 'inv_send2paypal' ] as $to ) {
            $this->assertFalse( Aragrow_TimeGrow_Order_Cancellation::paid_move_allowed( 'invoice_paid', $to ), $to );
        }
    }

    public function test_partly_paid_invoice_can_be_paid_in_full_or_refunded() {
        $this->assertTrue( Aragrow_TimeGrow_Order_Cancellation::paid_move_allowed( 'partial_paid', 'invoice_paid' ) );
        $this->assertTrue( Aragrow_TimeGrow_Order_Cancellation::paid_move_allowed( 'partial_paid', 'refunded' ) );
        $this->assertFalse( Aragrow_TimeGrow_Order_Cancellation::paid_move_allowed( 'partial_paid', 'cancelled' ) );
        $this->assertFalse( Aragrow_TimeGrow_Order_Cancellation::paid_move_allowed( 'partial_paid', 'processing' ) );
    }

    public function test_refunded_invoice_can_only_be_cancelled() {
        $this->assertTrue( Aragrow_TimeGrow_Order_Cancellation::paid_move_allowed( 'refunded', 'cancelled' ) );
        foreach ( [ 'processing', 'invoice_paid', 'partial_paid', 'invoice_sent' ] as $to ) {
            $this->assertFalse( Aragrow_TimeGrow_Order_Cancellation::paid_move_allowed( 'refunded', $to ), $to );
        }
        $this->assertSame( [ 'cancelled' ], Aragrow_TimeGrow_Order_Cancellation::allowed_moves( 'refunded' ) );
    }

    public function test_refunded_order_not_made_by_timegrow_is_not_limited() {
        $this->assertNull( Aragrow_TimeGrow_Order_Cancellation::allowed_moves( 'refunded', false ) );
        $this->assertTrue( Aragrow_TimeGrow_Order_Cancellation::paid_move_allowed( 'refunded', 'processing', false ) );
    }

    public function test_unpaid_orders_are_not_limited() {
        $this->assertTrue( Aragrow_TimeGrow_Order_Cancellation::paid_move_allowed( 'processing', 'cancelled' ) );
        $this->assertNull( Aragrow_TimeGrow_Order_Cancellation::allowed_moves( 'invoice_sent' ) );
    }

    public function test_paypal_fully_refunded() {
        $this->assertTrue( Aragrow_TimeGrow_PayPal_Payment_Sync::fully_refunded( [ 'status' => 'REFUNDED' ] ) );
        $this->assertTrue( Aragrow_TimeGrow_PayPal_Payment_Sync::fully_refunded( [ 'status' => 'PAID', 'amount' => [ 'value' => '175.01' ], 'refunds' => [ 'refund_amount' => [ 'value' => '175.01' ] ] ] ) );
        $this->assertFalse( Aragrow_TimeGrow_PayPal_Payment_Sync::fully_refunded( [ 'status' => 'PARTIALLY_REFUNDED', 'amount' => [ 'value' => '500.00' ], 'refunds' => [ 'refund_amount' => [ 'value' => '120.00' ] ] ] ) );
        $this->assertFalse( Aragrow_TimeGrow_PayPal_Payment_Sync::fully_refunded( [ 'status' => 'PAID' ] ) );
    }

    public function test_paypal_refunded_amount() {
        $this->assertSame( 0.0, Aragrow_TimeGrow_PayPal_Payment_Sync::refunded_amount( [ 'status' => 'PAID', 'amount' => [ 'value' => '500.00' ] ] ) );
        $this->assertSame( 120.0, Aragrow_TimeGrow_PayPal_Payment_Sync::refunded_amount( [ 'status' => 'PARTIALLY_REFUNDED', 'refunds' => [ 'refund_amount' => [ 'value' => '120.00' ] ] ] ) );
        $this->assertSame( 500.0, Aragrow_TimeGrow_PayPal_Payment_Sync::refunded_amount( [ 'status' => 'REFUNDED', 'refunds' => [ 'refund_amount' => [ 'value' => '500.00' ] ] ] ) );
        // A refund recorded in PayPal without an amount counts as the whole invoice
        $this->assertSame( 500.0, Aragrow_TimeGrow_PayPal_Payment_Sync::refunded_amount( [ 'status' => 'MARKED_AS_REFUNDED', 'amount' => [ 'value' => '500.00' ] ] ) );
    }

    public function test_paypal_draft_is_deleted() {
        $this->assertSame( 'delete', Aragrow_TimeGrow_PayPal_Payment_Sync::cancel_plan( 'DRAFT' ) );
        $this->assertSame( 'delete', Aragrow_TimeGrow_PayPal_Payment_Sync::cancel_plan( 'SCHEDULED' ), 'PayPal cancels only sent invoices' );
    }

    public function test_sent_paypal_invoices_are_cancelled() {
        foreach ( [ 'SENT', 'UNPAID' ] as $status ) {
            $this->assertSame( 'cancel', Aragrow_TimeGrow_PayPal_Payment_Sync::cancel_plan( $status ), $status );
        }
    }

    public function test_paid_paypal_invoices_stop_the_cancel() {
        foreach ( [ 'PAID', 'MARKED_AS_PAID', 'PARTIALLY_PAID', 'PAYMENT_PENDING', 'REFUNDED', 'PARTIALLY_REFUNDED', 'MARKED_AS_REFUNDED' ] as $status ) {
            $this->assertSame( 'paid', Aragrow_TimeGrow_PayPal_Payment_Sync::cancel_plan( $status ), $status );
        }
    }

    public function test_already_cancelled_in_paypal_lets_the_order_cancel() {
        $this->assertSame( 'done', Aragrow_TimeGrow_PayPal_Payment_Sync::cancel_plan( 'CANCELLED' ) );
    }
}
