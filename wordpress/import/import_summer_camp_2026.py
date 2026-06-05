"""
Seed the 2026 Summer Camp performances as tlt_event records (category 'camp',
so they get the distinct orange "Summer Camp" badge on /calendar/).
Source: the /summer-camp-2026/ page. Each performance is one event; they link
back to the camp page.

Idempotent — keyed on a stable slug per performance.
"""
import re, time, pymysql

CAMP_PAGE_URL = '/summer-camp-2026/'
LOCATION      = 'Hilltop Heritage Middle School'

CATEGORY = 'education_performance'

SHOWS = [
    ( 'Oliver! JR.',              [ ( '2026-07-17', '7:00 PM' ), ( '2026-07-19', '2:00 PM' ) ] ),
    ( 'High School Musical JR.',  [ ( '2026-07-18', '11:00 AM' ), ( '2026-07-18', '5:00 PM' ) ] ),
    ( 'Trolls JR.',               [ ( '2026-08-14', '7:00 PM' ), ( '2026-08-15', '2:00 PM' ) ] ),
    ( 'Xanadu JR.',               [ ( '2026-08-15', '11:00 AM' ), ( '2026-08-15', '5:00 PM' ) ] ),
]

def slugify(s):
    return re.sub( r'-+', '-', re.sub( r'[^a-z0-9]+', '-', s.lower() ) ).strip('-')

def main():
    c = pymysql.connect( host='127.0.0.1', port=10005, user='root', password='root', database='local' )
    cur = c.cursor( pymysql.cursors.DictCursor )
    now = time.strftime( '%Y-%m-%d %H:%M:%S' )

    # Clear any prior camp-2026 events first (titles/slugs may have changed,
    # e.g. the TBA show became Trolls JR.) so none are left orphaned.
    cur.execute( "SELECT ID FROM wp_posts WHERE post_type='tlt_event' AND post_name LIKE 'camp-2026-%%'" )
    old = [ r['ID'] for r in cur.fetchall() ]
    for pid in old:
        cur.execute( "DELETE FROM wp_postmeta WHERE post_id=%s", (pid,) )
        cur.execute( "DELETE FROM wp_posts WHERE ID=%s", (pid,) )
    if old:
        print( f"Cleared {len(old)} prior camp-2026 events" )

    def upsert_event( title, slug, date, t ):
        cur.execute( "SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='tlt_event'", (slug,) )
        row = cur.fetchone()
        if row:
            pid = row['ID']
            cur.execute( "UPDATE wp_posts SET post_title=%s, post_status='publish', post_modified=%s WHERE ID=%s",
                         (title, now, pid) )
            action = 'updated'
        else:
            cur.execute( """INSERT INTO wp_posts SET post_author=1, post_date=%s, post_date_gmt=%s,
                post_modified=%s, post_modified_gmt=%s, post_content='', post_title=%s, post_excerpt='',
                post_status='publish', post_type='tlt_event', post_name=%s, comment_status='closed',
                ping_status='closed', post_parent=0, menu_order=0, post_password='', to_ping='', pinged='',
                post_content_filtered='', guid=%s""",
                (now, now, now, now, title, slug, 'http://tlt.local/?post_type=tlt_event&p=' + slug) )
            pid = cur.lastrowid
            action = 'created'
        meta = {
            'event_start_date': date, 'event_end_date': '', 'event_time': t,
            'event_location': LOCATION, 'event_url': CAMP_PAGE_URL, 'event_category': CATEGORY,
        }
        for k, v in meta.items():
            cur.execute( "DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, k) )
            cur.execute( "INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)", (pid, k, v) )
        return pid, action

    n = 0
    for title, perfs in SHOWS:
        for date, t in perfs:
            ampm = slugify( t )
            slug = f"camp-2026-{slugify(title)}-{date}-{ampm}"
            pid, action = upsert_event( title, slug, date, t )
            print( f"{action:>7}  {title:<28} {date} {t:<9} id={pid}" )
            n += 1
    c.commit(); c.close()
    print( f"\nDone. {n} summer-camp performances on the calendar." )

if __name__ == '__main__':
    main()
