<?php
use PHPUnit\Framework\TestCase;

/**
 * Which way a client gets invoices (Email, PayPal, Print and mail).
 */
class InvoiceDeliveryTest extends TestCase {

    protected function setUp(): void {
        TimeGrow_Test_State::reset();
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
