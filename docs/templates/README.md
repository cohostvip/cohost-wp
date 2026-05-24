# Cohost WP — Templates planning

This folder contains the design, planning, and authoring docs for the **Cohost WP template library** — the system that lets partners pick a starting layout for their events list + event profile pages with one click.

## Contents

| File | Purpose | Status |
|---|---|---|
| [`architecture.md`](./architecture.md) | How the template system actually works end-to-end (plugin code, manifest fetch, apply flow, caching). | ✅ Plugin side built (v0.1) |
| [`template-spec.md`](./template-spec.md) | JSON schema for `templates.json` and the conventions templates must follow. | ✅ Spec frozen for v1 |
| [`authoring-guide.md`](./authoring-guide.md) | Step-by-step instructions for designing, marking up, screenshotting, and submitting a new template. | ✅ Ready to use |
| [`website-launch-plan.md`](./website-launch-plan.md) | Plan for standing up `templates.cohost.vip` — hosting choice, URL structure, build pipeline, phasing. | 🟡 Plan only, not built |
| [`template-ideas.md`](./template-ideas.md) | Prioritized backlog of templates we want in the library, with use-case notes. | 🟡 Living list — add and re-prioritize as partners ask for things |

## Where things live

```
oss/cohost-wp/
├─ docs/templates/             ← you are here (planning + authoring docs)
├─ cohost-wp/
│  ├─ includes/
│  │  ├─ class-cohost-template-library.php   ← fetches manifest + bundled fallback
│  │  └─ class-cohost-templates.php          ← admin gallery UI + apply action
│  └─ assets/
│     ├─ css/templates.css                   ← gallery styles
│     └─ img/templates/*.svg                 ← bundled preview images
└─ ...

(planned, not built yet)
templates.cohost.vip/
├─ index.html                  ← public gallery website
├─ wp/templates.json           ← the manifest the plugin fetches
├─ wp/<template-id>.png        ← preview images referenced from the manifest
└─ ...
```

## TL;DR for someone joining

- Plugin v0.1 ships **6 bundled starter templates** so the gallery is never empty.
- Plugin **also** fetches a remote JSON manifest from `https://templates.cohost.vip/wp/templates.json` (15-minute transient cache), so we can ship new templates without releasing a new plugin version.
- The remote manifest doesn't exist yet. **Everything works without it** — the plugin gracefully falls back to bundled templates. Standing it up unlocks server-side iteration on the template catalog (see [`website-launch-plan.md`](./website-launch-plan.md)).
- Authoring a new template = writing a chunk of valid WP block markup + a 16:9 PNG preview + adding an entry to the manifest. No code changes. See [`authoring-guide.md`](./authoring-guide.md).

## Decision log

- **Block markup over raw HTML** — templates ship as Gutenberg block markup (`<!-- wp:cohost/event-name --> ...`), not raw HTML. Reasons: (1) users can edit cleanly in the block editor afterward, (2) no escaping/sanitization gymnastics, (3) the structure is durable when the plugin's frontend HTML changes.
- **Remote manifest, not embedded patterns** — chose remote JSON over `register_block_pattern()` so we can iterate on the catalog without releasing a new plugin version. Pattern API stays available as an option later if we want templates to also surface in the native WP Patterns tab.
- **Bundled fallback set is non-empty** — the plugin always ships ≥6 starters. The remote source is an *addition*, not a *requirement*. New installs work offline; partners on locked-down hosting still get value.
- **Apply replaces, not merges** — clicking Apply overwrites the page's `post_content` with the template's markup. Confirmed via JS confirm dialog. Safer than trying to merge with existing content; users who want partial layouts compose blocks manually.
