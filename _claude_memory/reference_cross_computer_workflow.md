---
name: TLT cross-computer dev workflow
description: How Blake's two computers stay in sync (git + Drive + junctions); answer "what should I do?" questions about syncing
type: reference
---

Blake works on the TLT website project from two Windows computers (BRY_DELL is the home computer; the other is his work computer). They sync via three layers:

**1. Git (GitHub) for code/docs/data**
- Repo: `https://github.com/brytheatrics/tlt-website`
- Local clone: `C:\Users\blake\dev\TLT_Website\` (or `tlt-website` lowercase on the home computer)
- Tracked: `wordpress/` (theme + plugin), `triage/` scripts, `*.md` docs, `*.csv`/`*.json` data files, `_claude_memory/`
- Day-to-day tool: **GitHub Desktop** (visual UI, no command-line)

**2. Google Drive for big files**
- Drive root on each computer: `C:\Users\blake\My Drive (blake@blakeryork.com)\`
- Synced subfolder: `TLT_Assets\` containing `scrape\`, `assets\`, `mockup\`
- Inside the dev folder, those three names are **Windows directory junctions** pointing into Drive's `TLT_Assets\` — so paths in scripts work but actual files live in Drive
- Also in Drive: `TLT_Tools\` with PowerShell helper scripts (`RUN-DIAGNOSTIC.bat`, `setup-junctions.ps1`)

**3. Live WordPress site (Local by Flywheel)**
- Path: `C:\Users\blake\Local Sites\tlt\app\public\`
- The `wp-content/themes/tlt/` and `wp-content/plugins/tlt-post-types/` inside the live site are **junctions to the git-tracked dev folder** — so any git pull instantly updates the live site, no copy step needed
- Database, uploads, and other plugins (e.g. ACF when installed) live only inside the Local site; sync those between computers via Local's Cloud Backup or manual zip export → Drive

**Daily workflow Blake should follow:**

When done working on Computer A → ready to move to Computer B:
1. Save files
2. Open GitHub Desktop → write a commit message describing the change → click **Commit to main** → click **Push origin**
3. (Drive auto-syncs in background — don't have to do anything for those)

When starting on the other computer:
1. Open GitHub Desktop → click **Fetch origin** → if it shows "Pull origin (N)" click it
2. (Drive auto-syncs in background)
3. Refresh browser at `tlt.local` — changes should be live (junctions pick up new files automatically)

**Things that DON'T need a copy step anymore (because of junctions):**
- Theme file edits (anything in `wordpress/themes/tlt/`)
- Plugin file edits (anything in `wordpress/plugins/tlt-post-types/`)
- redirects.csv in the theme

**Things that DO need extra effort to cross computers:**
- Database changes (page content edited in WP admin) — sync via Local Cloud Backup or zip export to Drive
- Uploaded media files (Media Library) — same as above
- New plugins installed via WP admin — same as above

**One-time setup per computer (already done on both):**
1. Install GitHub Desktop, Google Drive Desktop, Local by Flywheel
2. Clone the GitHub repo to `C:\Users\blake\dev\` (don't pre-create the folder; let clone make it)
3. Wait for Drive to sync `TLT_Assets\`
4. Run `My Drive\TLT_Tools\setup-junctions.ps1` (sets up theme/plugin junctions to git-tracked dev folder)
5. The PowerShell scripts in `TLT_Tools\` use `$env:USERPROFILE` so they auto-adapt to whatever the username is

**If something breaks:**
- `RUN-DIAGNOSTIC.bat` in `TLT_Tools\` runs auto-detection of common problems (junction missing, redirects.csv conflicts, /home/ URL test) and outputs `diagnostic-output.txt` — Drive syncs it back so Claude can read it
- Common issue: browser cache. Always try Ctrl+Shift+R first.

The full workflow is also documented in `SETUP.md` at the repo root — that's the file Blake refers to when setting up a new computer; this memory is for me to answer his questions about the workflow without him having to find that file.
