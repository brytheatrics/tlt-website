"""
Populate /recorded-programs/ with the YouTube videos from the original
TLT Squarespace page. Sets the video_sections JSON meta that
page-video-archive.php renders.
"""
import json, pymysql

VIDEOS = [
    {
        'heading': 'Main Stage Productions',
        'videos': [
            { 'url': 'https://www.youtube.com/watch?v=6fqf_LnwQrU', 'caption': 'Macbeth at Tacoma Little Theatre' },
            { 'url': 'https://www.youtube.com/watch?v=rRQNigjpskw', 'caption': "A Doll's House — June 2018" },
        ],
    },
    {
        'heading': 'Virtual Staged Readings',
        'intro':   'TLT Page to Screen Events — produced during the 2020 COVID closure.',
        'videos': [
            { 'url': 'https://www.youtube.com/watch?v=bQjM_r6Imjs', 'caption': 'Buenas Noches Mamá — A TLT Page to Screen Event' },
            { 'url': 'https://www.youtube.com/watch?v=OjXutzfd3cU', 'caption': "Washington Irving's Old Christmas" },
            { 'url': 'https://www.youtube.com/watch?v=pDS9SKInxc4', 'caption': 'A Minidoka Christmas' },
            { 'url': 'https://www.youtube.com/watch?v=k2_FxOfNUmU', 'caption': 'Hanukkah Lights in the Big Sky' },
            { 'url': 'https://www.youtube.com/watch?v=-O8DYII1CXg', 'caption': 'The Final Assignment — A TLT Page to Screen Event' },
            { 'url': 'https://www.youtube.com/watch?v=Qzo8HNNb2hE', 'caption': 'The Legend of Robin Hood — A Play by Nathan Makaryk (October 3, 2020)' },
        ],
    },
    {
        'heading': 'Dance Lessons',
        'videos': [
            { 'url': 'https://www.youtube.com/watch?v=5VRlu2ngAEw', 'caption': 'Have fun dancing with TLT and Heather!' },
            { 'url': 'https://www.youtube.com/watch?v=bysM35PmGhg', 'caption': 'Have fun dancing with Melanie and TLT!' },
            { 'url': 'https://www.youtube.com/watch?v=9Hs6eCfBP4o', 'caption': 'Step in Time with Heather Arneson' },
        ],
    },
]


c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()
cur.execute("SELECT ID FROM wp_posts WHERE post_name='recorded-programs' AND post_status='publish' LIMIT 1")
r = cur.fetchone()
if not r:
    raise SystemExit("recorded-programs page not found")
pid = r[0]

# Make sure the page uses the right template
cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key='_wp_page_template'", (pid,))
cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)",
            (pid, '_wp_page_template', 'page-video-archive.php'))

# Set video_sections JSON
cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key='video_sections'", (pid,))
cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)",
            (pid, 'video_sections', json.dumps(VIDEOS, ensure_ascii=False)))

c.commit()
c.close()
print(f"Set {sum(len(s['videos']) for s in VIDEOS)} videos across {len(VIDEOS)} sections on /recorded-programs/ (id={pid})")
