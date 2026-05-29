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

## Recent work (most recent session)

- **Cityline interview integration** — added `show_cityline_url` field; renders on homepage + show detail. Bulk-imported 43 historical Cityline interviews from the [Cityline playlist](https://www.youtube.com/playlist?list=PLHN0JO4EyqcdNYjDZyvHnLrl1heaNFuwq) to their matching shows
- **Mobile fixes** — built proper hamburger drawer; fixed hero image cropping on portrait viewports (object-position 50% 70%); fixed homepage promo rows stacking inconsistently on mobile
- **Header z-index bug** — the hidden site-search form was overlapping the Home menu link in the mobile drawer at z-index 100, eating taps. Fixed with explicit `.site-search[hidden] { display: none; }` rule
- **Tickets consolidation** — `/tickets/` is now the full ticket-info page (template page-ticketinfo.php). `/ticketinfo/` is trashed. Submenu has "Ticket Information" (→ `/tickets/`), "Single Tickets" (→ ludus.com), "Season Tickets", "Gift Cards", "Plan Your Trip"
- **Splash → home wipe** — moved wipe injection from page-home.php DOMContentLoaded to header.php right after wp_body_open() so the header doesn't flash before the cover lands
- **Built/rebuilt page templates** — `/season-tickets/`, `/job-openings/`, `/press/`, `/off-the-shelf/`, `/donation-request/`, `/visit/`, `/board-and-staff/`, `/contact/`
- **PDF program links** — added `target="_blank"` so mobile Safari opens inline instead of downloading
- **Volunteer link** — points externally to https://tlt.ludus.com/volunteer

## Outstanding work (highest priority first)

1. **Splash page focal point per image** — currently splash backgrounds are centered; subjects get cropped on mobile portrait. Schema currently is `show_splash_gallery` = JSON array of URLs. Need to support `[{url: "...", focal: "30% 50%"}]` and apply per-image background-position.
2. **Mobile audit for top traffic pages** — Splash takes 40% of all pageviews. Home next. Show detail pages combined account for another big chunk. Auditions and Education round out the top 8.
3. **Production photos import from `\\TLT-SERVER\Marketing\`** — Chris's photographers already export web-sized JPEGs in `JPEG/` subfolders. Real total after import would be ~700 MB. Folder naming varies across eras (Production Photos vs Press Photos vs Production Stills, etc.). User confirmed: photo consent likely fine, plus a "request removal" line in footer.
4. **Set up Cloudways** — user is in process of signing up for Cloudways Micro ($14/mo). After signup: push migrated code + DB to Cloudways temp URL, test there freely until DNS cutover.
5. **Production hardening** (see LAUNCH_CHECKLIST.md P0/P1)

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
