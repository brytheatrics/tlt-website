"""
The first import_year_summary_shows.py pass missed directors that were
joined to the date line in the same <p> via <br/>. Re-parse the year-summary
posts and update show_director meta on already-imported records.

Idempotent.
"""
import re, sys, io, pymysql
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

YEAR_SUMMARIES = ['2010-2011', '2011-2012', '2012-2013']

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

for season_slug in YEAR_SUMMARIES:
    cur.execute("SELECT post_content FROM wp_posts WHERE post_name=%s AND post_type='post' LIMIT 1", (season_slug,))
    r = cur.fetchone()
    if not r: continue
    body = r[0]

    # For each <h2>Title</h2>, find first <p> in the trailing content and look for
    # "Directed by NAME" inside it (either after a <br/> or at start of separate <p>).
    pattern = re.compile(
        r'<h2[^>]*>\s*(?:<a[^>]*>)?(.*?)(?:</a>)?\s*</h2>(.*?)(?=<h2|$)',
        re.S | re.I
    )
    for m in pattern.finditer(body):
        title = re.sub(r'<[^>]+>', '', m.group(1)).strip()
        rest = m.group(2)
        if not title: continue

        # Search in the first 2 paragraphs for "Directed by NAME"
        ps = re.findall(r'<p[^>]*>(.*?)</p>', rest, re.S)[:3]
        director = ''
        for p in ps:
            # The director might be after a <br/> in the same paragraph,
            # or be the entire next paragraph.
            md = re.search(r'Directed\s+by\s+([^<\n]+?)(?:<br|<|\n|$)', p, re.I)
            if md:
                name = md.group(1).strip()
                # Strip trailing punctuation
                name = re.sub(r'[\s,;]+$', '', name)
                # Stop at next "by" or month indicating spillover
                name = re.split(r'\s+(?:by|January|February|March|April|May|June|July|August|September|October|November|December)\b', name, 1, re.I)[0]
                director = name.strip()
                break
        if not director: continue

        # Find the show by title within this season
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
            print(f"  [skip] {season_slug}: no show record for '{title}'")
            continue
        pid = sr[0]

        cur.execute("SELECT meta_value FROM wp_postmeta WHERE post_id=%s AND meta_key='show_director'", (pid,))
        existing = cur.fetchone()
        if existing and existing[0]:
            print(f"  [skip] {title}: already has director '{existing[0]}'")
            continue

        cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key='show_director'", (pid,))
        cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)", (pid, 'show_director', director))
        print(f"  {season_slug}  {title:<45} <- director: '{director}'")

c.commit()
c.close()
print("\nDone.")
