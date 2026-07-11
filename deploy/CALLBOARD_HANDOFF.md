# TLT Callboard — Port Handoff

This is a one-page snapshot of what's ported, what needs your config, and what to test. Written by the AI after finishing the pass Blake asked for on 2026-07-10.

## Bottom line

**Every mutation and generator from the GAS callboard is now ported to the WordPress plugin.** You can retire the GAS webapp entirely once you (a) drop in the two API keys below and (b) share the two Drive folders/templates with the service account.

**Bio submission stays on Netlify** per your instruction — the plugin doesn't touch it.

---

## Config you MUST set before contract flows work

Add these `define()` lines to `wp-config.php` on Cloudways (above the `/* That's all, stop editing! */` line):

```php
// OpenSign — value is hardcoded on line 18 of ContractGenerator.js in the
// GAS Drive folder (deploy/callboard-gas-reference/ContractGenerator.js on
// your local disk). Copy the string starting with "opensign." here.
define( 'TLT_CALLBOARD_OPENSIGN_KEY', 'opensign.…REPLACE_ME…' );

// Resend — was in GAS Script Properties as RESEND_API_KEY. Grab yours from
// Resend's dashboard (or transfer the Script Properties value into here).
define( 'TLT_CALLBOARD_RESEND_KEY', 're_XXXXXXXXXXXXXXXXX' );
```

**Without these**, the endpoints return a clear `MISSING_CONFIG`-style error and don't touch anything. Everything else (contact sheet, tech schedule, bios doc, program export, contract *generation*) works without them.

Optional overrides (defaults are fine):
```php
define( 'TLT_CALLBOARD_MAIL_FROM',     'Tacoma Little Theatre <contracts@tacomalittletheatre.com>' );
define( 'TLT_CALLBOARD_MAIL_REPLY_TO', 'tlt@tacomalittletheatre.com' );
define( 'TLT_CALLBOARD_MAIL_BCC',      'contracts@tacomalittletheatre.com' );
```

## Drive access to grant the SA

`tlt-ludus-sync@tlt-bio-app.iam.gserviceaccount.com` needs **Editor** on:

| What                          | ID / Link |
|-------------------------------|-----------|
| Contact Sheet folder          | `18CAXsUPT2WZgGBDbP-SGZeYbI0W-LSC_` — [open](https://drive.google.com/drive/folders/18CAXsUPT2WZgGBDbP-SGZeYbI0W-LSC_) |
| Contact Sheet template        | `1vFJOkb8GI4SVhjdNIELlhZ8K2BjpK9cJtkfEBVGnz7s` — [open](https://docs.google.com/document/d/1vFJOkb8GI4SVhjdNIELlhZ8K2BjpK9cJtkfEBVGnz7s/edit) |
| Tech Schedule folder          | `1eAk4aNXBdbBVG6pJt4GDd9rf3Qg37UJT` |
| Tech Schedule template        | `138nn2ZR_VKywXYakTWOchtNUzbwv_uebt7VQA5SKuEw` |
| Bios root folder              | `1_hUkdeqSFZJtI49MPg52p22GmQnZ58Pq` |
| Contracts root folder         | `1azafGrlfByl7kgVtUYBr3JhzVPO34pxZ` |
| Duties Doc                    | `1kEDGRgKmpyzxnop36L77AQXOGaeVYFbnScBh1R_KNLI` |
| Contract templates (4)        | Same IDs as GAS — `TPL_GENERAL`, `TPL_DIRECTOR`, `TPL_ACTOR`, `TPL_OPERATOR` in the plugin |

If any of these are inside a parent folder that's already Editor-shared with the SA, they inherit — no additional action needed. If any endpoint returns a `drive_copy_http returned 403` or `403 Forbidden`, that's the tell — share the specific folder/file and retry.

Handbook Doc (referenced in emails, not copied) — just needs to be readable by anyone with the link:

- `1uVtm_ZC06MJel5WOW9bY0DSjMqETA6jWBTIbF9HXguk` (already shared publicly if the GAS callboard ever emailed it)

## What was ported

Everything the GAS callboard's Index.html called via `google.script.run`. The frontend uses a `google.script.run` shim that routes calls to WordPress REST endpoints — the callers didn't change.

| GAS function                          | WordPress endpoint                     |
|---------------------------------------|----------------------------------------|
| All Phase 1 reads                     | (already done last session)            |
| `getContactSheetLink`                 | `GET  /contact-sheet-link`             |
| `generateContactSheet`                | `POST /contact-sheet-generate`         |
| `regenerateContactSheet` (new)        | `POST /contact-sheet-regenerate`       |
| `getScheduleLink`                     | `GET  /schedule-link` *(now returns {url,exists,source})* |
| Tech schedule generation              | `POST /tech-schedule-generate`         |
| `compileBiosDoc`                      | `POST /bios-doc-compile`               |
| `sendBioRequestsForShow`              | `POST /bios-send-requests`             |
| `resendBioRequest`                    | `POST /bios-resend`                    |
| `exportProgramFile`                   | `POST /program-export`                 |
| `generateContractFromWebapp`          | `POST /contract-generate`              |
| `generateCombinedContractFromWebapp`  | `POST /contract-generate-combined`     |
| `sendContractFromWebapp`              | `POST /contract-send`                  |
| `sendCombinedContractFromWebapp`      | `POST /contract-send-combined`         |
| `deleteGeneratedContract`             | `POST /contract-delete`                |

Frontend UX additions:
- **Contact Sheet button** — View/Regenerate modal if one exists; auto-generate if not
- **Tech Schedule button** — Same View/Regenerate modal pattern

## What to test (recommended order)

1. **Log in** at the temp URL (or callboard.tacomalittletheatre.com if you've DNS-pointed). Purge the Cloudways Full Page Cache from the dashboard first — the frontend and plugin changes won't appear otherwise.

2. **Sanity check** — click through the tabs. Dashboard, Bios, Contracts, Actors, Programs, Calendar, Contactbook should all render. If not, that's a regression from prior work, not this port.

3. **Contact sheet** — click 👥 Contact Sheet on a show. First time → auto-generate; second time → modal. (You tested this at the end of the last session.)

4. **Tech schedule** — click 📄 View Tech Schedule on a show. Same modal pattern now. Regenerate should trash the old and open a fresh one.

5. **Bios doc** — Bios tab → click "Compile Bio Doc" for a show. Should show a URL to a fresh doc in the season subfolder of Bios root.

6. **Bio request emails** — Bios tab → send request for one person via resend. Watch the person get an email. (Needs `TLT_CALLBOARD_RESEND_KEY` set.)

7. **Program export** — Programs tab → click Export. Should download a `<show> - Program.json` file. Verify it has the same shape as GAS output (compare with a previous Drive export).

8. **Contract generation** — Contracts tab → pick a role → Generate. Should produce a Doc in the season/show folder, matching the GAS layout. **Diff-check the first one you generate** against a GAS-generated one for the same show/role. Layout may differ slightly (see Limitations).

9. **Contract send** — after generating, click Send. Should produce an OpenSign document, email the talent, and update the sheet. (Needs both `TLT_CALLBOARD_OPENSIGN_KEY` and `TLT_CALLBOARD_RESEND_KEY` set.)

10. **Combined contract** — same but with 2+ shows selected.

11. **Delete contract** — should trash the Doc + reset status to "Not Started".

## Known limitations vs. GAS

These are things where the port is functionally correct but not pixel-identical to GAS output:

1. **Contract Duties section formatting** — GAS did precise per-paragraph indentation and spacing on the bulleted duties list. The PHP port uses `createParagraphBullets` with `BULLET_DISC_CIRCLE_SQUARE`, which is Docs API's built-in bullet preset. Bullets appear, but spacing may not perfectly match GAS. Chris probably won't notice.

2. **`<<Board>>` expansion** — GAS preserved the exact font attributes of the source paragraph and inserted per-board-member paragraphs with matching font. The port inserts plain-text paragraphs and relies on the surrounding paragraph style. If the Board block visually drifts (wrong font size), we can post-process the range.

3. **Empty staff block hiding** — Port hides pairs of (empty paragraph + label paragraph) matching the exact labels from `_STAFF_BLOCKS`. If your templates have variant labels ("Managing Artistic Director " with trailing space, or "Managing/Artistic Director"), they may not auto-hide. Fix: normalize the labels in the template.

4. **Combined contracts don't do the GAS "save-reopen" trick** — GAS saved-closed-reopened to force duplicate-tag collapse to settle. The port runs the same collapse but does it in a single pass. If you see doubled show names ("A and B A and B") in a generated combined contract, tell me and I'll add a second-pass collapse call.

5. **PDF page counter** — matches the GAS regex-count trick. For unusual documents it may over/undercount by 1. That shifts widget placement one page. If OpenSign complains or widgets end up on the wrong page, we can swap to a real PDF library.

6. **`getContractsData` in `/contracts`** — already existed from prior work; not touched. If contract statuses look wrong after a send, that's a display bug in the existing read endpoint, not a send bug. Compare `/contracts` output to the sheet directly.

7. **Email body layout** — I hand-built the HTML to visually match GAS's Resend email. The exact fonts/spacing/comp-code styling may look ~90% identical. Send yourself a test bio request and compare with an archived GAS-sent one; if you want tweaks, they're all in `tlt_cb_bio_email_html()` and `tlt_cb_combined_bio_email_html()`.

8. **OpenSign webhook** — not ported, and shouldn't be. Signed status detection continues to be handled by the standalone ContractOrganizer script on the `contracts@` account (per your CLAUDE.md, more reliable than the OpenSign webhook). No changes needed there.

## File locations

- **Plugin**: `wordpress/plugins/tlt-callboard/tlt-callboard.php` (production copy at `/home/master_vdrkzztcte/applications/dtvxxevyxd/public_html/wp-content/plugins/tlt-callboard/tlt-callboard.php`)
- **Frontend (source of truth is the port script + GAS Index.html)**:
  - Source: `deploy/callboard-gas-reference/Index.html` (mirror of the GAS Drive folder)
  - Transformer: `scratchpad/port_callboard.py` (in the temp scratchpad — copy to `deploy/` if you want it in git)
  - Output: `deploy/callboard-frontend/index.html` (deployed to `/callboard/index.html`)
- **Reference GAS files**: `deploy/callboard-gas-reference/` (added this session)

## Redeploying

Same commands as before — from repo root, with `.ssh/tlt_cloudways` as the key:

```bash
# Plugin
tar -czf - -C wordpress/plugins tlt-callboard \
  | ssh -i "$SSH_KEY" "${SSH_USER}@${SSH_HOST}" \
    "cd applications/dtvxxevyxd/public_html/wp-content/plugins && tar -xzf -"

# Frontend
cat deploy/callboard-frontend/index.html \
  | ssh -i "$SSH_KEY" "${SSH_USER}@${SSH_HOST}" \
    "cat > applications/dtvxxevyxd/public_html/callboard/index.html"
```

**Then purge Cloudways Full Page Cache from the dashboard.**

## When you're ready to retire the GAS webapp

Once you've tested end-to-end and are happy:

1. Update the Season tab's `Callboard App Url` setting to point at `https://tacomalittletheatre.com/callboard/` (or wherever the WordPress copy lives).
2. Do NOT delete the GAS project — the syncs (Ludus + CastingManager) don't touch it, but ContractOrganizer on `contracts@` polls for signed contracts and updates the Callboard sheet directly. That flow is untouched.
3. Bio submission on Netlify — leave alone per your instruction.

## When something breaks

- Cloudways cache — always purge from dashboard after every deploy
- `403 Forbidden` on any endpoint — SA doesn't have access to a Drive resource; share it
- `MISSING_CONFIG` — set the constant in wp-config.php
- OpenSign errors — check the `opensign.…` API key is current in wp-config.php
- Resend errors — check the `re_…` API key is current in wp-config.php
- WordPress transient wonkiness — `wp transient delete tlt_cb_google_token_v2` to force a fresh SA token

If a specific contract comes out wrong, dump the source Doc's placeholder list (View → Show Non-printing) and the shape of the assembled data by hitting `POST /contract-generate` with a curl and inspecting the response.
