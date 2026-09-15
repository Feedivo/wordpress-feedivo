<?php
/**
 * Plugin Name:       Feedivo
 * Plugin URI:        https://feedivo.de/wordpress
 * Description:       Create fast, privacy-friendly social media feeds for Instagram, Facebook, Threads, Pinterest and YouTube. Gutenberg, Elementor and shortcode ready.
 * Version:           1.18.2
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Feedivo
 * Author URI:        https://feedivo.de
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       feedivo
 * Domain Path:       /languages
 *
 * @package Feedivo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FEEDIVO_VERSION', '1.18.2' );
define( 'FEEDIVO_PLUGIN_FILE', __FILE__ );
define( 'FEEDIVO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FEEDIVO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'FEEDIVO_API_URL' ) ) {
	define( 'FEEDIVO_API_URL', 'https://feedivo.de' );
}

require_once FEEDIVO_PLUGIN_DIR . 'includes/class-feedivo-settings.php';
require_once FEEDIVO_PLUGIN_DIR . 'includes/class-feedivo-api-client.php';
require_once FEEDIVO_PLUGIN_DIR . 'includes/class-feedivo-post-type.php';
require_once FEEDIVO_PLUGIN_DIR . 'includes/class-feedivo-media.php';
require_once FEEDIVO_PLUGIN_DIR . 'includes/class-feedivo-sync.php';
require_once FEEDIVO_PLUGIN_DIR . 'includes/class-feedivo-renderer.php';
require_once FEEDIVO_PLUGIN_DIR . 'includes/class-feedivo-shortcode.php';
require_once FEEDIVO_PLUGIN_DIR . 'includes/class-feedivo-block.php';
require_once FEEDIVO_PLUGIN_DIR . 'includes/class-feedivo-ajax.php';
require_once FEEDIVO_PLUGIN_DIR . 'includes/class-feedivo-frontend.php';
require_once FEEDIVO_PLUGIN_DIR . 'includes/class-feedivo-admin-page.php';
require_once FEEDIVO_PLUGIN_DIR . 'includes/class-feedivo-plugin.php';

function feedivo_cron_schedules( $schedules ) {
	$schedules['feedivo_five_minutes'] = array(
		'interval' => 300,
		'display'  => __( 'Every 5 minutes (Feedivo)', 'feedivo' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'feedivo_cron_schedules' );

register_activation_hook( __FILE__, 'feedivo_activate' );
register_deactivation_hook( __FILE__, 'feedivo_deactivate' );

function feedivo_activate() {
	Feedivo_Post_Type::register();
	flush_rewrite_rules();
	Feedivo_Post_Type::mark_rewrite_rules_current();

	if ( ! wp_next_scheduled( 'feedivo_sync_event' ) ) {
		wp_schedule_event( time() + 60, 'feedivo_five_minutes', 'feedivo_sync_event' );
	}
}

function feedivo_deactivate() {
	wp_clear_scheduled_hook( 'feedivo_sync_event' );
	wp_clear_scheduled_hook( 'feedivo_sync_tick' );
	delete_option( 'feedivo_sync_lock' );
	flush_rewrite_rules();
}

add_action( 'plugins_loaded', array( 'Feedivo_Plugin', 'instance' ) );
