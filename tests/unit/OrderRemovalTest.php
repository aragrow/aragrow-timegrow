<?php
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/modules/core/includes/TimeGrowTimeEntry.php';

/** What happens to an invoice's time when the order is trashed, restored or deleted. */
class OrderRemovalTest extends TestCase {

    public function test_trash_and_restore_keep_the_time_billed() {
        foreach ( [ 'processing', 'invoice_sent', 'invoice_paid', 'partial_paid' ] as $status ) {
            $this->assertSame( 'keep', TimeGrowTimeEntry::on_order_removed( 'trash', $status, 0 ), $status );
            $this->assertSame( 'keep', TimeGrowTimeEntry::on_order_removed( 'restore', $status, 0 ), $status );
        }
    }

    public function test_deleting_a_paid_invoice_never_frees_its_time() {
        $this->assertSame( 'detach', TimeGrowTimeEntry::on_order_removed( 'delete', 'invoice_paid', 0 ) );
        $this->assertSame( 'detach', TimeGrowTimeEntry::on_order_removed( 'delete', 'partial_paid', 0 ) );
        $this->assertSame( 'detach', TimeGrowTimeEntry::on_order_removed( 'delete', 'processing', '250.00' ), 'a manual payment counts' );
    }

    public function test_deleting_an_unpaid_invoice_frees_its_time() {
        foreach ( [ 'pending', 'processing', 'invoice_sent', 'inv_sent2paypal', 'inv_draft2paypal' ] as $status ) {
            $this->assertSame( 'release', TimeGrowTimeEntry::on_order_removed( 'delete', $status, '' ), $status );
        }
    }
}
