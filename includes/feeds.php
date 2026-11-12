<?php
/**
 * Turn off feeds — search engines read the sitemap, so most BD sites have no use for RSS/Atom feeds.
 *
 * Feed addresses 301 to the page they belong to (/feed/ to the home page, /a-post/feed/ to the post),
 * like Yoast's crawl settings, and the feed links are taken out of the page head.
 *
 * @package bd-toolkit
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bdtk_settings = bdtk_get_settings();
$bdtk_feeds    = $bdtk_settings['feeds'];

function bdtk_feeds_redirect() {
	if ( ! is_feed() ) {
		return;
	}

	$settings = bdtk_get_settings();
	$switch   = is_comment_feed() ? 'comment_feeds' : 'post_feeds';

	if ( empty( $settings['feeds'][ $switch ] ) ) {
		return;
	}

	$current = set_url_scheme( 'http://' . wp_unslash( $_SERVER['HTTP_HOST'] ) . wp_unslash( $_SERVER['REQUEST_URI'] ) );
	$target  = remove_query_arg( 'feed', $current );
	$target  = preg_replace( '#/(?:comments/)?feed(?:/(?:rss2?|atom|rdf))?/?(?=$|\?)#', '/', $target );

	if ( $target === $current ) {
		$target = home_url( '/' );
	}

	wp_safe_redirect( $target, 301 );
	exit;
}
add_action( 'template_redirect', 'bdtk_feeds_redirect', 1 );

if ( ! empty( $bdtk_feeds['post_feeds'] ) ) {
	remove_action( 'wp_head', 'feed_links', 2 );
	remove_action( 'wp_head', 'feed_links_extra', 3 );
}

if ( ! empty( $bdtk_feeds['comment_feeds'] ) ) {
	add_filter( 'feed_links_show_comments_feed', '__return_false' );
	add_filter( 'feed_links_extra_show_post_comments_feed', '__return_false' );
}
