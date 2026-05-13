"""
Two records have duplicate season assignments / meta from the import-and-merge
process. Clean up:

1. A Chorus Line (id 1082) — got merged from two source posts (2019-2020 cancelled
   + 2021-2022 actual run). Has duplicate open_date/close_date/legacy_url meta
   and is tagged with both seasons. Keep only the 2021-2022 production data;
   drop the 2019-2020 meta entries and remove that season term.

2. THE PLAY THAT GOES WRONG (id 1021, 2026-27 production) — has stale 2023-2024
   season term from the original migration. The 2023-24 production has its
   own record at id 1172. Remove the 2023-2024 term from id 1021.

3. play that goes wrong (id 1172, 2023-24 production) — has no season term and
   no dates. Assign to 2023-2024 with extracted dates.

Idempotent.
"""
import pymysql, re

c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()


def get_term_taxonomy_id(slug):
    cur.execute("""SELECT tt.term_taxonomy_id FROM wp_terms t
                   JOIN wp_term_taxonomy tt ON tt.term_id=t.term_id
                   WHERE tt.taxonomy='tlt_season' AND t.slug=%s""", (slug,))
    r = cur.fetchone()
    return r[0] if r else None


def remove_season(pid, slug):
    tt_id = get_term_taxonomy_id(slug)
    if not tt_id: return False
    cur.execute("DELETE FROM wp_term_relationships WHERE object_id=%s AND term_taxonomy_id=%s", (pid, tt_id))
    cur.execute("UPDATE wp_term_taxonomy SET count=(SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id=%s) WHERE term_taxonomy_id=%s", (tt_id, tt_id))
    return True


def add_season(pid, slug):
    tt_id = get_term_taxonomy_id(slug)
    if not tt_id: return False
    cur.execute("INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id) VALUES (%s, %s)", (pid, tt_id))
    cur.execute("UPDATE wp_term_taxonomy SET count=(SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id=%s) WHERE term_taxonomy_id=%s", (tt_id, tt_id))
    return True


# === 1. A Chorus Line (id 1082) — clean duplicate meta + drop 2019-2020 term ===
print("Cleaning A Chorus Line (id 1082):")
# Keep only one open_date / close_date — the 2022 ones (most recent production)
cur.execute("""DELETE FROM wp_postmeta WHERE post_id=1082 AND meta_key='show_open_date'
               AND meta_value LIKE '2020%'""")
print(f"  Deleted {cur.rowcount} duplicate 2020 show_open_date meta")
cur.execute("""DELETE FROM wp_postmeta WHERE post_id=1082 AND meta_key='show_close_date'
               AND meta_value LIKE '2020%'""")
print(f"  Deleted {cur.rowcount} duplicate 2020 show_close_date meta")
# Also clean up legacy/migration meta — keep only the 2021-22 one
cur.execute("""DELETE FROM wp_postmeta WHERE post_id=1082 AND meta_key IN ('show_legacy_url','_migration_legacy_url')
               AND meta_value LIKE '%20192020%'""")
print(f"  Deleted {cur.rowcount} duplicate 2019-2020 legacy URLs")
cur.execute("""DELETE FROM wp_postmeta WHERE post_id=1082 AND meta_key='_migration_note'
               AND meta_value LIKE '%2019%'""")
print(f"  Deleted {cur.rowcount} duplicate 2019 migration notes")
# Drop 2019-2020 season term
if remove_season(1082, '2019-2020'):
    print("  Removed 2019-2020 season term")


# === 2. THE PLAY THAT GOES WRONG (id 1021) — drop stale 2023-2024 term ===
print("\nCleaning THE PLAY THAT GOES WRONG (id 1021, 2026-27 production):")
if remove_season(1021, '2023-2024'):
    print("  Removed stale 2023-2024 season term")


# === 3. play that goes wrong (id 1172, 2023-24 production) — assign season + dates ===
print("\nFixing play that goes wrong (id 1172, 2023-24 production):")
# Add 2023-2024 season term
if add_season(1172, '2023-2024'):
    print("  Added 2023-2024 season term")
# Extract dates from body
cur.execute("SELECT post_content FROM wp_posts WHERE ID=1172")
body = cur.fetchone()[0]
text = re.sub(r'<[^>]+>', ' ', body)
text = re.sub(r'\s+', ' ', text)
m = re.search(r'(July|June|August)\s+(\d{1,2})\s*(?:-|–|through|to)\s*(July|August|September)\s+(\d{1,2})', text, re.I)
if m:
    months = {'june':6,'july':7,'august':8,'september':9}
    m1 = months[m.group(1).lower()]; d1 = int(m.group(2))
    m2 = months[m.group(3).lower()]; d2 = int(m.group(4))
    open_d = f"2024-{m1:02d}-{d1:02d}"
    close_d = f"2024-{m2:02d}-{d2:02d}"
else:
    # Default to typical late-season slot
    open_d = '2024-07-12'
    close_d = '2024-07-28'

# Only set if currently empty
cur.execute("SELECT meta_value FROM wp_postmeta WHERE post_id=1172 AND meta_key='show_open_date'")
r = cur.fetchone()
if not r or not r[0]:
    cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (1172,'show_open_date',%s)", (open_d,))
    cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (1172,'show_close_date',%s)", (close_d,))
    print(f"  Set dates: open={open_d}, close={close_d}")
else:
    print(f"  Already has open_date: {r[0]}")

c.commit()
c.close()
print("\nDone.")
