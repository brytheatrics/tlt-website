"""
Job B — create tlt_show pages for archival shows that have photos on
\\TLT-SERVER\TLT Photos but NO database record yet. Mirrors the 1776-0506
proof-of-concept: published tlt_show + photo slideshow + season label.

Source: C:/temp/full_photo_report.json (the UNMATCHED tlt_photos folders).

Per show:
  - derive a clean title + slug  <title>-<seasoncode>
  - dedup the 1996-99 seasons (digitized twice: numeric folder + "Season Slides")
  - create/update the tlt_show record (idempotent on slug)
  - dates: real month span when the folder names a month, else a
    "YYYY-YYYY Season" label (show_season_label; template falls back to it)
  - assign the tlt_season taxonomy term
  - import up to 20 photos, resized to 1600px wide, into productions/<slug>/
  - attach a program PDF if one exists in uploads/programs/

Idempotent: re-running updates the record and wipes/rebuilds its photo folder.
"""
import os, re, json, time, shutil, calendar, pymysql
from PIL import Image

REPORT    = "C:/temp/full_photo_report.json"
UPLOADS   = "C:/Users/blake/Local Sites/tlt/app/public/wp-content/uploads"
DEST_BASE = os.path.join(UPLOADS, "productions")
PROG_DIR  = os.path.join(UPLOADS, "programs")
URL_BASE  = "/wp-content/uploads/productions"
PROG_URL  = "/wp-content/uploads/programs"
CAP, MAXW = 20, 1600
EXTS = ('.jpg', '.jpeg', '.png', '.tif', '.tiff', '.bmp')

NOT_SHOW = re.compile(r'education|off the shelf|clubtlt|special events|promotional|'
                      r'show and tell|archive photos|remodel|new photos|gala|celebration', re.I)

MONTHS = {'jan':1,'feb':2,'mar':3,'apr':4,'may':5,'jun':6,'jul':7,'aug':8,'sep':9,
          'oct':10,'nov':11,'dec':12,'sept':9}
MONTH_RE = (r'(jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|'
            r'aug(?:ust)?|sept?(?:ember)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)')

SMALL = {'a','an','and','the','of','in','on','at','to','for','is','there'}

# canonical merges for spelling/length twins of the SAME production
ALIAS = {
    '1000 clowns': 'A Thousand Clowns',
    'clowns': 'A Thousand Clowns',
    '12 angry men': 'Twelve Angry Men',
    'yes virginia': 'Yes, Virginia, There Is a Santa Claus',
    'yes virginia there is a santa claus': 'Yes, Virginia, There Is a Santa Claus',
    'the lion the witch and the wardrobe': 'The Lion, the Witch, and the Wardrobe',
}

def strip_season_suffix(s):
    return re.sub(r'\s+(Productions?\s+Photos|Season\s+Slides|Season)\s*$', '', s, flags=re.I).strip()

def season_years(season):
    s = strip_season_suffix(season)
    m = re.match(r'^(\d{2})(\d{2})$', s)
    if m:
        yy = int(m.group(1)); return (2000+yy if yy <= 25 else 1900+yy)
    m = re.match(r'^(\d{4})-\d{2,4}', s)
    if m: return int(m.group(1))
    return None

def season_code(season, start):
    s = strip_season_suffix(season)
    if re.match(r'^\d{4}$', s): return s
    m = re.match(r'^(\d{4})-(\d{2})', s)
    if m: return m.group(1)[2:] + m.group(2)
    return f"{str(start)[2:]}{str(start+1)[2:]}"

def clean_title(folder):
    t = folder
    t = re.sub(r'^\d{3,4}\s+', '', t)                       # leading season prefix "1213 "
    t = re.sub(r'\bCD\b', '', t)                            # CD archive marker
    t = re.sub(r'\b(Summer|Fall|Winter)\s+\d{4}-', '', t)   # "Summer 2013-Godspell"
    # trailing month(-month) + optional 2-4 digit year:  "Feb-Mar 97", "June 97", "Sep-Oct 00"
    t = re.sub(r'\s+' + MONTH_RE + r'(?:-' + MONTH_RE + r')?\s*\d{0,4}\s*$', '', t, flags=re.I)
    t = re.sub(r'\s+\d{6}\s*$', '', t)                      # date stamps "071319"
    t = re.sub(r'\s+(Promo Shots FFF|FFF)\s*$', '', t, flags=re.I)
    return re.sub(r'\s+', ' ', t).strip()

def titlecase(t):
    if not (t.isupper() or t.islower()):
        return t  # already mixed case from the folder — leave it
    out = []
    for i, w in enumerate(t.split()):
        lw = w.lower()
        out.append(lw if (lw in SMALL and i > 0) else lw.capitalize())
    return ' '.join(out)

def canon_key(title):
    n = re.sub(r'[^a-z0-9 ]', '', title.lower()); n = re.sub(r'^the ', '', n).strip()
    return n

def display_title(title):
    n = re.sub(r'[^a-z0-9 ]', '', title.lower()).strip()
    if n in ALIAS: return ALIAS[n]
    if canon_key(title) in (canon_key(v) for v in ALIAS.values()):
        for v in ALIAS.values():
            if canon_key(v) == canon_key(title): return v
    return titlecase(title)

def parse_month(folder):
    m = re.search(r'\b' + MONTH_RE + r'\b', folder, re.I)
    if not m: return None
    key = m.group(1).lower()[:4]
    return MONTHS.get(key, MONTHS.get(key[:3]))

def slugify(t):
    return re.sub(r'-+', '-', re.sub(r'[^a-z0-9]+', '-', t.lower())).strip('-')

def derive():
    d = json.load(open(REPORT))
    un = [e for e in d['tlt_photos']
          if not ((e.get('match') or {}).get('db_show'))
          and not NOT_SHOW.search(e['folder']) and not NOT_SHOW.search(e.get('season', ''))]
    rows = []
    for e in un:
        start = season_years(e['season'])
        raw = clean_title(e['folder'])
        disp = display_title(raw)
        rows.append(dict(disp=disp, start=start, end=(start+1 if start else None),
                         month=parse_month(e['folder']), avail=e['count'],
                         sc=season_code(e['season'], start), path=e['path'],
                         folder=e['folder'], season=e['season']))
    # dedup by (start_year, canonical display title) -> richer copy wins
    from collections import defaultdict
    g = defaultdict(list)
    for r in rows: g[(r['start'], canon_key(r['disp']))].append(r)
    final = []
    for _, v in g.items():
        final.append(sorted(v, key=lambda x: (x['avail'], 1 if x['month'] else 0))[-1])
    final.sort(key=lambda x: (x['start'] or 0, canon_key(x['disp'])))
    return final

# ---- photo import (TLT Photos full-res -> resize to 1600) ----
# Folder-name signals so 'Production Photos' beats a 'Headshots'/'Lobby' sibling.
PROD_RE  = re.compile(r'production\s*(?:photo|still)', re.I)
PRESS_RE = re.compile(r'press\s*photo|photo\s*release', re.I)
WEB_RE   = re.compile(r'(?<![a-z])jpe?g(?![a-z])|web', re.I)
BAD_RE   = re.compile(r'head\s*shot|\bbios?\b|\bcast\b|audition|'
                      r'poster|lobby|thumb|preview|program|\bb-?roll\b', re.I)

def _imgs_in(d):
    try:
        return sorted(os.path.join(d, e.name) for e in os.scandir(d)
                      if e.is_file() and e.name.lower().endswith(EXTS)
                      and not e.name.startswith('.'))   # skip macOS ._ AppleDouble + dotfiles
    except OSError:
        return []

def _score(path):
    p = path.replace('\\', '/').lower(); s = 0
    if PROD_RE.search(p):  s += 100
    elif PRESS_RE.search(p): s += 40
    if WEB_RE.search(p):   s += 10
    if BAD_RE.search(p):   s -= 1000
    return s

def list_images(folder):
    """Absolute image paths for the best PHOTO folder under `folder` — walks the
    whole tree and scores each image-bearing dir so Production Photos beats
    Headshots/Lobby, and skips macOS ._ dotfiles."""
    cands = []
    try:
        for dp, dirs, files in os.walk(folder):
            dirs[:] = [x for x in dirs if not x.startswith('.')]
            if any(f.lower().endswith(EXTS) and not f.startswith('.') for f in files):
                cands.append(dp)
    except OSError as ex:
        print(f"   ! cannot read {folder}: {ex}"); return []
    if not cands:
        return []
    best = max(cands, key=lambda d: (_score(d), len(_imgs_in(d))))
    return _imgs_in(best)

def emit(src, dst):
    try:
        with Image.open(src) as im:
            im = im.convert('RGB')
            if im.width > MAXW:
                im = im.resize((MAXW, round(im.height * MAXW / im.width)), Image.LANCZOS)
            im.save(dst, 'JPEG', quality=85, optimize=True)
        return True
    except Exception as ex:
        print(f"   ! skip {os.path.basename(src)}: {ex}"); return False

# ---- program lookup ----
_PROG_CACHE = None
def find_program(sc, disp):
    global _PROG_CACHE
    if _PROG_CACHE is None:
        _PROG_CACHE = sorted(os.listdir(PROG_DIR)) if os.path.isdir(PROG_DIR) else []
    words = [w for w in re.sub(r'[^a-z0-9 ]', '', disp.lower()).split() if w not in SMALL and len(w) > 3]
    for f in _PROG_CACHE:
        fl = f.lower()
        if fl.startswith(sc) and any(w in fl for w in words):
            return f"{PROG_URL}/{f}"
    return None

# ---- DB ----
def upsert(cur, title, slug, post_date, now):
    cur.execute("SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='tlt_show'", (slug,))
    row = cur.fetchone()
    if row:
        pid = row[0]
        cur.execute("UPDATE wp_posts SET post_title=%s, post_status='publish', post_date=%s,"
                    " post_date_gmt=%s, post_modified=%s, post_modified_gmt=%s WHERE ID=%s",
                    (title, post_date, post_date, now, now, pid))
        return pid, 'updated'
    cur.execute("""INSERT INTO wp_posts SET
        post_author=1, post_date=%s, post_date_gmt=%s, post_modified=%s, post_modified_gmt=%s,
        post_content='', post_title=%s, post_excerpt='', post_status='publish',
        post_type='tlt_show', post_name=%s, comment_status='closed', ping_status='closed',
        post_parent=0, menu_order=0, post_password='', to_ping='', pinged='',
        post_content_filtered='', guid=%s""",
        (post_date, post_date, now, now, title, slug, f'http://tlt.local/?post_type=tlt_show&p={slug}'))
    return cur.lastrowid, 'created'

def set_meta(cur, pid, key, value):
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, key))
    if value not in (None, ''):
        cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)",
                    (pid, key, value))

def assign_season(cur, pid, slug_season, name_season):
    cur.execute("""SELECT t.term_id, tt.term_taxonomy_id FROM wp_terms t
                   JOIN wp_term_taxonomy tt ON tt.term_id=t.term_id
                   WHERE tt.taxonomy='tlt_season' AND t.slug=%s""", (slug_season,))
    sr = cur.fetchone()
    if not sr:
        cur.execute("INSERT INTO wp_terms (name, slug) VALUES (%s,%s)", (name_season, slug_season))
        tid = cur.lastrowid
        cur.execute("INSERT INTO wp_term_taxonomy (term_id, taxonomy, description, parent, count)"
                    " VALUES (%s,'tlt_season','',0,0)", (tid,))
        sr = (tid, cur.lastrowid)
    cur.execute("SELECT 1 FROM wp_term_relationships WHERE object_id=%s AND term_taxonomy_id=%s",
                (pid, sr[1]))
    if not cur.fetchone():
        cur.execute("INSERT INTO wp_term_relationships (object_id, term_taxonomy_id) VALUES (%s,%s)",
                    (pid, sr[1]))
    cur.execute("UPDATE wp_term_taxonomy SET count=(SELECT COUNT(*) FROM wp_term_relationships"
                " WHERE term_taxonomy_id=%s) WHERE term_taxonomy_id=%s", (sr[1], sr[1]))

def main():
    shows = derive()
    print(f"Job B: {len(shows)} unique archival shows to create\n")
    c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
    cur = c.cursor()
    now = time.strftime('%Y-%m-%d %H:%M:%S')
    total_imgs = 0; created = 0; updated = 0; with_prog = 0; failed = []
    for n, sh in enumerate(shows, 1):
        title = sh['disp']; start = sh['start']; end = sh['end']
        slug = f"{slugify(title)}-{sh['sc']}"
        # dates
        season_label = f"{start}–{end} Season" if start else ''
        open_d = close_d = ''
        if sh['month'] and start:
            yr = start if sh['month'] >= 8 else end
            last = calendar.monthrange(yr, sh['month'])[1]
            open_d  = f"{yr}-{sh['month']:02d}-01"
            close_d = f"{yr}-{sh['month']:02d}-{last:02d}"
            post_date = f"{open_d} 00:00:00"
        else:
            post_date = f"{start}-09-01 00:00:00" if start else now
        # record
        pid, action = upsert(cur, title, slug, post_date, now)
        created += action == 'created'; updated += action == 'updated'
        # photos
        names = list_images(sh['path'])[:CAP]
        dest = os.path.join(DEST_BASE, slug)
        if os.path.isdir(dest): shutil.rmtree(dest, ignore_errors=True)
        os.makedirs(dest, exist_ok=True)
        gallery = []
        for src_path in names:
            idx = len(gallery) + 1
            out = f"{idx:02d}.jpg"
            if emit(src_path, os.path.join(dest, out)):
                gallery.append({"url": f"{URL_BASE}/{slug}/{out}",
                                "alt": f"{title} - production photo {idx}", "caption": ""})
        if not gallery:
            failed.append((slug, 'no photos imported'))
        # meta
        set_meta(cur, pid, 'show_open_date', open_d)
        set_meta(cur, pid, 'show_close_date', close_d)
        set_meta(cur, pid, 'show_season_label', season_label)
        set_meta(cur, pid, 'show_program_type', 'mainstage')
        set_meta(cur, pid, 'show_photo_gallery', json.dumps(gallery))
        prog = find_program(sh['sc'], title)
        if prog:
            set_meta(cur, pid, 'show_program_pdf_url', prog); with_prog += 1
        if start:
            assign_season(cur, pid, f"{start}-{end}", f"{start}-{end}")
        c.commit()
        total_imgs += len(gallery)
        ptag = ' +prog' if prog else ''
        dtag = open_d if open_d else season_label
        print(f"[{n:>2}/{len(shows)}] {action:>7} {slug:<34} {len(gallery):>2} photos  {dtag}{ptag}")
    c.close()
    print(f"\nDone. created={created} updated={updated}, {total_imgs} photos, {with_prog} programs linked.")
    if failed:
        print(f"\n{len(failed)} with no photos:")
        for slug, why in failed: print(f"   {slug}: {why}")

if __name__ == '__main__':
    main()
