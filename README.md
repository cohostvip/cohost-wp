<p align="left">
  <img src="cohost/assets/img/icon.svg" alt="Cohost" width="72" height="72" />
</p>

# Cohost WordPress Plugin

> **Show your Cohost events on your own WordPress site — your branding, your domain, your audience.** No iframes. No redirect to a third-party ticketing page. The list and per-event profile render natively in your theme.

The plugin source lives in [`./cohost`](./cohost). Zip that directory to install it on a WordPress site.

## About Cohost

Cohost is a headless platform for event managers — it handles events, attendees, ticketing, scanning, check-in, and workflows so the storefront (WordPress, Webflow, your Next.js site, etc.) can stay yours. This plugin is the WordPress storefront.

The brand mark is the lowercase **"c"** with the orange square dot at the baseline right. The square is always `#f97316` and shows up across the plugin (button accent, card hover, logo). Full brand guide: [`BRAND.md`](docs/BRAND.md).

## Features

- **API-token authentication** — paste a personal access token from your Cohost dashboard. (OAuth support is planned.)
- **`[cohost_events]`** shortcode — responsive grid of upcoming events (cover image, date, venue, summary).
- **`[cohost_event id="…"]`** shortcode — full event profile (hero image, meta, description, ticket CTA).
- **Pretty URLs** — pick an "Events page" in settings; cards link to `/{events-page-slug}/{event-id-or-slug}`. The same page renders list and detail via a rewrite rule. Falls back to `?cohost_event=ID` when permalinks are off.
- **Brand-aligned styling** — Cohost dark `#161616`, light `#F2F2F2`, accent square `#f97316`, Inter typography. Override anything via theme CSS.
- **Caching** — API responses cached 60s via WP transients, with a manual flush button in settings.
- **Admin chrome** — branded settings header, top-level menu icon, plugin action link.

## Installation

1. From the repo root, zip the plugin directory:
   ```sh
   cd oss/cohost-wp
   npm run zip
   ```
   (Or do it manually: `cd oss/cohost-wp && zip -r cohost.zip cohost`.)
2. In WP Admin, go to **Plugins → Add New → Upload Plugin** and upload `cohost.zip`.
3. Activate.
4. Open **Cohost** in the admin menu (or **Settings → Cohost**):
   - Paste an API token and save.
   - Pick a page to host the events list and add `[cohost_events]` to it.

## Local development

Reproducible across machines via [`@wordpress/env`](https://www.npmjs.com/package/@wordpress/env). All you need is **Docker** + **Node 18+** — no PHP, no MAMP, no MySQL on the host. WordPress and PHP run inside containers managed entirely by `wp-env`. Nothing is added to the root monorepo's docker-compose; this folder is fully isolated.

WordPress is pinned to **6.5.5** and PHP to **8.2** in [`.wp-env.json`](./.wp-env.json), so every dev gets the same environment.

```sh
cd oss/cohost-wp

# one-time
npm install

# boot WordPress at http://localhost:8888 (admin: admin / password)
npm start

# tail logs
npm run logs

# run wp-cli inside the container
npm run cli -- plugin list
npm run cli -- option get cohost_wp_api_token

# flush rewrites after changing the Events page
npm run flush

# stop / restart / wipe everything
npm run stop
npm run restart
npm run clean      # resets the WP DB but keeps the container
npm run destroy    # removes containers + volumes entirely

# lint plugin PHP (runs in a throwaway php:8.2-cli container, nothing installed on host)
npm run lint:php

# zip the plugin folder for distribution
npm run zip
```

The plugin source at `./cohost` is **bind-mounted** into the container at `wp-content/plugins/cohost/`, so edits are reflected on refresh — no rebuild loop.

### Logging in

| | |
|---|---|
| URL | http://localhost:8888 |
| Admin URL | http://localhost:8888/wp-admin |
| Username | `admin` |
| Password | `password` |

### Smoke-testing the plugin

After `npm start`:

1. Log in to wp-admin.
2. Activate **Cohost** under Plugins (it's already mounted).
3. Go to **Cohost → Settings**:
   - Paste a Cohost API token, save.
   - Click **Clear API cache** to confirm admin actions work.
4. Create a page (e.g. *Events*), add `[cohost_events]`, publish.
5. Set that page as the **Events page** in the Cohost settings, save.
6. Run `npm run flush` so the rewrite rule for `/events/{id}` registers.
7. Visit the page — you should see the grid pulled from `https://api.cohost.vip/v1/events`. Click a card → loads the profile on the same page via the rewrite.

### Pointing at a non-prod Cohost API

Override the base URL in **Settings → Cohost** (e.g. `http://host.docker.internal:3000` to hit a Cohost API running on your host from inside the WP container).

### What's installed where

| Path | What it is |
|---|---|
| `oss/cohost-wp/cohost/` | the WordPress plugin (the only thing that ships) |
| `oss/cohost-wp/.wp-env.json` | pinned WP + PHP versions, port config, plugin mount |
| `oss/cohost-wp/.dev/htaccess` | mounted as the WP root `.htaccess` so pretty permalinks work |
| `oss/cohost-wp/package.json` | dev-only scripts; **not** part of `pnpm-workspace.yaml` |
| `oss/cohost-wp/node_modules/` | local to this folder, gitignored |

## Configuration

| Setting | Description |
|---|---|
| API token | Personal access token from your Cohost dashboard. Sent as `Bearer <token>` on every API call. |
| API base URL | Defaults to `https://api.cohost.vip/v1`. Override only for non-production environments (the path includes the API version, so any override should also include the `/v1` segment or whatever version your environment uses). |
| Events page | The WP page that displays the list. Used to build click-through URLs and the rewrite rule. |

## Shortcodes

### `[cohost_events]` — events list

All attributes are optional. Filter attributes (`from`, `to`, `sort`, `order`) are forwarded to `GET /events` as query params; `columns` is display-only.

| Attribute | Description | Example |
|---|---|---|
| `limit` | Events per page (default `12`) | `limit="6"` |
| `columns` | Grid columns 1–6 (default `3`) — **display only** | `columns="2"` |
| `from` / `to` | ISO date range | `from="2026-06-01" to="2026-12-31"` |
| `sort` / `order` | Sort field + direction | `sort="startDate" order="asc"` |

Examples:

```text
[cohost_events]
[cohost_events limit="6" columns="2"]
[cohost_events from="2026-06-01" to="2026-12-31" sort="startDate" order="asc"]
```

Pagination is automatic: if the API response indicates more pages, **Previous** / **Next** links use the `?cohost_page=N` query var on the same WP page.

### `[cohost_event id="…"]` — single event profile

The `id` attribute accepts **either an event ID or an event slug** — the Cohost API resolves both at `/events/{idOrSlug}`, so the plugin does not need to know which one you passed. Slugs make for cleaner URLs and match what the rewrite rule generates for click-throughs.

```text
[cohost_event id="evt_abc123"]            # by ID
[cohost_event id="summer-festival-2026"]  # by slug
```

If you've configured an "Events page" in settings, the plugin already does this for you — clicks on cards from `[cohost_events]` use the slug (when present, falling back to the ID), and the events page renders the profile inline via the rewrite rule.

## Templates

The plugin ships with a built-in template library — pre-composed layouts for the events list page and event profile page that partners can apply with one click. See **Cohost → Templates** in the WP admin.

The template system has its own design + planning docs under [`docs/templates/`](./docs/templates/):

- [`README.md`](./docs/templates/README.md) — navigation and decision log
- [`architecture.md`](./docs/templates/architecture.md) — how it works end-to-end
- [`template-spec.md`](./docs/templates/template-spec.md) — JSON schema
- [`authoring-guide.md`](./docs/templates/authoring-guide.md) — adding a new template
- [`website-launch-plan.md`](./docs/templates/website-launch-plan.md) — plan for `templates.cohost.vip`
- [`template-ideas.md`](./docs/templates/template-ideas.md) — backlog of templates to ship

## Brand assets

The plugin ships the dark "C" app icon and dark wordmark from [cohost-branding](../cohost-branding):

- `cohost/assets/img/icon.svg` / `icon.png` — app icon, dark background `#161616`, light "c", orange `#f97316` square. Used as the admin header logo.
- `cohost/assets/img/wordmark.svg` — full "cohost" wordmark, dark variant.

Per the brand guide:

- The accent orange is **always `#f97316`** (the deprecated `#EB563B` should never be used).
- The lettermark must be at least 16px tall.
- Dark vs. light variants are picked based on background — never dark-on-dark or light-on-light.

## Architecture

```
cohost.php                          # plugin header, defines, hooks
includes/
  class-cohost-plugin.php           # singleton, wires modules
  class-cohost-api-client.php       # HTTP client, bearer-token auth, transient cache
  class-cohost-settings.php         # admin menu, settings page, branded header
  class-cohost-shortcodes.php       # [cohost_events], [cohost_event]
  class-cohost-rewrite.php          # /events-page/{id} pretty URLs
templates/
  events-list.php
  event-profile.php
assets/
  css/cohost.css                    # brand-aligned frontend styles
  css/admin.css                     # settings page styles
  img/icon.svg / icon.png           # app icon (dark variant)
  img/wordmark.svg                  # wordmark (dark variant)
```

## License

MIT
