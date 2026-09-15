<?php
/**
 * Settings screen: connect via connection ID, see connection status, sync,
 * disconnect and purge. Every state-changing action goes through
 * admin-post.php and authorize() — nonce first, then manage_options.
 *
 * @package Feedivo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Feedivo_Admin_Page {

	const PAGE_SLUG = 'feedivo-settings';

	/** @var string Hook suffix of the settings page, for the asset enqueue. */
	private static $hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_feedivo_connect', array( __CLASS__, 'handle_connect' ) );
		add_action( 'admin_post_feedivo_sync_now', array( __CLASS__, 'handle_sync_now' ) );
		add_action( 'admin_post_feedivo_save_archive', array( __CLASS__, 'handle_save_archive' ) );
		add_action( 'admin_post_feedivo_disconnect_integration', array( __CLASS__, 'handle_disconnect_integration' ) );
		add_action( 'admin_post_feedivo_disconnect', array( __CLASS__, 'handle_disconnect' ) );
		add_action( 'admin_post_feedivo_delete_data', array( __CLASS__, 'handle_delete_data' ) );
		add_action( 'admin_notices', array( __CLASS__, 'unconnected_notice' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );

		$basename = plugin_basename( FEEDIVO_PLUGIN_FILE );
		add_filter( 'plugin_action_links_' . $basename, array( __CLASS__, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_meta' ), 10, 2 );
	}

	public static function settings_url() {
		return admin_url( 'edit.php?post_type=' . Feedivo_Post_Type::POST_TYPE . '&page=' . self::PAGE_SLUG );
	}

	/**
	 * "Settings" in front of Deactivate/Delete on the Plugins screen — the first
	 * place a user looks after activating.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( self::settings_url() ),
			esc_html__( 'Settings', 'feedivo' )
		);

		array_unshift( $links, $settings );

		return $links;
	}

	/**
	 * Extra links in the plugin's description cell. The filter fires for every
	 * installed plugin, so this only acts on its own row.
	 *
	 * @param string[] $links       Existing row meta.
	 * @param string   $plugin_file Plugin file the row belongs to.
	 * @return string[]
	 */
	public static function row_meta( $links, $plugin_file ) {
		if ( plugin_basename( FEEDIVO_PLUGIN_FILE ) !== $plugin_file ) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( 'https://feedivo.de/hilfe' ),
			esc_html__( 'Documentation', 'feedivo' )
		);
		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( 'https://feedivo.de/kontakt' ),
			esc_html__( 'Support', 'feedivo' )
		);

		return $links;
	}

	public static function add_menu() {
		self::$hook = (string) add_submenu_page(
			'edit.php?post_type=' . Feedivo_Post_Type::POST_TYPE,
			__( 'Feedivo Settings', 'feedivo' ),
			__( 'Settings', 'feedivo' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render' )
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( '' === self::$hook || $hook !== self::$hook ) {
			return;
		}

		wp_enqueue_script(
			'feedivo-admin',
			FEEDIVO_PLUGIN_URL . 'assets/js/feedivo-admin.js',
			array(),
			FEEDIVO_VERSION,
			true
		);
	}

	public static function handle_connect() {

		self::authorize( 'feedivo_connect' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in authorize() above.
		$raw            = isset( $_POST['feedivo_integration_id'] ) ? sanitize_text_field( wp_unslash( $_POST['feedivo_integration_id'] ) ) : '';
		$integration_id = self::sanitize_integration_id( $raw );
		if ( '' === $integration_id ) {
			self::back( 'connect_invalid' );
		}

		$result = ( new Feedivo_Api_Client() )->handshake( $integration_id );
		if ( is_wp_error( $result ) ) {
			self::back( 'connect_failed', $result->get_error_message() );
		}

		$results = (array) ( $result['data']['results'] ?? array() );
		if ( array() === $results ) {
			self::back( 'connect_failed' );
		}

		foreach ( $results as $bound ) {
			$uuid = (string) ( $bound['integration']['id'] ?? '' );
			if ( '' === $uuid || empty( $bound['token'] ) ) {
				continue;
			}
			Feedivo_Settings::add_integration(
				$uuid,
				array(
					'token'        => (string) $bound['token'],
					'name'         => (string) ( $bound['integration']['name'] ?? '' ),
					'status'       => 'active',
					'connected_at' => time(),
					'last_error'   => '',
				)
			);
		}

		$ran = Feedivo_Sync::run_now();
		self::back( $ran ? 'connected' : 'connected_scheduled' );
	}

	public static function handle_sync_now() {
		self::authorize( 'feedivo_sync_now' );

		if ( ! Feedivo_Settings::is_connected() ) {
			self::back( 'not_connected' );
		}
		if ( Feedivo_Sync::is_locked() ) {
			self::back( 'sync_running' );
		}

		if ( ! Feedivo_Sync::run_now() ) {
			self::back( 'sync_scheduled' );
		}

		$done = 'idle' === (string) ( Feedivo_Settings::sync_state()['phase'] ?? 'idle' );
		self::back( $done ? 'synced' : 'sync_partial' );
	}

	public static function handle_save_archive() {

		self::authorize( 'feedivo_save_archive' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in authorize() above.
		$disabled = ! empty( $_POST['feedivo_archive_disabled'] );
		Feedivo_Settings::set_archive_disabled( $disabled );

		// Flush after registration uses the new value on the next request.
		self::back( $disabled ? 'archive_disabled' : 'archive_enabled' );
	}

	public static function handle_disconnect_integration() {

		self::authorize( 'feedivo_disconnect_integration' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in authorize() above.
		$uuid = isset( $_POST['feedivo_integration'] ) ? sanitize_text_field( wp_unslash( $_POST['feedivo_integration'] ) ) : '';
		if ( '' !== $uuid ) {
			Feedivo_Sync::disconnect_integration( $uuid );
		}

		self::back( 'integration_disconnected' );
	}

	public static function handle_disconnect() {
		self::authorize( 'feedivo_disconnect' );

		$client = new Feedivo_Api_Client();
		foreach ( Feedivo_Settings::tokens() as $token ) {
			$client->disconnect( $token );
		}

		Feedivo_Settings::disconnect();
		wp_clear_scheduled_hook( 'feedivo_sync_tick' );
		Feedivo_Sync::request_purge();

		self::back( 'disconnected' );
	}

	public static function handle_delete_data() {
		self::authorize( 'feedivo_delete_data' );

		Feedivo_Sync::request_purge();
		self::back( 'purge_scheduled' );
	}

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::refresh_statuses();

		$connected    = Feedivo_Settings::is_connected();
		$integrations = Feedivo_Settings::integrations();
		$last_sync    = Feedivo_Settings::last_sync();
		$state        = Feedivo_Settings::sync_state();
		$next_cron    = wp_next_scheduled( 'feedivo_sync_event' );
		?>
		<div class="wrap">
			<h1>Feedivo</h1>

			<?php if ( Feedivo_Settings::has_errors() ) : ?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'One or more Feedivo connections need your attention. Reconnect them below to keep your feeds up to date.', 'feedivo' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! $connected ) : ?>
				<div class="card" style="max-width: 860px;">
					<h2><?php esc_html_e( 'Connect this site to Feedivo', 'feedivo' ); ?></h2>
					<div class="notice notice-info inline">
						<p><strong><?php esc_html_e( 'New to Feedivo?', 'feedivo' ); ?></strong></p>
						<p><?php esc_html_e( 'Create a free account, connect your social channels and design your first feed. Then return here with its connection ID.', 'feedivo' ); ?></p>
						<p>
							<a class="button button-primary" href="<?php echo esc_url( 'https://feedivo.de/register' ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Create a free Feedivo account', 'feedivo' ); ?>
							</a>
						</p>
					</div>
					<p><?php esc_html_e( 'Paste the connection ID from your Feedivo account. Your feeds, content and design stay easy to manage in one place.', 'feedivo' ); ?></p>
					<?php self::render_connect_form(); ?>
				</div>
			<?php else : ?>
				<?php self::render_integrations_card( $integrations ); ?>
				<?php self::render_feeds_card(); ?>
				<?php self::render_archive_card(); ?>
				<?php self::render_sync_card( $state, $last_sync, $next_cron ); ?>
			<?php endif; ?>

			<div class="card" style="max-width: 860px; border-left: 4px solid #d63638;">
				<h2><?php esc_html_e( 'Danger zone', 'feedivo' ); ?></h2>
				<p><?php esc_html_e( 'Delete all posts and media imported by Feedivo while keeping the connection. Your Feedivo account is not changed, and the next update imports the content again.', 'feedivo' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					  data-feedivo-confirm="<?php echo esc_attr__( 'Delete all content imported by Feedivo from this website? This cannot be undone.', 'feedivo' ); ?>">
					<?php wp_nonce_field( 'feedivo_delete_data' ); ?>
					<input type="hidden" name="action" value="feedivo_delete_data">
					<?php submit_button( __( 'Delete imported content', 'feedivo' ), 'delete', 'submit', false ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	public static function unconnected_notice() {
		if ( ! current_user_can( 'manage_options' ) || Feedivo_Settings::is_connected() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'edit' !== $screen->base || Feedivo_Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}
		?>
		<div class="notice notice-info">
			<p><strong><?php esc_html_e( 'Create a beautiful social media feed for your WordPress website.', 'feedivo' ); ?></strong></p>
			<p><?php esc_html_e( 'Connect your channels in Feedivo, choose your content and publish it here with one connection ID.', 'feedivo' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( 'https://feedivo.de/register' ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Create a free Feedivo account', 'feedivo' ); ?>
				</a>
				<a class="button" href="<?php echo esc_url( self::settings_url() ); ?>">
					<?php esc_html_e( 'Connect an existing Feedivo account', 'feedivo' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	private static function render_connect_form() {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'feedivo_connect' ); ?>
			<input type="hidden" name="action" value="feedivo_connect">
			<p>
				<label for="feedivo_integration_id"><strong><?php esc_html_e( 'Connection ID', 'feedivo' ); ?></strong></label><br>
				<input type="text" class="regular-text code" id="feedivo_integration_id" name="feedivo_integration_id"
					   placeholder="00000000-0000-0000-0000-000000000000" required>
			</p>
			<p class="description"><?php esc_html_e( 'Find this ID on the WordPress connection page in your Feedivo account. You can add more connections at any time.', 'feedivo' ); ?></p>
			<?php submit_button( __( 'Connect', 'feedivo' ) ); ?>
		</form>
		<?php
	}

	private static function render_integrations_card( array $integrations ) {
		$feed_counts = self::feed_counts_by_integration();
		?>
		<div class="card" style="max-width: 860px;">
			<h2><?php esc_html_e( 'Connections', 'feedivo' ); ?></h2>
			<table class="widefat striped" style="width: 100%;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Connection', 'feedivo' ); ?></th>
						<th><?php esc_html_e( 'Status', 'feedivo' ); ?></th>
						<th><?php esc_html_e( 'Feeds', 'feedivo' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $integrations as $uuid => $row ) : ?>
						<tr>
							<td><?php echo esc_html( (string) ( $row['name'] ?? $uuid ) ); ?></td>
							<td>
								<?php $status = (string) ( $row['status'] ?? 'active' ); ?>
								<?php if ( 'active' === $status ) : ?>
									<span style="color:#00a32a;">&#9679; <?php esc_html_e( 'Active', 'feedivo' ); ?></span>
								<?php elseif ( 'paused' === $status ) : ?>
									<span style="color:#dba617;" title="<?php echo esc_attr( (string) ( $row['last_error'] ?? '' ) ); ?>">&#9679; <?php esc_html_e( 'Paused by your plan', 'feedivo' ); ?></span>
								<?php else : ?>
									<span style="color:#d63638;" title="<?php echo esc_attr( (string) ( $row['last_error'] ?? '' ) ); ?>">&#9679; <?php esc_html_e( 'Needs reconnect', 'feedivo' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( (string) ( $feed_counts[ $uuid ] ?? 0 ) ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
									  data-feedivo-confirm="<?php echo esc_attr__( 'Disconnect this connection? Its imported content will be removed from this website. Content shared with another connection will stay.', 'feedivo' ); ?>">
									<?php wp_nonce_field( 'feedivo_disconnect_integration' ); ?>
									<input type="hidden" name="action" value="feedivo_disconnect_integration">
									<input type="hidden" name="feedivo_integration" value="<?php echo esc_attr( (string) $uuid ); ?>">
									<?php submit_button( __( 'Disconnect', 'feedivo' ), 'small', 'submit', false ); ?>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'Add another connection', 'feedivo' ); ?></h3>
			<?php self::render_connect_form(); ?>

			<hr>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;"
				  data-feedivo-confirm="<?php echo esc_attr__( 'Disconnect all Feedivo connections and remove their imported content from this website? This cannot be undone.', 'feedivo' ); ?>">
				<?php wp_nonce_field( 'feedivo_disconnect' ); ?>
				<input type="hidden" name="action" value="feedivo_disconnect">
				<?php submit_button( __( 'Disconnect all connections', 'feedivo' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	private static function render_feeds_card() {
		$terms = get_terms( array( 'taxonomy' => Feedivo_Post_Type::TAXONOMY, 'hide_empty' => false ) );
		?>
		<div class="card" style="max-width: 860px;">
			<h2><?php esc_html_e( 'Feeds', 'feedivo' ); ?></h2>
			<?php if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) : ?>
				<table class="widefat striped" style="width: 100%;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Feed', 'feedivo' ); ?></th>
							<th><?php esc_html_e( 'Posts', 'feedivo' ); ?></th>
							<th><?php esc_html_e( 'Shortcode', 'feedivo' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $terms as $term ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . Feedivo_Post_Type::POST_TYPE . '&' . Feedivo_Post_Type::TAXONOMY . '=' . $term->slug ) ); ?>"><?php echo esc_html( $term->name ); ?></a></td>
								<td><?php echo esc_html( (string) $term->count ); ?></td>
								<td><code style="white-space:nowrap;">[feedivo feed="<?php echo esc_attr( (string) get_term_meta( $term->term_id, 'feedivo_uuid', true ) ); ?>"]</code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Your feeds will appear here after the first update.', 'feedivo' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_archive_card() {
		$disabled = Feedivo_Settings::archive_disabled();
		$post_base = (string) apply_filters( 'feedivo_post_rewrite_slug', 'feed-posts' );
		$term_base = (string) apply_filters( 'feedivo_feed_rewrite_slug', 'feed-kategorien' );
		?>
		<div class="card" style="max-width: 860px;">
			<h2><?php esc_html_e( 'Archive pages', 'feedivo' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: 1: archive URL path, 2: feed category URL path */
					esc_html__( 'Feedivo can create an overview at %1$s and a page for each feed below %2$s. Hide these pages if you only want feeds to appear where you add them.', 'feedivo' ),
					'<code>/' . esc_html( $post_base ) . '/</code>',
					'<code>/' . esc_html( $term_base ) . '/</code>'
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'feedivo_save_archive' ); ?>
				<input type="hidden" name="action" value="feedivo_save_archive">
				<p>
					<label>
						<input type="checkbox" name="feedivo_archive_disabled" value="1" <?php checked( $disabled ); ?>>
						<?php esc_html_e( 'Turn off the archive pages', 'feedivo' ); ?>
					</label>
				</p>
				<p class="description">
					<?php esc_html_e( 'Individual post links remain available. Your blocks, shortcodes and Elementor widgets continue to work as usual.', 'feedivo' ); ?>
				</p>
				<?php submit_button( __( 'Save', 'feedivo' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @param array     $state     Sync engine state.
	 * @param array     $last_sync Last sync summary.
	 * @param int|false $next_cron Next scheduled run timestamp.
	 */
	private static function render_sync_card( array $state, array $last_sync, $next_cron ) {
		?>
		<div class="card" style="max-width: 860px;">
			<h2><?php esc_html_e( 'Updates', 'feedivo' ); ?></h2>
			<p>
				<?php if ( ! empty( $state['phase'] ) && 'idle' !== $state['phase'] ) : ?>
					<?php
					esc_html_e( 'Feedivo is updating your content…', 'feedivo' );
					?>
				<?php elseif ( ! empty( $last_sync['finished_at'] ) ) : ?>
					<?php
					printf(
						/* translators: 1: date/time, 2: number of posts */
						esc_html__( 'Last update: %1$s (%2$d posts).', 'feedivo' ),
						esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last_sync['finished_at'] ) ),
						(int) ( $last_sync['posts_synced'] ?? 0 )
					);
					?>
				<?php else : ?>
					<?php esc_html_e( 'No update has completed yet.', 'feedivo' ); ?>
				<?php endif; ?>
			</p>
			<?php if ( ! empty( $last_sync['errors'] ) ) : ?>
				<?php
				$raw_errors      = array_slice( (array) $last_sync['errors'], 0, 5 );
				$friendly_errors = array_unique( array_map( array( __CLASS__, 'friendly_error' ), array_map( 'strval', $raw_errors ) ) );
				?>
				<div class="notice notice-warning inline">
					<p><strong><?php esc_html_e( 'The last update needs attention:', 'feedivo' ); ?></strong></p>
					<ul style="margin-left: 1.5em; list-style: disc;">
						<?php foreach ( $friendly_errors as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
					<details style="margin: 0 0 8px;">
						<summary><?php esc_html_e( 'Technical details', 'feedivo' ); ?></summary>
						<ul style="margin-left: 1.5em; list-style: disc;">
							<?php foreach ( $raw_errors as $error ) : ?>
								<li><code><?php echo esc_html( (string) $error ); ?></code></li>
							<?php endforeach; ?>
						</ul>
					</details>
				</div>
			<?php endif; ?>
			<p class="description">
				<?php
				if ( $next_cron ) {
					printf(
						/* translators: %s: date/time of the next scheduled sync */
						esc_html__( 'Next automatic update: %s.', 'feedivo' ),
						esc_html( wp_date( get_option( 'time_format' ), $next_cron ) )
					);
				} else {
					esc_html_e( 'Automatic updates are not scheduled. Reactivate the plugin to restore them.', 'feedivo' );
				}
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'feedivo_sync_now' ); ?>
				<input type="hidden" name="action" value="feedivo_sync_now">
				<?php submit_button( __( 'Update now', 'feedivo' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	public static function notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only notice code; values come from a fixed map and are escaped below.
		if ( empty( $_GET['feedivo_notice'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$code   = sanitize_key( wp_unslash( $_GET['feedivo_notice'] ) );
		$detail = isset( $_GET['feedivo_detail'] ) ? sanitize_text_field( wp_unslash( $_GET['feedivo_detail'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$map = array(
			'connected'               => array( 'success', __( 'Connected to Feedivo. Your content is ready; larger feeds continue updating in the background.', 'feedivo' ) ),
			'connected_scheduled'     => array( 'success', __( 'Connected to Feedivo. Your first update will start shortly.', 'feedivo' ) ),
			'connect_invalid'         => array( 'error', __( 'Enter a valid connection ID from your Feedivo account.', 'feedivo' ) ),
			'connect_failed'          => array( 'error', __( 'Connecting to Feedivo failed.', 'feedivo' ) ),
			'synced'                  => array( 'success', __( 'Your Feedivo content is up to date.', 'feedivo' ) ),
			'sync_partial'            => array( 'success', __( 'Content updated. Larger feeds continue in the background.', 'feedivo' ) ),
			'sync_scheduled'          => array( 'success', __( 'Your update is queued and will start shortly.', 'feedivo' ) ),
			'sync_running'            => array( 'warning', __( 'An update is already running.', 'feedivo' ) ),
			'not_connected'           => array( 'error', __( 'Not connected to Feedivo yet.', 'feedivo' ) ),
			'integration_disconnected' => array( 'success', __( 'Connection disconnected — its content is being removed in the background.', 'feedivo' ) ),
			'disconnected'            => array( 'success', __( 'Disconnected. All imported Feedivo content is being removed in the background.', 'feedivo' ) ),
			'purge_scheduled'         => array( 'success', __( 'Your Feedivo content is being removed in the background.', 'feedivo' ) ),
			'archive_disabled'        => array( 'success', __( 'Archive pages turned off. The single post pages stay reachable.', 'feedivo' ) ),
			'archive_enabled'         => array( 'success', __( 'Archive pages turned on.', 'feedivo' ) ),
		);

		if ( ! isset( $map[ $code ] ) ) {
			return;
		}

		list( $type, $message ) = $map[ $code ];

		if ( '' !== $detail ) {

			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p><p>%s</p><details style="margin: 0 0 8px;"><summary>%s</summary><p><code>%s</code></p></details></div>',
				esc_attr( $type ),
				esc_html( $message ),
				esc_html( self::friendly_error( $detail ) ),
				esc_html__( 'Technical details', 'feedivo' ),
				esc_html( $detail )
			);
			return;
		}

		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( $message ) );
	}

	private static function refresh_statuses() {
		if ( get_transient( 'feedivo_status_checked' ) ) {
			return;
		}
		set_transient( 'feedivo_status_checked', 1, MINUTE_IN_SECONDS );

		$client = new Feedivo_Api_Client();
		foreach ( Feedivo_Settings::integrations() as $uuid => $row ) {
			$token = (string) ( $row['token'] ?? '' );
			if ( '' === $token || 'error' === ( $row['status'] ?? 'active' ) ) {
				continue;
			}

			$result = $client->get_feeds( $token, 5 );
			if ( ! is_wp_error( $result ) ) {
				Feedivo_Settings::set_status( $uuid, 'active' );
				continue;
			}

			$kind = Feedivo_Api_Client::error_kind( $result );
			if ( 'transient' !== $kind ) {
				Feedivo_Settings::set_status( $uuid, 'auth' === $kind ? 'error' : 'paused', $result->get_error_message() );
			}
		}
	}

	private static function friendly_error( string $raw ) {
		$m = strtolower( $raw );

		foreach ( array( 'curl error', 'timed out', 'timeout', 'could not resolve', 'connection refused' ) as $needle ) {
			if ( false !== strpos( $m, $needle ) ) {
				return __( 'Feedivo could not be reached. The next update will try again automatically.', 'feedivo' );
			}
		}
		if ( false !== strpos( $m, 'verknüpfungs-id unbekannt' ) ) {
			return __( 'This connection ID is unknown. Check it on the WordPress connection\'s page in your Feedivo account.', 'feedivo' );
		}
		if ( false !== strpos( $m, 'ungültiges oder fehlendes api-token' ) ) {
			return __( 'This connection is no longer authorized — it was deleted in Feedivo or another site connected with the same ID. Reconnect it with its connection ID, or disconnect it here.', 'feedivo' );
		}
		if ( false !== strpos( $m, 'pausiert' ) ) {
			return __( 'This connection is paused by your Feedivo plan. In Feedivo, choose which connections stay active or switch to a suitable plan. Reconnecting will not help.', 'feedivo' );
		}
		foreach ( array( 'rate limit', 'zu viele anfragen', 'too many requests' ) as $needle ) {
			if ( false !== strpos( $m, $needle ) ) {
				return __( 'Feedivo is receiving many requests right now. The next update will try again automatically.', 'feedivo' );
			}
		}
		foreach ( array( 'unexpected response', 'unerwartete antwort' ) as $needle ) {
			if ( false !== strpos( $m, $needle ) ) {
				return __( 'Unexpected response from the Feedivo API.', 'feedivo' );
			}
		}

		return __( 'The request to Feedivo failed.', 'feedivo' );
	}

	private static function sanitize_integration_id( string $raw ) {
		return substr( strtolower( (string) preg_replace( '/[^0-9a-fA-F-]/', '', trim( $raw ) ) ), 0, 64 );
	}

	/**
	 * Feed count per connection, derived from term coverage.
	 *
	 * @return array<string, int> uuid => number of feeds.
	 */
	private static function feed_counts_by_integration() {
		$counts = array();
		$terms  = get_terms(
			array(
				'taxonomy'   => Feedivo_Post_Type::TAXONOMY,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return $counts;
		}
		foreach ( $terms as $term_id ) {
			$covering = get_term_meta( (int) $term_id, Feedivo_Sync::TERM_COVERING, true );
			if ( is_array( $covering ) ) {
				foreach ( $covering as $uuid ) {
					$counts[ (string) $uuid ] = ( $counts[ (string) $uuid ] ?? 0 ) + 1;
				}
			}
		}
		return $counts;
	}

	private static function authorize( string $action ) {
		check_admin_referer( $action );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'feedivo' ) );
		}
	}

	/**
	 * Redirect back to the settings page with a notice code.
	 *
	 * @param string $notice Notice code.
	 * @param string $detail Optional extra message.
	 * @return never
	 */
	private static function back( string $notice, string $detail = '' ) {
		$url = add_query_arg(
			array_filter(
				array(
					'feedivo_notice' => $notice,
					'feedivo_detail' => $detail,
				)
			),
			self::settings_url()
		);
		wp_safe_redirect( $url );
		exit;
	}
}
