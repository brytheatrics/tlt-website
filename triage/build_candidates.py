"""Build a candidates.json file: every page that needs a keep/skip decision,
with metadata pulled from scraped HTML."""
import os, re, json, csv, glob
from bs4 import BeautifulSoup

ROOT = os.path.dirname(os.path.abspath(__file__))
PROJECT = os.path.join(ROOT, "..")
SCRAPE = os.path.join(PROJECT, "scrape")

# Load pre-classified non-show posts
classification = {}
csv_path = os.path.join(PROJECT, "blog_post_classification.csv")
if os.path.exists(csv_path):
    with open(csv_path, "r", encoding="utf-8") as f:
        for r in csv.DictReader(f):
            classification[r["url"]] = r

def slugify(url):
    p = url.replace("https://www.tacomalittletheatre.com","")
    return p.strip("/").replace("/","__") + ".html"

def find_local(url):
    # try each scrape directory
    fn = slugify(url)
    for sub in ("pages","pages_shows","pages_other_blog"):
        fp = os.path.join(SCRAPE, sub, fn)
        if os.path.exists(fp): return os.path.relpath(fp, PROJECT).replace("\\","/")
    return None

def extract_meta(local_rel):
    if not local_rel: return {"title":"", "desc":"", "hero":""}
    full = os.path.join(PROJECT, local_rel)
    try:
        s = BeautifulSoup(open(full,"r",encoding="utf-8").read(),"html.parser")
    except: return {"title":"","desc":"","hero":""}
    og_t = s.find("meta",property="og:title")
    title = (og_t["content"] if og_t else (s.title.string if s.title else "")).strip()
    title = title.replace("— Tacoma Little Theatre","").strip()
    og_d = s.find("meta",property="og:description")
    desc = og_d["content"].strip() if og_d else ""
    og_i = s.find("meta",property="og:image")
    hero = og_i["content"].strip() if og_i else ""
    # body word count
    body = s.find("body")
    word_count = len(body.get_text().split()) if body else 0
    return {"title": title[:140], "desc": desc[:300], "hero": hero, "word_count": word_count}

with open(os.path.join(SCRAPE,"all_urls.txt"),"r",encoding="utf-8") as f:
    urls = [u.strip() for u in f if u.strip()]
season_pat = re.compile(r"/blog/\d{8}/")

def category(url):
    path = url.replace("https://www.tacomalittletheatre.com","")
    if path in ("/cover","/home","/"): return "core"
    if "/blog/" not in path or path == "/blog": return "core"
    if season_pat.search(path): return "modern_show"
    # otherwise use pre-classification or fallback
    c = classification.get(path, {}).get("category", "uncategorized")
    return c

candidates = []
for url in urls:
    path = url.replace("https://www.tacomalittletheatre.com","")
    cat = category(url)
    local = find_local(url)
    meta = extract_meta(local)
    suggestion = classification.get(path, {}).get("suggested_action", "")
    candidates.append({
        "url": url,
        "path": path,
        "category": cat,
        "local_html": local,
        "title": meta["title"],
        "desc": meta["desc"],
        "hero": meta["hero"],
        "word_count": meta.get("word_count",0),
        "suggested_action": suggestion,
    })

out = os.path.join(ROOT, "candidates.json")
with open(out,"w",encoding="utf-8") as f:
    json.dump(candidates, f, indent=1)

from collections import Counter
print(f"Wrote {len(candidates)} candidates to {out}")
print()
print("By category:")
for c, n in Counter(x["category"] for x in candidates).most_common():
    print(f"  {n:4} {c}")
