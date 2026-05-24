<?php
/**
 * Shortcodes for events list and event profile.
 *
 * @package CohostWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cohost_Shortcodes {

	public static function init() {
		add_shortcode( 'cohost_events', array( __CLASS__, 'render_events' ) );
		add_shortcode( 'cohost_event', array( __CLASS__, 'render_event' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		wp_register_style(
			'cohost',
			COHOST_WP_URL . 'assets/css/cohost.css',
			array(),
			COHOST_WP_VERSION
		);
	}

	/**
	 * Build the URL to an event detail page.
	 *
	 * Prefers the dedicated "event profile page" if configured; otherwise
	 * falls back to the events-list page (legacy single-page behavior).
	 */
	public static function event_permalink( $event_id ) {
		// Event profiles route through a registered rewrite (see Cohost_Rewrite),
		// which requires pretty permalinks. On Plain permalinks no profile URL
		// is generated — cards render unlinked and the admin sees a warning on
		// the settings page.
		if ( ! get_option( 'permalink_structure' ) ) {
			return '';
		}
		$profile_page_id = intval( get_option( 'cohost_wp_event_page_id', 0 ) );
		$list_page_id    = intval( get_option( 'cohost_wp_events_page_id', 0 ) );
		$page_id         = $profile_page_id ? $profile_page_id : $list_page_id;
		if ( ! $page_id ) {
			return '';
		}
		$base = get_permalink( $page_id );
		if ( ! $base ) {
			return '';
		}
		return trailingslashit( $base ) . rawurlencode( $event_id );
	}

	public static function render_events( $atts ) {
		wp_enqueue_style( 'cohost' );

		$atts = shortcode_atts(
			array(
				'limit'   => 12,    // pagination size
				'columns' => 3,     // grid columns (display only)
				'from'    => '',    // ISO date / YYYY-MM-DD
				'to'      => '',
				'sort'    => '',    // startDate|name|createdAt|...
				'order'   => '',    // asc|desc
			),
			$atts,
			'cohost_events'
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public pagination param, read-only display, no state mutation.
		$page = isset( $_GET['cohost_page'] ) ? max( 1, intval( $_GET['cohost_page'] ) ) : 1;

		$response = Cohost_API_Client::list_events( array(
			'limit' => intval( $atts['limit'] ),
			'page'  => $page,
			'from'  => $atts['from'],
			'to'    => $atts['to'],
			'sort'  => $atts['sort'],
			'order' => $atts['order'],
		) );

		if ( is_wp_error( $response ) ) {
			return self::render_error( $response );
		}

		$events = self::extract_results( $response );

		ob_start();
		$columns = max( 1, min( 6, intval( $atts['columns'] ) ) );
		include COHOST_WP_PATH . 'templates/events-list.php';
		return ob_get_clean();
	}

	public static function render_event( $atts ) {
		wp_enqueue_style( 'cohost' );

		$atts = shortcode_atts(
			array(
				'id' => '',
			),
			$atts,
			'cohost_event'
		);

		$id = $atts['id'];

		// Fall back to the URL via the registered rewrite query var so a single
		// "Event profile" page renders whichever event the URL identifies.
		// Requires pretty permalinks; sites on Plain permalinks are warned on
		// the settings page.
		if ( ! $id ) {
			$id = get_query_var( 'cohost_event_id' );
		}

		if ( ! $id ) {
			// Friendly empty state when the page is visited without an event ID
			// (e.g. someone hits the bare profile-page URL directly).
			return '<div class="cohost-empty">' . esc_html__( 'No event selected.', 'cohost' ) . '</div>';
		}

		$response = Cohost_API_Client::get_event( $id );
		if ( is_wp_error( $response ) ) {
			return self::render_error( $response );
		}

		$event = self::extract_event( $response );
		if ( ! $event ) {
			return '<div class="cohost-error">' . esc_html__( 'Event not found.', 'cohost' ) . '</div>';
		}

		// Fetch content blocks. Treat any error (legacy data, etc.) as "no blocks"
		// rather than failing the page — the header/meta still render usefully.
		$blocks_result = Cohost_API_Client::get_event_blocks( $id );
		$blocks        = is_wp_error( $blocks_result ) ? array() : $blocks_result;

		ob_start();
		include COHOST_WP_PATH . 'templates/event-profile.php';
		return ob_get_clean();
	}

	/**
	 * Render a single content block.
	 *
	 * Block shape (from GET /events/{id}/blocks):
	 *   { id, type, designation, title, status, order, data }
	 *
	 * Known types: richtext, gallery, faq, locationList. Unknown types are
	 * skipped (we don't dump raw JSON to end users). Themes can override
	 * rendering via the `cohost_wp_render_block` filter.
	 */
	public static function render_block( $block ) {
		if ( ! is_array( $block ) ) {
			return '';
		}
		$type = isset( $block['type'] ) ? (string) $block['type'] : '';
		$data = isset( $block['data'] ) && is_array( $block['data'] ) ? $block['data'] : array();
		$title = isset( $block['title'] ) ? (string) $block['title'] : '';

		ob_start();
		switch ( $type ) {
			case 'richtext':
				$html = isset( $data['content']['html'] ) ? $data['content']['html'] : ( isset( $data['html'] ) ? $data['html'] : '' );
				if ( $html ) {
					echo '<div class="cohost-block cohost-block--richtext">';
					echo wp_kses_post( $html );
					echo '</div>';
				}
				break;

			case 'gallery':
				$images = isset( $data['images'] ) && is_array( $data['images'] ) ? $data['images'] : array();
				if ( ! empty( $images ) ) {
					echo '<div class="cohost-block cohost-block--gallery">';
					if ( $title ) {
						echo '<h2 class="cohost-block__title">' . esc_html( $title ) . '</h2>';
					}
					echo '<ul class="cohost-block__gallery-grid">';
					foreach ( $images as $img ) {
						$url     = isset( $img['url'] ) ? $img['url'] : '';
						$caption = isset( $img['caption'] ) ? (string) $img['caption'] : '';
						if ( ! $url ) {
							continue;
						}
						echo '<li class="cohost-block__gallery-item">';
						echo '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $caption ) . '" loading="lazy" />';
						if ( $caption ) {
							echo '<figcaption>' . esc_html( $caption ) . '</figcaption>';
						}
						echo '</li>';
					}
					echo '</ul>';
					echo '</div>';
				}
				break;

			case 'faq':
				$items = isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
				if ( ! empty( $items ) ) {
					echo '<div class="cohost-block cohost-block--faq">';
					if ( $title ) {
						echo '<h2 class="cohost-block__title">' . esc_html( $title ) . '</h2>';
					}
					echo '<dl class="cohost-block__faq">';
					foreach ( $items as $item ) {
						$q = isset( $item['question'] ) ? (string) $item['question'] : '';
						$a = isset( $item['answer'] ) ? (string) $item['answer'] : '';
						if ( ! $q && ! $a ) {
							continue;
						}
						echo '<dt>' . esc_html( $q ) . '</dt>';
						echo '<dd>' . nl2br( esc_html( $a ) ) . '</dd>';
					}
					echo '</dl>';
					echo '</div>';
				}
				break;

			case 'locationList':
			case 'locations':
				$locations = isset( $data['locations'] ) && is_array( $data['locations'] ) ? $data['locations'] : array();
				if ( ! empty( $locations ) ) {
					echo '<div class="cohost-block cohost-block--locations">';
					if ( $title ) {
						echo '<h2 class="cohost-block__title">' . esc_html( $title ) . '</h2>';
					}
					echo '<ul class="cohost-block__locations">';
					foreach ( $locations as $loc ) {
						$lname = isset( $loc['name'] ) ? (string) $loc['name'] : '';
						$laddr = isset( $loc['address']['formattedAddress'] ) ? (string) $loc['address']['formattedAddress'] : '';
						echo '<li>';
						if ( $lname ) {
							echo '<strong>' . esc_html( $lname ) . '</strong>';
						}
						if ( $laddr ) {
							echo '<br/>' . esc_html( $laddr );
						}
						echo '</li>';
					}
					echo '</ul>';
					echo '</div>';
				}
				break;

			default:
				// Unknown block type — skip rather than dump raw JSON.
				break;
		}
		$html = ob_get_clean();

		/**
		 * Filter the HTML for a single rendered block.
		 *
		 * @param string $html  Rendered HTML.
		 * @param array  $block The raw block payload.
		 */
		return apply_filters( 'cohost_wp_render_block', $html, $block );
	}

	private static function render_error( $error ) {
		$message = is_wp_error( $error ) ? $error->get_error_message() : (string) $error;
		if ( current_user_can( 'manage_options' ) ) {
			/* translators: %s: error message returned by the Cohost API. */
			return '<div class="cohost-error">' . esc_html( sprintf( __( 'Cohost error: %s', 'cohost' ), $message ) ) . '</div>';
		}
		return '<div class="cohost-error">' . esc_html__( 'Events are temporarily unavailable.', 'cohost' ) . '</div>';
	}

	/**
	 * Extract a results array from various paginated response shapes.
	 */
	private static function extract_results( $response ) {
		if ( ! is_array( $response ) ) {
			return array();
		}
		foreach ( array( 'results', 'data', 'items', 'events' ) as $key ) {
			if ( isset( $response[ $key ] ) && is_array( $response[ $key ] ) ) {
				return $response[ $key ];
			}
		}
		// Maybe the response itself is the array of events.
		if ( isset( $response[0] ) ) {
			return $response;
		}
		return array();
	}

	/**
	 * Unwrap an event object from potential envelope shapes.
	 */
	private static function extract_event( $response ) {
		if ( ! is_array( $response ) ) {
			return null;
		}
		if ( isset( $response['event'] ) && is_array( $response['event'] ) ) {
			return $response['event'];
		}
		if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
			return $response['data'];
		}
		return $response;
	}

	/**
	 * Best-effort field accessor for an event payload.
	 *
	 * Keys may be dot-paths (e.g. "flyer.url", "location.name", "description.html")
	 * to dig into nested objects.
	 */
	public static function field( $event, $keys, $default = '' ) {
		$keys = (array) $keys;
		foreach ( $keys as $key ) {
			$value = self::dig( $event, $key );
			if ( '' !== $value && null !== $value ) {
				return $value;
			}
		}
		return $default;
	}

	private static function dig( $source, $path ) {
		if ( ! is_array( $source ) ) {
			return null;
		}
		$segments = explode( '.', $path );
		$cursor   = $source;
		foreach ( $segments as $segment ) {
			if ( is_array( $cursor ) && array_key_exists( $segment, $cursor ) ) {
				$cursor = $cursor[ $segment ];
			} else {
				return null;
			}
		}
		return $cursor;
	}

	/**
	 * Format an ISO date for display, in the event's local timezone when provided.
	 *
	 * Cohost events ship a `tz` field (IANA, e.g. "America/New_York"). Without
	 * it the WordPress site timezone is used as a fallback, and as a last
	 * resort UTC.
	 *
	 * @param string $iso    ISO 8601 timestamp (e.g. 2026-01-01T01:00:00.000Z).
	 * @param string $tz     IANA timezone of the event.
	 * @param string $format date()-style format. Defaults to site date+time format.
	 */
	public static function format_date( $iso, $tz = '', $format = '' ) {
		if ( ! $iso ) {
			return '';
		}
		try {
			$dt = new DateTimeImmutable( $iso );
		} catch ( Exception $e ) {
			return '';
		}
		$timezone = null;
		if ( $tz ) {
			try {
				$timezone = new DateTimeZone( $tz );
			} catch ( Exception $e ) {
				$timezone = null;
			}
		}
		if ( ! $timezone ) {
			$timezone = wp_timezone();
		}
		$format = $format ? $format : ( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
		return wp_date( $format, $dt->getTimestamp(), $timezone );
	}
}
