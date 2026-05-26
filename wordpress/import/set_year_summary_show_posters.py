"""
Each year-summary post (2010-11, 2011-12, 2012-13) has <img> poster URLs
paired with each <h2>show title</h2>. Map them back to the tlt_show records
we just imported and set _thumbnail_external_url.

Idempotent.
"""
import re, sys, io, pymysql
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

YEAR_SUMMARIES = ['2010-2011', '2011-2012', '2012-2013']

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

set_count = 0

for season_slug in YEAR_SUMMARIES:
    cur.execute("SELECT post_content FROM wp_posts WHERE post_name=%s AND post_type='post' LIMIT 1", (season_slug,))
    r = cur.fetchone()
    if not r: continue
    body = r[0]

    for m in re.finditer(r'<h2[^>]*>(?:<a[^>]*>)?(.*?)(?:</a>)?</h2>(.*?)(?=<h2|$)', body, re.S | re.I):
        title = re.sub(r'<[^>]+>', '', m.group(1)).strip()
        if not title: continue
        rest = m.group(2)
        img_m = re.search(r'<img[^>]+src=["\']([^"\']+)["\']', rest)
        if not img_m: continue
        url = img_m.group(1)

        # Find the tlt_show in this season by title
        cur.execute("""SELECT p.ID FROM wp_posts p
                       JOIN wp_term_relationships tr ON tr.object_id=p.ID
                       JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
                       JOIN wp_terms t ON t.term_id=tt.term_id
                       WHERE p.post_type='tlt_show' AND p.post_status='publish'
                         AND tt.taxonomy='tlt_season' AND t.slug=%s
                         AND LOWER(p.post_title)=LOWER(%s)
                       LIMIT 1""", (season_slug, title))
        sr = cur.fetchone()
        if not sr:
            print(f"  [skip] {season_slug}: no show '{title}'")
            continue
        pid = sr[0]

        # Set thumbnail if missing
        cur.execute("SELECT meta_value FROM wp_postmeta WHERE post_id=%s AND meta_key='_thumbnail_external_url'", (pid,))
        existing = cur.fetchone()
        if existing and existing[0]:
            print(f"  [skip] {season_slug}: '{title}' already has thumb")
            continue
        cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)", (pid, '_thumbnail_external_url', url))
        print(f"  {season_slug}  {title:<40} <- {url}")
        set_count += 1

c.commit()
c.close()
print(f"\nDone. Set {set_count} poster thumbnails.")
