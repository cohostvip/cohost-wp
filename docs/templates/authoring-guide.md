# Authoring guide — designing a new template

Practical, end-to-end. Read [`template-spec.md`](./template-spec.md) first if you haven't.

## Workflow at a glance

1. Pick a use case (e.g. "event profile that highlights the artist lineup").
2. Sketch the layout on paper or Figma. Decide which Cohost blocks fit and where the partner's editable areas are.
3. Build it in a real WP block editor — the wp-env install in this repo is fine.
4. Copy the block markup out of the editor.
5. Take a 16:9 preview screenshot.
6. Add the entry to `templates.json` (or to `fallback_templates()` if it's a starter).
7. Test the apply flow.
8. Open a PR.

## Step-by-step

### 1. Pick a use case

Templates are valuable when they save partners *meaningful* time. A 5-block layout they can compose in 30 seconds is a poor template; a multi-column, multi-section layout with sensible defaults is a great one.

Good template ideas usually answer: "What's a recurring layout pattern in event websites that requires arranging 6+ blocks correctly?"

Examples:
- ✅ "Concert poster" — flyer hero + giant date + ticket CTA + small description
- ✅ "Two-column with sticky tickets" — content scrolls, ticket button stays visible
- ✅ "Festival lineup" — flyer + artist grid + day-by-day schedule
- ❌ "Just the events grid" — that's already the default; not template-worthy
- ❌ "Centered text" — too generic, partners can do this in 5 seconds

### 2. Sketch + decide block usage

For each region of the layout, decide:

- Is this a **Cohost block** (data-driven, e.g. `cohost/event-name` pulling the event's name)?
- Or a **native block** the partner authors directly (e.g. a Paragraph for marketing copy)?

Templates work best when the data-driven blocks dominate — partners don't have to fill in event-specific text. Use native blocks for *structural* elements (Cover, Columns, Group, Spacer) and *placeholder* elements partners will customize (a Paragraph saying "Add your venue note here").

### 3. Build in the WP editor

From the repo root:

```sh
cd oss/cohost-wp
npm start                    # boots WP at http://localhost:8888
```

Log in (`admin` / `password`), open the Event page (or any Page), build your layout in the block editor. Use the actual Cohost blocks from the inserter under **Cohost** category.

For Cohost blocks, set attributes via the right sidebar — `cohost/event-name` has a heading-level dropdown, `cohost/event-date` has display + format options, `cohost/event-flyer` has size + aspect + alignment, etc.

When the layout looks right in the editor, **switch to the Code editor** (`⋮` menu → "Code editor", or `Ctrl/⌘+Shift+M`) to see the raw block markup.

### 4. Copy the markup

Select all and copy. You'll get something like:

```
<!-- wp:cohost/event-flyer {"size":"full","aspect":"16/9"} /-->

<!-- wp:columns {"verticalAlignment":"top"} -->
<div class="wp-block-columns are-vertically-aligned-top">
<!-- wp:column {"width":"33%"} -->
<div class="wp-block-column" style="flex-basis:33%">
<!-- wp:cohost/event-name {"level":1} /-->
<!-- wp:cohost/event-date {"display":"compact","format":"datetime"} /-->
...
```

Strip blank lines and condense. The manifest stores this as a single `content` string (literal newlines work, but flattening is cleaner).

### 5. Preview screenshot

Take a screenshot at 1280×720 (or 640×360 minimum) of the **rendered page**, not the editor. The preview should communicate the layout shape at a glance.

Save as PNG, JPG, or WebP. Tools that work well:

- macOS Screenshot (`⌘⇧4` then space, click the browser window) — usually fine
- Figma export at 16:9 — best when you want a stylized preview without real event data
- For bundled starters: hand-draw an SVG schematic (see `cohost-wp/assets/img/templates/listing-simple-grid.svg` for the convention)

Upload the image to wherever the templates website hosts assets (see [`website-launch-plan.md`](./website-launch-plan.md)).

### 6. Add to manifest

Add an entry to `templates.json`:

```json
{
  "id": "festival-lineup",
  "type": "profile",
  "title": "Festival lineup",
  "description": "Hero flyer, artist grid, day-by-day schedule. Good for multi-day or multi-artist events.",
  "preview": "https://templates.cohost.vip/wp/festival-lineup.png",
  "tags": ["multi-day", "music"],
  "content": "<!-- wp:cohost/event-flyer ... -->..."
}
```

For a **bundled starter** (ships inside the plugin), add an entry to the `fallback_templates()` array in `class-cohost-template-library.php` instead. Use the same schema; the plugin sets `source: "bundled"` automatically.

### 7. Test the apply flow

1. From the WP admin, go to **Cohost → Templates**.
2. Click **Refresh from server** if you're testing a remote-manifest change.
3. Find your new template card. Click **Apply**.
4. Confirm the dialog.
5. The plugin redirects to a success notice. Click **View page** in the notice.
6. Verify the front-end render matches what you designed in step 3.

If the page looks broken:

- Check `parse_blocks()` accepts the markup: `wp eval 'var_dump(parse_blocks(get_post(13)->post_content));'`
- Look for unmatched block comments (`<!-- wp:foo -->` without the corresponding `<!-- /wp:foo -->`)
- Check the browser console — bad block JSON attributes cause editor errors

### 8. Submit

For remote templates: open a PR against the templates website repo (TBD — see [`website-launch-plan.md`](./website-launch-plan.md)). Include both the manifest entry and the preview image.

For bundled starters: open a PR against this plugin repo modifying `class-cohost-template-library.php` and adding an SVG to `assets/img/templates/`.

## Style conventions

- **Heading levels**: profile templates start with H1 (the event name). Listing templates start with H1 (the page title) only if the template includes its own heading; otherwise let the WP page title block handle H1.
- **Color**: don't pin colors. Let the partner's theme own the palette. Exception: orange `#f97316` for the brand square, but our blocks already render that.
- **Spacing**: use the Spacer block (`<!-- wp:spacer {"height":"40px"} /-->`) sparingly — themes have their own block-spacing settings that should usually be respected.
- **Buttons**: use `cohost/event-tickets` for the ticket CTA, native `core/buttons` for everything else.
- **Mobile**: WP block layouts are responsive by default. Test at 360px width before submitting.

## Common pitfalls

- **JSON escaping in attributes** — block attributes are JSON. Always `\"` not `"` inside the attribute object: `<!-- wp:heading {"textAlign":"center"} -->` not `<!-- wp:heading {"textAlign":center} -->`.
- **Forgetting the closing comment** — block markup is sensitive to mismatched `<!-- wp:foo --> ... <!-- /wp:foo -->` pairs. Use self-closing `<!-- wp:foo /-->` when the block has no inner content.
- **Pinning specific event content** — if your template includes "Get tickets to Summer Festival 2026", you've baked an event into the template. Use Cohost blocks to pull dynamic data; static text should be generic.
- **Using paragraphs where you mean cohost blocks** — typing "{event name}" into a Paragraph won't substitute. Use `<!-- wp:cohost/event-name /-->`.
- **Forgetting the preview image** — works fine but the gallery falls back to a placeholder. Always supply one.

## Checklist before submitting

- [ ] Renders correctly in editor and on the front end
- [ ] Tested with at least one real event from the API
- [ ] Tested at mobile width (≤480px)
- [ ] No third-party block dependencies
- [ ] No pinned event-specific content
- [ ] Preview image is 16:9, ≥640×360, brand-coherent
- [ ] `id` is unique, lowercase-hyphenated, descriptive
- [ ] `description` is one sentence, explains the use case
- [ ] Markup parses with `parse_blocks()` without errors
