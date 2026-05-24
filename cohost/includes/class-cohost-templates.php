<?php
/**
 * Templates admin page — gallery + one-click apply.
 *
 * @package CohostWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cohost_Templates {

	const PAGE_SLUG = 'cohost-wp-templates';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 11 );
		add_action( 'admin_post_cohost_wp_apply_template', array( __CLASS__, 'handle_apply' ) );
		add_action( 'admin_post_cohost_wp_refresh_templates', array( __CLASS__, 'handle_refresh' ) );
	}

	public static function register_menu() {
		add_submenu_page(
			Cohost_Settings::PAGE_SLUG,
			__( 'Templates', 'cohost' ),
			__( 'Templates', 'cohost' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	public static function handle_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'cohost_wp_refresh_templates' );
		Cohost_Template_Library::clear_cache();
		self::set_notice( array( 'type' => 'templates_refreshed' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Apply a template:
	 *   1. Resolve the target page (from cohost_wp_events_page_id or
	 *      cohost_wp_event_page_id depending on type), creating it if it
	 *      doesn't exist.
	 *   2. Replace the page's post_content with the template's block markup.
	 *   3. Flush rewrites (in case the page slug changed).
	 *   4. Redirect back to the templates page with a success notice.
	 */
	public static function handle_apply() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Forbidden' );
		}
		check_admin_referer( 'cohost_wp_apply_template' );

		$id = isset( $_POST['template_id'] ) ? sanitize_key( wp_unslash( $_POST['template_id'] ) ) : '';
		$tpl = Cohost_Template_Library::get( $id );
		if ( ! $tpl ) {
			self::redirect_with_error( __( 'Template not found.', 'cohost' ) );
		}

		$option_name = 'profile' === $tpl['type'] ? 'cohost_wp_event_page_id' : 'cohost_wp_events_page_id';
		$page_id     = intval( get_option( $option_name, 0 ) );
		$created     = false;

		if ( ! $page_id ) {
			// No page configured — create one with sensible defaults so the
			// partner ends up with a working page on first apply.
			$page_id = wp_insert_post( array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'profile' === $tpl['type'] ? __( 'Event', 'cohost' ) : __( 'Events', 'cohost' ),
				'post_content' => $tpl['content'],
			), true );
			if ( is_wp_error( $page_id ) ) {
				self::redirect_with_error( $page_id->get_error_message() );
			}
			update_option( $option_name, intval( $page_id ), false );
			$created = true;
		} else {
			$updated = wp_update_post( array(
				'ID'           => $page_id,
				'post_content' => $tpl['content'],
				'post_status'  => 'publish',
			), true );
			if ( is_wp_error( $updated ) ) {
				self::redirect_with_error( $updated->get_error_message() );
			}
		}

		// Re-register the rewrite for the (possibly new) page slug.
		Cohost_Rewrite::add_rewrite();
		flush_rewrite_rules();

		self::set_notice( array(
			'type'    => $created ? 'template_applied_created' : 'template_applied',
			'page_id' => intval( $page_id ),
		) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	private static function redirect_with_error( $message ) {
		self::set_notice( array(
			'type'    => 'template_error',
			'message' => (string) $message,
		) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	private static function notice_transient_key() {
		return 'cohost_wp_tpl_notice_' . get_current_user_id();
	}

	private static function set_notice( array $notice ) {
		set_transient( self::notice_transient_key(), $notice, 30 );
	}

	private static function consume_notice() {
		$key    = self::notice_transient_key();
		$notice = get_transient( $key );
		if ( is_array( $notice ) ) {
			delete_transient( $key );
			return $notice;
		}
		return null;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Reuse the admin stylesheet for the branded header.
		wp_enqueue_style( 'cohost-wp-admin', COHOST_WP_URL . 'assets/css/admin.css', array(), COHOST_WP_VERSION );
		wp_enqueue_style( 'cohost-wp-templates', COHOST_WP_URL . 'assets/css/templates.css', array(), COHOST_WP_VERSION );

		$notice = self::consume_notice();
		if ( $notice ) {
			self::render_notice( $notice );
		}

		$templates = Cohost_Template_Library::all();
		$listings  = array_values( array_filter( $templates, static fn( $t ) => 'listing' === $t['type'] ) );
		$profiles  = array_values( array_filter( $templates, static fn( $t ) => 'profile' === $t['type'] ) );

		$events_page_id = intval( get_option( 'cohost_wp_events_page_id', 0 ) );
		$event_page_id  = intval( get_option( 'cohost_wp_event_page_id', 0 ) );

		?>
		<div class="wrap cohost-admin">
			<header class="cohost-admin__header">
				<img class="cohost-admin__logo" src="<?php echo esc_url( COHOST_WP_URL . 'assets/img/icon.svg' ); ?>" alt="Cohost" width="56" height="56" />
				<div class="cohost-admin__title">
					<h1><?php esc_html_e( 'Templates', 'cohost' ); ?></h1>
					<p class="cohost-admin__tagline"><?php esc_html_e( 'Pick a starting layout. One click to apply — edit afterwards in the block editor.', 'cohost' ); ?></p>
				</div>
			</header>

			<p class="cohost-admin__intro">
				<?php esc_html_e( 'Templates are pre-composed block layouts. Pick one for your events list page and one for your event profile page, then customize freely.', 'cohost' ); ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
					<?php wp_nonce_field( 'cohost_wp_refresh_templates' ); ?>
					<input type="hidden" name="action" value="cohost_wp_refresh_templates" />
					<button type="submit" class="button button-small"><?php esc_html_e( 'Refresh from server', 'cohost' ); ?></button>
				</form>
			</p>

			<h2 class="title"><?php esc_html_e( 'Events list page', 'cohost' ); ?>
				<?php if ( $events_page_id ) : ?>
					<?php /* translators: %s: title of the currently configured WP page. */ ?>
					<span class="cohost-templates__current-page"><?php echo esc_html( sprintf( __( 'Currently using: %s', 'cohost' ), get_the_title( $events_page_id ) ) ); ?></span>
				<?php else : ?>
					<span class="cohost-templates__current-page cohost-templates__current-page--unset"><?php esc_html_e( 'No page configured — applying a template will create one.', 'cohost' ); ?></span>
				<?php endif; ?>
			</h2>
			<?php self::render_grid( $listings ); ?>

			<h2 class="title"><?php esc_html_e( 'Event profile page', 'cohost' ); ?>
				<?php if ( $event_page_id ) : ?>
					<?php /* translators: %s: title of the currently configured WP page. */ ?>
					<span class="cohost-templates__current-page"><?php echo esc_html( sprintf( __( 'Currently using: %s', 'cohost' ), get_the_title( $event_page_id ) ) ); ?></span>
				<?php else : ?>
					<span class="cohost-templates__current-page cohost-templates__current-page--unset"><?php esc_html_e( 'No page configured — applying a template will create one.', 'cohost' ); ?></span>
				<?php endif; ?>
			</h2>
			<?php self::render_grid( $profiles ); ?>
		</div>
		<?php
	}

	private static function render_grid( $templates ) {
		if ( empty( $templates ) ) {
			echo '<p><em>' . esc_html__( 'No templates available.', 'cohost' ) . '</em></p>';
			return;
		}
		echo '<ul class="cohost-templates-grid">';
		foreach ( $templates as $tpl ) {
			?>
			<li class="cohost-template-card">
				<div class="cohost-template-card__preview">
					<?php if ( ! empty( $tpl['preview'] ) ) : ?>
						<img src="<?php echo esc_url( $tpl['preview'] ); ?>" alt="<?php echo esc_attr( $tpl['title'] ); ?>" loading="lazy" />
					<?php else : ?>
						<div class="cohost-template-card__preview-placeholder"><?php echo esc_html( $tpl['title'] ); ?></div>
					<?php endif; ?>
				</div>
				<div class="cohost-template-card__body">
					<div class="cohost-template-card__title-row">
						<h3 class="cohost-template-card__title"><?php echo esc_html( $tpl['title'] ); ?></h3>
						<?php if ( 'bundled' === ( $tpl['source'] ?? '' ) ) : ?>
							<span class="cohost-template-card__badge cohost-template-card__badge--bundled" title="<?php esc_attr_e( 'Ships with the plugin (works offline).', 'cohost' ); ?>"><?php esc_html_e( 'starter', 'cohost' ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $tpl['description'] ) ) : ?>
						<p class="cohost-template-card__description"><?php echo esc_html( $tpl['description'] ); ?></p>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm( '<?php echo esc_js( __( 'This will replace the contents of the target page. Continue?', 'cohost' ) ); ?>' );">
						<?php wp_nonce_field( 'cohost_wp_apply_template' ); ?>
						<input type="hidden" name="action" value="cohost_wp_apply_template" />
						<input type="hidden" name="template_id" value="<?php echo esc_attr( $tpl['id'] ); ?>" />
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'cohost' ); ?></button>
					</form>
				</div>
			</li>
			<?php
		}
		echo '</ul>';
	}

	private static function render_notice( $notice ) {
		$allowed_link = array(
			'a' => array(
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
			),
		);
		$type    = isset( $notice['type'] ) ? (string) $notice['type'] : '';
		$page_id = isset( $notice['page_id'] ) ? intval( $notice['page_id'] ) : 0;
		switch ( $type ) {
			case 'template_applied':
				$link = $page_id ? sprintf(
					' <a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( get_permalink( $page_id ) ),
					esc_html__( 'View page', 'cohost' )
				) : '';
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template applied. Your page now uses the new layout.', 'cohost' ) . wp_kses( $link, $allowed_link ) . '</p></div>';
				break;
			case 'template_applied_created':
				$link = $page_id ? sprintf(
					' <a href="%s" target="_blank" rel="noopener">%s</a>',
					esc_url( get_permalink( $page_id ) ),
					esc_html__( 'View page', 'cohost' )
				) : '';
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Template applied — a new page was created and configured for you.', 'cohost' ) . wp_kses( $link, $allowed_link ) . '</p></div>';
				break;
			case 'templates_refreshed':
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Templates refreshed from the server.', 'cohost' ) . '</p></div>';
				break;
			case 'template_error':
				$msg = isset( $notice['message'] ) && '' !== $notice['message']
					? (string) $notice['message']
					: __( 'Unknown error.', 'cohost' );
				/* translators: %s: detailed error message. */
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sprintf( __( 'Could not apply template: %s', 'cohost' ), $msg ) ) . '</p></div>';
				break;
		}
	}
}
