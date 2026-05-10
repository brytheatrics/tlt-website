# TLT WordPress Deployment Guide

Drop-in deployment of the migrated TLT site. Everything in this `wordpress/` folder is ready to use.

## What's in this folder

```
wordpress/
├── DEPLOYMENT.md                  ← this file
├── plugins/
│   └── tlt-post-types/
│       └── tlt-post-types.php     ← Show, Team, News post types + meta
├── themes/
│   └── tlt/                       ← Custom theme matching the mockup
│       ├── style.css
│       ├── functions.php
│       ├── header.php
│       ├── footer.php
│       ├── front-page.php         ← Auto-rotating hero homepage
│       ├── single-tlt_show.php    ← Show detail w/ Event schema
│       ├── archive-tlt_show.php   ← /shows/ index
│       ├── single-tlt_team.php
│       ├── archive-tlt_team.php
│       ├── page-splash.php        ← Cycling-photo splash template
│       ├── page.php / single.php / index.php
│       └── assets/                ← Logos
└── import/
    ├── tlt-migration.wxr.xml      ← 189 items, drop into WP Admin → Tools → Import
    ├── redirects.htaccess         ← 578 Apache redirects
    ├── redirects.nginx.conf       ← Same, nginx syntax
    ├── redirection-plugin.csv     ← For the WP "Redirection" plugin
    ├── build_wxr.py               ← Re-run if decisions/scrape change
    └── build_redirects.py         ← Re-run if redirect map changes
```

## Path A: Local development (free, no commitment)

The fastest way to see the site in action. **Recommended for first review.**

1. **Install Local by Flywheel** (free): https://localwp.com/
2. Create a new site. Pick PHP 8.x, MySQL 8.x, WordPress latest. Site name "TLT".
3. Open the new site's folder (Local has a "Go to site folder" button).
4. **Copy plugin:** drop `wordpress/plugins/tlt-post-types/` into `app/public/wp-content/plugins/`
5. **Copy theme:** drop `wordpress/themes/tlt/` into `app/public/wp-content/themes/`
6. Visit the WP admin (Local has a "WP Admin" button):
   - **Plugins** → activate "TLT Post Types"
   - **Appearance → Themes** → activate "TLT"
   - Install one more plugin from the directory: **"Redirection"** by John Godley (handles redirects without touching .htaccess)
7. **Import content:**
   - Tools → Import → "WordPress" → Run Importer (it'll prompt to install if needed)
   - Upload `wordpress/import/tlt-migration.wxr.xml`
   - When asked about images: check "Download and import file attachments"
   - Wait — WordPress will pull featured images from Squarespace's CDN (one-time)
8. **Import redirects:**
   - Tools → Redirection → Import/Export
   - Upload `wordpress/import/redirection-plugin.csv`, choose "CSV"
9. **Settings → Permalinks** → choose "Post name" → Save (this flushes rewrite rules)
10. **Settings → Reading** → "Your homepage displays" → Static page → choose your homepage. Set the splash page (if you create one) using the "Splash" template.

You should now have a working local site at the URL Local provides.

## Path B: Live deployment to Cloudways or SiteGround

After local review passes, you'll repeat the same steps on real hosting:

1. **Sign up for hosting** (Cloudways DigitalOcean ~$14/mo, or SiteGround GrowBig ~$8 intro / ~$25 renewal).
2. The host installs WordPress for you in 1-click.
3. SSH or use their file manager: drop the same `plugins/tlt-post-types/` and `themes/tlt/` folders.
4. Same activation, same import, same redirect import.
5. **Stay on a temporary URL** (e.g. `tlt-staging.cloudwaysapps.com`) until you're ready.
6. **DNS cutover at GoDaddy:** point `@` and `www` A records to the new host's IP. Email DNS records (MX, SPF, DKIM) are NOT touched — Google Workspace keeps working.
7. Within minutes, visitors see the new site. Old Squarespace stays live behind the scenes for a few weeks as a safety net (you can let it expire when you're confident).

## Key things to set up after import

These are content/configuration tasks that aren't fully automatable:

- **Menus**: Appearance → Menus → create the Primary menu (Shows, Tickets, Education, Get Involved, About, Visit), Top Bar (Donate, Volunteer), and Footer columns.
- **Site title / tagline**: Settings → General.
- **Custom logo**: Appearance → Customize → Site Identity → upload the long horizontal logo from `wordpress/themes/tlt/assets/logo-long.png`.
- **Splash page**: Pages → Add New → title "Splash" → Template "Splash (cycling production photos)" → Publish. The template auto-pulls the current show.
- **Show photo galleries**: open each Show post, attach 6-12 photos from `\\TLT-SERVER\TLT Photos\`. Featured image already imported from Squarespace.
- **Hardcoded fields**: a few shows may need their `show_open_date` / `show_close_date` filled by hand if the auto-extraction missed them.
- **Front page**: Settings → Reading → Static page → select your "Home" page (or leave it on "Latest posts" to use front-page.php).

## When something doesn't look right

- **Permalinks 404**: Settings → Permalinks → Save Changes (flushes rewrite rules)
- **Show dates not appearing**: Edit the show, scroll to "Show Details" meta box, enter dates
- **Featured image broken**: open show in admin, replace via "Set featured image"
- **Redirects not firing**: enable Redirection plugin debug log; check that .htaccess isn't blocking
- **Theme styles look off**: hard-refresh your browser (Ctrl+Shift+R)

## Re-running the build scripts

If you change `triage/decisions.json` or want to regenerate:

```
cd wordpress/import
python build_wxr.py        # regenerates tlt-migration.wxr.xml
python build_redirects.py  # regenerates redirects
```

The exports are deterministic — same input, same output, no random data.
