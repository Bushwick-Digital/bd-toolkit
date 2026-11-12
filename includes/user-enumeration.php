<?php
/**
 * Stop user enumeration — stops scanners from discovering usernames.
 *
 * Ported from Stop User Enumeration 1.7.10 by Fullworks (GPLv2 or later):
 * https://wordpress.org/plugins/stop-user-enumeration/
 * Generic login / lost-password messages and disabling author archives are BD additions.
 * Each check has its own switch on Settings > BD Toolkit. Logged-in users are never blocked.
 *
 * @package bd-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bdtk_settings = bdtk_get_settings();
$bdtk_enum     = $bdtk_settings['enumeration'];

// /?author=1 redirects to /author/{username}/ — block any logged-out request with a number in it.
function bdtk_enum_check_author_query() {
	if ( is_user_logged_in() || ! isset( $_REQUEST['author'] ) ) {
		return;
	}

	$author = sanitize_text_field( wp_unslash( $_REQUEST['author'] ) );

	if ( preg_match( '/\d/', $author ) ) {
		wp_die( 'Forbidden.', '', array( 'response' => 403 ) );
	}
}

// Matches the route (not the URL), so /wp-json/wp/v2/users and ?rest_route=/wp/v2/users are both covered.
function bdtk_enum_rest_users( $result, $server, $request ) {
	if ( is_user_logged_in() ) {
		return $result;
	}

	$route = $request->get_route();

	if ( empty( $route ) || ! preg_match( apply_filters( 'bdtk_enum_rest_match', '#^/wp/v[0-9]+/users#i' ), $route ) ) {
		return $result;
	}

	// JWT login plugins authenticate through their own routes.
	if ( preg_match( apply_filters( 'bdtk_enum_rest_allowed_match', '#^/simple-jwt-login/#i' ), $route ) ) {
		return $result;
	}

	return new WP_Error( 'rest_cannot_access', 'Sorry, you are not allowed to list users.', array( 'status' => rest_authorization_required_code() ) );
}

function bdtk_enum_remove_users_sitemap( $provider, $name ) {
	return 'users' === $name ? false : $provider;
}

// oEmbed responses include author_url (/author/{username}/); the author name alone is enough.
function bdtk_enum_remove_oembed_author_url( $data ) {
	unset( $data['author_url'] );
	return $data;
}

// Core says "The password you entered for the username admin is incorrect" vs "admin123 is not
// registered", which confirms which usernames/emails exist. Show one message for all three cases.
function bdtk_enum_generic_login_errors( $errors ) {
	$found = false;

	foreach ( array( 'invalid_username', 'invalid_email', 'incorrect_password' ) as $code ) {
		if ( $errors->get_error_message( $code ) ) {
			$errors->remove( $code );
			$found = true;
		}
	}

	if ( $found ) {
		$errors->add(
			'bdtk_invalid_login',
			'<strong>Error:</strong> The username, email address or password is incorrect. <a href="' . esc_url( wp_lostpassword_url() ) . '">Lost your password?</a>'
		);
	}

	return $errors;
}

// Lost password says "There is no account with that username or email address" for unknown
// accounts. Send them to the same "check your email" screen a real account gets — the same redirect
// core uses on success, so both cases look identical. Only on wp-login.php, not WooCommerce etc.
function bdtk_enum_generic_lostpassword( $errors, $user_data ) {
	global $pagenow;

	if ( 'wp-login.php' !== $pagenow || $user_data || $errors->get_error_message( 'empty_username' ) ) {
		return;
	}

	$redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : 'wp-login.php?checkemail=confirm';

	wp_safe_redirect( $redirect_to );
	exit;
}

// "The email could not be sent" only ever appears for real accounts (a site with broken mail), so it
// leaks too. Same redirect. The missing email still tells the real user something is wrong.
function bdtk_enum_generic_lostpassword_mail_failure( $errors ) {
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! is_wp_error( $errors ) || ! $errors->get_error_message( 'retrieve_password_email_failure' ) ) {
		return;
	}

	$redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? wp_unslash( $_REQUEST['redirect_to'] ) : 'wp-login.php?checkemail=confirm';

	wp_safe_redirect( $redirect_to );
	exit;
}

// Feeds print each post's author display name (<dc:creator>, Atom <name>), which is often the username.
function bdtk_enum_feed_author( $author ) {
	return is_feed() ? get_bloginfo( 'name' ) : $author;
}

function bdtk_enum_feed_author_url( $url ) {
	return is_feed() ? '' : $url;
}

// /author/{username}/ shows the login name in the URL. Redirect like Yoast's "disable author archives".
function bdtk_enum_disable_author_archives() {
	if ( is_author() ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}

if ( ! empty( $bdtk_enum['author_query'] ) ) {
	add_action( 'init', 'bdtk_enum_check_author_query' );
}

if ( ! empty( $bdtk_enum['rest_users'] ) ) {
	add_filter( 'rest_pre_dispatch', 'bdtk_enum_rest_users', 10, 3 );
}

if ( ! empty( $bdtk_enum['sitemap'] ) ) {
	add_filter( 'wp_sitemaps_add_provider', 'bdtk_enum_remove_users_sitemap', 10, 2 );
}

if ( ! empty( $bdtk_enum['oembed'] ) ) {
	add_filter( 'oembed_response_data', 'bdtk_enum_remove_oembed_author_url' );
}

if ( ! empty( $bdtk_enum['feeds'] ) ) {
	add_filter( 'the_author', 'bdtk_enum_feed_author' );
	add_filter( 'get_the_author_url', 'bdtk_enum_feed_author_url' );
}

if ( ! empty( $bdtk_enum['author_archives'] ) ) {
	add_action( 'template_redirect', 'bdtk_enum_disable_author_archives', 1 );
}

if ( ! empty( $bdtk_enum['login_errors'] ) ) {
	add_filter( 'wp_login_errors', 'bdtk_enum_generic_login_errors' );
	add_action( 'lostpassword_post', 'bdtk_enum_generic_lostpassword', 99, 2 );
	add_action( 'lost_password', 'bdtk_enum_generic_lostpassword_mail_failure' );
}
