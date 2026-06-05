"""
Clean show body content (post_content) down to just the synopsis + production
notes, stripping the migrated Squarespace boilerplate/junk: ticket links, date
ranges, showtimes, pricing, Pay-What-You-Can, ASL lines, director credits, run
time, the inline cast, press credits, and the leftover layout/figure HTML.

Run with no args = DRY RUN (prints before/after, writes nothing).
Run with 'apply' = writes cleaned bodies to the DB.

  python clean_show_bodies.py            # dry run, all 2010-2026 shows
  python clean_show_bodies.py SLUG ...   # dry run, only these slugs
  python clean_show_bodies.py apply      # apply to all
"""
import sys, re, html, pymysql

MONTHS = 'january|february|march|april|may|june|july|august|september|october|november|december'

def keep_line(l):
    low = l.lower()
    if re.search(r'click here|buy tickets', low): return False
    if re.match(r'^tickets?$', low): return False
    if re.search(r'directed by|musically directed|music(?:al)?\s+direction|choreograph|stage manage[dr]|scenic design|costume design|lighting design|sound design', low): return False
    if re.search(r'pay what you can|asl interpreted', low): return False
    if re.match(r'^\$\s?\d', l) or re.search(r'adults?\b.{0,40}\b(students|seniors|military|children)', low): return False
    if re.search(r'\brun ?time\b', low): return False
    if re.match(r'^act\s+\d', low): return False
    if re.search(r'there will be (a|an|one)\b.{0,30}intermission', low): return False
    if re.search(r'featuring the talents', low): return False
    if re.match(r'^(the\s+)?cast(\s+of\s+characters)?:?$', low): return False
    if re.match(r'^program$', low): return False
    if re.search(r'(the sound on stage|the suburban times|oly arts)', low) and len(l) < 70: return False
    if re.match(r'^(' + MONTHS + r')\s+\d', low) and ('-' in l or '–' in l): return False      # date range
    if re.search(r'(thursdays?|fridays?|saturdays?|sundays?)\b.{0,40}\d', low): return False           # showtimes
    if re.match(r'^added performances', low): return False
    if re.match(r'^\d+\s+performances', low): return False
    if re.search(r'\bat\s+\d{1,2}:\d{2}\s*[ap]m', low) and len(l) < 90: return False
    if re.match(r"^[A-Z][A-Za-z.'-]+(?:\s+[A-Z][A-Za-z.'-]+){1,3}\s+as\s+", l): return False           # cast line
    if re.match(r'^\(.*\)$', l): return False                                                          # parenthetical only
    if len(re.sub(r'[^a-zA-Z]', '', l)) < 3: return False                                              # junk
    return True

def clean(raw):
    b = re.sub(r'(?i)<\s*(br|/p|/li|/h[1-6]|/div|/figure|/section|/article)\s*/?>', '\n', raw)
    txt = html.unescape(re.sub(r'<[^>]+>', ' ', b))
    txt = re.sub(r'[ \t]+', ' ', txt)
    lines = [re.sub(r'\s+', ' ', l).strip() for l in txt.split('\n')]
    kept = [l for l in lines if l and keep_line(l)]
    # de-dupe consecutive repeats
    out = []
    for l in kept:
        if not out or out[-1] != l:
            out.append(l)
    return '\n'.join('<p>' + l + '</p>' for l in out)

def main():
    args = sys.argv[1:]
    apply = 'apply' in args
    slugs = [a for a in args if a != 'apply']
    c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local', charset='utf8mb4')
    cur = c.cursor(pymysql.cursors.DictCursor)
    cur.execute("""SELECT p.ID,p.post_name,p.post_content,
      (SELECT GROUP_CONCAT(t.name) FROM wp_term_relationships tr JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id AND tt.taxonomy='tlt_season' JOIN wp_terms t ON t.term_id=tt.term_id WHERE tr.object_id=p.ID) season
      FROM wp_posts p WHERE p.post_type='tlt_show' AND p.post_status='publish'""")
    n = 0
    for r in cur.fetchall():
        if slugs and r['post_name'] not in slugs:
            continue
        if not slugs:
            m = re.match(r'^(\d{4})', r['season'] or '')
            if not m or not (2010 <= int(m.group(1)) <= 2025):
                continue
        cleaned = clean(r['post_content'] or '')
        if not apply:
            plain = re.sub(r'<[^>]+>', ' ', cleaned)
            print(f"=== {r['post_name']} ===")
            print('  ', re.sub(r'\s+', ' ', plain).strip()[:300])
            print()
        else:
            cur.execute("UPDATE wp_posts SET post_content=%s WHERE ID=%s", (cleaned, r['ID']))
            n += 1
    if apply:
        c.commit(); print(f"Applied cleaned bodies to {n} shows.")
    c.close()

if __name__ == '__main__':
    main()
