<?php
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/modules/mobile/includes/TimeGrowMobileAccess.php';
require_once dirname( __DIR__, 2 ) . '/modules/mobile/includes/TimeGrowMobileAccessControl.php';
require_once dirname( __DIR__, 2 ) . '/modules/mobile/includes/models/TimeGrowMobilePINModel.php';
require_once dirname( __DIR__, 2 ) . '/modules/mobile/includes/TimeGrowPINAuthenticator.php';
require_once dirname( __DIR__, 2 ) . '/modules/mobile/includes/TimeGrowMobile2FA.php';

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

    public function test_the_pin_login_is_for_team_members_only() {
        $this->assertTrue( TimeGrowMobileAccess::pin_roles_allowed( [ 'team_member' ] ) );
        $this->assertFalse( TimeGrowMobileAccess::pin_roles_allowed( [ 'administrator' ] ), 'administrators use the password login' );
        $this->assertFalse( TimeGrowMobileAccess::pin_roles_allowed( [ 'administrator', 'team_member' ] ) );
        $this->assertFalse( TimeGrowMobileAccess::pin_roles_allowed( [ 'customer' ] ) );
    }

    public function test_pages_are_the_daily_tasks_time_entry_method_first() {
        $this->assertSame( [ 'timegrow-nexus-clock', 'timegrow-nexus-manual', 'timegrow-nexus-expenses' ], TimeGrowMobileAccess::pages_for( true, true, 'clock' ) );
        $this->assertSame( [ 'timegrow-nexus-manual', 'timegrow-nexus-clock' ], TimeGrowMobileAccess::pages_for( true, false, 'manual' ) );
        $this->assertSame( [ 'timegrow-nexus-expenses' ], TimeGrowMobileAccess::pages_for( false, true, '' ) );
        $this->assertSame( [], TimeGrowMobileAccess::pages_for( false, false, 'clock' ) );
    }

    public function test_a_mobile_session_reaches_only_its_pages_receipts_and_ajax_actions() {
        $pages = TimeGrowMobileAccess::pages_for( true, true, 'clock' );
        $d = function ( $script, $get, $time = true ) use ( $pages ) { return TimeGrowMobileAccessControl::decide( $script, $get, $pages, $time ); };
        $this->assertSame( 'allow', $d( 'admin.php', [ 'page' => 'timegrow-nexus-expenses' ] ) );
        foreach ( [ 'timegrow-nexus-reports', 'timegrow-nexus-a-report', 'timegrow-nexus', 'timegrow-settings', 'timegrow-expenses-list' ] as $page ) {
            $this->assertSame( 'redirect', $d( 'admin.php', [ 'page' => $page ] ), $page );
        }
        foreach ( [ 'profile.php', 'index.php', 'users.php', 'plugins.php', 'options-general.php' ] as $script ) {
            $this->assertSame( 'redirect', $d( $script, [] ), $script );
        }
        $this->assertSame( 'allow', $d( 'admin-post.php', [ 'action' => 'timegrow_view_receipt' ] ), 'opening their receipts' );
        $this->assertSame( 'refuse', $d( 'admin-post.php', [ 'action' => 'timegrow_export_csv' ] ) );
        foreach ( [ 'save_time_entry', 'timegrow_nexus_save_expense', 'analyze_receipt_realtime', 'timegrow_voice_parse', 'heartbeat' ] as $action ) {
            $this->assertSame( 'allow', $d( 'admin-ajax.php', [ 'action' => $action ] ), $action );
        }
        foreach ( [ 'save_project', 'save_client', 'delete_nexus', 'timegrow_generate_pin', '' ] as $action ) {
            $this->assertSame( 'refuse', $d( 'admin-ajax.php', [ 'action' => $action ] ), $action );
        }
        $this->assertSame( 'refuse', $d( 'admin-ajax.php', [ 'action' => 'save_time_entry' ], false ), 'saving time needs the Time tracking capability' );
    }

    public function test_pins_saved_before_220_still_match() {
        $record = (object) [ 'pin_salt' => 'abc', 'pin_hash' => hash( 'sha256', 'K7P2QX' . 'abc' ) ];
        $this->assertTrue( TimeGrowPINAuthenticator::pin_matches( 'K7P2QX', $record ) );
        $this->assertFalse( TimeGrowPINAuthenticator::pin_matches( 'K7P2QY', $record ) );
    }

    public function test_generated_pins_avoid_i_and_o() {
        $this->assertSame( 0, preg_match( '/[IO]/', TimeGrowPINAuthenticator::PIN_CHARS ) );
        $this->assertSame( 34, strlen( TimeGrowPINAuthenticator::PIN_CHARS ) );
    }

    public function test_without_wordfence_login_security_the_pin_login_has_no_code_step() {
        $user = (object) [ 'ID' => 20, 'roles' => [ 'team_member' ] ];
        $this->assertFalse( TimeGrowMobile2FA::active() );
        $this->assertFalse( TimeGrowMobile2FA::enabled_for( $user ) );
        $this->assertFalse( TimeGrowMobile2FA::setup_missing( $user ), 'nothing to set up' );
        $this->assertFalse( TimeGrowMobile2FA::verify( $user, '123456' ), 'no code is ever accepted without it' );
        $this->assertSame( '', TimeGrowMobile2FA::status_text( $user ) );
    }
}
