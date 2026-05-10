# TLT Website — Cross-Computer Setup

This project syncs across multiple computers using **Git (for code) + Google Drive (for big files)**.

## What lives where

| Content | Sync method | Path |
|---|---|---|
| Code, theme, plugin, scripts, docs (~10 MB) | **Git → GitHub** | `C:\Users\blake\dev\TLT_Website\` |
| Big folders: scrape, assets, mockup (~650 MB) | **Google Drive** | `My Drive\TLT_Assets\` |
| Local WordPress site | **Local by Flywheel Cloud Backups** | `C:\Users\blake\Local Sites\tlt\` |

The big folders **appear** to live in the dev folder via Windows directory junctions, so all paths in scripts and code work normally — but they're physically synced through Drive.

## Set up on a new computer

### 1. Install required apps
- [GitHub Desktop](https://desktop.github.com)
- [Google Drive Desktop](https://www.google.com/drive/download/) (sign in to the same Google account)
- [Local by Flywheel](https://localwp.com)

### 2. Wait for Drive to sync
After installing Drive, give it time to sync `My Drive\TLT_Assets\` (~650 MB). Verify:
```
C:\Users\blake\My Drive (blake@blakeryork.com)\TLT_Assets\scrape\
C:\Users\blake\My Drive (blake@blakeryork.com)\TLT_Assets\assets\
C:\Users\blake\My Drive (blake@blakeryork.com)\TLT_Assets\mockup\
```

### 3. Clone the repo
GitHub Desktop → File → Clone Repository → enter the GitHub URL. Clone to `C:\Users\blake\dev\TLT_Website\`.

### 4. Re-create the directory junctions
Open PowerShell as your user (no admin needed) and run:
```powershell
cd C:\Users\blake\dev\TLT_Website
New-Item -ItemType Junction -Path "scrape" -Target "C:\Users\blake\My Drive (blake@blakeryork.com)\TLT_Assets\scrape"
New-Item -ItemType Junction -Path "assets" -Target "C:\Users\blake\My Drive (blake@blakeryork.com)\TLT_Assets\assets"
New-Item -ItemType Junction -Path "mockup" -Target "C:\Users\blake\My Drive (blake@blakeryork.com)\TLT_Assets\mockup"
```

### 5. Restore the Local WordPress site
In Local app → Connect to Cloud Backup → Google Drive → restore the TLT site backup.

### 6. Restore Claude memory
The Claude memory folder is at `_claude_memory/` in the repo. Symlink the Claude config to point at it:
```powershell
$claudeMemDir = "$env:USERPROFILE\.claude\projects\C--Users-blake-dev-TLT-Website\memory"
if (Test-Path $claudeMemDir) { Remove-Item $claudeMemDir -Recurse -Force }
New-Item -ItemType SymbolicLink -Path $claudeMemDir -Target "C:\Users\blake\dev\TLT_Website\_claude_memory"
```
(SymbolicLink for files needs admin OR Windows Developer Mode enabled. If that errors, just copy-paste the files manually whenever updated.)

## Daily workflow

### Working on Computer A → switching to Computer B

**On Computer A when done:**
1. Save anything you've been editing
2. Open GitHub Desktop → review changes → write a short summary message → click **Commit to main** → click **Push origin**
3. Drive auto-syncs the big files in the background (icon in tray turns from spinner → checkmark when done)

**On Computer B before starting:**
1. Open GitHub Desktop → click **Fetch origin** → if you see "Pull origin (N commits)" click **Pull**
2. Wait for Drive's tray icon to show "in sync"
3. Start working

### What goes through which sync

| If you edit... | It syncs via... |
|---|---|
| `wordpress/` (theme, plugins) | Git |
| `triage/` scripts | Git |
| `*.md`, `*.csv`, `*.json` (docs/data) | Git |
| `scrape/` (HTML scrapes) | Drive |
| `assets/` (logos, history PDFs) | Drive |
| `mockup/` (legacy mockup files) | Drive |
| Local WordPress site (theme/plugin/uploads/database) | Local Cloud Backups |

### Avoid

- **Don't edit the same file from two computers in the same session.** Commit + push from one before opening the other.
- **Don't put `.git` inside `My Drive\`.** Drive can corrupt git internals. The `.git` folder MUST stay in `dev\TLT_Website\`.
- **Don't move scrape/, assets/, or mockup/ back into `dev\`.** They're junctioned for a reason — keeps the git repo small.

## What if something breaks

- **Junction broken / "scrape folder empty":** Re-run the PowerShell commands in Step 4 above.
- **Drive not synced yet:** Check the Drive tray icon. If it's spinning, wait. If it's offline, sign in.
- **Git conflicts after pulling:** GitHub Desktop will show a yellow banner with "Resolve conflicts." Click it; the visual conflict editor walks you through it.
- **Local WordPress site missing:** Use Local → Connect to Cloud Backup → restore.
