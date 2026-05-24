<?php
/**
 * Admin settings page.
 *
 * @package CohostWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cohost_Settings {

	const OPTION_GROUP = 'cohost_wp_settings';
	const PAGE_SLUG    = 'cohost';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'admin_post_cohost_wp_clear_cache', array( __CLASS__, 'handle_clear_cache' ) );
		add_filter( 'plugin_action_links_' . COHOST_WP_BASENAME, array( __CLASS__, 'plugin_action_links' ) );
	}

	public static function enqueue_admin_assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook && 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		wp_enqueue_style( 'cohost-wp-admin', COHOST_WP_URL . 'assets/css/admin.css', array(), COHOST_WP_VERSION );
	}

	public static function plugin_action_links( $links ) {
		$url = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'cohost' ) . '</a>' );
		return $links;
	}

	public static function register_menu() {
		add_menu_page(
			__( 'Cohost', 'cohost' ),
			__( 'Cohost', 'cohost' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			self::menu_icon_data_uri(),
			58
		);
		add_options_page(
			__( 'Cohost', 'cohost' ),
			__( 'Cohost', 'cohost' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Inline SVG used as the admin menu icon. Per Cohost brand: lowercase "c"
	 * with the orange square dot at the baseline-right.
	 *
	 * Path is the canonical Cohost lettermark from the brand guide,
	 * preserving the brand-correct C shape and C-to-square proportions.
	 *
	 * Notes:
	 * - The lettermark path uses original coords y∈[23, 51], x∈[0, 39]. We
	 *   center it vertically inside a 39×39 viewBox by translating (0, -17.5),
	 *   matching the square aspect of the other WP sidebar icons.
	 * - data-URI SVGs in the admin menu can't inherit color via
	 *   `currentColor` (no surrounding inline context), so colors are
	 *   hard-coded to brand values: light "c" (#F2F2F2) + orange square
	 *   (#f97316). This deliberately diverges from WP's monochrome menu
	 *   icon convention so the menu entry reads as Cohost-branded.
	 */
	private static function menu_icon_data_uri() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 39 39" fill="none">'
			. '<g transform="translate(0, -17.5)">'
			. '<path d="M13.92 50.576C11.296 50.576 8.928 49.984 6.816 48.8C4.736 47.584 3.072 45.936 1.824 43.856C0.608 41.776 0 39.44 0 36.848C0 34.256 0.608 31.936 1.824 29.888C3.04 27.808 4.704 26.176 6.816 24.992C8.928 23.808 11.296 23.216 13.92 23.216C15.872 23.216 17.68 23.552 19.344 24.224C21.008 24.896 22.432 25.84 23.616 27.056C24.8 28.24 25.648 29.648 26.16 31.28L19.92 33.968C19.472 32.656 18.704 31.616 17.616 30.848C16.56 30.08 15.328 29.696 13.92 29.696C12.672 29.696 11.552 30 10.56 30.608C9.6 31.216 8.832 32.064 8.256 33.152C7.712 34.24 7.44 35.488 7.44 36.896C7.44 38.304 7.712 39.552 8.256 40.64C8.832 41.728 9.6 42.576 10.56 43.184C11.552 43.792 12.672 44.096 13.92 44.096C15.36 44.096 16.608 43.712 17.664 42.944C18.72 42.176 19.472 41.136 19.92 39.824L26.16 42.56C25.68 44.096 24.848 45.472 23.664 46.688C22.48 47.904 21.056 48.864 19.392 49.568C17.728 50.24 15.904 50.576 13.92 50.576Z" fill="#F2F2F2"/>'
			. '<rect x="29" y="40" width="10" height="10" fill="#f97316"/>'
			. '</g>'
			. '</svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	public static function register_settings() {
		register_setting( self::OPTION_GROUP, 'cohost_wp_api_token', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );
		register_setting( self::OPTION_GROUP, 'cohost_wp_api_base_url', array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => Cohost_API_Client::DEFAULT_BASE_URL,
		) );
		register_setting( self::OPTION_GROUP, 'cohost_wp_events_page_id', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		) );
		register_setting( self::OPTION_GROUP, 'cohost_wp_event_page_id', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		) );
		register_setting( self::OPTION_GROUP, 'cohost_wp_templates_url', array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => Cohost_Template_Library::DEFAULT_URL,
		) );
	}

	public static function handle_clear_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'cohost_wp_clear_cache' );
		Cohost_API_Client::clear_cache();
		set_transient( 'cohost_wp_notice_' . get_current_user_id(), array( 'type' => 'cache_cleared' ), 30 );
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Notices are passed through a one-shot user-scoped transient set by
		// the nonce-verified admin-post handler — no $_GET reads, no auth gap.
		$notice_key = 'cohost_wp_notice_' . get_current_user_id();
		$notice     = get_transient( $notice_key );
		if ( is_array( $notice ) ) {
			delete_transient( $notice_key );
			if ( 'cache_cleared' === ( $notice['type'] ?? '' ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Cohost cache cleared.', 'cohost' ) . '</p></div>';
			}
		}

		if ( ! get_option( 'permalink_structure' ) ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Cohost:', 'cohost' ) . '</strong> '
				. esc_html__( 'event profile pages require pretty permalinks. Go to', 'cohost' )
				. ' <a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">'
				. esc_html__( 'Settings → Permalinks', 'cohost' ) . '</a> '
				. esc_html__( 'and pick anything other than "Plain". Until then, event cards render unlinked.', 'cohost' )
				. '</p></div>';
		}

		$api_token      = get_option( 'cohost_wp_api_token', '' );
		$base_url       = get_option( 'cohost_wp_api_base_url', Cohost_API_Client::DEFAULT_BASE_URL );
		$events_page_id = intval( get_option( 'cohost_wp_events_page_id', 0 ) );
		$event_page_id  = intval( get_option( 'cohost_wp_event_page_id', 0 ) );
		$templates_url  = get_option( 'cohost_wp_templates_url', Cohost_Template_Library::DEFAULT_URL );
		?>
		<div class="wrap cohost-admin">
			<header class="cohost-admin__header">
				<img class="cohost-admin__logo" src="<?php echo esc_url( COHOST_WP_URL . 'assets/img/icon.svg' ); ?>" alt="Cohost" width="56" height="56" />
				<div class="cohost-admin__title">
					<h1><?php esc_html_e( 'Cohost', 'cohost' ); ?></h1>
					<p class="cohost-admin__tagline"><?php esc_html_e( 'Your events on your site — your branding, your domain, your audience.', 'cohost' ); ?></p>
				</div>
			</header>
			<p class="cohost-admin__intro"><?php esc_html_e( 'Connect your WordPress site to Cohost with a personal API token, then drop the shortcodes onto any page.', 'cohost' ); ?></p>

			<h2 class="title"><?php esc_html_e( 'Connection status', 'cohost' ); ?></h2>
			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'API token', 'cohost' ); ?></th>
					<td>
						<?php if ( $api_token ) : ?>
							<p><span style="color:#1a7f37;">&#10003;</span> <?php esc_html_e( 'API token configured.', 'cohost' ); ?></p>
						<?php else : ?>
							<p><em><?php esc_html_e( 'No API token set.', 'cohost' ); ?></em></p>
						<?php endif; ?>
					</td>
				</tr>
			</tbody></table>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<h2 class="title"><?php esc_html_e( 'API token', 'cohost' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Paste a personal access token from your Cohost dashboard.', 'cohost' ); ?></p>
				<table class="form-table" role="presentation"><tbody>
					<tr>
						<th scope="row"><label for="cohost_wp_api_token"><?php esc_html_e( 'API token', 'cohost' ); ?></label></th>
						<td>
							<input type="password" autocomplete="new-password" id="cohost_wp_api_token" name="cohost_wp_api_token" value="<?php echo esc_attr( $api_token ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Stored in wp_options. Sent as Bearer token on every API call.', 'cohost' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cohost_wp_api_base_url"><?php esc_html_e( 'API base URL', 'cohost' ); ?></label></th>
						<td>
							<input type="url" id="cohost_wp_api_base_url" name="cohost_wp_api_base_url" value="<?php echo esc_attr( $base_url ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'Includes the API version path (e.g. /v1). Override only if pointing at a non-production Cohost API.', 'cohost' ); ?></p>
						</td>
					</tr>
				</tbody></table>

				<h2 class="title"><?php esc_html_e( 'Display', 'cohost' ); ?></h2>
				<p class="description" style="max-width:760px;"><?php esc_html_e( 'Use two separate pages so you can author the surrounding content for each one (e.g. a hero banner above the list, a signup form below the profile). Both pages are normal WordPress pages — edit them like any other.', 'cohost' ); ?></p>
				<table class="form-table" role="presentation"><tbody>
					<tr>
						<th scope="row"><label for="cohost_wp_events_page_id"><?php esc_html_e( 'Events list page', 'cohost' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages( array(
								'name'              => 'cohost_wp_events_page_id',
								'id'                => 'cohost_wp_events_page_id',
								'show_option_none'  => esc_html__( '— None —', 'cohost' ),
								'option_none_value' => 0,
								'selected'          => (int) $events_page_id,
							) );
							?>
							<p class="description"><?php esc_html_e( 'The page that hosts the grid. Add the [cohost_events] shortcode to it.', 'cohost' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="cohost_wp_event_page_id"><?php esc_html_e( 'Event profile page', 'cohost' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages( array(
								'name'              => 'cohost_wp_event_page_id',
								'id'                => 'cohost_wp_event_page_id',
								'show_option_none'  => esc_html__( '— Use events list page —', 'cohost' ),
								'option_none_value' => 0,
								'selected'          => (int) $event_page_id,
							) );
							?>
							<p class="description"><?php esc_html_e( 'The page that displays a single event. Add the [cohost_event] shortcode to it (no id attribute — it is read from the URL). Cards from the list page link here. If left blank, the events list page is reused for both list and profile.', 'cohost' ); ?></p>
						</td>
					</tr>
				</tbody></table>

				<details style="margin-top:1rem;">
					<summary style="cursor:pointer;font-weight:600;color:#374151;"><?php esc_html_e( 'Advanced', 'cohost' ); ?></summary>
					<table class="form-table" role="presentation"><tbody>
						<tr>
							<th scope="row"><label for="cohost_wp_templates_url"><?php esc_html_e( 'Templates manifest URL', 'cohost' ); ?></label></th>
							<td>
								<input type="url" id="cohost_wp_templates_url" name="cohost_wp_templates_url" value="<?php echo esc_attr( $templates_url ); ?>" class="regular-text" />
								<p class="description"><?php esc_html_e( 'JSON manifest of templates that appear on the Templates page. Defaults to the Cohost-hosted manifest. Override only for testing.', 'cohost' ); ?></p>
							</td>
						</tr>
					</tbody></table>
				</details>

				<?php submit_button(); ?>
			</form>

			<h2 class="title"><?php esc_html_e( 'Shortcodes', 'cohost' ); ?></h2>
			<p><strong><code>[cohost_events]</code></strong> — <?php esc_html_e( 'Renders an events grid. All attributes are optional:', 'cohost' ); ?></p>
			<table class="widefat striped" style="max-width:840px;">
				<thead>
					<tr><th><?php esc_html_e( 'Attribute', 'cohost' ); ?></th><th><?php esc_html_e( 'Description', 'cohost' ); ?></th><th><?php esc_html_e( 'Example', 'cohost' ); ?></th></tr>
				</thead>
				<tbody>
					<tr><td><code>limit</code></td><td><?php esc_html_e( 'Events per page (default 12)', 'cohost' ); ?></td><td><code>limit="6"</code></td></tr>
					<tr><td><code>columns</code></td><td><?php esc_html_e( 'Grid columns 1–6 (default 3) — display only', 'cohost' ); ?></td><td><code>columns="2"</code></td></tr>
					<tr><td><code>from</code> / <code>to</code></td><td><?php esc_html_e( 'ISO date range', 'cohost' ); ?></td><td><code>from="2026-06-01" to="2026-12-31"</code></td></tr>
					<tr><td><code>sort</code> / <code>order</code></td><td><?php esc_html_e( 'Sort field + direction', 'cohost' ); ?></td><td><code>sort="startDate" order="asc"</code></td></tr>
				</tbody>
			</table>
			<p><?php esc_html_e( 'Example combining several:', 'cohost' ); ?></p>
			<p><code>[cohost_events from="2026-06-01" to="2026-12-31" limit="6" columns="2" sort="startDate" order="asc"]</code></p>

			<p style="margin-top:1.5em;"><strong><code>[cohost_event id="…"]</code></strong> — <?php esc_html_e( 'Renders a single event profile. The id attribute accepts EITHER an event ID or an event slug — the API resolves both at the same endpoint.', 'cohost' ); ?></p>
			<p>
				<code>[cohost_event id="evt_abc123"]</code> &nbsp; <?php esc_html_e( 'or', 'cohost' ); ?> &nbsp; <code>[cohost_event id="summer-festival-2026"]</code>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'cohost_wp_clear_cache' ); ?>
				<input type="hidden" name="action" value="cohost_wp_clear_cache" />
				<button type="submit" class="button"><?php esc_html_e( 'Clear API cache', 'cohost' ); ?></button>
			</form>
		</div>
		<?php
	}
}
