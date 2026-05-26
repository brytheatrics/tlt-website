"""
Properly restore the 2019-2020 A Chorus Line + Terms of Endearment records
with their original credits, cast, synopsis, and posters from the scraped
Squarespace pages. These were two distinct productions from the 2021-22
remounts — different posters, and (for Chorus Line) a different cast and
different director.

A Chorus Line 2019-20: opened March 6, 2020 — ran one week before COVID
shut it down. Directed and choreographed by Eric Clausell; musically
directed by Jeff Bell. Different cast from the 2021-22 production.

Terms of Endearment 2019-20: scheduled for April-May 2020, postponed
before opening. Directed by Blake R. York. Cast remained for the 2021-22
remount (where it was directed by Marilyn Bennett per the existing record).

Idempotent.
"""
import re, sys, io, pymysql
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')


def extract_body(scraped_path, title_keyword):
    """Pull the show-page body content (synopsis + cast) from the scrape.

    Looks for content between the second occurrence of the title and either
    Reviews/Tags or the page footer.
    """
    with open(scraped_path, 'r', encoding='utf-8') as f:
        html = f.read()
    html = re.sub(r'<script[^>]*>.*?</script>', '', html, flags=re.S)
    html = re.sub(r'<style[^>]*>.*?</style>', '', html, flags=re.S)

    # The Squarespace template wraps the title in <h1>...<a>TITLE</a>...</h1>.
    # Find the h1 whose visible text matches, then capture everything until
    # the next-prev navigation or the footer mission block.
    pattern = (
        rf'<h1[^>]*>(?:[^<]|<(?!/h1>))*?{re.escape(title_keyword)}(?:[^<]|<(?!/h1>))*?</h1>'
        r'(.*?)'
        r'(?=<nav[^>]*pagination|<div[^>]*class="[^"]*next-prev|Located at 210|<!-- /content)'
    )
    m = re.search(pattern, html, re.S | re.I)
    if not m: return None
    block = m.group(1)
    # Strip any next/prev nav, comment sections, tag lists
    block = re.sub(r'<nav[^>]*class="[^"]*(?:pagination|next-prev)[^"]*"[^>]*>.*?</nav>', '', block, flags=re.S | re.I)
    block = re.sub(r'<div[^>]*class="[^"]*(?:tags|categories)[^"]*"[^>]*>.*?</div>', '', block, flags=re.S | re.I)
    # Strip "← A CHORUS LINE  THE MANCHURIAN CANDIDATE →" inline nav
    block = re.sub(r'<a[^>]*>\s*(?:&larr;|←|&rarr;|→)[^<]*</a>', '', block, flags=re.I)
    return block.strip()


def squarespace_to_local(html):
    """Rewrite known Squarespace-CDN URLs to their rehosted /wp-content/uploads/migrated/ paths.

    Hard-coded for the two posters we care about; other images are best-effort.
    """
    return (html
        .replace(
            'images.squarespace-cdn.com/content/v1/5550122ee4b0005c697257d6/1564000553849-2RNGATMDHGEUQWIFTJE5/chorus+line+poster+rgb.jpg',
            'tlt.local/wp-content/uploads/migrated/chorus-line-poster-rgb-2.jpg'
        )
        .replace(
            'images.squarespace-cdn.com/content/v1/5550122ee4b0005c697257d6/1577407329344-PG3IGLHM7SLZ0NWH2LTP/TLT+Terms+of+Endearment+Poster.jpg',
            'tlt.local/wp-content/uploads/migrated/tlt-terms-of-endearment-poster-2.jpg'
        ))


SHOWS = [
    {
        'slug':              'a-chorus-line-2019',
        'title':             'A Chorus Line',
        'scrape':            'scrape/pages_shows/blog__20192020__chorusline.html',
        'open':              '2020-03-06',
        'close':             '2020-03-15',  # ran ~1 week before COVID shut it
        'director':          'Eric Clausell',
        'music_director':    'Jeff Bell',
        'choreographer':     'Eric Clausell',
        'poster':            '/wp-content/uploads/migrated/chorus-line-poster-rgb-2.jpg',
        'cancelled_note':    '<p><em>Production opened March 6, 2020 and ran approximately one week before being cut short by the COVID-19 pandemic shutdown. TLT later restaged <em>A Chorus Line</em> in the 2021-2022 season with a new cast.</em></p>',
    },
    {
        'slug':              'terms-of-endearment-2019',
        'title':             'Terms of Endearment',
        'scrape':            'scrape/pages_shows/blog__20192020__chorusline-fnlh3.html',
        'open':              '2020-05-01',
        'close':             '2020-05-17',
        'director':          'Blake R. York',
        'music_director':    '',
        'choreographer':     '',
        'poster':            '/wp-content/uploads/migrated/tlt-terms-of-endearment-poster-2.jpg',
        'cancelled_note':    '<p><em>Scheduled for the 2019-2020 season but postponed before opening due to the COVID-19 pandemic. TLT staged <em>Terms of Endearment</em> in the 2021-2022 season with the same cast.</em></p>',
    },
]


def set_meta(cur, pid, key, value):
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, key))
    if value != '':
        cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)",
                    (pid, key, value))


c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

for s in SHOWS:
    cur.execute("SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='tlt_show'", (s['slug'],))
    r = cur.fetchone()
    if not r:
        print(f"  [missing] {s['slug']} — not found")
        continue
    pid = r[0]

    body = extract_body(s['scrape'], s['title'])
    if body:
        body = squarespace_to_local(body)
        # Strip duplicate credit lines so they don't appear twice
        body = re.sub(r'<p[^>]*>\s*(?:Directed[^<]*|Musically Directed[^<]*|Musical Direction[^<]*|Choreographed[^<]*)\s*</p>', '', body, flags=re.I)
        # Build the new post_content: cancelled note + cleaned original body
        post_content = s['cancelled_note'] + '\n\n' + body
    else:
        print(f"  [warn] could not extract body for {s['slug']}, keeping note only")
        post_content = s['cancelled_note']

    cur.execute("UPDATE wp_posts SET post_content=%s WHERE ID=%s", (post_content, pid))

    set_meta(cur, pid, 'show_open_date',  s['open'])
    set_meta(cur, pid, 'show_close_date', s['close'])
    set_meta(cur, pid, 'show_director',   s['director'])
    set_meta(cur, pid, 'show_music_director', s['music_director'])
    set_meta(cur, pid, 'show_choreographer',  s['choreographer'])
    set_meta(cur, pid, '_thumbnail_external_url', s['poster'])
    # Keep cancelled flag set — still didn't complete its run as planned
    set_meta(cur, pid, 'show_cancelled', '1')
    set_meta(cur, pid, 'show_program_type', 'mainstage')

    print(f"  RESTORED {s['slug']:<32} dir={s['director']:<22} poster={s['poster']}")

c.commit()
c.close()
print("\nDone.")
