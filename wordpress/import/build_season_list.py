"""Build a season-by-season MD list for cross-checking.
- Modern: tlt_show post type grouped by tlt_season taxonomy
- Historic: parse PDF links in decade-summary posts to extract season + show name
- Output: SEASON_LIST.md ordered most-recent first
"""
import os, re, pymysql
from collections import defaultdict

ROOT = os.path.dirname(os.path.abspath(__file__))
PROJECT = os.path.normpath(os.path.join(ROOT, "..", ".."))
OUT = os.path.join(PROJECT, "SEASON_LIST.md")

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

# All seasons with their shows
seasons = defaultdict(list)  # "YYYY-YYYY" -> [{"title":..., "source":"db|pdf|note"}]
notes = defaultdict(list)    # "YYYY-YYYY" -> [warnings]

# 1) Modern shows from tlt_show + tlt_season
cur.execute("""
SELECT t.name AS season, p.post_title, p.ID
FROM wp_posts p
JOIN wp_term_relationships tr ON tr.object_id=p.ID
JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id AND tt.taxonomy='tlt_season'
JOIN wp_terms t ON t.term_id=tt.term_id
WHERE p.post_type='tlt_show' AND p.post_status='publish'
ORDER BY t.name DESC, p.post_title
""")
for season, title, pid in cur.fetchall():
    seasons[season].append({"title": title, "source": "db", "id": pid})

# 2) Historic shows from decade post PDF links (filename pattern: YYYY-YYYY-Show-Name.pdf)
cur.execute("""
SELECT p.post_title, p.post_content
FROM wp_posts p
JOIN wp_postmeta pm ON pm.post_id=p.ID AND pm.meta_key='_migration_legacy_url'
WHERE p.post_status='publish' AND pm.meta_value REGEXP '/blog/2015/[0-9]{4}-[0-9]{4}'
""")
for title, content in cur.fetchall():
    # Find all PDF links
    for href in re.findall(r'href=["\']([^"\']+\.pdf)["\']', content, re.I):
        fname = os.path.basename(href)
        # Strip extension
        stem = re.sub(r'\.pdf$', '', fname, flags=re.I)
        # Pattern: YYYY-YYYY-Show-Name OR YYYY-Show-Name
        m = re.match(r'^(\d{4})[-_](\d{4})[-_](.+)$', stem)
        if m:
            start, end, show = m.groups()
            season = f"{start}-{end}"
        else:
            m = re.match(r'^(\d{4})[-_](.+)$', stem)
            if not m: continue
            start, show = m.groups()
            season = f"{start}-{int(start)+1}"
            notes[season].append(f"Single-year filename '{fname}' — assumed season {season}")
        # Clean show name
        show_clean = re.sub(r'[-_]+', ' ', show).strip()
        # Avoid duplicates within season
        if any( s["title"].lower() == show_clean.lower() for s in seasons[season] ):
            continue
        seasons[season].append({"title": show_clean, "source": "pdf", "filename": fname})

# 3) Compute season number (1 = 1918-1919)
def season_num(season_label):
    m = re.match(r'^(\d{4})-', season_label)
    if not m: return None
    return int(m.group(1)) - 1917

# Build output
lines = []
lines.append("# Tacoma Little Theatre — Complete Show List by Season")
lines.append("")
lines.append("> Cross-reference list. Each entry has source: **db** = currently in WordPress as a Show post; **pdf** = pulled from old program filenames in decade summaries.")
lines.append("")

for season in sorted(seasons.keys(), key=lambda x: x, reverse=True):
    n = season_num(season)
    season_header = f"## Season {n}: {season}" if n else f"## {season}"
    lines.append(season_header)
    if season in notes:
        for note in notes[season]:
            lines.append(f"> ⚠️ {note}")
    # De-dup case-insensitive
    seen = set()
    for s in seasons[season]:
        key = s["title"].lower().strip()
        if key in seen: continue
        seen.add(key)
        src = s["source"]
        marker = "[DB]" if src == "db" else "[program PDF]"
        lines.append(f"- {s['title']}  *{marker}*")
    lines.append("")

# Total tally
total_shows = sum( len({s['title'].lower() for s in shows}) for shows in seasons.values() )
lines.insert(2, f"**Total seasons listed:** {len(seasons)}  ·  **Total unique shows:** {total_shows}")
lines.insert(3, "")

with open(OUT, "w", encoding="utf-8") as f:
    f.write("\n".join(lines))

print(f"Wrote {OUT}")
print(f"  Seasons: {len(seasons)}")
print(f"  Shows: {total_shows}")
c.close()
