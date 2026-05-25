"""
Several shows have credit lines in their post_content body that never got
extracted to the proper meta fields during migration. For example, Rent
has "Musical Direction by Shawna Avinger" in the body but no
show_music_director meta — so the show page only displays "Directed by"
and "Choreographed by" but skips Shawna entirely.

Walk every tlt_show body and extract:
  - "Directed by NAME"                          -> show_director (if missing/empty)
  - "Musically Directed by NAME" |
    "Musical Direction by NAME" |
    "Music Direction by NAME"                   -> show_music_director (if missing)
  - "Choreographed by NAME" |
    "Choreography by NAME"                      -> show_choreographer (if missing)
  - "Co-Directed and Choreographed by NAME"     -> show_choreographer (if missing)
                                                 also notes the co-director

Stop extraction at <br>, <, or end-of-line. Strip trailing junk
(month names, etc.) the same way cleanup_director_fields.py does.

Idempotent — won't overwrite already-set fields.
"""
import re, sys, io, pymysql
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

TRAILING_MARKERS = re.compile(
    r'\s+(?:'
    r'(?:January|February|March|April|May|June|July|August|September|October|November|December)\b'
    r'|Musically\b|Musical\s+Direction\b|Music\s+Direction\b'
    r'|Choreographed\b|Choreographer\b|Choreography\b'
    r'|Co-Directed\b|POSTPONED\b'
    r')'
    r'.*$', re.I
)
def clean(s):
    s = TRAILING_MARKERS.sub('', s)
    return re.sub(r'\s+', ' ', s).strip()


# Capture "<phrase> by NAME" where NAME is everything until <br> or < or end
def find_credit(body, phrase_regex):
    """Return the first NAME after the given phrase pattern, or None."""
    pattern = r'\b' + phrase_regex + r'\s+([^<\n]+?)(?:<|$)'
    m = re.search(pattern, body, re.I)
    if not m: return None
    return clean(m.group(1))


CREDIT_RULES = [
    # (meta_key, phrase_regex)
    ('show_director',       r'Directed\s+by'),
    ('show_music_director', r'(?:Musically\s+Directed|Music(?:al)?\s+Direction)\s+by'),
    ('show_choreographer',  r'(?:Choreographed|Choreography)\s+by'),
]


def get_meta(cur, pid, key):
    cur.execute("SELECT meta_value FROM wp_postmeta WHERE post_id=%s AND meta_key=%s LIMIT 1", (pid, key))
    r = cur.fetchone()
    return r[0] if r else None


def set_meta(cur, pid, key, value):
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, key))
    cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)", (pid, key, value))


c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

cur.execute("SELECT ID, post_name, post_content FROM wp_posts WHERE post_type='tlt_show' AND post_status='publish'")
shows = cur.fetchall()

fixed_count = 0
for pid, slug, body in shows:
    if not body: continue
    for key, phrase in CREDIT_RULES:
        # Skip if already set with a non-empty value
        existing = get_meta(cur, pid, key)
        if existing: continue
        # Also try the Co-Directed-and-Choreographed pattern for the choreographer slot
        if key == 'show_choreographer':
            name = find_credit(body, r'Co-Directed\s+and\s+Choreographed\s+by')
            if name:
                set_meta(cur, pid, key, name)
                print(f"  {slug:<40} {key} <- '{name}' (Co-Directed and Choreographed)")
                fixed_count += 1
                continue
        name = find_credit(body, phrase)
        if name:
            set_meta(cur, pid, key, name)
            print(f"  {slug:<40} {key} <- '{name}'")
            fixed_count += 1

c.commit()
c.close()
print(f"\nDone. Backfilled {fixed_count} credit fields.")
