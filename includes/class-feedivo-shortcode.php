<?php
/**
 * `[feedivo feed="<uuid>"]` — optional local overrides style|columns|limit;
 * everything else comes from the feed's display settings in Feedivo.
 *
 * @package Feedivo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Feedivo_Shortcode {

	public static function init() {
		add_shortcode( 'feedivo', array( __CLASS__, 'render' ) );
	}

	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'feed'    => '',
				'style'   => '',
				'columns' => '',
				'limit'   => '',
			),
			$atts,
			'feedivo'
		);

		$renderer = new Feedivo_Renderer();
		$term     = $renderer->resolve_term( sanitize_text_field( $atts['feed'] ) );

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

		return $renderer->render(
			array(
				'feed'    => $term,
				'style'   => sanitize_key( $atts['style'] ),
				'columns' => (int) $atts['columns'],
				'limit'   => (int) $atts['limit'],
			)
		);
	}
}
