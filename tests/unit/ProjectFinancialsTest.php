<?php
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/modules/core/includes/controllers/reports/TimeGrowReportsProjectFinancialsTrait.php';

class ProjectFinancialsHost {
    use TimeGrowReportsProjectFinancialsTrait;
}

/** Project Financials: value, billed / not billed, net and budget used. */
class ProjectFinancialsTest extends TestCase {

    private function row( array $a ) {
        return ProjectFinancialsHost::project_financials( (object) array_merge( [ 'hours' => 0, 'billable_hours' => 0, 'billed_hours' => 0, 'unbilled_hours' => 0,
            'all_hours' => 0, 'rate' => 100, 'expenses' => 0, 'estimate_hours' => 0 ], $a ) );
    }

    public function test_money() {
        $r = $this->row( [ 'hours' => 12, 'billable_hours' => 10, 'billed_hours' => 6, 'unbilled_hours' => 4, 'rate' => 120, 'expenses' => 250.50 ] );
        $this->assertSame( [ 1200.0, 720.0, 480.0, 949.5 ], [ $r->value, $r->billed, $r->unbilled, $r->net ], 'value = billable hours x rate; net = value - expenses' );
        $this->assertSame( -80.0, $this->row( [ 'expenses' => 80 ] )->net, 'expenses only: a loss' );
    }

    public function test_budget() {
        $this->assertSame( 50.0, $this->row( [ 'all_hours' => 20, 'estimate_hours' => 40 ] )->budget_used );
        $this->assertNull( $this->row( [ 'all_hours' => 20 ] )->budget_used, 'no estimate' );
        $this->assertSame( '', ProjectFinancialsHost::budget_state( 89.9 ) );
        $this->assertSame( 'near', ProjectFinancialsHost::budget_state( 90.0 ) );
        $this->assertSame( 'over', ProjectFinancialsHost::budget_state( 100.1 ) );
        $this->assertSame( '', ProjectFinancialsHost::budget_state( null ) );
    }

    public function test_totals() {
        $t = ProjectFinancialsHost::project_financial_totals( [
            $this->row( [ 'hours' => 5, 'billable_hours' => 5, 'billed_hours' => 5, 'all_hours' => 50, 'estimate_hours' => 40 ] ),
            $this->row( [ 'hours' => 3, 'billable_hours' => 2, 'unbilled_hours' => 2, 'all_hours' => 19, 'estimate_hours' => 20, 'expenses' => 30 ] ),
        ] );
        $this->assertSame( [ 8.0, 700.0, 500.0, 200.0, 30.0, 670.0, 1, 1 ], [ $t['hours'], $t['value'], $t['billed'], $t['unbilled'], $t['expenses'], $t['net'], $t['over'], $t['near'] ] );
    }
}
