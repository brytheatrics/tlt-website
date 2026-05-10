"""Reclassify imported posts using user notes as ground truth.

A post should be:
  - tlt_show  if user's note contains "show page" or category was clearly modern_show
  - tlt_team  if user's note mentions "current ... director|manager|carpenter|technician|staff|board"
  - tlt_team  if URL is /blog/board/...
  - post      otherwise (news, announcements, fundraising, etc.)
"""
import json, os, re, pymysql

ROOT = os.path.dirname(os.path.abspath(__file__))
PROJECT = os.path.normpath(os.path.join(ROOT, "..", ".."))

decisions = json.load(open(os.path.join(PROJECT, "triage", "decisions.json")))

def correct_type_for(url, note):
    note_l = (note or "").lower()
    path = url.replace("https://www.tacomalittletheatre.com","")

    # Board profiles
    if path.startswith("/blog/board/"): return "tlt_team", "board"

    # Modern shows under /blog/YYYYYYYY/[slug] - keep as show only if user said it's a show
    if re.match(r"^/blog/\d{8}/", path):
        if "show page" in note_l: return "tlt_show", None
        if any(x in note_l for x in ("current ","staff","board")): return "tlt_team", "staff"
        # If note mentions director/manager/carpenter without "current" - still likely team
        # But if no note, default to keeping as show (it's modern season URL)
        if note:
            # Note exists but doesn't say show page — probably an announcement/audition that user kept
            return "post", None
        return "tlt_show", None

    # Notes that explicitly say it's a staff page
    if any(x in note_l for x in [
        "current managing artistic director","current technical director","current development director",
        "current ed director","current lead carpenter","current shop technician","current box office",
        "current production manager","current artistic"
    ]):
        return "tlt_team", "staff"

    # Generic "current X" patterns
    if re.search(r"\bcurrent\b.*\b(director|manager|carpenter|technician|lead|staff)\b", note_l):
        return "tlt_team", "staff"

    # Notes ending in "show page" or containing "show page"
    if "show page" in note_l: return "tlt_show", None

    # Tag/category landing pages used as season landings - keep as Show? No, they're tag pages
    # User notes called them "season landing page" - those should be Pages in /seasons/ taxonomy
    # For now, demote to post (news) so they don't clutter shows
    if "/blog/category/" in path or "/blog/tag/" in path:
        return "post", None

    # Decade pages (e.g. /blog/2015/1918-1930)
    if re.match(r"^/blog/2015/\d{4}-\d{4}", path):
        return "post", None  # historical archive content - will become /history page sections

    # Anything else under /blog/[year]/ — fundraising, audition history, news, etc.
    return "post", None

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

# Get all posts with a legacy URL meta (i.e. things we imported)
cur.execute("""
SELECT p.ID, p.post_type, pm.meta_value AS legacy_url
FROM wp_posts p
JOIN wp_postmeta pm ON pm.post_id = p.ID
WHERE pm.meta_key = '_migration_legacy_url' AND p.post_status = 'publish'
""")
rows = cur.fetchall()

changes = 0
type_changes = {"tlt_show->post":0, "tlt_show->tlt_team":0, "post->tlt_team":0, "post->tlt_show":0}
for post_id, current_type, url in rows:
    dec = decisions.get(url, {})
    note = dec.get("note","")
    correct, role = correct_type_for(url, note)
    if correct != current_type:
        cur.execute("UPDATE wp_posts SET post_type=%s WHERE ID=%s", (correct, post_id))
        # If becoming team, set the team meta flags
        if correct == "tlt_team":
            cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s, %s, %s)",
                        (post_id, "team_is_board" if role=="board" else "team_is_staff", "1"))
        key = f"{current_type}->{correct}"
        type_changes[key] = type_changes.get(key, 0) + 1
        changes += 1

c.commit()

print(f"Reclassified {changes} posts:")
for k, n in type_changes.items():
    if n: print(f"  {k}: {n}")

# Final counts
for pt in ("tlt_show","tlt_team","post","page"):
    cur.execute("SELECT COUNT(*) FROM wp_posts WHERE post_type=%s AND post_status='publish'", (pt,))
    print(f"  {pt}: {cur.fetchone()[0]}")
c.close()
