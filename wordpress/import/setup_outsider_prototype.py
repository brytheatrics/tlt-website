"""Create a stub 'The Outsider' show entry in the DB for hero prototyping."""
import pymysql, time, json, os

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()
now = time.strftime('%Y-%m-%d %H:%M:%S')

# Check if exists
cur.execute("SELECT ID FROM wp_posts WHERE post_name='the-outsider' AND post_type='tlt_show'")
existing = cur.fetchone()
if existing:
    pid = existing[0]
    print(f"The Outsider already exists (ID={pid})")
else:
    cur.execute("""INSERT INTO wp_posts SET
        post_author=1, post_date=%s, post_date_gmt=%s, post_modified=%s, post_modified_gmt=%s,
        post_content=%s, post_title=%s, post_excerpt=%s,
        post_status='publish', post_type='tlt_show', post_name='the-outsider',
        comment_status='closed', ping_status='closed', post_parent=0, menu_order=0,
        post_password='', to_ping='', pinged='', post_content_filtered='',
        guid='http://tlt.local/?p=the-outsider'""",
        (now,now,now,now,
         '<p>A political comedy by Paul Slade Smith — opening the 2026-2027 season.</p>',
         'THE OUTSIDER',
         'A political comedy by Paul Slade Smith'))
    pid = cur.lastrowid
    print(f"Created The Outsider (ID={pid})")

# Set meta fields so it shows on the hero
fields = {
    'show_open_date': '2026-09-25',
    'show_close_date': '2026-10-11',
    'show_program_type': 'mainstage',
    'show_director': 'TBD',
    'show_run_time': 'Approx. 2 hrs · One 15-minute intermission',
    'show_age_rec': 'Recommended for general audiences',
    'show_ticket_url': 'https://tlt.ludus.com/',
    'show_tagline': 'A political comedy — playing September 25 to October 11, 2026',
}
for k, v in fields.items():
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, k))
    cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)", (pid, k, v))

# Also assign to 2026-2027 season term
cur.execute("SELECT t.term_id, tt.term_taxonomy_id FROM wp_terms t JOIN wp_term_taxonomy tt ON tt.term_id=t.term_id WHERE tt.taxonomy='tlt_season' AND t.slug='2026-2027'")
sr = cur.fetchone()
if not sr:
    cur.execute("INSERT INTO wp_terms (name, slug) VALUES ('2026-2027','2026-2027')")
    tid = cur.lastrowid
    cur.execute("INSERT INTO wp_term_taxonomy (term_id, taxonomy, description, parent, count) VALUES (%s,'tlt_season','',0,0)", (tid,))
    sr = (tid, cur.lastrowid)
cur.execute("SELECT 1 FROM wp_term_relationships WHERE object_id=%s AND term_taxonomy_id=%s", (pid, sr[1]))
if not cur.fetchone():
    cur.execute("INSERT INTO wp_term_relationships (object_id, term_taxonomy_id) VALUES (%s,%s)", (pid, sr[1]))
cur.execute("UPDATE wp_term_taxonomy SET count=(SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id=%s) WHERE term_taxonomy_id=%s", (sr[1], sr[1]))

c.commit()
c.close()
print(f"Outsider configured. Slug: the-outsider, dates: 2026-09-25 to 2026-10-11.")
print(f"Once layers are in /wp-content/uploads/hero-layers/the-outsider/, hero will engage.")
