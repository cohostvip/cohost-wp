# `templates.cohost.vip` launch plan

Plan for standing up the templates website that the Cohost WP plugin reads from. **Status: not built — this doc is the plan.**

The plugin already works without it (bundled fallback set covers the gallery). Standing up `templates.cohost.vip` unlocks server-side iteration on the catalog and adds a public-facing template gallery website that doubles as marketing.

## What "the templates website" actually is

Two products at the same domain:

1. **Manifest endpoint** — a JSON file the WP plugin polls (`/wp/templates.json`). Critical path. Must always return valid JSON.
2. **Public gallery website** — a human-facing page showing every template with previews, descriptions, and "Install Cohost WP" CTAs. Marketing surface. Doesn't have to be online for plugins to work.

These can ship independently. **Phase 1 = manifest only.** Phase 2 = gallery website.

## Hosting choice

**Recommendation: Google Cloud Storage bucket + Cloud CDN, served from `templates.cohost.vip`.**

| Option | Pros | Cons |
|---|---|---|
| **GCS + Cloud CDN** | Free-tier-friendly. Same infra family as the rest of Cohost (api.cohost.vip is Cloud Run). Static — nothing to maintain. | Need to set up bucket-as-website + Cloud CDN in front for the custom domain + SSL. ~half-day infra task. |
| Cloudflare Pages | Free, fast, easy custom domain | Adds another vendor to the stack |
| GitHub Pages | Free, version control built-in | Doesn't fit Cohost's existing infra; rate-limited; no custom analytics |
| Cloud Run static server | Same family as api.cohost.vip | Overkill for static content; cost > benefit |

GCS is the right call. Cohost already deploys to GCP. The bucket for branding assets (per `cohost-branding/BRAND.md`: `https://storage.googleapis.com/cohost-static/branding/`) sets the precedent.

## URL structure

```
https://templates.cohost.vip/                       ← public gallery (Phase 2)
https://templates.cohost.vip/wp/                    ← WP-plugin-specific subtree
https://templates.cohost.vip/wp/templates.json      ← manifest the plugin fetches
https://templates.cohost.vip/wp/<id>.png            ← preview images per template
https://templates.cohost.vip/wp/<id>.html           ← optional: live preview pages
```

Reserving `/wp/` allows for future `/webflow/`, `/nextjs/`, `/zapier/` etc. parallel manifests when the same template-library pattern extends to other Cohost integrations.

## Phasing

### Phase 0 — already done

- Plugin reads `cohost_wp_templates_url` (configurable, defaults to `https://templates.cohost.vip/wp/templates.json`).
- Plugin gracefully handles 404 / 5xx / network failure (falls back to bundled set).
- Plugin caches the manifest 15 minutes; refresh button bypasses cache.
- Bundled starter set ships with the plugin so a new install never sees an empty gallery.

**Result:** the plugin is launchable today. Partners install, see 6 starters, can apply any of them, get a working site.

### Phase 1 — Minimum viable manifest (target: ~1 day)

**Goal:** stand up `templates.cohost.vip/wp/templates.json` with a working manifest. No public website yet.

Tasks:

1. Provision GCS bucket `cohost-templates-prod` (or reuse `cohost-static`).
2. Set up bucket-as-website with `index.html` redirecting to `/wp/templates.json` for now.
3. Set up Cloud CDN + custom domain `templates.cohost.vip` + managed SSL cert.
4. Create initial `wp/templates.json` with the same 6 starters as the plugin's bundled set, so behavior is identical. (Yes, redundant — but it's a smoke test that the fetch path works.)
5. Add 2–3 *new* templates not in the bundled set, to verify the merge logic.
6. Author the new template previews (PNG screenshots from a real WP install). Upload to `wp/<id>.png`.
7. Test from a real WP install. Verify gallery shows 8–9 templates, the new ones load with PNG previews, the bundled ones still render with their schematic SVGs.

Delivery checklist:

- [ ] HTTPS works on `templates.cohost.vip`
- [ ] `templates.cohost.vip/wp/templates.json` returns 200 + valid JSON
- [ ] Cache headers: `Cache-Control: public, max-age=900` (matches plugin transient TTL) — even if the plugin caches client-side, the CDN benefits from the same TTL.
- [ ] CORS: `Access-Control-Allow-Origin: *` (the plugin's `wp_safe_remote_get` is server-to-server, so CORS is moot for it; but the public gallery's JS consumer in Phase 2 will need it).
- [ ] At least 8 templates total (6 starters + 2–3 net-new).

### Phase 2 — Public gallery website (target: ~3–5 days)

**Goal:** human-facing gallery at `templates.cohost.vip/` for marketing and discoverability.

Architecture: static site generator (Astro / Next static export / 11ty — anyone of those is fine) that:

1. Reads the same `wp/templates.json` as its source of truth.
2. Generates one card per template (preview + title + description + tags).
3. Filterable by `type` (Listing / Profile) and by tag.
4. Each card links to a detail page `/<id>` with: bigger preview, copy-able block markup, a "Get this template" CTA that links to "Install Cohost WP" (or the live demo).
5. Top-level CTA: "Install Cohost WP plugin" → links to the WP.org plugin directory listing (when published) or to a download page.

Build pipeline: GitHub Actions on push → SSG build → upload to GCS bucket → Cloud CDN cache invalidation. Same pattern Cohost docs / cohost-content already use (per the repo structure).

Why static: zero ongoing infra cost, infinite scale, fast page loads. Templates change rarely (≤weekly) so build-on-push is fine.

Delivery checklist:

- [ ] Gallery home page renders all templates from `templates.json`
- [ ] Filter by type works
- [ ] Each template has a detail page with copy-able block markup
- [ ] "Install plugin" CTA points somewhere real (or a "coming soon" mailing list)
- [ ] OG image / Twitter card on every page (uses the template's preview image)
- [ ] Cohost-branded header / footer per `BRAND.md`
- [ ] Lighthouse score 95+ on all pages

### Phase 3 — Editorial pipeline (target: ongoing)

**Goal:** make it easy for the Cohost team to add templates without engineering involvement.

Options (pick one):

**A. PR-driven** — `templates.json` lives in a Git repo. Designers/PMs file PRs against it. CI auto-deploys on merge. Pros: version control, review process. Cons: requires Git fluency for everyone authoring.

**B. CMS-backed** — manifest is generated from a Sanity / Contentful / Notion source on each deploy. Pros: WYSIWYG-friendly. Cons: extra moving piece; CMS schema needs to mirror the spec.

**C. Sanity** — Cohost already uses Sanity for content (per the MCP server config). Add a "WP Templates" schema. The deploy pipeline reads from Sanity, generates `templates.json`, deploys.

**Recommendation: C.** It reuses existing tooling, and the editorial team already has Sanity access.

Sanity schema sketch:

```js
// schemas/wp-template.js
export default {
  name: 'wpTemplate',
  type: 'document',
  fields: [
    { name: 'id',          type: 'string',  validation: r => r.required() },
    { name: 'type',        type: 'string',  options: { list: ['listing', 'profile'] } },
    { name: 'title',       type: 'string',  validation: r => r.required() },
    { name: 'description', type: 'text' },
    { name: 'preview',     type: 'image' },
    { name: 'content',     type: 'text',    description: 'WP block markup' },
    { name: 'tags',        type: 'array',   of: [{ type: 'string' }] },
    { name: 'isDraft',     type: 'boolean', initialValue: false }, // unpublished templates skip the manifest
    { name: 'order',       type: 'number',  description: 'Display order within type' },
  ],
}
```

A scheduled Cloud Function (or build trigger) queries `*[_type == "wpTemplate" && !isDraft] | order(order asc)`, transforms each into the manifest schema, writes to GCS.

### Phase 4 — Analytics + iteration (target: post-launch)

Once the catalog has 15+ templates and partner volume justifies it:

- Add `?utm_source=cohost-wp&utm_medium=plugin&template=<id>` to "View page" links from the plugin gallery, so we can see which templates partners actually use.
- Server-side: count how often each `<id>.png` is requested (proxy for gallery views).
- Plugin-side telemetry (opt-in): which templates were applied. Non-PII. Useful for retiring underperformers.

## Open questions

- **Domain ownership** — `cohost.vip` is Cohost's. Standing up `templates.cohost.vip` is a DNS task (Cloud DNS or whatever Cohost uses). Need eng + DNS access — call out as a blocker if neither exists.
- **Who owns ongoing template authoring** — engineering? marketing? a designer? Pick one before Phase 3 lands or templates will stagnate.
- **Should bundled fallbacks ever be removed?** — once the remote manifest is reliable, the 6 bundled starters could be slimmed to 1–2. But they double as offline-mode + first-install UX. Probably keep.
- **WP.org plugin directory listing** — separate workstream. The templates website is more useful once the plugin is one click to install. Loose dependency.

## Cost estimate (back-of-envelope)

- GCS storage: ~10MB total (manifest + ~30 preview PNGs at ~300KB each). Free tier covers it.
- Cloud CDN: 1GB egress free tier; expect << 1GB/month even at 10k partner installs polling every 15 min.
- Custom domain SSL: free (managed cert).
- Build pipeline: GitHub Actions free tier covers it.
- **Net: $0/month at expected scale.**
