"""
Convert all "Volunteer" nav menu items from internal page links to external
custom links pointing at the Ludus volunteer signup. Opens in a new tab.
"""
import pymysql

URL = 'https://tlt.ludus.com/volunteer'

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

# Find all Volunteer nav menu items (regardless of which menu they belong to)
cur.execute("""
    SELECT ID FROM wp_posts
    WHERE post_type='nav_menu_item' AND post_status='publish' AND post_title='Volunteer'
""")
ids = [r[0] for r in cur.fetchall()]
print(f"Found {len(ids)} Volunteer menu items: {ids}")

def set_meta(pid, key, value):
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, key))
    if value != '':
        cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s, %s, %s)",
                    (pid, key, value))

for pid in ids:
    set_meta(pid, '_menu_item_type', 'custom')
    set_meta(pid, '_menu_item_object', 'custom')
    set_meta(pid, '_menu_item_object_id', str(pid))  # WP convention: equals the menu item ID for custom links
    set_meta(pid, '_menu_item_url', URL)
    set_meta(pid, '_menu_item_target', '_blank')
    set_meta(pid, '_menu_item_xfn', 'noopener')
    print(f"  [{pid}] -> {URL} (target=_blank)")

c.commit()
c.close()
print("Done.")
