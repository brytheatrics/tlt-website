"""
Import director + cast from the filled-out shows CSV into the tlt_show records.
Only writes show_director and show_cast (leaves titles, dates, blurb untouched).
Names are stored verbatim — no title-casing (e.g. 'pug Bujeaud' stays lowercase).

Idempotent.
"""
import csv, sys, pymysql

CSV = sys.argv[1] if len(sys.argv) > 1 else 'C:/Users/blake/Downloads/2010-2026 missing data - tlt-shows-2010-2026.csv'

def set_meta(cur, pid, key, value):
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, key))
    if value != '':
        cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)", (pid, key, value))

def main():
    rows = list(csv.DictReader(open(CSV, encoding='utf-8-sig')))
    c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
    cur = c.cursor(pymysql.cursors.DictCursor)
    n_dir = n_cast = skipped = 0
    for r in rows:
        pid = (r.get('id') or '').strip()
        if not pid.isdigit():
            continue
        pid = int(pid)
        # confirm it's a real (non-trashed) show
        cur.execute("SELECT post_status FROM wp_posts WHERE ID=%s AND post_type='tlt_show'", (pid,))
        row = cur.fetchone()
        if not row or row['post_status'] != 'publish':
            skipped += 1
            continue
        director = (r.get('director') or '').strip()
        cast = (r.get('cast') or '').strip()
        if director:
            set_meta(cur, pid, 'show_director', director); n_dir += 1
        if cast:
            set_meta(cur, pid, 'show_cast', cast); n_cast += 1
    c.commit(); c.close()
    print(f"Imported: {n_dir} directors, {n_cast} casts. Skipped {skipped} (trashed/missing).")

if __name__ == '__main__':
    main()
