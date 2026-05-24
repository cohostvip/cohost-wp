<?php
/**
 * Pretty-URL rewrite for /events/{id} → events page with cohost_event_id query var.
 *
 * @package CohostWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cohost_Rewrite {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
		add_filter( 'document_title_parts', array( __CLASS__, 'override_title' ) );
	}

	public static function register_query_var( $vars ) {
		$vars[] = 'cohost_event_id';
		return $vars;
	}

	public static function add_rewrite() {
		// Profile page hosts /event-slug/{id}. If a separate one is configured,
		// use it; otherwise the events-list page handles both list and profile.
		$profile_id = intval( get_option( 'cohost_wp_event_page_id', 0 ) );
		$list_id    = intval( get_option( 'cohost_wp_events_page_id', 0 ) );
		$page_id    = $profile_id ? $profile_id : $list_id;
		if ( ! $page_id ) {
			return;
		}
		$page = get_post( $page_id );
		if ( ! $page || 'page' !== $page->post_type ) {
			return;
		}
		$slug_path = get_page_uri( $page );
		if ( ! $slug_path ) {
			return;
		}
		add_rewrite_rule(
			'^' . preg_quote( $slug_path, '#' ) . '/([^/]+)/?$',
			'index.php?page_id=' . $page_id . '&cohost_event_id=$matches[1]',
			'top'
		);
	}

	public static function override_title( $parts ) {
		$id = get_query_var( 'cohost_event_id' );
		if ( ! $id ) {
			return $parts;
		}
		$response = Cohost_API_Client::get_event( $id );
		if ( is_wp_error( $response ) || ! is_array( $response ) ) {
			return $parts;
		}
		$event = isset( $response['event'] ) ? $response['event'] : ( isset( $response['data'] ) ? $response['data'] : $response );
		$name  = is_array( $event ) ? Cohost_Shortcodes::field( $event, array( 'name', 'title' ) ) : '';
		if ( $name ) {
			$parts['title'] = $name;
		}
		return $parts;
	}
}
