<?php
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/modules/mobile/includes/TimeGrowMobileAccess.php';

/**
 * Remote access (mobile PIN login, app token): only administrators and team members.
 */
class MobileAccessTest extends TestCase {

    public function test_only_administrators_and_team_members() {
        $this->assertTrue( TimeGrowMobileAccess::roles_allowed( [ 'administrator' ] ) );
        $this->assertTrue( TimeGrowMobileAccess::roles_allowed( [ 'team_member' ] ) );
        $this->assertTrue( TimeGrowMobileAccess::roles_allowed( [ 'subscriber', 'team_member' ] ), 'any of their roles counts' );
        foreach ( [ [ 'customer' ], [ 'subscriber' ], [ 'mobile_user' ], [ 'editor' ], [] ] as $roles ) {
            $this->assertFalse( TimeGrowMobileAccess::roles_allowed( $roles ), json_encode( $roles ) );
        }
    }

    public function test_mobile_capabilities_include_ai_voice_and_receipts() {
        $caps = array_keys( TimeGrowMobileAccess::capabilities() );
        $this->assertSame( [ 'access_mobile_time_tracking', 'access_mobile_expenses', 'access_mobile_ai_voice', 'access_mobile_ai_receipts' ], $caps );
        $this->assertTrue( TimeGrowMobileAccess::is_capability( 'access_mobile_ai_voice' ) );
        $this->assertFalse( TimeGrowMobileAccess::is_capability( 'manage_options' ), 'only mobile capabilities can be switched on the Mobile Access page' );
    }

    public function test_refusal_looks_like_a_wrong_pin() {
        $this->assertSame( 'Invalid username or PIN.', TimeGrowMobileAccess::refused_message() );
    }
}
