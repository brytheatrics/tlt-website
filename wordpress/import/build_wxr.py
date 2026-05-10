"""
Build a WordPress eXtended RSS (WXR) import file from triage decisions
plus scraped HTML content.

The output XML can be imported via WP Admin → Tools → Import → WordPress.

For each "Keep" item we generate:
  - tlt_show, tlt_team, post (news), or page
  - Title, slug (clean), content (cleaned HTML), excerpt
  - Featured image URL (set as meta for theme to read)
  - Custom meta: director, dates, run_time, age, content_warning, ticket_url, etc.
  - Original Squarespace URL stored as 'show_legacy_url' / 'team_legacy_url'

The migration is idempotent — re-running won't create duplicates if you
import with "Skip" on the existing-post check (the slugs are stable).
"""
import os, re, json, csv, html, datetime
from urllib.parse import urlparse
from xml.sax.saxutils import escape as xml_escape
from bs4 import BeautifulSoup

ROOT     = os.path.dirname(os.path.abspath(__file__))
PROJECT  = os.path.normpath(os.path.join(ROOT, "..", ".."))
TRIAGE   = os.path.join(PROJECT, "triage")
SCRAPE   = os.path.join(PROJECT, "scrape")
OUT      = os.path.join(ROOT, "tlt-migration.wxr.xml")

# Load all the data
decisions = json.load(open(os.path.join(TRIAGE, "decisions.json")))
candidates = json.load(open(os.path.join(TRIAGE, "candidates.json")))
url_to_cand = {c["url"]: c for c in candidates}

# Read migration_redirect_map.csv to get our nice new slugs
new_url_for = {}
real_name_for = {}
with open(os.path.join(PROJECT, "migration_redirect_map.csv"), "r", encoding="utf-8") as f:
    for r in csv.DictReader(f):
        old = "https://www.tacomalittletheatre.com" + r["old_url"]
        new_url_for[old] = r["new_url"]
        real_name_for[old] = r["real_name_from_note"] or r["title"]

# ----------------- Content extraction helpers -----------------

def slugify(s):
    s = re.sub(r"[^A-Za-z0-9\s-]", "", (s or "").lower())
    s = re.sub(r"\s+", "-", s.strip())
    return re.sub(r"-+", "-", s).strip("-")

def extract_clean_content(html_text):
    """Pull the article body out, stripping Squarespace chrome (header, nav,
    pagination, related posts, footer)."""
    s = BeautifulSoup(html_text, "html.parser")
    # Find article content - Squarespace usually puts it in main / article / .blog-item
    art = s.find("article")
    if not art:
        art = s.find("main") or s.find(class_=re.compile("blog-item|post-body|page-content", re.I))
    if not art:
        return ""
    # Remove pagination, related, share buttons
    for sel in ["nav.pagination","nav.pagination-clear",".pagination",".comments",".share-buttons",".tags","aside","script","style","footer","header","nav"]:
        for el in art.select(sel):
            el.decompose()
    # Strip Squarespace data attributes
    for el in art.find_all(True):
        for attr in list(el.attrs):
            if attr.startswith("data-") or attr in ("onload","onclick","class","id","style") and len(str(el.get(attr,""))) > 80:
                if attr in ("class","id","style") and el.name in ("p","h1","h2","h3","ul","ol","blockquote"):
                    continue
                del el.attrs[attr]
    return str(art)

def extract_show_meta(soup, url):
    """Pull director, dates, run time etc. from the page."""
    meta = {}
    body_text = soup.get_text(" ", strip=True)
    desc = (soup.find("meta", property="og:description") or {}).get("content","") or ""
    full_text = (desc + " " + body_text)[:2000]

    # Director
    m = re.search(r"Directed\s+by\s+([A-Z][A-Za-z\.'\-]+(?:\s+[A-Z][A-Za-z\.'\-]+)+)", full_text)
    if m: meta["show_director"] = m.group(1).strip()

    m = re.search(r"Music(?:al(?:ly)?)?\s+Direct(?:ed|or)\s+(?:by\s+)?([A-Z][A-Za-z\.'\-]+(?:\s+[A-Z][A-Za-z\.'\-]+)+)", full_text)
    if m: meta["show_music_director"] = m.group(1).strip()

    m = re.search(r"Choreograph(?:ed|er|y)\s+(?:by\s+)?([A-Z][A-Za-z\.'\-]+(?:\s+[A-Z][A-Za-z\.'\-]+)+)", full_text)
    if m: meta["show_choreographer"] = m.group(1).strip()

    # Dates: "October 24 - November 9, 2025"
    months = "January|February|March|April|May|June|July|August|September|October|November|December"
    pat = rf"({months})\s+(\d{{1,2}})(?:st|nd|rd|th)?\s*[-–]\s*({months})?\s*(\d{{1,2}})(?:st|nd|rd|th)?,\s*(\d{{4}})"
    m = re.search(pat, full_text)
    if m:
        m1, d1, m2, d2, year = m.groups()
        m2 = m2 or m1
        try:
            start = datetime.datetime.strptime(f"{m1} {d1} {year}", "%B %d %Y")
            end   = datetime.datetime.strptime(f"{m2} {d2} {year}", "%B %d %Y")
            meta["show_open_date"]  = start.strftime("%Y-%m-%d")
            meta["show_close_date"] = end.strftime("%Y-%m-%d")
        except: pass

    # Run time
    m = re.search(r"Run Time:?\s*(.+?)(?=Recommended|$)", full_text[:1500], re.I)
    if m:
        rt = m.group(1).strip(" .,")[:150]
        if rt: meta["show_run_time"] = rt

    # Age recommendation
    m = re.search(r"recommended\s+for\s+Ages?\s+(\d+\+?\s+and\s+up|[\d\+]+|all\s+ages)", full_text, re.I)
    if m: meta["show_age_rec"] = "Recommended for Ages " + m.group(1)

    # Content warning - text after "This production contains" until next sentence ending with .
    m = re.search(r"(This production contains[^.]+(?:\.[^.]+){0,2}\.)", full_text)
    if m: meta["show_content_warning"] = m.group(1)

    # Ticket URL
    for a in soup.find_all("a", href=True):
        h = a["href"]
        text = a.get_text(strip=True).lower()
        if any(x in h for x in ["showtix4u","tixr.com","ticketleap","tickettailor","brownpaper","eventbrite"]) or \
           ("buy" in text and "ticket" in text) or "purchase tickets" in text:
            meta["show_ticket_url"] = h; break

    # Program PDF
    for a in soup.find_all("a", href=True):
        h = a["href"]
        if h.lower().endswith(".pdf") and any(x in h.lower() for x in ["program","-pgm","2024-25","2025-26"]):
            meta["show_program_pdf_url"] = h
            break

    return meta

def get_decision(url):
    return decisions.get(url, {}).get("decision", "")

def get_note(url):
    return decisions.get(url, {}).get("note", "").strip()

# ----------------- Build XML items -----------------

def xml(s):
    return xml_escape(s if s is not None else "", {'"': '&quot;', "'": '&apos;'})

def cdata(s):
    return f"<![CDATA[{(s or '').replace(']]>', ']]]]><![CDATA[>')}]]>"

def make_item(post_id, title, slug, post_type, content, excerpt="",
              status="publish", post_date=None, post_meta=None, terms=None):
    post_date = post_date or datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    pubdate = datetime.datetime.strptime(post_date, "%Y-%m-%d %H:%M:%S").strftime("%a, %d %b %Y %H:%M:%S +0000")
    out = ["<item>"]
    out.append(f"  <title>{xml(title)}</title>")
    out.append(f"  <link>https://example.local/?p={post_id}</link>")
    out.append(f"  <pubDate>{pubdate}</pubDate>")
    out.append(f"  <dc:creator>{cdata('admin')}</dc:creator>")
    out.append(f"  <guid isPermaLink=\"false\">https://example.local/?post_type={post_type}&amp;p={post_id}</guid>")
    out.append(f"  <description></description>")
    out.append(f"  <content:encoded>{cdata(content)}</content:encoded>")
    out.append(f"  <excerpt:encoded>{cdata(excerpt)}</excerpt:encoded>")
    out.append(f"  <wp:post_id>{post_id}</wp:post_id>")
    out.append(f"  <wp:post_date>{cdata(post_date)}</wp:post_date>")
    out.append(f"  <wp:post_date_gmt>{cdata(post_date)}</wp:post_date_gmt>")
    out.append(f"  <wp:post_modified>{cdata(post_date)}</wp:post_modified>")
    out.append(f"  <wp:post_modified_gmt>{cdata(post_date)}</wp:post_modified_gmt>")
    out.append(f"  <wp:comment_status>{cdata('closed')}</wp:comment_status>")
    out.append(f"  <wp:ping_status>{cdata('closed')}</wp:ping_status>")
    out.append(f"  <wp:post_name>{cdata(slug)}</wp:post_name>")
    out.append(f"  <wp:status>{cdata(status)}</wp:status>")
    out.append(f"  <wp:post_parent>0</wp:post_parent>")
    out.append(f"  <wp:menu_order>0</wp:menu_order>")
    out.append(f"  <wp:post_type>{cdata(post_type)}</wp:post_type>")
    out.append(f"  <wp:post_password>{cdata('')}</wp:post_password>")
    out.append(f"  <wp:is_sticky>0</wp:is_sticky>")
    if terms:
        for taxonomy, term_name in terms:
            slug_t = slugify(term_name)
            out.append(f'  <category domain="{xml(taxonomy)}" nicename="{xml(slug_t)}">{cdata(term_name)}</category>')
    if post_meta:
        for k, v in post_meta.items():
            if v in (None, "", False): continue
            out.append("  <wp:postmeta>")
            out.append(f"    <wp:meta_key>{cdata(k)}</wp:meta_key>")
            out.append(f"    <wp:meta_value>{cdata(str(v))}</wp:meta_value>")
            out.append("  </wp:postmeta>")
    out.append("</item>")
    return "\n".join(out)

# Build items
items = []
post_id = 1000

for url, dec in decisions.items():
    if dec.get("decision") != "keep": continue
    cand = url_to_cand.get(url)
    if not cand: continue
    cat = cand["category"]
    new_url = new_url_for.get(url, "")
    title = (real_name_for.get(url) or cand["title"] or url.rsplit("/",1)[-1]).strip()
    if not title: title = url.rsplit("/",1)[-1]

    # Read scraped HTML if present
    local = cand.get("local_html")
    soup = None
    content_html = ""
    if local:
        try:
            html_text = open(os.path.join(PROJECT, local), "r", encoding="utf-8").read()
            soup = BeautifulSoup(html_text, "html.parser")
            content_html = extract_clean_content(html_text)
        except: pass

    excerpt = cand.get("desc","")[:300]
    hero = cand.get("hero","")

    meta = { "_thumbnail_external_url": hero } if hero else {}
    note = get_note(url)
    if note: meta["_migration_note"] = note
    meta["_migration_legacy_url"] = url

    # Pick post type based on category
    if cat in ("modern_show","old show/news","ClubTLT","decade page","special event"):
        post_type = "tlt_show"
        # Show meta
        if soup:
            show_meta = extract_show_meta(soup, url)
            for k,v in show_meta.items(): meta[k] = v
        if dec.get("note","").lower().count("cancelled") or "covid" in dec.get("note","").lower() and "cancel" in dec.get("note","").lower():
            meta["show_cancelled"] = 1
        meta["show_legacy_url"] = url
        # program type
        if cat == "ClubTLT": meta["show_program_type"] = "club-tlt"
        elif cat == "decade page": meta["show_program_type"] = "special"
        elif cat == "special event": meta["show_program_type"] = "special"
        else: meta["show_program_type"] = "mainstage"

        # Slug from new URL
        slug_from_url = new_url.rstrip("/").rsplit("/",1)[-1] if new_url else slugify(title)
        slug = slug_from_url or slugify(title)

        # Determine season term from URL
        terms = []
        m = re.search(r"/blog/(\d{4})(\d{4})/", url)
        if m:
            terms.append( ("tlt_season", f"{m.group(1)}-{m.group(2)}") )

    elif cat == "board profile":
        post_type = "tlt_team"
        meta["team_is_board"] = 1
        meta["team_legacy_url"] = url
        # try role from title pattern "NAME, ROLE"
        m = re.match(r"^([^,]+),\s*(.+)$", cand.get("title",""))
        if m:
            title = m.group(1).strip().title()
            meta["team_role_title"] = m.group(2).strip().title()
        slug = slugify(title)
        terms = [("tlt_team_role", "Board")]

    elif cat == "uncategorized" and "/blog/2015/" in url:
        # Likely a staff page based on note
        if "current" in note.lower() or "director" in note.lower() or "manager" in note.lower():
            post_type = "tlt_team"
            meta["team_is_staff"] = 1
            meta["team_legacy_url"] = url
            slug = slugify(title)
            terms = [("tlt_team_role", "Staff")]
        else:
            post_type = "post"
            slug = slugify(title)
            terms = []

    elif cat == "core":
        post_type = "page"
        slug = url.rstrip("/").rsplit("/",1)[-1] or "home"
        terms = []

    else:
        post_type = "post"
        slug = slugify(title)
        terms = []

    items.append( make_item(post_id, title, slug, post_type, content_html or f"<p>{xml(excerpt)}</p>",
                            excerpt=excerpt, post_meta=meta, terms=terms) )
    post_id += 1

# ----------------- Build full WXR document -----------------

site_url = "https://www.tacomalittletheatre.com"
site_name = "Tacoma Little Theatre"

header = f"""<?xml version="1.0" encoding="UTF-8" ?>
<!-- TLT migration WXR — generated {datetime.datetime.now().isoformat()} -->
<rss version="2.0"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:wfw="http://wellformedweb.org/CommentAPI/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
  <title>{site_name}</title>
  <link>{site_url}</link>
  <description>TLT Migration Import</description>
  <pubDate>{datetime.datetime.now().strftime('%a, %d %b %Y %H:%M:%S +0000')}</pubDate>
  <language>en-US</language>
  <wp:wxr_version>1.2</wp:wxr_version>
  <wp:base_site_url>{site_url}</wp:base_site_url>
  <wp:base_blog_url>{site_url}</wp:base_blog_url>
  <wp:author>
    <wp:author_id>1</wp:author_id>
    <wp:author_login>{cdata('admin')}</wp:author_login>
    <wp:author_email>{cdata('admin@tacomalittletheatre.com')}</wp:author_email>
    <wp:author_display_name>{cdata('TLT')}</wp:author_display_name>
    <wp:author_first_name>{cdata('TLT')}</wp:author_first_name>
    <wp:author_last_name>{cdata('')}</wp:author_last_name>
  </wp:author>
"""

footer = "\n</channel>\n</rss>\n"

with open(OUT, "w", encoding="utf-8") as f:
    f.write(header)
    for it in items: f.write(it + "\n")
    f.write(footer)

# Stats
from collections import Counter
type_counts = Counter()
for it in items:
    m = re.search(r"<wp:post_type><!\[CDATA\[(.+?)\]\]>", it)
    if m: type_counts[m.group(1)] += 1

print(f"Wrote {OUT}")
print(f"Items: {len(items)}")
for t, n in type_counts.most_common(): print(f"  {t}: {n}")
sz = os.path.getsize(OUT)
print(f"Size: {sz/1024:.1f} KB")
