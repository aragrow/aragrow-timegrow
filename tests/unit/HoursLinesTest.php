<?php
use PHPUnit\Framework\TestCase;

/**
 * Saving lines on an hours invoice: quantity stays 1, the total is hours x the line's rate,
 * and lines are only repriced when their hours change.
 */
class HoursLinesTest extends TestCase {

    private $integration;

    protected function setUp(): void {
        TimeGrow_Test_State::reset();
        $this->integration = new Aragrow_WOO_Integration_Plugins();
    }

    private function save_lines( $order, array $hours_by_line ) {
        $this->integration->apply_hours_after_items_save( $order->get_id(), [ 'timegrow_line_hours' => $hours_by_line ] );
    }

    public function test_changing_hours_reprices_at_the_line_rate() {
        $item  = new WC_Order_Item_Product( 1, 281.25, 281.25, 10, [ '_timegrow_project_hours' => '3.75' ] );
        $order = TimeGrow_Fake_Order::hours_invoice( 1, [ 101 => $item ] );

        $this->save_lines( $order, [ 101 => '5' ] );

        $this->assertSame( '5.00', $item->get_meta( '_timegrow_project_hours' ) );
        $this->assertEquals( 375.0, $item->get_subtotal() ); // 5 x 75
        $this->assertEquals( 375.0, $item->get_total() );
        $this->assertSame( '75', $item->get_meta( '_timegrow_line_rate' ) );
        $this->assertEquals( 1, $item->get_quantity() );
        $this->assertSame( 1, $order->totals_calculated );
        $this->assertCount( 1, $order->notes );
    }

    public function test_unchanged_hours_never_reprice_even_if_rates_changed() {
        TimeGrow_Test_State::$company_rate = 500; // rate went up after the invoice was made
        $item  = new WC_Order_Item_Product( 1, 281.25, 281.25, 10, [ '_timegrow_project_hours' => '3.75' ] );
        $order = TimeGrow_Fake_Order::hours_invoice( 1, [ 101 => $item ] );

        $this->save_lines( $order, [ 101 => '3.75' ] );

        $this->assertEquals( 281.25, $item->get_subtotal() );
        $this->assertSame( 0, $order->totals_calculated );
        $this->assertCount( 0, $order->notes );
    }

    public function test_quantity_is_set_to_one_without_repricing() {
        $item  = new WC_Order_Item_Product( 3.75, 281.25, 281.25, 10, [ '_timegrow_project_hours' => '3.75' ] );
        $order = TimeGrow_Fake_Order::hours_invoice( 1, [ 101 => $item ] );

        $this->save_lines( $order, [ 101 => '3.75' ] );

        $this->assertEquals( 1, $item->get_quantity() );
        $this->assertEquals( 281.25, $item->get_subtotal() );
    }

    public function test_discount_ratio_is_kept() {
        // 10% discount: total is 90% of subtotal
        $item  = new WC_Order_Item_Product( 1, 200, 180, 10, [ '_timegrow_project_hours' => '2.00', '_timegrow_line_rate' => '100' ] );
        $order = TimeGrow_Fake_Order::hours_invoice( 1, [ 101 => $item ] );

        $this->save_lines( $order, [ 101 => '4' ] );

        $this->assertEquals( 400.0, $item->get_subtotal() );
        $this->assertEquals( 360.0, $item->get_total() );
    }

    public function test_hand_edited_price_becomes_the_line_rate() {
        // Stored rate 100 for 2 hours, but the price was edited by hand to 180
        $item  = new WC_Order_Item_Product( 1, 180, 180, 10, [ '_timegrow_project_hours' => '2.00', '_timegrow_line_rate' => '100' ] );
        $order = TimeGrow_Fake_Order::hours_invoice( 1, [ 101 => $item ] );

        $this->save_lines( $order, [ 101 => '2' ] );
        $this->assertSame( '90', $item->get_meta( '_timegrow_line_rate' ) );

        // The next hours change uses the edited rate
        $this->save_lines( $order, [ 101 => '3' ] );
        $this->assertEquals( 270.0, $item->get_subtotal() );
    }

    public function test_normal_rounding_is_not_treated_as_an_edit() {
        // 62.50 x 0.33 = 20.625, stored as 20.63
        $item  = new WC_Order_Item_Product( 1, 20.63, 20.63, 10, [ '_timegrow_project_hours' => '0.33', '_timegrow_line_rate' => '62.5' ] );
        $order = TimeGrow_Fake_Order::hours_invoice( 1, [ 101 => $item ] );

        $this->save_lines( $order, [ 101 => '0.33' ] );

        $this->assertSame( '62.5', $item->get_meta( '_timegrow_line_rate' ) );
    }

    public function test_zero_hours_keeps_the_line_rate() {
        $item  = new WC_Order_Item_Product( 1, 300, 300, 10, [ '_timegrow_project_hours' => '2.00', '_timegrow_line_rate' => '150' ] );
        $order = TimeGrow_Fake_Order::hours_invoice( 1, [ 101 => $item ] );

        $this->save_lines( $order, [ 101 => '0' ] );
        $this->assertEquals( 0.0, $item->get_subtotal() );

        $this->save_lines( $order, [ 101 => '2' ] );
        $this->assertEquals( 300.0, $item->get_subtotal() );
    }

    public function test_regular_orders_are_left_alone() {
        $item  = new WC_Order_Item_Product( 3, 30, 30, 10, [ '_timegrow_project_hours' => '3.00' ] );
        $order = new TimeGrow_Fake_Order( 1, [ 101 => $item ] ); // no hours flag

        $this->save_lines( $order, [ 101 => '8' ] );

        $this->assertEquals( 3, $item->get_quantity() );
        $this->assertEquals( 30, $item->get_subtotal() );
        $this->assertSame( 0, $item->saved );
    }
}
