<?php
/**
 * Hardening — turn off login routes most BD sites never use.
 *
 * @package bd-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bdtk_settings  = bdtk_get_settings();
$bdtk_hardening = $bdtk_settings['hardening'];

function bdtk_remove_pingback_header( $headers ) {
	unset( $headers['X-Pingback'] );
	return $headers;
}

// No pingbacks, and no password guessing through system.multicall. Turn off if a site uses Jetpack or the mobile app.
if ( ! empty( $bdtk_hardening['xmlrpc'] ) ) {
	add_filter( 'xmlrpc_enabled', '__return_false' );
	add_filter( 'xmlrpc_methods', '__return_empty_array' );
	add_filter( 'wp_headers', 'bdtk_remove_pingback_header' );
	remove_action( 'wp_head', 'rsd_link' );
}

// Application Passwords let anyone try logins over the REST API and XML-RPC. Also hides them on user profiles.
if ( ! empty( $bdtk_hardening['app_passwords'] ) ) {
	add_filter( 'wp_is_application_passwords_available', '__return_false' );
}
