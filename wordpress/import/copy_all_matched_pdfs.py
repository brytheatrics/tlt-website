"""
Read pdf_inventory.json (which has the matched filename -> server_match map
from the original migration audit) and copy every matched PDF to
/wp-content/uploads/programs/ with its website filename.

This populates the programs folder so the 1094 /s/*.pdf links the cleanup
agent rewrote stop 404'ing. Idempotent.
"""
import os, json, shutil

DEST_DIR = "C:/Users/blake/Local Sites/tlt/app/public/wp-content/uploads/programs"
INVENTORY = "C:/Users/blake/dev/TLT_Website/pdf_inventory.json"

with open(INVENTORY, 'r') as f:
    inv = json.load(f)

os.makedirs(DEST_DIR, exist_ok=True)

copied = 0
exists = 0
missing_source = 0

for entry in inv.get('website_pdfs', []):
    sm = entry.get('server_match')
    fn = entry.get('filename')
    if not sm or not fn:
        continue
    # Resolve to absolute server path
    src = f"//TLT-SERVER/{sm}"
    if not os.path.isfile(src):
        missing_source += 1
        continue
    dest = os.path.join(DEST_DIR, fn)
    if os.path.exists(dest) and os.path.getsize(dest) == os.path.getsize(src):
        exists += 1
        continue
    try:
        shutil.copy2(src, dest)
        copied += 1
    except Exception as e:
        print(f"  [error] {fn}: {e}")
        missing_source += 1

print(f"Copied:          {copied}")
print(f"Already existed: {exists}")
print(f"Source missing:  {missing_source}")
print(f"\nDestination now has {len(os.listdir(DEST_DIR))} files.")
