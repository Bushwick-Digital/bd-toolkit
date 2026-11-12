<?php
/**
 * Updates from the public GitHub repo's releases, through Plugin Update Checker 5.7 (MIT):
 * https://github.com/YahnisElsts/plugin-update-checker
 *
 * The repo is public, so update checks need no login and nothing private is stored on sites.
 *
 * @package bd-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require BDTK_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';

function bdtk_updater_init() {
	$checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/Bushwick-Digital/bd-toolkit/',
		BDTK_DIR . 'bd-toolkit.php',
		'bd-toolkit'
	);

	$checker->getVcsApi()->enableReleaseAssets( '/^bd-toolkit\.zip$/', YahnisElsts\PluginUpdateChecker\v5p7\Vcs\Api::REQUIRE_RELEASE_ASSETS );
}

// Releases only: install from the bd-toolkit.zip attached to each release. GitHub's own tag and
// branch zips unpack into a differently named folder.
function bdtk_updater_strategies( $strategies ) {
	return array_intersect_key( $strategies, array( 'latest_release' => true ) );
}

bdtk_updater_init();
add_filter( 'puc_vcs_update_detection_strategies-bd-toolkit', 'bdtk_updater_strategies' );
