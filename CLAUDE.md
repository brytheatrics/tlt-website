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

## Page templates (in `wordpress/themes/tlt/`)

`page-home.php`, `page-splash.php`, `page-visit.php`, `page-contact.php`, `page-board-and-staff.php`, `page-auditions.php`, `page-ticketinfo.php`, `page-season-tickets.php`, `page-donation-request.php`, `page-press.php`, `page-press-post.php`, `page-job-openings.php`, `page-job-posting.php`, `page-off-the-shelf.php`, `page-education.php`, `page-prior-seasons.php`, `page-post-listing.php`, `page-ticketing.php`, `page-designed.php`, `page-campaign.php`, `page-styleguide.php`

## Recent work (most recent session — May 29 2026)

- **ACF-ified hardcoded templates** — Education got the full treatment (5 tabs: Hero / Why / Programs / Scholarships / Policies, with repeaters for programs and policies). Visit / Off the Shelf / Auditions / Season Tickets / Ticket Info / Donation Request / Press / Job Openings got "hero-only" ACF (eyebrow pill + title + lede). Pattern: helpers `tlt_register_hero_acf_group()` and `tlt_hero_field()` in `includes/acf-page-templates.php`. Defaults baked in so pages render identically without Chris editing.
- **Editor auto-reload after template switch** — when Chris picks an ACF-managed template and saves, page reloads automatically so the ACF panel appears (was: manually navigate back to Pages → re-open)
- **Promotion fixes** — `promo_cta_url` field type changed from `url` (rejected `/board-and-staff/`) to `text`; date filter normalizes both `Ymd` (admin-saved) and `Y-m-d` (legacy/seeded)
- **2627 posters extracted + wired** — all 7 shows in the 2026-2027 season have their poster PSDs extracted to `/wp-content/uploads/posters/2627/<slug>.jpg` and linked via `show_hero_image_url` + `_thumbnail_external_url`
- **2627 hero animation refreshed** — new 6-layer PSD with added "Overlays" layer, renumbered so Overlays sits on top. Files at `/wp-content/uploads/hero-layers/the-outsider/`, old layers backed up to `the-outsider.bak/`
- **Show photo gallery → slideshow** — `single-tlt_show.php` Production Photos section now renders as a slideshow (arrows, dots, counter, keyboard, swipe) instead of a wall of thumbnails
- **Pre-2010 show mockup** — `/shows/1776-0506/` is the first proof-of-concept for option-1 (per-show pages for ancient shows). Photo gallery from `\\TLT-SERVER\TLT Photos\0506 Production Photos\1776`, program PDF linked
- **Decade-archive button pattern** — `/2000-2010/` has 2005-06 mocked with `[📷 Photos] [📄 Program]` buttons per show (only when the source exists). `.archive-list` + `.archive-btn` CSS lives in theme `style.css`. Pattern ready to roll out to other seasons/decades
- **Local ACF install fix** — ACF wasn't installed locally; `active_plugins` serialized length was also wrong (would silently break ALL plugins). Both fixed.
- **Production photo inventory complete** — 92 of 112 DB shows have photos in `\\TLT-SERVER\Marketing\` and/or `\\TLT-SERVER\TLT Photos\`. Full match report at `C:/temp/full_photo_report.json`. **Import script NOT YET WRITTEN.** Decision pending: photo cap per show (default 20).
- **Cityline interview integration** *(prior)* — `show_cityline_url` field; 43 historical interviews bulk-imported
- **Splash → home wipe** *(prior)* — moved injection from page-home.php DOMContentLoaded to header.php right after wp_body_open()
- **Mobile drawer + header z-index fix** *(prior)*
- **Tickets consolidation** *(prior)* — `/tickets/` is the full ticket-info page; `/ticketinfo/` trashed
- **PDF links** *(prior)* — `target="_blank"`
- **Volunteer link** *(prior)* — external to tlt.ludus.com

## Outstanding work (highest priority first)

1. **Run the production photos import** — inventory done, script not written. Source data: `C:/temp/full_photo_report.json` (92 matched shows). Plan: prefer `Marketing\<show>\Production Photos\*\JPEG\` (already web-sized) → fall back to `TLT Photos\<season> Production Photos\<show>\` (resize to 1600px during copy). Cap ~20 photos per show → ~500 MB total. Copy to `/wp-content/uploads/productions/<slug>/01.jpg…`, set `show_photo_gallery` meta. Slideshow already wired in `single-tlt_show.php`.
2. **Roll out decade-archive button pattern** — only `/2000-2010/` 2005-06 block is done. Pattern: walk each `<li>` in each decade page (1900-1910 through 2000-2010), detect `.pdf` link + check for any matching `tlt_show` record with photos, render `[📷 Photos] [📄 Program]` buttons as appropriate. CSS lives in style.css (`.archive-list` / `.archive-btn`).
3. **Create show records for pre-2010 shows that have photos** — 1776 is the proof of concept (`/shows/1776-0506/`). About ~250 more shows from `TLT Photos\<season> Production Photos\<show>\` folders going back to 1996. Sparse metadata (folder names only have title), but galleries become browsable + Google-indexable.
4. **Cloudways trial expiration** — trial expires ~2026-05-31. Server credentials in `deploy/cloudways.json`. Upgrade to paid plan before then OR re-provision after.
5. **Mobile audit for top-traffic pages** — Splash (40% of pageviews) and Home are done. Remaining top 8: Show detail, Auditions, Education, "About the Program" (= Education).
6. **Splash page focal point per image** — currently splash backgrounds use centered `background-position`; subjects get cropped on mobile portrait. Schema currently `show_splash_gallery` = JSON array of URLs. Need to support `[{url: "...", focal: "30% 50%"}]`.
7. **ACF-ify the rest** — Education got the full treatment. The hero-only ACF on Visit/Off the Shelf/Auditions/Season Tickets/Ticket Info/Donation Request/Press/Job Openings only covers the top eyebrow+title+lede. Body content on these is still hardcoded. Decide which to deepen based on Chris's editing needs.
8. **Production hardening** (see LAUNCH_CHECKLIST.md P0/P1) — SMTP, Flamingo, backups, security, search-replace at DNS cutover.

## Domain / hosting status

- Cloudways account: in progress (user signing up now)
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
