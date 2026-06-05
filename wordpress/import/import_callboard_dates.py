"""
Import performance + audition dates for the 2026-2027 season from the Callboard
CSV export into each show's show_performances / show_audition_schedule meta
(which drive the /calendar/ page).

Only public-facing rows are imported:
  - Event Type == 'Performance'  -> show_performances     ("YYYY-MM-DD 7:30 PM")
  - Event Type == 'Auditions'    -> show_audition_schedule ("YYYY-MM-DD 7:00 PM @ Location")
Internal rows (rehearsals, tech, production meetings, design packets, etc.) are skipped.

Idempotent: overwrites the two meta fields per matched show.
"""
import csv, re, datetime, pymysql

CSV = 'C:/Users/blake/Downloads/26-27 Callboard_ Tacoma Little Theatre - Dates.csv'

# Auditions have no location column in the CSV. Auditions run Sun–Tue; the
# Tuesday session is always at STAR Center, the rest at TLT.
DEFAULT_AUDITION_LOCATION = 'Tacoma Little Theatre'
TUESDAY_AUDITION_LOCATION = 'STAR Center'

def norm_time(t):
    t = t.strip()
    m = re.match(r'^(\d{1,2}):(\d{2})(?::\d{2})?\s*([AaPp][Mm])$', t)
    if not m:
        return ''
    return f"{int(m.group(1))}:{m.group(2)} {m.group(3).upper()}"

def norm_date(d):
    try:
        return datetime.datetime.strptime(d.strip(), '%m/%d/%Y').strftime('%Y-%m-%d')
    except ValueError:
        return ''

def main():
    rows = list(csv.DictReader(open(CSV, encoding='utf-8-sig', newline='')))
    perf = {}   # show -> [lines]
    aud  = {}   # show -> [lines]
    for r in rows:
        show = r['Show'].strip()
        if not show:
            continue
        etype = r['Event Type'].strip()
        date  = norm_date(r['Start Date'])
        time  = norm_time(r['Start Time'])
        if not date:
            continue
        if etype == 'Performance':
            perf.setdefault(show, []).append(f"{date} {time}".strip())
        elif etype == 'Auditions':
            weekday = datetime.datetime.strptime(date, '%Y-%m-%d').strftime('%A')
            loc = TUESDAY_AUDITION_LOCATION if weekday == 'Tuesday' else DEFAULT_AUDITION_LOCATION
            aud.setdefault(show, []).append(f"{date} {time} @ {loc}".strip())

    c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
    cur = c.cursor(pymysql.cursors.DictCursor)
    # Map CSV show name -> tlt_show ID by case-insensitive title. Restrict to the
    # 2026-2027 season so revivals (same title in an older season, e.g. The Play
    # That Goes Wrong) don't steal the match.
    cur.execute("""SELECT p.ID, p.post_title FROM wp_posts p
        JOIN wp_term_relationships tr ON tr.object_id=p.ID
        JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id AND tt.taxonomy='tlt_season'
        JOIN wp_terms t ON t.term_id=tt.term_id AND t.name='2026-2027'
        WHERE p.post_type='tlt_show' AND p.post_status='publish'""")
    by_title = { r['post_title'].strip().lower(): r['ID'] for r in cur.fetchall() }

    def set_meta(pid, key, value):
        cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, key))
        cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)", (pid, key, value))

    print(f"{'Show':<36} {'id':>5}  perf  aud")
    for show in sorted(set(perf) | set(aud)):
        pid = by_title.get(show.lower())
        if not pid:
            print(f"  !! NO MATCH for '{show}'")
            continue
        set_meta(pid, 'show_performances', '\n'.join(perf.get(show, [])))
        set_meta(pid, 'show_audition_schedule', '\n'.join(aud.get(show, [])))
        print(f"{show:<36} {pid:>5}  {len(perf.get(show, [])):>4}  {len(aud.get(show, [])):>3}")
    c.commit()
    c.close()

if __name__ == '__main__':
    main()
