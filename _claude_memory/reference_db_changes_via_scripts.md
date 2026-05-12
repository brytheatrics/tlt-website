---
name: Database changes must be committed scripts, not ad-hoc SQL
description: Pattern for keeping WordPress database state in sync across Blake's two computers
type: reference
---

**The problem:** Blake works on two computers. Code syncs via git automatically. The WordPress database does NOT — it only crosses via Local's zip export/import, which is manual and error-prone (forgotten exports, stale snapshots, etc.).

**The pattern:** Any time Claude makes database changes (menu items, post content rewrites, post type reclassification, meta field updates, etc.), DO NOT just run inline SQL via pymysql one-off. Instead:

1. Write the change as a reusable Python script in `wordpress/import/`
2. Run it on the current computer
3. Commit the script to git and push

This way, on the other computer the user can:
1. `git pull`
2. Run the same script
3. End up with the identical DB state

Existing examples of this pattern in the repo:
- `wordpress/import/rebuild_menu_from_b.py` — rebuilds the Primary nav menu
- `wordpress/import/build_menus.py` — original menu build
- `wordpress/import/reclassify.py` — moves posts between types
- `wordpress/import/fix_titles.py` — title cleanup
- `wordpress/import/migrate_program_pdfs.py` — PDF link rewriting

**When this matters most:**
- Menu changes (very visible, hard to debug if missing)
- New posts/pages added programmatically
- Meta field migrations
- Post type reclassifications

**When it doesn't matter:**
- User-driven WP Admin edits (Blake/Chris typing in the editor) — those are one-off and going-live on Cloudways will eliminate the cross-computer problem anyway
- Theme/plugin code changes — those sync via git automatically

**Failure mode to avoid:** writing a quick `python -c "import pymysql; ..."` one-off in a shell session. That change exists only in that DB and disappears the moment the user switches computers. Always save it as a `.py` file that goes through git.
