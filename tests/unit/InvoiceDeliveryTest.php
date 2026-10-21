<?php
use PHPUnit\Framework\TestCase;

/**
 * Which way a client gets invoices (Email, PayPal, Print and mail).
 */
class InvoiceDeliveryTest extends TestCase {

    protected function setUp(): void {
        TimeGrow_Test_State::reset();
    }

    public function test_send_only_from_unpaid_statuses_not_at_paypal() {
        foreach ( [ 'pending', 'on-hold', 'processing', 'invoice_sent', 'to_be_mailed' ] as $status ) {
            $this->assertSame( '', Aragrow_TimeGrow_Invoice_Delivery::send_refusal( $status ), $status );
        }
        foreach ( [ 'cancelled', 'refunded', 'invoice_paid', 'partial_paid', 'inv_send2paypal', 'inv_sent2paypal', 'inv_draft2paypal', 'trash' ] as $status ) {
            $this->assertNotSame( '', Aragrow_TimeGrow_Invoice_Delivery::send_refusal( $status ), $status );
        }
    }

    public function test_timegrow_invoice_can_be_paid_once_sent_and_never_cancelled_by_the_client() {
        $ours  = TimeGrow_Fake_Order::hours_invoice( 1 );
        $ours->meta['_timekeeping_invoice'] = '1';
        $other = new TimeGrow_Fake_Order( 2 );

        $this->assertSame( [ 'pending', 'failed', 'invoice_sent', 'to_be_mailed' ], Aragrow_TimeGrow_Invoice_Delivery::payable_statuses( [ 'pending', 'failed' ], $ours ) );
        $this->assertSame( [ 'pending', 'failed' ], Aragrow_TimeGrow_Invoice_Delivery::payable_statuses( [ 'pending', 'failed' ], $other ) );

        $this->assertSame( [], Aragrow_TimeGrow_Order_Cancellation::customer_cancel_statuses( [ 'pending', 'failed' ], $ours ) );
        $this->assertSame( [ 'pending', 'failed' ], Aragrow_TimeGrow_Order_Cancellation::customer_cancel_statuses( [ 'pending', 'failed' ], $other ) );
    }

    public function test_full_payment_records_the_total_after_a_partial_one() {
        $this->assertSame( '1000.00', Aragrow_WOO_Integration_Plugins::full_payment_amount( '', '1000.00' ) );
        $this->assertSame( '1000.00', Aragrow_WOO_Integration_Plugins::full_payment_amount( '500', '1000.00' ) );
        $this->assertNull( Aragrow_WOO_Integration_Plugins::full_payment_amount( '1000.00', '1000.00' ) );
        $this->assertNull( Aragrow_WOO_Integration_Plugins::full_payment_amount( '1050', '1000.00' ), 'an overpayment is kept' );
    }

    public function test_email_when_nothing_is_saved() {
        $this->assertSame( 'email', Aragrow_TimeGrow_Invoice_Delivery::method_for_user( 7 ) );
    }

    public function test_saved_choice_is_used() {
        TimeGrow_Test_State::$user_meta[7]['timegrow_invoice_delivery'] = 'paypal';
        TimeGrow_Test_State::$user_meta[8]['timegrow_invoice_delivery'] = 'mail';

        $this->assertSame( 'paypal', Aragrow_TimeGrow_Invoice_Delivery::method_for_user( 7 ) );
        $this->assertSame( 'mail', Aragrow_TimeGrow_Invoice_Delivery::method_for_user( 8 ) );
    }

    public function test_unknown_value_falls_back_to_email() {
        TimeGrow_Test_State::$user_meta[7]['timegrow_invoice_delivery'] = 'fax';

        $this->assertSame( 'email', Aragrow_TimeGrow_Invoice_Delivery::method_for_user( 7 ) );
    }

    public function test_order_without_a_customer_uses_email() {
        $order = new TimeGrow_Fake_Order( 1 );

        $this->assertSame( 'email', Aragrow_TimeGrow_Invoice_Delivery::method_for_order( $order ) );
    }

    public function test_order_uses_its_customers_choice() {
        TimeGrow_Test_State::$user_meta[9]['timegrow_invoice_delivery'] = 'mail';
        $order = new TimeGrow_Fake_Order( 1 );
        $order->customer_id = 9;

        $this->assertSame( 'mail', Aragrow_TimeGrow_Invoice_Delivery::method_for_order( $order ) );
    }
}
