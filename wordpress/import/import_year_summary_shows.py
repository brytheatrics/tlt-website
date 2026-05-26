"""
The year-summary posts for 2010-2011, 2011-2012, 2012-2013 contain full
show info (title, dates, director, synopsis) that never got migrated as
proper tlt_show records. Parse them out and create the show records.

Each show block in the body looks like:

    <h2><a href="/wp-content/uploads/programs/SLUG.pdf">Show Name</a></h2>
    <p>August 27th through September 26th 2010</p>           <- dates
    <p>Directed by NAME</p>                                  <- optional
    <p>Synopsis text…</p>                                    <- description

Idempotent — re-running skips shows already imported (matched by title
within season term).
"""
import re, sys, io, time, pymysql
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

YEAR_SUMMARIES = ['2010-2011', '2011-2012', '2012-2013']

MONTHS = {
    'jan':1,'january':1,'feb':2,'february':2,'mar':3,'march':3,'apr':4,'april':4,
    'may':5,'jun':6,'june':6,'jul':7,'july':7,'aug':8,'august':8,'sep':9,'sept':9,'september':9,
    'oct':10,'october':10,'nov':11,'november':11,'dec':12,'december':12,
}


def slugify(s, suffix=''):
    s = s.lower()
    s = re.sub(r"[^a-z0-9]+", '-', s).strip('-')
    return s + (f'-{suffix}' if suffix else '')


def parse_date_range(text, season_start, season_end):
    """Parse 'August 27th through September 26th 2010' -> (open, close) ISO strings."""
    m = re.search(
        r'(?P<m1>[A-Za-z]+)\s+(?P<d1>\d{1,2})(?:st|nd|rd|th)?\s*(?:-|–|through|to)\s*'
        r'(?P<m2>[A-Za-z]+)?\s*(?P<d2>\d{1,2})(?:st|nd|rd|th)?(?:[,\s]+(?P<y>\d{4}))?',
        text, re.I
    )
    if not m: return (None, None)
    m1 = MONTHS.get(m.group('m1').lower().rstrip('.'))
    m2_str = m.group('m2')
    m2 = MONTHS.get(m2_str.lower().rstrip('.')) if m2_str else m1
    d1 = int(m.group('d1')); d2 = int(m.group('d2'))
    y = int(m.group('y')) if m.group('y') else None

    if y:
        y2 = y
        y1 = season_start if m1 >= 8 else y2
    else:
        y1 = season_start if m1 >= 8 else season_end
        y2 = season_start if m2 >= 8 else season_end
    try:
        return (f'{y1:04d}-{m1:02d}-{d1:02d}', f'{y2:04d}-{m2:02d}-{d2:02d}')
    except (ValueError, TypeError):
        return (None, None)


def parse_show_blocks(body):
    """Split body on <h2>…</h2> and yield (title, pdf_url, dates_text, director_text, synopsis_html)."""
    # Each show starts with an <h2> with a linked title. Use that as the splitter.
    pattern = re.compile(
        r'<h2[^>]*>\s*'
        r'(?:<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>)?'   # 1: optional pdf url
        r'(.*?)'                                            # 2: title
        r'(?:</a>)?'
        r'\s*</h2>'
        r'(.*?)'                                            # 3: everything until next <h2> or end
        r'(?=<h2[^>]*>|$)',
        re.S | re.I
    )
    for m in pattern.finditer(body):
        pdf, title_html, rest = m.group(1), m.group(2), m.group(3)
        title = re.sub(r'<[^>]+>', '', title_html).strip()
        if not title: continue
        # Find first <p> in rest = date range
        ps = re.findall(r'<p[^>]*>(.*?)</p>', rest, re.S)
        ps_text = [re.sub(r'<[^>]+>', '', p).strip() for p in ps]
        dates_text = ps_text[0] if ps_text else ''
        director = ''
        synopsis_paragraphs = []
        for p in ps_text[1:]:
            if re.match(r'^Directed by\b', p, re.I):
                director = re.sub(r'^Directed by\s+', '', p, flags=re.I).strip()
            elif p:
                synopsis_paragraphs.append(p)
        yield {
            'title':    title,
            'pdf_url':  pdf,
            'dates_text': dates_text,
            'director': director,
            'synopsis': '\n\n'.join(f'<p>{p}</p>' for p in synopsis_paragraphs),
        }


def get_or_make_term(cur, season_slug):
    cur.execute("""SELECT tt.term_taxonomy_id FROM wp_terms t
                   JOIN wp_term_taxonomy tt ON tt.term_id=t.term_id
                   WHERE tt.taxonomy='tlt_season' AND t.slug=%s""", (season_slug,))
    r = cur.fetchone()
    if r: return r[0]
    cur.execute("INSERT INTO wp_terms (name, slug) VALUES (%s,%s)", (season_slug, season_slug))
    tid = cur.lastrowid
    cur.execute("INSERT INTO wp_term_taxonomy (term_id, taxonomy, description, parent, count) VALUES (%s,'tlt_season','',0,0)", (tid,))
    return cur.lastrowid


def show_exists_in_season(cur, title, season_slug):
    cur.execute("""SELECT p.ID FROM wp_posts p
                   JOIN wp_term_relationships tr ON tr.object_id=p.ID
                   JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id
                   JOIN wp_terms t ON t.term_id=tt.term_id
                   WHERE p.post_type='tlt_show' AND p.post_status='publish'
                     AND tt.taxonomy='tlt_season' AND t.slug=%s
                     AND LOWER(p.post_title)=LOWER(%s)
                   LIMIT 1""", (season_slug, title))
    r = cur.fetchone()
    return r[0] if r else None


def set_meta(cur, pid, key, value):
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, key))
    if value:
        cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)", (pid, key, value))


def main():
    c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
    cur = c.cursor()
    now = time.strftime('%Y-%m-%d %H:%M:%S')

    for season_slug in YEAR_SUMMARIES:
        m = re.match(r'^(\d{4})-(\d{4})$', season_slug)
        if not m: continue
        season_start = int(m.group(1)); season_end = int(m.group(2))

        cur.execute("SELECT post_content FROM wp_posts WHERE post_name=%s AND post_type='post' LIMIT 1", (season_slug,))
        r = cur.fetchone()
        if not r:
            print(f"  [skip] {season_slug}: no summary post found")
            continue

        body = r[0]
        tt_id = get_or_make_term(cur, season_slug)
        print(f"\n=== {season_slug} ===")

        created = 0
        for block in parse_show_blocks(body):
            title = block['title']
            existing = show_exists_in_season(cur, title, season_slug)
            if existing:
                print(f"  [skip] {title} (already exists as id={existing})")
                continue

            open_d, close_d = parse_date_range(block['dates_text'], season_start, season_end)

            slug = slugify(title) + f'-{season_start}'
            # Ensure uniqueness
            cur.execute("SELECT ID FROM wp_posts WHERE post_name=%s", (slug,))
            if cur.fetchone():
                slug = slugify(title, suffix=f'{season_start}-2')

            cur.execute("""INSERT INTO wp_posts SET
                post_author=1, post_date=%s, post_date_gmt=%s, post_modified=%s, post_modified_gmt=%s,
                post_content=%s, post_title=%s, post_excerpt='',
                post_status='publish', post_type='tlt_show', post_name=%s,
                comment_status='closed', ping_status='closed', post_parent=0, menu_order=0,
                post_password='', to_ping='', pinged='', post_content_filtered='',
                guid=%s""",
                (now, now, now, now, block['synopsis'], title, slug,
                 f'http://tlt.local/?p=tlt_show-{slug}'))
            pid = cur.lastrowid

            set_meta(cur, pid, 'show_open_date',  open_d or '')
            set_meta(cur, pid, 'show_close_date', close_d or '')
            set_meta(cur, pid, 'show_director',   block['director'] or '')
            set_meta(cur, pid, 'show_program_type', 'mainstage')
            if block['pdf_url']:
                set_meta(cur, pid, 'show_program_pdf_url', block['pdf_url'])

            # Assign to season term
            cur.execute("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id) VALUES (%s,%s)", (pid, tt_id))

            print(f"  CREATED id={pid:<5} slug={slug}")
            print(f"           title='{title}'")
            print(f"           dates={open_d} -> {close_d}  director='{block['director']}'")
            created += 1

        # Refresh term count
        cur.execute("UPDATE wp_term_taxonomy SET count=(SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id=%s) WHERE term_taxonomy_id=%s", (tt_id, tt_id))
        print(f"  Imported {created} shows for {season_slug}")

    c.commit()
    c.close()
    print("\nDone.")


if __name__ == '__main__':
    main()
