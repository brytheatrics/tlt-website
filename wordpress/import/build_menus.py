"""Build WP nav menus with hierarchy (dropdowns) matching TLT's existing nav."""
import pymysql, time

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

def slug_to_id(slug):
    cur.execute("SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='page' AND post_status='publish'", (slug,))
    r = cur.fetchone()
    return r[0] if r else None

def get_menu_terms(name, slug):
    cur.execute("""SELECT t.term_id, tt.term_taxonomy_id FROM wp_terms t
                   JOIN wp_term_taxonomy tt ON tt.term_id=t.term_id
                   WHERE t.slug=%s AND tt.taxonomy='nav_menu'""", (slug,))
    r = cur.fetchone()
    if r:
        cur.execute("""DELETE FROM wp_posts WHERE post_type='nav_menu_item' AND ID IN (
                       SELECT object_id FROM wp_term_relationships WHERE term_taxonomy_id=%s
                       )""", (r[1],))
        cur.execute("DELETE FROM wp_term_relationships WHERE term_taxonomy_id=%s", (r[1],))
        return r
    cur.execute("INSERT INTO wp_terms (name, slug) VALUES (%s, %s)", (name, slug))
    tid = cur.lastrowid
    cur.execute("INSERT INTO wp_term_taxonomy (term_id, taxonomy, description, parent, count) VALUES (%s, 'nav_menu', '', 0, 0)", (tid,))
    return (tid, cur.lastrowid)

def add_menu_item(menu_tt_id, label, url=None, post_id=None, position=0, parent=0, target=''):
    now = time.strftime('%Y-%m-%d %H:%M:%S')
    cur.execute("""INSERT INTO wp_posts SET
        post_author=1, post_date=%s, post_date_gmt=%s, post_modified=%s, post_modified_gmt=%s,
        post_content='', post_title=%s, post_excerpt='', post_status='publish', post_type='nav_menu_item',
        post_name=%s, comment_status='closed', ping_status='closed', post_parent=0, menu_order=%s,
        post_password='', to_ping='', pinged='', post_content_filtered='', guid=%s
    """, (now, now, now, now, label,
          label.lower().replace(' ', '-').replace('&','and'), position,
          f'http://tlt.local/?p={int(time.time()*1000000)%10000000}'))
    item_id = cur.lastrowid
    if post_id:
        meta = [('_menu_item_type', 'post_type'),
                ('_menu_item_object', 'page'),
                ('_menu_item_object_id', str(post_id)),
                ('_menu_item_url', '')]
    else:
        meta = [('_menu_item_type', 'custom'),
                ('_menu_item_object', 'custom'),
                ('_menu_item_object_id', str(item_id)),
                ('_menu_item_url', url or '#')]
    meta += [('_menu_item_menu_item_parent', str(parent)),
             ('_menu_item_xfn', ''),
             ('_menu_item_target', target)]
    for k, v in meta:
        cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)", (item_id, k, v))
    cur.execute("INSERT INTO wp_term_relationships (object_id, term_taxonomy_id) VALUES (%s, %s)", (item_id, menu_tt_id))
    return item_id

# === Build Primary menu with dropdowns ===
primary_term_id, primary_tt = get_menu_terms('Primary', 'primary-menu')

position = 0
def pos():
    global position; position += 1; return position

# HOME
add_menu_item(primary_tt, 'Home', url='/', position=pos())

# ABOUT (dropdown)
about_id = add_menu_item(primary_tt, 'About', post_id=slug_to_id('about'), position=pos())
add_menu_item(primary_tt, 'Board of Directors & Staff', post_id=slug_to_id('board-and-staff'), position=pos(), parent=about_id)
add_menu_item(primary_tt, 'History', post_id=slug_to_id('history'), position=pos(), parent=about_id)
add_menu_item(primary_tt, 'Contact & Transportation', post_id=slug_to_id('contact'), position=pos(), parent=about_id)
add_menu_item(primary_tt, 'Donate', url='https://tlt.ludus.com/donate.php', position=pos(), parent=about_id, target='_blank')
add_menu_item(primary_tt, 'Donation Request', post_id=slug_to_id('donation-request'), position=pos(), parent=about_id)
add_menu_item(primary_tt, 'Press', post_id=slug_to_id('press'), position=pos(), parent=about_id)

# TICKETS (dropdown)
tickets_id = add_menu_item(primary_tt, 'Tickets', post_id=slug_to_id('tickets'), position=pos())
add_menu_item(primary_tt, 'Ticket Information', post_id=slug_to_id('ticketinfo'), position=pos(), parent=tickets_id)
add_menu_item(primary_tt, 'Single Tickets', url='https://tlt.ludus.com', position=pos(), parent=tickets_id, target='_blank')
add_menu_item(primary_tt, 'Season Tickets', post_id=slug_to_id('season-tickets'), position=pos(), parent=tickets_id)
add_menu_item(primary_tt, 'Gift Card Purchase', url='https://tlt.ludus.com/giftcards.php', position=pos(), parent=tickets_id, target='_blank')
add_menu_item(primary_tt, 'Seating Chart', url='/wp-content/uploads/TLT-Seating-Chart.png', position=pos(), parent=tickets_id, target='_blank')
add_menu_item(primary_tt, 'Parking Information', post_id=slug_to_id('parking-information'), position=pos(), parent=tickets_id)

# CURRENT SEASON
add_menu_item(primary_tt, 'Current Season', url='/shows/', position=pos())

# OFF THE SHELF
add_menu_item(primary_tt, 'Off the Shelf', post_id=slug_to_id('off-the-shelf'), position=pos())

# FLUSH CAMPAIGN
add_menu_item(primary_tt, 'Flush Campaign', post_id=slug_to_id('flush'), position=pos())

# PRIOR SEASONS — single button (no dropdown), goes to /prior-seasons/
add_menu_item(primary_tt, 'Prior Seasons', post_id=slug_to_id('prior-seasons'), position=pos())

# EDUCATION (dropdown)
edu_id = add_menu_item(primary_tt, 'Education', post_id=slug_to_id('education'), position=pos())
add_menu_item(primary_tt, 'About the Program', post_id=slug_to_id('education'), position=pos(), parent=edu_id)
add_menu_item(primary_tt, 'ClubTLT', post_id=slug_to_id('clubtlt'), position=pos(), parent=edu_id)
add_menu_item(primary_tt, 'Students on Stage', post_id=slug_to_id('students-on-stage'), position=pos(), parent=edu_id)
add_menu_item(primary_tt, 'Camp & Class Registration', url='https://tlt.ludus.com/index.php?sections=classes', position=pos(), parent=edu_id, target='_blank')

# GET INVOLVED (dropdown)
gi_id = add_menu_item(primary_tt, 'Get Involved', post_id=slug_to_id('get-involved'), position=pos())
add_menu_item(primary_tt, 'Auditions', post_id=slug_to_id('auditions'), position=pos(), parent=gi_id)
add_menu_item(primary_tt, 'Volunteer', post_id=slug_to_id('volunteer'), position=pos(), parent=gi_id)
add_menu_item(primary_tt, 'Job Openings', post_id=slug_to_id('job-openings'), position=pos(), parent=gi_id)
add_menu_item(primary_tt, 'Join Our Email List', url='https://tlt.ludus.com/subscribe.php', position=pos(), parent=gi_id, target='_blank')

# === Top Bar ===
topbar_term_id, topbar_tt = get_menu_terms('Top Bar', 'topbar-menu')
add_menu_item(topbar_tt, 'Donate', url='https://tlt.ludus.com/donate.php', position=1, target='_blank')
add_menu_item(topbar_tt, 'Volunteer', post_id=slug_to_id('volunteer'), position=2)

# === Footer menus (unchanged) ===
fv_term_id, fv_tt = get_menu_terms('Footer Visit', 'footer-visit')
for i, (lbl, pid, url, tgt) in enumerate([
    ('Tickets', slug_to_id('tickets'), None, ''),
    ('Parking', slug_to_id('parking-information'), None, ''),
    ('Contact', slug_to_id('contact'), None, ''),
], 1):
    add_menu_item(fv_tt, lbl, url=url, post_id=pid, position=i, target=tgt)

fa_term_id, fa_tt = get_menu_terms('Footer About', 'footer-about')
for i, (lbl, pid, url, tgt) in enumerate([
    ('History', slug_to_id('history'), None, ''),
    ('Board & Staff', slug_to_id('board-and-staff'), None, ''),
    ('Press', slug_to_id('press'), None, ''),
    ('Job Openings', slug_to_id('job-openings'), None, ''),
], 1):
    add_menu_item(fa_tt, lbl, url=url, post_id=pid, position=i, target=tgt)

fg_term_id, fg_tt = get_menu_terms('Footer Get Involved', 'footer-get-involved')
for i, (lbl, pid, url, tgt) in enumerate([
    ('Donate', None, 'https://tlt.ludus.com/donate.php', '_blank'),
    ('Volunteer', slug_to_id('volunteer'), None, ''),
    ('Auditions', slug_to_id('auditions'), None, ''),
    ('ClubTLT', slug_to_id('clubtlt'), None, ''),
], 1):
    add_menu_item(fg_tt, lbl, url=url, post_id=pid, position=i, target=tgt)

# Update term counts
cur.execute("""UPDATE wp_term_taxonomy tt SET count=(
                 SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id=tt.term_taxonomy_id
               ) WHERE tt.taxonomy='nav_menu'""")

# Set theme_mods for menu locations
locations = {
    'primary': primary_term_id,
    'topbar': topbar_term_id,
    'footer_visit': fv_term_id,
    'footer_about': fa_term_id,
    'footer_get_involved': fg_term_id,
}
inner = ''.join(f's:{len(k)}:"{k}";i:{v};' for k, v in locations.items())
ser = f'a:1:{{s:18:"nav_menu_locations";a:{len(locations)}:{{{inner}}}}}'
cur.execute("SELECT option_id FROM wp_options WHERE option_name='theme_mods_tlt'")
if cur.fetchone():
    cur.execute("UPDATE wp_options SET option_value=%s WHERE option_name='theme_mods_tlt'", (ser,))
else:
    cur.execute("INSERT INTO wp_options (option_name, option_value, autoload) VALUES (%s, %s, 'yes')", ('theme_mods_tlt', ser))

cur.execute("UPDATE wp_options SET option_value='' WHERE option_name='rewrite_rules'")
c.commit()

# Print structure
cur.execute("""SELECT p.ID, p.post_title, pm.meta_value AS parent
               FROM wp_posts p
               JOIN wp_term_relationships tr ON tr.object_id=p.ID
               JOIN wp_postmeta pm ON pm.post_id=p.ID AND pm.meta_key='_menu_item_menu_item_parent'
               WHERE p.post_type='nav_menu_item' AND tr.term_taxonomy_id=%s
               ORDER BY p.menu_order""", (primary_tt,))
print("\nPrimary menu structure:")
items = list(cur.fetchall())
top_level = {pid: title for pid, title, parent in items if parent == '0'}
for pid, title, parent in items:
    indent = '  ' if parent != '0' else ''
    print(f"  {indent}{title}")
c.close()
