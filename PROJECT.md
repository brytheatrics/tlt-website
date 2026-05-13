# Tacoma Little Theatre — Website Migration

Migration project: Squarespace → self-hosted WordPress.

## Why we're doing this

- Current site: [tacomalittletheatre.com](https://www.tacomalittletheatre.com) on Squarespace **Basic plan ($19/mo)**.
- Squarespace blocks custom code on Basic. Unlocking it requires the **Business plan ($35/mo)** — a $16/mo bump that isn't worth it.
- We want to embed custom apps (e.g. the existing **Google Apps Script callboard** behind a password) without paying Squarespace for the privilege.
- Bonus: shows are currently buried under `/blog/[slug]` with tag filters — invisible to Google as events. Migration is a chance to restructure show pages with proper URLs (`/shows/[slug]`) and Event schema markup.

## Where we're going

**Stack:**
```
GoDaddy (domain, unchanged)  →  Cloudways (~$14/mo host)
                              →  WordPress (self-hosted, free)
                              →  Custom theme + custom post types + flex blocks
                                 (NO page builder — see ARCHITECTURE.md)
```

**Not changing:** Google Workspace email (`@tacomalittletheatre.com`) — different DNS records, won't be touched.

**Cost:** ~$14/mo total, vs. current $19/mo Squarespace and the $35/mo we'd need for code access.

> 📐 **Engineering plan:** see [`_planning/ARCHITECTURE.md`](_planning/ARCHITECTURE.md) for the full architecture (templates, post types, automation rules, etc.).
> 📋 **Decision log:** see [`_planning/decisions.md`](_planning/decisions.md) for the running log of decisions and why.
> 📊 **Template inventory:** see [`_planning/template_inventory.md`](_planning/template_inventory.md) for which templates exist, what's missing, build order.

## Constraints / decisions made

- **WordPress.org (self-hosted), not WordPress.com.** WP.com has the same code-restriction problem as Squarespace.
- **NO page builder.** Custom theme with hard templates + flex-content block library + Designed Page template. Chris doesn't need drag-drop; he needs structured forms that produce beautifully-designed pages. Rationale in [`_planning/ARCHITECTURE.md`](_planning/ARCHITECTURE.md).
- **Custom post types** for Shows, Team, Promotions (universal banner system), News. Pages assemble themselves from these data sources rather than being hand-edited.
- **Date-driven content.** Every "this needs to change at time X" task is a date field. Required, not optional. Eliminates the "Chris forgot to take this down" problem.
- **Customizer for content settings only** (logo, address, mission text, social links). Brand controls (colors, fonts) live in code as CSS variables — not exposed in admin UI. Chris is Administrator but can't accidentally drift the brand because the controls don't exist for him to click.
- **Show post type covers Off the Shelf and Murder Mystery Dinners** as program type variants, not separate post types.
- **Single auditions hub page**, not per-show audition pages.
- **Splash auto-shows** when currently-running show has splash photos; auto-hides otherwise.
- **Password-protected pages** for internal tools (callboard, etc.) — built-in WP feature.
- **TLT-SERVER stays the master photo archive (155 GB).** Website only shows a curated 6-12 hero photos per show. ~400 MB total site-wide for photos, fits any cheap hosting plan.
- **TLT Videos are archive-only** (licensing) — never linked or uploaded to public site.
- **Read-only on TLT-SERVER, no deletes.** Boss owns the server setup; lost data is a serious problem.
- **Git repo exists:** github.com/brytheatrics/tlt-website. Cross-computer dev via git + Drive + Windows junctions.

## Site inventory & migration scope

After analyzing inbound links and classifying the 459 "non-show" posts, the real scope is much larger than initially thought. Most of the 459 are real, distinct content — not orphans, not duplicates.

| Bucket | Count | Plan |
|---|---|---|
| Core pages | 17 | Rebuild as proper WP pages |
| Modern shows (`/blog/YYYYYYYY/slug`) | ~111 real + 5 missed-matches | Show post type |
| Old shows (`/blog/2015/`, `/blog/2016/`) | ~256 | Show post type, same as modern |
| Board profiles (`/blog/board/...`) | 8 | New "Staff" post type, one per person |
| ClubTLT shows | 6 distinct | Show post type with "ClubTLT" tag |
| Off the Shelf staged readings | several incl. suffixed | Show post type with "Off the Shelf" tag |
| Audition posts | 43 | Skip — outdated. 301 redirect to `/auditions/` |
| Education events | 31 | Selective — keep last 2 years |
| Fundraising | 13 | Selective — keep recent |
| Decade summary pages | 12 | Migrate to `/history/` archive |
| COVID notices | 3 | Skip |
| Truly orphaned blanks | 3 | Skip (`/blog/category/98th+Season`, `/Auditions`, `/Sticky`) |
| Squarespace category indexes | 17 | Skip — auto-generated, replaced by WP archive pages |

**Total to migrate: ~440-450 pages** — bulk-importable from scraped HTML, not hand-rebuilt.

### IMPORTANT: Squarespace suffixed slugs are NOT duplicates

URLs like `/blog/board/kay-emp99`, `/blog/2017/cyrano-...-frlk2-z29c7`, `/blog/2018/sexiest-9glhh` are **distinct content**. Chris (boss) duplicates a page as a template, changes the content, but Squarespace auto-appends a random suffix because the slug is similar. Each suffixed URL is a different person/show/reading. **Never merge them during migration.** The redirect map (`url_redirect_map.csv`) preserves these as distinct targets.

### Inbound link analysis

- 593 total URLs scanned
- Only **3 truly orphaned** (no inbound links from anywhere): the empty Squarespace category pages above
- Old shows from `/blog/2015/` and `/blog/2016/` are still reachable via Squarespace's `/blog/category/` and `/blog/tag/` index pages — not from main nav, but Google still has them indexed

## TLT-SERVER inventory (read-only walk, completed)

| Folder | Files | Size | Use |
|---|---|---|---|
| TLT Photos | 43,098 | **155.86 GB** | Master production photo archive — keep on server, curate for web |
| TLT Programs | 549 | 0.46 GB | Show programs, 1925-current. Already confirmed bit-identical to website's PDFs |
| TLT Archive | 2,376 | 2.74 GB | Historical content by decade; TLT History.pdf for /history page |
| Marketing | 15,115 | 242.45 GB | Logos pulled, rest stays on server |
| TLT Videos | 14,360 | **2,265 GB** (2.27 TB) | Archive-only per licensing — DO NOT post |
| Seasons | 66 | 0.14 GB | |
| Special Events | 603 | 6.74 GB | |
| History | 2 | <0.01 GB | TLT History.pdf already pulled |

## Progress so far

- [x] Site sitemap pulled, URLs inventoried by category → `scrape/all_urls.txt`
- [x] **Core 17 pages scraped** → `scrape/pages/` (2.6 MB, 18 HTML files)
- [x] **Core images deduped** → `scrape/images_dedup/` (138 unique photos, 51 MB)
- [x] **116 show pages scraped** → `scrape/pages_shows/`
- [x] **Show images deduped** → `scrape/images_shows_dedup/` (229 unique, 95 MB; from 964 raw files = 226 MB)
- [x] **459 other blog posts archived** → `scrape/pages_other_blog/` (HTML only, no images)
- [x] **TLT-SERVER inventoried** → `tlt_server_inventory.json` (read-only, no writes)
- [x] **Marketing/Logos pulled** → `assets/logos/` (43 MB)
- [x] **TLT History.pdf + TLT in a text book.pdf pulled** → `assets/history/`
- [x] **Design tokens extracted** → `DESIGN_TOKENS.md`
  - Brand font: **Montserrat** (Google Fonts)
  - Body font: Helvetica Neue / Arial fallback
  - Brand color: #b8252f (TLT red, derived from logo)
  - Squarespace base palette: #fff / #272727 / #000 / #222 / #3e3e3e
- [x] **Show → server folder mapping** → `show_to_server_map.json`
  - 116 website shows → TLT-SERVER folders
  - 57 strong matches (≥0.8 score), 13 weak, 46 unmatched (mostly duplicate URL slugs and education events)
- [x] **Hero photos identified per show** → `show_hero_photos.json` (116/116, 100% capture)
- [x] **PDF inventory across website** → `pdf_inventory.json`
  - 554 PDFs found on website
  - **495 (89%) match server copies** in TLT Programs
  - 59 unmatched (bylaws, admin docs not in TLT Programs folder)
- [x] **URL redirect map built** → `url_redirect_map.csv` + `.json` + `redirect_rules_sample.conf`
  - 593 URLs mapped to proposed new structure
  - Old `/blog/YYYYYYYY/slug` → new `/shows/YYYY-YYYY/slug/`
  - Old `/blog/anything-else` → `/news/anything-else/`
- [x] **Static HTML mockup built** → `mockup/`
  - `mockup/index.html` — homepage with current season grid
  - `mockup/shows/davinci.html` — sample show detail page
  - `mockup/css/style.css` — design system using extracted tokens
  - Uses real TLT logo + 2025-26 season hero images
- [ ] Stand up local WordPress (Docker) and build matching theme
- [ ] Write migration scripts: scraped HTML → WP custom post type imports
- [ ] **User action:** sign up for hosting (Cloudways or SiteGround)
- [ ] **User action:** buy Bricks license if going that route
- [ ] Deploy to live host on temporary URL for review
- [ ] **User action:** DNS cutover at GoDaddy

## Triage results (user reviewed all 593 pages)

| Decision | Count |
|---|---|
| Keep | 189 (32%) |
| Trash | 404 (68%) |
| Don't know | 0 |
| Notes left by user | 110 |

**Critical finding from user's notes:** Squarespace's duplicate-slug pattern at TLT is severe — slugs bear NO relationship to content. Examples:
- `/blog/2016/the-underpants-5ypj7-em9m8-l4mjh-y6c62-hkpgb-86s8n` is **Macbeth**
- `/blog/20212022/clue-anzln-g52ct-dwpf6` is **The Luck of the Irish**
- `/blog/2015/office-manager-373ag-9pnef-aclg5-p78cz` is **current Shop Technician**

User's notes in `triage/decisions.json` are the canonical decoder of URL → real content. The migration script (`triage/process_decisions.py`) parses those notes and the page titles to build proper redirects.

**Outputs from processing:**
- [`migration_redirect_map.csv`](migration_redirect_map.csv) — 593 rows: old URL → new URL with real names (e.g. `/blog/2016/the-underpants-5ypj7-...-86s8n` → `/shows/legacy/macbeth/`)
- [`shows_needing_photo_review.csv`](shows_needing_photo_review.csv) — 72 kept shows where photo-folder match was uncertain or missing
- Trashed URLs all redirect to sensible category landing pages (auditions → /auditions/, fundraising → /donate/, etc.) — no 404s

## Resolved with user

- ✅ **Mockup direction**: Approved with tweaks. Hero needs to auto-rotate (currently running → next up). Splash page rebuilt to match current /cover (cycling production photos behind static text).
- ✅ **Hero photo strategy**: Default to Squarespace `og:image` (already captured for all 116 modern shows). Marketing/Chris swaps in better picks per-show as wanted.
- ✅ **Blog strategy**: Migrate ~440 pages, skip ~70 outdated (auditions, COVID, blanks, category indexes). See scope table above.
- ✅ **Squarespace suffixed slugs**: Treated as distinct content, never merged. Redirect map fixed.
- ✅ **The 46 unmatched shows**: ~5 real shows (`bedroom`, `sotto`, `holmes-gaf4k`, `danceharry`, `murdermerlot`) need manual photo-folder mapping. Other 41 are auditions / education / events / internal pages — not real shows.

## Key questions still waiting on user

1. **Confirm `/blog/2015/cabaret`-style URLs are individual show pages** (not season landing pages). Open one in browser to verify before we proceed with bulk Show post type import for the ~256 old shows.
2. **Production photo curation** for archive: nobody currently does this. Default plan = Squarespace's already-chosen hero per show. Acceptable? Or want to leave shows photo-less until someone curates?
3. **Callboard integration**: keep as iframe to existing Apps Script, or rebuild natively in WP later?
4. **Host preference** — Cloudways ($14/mo flat) or SiteGround ($8/mo intro then ~$25/mo)?
5. **Bricks vs Elementor** — Bricks $79 lifetime / cleaner, Elementor free / more popular tutorials.
6. **Show name discrepancies** in `1213 Production Photos` (server has Joy Luck Club, Shakespeare, Laramie; website 2012-2013 lists Sylvia, Night Watch, Six Dance Lessons). Are server folders subscription seasons (Sep-Aug) vs. website calendar years? Or did the website only cover some shows that year?
7. **Tweaks to mockup** — what's missing on the new homepage (donor recognition, sponsors, social, news, etc.)?

## WordPress build delivered

Full migration package now lives in [`wordpress/`](wordpress/). See [`wordpress/DEPLOYMENT.md`](wordpress/DEPLOYMENT.md) for the full how-to. Headlines:

- **Plugin** (`wordpress/plugins/tlt-post-types/`) — registers Show, Team, News post types with structured fields (director, dates, run time, age rec, content warning, ticket URL, program PDF, cancelled flag, program type).
- **Theme** (`wordpress/themes/tlt/`) — custom WordPress theme matching the mockup. Includes auto-rotating homepage hero (queries the next-running show), splash page template (cycling production photos), Show detail with Event schema markup, Team archive split by Staff/Board, all using extracted design tokens (Montserrat + brand colors).
- **WXR import** (`wordpress/import/tlt-migration.wxr.xml`, 1.7 MB) — 189 items pre-built: 119 shows, 46 news posts, 18 pages, 6 team. Drop into WP Admin → Tools → Import.
- **Redirects** — 578 redirects in three formats: Apache `.htaccess`, nginx config, and CSV for the WP Redirection plugin.

**Deployment is one-package, ready to drop into any WordPress install.** The fastest local review path is Local by Flywheel (free) — see DEPLOYMENT.md step-by-step.

## Files added since last update

- `mockup/splash.html` — rebuilt to match current /cover splash (cycling production photos behind static text/buttons)
- `mockup/assets/davinci-prod-*.jpg` — 5 Da Vinci production photos for splash demo (pulled from server, local mockup only)
- `blog_post_classification.csv` — every non-show URL categorized with suggested action
- `orphan_analysis.csv` — inbound link counts per URL, classifies what's truly abandoned vs. nav-orphaned vs. content-linked

## Working directory layout

```
C:\Users\blake\dev\TLT_Website\
├── PROJECT.md                          ← this file
├── DESIGN_TOKENS.md                    ← extracted brand style guide
├── tlt_server_inventory.json           ← full read-only inventory of server
├── show_to_server_map.json             ← website show → server folder mapping
├── show_hero_photos.json               ← featured image per show
├── pdf_inventory.json                  ← all website PDFs + server matches
├── url_redirect_map.csv                ← 593 URL redirects (CSV for spreadsheet review)
├── url_redirect_map.json               ← same, JSON
├── redirect_rules_sample.conf          ← Apache-style redirect rules to drop into hosting
├── assets/
│   ├── logos/                          ← TLT logos pulled from server (43 MB)
│   └── history/                        ← TLT History.pdf, TLT in a text book.pdf
├── mockup/                             ← static HTML preview of new design
│   ├── index.html
│   ├── shows/davinci.html
│   ├── css/style.css
│   └── assets/                         ← logos + 2025-26 hero images
└── scrape/
    ├── all_urls.txt                    ← all 593 sitemap URLs
    ├── sitemap.xml
    ├── site.css                        ← Squarespace's compiled CSS (523 KB)
    ├── *.py                            ← all scraping/processing scripts
    ├── manifest_core.json              ← core pages → images
    ├── manifest_shows.json             ← show pages → images
    ├── pages/                          ← 18 core pages (HTML)
    ├── pages_shows/                    ← 116 show pages (HTML)
    ├── pages_other_blog/               ← 459 other blog posts (HTML)
    ├── images_dedup/                   ← 138 unique core images (51 MB)
    ├── images_shows_dedup/             ← 229 unique show images (95 MB)
    └── images_*_dupes/                 ← Squarespace size variants, safe to delete
```

## Total disk used by this project

| | |
|---|---|
| scrape/ (HTML + raw images) | ~370 MB |
| scrape/ (after dedup) | ~150 MB |
| assets/ | ~44 MB |
| mockup/ | ~3 MB |

## How to resume if Claude crashes

1. Open this file, read top-to-bottom.
2. Check `scrape/`, `assets/`, `mockup/` to confirm what's actually on disk vs. what this doc says.
3. Look at the unchecked items in **Progress so far** — that's the next work.
4. Tell Claude: "Continue the TLT migration. Read PROJECT.md."
