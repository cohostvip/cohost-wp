# Cohost Brand Guide

## Brand Colors

| Name | Hex | Usage |
|------|-----|-------|
| **Dark** | `#161616` | Primary text, logos on light backgrounds |
| **Light** | `#F2F2F2` | Logos on dark backgrounds |
| **Accent (Orange)** | `#f97316` | The signature square dot, CTAs, highlights |
| **Background Light** | `#e8f1f8` | App icon background (light variant) |

### Extended Palette (Dashboard)

| Name | Hex | Usage |
|------|-----|-------|
| Chart 1 | `#e76e50` | Data visualization |
| Chart 2 | `#2a9d90` | Data visualization |
| Chart 3 | `#f97316` | Data visualization (same as accent) |
| Chart 4 | `#274754` | Data visualization |
| Chart 5 | `#e8c468` | Data visualization |

> **Important:** The accent orange is `#f97316`. Some older assets used `#EB563B` — this is deprecated. Always use `#f97316`.

---

## Logo Assets

### Lettermark (`lettermark/`)
The primary brand mark: lowercase "c" with an orange square positioned at the baseline right.

- `lettermark-dark.svg` / `.png` — dark text, for light backgrounds
- `lettermark-light.svg` / `.png` — light text, for dark backgrounds

Use for: nav bars, compact headers, profile avatars, social media.

### Wordmark (`wordmark/`)
Full "cohost" text in lowercase with the orange square dot after the "t".

- `wordmark-dark.svg` / `.png` — dark text, for light backgrounds
- `wordmark-light.svg` / `.png` — light text, for dark backgrounds

Use for: headers, marketing pages, documentation sites, email signatures.

### Wordmark Full (`wordmark-full/`)
Heavy display weight "cohost" text (the original bold logotype). No square dot.

- `wordmark-full-dark.svg` / `.png` — dark text, for light backgrounds
- `wordmark-full-light.svg` / `.png` — light text, for dark backgrounds

Use for: hero sections, splash screens, large format display.

### Icon (`icon/`)
The standalone bold "C" letterform (circular, no square). The original logomark.

- `icon-dark.svg` / `.png` — dark fill, for light backgrounds
- `icon-light.svg` / `.png` — light fill, for dark backgrounds

Use for: legacy contexts where the square dot doesn't fit.

### App Icon (`app-icon/`)
The lettermark (C + orange square) on a rounded rectangle background. Suitable for app stores, integration listings, and platform icons.

- `app-icon.svg` / `.png` — light background (`#e8f1f8`), dark lettermark
- `app-icon-dark.svg` / `.png` — dark background (`#161616`), light lettermark

Use for: app stores, Zapier/n8n/MCP listings, PWA icons, social profile images.

### Favicon (`favicon/`)
The lettermark on a white rounded rectangle background, optimized for browser tab visibility on both light and dark browser chrome.

- `favicon.svg` — scalable vector
- `favicon-512.png` — 512x512 raster

Use for: browser favicons, bookmark icons.

---

## Usage Rules

1. **Always use the lettermark (C + square)** as the primary mark. The plain "C" icon is legacy.
2. **Never change the accent color.** The orange square is always `#f97316`.
3. **Pick dark or light variant** based on background — never use dark-on-dark or light-on-light.
4. **Maintain clear space** around the logo equal to the height of the orange square.
5. **Minimum size:** The lettermark should not be smaller than 16px in height.
6. **Do not rotate, stretch, or add effects** to any logo asset.

---

## Typography

- **Primary font:** Inter (Google Fonts)
- **Fallback:** system sans-serif stack

---

## Fetching Assets

These assets are hosted on Google Cloud Storage at:

```
https://storage.googleapis.com/cohost-static/branding/
```

Example usage:
```html
<link rel="icon" href="https://storage.googleapis.com/cohost-static/branding/favicon/favicon.svg" type="image/svg+xml" />
<img src="https://storage.googleapis.com/cohost-static/branding/wordmark/wordmark-dark.svg" alt="Cohost" />
```

To update assets across all projects, upload new versions to the storage bucket. All projects referencing the CDN URL will pick up changes automatically.
