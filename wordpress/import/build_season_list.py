"""Build a deduplicated, chronologically-sorted master list of every TLT show.

Three sources of truth (read-only):
  A. tlt_show records in WordPress DB                      [DB]            (highest priority)
  B. Program PDFs on //TLT-SERVER/TLT Programs/            [program PDF]
  C. Decade summary blog posts in DB                       [decade post]   (lowest priority)

Outputs:
  - SEASON_LIST.md                       master list, season-by-season, newest first
  - _planning/season_list_audit.md       companion audit report

No DB writes. Idempotent.
"""
import os
import re
import html
import pymysql
from collections import defaultdict, OrderedDict

ROOT = os.path.dirname(os.path.abspath(__file__))
PROJECT = os.path.normpath(os.path.join(ROOT, "..", ".."))
SERVER = "//TLT-SERVER/TLT Programs"
OUT_MAIN = os.path.join(PROJECT, "SEASON_LIST.md")
OUT_AUDIT = os.path.join(PROJECT, "_planning", "season_list_audit.md")

TLT_FOUNDED = 1918  # season 1 = 1918-1919

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def season_label(start_year):
    return f"{start_year}-{start_year + 1}"


def season_num(label):
    m = re.match(r"^(\d{4})-", label)
    if not m:
        return None
    return int(m.group(1)) - TLT_FOUNDED + 1


def norm_title(t):
    """Case-fold and strip punctuation/whitespace for dedupe comparison.
    "HALF WAY UP THE TREE" and "Halfway Up The Tree" should compare equal.
    "Inherit the Wind" and "INHERIT THE WIND" should compare equal.
    """
    t = html.unescape(t or "")
    t = t.replace("’", "'").replace("‘", "'")
    t = t.replace("“", '"').replace("”", '"')
    t = t.replace("�", "'")  # mojibake from decade posts (curly quotes)
    # Strip articles to make "THE FOREIGNER" and "Foreigner" match.
    s = re.sub(r"[^a-z0-9]+", "", t.lower())
    return s


def clean_title_for_display(t):
    t = html.unescape(t or "").strip()
    t = t.replace("’", "'").replace("‘", "'")
    t = t.replace("“", '"').replace("”", '"')
    t = t.replace("�", "'")  # mojibake replacement char -> apostrophe
    t = re.sub(r"\s+", " ", t)
    return t


SOURCE_PRIORITY = {"db": 3, "pdf": 2, "decade": 1}
SOURCE_MARKER = {"db": "[DB]", "pdf": "[program PDF]", "decade": "[decade post]"}


# ---------------------------------------------------------------------------
# DB connection
# ---------------------------------------------------------------------------
conn = pymysql.connect(
    host="127.0.0.1", port=10005, user="root", password="root", database="local",
    charset="utf8mb4",
)
cur = conn.cursor()


# ---------------------------------------------------------------------------
# Source A: tlt_show records
# ---------------------------------------------------------------------------
print("Loading DB shows...")

# Pull posts, meta, and term assignments in three separate queries to avoid the
# Cartesian explosion that LEFT JOINing all of them at once produces (a show
# with N seasons and M open_date meta values otherwise yields N*M rows).
cur.execute(
    """
    SELECT ID, post_title, post_name
    FROM wp_posts
    WHERE post_type = 'tlt_show' AND post_status = 'publish'
    """
)
posts = {pid: (title, slug) for pid, title, slug in cur.fetchall()}

cur.execute(
    """
    SELECT post_id, meta_key, meta_value
    FROM wp_postmeta
    WHERE meta_key IN ('show_open_date', 'show_close_date')
      AND post_id IN ({})
    """.format(",".join(str(p) for p in posts) or "0")
)
meta = defaultdict(dict)
for pid, k, v in cur.fetchall():
    # If a show has multiple values for the same key, prefer the first non-empty.
    if v and not meta[pid].get(k):
        meta[pid][k] = v

cur.execute(
    """
    SELECT tr.object_id, t.slug
    FROM wp_term_relationships tr
    JOIN wp_term_taxonomy tt
      ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'tlt_season'
    JOIN wp_terms t ON t.term_id = tt.term_id
    WHERE tr.object_id IN ({})
    """.format(",".join(str(p) for p in posts) or "0")
)
post_seasons = defaultdict(list)
for pid, slug in cur.fetchall():
    post_seasons[pid].append(slug)

db_shows = []
db_no_season = []
db_season_mismatch = []
db_multi_season = []  # shows assigned to multiple season terms

for pid, (title, slug) in posts.items():
    title_clean = clean_title_for_display(title)
    seasons_for_post = post_seasons.get(pid, [])
    if not seasons_for_post:
        db_no_season.append((pid, title_clean))
        continue

    open_date = meta.get(pid, {}).get("show_open_date", "") or ""
    close_date = meta.get(pid, {}).get("show_close_date", "") or ""

    if len(seasons_for_post) > 1:
        db_multi_season.append((pid, title_clean, sorted(seasons_for_post)))

    # Pick the single best season for this post:
    #   - if open_date is present and exactly one season matches it, use that
    #   - else use the season closest to the open_date (or first sorted)
    chosen = None
    if open_date:
        m = re.match(r"^(\d{4})-(\d{2})-", open_date)
        if m:
            yr, mo = int(m.group(1)), int(m.group(2))
            # The implied season-start year given open_date:
            implied_start = yr if mo >= 7 else yr - 1
            implied_label = season_label(implied_start)
            if implied_label in seasons_for_post:
                chosen = implied_label
    if chosen is None:
        # Fall back to the latest (highest year) — usually the "live" assignment.
        chosen = sorted(seasons_for_post, key=lambda s: int(s[:4]), reverse=True)[0]

    db_shows.append({
        "id": pid,
        "title": title_clean,
        "open_date": open_date,
        "close_date": close_date,
        "season": chosen,
        "source": "db",
    })

    if open_date:
        m = re.match(r"^(\d{4})-(\d{2})-", open_date)
        s = re.match(r"^(\d{4})-(\d{4})$", chosen)
        if m and s:
            yr = int(m.group(1)); mo = int(m.group(2))
            s_start = int(s.group(1)); s_end = int(s.group(2))
            in_bounds = (
                (yr == s_start and mo >= 7) or
                (yr == s_end and mo <= 8)
            )
            if not in_bounds:
                db_season_mismatch.append((pid, title_clean, open_date, chosen))

print(f"  {len(db_shows)} DB shows, {len(db_no_season)} with no season term")


# ---------------------------------------------------------------------------
# Source B: program PDFs on TLT-SERVER
# ---------------------------------------------------------------------------
print("Walking TLT-SERVER program PDFs...")

pdf_shows = []          # list of dicts
typo_fixes = []         # (filename, original_range, corrected_range)
single_year_filenames = []  # (filename, assumed_season)

# Filename patterns:
#  - "1959-1960 Bell, Book and Candle.pdf"          (4-digit-4-digit range)
#  - "1959-60 Bell.pdf"                              (4-digit-2-digit range)
#  - "1314 Bye Bye Birdie.pdf"                       (compact YYAB, AB=YY+1, season 2013-14)
#  - "1934 THE STREETS OF NEW YORK.pdf"             (single year, space sep)
#  - "1934-THE-STREETS-OF-NEW-YORK.pdf"             (single year, dash sep)
RE_RANGE_4_4 = re.compile(r"^(\d{4})\s*-\s*(\d{4})[\s\-_]+(.+?)\.pdf$", re.I)
RE_RANGE_4_2 = re.compile(r"^(\d{4})\s*-\s*(\d{2})[\s\-_]+(.+?)\.pdf$", re.I)
RE_SINGLE    = re.compile(r"^(\d{4})[\s\-_]+(.+?)\.pdf$", re.I)


def looks_like_compact_season(n):
    """Detect 4-digit values like 1314 where AB = YY + 1.
    1314 -> 13/14 -> season 2013-2014
    1920 -> 19/20 -> season 2019-2020
    Only valid for plausible 21st-century shorthand (13-21 range)."""
    s = f"{n:04d}"
    a = int(s[:2]); b = int(s[2:])
    if (b - a) % 100 != 1:
        return None
    # Constrain to plausible: TLT moved to 4-digit YY shorthand starting 13-14.
    if not (13 <= a <= 25):
        return None
    return 2000 + a

if not os.path.exists(SERVER):
    print(f"  WARNING: {SERVER} not reachable; skipping PDF source.")
else:
    for dirpath, dirnames, filenames in os.walk(SERVER):
        if "#recycle" in dirpath:
            continue
        for fn in filenames:
            if not fn.lower().endswith(".pdf"):
                continue
            full = os.path.join(dirpath, fn)

            m4 = RE_RANGE_4_4.match(fn)
            m2 = RE_RANGE_4_2.match(fn) if not m4 else None
            ms = RE_SINGLE.match(fn) if not (m4 or m2) else None

            if m4:
                y1 = int(m4.group(1))
                y2 = int(m4.group(2))
                show = m4.group(3)
                if y2 - y1 != 1:
                    # Typo — assume y2 should have been y1+1
                    corrected = season_label(y1)
                    typo_fixes.append((fn, f"{y1}-{y2}", corrected))
                    season = corrected
                else:
                    season = season_label(y1)
            elif m2:
                y1 = int(m2.group(1))
                yy = int(m2.group(2))
                show = m2.group(3)
                # Expect yy == (y1+1) % 100
                expected = (y1 + 1) % 100
                if yy != expected:
                    corrected = season_label(y1)
                    typo_fixes.append((fn, f"{y1}-{m2.group(2)}", corrected))
                season = season_label(y1)
            elif ms:
                y1 = int(ms.group(1))
                show = ms.group(2)
                compact = looks_like_compact_season(y1)
                if compact is not None:
                    season = season_label(compact)
                    # Don't record these as "single-year" — they're compact season labels.
                else:
                    season = season_label(y1)
                    single_year_filenames.append((fn, season))
            else:
                # Doesn't fit any pattern -> skip but record? Skip silently.
                continue

            # Normalize show name: replace dashes/underscores with spaces, strip trailing junk
            title = re.sub(r"[-_]+", " ", show).strip()
            title = re.sub(r"\s+", " ", title)
            # Strip trailing " Program" / " program" suffix the archive sometimes adds.
            title = re.sub(r"\s+Program\s*$", "", title, flags=re.I)
            title = clean_title_for_display(title)

            pdf_shows.append({
                "title": title,
                "filename": fn,
                "path": full,
                "season": season,
                "source": "pdf",
            })

print(f"  {len(pdf_shows)} PDFs parsed, {len(typo_fixes)} typo fixes, {len(single_year_filenames)} single-year names")


# ---------------------------------------------------------------------------
# Source C: decade summary posts
# ---------------------------------------------------------------------------
print("Loading decade summary posts...")
cur.execute(
    """
    SELECT post_title, post_content
    FROM wp_posts
    WHERE post_type = 'post' AND post_status = 'publish'
      AND post_title REGEXP '^[0-9]{4}-[0-9]{4}$'
      AND CAST(SUBSTRING(post_title,6,4) AS UNSIGNED)
        - CAST(SUBSTRING(post_title,1,4) AS UNSIGNED) = 10
    """
)
decade_rows = cur.fetchall()
# Dedupe by title (post_content is identical for the duplicates we saw)
seen_decades = set()
decade_posts = []
for title, content in decade_rows:
    if title in seen_decades:
        continue
    seen_decades.add(title)
    decade_posts.append((title, content))

decade_shows = []          # list of dicts (season, title, order_index)
RE_H2 = re.compile(r"<h2[^>]*>(.*?)</h2>", re.I | re.S)
RE_LI = re.compile(r"<li[^>]*>(.*?)</li>", re.I | re.S)


def strip_tags(s):
    return re.sub(r"<[^>]+>", "", s).strip()


def parse_h2_season(h):
    """Accept '1980-81' or '1980-1981'. Return canonical 'YYYY-YYYY' or None."""
    h = strip_tags(h)
    m = re.match(r"^\s*(\d{4})\s*-\s*(\d{2,4})\s*$", h)
    if not m:
        return None
    y1 = int(m.group(1))
    y2s = m.group(2)
    if len(y2s) == 4:
        y2 = int(y2s)
        if y2 - y1 != 1:
            # treat as typo
            return season_label(y1)
        return season_label(y1)
    else:
        return season_label(y1)


for dec_title, content in decade_posts:
    # Split content on <h2> headers, keeping pairs of (header, body-until-next-h2)
    parts = re.split(r"(<h2[^>]*>.*?</h2>)", content, flags=re.I | re.S)
    # parts looks like [pre, h2, body, h2, body, ...]
    for i in range(1, len(parts), 2):
        h2 = parts[i]
        body = parts[i + 1] if i + 1 < len(parts) else ""
        season = parse_h2_season(h2)
        if not season:
            continue
        order = 0
        for li_match in RE_LI.finditer(body):
            li_inner = li_match.group(1)
            # Inner might have <a>...</a> or <p>...</p>
            text = strip_tags(li_inner)
            text = clean_title_for_display(text)
            if not text:
                continue
            decade_shows.append({
                "title": text,
                "season": season,
                "order": order,
                "source": "decade",
            })
            order += 1

print(f"  {len(decade_shows)} show entries pulled from decade posts")

conn.close()


# ---------------------------------------------------------------------------
# Merge & dedupe per-season (case-insensitive, punctuation-insensitive)
# ---------------------------------------------------------------------------
print("Merging & deduping...")

# Build per-season buckets, then within each season dedupe by norm_title, keeping
# the highest-priority source. Track the decade-order for sorting fallback.

per_season = defaultdict(dict)  # season -> { norm_title: best_entry }
# entry contains: title, source, marker, open_date (for db), filename (for pdf), order (for decade)

def consider(season, entry):
    key = norm_title(entry["title"])
    if not key:
        return
    bucket = per_season[season]
    cur_entry = bucket.get(key)
    new_prio = SOURCE_PRIORITY[entry["source"]]
    if cur_entry is None or new_prio > SOURCE_PRIORITY[cur_entry["source"]]:
        # Preserve decade-order if we had one, so the sort-fallback still works.
        if cur_entry is not None and "order" in cur_entry and "order" not in entry:
            entry = {**entry, "order": cur_entry["order"]}
        bucket[key] = entry
    else:
        # The new entry loses on priority — but if it carries an `order` value
        # the kept entry didn't have, inherit it so sort-fallback still works.
        if "order" in entry and "order" not in cur_entry:
            cur_entry["order"] = entry["order"]


# In-season duplicate detection (before priority dedupe collapses them)
intra_season_dupes = defaultdict(list)  # season -> [(title_a_source, title_b_source)]
seen_for_dupe = defaultdict(dict)  # season -> {norm: [(title, source)]}

def record_for_dupe(season, title, source):
    seen_for_dupe[season].setdefault(norm_title(title), []).append((title, source))


# Add DB shows
for s in db_shows:
    consider(s["season"], {
        "title": s["title"], "source": "db",
        "open_date": s["open_date"], "close_date": s["close_date"],
        "id": s["id"],
    })
    record_for_dupe(s["season"], s["title"], "db")

# Add PDF shows
for s in pdf_shows:
    consider(s["season"], {
        "title": s["title"], "source": "pdf",
        "filename": s["filename"], "path": s["path"],
    })
    record_for_dupe(s["season"], s["title"], "pdf")

# Add decade shows
for s in decade_shows:
    consider(s["season"], {
        "title": s["title"], "source": "decade",
        "order": s["order"],
    })
    record_for_dupe(s["season"], s["title"], "decade")

# Populate intra_season_dupes (only seasons where same norm shows up 2+ times w/ different display titles)
for season, m in seen_for_dupe.items():
    for n, entries in m.items():
        if len(entries) >= 2:
            # Multiple display-titles map to same norm — list them
            distinct_titles = list({t for t, _ in entries})
            if len(distinct_titles) >= 2:
                intra_season_dupes[season].append(entries)


# ---------------------------------------------------------------------------
# Cross-season adjacent duplicate detection
# ---------------------------------------------------------------------------
adjacent_dupes = []  # (season_a, season_b, title_a, title_b)
season_list_sorted = sorted(per_season.keys())  # ascending
for i in range(len(season_list_sorted) - 1):
    a = season_list_sorted[i]
    b = season_list_sorted[i + 1]
    # Only treat as "adjacent" if start years differ by exactly 1
    ya = int(a[:4]); yb = int(b[:4])
    if yb - ya != 1:
        continue
    norms_a = set(per_season[a].keys())
    norms_b = set(per_season[b].keys())
    shared = norms_a & norms_b
    for n in shared:
        ta = per_season[a][n]["title"]
        tb = per_season[b][n]["title"]
        adjacent_dupes.append((a, b, ta, tb))


# ---------------------------------------------------------------------------
# Sort within season: by open_date if present, else decade-post order, else title
# ---------------------------------------------------------------------------
def sort_key(entry):
    # Tier 1: DB shows with open_date (real chronology)
    if entry["source"] == "db" and entry.get("open_date"):
        return (0, entry["open_date"], 0, entry["title"].lower())
    # Tier 2: anything with a decade-post order (roughly chronological)
    if entry.get("order") is not None:
        return (1, "", entry["order"], entry["title"].lower())
    # Tier 3: PDFs / DB-no-date — alphabetical fallback
    return (2, "", 0, entry["title"].lower())


# ---------------------------------------------------------------------------
# Counts per source (for audit)
# ---------------------------------------------------------------------------
per_season_counts = {}
# Raw counts (before priority-dedupe), per source
raw_db = defaultdict(set)
raw_pdf = defaultdict(set)
raw_decade = defaultdict(set)
for s in db_shows:
    raw_db[s["season"]].add(norm_title(s["title"]))
for s in pdf_shows:
    raw_pdf[s["season"]].add(norm_title(s["title"]))
for s in decade_shows:
    raw_decade[s["season"]].add(norm_title(s["title"]))

for season in per_season:
    per_season_counts[season] = {
        "db": len(raw_db.get(season, set())),
        "pdf": len(raw_pdf.get(season, set())),
        "decade": len(raw_decade.get(season, set())),
        "total": len(per_season[season]),
    }


# ---------------------------------------------------------------------------
# Build SEASON_LIST.md
# ---------------------------------------------------------------------------
all_seasons = sorted(per_season.keys(),
                     key=lambda s: int(s[:4]),
                     reverse=True)
total_unique = sum(len(b) for b in per_season.values())

lines = []
lines.append("# Tacoma Little Theatre — Complete Show List by Season")
lines.append("")
lines.append(f"**Total seasons listed:** {len(all_seasons)}  ·  **Total unique shows:** {total_unique}")
lines.append("")
lines.append("> Each show shows its strongest source: **[DB]** = full Show post in WordPress · **[program PDF]** = digitized program in the archive · **[decade post]** = mentioned only in a decade-summary blog post.")
lines.append("")

MONTHS = ["", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"]
def fmt_date_range(open_d, close_d):
    def parse(d):
        m = re.match(r"^(\d{4})-(\d{2})-(\d{2})", d or "")
        if not m: return None
        return int(m.group(1)), int(m.group(2)), int(m.group(3))
    a = parse(open_d); b = parse(close_d)
    if not a: return ""
    if b and a[0] == b[0]:
        return f"({MONTHS[a[1]]} {a[2]} – {MONTHS[b[1]]} {b[2]}, {a[0]})"
    if b:
        return f"({MONTHS[a[1]]} {a[2]}, {a[0]} – {MONTHS[b[1]]} {b[2]}, {b[0]})"
    return f"({MONTHS[a[1]]} {a[2]}, {a[0]})"

for season in all_seasons:
    n = season_num(season)
    header = f"## Season {n}: {season}" if n else f"## {season}"
    lines.append(header)
    entries = list(per_season[season].values())
    entries.sort(key=sort_key)
    for e in entries:
        marker = SOURCE_MARKER[e["source"]]
        title = e["title"]
        suffix = ""
        if e["source"] == "db":
            dr = fmt_date_range(e.get("open_date", ""), e.get("close_date", ""))
            if dr:
                suffix = "  " + dr
        lines.append(f"- {title}  *{marker}*{suffix}")
    lines.append("")

with open(OUT_MAIN, "w", encoding="utf-8") as f:
    f.write("\n".join(lines))
print(f"Wrote {OUT_MAIN}")


# ---------------------------------------------------------------------------
# Build audit report
# ---------------------------------------------------------------------------
os.makedirs(os.path.dirname(OUT_AUDIT), exist_ok=True)
a = []
a.append("# Season List Audit")
a.append("")
a.append(f"Generated alongside `SEASON_LIST.md`. Read-only sanity checks.")
a.append("")

a.append("## Typo'd year ranges fixed")
a.append("")
if typo_fixes:
    a.append("Server filenames with `(end - start) != 1`. Treated as typos and merged into the season starting at the first year.")
    a.append("")
    for fn, orig, fixed in sorted(typo_fixes):
        a.append(f"- `{fn}` ({orig}) → merged into `{fixed}`")
else:
    a.append("_None found._")
a.append("")

a.append("## Single-year filenames")
a.append("")
if single_year_filenames:
    a.append("Server filenames with only one year. Assumed `YYYY` → season `YYYY-YYYY+1`.")
    a.append("")
    for fn, season in sorted(single_year_filenames):
        a.append(f"- `{fn}` → assumed `{season}`")
else:
    a.append("_None found._")
a.append("")

a.append("## Cross-season duplicate detection (adjacent seasons only)")
a.append("")
if adjacent_dupes:
    a.append("Same normalized title appears in two consecutive seasons. Could be a legitimate revival or a misfiled program.")
    a.append("")
    for sa, sb, ta, tb in sorted(adjacent_dupes):
        a.append(f"- `{sa}` vs `{sb}`: \"{ta}\" / \"{tb}\"")
else:
    a.append("_None found._")
a.append("")

a.append("## DB shows with no season term")
a.append("")
if db_no_season:
    for pid, title in db_no_season:
        a.append(f"- ID {pid}: {title}")
else:
    a.append("_None — all DB shows have a season term._")
a.append("")

a.append("## DB shows assigned to multiple season terms")
a.append("")
if db_multi_season:
    a.append("Single Show post is tagged with more than one `tlt_season` term. The script picked the term that matches `show_open_date`; flag for cleanup.")
    a.append("")
    for pid, title, seasons in db_multi_season:
        a.append(f"- ID {pid}: {title} — terms: {', '.join(seasons)}")
else:
    a.append("_None — every show is in exactly one season._")
a.append("")

a.append("## DB shows whose `show_open_date` contradicts the assigned season")
a.append("")
if db_season_mismatch:
    a.append("`show_open_date` falls outside the typical Aug(start) – Aug(end+1) window for the assigned season.")
    a.append("")
    for pid, title, od, season in db_season_mismatch:
        a.append(f"- ID {pid}: {title} — open_date `{od}` but season `{season}`")
else:
    a.append("_None found._")
a.append("")

a.append("## Likely duplicate titles within a single season")
a.append("")
if intra_season_dupes:
    a.append("Same case-folded title appeared from multiple sources; the highest-priority source was kept in `SEASON_LIST.md`.")
    a.append("")
    for season in sorted(intra_season_dupes.keys(), key=lambda s: int(s[:4]), reverse=True):
        for entries in intra_season_dupes[season]:
            pretty = "  /  ".join(f"\"{t}\" *{SOURCE_MARKER[src]}*" for t, src in entries)
            a.append(f"- `{season}`: {pretty}")
else:
    a.append("_None found._")
a.append("")

a.append("## Season counts per source")
a.append("")
a.append("| Season | DB | PDF | Decade post | Unique total |")
a.append("|---|---:|---:|---:|---:|")
for season in sorted(per_season.keys(), key=lambda s: int(s[:4]), reverse=True):
    c = per_season_counts[season]
    a.append(f"| {season} | {c['db']} | {c['pdf']} | {c['decade']} | {c['total']} |")
a.append("")

with open(OUT_AUDIT, "w", encoding="utf-8") as f:
    f.write("\n".join(a))
print(f"Wrote {OUT_AUDIT}")

print()
print(f"Summary: {total_unique} unique shows across {len(all_seasons)} seasons.")
print(f"Typo fixes: {len(typo_fixes)}  ·  Single-year filenames: {len(single_year_filenames)}")
print(f"Adjacent duplicates: {len(adjacent_dupes)}  ·  In-season multi-source dupes: {sum(len(v) for v in intra_season_dupes.values())}")
