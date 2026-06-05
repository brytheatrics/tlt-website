"""
Strip leftover Squarespace migration artifacts from tlt_show bodies that the
earlier clean_show_bodies.py pass didn't catch:

  - "View fullsize"            -> Squarespace image-caption junk
  - "REVIEWS" + the publication-name list under it -> these were hyperlinks to
    external reviews on Squarespace; migration dropped the hrefs, leaving dead
    plain text. We match the list against a known-publication whitelist so we
    never eat real synopsis copy (some shows put the REVIEWS list ABOVE the
    blurb, e.g. a tagline like "Everything's coming up roses").
  - orphaned cast headers "STARRING" / "Starring:" / "CAST LIST" / "THE CAST"
    (the cast is rendered structurally from show_cast meta now)

We deliberately DO NOT touch:
  - content-warning lines (STRONG PROFANITY, FLASHING LIGHT, etc.)
  - stale-but-real announcements (POSTPONED, EXTENDED TO ..., ADDED PERFORMANCE)
    -> these are listed at the end for human review instead.

Run with no args = DRY RUN (prints before/after, writes nothing).
Run with 'apply' = writes cleaned bodies to the DB.
"""
import sys, re, pymysql

PUBS = [
    'dresdner', 'tacoma weekly', 'tacoma news', 'news tribune', 'weekly volcano',
    'axs.com', 'komo', 'nw adv', 'nw adev', 'northwest adventure', "talkin",
    'drama in the hood', 'suburban times', 'oly arts', 'sound on stage',
    'broadway world', 'broadwayworld', 'the news tribune',
]
HEADER_DROP = {'starring', 'starring:', 'cast list', 'the cast'}
ANNOUNCE_RE = re.compile(r'^(postponed|extended to|added performance|special added|special .*performance|special halloween)', re.I)


def is_pub(t):
    low = t.lower().strip().rstrip('.')
    if len(t) > 45:
        return False
    return any(k in low for k in PUBS)


def process(body):
    spans = []        # (start, end) char ranges to delete
    announcements = []
    mode_reviews = False
    for m in re.finditer(r'<p>(.*?)</p>', body, re.S):
        t = re.sub('<[^>]+>', '', m.group(1)).strip()
        low = t.lower()
        drop = False
        if t == 'View fullsize':
            drop = True
        elif low == 'reviews':
            drop = True
            mode_reviews = True
        elif is_pub(t):
            # A known review-publication name on its own line is always a dead
            # migration link (some shows have no "REVIEWS" header above the list).
            drop = True
        elif mode_reviews:
            mode_reviews = False
        if not drop and low in HEADER_DROP:
            drop = True
        if not drop and ANNOUNCE_RE.match(t):
            announcements.append(t)
        if drop:
            spans.append((m.start(), m.end()))
    for s, e in reversed(spans):
        body = body[:s] + body[e:]
    body = re.sub(r'\n{3,}', '\n\n', body).strip()
    return body, len(spans), announcements


def main():
    apply = 'apply' in sys.argv[1:]
    c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local', charset='utf8mb4')
    cur = c.cursor(pymysql.cursors.DictCursor)
    cur.execute("SELECT ID,post_name,post_content FROM wp_posts WHERE post_type='tlt_show' AND post_status='publish'")
    rows = cur.fetchall()
    changed = 0
    all_announce = []
    for r in rows:
        orig = r['post_content'] or ''
        new, nremoved, announce = process(orig)
        if announce:
            all_announce.append((r['post_name'], announce))
        if new != orig:
            changed += 1
            if not apply:
                removed = [re.sub('<[^>]+>', '', p).strip() for p in re.findall(r'<p>(.*?)</p>', orig, re.S)]
                kept = set(re.sub('<[^>]+>', '', p).strip() for p in re.findall(r'<p>(.*?)</p>', new, re.S))
                gone = [x for x in removed if x not in kept]
                print(f"=== {r['post_name']}  (-{nremoved}) ===")
                for g in gone:
                    print('   DROP:', g[:80])
                print()
            else:
                cur.execute("UPDATE wp_posts SET post_content=%s WHERE ID=%s", (new, r['ID']))
    if apply:
        c.commit()
        print(f"Applied to {changed} shows.")
    else:
        print(f"[DRY RUN] would change {changed} shows.")
    if all_announce:
        print("\n--- STALE ANNOUNCEMENTS (left in place for human review) ---")
        for name, items in all_announce:
            print(f"  {name}: {', '.join(items)}")
    c.close()


if __name__ == '__main__':
    main()
