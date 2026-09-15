<?php
/**
 * Sync engine. Runs in short, resumable slices through the phases
 * feeds → posts → cleanup, plus purge. Coverage is only mutated for
 * connections whose feed list was fetched successfully.
 *
 * @package Feedivo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Feedivo_Sync {

	const EVENT       = 'feedivo_sync_event';
	const TICK        = 'feedivo_sync_tick';
	const LOCK_OPTION = 'feedivo_sync_lock';
	const LOCK_TTL    = 600;

	const TERM_COVERING = 'feedivo_covering';

	const FULL_SYNC_INTERVAL = DAY_IN_SECONDS;

	const PAGE_SIZE  = 25;
	const BATCH_SIZE = 50;

	const MAX_MEDIA_RETRIES = 3;

	public static function init() {
		add_action( self::EVENT, array( __CLASS__, 'run_slice' ) );
		add_action( self::TICK, array( __CLASS__, 'run_slice' ) );
	}

	public static function request_run() {
		wp_schedule_single_event( time(), self::TICK );
		spawn_cron();
	}

	public static function run_now() {
		$pending_purge = 'purge' === (string) ( Feedivo_Settings::sync_state()['phase'] ?? '' );

		if ( ! self::run_slice() ) {
			self::request_run();
			return false;
		}

		if ( $pending_purge && 'idle' === (string) ( Feedivo_Settings::sync_state()['phase'] ?? '' ) && Feedivo_Settings::is_connected() ) {
			self::run_slice();
		}

		return true;
	}

	public static function request_purge() {
		Feedivo_Settings::set_sync_state(
			array(
				'run_id'          => time(),
				'phase'           => 'purge',
				'cleanup_last_id' => 0,
			)
		);
		self::request_run();
	}

	public static function disconnect_integration( string $uuid ) {

		$token = (string) ( Feedivo_Settings::integration( $uuid )['token'] ?? '' );
		if ( '' !== $token ) {
			( new Feedivo_Api_Client() )->disconnect( $token );
		}

		Feedivo_Settings::remove_integration( $uuid );

		if ( ! Feedivo_Settings::is_connected() ) {
			Feedivo_Settings::set_sync_state(
				array(
					'run_id'          => time(),
					'phase'           => 'purge',
					'cleanup_last_id' => 0,
				)
			);
			self::run_slice();
			return;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => Feedivo_Post_Type::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term_id ) {
				$covering = self::covering( (int) $term_id );
				if ( in_array( $uuid, $covering, true ) ) {
					self::set_covering( (int) $term_id, array_values( array_diff( $covering, array( $uuid ) ) ) );
				}
			}
		}

		self::run_slice();
	}

	public static function is_locked() {
		$lock = (int) get_option( self::LOCK_OPTION, 0 );
		return $lock > time() - self::LOCK_TTL;
	}

	public static function run_slice() {
		$state = Feedivo_Settings::sync_state();
		$phase = (string) ( $state['phase'] ?? 'idle' );

		if ( 'purge' !== $phase && ! Feedivo_Settings::is_connected() ) {
			return false;
		}

		if ( ! self::acquire_lock() ) {
			return false;
		}

		$deadline = microtime( true ) + self::time_budget();

		try {
			if ( 'idle' === $phase || '' === $phase ) {
				$state = self::fresh_state();
			}

			while ( microtime( true ) < $deadline && 'idle' !== $state['phase'] ) {
				switch ( $state['phase'] ) {
					case 'feeds':
						self::phase_feeds( $state, $deadline );
						break;
					case 'posts':
						self::phase_posts( $state, $deadline );
						break;
					case 'cleanup':
						self::phase_cleanup( $state, $deadline );
						break;
					case 'purge':
						self::phase_purge( $state, $deadline );
						break;
					default:
						$state['phase'] = 'idle';
				}
			}

			Feedivo_Settings::set_sync_state( $state );

			if ( 'idle' !== $state['phase'] ) {
				wp_schedule_single_event( time() + 30, self::TICK );
			}
		} finally {
			delete_option( self::LOCK_OPTION );
		}

		return true;
	}

	private static function phase_feeds( array &$state, float $deadline ) {
		$client = new Feedivo_Api_Client();

		$succeeded = array();
		$returned  = array();
		$union     = array();

		foreach ( Feedivo_Settings::integrations() as $uuid => $row ) {
			$token = (string) ( $row['token'] ?? '' );
			if ( '' === $token ) {
				continue;
			}

			if ( 'error' === ( $row['status'] ?? 'active' ) ) {
				continue;
			}

			if ( microtime( true ) >= $deadline ) {
				break;
			}

			$envelope = $client->get_feeds( $token );
			if ( is_wp_error( $envelope ) ) {
				self::record_failure( $uuid, $envelope );
				self::log_error( $state, sprintf( 'Feeds (%s): %s', (string) ( $row['name'] ?? $uuid ), $envelope->get_error_message() ) );
				continue;
			}

			// A malformed response is not an empty feed list.
			$feeds = self::valid_feed_list( $envelope );
			if ( null === $feeds ) {
				$message = __( 'Unexpected response from the Feedivo API.', 'feedivo' );
				Feedivo_Settings::note_error( $uuid, $message );
				self::log_error( $state, sprintf( 'Feeds (%s): %s', (string) ( $row['name'] ?? $uuid ), $message ) );
				continue;
			}

			Feedivo_Settings::set_status( $uuid, 'active' );
			$succeeded[]       = $uuid;
			$returned[ $uuid ] = array();

			foreach ( $feeds as $feed ) {
				$term_id = self::sync_term( $feed, $state );
				if ( null === $term_id ) {
					continue;
				}
				$returned[ $uuid ][] = $term_id;

				if ( ! empty( $feed['paused'] ) ) {
					continue;
				}

				if ( ! isset( $union[ $term_id ] ) ) {
					$config_stamp = (string) ( $feed['updated_at'] ?? '' );
					$synced_stamp = (string) get_term_meta( $term_id, 'feedivo_synced_config', true );
					$last_full    = (int) get_term_meta( $term_id, 'feedivo_full_sync_at', true );
					$full         = $config_stamp !== $synced_stamp || $last_full < time() - self::FULL_SYNC_INTERVAL;

					$union[ $term_id ] = array(
						'uuid'         => (string) $feed['id'],
						'term_id'      => $term_id,
						'integration'  => (string) $uuid,
						'mode'         => $full ? 'full' : 'incremental',
						'since'        => $full ? null : self::incremental_since( $term_id ),
						'cursor'       => null,
						'config_stamp' => $config_stamp,
					);
				}
			}
		}

		$local_terms = get_terms(
			array(
				'taxonomy'   => Feedivo_Post_Type::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		$configured = array_keys( Feedivo_Settings::integrations() );

		if ( ! is_wp_error( $local_terms ) ) {
			foreach ( $local_terms as $term_id ) {
				$term_id  = (int) $term_id;
				$covering = self::covering( $term_id );

				// Never remove coverage when every request failed.
				if ( array() !== $succeeded ) {
					$covering = array_values( array_intersect( $covering, $configured ) );
				}

				foreach ( $succeeded as $uuid ) {
					$in = in_array( $term_id, $returned[ $uuid ], true );
					if ( $in && ! in_array( $uuid, $covering, true ) ) {
						$covering[] = $uuid;
					} elseif ( ! $in ) {
						$covering = array_values( array_diff( $covering, array( $uuid ) ) );
					}
				}

				self::set_covering( $term_id, $covering );
				if ( array() === $covering ) {
					$state['removed_terms'][] = $term_id;
				}
			}
		}

		$state['queue'] = array_values( $union );
		$state['phase'] = array() !== $state['queue'] ? 'posts' : 'cleanup';
	}

	private static function phase_posts( array &$state, float $deadline ) {
		$client = new Feedivo_Api_Client();

		while ( microtime( true ) < $deadline ) {
			if ( empty( $state['current'] ) ) {
				if ( array() === $state['queue'] ) {
					$state['phase']           = 'cleanup';
					$state['cleanup_last_id'] = 0;
					return;
				}
				$state['current'] = array_shift( $state['queue'] );
			}

			$current = $state['current'];

			$token = (string) ( Feedivo_Settings::integration( (string) $current['integration'] )['token'] ?? '' );
			if ( '' === $token ) {
				$state['current'] = null;
				continue;
			}

			$envelope = $client->get_posts(
				$token,
				$current['uuid'],
				$current['cursor'],
				'incremental' === $current['mode'] ? $current['since'] : null,
				self::PAGE_SIZE
			);

			if ( is_wp_error( $envelope ) ) {
				self::log_error( $state, sprintf( 'Feed %s: %s', $current['uuid'], $envelope->get_error_message() ) );

				self::record_failure( (string) $current['integration'], $envelope );
				$state['current'] = null;
				continue;
			}

			if ( ! self::valid_post_page( $envelope ) ) {
				self::log_error(
					$state,
					sprintf(
						/* translators: %s: feed UUID. */
						__( 'Feed %s: unexpected response from the Feedivo API.', 'feedivo' ),
						$current['uuid']
					)
				);
				$state['current'] = null;
				continue;
			}

			foreach ( $envelope['data'] as $api_post ) {
				self::upsert_post( $api_post, (int) $current['term_id'], (int) $state['run_id'], $state );
				if ( microtime( true ) >= $deadline ) {

					return;
				}
			}

			$next_cursor = $envelope['meta']['pagination']['next_cursor'];
			if ( null === $next_cursor ) {
				$tid = (int) $current['term_id'];
				update_term_meta( $tid, 'feedivo_synced_at', gmdate( DATE_ATOM ) );
				update_term_meta( $tid, 'feedivo_synced_config', $current['config_stamp'] );

				if ( 'full' === $current['mode'] ) {
					$state['full_terms'][] = $tid;
				}

				if ( ! empty( $state['media_failed'][ $tid ] ) ) {

					$retries = (int) get_term_meta( $tid, 'feedivo_media_retry', true );
					if ( $retries < self::MAX_MEDIA_RETRIES ) {
						update_term_meta( $tid, 'feedivo_media_retry', $retries + 1 );
						delete_term_meta( $tid, 'feedivo_full_sync_at' );
					} else {
						update_term_meta( $tid, 'feedivo_full_sync_at', time() );
						delete_term_meta( $tid, 'feedivo_media_retry' );
					}
					unset( $state['media_failed'][ $tid ] );
				} else {
					delete_term_meta( $tid, 'feedivo_media_retry' );
					if ( 'full' === $current['mode'] ) {
						update_term_meta( $tid, 'feedivo_full_sync_at', time() );
					}
				}

				$state['current'] = null;
			} else {
				$state['current']['cursor'] = (string) $next_cursor;
			}
		}
	}

	private static function phase_cleanup( array &$state, float $deadline ) {
		$full_terms    = array_map( 'intval', (array) ( $state['full_terms'] ?? array() ) );
		$removed_terms = array_map( 'intval', (array) ( $state['removed_terms'] ?? array() ) );

		if ( array() === $full_terms && array() === $removed_terms ) {
			self::finish_run( $state );
			return;
		}

		while ( microtime( true ) < $deadline ) {
			$post_ids = self::next_post_batch( (int) $state['cleanup_last_id'] );
			if ( array() === $post_ids ) {
				foreach ( $removed_terms as $term_id ) {
					wp_delete_term( $term_id, Feedivo_Post_Type::TAXONOMY );
				}
				self::finish_run( $state );
				return;
			}

			foreach ( $post_ids as $post_id ) {
				$state['cleanup_last_id'] = $post_id;

				$term_ids = wp_get_object_terms( $post_id, Feedivo_Post_Type::TAXONOMY, array( 'fields' => 'ids' ) );
				$term_ids = is_wp_error( $term_ids ) ? array() : array_map( 'intval', $term_ids );
				$seen     = Feedivo_Post_Type::get_seen( $post_id );

				$keep = array();
				foreach ( $term_ids as $term_id ) {
					if ( in_array( $term_id, $removed_terms, true ) ) {
						wp_remove_object_terms( $post_id, $term_id, Feedivo_Post_Type::TAXONOMY );
						Feedivo_Post_Type::forget_seen_term( $post_id, $term_id );
						continue;
					}
					if ( in_array( $term_id, $full_terms, true )
						&& ( $seen[ $term_id ] ?? 0 ) !== (int) $state['run_id'] ) {
						wp_remove_object_terms( $post_id, $term_id, Feedivo_Post_Type::TAXONOMY );
						Feedivo_Post_Type::forget_seen_term( $post_id, $term_id );
						continue;
					}
					$keep[] = $term_id;
				}

				if ( array() === $keep ) {
					Feedivo_Media::delete_post_attachments( $post_id );
					wp_delete_post( $post_id, true );
				}

				if ( microtime( true ) >= $deadline ) {
					return;
				}
			}
		}
	}

	private static function phase_purge( array &$state, float $deadline ) {
		while ( microtime( true ) < $deadline ) {
			$post_ids = self::next_post_batch( (int) ( $state['cleanup_last_id'] ?? 0 ) );

			if ( array() === $post_ids ) {
				$terms = get_terms(
					array(
						'taxonomy'   => Feedivo_Post_Type::TAXONOMY,
						'hide_empty' => false,
						'fields'     => 'ids',
					)
				);
				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term_id ) {
						wp_delete_term( (int) $term_id, Feedivo_Post_Type::TAXONOMY );
					}
				}
				Feedivo_Settings::set_last_sync(
					array(
						'finished_at'  => time(),
						'posts_synced' => 0,
						'errors'       => array(),
					)
				);
				$state = array( 'phase' => 'idle' );
				return;
			}

			foreach ( $post_ids as $post_id ) {
				$state['cleanup_last_id'] = $post_id;
				Feedivo_Media::delete_post_attachments( $post_id );
				wp_delete_post( $post_id, true );

				if ( microtime( true ) >= $deadline ) {
					return;
				}
			}
		}
	}

	private static function upsert_post( array $api_post, int $term_id, int $run_id, array &$state ) {
		$source_key = (string) ( $api_post['platform'] ?? '' ) . ':' . (string) ( $api_post['id'] ?? '' );
		$existing   = self::find_post_by_source_key( $source_key );
		$subtype    = (string) ( $api_post['subtype'] ?? '' );

		$hash = md5(
			wp_json_encode(
				array(
					$api_post['caption'] ?? '',
					$api_post['permalink'] ?? '',
					$api_post['type'] ?? '',
					$subtype,
					$api_post['author'] ?? '',
					$api_post['published_at'] ?? '',
					count( (array) ( $api_post['media'] ?? array() ) ),
					wp_list_pluck( (array) ( $api_post['media'] ?? array() ), 'type' ),

					wp_list_pluck( (array) ( $api_post['media'] ?? array() ), 'embed_url' ),

					array_map(
						static function ( $media ) {
							return empty( $media['url'] ) ? 0 : 1;
						},
						(array) ( $api_post['media'] ?? array() )
					),
				)
			)
		);

		if ( null !== $existing && ( Feedivo_Post_Type::get_data( $existing )['hash'] ?? '' ) === $hash ) {
			wp_set_object_terms( $existing, array( $term_id ), Feedivo_Post_Type::TAXONOMY, true );
			Feedivo_Post_Type::set_seen( $existing, $term_id, $run_id );
			self::apply_pinned( $existing, ! empty( $api_post['pinned'] ) );
			return;
		}

		$caption  = (string) ( $api_post['caption'] ?? '' );
		$platform = (string) ( $api_post['platform'] ?? '' );
		$gmt      = '';
		if ( ! empty( $api_post['published_at'] ) ) {
			$timestamp = strtotime( (string) $api_post['published_at'] );
			$gmt       = false !== $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : '';
		}

		$title = self::clean_title( $caption );
		if ( '' === $title ) {
			$title = sprintf(
				/* translators: 1: platform name, 2: date */
				__( '%1$s post from %2$s', 'feedivo' ),
				ucfirst( $platform ),
				'' !== $gmt ? wp_date( get_option( 'date_format' ), strtotime( $gmt ) ) : '—'
			);
		}

		$postarr = array(
			'ID'           => $existing ?: 0,
			'post_type'    => Feedivo_Post_Type::POST_TYPE,

			'post_status'  => 'story' === $subtype ? 'private' : 'publish',
			'post_title'   => $title,
			'post_content' => wp_kses_post( wpautop( $caption ) ),
		);
		if ( null === $existing ) {

			$postarr['post_name'] = sanitize_title( $title );
		}
		if ( '' !== $gmt ) {
			$postarr['post_date_gmt'] = $gmt;
			$postarr['post_date']     = get_date_from_gmt( $gmt );
		}

		$post_id = wp_insert_post( $postarr, true );
		if ( is_wp_error( $post_id ) ) {
			self::log_error( $state, sprintf( 'Post %s: %s', $source_key, $post_id->get_error_message() ) );
			return;
		}

		update_post_meta( $post_id, Feedivo_Post_Type::META_SOURCE_KEY, $source_key );
		Feedivo_Post_Type::merge_data(
			$post_id,
			array(
				'platform'  => $platform,
				'type'      => (string) ( $api_post['type'] ?? 'image' ),
				'subtype'   => $subtype,
				'permalink' => esc_url_raw( (string) ( $api_post['permalink'] ?? '' ) ),
				'author'    => (string) ( $api_post['author'] ?? '' ),
				'hashtags'  => array_map( 'sanitize_text_field', (array) ( $api_post['hashtags'] ?? array() ) ),

				'embed'     => Feedivo_Post_Type::safe_embed_url( (string) ( $api_post['media'][0]['embed_url'] ?? '' ) ),
			)
		);

		wp_set_object_terms( $post_id, array( $term_id ), Feedivo_Post_Type::TAXONOMY, true );
		Feedivo_Post_Type::set_seen( $post_id, $term_id, $run_id );
		self::apply_pinned( (int) $post_id, ! empty( $api_post['pinned'] ) );

		$media_ok = Feedivo_Media::sync_post_media( $post_id, $api_post, $source_key );
		if ( true === $media_ok ) {
			// Only a fully-synced post keeps a hash — one without is retried.
			Feedivo_Post_Type::merge_data( $post_id, array( 'hash' => $hash ) );
		} else {
			$state['media_failed'][ $term_id ] = true;
			self::log_error( $state, sprintf( 'Media for %s: %s', $source_key, $media_ok->get_error_message() ) );
		}

		$state['posts_synced'] = (int) ( $state['posts_synced'] ?? 0 ) + 1;
	}

	private static function apply_pinned( int $post_id, bool $pinned ) {
		if ( $pinned ) {
			update_post_meta( $post_id, Feedivo_Post_Type::META_PINNED, '1' );
		} elseif ( '' !== (string) get_post_meta( $post_id, Feedivo_Post_Type::META_PINNED, true ) ) {
			delete_post_meta( $post_id, Feedivo_Post_Type::META_PINNED );
		}
	}

	private static function record_failure( string $uuid, WP_Error $error ) {
		$kind = Feedivo_Api_Client::error_kind( $error );
		if ( 'transient' === $kind ) {
			Feedivo_Settings::note_error( $uuid, $error->get_error_message() );
			return;
		}
		Feedivo_Settings::set_status( $uuid, 'auth' === $kind ? 'error' : 'paused', $error->get_error_message() );
	}

	private static function incremental_since( int $term_id ) {
		$stamp = strtotime( (string) get_term_meta( $term_id, 'feedivo_synced_at', true ) );
		return $stamp ? gmdate( DATE_ATOM, $stamp - 2 * DAY_IN_SECONDS ) : null;
	}

	/**
	 * @param int $term_id Feed term.
	 * @return array<int, string> Connection UUIDs covering the feed.
	 */
	private static function covering( int $term_id ) {
		$covering = get_term_meta( $term_id, self::TERM_COVERING, true );
		return is_array( $covering ) ? array_values( array_unique( array_filter( array_map( 'strval', $covering ) ) ) ) : array();
	}

	/**
	 * @param int                $term_id  Feed term.
	 * @param array<int, string> $covering Connection UUIDs.
	 */
	private static function set_covering( int $term_id, array $covering ) {
		update_term_meta( $term_id, self::TERM_COVERING, array_values( array_unique( $covering ) ) );
	}

	private static function clean_title( string $caption ) {
		$clean = (string) preg_replace( '/[\p{So}\p{Sk}\p{Cs}\p{Co}\p{Cn}\p{Cf}]+/u', '', $caption );
		$clean = trim( (string) preg_replace( '/\s+/u', ' ', $clean ) );

		return trim( wp_trim_words( $clean, 8, '' ) );
	}

	private static function sync_term( array $feed, array &$state ) {
		$uuid = (string) ( $feed['id'] ?? '' );
		$name = (string) ( $feed['name'] ?? $uuid );
		$slug = sanitize_title( (string) ( $feed['slug'] ?? $name ) );
		if ( '' === $uuid ) {
			return null;
		}

		$term = Feedivo_Post_Type::term_by_uuid( $uuid );

		if ( null === $term ) {
			$created = wp_insert_term( $name, Feedivo_Post_Type::TAXONOMY, array( 'slug' => $slug ) );
			if ( is_wp_error( $created ) ) {

				$created = wp_insert_term(
					$name,
					Feedivo_Post_Type::TAXONOMY,
					array( 'slug' => $slug . '-' . substr( md5( $uuid ), 0, 6 ) )
				);
			}
			if ( is_wp_error( $created ) ) {
				self::log_error( $state, sprintf( 'Feed term %s: %s', $name, $created->get_error_message() ) );
				return null;
			}
			$term_id = (int) $created['term_id'];
			update_term_meta( $term_id, 'feedivo_uuid', $uuid );
		} else {
			$term_id = (int) $term->term_id;
			if ( $term->name !== $name ) {
				wp_update_term( $term_id, Feedivo_Post_Type::TAXONOMY, array( 'name' => $name ) );
			}
		}

		update_term_meta( $term_id, 'feedivo_display', (array) ( $feed['display'] ?? array() ) );
		update_term_meta( $term_id, 'feedivo_paused', empty( $feed['paused'] ) ? 0 : 1 );
		update_term_meta( $term_id, 'feedivo_sort', 'oldest' === (string) ( $feed['filters']['sort'] ?? '' ) ? 'oldest' : 'newest' );

		return $term_id;
	}

	/**
	 * Validate a /feeds envelope before it may rewrite coverage.
	 *
	 * @param mixed $envelope Decoded API envelope.
	 * @return array|null Feed list, or null when the response cannot be trusted.
	 */
	private static function valid_feed_list( $envelope ) {
		if ( ! is_array( $envelope ) || ! isset( $envelope['data'] ) || ! is_array( $envelope['data'] ) ) {
			return null;
		}

		foreach ( $envelope['data'] as $feed ) {
			if ( ! is_array( $feed ) || '' === (string) ( $feed['id'] ?? '' ) ) {
				return null;
			}
		}

		return $envelope['data'];
	}

	/**
	 * Validate a /posts page. The next_cursor key must be present; a missing
	 * key never counts as "last page".
	 *
	 * @param mixed $envelope Decoded API envelope.
	 * @return bool
	 */
	private static function valid_post_page( $envelope ) {
		if ( ! is_array( $envelope ) || ! isset( $envelope['data'] ) || ! is_array( $envelope['data'] ) ) {
			return false;
		}

		$pagination = $envelope['meta']['pagination'] ?? null;
		if ( ! is_array( $pagination ) || ! array_key_exists( 'next_cursor', $pagination ) ) {
			return false;
		}

		$cursor = $pagination['next_cursor'];

		return null === $cursor || ( is_string( $cursor ) && '' !== $cursor );
	}

	/**
	 * Deletion-safe iteration: "ID greater than" instead of offsets.
	 *
	 * @param int $last_id Last processed post ID.
	 * @return int[] Next batch of post IDs, ascending.
	 */
	private static function next_post_batch( int $last_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- keyset cursor over rows that are being deleted as we walk them; WP_Query offsets would skip rows and a cache would be stale by design.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND ID > %d ORDER BY ID ASC LIMIT %d",
				Feedivo_Post_Type::POST_TYPE,
				$last_id,
				self::BATCH_SIZE
			)
		);

		return array_map( 'intval', $ids );
	}

	private static function find_post_by_source_key( string $source_key ) {
		$found = get_posts(
			array(
				'post_type'      => Feedivo_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- identity lookup: the source key is how a synced post is recognised across runs.
				'meta_key'       => Feedivo_Post_Type::META_SOURCE_KEY,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- see above.
				'meta_value'     => $source_key,
			)
		);

		return array() === $found ? null : (int) $found[0];
	}

	private static function fresh_state() {
		return array(
			'run_id'          => time(),
			'phase'           => 'feeds',
			'queue'           => array(),
			'current'         => null,
			'full_terms'      => array(),
			'removed_terms'   => array(),
			'cleanup_last_id' => 0,
			'posts_synced'    => 0,
			'media_failed'    => array(),
			'errors'          => array(),
		);
	}

	private static function finish_run( array &$state ) {
		Feedivo_Settings::set_last_sync(
			array(
				'finished_at'  => time(),
				'posts_synced' => (int) ( $state['posts_synced'] ?? 0 ),
				'errors'       => (array) ( $state['errors'] ?? array() ),
			)
		);
		$state = array( 'phase' => 'idle' );
	}

	private static function log_error( array &$state, string $message ) {
		$state['errors']   = (array) ( $state['errors'] ?? array() );
		$state['errors'][] = $message;

		$state['errors']   = array_slice( $state['errors'], -20 );
	}

	private static function acquire_lock() {
		if ( add_option( self::LOCK_OPTION, (string) time(), '', 'no' ) ) {
			return true;
		}
		if ( (int) get_option( self::LOCK_OPTION, 0 ) < time() - self::LOCK_TTL ) {
			update_option( self::LOCK_OPTION, (string) time(), false );
			return true;
		}
		return false;
	}

	private static function time_budget() {
		$budget = (float) apply_filters( 'feedivo_sync_time_budget', 20 );
		$max    = (int) ini_get( 'max_execution_time' );
		if ( $max > 0 ) {
			$budget = min( $budget, max( 5, $max - 5 ) );
		}
		return $budget;
	}
}
