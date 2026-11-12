<?php
/**
 * Custom login address — serves the login page at /{slug}/ and shows a 404 to logged-out visitors at
 * wp-login.php and /wp-admin.
 *
 * Request handling ported from WPS Hide Login 1.9.19 (GPLv2 or later):
 * https://wordpress.org/plugins/wps-hide-login/ — multisite handling left out.
 *
 * Forgot the address? `wp bdtk login-url`, or define( 'BDTK_LOGIN_URL_OFF', true ) in wp-config.php,
 * or rename the plugin folder.
 *
 * @package bd-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bdtk_login_slug() {
	$settings = bdtk_get_settings();
	return $settings['login_url']['slug'];
}

function bdtk_login_new_url( $scheme = null ) {
	return bdtk_login_url_for_slug( bdtk_login_slug(), $scheme );
}

// Logged-out /wp-admin requests are sent here; bdtk_login_detect_request() serves it as a 404.
function bdtk_login_404_url() {
	return get_option( 'permalink_structure' ) ? bdtk_login_trailingslashit( home_url( '/404' ) ) : home_url( '/?404' );
}

function bdtk_login_is_404_request( $path ) {
	if ( get_option( 'permalink_structure' ) ) {
		return home_url( '404', 'relative' ) === $path;
	}

	return isset( $_GET['404'] ) && '' === $_GET['404'];
}

function bdtk_login_request_path() {
	$uri     = isset( $_SERVER['REQUEST_URI'] ) ? rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	$request = wp_parse_url( $uri );

	return isset( $request['path'] ) ? $request['path'] : '';
}

// Runs before WordPress decides what to load: marks wp-login.php requests (shown as 404 later) and
// turns requests for the custom address into the login page.
function bdtk_login_detect_request() {
	global $pagenow, $bdtk_login_hidden_request;

	if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ! isset( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	$uri  = rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	$path = untrailingslashit( bdtk_login_request_path() );
	$slug = bdtk_login_slug();

	$is_wp_login    = false !== strpos( $uri, 'wp-login.php' ) || site_url( 'wp-login', 'relative' ) === $path;
	$is_wp_register = false !== strpos( $uri, 'wp-register.php' ) || site_url( 'wp-register', 'relative' ) === $path;

	if ( ( $is_wp_login || $is_wp_register || bdtk_login_is_404_request( $path ) ) && ! is_admin() ) {
		$bdtk_login_hidden_request = true;
		$_SERVER['REQUEST_URI']    = bdtk_login_trailingslashit( '/' . str_repeat( '-/', 10 ) );
		$pagenow                   = 'index.php';
	} elseif ( home_url( $slug, 'relative' ) === $path || ( ! get_option( 'permalink_structure' ) && isset( $_GET[ $slug ] ) && empty( $_GET[ $slug ] ) ) ) {
		$_SERVER['SCRIPT_NAME'] = $slug;
		$pagenow                = 'wp-login.php';
	}
}

// Always a real 404, whatever the permalink setting: without this, plain permalinks would
// canonical-redirect the fake path and show the home page.
function bdtk_login_load_404() {
	global $pagenow, $wp_query;

	$pagenow = 'index.php';

	if ( ! defined( 'WP_USE_THEMES' ) ) {
		define( 'WP_USE_THEMES', true );
	}

	remove_action( 'template_redirect', 'redirect_canonical' );

	wp();
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();

	require_once ABSPATH . WPINC . '/template-loader.php';
	exit;
}

function bdtk_login_handle_request() {
	global $pagenow, $bdtk_login_hidden_request;

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}

	// Password-protected posts post to wp-login.php?action=postpass — leave that working.
	if ( isset( $_GET['action'] ) && 'postpass' === $_GET['action'] && isset( $_POST['post_password'] ) ) {
		return;
	}

	$path    = bdtk_login_request_path();
	$options = wp_parse_url( admin_url( 'options.php' ), PHP_URL_PATH );

	if ( is_admin() && ! is_user_logged_in() && ! wp_doing_ajax() && ! wp_doing_cron() && 'admin-post.php' !== $pagenow && $options !== $path ) {
		wp_safe_redirect( bdtk_login_404_url() );
		exit;
	}

	if ( ! is_user_logged_in() && isset( $_GET['wc-ajax'] ) && 'profile.php' === $pagenow ) {
		wp_safe_redirect( bdtk_login_404_url() );
		exit;
	}

	if ( ! is_user_logged_in() && $options === $path ) {
		header( 'Location: ' . bdtk_login_404_url() );
		exit;
	}

	if ( 'wp-login.php' === $pagenow && $path !== bdtk_login_trailingslashit( $path ) && get_option( 'permalink_structure' ) ) {
		$query = isset( $_SERVER['QUERY_STRING'] ) && '' !== $_SERVER['QUERY_STRING'] ? '?' . wp_unslash( $_SERVER['QUERY_STRING'] ) : '';
		wp_safe_redirect( bdtk_login_trailingslashit( bdtk_login_new_url() ) . $query );
		exit;
	}

	if ( ! empty( $bdtk_login_hidden_request ) ) {
		bdtk_login_load_404();
	}

	if ( 'wp-login.php' === $pagenow ) {
		// wp-login.php reads these as globals.
		global $error, $interim_login, $action, $user_login;

		if ( is_user_logged_in() && ! isset( $_REQUEST['action'] ) ) {
			wp_safe_redirect( admin_url() );
			exit;
		}

		require_once ABSPATH . 'wp-login.php';
		exit;
	}
}

// Rewrites every generated wp-login.php link (logout, lost password, reset emails, redirects).
function bdtk_login_filter_url( $url, $scheme = null ) {
	if ( false !== strpos( $url, 'wp-login.php?action=postpass' ) ) {
		return $url;
	}

	// Gravity Forms previews for logged-out users keep the original URL.
	if ( ! is_user_logged_in() && isset( $_GET['gf_page'] ) ) {
		return $url;
	}

	if ( false === strpos( $url, 'wp-login.php' ) || false !== strpos( (string) wp_get_referer(), 'wp-login.php' ) ) {
		return $url;
	}

	if ( is_ssl() ) {
		$scheme = 'https';
	}

	$parts = explode( '?', $url );

	if ( ! isset( $parts[1] ) ) {
		return bdtk_login_new_url( $scheme );
	}

	parse_str( $parts[1], $args );

	if ( isset( $args['login'] ) ) {
		$args['login'] = rawurlencode( $args['login'] );
	}

	return add_query_arg( $args, bdtk_login_new_url( $scheme ) );
}

function bdtk_login_site_url( $url, $path, $scheme, $blog_id ) {
	return bdtk_login_filter_url( $url, $scheme );
}

function bdtk_login_network_site_url( $url, $path, $scheme ) {
	return bdtk_login_filter_url( $url, $scheme );
}

function bdtk_login_wp_redirect( $location, $status ) {
	if ( false !== strpos( $location, 'https://wordpress.com/wp-login.php' ) ) {
		return $location;
	}

	return bdtk_login_filter_url( $location );
}

// A theme's "Log in" link on a 404 page would reveal the address to someone probing wp-login.php.
function bdtk_login_login_url( $login_url, $redirect, $force_reauth ) {
	if ( is_404() ) {
		return '#';
	}

	if ( false === $force_reauth || empty( $redirect ) ) {
		return $login_url;
	}

	$redirect = explode( '?', $redirect );

	return admin_url( 'options.php' ) === $redirect[0] ? admin_url() : $login_url;
}

// Privacy request confirmation links (export/erase data) are emailed as wp-login.php links.
function bdtk_login_privacy_redirect() {
	if ( ! isset( $_GET['action'], $_GET['request_id'], $_GET['confirm_key'] ) || 'confirmaction' !== $_GET['action'] ) {
		return;
	}

	$request_id = (int) $_GET['request_id'];
	$key        = sanitize_text_field( wp_unslash( $_GET['confirm_key'] ) );

	if ( ! is_wp_error( wp_validate_user_request_key( $request_id, $key ) ) ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'action'      => 'confirmaction',
					'request_id'  => $request_id,
					'confirm_key' => $key,
				),
				bdtk_login_new_url()
			)
		);
		exit;
	}
}

function bdtk_login_block_customizer() {
	global $pagenow;

	if ( ! is_user_logged_in() && 'customize.php' === $pagenow ) {
		wp_die( 'This has been disabled.', '', array( 'response' => 403 ) );
	}
}

function bdtk_login_block_signup() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? rawurldecode( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

	if ( ! is_multisite() && ( false !== strpos( $uri, 'wp-signup' ) || false !== strpos( $uri, 'wp-activate' ) ) ) {
		wp_die( 'This feature is not enabled.' );
	}
}

add_action( 'plugins_loaded', 'bdtk_login_detect_request', 9999 );
add_action( 'wp_loaded', 'bdtk_login_handle_request' );
add_action( 'setup_theme', 'bdtk_login_block_customizer', 1 );
add_action( 'init', 'bdtk_login_block_signup' );
add_action( 'template_redirect', 'bdtk_login_privacy_redirect' );
add_filter( 'site_url', 'bdtk_login_site_url', 10, 4 );
add_filter( 'network_site_url', 'bdtk_login_network_site_url', 10, 3 );
add_filter( 'wp_redirect', 'bdtk_login_wp_redirect', 10, 2 );
add_filter( 'login_url', 'bdtk_login_login_url', 10, 3 );

// /login, /admin and /dashboard normally redirect to the login page, which would reveal it.
remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
