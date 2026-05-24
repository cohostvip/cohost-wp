<?php
/**
 * Uninstall handler — removes plugin options on deletion.
 *
 * @package CohostWP
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$cohost_wp_options = array(
	'cohost_wp_api_token',
	'cohost_wp_api_base_url',
	'cohost_wp_events_page_id',
	'cohost_wp_event_page_id',
	'cohost_wp_templates_url',
);

foreach ( $cohost_wp_options as $cohost_wp_option ) {
	delete_option( $cohost_wp_option );
}

global $wpdb;
// Bulk-purge every cohost_get_* and cohost_wp_templates_* transient in a
// single query. Iterating would mean N option lookups + N deletes; wp_cache_*
// doesn't apply on the uninstall path.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_cohost\\_get\\_%' OR option_name LIKE '\\_transient\\_timeout\\_cohost\\_get\\_%' OR option_name LIKE '\\_transient\\_cohost\\_wp\\_templates\\_%' OR option_name LIKE '\\_transient\\_timeout\\_cohost\\_wp\\_templates\\_%'" );
