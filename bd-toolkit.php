<?php
/**
 * Plugin Name: BD Toolkit
 * Description: Duplicate and reorder pages and posts, set a custom login address, keep usernames private, and turn off sign-in methods the site doesn't need.
 * Version: 1.2.1
 * Author: BD
 * Author URI: https://bushwickdigital.com
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 *
 * @package bd-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BDTK_VERSION', '1.2.1' );
define( 'BDTK_DIR', plugin_dir_path( __FILE__ ) );
define( 'BDTK_URL', plugin_dir_url( __FILE__ ) );

require BDTK_DIR . 'includes/helpers.php';
require BDTK_DIR . 'includes/settings.php';

// Loaded right away, not after the theme: it has to see the request on plugins_loaded.
if ( bdtk_login_url_active() ) {
	require BDTK_DIR . 'includes/login-url.php';
}

require BDTK_DIR . 'includes/report.php';
register_deactivation_hook( __FILE__, 'bdtk_report_unschedule' );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require BDTK_DIR . 'includes/cli.php';
}

require BDTK_DIR . 'includes/updater.php';

// Modules load after the theme so we can step aside when a theme already ships the same feature
// (bd-theme-2024 98f1a49 had duplicate + reorder built in).
function bdtk_load_modules() {
	if ( bdtk_module_enabled( 'duplicate' ) && ! bdtk_theme_has_module( 'duplicate' ) ) {
		require BDTK_DIR . 'includes/duplicate.php';
	}

	if ( bdtk_module_enabled( 'reorder' ) && ! bdtk_theme_has_module( 'reorder' ) ) {
		require BDTK_DIR . 'includes/reorder.php';
	}

	if ( bdtk_module_enabled( 'enumeration' ) ) {
		require BDTK_DIR . 'includes/user-enumeration.php';
	}

	if ( bdtk_module_enabled( 'feeds' ) ) {
		require BDTK_DIR . 'includes/feeds.php';
	}

	if ( bdtk_module_enabled( 'hardening' ) ) {
		require BDTK_DIR . 'includes/hardening.php';
	}
}
add_action( 'after_setup_theme', 'bdtk_load_modules', 20 );
