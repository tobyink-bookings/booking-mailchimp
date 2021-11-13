<?php
/**
 * Plugin Name:       TIL Bookings Mailchimp Integration
 * Plugin URI:        https://github.com/tobyink-bookings
 * Description:       Conditionally add new bookings to a mailchimp audience
 * Version:           1.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            Toby Ink Ltd
 * Author URI:        https://toby.ink/hire/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

define( 'BOOKINGS_MAILCHIMP_PLUGIN_VERSION', '1.0' );

add_filter( 'booking_settings', function ( $keys ) {
	$keys['section_mailchimp']             = [ 'heading' => 'Mailchimp Integration' ];
	$keys['booking_mailchimp_key']         = [ 'label' => 'API Key' ];
	$keys['booking_mailchimp_audience']    = [ 'label' => 'Audience' ];
	$keys['booking_mailchimp_condition']   = [ 'label' => 'Condition' ];
	$keys['booking_mailchimp_field_email'] = [ 'label' => 'Email field' ];
	$keys['booking_mailchimp_field_fname'] = [ 'label' => 'First name field' ];
	$keys['booking_mailchimp_field_lname'] = [ 'label' => 'Last name field' ];
	$keys['booking_mailchimp_status']      = [ 'label' => 'Status ("pending" or "subscribed")' ];
	return $keys;
} );

add_action( 'acf/save_post', function ( $post_id ) {
	$condition = get_option( 'booking_mailchimp_condition', 'true' );
	$result    = eval( "return( $condition );" );
	if ( ! $result ) {
		return false;
	}

	return booking_mailchimp_save_post( $post_id );
}, 80, 1 );

function booking_mailchimp_save_post ( $post_id ) {
	$key       = get_option( 'booking_mailchimp_key' );
	$audience  = get_option( 'booking_mailchimp_audience' );

	if ( ! $key or ! $audience ) {
		return null;
	}

	$status = get_option( 'booking_mailchimp_status', 'subscribed' );

	$existing = get_post_meta( $post_id, 'booking_mailchimp', true );
	if ( $existing ) {
		$existing = json_decode( $existing );
		if ( $existing->status == $status ) {
			return true;
		}
	}

	$f_email   = get_option( 'booking_mailchimp_field_email' );
	$f_fname   = get_option( 'booking_mailchimp_field_fname' );
	$f_lname   = get_option( 'booking_mailchimp_field_lname' );

	list( $dummy, $server ) = explode( '-', $key );

	$url  = "https://${server}.api.mailchimp.com/3.0/lists/${audience}/members";
	$data = [
		'email_address' => get_post_meta( $post_id, $f_email, true ),
		'status'        => $status,
		'merge_fields'  => []
	];

	$fname = get_post_meta( $post_id, $f_fname, true );
	if ( isset($fname) ) {
		$data['merge_fields']['FNAME'] = $fname;
	}

	$lname = get_post_meta( $post_id, $f_lname, true );
	if ( isset($lname) ) {
		$data['merge_fields']['LNAME'] = $lname;
	}

	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt( $ch, CURLOPT_HEADER, 0 );
	curl_setopt( $ch, CURLOPT_TIMEOUT, 60 );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ] );
	curl_setopt( $ch, CURLOPT_POST, 1 );
	curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );
	curl_setopt( $ch, CURLOPT_USERPWD, "key:$key" );

	$result = curl_exec( $ch );
	update_post_meta( $post_id, 'booking_mailchimp', $result );

	if ( curl_errno( $ch ) ) {
		$result = curl_error( $ch );
		update_post_meta( $post_id, 'booking_mailchimp_error', $result );
		return false;
	}

	return true;
}

