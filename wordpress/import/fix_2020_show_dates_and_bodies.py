"""
Fix three 2019-2020 show records using actual poster art as the source of truth:

  A Chorus Line (id 1333):
    - dates: 2020-03-06 to 2020-03-29 (scheduled per poster; ran ~1 week
      before COVID closure)
    - body: strip Squarespace cruft (post date, credit lines, schedule/
      pricing paragraph, Reviews/Tags nav) — keep synopsis + cast list

  Terms of Endearment (id 1334):
    - director: Kathy Pingel  (was Blake R. York — that was 2021-22)
    - dates: 2020-04-24 to 2020-05-10  (poster)
    - body: strip Squarespace cruft

  The Manchurian Candidate (id 1094):
    - dates: 2020-06-05 to 2020-06-21  (poster — my earlier "canonical
      schedule" estimate of Apr-May was wildly off)

Idempotent.
"""
import re, sys, io, pymysql
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')


def clean_body(html):
    """Strip Squarespace cruft (post date, credit lines, schedule/pricing, trailing nav)."""
    s = html

    # Drop Squarespace post-date paragraph (e.g. "June 23, 2020")
    s = re.sub(
        r'<p[^>]*>\s*(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},\s+\d{4}\s*</p>',
        '', s, flags=re.I
    )

    # Drop credit paragraphs — template renders these from meta
    s = re.sub(
        r'<p[^>]*>\s*(?:<[^>]+>\s*)*'
        r'(?:Directed (?:&amp;|&|and) Choreographed by|Directed (?:&amp;|&|and) Musically Directed by'
        r'|Co-Directed and Choreographed by|Co-Directed by|Musically Directed by|Music(?:al)? Direction by'
        r'|Choreographed by|Choreography by|Directed by)'
        r'\b[^<]*'
        r'(?:\s*<br\s*/?>\s*[^<]*)*'
        r'\s*</p>',
        '', s, flags=re.I
    )

    # Drop schedule/pricing paragraph (matches lines starting with a date range
    # or "Fridays and Saturdays" or "POSTPONED Fridays" or with $-pricing)
    s = re.sub(
        r'<p[^>]*>\s*(?:<[^>]+>\s*)*'
        r'(?:[A-Z][a-z]+\s+\d{1,2}\s*-\s*[A-Z][a-z]+\s+\d{1,2},?\s*\d{4}'
        r'|POSTPONED'
        r'|Fridays\s+and\s+Saturdays)'
        r'.*?'
        r'(?:Children\s+12[^<]*|Pay\s+What\s+You\s+Can[^<]*)'
        r'\s*</p>',
        '', s, flags=re.I | re.S
    )

    # Drop trailing "Reviews / Tags YYYY-YYYY" Squarespace navigation block
    # in any of its variants:
    s = re.sub(r'<h[1-6][^>]*>\s*Reviews?\s*</h[1-6]>', '', s, flags=re.I)
    s = re.sub(r'<p[^>]*>\s*Reviews?\s*(?:&nbsp;)?\s*</p>', '', s, flags=re.I)
    # Empty paragraphs that often sit right after the Reviews header
    s = re.sub(r'<p[^>]*>\s*(?:&nbsp;|\xa0)?\s*</p>', '', s)
    # The whole <footer class="meta">…<span class="tags">…</span>…</footer> block
    s = re.sub(r'<footer[^>]*>.*?</footer>', '', s, flags=re.S | re.I)
    # Squarespace also emits a <header><div class="meta"><span class="date"><time>...</time></span></div></header>
    # at the top of imported show bodies with the post publication date. Strip it.
    s = re.sub(r'<header[^>]*>.*?</header>', '', s, flags=re.S | re.I)
    # And a standalone <time class="published"> tag (sometimes outside header)
    s = re.sub(r'<time[^>]*class="[^"]*published[^"]*"[^>]*>.*?</time>', '', s, flags=re.S | re.I)
    # Any standalone <span class="tags"> not in a footer
    s = re.sub(r'<span[^>]*class="[^"]*tags[^"]*"[^>]*>.*?</span>', '', s, flags=re.S | re.I)
    s = re.sub(r'<(?:p|div)[^>]*class="[^"]*tag[^"]*"[^>]*>.*?</(?:p|div)>', '', s, flags=re.S | re.I)
    # The trailing "sqs-html-content" wrapper that wraps the Reviews block
    s = re.sub(r'<div[^>]*class="[^"]*sqs-html-content[^"]*"[^>]*>\s*(?:<[^>]+>\s*)*</div>', '', s, flags=re.S)
    s = re.sub(r'Recommended\s+for\s+Ages\s+[0-9+]+', '', s)

    # Collapse multiple blank lines / empty <p>s
    s = re.sub(r'<p[^>]*>\s*</p>', '', s)
    s = re.sub(r'\n{3,}', '\n\n', s)

    return s.strip()


def set_meta(cur, pid, key, value):
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, key))
    if value != '':
        cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)", (pid, key, value))


FIXES = [
    {
        'slug':     'a-chorus-line-2019',
        'open':     '2020-03-06',
        'close':    '2020-03-29',  # poster scheduled close; actual run cut short by COVID
        'clean':    True,
    },
    {
        'slug':     'terms-of-endearment-2019',
        'director': 'Kathy Pingel',
        'open':     '2020-04-24',
        'close':    '2020-05-10',
        'clean':    True,
    },
    {
        'slug':     'manchurian-candidate',
        'open':     '2020-06-05',
        'close':    '2020-06-21',
        'clean':    False,  # don't reformat body — only fix dates
    },
]

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

for fix in FIXES:
    cur.execute("SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='tlt_show'", (fix['slug'],))
    r = cur.fetchone()
    if not r:
        print(f"  [skip] {fix['slug']}: not found")
        continue
    pid = r[0]

    set_meta(cur, pid, 'show_open_date',  fix['open'])
    set_meta(cur, pid, 'show_close_date', fix['close'])
    if 'director' in fix:
        set_meta(cur, pid, 'show_director', fix['director'])

    if fix.get('clean'):
        cur.execute("SELECT post_content FROM wp_posts WHERE ID=%s", (pid,))
        body = cur.fetchone()[0]
        new_body = clean_body(body)
        if new_body != body:
            cur.execute("UPDATE wp_posts SET post_content=%s WHERE ID=%s", (new_body, pid))
            print(f"  {fix['slug']:<35} dates={fix['open']} -> {fix['close']}, body cleaned ({len(body)} -> {len(new_body)} chars)")
        else:
            print(f"  {fix['slug']:<35} dates={fix['open']} -> {fix['close']}, body unchanged")
    else:
        print(f"  {fix['slug']:<35} dates={fix['open']} -> {fix['close']}")

c.commit()
c.close()
print("\nDone.")
