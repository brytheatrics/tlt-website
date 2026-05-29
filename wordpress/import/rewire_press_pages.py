"""
Create /press/tlt-wins-national-award/ as a child page with the full release
content, and convert /press/ into a listing using page-press.php.
"""
import pymysql

# Press release: TLT Wins National Award (AACT Diamond Award) — May 28, 2021
RELEASE_CONTENT = """<p>Tacoma Little Theatre of Tacoma, Washington, is being honored with the Diamond Crown Organizational Award by the American Association of Community Theatre (AACT). The 2021 AACT National Award presentations will be pre-recorded and streamed during Virtual AACTFest 2021 National Theatre Festival, Saturday, June 19, 2021.</p>

<p>The AACT Diamond Crown Organizational Award recognizes longevity and vitality of AACT member theatres that have expanded programming and/or facilities in the past ten years and have the administrative leadership to remain vital to their communities for the next ten years. Recipients must have been in existence for at least seventy-five years and have a sustained record of producing high-quality theatre.</p>

<p>Tacoma Little Theatre (TLT) was founded in 1918 as the Tacoma Little Theatre and Drama League, and at 103 years is among the oldest community theatres currently operating in the United States. TLT's vision is to offer a destination for the diverse community of Tacoma and Puget Sound by offering a welcoming environment for the artistic exploration of all theatrical disciplines.</p>

<p>AACT provides networking, resources, and support for America's theatres. AACT represents the interests of more than 7,000 theatres across the United States and its territories, as well as theatre companies with the U.S. Military Services overseas.</p>"""

PRESS_LISTING_CONTENT = ''  # listing is rendered by template

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()


def set_meta(pid, key, value):
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, key))
    if value != '':
        cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s, %s, %s)",
                    (pid, key, value))


# --- /press/ listing ---
cur.execute("SELECT ID FROM wp_posts WHERE post_name='press' AND post_status='publish' AND post_type='page' LIMIT 1")
press_id = cur.fetchone()[0]
cur.execute("UPDATE wp_posts SET post_content=%s WHERE ID=%s", (PRESS_LISTING_CONTENT, press_id))
set_meta(press_id, '_wp_page_template', 'page-press.php')
# Also clean up that bogus _migration_legacy_url + the wrong template assignment
cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key='_migration_legacy_url'", (press_id,))
print(f"Listing /press/ (id={press_id}): page-press.php, cleared content")

# --- /press/tlt-wins-national-award/ ---
SLUG = 'tlt-wins-national-award'
cur.execute("SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='page' LIMIT 1", (SLUG,))
r = cur.fetchone()
if r:
    rel_id = r[0]
    cur.execute(
        "UPDATE wp_posts SET post_title=%s, post_content=%s, post_parent=%s, post_status='publish' WHERE ID=%s",
        ('TLT Wins National Award', RELEASE_CONTENT, press_id, rel_id)
    )
    print(f"Updated existing /press/{SLUG}/ (id={rel_id})")
else:
    cur.execute("""
        INSERT INTO wp_posts (
            post_author, post_date, post_date_gmt, post_content, post_title,
            post_excerpt, post_status, comment_status, ping_status, post_password,
            post_name, to_ping, pinged, post_modified, post_modified_gmt,
            post_content_filtered, post_parent, guid, menu_order, post_type, post_mime_type, comment_count
        ) VALUES (
            1, '2021-05-28 12:00:00', '2021-05-28 12:00:00', %s, %s,
            '', 'publish', 'closed', 'closed', '',
            %s, '', '', NOW(), UTC_TIMESTAMP(),
            '', %s, '', 0, 'page', '', 0
        )
    """, (RELEASE_CONTENT, 'TLT Wins National Award', SLUG, press_id))
    rel_id = cur.lastrowid
    cur.execute("UPDATE wp_posts SET guid=%s WHERE ID=%s",
                (f'http://tlt.local/?page_id={rel_id}', rel_id))
    print(f"Created /press/{SLUG}/ (id={rel_id})")

set_meta(rel_id, '_wp_page_template', 'page-press-post.php')
set_meta(rel_id, 'press_date', 'May 28, 2021')
set_meta(rel_id, 'press_thumb', '/wp-content/uploads/migrated/tlt-american-association-of-community-thaetre-diamond-award-2.jpg')

c.commit()
c.close()
