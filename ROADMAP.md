# Cohost WP — Roadmap

Future work, captured for later decision-making. Items are ordered by their best estimate of partner-impact-per-engineering-effort, not by commitment to ship. Strike through what's done, leave the rest as discussion fodder.

---

## ✅ Template library — plugin side built (v0.1)

Pre-composed block layouts for events list + event profile pages. Partners apply with one click. See **Cohost → Templates** in the WP admin.

Plugin v0.1 ships:
- 6 bundled starter templates (works offline)
- Remote manifest fetch from `https://templates.cohost.vip/wp/templates.json` (15-min transient cache)
- Apply flow that creates the target page if missing, replaces content if present, flushes rewrites
- Configurable manifest URL under Settings → Advanced (for staging/testing)

**Still to do:** stand up `templates.cohost.vip`. See [`docs/templates/website-launch-plan.md`](./docs/templates/website-launch-plan.md) — Phase 1 (manifest only) is ~1 day; Phase 2 (public gallery website) is ~3–5 days; Phase 3 (Sanity-backed editorial pipeline) post-launch.

Full design + spec + authoring guide: [`docs/templates/`](./docs/templates/).

---

## Onboarding automation (configure partner sites from the Cohost dashboard)

WordPress has a REST API and (since 5.6) ships Application Passwords for site-to-site auth, so most setup steps *can* be automated remotely. The real chokepoint is **plugin install** — managed hosts (WP Engine, Kinsta, GoDaddy, etc.) frequently lock down `/wp/v2/plugins` POST, so that step often falls back to the partner clicking "Install" once.

Three levels, smallest scope first:

### Level 1 — Setup link (lowest-friction, recommended first)

Cohost dashboard generates a deep link:

```
https://partner.com/wp-admin/admin.php?page=cohost-wp&cohost_setup=1&cohost_token={api_token}
```

The partner installs the plugin once (one-click from the WP plugin directory), clicks the setup link from Cohost (email / dashboard button). The plugin sees `?cohost_setup=1`, asks the logged-in admin to confirm, then:

- Saves the API token
- Creates the events + event WP pages with the shortcodes pre-inserted
- Picks both in settings
- Flushes rewrites

**Plugin work:** ~half a day. Add a small `Cohost_Setup` class that handles the query params, renders a confirmation screen, runs the setup actions on submit.

**Cohost dashboard work:** "Generate WordPress setup link" button on a partner's integration page.

**Tradeoff:** Partner still has to install the plugin. But everything *after* install is one click.

### Level 2 — Application Password handshake

Partner generates a one-time Application Password in their WP profile and pastes it into the Cohost dashboard. Cohost's backend hits:

- `GET /wp-json/wp/v2/pages` to discover existing pages
- `POST /wp-json/wp/v2/pages` to create the events + event pages
- `POST /wp-json/cohost/v1/connect` (a new endpoint we'd add to the plugin) to set the token + options atomically

**Plugin work:** ~1 day. New `Cohost_REST` class registering the `cohost/v1/connect` endpoint, authenticated by the token-or-Application-Password the caller supplies.

**Cohost dashboard work:** Application Password input form + backend orchestration.

**Tradeoff:** Partner has to generate an Application Password (~30 seconds, but a non-trivial extra step for non-technical users). Breaks on managed hosts that block REST-API writes for non-admin roles.

### Level 3 — Full remote install

Cohost POSTs to `/wp-json/wp/v2/plugins` to install + activate the plugin, then runs Level 2.

**Tradeoff:** Fails outright on hosts with disallowed file mods. Likely worth it only if Levels 1 and 2 prove insufficient at scale, or if we want a "click here to add to WordPress" flow comparable to Stripe's "Add to Shopify".

---

## Checkout

Currently the plugin has an `event-tickets` block that renders a disabled placeholder when no `checkoutUrl` is on the event. Three patterns to enable purchase on the partner site (in increasing engineering effort):

| Approach | What's on the page | PCI scope on partner | Effort |
|---|---|---|---|
| **A. Embedded Cohost checkout iframe** | An iframe pointing at e.g. `checkout.cohost.vip/{cartId}` (modal or inline) | None | **Low** — Cohost already commits to this for the Webflow integration; reuse |
| **B. Stripe Elements (native form)** | The partner's own checkout form, with Stripe-hosted card iframes inside | SAQ A (HTTPS only) | **Medium** — needs Cohost API to issue PaymentIntent client_secret + webhook to finalize the order |
| **C. Stripe Checkout (redirect)** | "Get tickets" button → `checkout.stripe.com/...` → success URL on partner site | None | **Lowest** — one POST + redirect |

**B** is the most "feels like the partner's own site" experience and is what was explicitly asked about. Prerequisite: Cohost commerce API needs to expose:

1. `POST /v1/cart` to create a cart with selected tickets
2. `POST /v1/checkout/payment-intent` returning Stripe `client_secret`
3. Webhook handler on Cohost side: `payment_intent.succeeded` → finalize order, create attendee
4. A Stripe **publishable** key the partner page can use (likely Cohost Connect platform key + organizer's connected account ID)

Until those exist, **A** is the realistic v1 if any checkout integration ships.

---

## Theme template overrides

Standard WP plugin pattern (WooCommerce, EDD): the plugin checks `wp-content/themes/{active-theme}/cohost-wp/event-profile.php` first and falls back to its bundled template if absent. Themes copy the bundled template, edit freely, get the plugin updates without losing customization.

**Status:** Less critical now that Gutenberg blocks (event-name, event-date, event-flyer, etc.) cover most "I want a different layout" requests via the block editor. Worth adding only if we hit a real "blocks aren't enough" use case from a partner.

**Plugin work:** ~10 lines of plumbing (helper that checks theme path before plugin path) + a "How to override" section in the README.

---

## Filter / action hooks for surgical customization

For per-section markup tweaks without forking templates: `apply_filters('cohost_wp_event_card_html', $html, $event)`, `do_action('cohost_wp_event_before_header', $event)`, etc. Same pattern WooCommerce uses.

**Status:** Cheap to add and future-proofs the plugin. Risk: every hook name shipped becomes part of the public API forever. Only ship the hook points we know users will actually need.

---

## OAuth 2.0 (re-introduce)

Removed in v0.1 to keep the surface small. The Authorization Code flow against `cohost.vip/oauth/authorize` was already implemented (commit history has the deleted `class-cohost-oauth.php`). Bringing it back means restoring that file plus the OAuth UI in the settings page.

**When:** when partners ask to manage multiple sites without a long-lived API token per site, or when Cohost wants to revoke access centrally without per-site coordination.

---

## Events as native WP posts (sync mode — explicitly rejected for now)

Register a `cohost_event` custom post type, sync events from Cohost via cron, write each one as a real WP post.

**Pros:** Yoast / Rank Math / native WP search / sitemap generators / menu builder all see events.

**Cons:** Sync drift (event edited in Cohost dashboard → WP copy goes stale), DB bloat (885 events = 885 posts), more code to maintain. The current virtual-rendering approach is cleaner and the API-cache transient gives ~real-time freshness.

**Decision so far:** Skip. Revisit only if a partner specifically needs WP-post-native features for events (e.g., a strict SEO use case where Yoast scoring is non-negotiable).

---

## Misc

- **Per-event override block.** "Pin this featured event" homepage block with an explicit Event ID — the per-block `eventId` attribute on the existing blocks already supports this; just need a tiny picker UI in the inspector.
- **Sales-channel scoping.** Today the API token determines which channel's events appear. Could expose a `channel="..."` shortcode/block attr if partners need multiple channels on one site.
- **i18n.** Plugin uses `__()` everywhere but the `.pot` file isn't generated. Add a build step or skip until non-English partners ask.
- **Caching dial.** 60s transient TTL is hard-coded. Make it a setting if real partners need shorter/longer cache windows.
