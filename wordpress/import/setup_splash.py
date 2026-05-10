"""
1. Register the 5 mountaintop photos as media library attachments
2. Attach them to the Mountaintop show post so the splash cycles through them
3. Set /splash/ as the static front page; current /home/ becomes the secondary homepage
"""
import pymysql, time, os

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()
now = time.strftime('%Y-%m-%d %H:%M:%S')

# Find the Mountaintop show post
cur.execute("SELECT ID FROM wp_posts WHERE post_type='tlt_show' AND post_title LIKE '%MOUNTAINTOP%' LIMIT 1")
mountain_id = cur.fetchone()[0]
print(f"Mountaintop ID: {mountain_id}")

# Register the 5 production photos as attachments parented to Mountaintop
photos = ['003', '007', '010', '016', '018']
attachment_ids = []
for n in photos:
    fname = f'mountaintop-{n}.jpg'
    url = f'http://tlt.local/wp-content/uploads/2026/05/{fname}'
    rel_path = f'2026/05/{fname}'

    # Check if attachment already exists
    cur.execute("SELECT ID FROM wp_posts WHERE post_type='attachment' AND guid=%s", (url,))
    existing = cur.fetchone()
    if existing:
        attachment_ids.append(existing[0])
        # Make sure it's parented to mountaintop
        cur.execute("UPDATE wp_posts SET post_parent=%s WHERE ID=%s",(mountain_id, existing[0]))
        continue

    cur.execute("""INSERT INTO wp_posts SET
        post_author=1, post_date=%s, post_date_gmt=%s, post_modified=%s, post_modified_gmt=%s,
        post_content='', post_title=%s, post_excerpt='',
        post_status='inherit', post_type='attachment', post_mime_type='image/jpeg',
        post_name=%s, comment_status='closed', ping_status='closed',
        post_parent=%s, menu_order=0, post_password='',
        to_ping='', pinged='', post_content_filtered='', guid=%s""",
        (now,now,now,now, f'Mountaintop {n}', f'mountaintop-{n}', mountain_id, url))
    aid = cur.lastrowid
    cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,'_wp_attached_file',%s)",(aid, rel_path))
    meta_blob = f'a:5:{{s:5:"width";i:2400;s:6:"height";i:1600;s:4:"file";s:{len(rel_path)}:"{rel_path}";s:5:"sizes";a:0:{{}}s:10:"image_meta";a:0:{{}}}}'
    cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,'_wp_attachment_metadata',%s)",
        (aid, meta_blob))
    attachment_ids.append(aid)
    print(f"  Created attachment ID={aid} for {fname}")

# Set the first attachment as Mountaintop's featured image (so the hero/cards still work)
cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key='_thumbnail_id'", (mountain_id,))
cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s, '_thumbnail_id', %s)", (mountain_id, attachment_ids[0]))

# Now do the front-page swap.
# Get current "Home" page (the rich front-page) and "Splash" page IDs
cur.execute("SELECT ID FROM wp_posts WHERE post_name='shows-home' AND post_type='page'")
home_id = cur.fetchone()
home_id = home_id[0] if home_id else None
cur.execute("SELECT ID FROM wp_posts WHERE post_name='splash' AND post_type='page'")
splash_id = cur.fetchone()
splash_id = splash_id[0] if splash_id else None

print(f"Home page (current front) ID: {home_id}")
print(f"Splash page ID: {splash_id}")

if home_id and splash_id:
    # Rename "shows-home" slug to "home"
    # First check if /home/ slug already exists for someone else
    cur.execute("SELECT ID FROM wp_posts WHERE post_name='home' AND post_type='page' AND ID<>%s", (home_id,))
    conflict = cur.fetchone()
    if conflict:
        # Rename the conflicting old "/home/" page (the imported empty Squarespace one) to /home-old/
        cur.execute("UPDATE wp_posts SET post_name='home-legacy' WHERE ID=%s", (conflict[0],))
        print(f"  Renamed conflicting old /home/ page (ID={conflict[0]}) to /home-legacy/")
    cur.execute("UPDATE wp_posts SET post_name='home' WHERE ID=%s", (home_id,))
    print(f"  Renamed shows-home -> home")

    # Set Splash as the front page
    cur.execute("UPDATE wp_options SET option_value=%s WHERE option_name='page_on_front'", (str(splash_id),))
    print(f"  Set page_on_front to splash (ID={splash_id})")

    # Flush rewrites
    cur.execute("UPDATE wp_options SET option_value='' WHERE option_name='rewrite_rules'")

c.commit()

# Verify
cur.execute("SELECT option_value FROM wp_options WHERE option_name IN ('show_on_front','page_on_front')")
for r in cur.fetchall(): print(f"  Option: {r}")
c.close()
print("\nDone. Visit tlt.local/ for splash, tlt.local/home/ for the homepage content.")
