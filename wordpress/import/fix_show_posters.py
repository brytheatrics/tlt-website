"""
Replace production-photo thumbnails with proper posters on shows that have
a 'tltSHORTSLUG{N}.jpg'-style thumbnail (i.e. migrated production photos
where N indicates the photo's position in a sequence).

For each affected show:
  1. Look for the canonical poster in Marketing/Prior Seasons/<season> Marketing/...
  2. Copy it to /wp-content/uploads/posters/<slug>.jpg
  3. Update _thumbnail_external_url to the new path

Limited to specific shows Blake spotted:
- All 2015-2016 shows
- Dracula (2016-17)

Easy to extend to more shows if needed.
"""
import os, shutil, re, pymysql, sys, io
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

POSTERS_DEST = "C:/Users/blake/Local Sites/tlt/app/public/wp-content/uploads/posters"
os.makedirs(POSTERS_DEST, exist_ok=True)

# Explicit mapping from show slug -> server poster path
POSTER_MAP = {
    # 2015-2016 season (97th)
    'a-christmas-story-2':  '//TLT-SERVER/Marketing/Prior Seasons/1516 Marketing/Posters/97th season final poster files/1516posterjpg/1516christmas.jpg',
    'last-night-of-ballyhoo': '//TLT-SERVER/Marketing/Prior Seasons/1516 Marketing/Posters/97th season final poster files/1516posterjpg/1516ballyhoo.jpg',
    'boeing-boeing':         '//TLT-SERVER/Marketing/Prior Seasons/1516 Marketing/Posters/97th season final poster files/1516posterjpg/1516boeing.jpg',
    'rabbit-hole':           '//TLT-SERVER/Marketing/Prior Seasons/1516 Marketing/Posters/97th season final poster files/1516posterjpg/1516rabbithole.jpg',
    'second-samuel':         '//TLT-SERVER/Marketing/Prior Seasons/1516 Marketing/Posters/97th season final poster files/1516posterjpg/1516secondsamuel.jpg',
    'smokey-joes-cafe':      '//TLT-SERVER/Marketing/Prior Seasons/1516 Marketing/Posters/97th season final poster files/1516posterjpg/1516smokey.jpg',
    'vanya-and-sonia-and-masha-and-spike': '//TLT-SERVER/Marketing/Prior Seasons/1516 Marketing/Posters/97th season final poster files/1516posterjpg/1516vanya.jpg',
    # 2016-2017 season (98th/99th depending on counting)
    'dracula':               '//TLT-SERVER/Marketing/Prior Seasons/1617 Marketing/1617 Posters/TLT Posters/1617 Poster JPG PDF/Dracula Poster.jpg',
    'exit-laughing':         '//TLT-SERVER/Marketing/Prior Seasons/1617 Marketing/1617 Posters/TLT Posters/1617 Poster JPG PDF/Exit Laughing Poster 6.jpg',
    'gypsy':                 '//TLT-SERVER/Marketing/Prior Seasons/1617 Marketing/1617 Posters/TLT Posters/1617 Poster JPG PDF/Gypsy Poster.jpg',
    'miracle-on-34th-street': '//TLT-SERVER/Marketing/Prior Seasons/1617 Marketing/1617 Posters/TLT Posters/1617 Poster JPG PDF/Miracle on 34th Street.jpg',
    'mice-and-men':          '//TLT-SERVER/Marketing/Prior Seasons/1617 Marketing/1617 Posters/TLT Posters/1617 Poster JPG PDF/Of Mice and Men.jpg',
    'man-who-shot-liberty-valance': '//TLT-SERVER/Marketing/Prior Seasons/1617 Marketing/1617 Posters/TLT Posters/1617 Poster JPG PDF/The Man Who Shot Liberty Valance.jpg',
    'underpants':            '//TLT-SERVER/Marketing/Prior Seasons/1617 Marketing/1617 Posters/TLT Posters/1617 Poster JPG PDF/The Underpants Poster.jpg',
}


def slugify(s):
    s = s.lower()
    s = re.sub(r'[^a-z0-9]+', '-', s).strip('-')
    return s


c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

fixed = 0
missing_source = 0
for slug, src in POSTER_MAP.items():
    cur.execute("SELECT ID, post_title FROM wp_posts WHERE post_name=%s AND post_type='tlt_show'", (slug,))
    r = cur.fetchone()
    if not r:
        print(f"  [skip] show '{slug}' not found in DB")
        continue
    pid, title = r
    if not os.path.isfile(src):
        print(f"  [missing] {slug}: server file not found at {src}")
        missing_source += 1
        continue
    ext = os.path.splitext(src)[1].lower()
    dest = os.path.join(POSTERS_DEST, f"{slug}{ext}")
    if not os.path.exists(dest) or os.path.getsize(dest) != os.path.getsize(src):
        shutil.copy2(src, dest)
        action = 'copied'
    else:
        action = 'exists'
    new_url = f"/wp-content/uploads/posters/{slug}{ext}"

    # Update _thumbnail_external_url meta
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key='_thumbnail_external_url'", (pid,))
    cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)",
                (pid, '_thumbnail_external_url', new_url))
    print(f"  [{action}] {slug:<42} {title:<40} -> {new_url}")
    fixed += 1

c.commit()
c.close()
print(f"\nDone. Fixed: {fixed}. Source missing: {missing_source}.")
