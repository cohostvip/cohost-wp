# Template spec — manifest schema and conventions

The plugin reads templates from a JSON manifest. This document is the canonical spec for the manifest format.

## Manifest URL

Default: `https://templates.cohost.vip/wp/templates.json`

Override per WP install via **Cohost → Settings → Advanced → Templates manifest URL**.

## Top-level shape

```json
{
  "version": 1,
  "templates": [
    { ... template ... },
    { ... template ... }
  ]
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `version` | int | yes | Manifest format version. Current: `1`. Reserved for future breaking changes — the plugin ignores manifests with a higher version than it knows. |
| `templates` | array | yes | Ordered list of templates. Order is honored in the gallery UI within each `type` group. |

## Template object

```json
{
  "id": "magazine-profile",
  "type": "profile",
  "title": "Magazine",
  "description": "Wide hero flyer, two-column body — name + date + venue on the left, summary + content on the right.",
  "preview": "https://templates.cohost.vip/wp/magazine-profile.png",
  "content": "<!-- wp:cohost/event-flyer {\"size\":\"full\",\"aspect\":\"16/9\"} /-->...",
  "tags": ["editorial", "two-column"]
}
```

| Field | Type | Required | Validation | Notes |
|---|---|---|---|---|
| `id` | string | yes | `sanitize_key` — lowercase, `[a-z0-9_-]` only | Globally unique. **An `id` is forever** — once a partner has applied a template, the id lives in their page's history. Don't rename. New layout = new id. |
| `type` | enum | yes | `"profile"` or `"listing"` | Determines which page (event profile vs events list) the Apply button targets. |
| `title` | string | yes | `sanitize_text_field` | Shown as the card heading in the gallery. Aim for ≤24 chars. |
| `description` | string | no | `sanitize_text_field` | One sentence shown below the title. ≤140 chars. |
| `preview` | URL | no | `esc_url_raw` | 16:9 image URL. Should be ≥640×360 for retina. PNG/JPG/WebP/SVG all fine. |
| `content` | string | yes | (none — passed through to `post_content`) | Valid WP block markup. See "Content rules" below. |
| `tags` | array&lt;string&gt; | no | each `sanitize_text_field` | Free-form labels. Not surfaced in v1 UI — reserved for future filtering. |

Templates that fail validation are silently dropped — they don't appear in the gallery, don't error the page. The plugin logs nothing about it (admin-side debug only).

## Content rules

`content` is **block markup**, not raw HTML. It must:

1. **Parse cleanly** with `parse_blocks()`. The plugin doesn't validate this server-side, but malformed markup produces broken pages — test in a real WP editor before submitting.
2. **Use only blocks the partner is guaranteed to have available**:
   - All native WP core blocks (Paragraph, Heading, Image, Cover, Columns, Group, Buttons, Spacer, Separator, etc.).
   - Cohost's own blocks: `cohost/event-name`, `cohost/event-date`, `cohost/event-flyer`, `cohost/event-venue`, `cohost/event-summary`, `cohost/event-content`, `cohost/event-tickets`.
   - The legacy `core/shortcode` block wrapping `[cohost_events]` or `[cohost_event]`. Avoid for new templates — prefer the dedicated `cohost/*` blocks.
3. **Not depend on third-party blocks** — a template that uses an Astra/Kadence-only block would break for everyone else. Native + cohost only.
4. **Not inline styles or fonts** that compete with the partner's theme. Themes own typography.

### Block-attribute conventions

When using `cohost/*` blocks in templates, prefer attribute values that match the spirit of the layout, not the partner's brand. Example:

```
✅  <!-- wp:cohost/event-flyer {"size":"full","aspect":"16/9"} /-->
❌  <!-- wp:cohost/event-flyer {"size":"large","width":"880px","aspect":"16/9"} /-->
```

The partner can tweak after applying. Don't pin defaults that lock them out of small adjustments.

### Markup style guide

- Two-space indentation OR no indentation — JSON-encoded markup is fine either way.
- Inner block markup follows the WP serialization format exactly (`<!-- wp:name --> ... <!-- /wp:name -->`). Mismatched comments break the editor.
- Self-closing form (`<!-- wp:cohost/event-name /-->`) is preferred for blocks with no inner content.

## Versioning

- The plugin's library reads `version`. Today only `1` is recognized.
- A breaking change (e.g. adding a required field, changing how `content` is stored) bumps the version. The plugin will then ignore manifests with the higher version and continue using bundled fallbacks until updated.
- **Non-breaking additions** (new optional fields like `tags`, new template `type` values) don't require a version bump — the plugin tolerates unknown fields and skips templates with unknown `type`.

## Bundled fallback set

The plugin ships 6 templates with the same schema, defined in `Cohost_Template_Library::fallback_templates()`. They:

- Are merged with the remote list, with remote winning on `id` collision.
- Have `source: "bundled"` (set automatically; not author-supplied).
- Have schematic SVG previews under `cohost-wp/assets/img/templates/<id>.svg`.

To replace a bundled template with a "better" remote version, just publish a manifest entry with the same `id`.

## Examples

Minimum valid template (listing):

```json
{
  "id": "starter",
  "type": "listing",
  "title": "Starter",
  "content": "<!-- wp:shortcode -->[cohost_events]<!-- /wp:shortcode -->"
}
```

Full template (profile):

```json
{
  "id": "magazine-profile",
  "type": "profile",
  "title": "Magazine",
  "description": "Wide hero flyer, two-column body — name + date + venue on the left, summary + content on the right.",
  "preview": "https://templates.cohost.vip/wp/magazine-profile.png",
  "tags": ["editorial", "two-column"],
  "content": "<!-- wp:cohost/event-flyer {\"size\":\"full\",\"aspect\":\"16/9\"} /--><!-- wp:columns {\"verticalAlignment\":\"top\"} --><div class=\"wp-block-columns are-vertically-aligned-top\"><!-- wp:column {\"width\":\"33%\"} --><div class=\"wp-block-column\" style=\"flex-basis:33%\"><!-- wp:cohost/event-name {\"level\":1} /--><!-- wp:cohost/event-date {\"display\":\"compact\",\"format\":\"datetime\"} /--><!-- wp:cohost/event-venue {\"display\":\"name+address\"} /--><!-- wp:cohost/event-tickets {\"label\":\"Get tickets\"} /--></div><!-- /wp:column --><!-- wp:column {\"width\":\"67%\"} --><div class=\"wp-block-column\" style=\"flex-basis:67%\"><!-- wp:cohost/event-summary /--><!-- wp:cohost/event-content /--></div><!-- /wp:column --></div><!-- /wp:columns -->"
}
```
