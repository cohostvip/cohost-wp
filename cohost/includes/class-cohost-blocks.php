<?php
/**
 * Gutenberg blocks for individual event fields.
 *
 * Each block is a dynamic (server-rendered) block: PHP `render_callback`
 * outputs the HTML, and the block editor uses `<ServerSideRender />` so the
 * editor preview matches the front end exactly. No webpack/build pipeline.
 *
 * Blocks resolve "which event" in this order:
 *   1. attributes.eventId     (explicit override per block instance)
 *   2. ?cohost_event= or `cohost_event_id` query var (event profile page)
 *
 * @package CohostWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cohost_Blocks {

	const CATEGORY = 'cohost';

	/**
	 * Per-request event cache so seven blocks on a single page don't each
	 * trip a separate API call (the API client's transient cache handles
	 * cross-request reuse, this handles intra-request).
	 *
	 * @var array<string,array|WP_Error>
	 */
	private static $event_cache = array();

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'block_categories_all', array( __CLASS__, 'add_category' ), 10, 1 );
	}

	public static function add_category( $categories ) {
		foreach ( $categories as $cat ) {
			if ( isset( $cat['slug'] ) && self::CATEGORY === $cat['slug'] ) {
				return $categories;
			}
		}
		$entry = array(
			'slug'  => self::CATEGORY,
			'title' => __( 'Cohost', 'cohost' ),
		);
		// Insert the Cohost category just before "widgets" (typically position
		// 3 or 4) so it sits with content categories rather than dominating
		// the top or getting buried at the bottom. Falls back to before the
		// first of `theme` or `embed`, then to "after `media`", then end.
		$preferred_anchors = array( 'widgets', 'theme', 'embed' );
		$insert_at         = null;
		foreach ( $preferred_anchors as $anchor ) {
			foreach ( $categories as $i => $cat ) {
				if ( isset( $cat['slug'] ) && $anchor === $cat['slug'] ) {
					$insert_at = $i;
					break 2;
				}
			}
		}
		if ( null === $insert_at ) {
			// No anchor found — drop in after `media` if present, else append.
			foreach ( $categories as $i => $cat ) {
				if ( isset( $cat['slug'] ) && 'media' === $cat['slug'] ) {
					$insert_at = $i + 1;
					break;
				}
			}
		}
		if ( null === $insert_at ) {
			$categories[] = $entry;
			return $categories;
		}
		array_splice( $categories, $insert_at, 0, array( $entry ) );
		return $categories;
	}

	public static function register() {
		// Editor JS — registered as a script so each block can `editor_script` it.
		// Use filemtime in dev so saved edits bust the browser cache without
		// having to bump COHOST_WP_VERSION on every iteration. Falls back to
		// the plugin version if the file isn't readable.
		$blocks_path = COHOST_WP_PATH . 'assets/js/blocks.js';
		$blocks_ver  = file_exists( $blocks_path ) ? (string) filemtime( $blocks_path ) : COHOST_WP_VERSION;
		wp_register_script(
			'cohost-wp-blocks',
			COHOST_WP_URL . 'assets/js/blocks.js',
			array( 'wp-blocks', 'wp-block-editor', 'wp-element', 'wp-components', 'wp-i18n', 'wp-server-side-render' ),
			$blocks_ver,
			true
		);
		// Make sure the front-end stylesheet handle is registered so blocks
		// can declare `style` and have it auto-enqueued on pages that use them.
		if ( ! wp_style_is( 'cohost', 'registered' ) ) {
			$css_path = COHOST_WP_PATH . 'assets/css/cohost.css';
			$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : COHOST_WP_VERSION;
			wp_register_style(
				'cohost',
				COHOST_WP_URL . 'assets/css/cohost.css',
				array(),
				$css_ver
			);
		}

		$common_attrs = array(
			'eventId' => array( 'type' => 'string', 'default' => '' ),
		);

		register_block_type( 'cohost/event-name', array(
			'category'        => self::CATEGORY,
			'editor_script'   => 'cohost-wp-blocks',
			'style'           => 'cohost',
			'editor_style'    => 'cohost',
			'attributes'      => array_merge( $common_attrs, array(
				'level' => array( 'type' => 'number', 'default' => 1 ),
			) ),
			'render_callback' => array( __CLASS__, 'render_name' ),
		) );

		register_block_type( 'cohost/event-date', array(
			'category'        => self::CATEGORY,
			'editor_script'   => 'cohost-wp-blocks',
			'style'           => 'cohost',
			'editor_style'    => 'cohost',
			'attributes'      => array_merge( $common_attrs, array(
				// What to show: start | end | both | compact
				'display'      => array( 'type' => 'string', 'default' => 'compact' ),
				// Format preset: datetime | date | time | custom
				'format'       => array( 'type' => 'string', 'default' => 'datetime' ),
				// PHP date()-style format used when format = custom.
				'customFormat' => array( 'type' => 'string', 'default' => 'M j, Y g:ia' ),
				// Separator string used between start and end (e.g. " – ", " to ").
				'separator'    => array( 'type' => 'string', 'default' => ' – ' ),
			) ),
			'render_callback' => array( __CLASS__, 'render_date' ),
		) );

		register_block_type( 'cohost/event-flyer', array(
			'category'        => self::CATEGORY,
			'editor_script'   => 'cohost-wp-blocks',
			'style'           => 'cohost',
			'editor_style'    => 'cohost',
			'attributes'      => array_merge( $common_attrs, array(
				// thumbnail | small | medium | large | full
				'size'   => array( 'type' => 'string', 'default' => 'large' ),
				// auto | 16/9 | 4/3 | 1/1 | 3/4
				'aspect' => array( 'type' => 'string', 'default' => 'auto' ),
				// 'left' | 'center' | 'right'
				'align'  => array( 'type' => 'string', 'default' => 'center' ),
			) ),
			'render_callback' => array( __CLASS__, 'render_flyer' ),
		) );

		register_block_type( 'cohost/event-venue', array(
			'category'        => self::CATEGORY,
			'editor_script'   => 'cohost-wp-blocks',
			'style'           => 'cohost',
			'editor_style'    => 'cohost',
			'attributes'      => array_merge( $common_attrs, array(
				// name | address | name+address
				'display' => array( 'type' => 'string', 'default' => 'name+address' ),
			) ),
			'render_callback' => array( __CLASS__, 'render_venue' ),
		) );

		register_block_type( 'cohost/event-summary', array(
			'category'        => self::CATEGORY,
			'editor_script'   => 'cohost-wp-blocks',
			'style'           => 'cohost',
			'editor_style'    => 'cohost',
			'attributes'      => $common_attrs,
			'render_callback' => array( __CLASS__, 'render_summary' ),
		) );

		register_block_type( 'cohost/event-content', array(
			'category'        => self::CATEGORY,
			'editor_script'   => 'cohost-wp-blocks',
			'style'           => 'cohost',
			'editor_style'    => 'cohost',
			'attributes'      => $common_attrs,
			'render_callback' => array( __CLASS__, 'render_content' ),
		) );

		register_block_type( 'cohost/event-tickets', array(
			'category'        => self::CATEGORY,
			'editor_script'   => 'cohost-wp-blocks',
			'style'           => 'cohost',
			'editor_style'    => 'cohost',
			'attributes'      => array_merge( $common_attrs, array(
				'label' => array( 'type' => 'string', 'default' => 'Get tickets' ),
			) ),
			'render_callback' => array( __CLASS__, 'render_tickets' ),
		) );
	}

	// ------------------------------------------------------------------------
	// Event resolution
	// ------------------------------------------------------------------------

	/**
	 * Fetch the event the block instance refers to (or null on miss / error).
	 */
	private static function resolve_event( $attrs ) {
		$id = isset( $attrs['eventId'] ) ? trim( (string) $attrs['eventId'] ) : '';
		if ( ! $id ) {
			// Pretty-URL rewrite (Cohost_Rewrite) exposes the event id here.
			// Event profiles require pretty permalinks — sites on Plain
			// permalinks are warned on the settings page.
			$id = get_query_var( 'cohost_event_id' );
		}
		if ( ! $id ) {
			return null;
		}
		if ( array_key_exists( $id, self::$event_cache ) ) {
			return self::$event_cache[ $id ];
		}
		$response = Cohost_API_Client::get_event( $id );
		$event    = null;
		if ( ! is_wp_error( $response ) && is_array( $response ) ) {
			if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
				$event = $response['data'];
			} elseif ( isset( $response['event'] ) && is_array( $response['event'] ) ) {
				$event = $response['event'];
			} else {
				$event = $response;
			}
		}
		self::$event_cache[ $id ] = $event; // null on error/miss; the unwrapped event otherwise.
		return $event;
	}

	private static function placeholder( $message ) {
		// Editor preview / front-end fallback when there's no event in scope.
		// Only admins see the message; visitors get nothing rather than a debug string.
		if ( is_admin() || current_user_can( 'manage_options' ) ) {
			return '<div class="cohost-block-placeholder">' . esc_html( $message ) . '</div>';
		}
		return '';
	}

	// ------------------------------------------------------------------------
	// Renderers
	// ------------------------------------------------------------------------

	public static function render_name( $attrs ) {
		$event = self::resolve_event( $attrs );
		if ( ! $event ) {
			return self::placeholder( __( '[Cohost] Event name — no event in scope.', 'cohost' ) );
		}
		$name  = Cohost_Shortcodes::field( $event, array( 'name', 'title' ), '' );
		if ( '' === $name ) {
			return '';
		}
		$level = isset( $attrs['level'] ) ? max( 1, min( 6, intval( $attrs['level'] ) ) ) : 1;
		$tag   = 'h' . $level;
		return sprintf(
			'<%1$s class="cohost-block cohost-block--name">%2$s</%1$s>',
			$tag,
			esc_html( $name )
		);
	}

	public static function render_date( $attrs ) {
		$event = self::resolve_event( $attrs );
		if ( ! $event ) {
			return self::placeholder( __( '[Cohost] Event date — no event in scope.', 'cohost' ) );
		}
		$start = Cohost_Shortcodes::field( $event, array( 'start', 'startDate', 'startsAt', 'startAt' ) );
		$end   = Cohost_Shortcodes::field( $event, array( 'end', 'endDate', 'endsAt', 'endAt' ) );
		$tz    = Cohost_Shortcodes::field( $event, array( 'tz', 'timezone', 'location.timezone' ) );

		if ( ! $start ) {
			return '';
		}

		$display       = isset( $attrs['display'] ) ? (string) $attrs['display'] : 'compact';
		$format_preset = isset( $attrs['format'] ) ? (string) $attrs['format'] : 'datetime';
		$custom_format = isset( $attrs['customFormat'] ) ? (string) $attrs['customFormat'] : 'M j, Y g:ia';
		$separator     = isset( $attrs['separator'] ) ? (string) $attrs['separator'] : ' – ';

		$format = self::resolve_format( $format_preset, $custom_format, 'datetime' );
		$time_format = self::resolve_format( 'time', $custom_format, 'time' );
		$date_format = self::resolve_format( 'date', $custom_format, 'date' );

		$rendered = '';

		switch ( $display ) {
			case 'start':
				$rendered = Cohost_Shortcodes::format_date( $start, $tz, $format );
				break;

			case 'end':
				$rendered = $end ? Cohost_Shortcodes::format_date( $end, $tz, $format ) : '';
				break;

			case 'both':
				$rendered = Cohost_Shortcodes::format_date( $start, $tz, $format );
				if ( $end ) {
					$rendered .= esc_html( $separator ) . Cohost_Shortcodes::format_date( $end, $tz, $format );
				}
				break;

			case 'compact':
			default:
				// Same calendar day in the event's tz → start full + end as time only.
				// Different days → both full.
				$rendered = Cohost_Shortcodes::format_date( $start, $tz, $format );
				if ( $end ) {
					$same_day = self::same_day( $start, $end, $tz );
					$end_str  = $same_day
						? Cohost_Shortcodes::format_date( $end, $tz, $time_format )
						: Cohost_Shortcodes::format_date( $end, $tz, $format );
					$rendered .= esc_html( $separator ) . $end_str;
				}
				break;
		}

		if ( '' === trim( wp_strip_all_tags( $rendered ) ) ) {
			return '';
		}

		return '<div class="cohost-block cohost-block--date">' . esc_html( $rendered ) . '</div>';
	}

	private static function resolve_format( $preset, $custom_format, $fallback ) {
		switch ( $preset ) {
			case 'date':
				return get_option( 'date_format', 'F j, Y' );
			case 'time':
				return get_option( 'time_format', 'g:i a' );
			case 'datetime':
				return get_option( 'date_format', 'F j, Y' ) . ' ' . get_option( 'time_format', 'g:i a' );
			case 'custom':
				return $custom_format ? $custom_format : 'M j, Y g:ia';
		}
		return self::resolve_format( $fallback, $custom_format, 'datetime' );
	}

	private static function same_day( $iso_a, $iso_b, $tz ) {
		try {
			$tzobj = $tz ? new DateTimeZone( $tz ) : wp_timezone();
			$a = new DateTimeImmutable( $iso_a );
			$b = new DateTimeImmutable( $iso_b );
			return $a->setTimezone( $tzobj )->format( 'Y-m-d' ) === $b->setTimezone( $tzobj )->format( 'Y-m-d' );
		} catch ( Exception $e ) {
			return false;
		}
	}

	public static function render_flyer( $attrs ) {
		$event = self::resolve_event( $attrs );
		if ( ! $event ) {
			return self::placeholder( __( '[Cohost] Event flyer — no event in scope.', 'cohost' ) );
		}
		$src = Cohost_Shortcodes::field( $event, array( 'flyer.url', 'coverImage.url', 'image.url', 'cover.url' ) );
		if ( ! $src || is_array( $src ) ) {
			return '';
		}
		$alt = Cohost_Shortcodes::field( $event, array( 'name', 'title' ), '' );
		$size   = isset( $attrs['size'] ) ? (string) $attrs['size'] : 'large';
		$aspect = isset( $attrs['aspect'] ) ? (string) $attrs['aspect'] : 'auto';
		$align  = isset( $attrs['align'] ) ? (string) $attrs['align'] : 'center';

		$classes = array(
			'cohost-block',
			'cohost-block--flyer',
			'cohost-block--flyer-' . preg_replace( '/[^a-z0-9-]/', '', strtolower( $size ) ),
			'cohost-block--flyer-aspect-' . preg_replace( '/[^a-z0-9]/', '', strtolower( str_replace( '/', 'x', $aspect ) ) ),
			'cohost-block--flyer-align-' . preg_replace( '/[^a-z0-9]/', '', strtolower( $align ) ),
		);
		return sprintf(
			'<figure class="%1$s"><img src="%2$s" alt="%3$s" loading="lazy" /></figure>',
			esc_attr( implode( ' ', $classes ) ),
			esc_url( $src ),
			esc_attr( $alt )
		);
	}

	public static function render_venue( $attrs ) {
		$event = self::resolve_event( $attrs );
		if ( ! $event ) {
			return self::placeholder( __( '[Cohost] Event venue — no event in scope.', 'cohost' ) );
		}
		$name = Cohost_Shortcodes::field( $event, array( 'venue.name', 'location.name' ) );
		$addr = Cohost_Shortcodes::field( $event, array( 'venue.address.formattedAddress', 'location.address.formattedAddress' ) );
		if ( is_array( $name ) ) { $name = ''; }
		if ( is_array( $addr ) ) { $addr = ''; }
		$display = isset( $attrs['display'] ) ? (string) $attrs['display'] : 'name+address';
		if ( 'name' === $display )    { $addr = ''; }
		if ( 'address' === $display ) { $name = ''; }
		if ( ! $name && ! $addr ) {
			return '';
		}
		$out  = '<div class="cohost-block cohost-block--venue">';
		if ( $name ) { $out .= '<strong class="cohost-block__venue-name">' . esc_html( $name ) . '</strong>'; }
		if ( $name && $addr ) { $out .= '<br/>'; }
		if ( $addr ) { $out .= '<span class="cohost-block__venue-address">' . esc_html( $addr ) . '</span>'; }
		$out .= '</div>';
		return $out;
	}

	public static function render_summary( $attrs ) {
		$event = self::resolve_event( $attrs );
		if ( ! $event ) {
			return self::placeholder( __( '[Cohost] Event summary — no event in scope.', 'cohost' ) );
		}
		$summary = Cohost_Shortcodes::field( $event, array( 'summary', 'shortDescription', 'subtitle' ) );
		if ( ! $summary || is_array( $summary ) ) {
			return '';
		}
		return '<p class="cohost-block cohost-block--summary">' . esc_html( $summary ) . '</p>';
	}

	public static function render_content( $attrs ) {
		$event = self::resolve_event( $attrs );
		if ( ! $event ) {
			return self::placeholder( __( '[Cohost] Event content — no event in scope.', 'cohost' ) );
		}
		$id = Cohost_Shortcodes::field( $event, array( 'id' ) );
		if ( ! $id ) {
			return '';
		}
		$blocks = Cohost_API_Client::get_event_blocks( $id );
		if ( is_wp_error( $blocks ) || empty( $blocks ) ) {
			return '';
		}
		$out = '<div class="cohost-block cohost-block--content cohost-event__blocks">';
		foreach ( $blocks as $b ) {
			$out .= Cohost_Shortcodes::render_block( $b );
		}
		$out .= '</div>';
		return $out;
	}

	public static function render_tickets( $attrs ) {
		$event = self::resolve_event( $attrs );
		if ( ! $event ) {
			return self::placeholder( __( '[Cohost] Event tickets — no event in scope.', 'cohost' ) );
		}
		$ticket_url = Cohost_Shortcodes::field( $event, array( 'checkoutUrl', 'ticketUrl' ) );
		$label      = isset( $attrs['label'] ) && '' !== $attrs['label'] ? (string) $attrs['label'] : __( 'Get tickets', 'cohost' );
		if ( ! $ticket_url ) {
			// No checkout URL yet — show a disabled placeholder so authors see the block in the editor.
			return '<div class="cohost-block cohost-block--tickets cohost-block--tickets-disabled">' . esc_html( $label ) . '</div>';
		}
		return sprintf(
			'<p class="cohost-block cohost-block--tickets"><a class="cohost-button" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></p>',
			esc_url( $ticket_url ),
			esc_html( $label )
		);
	}
}
