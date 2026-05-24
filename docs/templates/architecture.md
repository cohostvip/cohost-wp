# Architecture

How the Cohost WP template system actually works, end-to-end.

## High-level flow

```
              ┌────────────────────────────────────────────┐
              │  WP Admin → Cohost → Templates             │
              │  Cohost_Templates::render_page()           │
              └─────────────────┬──────────────────────────┘
                                │
                                ▼
              ┌────────────────────────────────────────────┐
              │  Cohost_Template_Library::all()            │
              │                                            │
              │  1. transient hit?  →  yes: use cached     │
              │  2. transient miss  →  fetch_remote()      │
              │       GET ${cohost_wp_templates_url}       │
              │       └ default: templates.cohost.vip/...  │
              │                                            │
              │  3. merge:  bundled  ⊎  remote             │
              │     (remote overrides bundled by `id`)     │
              └─────────────────┬──────────────────────────┘
                                │
                                ▼
              ┌────────────────────────────────────────────┐
              │  Gallery cards rendered                    │
              │  ─ preview image                           │
              │  ─ title + description + tags              │
              │  ─ Apply button (per card)                 │
              └─────────────────┬──────────────────────────┘
                                │  user clicks Apply
                                ▼
              ┌────────────────────────────────────────────┐
              │  POST admin-post.php                       │
              │  action=cohost_wp_apply_template           │
              │  Cohost_Templates::handle_apply()          │
              │                                            │
              │  • verify nonce + capability               │
              │  • look up template by id                  │
              │  • resolve target page (events vs event)   │
              │  • create page if not configured           │
              │  • else update post_content                │
              │  • Cohost_Rewrite::add_rewrite()           │
              │  • flush_rewrite_rules()                   │
              │  • redirect → success notice               │
              └────────────────────────────────────────────┘
```

## Components

### `class-cohost-template-library.php`

**Responsibility:** know how to obtain the canonical list of templates, regardless of network state.

Public surface:

- `Cohost_Template_Library::all()` — merged list (bundled ⊎ remote), remote takes precedence by id.
- `Cohost_Template_Library::get( $id )` — single template lookup.
- `Cohost_Template_Library::fallback_templates()` — bundled starter set (always available).
- `Cohost_Template_Library::clear_cache()` — flushes the manifest transient.
- `Cohost_Template_Library::DEFAULT_URL` — `https://templates.cohost.vip/wp/templates.json`.

Caching:

- One transient (`cohost_wp_templates_v1`), TTL `15 minutes`. Keyed once globally — the manifest doesn't vary per user/site.
- Cache is opaque: stores the post-normalization array, not the raw HTTP body.

Network failures:

- `wp_safe_remote_get()` with 8s timeout.
- Any non-2xx response, network error, or unparseable body → `fetch_remote()` returns `[]`. The bundled set is unaffected.
- The empty result still gets cached (prevents a hot-loop of failing requests every page load). Refresh button bypasses the transient explicitly.

Normalization:

- Each remote template entry is run through `normalize()` — drops anything missing required fields (`id`, `type ∈ {listing, profile}`, `content`).
- All string fields are sanitized (`sanitize_text_field`, `esc_url_raw`).
- `id` is sanitized via `sanitize_key` (lowercase, alphanumeric + hyphens/underscores only).

### `class-cohost-templates.php`

**Responsibility:** UI and user-driven actions.

Hooks:

- `admin_menu` (priority 11) — adds **Templates** submenu under the Cohost top-level menu.
- `admin_post_cohost_wp_apply_template` — handles the Apply form submission.
- `admin_post_cohost_wp_refresh_templates` — handles the manifest cache flush.

Apply algorithm:

```
1. Validate: nonce + manage_options capability.
2. Load template by id. 404-equivalent if missing.
3. Determine target page option:
     template.type == 'profile'  → 'cohost_wp_event_page_id'
     template.type == 'listing'  → 'cohost_wp_events_page_id'
4. If option is 0 (no page configured):
     a. wp_insert_post(post_type='page', post_status='publish',
                       post_title=__('Event'|'Events'),
                       post_content=template.content)
     b. Save the new post id to the option.
     c. Mark `created=true` for the success notice.
   Else:
     wp_update_post(ID=page_id, post_content=template.content, post_status=publish).
5. Cohost_Rewrite::add_rewrite(); flush_rewrite_rules();
   (page slug may have changed, e.g. when the page was just created.)
6. Redirect to ?cohost_notice=template_applied[_created].
```

Error path: any failure in steps 4–5 short-circuits to a `template_error` notice with the WP_Error message.

### `assets/css/templates.css`

Pure presentation, scoped under `.cohost-templates-grid` and `.cohost-template-card`. No theme-overridable hooks needed — this is admin chrome, not partner-customizable.

### `assets/img/templates/*.svg`

Schematic preview SVGs for the bundled templates. Each is 320×180 (16:9), brand-aligned (dark blocks for images, gray bars for text, orange `#f97316` square accents). Used as the `preview` URL for the corresponding bundled template entry.

Why SVG (not PNG screenshots)? The bundled previews are *schematic* — they communicate the layout shape, not the literal rendering. SVGs are tiny, brand-consistent, and never go out of date when fonts or block styles change. Remote (manifest-supplied) templates may use real PNG screenshots — that's appropriate when the template represents a specific finished design.

## Data shape

### Template object (in-memory)

```php
[
  'id'          => 'magazine-profile',
  'type'        => 'profile' | 'listing',
  'title'       => 'Magazine',
  'description' => 'Wide hero flyer, two-column body',
  'preview'     => 'https://templates.cohost.vip/wp/magazine-profile.png',
  'content'     => '<!-- wp:cover ... --><!-- wp:cohost/event-name ... -->...',
  'tags'        => ['editorial', 'two-column'],
  'source'      => 'remote' | 'bundled',
]
```

`source` is set by the library — partners and authors don't supply it.

### Manifest (remote JSON)

```json
{
  "version": 1,
  "templates": [
    {
      "id": "magazine-profile",
      "type": "profile",
      "title": "Magazine",
      "description": "Wide hero flyer, two-column body",
      "preview": "https://templates.cohost.vip/wp/magazine-profile.png",
      "content": "<!-- wp:cover ... --><!-- wp:cohost/event-name ... -->...",
      "tags": ["editorial", "two-column"]
    }
  ]
}
```

See [`template-spec.md`](./template-spec.md) for full validation rules.

## Security

- Apply is `manage_options`-gated and nonce-checked. Non-admins can't trigger it, even with the URL.
- Block markup is stored in `post_content` — WP's block parser handles it. Same trust model as any post content.
- Remote manifest is fetched with `wp_safe_remote_get` (respects `WP_HTTP_BLOCK_EXTERNAL`).
- Templates URL is configurable via the WP options table (admin-only). No way for non-admins to redirect the source.
- `<script>` and other dangerous tags inside `content` would only run if the rendered blocks output them. Our blocks don't; native blocks like Custom HTML do, but those are author-controlled at apply time anyway.

## Extensibility seams

If we ever need them:

- `apply_filters('cohost_wp_template_library_url', $url)` — partners override the manifest URL programmatically (today the admin UI suffices).
- `apply_filters('cohost_wp_template_library_all', $templates)` — partners filter/extend the merged list.
- `do_action('cohost_wp_template_applied', $template, $page_id, $created)` — fires after a successful apply, for analytics.

These are **not** registered yet — only add when a real use case appears.
