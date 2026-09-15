<?php
/**
 * "Load more" endpoint: pages through the locally synced posts.
 *
 * @package Feedivo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Feedivo_Ajax {

	public static function init() {
		add_action( 'wp_ajax_feedivo_load_more', array( __CLASS__, 'load_more' ) );
		add_action( 'wp_ajax_nopriv_feedivo_load_more', array( __CLASS__, 'load_more' ) );
	}

	public static function load_more() {

		if ( ! check_ajax_referer( 'feedivo_front', 'nonce', false ) ) {
			wp_send_json_error( array( 'code' => 'nonce_expired' ), 403 );
		}

		$term_id = isset( $_POST['term'] ) ? (int) $_POST['term'] : 0;
		$page    = isset( $_POST['page'] ) ? max( 1, (int) $_POST['page'] ) : 1;

		// JSON, so sanitize_text_field() would mangle it; clean_overrides() validates each value.
		$raw       = isset( $_POST['overrides'] ) ? sanitize_textarea_field( wp_unslash( $_POST['overrides'] ) ) : '';
		$overrides = json_decode( $raw, true );
		$overrides = is_array( $overrides ) ? $overrides : array();

		$renderer = new Feedivo_Renderer();
		$term     = $renderer->resolve_term( $term_id );

		if ( null === $term ) {
			wp_send_json_error( array( 'code' => 'unknown_feed' ), 404 );
		}

		$settings = $renderer->settings_for_term( $term, $renderer->clean_overrides( $overrides ) );
		$query    = $renderer->query( $term, $settings['limit'], $page );

		wp_send_json_success(
			array(
				'html'     => $renderer->render_items( $query, $settings ),
				'has_more' => $page < (int) $query->max_num_pages,
			)
		);
	}
}
