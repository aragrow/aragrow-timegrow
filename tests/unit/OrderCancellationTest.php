<?php
use PHPUnit\Framework\TestCase;

/**
 * Cancelling a TimeGrow invoice: when it is refused, and what happens to the PayPal invoice.
 */
class OrderCancellationTest extends TestCase {

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

    public function test_paypal_refunded_amount() {
        $this->assertSame( 0.0, Aragrow_TimeGrow_PayPal_Payment_Sync::refunded_amount( [ 'status' => 'PAID', 'amount' => [ 'value' => '500.00' ] ] ) );
        $this->assertSame( 120.0, Aragrow_TimeGrow_PayPal_Payment_Sync::refunded_amount( [ 'status' => 'PARTIALLY_REFUNDED', 'refunds' => [ 'refund_amount' => [ 'value' => '120.00' ] ] ] ) );
        $this->assertSame( 500.0, Aragrow_TimeGrow_PayPal_Payment_Sync::refunded_amount( [ 'status' => 'REFUNDED', 'refunds' => [ 'refund_amount' => [ 'value' => '500.00' ] ] ] ) );
        // A refund recorded in PayPal without an amount counts as the whole invoice
        $this->assertSame( 500.0, Aragrow_TimeGrow_PayPal_Payment_Sync::refunded_amount( [ 'status' => 'MARKED_AS_REFUNDED', 'amount' => [ 'value' => '500.00' ] ] ) );
    }

    public function test_paypal_draft_is_deleted() {
        $this->assertSame( 'delete', Aragrow_TimeGrow_PayPal_Payment_Sync::cancel_plan( 'DRAFT' ) );
    }

    public function test_sent_paypal_invoices_are_cancelled() {
        foreach ( [ 'SENT', 'UNPAID', 'SCHEDULED', 'PAYMENT_PENDING' ] as $status ) {
            $this->assertSame( 'cancel', Aragrow_TimeGrow_PayPal_Payment_Sync::cancel_plan( $status ), $status );
        }
    }

    public function test_paid_paypal_invoices_stop_the_cancel() {
        foreach ( [ 'PAID', 'MARKED_AS_PAID', 'PARTIALLY_PAID', 'REFUNDED', 'PARTIALLY_REFUNDED', 'MARKED_AS_REFUNDED' ] as $status ) {
            $this->assertSame( 'paid', Aragrow_TimeGrow_PayPal_Payment_Sync::cancel_plan( $status ), $status );
        }
    }

    public function test_already_cancelled_in_paypal_lets_the_order_cancel() {
        $this->assertSame( 'done', Aragrow_TimeGrow_PayPal_Payment_Sync::cancel_plan( 'CANCELLED' ) );
    }
}
