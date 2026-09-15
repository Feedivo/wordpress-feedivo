<?php
/**
 * Block editor integration: the "Feedivo Feed" block. Renders through the
 * shared renderer, so the output matches shortcode and Elementor widget.
 *
 * @package Feedivo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Feedivo_Block {

	const EDITOR_HANDLE = 'feedivo-block-editor';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'localize_editor' ) );
	}

	public static function register() {

		Feedivo_Frontend::register_assets();

		wp_register_script(
			self::EDITOR_HANDLE,
			FEEDIVO_PLUGIN_URL . 'assets/js/feedivo-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			FEEDIVO_VERSION,
			true
		);
		wp_set_script_translations( self::EDITOR_HANDLE, 'feedivo' );

		register_block_type_from_metadata(
			FEEDIVO_PLUGIN_DIR . 'blocks/feed',
			array( 'render_callback' => array( __CLASS__, 'render' ) )
		);
	}

	public static function localize_editor() {
		wp_localize_script(
			self::EDITOR_HANDLE,
			'feedivoBlock',
			array(
				'feeds'          => self::feeds(),
				'settingsUrl'    => Feedivo_Admin_Page::settings_url(),

				'frontendScript' => add_query_arg( 'ver', FEEDIVO_VERSION, FEEDIVO_PLUGIN_URL . 'assets/js/feed-frontend.js' ),
			)
		);
	}

	public static function render( $attributes ) {
		$uuid = isset( $attributes['feedUuid'] ) ? sanitize_text_field( (string) $attributes['feedUuid'] ) : '';

		$renderer = new Feedivo_Renderer();
		$term     = '' === $uuid ? null : $renderer->resolve_term( $uuid );

		if ( null === $term ) {
			if ( current_user_can( 'manage_options' ) ) {
				return sprintf(
					'<div class="feedivo-feed-missing">%s</div>',
					esc_html__( 'This Feedivo feed is not available. Update your content or choose another feed. This message is only visible to administrators.', 'feedivo' )
				);
			}
			return '<!-- feedivo: unknown feed -->';
		}

		Feedivo_Frontend::enqueue_assets();

		return sprintf(
			'<div %1$s>%2$s</div>',
			get_block_wrapper_attributes(),
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer output is escaped internally.
			$renderer->render( array( 'feed' => $term ) )
		);
	}

	/**
	 * @return array<int, array{uuid:string, name:string}>
	 */
	private static function feeds() {
		$feeds = array();

		$terms = get_terms(
			array(
				'taxonomy'   => Feedivo_Post_Type::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return $feeds;
		}

		foreach ( $terms as $term ) {
			$uuid = (string) get_term_meta( $term->term_id, 'feedivo_uuid', true );
			if ( '' !== $uuid ) {
				$feeds[] = array(
					'uuid' => $uuid,
					'name' => $term->name,
				);
			}
		}

		return $feeds;
	}
}
