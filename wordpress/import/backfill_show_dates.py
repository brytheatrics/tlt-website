"""
24 tlt_show records have no show_open_date meta, so they sort to the front
of their season grid in random order. Extract dates from their post_content
body (which has the original Squarespace text with "September 14 - 30, 2018"
type phrases) and combine with the season term's year range to get full dates.

Idempotent.
"""
import sys, io, re, pymysql
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

MONTHS = {
    'jan':1,'january':1,'feb':2,'february':2,'mar':3,'march':3,'apr':4,'april':4,
    'may':5,'jun':6,'june':6,'jul':7,'july':7,'aug':8,'august':8,'sep':9,'sept':9,'september':9,
    'oct':10,'october':10,'nov':11,'november':11,'dec':12,'december':12,
}

# Regex for date ranges like:
#   "October 21st - November 6"
#   "May 9 through June 1"
#   "April 25 - May 11"
#   "January 24 through February 9"
#   "September 14 - 30, 2018"
DATE_RANGE_PATTERNS = [
    # "September 14 through October 11, 2025"
    re.compile(r'(?P<m1>jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\.?\s+(?P<d1>\d{1,2})(?:st|nd|rd|th)?\s*(?:-|–|through|to)\s*(?P<m2>jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\.?\s+(?P<d2>\d{1,2})(?:st|nd|rd|th)?(?:[,\s]+(?P<y>\d{4}))?', re.I),
    # "September 14 - 30, 2018" (same month, day range)
    re.compile(r'(?P<m1>jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\.?\s+(?P<d1>\d{1,2})(?:st|nd|rd|th)?\s*(?:-|–|to)\s*(?P<d2>\d{1,2})(?:st|nd|rd|th)?(?:[,\s]+(?P<y>\d{4}))?', re.I),
]


def parse_date_range(body, season_start, season_end):
    """Try to extract a date range from body. Returns (open_iso, close_iso) or None."""
    text = re.sub(r'<[^>]+>', ' ', body)
    text = re.sub(r'\s+', ' ', text)
    # Skip the navigation menu chunk at top — find first real content
    # Body usually starts with navigation; the date is usually deeper in
    for pattern in DATE_RANGE_PATTERNS:
        for m in pattern.finditer(text):
            d = m.groupdict()
            m1 = MONTHS.get(d['m1'].lower().rstrip('.'))
            m2 = MONTHS.get(d.get('m2', '').lower().rstrip('.')) if d.get('m2') else m1
            d1 = int(d['d1']); d2 = int(d['d2'])
            # Decide year
            if d.get('y'):
                # Explicit year on close
                y2 = int(d['y'])
                # Sometimes the open year is different (Dec → Jan crossover); use season_start if m1 is Sep-Dec
                y1 = season_start if m1 >= 8 else y2
            else:
                # No explicit year — use the season to derive
                y1 = season_start if m1 >= 8 else season_end
                y2 = season_start if m2 >= 8 else season_end
            try:
                return (f"{y1:04d}-{m1:02d}-{d1:02d}", f"{y2:04d}-{m2:02d}-{d2:02d}")
            except ValueError:
                continue
    return None


c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

# Find all shows missing open_date
cur.execute("""SELECT p.ID, p.post_title, p.post_name, p.post_content
               FROM wp_posts p
               WHERE p.post_type='tlt_show' AND p.post_status='publish'
                 AND NOT EXISTS (SELECT 1 FROM wp_postmeta pm WHERE pm.post_id=p.ID AND pm.meta_key='show_open_date' AND pm.meta_value<>'')""")
candidates = cur.fetchall()
print(f"Shows missing open_date: {len(candidates)}\n")

fixed = 0
not_parsed = []

for pid, title, slug, body in candidates:
    # Get season
    cur.execute("""SELECT t.slug FROM wp_term_relationships tr
                   JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
                   JOIN wp_terms t ON t.term_id=tt.term_id
                   WHERE tr.object_id=%s AND tt.taxonomy='tlt_season' LIMIT 1""", (pid,))
    r = cur.fetchone()
    if not r:
        print(f"  [skip] {slug}: no season term")
        continue
    season_slug = r[0]
    m = re.match(r'^(\d{4})-(\d{4})$', season_slug)
    if not m:
        print(f"  [skip] {slug}: season '{season_slug}' not parseable")
        continue
    season_start = int(m.group(1)); season_end = int(m.group(2))

    parsed = parse_date_range(body, season_start, season_end)
    if not parsed:
        not_parsed.append((slug, title, season_slug))
        continue

    open_iso, close_iso = parsed
    # Insert meta
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key='show_open_date'", (pid,))
    cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)", (pid, 'show_open_date', open_iso))
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key='show_close_date'", (pid,))
    cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)", (pid, 'show_close_date', close_iso))
    print(f"  id={pid:<5} {slug:<35} ({season_slug}) -> open={open_iso}, close={close_iso}")
    fixed += 1

c.commit()
c.close()

print(f"\nDone. Fixed: {fixed}.")
if not_parsed:
    print(f"\nCouldn't parse {len(not_parsed)} from body (will need manual entry):")
    for slug, title, season in not_parsed:
        print(f"  {slug:<35} {title:<40} (season {season})")
