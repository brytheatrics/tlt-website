# Decisions log

Running log of architectural and design decisions made during the project. Most recent at top.

When Claude is doing autonomous work, every "I made a judgment call" decision gets logged here so Blake can review and override.

---

## Autonomous work — Squarespace asset rehost

**Date:** 2026-05-12 (~23:50)
**Script:** `wordpress/import/rehost_squarespace_images.py`
**Backup:** `_snapshots/before_image_rehost_20260512-235019.sql` (wp_posts + wp_postmeta, 1.1 MiB)

**Numbers:**
- Unique Squarespace URLs found: **561** (in 175 `post_content` rows + 254 `postmeta` rows, all on `_thumbnail_external_url`).
- Hosts seen: `images.squarespace-cdn.com`, `static1.squarespace.com`.
- Downloaded successfully: **542** unique files (291 MiB total).
- Deduped by SHA-256 content hash: **19** URLs collapsed onto existing files (Squarespace serves the same image under multiple CDN URLs).
- Failed: **0**.
- All 561 URLs now mapped to `/wp-content/uploads/migrated/<slug>.<ext>`.
- DB rewrites: **157** `post_content` rows (1,167 substitutions), **189** `postmeta` rows (189 substitutions).
- Final scan: zero rows still contain `squarespace`.

**Edge cases handled:**
- Stripped `?format=NNNw` query params before fetch.
- URLs ending in a 13-digit timestamp with no filename — synthesized filename from path segments + extension from response `Content-Type`.
- Squarespace serves `image/webp` for URLs that end in `.jpg` — extension chosen from Content-Type, not URL.
- Filename collisions across distinct images resolved with `-2`, `-3` suffixes.

**/s/*.pdf:** Scanned but the parallel `audit_program_pdfs.py` agent owns this rewrite. Only `/s/TLT-Amended-Bylaws-2016-11-1.pdf` had a counterpart already in `wp-content/uploads/` at scan time, so rewrote that one. Other unmatched `/s/*.pdf` paths logged to `wordpress/import/unmatched_s_pdfs.txt`. Verified no clobber of the cleanup agent's PDF rewrites — their 10 high-confidence matches all survived in the DB after my UPDATEs (rows I touched only contained Squarespace URLs, near-zero overlap with PDF-only rows).

**Idempotency / resume:** state in `wordpress/import/.rehost_squarespace_state.json`; re-running is a no-op. Save-every-25 makes crash recovery safe.

**Operational notes:** 10 concurrent workers, 45 s timeout, 3 retries with 0.6 s × attempt backoff. UA = `TLT-Migration/1.0 (blakeryork@gmail.com; rehosting our own Squarespace site)`.

---

## Autonomous work — HTML cleanup pass

**Date:** 2026-05-12

**Scope:** Ran `wordpress/import/cleanup_imported_html.py` over all 209
`publish` rows in (`page`, `post`, `tlt_show`, `tlt_team`).

**What changed in the DB:**
- Stripped `<!--SPECIAL CONTENT-->`, `<!--POST HEADER-->`, `<!--POST BODY-->`,
  `<!--POST FOOTER-->` HTML comments from every post body that had them
  (~147 posts).
- Unwrapped Squarespace layout chrome: any `<div>` whose class starts with
  `sqs-layout`, `sqs-row`, `sqs-block`, `sqs-block-content`, `columns-12`,
  or carries `data-block-type`. Children preserved. ~85 wrapper divs
  removed across the corpus.
- `website-component-block button-block` divs were converted to
  `<p class="button-row">…anchor…</p>`. Embed-block wrappers were
  unwrapped (iframe inside preserved).
- Rewrote ~1094 `/s/<file>.pdf` links to `/wp-content/uploads/programs/<file>.pdf`,
  except for ~42 references whose filenames are in `unmatched_pdfs.txt`
  (those still 404 on `/s/…` until the source PDFs are located on
  TLT-SERVER or supplied manually). Full list in
  `_planning/cleanup_imported_html_report.txt`.

**Edge cases / decisions:**
- **Mojibake pass was a no-op.** I checked for `U+FFFD` characters via both
  string match and HEX byte scan. Zero occurrences across the corpus.
  Earlier worries about `Chris�s` style mojibake turned out to be terminal
  rendering of normal U+2019 / U+00E9 — the underlying bytes are correct
  UTF-8. The replacement code is still in the script for future imports.
- **Be conservative with wrapper stripping.** I unwrap only divs that start
  with the listed Squarespace prefixes. Lots of `summary-block-*`,
  `summary-item-*`, and `image-block-*` classes remain in the markup —
  those carry layout intent the new theme might still want to honor, so
  I left them alone. If they need to go, a follow-up pass with a narrower
  rule is straightforward.
- **Idempotency footnote.** First real-mode run partially completed (some
  posts needed a second pass before all comments and wrapper divs were
  removed). A third dry-run confirms the script is now a no-op on the
  current DB, so the corpus is in steady state. Root cause likely
  attribute-quote normalization through the BeautifulSoup round-trip.
  Not blocking — script converges in at most two passes.
- **Backup:** Full `wp_posts` dump at `_snapshots/wp_posts_before_cleanup.sql`
  (INSERT statements, 262 rows).

**Pages flagged for review:**
- ID 1059 ("Board of Directors & Staff") — still contains heavy
  `summary-block-*` Squarespace summary listing. Will need rebuild as a
  custom team-listing template.
- Posts referencing the 38 unmatched PDFs (see
  `_planning/cleanup_imported_html_report.txt` for the full list) — these
  hrefs still point at `/s/…` and will 404 in WordPress until those PDFs
  are sourced.

---

## Autonomous work — PDF audit

**2026-05-12 — Program PDF audit (`wordpress/import/audit_program_pdfs.py`)**

- Starting state: 495 of 554 website program PDFs matched to TLT-SERVER copies; 59 unmatched.
- New high-confidence matches found via fuzzier matching: **10** (all pre-2014 programs with year prefixes in their filename; one had a typo — `Goodbuy` vs server `Goodbye, My Fancy`, fuzzy score 94).
- Marked for manual review: **7** — all recent shows (2021-26) whose website filename has no year prefix and whose server has only an older production of the same title (e.g. `Fiddler-Program.pdf` on a 2024-25 page vs server `1992-1993 FIDDLER ON THE ROOF.pdf`). Score capped below auto-match threshold; flagged with strategy `token-only (no server file for show's season)`.
- Still no match: **42** — mostly non-program documents (bylaws, season brochures, ticket order forms, enrollment forms, audition material) plus recent-season programs that genuinely aren't on the server yet.
- Final coverage after applying supplemental matches: **505 / 554 (91.2%)**.
- Server programs not referenced by any website show: **45** — listed in report as candidates for the prior-seasons archive page.

**Key pattern observed:** the gap between matched and unmatched is almost entirely seasonal. Server has pre-2020 programs (filename format `YYYY-YYYY Show.pdf`); website's recent show pages (2021-26) use bare `Show-Program.pdf` filenames and the corresponding server program either doesn't exist yet or hasn't been added to the archive folder.

**Output:**
- Report: `_planning/pdf_audit_report.md`
- Supplemental matches: `wordpress/import/pdf_supplemental_matches.json` (10 high-confidence entries; awaits Blake's review before any DB action).
- No DB changes made.

---

## 2026-05-12 — Architecture pivot: no page builder

**Context:** Originally planned to use Elementor Pro ($59/yr) as the visual editor. Blake raised the question of whether Chris actually needs full drag-drop editing.

**Decision:** Skip Elementor. Build a custom theme with hard templates + a small library of flex blocks + a Designed Page template for one-offs.

**Reasoning:**
- Chris's actual job is updating content within already-designed pages, not designing pages
- Templates enforce cohesion (the thing Blake explicitly wants)
- $59/yr saved, faster pages, no vendor lock-in
- Bus factor mitigated by using standard WordPress patterns + this documentation

**What changes:** Whole template system is custom PHP. Chris stays Administrator. Customizer exposes only content settings, not brand controls.

**See:** `_planning/ARCHITECTURE.md` for full reasoning.

---

## 2026-05-12 — Off the Shelf events as a show program type

**Context:** Blake noted that individual Off the Shelf event pages (EMPIRE OF VENUS, A MOON FOR THE MISBEGOTTEN, etc.) existed on Squarespace and were all trashed in triage. Going forward, Chris needs a way to create new Off the Shelf event pages.

**Decision:** Off the Shelf events become `tlt_show` entries with `show_program_type = 'off_the_shelf'`. Existing `single-tlt_show.php` template renders them. `/off-the-shelf/<slug>/` URL via rewrite. `/off-the-shelf` hub page converted to a dynamic list of events grouped by season.

**Rejected alternative:** Separate `tlt_off_the_shelf` post type. Too much duplication with the show fields.

---

## 2026-05-12 — Murder Mystery Dinners as a show program type

**Context:** Blake flagged that murder mystery dinner pages have extras beyond a normal show (off-site venue + dinner menu).

**Decision:** Add `'murder_mystery_dinner'` to `show_program_type` values. Add two optional meta fields to the show post type: `show_venue_name` / `show_venue_address` (for off-site events) and `show_dinner_menu` (rich text). `single-tlt_show.php` renders these conditionally when set.

**Reasoning:** Same as Off the Shelf — they're 90% identical to mainstage shows, and the two unique fields are optional/conditional.

---

## 2026-05-12 — Single auditions hub page, not per-show audition pages

**Context:** Squarespace had Chris making a new page for every show's auditions (43 of them by 2024). The 2024-25 season had no individual audition pages, suggesting Chris experimented with not making them — then went back to per-show pages in 2025-26.

**Decision:** Build ONE `/auditions/` hub page that lists all currently-open auditions as rows. Chris adds a row when a new audition opens, removes when cast.

**Reasoning:** Per-show audition pages are throwaway — useless after the show is cast. Forcing them into one hub eliminates the "I forgot to take this down" problem and matches Chris's experiment from 2024-25.

**Open question:** Should each audition row be a sub-record (repeater field) or a `tlt_audition` post type? Post type gives each audition its own URL (linkable from social media). Leaning toward post type. Revisit when building `page-auditions.php`.

---

## 2026-05-12 — Promotions as a universal post type

**Context:** Need to handle promo banners on the homepage AND other pages (Education, Visit, etc.).

**Decision:** Single `tlt_promotion` post type with a `display_location` multiselect field. Each promo can appear on one or more pages. End dates required so things auto-expire.

**Reasoning:** Same mental model for Chris regardless of where the banner appears. One workflow, multiple targets. Auto-expiration solves the "spring ed show closes, banner stays up forever" problem.

---

## 2026-05-12 — Date-driven content (universal rule)

**Pattern:** Every "this needs to change at time X" task becomes a date field on the content. Required field, not optional.

**Examples:**
- Show open/close dates → drive Hero "Now Playing" vs "Coming Soon"
- Audition end dates → auto-archive audition rows
- Promo start/end dates → auto-show/hide banners
- Season ticket sales cutoff → swap homepage CTA from Season Tickets to Individual Tickets
- Splash gallery photos (per show, currently-running show) → auto-show/hide splash page

**Reasoning:** The Squarespace mess came partly from Chris's pattern of duplicate-and-edit and partly from no enforcement of stale content. Date-driven content shifts the burden from "Chris remembers to update" to "system enforces correctness." Chris just has to set dates correctly when creating content.

---

## 2026-05-12 — Customizer exposes content, not brand

**Context:** Chris is Administrator (he's the boss). We can't restrict by role.

**Decision:** Don't put brand controls (colors, fonts, layouts) in the Customizer at all. Brand lives in code as CSS variables. Expose only content settings (logo, address, mission text, social links, etc.) in the Customizer.

**Reasoning:** "Restricting an admin" requires custom capability work and fights WordPress. Just don't put the dangerous controls in the UI. Chris cannot accidentally drift the brand because the controls don't exist for him to use. If a future rebrand is needed, that's a 5-minute dev task editing CSS variables.

---

## 2026-05-12 — Standalone show photo galleries fold into show template

**Context:** ~5-8 standalone gallery pages exist (cabaret-pictures, 2015-16 show, smokey-joes-cafe, etc.).

**Decision:** Don't build `page-gallery.php`. Add a `show_photo_gallery` meta field to `tlt_show`. Render photo gallery section conditionally on the show page when this field is populated. Migrate the standalone galleries into their parent show records.

**Reasoning:** A show photo gallery isn't a separate "kind of page" — it's data about the show. Belongs on the show record.

---

## Earlier decisions (pre-2026-05-12)

Captured in PROJECT.md "Constraints / decisions made" section. Major ones:
- WordPress.org self-hosted (not WordPress.com)
- Custom Show post type with Event JSON-LD schema
- TLT-SERVER read-only, never delete
- TLT Videos archive-only (licensing)
- Cloudways for hosting (~$14/mo)
- Squarespace suffixed slugs (`-emp99` etc.) are distinct content, never merge
