<?php
/**
 * Cohost REST API client.
 *
 * @package CohostWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cohost_API_Client {

	const DEFAULT_BASE_URL = 'https://api.cohost.vip/v1';
	const CACHE_GROUP      = 'cohost_wp';

	/**
	 * @return string
	 */
	public static function base_url() {
		$url = get_option( 'cohost_wp_api_base_url', self::DEFAULT_BASE_URL );
		return untrailingslashit( $url ? $url : self::DEFAULT_BASE_URL );
	}

	/**
	 * Resolve the active access token (API token from settings).
	 *
	 * @return string|null
	 */
	public static function access_token() {
		$token = get_option( 'cohost_wp_api_token' );
		return $token ? trim( $token ) : null;
	}

	/**
	 * Perform an authenticated GET request.
	 *
	 * @param string $path  e.g. /events
	 * @param array  $query Query string parameters.
	 * @param int    $ttl   Cache TTL in seconds (0 to disable).
	 *
	 * @return array|WP_Error Decoded JSON body, or WP_Error on failure.
	 */
	public static function get( $path, $query = array(), $ttl = 60 ) {
		$base  = self::base_url();
		$path  = '/' . ltrim( $path, '/' );
		$query = is_array( $query ) ? array_filter( $query, static function ( $v ) { return $v !== null && $v !== ''; } ) : array();
		$url   = $base . $path . ( ! empty( $query ) ? ( '?' . http_build_query( $query ) ) : '' );

		$cache_key = 'cohost_get_' . md5( $url );
		if ( $ttl > 0 ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$token = self::access_token();
		$args  = array(
			'timeout' => 15,
			'headers' => array(
				'Accept' => 'application/json',
			),
		);
		if ( $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $json ) && ! empty( $json['error']['message'] )
				? $json['error']['message']
				: ( is_array( $json ) && ! empty( $json['message'] ) ? $json['message'] : ( $body ? $body : 'Request failed' ) );
			return new WP_Error( 'cohost_http_' . $code, $message, array( 'status' => $code ) );
		}

		if ( $ttl > 0 ) {
			set_transient( $cache_key, $json, $ttl );
		}

		return $json;
	}

	/**
	 * Perform an authenticated POST/PATCH/DELETE.
	 *
	 * @param string $method  HTTP method.
	 * @param string $path    Endpoint path.
	 * @param array  $body    Request body, will be JSON encoded.
	 *
	 * @return array|WP_Error
	 */
	public static function request( $method, $path, $body = null ) {
		$url   = self::base_url() . '/' . ltrim( $path, '/' );
		$token = self::access_token();
		$args  = array(
			'method'  => strtoupper( $method ),
			'timeout' => 15,
			'headers' => array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
			),
		);
		if ( $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $json ) && ! empty( $json['error']['message'] )
				? $json['error']['message']
				: 'Request failed';
			return new WP_Error( 'cohost_http_' . $code, $message, array( 'status' => $code ) );
		}
		return $json;
	}

	/**
	 * Fetch a paginated list of events.
	 *
	 * Supported filters (forwarded to GET /events as query params):
	 *   limit, page  — pagination
	 *   from, to     — ISO date range (e.g. 2026-06-01 / 2026-12-31)
	 *   sort         — startDate|name|createdAt|...
	 *   order        — asc|desc
	 *
	 * @param array $args
	 * @return array|WP_Error Paginated response.
	 */
	public static function list_events( $args = array() ) {
		$query = array();

		foreach ( array( 'limit', 'page' ) as $key ) {
			if ( isset( $args[ $key ] ) && '' !== $args[ $key ] ) {
				$query[ $key ] = intval( $args[ $key ] );
			}
		}
		foreach ( array( 'from', 'to', 'sort', 'order' ) as $key ) {
			if ( isset( $args[ $key ] ) && '' !== $args[ $key ] && null !== $args[ $key ] ) {
				$query[ $key ] = sanitize_text_field( (string) $args[ $key ] );
			}
		}

		return self::get( '/events', $query, 60 );
	}

	/**
	 * Fetch a single event by ID **or slug**.
	 *
	 * The Cohost API resolves `/events/{idOrSlug}` against either identifier,
	 * so this accepts both transparently.
	 *
	 * @param string $id_or_slug Event ID or slug.
	 * @return array|WP_Error
	 */
	public static function get_event( $id_or_slug ) {
		$id_or_slug = sanitize_text_field( $id_or_slug );
		if ( '' === $id_or_slug ) {
			return new WP_Error( 'cohost_invalid_id', 'Event ID or slug is required' );
		}
		return self::get( '/events/' . rawurlencode( $id_or_slug ), array(), 60 );
	}

	/**
	 * Fetch the live content blocks for an event (sorted by order).
	 *
	 * Cohost's `description` field is deprecated; the rich event body is now
	 * composed of structured content blocks (richtext, gallery, faq, etc.)
	 * which the API returns at `GET /events/{idOrSlug}/blocks`.
	 *
	 * Response shape: `{ status:"ok", data:{ blocks: ContentBlock[] } }`.
	 * On legacy events whose contentBlocks haven't been migrated to the array
	 * shape, the API may 500; callers should treat that as "no blocks".
	 *
	 * @param string $id_or_slug
	 * @return array|WP_Error Array of block objects, or WP_Error on failure.
	 */
	public static function get_event_blocks( $id_or_slug ) {
		$id_or_slug = sanitize_text_field( $id_or_slug );
		if ( '' === $id_or_slug ) {
			return new WP_Error( 'cohost_invalid_id', 'Event ID or slug is required' );
		}
		$response = self::get( '/events/' . rawurlencode( $id_or_slug ) . '/blocks', array(), 60 );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		// Tolerate both { data: { blocks: [...] } } and { blocks: [...] }.
		if ( isset( $response['data']['blocks'] ) && is_array( $response['data']['blocks'] ) ) {
			return $response['data']['blocks'];
		}
		if ( isset( $response['blocks'] ) && is_array( $response['blocks'] ) ) {
			return $response['blocks'];
		}
		return array();
	}

	/**
	 * Clear cached API responses (transients with the cohost_get_ prefix).
	 */
	public static function clear_cache() {
		global $wpdb;
		// Bulk-delete every cohost_get_ transient in a single query. WordPress
		// ships no public helper that deletes transients by prefix; iterating
		// would mean N option lookups + N deletes. Caching/wp_cache_* helpers
		// don't apply here — this IS the cache invalidation path.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_cohost\\_get\\_%' OR option_name LIKE '\\_transient\\_timeout\\_cohost\\_get\\_%'" );
	}
}
