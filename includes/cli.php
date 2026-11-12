<?php
/**
 * WP-CLI commands.
 *
 * @package bd-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows the site's login address.
 *
 * ## EXAMPLES
 *
 *     wp bdtk login-url
 */
function bdtk_cli_login_url() {
	if ( bdtk_login_url_active() ) {
		$settings = bdtk_get_settings();
		WP_CLI::line( bdtk_login_url_for_slug( $settings['login_url']['slug'] ) );
		return;
	}

	$note = defined( 'BDTK_LOGIN_URL_OFF' ) && BDTK_LOGIN_URL_OFF ? 'custom login address switched off in wp-config.php' : 'custom login address is off';

	WP_CLI::line( site_url( 'wp-login.php', 'login' ) . ' (' . $note . ')' );
}

WP_CLI::add_command( 'bdtk login-url', 'bdtk_cli_login_url' );

/**
 * Sends the daily site report now and shows the webhook's response.
 *
 * ## OPTIONS
 *
 * [--dry-run]
 * : Print the report instead of sending it.
 *
 * ## EXAMPLES
 *
 *     wp bdtk report
 *     wp bdtk report --dry-run
 */
function bdtk_cli_report( $args, $assoc_args ) {
	if ( ! empty( $assoc_args['dry-run'] ) ) {
		WP_CLI::line( wp_json_encode( bdtk_report_data(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
		return;
	}

	if ( '' === bdtk_report_url() ) {
		WP_CLI::error( 'The site report has no webhook address. Set BDTK_REPORT_URL (and BDTK_REPORT_AUTH) in wp-config.php.' );
	}

	if ( ! bdtk_report_enabled() ) {
		WP_CLI::warning( 'The daily report is switched off in wp-config.php (BDTK_REPORT_OFF). Sending once anyway.' );
	}

	$response = bdtk_report_send( true );

	if ( is_wp_error( $response ) ) {
		WP_CLI::error( $response->get_error_message() );
	}

	$next = wp_next_scheduled( 'bdtk_daily_report' );

	WP_CLI::line( 'Webhook responded ' . wp_remote_retrieve_response_code( $response ) . ': ' . wp_remote_retrieve_body( $response ) );
	WP_CLI::line( 'Next daily report: ' . ( $next ? wp_date( 'Y-m-d H:i T', $next ) : 'not scheduled' ) );
}

WP_CLI::add_command( 'bdtk report', 'bdtk_cli_report' );
