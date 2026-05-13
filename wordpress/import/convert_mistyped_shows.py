"""
20 shows were imported from Squarespace as post_type='post' with a '-page'
suffix on their slug, instead of post_type='tlt_show'. As a result they render
with single.php (generic post layout) instead of single-tlt_show.php (which
shows poster, dates, director, content warning, program PDF, videos, JSON-LD).

This script:
1. For each post with a show_open_date meta and a '-page' suffix slug:
   - Changes post_type to 'tlt_show'
   - Strips '-page' from the slug (so URL becomes /shows/<slug>/)
   - Removes any post-type='category' assignments (tlt_show uses tlt_season)
2. For duplicate posts that we know map to existing shows (e.g. another
   'terms-of-endearment' record), marks them draft.

Idempotent: re-running with all posts already converted is a no-op.
"""
import pymysql, sys, io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

# Find all candidates: post_type='post', has show_open_date meta, slug ending in -page
cur.execute("""SELECT p.ID, p.post_title, p.post_name
               FROM wp_posts p
               JOIN wp_postmeta pm ON pm.post_id=p.ID AND pm.meta_key='show_open_date' AND pm.meta_value <> ''
               WHERE p.post_type='post' AND p.post_status='publish' AND p.post_name LIKE '%-page'
               ORDER BY p.ID""")
candidates = cur.fetchall()
print(f"Candidates to convert: {len(candidates)}\n")

converted = 0
skipped = 0
for pid, title, slug in candidates:
    new_slug = slug[:-5]  # strip '-page'

    # Check if the target slug is already in use by a tlt_show (collision)
    cur.execute("SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='tlt_show' AND ID <> %s LIMIT 1", (new_slug, pid))
    if cur.fetchone():
        # Try with year suffix from open_date
        cur.execute("SELECT meta_value FROM wp_postmeta WHERE post_id=%s AND meta_key='show_open_date'", (pid,))
        od = cur.fetchone()
        year = od[0][:4] if od and od[0] else 'legacy'
        new_slug_with_year = f"{new_slug}-{year}"
        cur.execute("SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='tlt_show' AND ID <> %s LIMIT 1", (new_slug_with_year, pid))
        if cur.fetchone():
            print(f"  [skip] {slug} -> {new_slug}: collides, even {new_slug_with_year} is taken")
            skipped += 1
            continue
        new_slug = new_slug_with_year

    # Convert
    cur.execute("""UPDATE wp_posts SET post_type='tlt_show', post_name=%s WHERE ID=%s""", (new_slug, pid))

    # Detach from blog 'category' taxonomy (tlt_show uses tlt_season, not category)
    cur.execute("""DELETE tr FROM wp_term_relationships tr
                   JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
                   WHERE tr.object_id=%s AND tt.taxonomy='category'""", (pid,))

    print(f"  converted id={pid:<5} {slug:<40} -> tlt_show /shows/{new_slug}/  \"{title}\"")
    converted += 1

# Handle the terms-of-endearment duplicate (id 1177 — the other one with a different
# legacy URL pointing at the 2019-2020 chorus line page, but title 'Terms of endearment').
cur.execute("SELECT ID FROM wp_posts WHERE ID=1177 AND post_name='terms-of-endearment' AND post_status='publish'")
if cur.fetchone():
    cur.execute("UPDATE wp_posts SET post_status='draft' WHERE ID=1177")
    print(f"\n  drafted id=1177 (terms-of-endearment duplicate)")

c.commit()
c.close()
print(f"\nDone. Converted: {converted}. Skipped (collisions): {skipped}.")
