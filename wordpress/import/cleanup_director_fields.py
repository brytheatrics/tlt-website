"""
Many show records have trailing junk on credit fields from the Squarespace
scrape — month names ("Trina Williamson October"), "Musically Directed"
fragments, "POSTPONED" markers, etc. Applies to:
  - show_director
  - show_music_director
  - show_choreographer

Strip everything from the first occurrence of any trailing marker word.
Also resolves duplicate meta rows by keeping the cleaner value.

Idempotent.
"""
import re, pymysql

# Trailing patterns to strip. Order matters — first match wins.
# Each pattern matches " <marker>..." where everything from " <marker>" onward gets cut.
TRAILING_MARKERS = re.compile(
    r'\s+(?:'
    r'(?:January|February|March|April|May|June|July|August|September|October|November|December)\b'
    r'|Musically\b'
    r'|Musical\s+Direction\b'
    r'|Choreographed\b'
    r'|Choreographer\b'
    r'|Co-directed\b'
    r'|POSTPONED\b'
    r')'
    r'.*$',
    re.I
)


def clean(s):
    if not s: return s
    s = TRAILING_MARKERS.sub('', s)
    s = re.sub(r'\s+', ' ', s).strip()
    return s


CREDIT_FIELDS = ['show_director', 'show_music_director', 'show_choreographer']

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

# Resolve duplicate meta rows per (post, field): keep the cleaner value
for field in CREDIT_FIELDS:
    cur.execute("""SELECT post_id, GROUP_CONCAT(meta_id ORDER BY meta_id), COUNT(*)
                   FROM wp_postmeta WHERE meta_key=%s
                   GROUP BY post_id HAVING COUNT(*) > 1""", (field,))
    for pid, ids_csv, n in cur.fetchall():
        ids = [int(x) for x in ids_csv.split(',')]
        cur.execute("SELECT meta_id, meta_value FROM wp_postmeta WHERE meta_id IN (%s)" % ','.join(map(str, ids)))
        rows = cur.fetchall()
        rows_sorted = sorted(rows, key=lambda r: (1 if 'POSTPONED' in (r[1] or '') else 0, len(r[1] or '')))
        keep_id = rows_sorted[0][0]
        drop_ids = [r[0] for r in rows_sorted[1:]]
        cur.execute("DELETE FROM wp_postmeta WHERE meta_id IN (%s)" % ','.join(map(str, drop_ids)))
        print(f"  post {pid} {field}: deduped {n} rows, kept meta_id={keep_id}")

# Clean every value in every credit field
fixed = 0
for field in CREDIT_FIELDS:
    cur.execute("""SELECT pm.meta_id, pm.post_id, p.post_name, pm.meta_value
                   FROM wp_postmeta pm
                   JOIN wp_posts p ON p.ID=pm.post_id
                   WHERE pm.meta_key=%s AND p.post_type='tlt_show'""", (field,))
    for mid, pid, slug, val in cur.fetchall():
        cleaned = clean(val)
        if cleaned != val:
            cur.execute("UPDATE wp_postmeta SET meta_value=%s WHERE meta_id=%s", (cleaned, mid))
            print(f"  {slug:<42} {field:<22} '{val}' -> '{cleaned}'")
            fixed += 1

c.commit()
c.close()
print(f"\nDone. Cleaned {fixed} credit fields.")
