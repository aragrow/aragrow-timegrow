<?php
use PHPUnit\Framework\TestCase;

/**
 * Rate order: project, then client (user meta), then company. Then a line's own rate.
 */
class HourlyRateTest extends TestCase {

    private $integration;

    protected function setUp(): void {
        TimeGrow_Test_State::reset();
        $this->integration = new Aragrow_WOO_Integration_Plugins();
    }

    public function test_project_rate_wins() {
        TimeGrow_Test_State::$projects_by_product[10] = [ 'client_id' => 5, 'default_flat_fee' => '75.00' ];
        TimeGrow_Test_State::$user_meta[5]['timegrow_default_flat_fee'] = '100';
        TimeGrow_Test_State::$company_rate = 150;

        $this->assertSame( 75.0, $this->integration->get_hours_rate( 10 ) );
    }

    public function test_client_rate_when_project_has_none() {
        TimeGrow_Test_State::$projects_by_product[10] = [ 'client_id' => 5, 'default_flat_fee' => '0.00' ];
        TimeGrow_Test_State::$user_meta[5]['timegrow_default_flat_fee'] = '100';
        TimeGrow_Test_State::$company_rate = 150;

        $this->assertSame( 100.0, $this->integration->get_hours_rate( 10 ) );
    }

    public function test_company_rate_when_project_and_client_have_none() {
        TimeGrow_Test_State::$projects_by_product[10] = [ 'client_id' => 5, 'default_flat_fee' => '0.00' ];
        TimeGrow_Test_State::$company_rate = 150;

        $this->assertSame( 150.0, $this->integration->get_hours_rate( 10 ) );
    }

    public function test_order_customer_used_when_no_project_matches() {
        TimeGrow_Test_State::$user_meta[8]['timegrow_default_flat_fee'] = '90';
        TimeGrow_Test_State::$company_rate = 150;

        $this->assertSame( 90.0, $this->integration->get_hours_rate( 999, 8 ) );
    }

    public function test_zero_when_no_rate_is_set() {
        $this->assertSame( 0.0, $this->integration->get_hours_rate( 999 ) );
    }

    public function test_line_rate_prefers_the_stored_rate() {
        $item  = new WC_Order_Item_Product( 1, 300, 300, 10, [ '_timegrow_project_hours' => '2.00', '_timegrow_line_rate' => '120' ] );
        $order = TimeGrow_Fake_Order::hours_invoice( 1, [ 101 => $item ] );

        $this->assertSame( 120.0, $this->integration->get_line_rate( $order, $item ) );
    }

    public function test_line_rate_from_price_and_hours_when_nothing_stored() {
        $item  = new WC_Order_Item_Product( 1, 281.25, 281.25, 10, [ '_timegrow_project_hours' => '3.75' ] );
        $order = TimeGrow_Fake_Order::hours_invoice( 1, [ 101 => $item ] );

        $this->assertSame( 75.0, $this->integration->get_line_rate( $order, $item ) );
    }

    public function test_line_rate_falls_back_to_rate_order_without_hours() {
        TimeGrow_Test_State::$company_rate = 150;
        $item  = new WC_Order_Item_Product( 1, 0, 0, 10 );
        $order = TimeGrow_Fake_Order::hours_invoice( 1, [ 101 => $item ] );

        $this->assertSame( 150.0, $this->integration->get_line_rate( $order, $item ) );
    }

    public function test_line_rate_uses_unit_price_for_a_hand_added_line() {
        $item  = new WC_Order_Item_Product( 2, 50, 50, 999 );
        $order = TimeGrow_Fake_Order::hours_invoice( 1, [ 101 => $item ] );

        $this->assertSame( 25.0, $this->integration->get_line_rate( $order, $item ) );
    }
}
