"""Generate SQL UPDATEs to clean up imported titles in WP database.
Output: fix_titles.sql — paste into Local's Adminer / phpMyAdmin to run."""
import csv, os, re

ROOT = os.path.dirname(os.path.abspath(__file__))
PROJECT = os.path.normpath(os.path.join(ROOT, "..", ".."))
SRC = os.path.join(PROJECT, "migration_redirect_map.csv")
OUT = os.path.join(ROOT, "fix_titles.sql")

JUNK_TITLES = {"for","the","and","of","page","pages","season","landing","tag","interview","category","show"}

def clean(name, fallback=""):
    if not name: return fallback
    n = name.strip()
    # Strip trailing " page" / " Page" / " PAGE"
    n = re.sub(r"\s+[Pp][Aa][Gg][Ee]\s*$", "", n).strip()
    # Strip trailing year qualifiers like " 2019 2020" or " (2013)"
    n = re.sub(r"\s+\d{4}(\s+\d{4})?\s*$", "", n).strip()
    n = re.sub(r"\s*\(\d{4}\)\s*$", "", n).strip()
    # Salvage broken: anything with raw note junk
    if "and I believe" in n or "this was done" in n.lower():
        n = re.sub(r"\s*\(.*$", "", n).strip()
        n = re.sub(r"this is the\s*", "", n, flags=re.I).strip()
        if not n or len(n) < 3: n = fallback or "Untitled"
    # If after cleaning we have only a junk word, use the fallback
    if n.lower() in JUNK_TITLES or len(n) < 3:
        n = fallback or n
    # Title-case if it's all-lowercase or mixed and looks like a show name
    if n.islower() or (not n[0].isupper() and len(n.split()) >= 2):
        small_words = {"a","an","the","and","of","or","for","to","at","in","on","by","is"}
        words = n.split()
        out_words = []
        for i, w in enumerate(words):
            wl = w.lower()
            if i > 0 and wl in small_words: out_words.append(wl)
            else: out_words.append(w[0].upper() + w[1:].lower() if w else w)
        n = " ".join(out_words)
    # Fix "Page" remaining (cap variant)
    n = re.sub(r"\s+Page\s*$", "", n).strip()
    return n

updates = []
with open(SRC, "r", encoding="utf-8") as f:
    for r in csv.DictReader(f):
        if r["decision"] != "keep": continue
        rn = r["real_name_from_note"]
        # Use page title (cleaned) as fallback when note is junk/empty
        fallback = (r.get("title") or "").strip()
        # Strip ' — Tacoma Little Theatre' from title
        fallback = re.sub(r"\s*[—\-]+\s*Tacoma Little Theatre\s*$", "", fallback).strip()
        # For category pages, derive from the URL slug e.g. /blog/category/95th+Season -> '95th Season'
        if "/blog/category/" in r["old_url"]:
            cat_slug = r["old_url"].split("/blog/category/",1)[1].rstrip("/")
            fallback = cat_slug.replace("+"," ").replace("-"," ")
        if "/blog/tag/" in r["old_url"] and not rn:
            tag_slug = r["old_url"].split("/blog/tag/",1)[1].rstrip("/")
            fallback = tag_slug.replace("+"," ").replace("-"," ")

        cleaned = clean(rn, fallback=fallback)
        if cleaned and cleaned != rn:
            updates.append((r["old_url"], cleaned))

# Write SQL
with open(OUT, "w", encoding="utf-8") as f:
    f.write("-- TLT title cleanup — run once in Local's Adminer/phpMyAdmin\n")
    f.write("-- Updates each show title by matching against the stored legacy URL meta.\n\n")
    for old_url, new_title in updates:
        legacy_url_full = "https://www.tacomalittletheatre.com" + old_url
        # SQL-escape single quotes
        escaped_title = new_title.replace("'", "''")
        escaped_url = legacy_url_full.replace("'", "''")
        f.write(f"""UPDATE wp_posts SET post_title = '{escaped_title}'
WHERE ID IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = 'show_legacy_url' AND meta_value = '{escaped_url}'
);\n""")

print(f"Wrote {OUT}")
print(f"Will fix {len(updates)} titles.")
print("\nSample updates:")
for old, new in updates[:8]:
    print(f"  {old}  ->  {new!r}")
