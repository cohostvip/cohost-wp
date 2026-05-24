# Template ideas — backlog

Living list. Add freely; reorder by priority. Move to `Done` (or remove) once shipped.

Priority columns: **MVP** = ship in the bundled starter set, **High** = first batch on the remote manifest, **Med** = second wave, **Low** = nice-to-have / niche.

---

## Listings (events grid pages)

| Priority | ID | Use case | Notes |
|---|---|---|---|
| MVP ✅ | `listing-simple-grid` | Just the events grid. Default. | Shipped (bundled). |
| MVP ✅ | `listing-hero-grid` | Heading + intro paragraph above the grid. | Shipped (bundled). |
| High | `listing-featured-and-list` | One large featured event (big flyer), 6 smaller events below in 2 columns. | Good for curated "tonight" pages. Needs the featured event to be sticky-ed to top of API response — which we don't expose yet. Workaround: pin via shortcode `eventId` attr. |
| High | `listing-by-day` | Group events by start date — section headers ("Today", "Tomorrow", "This weekend") then the events in that bucket. | Requires either client-side grouping or a `/events?groupBy=day` API param. **Blocked on API.** |
| Med | `listing-with-search` | Heading + search input (filters the grid) + grid. | Requires a small JS file for client-side filtering, or the API exposing search. Check if `/events?search=` works first. |
| Med | `listing-magazine` | Editorial-style: alternating large/small events, mixed flyer crops. | Heavy — uses Columns + multiple grid widths. Authoring effort: ~2 hrs. |
| Low | `listing-calendar` | Calendar-month view with event dots. | Big lift — needs a custom block, not just composition. Park until partner asks. |
| Low | `listing-poster-wall` | Pinterest-style masonry of flyers, hover to reveal name + date. | Wants masonry layout that core blocks don't provide. |

## Profiles (single event pages)

| Priority | ID | Use case | Notes |
|---|---|---|---|
| MVP ✅ | `profile-standard` | Default — flyer, name, date, venue, summary, content, tickets. | Shipped (bundled). |
| MVP ✅ | `profile-magazine` | Wide hero flyer, two-column body. | Shipped (bundled). |
| MVP ✅ | `profile-poster` | Big flyer, prominent name and date, bold ticket button, content below. | Shipped (bundled). |
| MVP ✅ | `profile-minimal` | Name + date + content only. No image, no meta. | Shipped (bundled). |
| High | `profile-sticky-tickets` | Main content scrolls, tickets block in a Group with `position: sticky`. | Test theme compat — some themes break sticky. |
| High | `profile-with-share-bar` | Social-share buttons under the title. | Needs a share-buttons primitive — partner can use a third-party plugin (Jetpack Sharing, etc.) but that breaks our "no third-party blocks" rule. Maybe ship a tiny `cohost/share-buttons` block instead. |
| Med | `profile-festival-lineup` | Hero flyer, artist grid, day-by-day schedule, venue map. | Multi-day events. Big template (~15 blocks). |
| Med | `profile-with-related-events` | Standard layout + "More events from this organizer" grid below. | Needs a `[cohost_events organizer="..."]` filter — exists? Check the events shortcode supports it. |
| Med | `profile-rsvp-style` | No ticket pricing prominent — just an RSVP button (single CTA). | For free / invite-only events. |
| Low | `profile-livestream` | Title + date + a "Watch live" embed slot above content. | Niche; needs a Video / Embed block. |
| Low | `profile-podcast-episode` | Repurposes the event profile for a podcast-episode page. Title, audio embed, transcript. | Off-spec but could expand the plugin's reach beyond live events. |

---

## How to use this list

- Pull the top item from each section that's not blocked, author it, ship it.
- "Blocked on API" items stay parked until the API ships the dependency.
- When adding a new idea, write a one-sentence use case and the realistic blocker if any. Don't list "would be cool" without a concrete partner ask.
