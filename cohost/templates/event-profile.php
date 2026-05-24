<?php
/**
 * Single event profile template.
 *
 * Vars: $event (array), $blocks (array of content blocks)
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

$name        = Cohost_Shortcodes::field( $event, array( 'name', 'title' ), __( 'Untitled event', 'cohost' ) );
$start       = Cohost_Shortcodes::field( $event, array( 'start', 'startDate', 'startsAt', 'startAt' ) );
$end         = Cohost_Shortcodes::field( $event, array( 'end', 'endDate', 'endsAt', 'endAt' ) );
$tz          = Cohost_Shortcodes::field( $event, array( 'tz', 'timezone', 'location.timezone' ) );
$venue_name  = Cohost_Shortcodes::field( $event, array( 'venue.name', 'location.name' ) );
$venue_addr  = Cohost_Shortcodes::field( $event, array( 'venue.address.formattedAddress', 'location.address.formattedAddress', 'venue.fullAddress', 'location.fullAddress' ) );
if ( is_array( $venue_addr ) ) {
	// Fall back to building an address from parts.
	$parts = array_filter( array(
		Cohost_Shortcodes::field( $event, array( 'location.address.address_1', 'venue.address.address_1' ) ),
		Cohost_Shortcodes::field( $event, array( 'location.address.city', 'venue.address.city' ) ),
		Cohost_Shortcodes::field( $event, array( 'location.address.region', 'venue.address.region' ) ),
	) );
	$venue_addr = implode( ', ', $parts );
}
$image       = Cohost_Shortcodes::field( $event, array( 'flyer.url', 'coverImage.url', 'image.url', 'imageUrl', 'cover.url', 'coverImage', 'image', 'cover' ) );
if ( is_array( $image ) ) {
	$image = '';
}
$summary     = Cohost_Shortcodes::field( $event, array( 'summary', 'shortDescription', 'subtitle' ) );
$ticket_url  = Cohost_Shortcodes::field( $event, array( 'checkoutUrl', 'ticketUrl' ) );
$page_id     = intval( get_option( 'cohost_wp_events_page_id', 0 ) );
$back_url    = $page_id ? get_permalink( $page_id ) : '';
?>
<article class="cohost-event">
	<?php if ( $back_url ) : ?>
		<p class="cohost-event__back"><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'All events', 'cohost' ); ?></a></p>
	<?php endif; ?>

	<?php if ( $image ) : ?>
		<div class="cohost-event__hero" style="background-image:url('<?php echo esc_url( $image ); ?>');"></div>
	<?php endif; ?>

	<header class="cohost-event__header">
		<h1 class="cohost-event__title"><?php echo esc_html( $name ); ?></h1>
		<?php if ( $summary ) : ?>
			<p class="cohost-event__summary"><?php echo esc_html( $summary ); ?></p>
		<?php endif; ?>
	</header>

	<dl class="cohost-event__meta">
		<?php if ( $start ) : ?>
			<dt><?php esc_html_e( 'Starts', 'cohost' ); ?></dt>
			<dd><?php echo esc_html( Cohost_Shortcodes::format_date( $start, $tz ) ); ?></dd>
		<?php endif; ?>
		<?php if ( $end ) : ?>
			<dt><?php esc_html_e( 'Ends', 'cohost' ); ?></dt>
			<dd><?php echo esc_html( Cohost_Shortcodes::format_date( $end, $tz ) ); ?></dd>
		<?php endif; ?>
		<?php if ( $venue_name || $venue_addr ) : ?>
			<dt><?php esc_html_e( 'Venue', 'cohost' ); ?></dt>
			<dd>
				<?php if ( $venue_name ) : ?><strong><?php echo esc_html( $venue_name ); ?></strong><?php endif; ?>
				<?php if ( $venue_addr ) : ?><br/><?php echo esc_html( $venue_addr ); ?><?php endif; ?>
			</dd>
		<?php endif; ?>
	</dl>

	<?php if ( ! empty( $blocks ) ) : ?>
		<div class="cohost-event__blocks">
			<?php foreach ( $blocks as $block ) : ?>
				<?php echo wp_kses_post( Cohost_Shortcodes::render_block( $block ) ); ?>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $ticket_url ) : ?>
		<p class="cohost-event__cta"><a class="cohost-button" href="<?php echo esc_url( $ticket_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get tickets', 'cohost' ); ?></a></p>
	<?php endif; ?>
</article>
