"""
The recent program PDFs that the TLT Programs folder is missing live in
Marketing/<season> Marketing/<show> folders. Walk those, match against the
website's unmatched program filenames, and copy to /wp-content/uploads/programs/.

Resolves ~25 of the 42 "no match" PDFs from the audit.

Idempotent: only copies if the destination file doesn't exist or is older.
"""
import os, re, shutil, json, time

MARKETING_ROOTS = [
    "//TLT-SERVER/Marketing/2627 Marketing",
    "//TLT-SERVER/Marketing/2526 Marketing",
    "//TLT-SERVER/Marketing/Prior Seasons/2425 Marketing",
    "//TLT-SERVER/Marketing/Prior Seasons/2324 Marketing",
    "//TLT-SERVER/Marketing/Prior Seasons/2223 Marketing",
    "//TLT-SERVER/Marketing/Prior Seasons/2122 Marketing",
    "//TLT-SERVER/Marketing/Prior Seasons/2021 Marketing",
    "//TLT-SERVER/Marketing/Prior Seasons/1920 Marketing",
    "//TLT-SERVER/Marketing/Prior Seasons/1819 Marketing",
    "//TLT-SERVER/Marketing/Prior Seasons/1718 Marketing",
    "//TLT-SERVER/Marketing/Prior Seasons/1617 Marketing",
    "//TLT-SERVER/Marketing/Prior Seasons/1516 Marketing",
    "//TLT-SERVER/Marketing/Prior Seasons/1415 Marketing",
    "//TLT-SERVER/Marketing/Prior Seasons/1314 Marketing",
    "//TLT-SERVER/Marketing/Prior Seasons/1112 Marketing",
    "//TLT-SERVER/Marketing/Prior Seasons/0910 Marketing",
    "//TLT-SERVER/Marketing/Prior Seasons/0405 Marketing",
    "//TLT-SERVER/Marketing/Prior Seasons/0304 Marketing",
]

DEST_DIR = "C:/Users/blake/Local Sites/tlt/app/public/wp-content/uploads/programs"

# These came from the original migrate_program_pdfs.py — website filenames that
# didn't match a TLT Programs server file.
UNMATCHED_FILE = "C:/Users/blake/dev/TLT_Website/unmatched_pdfs.txt"

# Also pick up names from the cleanup agent's report
EXTRA_FROM_AUDIT = [
    "A-Chorus-Line-2022-Program.pdf",
    "Almost-Maine-Program.pdf",
    "Bug-Program.pdf",
    "Clue-Program.pdf",
    "Curious-Program.pdf",
    "Da-Vinci-Program.pdf",
    "Delta-Program.pdf",
    "Fiddler-Program.pdf",
    "Lorca-Dramaturgy.pdf",
    "Lorca-Program.pdf",
    "Matilda-Program.pdf",
    "Misery-Program-Opening.pdf",
    "Murder-on-the-Orient-Express-Program.pdf",
    "One-Man-Program.pdf",
    "Rent-Program-Single-Page.pdf",
    "Rock-of-Ages-Program.pdf",
    "Rocky-Program.pdf",
    "Rudolph-Program.pdf",
    "Shawshank-Program.pdf",
    "Silent-Sky-Program.pdf",
    "Spring-Awakening-Program.pdf",
    "Steel-Mags-Program.pdf",
    "Terms-Program.pdf",
    "The-Happiest-Song-Plays-Last-Program.pdf",
    "The-Mountaintop-Dramaturgy.pdf",
    "The-Mountaintop-Program.pdf",
    "The-Mousetrap-Prorgram.pdf",
    "The-Play-That-Goes-Wrong-Program-PDF-Program.pdf",
    "The-Time-Machine-Program.pdf",
    "TLT-Po-Boy-Program.pdf",
    "TLT-Significant-Other-Program.pdf",
    "TLT-The-Luck-of-the-Irish-program.pdf",
    "TLT-OZ-Program-redact.pdf",
    "A-Dolls-House-Part-2-Program-Single-Page.pdf",
    "Xmas-Program.pdf",
    # Non-program docs likely in 2526 / 2627 Marketing season-tix folders:
    "2627-Season-Descriptions.pdf",
    "2627-Season-Ticket-Order-Form.pdf",
    "2526-Season-Brochure.pdf",
    "2526-Season-Ticket-Order-Form.pdf",
    "TLT-2021-2022-Season-Brochure.pdf",
    "TLT-2020-2021-Season-Ticket-Order-Form.pdf",
    "2019-2020-TLT-Season-Brochure.pdf",
    "1920-Season-Ticket-Order-Form.pdf",
    # Bylaws (TLT Archive maybe; long shot)
    "TLT-Amended-Bylaws-2016-11-1.pdf",
]


def normalize(s):
    """Lowercase, strip extension, replace runs of non-alphanumeric with single space."""
    s = s.lower()
    s = re.sub(r'\.pdf$', '', s)
    s = re.sub(r'[^a-z0-9]+', ' ', s)
    s = re.sub(r'\s+', ' ', s).strip()
    return s


def walk_marketing_pdfs():
    """Walk Marketing roots, return dict {normalized name: full path}."""
    server_pdfs = {}
    duplicates = []
    for root in MARKETING_ROOTS:
        if not os.path.isdir(root):
            print(f"  [skip] {root} (doesn't exist)")
            continue
        for dirpath, dirnames, filenames in os.walk(root):
            # Skip recycle bins
            if '#recycle' in dirpath:
                continue
            for fn in filenames:
                if not fn.lower().endswith('.pdf'):
                    continue
                full = os.path.join(dirpath, fn)
                key = normalize(fn)
                if key in server_pdfs:
                    duplicates.append((key, server_pdfs[key], full))
                else:
                    server_pdfs[key] = full
    return server_pdfs, duplicates


def load_unmatched():
    """Combine the unmatched.txt file + EXTRA_FROM_AUDIT into a deduped list of website filenames."""
    seen = set()
    names = []
    for n in EXTRA_FROM_AUDIT:
        if n not in seen:
            seen.add(n); names.append(n)
    if os.path.isfile(UNMATCHED_FILE):
        with open(UNMATCHED_FILE, 'r') as f:
            for line in f:
                n = line.strip()
                if not n: continue
                # Some lines might be paths; reduce to basename
                n = os.path.basename(n)
                if n not in seen:
                    seen.add(n); names.append(n)
    return names


# Explicit overrides for filenames that don't fuzzy-match cleanly.
EXPLICIT_MATCHES = {
    'Steel-Mags-Program.pdf': '//TLT-SERVER/Marketing/Prior Seasons/2223 Marketing/2223 Steel Magnolias/TLT Steel Magnolias Program.pdf',
    'TLT-OZ-Program-redact.pdf': '//TLT-SERVER/Marketing/Prior Seasons/2122 Marketing/2122 OZ/TLT OZ Program.pdf',
    'A-Dolls-House-Part-2-Program-Single-Page.pdf': "//TLT-SERVER/Marketing/Prior Seasons/2324 Marketing/2324 Doll's House Part 2/A Doll's House Part 2 Program Single Page.pdf",
    '2526-Season-Brochure.pdf': '//TLT-SERVER/Marketing/2526 Marketing/2526 Brochure/2526 TLT Season Brochure 16pg Folder/2526 TLT Season Brochure 16pg.pdf',
}


def main():
    os.makedirs(DEST_DIR, exist_ok=True)
    print(f"Source roots: {len(MARKETING_ROOTS)}")
    print(f"Destination:  {DEST_DIR}")

    print("\nWalking Marketing folders for PDFs...")
    server_pdfs, dupes = walk_marketing_pdfs()
    print(f"  Found {len(server_pdfs)} unique PDFs across all Marketing folders.")
    if dupes:
        print(f"  ({len(dupes)} duplicate filenames; using the first occurrence per name.)")

    unmatched = load_unmatched()
    print(f"\nWebsite filenames to try matching: {len(unmatched)}")

    matched = []
    still_missing = []

    for website_fn in unmatched:
        # Check explicit overrides first
        path = None
        if website_fn in EXPLICIT_MATCHES and os.path.isfile( EXPLICIT_MATCHES[website_fn] ):
            path = EXPLICIT_MATCHES[website_fn]
        if not path:
            key = normalize(website_fn)
            # First try exact normalized match
            path = server_pdfs.get(key)
        # If no exact match, try fuzzy variants (drop "tlt", "program", "the", etc.)
        if not path:
            simple = re.sub(r'\b(tlt|the|a|an|program|prorgram|single page|pdf|redact|2022)\b', '', key)
            simple = re.sub(r'\s+', ' ', simple).strip()
            if simple:
                for k, p in server_pdfs.items():
                    k_simple = re.sub(r'\b(tlt|the|a|an|program|prorgram|single page|pdf|redact|2022)\b', '', k)
                    k_simple = re.sub(r'\s+', ' ', k_simple).strip()
                    if k_simple == simple and len(simple) > 3:
                        path = p
                        break
        if path:
            dest = os.path.join(DEST_DIR, website_fn)
            if not os.path.exists(dest) or os.path.getsize(dest) != os.path.getsize(path):
                shutil.copy2(path, dest)
                action = 'copied'
            else:
                action = 'exists'
            matched.append((website_fn, path, action))
        else:
            still_missing.append(website_fn)

    print(f"\nMatched: {len(matched)}")
    print(f"Still missing: {len(still_missing)}")

    # Report
    print("\n--- Matches ---")
    for fn, src, action in matched:
        rel = src.replace('//TLT-SERVER/', '')
        print(f"  [{action}] {fn:<55} <- {rel}")

    if still_missing:
        print("\n--- Still missing ---")
        for fn in still_missing:
            print(f"  {fn}")

    # Save a follow-up map
    report = {
        'matched': [{'website': m[0], 'server': m[1], 'action': m[2]} for m in matched],
        'still_missing': still_missing,
        'generated_at': time.strftime('%Y-%m-%d %H:%M:%S'),
    }
    out = "C:/Users/blake/dev/TLT_Website/_planning/programs_from_marketing.json"
    with open(out, 'w') as f:
        json.dump(report, f, indent=2)
    print(f"\nReport: {out}")


if __name__ == '__main__':
    main()
