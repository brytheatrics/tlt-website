"""
Prototype: make The Outsider the active hero show, with the flat poster as
the hero image. Removes the partially-broken layered hero folder so the
flat fallback is used.
"""
import os, shutil, pymysql
from PIL import Image

SRC_POSTER = "G:/My Drive/Marketing/2627 Posters/1 The Outsider/The Outsider Poster Base.png"
DEST = "C:/Users/blake/Local Sites/tlt/app/public/wp-content/uploads/2026/05/the-outsider-hero.jpg"
os.makedirs(os.path.dirname(DEST), exist_ok=True)

# Resize and convert to JPG so the hero loads fast
print("Resizing poster for hero use...")
with Image.open(SRC_POSTER) as img:
    img = img.convert('RGB')
    target_w = 2000
    if img.width > target_w:
        ratio = target_w / img.width
        img = img.resize((target_w, int(img.height * ratio)), Image.LANCZOS)
    img.save(DEST, 'JPEG', quality=88, optimize=True)
print(f"  Saved {DEST} ({os.path.getsize(DEST)/1024:.0f} KB)")

# Update DB
c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()
cur.execute("SELECT ID FROM wp_posts WHERE post_name='the-outsider' AND post_type='tlt_show'")
pid = cur.fetchone()[0]

# Set hero image override + make Outsider the currently-running show so the hero engages
fields = {
    'show_hero_image_url': '/wp-content/uploads/2026/05/the-outsider-hero.jpg',
    '_thumbnail_external_url': '/wp-content/uploads/2026/05/the-outsider-hero.jpg',
    'show_open_date': '2026-05-01',     # so it shows as "Now Playing" right now
    'show_close_date': '2026-12-31',
}
for k, v in fields.items():
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, k))
    cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)", (pid, k, v))
c.commit()
c.close()

# Clean up broken hero-layers folder so the static fallback is used
broken_layers = "C:/Users/blake/Local Sites/tlt/app/public/wp-content/uploads/hero-layers/the-outsider"
if os.path.isdir(broken_layers):
    shutil.rmtree(broken_layers)
    print(f"Removed broken layers folder at {broken_layers}")

print(f"\nDone. The Outsider is now the 'Now Playing' hero show (ID {pid}).")
print("Refresh tlt.local/home/ to see it.")
