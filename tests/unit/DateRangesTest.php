<?php
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowDateRanges.php';

/** Report quick ranges: weeks start on Monday, months and years are whole. */
class DateRangesTest extends TestCase {

    const TODAY = '2026-10-18'; // a Sunday

    private function range( $key, $today = self::TODAY ) {
        $p = TimeGrowDateRanges::presets( $today )[ $key ];
        return [ $p[1], $p[2] ];
    }

    public function test_presets() {
        $this->assertSame( [ '2026-10-18', '2026-10-18' ], $this->range( 'today' ) );
        $this->assertSame( [ '2026-10-17', '2026-10-17' ], $this->range( 'yesterday' ) );
        $this->assertSame( [ '2026-10-12', '2026-10-18' ], $this->range( 'this_week' ), 'Sunday belongs to the week that started Monday' );
        $this->assertSame( [ '2026-10-05', '2026-10-11' ], $this->range( 'last_week' ) );
        $this->assertSame( [ '2026-10-01', '2026-10-18' ], $this->range( 'this_month' ) );
        $this->assertSame( [ '2026-09-01', '2026-09-30' ], $this->range( 'last_month' ) );
        $this->assertSame( [ '2026-08-01', '2026-10-18' ], $this->range( 'last_3_months' ) );
        $this->assertSame( [ '2026-01-01', '2026-10-18' ], $this->range( 'this_year' ) );
        $this->assertSame( [ '2025-01-01', '2025-12-31' ], $this->range( 'last_year' ) );
    }

    public function test_month_edges() {
        $this->assertSame( [ '2026-02-01', '2026-02-28' ], $this->range( 'last_month', '2026-03-31' ), 'not March 3 from "-1 month"' );
        $this->assertSame( [ '2025-12-01', '2025-12-31' ], $this->range( 'last_month', '2026-01-15' ) );
        $this->assertSame( [ '2025-11-01', '2026-01-31' ], $this->range( 'last_3_months', '2026-01-31' ) );
    }

    public function test_the_current_preset_is_found() {
        $this->assertSame( 'last_week', TimeGrowDateRanges::active( '2026-10-05', '2026-10-11', self::TODAY ) );
        $this->assertSame( '', TimeGrowDateRanges::active( '2026-10-05', '2026-10-10', self::TODAY ) );
        $this->assertSame( '', TimeGrowDateRanges::active( '', '', self::TODAY ) );
    }
}
