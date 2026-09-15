<?php
/**
 * Typed access to the plugin's options; the bulky ones are stored with
 * autoload=off. Connections are a map keyed by connection UUID, each with its
 * own token.
 *
 * @package Feedivo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Feedivo_Settings {

	const OPT_INTEGRATIONS     = 'feedivo_integrations';
	const OPT_SYNC_STATE       = 'feedivo_sync_state';
	const OPT_LAST_SYNC        = 'feedivo_last_sync';
	const OPT_ARCHIVE_DISABLED = 'feedivo_archive_disabled';

	/**
	 * @return array<string, array{token:string, name:string, status:string, connected_at:int, last_error:string}>
	 */
	public static function integrations() {
		$value = get_option( self::OPT_INTEGRATIONS, array() );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * @param array<string, array> $integrations
	 */
	public static function set_integrations( array $integrations ) {
		self::set( self::OPT_INTEGRATIONS, $integrations );
	}

	public static function add_integration( string $uuid, array $data ) {
		if ( '' === $uuid ) {
			return;
		}
		$all          = self::integrations();
		$all[ $uuid ] = array_merge(
			array(
				'token'        => '',
				'name'         => '',
				'status'       => 'active',
				'connected_at' => time(),
				'last_error'   => '',
			),
			$all[ $uuid ] ?? array(),
			$data
		);
		self::set_integrations( $all );
	}

	public static function remove_integration( string $uuid ) {
		$all = self::integrations();
		unset( $all[ $uuid ] );
		self::set_integrations( $all );
	}

	public static function integration( string $uuid ) {
		$all = self::integrations();
		return isset( $all[ $uuid ] ) && is_array( $all[ $uuid ] ) ? $all[ $uuid ] : array();
	}

	/**
	 * Tokens of all bound connections.
	 *
	 * @return array<string, string> uuid => token (empty tokens skipped).
	 */
	public static function tokens() {
		$tokens = array();
		foreach ( self::integrations() as $uuid => $row ) {
			if ( '' !== (string) ( $row['token'] ?? '' ) ) {
				$tokens[ $uuid ] = (string) $row['token'];
			}
		}
		return $tokens;
	}

	public static function is_connected() {
		return array() !== self::tokens();
	}

	public static function set_status( string $uuid, string $status, string $message = '' ) {
		$all = self::integrations();
		if ( ! isset( $all[ $uuid ] ) ) {
			return;
		}
		if ( ( $all[ $uuid ]['status'] ?? '' ) === $status && ( $all[ $uuid ]['last_error'] ?? '' ) === $message ) {
			return;
		}
		$all[ $uuid ]['status']     = $status;
		$all[ $uuid ]['last_error'] = $message;
		self::set_integrations( $all );
	}

	public static function note_error( string $uuid, string $message ) {
		$all = self::integrations();
		if ( isset( $all[ $uuid ] ) && ( $all[ $uuid ]['last_error'] ?? '' ) !== $message ) {
			$all[ $uuid ]['last_error'] = $message;
			self::set_integrations( $all );
		}
	}

	public static function has_errors() {
		foreach ( self::integrations() as $row ) {
			if ( 'error' === ( $row['status'] ?? 'active' ) ) {
				return true;
			}
		}
		return false;
	}

	public static function archive_disabled() {
		return '1' === (string) get_option( self::OPT_ARCHIVE_DISABLED, '' );
	}

	public static function set_archive_disabled( bool $disabled ) {
		// Registration reads this autoloaded option on every frontend request.
		update_option( self::OPT_ARCHIVE_DISABLED, $disabled ? '1' : '0', true );
	}

	public static function sync_state() {
		$value = get_option( self::OPT_SYNC_STATE, array() );
		return is_array( $value ) ? $value : array();
	}

	public static function set_sync_state( array $state ) {
		self::set( self::OPT_SYNC_STATE, $state );
	}

	/**
	 * @return array{finished_at?:int, posts_synced?:int, errors?:array}
	 */
	public static function last_sync() {
		$value = get_option( self::OPT_LAST_SYNC, array() );
		return is_array( $value ) ? $value : array();
	}

	public static function set_last_sync( array $summary ) {
		self::set( self::OPT_LAST_SYNC, $summary );
	}

	public static function disconnect() {
		delete_option( self::OPT_INTEGRATIONS );
		delete_option( self::OPT_SYNC_STATE );
	}

	/**
	 * Write an option with autoload off. update_option() keeps an existing
	 * autoload flag, so a missing option is added first.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 */
	private static function set( string $option, $value ) {
		if ( false === get_option( $option, false ) ) {
			add_option( $option, $value, '', 'no' );
			return;
		}
		update_option( $option, $value, false );
	}
}
