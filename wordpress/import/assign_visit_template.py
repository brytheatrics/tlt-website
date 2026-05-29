"""Assign the Visit template to /visit/."""
import pymysql
c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()
cur.execute("SELECT ID FROM wp_posts WHERE post_name='visit' AND post_status='publish' AND post_type='page' LIMIT 1")
r = cur.fetchone()
if not r:
    raise SystemExit("/visit/ page not found")
pid = r[0]
cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key='_wp_page_template'", (pid,))
cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s, %s, %s)",
            (pid, '_wp_page_template', 'page-visit.php'))
c.commit()
c.close()
print(f"Assigned page-visit.php to /visit/ (id={pid})")
