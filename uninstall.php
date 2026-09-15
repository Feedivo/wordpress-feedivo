<?php
/**
 * Uninstall: remove synced posts, attachments, feed terms, options and cron.
 *
 * @package Feedivo
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

function feedivo_uninstall_cleanup() {
	// Large sites may need more than the default execution time.
	if ( function_exists( 'set_time_limit' ) ) {
		// phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- see above.
		set_time_limit( 0 );
	}
	register_post_type( 'feedivo_post', array( 'public' => false ) );
	register_taxonomy( 'feedivo_feed', 'feedivo_post', array( 'public' => false ) );

	while ( true ) {
		$post_ids = get_posts(
			array(
				'post_type'      => 'feedivo_post',
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'fields'         => 'ids',
			)
		);
		if ( array() === $post_ids ) {
			break;
		}

		foreach ( $post_ids as $post_id ) {
			$attachments = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_parent'    => $post_id,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);
			foreach ( $attachments as $attachment_id ) {
				wp_delete_attachment( (int) $attachment_id, true );
			}
			wp_delete_post( (int) $post_id, true );
		}
	}

	$terms = get_terms(
		array(
			'taxonomy'   => 'feedivo_feed',
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term_id ) {
			wp_delete_term( (int) $term_id, 'feedivo_feed' );
		}
	}

	foreach ( array(
		'feedivo_integrations',
		'feedivo_sync_state',
		'feedivo_last_sync',
		'feedivo_sync_lock',
		'feedivo_rewrite_version',
		'feedivo_archive_disabled',
	) as $option ) {
		delete_option( $option );
	}

	delete_transient( 'feedivo_status_checked' );

	wp_clear_scheduled_hook( 'feedivo_sync_event' );
	wp_clear_scheduled_hook( 'feedivo_sync_tick' );
}

feedivo_uninstall_cleanup();
