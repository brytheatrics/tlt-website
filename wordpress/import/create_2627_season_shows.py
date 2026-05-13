"""
Create / update the seven 2026-2027 mainstage shows and generate
filler posters for each. Sourced from /season-tickets/ page copy.

Idempotent — re-running updates existing records and overwrites posters.
"""
import os, time, pymysql, hashlib
from PIL import Image, ImageDraw, ImageFont

# --- show data ----------------------------------------------------------
SHOWS = [
    {
        'slug': 'the-outsider',
        'title': 'THE OUTSIDER',
        'open': '2026-08-28', 'close': '2026-09-13',
        'tagline': 'Politics have never been this awkward… or this funny.',
        'body': ('Ned Newley doesn’t even want to be governor. He’s terrified of '
                 'public speaking, and his poll numbers are impressively bad. To his ever-supportive '
                 'Chief of Staff, Ned seems destined to fail. But political consultant Arthur Vance '
                 'sees things differently. A timely and hilarious comedy that skewers politics and '
                 'celebrates democracy.'),
        'color': (40, 60, 110),    # navy blue
    },
    {
        'slug': 'arsenic-and-old-lace',
        'title': 'ARSENIC AND OLD LACE',
        'open': '2026-10-16', 'close': '2026-11-01',
        'tagline': 'Meet the sweetest little old ladies… with a deadly hobby.',
        'body': ('Drama critic Mortimer Brewster’s engagement announcement is upended when he '
                 'discovers a corpse in his elderly aunts’ window seat. Between his aunts’ '
                 'penchant for poisoning wine, a brother who thinks he’s Teddy Roosevelt, and '
                 'another brother using plastic surgery to hide from the police, it’ll be a '
                 'miracle if Mortimer makes it to his wedding.'),
        'color': (90, 30, 40),     # deep burgundy
    },
    {
        'slug': 'hallmarked',
        'title': 'HALLMARKED',
        'open': '2026-12-04', 'close': '2026-12-27',
        'tagline': 'A musical for people who love Hallmark movies… and people who don’t.',
        'body': ('The West Coast premiere of a new musical. It seems everyone on the planet is '
                 'obsessed with Hallmark movies. Everyone except Julie. Packed with fabulous new '
                 'pop songs, loads of laughter, and heartwarming delight, Hallmarked is a rom-com '
                 'fever dream for those who swoon over the movies and for those who love roasting '
                 'the people who do.'),
        'color': (180, 50, 70),    # holiday red
    },
    {
        'slug': 'dot',
        'title': 'DOT',
        'open': '2027-02-05', 'close': '2027-02-21',
        'tagline': 'A family comedy with heart—and just a touch of heartbreak.',
        'body': ('The holidays are always a wild family affair at the Shealy house. As Dotty '
                 'struggles to hold on to her memory, her children must fight to balance care for '
                 'their mother and care for themselves. A twisted and hilarious new play that '
                 'grapples unflinchingly with aging parents and midlife crises.'),
        'color': (60, 95, 80),     # muted forest green
    },
    {
        'slug': 'urinetown',
        'title': 'URINETOWN',
        'open': '2027-03-26', 'close': '2027-04-18',
        'tagline': 'A wickedly funny, fast-paced, surprisingly intelligent comedic romp.',
        'body': ('In this side-splitting satire, the young hero Bobby Strong leads his community in '
                 'a fight against oppression. Set in a dystopian world where water is scarce and '
                 '“Hope” is even scarcer, all citizens must now pay a fee for '
                 '“The Privilege to Pee.”'),
        'color': (200, 140, 30),   # mustard yellow
    },
    {
        'slug': 'the-importance-of-being-earnest',
        'title': 'THE IMPORTANCE OF BEING EARNEST',
        'open': '2027-05-21', 'close': '2027-06-06',
        'tagline': 'A trivial comedy for serious people — in partnership with UWT.',
        'body': ('Being sensible can be excessively boring. At least Jack thinks so. While assuming '
                 'the role of dutiful guardian in the country, he lets loose in town under a false '
                 'identity. Hoping to impress two eligible ladies, the gentlemen find themselves '
                 'caught in a web of lies they must carefully navigate.'),
        'color': (110, 80, 130),   # plum
    },
    {
        'slug': 'the-play-that-goes-wrong',
        'title': 'THE PLAY THAT GOES WRONG',
        'open': '2027-07-09', 'close': '2027-07-25',
        'tagline': 'Back by popular demand — get ready to laugh even more.',
        'body': ('Welcome to opening night of the Cornley University Drama Society’s newest '
                 'production, where things are quickly going from bad to utterly disastrous. This '
                 '1920s whodunit has everything you never wanted in a show — an unconscious '
                 'leading lady, a corpse that can’t play dead, and actors who trip over '
                 'everything (including their lines).'),
        'color': (30, 30, 30),     # near-black (curtain)
    },
]

# --- filler poster generation -------------------------------------------
POSTER_DIR = "C:/Users/blake/Local Sites/tlt/app/public/wp-content/uploads/2026/05"
POSTER_URL_BASE = "/wp-content/uploads/2026/05"
POSTER_W, POSTER_H = 800, 1200

def find_font(size):
    """Try a few common Windows fonts, fall back to PIL default."""
    candidates = [
        "C:/Windows/Fonts/MONTSERRAT-BOLD.ttf",
        "C:/Windows/Fonts/Montserrat-Bold.ttf",
        "C:/Windows/Fonts/Arial.ttf",
        "C:/Windows/Fonts/arial.ttf",
    ]
    for path in candidates:
        if os.path.isfile(path):
            try:
                return ImageFont.truetype(path, size)
            except OSError:
                pass
    return ImageFont.load_default()

def wrap_text(draw, text, font, max_w):
    """Wrap text into lines that fit max_w pixels."""
    words = text.split()
    lines, cur = [], ''
    for w in words:
        test = (cur + ' ' + w).strip()
        bbox = draw.textbbox((0, 0), test, font=font)
        if bbox[2] - bbox[0] <= max_w:
            cur = test
        else:
            if cur: lines.append(cur)
            cur = w
    if cur: lines.append(cur)
    return lines

def format_dates(open_d, close_d):
    """'2026-08-28', '2026-09-13' -> 'AUG 28 – SEP 13, 2026'  (year omitted if same)."""
    import datetime as dt
    o = dt.date.fromisoformat(open_d)
    c = dt.date.fromisoformat(close_d)
    months = ['JAN','FEB','MAR','APR','MAY','JUN','JUL','AUG','SEP','OCT','NOV','DEC']
    if o.year == c.year and o.month == c.month:
        return f"{months[o.month-1]} {o.day} – {c.day}, {c.year}"
    if o.year == c.year:
        return f"{months[o.month-1]} {o.day} – {months[c.month-1]} {c.day}, {c.year}"
    return f"{months[o.month-1]} {o.day}, {o.year} – {months[c.month-1]} {c.day}, {c.year}"

def make_poster(show):
    """Generate a simple title-card poster as a placeholder."""
    bg = show['color']
    img = Image.new('RGB', (POSTER_W, POSTER_H), bg)
    draw = ImageDraw.Draw(img)

    # Faint inner border for a "card" feel
    inset = 50
    draw.rectangle([inset, inset, POSTER_W-inset, POSTER_H-inset], outline=(255,255,255), width=3)

    # Eyebrow
    eyebrow_font = find_font(28)
    draw.text((POSTER_W//2, 160), 'TACOMA LITTLE THEATRE', font=eyebrow_font,
              fill=(255,255,255), anchor='mm')
    draw.text((POSTER_W//2, 200), '2026 – 2027 SEASON', font=eyebrow_font,
              fill=(255,255,255), anchor='mm')

    # Title (wrapped)
    title_font = find_font(72)
    title_lines = wrap_text(draw, show['title'], title_font, POSTER_W - 160)
    line_h = 86
    total_h = line_h * len(title_lines)
    start_y = POSTER_H//2 - total_h//2
    for i, line in enumerate(title_lines):
        draw.text((POSTER_W//2, start_y + i*line_h + line_h//2), line, font=title_font,
                  fill=(255,255,255), anchor='mm')

    # Date band
    date_font = find_font(32)
    draw.text((POSTER_W//2, POSTER_H - 240), format_dates(show['open'], show['close']),
              font=date_font, fill=(255,255,255), anchor='mm')

    # Tagline
    tag_font = find_font(22)
    tag_lines = wrap_text(draw, show['tagline'], tag_font, POSTER_W - 200)
    for i, line in enumerate(tag_lines):
        draw.text((POSTER_W//2, POSTER_H - 180 + i*30), line, font=tag_font,
                  fill=(230,230,230), anchor='mm')

    # Placeholder marker (small, bottom-right)
    pf = find_font(16)
    draw.text((POSTER_W - inset - 10, POSTER_H - inset - 10), 'PLACEHOLDER POSTER',
              font=pf, fill=(255,255,255), anchor='rb')

    return img

# --- DB writes ----------------------------------------------------------
def upsert_show(cur, show, now):
    cur.execute("SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='tlt_show'", (show['slug'],))
    row = cur.fetchone()
    if row:
        pid = row[0]
        cur.execute("""UPDATE wp_posts
                       SET post_title=%s, post_content=%s, post_excerpt=%s,
                           post_status='publish', post_modified=%s, post_modified_gmt=%s
                       WHERE ID=%s""",
                    (show['title'], '<p>'+show['body']+'</p>', show['tagline'], now, now, pid))
        action = 'updated'
    else:
        cur.execute("""INSERT INTO wp_posts SET
            post_author=1, post_date=%s, post_date_gmt=%s, post_modified=%s, post_modified_gmt=%s,
            post_content=%s, post_title=%s, post_excerpt=%s,
            post_status='publish', post_type='tlt_show', post_name=%s,
            comment_status='closed', ping_status='closed', post_parent=0, menu_order=0,
            post_password='', to_ping='', pinged='', post_content_filtered='',
            guid=%s""",
            (now, now, now, now, '<p>'+show['body']+'</p>', show['title'], show['tagline'],
             show['slug'], f'http://tlt.local/?p={show["slug"]}'))
        pid = cur.lastrowid
        action = 'created'
    return pid, action

def set_meta(cur, pid, key, value):
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, key))
    cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)",
                (pid, key, value))

def assign_season(cur, pid):
    cur.execute("""SELECT t.term_id, tt.term_taxonomy_id FROM wp_terms t
                   JOIN wp_term_taxonomy tt ON tt.term_id=t.term_id
                   WHERE tt.taxonomy='tlt_season' AND t.slug='2026-2027'""")
    sr = cur.fetchone()
    if not sr:
        cur.execute("INSERT INTO wp_terms (name, slug) VALUES ('2026-2027','2026-2027')")
        tid = cur.lastrowid
        cur.execute("""INSERT INTO wp_term_taxonomy (term_id, taxonomy, description, parent, count)
                       VALUES (%s,'tlt_season','',0,0)""", (tid,))
        sr = (tid, cur.lastrowid)
    cur.execute("SELECT 1 FROM wp_term_relationships WHERE object_id=%s AND term_taxonomy_id=%s",
                (pid, sr[1]))
    if not cur.fetchone():
        cur.execute("INSERT INTO wp_term_relationships (object_id, term_taxonomy_id) VALUES (%s,%s)",
                    (pid, sr[1]))
    cur.execute("""UPDATE wp_term_taxonomy
                   SET count=(SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id=%s)
                   WHERE term_taxonomy_id=%s""", (sr[1], sr[1]))

def main():
    os.makedirs(POSTER_DIR, exist_ok=True)
    c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
    cur = c.cursor()
    now = time.strftime('%Y-%m-%d %H:%M:%S')

    for show in SHOWS:
        pid, action = upsert_show(cur, show, now)

        # Filler poster
        poster_fn = f"{show['slug']}-poster.jpg"
        poster_path = os.path.join(POSTER_DIR, poster_fn)
        make_poster(show).save(poster_path, 'JPEG', quality=90, optimize=True)
        poster_url = f"{POSTER_URL_BASE}/{poster_fn}"

        # Meta
        set_meta(cur, pid, 'show_open_date',      show['open'])
        set_meta(cur, pid, 'show_close_date',     show['close'])
        set_meta(cur, pid, 'show_program_type',   'mainstage')
        set_meta(cur, pid, 'show_tagline',        show['tagline'])
        set_meta(cur, pid, 'show_ticket_url',     'https://tlt.ludus.com/')
        set_meta(cur, pid, 'show_hero_image_url', poster_url)
        set_meta(cur, pid, '_thumbnail_external_url', poster_url)

        # Season
        assign_season(cur, pid)

        size_kb = os.path.getsize(poster_path) / 1024
        print(f"{action:>7}  {show['slug']:<35} id={pid}  poster {size_kb:.0f} KB")

    c.commit()
    c.close()
    print(f"\nDone. 7 shows in the 2026-2027 season. Filler posters in {POSTER_DIR}")

if __name__ == '__main__':
    main()
