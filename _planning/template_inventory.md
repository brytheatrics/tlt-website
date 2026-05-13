# TLT Template Inventory

Research pass over the migrated Squarespace content to identify what template
patterns recur, what's already handled, and what we still need to build for the
custom WordPress theme (no Elementor, no page builder).

Source data:
- `triage/decisions.json` — 593 pages triaged, 189 kept, 404 trashed
- `triage/candidates.json` — page metadata
- WordPress DB (`local`, port 10005): `wp_posts` already has 29 imported pages,
  91 imported posts, 70 `tlt_show`, 19 `tlt_team`

## Kept-page mix by category

| Category        | Kept | Trashed | Notes |
| --------------- | ---: | ------: | ----- |
| modern_show     |   55 |      61 | Already → `single-tlt_show.php` |
| old show/news   |   52 |     210 | Mostly old shows → `single-tlt_show.php`; some news posts → `single.php` |
| uncategorized   |   37 |      24 | Mostly tag-archive pages (1970-1980, 2012-2013, etc.) and a few oddballs |
| core            |   18 |       0 | Landing-style pages we drive the site from |
| decade page     |   12 |       0 | 1918-1930 .. 2012-2013 — prior-seasons archive content |
| category index  |    9 |       8 | Squarespace category list pages — replaced by WP archives |
| board profile   |    6 |       2 | Already → `single-tlt_team.php` |
| audition post   |    0 |      43 | Blake trashed all of these |
| education event |    0 |      31 | Blake trashed all of these |
| fundraising     |    0 |      13 | All trashed |
| ClubTLT         |    0 |       6 | Roll up into main `/clubtlt` page |
| covid notice    |    0 |       3 | Dead |

Two takeaways:
1. The big show/board/season templates already cover ~60% of kept pages.
2. The remaining ~80 kept pages are the long tail of "core" landing pages, decade
   indexes, and assorted prose.

---

## Section A — Existing template coverage

| Template                    | Covers (approx kept) | Notes |
| --------------------------- | -------------------: | ----- |
| `page-home.php`             | 1 | `/home` (Customizer-driven hero + season) |
| `page-splash.php`           | 1 | `/cover` (entry splash) |
| `page-prior-seasons.php`    | 1 | `/prior-seasons` index |
| `taxonomy-tlt_season.php`   | n/a (auto) | Per-season archive |
| `single-tlt_show.php`       | ~105 | All `modern_show` (55) + most `old show/news` (~50 are shows, the rest news posts) |
| `single-tlt_team.php`       | 6 | All kept board profiles |
| `page-education.php`        | 1 | `/education` ("About the Program") |
| `page-board-and-staff.php`  | 1 | `/board-and-staff` |
| `page-off-the-shelf.php`    | 1 | `/off-the-shelf` |

**Subtotal already handled: ~117 of 189 kept pages.**

Gap of roughly **70 kept pages** plus the decade indexes and assorted news/prose posts. The rest of this doc addresses them.

---

## Section B — New hard templates needed

Templates worth building because the pattern recurs and the data is predictable.
For each I've noted the kept pages it would cover.

### B1. `page-history.php` — long-form prose with images
Covers: `/history` (`History`, 16 KB), the long `/blog/2015/<decade>` decade text pages where they're prose, and the splash text on `/education`.
Fields/sections: hero image, prose body with inline figures, optional pull-quote, optional download links sidebar (PDF press kit, etc.).
Approx pages: 1 dedicated + supports any long-prose page (`/parking-information`, `/clubtlt`, `/students-on-stage`, `/job-openings` body — ~5 more).

### B2. `page-ticketing.php` — pricing/info reference page
Covers: `/ticketinfo` (8 KB pricing tables), `/season-tickets`, `/parking-information`, the `/tickets` hub stub.
Sections: lead paragraph, repeating "pricing tier" blocks (musicals/plays/group/PWYC/fees/gift cards), CTA buttons (Purchase Tickets, Brochure PDF, Mail-in Order Form PDF), optional FAQ list.
Approx pages: 3–4.

### B3. `page-contact.php` — contact + transportation
Covers: `/contact` only.
Sections: contact form (Contact Form 7 or similar shortcode slot), box-office hours block, address card, transportation/parking notes, embedded map.
Approx pages: 1.

### B4. `page-auditions.php` — auditions hub
Covers: `/auditions` only (but it's central — the rest of the audition listings were trashed, so this single page does the work).
Sections: intro/instructions prose, repeating "audition row" (show title, dates, director, Casting Manager link, optional packet PDF link, status: open/cast/closed). Pulls upcoming auditions from `tlt_show` meta if we add `audition_dates`/`audition_status` fields, otherwise manual rows.
Approx pages: 1, but the most-edited page on the site.

### B5. `page-job-board.php` / `page-press.php` — post-summary listing
Covers: `/job-openings`, `/press`. Both were Squarespace "summary-v2-block" that pulled a category of blog posts.
Sections: intro prose, list of latest posts from a chosen category/tag (thumbnail + date + title + excerpt).
Pattern: one template, parameterized by category (use page meta to pick which category to render, or build two thin templates that share a partial).
Approx pages: 2.

### B6. `page-campaign.php` — fundraising / call-to-action page ("Flush" pattern)
Covers: `/flush` (4 KB), and is the natural home for any future campaign page (annual fund, capital campaign, ClubTLT, donate-now landing). Donations live here even though Blake trashed the legacy individual fundraising posts — the campaign hub stays.
Sections: full-bleed hero image with caption, big headline + lead paragraph, body prose with figures, donation tier cards or donate-button row, donor recognition list (optional), in-kind/sponsor logos row (optional).
Approx pages: 1 now, expected to grow to 3–4 (Flush + Annual Fund + ClubTLT membership + future).

### B7. `page-video-archive.php` — recorded programs
Covers: `/recorded-programs`. Repeating sections of "Section title + grid of embedded videos with captions."
Sections: section heading + video grid (YouTube/Vimeo embed + caption), plus a "Partner theatres" logo grid at the bottom.
Approx pages: 1.

### B8. `page-gallery.php` — show photo gallery
Covers: standalone gallery posts like `cabaret-pictures` (12 KB, just a Squarespace gallery-block) and the gallery body in `2015-16 show`, `A christmas story`, `Smokey joes cafe`, `Second samuel`. These are 5–8 production-photo galleries that aren't tied to a current show.
Sections: title, intro line, photo gallery (lightbox).
Alternative: fold this into `single-tlt_show.php` as an optional "gallery" tab when a show has a `photo_gallery` meta. That's probably the right call — there's no reason for a standalone gallery page when each show could own its own gallery.
Approx pages: 5–8, all merge-able into existing shows.

### B9. `page-volunteer.php` (or use Designed Page) — single-CTA landing
Covers: `/volunteer` (one button), `/donation-request` (one paragraph + link), `/donate` stub, `/tickets` stub, `/visit` stub, `/get-involved` stub, `/about` stub.
These are 6–7 thin hub pages that are basically "headline + 1–2 sentences + button(s) + link list."
Recommendation: do NOT build a dedicated template. These are perfect "Designed Page" cases (Section D) — single hero/image + heading + body + 1–3 CTAs.

---

## Section C — Flex-content block patterns

For pages that don't fit a hard template (the long tail of `core` and `uncategorized`), build a small library of stackable blocks. Counts are how often each pattern appears in the kept content.

| Block | What it renders | Where it appears | Count |
| ----- | --------------- | ---------------- | ----: |
| **Prose** | Rich text (h2/h3/p/ul/strong/em/a) — the workhorse | Every page | ~all |
| **Figure (image + caption)** | One image, optional caption, optional link wrap | `/flush`, `/history`, `/clubtlt`, every show page | ~40 |
| **Image-right / image-left text block** | 2-col: image floats next to a prose column | `/clubtlt`, `/ticketinfo`, `/season-tickets`, `tlt-wins-national-award` | ~12 |
| **Full-bleed banner image** | Wide hero-ish image with optional caption | `/flush`, `/history`, `/off-the-shelf` | ~6 |
| **Button / CTA** | Single styled button (label + URL + optional target) | `/volunteer`, `/ticketinfo`, `/season-tickets`, `harbor-lights`, `summer-camp-2026`, `spring-classes-2026`, `golden-ball-murder` | ~20 |
| **Video embed** | YouTube or Vimeo iframe with caption | `/recorded-programs`, some shows | ~15 |
| **Photo gallery** | Multi-image lightbox grid | `cabaret-pictures`, `2015-16 show`, `smokey-joes-cafe`, `a-christmas-story`, `second-samuel` | ~6 |
| **Section heading** | Big h2 used as section break | `/recorded-programs`, `/ticketinfo`, `golden-ball-murder` | ~25 |
| **PDF link list** | Bulleted list of `<a href=".pdf">` titles — decade pages are nothing but this | `1918-1930`..`2012-2013`, audition packet sidebars | 12 |
| **Two-column callout** | Side-by-side info pair (e.g. address + hours, two pricing tiers) | `/contact`, `/ticketinfo` | ~6 |
| **Logo row / sponsor grid** | Wrapping row of logos with links | `/recorded-programs` (partner theatres), donor pages | ~3 |
| **Post-summary list** | Latest N posts from a category, thumb + title + excerpt | `/press`, `/job-openings` | 2 (covered by B5) |

A page-template that simply iterates a flex-content array of these block types covers every "uncategorized" kept page and gives Chris a clean way to assemble future one-offs without Elementor.

---

## Section D — Pages that don't fit anywhere (Designed Pages)

These are pages where the entire content is essentially "image + heading + CTA(s)" with no structural pattern worth templatizing.

| URL | Use Designed Page? |
| --- | --- |
| `/volunteer` | Yes — single CTA |
| `/donation-request` | Yes — 1 paragraph + email link |
| `harbor-lights` (partner perk announce) | Yes — image + body + button |
| `summer-camp-2026`, `spring-classes-2026` | Yes — promo image + button (these are the "class announcement" pattern Chris reuses every season) |
| `golden-ball-murder` (mystery-dinner one-off) | Edge case — has a dinner menu and cast list. Either flex blocks (prose + button + prose) OR a tiny `page-special-event.php` if mystery dinners recur. Recommend: flex blocks. |
| `amazon-smile`, `fred-meyer-community-rewards` | Designed Page; or roll into a "Ways to Give" parent page as cards |

The "promo image + body + button" pattern shows up enough (class announcements, partner deals, special-event teasers) that the Designed Page template is worth investing in even though each individual instance is bespoke. Spec it as: 1 hero image, headline, optional subhead, rich-text body, up to 3 CTAs, optional secondary image.

**One genuinely bespoke case:** `/history` is long enough (16 KB of prose with multiple inline images and section breaks) that it could justify a dedicated `page-history.php` (proposed B1) OR be assembled from flex blocks. Either works.

---

## Section E — Squarespace markup that needs cleanup or pass-through

Every imported `post_content` is wrapped in Squarespace structural divs. Patterns observed:

- **Wrapper chrome (every page):** `<div class="sqs-layout sqs-grid-12 columns-12" ...>` and 3–4 levels of nested empty `<div>`s. Drop on import or hide via CSS. Already partially scrubbed by `wordpress/import/clean_page_content.py` (worth re-running on the long tail).
- **Image blocks:** `<figure class="intrinsic"> <img src="https://images.squarespace-cdn.com/..."> <figcaption class="image-caption-wrapper">`. The CDN URLs still resolve, but for offline / future-proof we should rewrite to local uploads. Importer already does this for show posters; need to extend to in-body images.
- **`<div class="float float-right">` and `float-left`:** Squarespace's image-with-text-wrap pattern. Map to a flex-block (image-right/image-left text block, C row 3) or strip and render as figure.
- **`<div class="website-component-block button-block">` with `<a>`:** the Squarespace button. Easy regex to rewrite into a `.btn` class, or fold into Button flex-block on re-import.
- **`<div class="summary-v2-block">`:** the Squarespace blog-roll embed used on `/press` and `/job-openings`. Cannot pass through — must be replaced by a real WP query (handled by template B5).
- **`<div class="gallery-block">` with stacked `<div class="slide">`:** Squarespace gallery. Replace with a Gallery flex-block / `[gallery]` shortcode pointing at imported attachments.
- **`<div class="video-block">`:** Currently in `/recorded-programs` the actual `<iframe>`s were stripped by the scraper, only captions survived. We will need Chris to paste video URLs back in — set up the Video flex-block to take a URL and oEmbed it.
- **`<!--SPECIAL CONTENT-->`, `<!--POST HEADER-->`, `<!--POST BODY-->`, `<!--POST FOOTER-->`:** Squarespace post-template markers — these wrap most imported `post`s. The "special content" block (a header image above the body) is real content; the rest are layout markers and should be stripped.
- **Smart-quote / em-dash encoding artifacts (`�`):** Most imported bodies have `�` where Squarespace had `'`, `"`, `—`, `…`. A find-replace pass would noticeably improve every page; recommend before launch.
- **`<a href="/s/...pdf">`:** internal Squarespace `/s/` PDF URLs are everywhere (decade pages especially). The PDFs themselves were migrated (`migrate_program_pdfs.py`); links need rewriting to point at the new upload paths.

---

## Recommended build order

The aim is to unblock the most kept content per template built.

1. **Cleanup pass on imported HTML** — strip Squarespace wrapper divs, normalize smart quotes, rewrite `/s/*.pdf` links. Pays off on every page below. Half a day; biggest single-step quality win.
2. **`page.php` styling** — make the default page template actually look good. Half the kept pages (everything in flex-block / prose land) sits on this. Without it, every other template inherits ugly defaults.
3. **Flex-block library (Section C):** Prose, Figure, Button, Section Heading, Image-with-text, Full-bleed Banner. Six small partials. Unlocks ~30 long-tail pages immediately and is the foundation for the Designed Page template.
4. **`page-auditions.php`** (B4) — single highest-traffic editorial page, currently broken on the migrated version. Big visibility win.
5. **`page-ticketing.php`** (B2) — `/ticketinfo` + `/season-tickets` + `/parking-information`. Money pages.
6. **`page-campaign.php`** (B6) — `/flush` first; sets the pattern for future fundraising. Test the flex-block library here.
7. **`page-job-board.php` / `page-press.php`** (B5) — these are blocked on B5 because their original content was a Squarespace summary block, not real prose. Easy template once a category-listing partial exists.
8. **Designed Page template** (Section D) — once flex blocks exist this is mostly a layout shell. Handles `/volunteer`, `harbor-lights`, class announcements, mystery-dinner promos.
9. **`page-contact.php`** (B3), **`page-video-archive.php`** (B7) — niche but bounded; do after the bigger templates land.
10. **Gallery handling** — extend `single-tlt_show.php` to support a `photo_gallery` meta rather than building a standalone `page-gallery.php`. Five-ish kept gallery posts get folded into their parent show.

After step 8, ~95% of the 189 kept pages have a real home.
