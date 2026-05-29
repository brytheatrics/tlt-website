"""
The Squarespace-imported tlt_team post_content includes a <figure>...</figure>
block at the top with the team member's headshot. The single-tlt_team.php
template already displays the featured image, so this produces a duplicate.

Strip the leading Squarespace image wrapper (and any other embedded images
in the bio) from each tlt_team post_content. Keep the bio text.

Idempotent.
"""
import re, sys, io
import pymysql
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')


def clean_bio(html):
    s = html

    # 1) Drop entire <figure>...</figure> blocks (Squarespace image wrappers)
    s = re.sub(r'<figure\b[^>]*>.*?</figure>', '', s, flags=re.S | re.I)

    # 2) Drop any remaining <img ...> tags (defensive)
    s = re.sub(r'<img\b[^>]*/?>', '', s, flags=re.I)

    # 3) Drop divs that contained just the figure wrapper. SS nests deeply with
    #    'image-block-wrapper', 'sqs-block-image', 'fluid-image-container', etc.
    for cls in ['image-block', 'sqs-block-image', 'sqs-block-content',
                'fluid-image', 'intrinsic', 'fluidImageOverlay', 'sqs-image']:
        s = re.sub(r'<div[^>]*class="[^"]*' + cls + r'[^"]*"[^>]*>\s*</div>',
                   '', s, flags=re.I)

    # 4) Collapse runs of now-empty divs
    prev = None
    while prev != s:
        prev = s
        s = re.sub(r'<div[^>]*>\s*</div>', '', s, flags=re.I)
        s = re.sub(r'<div[^>]*>(\s*)</div>', '', s, flags=re.I)

    # 5) Collapse blank lines
    s = re.sub(r'\n{3,}', '\n\n', s)
    return s.strip()


c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()
cur.execute("SELECT ID, post_title, post_content FROM wp_posts WHERE post_type='tlt_team' AND post_status='publish'")
rows = cur.fetchall()
print(f"Found {len(rows)} team members\n")

n = 0
for pid, title, content in rows:
    new = clean_bio(content or '')
    if new != content:
        cur.execute("UPDATE wp_posts SET post_content=%s WHERE ID=%s", (new, pid))
        n += 1
        print(f"  cleaned: {title:<35} {len(content)} -> {len(new)} chars")

c.commit()
c.close()
print(f"\nCleaned {n} of {len(rows)} bios.")
