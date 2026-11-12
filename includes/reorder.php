<?php
/**
 * Drag-and-drop ordering (menu_order) for posts, pages and CPTs in the admin list tables.
 *
 * Post types are picked on Settings > BD Toolkit, or with the bdtk_reorder_post_types filter.
 * Front-end queries for these types without an explicit orderby follow the saved order (setting).
 *
 * @package bd-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bdtk_sortable_post_types() {
	return bdtk_module_post_types( 'reorder' );
}

function bdtk_user_can_sort( $post_type ) {
	$post_type_object = get_post_type_object( $post_type );

	return $post_type_object
		&& in_array( $post_type, bdtk_sortable_post_types(), true )
		&& current_user_can( $post_type_object->cap->edit_others_posts );
}

// Drag is only active while the list shows the saved order (not sorted by a column, not trash).
function bdtk_sortable_is_active_screen() {
	if ( ! bdtk_sortable_is_sortable_screen() ) {
		return false;
	}

	if ( ! empty( $_GET['orderby'] ) || ( isset( $_GET['post_status'] ) && 'trash' === $_GET['post_status'] ) ) {
		return false;
	}

	return true;
}

// The handle column is added on the screen-level filter at a late priority, after column managers like
// Admin Columns Pro (priority 200) have replaced the headings with their own saved column set.
function bdtk_sortable_register_columns() {
	foreach ( bdtk_sortable_post_types() as $post_type ) {
		add_filter( 'manage_edit-' . $post_type . '_columns', 'bdtk_sortable_column', 999 );
		add_action( 'manage_' . $post_type . '_posts_custom_column', 'bdtk_sortable_column_content', 10, 2 );
	}
}
add_action( 'admin_init', 'bdtk_sortable_register_columns' );

function bdtk_sortable_column( $columns ) {
	$screen = get_current_screen();

	if ( ! $screen || ! bdtk_user_can_sort( $screen->post_type ) || isset( $columns['bdtk_order'] ) ) {
		return $columns;
	}

	$order_column = array( 'bdtk_order' => '<span class="screen-reader-text">Order</span>' );

	if ( ! isset( $columns['cb'] ) ) {
		return array_merge( $order_column, $columns );
	}

	$new_columns = array();

	foreach ( $columns as $key => $label ) {
		$new_columns[ $key ] = $label;

		if ( 'cb' === $key ) {
			$new_columns = array_merge( $new_columns, $order_column );
		}
	}

	return $new_columns;
}

function bdtk_sortable_column_content( $column, $post_id ) {
	if ( 'bdtk_order' !== $column ) {
		return;
	}

	printf(
		'<button type="button" class="bdtk_sort_handle" data-parent="%1$d" title="Drag to reorder" aria-label="%2$s" aria-describedby="bdtk-sort-help"><span class="dashicons dashicons-menu" aria-hidden="true"></span></button>',
		absint( wp_get_post_parent_id( $post_id ) ),
		esc_attr( 'Reorder “' . _draft_or_post_title( $post_id ) . '”' )
	);
}

// Admin Columns Pro re-applies a remembered (or list default) column sort whenever the list is opened
// without ?orderby, which would keep the handles switched off. Sortable types open in the saved order.
function bdtk_sortable_is_sortable_screen() {
	$screen = get_current_screen();

	return $screen && 'edit' === $screen->base && bdtk_user_can_sort( $screen->post_type );
}

function bdtk_sortable_acp_remember_sorting( $remember ) {
	return bdtk_sortable_is_sortable_screen() ? false : $remember;
}
add_filter( 'ac/sorting/remember_last_sorting_preference', 'bdtk_sortable_acp_remember_sorting' );

function bdtk_sortable_acp_default_sorting( $args ) {
	return bdtk_sortable_is_sortable_screen() ? array() : $args;
}
add_filter( 'ac/sorting/default', 'bdtk_sortable_acp_default_sorting' );

// A sort Admin Columns Pro remembered before (or from another plugin version) is dropped when the plain list opens.
function bdtk_sortable_acp_forget_sorting( $list_screen ) {
	if ( ! empty( $_GET['orderby'] ) || ! bdtk_sortable_is_sortable_screen() || ! class_exists( 'ACP\Sorting\UserPreference\SortType' ) ) {
		return;
	}

	ACP\Sorting\UserPreference\SortType::create( $list_screen )->delete();
}
add_action( 'ac/table/list_screen', 'bdtk_sortable_acp_forget_sorting', 10 ); // Before ACP applies it at 11.

function bdtk_sortable_admin_assets( $hook ) {
	if ( 'edit.php' !== $hook ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || ! bdtk_user_can_sort( $screen->post_type ) ) {
		return;
	}

	// Handles stay hidden until admin.js activates them, so sorted-by-column views show an empty column.
	wp_enqueue_style( 'bdtk-admin', BDTK_URL . 'assets/admin.css', array(), BDTK_VERSION );

	if ( ! bdtk_sortable_is_active_screen() ) {
		return;
	}

	wp_enqueue_script( 'bdtk-admin', BDTK_URL . 'assets/admin.js', array( 'jquery', 'jquery-ui-sortable', 'wp-a11y' ), BDTK_VERSION, true );
	wp_localize_script(
		'bdtk-admin',
		'bdtkSortable',
		array(
			'nonce'    => wp_create_nonce( 'bdtk_sortable' ),
			'postType' => $screen->post_type,
		)
	);
}
add_action( 'admin_enqueue_scripts', 'bdtk_sortable_admin_assets' );

function bdtk_sortable_help_text() {
	if ( ! bdtk_sortable_is_active_screen() ) {
		return;
	}
	?>
	<p id="bdtk-sort-help" class="screen-reader-text">Drag to reorder, or press the up and down arrow keys to move this item. The new order saves automatically.</p>
	<?php
}
add_action( 'admin_footer-edit.php', 'bdtk_sortable_help_text' );

// Saves the order of the visible rows. Every item of the post type gets a unique menu_order, so
// reordering page 2 of a paginated list (or a filtered list) never collides with other items.
function bdtk_sortable_save() {
	check_ajax_referer( 'bdtk_sortable', 'nonce' );

	$post_type = isset( $_POST['post_type'] ) ? sanitize_key( $_POST['post_type'] ) : '';
	$order     = isset( $_POST['order'] ) ? array_values( array_filter( array_map( 'absint', (array) $_POST['order'] ) ) ) : array();

	if ( ! bdtk_user_can_sort( $post_type ) ) {
		wp_send_json_error( 'You are not allowed to reorder these items.', 403 );
	}

	if ( ! $order ) {
		wp_send_json_error( 'Nothing to reorder.', 400 );
	}

	global $wpdb;

	$tiebreak = is_post_type_hierarchical( $post_type ) ? 'post_title ASC' : 'post_date DESC';
	$rows     = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ID, menu_order FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ( 'trash', 'auto-draft' ) ORDER BY menu_order ASC, {$tiebreak}, ID ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$post_type
		)
	);

	$all_ids        = array();
	$current_orders = array();

	foreach ( $rows as $row ) {
		$all_ids[]                        = (int) $row->ID;
		$current_orders[ (int) $row->ID ] = (int) $row->menu_order;
	}

	// Slot the visible rows, in their new order, into the positions they already occupy.
	$positions = array();
	$index     = array_flip( $all_ids );

	foreach ( $order as $post_id ) {
		if ( ! isset( $index[ $post_id ] ) ) {
			wp_send_json_error( 'One of the items no longer exists. Please reload the page.', 409 );
		}
		$positions[] = $index[ $post_id ];
	}

	sort( $positions );

	foreach ( $positions as $i => $position ) {
		$all_ids[ $position ] = $order[ $i ];
	}

	// Direct update on purpose: wp_update_post() would bump modified dates and fire save_post for every item.
	foreach ( $all_ids as $i => $post_id ) {
		$menu_order = $i + 1;

		if ( $current_orders[ $post_id ] !== $menu_order ) {
			$wpdb->update( $wpdb->posts, array( 'menu_order' => $menu_order ), array( 'ID' => $post_id ), array( '%d' ), array( '%d' ) );
			clean_post_cache( $post_id );
		}
	}

	do_action( 'bdtk_sortable_saved', $post_type, $all_ids );

	wp_send_json_success();
}
add_action( 'wp_ajax_bdtk_sortable_save', 'bdtk_sortable_save' );

// Admin list (non-hierarchical types): show saved order by default. Pages already use menu_order.
function bdtk_sortable_admin_query( $query ) {
	global $pagenow;

	if ( ! is_admin() || 'edit.php' !== $pagenow || ! $query->is_main_query() || ! empty( $_GET['orderby'] ) ) {
		return;
	}

	$post_type = $query->get( 'post_type' );

	if ( ! is_string( $post_type ) || is_post_type_hierarchical( $post_type ) || ! in_array( $post_type, bdtk_sortable_post_types(), true ) ) {
		return;
	}

	$query->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'DESC' ) );
}
add_action( 'pre_get_posts', 'bdtk_sortable_admin_query' );

// Front end: queries without an explicit orderby follow the saved order. Ties (never sorted, or new
// items at menu_order 0) fall back to newest first, so nothing changes until someone drags.
function bdtk_sortable_front_query( $query ) {
	if ( ( is_admin() && ! wp_doing_ajax() ) || $query->get( 'orderby' ) || $query->is_search() || $query->is_feed() ) {
		return;
	}

	$post_type = $query->get( 'post_type' );

	if ( empty( $post_type ) ) {
		if ( $query->is_singular() ) {
			return;
		}

		$post_type = 'post';

		if ( $query->is_tax() ) {
			$taxonomy  = get_taxonomy( $query->get( 'taxonomy' ) );
			$post_type = $taxonomy ? $taxonomy->object_type : array();
		}
	}

	$post_types = (array) $post_type;

	if ( ! $post_types || array_diff( $post_types, bdtk_sortable_post_types() ) ) {
		return;
	}

	$query->set( 'orderby', array( 'menu_order' => 'ASC', 'date' => 'DESC' ) );
}
$bdtk_settings = bdtk_get_settings();

if ( ! empty( $bdtk_settings['reorder']['front_end'] ) ) {
	add_action( 'pre_get_posts', 'bdtk_sortable_front_query' );
}
