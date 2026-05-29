"""
Lock the staff display order on /board-and-staff/ to Blake's preferred
sequence by writing menu_order values. Page template sorts by
menu_order ASC, title ASC, so menu_order=0 records (board) fall back
to alphabetical.

Idempotent.
"""
import pymysql

ORDER = [
    (1138, 'Chris Serface'),       # Managing Artistic Director
    (1139, 'Blake R. York'),       # Technical Director
    (1140, 'Diana George'),        # Development Director
    (1141, 'Nick Fitzgerald'),     # Education Director
    (1143, 'Frank Roberts'),       # Lead Carpenter
    (1145, 'Thomas Robinson'),     # Shop Technician
    (1152, 'Emma DeLoye'),         # Box Office Lead
    (1157, 'Teagan McMonagle'),    # Box Office/Shop
]

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

for i, (pid, name) in enumerate(ORDER, start=1):
    cur.execute("SELECT post_title, post_type, post_status FROM wp_posts WHERE ID=%s", (pid,))
    r = cur.fetchone()
    if not r:
        print(f"  [missing] id={pid} ({name})")
        continue
    title, ptype, status = r
    if ptype != 'tlt_team':
        print(f"  [wrong type] id={pid} ({title}) type={ptype}")
        continue
    cur.execute("UPDATE wp_posts SET menu_order=%s WHERE ID=%s", (i, pid))
    print(f"  [{i}] {title}")

c.commit()
c.close()
print(f"\nSet menu_order on {len(ORDER)} staff records.")
