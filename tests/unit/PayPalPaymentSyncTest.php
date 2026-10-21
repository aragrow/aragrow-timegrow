<?php
use PHPUnit\Framework\TestCase;

/**
 * What a PayPal invoice status means for the WooCommerce order.
 */
class PayPalPaymentSyncTest extends TestCase {

    private function invoice( $status, $paid = null, array $transactions = [] ) {
        $invoice = [ 'status' => $status, 'amount' => [ 'value' => '581.25' ] ];
        if ( $paid !== null ) {
            $invoice['payments'] = [ 'paid_amount' => [ 'value' => $paid ], 'transactions' => $transactions ];
        }
        return $invoice;
    }

    public function test_next_batch_takes_the_orders_tried_longest_ago() {
        $order = function( $id, $tried ) {
            return new TimeGrow_Fake_Order( $id, [], $tried === null ? [] : [ '_paypal_sync_tried' => (string) $tried ] );
        };
        $orders = [ $order( 1, 3000 ), $order( 2, 1000 ), $order( 3, null ), $order( 4, 2000 ), $order( 5, null ) ];

        $ids = array_map( function( $o ) { return $o->get_id(); }, Aragrow_TimeGrow_PayPal_Payment_Sync::next_batch( $orders, 3 ) );

        $this->assertSame( [ 3, 5, 2 ], $ids ); // never tried (oldest order first), then tried longest ago
    }

    public function test_paid_invoice_marks_the_order_paid_with_payment_details() {
        $invoice = $this->invoice( 'PAID', '581.25', [
            [ 'payment_id' => '7AB12345CD678901E', 'payment_date' => '2026-10-15 PDT' ],
        ] );

        $d = Aragrow_TimeGrow_PayPal_Payment_Sync::decide( $invoice, 'inv_sent2paypal' );

        $this->assertSame( 'paid', $d['action'] );
        $this->assertSame( 581.25, $d['amount'] );
        $this->assertSame( '2026-10-15', $d['date'] );
        $this->assertSame( '7AB12345CD678901E', $d['transaction_id'] );
    }

    public function test_paid_without_payment_details_uses_the_invoice_amount() {
        $d = Aragrow_TimeGrow_PayPal_Payment_Sync::decide( $this->invoice( 'MARKED_AS_PAID' ), 'inv_sent2paypal' );

        $this->assertSame( 'paid', $d['action'] );
        $this->assertSame( 581.25, $d['amount'] );
    }

    public function test_already_paid_order_is_not_updated_again() {
        $d = Aragrow_TimeGrow_PayPal_Payment_Sync::decide( $this->invoice( 'PAID', '581.25' ), 'invoice_paid' );

        $this->assertSame( 'none', $d['action'] );
    }

    public function test_partial_payment_records_the_amount_paid() {
        $d = Aragrow_TimeGrow_PayPal_Payment_Sync::decide( $this->invoice( 'PARTIALLY_PAID', '200.00' ), 'inv_sent2paypal' );

        $this->assertSame( 'partial', $d['action'] );
        $this->assertSame( 200.0, $d['amount'] );
    }

    public function test_same_partial_amount_is_not_recorded_twice() {
        $d = Aragrow_TimeGrow_PayPal_Payment_Sync::decide( $this->invoice( 'PARTIALLY_PAID', '200.00' ), 'partial_paid', 200.0 );

        $this->assertSame( 'none', $d['action'] );
    }

    public function test_a_second_partial_payment_updates_the_amount() {
        $d = Aragrow_TimeGrow_PayPal_Payment_Sync::decide( $this->invoice( 'PARTIALLY_PAID', '350.00' ), 'partial_paid', 200.0 );

        $this->assertSame( 'partial', $d['action'] );
        $this->assertSame( 350.0, $d['amount'] );
    }

    public function test_cancelled_or_refunded_adds_a_note_once() {
        $first = Aragrow_TimeGrow_PayPal_Payment_Sync::decide( $this->invoice( 'CANCELLED' ), 'inv_sent2paypal', 0, 'SENT' );
        $again = Aragrow_TimeGrow_PayPal_Payment_Sync::decide( $this->invoice( 'CANCELLED' ), 'inv_sent2paypal', 0, 'CANCELLED' );
        $refund = Aragrow_TimeGrow_PayPal_Payment_Sync::decide( $this->invoice( 'REFUNDED' ), 'invoice_paid', 0, 'PAID' );

        $this->assertSame( 'note', $first['action'] );
        $this->assertSame( 'none', $again['action'] );
        $this->assertSame( 'note', $refund['action'] );
    }

    public function test_open_invoices_change_nothing() {
        foreach ( [ 'DRAFT', 'SENT', 'SCHEDULED', 'UNPAID', 'PAYMENT_PENDING' ] as $status ) {
            $d = Aragrow_TimeGrow_PayPal_Payment_Sync::decide( $this->invoice( $status ), 'inv_sent2paypal' );
            $this->assertSame( 'none', $d['action'], $status );
            $this->assertSame( $status, $d['paypal_status'] );
        }
    }
}
