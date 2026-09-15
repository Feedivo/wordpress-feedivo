<?php
/**
 * HTTP client for the Feedivo API: `{data, meta, errors}` envelope in, decoded
 * array or WP_Error out. Every call takes the token of one connection.
 *
 * @package Feedivo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Feedivo_Api_Client {

	const TIMEOUT = 15;

	public function handshake( string $integration_id ) {
		$body = array(
			'integration_id' => $integration_id,
			'site_url'       => home_url(),
			'plugin_version' => FEEDIVO_VERSION,
		);

		$response = wp_remote_post(
			$this->base_url() . '/api/v1/wordpress/handshake',
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		return $this->parse( $response );
	}

	public function disconnect( string $token ) {
		if ( '' === $token ) {
			return new WP_Error( 'feedivo_not_connected', __( 'Not connected to Feedivo yet.', 'feedivo' ) );
		}

		$response = wp_remote_post(
			$this->base_url() . '/api/v1/wordpress/disconnect',
			array(
				'timeout' => self::TIMEOUT,
				'headers' => $this->auth_headers( $token ),
			)
		);

		return $this->parse( $response );
	}

	/**
	 * Headers for an authenticated call; the plugin version travels with each one.
	 *
	 * @param string $token Bearer token of the connection.
	 * @return array<string, string>
	 */
	private function auth_headers( string $token ) {
		return array(
			'Authorization'            => 'Bearer ' . $token,
			'Accept'                   => 'application/json',
			'X-Feedivo-Plugin-Version' => FEEDIVO_VERSION,
		);
	}

	public function get_feeds( string $token, int $timeout = self::TIMEOUT ) {
		return $this->get( $token, '/api/v1/feeds', $timeout );
	}

	public static function error_kind( WP_Error $error ) {
		$data = (array) $error->get_error_data();
		if ( 401 === (int) ( $data['status'] ?? 0 ) ) {
			return 'auth';
		}
		if ( 403 === (int) ( $data['status'] ?? 0 ) && 'entitlement_paused' === (string) ( $data['code'] ?? '' ) ) {
			return 'paused';
		}
		return 'transient';
	}

	public function get_posts( string $token, string $feed_uuid, $cursor = null, $since = null, int $limit = 50 ) {
		$args = array( 'limit' => $limit );
		if ( null !== $cursor && '' !== $cursor ) {
			$args['cursor'] = $cursor;
		}
		if ( null !== $since && '' !== $since ) {
			$args['since'] = $since;
		}

		return $this->get( $token, '/api/v1/feeds/' . rawurlencode( $feed_uuid ) . '/posts?' . http_build_query( $args ) );
	}

	private function get( string $token, string $path, int $timeout = self::TIMEOUT ) {
		if ( '' === $token ) {
			return new WP_Error( 'feedivo_not_connected', __( 'Not connected to Feedivo yet.', 'feedivo' ) );
		}

		$response = wp_remote_get(
			$this->base_url() . $path,
			array(
				'timeout' => $timeout,
				'headers' => $this->auth_headers( $token ),
			)
		);

		return $this->parse( $response );
	}

	private function parse( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			$message = __( 'Unexpected response from the Feedivo API.', 'feedivo' );
			if ( is_array( $body ) && ! empty( $body['errors'][0]['message'] ) ) {
				$message = (string) $body['errors'][0]['message'];
			}
			return new WP_Error(
				'feedivo_api',
				$message,
				array(
					'status' => $code,
					'code'   => is_array( $body ) ? (string) ( $body['errors'][0]['code'] ?? '' ) : '',
				)
			);
		}

		return $body;
	}

	private function base_url() {
		return untrailingslashit( (string) apply_filters( 'feedivo_api_url', FEEDIVO_API_URL ) );
	}
}
