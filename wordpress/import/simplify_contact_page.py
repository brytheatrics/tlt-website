"""
Strip Transportation & Parking info from /contact/ — that lives on /visit/
now. Rename title from "Contact & Transportation" to "Contact" and replace
post_content with a brief intro.
"""
import pymysql

NEW_CONTENT = """<p>Have a question for us? Fill out the form below and we'll get back to you as soon as we can.</p>
<p>Planning a visit? See our <a href="/visit/">Visit page</a> for directions, parking, accessibility, and recommendations for places to eat and drink nearby.</p>"""

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

cur.execute("SELECT ID, post_title FROM wp_posts WHERE post_name='contact' AND post_status='publish' AND post_type='page' LIMIT 1")
r = cur.fetchone()
if not r:
    raise SystemExit("/contact/ page not found")
pid, old_title = r
print(f"page id={pid}, current title={old_title!r}")

cur.execute("UPDATE wp_posts SET post_title=%s, post_content=%s WHERE ID=%s",
            ('Contact', NEW_CONTENT, pid))
c.commit()
c.close()
print("title -> 'Contact', content simplified")
