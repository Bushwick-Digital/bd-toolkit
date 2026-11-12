<?php
/**
 * Removes BD Toolkit settings when the plugin is deleted. Saved menu_order values stay on posts.
 *
 * @package bd-toolkit
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'bdtk_settings' );
delete_option( 'external_updates-bd-toolkit' );
wp_clear_scheduled_hook( 'bdtk_daily_report' );
