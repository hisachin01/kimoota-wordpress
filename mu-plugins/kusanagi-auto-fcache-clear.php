<?php
/**
 * Plugin Name: KUSANAGI Auto FCache Clear
 * Description: Clear KUSANAGI fcache automatically when published posts/pages are updated.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'save_post', 'kimoota_kusanagi_auto_clear_fcache', 20, 3 );

function kimoota_kusanagi_auto_clear_fcache( $post_id, $post, $update ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( ! $post || 'publish' !== $post->post_status ) {
		return;
	}

	if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
		return;
	}

	global $WP_KUSANAGI;

	if (
		empty( $WP_KUSANAGI )
		|| empty( $WP_KUSANAGI->modules['cache_clear'] )
		|| ! method_exists( $WP_KUSANAGI->modules['cache_clear'], 'clear_fcache' )
	) {
		error_log( '[KUSANAGI Auto FCache Clear] cache_clear module not available.' );
		return;
	}

	$permalink = get_permalink( $post_id );
	if ( ! $permalink ) {
		return;
	}

	$path = wp_parse_url( $permalink, PHP_URL_PATH );
	if ( ! $path ) {
		return;
	}

	$WP_KUSANAGI->modules['cache_clear']->clear_fcache( $path );
	$WP_KUSANAGI->modules['cache_clear']->clear_fcache( '/' );

	error_log( '[KUSANAGI Auto FCache Clear] cleared: ' . $path );
}
