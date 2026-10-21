<?php
use PHPUnit\Framework\TestCase;

/** A retry finds the PayPal invoice an earlier try made for the same order, instead of making a second one. */
class PayPalExistingInvoiceTest extends TestCase {

    private function invoice( $id, $reference, $email, $status = 'DRAFT' ) {
        return [ 'id' => $id, 'status' => $status, 'detail' => [ 'reference' => $reference ],
                 'primary_recipients' => [ [ 'billing_info' => [ 'email_address' => $email ] ] ] ];
    }

    public function test_finds_this_orders_invoice() {
        $items = [ $this->invoice( 'INV-OTHER', '1105', 'ana@example.test' ), $this->invoice( 'INV-1106', '1106', 'Ana@Example.test', 'SENT' ) ];
        $this->assertSame( 'INV-1106', Aragrow_WC_PayPal_Auto_Invoicer::pick_order_invoice( $items, 1106, 'ana@example.test' )['id'] );
    }

    public function test_same_order_number_for_another_client_is_not_used() {
        // The same PayPal account can serve another site whose order numbers overlap
        $items = [ $this->invoice( 'INV-PROD', '1106', 'someone@else.test' ) ];
        $this->assertNull( Aragrow_WC_PayPal_Auto_Invoicer::pick_order_invoice( $items, 1106, 'ana@example.test' ) );
    }

    public function test_cancelled_invoices_and_missing_details_are_not_used() {
        $items = [
            $this->invoice( 'INV-CANCELLED', '1106', 'ana@example.test', 'CANCELLED' ),
            $this->invoice( 'INV-NO-EMAIL', '1106', '' ),
            [ 'status' => 'DRAFT', 'detail' => [ 'reference' => '1106' ] ], // no ID
        ];
        $this->assertNull( Aragrow_WC_PayPal_Auto_Invoicer::pick_order_invoice( $items, 1106, 'ana@example.test' ) );
        $this->assertNull( Aragrow_WC_PayPal_Auto_Invoicer::pick_order_invoice( [ $this->invoice( 'INV-1106', '1106', 'ana@example.test' ) ], 1106, '' ), 'no client email on the order: never guessed' );
    }

    public function test_zero_decimal_currencies_send_whole_amounts() {
        $this->assertSame( '1234.50', Aragrow_WC_PayPal_Auto_Invoicer::money_value( 1234.5, 'USD' ) );
        $this->assertSame( '1235', Aragrow_WC_PayPal_Auto_Invoicer::money_value( 1234.5, 'jpy' ) );
        $this->assertSame( '99', Aragrow_WC_PayPal_Auto_Invoicer::money_value( 99.4, 'HUF' ) );
    }
}
