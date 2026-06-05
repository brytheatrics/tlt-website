# CLAUDE.md — Session Handoff

Read this first when starting a new session. It's the fastest way to get oriented.

## Project at a glance

TLT website migration: Squarespace → self-hosted WordPress.

- **Live site (Squarespace):** https://www.tacomalittletheatre.com — still receiving real traffic
- **Local dev (Local Sites):** http://tlt.local — full migrated site, where all work happens
- **Production host (planned):** Cloudways DigitalOcean Micro ($14/mo) — not yet provisioned
- **Domain:** GoDaddy; will repoint DNS to Cloudways at cutover

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

## Recent work (most recent session — May 29 2026)

- **Site calendar (NEW feature)** — `/calendar/` page (`page-calendar.php`): month grid + agenda, prev/next month nav via pretty URLs `/calendar/YYYY-MM/` (rewrite rule in `includes/calendar.php`; `?ym=` still works as fallback), color-coded by type. Data layer in theme `includes/calendar.php` (`tlt_calendar_entries($from,$to)`) merges three sources: show **performances** (`show_performances` meta), show **auditions** (`show_audition_schedule` meta), and **events** (`tlt_event`). Calendar icon added to the header next to search (`header.php`, `.site-cal-link`). Uses `tlt_today()` so it respects the pre-launch date override. All 7 **2026-27 shows have real performances + auditions** imported from the Callboard CSV via `wordpress/import/import_callboard_dates.py` (imports only `Performance` + `Auditions` rows; skips internal rehearsal/tech/meeting rows). Auditions default to TLT location; Outsider's Tuesday session overridden to STAR Center. Re-run the importer if the Callboard CSV updates (it matches names within the **2026-2027 season only**, so revivals like The Play That Goes Wrong — which also has a 2023-24 record — don't steal the match). Auditions link to Casting Manager (`TLT_AUDITION_SIGNUP_URL` in `calendar.php`, per-show override via `show_audition_signup_url`). Grid badges show just the name (colour = type); the agenda spells out "Performance:" / "Auditions:". **Education Performances** (category `education_performance`, distinct orange badge — reusable for camp showcases, fall class shows, etc.): summer-camp-2026 lineup (Oliver! JR., High School Musical JR., Trolls JR., Xanadu JR.) seeded as `tlt_event`s via `wordpress/import/import_summer_camp_2026.py` (sourced from the `/summer-camp-2026/` page; idempotent, clears prior `camp-2026-*` events first).

- **ACF-ified hardcoded templates** — Education got the full treatment (5 tabs: Hero / Why / Programs / Scholarships / Policies, with repeaters for programs and policies). Visit / Off the Shelf / Auditions / Season Tickets / Ticket Info / Donation Request / Press / Job Openings got "hero-only" ACF (eyebrow pill + title + lede). Pattern: helpers `tlt_register_hero_acf_group()` and `tlt_hero_field()` in `includes/acf-page-templates.php`. Defaults baked in so pages render identically without Chris editing.
- **Editor auto-reload after template switch** — when Chris picks an ACF-managed template and saves, page reloads automatically so the ACF panel appears (was: manually navigate back to Pages → re-open)
- **Promotion fixes** — `promo_cta_url` field type changed from `url` (rejected `/board-and-staff/`) to `text`; date filter normalizes both `Ymd` (admin-saved) and `Y-m-d` (legacy/seeded)
- **2627 posters extracted + wired** — all 7 shows in the 2026-2027 season have their poster PSDs extracted to `/wp-content/uploads/posters/2627/<slug>.jpg` and linked via `show_hero_image_url` + `_thumbnail_external_url`
- **2627 hero animation refreshed** — new 6-layer PSD with added "Overlays" layer, renumbered so Overlays sits on top. Files at `/wp-content/uploads/hero-layers/the-outsider/`, old layers backed up to `the-outsider.bak/`
- **Show photo gallery → slideshow** — `single-tlt_show.php` Production Photos section now renders as a slideshow (arrows, dots, counter, keyboard, swipe) instead of a wall of thumbnails
- **Pre-2010 show mockup** — `/shows/1776-0506/` is the first proof-of-concept for option-1 (per-show pages for ancient shows). Photo gallery from `\\TLT-SERVER\TLT Photos\0506 Production Photos\1776`, program PDF linked
- **Decade-archive button pattern** — `/2000-2010/` has 2005-06 mocked with `[📷 Photos] [📄 Program]` buttons per show (only when the source exists). `.archive-list` + `.archive-btn` CSS lives in theme `style.css`. Pattern ready to roll out to other seasons/decades
- **Local ACF install fix** — ACF wasn't installed locally; `active_plugins` serialized length was also wrong (would silently break ALL plugins). Both fixed.
- **Production photos imported (Job A)** — `wordpress/import/import_production_photos.py` copied/resized photos for all **92** matched DB shows → `/wp-content/uploads/productions/<slug>/NN.jpg` (cap 20, Marketing JPEG copied verbatim, TLT Photos resized to 1600px), set `show_photo_gallery` meta. 1,600 photos, 428 MB.
- **Archival show pages created (Job B)** — `wordpress/import/create_archive_shows.py` created **52** new `tlt_show` pages (1776 pattern) for shows that had photos on `\\TLT-SERVER\TLT Photos` but no DB record, going back to Carnival (1966). Derives/dedups titles, assigns `tlt_season` taxonomy, imports photos (894 total). Dates: real month-span when the folder names a month, else a **season label** (`show_season_label`, e.g. "2006–2007 Season"). `single-tlt_show.php` now falls back to that label when exact dates are absent. 20 non-show folders (Education, galas, Off the Shelf, ClubTLT, promo) deliberately skipped. **Total now: 144 shows with galleries, 2,494 photos, 610 MB.**
- **Fixed mis-matched galleries** — the photo-report matcher linked some folders to a same-titled but DIFFERENT production (by title, ignoring season). Found 5 live cases via an authoritative audit (`build_sources()` source-season vs show open-year): Arsenic 2026 ← Arsenic 2001-02, Annie 2010 ← Annie Get Your Gun 2004-05, Complete Works 2013 ← 2002-03, Six Dance 2012-13 ← 2009-10, Scrooge 2014 ← 2018 (already correct on scrooge-the-musical). `wordpress/import/fix_mismatched_galleries.py` cleared the wrong galleries and re-homed the photos to 4 new archival records (`*-0102/0405/0203/0910`). If more photos look wrong on a show, re-run that audit.
- **Decade-archive rollout + /shows/ cutoff** — `includes/archive-decades.php` (new) parses each decade post's `<h2>season</h2><ul>` body and re-renders every season as the 2005-06-style `archive-list` with [📷 Photos] (links to the `tlt_show` page when one with photos exists) + [📄 Program] buttons. Wired into BOTH `single.php` (decade pages) AND the **Earlier Seasons** section of `archive-tlt_show.php` on `/shows/` (replaced the old compact name→PDF bullet grid). Decade pages with year-headed bullet content (1918-2010) render from the post body; modern decades whose body has no season bullets (e.g. 2010-2020) fall back to `tlt_decade_record_sections()` which builds the per-season lists from `tlt_show` records. `/shows/` card grids use the **has-poster** rule (+ pre-2010 season cutoff). Matching is season-start-year + normalized title (+ containment fallback) — ~34 of the pre-2010 galleries currently match a decade-list name; the rest miss on title differences (folder name vs listed name). The `.archive-list`/`.archive-row` CSS was un-scoped from `.page-content` so it styles correctly in both `.page-content` (decade pages) and `.container` (/shows/). Button icons are inline Material SVGs (`fill="currentColor"`, in `tlt_render_archive_list`). NOTE: migrated decade posts open with empty Squarespace wrapper `<div>`s whose closing tags get dropped during section extraction — `tlt_parse_decade_body` now blanks/​balances the intro so those don't leak and constrain the footer.
- **2000-2010 visibility fixed** — it was missing from Past Decades (`page-prior-seasons.php`) and Earlier Seasons because its `_migration_legacy_url` is `/blog/tag/2000-2010` while the templates only matched `/blog/2015/`. Broadened both regexes to `/blog/(2015|tag)/`.
- **Duplicate decade posts deduped** — 4 decade/year summaries had two published copies with identical slugs (1918-1930, 1940-1950, 1990-2000, 2012-2013), which made `single.php` render each season twice. Trashed the redundant copy (kept the `/blog/2015/` canonical) and appended `__trashed` to the trashed slug so the canonical owns the URL (raw-SQL trash without the rename 404s the page).
- **Import gotchas fixed** — three classes, both scripts now handle them:
  1. macOS `._` AppleDouble files (sort first, break PIL) → skipped.
  2. Photos nested in subfolders (`corrected/`/`originals/`/by-date) → both scripts walk the tree.
  3. **Wrong-content folders** — the report's `best_set` sometimes pointed at a show's `Headshots\JPEG` instead of `Production Photos\JPEG` (Luck of the Irish, Rocky, Seussical, A Christmas Story, A Doll's House Part 2 all pulled headshots originally). Fixed: `list_images` now SCORES every image folder under the show root — `Production Photos`/`Production Stills` +100, press/release +40, jpeg/web +10, and `Headshots`/`Bios`/`Lobby`/`Preview`/`Poster`/`Audition` −1000 — and picks the best. Job A passes the marketing show ROOT (not best_set) so the scorer can choose. Audited all 144: 0 wrong-content folders remain. Worth reusing for any future server import.
- **Cityline interview integration** *(prior)* — `show_cityline_url` field; 43 historical interviews bulk-imported
- **Splash → home wipe** *(prior)* — moved injection from page-home.php DOMContentLoaded to header.php right after wp_body_open()
- **Mobile drawer + header z-index fix** *(prior)*
- **Tickets consolidation** *(prior)* — `/tickets/` is the full ticket-info page; `/ticketinfo/` trashed
- **PDF links** *(prior)* — `target="_blank"`
- **Volunteer link** *(prior)* — external to tlt.ludus.com

## Outstanding work (highest priority first)

1. *(resolved)* **The post-2010 photo-only shows** — RULE NOW: a show appears as a card on `/shows/` only if it has poster art (`archive-tlt_show.php` season loop skips poster-less shows + keeps the pre-2010 season cutoff). This hides the 8 youth/summer galleries (Godspell JR, Schoolhouse Rock JR, Aunt Maggity, Fractured Fairytales, Grease, Grunch, Midsummer, Murder at the Academy Awards) while keeping every real show. The Laramie Project was promoted to a full card (poster `uploads/posters/1213/the-laramie-project.jpg` + program `1213-The-Laramie-Project-Program.pdf` + 20 photos). The 8 hidden shows remain reachable by direct `/shows/<slug>/` URL only — not linked from /shows/ or the 2010-2020 decade page. Genuinely-missing programs (no file anywhere): Joy Luck Club (2012-13), Putnam County Spelling Bee (2011-12).
2. **Improve archive Photos-button matching** — decade pages now auto-render [📷 Photos]/[📄 Program] per show (`includes/archive-decades.php`), matching decade-list names to `tlt_show` records by season + normalized title (+ containment fallback). Some don't match because the Job-B folder title differs from the real show name (e.g. folder "Cole Porter" vs listed "Red, Hot & Cole"). Fix by renaming those show records or adding aliases. ~20/26 match in 2000-2010, ~11/13 in 1990-2000.
3. **Review the 52 archival pages** — auto-derived titles/dates; spot-check. Known rough edges: a few slugs with apostrophes (`broadway-s-fabulous-fifties`), `Once Upon A Mattress` keeps folder casing, ~34 show a season label rather than exact dates. Re-run `wordpress/import/create_archive_shows.py` (idempotent) after any tweaks.
3. **~150 more pre-1996 / unmatched shows** — Job B covered the 61 real-show folders that had a clear title+season. Older/sparser `TLT Photos` folders remain. Lower priority.
4. **Cloudways trial is DEAD** — as of 2026-05-29 the trial server (`deploy/cloudways.json`) no longer resolves in DNS and is unreachable on ports 80/22 — it was torn down, not just expired. Those creds are stale. Re-provision a fresh host (Cloudways paid or other) and update `deploy/cloudways.json` before deploy. Nothing local lost (theme/plugin/scripts in repo, DB on Local Sites, uploads on disk).
5. **Mobile audit for top-traffic pages** — Splash (40% of pageviews) and Home are done. Remaining top 8: Show detail, Auditions, Education, "About the Program" (= Education).
6. **Splash page focal point per image** — currently splash backgrounds use centered `background-position`; subjects get cropped on mobile portrait. Schema currently `show_splash_gallery` = JSON array of URLs. Need to support `[{url: "...", focal: "30% 50%"}]`.
7. **ACF-ify the rest** — Education got the full treatment. The hero-only ACF on Visit/Off the Shelf/Auditions/Season Tickets/Ticket Info/Donation Request/Press/Job Openings only covers the top eyebrow+title+lede. Body content on these is still hardcoded. Decide which to deepen based on Chris's editing needs.
8. **Production hardening** (see LAUNCH_CHECKLIST.md P0/P1) — SMTP, Flamingo, backups, security, search-replace at DNS cutover.

## Domain / hosting status

- Cloudways: original trial server is DEAD (deprovisioned ~2026-05-29; see Outstanding #4). User re-figuring out hosting. Deploy to a fresh host once provisioned.
- **TODO — apply tax exemption to the Cloudways account.** TLT is a nonprofit; submitting tax-exempt info should drop sales tax off the hosting bill. Blake to supply the tax-exempt cert/details (doesn't have them handy yet). Do it when setting up the paid subscription.
- DNS cutover: not done; tacomalittletheatre.com still on Squarespace
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
- **Cloudways deploy toolkit is in `deploy/`** (`export_db.py`, `sync_down.py`, `DEPLOY.md`). Site pushed to a Cloudways temp URL (see `deploy/cloudways.json`, gitignored). Started on a 3-day trial (~expires 2026-05-31) — verify the server still exists if working after that.
- User has two computers (home + work) and wants Claude Code to work from either. Plan: keep this repo in Git, Cloudways for DB/uploads, MDs synced via Git.
- User prefers shorter responses. Don't over-explain. Don't run diagnostic bash for pure-question conversations.
- DB is on Local Sites' MySQL; not in repo. Schema/content sync to Cloudways happens during deploy (one-time export-import).
- Image files referenced by templates may need to be present in `/wp-content/uploads/` for pages to render correctly.
