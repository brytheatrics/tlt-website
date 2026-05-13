"""
For any tlt_show record that doesn't have a show_program_pdf_url meta but
DOES have a /wp-content/uploads/programs/*.pdf link in its post_content,
extract that URL and store it as the meta. This lets single-tlt_show.php
render its dedicated "View Program" button instead of relying on inline links.

Idempotent.
"""
import re, pymysql

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

# All published shows missing show_program_pdf_url
cur.execute("""SELECT p.ID, p.post_title, p.post_content
               FROM wp_posts p
               WHERE p.post_type='tlt_show' AND p.post_status='publish'
                 AND NOT EXISTS (
                   SELECT 1 FROM wp_postmeta pm
                   WHERE pm.post_id=p.ID AND pm.meta_key='show_program_pdf_url'
                     AND pm.meta_value <> ''
                 )""")
shows_missing_meta = cur.fetchall()
print(f"Shows missing show_program_pdf_url meta: {len(shows_missing_meta)}")

added = 0
for pid, title, body in shows_missing_meta:
    if not body: continue
    # First-match wins — find a /wp-content/uploads/programs/*.pdf link in the body
    m = re.search(r'(/wp-content/uploads/programs/[^\s"\'<>]+\.pdf)', body)
    if m:
        url = m.group(1)
        cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)",
                    (pid, 'show_program_pdf_url', url))
        added += 1
        print(f"  {pid:>4}: {title[:40]:<42} -> {url}")

c.commit()
c.close()
print(f"\nDone. Added show_program_pdf_url for {added} shows.")
