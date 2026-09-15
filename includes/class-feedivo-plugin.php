<?php
/**
 * Wires all components and registers the Elementor widget when Elementor is active.
 *
 * @package Feedivo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Feedivo_Plugin {

	/** @var Feedivo_Plugin|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	private function __construct() {}

	private function init() {
		Feedivo_Post_Type::init();
		Feedivo_Sync::init();
		Feedivo_Frontend::init();
		Feedivo_Shortcode::init();
		Feedivo_Block::init();
		Feedivo_Ajax::init();

		if ( is_admin() ) {
			Feedivo_Admin_Page::init();
		}

		add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widget' ) );
	}

	public function register_elementor_widget( $widgets_manager ) {
		require_once FEEDIVO_PLUGIN_DIR . 'includes/class-feedivo-elementor-widget.php';
		$widgets_manager->register( new Feedivo_Elementor_Widget() );
	}
}
