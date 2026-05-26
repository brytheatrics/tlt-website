"""
The 2019-2020 season had 8 mainstage shows; two (A Chorus Line and Terms
of Endearment) were cancelled by COVID and later staged in 2021-22 as new
productions. Earlier cleanup folded those 2019-20 records into their
2021-22 counterparts, leaving the 2019-20 season visibly missing those
two slots.

This restores them as separate cancelled records so the historical season
shows all 8 shows again. Dates are approximate — exact opening/closing
would need to come from program PDFs or archived schedules if available.

Idempotent.
"""
import time, pymysql

SHOWS = [
    {
        'slug':  'a-chorus-line-2019',
        'title': 'A Chorus Line',
        'open':  '2020-03-06',
        'close': '2020-03-29',
        'note':  'Originally scheduled for the 2019-2020 season but cancelled due to the COVID-19 pandemic. TLT later staged A Chorus Line in the 2021-2022 season.',
    },
    {
        'slug':  'terms-of-endearment-2019',
        'title': 'Terms of Endearment',
        'open':  '2020-04-03',
        'close': '2020-04-19',
        'note':  'Originally scheduled for the 2019-2020 season but cancelled due to the COVID-19 pandemic. TLT later staged Terms of Endearment in the 2021-2022 season.',
    },
]

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()
now = time.strftime('%Y-%m-%d %H:%M:%S')

# Get 2019-2020 season term
cur.execute("""SELECT tt.term_taxonomy_id FROM wp_terms t
               JOIN wp_term_taxonomy tt ON tt.term_id=t.term_id
               WHERE tt.taxonomy='tlt_season' AND t.slug='2019-2020' LIMIT 1""")
tt_id = cur.fetchone()
if not tt_id:
    print("2019-2020 season term not found"); raise SystemExit(1)
tt_id = tt_id[0]


def set_meta(pid, key, val):
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, key))
    if val != '':
        cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)", (pid, key, val))


for s in SHOWS:
    cur.execute("SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='tlt_show'", (s['slug'],))
    r = cur.fetchone()
    if r:
        print(f"  [exists] {s['slug']} (id={r[0]})")
        continue
    cur.execute("""INSERT INTO wp_posts SET
        post_author=1, post_date=%s, post_date_gmt=%s, post_modified=%s, post_modified_gmt=%s,
        post_content=%s, post_title=%s, post_excerpt='',
        post_status='publish', post_type='tlt_show', post_name=%s,
        comment_status='closed', ping_status='closed', post_parent=0, menu_order=0,
        post_password='', to_ping='', pinged='', post_content_filtered='',
        guid=%s""",
        (now, now, now, now, f'<p>{s["note"]}</p>', s['title'], s['slug'],
         f'http://tlt.local/?p=tlt_show-{s["slug"]}'))
    pid = cur.lastrowid

    set_meta(pid, 'show_open_date',  s['open'])
    set_meta(pid, 'show_close_date', s['close'])
    set_meta(pid, 'show_program_type', 'mainstage')
    set_meta(pid, 'show_cancelled', '1')

    cur.execute("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id) VALUES (%s,%s)", (pid, tt_id))
    print(f"  CREATED id={pid:<5} {s['slug']:<35} ({s['open']} -> {s['close']}, cancelled)")

# Recount season term
cur.execute("UPDATE wp_term_taxonomy SET count=(SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id=%s) WHERE term_taxonomy_id=%s", (tt_id, tt_id))

c.commit()
c.close()
print("\nDone.")
