# WordPress.org plugin submission

How we get the Cohost plugin into the wp.org Plugin Directory so it's discoverable from every WP install's **Plugins → Add New** search.

This doc separates the **shortest path to claim the slug** (everything strictly required) from the **recommended polish** (everything that makes the listing look professional but isn't blocking).

---

## Slug strategy

| Priority | Slug | Notes |
|---|---|---|
| 1st choice | `cohost` | Brand-pure, single word. **Required:** check availability — see step 1 below. |
| Fallback | `cohost-events` | If `cohost` is taken or denied. Same zip, different slug requested at submission. |

> **Why we renamed from `cohost-wp`:** wp.org rejects plugin slugs containing the term `wp` (per Plugin Check's `trademarked_term` rule).

---

## A. Shortest path — claim the slug today

The minimum viable submission. The Plugin Team can approve a plugin with no icon, no banner, and no screenshots — listing-assets are not gated by the approval review, only by the SVN commit that comes after.

### Step 1 — `[required]` Confirm slug availability

Visit https://wordpress.org/plugins/cohost/. If it 404s, the slug is open. If it resolves to an existing plugin, fall back to `cohost-events`.

### Step 2 — `[required]` Build the submission zip

```sh
cd oss/cohost-wp
npm run zip
# produces oss/cohost-wp/cohost.zip (top-level folder: cohost/)
```

The zip's contents are already wp.org-compliant — we did the prep work in commit `cc64272cd`:
- `[required]` Plugin slug folder + main file + text domain all match (`cohost`)
- `[required]` Plugin header has `License: GPLv2 or later`
- `[required]` `readme.txt` follows wp.org format with **Privacy & data** section
- `[required]` `Tested up to:` is set to current WP (6.9 at time of writing — bump before each release)
- `[required]` Plugin Check (the official wp.org linter) returns **0 ERRORs**
- `[required]` All `__()` placeholders have `translators:` comments
- `[required]` `.pot` translation template is shipped in `/languages/`
- `[required]` Plugin works on a fresh WP install (verified locally via `wp-env`)

### Step 3 — `[required]` Submit at https://wordpress.org/plugins/developers/add/

Fill the form:
- `[required]` Plugin name: `Cohost`
- `[required]` Plugin slug: `cohost` (or `cohost-events` if the first is taken)
- `[required]` Plugin description (short — 150 char): *"Show your Cohost events on your own WordPress site — your branding, your domain, your audience."*
- `[required]` Upload `cohost.zip`
- `[optional]` Public source URL (GitHub or similar)
- `[required]` Submit

### Step 4 — `[required]` Wait for review

| Stage | Timeline |
|---|---|
| Initial automated checks | Minutes |
| Reviewer assigned | 1-7 days |
| First reviewer feedback | 7-14 days from submission |
| Approval | When reviewer signs off |

Plugins-that-phone-home (we call `api.cohost.vip` and `templates.cohost.vip`) often get longer reviews because reviewers verify the privacy disclosure. Ours is detailed enough that it should pass first time, but expect at least one round of "please clarify X".

### Step 5 — `[required]` Address reviewer feedback (if any)

Common asks:
- Tighten phone-home disclosure language
- Reduce/specify the data sent
- Add a settings opt-in for outbound calls
- Fix specific sniffer warnings flagged by Plugin Check

Reply to their email, push a fixed zip if needed.

### Step 6 — `[required]` Approval → SVN access

You'll get an email with SVN credentials. Slug is now claimed. Plugin won't be live yet — that needs the first SVN commit (step B.1 below).

> **At this point the slug is yours.** Everything below is optional polish before users see the listing.

---

## B. After approval — go live (still mostly required)

### Step B.1 — `[required]` Push the plugin to SVN trunk

```sh
svn co https://plugins.svn.wordpress.org/cohost/ cohost-svn
cd cohost-svn
# copy plugin files into trunk/
cp -R /path/to/oss/cohost-wp/cohost/* trunk/
svn add trunk/* --force
svn commit -m "Initial release 0.1.0"
```

Within ~15 minutes the plugin is live at `https://wordpress.org/plugins/cohost/` and installable from every WP admin's **Plugins → Add New → Search** for "cohost".

### Step B.2 — `[required]` Tag the release

```sh
svn cp trunk/ tags/0.1.0/
svn commit -m "Tag 0.1.0"
```

The Stable Tag in `readme.txt` (`Stable tag: 0.1.0`) tells wp.org which `tags/<version>/` directory to serve to end users. Without this step, wp.org serves `trunk/` directly — works, but breaks the convention.

---

## C. Recommended polish — before publishing publicly

Everything in this section is **`[optional]`** for approval but improves install conversion. Can be done in parallel with the slug review or after approval.

### C.1 `[optional]` Plugin icon

The small icon shown in **Plugins → Add New** search results.

| Asset | Dimensions | Source |
|---|---|---|
| Icon (small) | 128 × 128 PNG | Export from `cohost-branding/app-icon/app-icon-dark.svg` (the dark "C+square") |
| Icon (retina) | 256 × 256 PNG | Same, 2x resolution |

File naming for the SVN `/assets/` folder: `icon-128x128.png`, `icon-256x256.png`.

**Effort:** ~10 min. Without an icon, the listing shows a default placeholder that looks unfinished — recommend doing this before B.1.

### C.2 `[optional]` Banner image

Shown at the top of the wp.org listing page (`https://wordpress.org/plugins/cohost/`).

| Asset | Dimensions | Source |
|---|---|---|
| Banner (small) | 772 × 250 PNG | Designed: dark `#161616` background, "Cohost" wordmark + tagline + the orange `#f97316` square accent |
| Banner (retina) | 1544 × 500 PNG | Same, 2x |

File naming: `banner-772x250.png`, `banner-1544x500.png`.

**Effort:** ~30 min in Figma using assets from `cohost-branding/`.

### C.3 `[optional]` Screenshots

Shown in the wp.org listing page below the description.

| # | Screenshot | How to capture |
|---|---|---|
| 1 | Front-end events grid | Visit `/events/` on the local wp-env install, capture at 1280 × 960 |
| 2 | Front-end event profile | Click a card, capture the profile page |
| 3 | Templates gallery | Admin → Cohost → Templates |
| 4 | Block inserter | Page editor with the Cohost category open in the block inserter |
| 5 | Settings | Admin → Cohost → Settings |

File naming: `screenshot-1.png`, `screenshot-2.png`, etc. — saved to SVN `/assets/`.

`readme.txt` already declares the captions in the `== Screenshots ==` section; matching the order to the file numbers gives correct rendering.

**Effort:** ~5 min total via the local wp-env install.

### C.4 `[optional]` Expanded long description

`readme.txt` already has a description that passes review. Worth expanding before going live with high traffic — listings with detailed descriptions and FAQ sections convert better.

Sections to consider adding to `readme.txt`:
- Larger `== Description ==` body (currently a single paragraph)
- `== Frequently Asked Questions ==` — common partner questions about data, privacy, customization
- `== Upgrade Notice ==` — only relevant once we ship 0.1.1+

**Effort:** ~1 hour for a thorough first pass.

### C.5 `[optional]` Public source repo

Some partners (especially security-conscious ones) only install plugins whose source they can audit. Add the GitHub URL to:
- The plugin header `Plugin URI:` field
- `readme.txt` (`Plugin URI` line)
- The wp.org submission form's optional "View source" field

**Effort:** trivial once the public repo exists. Open question — see "Open questions" below.

---

## D. After publishing — release workflow

For each subsequent release (0.1.1, 0.2.0, etc.):

1. `[required]` Bump version in `cohost.php` plugin header
2. `[required]` Bump `Stable tag:` in `readme.txt`
3. `[required]` Run `wp plugin check cohost` — expect **0 ERRORs**
4. `[required]` Test on a fresh WP install
5. `[required]` `cp -R cohost-wp/cohost/* svn/trunk/` then `svn commit`
6. `[required]` `svn cp trunk/ tags/<version>/` then `svn commit`

The directory rebuilds; users see the update prompt within hours. Existing installs auto-update on the user's next admin login (or sooner if auto-updates are on).

---

## E. Open questions (decide before submitting)

- `[required-ish]` **Plugin Author / Author URI** — currently `Cohost / https://cohost.vip`. OK to ship, or use a specific maintainer's wp.org username instead?
- `[recommended]` **Public GitHub URL** — wp.org listing has an optional "View source" field. Cohost-WP source repo URL?
- `[recommended]` **Support channel** — wp.org forums (default), GitHub Issues, or `support@cohost.vip`?
- `[future]` **OAuth re-introduction** — when OAuth ships (per [`../ROADMAP.md`](../ROADMAP.md)), the privacy disclosure in `readme.txt` must be updated before that release.

---

## References

- [wp.org Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [`readme.txt` reference](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [Plugin asset specs](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)
- [Plugin Check tool](https://wordpress.org/plugins/plugin-check/)
- [Plugin Team handbook](https://make.wordpress.org/plugins/handbook/)

---

## TL;DR — the 3-minute version

1. `[required]` Check `wordpress.org/plugins/cohost/` 404s.
2. `[required]` `cd oss/cohost-wp && npm run zip`
3. `[required]` Upload `cohost.zip` at `wordpress.org/plugins/developers/add/`, request slug `cohost`.
4. `[required]` Wait 7-14 days. Reply to any reviewer feedback.
5. `[required]` On approval: `svn co`, copy plugin into `trunk/`, `svn commit`, `svn cp trunk/ tags/0.1.0/`, `svn commit`. Plugin is live.
6. `[optional]` Anytime after step 5: drop icon, banner, screenshots into `/assets/` via SVN. Listing looks polished.

Total work in your hands: ~30 min spread across submission + first SVN push. Total wall-clock: 1-2 weeks dominated by review wait.
