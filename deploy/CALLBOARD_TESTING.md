# TLT Callboard — Testing Checklist (living doc)

Grows as I audit the port. Everything under **NEEDS YOUR EYES** is something I can't verify without seeing real output or hitting a real user-facing path.

Last updated by AI: 2026-07-10 (session-in-progress after full port)

---

## Prerequisites before ANY test

1. **Purge Cloudways Full Page Cache** from the dashboard. WP-CLI cache flush doesn't clear it.
2. Add the two API keys in `wp-config.php` (see CALLBOARD_HANDOFF.md).
3. Share the Drive folders/templates with the SA (see CALLBOARD_HANDOFF.md).
4. Force-refresh `https://tacomalittletheatre.com/callboard/` (Ctrl+F5) so browsers pick up the new index.html.

---

## PASS / NEEDS EYES

Legend: `[ ]` = untested, `[?]` = AI-audited-only, `[✓]` = user-verified, `[✗]` = known broken.

### Auth + session

- [?] **Log in** with any Theatre-tab password. Token persists 30 days.
- [?] **Log out** via Ctrl+Shift+L or /logout endpoint.
- [?] **Approval password prompt** (Managing Artistic Director / Associate Producing Director / Technical Director / Associate Artistic Director) works for gated actions.

### Read endpoints

- [?] `/initial-data` — dashboard payload
- [?] `/dashboard` — dashboard-only refresh
- [?] `/shows`, `/current-season`, `/roles`
- [?] `/show-roster?show=…`, `/actors-for-show?show=…`
- [?] `/actors`, `/sales`, `/bios`, `/contacts`
- [?] `/contracts` — contract statuses per person per show
- [?] `/full-season` — every show + statuses
- [?] `/combinable-shows` — for a given (show, role, first, last), returns other shows for combining
- [?] `/schedule-link?show=…` — now returns `{url,exists,source}` (was plain URL — verify frontend consumers all use the modal path)
- [?] `/contact-sheet-link?show=…` — same shape
- [?] `/calendar-events`, `/calendar-conflicts`
- [?] `/program?show=…` — matches getProgramData layout

### Contact Sheet

- [ ] **NEEDS YOUR EYES: first-time generation.** Click 👥 Contact Sheet on a show that has NEVER had one. Should show "Generating…" then open a new Doc in the CS folder with CAST + PRODUCTION TEAM tables.
- [ ] **NEEDS YOUR EYES: modal appears second time.** Click again → modal with View / Regenerate / Cancel.
- [ ] **NEEDS YOUR EYES: View opens same URL.**
- [ ] **NEEDS YOUR EYES: Regenerate trashes old + creates fresh.**
- [ ] **NEEDS YOUR EYES: table formatting.** Column widths, header bold, cell padding should roughly match a GAS-generated contact sheet.

### Tech Schedule

- [ ] **NEEDS YOUR EYES: modal + generation.** Same UX as contact sheet — first click generates, second click shows modal.
- [ ] **NEEDS YOUR EYES: `<<TechRunLabel>>` row removal when C2C + Tech Run same day.**
- [ ] **NEEDS YOUR EYES: all `<<XxxDate>>` placeholders replaced.** Fixture: pick a show that has all dates filled in the Dates tab.

### Bios Doc compile

- [ ] **NEEDS YOUR EYES: no-bios error.** For a show with no submitted bios, should return "No submitted bios found for X" (not silently create empty doc).
- [ ] **NEEDS YOUR EYES: layout.** Doc has title + season + "Production Team" section + "Cast" section. Each entry: name (with role), then bio paragraph.
- [ ] **NEEDS YOUR EYES: URL saved to Season col L (12).**

### Bulk bio requests

- [ ] **NEEDS YOUR EYES: sends to everyone.** Sends a welcome email to every unique person on the show (team + cast). Returns `{ sent, skipped, errors }`.
- [ ] **NEEDS YOUR EYES: skips missing emails.** Anyone with no email in Contactbook → skipped.
- [ ] **NEEDS YOUR EYES: link works.** Bio link in the email opens the bio submission form pre-populated with show.
- [ ] **NEEDS YOUR EYES: email visual layout.** Check spacing, buttons, comp code presentation. See `tlt_cb_bio_email_html()` in plugin for tweaks.

### Bio resend (individual)

- [ ] **NEEDS YOUR EYES: single-show resend.** Sends bio email for one person on one show.
- [ ] **NEEDS YOUR EYES: combined resend.** If the person has a combined contract group (col S non-empty), sends the combined variant with per-show season reference table.

### Program export

- [ ] **NEEDS YOUR EYES: JSON download.** Click Export → downloads `<Show> - Program.json` via `drive.google.com/uc?export=download&id=…`. File should be readable JSON.
- [ ] **NEEDS YOUR EYES: JSON shape matches GAS.** Compare fields to an archived GAS export. Fields: show, season, info{title/author/director/legal/run/a1/a2/intermission/place/specialThanks}, staff[], productionTeam[], bios{team[], cast[]}, italicizeTitles[].
- [ ] **NEEDS YOUR EYES: InDesign script still consumes it.** If the shape's off, the InDesign side will fail silently.

### Contract generate (single)

- [ ] **NEEDS YOUR EYES: template selection.** Pick a role → verify it uses the right template (Director / Actor / Operator / General per the Duties sheet col B).
- [ ] **NEEDS YOUR EYES: all `<<Tag>>` placeholders substituted.** Open the generated Doc. Search for `<<` — should find none.
- [ ] **NEEDS YOUR EYES: Duties block.** Should be bulleted list with ALL-CAPS lines as bold headers. May differ slightly in spacing from GAS.
- [ ] **NEEDS YOUR EYES: Key Dates block.** Should list only events flagged TRUE on the Duties sheet for this role, with date bolded.
- [ ] **NEEDS YOUR EYES: Special Conditions block.** Rendered if any lines, otherwise the bracket block is deleted.
- [ ] **NEEDS YOUR EYES: Empty staff blocks hidden.** Roles like `<<AD>>` that are blank in Theatre tab should have their label paragraph deleted too (no dangling "Associate Producing Director" alone on a line).
- [ ] **NEEDS YOUR EYES: `<<Board>>` expansion.** Board members list correctly on separate paragraphs.
- [ ] **NEEDS YOUR EYES: sheet status = "Generated".** Contract Status col in Production Teams / Actors updated. Contract Link col has the Doc URL.
- [ ] **NEEDS YOUR EYES: side-by-side vs GAS.** Ideally generate the SAME contract in both systems and diff the output visually.

### Contract generate (combined)

- [ ] **NEEDS YOUR EYES: multi-show template flow.** Doc uses the joined show list wherever `<<Show>>` was.
- [ ] **NEEDS YOUR EYES: `<<Compensation>>` block.** Present in combined only. Should show per-show stipends + season total.
- [ ] **NEEDS YOUR EYES: combined ID (col S).** All rows for the combined group get the same CC-XXXXXXXXXXXX ID.
- [ ] **NEEDS YOUR EYES: propagation.** Setting one row's status also updates all col-S siblings.
- [ ] **NEEDS YOUR EYES: no doubled show names.** Check for "A and B A and B" glitches. If found, tell me — I removed the GAS save-reopen trick.

### Contract send (single + combined)

Requires TLT_CALLBOARD_OPENSIGN_KEY + TLT_CALLBOARD_RESEND_KEY set.

- [ ] **NEEDS YOUR EYES: PDF export succeeds.** The Doc gets exported to PDF bytes.
- [ ] **NEEDS YOUR EYES: page count is right.** Widgets should land on the last page.
- [ ] **NEEDS YOUR EYES: OpenSign document created.** Signer receives an OpenSign email.
- [ ] **NEEDS YOUR EYES: signature widgets in the right spot.** Coordinates should match what GAS used (see `tlt_cb_contract_opensign_widgets`).
- [ ] **NEEDS YOUR EYES: sheet status = "Sent for Signature".** Col I. Sent date filled in col J. OpenSign document ID in col L (overwrites doc URL).
- [ ] **NEEDS YOUR EYES: welcome/bio email arrives.** Signer receives the Resend email with buttons for bio, emergency info, handbook.
- [ ] **NEEDS YOUR EYES: SM name + email correct.** Uses show-specific SM email (Season col E) for the Stage Manager block in the email.
- [ ] **NEEDS YOUR EYES: comp codes correct.** Season col C = comp code 1, col D = comp code 2.

### Contract delete

- [ ] **NEEDS YOUR EYES: Doc goes to Drive trash.**
- [ ] **NEEDS YOUR EYES: Status resets to "Not Started".** Col I cleared. Col L cleared. Col J untouched (per GAS behavior).
- [ ] **NEEDS YOUR EYES: combined group all reset.** If the row has a col-S ID, all siblings reset too.

---

## AI-audited issues (fixed this session)

- **Contract generate response missing `email`** — GAS returned `email` so the frontend could pass it to `/contract-send`. Ported response omitted it, which would break the send button. Fixed: added Contactbook + Production Teams/Actors email lookup, response now includes `email` + `lastName`. (Both single and combined generators.)
- **Contact sheet phase 3 index race** — Inserts and column-width/cell-padding styling were in ONE batchUpdate. Table 0's cell inserts grew the doc, shifting table 1's `startIndex` mid-batch — so the second table's column widths + padding got applied to a stale index. Fixed: split into two batchUpdates. Between them, re-fetch table start indices.
- **`/contracts` endpoint shape mismatch** — `getContractsPageData` expected `{ shows, contracts }` but endpoint returned just the contracts array. Would have silently rendered the Contracts tab with empty show filter dropdown and no contracts visible. Fixed: `/contracts` now returns `{ shows, contracts }` by default; `getContractsData` in the shim hits `/contracts?shape=array` for post-mutation refreshes (which expect just the array).
- **Contract data won't load if `CurrentSeason` / `CurrentSeasonLong` aren't defined as named ranges** — GAS required the named ranges, so most sheets have them. Port now falls back to reading the Season tab's `Current Season` / `Current Season Long` label rows if the named ranges are missing.
- **Docs fields masks in contract walker + tech schedule row delete** were suspect for paren balance; swapped to no-mask (full-doc fetch). Slightly slower, always works.
- **`/program` was returning empty bios + missing `specialThanks`** — the old Phase 2 stub always set `bio: ''` and didn't fetch Contactbook bios. Programs tab renderer counts non-empty bios and would have shown "0 of N submitted" for every show. Fixed: `/program` now delegates to the same `tlt_cb_program_get_data()` used by the InDesign export, so both surfaces get the real data.
- **`tlt_cb_docs_create` was passing `removeParents=root` blindly** — SAs don't necessarily have their new Doc parented at literal "root", so the move could silently fail or duplicate the doc. Fixed: fetch current parents first, remove those explicitly. Also now surfaces move failure as a WP_Error so Bio doc compilation reports it instead of leaving an orphan.
- **Contract templates: `<<Staff>>`, `<<MC>>`, `<<ST>>` weren't replaced** — GAS had them in its replacements map as empty strings. Port omitted them; templates using those tags would render them literally. Fixed.
- **`compileBiosDoc` + `resendBioRequest` responses missing `success` flag** — frontend does `if (result.success) { ...ok... } else { alert(result.error) }`. Missing flag → falsy → shows "Error: undefined". Fixed: both endpoints now return `{success: true, ...}` on the success path. (contract generate/send/delete already returned `success: true`.)

---

## AI-audited concerns (need YOUR call)

_(populated as I find things I'm unsure about)_

_(none yet)_

---

## Things I explicitly did NOT do

- Did not touch the Netlify bio submission frontend (per your instruction).
- Did not touch the TLTBioApp GAS project (it's the bio submission REST backend; still receives Netlify traffic).
- Did not touch ContractOrganizer (contracts@ standalone GAS; polls OpenSign drop folder for signed PDFs).
- Did not touch Ludus / CastingManager Python sync scripts.
- Did not migrate GAS Script Properties — you'll need to move any needed values into `wp-config.php` constants.
- Did not port the GAS `doPost` OpenSign webhook — per CLAUDE.md that's inactive; ContractOrganizer handles signed detection.
- Did not add a "confirm" step to Contract Delete beyond what GAS had. If you want a confirmation modal, tell me.

---

## Config keys — status

- [ ] `TLT_CALLBOARD_OPENSIGN_KEY` — SET IN wp-config.php? Test contract send.
- [ ] `TLT_CALLBOARD_RESEND_KEY` — SET IN wp-config.php? Test bio-request emails.
- Optional: `TLT_CALLBOARD_MAIL_FROM`, `TLT_CALLBOARD_MAIL_REPLY_TO`, `TLT_CALLBOARD_MAIL_BCC` (defaults are fine).

## Drive shares — status

Since these were "already shared to season folder" per your last message, most should inherit. If any endpoint returns a 403, share explicitly:

- [ ] Contact Sheet folder `18CAXsUPT2WZgGBDbP-SGZeYbI0W-LSC_`
- [ ] Contact Sheet template `1vFJOkb8GI4SVhjdNIELlhZ8K2BjpK9cJtkfEBVGnz7s`
- [ ] Tech Schedule folder `1eAk4aNXBdbBVG6pJt4GDd9rf3Qg37UJT`
- [ ] Tech Schedule template `138nn2ZR_VKywXYakTWOchtNUzbwv_uebt7VQA5SKuEw`
- [ ] Bios root `1_hUkdeqSFZJtI49MPg52p22GmQnZ58Pq`
- [ ] Contracts root `1azafGrlfByl7kgVtUYBr3JhzVPO34pxZ`
- [ ] Duties Doc `1kEDGRgKmpyzxnop36L77AQXOGaeVYFbnScBh1R_KNLI`
- [ ] Contract templates General `1tfXC6fk7MiqJXMPUYoFPrShe380pV266psAzfGXq0V0`, Director `11M2io31fUcaKyIyfaivxA2Yqm0hdbs2ae2WgKzP_KiQ`, Actor `1SD-bwwuwUMHulsOY1IIhjaK8xNGkek0HMOe8xrScmxw`, Operator `1bdL4jz0GM1gQ1haXQ8uYFvXvQMmhsmc_z2KXR_DJvpQ`
