<?php
/**
 * Elementor widget: pick a feed; appearance comes from Feedivo. Loaded only
 * from the `elementor/widgets/register` callback, where Widget_Base exists.
 *
 * @package Feedivo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Feedivo_Elementor_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'feedivo-feed';
	}

	public function get_title() {
		return __( 'Feedivo Feed', 'feedivo' );
	}

	public function get_icon() {
		return 'eicon-gallery-grid';
	}

	public function get_categories() {
		return array( 'general' );
	}

	public function get_keywords() {
		return array( 'feedivo', 'instagram', 'facebook', 'threads', 'pinterest', 'youtube', 'social', 'feed' );
	}

	public function get_style_depends() {
		return array( 'feedivo' );
	}

	public function get_script_depends() {
		return array( 'feedivo' );
	}

	protected function register_controls() {
		$this->start_controls_section(
			'feedivo_content',
			array(
				'label' => __( 'Feed', 'feedivo' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'feed_uuid',
			array(
				'label'       => __( 'Feed', 'feedivo' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'options'     => $this->feed_options(),
				'default'     => '',
				'description' => __( 'Choose the design and content for this feed in Feedivo.', 'feedivo' ),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$uuid = (string) ( $this->get_settings_for_display( 'feed_uuid' ) ?? '' );

		if ( '' === $uuid ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				printf(
					'<div class="feedivo-feed-missing">%s</div>',
					esc_html__( 'Choose a Feedivo feed in the widget settings.', 'feedivo' )
				);
			}
			return;
		}

		Feedivo_Frontend::enqueue_assets();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- renderer output is escaped internally.
		echo ( new Feedivo_Renderer() )->render( array( 'feed' => $uuid ) );
	}

	/**
	 * @return array<string, string> uuid => feed name.
	 */
	private function feed_options() {
		$options = array( '' => __( '— Select a feed —', 'feedivo' ) );

		$terms = get_terms(
			array(
				'taxonomy'   => Feedivo_Post_Type::TAXONOMY,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) ) {
			return $options;
		}

		foreach ( $terms as $term ) {
			$uuid = (string) get_term_meta( $term->term_id, 'feedivo_uuid', true );
			if ( '' !== $uuid ) {
				$options[ $uuid ] = $term->name;
			}
		}

		return $options;
	}
}
