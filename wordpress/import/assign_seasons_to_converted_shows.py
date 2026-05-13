"""
The 21 shows we just converted from post to tlt_show have no tlt_season term
assignment, so they don't show up in /prior-seasons/ or per-season archives.

Pick the right season based on each show's show_open_date:
- A show opening in Sept-Dec maps to that season (e.g. Sept 2018 -> 2018-2019)
- A show opening in Jan-Aug maps to the prior-fall season (e.g. Mar 2022 -> 2021-2022)

Idempotent — won't add a season term twice.
"""
import sys, io, pymysql
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

def season_for_date(date_str):
    """Return season slug like '2021-2022' for a YYYY-MM-DD date string."""
    if not date_str or len(date_str) < 7:
        return None
    y = int(date_str[:4])
    m = int(date_str[5:7])
    if m >= 8:  # August forward = start of new season
        return f"{y}-{y+1}"
    else:
        return f"{y-1}-{y}"


def ensure_term(cur, slug, name):
    """Get or create a tlt_season term. Returns term_taxonomy_id."""
    cur.execute("""SELECT t.term_id, tt.term_taxonomy_id FROM wp_terms t
                   JOIN wp_term_taxonomy tt ON tt.term_id=t.term_id
                   WHERE tt.taxonomy='tlt_season' AND t.slug=%s""", (slug,))
    row = cur.fetchone()
    if row: return row[1]
    cur.execute("INSERT INTO wp_terms (name, slug) VALUES (%s, %s)", (name, slug))
    tid = cur.lastrowid
    cur.execute("""INSERT INTO wp_term_taxonomy (term_id, taxonomy, description, parent, count)
                   VALUES (%s, 'tlt_season', '', 0, 0)""", (tid,))
    return cur.lastrowid


c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

# Get all tlt_show records that lack a tlt_season term and have an open_date
cur.execute("""SELECT p.ID, p.post_title, p.post_name, pm.meta_value as open_date
               FROM wp_posts p
               JOIN wp_postmeta pm ON pm.post_id=p.ID AND pm.meta_key='show_open_date' AND pm.meta_value <> ''
               WHERE p.post_type='tlt_show' AND p.post_status='publish'
                 AND NOT EXISTS (
                   SELECT 1 FROM wp_term_relationships tr
                   JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
                   WHERE tr.object_id=p.ID AND tt.taxonomy='tlt_season'
                 )""")
candidates = cur.fetchall()
print(f"Shows missing season term: {len(candidates)}\n")

assigned = 0
for pid, title, slug, open_date in candidates:
    season_slug = season_for_date(open_date)
    if not season_slug:
        print(f"  [skip] {slug}: open_date '{open_date}' unparseable")
        continue
    tt_id = ensure_term(cur, season_slug, season_slug)
    cur.execute("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id) VALUES (%s, %s)", (pid, tt_id))
    cur.execute("""UPDATE wp_term_taxonomy SET count=(SELECT COUNT(*) FROM wp_term_relationships
                   WHERE term_taxonomy_id=%s) WHERE term_taxonomy_id=%s""", (tt_id, tt_id))
    print(f"  {slug:<40} open={open_date} -> season {season_slug}")
    assigned += 1

# Handle Wizard of Oz and Manchurian Candidate (no open_date)
# Based on Squarespace URL pattern: 20212022 = 2021-2022 season
for slug, season_slug in [
    ('wizard-of-oz', '2021-2022'),
    ('manchurian-candidate', '2019-2020'),
]:
    cur.execute("SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='tlt_show'", (slug,))
    r = cur.fetchone()
    if not r: continue
    pid = r[0]
    # Check if already has season
    cur.execute("""SELECT 1 FROM wp_term_relationships tr
                   JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
                   WHERE tr.object_id=%s AND tt.taxonomy='tlt_season'""", (pid,))
    if cur.fetchone(): continue
    tt_id = ensure_term(cur, season_slug, season_slug)
    cur.execute("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id) VALUES (%s, %s)", (pid, tt_id))
    cur.execute("""UPDATE wp_term_taxonomy SET count=(SELECT COUNT(*) FROM wp_term_relationships
                   WHERE term_taxonomy_id=%s) WHERE term_taxonomy_id=%s""", (tt_id, tt_id))
    print(f"  {slug:<40} (no open_date) -> season {season_slug}")
    assigned += 1

c.commit()
c.close()
print(f"\nDone. Assigned: {assigned}.")
