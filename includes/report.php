<?php
/**
 * Daily site report — once a day, WP-Cron posts the site's WordPress, PHP, plugin and theme versions
 * to Bushwick's webhook so we can see what every site runs.
 *
 * Fire and forget: the request is non-blocking with a short timeout, only ever runs from cron (never on a
 * visitor's page load), reads cached update data only, and any error is swallowed, so a slow or broken
 * webhook can't affect the site.
 *
 * The webhook address and login are not in the code (the repo is public). Set them per site in
 * wp-config.php; the report stays off until BDTK_REPORT_URL is set:
 *
 *   define( 'BDTK_REPORT_URL', 'https://...' );
 *   define( 'BDTK_REPORT_AUTH', 'user:password' );
 *
 * define( 'BDTK_REPORT_OFF', true ); turns it off even when both are set.
 *
 * @package bd-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bdtk_report_url() {
	return defined( 'BDTK_REPORT_URL' ) ? (string) BDTK_REPORT_URL : '';
}

function bdtk_report_enabled() {
	return '' !== bdtk_report_url() && ! ( defined( 'BDTK_REPORT_OFF' ) && BDTK_REPORT_OFF );
}

// Each site gets a random time of day so the webhook isn't hit by every site at once.
function bdtk_report_schedule() {
	if ( ! bdtk_report_enabled() ) {
		wp_clear_scheduled_hook( 'bdtk_daily_report' );
		return;
	}

	if ( ! wp_next_scheduled( 'bdtk_daily_report' ) ) {
		wp_schedule_event( time() + wp_rand( MINUTE_IN_SECONDS, DAY_IN_SECONDS ), 'daily', 'bdtk_daily_report' );
	}
}
add_action( 'init', 'bdtk_report_schedule' );

function bdtk_report_unschedule() {
	wp_clear_scheduled_hook( 'bdtk_daily_report' );
}

function bdtk_report_data() {
	global $wpdb;

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin_updates = get_site_transient( 'update_plugins' );
	$theme_updates  = get_site_transient( 'update_themes' );
	$auto_updates   = (array) get_site_option( 'auto_update_plugins', array() );
	$plugins        = array();
	$themes         = array();

	foreach ( get_plugins() as $file => $plugin ) {
		$plugins[] = array(
			'file'        => $file,
			'name'        => $plugin['Name'],
			'version'     => $plugin['Version'],
			'active'      => is_plugin_active( $file ),
			'update'      => isset( $plugin_updates->response[ $file ]->new_version ) ? $plugin_updates->response[ $file ]->new_version : null,
			'auto_update' => in_array( $file, $auto_updates, true ),
		);
	}

	foreach ( get_mu_plugins() as $file => $plugin ) {
		$plugins[] = array(
			'file'        => $file,
			'name'        => $plugin['Name'],
			'version'     => $plugin['Version'],
			'active'      => true,
			'must_use'    => true,
		);
	}

	$active_theme = get_stylesheet();

	foreach ( wp_get_themes() as $stylesheet => $theme ) {
		$themes[] = array(
			'stylesheet' => $stylesheet,
			'name'       => $theme->get( 'Name' ),
			'version'    => $theme->get( 'Version' ),
			'active'     => $active_theme === $stylesheet,
			'parent'     => $theme->parent() ? $theme->get_template() : null,
			'update'     => isset( $theme_updates->response[ $stylesheet ]['new_version'] ) ? $theme_updates->response[ $stylesheet ]['new_version'] : null,
		);
	}

	return array(
		'site'      => array(
			'name'        => get_bloginfo( 'name' ),
			'home_url'    => home_url( '/' ),
			'site_url'    => site_url( '/' ),
			'environment' => wp_get_environment_type(),
			'multisite'   => is_multisite(),
			'language'    => get_locale(),
			'timezone'    => wp_timezone_string(),
		),
		'wordpress' => array(
			'version'        => get_bloginfo( 'version' ),
			'update'         => bdtk_report_core_update(),
			'debug'          => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'search_engines' => (bool) get_option( 'blog_public' ),
		),
		'server'    => array(
			'php'          => PHP_VERSION,
			'database'     => $wpdb->db_server_info(),
			'software'     => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : null,
			'memory_limit' => ini_get( 'memory_limit' ),
			'wp_memory'    => WP_MEMORY_LIMIT,
		),
		'plugins'   => $plugins,
		'themes'    => $themes,
		'reporter'  => array(
			'plugin'  => 'bd-toolkit',
			'version' => BDTK_VERSION,
			'sent_at' => gmdate( 'c' ),
		),
	);
}

function bdtk_report_core_update() {
	$core = get_site_transient( 'update_core' );

	if ( empty( $core->updates ) ) {
		return null;
	}

	foreach ( $core->updates as $update ) {
		if ( 'upgrade' === $update->response ) {
			return $update->current;
		}
	}

	return null;
}

// Blocking only for `wp bdtk report`, so the result can be shown.
function bdtk_report_send( $blocking = false ) {
	if ( '' === bdtk_report_url() ) {
		return new WP_Error( 'bdtk_report_no_url', 'No webhook address: set BDTK_REPORT_URL in wp-config.php.' );
	}

	$headers = array( 'Content-Type' => 'application/json' );

	if ( defined( 'BDTK_REPORT_AUTH' ) && BDTK_REPORT_AUTH ) {
		$headers['Authorization'] = 'Basic ' . base64_encode( BDTK_REPORT_AUTH ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	try {
		return wp_remote_post(
			bdtk_report_url(),
			array(
				'blocking'   => $blocking,
				'timeout'    => $blocking ? 15 : 3,
				'user-agent' => 'BD Toolkit/' . BDTK_VERSION . '; ' . home_url( '/' ),
				'headers'    => $headers,
				'body'       => wp_json_encode( bdtk_report_data() ),
			)
		);
	} catch ( Throwable $e ) {
		return new WP_Error( 'bdtk_report_failed', $e->getMessage() );
	}
}

function bdtk_report_cron() {
	if ( bdtk_report_enabled() ) {
		bdtk_report_send();
	}
}
add_action( 'bdtk_daily_report', 'bdtk_report_cron' );
