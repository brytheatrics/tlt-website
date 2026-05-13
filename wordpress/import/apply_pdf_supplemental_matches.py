"""
Apply the 10 high-confidence supplemental PDF matches from the audit pass.
These are old-show programs (1927, 1944, 1964, etc.) where the audit script
found a >=88-score match on TLT-SERVER.

Copies the matched server file into wp-content/uploads/programs/ using the
website's expected filename. Idempotent.
"""
import os, shutil, json

SUPPLEMENTAL = "C:/Users/blake/dev/TLT_Website/wordpress/import/pdf_supplemental_matches.json"
DEST_DIR = "C:/Users/blake/Local Sites/tlt/app/public/wp-content/uploads/programs"

with open(SUPPLEMENTAL, 'r') as f:
    data = json.load(f)
matches = data.get('matches', data) if isinstance(data, dict) else data

os.makedirs(DEST_DIR, exist_ok=True)

count_copied = 0
count_exists = 0
count_missing_source = 0

for entry in matches:
    website_fn = entry['website_filename']
    server_rel = entry['server_path']  # relative path inside TLT Programs
    # The audit script may have stored a relative path; resolve to absolute
    if not server_rel.startswith('//') and not server_rel.startswith('\\\\'):
        server_abs = f"//TLT-SERVER/{server_rel}" if not server_rel.startswith('TLT') else f"//TLT-SERVER/{server_rel}"
    else:
        server_abs = server_rel

    if not os.path.isfile(server_abs):
        # Try alternative path constructions
        # The audit stored e.g. "TLT Programs/1925 -1939-40/1927-1928 The Intimate Strangers.pdf"
        alt = f"//TLT-SERVER/{server_rel}"
        if os.path.isfile(alt):
            server_abs = alt
        else:
            print(f"  [missing source] {website_fn} -> {server_rel}")
            count_missing_source += 1
            continue

    dest = os.path.join(DEST_DIR, website_fn)
    if os.path.exists(dest) and os.path.getsize(dest) == os.path.getsize(server_abs):
        action = 'exists'
        count_exists += 1
    else:
        shutil.copy2(server_abs, dest)
        action = 'copied'
        count_copied += 1
    print(f"  [{action}] {website_fn:<55} <- {server_abs.replace('//TLT-SERVER/', '')}")

print(f"\nDone. Copied: {count_copied}, already existed: {count_exists}, source missing: {count_missing_source}")
