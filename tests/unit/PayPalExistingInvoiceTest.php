<?php
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
}

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

    public function test_credential_problem_explains_unreadable_keys() {
        $saved = [ 'client_id' => 'enc-id', 'client_secret' => 'enc-secret' ];
        $this->assertSame( '', Aragrow_WC_PayPal_Auto_Invoicer::credential_problem( true, $saved, [ 'client_id' => 'id', 'client_secret' => 'secret' ] ) );
        $this->assertSame( '', Aragrow_WC_PayPal_Auto_Invoicer::credential_problem( false, [ 'client_id' => '', 'client_secret' => '' ], [] ), 'nothing saved: nothing to say' );
        $this->assertStringContainsString( 'not defined', Aragrow_WC_PayPal_Auto_Invoicer::credential_problem( false, $saved, [] ) );
        $this->assertStringContainsString( 'changed', Aragrow_WC_PayPal_Auto_Invoicer::credential_problem( true, $saved, [ 'client_id' => 'id', 'client_secret' => '' ] ) );
    }

    public function test_catalog_product_id_fits_paypals_limits() {
        $this->assertSame( 'TG-ABC', Aragrow_WC_PayPal_Auto_Invoicer::catalog_product_id( 'ABC' ) );
        $this->assertSame( 'TG-007', Aragrow_WC_PayPal_Auto_Invoicer::catalog_product_id( '7' ) );
        $this->assertSame( 'web-design-2026', Aragrow_WC_PayPal_Auto_Invoicer::catalog_product_id( 'web design/2026' ) );
        $this->assertSame( 50, strlen( Aragrow_WC_PayPal_Auto_Invoicer::catalog_product_id( str_repeat( 'A', 80 ) ) ) );
        $this->assertSame( '', Aragrow_WC_PayPal_Auto_Invoicer::catalog_product_id( '///' ) );
    }

    private function payload( array $settings, $order = null, $invoice_number = '' ) {
        $order = $order ?: new TimeGrow_Fake_Order( 1106 );
        return Aragrow_WC_PayPal_Auto_Invoicer::instance()->build_invoice_payload( $order, $settings, [ [ 'name' => 'Line' ] ], [ 'email_address' => 'client@example.test' ], $invoice_number );
    }

    public function test_an_empty_merchant_email_is_left_out_of_the_invoice() {
        $payload = $this->payload( [ 'merchant_email' => '' ] );
        $this->assertArrayNotHasKey( 'invoicer', $payload );
        $this->assertStringNotContainsString( '"invoicer"', json_encode( $payload ), 'never an empty JSON list' );

        $payload = $this->payload( [ 'merchant_email' => 'billing@example.test' ] );
        $this->assertSame( [ 'email_address' => 'billing@example.test' ], $payload['invoicer'] );
        $this->assertStringContainsString( '"invoicer":{"email_address"', json_encode( $payload ), 'a JSON object' );
    }

    public function test_invoice_payload_carries_reference_number_and_credit() {
        $order = new TimeGrow_Fake_Order( 1106 );
        $order->fees = [ new TimeGrow_Fake_Fee( 'Loyalty credit', -25 ) ];
        $payload = $this->payload( [ 'merchant_email' => '' ], $order, '000117' );

        $this->assertSame( '1106', $payload['detail']['reference'] );
        $this->assertSame( '000117', $payload['detail']['invoice_number'] );
        $this->assertSame( '25.00', $payload['amount']['breakdown']['discount']['invoice_discount']['amount']['value'] );
        $this->assertArrayNotHasKey( 'invoice_number', $this->payload( [ 'merchant_email' => '' ] )['detail'], 'PayPal numbers it when there is no PDF number' );
        $this->assertArrayNotHasKey( 'amount', $this->payload( [ 'merchant_email' => '' ] ), 'no credit, no discount' );
    }

    public function test_request_ids_repeat_for_a_retry_and_change_for_a_new_invoice() {
        $body  = [ 'detail' => [ 'reference' => '1106' ] ];
        $first = Aragrow_WC_PayPal_Auto_Invoicer::request_key( 'POST', '/v2/invoicing/invoices', $body, 'https://site.test', '1106:0' );

        $this->assertSame( $first, Aragrow_WC_PayPal_Auto_Invoicer::request_key( 'POST', '/v2/invoicing/invoices', $body, 'https://site.test', '1106:0' ), 'a retry after a timeout' );
        $this->assertNotSame( $first, Aragrow_WC_PayPal_Auto_Invoicer::request_key( 'POST', '/v2/invoicing/invoices', $body, 'https://site.test', '1106:1' ), 'a new invoice after a cancel' );
        $this->assertNotSame( $first, Aragrow_WC_PayPal_Auto_Invoicer::request_key( 'POST', '/v2/invoicing/invoices', $body, 'https://other.test', '1106:0' ), 'another site' );
        $this->assertSame( 36, strlen( $first ) );
    }
}
