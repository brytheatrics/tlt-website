"""For each core page (and team posts), extract clean content from the scraped HTML
and write it back into wp_posts.post_content. Strips Squarespace chrome,
preserves headings, paragraphs, lists, links, and image references."""
import os, re, pymysql
from bs4 import BeautifulSoup, NavigableString
from urllib.parse import urlparse

ROOT = os.path.dirname(os.path.abspath(__file__))
PROJECT = os.path.normpath(os.path.join(ROOT, "..", ".."))
SCRAPE = os.path.join(PROJECT, "scrape")

def slug_from_url(url):
    p = urlparse(url).path.strip("/")
    return p.replace("/","__") + ".html"

def find_local_html(url):
    fn = slug_from_url(url)
    for sub in ("pages","pages_shows","pages_other_blog"):
        fp = os.path.join(SCRAPE, sub, fn)
        if os.path.exists(fp): return fp
    return None

def extract_clean(html_text, base_url=""):
    s = BeautifulSoup(html_text, "html.parser")

    # Find the article / main / content body
    art = (
        s.find("article")
        or s.find("main")
        or s.find(class_=re.compile("blog-item|page-content|main-content|content-inner", re.I))
    )
    if not art:
        # Standard Squarespace page: pick the LARGEST sqs-layout (skips empty header/footer layouts)
        candidates = s.find_all(class_=lambda c: c and 'sqs-layout' in c)
        # Drop empty, header, and shared-footer layouts
        candidates = [c_ for c_ in candidates if (
            'empty' not in (c_.get('class') or [])
            and c_.get('id') != 'footerBlock'
            and 'footer content' not in (c_.get('data-layout-label') or '').lower()
            and 'header content' not in (c_.get('data-layout-label') or '').lower()
        )]
        if candidates:
            art = max(candidates, key=lambda x: len(x.get_text(strip=True)))
    if not art:
        return ""

    # Remove navigation / pagination / share / related / scripts
    for sel in ['nav', 'aside', 'header', 'footer', 'script', 'style',
                '.pagination', '.pagination-clear', '.tags', '.share-buttons',
                '.comments', '.gallery-design-grid', '.post-meta', '.byline',
                '.entry-actions', '[class*="squarespace-back-to-top"]']:
        for el in art.select(sel): el.decompose()

    # FIRST: copy data-src/data-image to src on images (before stripping data-*)
    for img in art.find_all("img"):
        for attr in ("data-src","data-image"):
            if img.get(attr) and not img.get("src"):
                img["src"] = img[attr]
    # Remove gallery placeholder imgs that have no src after the copy (they were JS-loaded thumbnails)
    for img in art.find_all("img"):
        if not img.get("src"):
            img.decompose()

    # Now drop Squarespace data-* attrs everywhere else
    for el in art.find_all(True):
        for attr in list(el.attrs):
            if attr.startswith('data-'): del el.attrs[attr]
        # Drop class names — but keep useful semantic classes
        if 'class' in el.attrs:
            cls = el['class']
            keep = [c for c in cls if not (c.startswith('sqs-') or c.startswith('sqsrte-') or c.startswith('image-block') or c.startswith('html-block') or c.startswith('row') or c.startswith('col') or c.startswith('span-') or c == 'columns-12')]
            if keep: el['class'] = keep
            else: del el.attrs['class']
        if 'id' in el.attrs and re.match(r'^(article-|block-|yui_|page-|sqs-)', el.attrs.get('id','')):
            del el.attrs['id']
        # Drop style/onclick
        for a in ('style','onclick','onload','itemprop','itemscope','itemtype'):
            if a in el.attrs: del el.attrs[a]

    # Convert image data-src to src
    for img in art.find_all("img"):
        for attr in ("data-src","data-image"):
            if img.get(attr) and not img.get("src"):
                img["src"] = img[attr]
        for a in ("data-src","data-image","data-load","srcset","loading"):
            if a in img.attrs: del img.attrs[a]
        # Drop tiny tracking pixels
        src = img.get("src","")
        if src.endswith(".gif") and "1x1" in src:
            img.decompose()

    # Remove empty divs
    for d in art.find_all('div'):
        if not d.get_text(strip=True) and not d.find_all(['img','iframe','video']):
            d.decompose()

    # Remove the page-title (h1) since WP will render the post_title separately
    h1 = art.find('h1')
    if h1: h1.decompose()

    # Get cleaned HTML
    html = str(art)
    # Tidy up: strip <article ...> wrapper
    html = re.sub(r'^<article[^>]*>', '', html).strip()
    html = re.sub(r'</article>\s*$', '', html).strip()
    # Collapse extra whitespace
    html = re.sub(r'\n\s*\n\s*\n+', '\n\n', html)
    # Remove empty paragraphs
    html = re.sub(r'<p>\s*</p>', '', html)
    html = re.sub(r'<p>\s*&nbsp;\s*</p>', '', html)
    return html.strip()

# Connect
c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

cur.execute("""
SELECT p.ID, p.post_title, p.post_type, pm.meta_value AS legacy_url
FROM wp_posts p
LEFT JOIN wp_postmeta pm ON pm.post_id=p.ID AND pm.meta_key='_migration_legacy_url'
WHERE p.post_status='publish' AND p.post_type IN ('page','tlt_team','tlt_show','post')
""")
rows = cur.fetchall()
print(f"Posts to clean: {len(rows)}")

cleaned = 0
skipped = 0
for pid, title, ptype, url in rows:
    if not url:
        skipped += 1; continue
    fp = find_local_html(url)
    if not fp:
        skipped += 1; continue
    try:
        with open(fp, "r", encoding="utf-8") as f:
            html_text = f.read()
        cleaned_html = extract_clean(html_text, url)
        if cleaned_html and len(cleaned_html) > 30:
            cur.execute("UPDATE wp_posts SET post_content=%s WHERE ID=%s", (cleaned_html, pid))
            cleaned += 1
    except Exception as e:
        print(f"  [err] {pid} {title}: {e}")

c.commit()
print(f"\nCleaned content for {cleaned} posts. Skipped {skipped} (no URL or HTML).")
c.close()
