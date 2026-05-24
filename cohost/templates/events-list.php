<?php
/**
 * Events grid template.
 *
 * Vars: $events (array), $columns (int), $atts (array), $response (array), $page (int)
 *
 * Note on phpcs: locals in this file are template-scoped (the file is loaded
 * via `include` from a Cohost_Shortcodes method that has already buffered
 * output); they are not globals. Plugin Check's PrefixAllGlobals scanner
 * cannot distinguish the two, so the rule is suppressed file-wide.
 *
 * @package CohostWP
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="cohost-events" data-columns="<?php echo esc_attr( $columns ); ?>">
	<?php if ( empty( $events ) ) : ?>
		<p class="cohost-empty"><?php esc_html_e( 'No events to display.', 'cohost' ); ?></p>
	<?php else : ?>
		<ul class="cohost-events-grid" style="--cohost-cols: <?php echo esc_attr( $columns ); ?>;">
			<?php foreach ( $events as $event ) :
				// Use the event ID (always resolvable on /events/{id}); slug
				// lookups depend on channel-event-profile mapping which can
				// 404 for unmigrated events.
				$id    = Cohost_Shortcodes::field( $event, array( 'id', '_id', 'eventId', 'slug' ) );
				$name  = Cohost_Shortcodes::field( $event, array( 'name', 'title' ), __( 'Untitled event', 'cohost' ) );
				$start = Cohost_Shortcodes::field( $event, array( 'start', 'startDate', 'startsAt', 'startAt' ) );
				$tz    = Cohost_Shortcodes::field( $event, array( 'tz', 'timezone', 'location.timezone' ) );
				$venue_name = Cohost_Shortcodes::field( $event, array( 'venue.name', 'location.name', 'venue', 'location' ) );
				if ( is_array( $venue_name ) ) {
					$venue_name = '';
				}
				$image = Cohost_Shortcodes::field( $event, array( 'flyer.url', 'coverImage.url', 'image.url', 'imageUrl', 'cover.url', 'coverImage', 'image', 'cover' ) );
				if ( is_array( $image ) ) {
					$image = '';
				}
				$summary = Cohost_Shortcodes::field( $event, array( 'summary', 'shortDescription', 'subtitle' ) );
				$href = $id ? Cohost_Shortcodes::event_permalink( $id ) : '';
			?>
			<li class="cohost-event-card">
				<?php if ( $href ) : ?><a class="cohost-event-card__link" href="<?php echo esc_url( $href ); ?>"><?php endif; ?>
					<?php if ( $image ) : ?>
						<div class="cohost-event-card__image" style="background-image:url('<?php echo esc_url( $image ); ?>');"></div>
					<?php else : ?>
						<div class="cohost-event-card__image cohost-event-card__image--placeholder"></div>
					<?php endif; ?>
					<div class="cohost-event-card__body">
						<?php if ( $start ) : ?>
							<div class="cohost-event-card__date"><?php echo esc_html( Cohost_Shortcodes::format_date( $start, $tz ) ); ?></div>
						<?php endif; ?>
						<h3 class="cohost-event-card__title"><?php echo esc_html( $name ); ?></h3>
						<?php if ( $venue_name ) : ?>
							<div class="cohost-event-card__venue"><?php echo esc_html( $venue_name ); ?></div>
						<?php endif; ?>
						<?php if ( $summary ) : ?>
							<p class="cohost-event-card__summary"><?php echo esc_html( wp_trim_words( $summary, 24 ) ); ?></p>
						<?php endif; ?>
					</div>
				<?php if ( $href ) : ?></a><?php endif; ?>
			</li>
			<?php endforeach; ?>
		</ul>

		<?php
		$total_pages = 0;
		if ( isset( $response['pagination']['pageCount'] ) ) {
			$total_pages = intval( $response['pagination']['pageCount'] );
		} elseif ( isset( $response['totalPages'] ) ) {
			$total_pages = intval( $response['totalPages'] );
		} elseif ( isset( $response['total'], $response['limit'] ) && intval( $response['limit'] ) > 0 ) {
			$total_pages = (int) ceil( intval( $response['total'] ) / intval( $response['limit'] ) );
		}
		$has_more = isset( $response['hasMore'] ) ? (bool) $response['hasMore'] : ( $total_pages > $page );
		?>
		<?php if ( $page > 1 || $has_more ) : ?>
		<nav class="cohost-events-pagination">
			<?php if ( $page > 1 ) : ?>
				<a class="cohost-events-pagination__prev" href="<?php echo esc_url( add_query_arg( 'cohost_page', $page - 1 ) ); ?>">&larr; <?php esc_html_e( 'Previous', 'cohost' ); ?></a>
			<?php endif; ?>
			<?php if ( $has_more ) : ?>
				<a class="cohost-events-pagination__next" href="<?php echo esc_url( add_query_arg( 'cohost_page', $page + 1 ) ); ?>"><?php esc_html_e( 'Next', 'cohost' ); ?> &rarr;</a>
			<?php endif; ?>
		</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
