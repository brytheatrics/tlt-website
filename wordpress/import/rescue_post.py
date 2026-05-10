"""Rescue a trashed-during-triage post: create it from the scraped HTML.
Usage: python rescue_post.py <legacy_url> <new_slug>
Example:
  python rescue_post.py https://www.tacomalittletheatre.com/blog/20252026/springclass spring-classes
"""
import sys, os, time, pymysql
from bs4 import BeautifulSoup
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from clean_page_content import extract_clean

PROJECT = os.path.normpath(os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', '..'))

def find_local(url):
    p = url.replace('https://www.tacomalittletheatre.com','').strip('/')
    fn = p.replace('/','__') + '.html'
    for sub in ('pages','pages_shows','pages_other_blog'):
        fp = os.path.join(PROJECT, 'scrape', sub, fn)
        if os.path.exists(fp): return fp
    return None

def main():
    if len(sys.argv) < 3:
        print("Usage: python rescue_post.py <legacy_url> <new_slug>")
        sys.exit(1)
    url = sys.argv[1].strip()
    slug = sys.argv[2].strip()
    fp = find_local(url)
    if not fp:
        print(f"No local scrape for {url}")
        sys.exit(2)

    with open(fp, 'r', encoding='utf-8') as f:
        html = f.read()
    s = BeautifulSoup(html, 'html.parser')
    title = ''
    og = s.find('meta', property='og:title')
    if og: title = og['content'].replace('— Tacoma Little Theatre','').strip()
    if not title and s.title: title = s.title.string.strip()
    desc = ''
    od = s.find('meta', property='og:description')
    if od: desc = od['content'][:300]
    hero = ''
    oi = s.find('meta', property='og:image')
    if oi: hero = oi['content']

    content = extract_clean(html)

    c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
    cur = c.cursor()
    now = time.strftime('%Y-%m-%d %H:%M:%S')

    cur.execute("SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='post'",(slug,))
    existing = cur.fetchone()
    if existing:
        print(f"Updating existing post ID={existing[0]} (/{slug}/)")
        cur.execute("UPDATE wp_posts SET post_title=%s, post_content=%s, post_excerpt=%s WHERE ID=%s",
                    (title, content, desc, existing[0]))
        pid = existing[0]
    else:
        cur.execute("""INSERT INTO wp_posts SET
            post_author=1, post_date=%s, post_date_gmt=%s, post_modified=%s, post_modified_gmt=%s,
            post_content=%s, post_title=%s, post_excerpt=%s,
            post_status='publish', post_type='post', post_name=%s,
            comment_status='closed', ping_status='closed', post_parent=0, menu_order=0,
            post_password='', to_ping='', pinged='', post_content_filtered='',
            guid=%s""", (now,now,now,now, content, title, desc, slug, f'http://tlt.local/?p={slug}'))
        pid = cur.lastrowid
        if hero:
            cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)",
                        (pid, '_thumbnail_external_url', hero))
        cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)",
                    (pid, '_migration_legacy_url', url))
        print(f"Created post ID={pid} at /{slug}/")
    c.commit()
    c.close()
    print(f"  Title: {title}")
    print(f"  Hero:  {hero[:80]}")

if __name__ == '__main__':
    main()
