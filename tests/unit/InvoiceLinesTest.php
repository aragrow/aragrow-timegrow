<?php
use PHPUnit\Framework\TestCase;

/**
 * Numbers sent to PayPal and printed on the PDF invoice.
 */
class InvoiceLinesTest extends TestCase {

    private $paypal;

    protected function setUp(): void {
        TimeGrow_Test_State::reset();
        $this->paypal = Aragrow_WC_PayPal_Auto_Invoicer::instance();
    }

    // ---- PayPal

    public function test_paypal_hours_line_sends_hours_and_hourly_rate() {
        $item  = new WC_Order_Item_Product( 1, 281.25, 281.25, 10, [ '_timegrow_project_hours' => '3.75' ] );
        $order = TimeGrow_Fake_Order::hours_invoice( 1, [ 101 => $item ] );

        $this->assertEquals( [ 3.75, 75.0 ], $this->paypal->line_quantity_and_rate( $order, $item ) );
    }

    public function test_paypal_regular_line_sends_quantity_and_unit_price() {
        $item  = new WC_Order_Item_Product( 4, 100, 100, 10 );
        $order = new TimeGrow_Fake_Order( 1, [ 101 => $item ] );

        $this->assertEquals( [ 4.0, 25.0 ], $this->paypal->line_quantity_and_rate( $order, $item ) );
    }

    public function test_paypal_ignores_stored_hours_on_a_regular_order() {
        $item  = new WC_Order_Item_Product( 2, 50, 50, 10, [ '_timegrow_project_hours' => '9.00' ] );
        $order = new TimeGrow_Fake_Order( 1, [ 101 => $item ] );

        $this->assertEquals( [ 2.0, 25.0 ], $this->paypal->line_quantity_and_rate( $order, $item ) );
    }

    public function test_paypal_charge_lines_only_when_above_zero() {
        $order = new TimeGrow_Fake_Order( 1 );
        $this->assertSame( [], $this->paypal->build_charge_lines( $order ) );

        $order->shipping_total = 12.5;
        $order->total_tax      = 7;
        $lines = $this->paypal->build_charge_lines( $order );

        $this->assertCount( 2, $lines );
        $this->assertSame( 'Shipping', $lines[0]['name'] );
        $this->assertSame( '12.50', $lines[0]['unit_amount']['value'] );
        $this->assertSame( 'Tax', $lines[1]['name'] );
        $this->assertSame( '7.00', $lines[1]['unit_amount']['value'] );
    }

    public function test_paypal_fee_becomes_a_line_and_credit_a_discount() {
        $order = new TimeGrow_Fake_Order( 1 );
        $order->fees = [ new TimeGrow_Fake_Fee( 'Rush fee', 40 ), new TimeGrow_Fake_Fee( 'Loyalty credit', -15.5 ), new TimeGrow_Fake_Fee( '', 5 ) ];
        $lines = $this->paypal->build_charge_lines( $order );

        $this->assertCount( 2, $lines );
        $this->assertSame( 'Rush fee', $lines[0]['name'] );
        $this->assertSame( '40.00', $lines[0]['unit_amount']['value'] );
        $this->assertSame( 'Fee', $lines[1]['name'] );
        $this->assertSame( 15.5, $this->paypal->fee_credit( $order ) );
    }

    public function test_paypal_no_credit_without_negative_fees() {
        $order = new TimeGrow_Fake_Order( 1 );
        $this->assertSame( 0.0, $this->paypal->fee_credit( $order ) );
        $order->fees = [ new TimeGrow_Fake_Fee( 'Rush fee', 40 ) ];
        $this->assertSame( 0.0, $this->paypal->fee_credit( $order ) );
    }

    // ---- PDF invoice

    public function test_pdf_hours_line_shows_hours_and_rate() {
        $item  = new WC_Order_Item_Product( 1, 300, 300, 10, [ '_timegrow_project_hours' => '2.00' ] );
        $order = TimeGrow_Fake_Order::hours_invoice( 1, [ 101 => $item ] );

        $values = Aragrow_WOO_Integration_Plugins::invoice_line_values( $order, $item, 1 );

        $this->assertTrue( $values['is_hours_line'] );
        $this->assertSame( '2.00', $values['qty_display'] );
        $this->assertEquals( 150.0, $values['unit_price'] );
    }

    public function test_pdf_regular_line_shows_quantity_and_unit_price() {
        $item  = new WC_Order_Item_Product( 3, 45, 45, 10 );
        $order = new TimeGrow_Fake_Order( 1, [ 101 => $item ] );

        $values = Aragrow_WOO_Integration_Plugins::invoice_line_values( $order, $item, 3 );

        $this->assertFalse( $values['is_hours_line'] );
        $this->assertSame( 3, $values['qty_display'] );
        $this->assertEquals( 15.0, $values['unit_price'] );
    }

    public function test_pdf_keeps_decimal_quantities() {
        $item  = new WC_Order_Item_Product( 2.5, 25, 25, 10 );
        $order = new TimeGrow_Fake_Order( 1, [ 101 => $item ] );

        $values = Aragrow_WOO_Integration_Plugins::invoice_line_values( $order, $item, 2.5 );

        $this->assertSame( 2.5, $values['qty_display'] );
        $this->assertEquals( 10.0, $values['unit_price'] );
    }
}
