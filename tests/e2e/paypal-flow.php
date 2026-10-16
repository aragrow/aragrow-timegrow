<?php
/**
 * TimeGrow end-to-end test: team -> client -> projects -> time -> invoice -> PayPal sandbox.
 *
 * Runs inside your real WordPress site and CREATES REAL RECORDS (a client, two projects,
 * their WooCommerce products, time entries, an order and a PayPal sandbox invoice).
 * Every run makes a new random client, so runs never collide. The team members Maria,
 * Santiago and Eva are created on the first run and reused after that; the hours are
 * spread across them. Everything it creates is
 * tagged with the run ID (user meta _timegrow_e2e_run, project descriptions, order note).
 *
 * It stops after each phase and prints IDs, admin links and amounts so you can check
 * what was created before going on.
 *
 * Run from the plugin folder, in Local's site shell (Local > right-click the site > Open site shell):
 *
 *   wp eval-file tests/e2e/paypal-flow.php            pause after each phase
 *   wp eval-file tests/e2e/paypal-flow.php auto       no pauses
 *   wp eval-file tests/e2e/paypal-flow.php no-send    stop after the invoice is made (no-paypal works too)
 *   wp eval-file tests/e2e/paypal-flow.php delivery=email   how the client gets invoices:
 *                                     paypal (default), email, mail (print and mail) or random
 *   Options combine: wp eval-file tests/e2e/paypal-flow.php auto delivery=random
 *
 * At a pause: Enter = next phase, q = stop here (what was created so far stays).
 *
 * The new client is set to PayPal (Invoice Delivery on the profile) unless delivery= says
 * otherwise, and the run checks that the profile and the order both say so.
 * Phase 5 sends the invoice with the real "Send invoice" order action, which follows the
 * client's Invoice Delivery choice. Safety: when that is PayPal, refuses to run unless
 * Settings > PayPal Integration is set to Sandbox.
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'WP_CLI' ) ) {
	exit( "Run this with: wp eval-file tests/e2e/paypal-flow.php\n" );
}

$e2e_args  = isset( $args ) && is_array( $args ) ? array_map( 'strtolower', $args ) : [];
$e2e_auto  = in_array( 'auto', $e2e_args, true );
$e2e_pp    = ! in_array( 'no-paypal', $e2e_args, true ) && ! in_array( 'no-send', $e2e_args, true ); // send the invoice at all
$e2e_delivery = 'paypal';
foreach ( $e2e_args as $arg ) {
	if ( strpos( $arg, 'delivery=' ) === 0 ) $e2e_delivery = substr( $arg, 9 );
}
if ( $e2e_delivery === 'random' ) $e2e_delivery = [ 'paypal', 'email', 'mail' ][ wp_rand( 0, 2 ) ];
if ( ! in_array( $e2e_delivery, [ 'paypal', 'email', 'mail' ], true ) ) {
	WP_CLI::error( "Unknown delivery '{$e2e_delivery}'. Use paypal, email, mail or random." );
}
$e2e_run   = gmdate( 'md-His' ) . '-' . strtolower( wp_generate_password( 4, false ) );

// ---------------------------------------------------------------------------
// Output helpers
// ---------------------------------------------------------------------------

function e2e_heading( $text ) {
	WP_CLI::line( '' );
	WP_CLI::line( WP_CLI::colorize( '%B== ' . $text . ' ==%n' ) );
}

function e2e_kv( array $pairs ) {
	$width = max( array_map( 'strlen', array_keys( $pairs ) ) );
	foreach ( $pairs as $key => $value ) {
		WP_CLI::line( '  ' . str_pad( $key, $width ) . '  ' . $value );
	}
}

function e2e_check( $label, $ok, $detail = '' ) {
	WP_CLI::line( '  ' . ( $ok ? WP_CLI::colorize( '%GPASS%n' ) : WP_CLI::colorize( '%RFAIL%n' ) ) . '  ' . $label . ( $detail !== '' ? '  (' . $detail . ')' : '' ) );
	return $ok;
}

function e2e_pause( $next, $auto ) {
	if ( $auto ) return;
	WP_CLI::line( '' );
	fwrite( STDOUT, WP_CLI::colorize( "%YPress Enter to continue to: {$next}  (q to stop)%n " ) );
	$answer = fgets( STDIN );
	if ( $answer !== false && strtolower( trim( $answer ) ) === 'q' ) {
		WP_CLI::success( 'Stopped. Everything created so far was kept.' );
		WP_CLI::halt( 0 );
	}
}

function e2e_money( $amount ) {
	return number_format( (float) $amount, 2 );
}

// ---------------------------------------------------------------------------
// Phase 0: preflight
// ---------------------------------------------------------------------------

e2e_heading( "TimeGrow end-to-end test, run {$e2e_run}" );

$admin = get_users( [ 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID' ] );
if ( ! $admin ) WP_CLI::error( 'No administrator found.' );
wp_set_current_user( $admin[0]->ID );

foreach ( [ 'WooCommerce', 'TimeGrowProjectModel', 'TimeGrowProjectController', 'TimeGrowTimeEntryModel', 'TimeGrowWooOrderCreator', 'Aragrow_WC_PayPal_Auto_Invoicer', 'Aragrow_TimeGrow_PayPal_Payment_Sync' ] as $class ) {
	if ( ! class_exists( $class ) ) WP_CLI::error( "Missing {$class}: is WooCommerce active and TimeGrow loaded?" );
}

$invoicer    = Aragrow_WC_PayPal_Auto_Invoicer::instance();
$pp_settings = $invoicer->settings();
$integration = get_option( 'timegrow_integration_settings', [] );

global $wpdb;
$company_id = (int) $wpdb->get_var( 'SELECT ID FROM ' . $wpdb->prefix . TIMEGROW_PREFIX . 'company_tracker ORDER BY ID LIMIT 1' );
if ( ! $company_id ) WP_CLI::error( 'No company found. Add one under TimeGrow > Companies first.' );

e2e_kv( [
	'Site'               => home_url(),
	'Running as'         => $admin[0]->display_name . ' (user ' . $admin[0]->ID . ')',
	'Company rate'       => e2e_money( timegrow_company_default_rate() ),
	'Product sync'       => ! empty( $integration['wc_products'] ) ? 'on' : 'off (products are made by Process Time instead)',
	'PayPal environment' => $pp_settings['env'],
	'PayPal sending'     => $pp_settings['enabled'] === 'yes' ? 'on' : 'off',
	'PayPal delivery'    => $pp_settings['send_action'] === 'send' ? 'email the invoice' : 'save as draft',
	'PayPal keys'        => $invoicer->has_credentials() ? 'saved' : 'missing',
	'Invoice delivery'   => $e2e_pp ? Aragrow_TimeGrow_Invoice_Delivery::methods()[ $e2e_delivery ] . ' (the client\'s choice in this run)' : '(not sending)',
] );

if ( $e2e_pp && $e2e_delivery === 'paypal' ) {
	if ( $pp_settings['env'] !== 'sandbox' ) WP_CLI::error( 'PayPal is set to Live. This test only runs against the PayPal sandbox.' );
	if ( $pp_settings['enabled'] !== 'yes' ) WP_CLI::error( 'Turn on "Send orders to PayPal" in Settings > PayPal Integration, or run with no-paypal.' );
	if ( ! $invoicer->has_credentials() ) WP_CLI::error( 'Save your PayPal sandbox Client ID and Secret first, or run with no-paypal.' );
}

WP_CLI::warning( 'This creates real records in this site: a client, 2 projects, products, time entries and an order.' );
e2e_pause( 'Team members (Maria, Santiago, Eva)', $e2e_auto );

// ---------------------------------------------------------------------------
// Team members: Maria, Santiago and Eva (created once, reused on later runs)
// ---------------------------------------------------------------------------

e2e_heading( 'Team members' );

$member_model = new TimeGrowTeamMemberModel();
$member_table = $wpdb->prefix . TIMEGROW_PREFIX . 'team_member_tracker';
$team_specs   = [
	[ 'login' => 'maria',    'first' => 'Maria',    'last' => 'Gomez',   'title' => 'Project Manager' ],
	[ 'login' => 'santiago', 'first' => 'Santiago', 'last' => 'Herrera', 'title' => 'Web Developer' ],
	[ 'login' => 'eva',      'first' => 'Eva',      'last' => 'Larsen',  'title' => 'SEO Specialist' ],
];

$team = [];
foreach ( $team_specs as $spec ) {
	$email = $spec['login'] . '.team@example.com';
	$user  = get_user_by( 'login', $spec['login'] );
	$made  = [];

	if ( ! $user ) {
		$user_id = wp_insert_user( [
			'user_login'   => $spec['login'],
			'user_pass'    => wp_generate_password( 24 ),
			'user_email'   => $email,
			'first_name'   => $spec['first'],
			'last_name'    => $spec['last'],
			'display_name' => $spec['first'] . ' ' . $spec['last'],
			'role'         => get_role( 'team_member' ) ? 'team_member' : 'subscriber',
		] );
		if ( is_wp_error( $user_id ) ) WP_CLI::error( 'Could not create user ' . $spec['login'] . ': ' . $user_id->get_error_message() );
		$user   = get_user_by( 'id', $user_id );
		$made[] = 'user';
	}

	$member_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$member_table} WHERE user_id = %d", $user->ID ) );
	if ( ! $member_id ) {
		$member_id = $member_model->create( [
			'user_id'    => $user->ID,
			'company_id' => $company_id,
			'name'       => $spec['first'] . ' ' . $spec['last'],
			'email'      => $user->user_email,
			'title'      => $spec['title'],
			'status'     => 1,
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		], [ '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s' ] );
		if ( ! $member_id ) WP_CLI::error( 'Could not create team member ' . $spec['first'] . ': ' . $wpdb->last_error );
		$made[] = 'team member';
	}

	$team[] = [
		'Name'        => $spec['first'] . ' ' . $spec['last'],
		'Title'       => $spec['title'],
		'User ID'     => $user->ID,
		'Member ID'   => (int) $member_id,
		'Login'       => $user->user_login,
		'This run'    => $made ? 'created ' . implode( ' + ', $made ) : 'already existed',
	];
}

// Pick who works on this run's projects: one, two or all three, at random
$participants = $team;
shuffle( $participants );
$participants = array_slice( $participants, 0, wp_rand( 1, count( $participants ) ) );
$picked_names = array_column( $participants, 'Name' );
foreach ( $team as &$member ) {
	$member['In this run'] = in_array( $member['Name'], $picked_names, true ) ? 'yes' : 'no';
}
unset( $member );

WP_CLI\Utils\format_items( 'table', $team, [ 'Name', 'Title', 'User ID', 'Member ID', 'Login', 'This run', 'In this run' ] );
WP_CLI::line( '  Taking part this run: ' . implode( ', ', $picked_names ) );
WP_CLI::line( '  Check them: ' . admin_url( 'admin.php?page=' . TIMEGROW_PARENT_MENU . '-team-member-list' ) );

e2e_pause( 'Phase 1, create the client', $e2e_auto );

// ---------------------------------------------------------------------------
// Phase 1: client
// ---------------------------------------------------------------------------

e2e_heading( 'Phase 1: client' );

$first_names = [ 'Ana', 'Ben', 'Carla', 'Diego', 'Elena', 'Frank', 'Grace', 'Hugo', 'Iris', 'Jonas' ];
$last_names  = [ 'Ruiz', 'Okafor', 'Lindqvist', 'Moreau', 'Tanaka', 'Novak', 'Haddad', 'Silva', 'Kowalski', 'Brennan' ];
$companies   = [ 'Pinecrest Labs', 'Harbor & Vine', 'Northfield Dental', 'Bluegate Media', 'Copperleaf Studio', 'Summit Freight', 'Oakline Legal', 'Brightwater Co' ];

$first   = $first_names[ array_rand( $first_names ) ];
$last    = $last_names[ array_rand( $last_names ) ];
$is_company = (bool) wp_rand( 0, 1 ); // some runs bill a company, others a person
$company    = $is_company ? $companies[ array_rand( $companies ) ] . ' ' . strtoupper( substr( $e2e_run, -4 ) ) : '';
$client_name = $is_company ? $company : "{$first} {$last} " . strtoupper( substr( $e2e_run, -4 ) );
$email   = 'e2e+' . str_replace( '-', '', $e2e_run ) . '@example.com';
$rate_c  = (float) [ 85, 90, 95, 110 ][ array_rand( [ 85, 90, 95, 110 ] ) ];

$client_id = wp_insert_user( [
	'user_login'   => 'e2e_' . str_replace( '-', '_', $e2e_run ),
	'user_pass'    => wp_generate_password( 24 ),
	'user_email'   => $email,
	'first_name'   => $first,
	'last_name'    => $last,
	'display_name' => $client_name,
	'role'         => 'customer',
] );
if ( is_wp_error( $client_id ) ) WP_CLI::error( 'Could not create the client: ' . $client_id->get_error_message() );

$billing = [
	'billing_first_name' => $first,
	'billing_last_name'  => $last,
	'billing_company'    => $company,
	'billing_email'      => $email,
	'billing_phone'      => '555' . wp_rand( 1000000, 9999999 ),
	'billing_address_1'  => wp_rand( 100, 9999 ) . ' Main Street',
	'billing_city'       => 'Austin',
	'billing_state'      => 'TX',
	'billing_postcode'   => '78701',
	'billing_country'    => 'US',
];
foreach ( $billing as $key => $value ) update_user_meta( $client_id, $key, $value );
update_user_meta( $client_id, 'timegrow_default_flat_fee', $rate_c );
update_user_meta( $client_id, '_timegrow_e2e_run', $e2e_run );
update_user_meta( $client_id, Aragrow_TimeGrow_Invoice_Delivery::META, $e2e_delivery );

e2e_kv( [
	'Client'      => $client_name,
	'Type'        => $is_company ? 'Company' : 'Person',
	'User ID'     => $client_id,
	'Contact'     => $is_company ? "{$first} {$last}" : '(the client is the person)',
	'Company'     => $is_company ? $company : '(none)',
	'Email'       => $email,
	'Address'     => $billing['billing_address_1'] . ', Austin, TX 78701, US',
	'Client rate' => e2e_money( $rate_c ) . ' per hour (Default Flat Fee on the profile)',
	'Invoice delivery' => Aragrow_TimeGrow_Invoice_Delivery::methods()[ $e2e_delivery ] . ' (on the profile)',
	'Check it'    => admin_url( 'user-edit.php?user_id=' . $client_id ),
] );
$e2e_saved = Aragrow_TimeGrow_Invoice_Delivery::method_for_user( $client_id );
e2e_check( 'Client Invoice Delivery is ' . Aragrow_TimeGrow_Invoice_Delivery::methods()[ $e2e_delivery ], $e2e_saved === $e2e_delivery, $e2e_saved );

e2e_pause( 'Phase 2, create two projects', $e2e_auto );

// ---------------------------------------------------------------------------
// Phase 2: projects (and their WooCommerce products)
// ---------------------------------------------------------------------------

e2e_heading( 'Phase 2: projects' );

$project_model = new TimeGrowProjectModel();
$controller    = new TimeGrowProjectController( $project_model, new TimeGrowProjectView(), new TimeGrowClientModel(), new TimeGrowWooProductModel() );
$sync_product  = new ReflectionMethod( $controller, 'sync_client_product' ); // the same step the Add Project screen runs
$sync_product->setAccessible( true );

$project_specs = [
	[ 'name' => 'Website Redesign', 'rate' => 120.0, 'hours' => 18.0 ], // own rate: the project rate wins
	[ 'name' => 'SEO Audit',        'rate' => 0.0,   'hours' => 12.0 ], // no rate: the client rate is used
];

$projects = [];
$now      = current_time( 'mysql' );
foreach ( $project_specs as $spec ) {
	$data = [
		'client_id'        => $client_id,
		'name'             => $spec['name'],
		'description'      => "Created by TimeGrow E2E test run {$e2e_run}",
		'default_flat_fee' => $spec['rate'],
		'start_date'       => gmdate( 'Y-m-d', strtotime( '-30 days' ) ),
		'end_date'         => '',
		'estimate_hours'   => 40,
		'billable'         => 1,
		'status'           => 1,
		'product_id'       => 0,
		'created_at'       => $now,
		'updated_at'       => $now,
	];
	$data['product_id'] = (int) $sync_product->invoke( $controller, $data );
	$format = [ '%d', '%s', '%s', '%f', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s' ];
	$project_id = $project_model->create( $data, $format );
	if ( ! $project_id ) WP_CLI::error( 'Could not create project ' . $spec['name'] . ': ' . $wpdb->last_error );

	$expected_rate = $spec['rate'] > 0 ? $spec['rate'] : $rate_c;
	$projects[]    = $spec + [ 'id' => (int) $project_id, 'product_id' => $data['product_id'], 'expected_rate' => $expected_rate ];
}

WP_CLI\Utils\format_items( 'table', array_map( function ( $p ) {
	return [
		'Project'        => $p['name'],
		'ID'             => $p['id'],
		'Project rate'   => $p['rate'] > 0 ? e2e_money( $p['rate'] ) : '(none)',
		'Expected rate'  => e2e_money( $p['expected_rate'] ),
		'Product ID'     => $p['product_id'] ?: '(none yet)',
		'Product price'  => $p['product_id'] ? e2e_money( wc_get_product( $p['product_id'] )->get_regular_price() ) : '',
	];
}, $projects ), [ 'Project', 'ID', 'Project rate', 'Expected rate', 'Product ID', 'Product price' ] );

foreach ( $projects as $p ) {
	WP_CLI::line( '  Check ' . $p['name'] . ': ' . admin_url( 'admin.php?page=' . TIMEGROW_PARENT_MENU . '-project-edit&id=' . $p['id'] ) );
	if ( $p['product_id'] ) WP_CLI::line( '        product: ' . admin_url( 'post.php?post=' . $p['product_id'] . '&action=edit' ) );
}

e2e_pause( 'Phase 3, log about 30 hours over the last two weeks', $e2e_auto );

// ---------------------------------------------------------------------------
// Phase 3: time entries (weekdays in the last 14 days, manual and clock entries)
// ---------------------------------------------------------------------------

e2e_heading( 'Phase 3: time entries' );

$entry_model = new TimeGrowTimeEntryModel();
$weekdays    = [];
for ( $d = 1; $d <= 14; $d++ ) {
	$day = strtotime( "-{$d} days", current_time( 'timestamp' ) );
	if ( (int) gmdate( 'N', $day ) <= 5 ) $weekdays[] = gmdate( 'Y-m-d', $day );
}
$tasks = [ 'Kickoff call and notes', 'Wireframes for the home page', 'Content review', 'Keyword research', 'Fixing page speed issues', 'Client feedback round', 'Analytics setup', 'Writing recommendations' ];

$entries = [];
foreach ( $projects as $p ) {
	$left = $p['hours'];
	$i    = 0;
	while ( $left > 0.001 ) {
		$hours = min( $left, wp_rand( 4, 16 ) / 4 );        // 1.00 to 4.00 hours, in quarter hours
		if ( $left - $hours > 0 && $left - $hours < 1 ) $hours = $left; // no tiny leftover entry
		$left -= $hours;
		$date  = $weekdays[ array_rand( $weekdays ) ];
		$clock = ( $i++ % 3 === 2 );                          // every third entry is a clock entry
		$desc  = $tasks[ array_rand( $tasks ) ] . " (E2E {$e2e_run})";

		$who  = $participants[ array_rand( $participants ) ];
		$data = [
			'project_id'  => $p['id'],
			'member_id'   => $who['Member ID'],
			'billable'    => 1,
			'billed'      => 0,
			'description' => $desc,
			'created_at'  => $now,
			'updated_at'  => $now,
		];
		if ( $clock ) {
			$in  = strtotime( $date . ' 09:00:00' ) + wp_rand( 0, 8 ) * 900;
			$data += [ 'entry_type' => 'IN', 'clock_in_date' => gmdate( 'Y-m-d H:i:s', $in ), 'clock_out_date' => gmdate( 'Y-m-d H:i:s', $in + (int) round( $hours * 3600 ) ) ];
			$format = [ '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ];
		} else {
			$data += [ 'entry_type' => 'MAN', 'date' => $date . ' 00:00:00', 'hours' => $hours ];
			$format = [ '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%f' ];
		}
		$entry_id = $entry_model->create( $data, $format );
		if ( ! $entry_id ) WP_CLI::error( 'Could not create a time entry: ' . $wpdb->last_error );

		$entries[] = [ 'Entry ID' => $entry_id, 'Project' => $p['name'], 'Date' => $date, 'Who' => $who['Name'], 'Type' => $clock ? 'Clock' : 'Manual', 'Hours' => number_format( $hours, 2 ), 'Description' => $desc ];
	}
}

usort( $entries, function ( $a, $b ) { return strcmp( $a['Date'], $b['Date'] ); } );
WP_CLI\Utils\format_items( 'table', $entries, [ 'Entry ID', 'Date', 'Project', 'Who', 'Type', 'Hours', 'Description' ] );

$by_person = [];
foreach ( $entries as $e ) $by_person[ $e['Who'] ] = ( $by_person[ $e['Who'] ] ?? 0 ) + (float) $e['Hours'];
WP_CLI::line( '  Hours by person: ' . implode( ', ', array_map( function ( $name, $h ) { return $name . ' ' . number_format( $h, 2 ); }, array_keys( $by_person ), $by_person ) ) );

$expected_total = 0;
foreach ( $projects as $p ) {
	$amount          = round( $p['hours'] * $p['expected_rate'], 2 );
	$expected_total += $amount;
	WP_CLI::line( sprintf( '  %-18s %6s hours x %s = %s', $p['name'], number_format( $p['hours'], 2 ), e2e_money( $p['expected_rate'] ), e2e_money( $amount ) ) );
}
WP_CLI::line( sprintf( '  %-18s %6s hours            %s', 'Expected invoice', number_format( array_sum( array_column( $project_specs, 'hours' ) ), 2 ), e2e_money( $expected_total ) ) );
WP_CLI::line( '  Check them: ' . admin_url( 'admin.php?page=' . TIMEGROW_PARENT_MENU . '-time-entries-list' ) );

e2e_pause( 'Phase 4, process the time into an invoice', $e2e_auto );

// ---------------------------------------------------------------------------
// Phase 4: Process Time (same steps as the Process Time page, for this client only)
// ---------------------------------------------------------------------------

e2e_heading( 'Phase 4: process time into an invoice' );

$to_bill = $entry_model->get_time_entries_to_bill( $client_id, null, null );
WP_CLI::line( '  Billable, unbilled entries found for this client: ' . count( $to_bill ) );

$creator = new TimeGrowWooOrderCreator();
list( $order_ids, $billed_entries ) = $creator->create_woo_orders_and_products( $to_bill );
ob_start(); // marking prints HTML meant for the admin page
$entry_model->mark_time_entries_as_billed( $billed_entries );
ob_end_clean();

if ( empty( $order_ids ) ) WP_CLI::error( 'No order was created.' );
$order = wc_get_order( $order_ids[0] );
$order->add_order_note( "Created by TimeGrow E2E test run {$e2e_run}" );

$lines = [];
foreach ( $order->get_items() as $item ) {
	$lines[] = [
		'Line'      => $item->get_name(),
		'Hours'     => $item->get_meta( '_timegrow_project_hours' ),
		'Rate'      => e2e_money( $item->get_meta( '_timegrow_line_rate' ) ),
		'Total'     => e2e_money( $item->get_total() ),
		'Qty'       => $item->get_quantity(),
		'Work done' => $item->get_meta( '_work_done' ),
	];
}

e2e_kv( [
	'Order'        => '#' . $order->get_order_number() . ' (ID ' . $order->get_id() . ')',
	'Status'       => wc_get_order_status_name( $order->get_status() ),
	'Hours invoice'=> Aragrow_WOO_Integration_Plugins::is_hours_invoice( $order ) ? 'yes' : 'no',
	'Customer'     => $order->get_formatted_billing_full_name() . ' <' . $order->get_billing_email() . '>',
	'Total'        => e2e_money( $order->get_total() ),
] );
WP_CLI\Utils\format_items( 'table', $lines, [ 'Line', 'Hours', 'Rate', 'Total', 'Qty', 'Work done' ] );

WP_CLI::line( '' );
e2e_check( 'One order for the client', count( $order_ids ) === 1, count( $order_ids ) . ' order(s)' );
e2e_check( 'Marked as an hours invoice', Aragrow_WOO_Integration_Plugins::is_hours_invoice( $order ) );
e2e_check( 'Every entry marked billed', count( $billed_entries ) === count( $entries ), count( $billed_entries ) . ' of ' . count( $entries ) );
e2e_check( 'Order total matches hours x rate', abs( (float) $order->get_total() - $expected_total ) < 0.01, 'expected ' . e2e_money( $expected_total ) . ', got ' . e2e_money( $order->get_total() ) );
foreach ( $order->get_items() as $item ) {
	e2e_check( 'Quantity is 1 on ' . $item->get_name(), (float) $item->get_quantity() === 1.0 );
}
WP_CLI::line( '  Check it: ' . $order->get_edit_order_url() );

if ( ! $e2e_pp ) {
	WP_CLI::success( "Done (no-send). Run {$e2e_run} created client {$client_id} and order {$order->get_id()}." );
	return;
}

$e2e_method = Aragrow_TimeGrow_Invoice_Delivery::methods()[ $e2e_delivery ];
e2e_pause( "Phase 5, send the invoice ({$e2e_method})", $e2e_auto );

// ---------------------------------------------------------------------------
// Phase 5: "Send invoice" order action (follows the client's Invoice Delivery choice)
// ---------------------------------------------------------------------------

e2e_heading( "Phase 5: send the invoice ({$e2e_method})" );

$order_actions = apply_filters( 'woocommerce_order_actions', [], $order );
e2e_check( 'Order action offered', isset( $order_actions[ Aragrow_TimeGrow_Invoice_Delivery::ACTION ] ), $order_actions[ Aragrow_TimeGrow_Invoice_Delivery::ACTION ] ?? 'missing' );
$e2e_order_method = Aragrow_TimeGrow_Invoice_Delivery::method_for_order( $order );
e2e_check( "Order follows the client's choice ({$e2e_method})", $e2e_order_method === $e2e_delivery, $e2e_order_method );

// Note every email WordPress sends while the invoice goes out
$e2e_mail    = [];
$e2e_capture = function ( $mail ) use ( &$e2e_mail ) { $e2e_mail[] = $mail; return $mail; };
add_filter( 'wp_mail', $e2e_capture );
Aragrow_TimeGrow_Invoice_Delivery::send( $order ); // same as picking "Send invoice" in Order actions and clicking Update
remove_filter( 'wp_mail', $e2e_capture );

$order = wc_get_order( $order->get_id() ); // fresh copy after sending
$notes = wc_get_order_notes( [ 'order_id' => $order->get_id(), 'limit' => 3 ] );

e2e_kv( [ 'Order status' => wc_get_order_status_name( $order->get_status() ) ] );
WP_CLI::line( '  Latest order notes:' );
foreach ( $notes as $note ) WP_CLI::line( '    - ' . wp_strip_all_tags( $note->content ) );
WP_CLI::line( '  Emails sent: ' . ( $e2e_mail ? '' : 'none' ) );
foreach ( $e2e_mail as $mail ) {
	$files = array_map( 'basename', (array) ( $mail['attachments'] ?? [] ) );
	WP_CLI::line( '    - to ' . implode( ', ', (array) $mail['to'] ) . ': "' . $mail['subject'] . '"' . ( $files ? '  attached: ' . implode( ', ', $files ) : '  (no attachment)' ) );
}

$client_mail = array_values( array_filter( $e2e_mail, function ( $m ) use ( $email ) { return in_array( $email, (array) $m['to'], true ); } ) );

if ( $e2e_delivery === 'email' ) {
	WP_CLI::line( '' );
	e2e_check( 'Order moved to Invoice Sent to Client', $order->get_status() === 'invoice_sent', wc_get_order_status_name( $order->get_status() ) );
	e2e_check( 'One email to the client', count( $client_mail ) === 1, count( $client_mail ) . ' sent' );
	$pdfs = $client_mail ? array_filter( (array) ( $client_mail[0]['attachments'] ?? [] ), function ( $f ) { return substr( strtolower( $f ), -4 ) === '.pdf'; } ) : [];
	e2e_check( 'PDF invoice attached', count( $pdfs ) > 0 );
	WP_CLI::line( '  Read the email in Local: Tools > Mailpit (or Mailhog).' );
	WP_CLI::line( '  Check the order: ' . $order->get_edit_order_url() );
	WP_CLI::success( "Done. Run {$e2e_run}: client {$client_id}, order {$order->get_id()}, emailed to {$email}." );
	return;
}

if ( $e2e_delivery === 'mail' ) {
	$pdf = html_entity_decode( Aragrow_TimeGrow_Invoice_Delivery::pdf_link( $order ) );
	e2e_kv( [
		'Mail to'          => str_replace( '<br/>', ', ', $order->get_formatted_billing_address() ),
		'Invoice PDF'      => $pdf ?: '(PDF plugin needed)',
		'Print & Mail page'=> admin_url( 'admin.php?page=' . Aragrow_TimeGrow_Invoice_Delivery::PAGE_SLUG ),
	] );
	WP_CLI::line( '' );
	e2e_check( 'Order moved to To Be Mailed', $order->get_status() === Aragrow_TimeGrow_Invoice_Delivery::MAILED_STATUS, wc_get_order_status_name( $order->get_status() ) );
	e2e_check( 'No email to the client', count( $client_mail ) === 0, count( $client_mail ) . ' sent' );
	e2e_check( 'Listed on the Print & Mail page', in_array( $order->get_id(), array_map( function ( $o ) { return $o->get_id(); }, Aragrow_TimeGrow_Invoice_Delivery::waiting_orders() ), true ) );
	WP_CLI::line( '  Next: open the Print & Mail page, print the PDF, and click "Mark as mailed".' );
	WP_CLI::success( "Done. Run {$e2e_run}: client {$client_id}, order {$order->get_id()} is waiting to be mailed." );
	return;
}

// PayPal: read the invoice back from the sandbox
$invoice_id = Aragrow_TimeGrow_PayPal_Payment_Sync::invoice_id_for( $order );
e2e_kv( [ 'PayPal invoice ID' => $invoice_id ?: '(none)' ] );
if ( $invoice_id === '' ) {
	WP_CLI::error( 'PayPal did not create an invoice. The order notes above say why.' );
}

try {
	$invoice = $invoicer->paypal_request( 'GET', '/v2/invoicing/invoices/' . rawurlencode( $invoice_id ) );
	$pp_items = array_map( function ( $line ) {
		return [ 'Item' => $line['name'] ?? '', 'Quantity' => $line['quantity'] ?? '', 'Unit amount' => $line['unit_amount']['value'] ?? '' ];
	}, $invoice['items'] ?? [] );

	e2e_kv( [
		'PayPal status'      => $invoice['status'] ?? '?',
		'PayPal total'       => ( $invoice['amount']['value'] ?? '?' ) . ' ' . ( $invoice['amount']['currency_code'] ?? '' ),
		'Invoice number'     => $invoice['detail']['invoice_number'] ?? '?',
		'Recipient'          => $invoice['primary_recipients'][0]['billing_info']['email_address'] ?? '?',
		'Business name'      => $invoice['primary_recipients'][0]['billing_info']['business_name'] ?? '(none)',
		'Merchant view'      => $invoice['detail']['metadata']['invoicer_view_url'] ?? '(not returned)',
		'Client (pay) view'  => $invoice['detail']['metadata']['recipient_view_url'] ?? '(not returned)',
	] );
	WP_CLI\Utils\format_items( 'table', $pp_items, [ 'Item', 'Quantity', 'Unit amount' ] );

	WP_CLI::line( '' );
	e2e_check( 'PayPal total matches the order', isset( $invoice['amount']['value'] ) && abs( (float) $invoice['amount']['value'] - (float) $order->get_total() ) < 0.05, 'PayPal ' . ( $invoice['amount']['value'] ?? '?' ) . ', order ' . e2e_money( $order->get_total() ) );
	$sent_business = $invoice['primary_recipients'][0]['billing_info']['business_name'] ?? '';
	e2e_check( $is_company ? 'Company sent as the business name' : 'No business name for a person', $is_company ? $sent_business === $company : $sent_business === '' );
	e2e_check( 'Hours sent as the PayPal quantity', ! empty( $pp_items ) && in_array( number_format( $projects[0]['hours'], 2, '.', '' ), array_map( function ( $r ) { return number_format( (float) $r['Quantity'], 2, '.', '' ); }, $pp_items ), true ) );
} catch ( Exception $e ) {
	WP_CLI::warning( 'Could not read the invoice back from PayPal: ' . $e->getMessage() );
}

WP_CLI::line( '  Check the order: ' . $order->get_edit_order_url() );

if ( $e2e_auto ) {
	WP_CLI::success( "Done. Run {$e2e_run}: client {$client_id}, order {$order->get_id()}, PayPal invoice {$invoice_id}." );
	return;
}

// ---------------------------------------------------------------------------
// Phase 6 (optional): pay it in the sandbox, then sync the payment back
// ---------------------------------------------------------------------------

WP_CLI::line( '' );
WP_CLI::line( '  Optional: open the client (pay) view above, log in with your PayPal sandbox BUYER account and pay.' );
e2e_pause( 'Phase 6, check PayPal for the payment', false );

e2e_heading( 'Phase 6: sync the payment from PayPal' );
$sync = new Aragrow_TimeGrow_PayPal_Payment_Sync( $invoicer );
try {
	$message = $sync->sync_order( $order );
} catch ( Exception $e ) {
	$message = 'PayPal check failed: ' . $e->getMessage();
}
$order = wc_get_order( $order->get_id() );
e2e_kv( [
	'Result'          => $message,
	'Order status'    => wc_get_order_status_name( $order->get_status() ),
	'Payment date'    => $order->get_meta( '_payment_date' ) ?: '-',
	'Payment amount'  => $order->get_meta( '_payment_amount' ) ?: '-',
	'Transaction ID'  => $order->get_meta( '_payment_trans_id' ) ?: '-',
] );

WP_CLI::success( "Done. Run {$e2e_run}: client {$client_id}, order {$order->get_id()}, PayPal invoice {$invoice_id}." );
