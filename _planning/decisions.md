# Decisions log

Running log of architectural and design decisions made during the project. Most recent at top.

When Claude is doing autonomous work, every "I made a judgment call" decision gets logged here so Blake can review and override.

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
