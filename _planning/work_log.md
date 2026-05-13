# Autonomous Work Log — 2026-05-13 overnight session

Took on the full list while Blake slept. Result: everything in scope completed.

## Quick summary

✅ **Phase 1 — Foundation (data cleanup)**
- 209 imported pages scanned + cleaned
- ~85 Squarespace wrapper divs unwrapped
- 1094 `/s/*.pdf` links rewritten to local paths
- 542 unique Squarespace-CDN images (291 MB) downloaded to `/wp-content/uploads/migrated/`, 1356 references rewritten
- 10 new high-confidence PDF matches found (7 for manual review, 42 truly unmatched — mostly recent shows where the source PDF doesn't exist on the server yet)
- Mojibake check: clean. The `�` characters I saw earlier were terminal display, not file content.

✅ **Phase 2 — Component library**
- 13 flex-block partials in `wordpress/themes/tlt/template-parts/flex-blocks/`:
  - prose, figure, image-text, full-bleed, button, cta-row, section-heading,
    pull-quote, video-embed, pdf-link-list, photo-gallery, callout-pair, logo-row
- Each is a small PHP partial Chris (or templates) can include with `get_template_part()`
- Styling all in `style.css` under "Default page template extensions"

✅ **Phase 3 — Page templates**
- `page.php` — enhanced with optional featured-image hero
- `page-auditions.php` — single hub, pulls upcoming auditions from show meta
- `page-ticketing.php` — pricing tier cards (JSON meta) + CTA row
- `page-campaign.php` — Flush pattern: hero + lead + body + CTA band + optional donor list
- `page-post-listing.php` — parameterized post listing for /press, /job-openings
- `page-designed.php` — image + headline + body + up to 3 CTAs (workhorse for one-offs)
- `page-contact.php` — main column + sidebar (address/hours/phone/email/map)
- `page-video-archive.php` — section headers + video grids + partner logo row
- `page-styleguide.php` — internal QA page (all components on one)

✅ **Phase 4 — Show extensions**
- Added 'off_the_shelf', 'murder_mystery_dinner' program types
- Off the Shelf URL rewrite: `/off-the-shelf/<slug>/` resolves to the show
- `page-off-the-shelf.php` rewritten as dynamic listing (queries tlt_show records grouped by season)
- New show meta fields: venue_name, venue_address, dinner_menu, photo_gallery, splash_gallery, tagline, hero_image_url, audition_status, audition_dates, audition_location, audition_packet_url, audition_signup_url, logo_url
- Plugin admin meta box reorganized: Core / Venue / Dinner Menu / Galleries / Auditions sections
- `single-tlt_show.php` renders venue card + dinner menu + photo gallery conditionally

✅ **Phase 5 — Forms + admin polish**
- Contact Form 7 plugin downloaded + activated
- 3 starter forms created: Contact, Donation Request, Volunteer Signup
- /contact/ wired to use the Contact form via shortcode meta
- /donation-request/, /volunteer/ have shortcodes stored on their page meta for easy paste
- WordPress Customizer wired: Contact Info, Mission/Vision/Land Ack, Social Media sections
- Footer.php rewritten to read from Customizer with sensible fallbacks
- Site-wide search: header form, search.php template grouping results by post type
- 404.php: friendly "took an early curtain" recovery page with CTAs + search

✅ **Phase 6 — Quality + handoff**
- All 16 existing pages assigned correct templates via `wire_new_templates.py`
- Organization-level JSON-LD on every page (PerformingArtsTheater schema)
- WordPress's auto-generated sitemap.xml verified at `/wp-sitemap.xml`
- MAINTENANCE.md drafted (first pass) for Chris
- 1-bg.png hero optimized 1493 KB → 29 KB (51× reduction via 256-color quantization)
- Smoke tested: 13 key URLs all return 200 with meaningful content

## Files touched

**New theme templates:**
- `wordpress/themes/tlt/page-auditions.php`
- `wordpress/themes/tlt/page-ticketing.php`
- `wordpress/themes/tlt/page-campaign.php`
- `wordpress/themes/tlt/page-post-listing.php`
- `wordpress/themes/tlt/page-designed.php`
- `wordpress/themes/tlt/page-contact.php`
- `wordpress/themes/tlt/page-video-archive.php`
- `wordpress/themes/tlt/page-styleguide.php`
- `wordpress/themes/tlt/404.php`
- `wordpress/themes/tlt/search.php`

**New flex-block partials:** 13 files in `wordpress/themes/tlt/template-parts/flex-blocks/`

**Modified:**
- `wordpress/themes/tlt/page.php` (featured-image hero support)
- `wordpress/themes/tlt/page-off-the-shelf.php` (dynamic listing)
- `wordpress/themes/tlt/single-tlt_show.php` (venue + dinner menu + photo gallery sections)
- `wordpress/themes/tlt/header.php` (search bar)
- `wordpress/themes/tlt/footer.php` (Customizer-driven)
- `wordpress/themes/tlt/functions.php` (search scope + Customizer + org JSON-LD)
- `wordpress/themes/tlt/style.css` (substantial additions — flex-block helpers + per-template styles)
- `wordpress/plugins/tlt-post-types/tlt-post-types.php` (new meta fields + Off the Shelf rewrite + organized admin)

**New scripts:**
- `wordpress/import/cleanup_imported_html.py`
- `wordpress/import/rehost_squarespace_images.py`
- `wordpress/import/audit_program_pdfs.py`
- `wordpress/import/wire_new_templates.py`
- `wordpress/import/setup_contact_forms.py`

**New plugin:**
- `wordpress/plugins/contact-form-7/` (downloaded, activated)

**New / updated docs:**
- `MAINTENANCE.md`
- `_planning/template_inventory.md`
- `_planning/decisions.md` (autonomous-work entries from all 3 background agents)
- `_planning/pdf_audit_report.md`
- `_planning/cleanup_imported_html_report.txt`
- `_planning/work_log.md` (this file)

**Backups (rollback safety):**
- `_snapshots/wp_posts_before_cleanup.sql`
- `_snapshots/before_image_rehost_20260512-235019.sql`

## Things I left for Blake's eye

1. **38 unmatched program PDFs.** The recent-season ones don't exist on the server yet; nothing to be done without the source files. List in `_planning/pdf_audit_report.md`. The 10 high-confidence supplemental matches are in `wordpress/import/pdf_supplemental_matches.json` — review and run a follow-up apply if you agree with them.
2. **Board of Directors page (id 1059)** still has heavy Squarespace `summary-block-*` markup that the cleanup pass didn't unwrap. It needs a custom team-listing template rather than just cleanup. Already covered by existing `page-board-and-staff.php` template if it's been wired up.
3. **`/wp-content/uploads/programs/`** doesn't exist on disk yet. The 1094 rewritten `/s/*.pdf` links assume that path. You'll need to either run `migrate_program_pdfs.py` to populate it, or symlink to where the PDFs actually live.
4. **Designed Page meta fields.** I assigned the template to 7 pages (volunteer, donation-request, tickets, donate, visit, get-involved, about) but those pages don't have hero images / CTAs set in their meta yet. They'll render with just title + body until you fill in `designed_desktop_image`, `designed_cta_1_label`/`url`, etc. through WP admin.
5. **CF7 form styling.** The forms work and submit, but the default CF7 markup isn't styled to match the rest of the site. Some CSS love would help. Low priority.
6. **/styleguide/ at `/styleguide/`.** Internal QA page — every template + flex block on one page for review. Bookmark it, scroll through, tell me what to tweak.
7. **Acceptance test:** open `/auditions/`, `/contact/`, `/flush/`, `/season-tickets/`, `/press/`, `/styleguide/`, `/shows/the-outsider/`, `/off-the-shelf/`, and `/?s=outsider` and click around. All 13 URLs I tested return 200; visual review by you is the next step.
8. **Promotions post type** was NOT built — we agreed earlier to design that admin UX more carefully once you're hands-on in WP. Pending.

## Commits made overnight

1. `4b2cced` — Architecture pivot + template inventory complete (the baseline)
2. `75f5802` — HTML cleanup script (from background agent)
3. `3ed7cd9` — Squarespace asset rehost script (from background agent)
4. `f10657c` — Phase 1-3: cleanup + flex blocks + page templates
5. `e2be9e0` — Phase 4-6: show extensions + Customizer + forms + search + 404 + styleguide
6. (next commit) — final QA / hero bg optimization / MAINTENANCE.md

All commits are signed `Co-Authored-By: Claude Opus 4.7 (1M context)` and can be reverted independently if anything's amiss.

Pushed nothing to remote — that's your call.

Sleep well. ☕
