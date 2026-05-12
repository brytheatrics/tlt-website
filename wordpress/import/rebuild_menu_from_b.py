"""Rebuild Primary menu on Computer A to match Computer B's structure."""
import pymysql, time

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

def slug_to_id(slug):
    cur.execute("SELECT ID FROM wp_posts WHERE post_name=%s AND post_status='publish' AND post_type IN ('page','post','tlt_show','tlt_team')", (slug,))
    r = cur.fetchone(); return r[0] if r else None

# Get Primary menu term_taxonomy_id, clear all existing items
cur.execute("""SELECT t.term_id, tt.term_taxonomy_id FROM wp_terms t
               JOIN wp_term_taxonomy tt ON tt.term_id=t.term_id
               WHERE t.slug='primary-menu' AND tt.taxonomy='nav_menu'""")
r = cur.fetchone()
if not r:
    raise SystemExit("No Primary menu found!")
primary_term_id, primary_tt = r
print(f"Primary menu term_id={primary_term_id}, tt_id={primary_tt}")

# Wipe existing items
cur.execute("""DELETE FROM wp_posts WHERE post_type='nav_menu_item' AND ID IN (
               SELECT object_id FROM wp_term_relationships WHERE term_taxonomy_id=%s)""", (primary_tt,))
cur.execute("DELETE FROM wp_term_relationships WHERE term_taxonomy_id=%s", (primary_tt,))
print("Cleared existing Primary menu items")

def add(label, url=None, post_id=None, position=0, parent=0, target='', classes=''):
    now = time.strftime('%Y-%m-%d %H:%M:%S')
    cur.execute("""INSERT INTO wp_posts SET
        post_author=1, post_date=%s, post_date_gmt=%s, post_modified=%s, post_modified_gmt=%s,
        post_content='', post_title=%s, post_excerpt='', post_status='publish',
        post_type='nav_menu_item', post_name=%s, comment_status='closed', ping_status='closed',
        post_parent=0, menu_order=%s, post_password='', to_ping='', pinged='',
        post_content_filtered='', guid=%s""",
        (now,now,now,now, label, label.lower().replace(' ','-').replace('&','and'), position,
         f'http://tlt.local/?p={int(time.time()*1000000)%10000000}'))
    item_id = cur.lastrowid
    if post_id:
        meta = [('_menu_item_type','post_type'),('_menu_item_object','page'),
                ('_menu_item_object_id',str(post_id)),('_menu_item_url','')]
    else:
        meta = [('_menu_item_type','custom'),('_menu_item_object','custom'),
                ('_menu_item_object_id',str(item_id)),('_menu_item_url', url or '#')]
    meta += [('_menu_item_menu_item_parent',str(parent)),('_menu_item_xfn',''),('_menu_item_target',target)]
    if classes:
        meta.append(('_menu_item_classes', f'a:1:{{i:0;s:{len(classes)}:"{classes}";}}'))
    for k,v in meta:
        cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)",(item_id,k,v))
    cur.execute("INSERT INTO wp_term_relationships (object_id, term_taxonomy_id) VALUES (%s,%s)",(item_id, primary_tt))
    return item_id

pos = 0
def p(): global pos; pos+=1; return pos

# HOME
add('Home', url='/home/', position=p())

# SHOWS dropdown
shows = add('Shows', url='/shows/', position=p())
add('This Season',       url='/shows/',                              position=p(), parent=shows)
add('Off the Shelf',     post_id=slug_to_id('off-the-shelf'),        position=p(), parent=shows)
add('Prior Seasons',     post_id=slug_to_id('prior-seasons'),        position=p(), parent=shows)
add('Recorded Programs', post_id=slug_to_id('recorded-programs'),    position=p(), parent=shows)

# VISIT dropdown
visit = add('Visit', post_id=slug_to_id('visit'), position=p())
add('Tickets',                post_id=slug_to_id('tickets'),         position=p(), parent=visit)
add('Ticket Information',     post_id=slug_to_id('ticketinfo'),      position=p(), parent=visit)
add('Season Tickets',         post_id=slug_to_id('season-tickets'),  position=p(), parent=visit)
add('Gift Cards',             url='https://tlt.ludus.com/giftcards.php', position=p(), parent=visit, target='_blank')
add('Seating Chart',          url='/wp-content/uploads/TLT-Seating-Chart.png', position=p(), parent=visit, target='_blank')
add('Plan Your Trip',         post_id=slug_to_id('visit'),           position=p(), parent=visit)
add('Parking & Transportation', post_id=slug_to_id('parking-information'), position=p(), parent=visit)

# EDUCATION dropdown
edu = add('Education', post_id=slug_to_id('education'), position=p())
add('About the Program',        post_id=slug_to_id('education'),      position=p(), parent=edu)
add('Camp & Class Registration', url='https://tlt.ludus.com/index.php?sections=classes', position=p(), parent=edu, target='_blank')
add('Students on Stage',         post_id=slug_to_id('students-on-stage'), position=p(), parent=edu)

# GET INVOLVED dropdown
gi = add('Get Involved', post_id=slug_to_id('get-involved'), position=p())
add('Auditions',     post_id=slug_to_id('auditions'),     position=p(), parent=gi)
add('Volunteer',     post_id=slug_to_id('volunteer'),     position=p(), parent=gi)
add('Job Openings',  post_id=slug_to_id('job-openings'),  position=p(), parent=gi)
add('Email List',    url='https://tlt.ludus.com/subscribe.php', position=p(), parent=gi, target='_blank')
add('Flush Campaign', post_id=slug_to_id('flush'),         position=p(), parent=gi)
add('ClubTLT',        post_id=slug_to_id('clubtlt'),       position=p(), parent=gi)

# ABOUT dropdown
about = add('About', post_id=slug_to_id('about'), position=p())
add('History',         post_id=slug_to_id('history'),         position=p(), parent=about)
add('Board & Staff',   post_id=slug_to_id('board-and-staff'), position=p(), parent=about)
add('Press',           post_id=slug_to_id('press'),           position=p(), parent=about)
add('Donation Request', post_id=slug_to_id('donation-request'), position=p(), parent=about)
add('Contact',         post_id=slug_to_id('contact'),         position=p(), parent=about)

# CTA pills
add('Buy Tickets', url='https://tlt.ludus.com/', position=p(), target='_blank', classes='nav-cta-primary')
add('Donate',      url='https://tlt.ludus.com/donate.php', position=p(), target='_blank', classes='nav-cta-secondary')

# Update term count
cur.execute("UPDATE wp_term_taxonomy SET count=(SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id=%s) WHERE term_taxonomy_id=%s",(primary_tt,primary_tt))
# Flush rewrites
cur.execute("UPDATE wp_options SET option_value='' WHERE option_name='rewrite_rules'")
c.commit()

print(f"\nBuilt {pos} menu items.")
cur.execute("SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id=%s", (primary_tt,))
print(f"Total in Primary menu now: {cur.fetchone()[0]}")
c.close()
