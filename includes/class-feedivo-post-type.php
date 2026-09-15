<?php
/**
 * Custom post type `feedivo_post` (synced social posts, read-only) and the
 * taxonomy `feedivo_feed` (one term per Feedivo feed). The sync engine owns
 * the lifecycle; the UI cannot create, edit or delete them.
 *
 * @package Feedivo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Feedivo_Post_Type {

	const POST_TYPE = 'feedivo_post';
	const TAXONOMY  = 'feedivo_feed';
	const REWRITE_OPTION  = 'feedivo_rewrite_version';
	const REWRITE_VERSION = '2';

	const META_DATA       = '_feedivo_data';
	const META_SEEN       = '_feedivo_seen';
	const META_SOURCE_KEY = '_feedivo_source_key';
	const META_PINNED     = '_feedivo_pinned';

	const EMBED_HOSTS = array( 'www.youtube-nocookie.com', 'youtube-nocookie.com', 'www.youtube.com', 'youtube.com' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 20 );
		add_filter( 'map_meta_cap', array( __CLASS__, 'enforce_read_only' ), 10, 4 );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_admin_column' ), 10, 2 );
	}

	public static function register() {
		$archives = ! Feedivo_Settings::archive_disabled();

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Social Posts', 'feedivo' ),
					'singular_name' => __( 'Social Post', 'feedivo' ),
					'menu_name'     => __( 'Feedivo', 'feedivo' ),
					'all_items'     => __( 'Social Posts', 'feedivo' ),
					'search_items'  => __( 'Search social posts', 'feedivo' ),
					'not_found'     => __( 'No social posts yet. They appear automatically after the first update.', 'feedivo' ),
				),
				'description'  => __( 'Social media posts imported by Feedivo.', 'feedivo' ),
				'public'       => true,

				'has_archive'  => $archives,
				'show_in_rest' => false,
				'menu_icon'    => 'dashicons-format-gallery',
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				'rewrite'      => array(
					'slug'       => apply_filters( 'feedivo_post_rewrite_slug', 'feed-posts' ),
					'with_front' => false,
				),
				'capabilities' => array(
					'create_posts' => 'do_not_allow',
				),
				'map_meta_cap' => true,
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'             => array(
					'name'          => __( 'Feedivo Feeds', 'feedivo' ),
					'singular_name' => __( 'Feedivo Feed', 'feedivo' ),
					'menu_name'     => __( 'Feedivo Feeds', 'feedivo' ),
				),
				'description'        => __( 'Your social media feeds from Feedivo.', 'feedivo' ),
				'public'             => true,
				// Disabling query and rewrite makes archive requests return a clean 404.
				'publicly_queryable' => $archives,
				'hierarchical'       => false,
				'show_admin_column'  => true,
				'show_in_rest'       => false,
				'meta_box_cb'        => false,
				'rewrite'            => $archives ? array(
					'slug'       => apply_filters( 'feedivo_feed_rewrite_slug', 'feed-kategorien' ),
					'with_front' => false,
				) : false,
				'capabilities'       => array(
					'manage_terms' => 'do_not_allow',
					'edit_terms'   => 'do_not_allow',
					'delete_terms' => 'do_not_allow',
					'assign_terms' => 'do_not_allow',
				),
			)
		);
	}

	public static function maybe_flush_rewrite_rules() {
		if ( self::rewrite_signature() === get_option( self::REWRITE_OPTION ) ) {
			return;
		}

		flush_rewrite_rules( false );
		self::mark_rewrite_rules_current();
	}

	public static function mark_rewrite_rules_current() {
		update_option( self::REWRITE_OPTION, self::rewrite_signature(), false );
	}

	private static function rewrite_signature() {
		return self::REWRITE_VERSION . ( Feedivo_Settings::archive_disabled() ? ':noarchive' : ':archive' );
	}

	/**
	 * Block editing/deleting in the UI; programmatic calls are unaffected.
	 *
	 * @param string[] $caps    Primitive capabilities required.
	 * @param string   $cap     Requested meta capability.
	 * @param int      $user_id User ID.
	 * @param array    $args    Context (args[0] = post ID).
	 * @return string[]
	 */
	public static function enforce_read_only( $caps, $cap, $user_id, $args ) {
		if ( ! in_array( $cap, array( 'edit_post', 'delete_post' ), true ) || empty( $args[0] ) ) {
			return $caps;
		}
		if ( get_post_type( (int) $args[0] ) === self::POST_TYPE ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	}

	public static function admin_columns( $columns ) {
		unset( $columns['date'] );
		$columns['feedivo_platform'] = __( 'Platform', 'feedivo' );
		$columns['feedivo_source']   = __( 'Source', 'feedivo' );
		$columns['date']             = __( 'Date', 'feedivo' );
		return $columns;
	}

	public static function render_admin_column( $column, $post_id ) {
		$data = self::get_data( (int) $post_id );
		if ( 'feedivo_platform' === $column ) {
			echo esc_html( ucfirst( (string) ( $data['platform'] ?? '' ) ) );
		}
		if ( 'feedivo_source' === $column ) {
			$permalink = (string) ( $data['permalink'] ?? '' );
			if ( '' !== $permalink ) {
				printf(
					'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( $permalink ),
					esc_html__( 'View original', 'feedivo' )
				);
			} else {
				echo '&mdash;';
			}
		}
	}

	public static function get_data( int $post_id ) {
		$data = get_post_meta( $post_id, self::META_DATA, true );
		return is_array( $data ) ? $data : array();
	}

	public static function merge_data( int $post_id, array $partial ) {
		update_post_meta( $post_id, self::META_DATA, array_merge( self::get_data( $post_id ), $partial ) );
	}

	public static function safe_embed_url( string $url ) {
		$url = esc_url_raw( $url );

		// Reject delimiters that browsers and wp_parse_url() may interpret differently.
		if ( '' === $url || preg_match( '~[\\\\\s]~', $url ) ) {
			return '';
		}
		if ( 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) ) {
			return '';
		}

		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );

		return in_array( $host, self::EMBED_HOSTS, true ) ? $url : '';
	}

	/**
	 * Per-feed "seen in run" markers: term_id => run_id.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int, int>
	 */
	public static function get_seen( int $post_id ) {
		$seen = get_post_meta( $post_id, self::META_SEEN, true );
		return is_array( $seen ) ? array_map( 'intval', $seen ) : array();
	}

	public static function set_seen( int $post_id, int $term_id, int $run_id ) {
		$seen             = self::get_seen( $post_id );
		$seen[ $term_id ] = $run_id;
		update_post_meta( $post_id, self::META_SEEN, $seen );
	}

	public static function forget_seen_term( int $post_id, int $term_id ) {
		$seen = self::get_seen( $post_id );
		unset( $seen[ $term_id ] );
		update_post_meta( $post_id, self::META_SEEN, $seen );
	}

	/**
	 * @param string $feed_uuid Feedivo feed UUID.
	 * @return WP_Term|null
	 */
	public static function term_by_uuid( string $feed_uuid ) {
		$terms = get_terms(
			array(
				'taxonomy'   => self::TAXONOMY,
				'hide_empty' => false,
				'number'     => 1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- UUID lookup over the plugin's own taxonomy; a handful of terms at most.
				'meta_key'   => 'feedivo_uuid',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- see above.
				'meta_value' => $feed_uuid,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}
		return $terms[0];
	}
}
