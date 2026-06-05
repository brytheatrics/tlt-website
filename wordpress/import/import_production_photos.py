"""
Job A — import production photos for the 92 shows that ALREADY have a tlt_show
record (matched in C:/temp/full_photo_report.json).

Per show:
  - prefer the Marketing JPEG/ subfolder (already web-sized) -> copy verbatim
  - else fall back to the TLT Photos full-res folder -> resize to 1600px wide
  - wipe-and-rebuild  uploads/productions/<slug>/  with up to 20 photos NN.jpg
  - set show_photo_gallery meta = JSON [{url,alt,caption}]  (matches single-tlt_show.php)

Idempotent: re-running wipes each show's folder and rebuilds cleanly.
"""
import os, sys, re, json, shutil, pymysql
from PIL import Image

REPORT   = "C:/temp/full_photo_report.json"
UPLOADS  = "C:/Users/blake/Local Sites/tlt/app/public/wp-content/uploads"
DEST_BASE= os.path.join(UPLOADS, "productions")
URL_BASE = "/wp-content/uploads/productions"
CAP      = 20
MAXW     = 1600
EXTS     = ('.jpg', '.jpeg', '.png', '.tif', '.tiff', '.bmp')

# Folder-name signals used to pick the RIGHT image folder under a show's source.
PROD_RE  = re.compile(r'production\s*(?:photo|still)', re.I)          # strongest: real production shots
PRESS_RE = re.compile(r'press\s*photo|photo\s*release', re.I)        # acceptable: press/release stills
WEB_RE   = re.compile(r'(?<![a-z])jpe?g(?![a-z])|web', re.I)         # web-sized export -> tie-breaker
BAD_RE   = re.compile(r'head\s*shot|\bbios?\b|\bcast\b|audition|'    # WRONG content: skip hard
                      r'poster|lobby|thumb|preview|program|\bb-?roll\b', re.I)

def _imgs_in(d):
    try:
        return sorted(os.path.join(d, e.name) for e in os.scandir(d)
                      if e.is_file() and e.name.lower().endswith(EXTS)
                      and not e.name.startswith('.'))   # skip macOS ._ AppleDouble + dotfiles
    except OSError:
        return []

def _score(path):
    p = path.replace('\\', '/').lower()
    s = 0
    if PROD_RE.search(p):  s += 100
    elif PRESS_RE.search(p): s += 40
    if WEB_RE.search(p):   s += 10
    if BAD_RE.search(p):   s -= 1000   # headshots/bios/etc. never win
    return s

def list_images(folder):
    """Absolute image paths for the best PHOTO folder under `folder`.
    Walks the whole tree and scores each image-bearing dir so a show's
    'Production Photos\\JPEG' beats its 'Headshots' sibling. If the given
    folder itself holds images and no better-scoring subfolder exists, uses it."""
    cands = []
    try:
        for dp, dirs, files in os.walk(folder):
            dirs[:] = [x for x in dirs if not x.startswith('.')]
            imgs = [f for f in files if f.lower().endswith(EXTS) and not f.startswith('.')]
            if imgs:
                cands.append(dp)
    except OSError as ex:
        print(f"   ! cannot read source: {ex}")
        return []
    if not cands:
        return []
    # highest score wins; tie-break on photo count
    best = max(cands, key=lambda d: (_score(d), len(_imgs_in(d))))
    return _imgs_in(best)

def emit(src_path, dst_path, prefer_verbatim):
    """Copy verbatim if already a small JPEG, else resize/re-encode to JPEG."""
    ext = os.path.splitext(src_path)[1].lower()
    if prefer_verbatim and ext in ('.jpg', '.jpeg'):
        try:
            with Image.open(src_path) as im:
                if im.width <= MAXW:
                    shutil.copyfile(src_path, dst_path)
                    return True
        except Exception:
            pass  # fall through to re-encode
    try:
        with Image.open(src_path) as im:
            im = im.convert('RGB')
            if im.width > MAXW:
                h = round(im.height * MAXW / im.width)
                im = im.resize((MAXW, h), Image.LANCZOS)
            im.save(dst_path, 'JPEG', quality=85, optimize=True)
        return True
    except Exception as ex:
        print(f"   ! skip bad image {os.path.basename(src_path)}: {ex}")
        return False

def _mkt_root(best_path, folder_name):
    """The show's marketing root folder, so list_images can choose the right
    subfolder itself instead of trusting the report's (sometimes-wrong) best_set.
    Truncate best_set at the show-folder segment (e.g. '...\\2122 Irish')."""
    idx = best_path.find(folder_name)
    if idx >= 0:
        return best_path[:idx + len(folder_name)]
    return os.path.dirname(best_path)

def build_sources():
    """Return list of dicts: {id,slug,title,src,is_marketing} — marketing preferred.
    For marketing shows `src` is the show ROOT (resolver picks Production Photos)."""
    d = json.load(open(REPORT))
    def db(e): return (e.get('match') or {}).get('db_show')
    mkt = {db(e)['id']: e for e in d['marketing'] if db(e)}
    tlt = {}
    for e in d['tlt_photos']:
        s = db(e)
        if s: tlt.setdefault(s['id'], e)
    out = []
    for sid in sorted(set(mkt) | set(tlt)):
        if sid in mkt:
            e = mkt[sid]; s = db(e)
            root = _mkt_root(e['best_set']['full_path'], e['folder'])
            out.append(dict(id=sid, slug=s['slug'], title=s['title'],
                            src=root, is_marketing=True))
        else:
            e = tlt[sid]; s = db(e)
            out.append(dict(id=sid, slug=s['slug'], title=s['title'],
                            src=e['path'], is_marketing=False))
    return out

def set_gallery(cur, pid, gallery):
    val = json.dumps(gallery)
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key='show_photo_gallery'", (pid,))
    cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,'show_photo_gallery',%s)",
                (pid, val))

def main():
    shows = build_sources()
    print(f"Job A: {len(shows)} shows with existing records\n")
    c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
    cur = c.cursor()
    total_imgs = 0; total_mb = 0.0; failed = []
    for n, sh in enumerate(shows, 1):
        slug = sh['slug']
        names = list_images(sh['src'])[:CAP]
        if not names:
            failed.append((slug, 'no source images'))
            print(f"[{n:>2}/{len(shows)}] {slug:<34} SKIP (no images)")
            continue
        dest = os.path.join(DEST_BASE, slug)
        if os.path.isdir(dest):
            shutil.rmtree(dest, ignore_errors=True)
        os.makedirs(dest, exist_ok=True)
        gallery = []; got = 0
        for src_path in names:
            idx = got + 1
            out_name = f"{idx:02d}.jpg"
            if emit(src_path, os.path.join(dest, out_name), sh['is_marketing']):
                gallery.append({"url": f"{URL_BASE}/{slug}/{out_name}",
                                "alt": f"{sh['title']} - production photo {idx}", "caption": ""})
                got += 1
        if not gallery:
            failed.append((slug, 'all images failed'))
            print(f"[{n:>2}/{len(shows)}] {slug:<34} SKIP (all failed)")
            continue
        set_gallery(cur, sh['id'], gallery)
        c.commit()
        mb = sum(os.path.getsize(os.path.join(dest, g['url'].split('/')[-1])) for g in gallery) / 1e6
        total_imgs += got; total_mb += mb
        src_tag = 'mkt' if sh['is_marketing'] else 'TLT'
        print(f"[{n:>2}/{len(shows)}] {slug:<34} {got:>2} photos  {mb:>5.1f} MB  ({src_tag})")
    c.close()
    print(f"\nDone. {total_imgs} photos across {len(shows)-len(failed)} shows, {total_mb:.0f} MB total.")
    if failed:
        print(f"\n{len(failed)} shows had problems:")
        for slug, why in failed: print(f"   {slug}: {why}")

if __name__ == '__main__':
    main()
