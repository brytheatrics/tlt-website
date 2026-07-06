"""
Restore show reviews lost in the original Squarespace -> WordPress export.

The migrated bodies kept only plain-text publication names (no links). The real
review URLs still live on the public Squarespace site, so we fetch each show's
legacy page and pull the external press links, then store them in the new
show_reviews meta ("Publication | URL", one per line).

Matching is by a press-domain whitelist (avoids grabbing nav/social/own links).

  python import_reviews.py            # DRY RUN — prints what it found, writes nothing
  python import_reviews.py apply      # writes show_reviews for every matched show
  python import_reviews.py SLUG ...   # dry run, only these slugs
"""
import sys, re, time, urllib.request, pymysql

PRESS = [
    'dramainthehood', 'tacomaweekly', 'weeklyvolcano', 'thenewstribune', 'axs.com',
    'komonews', 'broadwayworld', 'olyarts', 'suburbantimes', 'northwestadventure',
    'nwadventures', 'talkinbroadway', 'kingstoncommunitynews', 'soundonstage',
    'gritcitymag', 'southsoundmag', 'thesubtimes', 'encoremonthly', 'encoremedia',
]
EXCLUDE = [
    'tacomalittletheatre', 'squarespace', 'facebook', 'instagram', 'youtube',
    'twitter', 'ludus', 'vimeo', 'google', 'paypal', 'eventbrite', '.pdf', 'mailto:',
    'static1', '/cdn', 'fonts.', 'gstatic', 'cloudflare', 'tiktok', 'linkedin',
    # licensing / ticketing — not reviews
    'dramatists', 'samuelfrench', 'concordtheatricals', 'mtishows', 'playscripts',
    'broadwaylicensing', 'tamswitmark', 'dramaticpublishing', 'patreon', 'gofundme',
]


def fetch( url ):
    req = urllib.request.Request( url, headers={ 'User-Agent': 'Mozilla/5.0' } )
    with urllib.request.urlopen( req, timeout=30 ) as r:
        return r.read().decode( 'utf-8', 'replace' )


def scrape_reviews( html ):
    """Prefer the explicit "Reviews" heading block (grabs ALL reviewers, even
    blogs not in the press whitelist). Fall back to a whole-page press-domain
    scan for pages with a different structure."""
    out, seen = [], set()

    # Anchor on a "Reviews" label in any form (<h2>Reviews</h2>, <strong>REVIEWS</strong>,
    # <p>REVIEWS</p>, …) and take the block right after it — that block is the review
    # list. Scoping to it avoids grabbing unrelated external links elsewhere on the
    # page (Canva infographics, partner news, etc.). No press-domain whitelist needed
    # inside the block, so critic blogs are caught too.
    marker = re.search( r'>\s*reviews?\s*(?:</(?:strong|b|em|p|h[1-6])>|<br)', html, re.I )
    if marker:
        region = html[ marker.end() : marker.end() + 1800 ]
        stop = re.search( r'(starring|the cast|cast of|featuring|tickets|directed by|run ?time|</article)', region, re.I )
        if stop:
            region = region[ : stop.start() ]
        domain_filter = False
    else:
        region = html
        domain_filter = True

    for m in re.finditer( r'<a[^>]+href="(https?://[^"]+)"[^>]*>(.*?)</a>', region, re.S ):
        url = m.group(1)
        text = re.sub( r'<[^>]+>', '', m.group(2) ).strip()
        low = url.lower()
        if any( x in low for x in EXCLUDE ):
            continue
        if domain_filter and not any( d in low for d in PRESS ):
            continue
        key = url.split('#')[0]
        if key in seen:
            continue
        seen.add( key )
        if not text or len( text ) > 60:
            dom = re.sub( r'^www\.', '', re.sub( r'^https?://', '', low ).split('/')[0] )
            text = dom
        out.append( ( text, url ) )
    return out


def main():
    args = sys.argv[1:]
    apply = 'apply' in args
    slugs = [ a for a in args if a != 'apply' ]
    c = pymysql.connect( host='127.0.0.1', port=10005, user='root', password='root', database='local', charset='utf8mb4' )
    cur = c.cursor( pymysql.cursors.DictCursor )
    cur.execute( """SELECT p.ID, p.post_name, p.post_title,
        (SELECT meta_value FROM wp_postmeta WHERE post_id=p.ID AND meta_key='_migration_legacy_url' LIMIT 1) legacy
        FROM wp_posts p WHERE p.post_type='tlt_show' AND p.post_status='publish'
        HAVING legacy LIKE '%%tacomalittletheatre.com%%' ORDER BY p.post_name""" )
    rows = cur.fetchall()
    if slugs:
        rows = [ r for r in rows if r['post_name'] in slugs ]
    print( f"Scanning {len(rows)} shows with legacy URLs...\n" )

    matched = 0
    total_links = 0
    flagged = []
    for r in rows:
        # Some records' legacy URL is a season/tag index page, not a single show
        # page, so it would scoop up other shows' reviews. Skip for manual handling.
        if '/tag/' in ( r['legacy'] or '' ).lower():
            flagged.append( ( r['post_name'], 'tag/season legacy URL' ) )
            continue
        try:
            html = fetch( r['legacy'] )
        except Exception as e:
            print( f"  ! {r['post_name']}: fetch failed ({e})" )
            continue
        revs = scrape_reviews( html )
        if not revs:
            continue
        matched += 1
        total_links += len( revs )
        value = '\n'.join( f"{name} | {url}" for name, url in revs )
        print( f"=== {r['post_name']} ({len(revs)}) ===" )
        for name, url in revs:
            print( f"    {name}  ->  {url}" )
        if apply:
            cur.execute( "SELECT COUNT(*) n FROM wp_postmeta WHERE post_id=%s AND meta_key='show_reviews'", ( r['ID'], ) )
            if cur.fetchone()['n']:
                cur.execute( "UPDATE wp_postmeta SET meta_value=%s WHERE post_id=%s AND meta_key='show_reviews'", ( value, r['ID'] ) )
            else:
                cur.execute( "INSERT INTO wp_postmeta(post_id,meta_key,meta_value) VALUES(%s,'show_reviews',%s)", ( r['ID'], value ) )
        time.sleep( 0.4 )  # be polite to the live site

    if apply:
        c.commit()
        print( f"\nApplied reviews to {matched} shows ({total_links} links)." )
    else:
        print( f"\n[DRY RUN] {matched} shows have review links ({total_links} total). Re-run with 'apply' to write." )
    if flagged:
        print( "\n--- FLAGGED for manual review (NOT written) ---" )
        for name, note in flagged:
            print( f"  {name}: {note}" )
    c.close()


if __name__ == '__main__':
    main()
