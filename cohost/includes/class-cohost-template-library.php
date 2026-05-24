<?php
/**
 * Remote-first template library.
 *
 * Templates are pre-composed block markup that users apply with one click.
 * The library fetches a JSON manifest from a configurable URL (default:
 * https://templates.cohost.vip/wp/templates.json) and caches the result
 * in a transient. If the remote source is unreachable, the bundled starter
 * set is used so the Templates page is never empty.
 *
 * Template schema (JSON):
 *   {
 *     "version": 1,
 *     "templates": [{
 *       "id": "magazine-profile",        // unique slug
 *       "type": "profile" | "listing",   // which page kind it targets
 *       "title": "Magazine",
 *       "description": "Wide hero flyer, two-column body",
 *       "preview": "https://...png",     // 16/9 preview image URL
 *       "content": "<!-- wp:... -->...", // valid WP block markup
 *       "tags": ["minimal", "dark"]      // optional
 *     }]
 *   }
 *
 * @package CohostWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cohost_Template_Library {

	const DEFAULT_URL  = 'https://templates.cohost.vip/wp/templates.json';
	const CACHE_KEY    = 'cohost_wp_templates_v1';
	const CACHE_TTL    = 15 * MINUTE_IN_SECONDS;

	/**
	 * Resolve the configured manifest URL.
	 */
	public static function url() {
		$url = get_option( 'cohost_wp_templates_url', '' );
		$url = $url ? trim( $url ) : self::DEFAULT_URL;
		return $url;
	}

	/**
	 * Fetch and parse the remote manifest. Returns an array of template
	 * arrays, or an empty array when the response is unusable.
	 */
	private static function fetch_remote() {
		$url      = self::url();
		$response = wp_safe_remote_get( $url, array(
			'timeout' => 8,
			'headers' => array( 'Accept' => 'application/json' ),
		) );
		if ( is_wp_error( $response ) ) {
			return array();
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return array();
		}
		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );
		if ( ! is_array( $json ) ) {
			return array();
		}
		$templates = isset( $json['templates'] ) && is_array( $json['templates'] ) ? $json['templates'] : array();
		// Sanity-filter to records with the required fields. Ignore everything else.
		return array_values( array_filter( array_map( array( __CLASS__, 'normalize' ), $templates ) ) );
	}

	private static function normalize( $tpl ) {
		if ( ! is_array( $tpl ) ) {
			return null;
		}
		$id      = isset( $tpl['id'] ) ? sanitize_key( $tpl['id'] ) : '';
		$type    = isset( $tpl['type'] ) ? (string) $tpl['type'] : '';
		$content = isset( $tpl['content'] ) ? (string) $tpl['content'] : '';
		if ( ! $id || ! in_array( $type, array( 'listing', 'profile' ), true ) || ! $content ) {
			return null;
		}
		return array(
			'id'          => $id,
			'type'        => $type,
			'title'       => isset( $tpl['title'] ) ? (string) $tpl['title'] : $id,
			'description' => isset( $tpl['description'] ) ? (string) $tpl['description'] : '',
			'preview'     => isset( $tpl['preview'] ) ? esc_url_raw( $tpl['preview'] ) : '',
			'content'     => $content,
			'tags'        => isset( $tpl['tags'] ) && is_array( $tpl['tags'] ) ? array_values( array_filter( array_map( 'sanitize_text_field', $tpl['tags'] ) ) ) : array(),
			'source'      => 'remote',
		);
	}

	/**
	 * The bundled starter set, used as a fallback when the remote manifest
	 * is unreachable, and merged-under-remote so partners always see at
	 * least these even on a fresh install with no network.
	 */
	public static function fallback_templates() {
		$preview_url = COHOST_WP_URL . 'assets/img/templates/';
		return array(
			array(
				'id'          => 'listing-simple-grid',
				'type'        => 'listing',
				'title'       => __( 'Simple grid', 'cohost' ),
				'description' => __( 'Just the events grid — three columns, twelve events per page.', 'cohost' ),
				'preview'     => $preview_url . 'listing-simple-grid.svg',
				'tags'        => array( 'minimal', 'starter' ),
				'source'      => 'bundled',
				'content'     => "<!-- wp:shortcode -->\n[cohost_events]\n<!-- /wp:shortcode -->\n",
			),
			array(
				'id'          => 'listing-hero-grid',
				'type'        => 'listing',
				'title'       => __( 'Hero + grid', 'cohost' ),
				'description' => __( 'A heading + intro paragraph above the events grid.', 'cohost' ),
				'preview'     => $preview_url . 'listing-hero-grid.svg',
				'tags'        => array( 'starter' ),
				'source'      => 'bundled',
				'content'     => '<!-- wp:heading {"level":1,"textAlign":"center"} -->'
					. '<h1 class="wp-block-heading has-text-align-center">Upcoming events</h1>'
					. '<!-- /wp:heading -->'
					. '<!-- wp:paragraph {"align":"center"} -->'
					. '<p class="has-text-align-center">Find your next night out — full lineup below.</p>'
					. '<!-- /wp:paragraph -->'
					. '<!-- wp:shortcode -->[cohost_events limit="12" columns="3"]<!-- /wp:shortcode -->',
			),
			array(
				'id'          => 'profile-standard',
				'type'        => 'profile',
				'title'       => __( 'Standard', 'cohost' ),
				'description' => __( 'The default layout — flyer, name, date, venue, summary, content, tickets.', 'cohost' ),
				'preview'     => $preview_url . 'profile-standard.svg',
				'tags'        => array( 'starter' ),
				'source'      => 'bundled',
				'content'     => '<!-- wp:cohost/event-flyer {"size":"large","aspect":"16/9","align":"center"} /-->'
					. '<!-- wp:cohost/event-name {"level":1} /-->'
					. '<!-- wp:cohost/event-date {"display":"compact","format":"datetime"} /-->'
					. '<!-- wp:cohost/event-venue {"display":"name+address"} /-->'
					. '<!-- wp:cohost/event-summary /-->'
					. '<!-- wp:cohost/event-content /-->'
					. '<!-- wp:cohost/event-tickets {"label":"Get tickets"} /-->',
			),
			array(
				'id'          => 'profile-magazine',
				'type'        => 'profile',
				'title'       => __( 'Magazine', 'cohost' ),
				'description' => __( 'Wide hero flyer, two-column body — name + date + venue on the left, summary + content on the right.', 'cohost' ),
				'preview'     => $preview_url . 'profile-magazine.svg',
				'tags'        => array( 'editorial' ),
				'source'      => 'bundled',
				'content'     => '<!-- wp:cohost/event-flyer {"size":"full","aspect":"16/9"} /-->'
					. '<!-- wp:columns {"verticalAlignment":"top"} -->'
					. '<div class="wp-block-columns are-vertically-aligned-top">'
					. '<!-- wp:column {"width":"33%"} --><div class="wp-block-column" style="flex-basis:33%">'
					. '<!-- wp:cohost/event-name {"level":1} /-->'
					. '<!-- wp:cohost/event-date {"display":"compact","format":"datetime"} /-->'
					. '<!-- wp:cohost/event-venue {"display":"name+address"} /-->'
					. '<!-- wp:cohost/event-tickets {"label":"Get tickets"} /-->'
					. '</div><!-- /wp:column -->'
					. '<!-- wp:column {"width":"67%"} --><div class="wp-block-column" style="flex-basis:67%">'
					. '<!-- wp:cohost/event-summary /-->'
					. '<!-- wp:cohost/event-content /-->'
					. '</div><!-- /wp:column -->'
					. '</div><!-- /wp:columns -->',
			),
			array(
				'id'          => 'profile-poster',
				'type'        => 'profile',
				'title'       => __( 'Poster', 'cohost' ),
				'description' => __( 'Big flyer, prominent name and date, bold ticket button, content below.', 'cohost' ),
				'preview'     => $preview_url . 'profile-poster.svg',
				'tags'        => array( 'bold' ),
				'source'      => 'bundled',
				'content'     => '<!-- wp:cohost/event-flyer {"size":"full","aspect":"16/9"} /-->'
					. '<!-- wp:cohost/event-name {"level":1} /-->'
					. '<!-- wp:cohost/event-date {"display":"compact","format":"datetime"} /-->'
					. '<!-- wp:cohost/event-tickets {"label":"Get tickets"} /-->'
					. '<!-- wp:cohost/event-summary /-->'
					. '<!-- wp:cohost/event-content /-->',
			),
			array(
				'id'          => 'profile-minimal',
				'type'        => 'profile',
				'title'       => __( 'Minimal', 'cohost' ),
				'description' => __( 'Name, date, content. No image, no meta — for events that need text-only treatment.', 'cohost' ),
				'preview'     => $preview_url . 'profile-minimal.svg',
				'tags'        => array( 'minimal' ),
				'source'      => 'bundled',
				'content'     => '<!-- wp:cohost/event-name {"level":1} /-->'
					. '<!-- wp:cohost/event-date {"display":"compact","format":"datetime"} /-->'
					. '<!-- wp:cohost/event-content /-->',
			),
		);
	}

	/**
	 * Return the merged template list (remote + bundled). Remote takes
	 * precedence by id — partners can override a bundled "starter" by
	 * shipping the same id from the manifest.
	 */
	public static function all() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached && is_array( $cached ) ) {
			$remote = $cached;
		} else {
			$remote = self::fetch_remote();
			set_transient( self::CACHE_KEY, $remote, self::CACHE_TTL );
		}

		$bundled = self::fallback_templates();
		$by_id   = array();
		foreach ( $bundled as $tpl ) {
			$by_id[ $tpl['id'] ] = $tpl;
		}
		foreach ( $remote as $tpl ) {
			$by_id[ $tpl['id'] ] = $tpl; // remote overrides bundled
		}
		return array_values( $by_id );
	}

	public static function get( $id ) {
		$id  = sanitize_key( $id );
		$all = self::all();
		foreach ( $all as $tpl ) {
			if ( $tpl['id'] === $id ) {
				return $tpl;
			}
		}
		return null;
	}

	public static function clear_cache() {
		delete_transient( self::CACHE_KEY );
	}
}
