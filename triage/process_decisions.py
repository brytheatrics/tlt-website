"""Process triage decisions into actionable migration outputs:
1. Parse notes to extract real show/page names
2. Build improved URL redirect map using parsed names
3. Generate Chris-review CSV (kept items needing photo curation)
4. Build per-category trash redirect plan
"""
import os, re, json, csv
from collections import Counter, defaultdict

ROOT = os.path.dirname(os.path.abspath(__file__))
PROJECT = os.path.join(ROOT, "..")

decisions = json.load(open(os.path.join(ROOT, "decisions.json")))
candidates = json.load(open(os.path.join(ROOT, "candidates.json")))
url_to_cand = {c["url"]: c for c in candidates}

def slugify(s):
    """Convert 'The Da Vinci Code' -> 'the-da-vinci-code'"""
    s = re.sub(r"[^A-Za-z0-9\s-]", "", s.lower())
    s = re.sub(r"\s+", "-", s.strip())
    s = re.sub(r"-+", "-", s)
    return s.strip("-")

def clean_title(title):
    """Pull a usable name out of an og:title. Strip ' — Tacoma Little Theatre',
    section labels, role suffixes, etc."""
    if not title: return ""
    t = re.sub(r"\s*[—\-]\s*Tacoma Little Theatre\s*$", "", title, flags=re.I)
    t = re.sub(r"\s*[—\-]\s*Blog\s*$", "", t, flags=re.I)
    # 'KAY MEIER, SECRETARY' -> 'KAY MEIER'
    t = re.sub(r",\s*(SECRETARY|TREASURER|PRESIDENT|VICE.?PRESIDENT|CO-?TREASURER|CHAIR|MEMBER|DIRECTOR|MANAGER)\s*$", "", t, flags=re.I)
    return t.strip()

def parse_note_to_name(note):
    """Extract the real show/page name from Blake's notes.
    Examples:
      'Macbeth show page' -> 'Macbeth'
      'A chorus line page 2019 2020 (this was done twice)' -> 'A Chorus Line'
      'Terms of endearment page (this one got cancelled because of covid)' -> 'Terms of Endearment'
      'Current Development director, link says 2015' -> 'Current Development director'
      'Steel Magnolias show page (2013)' -> 'Steel Magnolias' (year=2013)
    Returns dict with 'name' and optional 'year', 'cancelled', 'role'
    """
    n = note.strip()
    if not n: return {}
    # Strip parenthetical comments first
    paren = re.search(r"\(([^)]+)\)", n)
    extras = paren.group(1) if paren else ""
    n_clean = re.sub(r"\([^)]*\)", "", n).strip()
    # Strip trailing markers
    n_clean = re.sub(r"(?i)\b(show|landing|season|page|tag|interview|category)\s*page\b", "", n_clean).strip()
    n_clean = re.sub(r"(?i)\bshow\s+list\b", "", n_clean).strip()
    n_clean = re.sub(r"(?i)\b(landing\s+page|season\s+page|landing|season)\b", "", n_clean).strip()
    n_clean = re.sub(r"(?i)\blink\s+says.*$", "", n_clean).strip()
    n_clean = re.sub(r"(?i)\(\d{4}\)\s*$", "", n_clean).strip()
    n_clean = re.sub(r"\s+\d{4}[-/]\d{4}\s*$", "", n_clean).strip()
    n_clean = re.sub(r"\s+\d{4}\s+\d{4}\s*$", "", n_clean).strip()
    n_clean = re.sub(r"\.+$", "", n_clean).strip(",.").strip()
    n_clean = re.sub(r"^(for|of|the)\s+", "", n_clean, flags=re.I).strip()
    if len(n_clean) < 3: n_clean = ""

    out = {"name": n_clean, "extras": extras}
    if re.search(r"(?i)cancel|covid", n + extras): out["cancelled"] = True
    if re.search(r"(?i)current", n_clean): out["is_staff"] = True
    yr = re.search(r"\b(20\d{2})\b", note)
    if yr: out["year"] = yr.group(1)
    return out

# === 1. Parse all notes ===
parsed_notes = {}
for url, dec in decisions.items():
    note = dec.get("note","").strip()
    if note:
        parsed_notes[url] = parse_note_to_name(note)

# === 2. Build improved redirect map ===
season_pat = re.compile(r"/blog/(\d{8})/(.+)$")
def derive_new_url(url, dec_obj):
    path = url.replace("https://www.tacomalittletheatre.com","")
    parsed = parsed_notes.get(url, {})
    note_name = parsed.get("name","").strip()
    cat = url_to_cand.get(url, {}).get("category", "")
    decision = dec_obj.get("decision","")
    title = url_to_cand.get(url, {}).get("title","")
    title_name = clean_title(title)
    # Prefer note name; fall back to cleaned title
    name = note_name or title_name

    # Trashed items still need a destination redirect (avoid 404)
    if decision == "trash":
        if cat == "audition post": return "/auditions/"
        if cat == "covid notice":  return "/news/"
        if cat == "season tickets": return "/tickets/"
        if cat == "category index": return "/news/"
        if cat == "fundraising":    return "/donate/"
        if cat == "education event": return "/education/"
        if cat == "ClubTLT":        return "/club-tlt/"
        if "audition" in path.lower(): return "/auditions/"
        if "city-line" in path.lower(): return "/about/press/"
        return "/news/"

    # Kept items
    if path in ("/cover","/home","/"): return "/"
    if path == "/blog": return "/news/"

    # Modern show / season post types
    m = season_pat.match(path)
    if m and decision == "keep":
        season = m.group(1)
        season_pretty = f"{season[:4]}-{season[4:]}"
        # Use the parsed real name from note if present
        if name and not parsed.get("is_staff"):
            return f"/shows/{season_pretty}/{slugify(name)}/"
        # Fall back to existing slug, cleaned
        slug = m.group(2)
        return f"/shows/{season_pretty}/{slug}/"

    # Old shows in /blog/2015/ and /blog/2016/ etc.
    if re.match(r"^/blog/(2015|2016|2017|2018|2019|2020|2021|2022|2023|2024|2025)/", path):
        if name and not parsed.get("is_staff"):
            # Extract year from note if it overrides
            yr = parsed.get("year")
            if not yr:
                m2 = re.match(r"^/blog/(\d{4})/", path)
                yr = m2.group(1) if m2 else "unknown"
            # Map publish year -> season label (rough heuristic; flag for review)
            return f"/shows/legacy/{slugify(name)}/"
        # Staff pages -> /team/
        if parsed.get("is_staff"):
            return f"/team/{slugify(name)}/"

    # Tag pages
    if "/blog/tag/" in path:
        tag = path.replace("/blog/tag/","").rstrip("/")
        # Year-range tags are season landing pages
        m3 = re.match(r"^(\d{4})-(\d{4})$", tag)
        if m3:
            return f"/seasons/{tag}/"
        # Specific show tags - use note name if available
        if name:
            return f"/shows/legacy/{slugify(name)}/"
        return f"/news/tag/{slugify(tag)}/"

    if "/blog/category/" in path:
        cat_name = path.replace("/blog/category/","").rstrip("/").replace("+"," ")
        return f"/news/category/{slugify(cat_name)}/"

    if path.startswith("/blog/board/"):
        slug = path.replace("/blog/board/","").rstrip("/")
        # Always prefer a real name from title (board profiles always have one)
        if title_name: return f"/team/{slugify(title_name)}/"
        if name: return f"/team/{slugify(name)}/"
        return f"/team/{slug}/"

    # Generic blog post
    if path.startswith("/blog/"):
        slug = path.replace("/blog/","").rstrip("/").replace("/","-")
        return f"/news/{slug}/"

    return path

improved_redirects = []
for url, dec in decisions.items():
    new = derive_new_url(url, dec)
    improved_redirects.append({
        "old_url": url.replace("https://www.tacomalittletheatre.com",""),
        "new_url": new,
        "decision": dec.get("decision",""),
        "category": url_to_cand.get(url, {}).get("category",""),
        "real_name_from_note": parsed_notes.get(url, {}).get("name",""),
        "title": url_to_cand.get(url, {}).get("title","")[:80],
        "cancelled": parsed_notes.get(url, {}).get("cancelled", False),
    })

# Write CSV
out_csv = os.path.join(PROJECT, "migration_redirect_map.csv")
with open(out_csv, "w", encoding="utf-8", newline="") as f:
    w = csv.DictWriter(f, fieldnames=["decision","category","old_url","new_url","real_name_from_note","title","cancelled"])
    w.writeheader()
    for r in sorted(improved_redirects, key=lambda x: (x["decision"], x["category"], x["old_url"])):
        w.writerow(r)

# === 3. Kept-shows-needing-photos ===
photo_map = json.load(open(os.path.join(PROJECT, "show_to_server_map.json")))
url_to_photos = {p["url"]: p for p in photo_map}

shows_no_photos = []
for r in improved_redirects:
    if r["decision"] != "keep": continue
    if not r["new_url"].startswith("/shows/"): continue
    full_url = "https://www.tacomalittletheatre.com" + r["old_url"]
    pinfo = url_to_photos.get(full_url)
    if not pinfo or pinfo.get("match_score", 0) < 0.6 or pinfo.get("photo_count", 0) == 0:
        shows_no_photos.append({
            "old_url": r["old_url"],
            "new_url": r["new_url"],
            "real_name": r["real_name_from_note"] or r["title"],
            "match_score": pinfo.get("match_score", 0) if pinfo else 0,
            "guessed_folder": pinfo.get("server_folder") if pinfo else None,
        })

with open(os.path.join(PROJECT, "shows_needing_photo_review.csv"), "w", encoding="utf-8", newline="") as f:
    w = csv.DictWriter(f, fieldnames=["old_url","new_url","real_name","match_score","guessed_folder"])
    w.writeheader()
    for r in sorted(shows_no_photos, key=lambda x: x["new_url"]):
        w.writerow(r)

# === Summary ===
from collections import Counter
print(f"Migration redirect map: {out_csv}")
print(f"  Total: {len(improved_redirects)}")
dec_counts = Counter(r["decision"] for r in improved_redirects)
for k,n in dec_counts.most_common(): print(f"    {k}: {n}")
print()
print(f"Notes parsed: {len(parsed_notes)}")
parsed_with_name = sum(1 for v in parsed_notes.values() if v.get("name"))
print(f"  With real name extracted: {parsed_with_name}")
cancelled = sum(1 for v in parsed_notes.values() if v.get("cancelled"))
print(f"  Marked cancelled (COVID): {cancelled}")
staff = sum(1 for v in parsed_notes.values() if v.get("is_staff"))
print(f"  Marked current staff:     {staff}")
print()
print(f"Shows kept but lacking confident photo folder match: {len(shows_no_photos)}")
print(f"  -> shows_needing_photo_review.csv")
