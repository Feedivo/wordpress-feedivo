<?php
/**
 * Sideloads post media into the media library: images, video posters and
 * video files; embed-only videos keep just the poster. Deduplicated on media
 * identity (post + position + variant), so re-syncs never re-download.
 *
 * @package Feedivo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Feedivo_Media {

	const META_KEY = '_feedivo_media_key';

	public static function sync_post_media( int $post_id, array $api_post, string $source_key ) {
		$items = self::media_items( $api_post );
		if ( array() === $items ) {
			Feedivo_Post_Type::merge_data( $post_id, array( 'gallery' => array(), 'video' => 0 ) );
			return true;
		}

		$first_error = null;
		$gallery_ids = array();
		$video_id    = 0;

		foreach ( $items as $item ) {
			$key      = sha1( $source_key . ':' . $item['position'] . ':' . $item['variant'] );
			$existing = self::find_attachment( $key );

			$attachment = ( null !== $existing )
				? $existing
				: self::sideload( $item['url'], $post_id, $key );

			if ( is_wp_error( $attachment ) ) {
				if ( null === $first_error ) {
					$first_error = $attachment;
				}
				continue;
			}

			if ( 'video' === $item['variant'] ) {
				if ( 0 === $video_id ) {
					$video_id = (int) $attachment;
				}
			} else {
				$gallery_ids[] = (int) $attachment;
			}
		}

		Feedivo_Post_Type::merge_data(
			$post_id,
			array(
				'gallery' => array_map( 'intval', $gallery_ids ),
				'video'   => $video_id,
			)
		);
		if ( array() !== $gallery_ids ) {
			set_post_thumbnail( $post_id, $gallery_ids[0] );
		}

		return null === $first_error ? true : $first_error;
	}

	public static function delete_post_attachments( int $post_id ) {
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_parent'    => $post_id,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- scoped to one post's own attachments.
				'meta_key'       => self::META_KEY,
				'meta_compare'   => 'EXISTS',
			)
		);

		foreach ( $attachments as $attachment_id ) {
			wp_delete_attachment( (int) $attachment_id, true );
		}
	}

	/**
	 * Normalise the API payload into a flat sideload list.
	 *
	 * @param array $api_post Post payload.
	 * @return array<int, array{url:string, variant:string}>
	 */
	private static function media_items( array $api_post ) {
		$raw = ! empty( $api_post['media'] ) && is_array( $api_post['media'] )
			? $api_post['media']
			: array(
				array(
					'url'           => $api_post['media_url'] ?? '',
					'thumbnail_url' => $api_post['thumbnail_url'] ?? '',
					'type'          => $api_post['type'] ?? 'image',
				),
			);

		$items = array();
		foreach ( array_values( $raw ) as $position => $media ) {
			$is_video = ( $media['type'] ?? 'image' ) === 'video';
			$main     = (string) ( $media['url'] ?? '' );
			$thumb    = (string) ( $media['thumbnail_url'] ?? '' );

			if ( $is_video ) {
				if ( '' !== $thumb ) {
					$items[] = array( 'position' => $position, 'variant' => 'thumb', 'url' => $thumb );
				}

				if ( '' !== $main && empty( $media['embed_url'] ) ) {
					$items[] = array( 'position' => $position, 'variant' => 'video', 'url' => $main );
				}
			} else {
				$url = '' !== $main ? $main : $thumb;
				if ( '' !== $url ) {
					$items[] = array( 'position' => $position, 'variant' => 'full', 'url' => $url );
				}
			}
		}

		return $items;
	}

	private static function find_attachment( string $media_key ) {
		$found = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- identity lookup; the only way to recognise an already-sideloaded file.
				'meta_key'       => self::META_KEY,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- see above.
				'meta_value'     => $media_key,
			)
		);

		return array() === $found ? null : (int) $found[0];
	}

	private static function sideload( string $url, int $post_id, string $media_key ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url, 60 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$name = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( '' === $name || ! preg_match( '/\.(jpe?g|png|gif|webp|mp4|mov)$/i', $name ) ) {
			$ext  = preg_match( '/\.(mp4|mov)(\?|$)/i', (string) $url ) ? 'mp4' : 'jpg';
			$name = 'feedivo-' . substr( $media_key, 0, 12 ) . '.' . $ext;
		}

		$attachment_id = media_handle_sideload(
			array(
				'name'     => $name,
				'tmp_name' => $tmp,
			),
			$post_id
		);

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			return $attachment_id;
		}

		update_post_meta( $attachment_id, self::META_KEY, $media_key );

		return (int) $attachment_id;
	}
}
