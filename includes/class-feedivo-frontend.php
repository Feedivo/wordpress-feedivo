<?php
/**
 * Frontend glue: asset registration/enqueueing and the singular view of a
 * synced post (via `the_content` — theme-safe, no template override).
 *
 * @package Feedivo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Feedivo_Frontend {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue' ), 20 );
		add_filter( 'the_content', array( __CLASS__, 'singular_content' ) );

		add_filter( 'style_loader_tag', array( __CLASS__, 'guard_style_tag' ), 10, 2 );

		add_filter( 'rocket_exclude_css', array( __CLASS__, 'exclude_css_paths' ) );
		add_filter( 'autoptimize_filter_css_exclude', array( __CLASS__, 'exclude_css_autoptimize' ) );

		add_filter( 'rocket_rucss_external_exclusions', array( __CLASS__, 'exclude_css_paths' ) );
		add_filter( 'perfmatters_rucss_excluded_stylesheets', array( __CLASS__, 'exclude_css_paths' ) );
		add_filter( 'litespeed_optm_css_excludes', array( __CLASS__, 'exclude_css_paths' ) );

		add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'elementor/preview/enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function register_assets() {
		if ( wp_style_is( 'feedivo', 'registered' ) ) {
			return;
		}

		wp_register_style( 'feedivo', FEEDIVO_PLUGIN_URL . 'assets/css/feed-layouts.css', array(), FEEDIVO_VERSION );
		wp_register_script( 'feedivo', FEEDIVO_PLUGIN_URL . 'assets/js/feed-frontend.js', array(), FEEDIVO_VERSION, true );
		wp_localize_script(
			'feedivo',
			'feedivoFront',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'feedivo_front' ),
				'i18n'    => array(
					'viewOriginal' => __( 'View original post', 'feedivo' ),
					/* translators: %s: platform name (Instagram/Facebook/Threads) */
					'viewOn'       => __( 'View on %s', 'feedivo' ),
					'loading'      => __( 'Loading…', 'feedivo' ),
					'play'         => __( 'Play video', 'feedivo' ),

					'post'         => __( 'Post', 'feedivo' ),
					'image'        => __( 'Image', 'feedivo' ),
					/* translators: joins a position and a total, as in "Image 2 of 5" */
					'of'           => __( 'of', 'feedivo' ),
				),
			)
		);
	}

	public static function guard_style_tag( $tag, $handle ) {
		if ( 'feedivo' !== $handle ) {
			return $tag;
		}
		return str_replace( '<link ', '<link data-noptimize="1" data-no-optimize="1" data-no-minify="1" ', $tag );
	}

	/**
	 * Path of the stylesheet, for every filter that takes paths or URL
	 * fragments and matches loosely on a substring.
	 *
	 * @param mixed $excluded List of paths, as passed by the filter.
	 * @return array
	 */
	public static function exclude_css_paths( $excluded ) {
		$excluded   = is_array( $excluded ) ? $excluded : array();
		$base       = (string) wp_parse_url( FEEDIVO_PLUGIN_URL, PHP_URL_PATH );
		$excluded[] = $base . 'assets/css/feed-layouts.css';
		return $excluded;
	}

	public static function exclude_css_autoptimize( $excluded ) {
		$excluded = trim( (string) $excluded );
		return ( '' === $excluded ? '' : $excluded . ', ' ) . 'plugins/feedivo/assets/css';
	}

	public static function enqueue_assets() {
		self::register_assets();
		wp_enqueue_style( 'feedivo' );
		wp_enqueue_script( 'feedivo' );
	}

	public static function maybe_enqueue() {
		if ( is_singular( Feedivo_Post_Type::POST_TYPE ) || is_tax( Feedivo_Post_Type::TAXONOMY ) ) {
			self::enqueue_assets();
		}
	}

	public static function singular_content( $content ) {
		if ( ! is_singular( Feedivo_Post_Type::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id   = get_the_ID();
		$data      = Feedivo_Post_Type::get_data( (int) $post_id );
		$platform  = (string) ( $data['platform'] ?? '' );
		$type      = (string) ( $data['type'] ?? '' );
		$permalink = (string) ( $data['permalink'] ?? '' );
		$author    = (string) ( $data['author'] ?? '' );

		$mode = (string) apply_filters( 'feedivo_singular_media', 'extra' );

		$media = '';
		if ( 'none' !== $mode ) {
			$gallery = $data['gallery'] ?? array();
			$gallery = is_array( $gallery ) ? array_map( 'intval', $gallery ) : array();

			if ( 'all' !== $mode ) {
				$gallery = array_values( array_diff( $gallery, array( (int) get_post_thumbnail_id( $post_id ) ) ) );
			}

			if ( array() !== $gallery ) {
				$media .= '<div class="feedivo-single-gallery">';
				foreach ( $gallery as $attachment_id ) {
					$media .= wp_get_attachment_image( $attachment_id, 'large', false, array( 'loading' => 'lazy' ) );
				}
				$media .= '</div>';
			}
		}

		$embed = Feedivo_Post_Type::safe_embed_url( (string) ( $data['embed'] ?? '' ) );
		if ( 'video' === $type && '' !== $embed ) {
			// Click-to-load facade: no external request before the click.
			$media .= sprintf(
				'<a class="feedivo-embed-facade" href="%s" target="_blank" rel="noopener noreferrer" data-feedivo-embed="%s" data-feedivo-embed-title="%s">%s<span class="feedivo-embed-play" aria-hidden="true">&#9658;</span><span class="screen-reader-text">%s</span></a>',
				esc_url( '' !== $permalink ? $permalink : $embed ),
				esc_url( $embed ),
				esc_attr( get_the_title( $post_id ) ),
				get_the_post_thumbnail( $post_id, 'large', array( 'loading' => 'lazy' ) ),
				esc_html__( 'Play video', 'feedivo' )
			);
		} elseif ( 'video' === $type && '' !== $permalink ) {
			$media .= sprintf(
				'<p><a class="feedivo-single-video-link" href="%s" target="_blank" rel="noopener noreferrer">&#9658; %s</a></p>',
				esc_url( $permalink ),
				esc_html__( 'Watch video on the original platform', 'feedivo' )
			);
		}

		$footer = '<footer class="feedivo-single-footer">';
		if ( '' !== $author ) {
			$footer .= '<span class="feedivo-single-author">@' . esc_html( $author ) . '</span> ';
		}
		if ( '' !== $permalink && '' !== $platform ) {

			$platform_label = 'youtube' === $platform ? 'YouTube' : ucfirst( $platform );
			$footer .= sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $permalink ),
				sprintf(
					/* translators: %s: platform name */
					esc_html__( 'View on %s', 'feedivo' ),
					esc_html( $platform_label )
				)
			);
		}
		$footer .= '</footer>';

		return $media . $content . $footer;
	}
}
