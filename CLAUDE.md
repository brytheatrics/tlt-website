# CLAUDE.md — Session Handoff

Read this first when starting a new session. It's the fastest way to get oriented.

## Project at a glance

TLT website migration: Squarespace → self-hosted WordPress.

- **Live site (Squarespace):** https://www.tacomalittletheatre.com — still receiving real traffic
- **Local dev (Local Sites):** http://tlt.local — full migrated site, where all work happens
- **Production host:** Cloudways DigitalOcean Micro ($14/mo) — paid subscription active
- **Domain:** `.com` is registered/DNS at **Squarespace** (repoint here at cutover). `.org` and `.net` are GoDaddy and auto-forward to `.com` — won't need touching unless we want them to hit the new host directly.

## Where things live

| What | Where |
|---|---|
| Custom theme | `wordpress/themes/tlt/` |
| Custom plugin (post types) | `wordpress/plugins/tlt-post-types/` |
| Import / one-off scripts | `wordpress/import/` |
| Migration redirect map | `wordpress/themes/tlt/redirects.csv` |
| Local Sites WP root | `C:\Users\blake\Local Sites\tlt\app\public\` |
| Local Sites uploads | `C:\Users\blake\Local Sites\tlt\app\public\wp-content\uploads\` |
| Project docs | Project root (this folder) |

## DB connection (for import scripts)

```python
pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
```

## Custom post types (registered in `wordpress/plugins/tlt-post-types/tlt-post-types.php`)

- **`tlt_show`** — productions; metas include `show_open_date`, `show_close_date`, `show_director`, `show_ticket_url`, `show_program_pdf_url`, `show_cityline_url`, `show_video_urls`, `show_splash_gallery`, `show_photo_gallery`, audition meta, etc.
- **`tlt_team`** — board + staff; metas: `team_role_title`, `team_email`, `team_pronouns`, `team_is_board`, `team_is_staff`
- **`tlt_event`** — calendar events that aren't productions (galas, rentals, ClubTLT, camp showcases). Registered in `tlt-post-types/includes/events.php`. Metas: `event_start_date`, `event_end_date`, `event_time`, `event_location`, `event_url`, `event_category`. Drives `/calendar/` alongside show performances/auditions.
- Show calendar schedules: `show_performances` (one line per perf: `YYYY-MM-DD 7:30 PM`) and `show_audition_schedule` (`YYYY-MM-DD 7:00 PM @ Location`) — editable in the Show meta box, feed `/calendar/`.

## Page templates (in `wordpress/themes/tlt/`)

`page-home.php`, `page-splash.php`, `page-visit.php`, `page-contact.php`, `page-board-and-staff.php`, `page-auditions.php`, `page-ticketinfo.php`, `page-season-tickets.php`, `page-donation-request.php`, `page-press.php`, `page-press-post.php`, `page-job-openings.php`, `page-job-posting.php`, `page-off-the-shelf.php`, `page-education.php`, `page-prior-seasons.php`, `page-post-listing.php`, `page-ticketing.php`, `page-designed.php`, `page-campaign.php`, `page-styleguide.php`, `page-calendar.php`

## Recent work (most recent session — late June 2026)

- **Show page final layout** — `single-tlt_show.php` rewritten to a clean left/right split.
  - **Left column:** poster + Videos (Cityline + show_video_urls) only. Minimizes the eye bouncing back and forth.
  - **Right column, top-to-bottom:** Cancelled badge → Dates → Title → "by Playwright" → Tagline (bold italic) → Buy Tickets CTA → Credits → Presented At (when set) → Synopsis (body content) → **Showtimes & Tickets** card → **At A Glance** card → **Content Warning** card → Cast → View Program + View Dramaturgy buttons → **Reviews** card.
  - Three structured card components share the same shell (red `--color-accent` header bar + grey `--color-soft` body): At A Glance, Content Warning, Reviews.
  - Content Warning has its own internal structure: `CONTENT WARNING` red bar → `This production of [Show] includes the following:` subhead → warning body.

- **"Recommended for Ages" smart formatting** — `single-tlt_show.php` collapses to one inline line.
  - `12+` → **"Recommended for Ages: 12+"**
  - `All Ages` / `General Audiences` / `Family Friendly` → **"Recommended for All Ages"** etc. (the keyword inline, no "Ages:" prefix)
  - Detection regex in the template; case-insensitive.

- **Playwright field upgrade** — `show_playwright` is now a textarea (was single text input) that supports two formats:
  - Single name (e.g. `Aaron Sorkin`) → auto-prefixed with "by " on the front end.
  - Multi-line musical credits (`Book by …` / `Music by …` / `Lyrics by …`) → rendered verbatim with `<br>` between lines.
  - Detection: regex catches lines starting with `Book|Music|Lyric|Word|Adapted|Based|Conceived|Story|Written|Libretto|by`. Otherwise prefixes "by".
  - Save uses `sanitize_textarea_field` so newlines survive.

- **Showtimes & Tickets free-form card** — new meta `show_performance_details` (textarea, in `tlt-post-types.php` show meta box). Practical info that used to clutter the body (performance times, ticket prices, double-cast schedule, ASL/PWYC nights) gets its own red-header card between the synopsis and At A Glance. Free-form, ~8 rows in admin; renders verbatim with `nl2br`.

- **Top-of-page announcement ribbon** — new meta `show_announcement` (textarea). Renders as a TLT-red ribbon at the very top of the show page (`.show-announcement`) for limited-run events ("Join us on opening night for a talkback…"). Clearing the field hides the ribbon. Styling in `style.css`.

- **Admin show meta box reorganized** — `tlt_render_show_meta()` now emits fields in the same order they appear on the front-end show page (Announcement → Cancelled → Dates → Hero → Credits → Presented At → Showtimes & Tickets → At A Glance → Content Warning → Cast → Program & Dramaturgy → Reviews → Videos → Poster & Photos → Promote on Homepage → Auditions → Calendar Schedules → Other/Admin). Inline helpers `$text_field()` and `$section()` declared at the top of the function.

- **Home page ACF (Chris-editable headlines/buttons)** — `page-home.php` added to `tlt_acf_managed_templates()`. New ACF field group "Home Page — Sections" with 6 tabs (Onstage / Education / Special Events / Get Involved / Support / Sponsors). Each tab: eyebrow + headline + lede + "hide number badge" toggle + buttons textarea. Buttons format: `Label | URL | new` per line (the `| new` suffix opens in new tab). Defaults baked into `tlt_home_section_defaults()` so pages render identically without Chris editing. Helpers: `tlt_home_field()`, `tlt_home_hide_number()`, `tlt_parse_home_buttons()`, `tlt_render_home_buttons()`, `tlt_render_home_section_head()` in `includes/acf-page-templates.php`. `tlt_render_homepage_section()` (in `tlt-post-types/includes/promotions.php`) now consumes the ACF overrides and renders the buttons row.

- **Calendar program-type → entry-type mapping** — show performances on the calendar now honor `show_program_type` for color/label (previously always rendered red "Performance"). Mapping in `includes/calendar.php`: mainstage → performance, off_the_shelf → off_the_shelf, murder_mystery_dinner → special, education → education_performance, special → special. **Removed unused types `club_tlt` and `childrens`** from both the show program-type dropdown AND the event-category dropdown (0 records using either).

- **Splash → home wipe flash fix** — dynamic header height measurement in `header.php` (was hardcoded `--header-h: 84px`; real header is 77-83px depending on breakpoint, more if a sitewide banner is active). Replaced the `.is-done` opacity fade-out with instant removal (the fade was the source of the visible flash when the wipe was misaligned by even a few pixels). Plus `<style>html { background: #272727 }</style>` injected in `<head>` on splash/home pages so the inter-page navigation "white frame" can't show through (was an intermittent flash). See `header.php` lines 1-50 and `style.css` `#homeWipe` block.

- **YouTube/Vimeo embed helper** — new `tlt_video_embed_url()` in `functions.php` normalizes any YouTube share URL (youtu.be/, watch?v=, /shorts/, /embed/) to the iframe-embeddable form. Fixes "refused to connect" when Chris pastes a share URL into `show_video_urls`. Preserves `?t=` start time. Applied in `single-tlt_show.php` videos loop.

- **2627 posters re-extracted from server** — `C:/temp/extract_2627_posters.py` re-ran against the latest PSDs at `\\TLT-SERVER\Marketing\2627 Marketing\2627 Posters\` (most modified June 9 2026 — newer than my first extraction). All 7 show posters now at `/wp-content/uploads/posters/2627/` (1600×2400 each, ~600 KB).

- **Mobile layered hero (in progress, blocker for launch — see Outstanding #5)** — implemented `<picture>`-element swap in `page-home.php` so a `mobile/` subfolder under `hero-layers/<slug>/` serves portrait-oriented versions of the layered PNGs to phones; desktop sees the original landscape layers. Browser picks via `<source media="(max-width: 700px)">`.
  - **The Outsider** has its mobile layers extracted from `\\TLT-SERVER\Marketing\2627 Marketing\2627 Posters\1 The Outsider\Hero Animation\The Outsider Hero - Copy.psd` (2700×4269 canvas) to `/wp-content/uploads/hero-layers/the-outsider/mobile/`. **Extraction is iterating** — the user is tweaking the design.
  - **Arsenic and Old Lace** mobile PSD is finished by the user and ready to test next (same workflow as The Outsider).
  - **CRITICAL design rule for hero PSDs:** the visible canvas defines the composition Chris sees, BUT each animated layer needs to extend past the canvas in the direction it slides from (man enters from right → layer canvas needs transparent space to the right; podium rises from below → layer canvas extends below). **The crop must not cut off the animation.** Extraction script (`C:/temp/extract_outsider_mobile.py`) composites each layer onto a viewport ~50% wider than canvas in horizontal and ~12% taller — that's the bleed envelope. CSS in the mobile `@media` block scales layers natural-aspect height-fit, centered; slide distances reduced to 20% (X) and 5% (Y) so the IMG-box edge stays inside the bleed.

- **Reviews archive recovery — MASSIVE** — Wayback Machine sweep recovered **451 review files** across 13 publications spanning 2007-2024.
  - **`archive/reviews/`** (in repo, gitignored — copyright stays internal) — 247 reviews mentioning TLT productions, organized into per-publication subfolders.
  - **`C:/Users/blake/Documents/Blake-Reviews/`** — 204 reviews mentioning Blake R. York across his career (TLT and other venues), also per-publication.
  - Sources covered: The Suburban Times (defunct domain — Wayback only), Weekly Volcano, Tacoma Weekly, Heilman & Haver, Dresdner's Theatre Reviews, Drama in the Hood, The Sound on Stage, Alec Clayton, AXS, Tacoma News Tribune, OLY ARTS, Shows I've Seen, South Sound Arts.
  - Build scripts at `C:/temp/`: `sweep_blake.py`, `sweep_curated.py`, `sweep_era.py`, `sweep_blogs.py`, `sweep_dith.py`, `sweep_extras.py`, `sweep_nt.py`, then `organize_by_pub.py`. Each markdown file has frontmatter: title / source / date / original URL / snapshot URL / body.
  - **Index:** `archive/reviews/tlt_review_match.csv` — every show's stored review URLs vs. what content I recovered.

- **96 review URLs swapped to Wayback in `show_reviews`** — `show_reviews` field on 62 shows now points at Wayback snapshots for dead URLs (Suburban Times domain entirely defunct, plus dead Tacoma Weekly / Weekly Volcano / Heilman & Haver paths). Two rounds: first used the local archive's snapshot URLs (51 URLs), second used the Wayback availability API for URLs not in our archive (45 URLs). **Audit log:** `archive/reviews/wayback_replacements.tsv` (97 rows, columns `show_id | title | reason | old_line | new_line`; reasons are `DEAD-DOMAIN` / `DEAD-PROBE` / `WAYBACK-API`). 26 reviews are truly gone (no Wayback snapshot exists) — left as-is.

- **Cloudways deploy pipeline is live** — paid subscription active, SSH public key added, `deploy/cloudways.json` has real creds (`ssh_host: 64.23.180.12`, `ssh_user: master_vdrkzztcte`, `app_folder: dtvxxevyxd`, `temp_url: https://wordpress-1633814-6469148.cloudwaysapps.com`). Latest push done via the toolkit: theme + plugin + DB (160 shows, 8 events, 8 promos) + 2627 posters + The Outsider hero-layers (desktop + mobile). Search-replace `tlt.local` → temp URL ran cleanly. **Site is live at the temp URL** for testing.
  - **⚠️ Cloudways has a server-level Full Page Cache** (identified as `CLOUDWAYS-CACHE-DE`) that runs above Varnish and WP-CLI can't purge it. After every code push you must **purge from the Cloudways dashboard** (Applications → your app → Application Management → Full Page Cache → Purge). While iterating, consider disabling that cache entirely; re-enable it just before real launch. Symptom of forgetting: recent code changes don't appear on the live temp URL; cache-busting query strings (`?nocache=…`) will show the fresh version.
  - Uploads NOT synced this pass (unchanged since May 29 deploy): `uploads/productions/` (~600MB of gallery photos), `uploads/programs/`, `uploads/migrated/`. If a show page loads with broken photo/PDF links, targeted rsync of those subfolders will fix it.

- **Prior work still live** *(unchanged this session)* — site calendar, ACF on Education + 8 hero-only templates, editor auto-reload, promotion fixes, 2627 hero animation desktop PSD, slideshow on show pages, decade-archive button pattern, 144 shows with photo galleries (2,494 photos / 610 MB), archival pre-2010 show pages, Cityline interviews, splash → home wipe baseline, mobile drawer/header z-index, tickets consolidation, PDF target=_blank, Ludus volunteer link.

## Outstanding work (highest priority first)

1. **Mobile layered hero — finish the season** *(in progress this session)* — Outsider iteration ongoing; Arsenic and Old Lace mobile PSD ready to test next; 5 more shows (Hallmarked, Dot, Urinetown, The Importance of Being Earnest, The Play That Goes Wrong) still need mobile PSDs designed and extracted. Per-show workflow:
   - Designer creates a portrait mobile PSD (recommended canvas **1080×1920 / 9:16**, with each animated layer's PNG extending past the canvas in the direction it slides from — see "Hero PSD design spec" below).
   - Run `C:/temp/extract_outsider_mobile.py` (rename per show — uses 50% horizontal + 12% vertical bleed envelope) to write `hero-layers/<slug>/mobile/` PNGs + `composite.jpg`.
   - Test on phone via Local Sites' Live Link.
   - Each new show should be a copy-paste of the Outsider workflow once we lock the iteration on Outsider/Arsenic.
2. **Test layered hero auto-rotation for all 7 shows** — verify every show in 2026-27 has its `hero-layers/<slug>/` populated (desktop AND mobile/) and that the auto-rotation works as each show closes (use `tlt_today()` date override to fast-forward). Details in LAUNCH_CHECKLIST.md → "Show transitions (layered animated heroes)". A broken hero is the first thing every visitor sees.
3. **ACF-ify the rest** — Education got the full treatment. The hero-only ACF on Visit/Off the Shelf/Auditions/Season Tickets/Ticket Info/Donation Request/Press/Job Openings only covers the top eyebrow+title+lede. Body content on these is still hardcoded. Blake's working through these manually to verify Chris can edit each one without code.
4. **Mobile audit for top-traffic pages** — Splash (40% of pageviews) and Home are done. Remaining top 8: Show detail, Auditions, Education, "About the Program" (= Education).
5. **Splash page focal point per image** — currently splash backgrounds use centered `background-position`; subjects get cropped on mobile portrait. Schema currently `show_splash_gallery` = JSON array of URLs. Need to support `[{url: "...", focal: "30% 50%"}]`.
6. **Production hardening** (see LAUNCH_CHECKLIST.md P0/P1) — SMTP, Flamingo, backups, security, search-replace at DNS cutover, redirects.
7. **Sync remaining uploads to Cloudways** — `uploads/productions/`, `uploads/programs/`, `uploads/migrated/` weren't in the initial push (they haven't changed since the trial deploy, but the trial server was torn down). Do this once before DNS cutover so show pages have their photos + programs. Recommended: WinSCP directory-sync (local ↔ remote) is more reliable than `scp -r` for 1000+ files.
8. *(lower priority, post-launch is fine)* **Improve archive Photos-button matching** — decade pages auto-render [📷 Photos]/[📄 Program] per show. Some don't match because the Job-B folder title differs from the real show name (e.g. folder "Cole Porter" vs listed "Red, Hot & Cole"). Fix by renaming those show records or adding aliases. ~20/26 match in 2000-2010, ~11/13 in 1990-2000.
9. *(lower priority)* **Review the 52 archival pages** — auto-derived titles/dates; spot-check. Known rough edges: a few slugs with apostrophes (`broadway-s-fabulous-fifties`), `Once Upon A Mattress` keeps folder casing, ~34 show a season label rather than exact dates. Re-run `wordpress/import/create_archive_shows.py` (idempotent) after tweaks.
10. *(lowest priority)* **~150 more pre-1996 / unmatched shows** — Job B covered the 61 real-show folders that had a clear title+season. Older/sparser `TLT Photos` folders remain.

## Hero PSD design spec (the crop must not cut off the animation)

The single most important rule for hero PSDs: **every animated layer needs the subject positioned at its final composed location, with extra transparent canvas extending past the visible viewport on the side it slides in from.** If the layer is exactly canvas-sized, sliding it in via CSS reveals the IMG box's hard left/right/top/bottom edge — which looks like a hard line scrolling across the hero. The bleed past the canvas is what hides that edge.

**Canvas:**
- **Desktop:** `1920 × 1080` (16:9 landscape). Matches the rendered hero aspect on most monitors.
- **Mobile:** `1080 × 1920` (9:16 portrait). Standard mobile video/story aspect.

**Per-layer bleed rules (transparent canvas extending past the visible composition):**

| Layer slides | Where it needs bleed | Minimum bleed |
|---|---|---|
| **Background** (fades only) | nowhere | none |
| **Person / man** (from right) | extend canvas RIGHT past the figure | 25-30% of canvas width |
| **Front mics** (from left) | extend canvas LEFT | 25-30% of canvas width |
| **Back mics** (rise from below) | extend canvas BELOW | 15-25% of canvas height |
| **Podium** (rise from below) | extend canvas BELOW | 15-25% of canvas height |
| **Overlays** (fades only) | nowhere | none |

When the designer builds the PSD, each layer's natural rectangle should EXCEED the canvas in the listed direction. The PSD canvas itself stays at `1080×1920` (mobile) or `1920×1080` (desktop) — only the individual layer bboxes extend past. (Photoshop's "Reveal All" or canvas-extend feature works.)

The extraction script (`C:/temp/extract_outsider_mobile.py`) composites each layer onto a viewport that's bigger than the canvas (currently `(-1350, -523, 4050, 4792)` for The Outsider's `2700×4269` canvas — 50% horizontal bleed each side, ~12% vertical bleed each side) so the off-canvas content survives extraction. **If layer bleed is missing from the PSD, the extraction can't invent it.**

**CSS mirrors this contract:** the mobile `@media` block in `style.css` scales layers to natural-aspect height-fit and reduces slide distances to 20% (X) and 5% (Y) — calibrated so the IMG-box edge stays inside the bleed envelope during animation. If a new layer's slide direction or bleed amount differs, adjust the `--layer-from` values for that show.

## Domain / hosting status

- Cloudways: paid subscription active. Update `deploy/cloudways.json` with the live server creds and re-test the deploy toolkit before DNS cutover.
- **TODO — apply tax exemption to the Cloudways account.** TLT is a nonprofit; submitting tax-exempt info should drop sales tax off the hosting bill. Blake to supply the tax-exempt cert/details when handy.
- DNS cutover: not done; tacomalittletheatre.com still on Squarespace. `.com` is registered/DNS at Squarespace — that's where the A/CNAME repoint happens. `.org` and `.net` are on GoDaddy and auto-forward to `.com` (no action needed unless we want them to hit the new host directly).
- **Launch is happening sooner than the close of Bedroom Farce** (was originally planning to wait until 2026-27 season opens). For launch: static non-animated hero for Bedroom Farce; layered animated heroes kick in with the 2026-27 season.
- Email: Google Workspace on TLT's own domain; will NOT be touched by DNS changes (MX/SPF/DKIM stay)

## Quick recap of analytics from Squarespace (Jan 2026 YTD)

- 98K visits, 180K pageviews
- iOS dominates (~50% of traffic)
- Mobile is the #1 browser bucket
- Direct traffic 50%, Google 39%, Bing/FB minimal
- Cover/splash gets 40% of all pageviews — mobile experience there is highest priority

## Workflow notes

- **Git push/pull is pre-authorized.** When Blake says "push to git", commit any outstanding work with sensible messages and push to origin. When he says "pull from git", run `git pull`. Don't re-confirm routine cases. STILL pause and flag if: a new secret/credential would be committed, there's a merge conflict, or a push would need a force-push. Repo: github.com/brytheatrics/tlt-website.
- **`tlt-manager/` is a SEPARATE project** that happens to live in this working dir (Google Apps Script: Ludus sync/casting/bios). It is gitignored and contains a Google service-account key — NEVER commit anything under it to the website repo.
- **Cloudways deploy toolkit is in `deploy/`** (`export_db.py`, `sync_down.py`, `DEPLOY.md`). `deploy/cloudways.json` is gitignored (per-machine credentials) and currently points at the live paid server. **After every code push, purge the Cloudways Full Page Cache from the dashboard** — WP-CLI cache flush does NOT clear it and code changes will appear stale without a manual purge.
- User has two computers (home + work) and wants Claude Code to work from either. Plan: keep this repo in Git, Cloudways for DB/uploads, MDs synced via Git.
- User prefers shorter responses. Don't over-explain. Don't run diagnostic bash for pure-question conversations.
- DB is on Local Sites' MySQL; not in repo. Schema/content sync to Cloudways happens during deploy (one-time export-import).
- Image files referenced by templates may need to be present in `/wp-content/uploads/` for pages to render correctly.
