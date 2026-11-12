<?php
/**
 * Duplicate posts, pages and CPTs (content, taxonomies, all meta incl. ACF) as a new draft.
 *
 * Post types are picked on Settings > BD Toolkit, or with the bdtk_duplicate_post_types filter.
 *
 * @package bd-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bdtk_duplicate_post_types() {
	return bdtk_module_post_types( 'duplicate' );
}

function bdtk_user_can_duplicate( $post ) {
	if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, bdtk_duplicate_post_types(), true ) ) {
		return false;
	}

	$post_type_object = get_post_type_object( $post->post_type );

	return current_user_can( 'edit_post', $post->ID ) && current_user_can( $post_type_object->cap->create_posts );
}

function bdtk_duplicate_url( $post_id ) {
	return wp_nonce_url( admin_url( 'admin.php?action=bdtk_duplicate_post&post=' . $post_id ), 'bdtk_duplicate_post_' . $post_id );
}

function bdtk_duplicate_post( $post ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return new WP_Error( 'bdtk_duplicate_missing', 'The item you are trying to duplicate no longer exists.' );
	}

	// wp_insert_post() expects slashed data — without wp_slash() block JSON and escaped content get mangled.
	$new_post_id = wp_insert_post(
		wp_slash(
			array(
				'post_type'      => $post->post_type,
				'post_status'    => 'draft',
				'post_title'     => $post->post_title . ' (Copy)',
				'post_content'   => $post->post_content,
				'post_excerpt'   => $post->post_excerpt,
				'post_author'    => get_current_user_id(),
				'post_parent'    => $post->post_parent,
				'menu_order'     => $post->menu_order,
				'post_password'  => $post->post_password,
				'comment_status' => $post->comment_status,
				'ping_status'    => $post->ping_status,
				'post_mime_type' => $post->post_mime_type,
			)
		),
		true
	);

	if ( is_wp_error( $new_post_id ) ) {
		return $new_post_id;
	}

	foreach ( get_object_taxonomies( $post->post_type ) as $taxonomy ) {
		$term_ids = wp_get_object_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );

		if ( ! is_wp_error( $term_ids ) && $term_ids ) {
			wp_set_object_terms( $new_post_id, $term_ids, $taxonomy );
		}
	}

	$excluded_meta = apply_filters(
		'bdtk_duplicate_excluded_meta',
		array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_wp_trash_meta_status', '_wp_trash_meta_time', '_wp_desired_post_slug' )
	);

	// Copies ACF flexible content / repeaters too — ACF stores everything as plain post meta.
	foreach ( get_post_meta( $post->ID ) as $meta_key => $meta_values ) {
		if ( in_array( $meta_key, $excluded_meta, true ) ) {
			continue;
		}

		// A save_post hook may already have written this key on the new post.
		delete_post_meta( $new_post_id, $meta_key );

		foreach ( $meta_values as $meta_value ) {
			add_post_meta( $new_post_id, $meta_key, wp_slash( maybe_unserialize( $meta_value ) ) );
		}
	}

	do_action( 'bdtk_duplicate_post', $new_post_id, $post );

	return $new_post_id;
}

function bdtk_duplicate_post_action() {
	$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;

	check_admin_referer( 'bdtk_duplicate_post_' . $post_id );

	if ( ! bdtk_user_can_duplicate( get_post( $post_id ) ) ) {
		wp_die( 'Sorry, you are not allowed to duplicate this item.', '', array( 'response' => 403 ) );
	}

	$new_post_id = bdtk_duplicate_post( $post_id );

	if ( is_wp_error( $new_post_id ) ) {
		wp_die( esc_html( $new_post_id->get_error_message() ) );
	}

	wp_safe_redirect( get_edit_post_link( $new_post_id, 'url' ) );
	exit;
}
add_action( 'admin_action_bdtk_duplicate_post', 'bdtk_duplicate_post_action' );

function bdtk_duplicate_row_action( $actions, $post ) {
	if ( 'trash' === $post->post_status || ! bdtk_user_can_duplicate( $post ) ) {
		return $actions;
	}

	$actions['bdtk_duplicate'] = sprintf(
		'<a href="%s" aria-label="%s">Duplicate</a>',
		esc_url( bdtk_duplicate_url( $post->ID ) ),
		esc_attr( 'Duplicate “' . _draft_or_post_title( $post ) . '”' )
	);

	return $actions;
}
add_filter( 'post_row_actions', 'bdtk_duplicate_row_action', 10, 2 );
add_filter( 'page_row_actions', 'bdtk_duplicate_row_action', 10, 2 );

// Classic editor (pages / ACF screens): link next to "Move to Trash".
function bdtk_duplicate_submitbox_link( $post ) {
	if ( 'auto-draft' === $post->post_status || ! bdtk_user_can_duplicate( $post ) ) {
		return;
	}
	?>
	<div class="bdtk_duplicate_action">
		<a href="<?php echo esc_url( bdtk_duplicate_url( $post->ID ) ); ?>">Duplicate</a>
	</div>
	<?php
}
add_action( 'post_submitbox_start', 'bdtk_duplicate_submitbox_link' );

// Admin bar link on the edit screen and when viewing the item on the front end.
function bdtk_duplicate_admin_bar( $wp_admin_bar ) {
	if ( is_admin() ) {
		$screen = get_current_screen();
		$post   = ( $screen && 'post' === $screen->base ) ? get_post() : null;
	} else {
		$post = is_singular() ? get_queried_object() : null;
	}

	if ( ! $post instanceof WP_Post || 'auto-draft' === $post->post_status || ! bdtk_user_can_duplicate( $post ) ) {
		return;
	}

	$wp_admin_bar->add_node(
		array(
			'id'    => 'bdtk-duplicate',
			'title' => 'Duplicate',
			'href'  => bdtk_duplicate_url( $post->ID ),
		)
	);
}
add_action( 'admin_bar_menu', 'bdtk_duplicate_admin_bar', 81 );

function bdtk_duplicate_register_bulk_actions() {
	foreach ( bdtk_duplicate_post_types() as $post_type ) {
		add_filter( 'bulk_actions-edit-' . $post_type, 'bdtk_duplicate_bulk_action' );
		add_filter( 'handle_bulk_actions-edit-' . $post_type, 'bdtk_duplicate_handle_bulk_action', 10, 3 );
	}
}
add_action( 'admin_init', 'bdtk_duplicate_register_bulk_actions' );

function bdtk_duplicate_bulk_action( $actions ) {
	$actions['bdtk_duplicate'] = 'Duplicate';
	return $actions;
}

// edit.php has already verified the bulk-posts nonce before this runs.
function bdtk_duplicate_handle_bulk_action( $redirect_to, $doaction, $post_ids ) {
	if ( 'bdtk_duplicate' !== $doaction ) {
		return $redirect_to;
	}

	$count = 0;

	foreach ( $post_ids as $post_id ) {
		if ( bdtk_user_can_duplicate( get_post( $post_id ) ) && ! is_wp_error( bdtk_duplicate_post( $post_id ) ) ) {
			$count++;
		}
	}

	return add_query_arg( 'bdtk_duplicated', $count, $redirect_to );
}

function bdtk_duplicate_bulk_notice() {
	if ( ! isset( $_GET['bdtk_duplicated'] ) ) {
		return;
	}

	$count = absint( $_GET['bdtk_duplicated'] );
	?>
	<div class="notice notice-success is-dismissible">
		<p><?php echo esc_html( 1 === $count ? '1 item duplicated as a draft.' : $count . ' items duplicated as drafts.' ); ?></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'bdtk_duplicate_bulk_notice' );

function bdtk_duplicate_removable_query_args( $args ) {
	$args[] = 'bdtk_duplicated';
	return $args;
}
add_filter( 'removable_query_args', 'bdtk_duplicate_removable_query_args' );
