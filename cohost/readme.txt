=== Cohost ===
Contributors: cohost
Tags: events, ticketing, event management, calendar, cohost
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.0
Stable tag: 0.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Show your Cohost events on your own WordPress site — your branding, your domain, your audience.

== Description ==

Connect your WordPress site to your Cohost account and show upcoming events as a list, with click-through to per-event profile pages — natively in your theme. No iframes. No redirect to a third-party ticketing page.

Cohost is the headless platform for event managers — events, attendees, ticketing, scanning, check-in, and workflows — and this plugin embeds your event catalog into any WordPress theme.

**Authentication:** API token (Bearer). OAuth support is planned.

**Brand-aligned UI:** uses the canonical Cohost palette (dark `#161616`, light `#F2F2F2`, accent square `#f97316`) and Inter typography. Easy to override from your theme.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/ (or upload the zip via Plugins → Add New).
2. Activate it in WP Admin → Plugins.
3. Open Cohost in the admin menu (or Settings → Cohost).
4. Paste your Cohost API token and save.
5. Pick the WordPress page that will host the events list and add `[cohost_events]` to it.

== Shortcodes ==

`[cohost_events]` — events grid. All attributes are optional:

* `limit` — events per page (default 12)
* `columns` — grid columns 1–6 (default 3, display only)
* `from`, `to` — ISO date range (e.g. `from="2026-06-01" to="2026-12-31"`)
* `sort`, `order` — e.g. `sort="startDate" order="asc"`

Example: `[cohost_events from="2026-06-01" to="2026-12-31" limit="6" columns="2"]`

`[cohost_event id="…"]` — single event profile. **The `id` attribute accepts either an event ID or an event slug** — the Cohost API resolves both at the same endpoint, so `[cohost_event id="evt_abc123"]` and `[cohost_event id="summer-festival-2026"]` both work.

When a configured "Events page" exists, clicking a card navigates to `/{page-slug}/{event-id-or-slug}` and the same page renders the profile.

== Brand ==

The plugin ships the canonical Cohost dark "C" app icon and dark wordmark. The accent square is always `#f97316`. The full Cohost brand guide is published at https://cohost.vip/brand.

== Privacy & data ==

This plugin makes outbound HTTP requests to two Cohost-operated services:

1. **Cohost API** (default `https://api.cohost.vip/v1`) — fetches the events the site displays. Each request includes the Bearer API token configured in **Cohost → Settings**. No user data leaves your site; only your token is sent. The base URL is configurable for self-hosted or staging Cohost environments.

2. **Cohost templates manifest** (default `https://templates.cohost.vip/wp/templates.json`) — fetched when an admin opens the **Cohost → Templates** page. Returns a list of starter layouts the admin can apply. No user data is sent. The URL is configurable under Settings → Advanced.

The plugin does not:

* Track end-users or store cookies.
* Send page-view analytics, telemetry, or installation pings.
* Embed third-party scripts on the public-facing site.

The plugin caches API responses in WP transients (60-second TTL for events, 15-minute TTL for templates) to minimize outbound traffic. All cached data is removed when the plugin is uninstalled.

For Cohost's privacy policy covering data on the Cohost side, see https://cohost.vip/legal/privacy.

== Changelog ==

= 0.1.2 =
* Lower the minimum PHP requirement to 7.0 (was 7.4) so the plugin installs on older hosting; the two PHP 7.4 arrow functions were rewritten as closures.
* Add the WordPress.org plugin icon (replaces the auto-generated placeholder pattern).

= 0.1.1 =
* Fix privacy policy URL.
* Bump "Tested up to" to 7.0.
* Remove `?cohost_event=` query-string fallback for event profiles — profiles now route exclusively through the registered rewrite rule (`cohost_event_id` query var), eliminating the unauthenticated `$_GET` read flagged in review. Event profiles now require pretty permalinks; the settings page shows a warning if WordPress is on "Plain" permalinks and event cards render unlinked in that case.
* Replace `$_GET`-based admin notice flags with one-shot user-scoped transients (settings page + templates page). No `$_GET`/`$_POST` reads happen outside nonce-verified handlers.
* Escape rendered event content blocks via `wp_kses_post()` on the front-end profile template; drop the silencing `phpcs:ignore`.
* Escape the concatenated "View page" link in template-applied admin notices via `wp_kses()` with an explicit allowed-HTML allowlist.
* Prefix `uninstall.php` locals (`$cohost_wp_options`, `$cohost_wp_option`) so they don't trip Plugin Check's PrefixAllGlobals scanner.
* Annotate the two intentional bulk-transient-purge `$wpdb->query()` calls (API client + uninstall) with explicit `phpcs:ignore` and rationale.
* Suppress PrefixAllGlobals file-wide in the two included template files, where locals are template-scoped (not globals) but the scanner can't tell.

= 0.1.0 =
* Initial release.
* Events list + event profile shortcodes.
* Filters: `limit`, `columns`, `from`, `to`, `sort`, `order`.
* `[cohost_event]` accepts ID or slug.
* API token authentication.
* Pretty URLs via rewrite rule.
* Branded admin chrome and brand-aligned frontend styles.
