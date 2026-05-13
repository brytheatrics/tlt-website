"""
Manually-set approximate dates for the 11 shows where the body content didn't
include dates. Based on TLT's typical 7-show season schedule:
  Slot 1: mid Sept
  Slot 2: mid-late Oct
  Slot 3: early Dec
  Slot 4: late Jan / early Feb
  Slot 5: early-mid Mar
  Slot 6: late Apr / early May
  Slot 7: mid-late Jun

Dates here are approximate but in the correct order. Chris can correct exact
dates later in WP admin if needed.

Idempotent — only sets dates if currently empty.
"""
import pymysql

# slug -> (open_date, close_date) approximate based on TLT scheduling
DATES = {
    # ----- 2013-2014 season — slots 5-6 (Mar and Apr/May) — others already fixed
    'moonlight-and-magnolias': ('2014-03-07', '2014-03-23'),
    'weir':                    ('2014-04-25', '2014-05-11'),
    'chapter-two':             ('2014-07-11', '2014-07-27'),  # season-end summer slot

    # ----- 2014-2015 season — 97th, no dates anywhere in body content
    'a-midsummer-nights-dream': ('2014-09-12', '2014-09-28'),
    'picasso-at-the-lapin-agile': ('2014-10-17', '2014-11-02'),
    'scrooge':                  ('2014-12-05', '2014-12-28'),
    'dial-m-for-murder':        ('2015-01-23', '2015-02-08'),
    'great-gatsby':             ('2015-03-06', '2015-03-22'),
    'fox-on-the-fairway':       ('2015-04-24', '2015-05-10'),
    # Cabaret already fixed: 2015-05-22 - 2015-06-14

    # ----- 2019-2020 — Manchurian Candidate was postponed/cancelled by COVID
    'manchurian-candidate':     ('2020-04-24', '2020-05-10'),

    # ----- 2021-2022 — Wizard of Oz was scheduled but cancelled
    'wizard-of-oz':             ('2021-12-03', '2021-12-26'),
}

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

fixed = 0
for slug, (open_d, close_d) in DATES.items():
    cur.execute("SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='tlt_show' LIMIT 1", (slug,))
    r = cur.fetchone()
    if not r:
        print(f"  [skip] {slug}: not found")
        continue
    pid = r[0]
    # Only set if currently empty
    cur.execute("SELECT meta_value FROM wp_postmeta WHERE post_id=%s AND meta_key='show_open_date'", (pid,))
    existing = cur.fetchone()
    if existing and existing[0]:
        print(f"  [skip] {slug}: already has open_date '{existing[0]}'")
        continue
    cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)", (pid, 'show_open_date', open_d))
    cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)", (pid, 'show_close_date', close_d))
    print(f"  {slug:<35} -> open={open_d}, close={close_d}")
    fixed += 1

c.commit()
c.close()
print(f"\nDone. Fixed {fixed} shows with canonical approximate dates.")
print("Note: these are approximations of TLT's typical schedule slots. Chris can correct exact dates in WP admin.")
