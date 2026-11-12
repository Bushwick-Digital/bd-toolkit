<?php
/**
 * Settings > BD Toolkit — tabbed cards: each module has an on/off switch, post types and options.
 *
 * @package bd-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bdtk_register_settings() {
	register_setting(
		'bdtk_settings',
		'bdtk_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'bdtk_sanitize_settings',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'bdtk_register_settings' );

// The form posts the *checked* post types; we store the unchecked ones as excluded.
// Each tab posts its module list in _modules, so saving one tab leaves the other tab's settings alone.
// WordPress runs this twice on the first save (update_option, then add_option), and the second
// pass receives our own output (no _modules, 'excluded' instead of 'post_types') — kept as-is.
function bdtk_sanitize_settings( $input ) {
	$input      = (array) $input;
	$post_types = array_keys( bdtk_get_content_post_types() );
	$output     = bdtk_get_settings();
	$submitted  = isset( $input['_modules'] ) ? array_map( 'sanitize_key', (array) $input['_modules'] ) : array_keys( bdtk_modules() );

	foreach ( bdtk_modules() as $module => $config ) {
		if ( ! in_array( $module, $submitted, true ) ) {
			continue;
		}

		$module_input = isset( $input[ $module ] ) ? (array) $input[ $module ] : array();
		$previous     = $output[ $module ];

		$output[ $module ] = array(
			'enabled' => empty( $module_input['enabled'] ) ? 0 : 1,
		);

		if ( $config['post_types'] ) {
			if ( isset( $module_input['excluded'] ) && ! isset( $module_input['post_types'] ) ) {
				$excluded = array_map( 'sanitize_key', (array) $module_input['excluded'] );
			} else {
				$checked  = isset( $module_input['post_types'] ) ? array_map( 'sanitize_key', (array) $module_input['post_types'] ) : array();
				$excluded = array_diff( $post_types, $checked );
			}

			$output[ $module ]['excluded'] = array_values( $excluded );
		}

		foreach ( $config['options'] as $key => $option ) {
			if ( isset( $option['type'] ) && 'text' === $option['type'] ) {
				$output[ $module ][ $key ] = bdtk_sanitize_text_option( $key, isset( $module_input[ $key ] ) ? $module_input[ $key ] : '', $previous[ $key ] );
			} else {
				$output[ $module ][ $key ] = empty( $module_input[ $key ] ) ? 0 : 1;
			}
		}
	}

	return $output;
}

// Text options are slugs for now (the login address). An unusable value keeps the previous one.
function bdtk_sanitize_text_option( $key, $value, $previous ) {
	$value   = sanitize_title( (string) $value );
	$problem = 'slug' === $key ? bdtk_login_slug_problem( $value ) : '';

	if ( $problem ) {
		add_settings_error( 'bdtk_settings', 'bdtk_' . $key, $problem );
		return $previous;
	}

	return $value;
}

// Email the site admin whenever the login address is set or changed, so it's never only in someone's head.
function bdtk_login_url_changed( $old_value, $value ) {
	$old_slug = ! empty( $old_value['login_url']['enabled'] ) && isset( $old_value['login_url']['slug'] ) ? $old_value['login_url']['slug'] : '';
	$new_slug = ! empty( $value['login_url']['enabled'] ) && isset( $value['login_url']['slug'] ) ? $value['login_url']['slug'] : '';

	if ( '' === $new_slug || $old_slug === $new_slug || ( defined( 'BDTK_LOGIN_URL_OFF' ) && BDTK_LOGIN_URL_OFF ) ) {
		return;
	}

	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$url  = bdtk_login_url_for_slug( $new_slug, 'login' );

	wp_mail(
		get_option( 'admin_email' ),
		sprintf( '[%s] Your login address has changed', $site ),
		sprintf( "Hello,\n\nThe login address for %s has changed. You can now log in at:\n\n%s\n\nPlease bookmark this address. The usual WordPress login address no longer shows the login page.\n", $site, $url )
	);
}
add_action( 'update_option_bdtk_settings', 'bdtk_login_url_changed', 10, 2 );

function bdtk_login_url_added( $option, $value ) {
	bdtk_login_url_changed( array(), $value );
}
add_action( 'add_option_bdtk_settings', 'bdtk_login_url_added', 10, 2 );

function bdtk_add_settings_page() {
	add_options_page( 'BD Toolkit', 'BD Toolkit', 'manage_options', 'bd-toolkit', 'bdtk_render_settings_page' );
}
add_action( 'admin_menu', 'bdtk_add_settings_page' );

function bdtk_settings_assets( $hook ) {
	if ( 'settings_page_bd-toolkit' === $hook ) {
		wp_enqueue_style( 'bdtk-settings', BDTK_URL . 'assets/settings.css', array(), BDTK_VERSION );
		wp_enqueue_script( 'bdtk-settings', BDTK_URL . 'assets/settings.js', array( 'jquery', 'wp-a11y' ), BDTK_VERSION, true );
	}
}
add_action( 'admin_enqueue_scripts', 'bdtk_settings_assets' );

function bdtk_plugin_action_links( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=bd-toolkit' ) ) . '">Settings</a>' );
	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( BDTK_DIR . 'bd-toolkit.php' ), 'bdtk_plugin_action_links' );

function bdtk_render_login_url_status() {
	if ( defined( 'BDTK_LOGIN_URL_OFF' ) && BDTK_LOGIN_URL_OFF ) {
		echo '<p class="bdtk-login-status">The custom login address is switched off in the site configuration, so the standard WordPress login is in use.</p>';
		return;
	}

	if ( ! bdtk_login_url_active() ) {
		return;
	}

	$settings = bdtk_get_settings();
	$url      = bdtk_login_url_for_slug( $settings['login_url']['slug'], 'login' );
	?>
	<p class="bdtk-login-status">
		Log in at <a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $url ); ?></a>
		<button type="button" class="button button-small bdtk-copy" data-copy="<?php echo esc_attr( $url ); ?>">Copy</button>
	</p>
	<?php
}

function bdtk_current_tab() {
	$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : '';
	return array_key_exists( $tab, bdtk_settings_tabs() ) ? $tab : 'content';
}

function bdtk_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings   = bdtk_get_settings();
	$post_types = bdtk_get_content_post_types();
	$tab        = bdtk_current_tab();
	$modules    = wp_list_filter( bdtk_modules(), array( 'tab' => $tab ) );
	?>
	<div class="wrap bdtk-settings">
		<h1>BD Toolkit <span class="bdtk-version">v<?php echo esc_html( BDTK_VERSION ); ?></span></h1>
		<p class="bdtk-intro">Tools for managing content and keeping this site secure.</p>
		<hr class="wp-header-end">

		<nav class="nav-tab-wrapper" aria-label="BD Toolkit sections">
			<?php foreach ( bdtk_settings_tabs() as $tab_key => $tab_label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'bd-toolkit', 'tab' => $tab_key ), admin_url( 'options-general.php' ) ) ); ?>" class="nav-tab<?php echo $tab === $tab_key ? ' nav-tab-active' : ''; ?>"<?php echo $tab === $tab_key ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $tab_label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<form method="post" action="options.php">
			<?php settings_fields( 'bdtk_settings' ); ?>

			<div class="bdtk-cards">
				<?php foreach ( $modules as $module => $config ) : ?>
					<?php
					$name   = 'bdtk_settings[' . $module . ']';
					$id     = 'bdtk-' . $module;
					$paused = bdtk_theme_has_module( $module );
					?>
					<input type="hidden" name="bdtk_settings[_modules][]" value="<?php echo esc_attr( $module ); ?>">

					<section class="bdtk-card" aria-labelledby="<?php echo esc_attr( $id ); ?>-title">
						<div class="bdtk-card__header">
							<div class="bdtk-card__heading">
								<h2 id="<?php echo esc_attr( $id ); ?>-title">
									<?php echo esc_html( $config['label'] ); ?>
									<?php if ( $paused ) : ?>
										<span class="bdtk-badge">Provided by your theme</span>
									<?php endif; ?>
								</h2>
								<p id="<?php echo esc_attr( $id ); ?>-desc"><?php echo esc_html( $config['description'] ); ?></p>
							</div>
							<label class="bdtk-switch">
								<input type="checkbox" role="switch" class="bdtk-module-toggle" name="<?php echo esc_attr( $name ); ?>[enabled]" value="1" aria-describedby="<?php echo esc_attr( $id ); ?>-desc" <?php checked( $settings[ $module ]['enabled'] ); ?>>
								<span class="bdtk-switch__track" aria-hidden="true"></span>
								<span class="screen-reader-text">Enable <?php echo esc_html( $config['label'] ); ?></span>
							</label>
						</div>

						<div class="bdtk-card__body">
							<?php if ( $config['post_types'] ) : ?>
								<fieldset class="bdtk-post-types">
									<legend>Use on</legend>
									<div class="bdtk-chips">
										<?php foreach ( $post_types as $post_type => $post_type_label ) : ?>
											<label class="bdtk-chip">
												<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[post_types][]" value="<?php echo esc_attr( $post_type ); ?>" <?php checked( ! in_array( $post_type, $settings[ $module ]['excluded'], true ) ); ?>>
												<span><?php echo esc_html( $post_type_label ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
									<p class="bdtk-hint">New content types are included automatically.</p>
								</fieldset>
							<?php endif; ?>

							<?php if ( $config['options'] ) : ?>
								<ul class="bdtk-options">
									<?php foreach ( $config['options'] as $key => $option ) : ?>
										<?php $option_id = $id . '-' . str_replace( '_', '-', $key ); ?>
										<?php if ( isset( $option['type'] ) && 'text' === $option['type'] ) : ?>
											<li class="bdtk-option bdtk-option--text">
												<div class="bdtk-option__text">
													<label for="<?php echo esc_attr( $option_id ); ?>" class="bdtk-option__label"><?php echo esc_html( $option['label'] ); ?></label>
													<p id="<?php echo esc_attr( $option_id ); ?>-desc"><?php echo esc_html( $option['description'] ); ?></p>
													<div class="bdtk-url-field">
														<span class="bdtk-url-field__prefix" aria-hidden="true"><?php echo esc_html( get_option( 'permalink_structure' ) ? trailingslashit( home_url() ) : home_url( '/?' ) ); ?></span>
														<input type="text" id="<?php echo esc_attr( $option_id ); ?>" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( $settings[ $module ][ $key ] ); ?>" class="code" spellcheck="false" autocomplete="off" aria-describedby="<?php echo esc_attr( $option_id ); ?>-desc">
													</div>
													<?php if ( 'login_url' === $module ) : ?>
														<?php bdtk_render_login_url_status(); ?>
													<?php endif; ?>
												</div>
											</li>
											<?php continue; ?>
										<?php endif; ?>
										<li class="bdtk-option">
											<label class="bdtk-switch bdtk-switch--small">
												<input type="checkbox" role="switch" id="<?php echo esc_attr( $option_id ); ?>" name="<?php echo esc_attr( $name . '[' . $key . ']' ); ?>" value="1" aria-describedby="<?php echo esc_attr( $option_id ); ?>-desc" <?php checked( $settings[ $module ][ $key ] ); ?>>
												<span class="bdtk-switch__track" aria-hidden="true"></span>
											</label>
											<div class="bdtk-option__text">
												<label for="<?php echo esc_attr( $option_id ); ?>" class="bdtk-option__label"><?php echo esc_html( $option['label'] ); ?></label>
												<p id="<?php echo esc_attr( $option_id ); ?>-desc"><?php echo esc_html( $option['description'] ); ?></p>
											</div>
										</li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
						</div>
					</section>
				<?php endforeach; ?>
			</div>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
