<?php
/**
 * Renders a feed from the locally synced posts, for the shortcode, the block,
 * the Elementor widget and the load-more endpoint. A style id is
 * "<family>-<variant>" and maps to container classes by convention.
 *
 * @package Feedivo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Feedivo_Renderer {

	const ASPECTS = array( 'square', 'portrait', 'tall', 'landscape', 'original' );
	const GAPS    = array( 'tight', 'normal', 'wide' );

	public function render( array $args ) {
		$term = $this->resolve_term( $args['feed'] ?? '' );
		if ( null === $term ) {
			return '';
		}

		$overrides = $this->clean_overrides( $args );
		$settings  = $this->settings_for_term( $term, $overrides );
		$query     = $this->query( $term, $settings['limit'], 1 );

		$items    = $this->render_items( $query, $settings );
		$has_more = $query->max_num_pages > 1;

		$paused_notice = '';
		if ( 1 === (int) get_term_meta( $term->term_id, 'feedivo_paused', true ) ) {
			$paused_notice = sprintf(
				'<div class="feedivo-paused-notice">%s</div>',
				esc_html__( 'This feed is currently paused and not being updated.', 'feedivo' )
			);
		}

		$family  = $this->family_of( $settings['style'] );
		$variant = $this->variant_of( $settings['style'] );

		$html  = sprintf(
			'<div class="feedivo-feed" data-feedivo-term="%1$d" data-feedivo-page="1" data-feedivo-overrides="%2$s">',
			(int) $term->term_id,
			esc_attr( (string) wp_json_encode( $overrides ) )
		);
		$html .= $paused_notice;

		$extra_class = ( 'square' === $settings['corners'] ? ' feed-corners--square' : '' )
			. ( $settings['hover'] ? '' : ' feed-hover--off' )
			. ( $settings['peek'] ? ' feed-peek--on' : '' );
		$accent_attr = '' !== $settings['accent']
			? ' style="' . esc_attr( '--feed-accent:' . $settings['accent'] ) . '"'
			: '';
		$autoplay_attr = $settings['autoplay'] ? '' : ' data-feed-autoplay="off"';

		$hidden_parts = array();
		foreach ( $settings['components'] as $part => $on ) {
			if ( ! $on ) {
				$hidden_parts[] = $part;
			}
		}
		$hide_attr = empty( $hidden_parts )
			? ''
			: ' data-feed-hide="' . esc_attr( implode( ',', $hidden_parts ) ) . '"';

		$load_more_html = '';
		if ( $settings['load_more'] && $has_more && 'carousel' !== $family ) {
			$load_more_html = sprintf(
				'<div class="feed-loadmore-wrap"><button type="button" class="feed-loadmore">%s</button></div>',
				esc_html__( 'Load more', 'feedivo' )
			);
		}

		$carousel_open  = '';
		$carousel_close = '';
		if ( 'carousel' === $family ) {
			$carousel_open  = '<div class="feed-carousel">';
			$carousel_close = sprintf(
				'<button type="button" class="feed-carousel-arrow feed-carousel-prev" aria-label="%1$s" hidden disabled>&#8249;</button>'
				. '<button type="button" class="feed-carousel-arrow feed-carousel-next" aria-label="%2$s" hidden disabled>&#8250;</button></div>',
				esc_attr__( 'Previous', 'feedivo' ),
				esc_attr__( 'Next', 'feedivo' )
			);
		}

		$html .= sprintf(
			'<div class="feed-embed"%8$s%13$s>%9$s<div class="feed-layout feed-layout--%1$s feed-variant--%2$s feed-aspect--%3$s feed-gap--%4$s feed-cols--%5$d%6$s feedivo-items"%8$s%7$s>%11$s</div>%10$s%12$s</div>',
			esc_attr( $family ),
			esc_attr( $variant ),
			esc_attr( $settings['aspect'] ),
			esc_attr( $settings['gap'] ),
			(int) $settings['columns'],
			esc_attr( $extra_class ),
			$autoplay_attr,
			$accent_attr,
			$carousel_open,
			$carousel_close,
			$items,
			$load_more_html,
			$hide_attr
		);

		if ( ! empty( $settings['branding'] ) ) {
			$html .= sprintf(
				'<div class="feedivo-branding"><a href="%1$s" target="_blank" rel="noopener">%2$s <strong>Feedivo</strong></a></div>',
				esc_url( FEEDIVO_API_URL . '/?utm_source=wordpress&utm_medium=branding&utm_campaign=free-plan' ),
				esc_html__( 'Provided by', 'feedivo' )
			);
		}

		$html .= self::lightbox_skeleton();
		$html .= '</div>';

		return $html;
	}

	public static function lightbox_skeleton() {
		static $done = false;
		if ( $done ) {
			return '';
		}
		$done = true;

		return '<div class="feedivo-lightbox" hidden>'
			. '<div class="feedivo-lightbox-inner" role="dialog" aria-modal="true">'
			. '<button type="button" class="feedivo-lightbox-close" aria-label="' . esc_attr__( 'Close', 'feedivo' ) . '">&times;</button>'
			. '<div class="feedivo-lightbox-media">'
			. '<button type="button" class="feedivo-lightbox-nav feedivo-lightbox-prev" aria-label="' . esc_attr__( 'Previous image', 'feedivo' ) . '" hidden>&#8249;</button>'
			. '<img alt="">'
			. '<video class="feedivo-lightbox-video" controls playsinline preload="metadata" hidden></video>'
			. '<div class="feedivo-lightbox-embed" hidden></div>'
			. '<a class="feedivo-lightbox-play" href="#" target="_blank" rel="noopener noreferrer" aria-hidden="true" hidden>&#9658;</a>'
			. '<button type="button" class="feedivo-lightbox-nav feedivo-lightbox-next" aria-label="' . esc_attr__( 'Next image', 'feedivo' ) . '" hidden>&#8250;</button>'
			. '<span class="feedivo-lightbox-count" hidden></span>'
			. '</div>'
			. '<div class="feedivo-lightbox-details">'
			. '<div class="feedivo-lightbox-details-inner">'
			. '<span class="feedivo-lightbox-badge" hidden></span>'
			. '<span class="feedivo-lightbox-author" hidden></span>'
			. '<p class="feedivo-lightbox-caption"></p>'
			. '<div class="feedivo-lightbox-date"></div>'
			. '<a class="feedivo-lightbox-link" href="#" target="_blank" rel="noopener noreferrer" hidden></a>'
			. '</div>'
			. '</div>'
			. '</div>'
			. '</div>';
	}

	public function render_items( WP_Query $query, array $settings ) {
		$is_list = 'list' === $this->family_of( $settings['style'] );
		$html    = '';
		foreach ( $query->posts as $post ) {
			$html .= $is_list ? $this->item_row( $post, $settings ) : $this->item_card( $post, $settings );
		}
		return $html;
	}

	/**
	 * Effective settings for a term: Feedivo display config + overrides.
	 *
	 * @param WP_Term $term      Feed term.
	 * @param array   $overrides Validated overrides.
	 * @return array{style:string, columns:int, aspect:string, gap:string, limit:int, load_more:bool, autoplay:bool, peek:bool, corners:string, hover:bool, accent:string, components:array}
	 */
	public function settings_for_term( WP_Term $term, array $overrides = array() ) {
		$display = get_term_meta( $term->term_id, 'feedivo_display', true );
		$display = is_array( $display ) ? $display : array();

		$style      = sanitize_key( (string) ( $display['style'] ?? '' ) );
		$style      = '' !== $style ? $style : 'grid-classic';
		$aspect     = in_array( $display['aspect'] ?? '', self::ASPECTS, true ) ? $display['aspect'] : 'square';
		$gap        = in_array( $display['gap'] ?? '', self::GAPS, true ) ? $display['gap'] : 'normal';
		$components = is_array( $display['components'] ?? null ) ? $display['components'] : array();

		$settings = array(
			'style'      => $style,

			'columns'    => min( 6, max( 1, (int) ( $display['columns'] ?? 4 ) ) ),
			'aspect'     => $aspect,
			'gap'        => $gap,
			'limit'      => min( 50, max( 1, (int) ( $display['limit'] ?? 12 ) ) ),
			'load_more'  => (bool) ( $display['load_more'] ?? true ),
			'autoplay'   => (bool) ( $display['autoplay'] ?? true ),

			'peek'       => (bool) ( $display['peek'] ?? false ),
			'corners'    => ( $display['corners'] ?? 'rounded' ) === 'square' ? 'square' : 'rounded',
			'hover'      => (bool) ( $display['hover'] ?? true ),
			'accent'     => preg_match( '/^#[0-9a-f]{6}$/i', (string) ( $display['accent'] ?? '' ) )
				? strtolower( (string) $display['accent'] )
				: '',

			'click'      => in_array( $display['click'] ?? '', array( 'lightbox', 'link' ), true ) ? $display['click'] : 'lightbox',

			'branding'   => (bool) ( $display['branding'] ?? false ),
			'components' => array(
				'caption'   => (bool) ( $components['caption'] ?? true ),
				'date'      => (bool) ( $components['date'] ?? true ),
				'platform'  => (bool) ( $components['platform'] ?? true ),
				'author'    => (bool) ( $components['author'] ?? false ),
				'type_icon' => (bool) ( $components['type_icon'] ?? true ),
				'source'    => (bool) ( $components['source'] ?? true ),
			),
		);

		return array_merge( $settings, $overrides );
	}

	/**
	 * Pinned posts first, then the feed's sort order, post ID as tie-breaker.
	 * Join and order are built by hand, because WP_Query cannot order by the
	 * mere presence of a meta row.
	 *
	 * @param WP_Term $term  Feed term.
	 * @param int     $limit Posts per page.
	 * @param int     $page  Page number (1-based).
	 * @return WP_Query
	 */
	public function query( WP_Term $term, int $limit, int $page ) {
		global $wpdb;

		$order = 'oldest' === (string) get_term_meta( $term->term_id, 'feedivo_sort', true ) ? 'ASC' : 'DESC';

		$clauses = static function ( array $sql ) use ( $wpdb, $order ) {
			$sql['join']   .= $wpdb->prepare(
				" LEFT JOIN {$wpdb->postmeta} feedivo_pin ON ( feedivo_pin.post_id = {$wpdb->posts}.ID AND feedivo_pin.meta_key = %s )",
				Feedivo_Post_Type::META_PINNED
			);
			$sql['orderby'] = "( feedivo_pin.post_id IS NOT NULL ) DESC, {$wpdb->posts}.post_date {$order}, {$wpdb->posts}.ID {$order}";
			return $sql;
		};

		add_filter( 'posts_clauses', $clauses );
		$query = new WP_Query(
			array(
				'post_type'           => Feedivo_Post_Type::POST_TYPE,

				'post_status'         => array( 'publish', 'private' ),
				'posts_per_page'      => $limit,
				'paged'               => max( 1, $page ),
				'ignore_sticky_posts' => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- a feed IS its taxonomy term; there is no other way to select it.
				'tax_query'           => array(
					array(
						'taxonomy' => Feedivo_Post_Type::TAXONOMY,
						'field'    => 'term_id',
						'terms'    => array( $term->term_id ),
					),
				),
			)
		);
		remove_filter( 'posts_clauses', $clauses );

		return $query;
	}

	public function clean_overrides( array $args ) {
		$overrides = array();
		if ( isset( $args['style'] ) && '' !== $args['style'] ) {
			$overrides['style'] = sanitize_key( $args['style'] );
		}
		if ( isset( $args['columns'] ) && (int) $args['columns'] > 0 ) {
			$overrides['columns'] = min( 6, max( 1, (int) $args['columns'] ) );
		}
		if ( isset( $args['limit'] ) && (int) $args['limit'] > 0 ) {
			$overrides['limit'] = min( 50, max( 1, (int) $args['limit'] ) );
		}
		return $overrides;
	}

	/**
	 * @param mixed $feed UUID string, term_id or WP_Term.
	 * @return WP_Term|null
	 */
	public function resolve_term( $feed ) {
		if ( $feed instanceof WP_Term ) {
			return $feed;
		}
		if ( is_numeric( $feed ) ) {
			$term = get_term( (int) $feed, Feedivo_Post_Type::TAXONOMY );
			return $term instanceof WP_Term ? $term : null;
		}
		if ( is_string( $feed ) && '' !== $feed ) {
			return Feedivo_Post_Type::term_by_uuid( $feed );
		}
		return null;
	}

	private function opens_lightbox( array $settings, array $data ) {
		return 'lightbox' === $settings['click'] || 'story' === (string) ( $data['subtype'] ?? '' );
	}

	private function family_of( string $style ) {
		$pos = strpos( $style, '-' );
		return false === $pos ? $style : substr( $style, 0, $pos );
	}

	private function variant_of( string $style ) {
		$pos = strpos( $style, '-' );
		return false === $pos ? 'classic' : substr( $style, $pos + 1 );
	}

	private function item_card( WP_Post $post, array $settings ) {
		$c         = $settings['components'];
		$data      = Feedivo_Post_Type::get_data( $post->ID );
		$lightbox  = $this->opens_lightbox( $settings, $data );
		$platform  = (string) ( $data['platform'] ?? '' );
		$type      = (string) ( $data['type'] ?? '' );
		$author    = (string) ( $data['author'] ?? '' );
		$caption   = $this->caption_text( $post );
		$date      = get_the_date( 'd.m.Y', $post );
		$thumb     = get_the_post_thumbnail_url( $post, 'medium_large' );

		if ( $lightbox ) {
			$html = '<button type="button" class="post-card" data-feedivo-lightbox>';
		} else {
			$html = sprintf( '<a class="post-card" href="%s">', esc_url( (string) get_permalink( $post ) ) );
		}

		$html .= $this->payload_json( $post, $data, $caption );

		$html .= $thumb
			? sprintf( '<img src="%s" alt="%s" loading="lazy">', esc_url( $thumb ), esc_attr( $this->card_alt( $post, $data ) ) )
			: '<span class="post-card__placeholder" aria-hidden="true">&#9633;</span>';

		if ( $c['platform'] ) {
			$chip = $this->platform_chip( $platform );
			if ( '' !== $chip ) {
				$html .= '<span class="post-card__tags">' . $chip . '</span>';
			}
		}

		if ( $c['type_icon'] ) {
			$html .= $this->type_icon( $type );
		}

		$html .= $this->hover_hint( $lightbox );

		$show_caption = $c['caption'] && '' !== $caption;
		$show_date    = $c['date'] && '' !== $date;
		$show_author  = $c['author'] && '' !== $author;
		if ( $show_caption || $show_date || $show_author ) {
			$html .= '<span class="post-card__scrim">';
			if ( $show_author ) {
				$html .= '<span class="post-card__author">@' . esc_html( $author ) . '</span>';
			}
			if ( $show_caption ) {
				$html .= '<span class="post-card__caption">' . esc_html( $this->caption_excerpt( $caption ) ) . '</span>';
			}
			if ( $show_date ) {
				$html .= '<span class="post-card__date">' . esc_html( $date ) . '</span>';
			}
			$html .= '</span>';
		}

		$html .= $lightbox ? '</button>' : '</a>';

		return $html;
	}

	private function item_row( WP_Post $post, array $settings ) {
		$c         = $settings['components'];
		$data      = Feedivo_Post_Type::get_data( $post->ID );
		$lightbox  = $this->opens_lightbox( $settings, $data );
		$platform  = (string) ( $data['platform'] ?? '' );
		$type      = (string) ( $data['type'] ?? '' );
		$author    = (string) ( $data['author'] ?? '' );
		$caption   = $this->caption_text( $post );
		$date      = get_the_date( 'd.m.Y', $post );
		$thumb     = get_the_post_thumbnail_url( $post, 'medium_large' );

		$html = '<article class="post-row">';

		if ( $lightbox ) {
			$html .= '<button type="button" class="post-row__media" data-feedivo-lightbox>';
		} else {
			$html .= sprintf( '<a class="post-row__media" href="%s">', esc_url( (string) get_permalink( $post ) ) );
		}
		$html .= $this->payload_json( $post, $data, $caption );
		$html .= $thumb
			? sprintf( '<img src="%s" alt="%s" loading="lazy">', esc_url( $thumb ), esc_attr( $this->card_alt( $post, $data ) ) )
			: '<span class="post-card__placeholder" aria-hidden="true">&#9633;</span>';
		if ( $c['type_icon'] ) {
			$html .= $this->type_icon( $type );
		}
		$html .= $lightbox ? '</button>' : '</a>';

		$html .= '<div class="post-row__body"><div class="post-row__meta">';
		if ( $c['platform'] ) {
			$html .= $this->platform_chip( $platform );
		}
		if ( $c['date'] && '' !== $date ) {
			$html .= '<span class="post-row__date">' . esc_html( $date ) . '</span>';
		}
		if ( $c['source'] ) {

			if ( $lightbox ) {
				$html .= sprintf(
					'<button type="button" class="post-row__source" data-feedivo-lightbox>%s %s</button>',
					$this->action_icon( true ),
					esc_html__( 'View post', 'feedivo' )
				);
			} else {
				$html .= sprintf(
					'<a class="post-row__source" href="%s">%s %s</a>',
					esc_url( (string) get_permalink( $post ) ),
					$this->action_icon( false ),
					esc_html__( 'View post', 'feedivo' )
				);
			}
		}
		$html .= '</div>';

		if ( ( $c['caption'] && '' !== $caption ) || ( $c['author'] && '' !== $author ) ) {
			$html .= '<p class="post-row__caption">';
			if ( $c['author'] && '' !== $author ) {
				$html .= '<span class="post-row__author">@' . esc_html( $author ) . '</span> ';
			}
			if ( $c['caption'] && '' !== $caption ) {
				$html .= esc_html( $caption );
			}
			$html .= '</p>';
		}

		$html .= '</div></article>';

		return $html;
	}

	private function payload_json( WP_Post $post, array $data, string $caption ) {
		$video_id = (int) ( $data['video'] ?? 0 );

		$payload = array(
			'media'     => (string) get_the_post_thumbnail_url( $post, 'large' ),
			'thumbnail' => (string) get_the_post_thumbnail_url( $post, 'medium_large' ),
			'video'     => $video_id ? (string) wp_get_attachment_url( $video_id ) : '',
			'embed'     => Feedivo_Post_Type::safe_embed_url( (string) ( $data['embed'] ?? '' ) ),
			'platform'  => (string) ( $data['platform'] ?? '' ),
			'type'      => (string) ( $data['type'] ?? 'image' ),
			'author'    => (string) ( $data['author'] ?? '' ),
			'caption'   => $caption,
			/* translators: lightbox timestamp format (PHP date), e.g. "d.m.Y, H:i \U\h\r" in German */
			'date'      => get_the_date( __( 'd.m.Y, H:i', 'feedivo' ), $post ),
			'permalink' => (string) ( $data['permalink'] ?? '' ),
			'gallery'   => $this->gallery_urls( $data ),

			'alt'       => $this->card_alt( $post, $data ),
		);

		return '<script type="application/json" class="feedivo-post">'
			. wp_json_encode( $payload, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. '</script>';
	}

	/**
	 * Gallery image URLs for the lightbox; empty for single-image posts.
	 *
	 * @param array $data _feedivo_data meta.
	 * @return array<int, string>
	 */
	private function gallery_urls( array $data ) {
		$gallery_ids = $data['gallery'] ?? array();
		if ( ! is_array( $gallery_ids ) || count( $gallery_ids ) <= 1 ) {
			return array();
		}
		$urls = array();
		foreach ( $gallery_ids as $attachment_id ) {
			$url = wp_get_attachment_image_url( (int) $attachment_id, 'large' );
			if ( $url ) {
				$urls[] = $url;
			}
		}
		return count( $urls ) > 1 ? $urls : array();
	}

	private function caption_excerpt( string $caption ) {
		if ( function_exists( 'mb_strimwidth' ) ) {
			return mb_strimwidth( $caption, 0, 90, '…' );
		}
		return strlen( $caption ) > 90 ? substr( $caption, 0, 89 ) . '…' : $caption;
	}

	private function caption_text( WP_Post $post ) {
		$html = (string) $post->post_content;
		$html = preg_replace( '/<br\s*\/?>/i', "\n", $html );
		$html = preg_replace( '/<\/p>\s*<p[^>]*>/i', "\n\n", $html );

		$text = wp_strip_all_tags( $html );

		return trim( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	private function type_icon( string $type ) {
		$glyphs = array(

			'video'    => '<svg class="bi" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8"/><path d="M6.271 5.055a.5.5 0 0 1 .52.038l3.5 2.5a.5.5 0 0 1 0 .814l-3.5 2.5A.5.5 0 0 1 6 10.5v-5a.5.5 0 0 1 .271-.445"/></svg>',

			'carousel' => '<svg class="bi" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M4.502 9a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3"/><path d="M14.002 13a2 2 0 0 1-2 2h-10a2 2 0 0 1-2-2V5A2 2 0 0 1 2 3a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v8a2 2 0 0 1-1.998 2M14 2H4a1 1 0 0 0-1 1h9.002a2 2 0 0 1 2 2v7A1 1 0 0 0 15 11V3a1 1 0 0 0-1-1M2.002 4a1 1 0 0 0-1 1v8l2.646-2.354a.5.5 0 0 1 .63-.062l2.66 1.773 3.71-3.71a.5.5 0 0 1 .577-.094l1.777 1.947V5a1 1 0 0 0-1-1z"/></svg>',
		);
		return isset( $glyphs[ $type ] )
			? '<span class="post-card__type" aria-hidden="true">' . $glyphs[ $type ] . '</span>'
			: '';
	}

	private function hover_hint( bool $lightbox ) {
		return '<span class="post-card__hover"><span class="post-card__hover-pill">'
			. $this->action_icon( $lightbox ) . ' ' . esc_html__( 'View post', 'feedivo' )
			. '</span></span>';
	}

	private function action_icon( bool $lightbox ) {
		if ( $lightbox ) {
			return '<svg class="bi" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.828 10.172a.5.5 0 0 0-.707 0l-4.096 4.096V11.5a.5.5 0 0 0-1 0v3.975a.5.5 0 0 0 .5.5H4.5a.5.5 0 0 0 0-1H1.732l4.096-4.096a.5.5 0 0 0 0-.707m4.344 0a.5.5 0 0 1 .707 0l4.096 4.096V11.5a.5.5 0 1 1 1 0v3.975a.5.5 0 0 1-.5.5H11.5a.5.5 0 0 1 0-1h2.768l-4.096-4.096a.5.5 0 0 1 0-.707m0-4.344a.5.5 0 0 0 .707 0l4.096-4.096V4.5a.5.5 0 1 0 1 0V.525a.5.5 0 0 0-.5-.5H11.5a.5.5 0 0 0 0 1h2.768l-4.096 4.096a.5.5 0 0 0 0 .707m-4.344 0a.5.5 0 0 1-.707 0L1.025 1.732V4.5a.5.5 0 0 1-1 0V.525a.5.5 0 0 1 .5-.5H4.5a.5.5 0 0 1 0 1H1.732l4.096 4.096a.5.5 0 0 1 0 .707"/></svg>';
		}

		return '<svg class="bi" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V8.364a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5"/><path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0z"/></svg>';
	}

	private function platform_chip( string $platform ) {
		$icons = array(
			'instagram' => '<svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.9 3.9 0 0 0-1.417.923A3.9 3.9 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.9 3.9 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.9 3.9 0 0 0-.923-1.417A3.9 3.9 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599s.453.546.598.92c.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.5 2.5 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.5 2.5 0 0 1-.92-.598 2.5 2.5 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233s.008-2.388.046-3.231c.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92s.546-.453.92-.598c.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92m-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217m0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334"/></svg>',
			'facebook'  => '<svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951"/></svg>',
			'threads'   => '<svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M6.321 6.016c-.27-.18-1.166-.802-1.166-.802.756-1.081 1.753-1.502 3.132-1.502.975 0 1.803.327 2.394.948s.928 1.509 1.005 2.644q.492.207.905.484c1.109.745 1.719 1.86 1.719 3.137 0 2.716-2.226 5.075-6.256 5.075C4.594 16 1 13.987 1 7.994 1 2.034 4.482 0 8.044 0 9.69 0 13.55.243 15 5.036l-1.36.353C12.516 1.974 10.163 1.43 8.006 1.43c-3.565 0-5.582 2.171-5.582 6.79 0 4.143 2.254 6.343 5.63 6.343 2.777 0 4.847-1.443 4.847-3.556 0-1.438-1.208-2.127-1.27-2.127-.236 1.234-.868 3.31-3.644 3.31-1.618 0-3.013-1.118-3.013-2.582 0-2.09 1.984-2.847 3.55-2.847.586 0 1.294.04 1.663.114 0-.637-.54-1.728-1.9-1.728-1.25 0-1.566.405-1.967.868ZM8.716 8.19c-2.04 0-2.304.87-2.304 1.416 0 .878 1.043 1.168 1.6 1.168 1.02 0 2.067-.282 2.232-2.423a6.2 6.2 0 0 0-1.528-.161"/></svg>',
			'pinterest' => '<svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0a8 8 0 0 0-2.915 15.452c-.07-.633-.134-1.606.027-2.297.146-.625.938-3.977.938-3.977s-.239-.479-.239-1.187c0-1.113.645-1.943 1.448-1.943.682 0 1.012.512 1.012 1.127 0 .686-.437 1.712-.663 2.663-.188.796.4 1.446 1.185 1.446 1.422 0 2.515-1.5 2.515-3.664 0-1.915-1.377-3.254-3.342-3.254-2.276 0-3.612 1.707-3.612 3.471 0 .688.265 1.425.595 1.826a.24.24 0 0 1 .056.23c-.061.252-.196.796-.222.907-.035.146-.116.177-.268.107-1-.465-1.624-1.926-1.624-3.1 0-2.523 1.834-4.84 5.286-4.84 2.775 0 4.932 1.977 4.932 4.62 0 2.757-1.739 4.976-4.151 4.976-.811 0-1.573-.421-1.834-.919l-.498 1.902c-.181.695-.669 1.566-.995 2.097A8 8 0 1 0 8 0"/></svg>',
			'youtube'   => '<svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8.051 1.999h.089c.822.003 4.987.033 6.11.335a2.01 2.01 0 0 1 1.415 1.42c.101.38.172.883.22 1.402l.01.104.022.26.008.104c.065.914.073 1.77.074 1.957v.075c-.001.194-.01 1.108-.082 2.06l-.008.105-.009.104c-.05.572-.124 1.14-.235 1.558a2.01 2.01 0 0 1-1.415 1.42c-1.16.312-5.569.334-6.18.335h-.142c-.309 0-1.587-.006-2.927-.052l-.17-.006-.087-.004-.171-.007-.171-.007c-1.11-.049-2.167-.128-2.654-.26a2.01 2.01 0 0 1-1.415-1.419c-.111-.417-.185-.986-.235-1.558L.09 9.82l-.008-.104A31 31 0 0 1 0 7.68v-.123c.002-.215.01-.958.064-1.778l.007-.103.003-.052.008-.104.022-.26.01-.104c.048-.519.119-1.023.22-1.402a2.01 2.01 0 0 1 1.415-1.42c.487-.13 1.544-.21 2.654-.26l.17-.007.172-.006.086-.003.171-.007A100 100 0 0 1 7.858 2zM6.4 5.209v4.818l4.157-2.408z"/></svg>',
		);

		if ( ! isset( $icons[ $platform ] ) ) {
			return '';
		}

		return sprintf(
			'<span class="post-card__chip badge-platform-%s">%s %s</span>',
			esc_attr( $platform ),
			$icons[ $platform ],
			esc_html( $this->platform_label( $platform ) )
		);
	}

	private function platform_label( string $platform ) {
		$labels = array(
			'instagram' => 'Instagram',
			'facebook'  => 'Facebook',
			'threads'   => 'Threads',
			'pinterest' => 'Pinterest',
			'youtube'   => 'YouTube',
		);

		return $labels[ $platform ] ?? '';
	}

	/**
	 * Alt text for a post image. In the list layout this is the media button's
	 * only accessible name (WCAG 4.1.2).
	 *
	 * @param WP_Post              $post Synced post.
	 * @param array<string, mixed> $data Payload meta.
	 * @return string
	 */
	private function card_alt( WP_Post $post, array $data ) {
		$label = $this->platform_label( (string) ( $data['platform'] ?? '' ) );
		/* translators: date format (PHP date) used in image alt text */
		$date = get_the_date( __( 'd.m.Y', 'feedivo' ), $post );

		if ( '' !== $label ) {
			return sprintf(
				/* translators: 1: platform name (e.g. Instagram), 2: date */
				__( '%1$s post from %2$s', 'feedivo' ),
				$label,
				$date
			);
		}

		/* translators: %s: date */
		return sprintf( __( 'Post from %s', 'feedivo' ), $date );
	}
}
