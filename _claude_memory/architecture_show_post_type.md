# Architecture — `tlt_show` is the workhorse

Many "different kinds of events" on the TLT site are actually just shows with optional extra fields. The `tlt_show` post type covers all of them via the `show_program_type` meta value.

## Program types

- `mainstage` — regular season productions (`/shows/<slug>/`)
- `off_the_shelf` — staged readings (`/off-the-shelf/<slug>/`)
- `murder_mystery_dinner` — off-site dinner shows (`/shows/<slug>/`)
- `childrens` — kid-targeted productions
- `special_event` — galas, anniversaries

## Optional meta fields used by non-mainstage variants

- `show_venue_name` / `show_venue_address` — for off-site shows (murder mystery dinners at the La Quinta etc.). When set, render an "Presented at:" location card.
- `show_dinner_menu` — rich text rendered after cast list. For murder mystery dinners.
- `show_photo_gallery` — photo gallery rendered as lightbox grid. Folds standalone gallery pages (cabaret-pictures etc.) into their parent show.
- `show_splash_gallery` — splash page photos for the currently-running show.

## Why not separate post types

Off the Shelf and Murder Mystery Dinners are 90% identical to mainstage shows (title, dates, director, cast, body, content warning, ticket URL, etc.). Adding two optional fields is much cleaner than duplicating the post type.

## URL strategy

- Mainstage and Murder Mystery Dinner: `/shows/<slug>/`
- Off the Shelf: `/off-the-shelf/<slug>/` via URL rewrite
- Hub pages (`/off-the-shelf`, `/shows`, season archives) pull dynamically from the post type, not hard-coded.

## What's NOT a show

- Audition listings → single `/auditions/` hub page with repeating rows (or `tlt_audition` post type, TBD)
- Promotional banners → `tlt_promotion` post type (universal — homepage, education page, etc.)
- News / press / job openings → standard WordPress posts with categories
- Board / staff → `tlt_team` post type
