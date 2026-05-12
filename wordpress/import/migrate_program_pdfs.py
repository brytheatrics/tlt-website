"""
Match Squarespace /s/*.pdf links to TLT-SERVER program files.
Copy matched PDFs to wp-content/uploads/programs/ (using Squarespace filenames
so URL rewriting is trivial).
Rewrite post_content to point at /wp-content/uploads/programs/.
"""
import os, re, json, shutil, pymysql
from urllib.parse import urlparse, unquote

ROOT = os.path.dirname(os.path.abspath(__file__))
PROJECT = os.path.normpath(os.path.join(ROOT, "..", ".."))
SERVER = "//TLT-SERVER/TLT Programs"
DEST   = "C:/Users/blake/Local Sites/tlt/app/public/wp-content/uploads/programs"
os.makedirs(DEST, exist_ok=True)

# Build server file index by year prefix + normalized show name
def normalize(s):
    return re.sub(r'[^a-z0-9]', '', s.lower())

print("Indexing TLT-SERVER programs...")
server_files = []
for dirpath, dirnames, filenames in os.walk(SERVER):
    if '#recycle' in dirpath: continue
    for fn in filenames:
        if not fn.lower().endswith('.pdf'): continue
        full = os.path.join(dirpath, fn)
        # Filename pattern: "YYYY-YY[YY] Show Name.pdf" or "YYYY Show Name.pdf"
        m = re.match(r'^(\d{4})(?:-(\d{2,4}))?\s+(.+?)\.pdf$', fn, re.I)
        if not m: continue
        year_start = m.group(1)
        show = m.group(3)
        server_files.append({
            "path": full,
            "filename": fn,
            "year": year_start,
            "show_norm": normalize(show),
            "show": show,
        })

print(f"  Indexed {len(server_files)} server files")

# Build a quick lookup: (year, normalized_show) -> path
lookup = {}
for f in server_files:
    lookup.setdefault(f["year"], []).append(f)

def find_match(squarespace_filename):
    """Try to match a Squarespace filename to a server PDF.
    Squarespace pattern: "YYYY-YYYY-Show-Name.pdf" or "YYYY-Show-Name.pdf"
    """
    stem = re.sub(r'\.pdf$', '', squarespace_filename, flags=re.I)
    m = re.match(r'^(\d{4})[-_](\d{4})[-_](.+)$', stem)
    if m:
        year_start, year_end, show = m.groups()
    else:
        m = re.match(r'^(\d{4})[-_](.+)$', stem)
        if not m: return None
        year_start, show = m.group(1), m.group(2)

    show_norm = normalize(show)
    candidates = list(lookup.get(year_start, []))
    # Try also year_start - 1 for cases like "1936-East-Lynne" (programs sometimes filed under bridging year)
    candidates.extend(lookup.get(str(int(year_start) - 1), []))

    # Exact match on normalized show name
    for c in candidates:
        if c["show_norm"] == show_norm: return c
    # Substring/contains match
    for c in candidates:
        if c["show_norm"].startswith(show_norm) or show_norm.startswith(c["show_norm"]): return c
        if abs(len(c["show_norm"]) - len(show_norm)) < 5 and (show_norm in c["show_norm"] or c["show_norm"] in show_norm):
            return c
    return None

# Walk all WP posts/pages with /s/*.pdf links
c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()
cur.execute("SELECT ID, post_content FROM wp_posts WHERE post_status='publish' AND post_content LIKE '%/s/%pdf%'")
rows = cur.fetchall()
print(f"\nFound {len(rows)} posts with /s/*.pdf links")

unique_pdfs = set()
for pid, content in rows:
    for href in re.findall(r'href=["\']([^"\']+\.pdf)["\']', content, re.I):
        if href.startswith('/s/'):
            unique_pdfs.add(os.path.basename(unquote(href)))

print(f"Unique PDF references: {len(unique_pdfs)}")

# Try to match each
matched = {}
unmatched = []
for fn in sorted(unique_pdfs):
    match = find_match(fn)
    if match:
        matched[fn] = match
    else:
        unmatched.append(fn)

print(f"  Matched to server: {len(matched)}")
print(f"  Unmatched: {len(unmatched)}")

# Copy matched files to wp-content/uploads/programs/
print(f"\nCopying matched files to {DEST}...")
copied = 0
copy_errors = []
for sqs_name, server_file in matched.items():
    dest = os.path.join(DEST, sqs_name)
    if os.path.exists(dest):
        copied += 1
        continue
    try:
        shutil.copy2(server_file["path"], dest)
        copied += 1
    except Exception as e:
        copy_errors.append((sqs_name, str(e)))
print(f"  Copied {copied}")
if copy_errors:
    print(f"  Errors: {len(copy_errors)} (see below)")
    for n, e in copy_errors[:5]: print(f"    {n}: {e}")

# Rewrite post_content
print(f"\nRewriting post links...")
updates = 0
for pid, content in rows:
    new_content = content
    # For matched: /s/X.pdf -> /wp-content/uploads/programs/X.pdf
    new_content = re.sub(
        r'(href=["\'])/s/([^"\']+\.pdf)(["\'])',
        lambda m: f'{m.group(1)}/wp-content/uploads/programs/{m.group(2)}{m.group(3)}',
        new_content,
        flags=re.I
    )
    if new_content != content:
        cur.execute("UPDATE wp_posts SET post_content=%s WHERE ID=%s", (new_content, pid))
        updates += 1
c.commit()
print(f"  Updated {updates} posts")

# Save unmatched list for review
unmatched_path = os.path.join(PROJECT, "unmatched_pdfs.txt")
with open(unmatched_path, "w", encoding="utf-8") as f:
    f.write("\n".join(unmatched))
print(f"\nUnmatched PDF filenames saved to {unmatched_path}")
print(f"  ({len(unmatched)} files — these will 404 unless we manually find them)")
print()
print("Sample unmatched:")
for fn in unmatched[:10]: print(f"  {fn}")
c.close()
