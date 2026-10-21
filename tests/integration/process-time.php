<?php
/**
 * TimeGrow integration test: Process Time (time entries -> WooCommerce orders).
 *
 * Runs inside your WordPress site, on throwaway records it makes and removes again: two test
 * clients (customers), a project and product each, and billable time entries for an existing
 * team member. Nothing is emailed and nothing goes to PayPal. Use it on your Local site.
 *
 * Run from the plugin folder, in Local's site shell (Local > right-click the site > Open site shell):
 *
 *   wp eval-file tests/integration/process-time.php          run every scenario, then clean up
 *   wp eval-file tests/integration/process-time.php keep     keep the records (to look at them)
 *
 * Scenarios: billing, adding to an open order, 4 runs at once, the lock, a failed new order,
 * a failure after a reused order was saved, time that rounds to 0.00 h, WooCommerce Products
 * off, a client name with "&", and the 75 default rate.
 *
 * Exits with an error when any check fails. The script also runs itself as the child processes
 * of the "4 runs at once" scenario (argument "child").
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'WP_CLI' ) ) {
	exit( "Run this with: wp eval-file tests/integration/process-time.php\n" );
}

$pt_args = isset( $args ) && is_array( $args ) ? $args : [];

add_filter( 'pre_wp_mail', '__return_true' ); // no emails from the test

/** One Process Time run, as the Nexus page does it. @return array { orders, billed, errors } */
function pt_run( array $client_ids ) {
	$model   = new TimeGrowTimeEntryModel();
	$entries = [];
	foreach ( $client_ids as $client_id ) {
		$entries = array_merge( $entries, (array) $model->get_time_entries_to_bill( $client_id, null, null ) );
	}
	if ( ! $entries ) {
		return [ 'orders' => [], 'billed' => 0, 'errors' => [] ];
	}
	$creator = new TimeGrowWooOrderCreator();
	list( $orders, $billed ) = $creator->create_woo_orders_and_products( $entries );
	$model->mark_time_entries_as_billed( $billed );
	$count = 0;
	foreach ( $billed as $entry ) {
		if ( ! empty( $entry->billed_order_id ) ) $count++;
	}
	return [ 'orders' => $orders, 'billed' => $count, 'errors' => $creator->get_errors() ];
}

// ---- Child process for the "4 runs at once" scenario: wait for the start time, run, print JSON
if ( in_array( 'child', $pt_args, true ) ) {
	$opts = [];
	foreach ( $pt_args as $arg ) {
		if ( strpos( $arg, '=' ) !== false ) {
			list( $k, $v ) = explode( '=', $arg, 2 );
			$opts[ $k ] = $v;
		}
	}
	wp_set_current_user( 1 );
	$wait = (float) ( $opts['start'] ?? 0 ) - microtime( true );
	if ( $wait > 0 ) usleep( (int) ( $wait * 1e6 ) );
	echo 'PT_CHILD ' . wp_json_encode( pt_run( array_map( 'intval', explode( ',', $opts['clients'] ?? '' ) ) ) ) . "\n";
	return;
}

// wp eval-file runs this file inside a function: the helpers below share these as globals
global $wpdb, $p, $pt, $pt_tag, $pt_member, $pt_fails, $pt_keep;
$p        = $wpdb->prefix . TIMEGROW_PREFIX;
$pt_keep  = in_array( 'keep', $pt_args, true );
$pt_tag   = 'PT-IT-' . strtolower( wp_generate_password( 6, false ) );
$pt_fails = 0;
$pt       = [ 'clients' => [], 'projects' => [], 'products' => [], 'entries' => [] ];

wp_set_current_user( 1 );

function pt_check( $label, $ok, $detail = '' ) {
	global $pt_fails;
	if ( ! $ok ) $pt_fails++;
	WP_CLI::line( '  ' . ( $ok ? WP_CLI::colorize( '%GPASS%n' ) : WP_CLI::colorize( '%RFAIL%n' ) ) . '  ' . $label . ( $detail !== '' ? '  (' . $detail . ')' : '' ) );
	return $ok;
}

function pt_section( $title ) {
	WP_CLI::line( '' );
	WP_CLI::line( WP_CLI::colorize( "%B== {$title} ==%n" ) );
}

/** A billable entry: manual hours, or a clock entry of $seconds. */
function pt_entry( $tag, $hours, $seconds = 0 ) {
	global $wpdb, $p, $pt, $pt_tag, $pt_member;
	if ( empty( $pt['projects'][ $tag ] ) || ! $p ) {
		throw new Exception( "No test project {$tag}." );
	}
	$now  = current_time( 'mysql' );
	$row  = [ 'project_id' => $pt['projects'][ $tag ], 'member_id' => $pt_member, 'billable' => 1, 'billed' => 0, 'status' => 1,
		'description' => "{$pt_tag} {$tag} entry", 'created_at' => $now, 'updated_at' => $now ];
	if ( $seconds ) {
		$in  = current_time( 'Y-m-d' ) . ' 08:00:00';
		$row += [ 'entry_type' => 'CLOCK', 'clock_in_date' => $in, 'clock_out_date' => gmdate( 'Y-m-d H:i:s', strtotime( $in . ' UTC' ) + $seconds ) ];
	} else {
		$row += [ 'entry_type' => 'MAN', 'date' => current_time( 'Y-m-d' ) . ' 00:00:00', 'hours' => $hours ];
	}
	$wpdb->insert( $p . 'time_entry_tracker', $row );
	$pt['entries'][ $tag ][] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
}

/** Entry rows for a client: ID => [ billed, billed_order_id ]. */
function pt_entries( $tag ) {
	global $wpdb, $p, $pt;
	$ids  = array_map( 'intval', $pt['entries'][ $tag ] ?? [] );
	$rows = $ids ? $wpdb->get_results( "SELECT ID, billed, billed_order_id FROM {$p}time_entry_tracker WHERE ID IN (" . implode( ',', $ids ) . ')' ) : [];
	$out  = [];
	foreach ( $rows as $r ) $out[ (int) $r->ID ] = [ (int) $r->billed, (int) $r->billed_order_id ];
	return $out;
}

/** The test client's orders. Never searches without a test client (that would match every order). */
function pt_orders( $tag ) {
	global $pt;
	$customer = (int) ( $pt['clients'][ $tag ] ?? 0 );
	if ( $customer <= 0 ) {
		throw new Exception( "No test client {$tag}: refusing to look up orders without one." );
	}
	return wc_get_orders( [ 'customer_id' => $customer, 'limit' => -1, 'status' => 'any', 'orderby' => 'ID', 'order' => 'ASC' ] );
}

function pt_hours( $order ) {
	$h = 0.0;
	foreach ( $order->get_items() as $item ) $h += (float) $item->get_meta( '_timegrow_project_hours' );
	return round( $h, 2 );
}

/** Between scenarios: the test clients' orders go, their entries are unbilled. */
function pt_reset() {
	global $wpdb, $p, $pt;
	foreach ( array_keys( $pt['clients'] ) as $tag ) {
		foreach ( pt_orders( $tag ) as $o ) $o->delete( true );
	}
	$all = array_merge( [], ...array_values( $pt['entries'] ) );
	if ( $all ) $wpdb->query( "UPDATE {$p}time_entry_tracker SET billed = 0, billed_order_id = NULL WHERE ID IN (" . implode( ',', array_map( 'intval', $all ) ) . ')' );
}

function pt_cleanup() {
	global $wpdb, $p, $pt;
	foreach ( array_keys( $pt['clients'] ) as $tag ) {
		foreach ( pt_orders( $tag ) as $o ) {
			foreach ( $o->get_items() as $item ) if ( $item->get_product_id() ) $pt['products'][] = (int) $item->get_product_id();
			$o->delete( true );
		}
	}
	foreach ( $pt['projects'] as $pid ) {
		$product_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT product_id FROM {$p}project_tracker WHERE ID = %d", $pid ) );
		if ( $product_id ) $pt['products'][] = $product_id;
	}
	$all = array_map( 'intval', array_merge( [], ...array_values( $pt['entries'] ) ) );
	if ( $all ) {
		$wpdb->query( "DELETE FROM {$p}time_entry_tracker WHERE ID IN (" . implode( ',', $all ) . ')' );
		if ( class_exists( 'TimeGrowTimeEntryAudit' ) ) {
			$wpdb->query( 'DELETE FROM ' . TimeGrowTimeEntryAudit::table() . ' WHERE entry_id IN (' . implode( ',', $all ) . ')' );
		}
	}
	foreach ( $pt['projects'] as $pid ) $wpdb->delete( $p . 'project_tracker', [ 'ID' => (int) $pid ] );
	foreach ( array_unique( $pt['products'] ) as $id ) {
		$product = wc_get_product( $id );
		if ( $product ) $product->delete( true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $pt['clients'] as $uid ) wp_delete_user( $uid );
	delete_option( 'timegrow_process_time_lock' );
}

$pt_member = (int) $wpdb->get_var( "SELECT ID FROM {$p}team_member_tracker ORDER BY ID LIMIT 1" );
if ( ! $pt_member ) {
	WP_CLI::error( 'No team member found. Add one under TimeGrow > Team Members first.' );
}
if ( get_option( 'timegrow_process_time_lock' ) !== false ) {
	WP_CLI::error( 'A Process Time run is in progress (timegrow_process_time_lock is set). Try again when it is done.' );
}

WP_CLI::line( "Process Time integration test, run {$pt_tag} (team member {$pt_member})" );

try {
	// ---- Fixtures: clients A and B, each with a project at 100 an hour and its product
	foreach ( [ 'A' => [ 1.5, 2, 0.5 ], 'B' => [ 1, 3 ] ] as $tag => $hours ) {
		$uid = wp_insert_user( [ 'user_login' => strtolower( "{$pt_tag}-{$tag}" ), 'user_pass' => wp_generate_password( 24 ),
			'user_email' => strtolower( "{$pt_tag}-{$tag}@example.test" ), 'display_name' => "PT Test Client {$tag}", 'role' => 'customer' ] );
		if ( is_wp_error( $uid ) ) throw new Exception( $uid->get_error_message() );
		$pt['clients'][ $tag ] = $uid;
		$product = new WC_Product_Simple();
		$product->set_name( "PT Test Product {$tag} {$pt_tag}" );
		$product->set_regular_price( 100 );
		$product->set_virtual( true );
		$product->set_catalog_visibility( 'hidden' );
		$product->save();
		$pt['products'][] = $product->get_id();
		$now = current_time( 'mysql' );
		$wpdb->insert( $p . 'project_tracker', [ 'client_id' => $uid, 'product_id' => $product->get_id(), 'name' => "PT Test Project {$tag}",
			'description' => $pt_tag, 'status' => 1, 'default_flat_fee' => 100, 'billable' => 1, 'created_at' => $now, 'updated_at' => $now ] );
		$pt['projects'][ $tag ] = (int) $wpdb->insert_id;
		foreach ( $hours as $h ) pt_entry( $tag, $h );
	}
	$A = $pt['clients']['A'];
	$B = $pt['clients']['B'];

	// ---- 1. Billing
	pt_section( '1. Billing' );
	$r = pt_run( [ $A, $B ] );
	$oa = pt_orders( 'A' );
	$ob = pt_orders( 'B' );
	pt_check( 'One order per client', count( $oa ) === 1 && count( $ob ) === 1, count( $oa ) . ' and ' . count( $ob ) );
	pt_check( 'Orders are Pending payment', $oa && $ob && $oa[0]->get_status() === 'pending' && $ob[0]->get_status() === 'pending' );
	pt_check( 'Marked as hours invoices', $oa && $oa[0]->get_meta( '_timegrow_qty_unit' ) === 'hours' && $oa[0]->get_meta( '_timekeeping_invoice' ) );
	pt_check( 'Client A: 4 h for 400.00', $oa && pt_hours( $oa[0] ) === 4.0 && (float) $oa[0]->get_total() === 400.0, $oa ? pt_hours( $oa[0] ) . ' h, ' . $oa[0]->get_total() : '' );
	pt_check( 'Every entry billed on its client\'s order', ! array_filter( pt_entries( 'A' ), function ( $e ) use ( $oa ) { return $e !== [ 1, $oa[0]->get_id() ]; } )
		&& ! array_filter( pt_entries( 'B' ), function ( $e ) use ( $ob ) { return $e !== [ 1, $ob[0]->get_id() ]; } ) );
	pt_check( 'No errors', ! $r['errors'], implode( '; ', $r['errors'] ) );

	// ---- 2. Adding to an open order
	pt_section( '2. A later run adds to the open order' );
	pt_entry( 'A', 4 );
	pt_run( [ $A ] );
	$oa2 = pt_orders( 'A' );
	pt_check( 'Still one order for client A', count( $oa2 ) === 1 && $oa2[0]->get_id() === $oa[0]->get_id() );
	pt_check( 'It now holds 8 h for 800.00', $oa2 && pt_hours( $oa2[0] ) === 8.0 && (float) $oa2[0]->get_total() === 800.0, $oa2 ? pt_hours( $oa2[0] ) . ' h, ' . $oa2[0]->get_total() : '' );
	$o = $oa2[0];
	$o->update_meta_data( '_paypal_invoice_id', 'INV2-TEST' ); // issued: never added to again
	$o->save();
	pt_entry( 'A', 1 );
	pt_run( [ $A ] );
	pt_check( 'An issued invoice gets a new order instead', count( pt_orders( 'A' ) ) === 2 );

	// ---- 3. Four runs at once
	pt_section( '3. Four runs at once' );
	pt_reset();
	$wp_cli = realpath( $_SERVER['argv'][0] ?? '' ) ?: ( $_SERVER['argv'][0] ?? 'wp' );
	$start  = microtime( true ) + 3;
	$procs  = [];
	for ( $i = 0; $i < 4; $i++ ) {
		$cmd = array_merge( [ PHP_BINARY ], ini_get( 'mysqli.default_socket' ) ? [ '-d', 'mysqli.default_socket=' . ini_get( 'mysqli.default_socket' ) ] : [],
			[ $wp_cli, '--path=' . ABSPATH, 'eval-file', __FILE__, 'child', 'clients=' . $A . ',' . $B, 'start=' . $start ] );
		$procs[] = proc_open( $cmd, [ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ], $pipes[ $i ] );
	}
	$results = [];
	foreach ( $procs as $i => $proc ) {
		$out = stream_get_contents( $pipes[ $i ][1] );
		stream_get_contents( $pipes[ $i ][2] );
		proc_close( $proc );
		if ( preg_match( '/^PT_CHILD (.*)$/m', $out, $m ) ) $results[] = json_decode( $m[1], true );
	}
	$entries_all = pt_entries( 'A' ) + pt_entries( 'B' );
	$billed_sum  = array_sum( array_column( $results, 'billed' ) );
	pt_check( 'All 4 runs finished', count( $results ) === 4, count( $results ) . ' of 4' );
	pt_check( 'Each entry billed exactly once in total', $billed_sum === count( $entries_all ), "{$billed_sum} billings for " . count( $entries_all ) . ' entries' );
	$hours_a = array_sum( array_map( 'pt_hours', pt_orders( 'A' ) ) );
	$hours_b = array_sum( array_map( 'pt_hours', pt_orders( 'B' ) ) );
	pt_check( 'Orders hold each hour once (A 9 h, B 4 h)', $hours_a === 9.0 && $hours_b === 4.0, "A {$hours_a} h, B {$hours_b} h" );
	pt_check( 'Every entry is billed', ! array_filter( $entries_all, function ( $e ) { return $e[0] !== 1; } ) );

	// ---- 4. The lock
	pt_section( '4. One run at a time' );
	$lock_value = new ReflectionProperty( 'TimeGrowNexus', 'lock_value' );
	if ( PHP_VERSION_ID < 80100 ) $lock_value->setAccessible( true );
	pt_check( 'The first run gets the lock', TimeGrowNexus::process_time_lock() );
	$first = $lock_value->getValue();
	$lock_value->setValue( null, '' );
	pt_check( 'A second run is refused', ! TimeGrowNexus::process_time_lock() );
	$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = 'timegrow_process_time_lock'", ( time() - 660 ) . ':stale' ) );
	pt_check( 'A lock older than 10 minutes is taken over', TimeGrowNexus::process_time_lock() );
	$second = $lock_value->getValue();
	$lock_value->setValue( null, $first );
	TimeGrowNexus::process_time_unlock(); // the slow first run finishes late
	// Read straight from the table: the lock is written with SQL, so get_option() could be stale
	$held = function () use ( $wpdb ) {
		return $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'timegrow_process_time_lock'" );
	};
	pt_check( 'The slow run does not remove the new lock', $held() === $second );
	$lock_value->setValue( null, $second );
	TimeGrowNexus::process_time_unlock();
	pt_check( 'The holder removes its own lock', $held() === null );

	// ---- 5. A failed new order
	pt_section( '5. A client whose new order fails' );
	pt_reset();
	$fail_new = function ( $order ) use ( $B ) {
		if ( (int) $order->get_customer_id() === (int) $B && count( $order->get_items( 'line_item' ) ) ) throw new Exception( 'simulated failure' );
	};
	add_action( 'woocommerce_before_order_object_save', $fail_new );
	$r = pt_run( [ $A, $B ] );
	remove_action( 'woocommerce_before_order_object_save', $fail_new );
	// WooCommerce catches exceptions inside its save, so TimeGrow's own check of the saved order reports it
	pt_check( 'The error is reported for client B only', count( $r['errors'] ) === 1 && strpos( $r['errors'][0], "Client {$B}:" ) === 0, implode( '; ', $r['errors'] ) );
	pt_check( 'Client B has no order', ! pt_orders( 'B' ) );
	pt_check( 'Client B\'s time is unbilled', ! array_filter( pt_entries( 'B' ), function ( $e ) { return $e[0] !== 0; } ) );
	pt_check( 'Client A is still billed', count( pt_orders( 'A' ) ) === 1 && ! array_filter( pt_entries( 'A' ), function ( $e ) { return $e[0] !== 1; } ) );

	// ---- 6. A failure after a reused order was saved
	pt_section( '6. A reused order that fails after saving' );
	pt_run( [ $B ] );
	$ob     = pt_orders( 'B' );
	$before = $ob ? [ pt_hours( $ob[0] ), (float) $ob[0]->get_total(), count( $ob[0]->get_items() ) ] : [];
	$new_b  = pt_entry( 'B', 2 );
	$thrown = false;
	$old_items = $ob ? array_keys( $ob[0]->get_items() ) : [];
	// The save "doesn't stick": the new line is gone from the database right after it was saved, so
	// TimeGrow's check of the saved order fails after the order (and its new line) was written
	$fail_after = function ( $order ) use ( $B, $old_items, &$thrown ) {
		if ( $thrown || (int) $order->get_customer_id() !== (int) $B ) return;
		foreach ( $order->get_items() as $id => $item ) {
			if ( $id && ! in_array( $id, $old_items, true ) ) { wc_delete_order_item( $id ); $thrown = true; }
		}
	};
	add_action( 'woocommerce_after_order_object_save', $fail_after );
	$r = pt_run( [ $B ] );
	remove_action( 'woocommerce_after_order_object_save', $fail_after );
	$ob_after = pt_orders( 'B' );
	$after    = $ob_after ? [ pt_hours( $ob_after[0] ), (float) $ob_after[0]->get_total(), count( $ob_after[0]->get_items() ) ] : [];
	pt_check( 'The failure happened after the save', $thrown && $r['errors'], implode( '; ', $r['errors'] ) );
	pt_check( 'The order keeps what it had (4 h, 400.00, 1 line)', $before === [ 4.0, 400.0, 1 ] && $after === $before, wp_json_encode( $after ) );
	pt_check( 'The new entry is unbilled', ( pt_entries( 'B' )[ $new_b ][0] ?? 1 ) === 0 );
	pt_run( [ $B ] );
	$ob_retry = pt_orders( 'B' );
	pt_check( 'The next run bills it once (6 h)', count( $ob_retry ) === 1 && pt_hours( $ob_retry[0] ) === 6.0, $ob_retry ? pt_hours( $ob_retry[0] ) . ' h' : '' );

	// ---- 7. Time that rounds to 0.00 h
	pt_section( '7. A 10-second clock entry' );
	$tiny = pt_entry( 'A', 0, 10 );
	pt_run( [ $A ] );
	pt_check( 'It stays unbilled for a later run', ( pt_entries( 'A' )[ $tiny ][0] ?? 1 ) === 0 );
	pt_check( 'No empty line was added', count( pt_orders( 'A' )[0]->get_items() ) === 1 );
	$wpdb->update( $p . 'time_entry_tracker', [ 'billable' => 0 ], [ 'ID' => $tiny ] ); // out of the later scenarios

	// ---- 8. WooCommerce Products off
	pt_section( '8. WooCommerce Products off' );
	pt_reset();
	$wpdb->update( $p . 'project_tracker', [ 'product_id' => null ], [ 'ID' => $pt['projects']['A'] ] );
	$products_off = function ( $v ) { $v = is_array( $v ) ? $v : []; $v['wc_products'] = 0; return $v; };
	add_filter( 'option_timegrow_integration_settings', $products_off );
	pt_run( [ $A ] );
	remove_filter( 'option_timegrow_integration_settings', $products_off );
	$oa   = pt_orders( 'A' );
	$item = $oa ? current( $oa[0]->get_items() ) : null;
	pt_check( 'The line has no product', $item && ! $item->get_product_id() );
	pt_check( 'It is named "Client - Project"', $item && $item->get_name() === 'PT Test Client A - PT Test Project A', $item ? $item->get_name() : '' );
	pt_check( 'The project still has no product', ! $wpdb->get_var( $wpdb->prepare( "SELECT product_id FROM {$p}project_tracker WHERE ID = %d", $pt['projects']['A'] ) ) );

	// ---- 9. A client name with "&" (products on)
	pt_section( '9. A client name with "&"' );
	pt_reset();
	wp_update_user( [ 'ID' => $A, 'display_name' => 'PT & Test Client A' ] );
	$products_on = function ( $v ) { $v = is_array( $v ) ? $v : []; $v['wc_products'] = 1; return $v; };
	add_filter( 'option_timegrow_integration_settings', $products_on );
	pt_run( [ $A ] );
	remove_filter( 'option_timegrow_integration_settings', $products_on );
	$oa      = pt_orders( 'A' );
	$item    = $oa ? current( $oa[0]->get_items() ) : null;
	$product = $item ? wc_get_product( $item->get_product_id() ) : null;
	pt_check( 'WordPress stores the name encoded', get_userdata( $A )->display_name === 'PT &amp; Test Client A' );
	pt_check( 'The line name is plain text', $item && $item->get_name() === 'PT & Test Client A - PT Test Project A', $item ? $item->get_name() : '' );
	pt_check( 'The new product name is plain text', $product && $product->get_name() === 'PT & Test Client A - PT Test Project A', $product ? $product->get_name() : 'no product' );

	// ---- 10. The 75 default rate
	pt_section( '10. No rate anywhere' );
	pt_reset();
	$wpdb->update( $p . 'project_tracker', [ 'default_flat_fee' => 0 ], [ 'ID' => $pt['projects']['B'] ] );
	$no_company_rate = function ( $q ) {
		return strpos( $q, 'SELECT default_flat_fee FROM' ) === 0 && strpos( $q, 'company_tracker ORDER BY ID LIMIT 1' ) !== false ? 'SELECT 0' : $q;
	};
	add_filter( 'query', $no_company_rate );
	pt_run( [ $B ] );
	$ob    = pt_orders( 'B' );
	$notes = $ob ? wc_get_order_notes( [ 'order_id' => $ob[0]->get_id() ] ) : [];
	$integration = new Aragrow_WOO_Integration_Plugins();
	$item  = $ob ? current( $ob[0]->get_items() ) : null;
	$editor_rate = $item ? $integration->get_line_rate( $ob[0], $item ) : 0;
	remove_filter( 'query', $no_company_rate );
	pt_check( 'Billed at 75 an hour (6 h = 450.00)', $ob && (float) $ob[0]->get_total() === 450.0, $ob ? $ob[0]->get_total() : '' );
	pt_check( 'The order says why', (bool) array_filter( $notes, function ( $n ) { return strpos( $n->content, 'so 75 an hour was used' ) !== false; } ) );
	pt_check( 'The hours editor uses the same rate', abs( $editor_rate - 75 ) < 0.001, (string) $editor_rate );

} catch ( Throwable $e ) {
	pt_check( 'The test ran to the end', false, $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() );
} finally {
	if ( $pt_keep ) {
		WP_CLI::line( '' );
		WP_CLI::line( "Kept the records (run {$pt_tag}): clients " . implode( ', ', $pt['clients'] ) . ', projects ' . implode( ', ', $pt['projects'] ) . '.' );
	} else {
		pt_cleanup();
	}
}

WP_CLI::line( '' );
if ( $pt_fails ) {
	WP_CLI::error( "{$pt_fails} check(s) failed. Run {$pt_tag}." );
}
WP_CLI::success( "Every check passed. Run {$pt_tag}" . ( $pt_keep ? ' (records kept).' : ', records removed.' ) );
