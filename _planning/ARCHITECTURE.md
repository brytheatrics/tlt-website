# TLT Website Architecture

Captures the architectural decisions made for the WordPress site. Authoritative — when in doubt, read this. PROJECT.md is the high-level project status; this is the engineering plan.

Last updated: 2026-05-12.

---

## Headline decisions

1. **No page builder.** Custom WordPress theme with hard templates + a small library of flexible-content blocks + a "Designed Page" template for one-offs. Decision documented in [No-page-builder rationale](#why-no-elementor) below.
2. **Date-driven, not memory-driven.** Every "this needs to change at time X" task is a date field on the content. The system enforces correctness; Chris doesn't have to remember.
3. **Custom post types do the work; pages are just views.** Promos, shows, board, news are post types. The homepage, education page, etc. assemble themselves from these post types instead of being hand-edited.
4. **Chris stays Administrator.** Brand controls (colors, fonts) aren't exposed in admin UI — they live in code as CSS variables. The Customizer exposes only content settings (logo, address, mission text, social links). Future rebrand = dev edit; future content edits = Chris in Customizer.
5. **Date-bounded content auto-expires.** End dates on auditions, promos, season-ticket cutoffs, etc. mean stale content disappears on its own.

---

## Why no Elementor

The conversation that led here, summarized:

**Chris's actual job day-to-day** is updating content within already-designed pages, not designing pages. Show titles, dates, cast, photos, board members, etc. — repeat content updates within consistent layouts. Elementor (or any drag-drop page builder) is over-served for that.

**Pros of templates over Elementor:**
- No $59/yr cost
- Faster pages (Elementor adds ~150KB CSS/JS per page)
- No vendor lock-in
- Design cohesion is enforced by the system (not by Chris's discipline)
- Pages live in code, not the DB (better cross-computer sync, better git history)
- Distinctive look — Elementor sites tend to look like Elementor sites

**Cons / tradeoffs accepted:**
- Bus factor: PHP requires a developer for new templates. Mitigated by:
  - Using only standard WordPress patterns (CPTs, taxonomies, theme.json, Customizer) so any WP contractor can pick it up
  - Documented architecture (this doc)
  - Customizer for content settings (no dev needed for routine changes)
- Less playground for Chris. Mitigated by Designed Page template + flex blocks for one-offs.
- New page types = dev tasks. Mitigated by:
  - Most "new page types" Chris reaches for are already covered by Designed Page or flex blocks
  - Blake is available for dev work
  - Outsourcing to a WP contractor is straightforward if needed

---

## Template architecture (3-tier)

| Tier | What it's for | Tool | Where editing happens |
|---|---|---|---|
| **1. Hard templates** | Predictable, repeating content types (shows, board, season grids, auditions, ticketing, contact) | Custom PHP template files in the theme | Chris fills in fields in WP admin |
| **2. Flex-content pages** | One-off-but-structurally-similar pages (campaign banners, fundraising stories, mission pages, etc.) | Small library of pre-styled section "blocks" Chris stacks | Chris picks blocks, fills in content; can't change colors/fonts/layouts |
| **3. Designed Pages** | Fully bespoke design (gala microsites, anniversary specials, fundraising appeals where the design IS the message) | Single template: hero image (desktop + mobile) + headline + body + up to 3 CTAs. Or, for fully Photoshop-designed pages, image-only template | Chris uploads images, headline, button labels |

### Templates already built (covers ~117 of 189 kept pages)

- `page-home.php` — homepage (auto-driven hero + season grid)
- `page-splash.php` — splash entry page (cycling production photos)
- `page-prior-seasons.php` — prior seasons index
- `taxonomy-tlt_season.php` — per-season archive
- `single-tlt_show.php` — single show page (handles director/music dir/choreographer/dates/poster/run time/age/content warning/buy tickets/program PDF/videos/cancelled badge/JSON-LD schema)
- `single-tlt_team.php` — board/staff profiles
- `page-education.php` — education landing
- `page-board-and-staff.php` — board/staff
- `page-off-the-shelf.php` — off the shelf series (needs conversion to dynamic list)

### Templates still to build

See `_planning/template_inventory.md` for the full agent-generated list. Headlines:

- `page-auditions.php` (B4) — highest-edit page, hub for all current auditions
- `page-ticketing.php` (B2) — /ticketinfo, /season-tickets, /parking-information
- `page-campaign.php` (B6) — Flush + future fundraising
- `page-job-board.php` / `page-press.php` (B5) — parameterized post-listing
- `page-contact.php` (B3) — contact form + hours + map
- `page-video-archive.php` (B7) — recorded programs grid
- **Designed Page** template
- Improvements to `page.php` defaults (handle inline images, image floats, hero image option)

### Flex-block library (smaller pieces)

- Prose (rich text — the workhorse)
- Figure (image + caption)
- Image-with-text-float (2-col image + prose)
- Section heading
- Full-bleed banner image
- Button / CTA
- Video embed (oEmbed)
- PDF link list (decade pages, audition packets)
- Photo gallery (lightbox)
- Two-column callout (address + hours, two pricing tiers)
- Logo/sponsor row
- Pull-quote / callout

---

## Custom post types

### `tlt_show` (already built)

**Replaces:** individual show pages, Off the Shelf event pages, Murder Mystery Dinner pages.

**Program types** (`show_program_type` meta — extend the existing values):
- `mainstage` — regular season productions
- `off_the_shelf` — staged readings (new)
- `murder_mystery_dinner` — off-site dinner shows (new)
- `childrens` — Kid-targeted productions (existing)
- `special_event` — galas, anniversaries (existing or future)

**Meta fields** (existing):
- `show_open_date` / `show_close_date`
- `show_director` / `show_music_director` / `show_choreographer`
- `show_run_time` / `show_age_rec` / `show_content_warning`
- `show_ticket_url` / `show_program_pdf_url`
- `show_video_urls` (comma-separated)
- `show_cancelled` (boolean)
- `show_hero_image_url`
- `show_tagline`

**Meta fields to add:**
- `show_venue_name` / `show_venue_address` — for off-site shows (Murder Mystery Dinners). When set, render an "Presented at:" location card.
- `show_dinner_menu` — for Murder Mystery Dinners. Rich text rendered after cast list.
- `show_photo_gallery` — for show photo galleries. Folds in standalone gallery posts (cabaret-pictures, etc.) rather than building a separate `page-gallery.php`.
- `show_splash_gallery` — array of splash photos for the splash page. When the show is "currently running" and this is non-empty, the splash page activates.

**URL rewrites:**
- Mainstage: `/shows/<slug>/` (current)
- Off the Shelf: `/off-the-shelf/<slug>/` (new — needs URL rewrite rule)
- Murder Mystery Dinner: `/shows/<slug>/` (probably — they're listed in the season)

### `tlt_team` (already built)

Board, staff, key volunteers. One page per person.

### `tlt_promotion` (planned)

**Universal — drives banners on multiple page types.**

Fields:
- Headline / body / image / CTA (button text + URL)
- `display_location` (multiselect) — Homepage, Education, Visit, Get Involved, etc.
- `start_date` / `end_date` — when set, promo auto-appears and auto-disappears
- `priority` — for ordering when multiple active

Behavior:
- Homepage, education page, etc. each render "promos where display_location = self AND today is within start/end (or dates unset)" in order of priority
- If linked to a specific show, end date defaults to show close date

Replaces:
- Hard-coded homepage banners
- Hard-coded education page promos
- The "I forgot to take this banner down" problem

### `tlt_audition` (planned, maybe — alternative is a single hub page)

We've decided to use a single `/auditions/` hub page with repeating rows rather than a post type. Each "row" can be a sub-record with title, dates, packet PDF, status (open/cast/closed). Open question: is this better as repeater fields on the page itself, or as a `tlt_audition` post type that the hub queries?

Leaning toward the post type approach so audition records have their own URLs (linkable from social media, etc.). To revisit when we build `page-auditions.php`.

---

## Splash page rules (auto-managed)

**Rule:** Splash page appears IF AND ONLY IF the currently-running show has splash photos uploaded.

Implementation:
- `show_splash_gallery` meta on each show (array of image attachment IDs)
- Splash page checks: is there a currently-running show? Does it have splash photos? If yes to both, render splash. If no to either, the splash redirect rule sends `/` straight to `/home/`.
- Photos arrive mid-run → Chris uploads them → splash starts appearing the next time someone hits the site
- Show closes → next show is upcoming but not running → splash disappears
- Next show opens → if photos uploaded, splash appears for that show automatically

Zero "remember to turn off the splash" work for Chris.

---

## Season ticket vs individual ticket CTA

**Rule:** Each season has a "Season Ticket Sales End Date" field. After that date, the homepage CTA auto-swaps from "Buy Season Tickets" to "Buy Individual Tickets" and the URL changes accordingly.

In practice, Chris sets this when planning the season ("Season ticket sales end Feb 1, 2027"). Set once per season. Switches automatically.

Optional manual override toggle ("Force show: Season Tickets / Individual Tickets / Auto") in case Chris wants to swap earlier or later than the date-driven default.

---

## Season transition (current → prior)

Already mostly automatic:

**Automatic:**
- Homepage's "current season" advances to the next season term once all current-season shows have `close_date < today`
- Closed shows show up in `/prior-seasons/` archive automatically
- Show status badges flip Now Playing → Closed
- Hero auto-picks next show, then falls back to recap mode when nothing's upcoming

**Manual (won't move automatically):**
- Promo banners need end dates (we'll require them — form won't save without)
- Production photos / programs for closed shows need to be uploaded after close (planned: admin dashboard widget will surface "show closed yesterday — upload its photos/program here")
- Splash gallery photos cycle out automatically via the rule above

---

## Forms

**Plugin:** Contact Form 7 (free, widely supported) or Gravity Forms ($59/yr, nicer).

**Workflow:**
1. Chris builds a form in plugin admin (drag-drop field types)
2. Plugin gives a shortcode like `[contact-form-7 id="42"]`
3. Chris pastes shortcode into any page's body
4. Form submissions email to box office + get stored in WP DB

**Use cases:**
- Contact (general inquiry)
- Volunteer signup
- Donation request (organizations applying TO TLT for donations)
- Kaleidoscope-style event registrations
- Future: gala RSVP, special event signups

Need to style the plugin output to match site (CTA button style, field padding, focus states).

---

## Customizer settings (admin-editable, Chris sees these)

**Content settings:** logo, footer address, phone, federal ID, mission statement, vision statement, land acknowledgment, social media links.

**NOT exposed:** brand colors, fonts, layouts, spacing. These live in code as CSS variables. Future rebrand = developer task (5 min: edit `--color-accent` etc.).

Reasoning: Chris is Administrator (he's the boss, no one above him). The "what Chris can change" set isn't constrained by role — it's constrained by what we expose in the Customizer. Brand controls aren't in the UI; therefore Chris cannot accidentally drift the brand. Easy to add to Customizer later if a real need emerges.

---

## Search

Site-wide search bar in the header. Uses WordPress built-in search. Scope:
- Shows
- Posts (news, job openings, press)
- Pages

Not scoped to:
- Promotions (internal-only data)
- Media attachments (too much noise)

Search results template needs custom styling.

---

## Future / forward-looking

### Auto-generated calendar
`/calendar/` page that pulls from shows + auditions + classes + special events into a month/week view. Single source of truth (no double-entry). Possibly iCal feed for patrons to subscribe.
See [`_claude_memory/feature_calendar_auto.md`](../_claude_memory/feature_calendar_auto.md).

### Admin dashboard widgets
Nudges for Chris: "Show closed yesterday — upload program & photos", "Splash photos haven't been updated in 60 days", "2 active promos reference closed shows". Helps with the "Chris forgot" cases that aren't fully automatable.

### Callboard integration
The existing Google Apps Script callboard sits behind a password. Embed as iframe in a password-protected WP page, OR rebuild natively in WP. Likely v2.

---

## Migration / cleanup phase (still pending)

Per `_planning/template_inventory.md` recommended build order:

1. **HTML cleanup pass on every imported page** — strip Squarespace wrapper divs, fix `�` smart-quote mojibake, rewrite `/s/*.pdf` links to local uploads.
2. **Rehost Squarespace-CDN images** — download still-CDN-hosted images, save locally, rewrite `<img src>`.
3. **Style `page.php` defaults** — typography, inline images, image floats, hero image option.
4. **Flex-block library** — 6-10 partials.
5. **`page-auditions.php`**, **`page-ticketing.php`**, **`page-campaign.php`**.
6. **`page-job-board.php` / `page-press.php`** (parameterized post listing).
7. **Designed Page template**.
8. **`page-contact.php`**, **`page-video-archive.php`**.
9. **Off the Shelf** and **Murder Mystery Dinner** program type extensions on `single-tlt_show.php`.
10. **Photo gallery** on show template (fold standalone gallery posts).
11. **Forms** — install plugin, build first 3 forms, style.
12. **Search** — header bar, results template.
13. **Customizer** — content settings only.
14. **Quality passes** — mobile, accessibility, performance.
15. **404 page** design.
16. **Sitemap.xml + organization JSON-LD schema**.
17. **MAINTENANCE.md** for Chris.

After step 8, ~95% of kept pages have a real template.

---

## What lives where

| Topic | File |
|---|---|
| High-level project status | [`PROJECT.md`](../PROJECT.md) |
| This document — architecture decisions | `_planning/ARCHITECTURE.md` (you are here) |
| Template inventory + counts | [`_planning/template_inventory.md`](template_inventory.md) |
| Decisions log during autonomous work | [`_planning/decisions.md`](decisions.md) |
| User memory / canonical facts about user + project | [`_claude_memory/MEMORY.md`](../_claude_memory/MEMORY.md) |
| Design tokens (extracted brand styles) | [`DESIGN_TOKENS.md`](../DESIGN_TOKENS.md) |
| Cross-computer dev setup | [`SETUP.md`](../SETUP.md) |
| Setup instructions for Chris (eventual) | `MAINTENANCE.md` (to be drafted) |
