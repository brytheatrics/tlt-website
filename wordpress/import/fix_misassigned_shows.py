"""
Three shows are in the wrong season terms; move them to 2013-2014:
- steel-magnolias-2  (was in 1998-1999)
- complete-works-of  (was in 2002-2003)
- to-kill-a-mockingbird  (no season at all)

The 1998-1999 and 2002-2003 terms become empty after this. We leave them in
place — they may get re-populated later if we backfill those seasons from
the program-PDF inventory.

Idempotent.
"""
import sys, io, pymysql
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')


def get_or_make_term(cur, slug, name):
    cur.execute("""SELECT tt.term_taxonomy_id FROM wp_terms t
                   JOIN wp_term_taxonomy tt ON tt.term_id=t.term_id
                   WHERE tt.taxonomy='tlt_season' AND t.slug=%s""", (slug,))
    row = cur.fetchone()
    if row: return row[0]
    cur.execute("INSERT INTO wp_terms (name, slug) VALUES (%s,%s)", (name, slug))
    tid = cur.lastrowid
    cur.execute("INSERT INTO wp_term_taxonomy (term_id, taxonomy, description, parent, count) VALUES (%s,'tlt_season','',0,0)", (tid,))
    return cur.lastrowid


def reassign(cur, slug, season_slug, season_name=None):
    cur.execute("SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='tlt_show'", (slug,))
    r = cur.fetchone()
    if not r:
        print(f"  [skip] {slug}: not found")
        return
    pid = r[0]
    # Remove all current season term assignments
    cur.execute("""DELETE tr FROM wp_term_relationships tr
                   JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
                   WHERE tr.object_id=%s AND tt.taxonomy='tlt_season'""", (pid,))
    # Assign to the new season
    tt_id = get_or_make_term(cur, season_slug, season_name or season_slug)
    cur.execute("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id) VALUES (%s,%s)", (pid, tt_id))
    # Recount all season terms
    cur.execute("""SELECT term_taxonomy_id FROM wp_term_taxonomy WHERE taxonomy='tlt_season'""")
    for (ttid,) in cur.fetchall():
        cur.execute("""UPDATE wp_term_taxonomy SET count=(SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id=%s)
                       WHERE term_taxonomy_id=%s""", (ttid, ttid))
    print(f"  {slug} (id={pid}) -> {season_slug}")


c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

print("Reassigning misassigned shows to 2013-2014:\n")
reassign(cur, 'steel-magnolias-2', '2013-2014')
reassign(cur, 'complete-works-of', '2013-2014')
reassign(cur, 'to-kill-a-mockingbird', '2013-2014')

# Also: complete-works-of has show_close_date that implies 2014 — but Blake says
# the actual production was Sept 6-22, 2013. Fix the open_date if it's set wrong.
# Check current value first
cur.execute("SELECT meta_value FROM wp_postmeta WHERE post_id=(SELECT ID FROM wp_posts WHERE post_name='complete-works-of' LIMIT 1) AND meta_key='show_open_date'")
r = cur.fetchone()
if r and r[0]:
    print(f"\n  complete-works-of show_open_date currently: '{r[0]}'")
    # Set dates correctly if not already
    if not r[0].startswith('2013'):
        cur.execute("""UPDATE wp_postmeta SET meta_value='2013-09-06'
                       WHERE post_id=(SELECT ID FROM wp_posts WHERE post_name='complete-works-of' LIMIT 1)
                         AND meta_key='show_open_date'""")
        print("    -> corrected to 2013-09-06")
elif not r:
    # No meta — insert it
    cur.execute("""INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES
                   ((SELECT ID FROM wp_posts WHERE post_name='complete-works-of' LIMIT 1), 'show_open_date', '2013-09-06')""")
    print("\n  complete-works-of: set show_open_date=2013-09-06")
# Same for show_close_date
cur.execute("SELECT meta_value FROM wp_postmeta WHERE post_id=(SELECT ID FROM wp_posts WHERE post_name='complete-works-of' LIMIT 1) AND meta_key='show_close_date'")
r = cur.fetchone()
if not r or not (r and r[0]):
    cur.execute("""INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES
                   ((SELECT ID FROM wp_posts WHERE post_name='complete-works-of' LIMIT 1), 'show_close_date', '2013-09-22')""")
    print("  complete-works-of: set show_close_date=2013-09-22")
elif not r[0].startswith('2013'):
    cur.execute("""UPDATE wp_postmeta SET meta_value='2013-09-22'
                   WHERE post_id=(SELECT ID FROM wp_posts WHERE post_name='complete-works-of' LIMIT 1)
                     AND meta_key='show_close_date'""")
    print(f"  complete-works-of: corrected close_date to 2013-09-22")

c.commit()
c.close()
print("\nDone.")
