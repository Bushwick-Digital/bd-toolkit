<?php
/**
 * Shared helpers and settings access.
 *
 * @package bd-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Each module: settings tab, label, on/off default (1 unless set), description, post type list, extra options.
function bdtk_modules() {
	return array(
		'duplicate'   => array(
			'tab'         => 'content',
			'label'       => 'Duplicate',
			'description' => 'Create a draft copy of a page or post with all of its content, sections and settings. Use the Duplicate link in the list, the bulk actions menu, the editor or the toolbar.',
			'post_types'  => true,
			'options'     => array(),
		),
		'reorder'     => array(
			'tab'         => 'content',
			'label'       => 'Drag-and-drop ordering',
			'default'     => 0,
			'description' => 'Drag pages and posts into the order you want, or move them with the arrow keys. Changes save automatically.',
			'post_types'  => true,
			'options'     => array(
				'front_end' => array(
					'label'       => 'Show this order on the website',
					'description' => 'Lists on the website follow the order you set. Items you haven\'t moved stay newest first.',
					'default'     => 1,
				),
			),
		),
		'login_url'   => array(
			'tab'         => 'login',
			'label'       => 'Custom login address',
			'default'     => 0,
			'description' => 'Moves the login page to an address you choose. Visitors who aren\'t logged in see a "page not found" page at the usual login and admin addresses.',
			'post_types'  => false,
			'options'     => array(
				'slug' => array(
					'type'        => 'text',
					'label'       => 'Login address',
					'description' => 'Use lowercase letters, numbers and dashes. Whenever it changes, the new address is emailed to the site admin.',
					'default'     => 'site-login',
				),
			),
		),
		'enumeration' => array(
			'tab'         => 'security',
			'label'       => 'Keep usernames private',
			'description' => 'Hides account usernames from visitors so they can\'t be used to guess logins. Logged-in users aren\'t affected.',
			'post_types'  => false,
			'options'     => array(
				'author_query' => array(
					'label'       => 'Block author lookups',
					'description' => 'Stops visitors from finding usernames through numbered author links.',
					'default'     => 1,
				),
				'rest_users'   => array(
					'label'       => 'Hide the user list',
					'description' => 'Only logged-in users can view the list of site users. Not suitable if a connected service needs public access to it.',
					'default'     => 1,
				),
				'oembed'       => array(
					'label'       => 'Hide author links in embeds',
					'description' => 'When a page is embedded on another website, the author\'s name is shown without a link to their profile.',
					'default'     => 1,
				),
				'sitemap'      => array(
					'label'       => 'Leave authors out of the sitemap',
					'description' => 'The sitemap that search engines read won\'t list author pages.',
					'default'     => 1,
				),
				'feeds'        => array(
					'label'       => 'Hide author names in feeds',
					'description' => 'RSS and Atom feeds show the site name as the author instead of the author\'s display name, which is often their username.',
					'default'     => 1,
				),
				'author_archives' => array(
					'label'       => 'Redirect author pages',
					'description' => 'Author pages show the username in their web address, so visitors are sent to the home page instead. Not suitable for sites that use author pages.',
					'default'     => 1,
				),
				'login_errors' => array(
					'label'       => 'Use one login error message',
					'description' => 'A wrong username, email or password shows the same message, and password reset requests look the same whether or not the account exists.',
					'default'     => 1,
				),
			),
		),
		'feeds'       => array(
			'tab'         => 'security',
			'label'       => 'Turn off feeds',
			'description' => 'Search engines read the sitemap, not RSS feeds, so most sites don\'t need them. Feed addresses send visitors to the page they belong to. Not suitable if a newsletter tool, such as a Mailchimp RSS campaign, builds emails from the feed.',
			'post_types'  => false,
			'options'     => array(
				'post_feeds'    => array(
					'label'       => 'Turn off post feeds',
					'description' => 'The main feed and the feeds for categories, tags and other lists.',
					'default'     => 1,
				),
				'comment_feeds' => array(
					'label'       => 'Turn off comment feeds',
					'description' => 'The site-wide comments feed and the comments feed of each post.',
					'default'     => 1,
				),
			),
		),
		'hardening'   => array(
			'tab'         => 'security',
			'label'       => 'Extra login protection',
			'description' => 'Turns off alternative ways of signing in that most websites don\'t need.',
			'post_types'  => false,
			'options'     => array(
				'xmlrpc'        => array(
					'label'       => 'Block XML-RPC',
					'description' => 'An older connection method that is a common target for automated login attempts. Not suitable for sites that use Jetpack or the WordPress mobile app.',
					'default'     => 1,
				),
				'app_passwords' => array(
					'label'       => 'Block application passwords',
					'description' => 'Application passwords let outside apps and services sign in to the site. Not suitable if a connected service, such as Zapier, signs in with one.',
					'default'     => 1,
				),
			),
		),
	);
}

// Settings store *excluded* post types, so a CPT registered later is enabled by default.
function bdtk_get_settings() {
	$settings = get_option( 'bdtk_settings', array() );
	$settings = is_array( $settings ) ? $settings : array();

	foreach ( bdtk_modules() as $module => $config ) {
		$defaults = array(
			'enabled'  => isset( $config['default'] ) ? $config['default'] : 1,
			'excluded' => array(),
		);

		foreach ( $config['options'] as $key => $option ) {
			$defaults[ $key ] = $option['default'];
		}

		$settings[ $module ] = wp_parse_args( isset( $settings[ $module ] ) ? (array) $settings[ $module ] : array(), $defaults );
	}

	return $settings;
}

function bdtk_settings_tabs() {
	return array(
		'content'  => 'Content',
		'security' => 'Security',
		'login'    => 'Login',
	);
}

function bdtk_module_enabled( $module ) {
	$settings = bdtk_get_settings();
	return ! empty( $settings[ $module ]['enabled'] );
}

// Theme functions that mean the theme already provides the module.
function bdtk_theme_has_module( $module ) {
	$theme_functions = array(
		'duplicate' => 'bd_duplicate_post',
		'reorder'   => 'bd_sortable_save',
	);

	return isset( $theme_functions[ $module ] ) && function_exists( $theme_functions[ $module ] );
}

// Content post types (posts, pages, CPTs) — skips attachments, core internals and ACF/ACFE admin types.
function bdtk_get_content_post_types() {
	$post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
	$excluded   = array( 'attachment', 'wp_block', 'wp_navigation', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_font_family', 'wp_font_face' );
	$result     = array();

	foreach ( $post_types as $name => $post_type ) {
		if ( in_array( $name, $excluded, true ) || 0 === strpos( $name, 'acf' ) ) {
			continue;
		}
		$result[ $name ] = $post_type->labels->name;
	}

	return $result;
}

// Post types a module is active for, after settings and the bdtk_{module}_post_types filter.
function bdtk_module_post_types( $module ) {
	$settings   = bdtk_get_settings();
	$post_types = array_diff( array_keys( bdtk_get_content_post_types() ), (array) $settings[ $module ]['excluded'] );

	return apply_filters( 'bdtk_' . $module . '_post_types', array_values( $post_types ) );
}

// The custom login address is on, unless switched off in wp-config.php with
// define( 'BDTK_LOGIN_URL_OFF', true ); — the recovery route if the address is forgotten.
function bdtk_login_url_active() {
	return bdtk_module_enabled( 'login_url' ) && ! ( defined( 'BDTK_LOGIN_URL_OFF' ) && BDTK_LOGIN_URL_OFF );
}

function bdtk_login_trailingslashit( $string ) {
	return '/' === substr( (string) get_option( 'permalink_structure' ), -1, 1 ) ? trailingslashit( $string ) : untrailingslashit( $string );
}

function bdtk_login_url_for_slug( $slug, $scheme = null ) {
	$home = home_url( '/', $scheme );

	return get_option( 'permalink_structure' ) ? bdtk_login_trailingslashit( $home . $slug ) : $home . '?' . $slug;
}

// Returns a message when the slug can't be used, or '' when it's fine.
function bdtk_login_slug_problem( $slug ) {
	if ( '' === $slug ) {
		return 'Please enter a login address.';
	}

	$wp       = new WP();
	$reserved = array_merge( $wp->public_query_vars, $wp->private_query_vars, array( 'wp-admin', 'wp-login', 'wp-login-php', 'wp-content', 'wp-includes', 'wp-json', 'admin', 'dashboard', 'feed', '404' ) );

	if ( in_array( $slug, $reserved, true ) ) {
		return 'That login address is reserved by WordPress. Please choose another.';
	}

	if ( get_page_by_path( $slug, OBJECT, array_values( get_post_types( array( 'public' => true ) ) ) ) ) {
		return 'A page or post already uses that address. Please choose another login address.';
	}

	return '';
}
