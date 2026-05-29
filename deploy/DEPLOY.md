# Deploy to Cloudways

How to push the local TLT WordPress site to the Cloudways server, and how to
keep two computers in sync afterward.

**Model:** local-mirror + Cloudways-canonical.
- **Code** → Git (this repo). Theme + plugin only get copied to the server.
- **Database + uploads** → Cloudways is the source of truth between machines.

> **Trial reminder:** the 3-day Cloudways trial server is **deleted when the
> trial ends** unless you upgrade to the paid plan. Use the trial to prove the
> pipeline; upgrade before relying on it as the canonical store.

---

## 0. Fill in these once the server is active

From Cloudways → your app → **Access Details** (Master Credentials):

| Value | Where on Cloudways | Put it here |
|---|---|---|
| Server public IP | Server → Master Credentials | `____.___.___.___` |
| SSH/SFTP username | Server → Master Credentials | `master_____` |
| SSH/SFTP password | Server → Master Credentials | (keep private) |
| App folder name | Application → Access Details ("Application name" / folder) | `_______` |
| Temp URL | Application → Access Details ("Application URL") | `https://wordpress-______-______.cloudwaysapps.com` |

The app's WordPress lives at:
`~/applications/<APP_FOLDER>/public_html/`

WP-CLI (`wp`) is pre-installed on Cloudways — run it from inside `public_html`.

---

## 1. Export the local database

```powershell
python deploy/export_db.py
```

Produces `deploy/dumps/tlt_<timestamp>.sql` (table-only, ~1 MB, no
`CREATE DATABASE`/`USE` so it imports into any target DB).

## 2. Upload code (theme + plugin)

A fresh Cloudways app already has WP core + a default theme/plugins. We add ours.
Use **WinSCP** (GUI, easiest) or `scp`. Upload:

| Local | → Server |
|---|---|
| `wordpress\themes\tlt\` | `~/applications/<APP>/public_html/wp-content/themes/tlt/` |
| `wordpress\plugins\tlt-post-types\` | `~/applications/<APP>/public_html/wp-content/plugins/tlt-post-types/` |

`scp` example (run from repo root):
```powershell
scp -r wordpress\themes\tlt        <USER>@<IP>:applications/<APP>/public_html/wp-content/themes/
scp -r wordpress\plugins\tlt-post-types <USER>@<IP>:applications/<APP>/public_html/wp-content/plugins/
```

## 3. Upload media (uploads/)

~989 MB, 1,130 files. WinSCP directory-sync is most reliable for this many files.

| Local | → Server |
|---|---|
| `C:\Users\blake\Local Sites\tlt\app\public\wp-content\uploads\` | `~/applications/<APP>/public_html/wp-content/uploads/` |

`scp` alternative:
```powershell
scp -r "C:\Users\blake\Local Sites\tlt\app\public\wp-content\uploads\*" <USER>@<IP>:applications/<APP>/public_html/wp-content/uploads/
```

## 4. Import the database

SCP the dump up, then import with WP-CLI (it reads the app's DB creds from
`wp-config.php`, so you don't need the DB name/password):

```powershell
scp deploy\dumps\tlt_<timestamp>.sql <USER>@<IP>:applications/<APP>/public_html/import.sql
ssh <USER>@<IP>
```
Then on the server:
```bash
cd ~/applications/<APP>/public_html
wp db import import.sql
rm import.sql
```

Importing our DB makes the `tlt` theme + `tlt-post-types` plugin active and
brings all content/menus/settings — overwriting the fresh install's defaults.

## 5. Fix URLs (tlt.local → temp domain)

Still on the server, in `public_html`:
```bash
wp search-replace 'http://tlt.local' 'https://wordpress-XXXXXX-XXXXXX.cloudwaysapps.com' --skip-columns=guid --all-tables
wp search-replace 'tlt.local'        'wordpress-XXXXXX-XXXXXX.cloudwaysapps.com' --skip-columns=guid --all-tables
wp cache flush
wp rewrite flush
```

> **Do NOT touch `guid`** (that's why `--skip-columns=guid`). At real DNS
> cutover later you'll re-run search-replace from the temp URL to
> `https://tacomalittletheatre.com`.

## 6. Post-push checks

On the server (or in wp-admin):
```bash
wp theme list      # tlt should be 'active'
wp plugin list     # tlt-post-types should be 'active'
wp post list --post_type=tlt_show --format=count
wp post list --post_type=tlt_team --format=count
```
Then in a browser, open the temp URL and spot-check:
- [ ] Homepage hero + season grid render (images load from uploads)
- [ ] A show detail page (PDF link, gallery)
- [ ] /board-and-staff/, /auditions/, /contact/
- [ ] Mobile drawer nav
- [ ] Splash → home wipe

### Not handled by this push (do on the server afterward)
- **Redirects:** install the **Redirection** plugin, import `wordpress/themes/tlt/redirects.csv`.
- **Email/forms (P0):** WP Mail SMTP + Flamingo — see `LAUNCH_CHECKLIST.md`. Forms
  won't deliver until SMTP is configured.

---

## Cross-computer sync (after the first push)

Cloudways is canonical for DB + uploads. On your **other** computer:

1. `git pull` — gets code.
2. `python deploy/sync_down.py` — pulls the DB from Cloudways and imports it into
   your local `local` DB, then rewrites the temp URL back to `http://tlt.local`.
3. Sync `uploads/` down with WinSCP directory-sync (remote → local) when images change.

To push local changes back up, repeat steps 1–5 above (or `git push` for code).
