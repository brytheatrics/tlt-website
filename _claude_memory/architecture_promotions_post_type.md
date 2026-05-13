# Architecture — Promotions as a universal post type

The `tlt_promotion` custom post type drives banner sections on multiple page types (homepage, education, visit, get involved, etc.) — not just the homepage.

## Fields

- Headline / body / image / CTA (button text + URL)
- `display_location` (multiselect) — Homepage, Education, Visit, Get Involved, etc.
- `start_date` / `end_date` — when set, promo auto-appears and auto-disappears (REQUIRED if Chris isn't going to remember to remove it)
- `priority` — for ordering when multiple are active in the same location

## Rendering

Each page template (`page-home.php`, `page-education.php`, etc.) renders promos where:
1. `display_location` includes the current page's location identifier
2. `today` is between `start_date` and `end_date` (or both dates are empty)

Ordered by `priority` ascending.

## Solves

- "Spring ed show closed, banner is still up" — auto-expires
- "Chris created 3 banners that look slightly different" — single template enforces consistency
- "How do we put the same auditions banner on homepage AND get-involved?" — multiselect `display_location`

## Tied to shows

When a promo links to a specific show, the end_date defaults to that show's `close_date` so promos for closed shows disappear automatically.

## What this REPLACES on the old site

- Hard-coded homepage banner sections
- The "I forgot to take this down" pattern that made Squarespace messy
- Per-show audition pages (Chris will edit the auditions hub, not make new banners)
