"""
Audit unmatched program PDFs from the prior migration.

Reads pdf_inventory.json (the 554 website PDFs with prior server_match results)
and tlt_server_inventory.json (TLT Programs folder, 549 files), then runs
fuzzier matching against the unmatched website PDFs.

Outputs:
  _planning/pdf_audit_report.md
  wordpress/import/pdf_supplemental_matches.json (only high-confidence new matches)

Read-only — DOES NOT touch the WordPress database. Idempotent.
"""
import json
import os
import re
from rapidfuzz import fuzz, process

ROOT = os.path.dirname(os.path.abspath(__file__))
PROJECT = os.path.normpath(os.path.join(ROOT, "..", ".."))

PDF_INVENTORY = os.path.join(PROJECT, "pdf_inventory.json")
SERVER_INVENTORY = os.path.join(PROJECT, "tlt_server_inventory.json")
REPORT_PATH = os.path.join(PROJECT, "_planning", "pdf_audit_report.md")
SUPPLEMENTAL_PATH = os.path.join(ROOT, "pdf_supplemental_matches.json")

# Confidence thresholds
HIGH_CONFIDENCE = 88  # auto-match (rapidfuzz token_set_ratio)
REVIEW = 70           # worth eyeballing

STOPWORDS = {"the", "a", "an", "of", "and", "to", "program", "tlt", "pdf",
             "single", "page", "dramaturgy", "redact", "opening"}

# Map blog URL season tokens to year-prefix candidates used in server filenames.
# Server filenames typically look like "YYYY-YYYY <Show>.pdf" (e.g. "2021-2022 Clue.pdf")
# or "YYYY-YY <Show>.pdf" or sometimes year-pair compressed like "1819" (rare).
SEASON_TOKEN_TO_YEARS = {
    "20212022": ("2021", "2022"),
    "20222023": ("2022", "2023"),
    "20232024": ("2023", "2024"),
    "20242025": ("2024", "2025"),
    "20252026": ("2025", "2026"),
    "20182019": ("2018", "2019"),
    "20192020": ("2019", "2020"),
    "20202021": ("2020", "2021"),
}


def norm_text(s):
    """Lowercase, drop punctuation, drop stopwords, collapse whitespace."""
    s = s.lower()
    s = re.sub(r"\.pdf$", "", s)
    s = re.sub(r"[^a-z0-9\s]", " ", s)
    tokens = [t for t in s.split() if t and t not in STOPWORDS]
    return " ".join(tokens)


def strip_slug_suffix(stem):
    """Strip Squarespace 4-6 char random suffixes like '-7xkg', '-2lgn'.

    Squarespace suffixes are always lowercase and mix letters with digits
    (or are all-digit/all-lowercase-letter sequences that look random).
    To avoid stripping real words like '-Fancy' or '-Earnest', require the
    suffix to be lowercase AND contain at least one digit.
    """
    # Case-sensitive: real English words in these filenames are TitleCase
    # or UPPERCASE (e.g. "Fancy", "EARNEST"). Squarespace suffixes are always
    # all-lowercase alphanumeric.
    return re.sub(r"-[a-z0-9]{4,6}$", "", stem)


def extract_season_from_ref(first_ref):
    """Pull season token like '20212022' from a path like 'pages_shows/blog__20212022__clue.html'."""
    if not first_ref:
        return None
    path = first_ref.get("from", "")
    m = re.search(r"blog__(\d{8})__", path)
    if m:
        return m.group(1)
    return None


def filename_year_prefix(stem):
    """Pull a leading year prefix like '1951-1952' or '1969-1970' or '1927-1928'."""
    m = re.match(r"^(\d{4})[-_](\d{4})[-_]", stem)
    if m:
        return m.group(1), m.group(2)
    m = re.match(r"^(\d{4})[-_]", stem)
    if m:
        return m.group(1), None
    return None, None


def load_server_index(server_inv):
    """Return list of {path, basename, stem, norm_stem, year_start, year_end}."""
    files = server_inv["TLT Programs"]["files"]
    out = []
    for f in files:
        p = f["path"]
        if not p.lower().endswith(".pdf"):
            continue
        base = os.path.basename(p)
        stem = re.sub(r"\.pdf$", "", base, flags=re.I)
        # Server pattern: "YYYY-YYYY Show.pdf" or "YYYY-YY Show.pdf" or "YYYY Show.pdf"
        ys, ye = None, None
        m = re.match(r"^(\d{4})\s*-\s*(\d{2,4})\s+(.+)$", stem)
        if m:
            ys, ye_raw, show = m.group(1), m.group(2), m.group(3)
            ye = ye_raw if len(ye_raw) == 4 else (ys[:2] + ye_raw)
        else:
            m = re.match(r"^(\d{4})\s+(.+)$", stem)
            if m:
                ys, show = m.group(1), m.group(2)
            else:
                show = stem
        out.append({
            "path": p,
            "basename": base,
            "stem": stem,
            "show": show,
            "norm_show": norm_text(show),
            "norm_stem": norm_text(stem),
            "year_start": ys,
            "year_end": ye,
        })
    return out


def best_match(website_pdf, server_index):
    """Return (best_server_file, score, strategy) or (None, 0, 'no match')."""
    fn = website_pdf["filename"]
    stem = re.sub(r"\.pdf$", "", fn, flags=re.I)
    stem_no_suffix = strip_slug_suffix(stem)

    # Build candidate pool
    candidates = list(server_index)

    # 1) Season constraint from referring blog page
    season = extract_season_from_ref(website_pdf.get("first_ref"))
    season_years = SEASON_TOKEN_TO_YEARS.get(season)
    season_constraint_satisfied = False
    if season_years:
        ys, ye = season_years
        # Server filenames sometimes use the compressed 4-char season "YYZZ"
        # (e.g. "1920 A Chorus Line.pdf" = 2019-2020 season). Match either form.
        compressed = ys[-2:] + ye[-2:]
        season_candidates = [c for c in candidates
                             if c["year_start"] in (ys, ye, compressed)
                             or c["year_end"] in (ys, ye, compressed)]
        if season_candidates:
            candidates = season_candidates
            season_constraint_satisfied = True

    # 2) Year prefix in filename
    fys, fye = filename_year_prefix(stem_no_suffix)
    if fys and not season_years:
        year_candidates = [c for c in candidates if c["year_start"] == fys
                           or c["year_start"] == str(int(fys) - 1)
                           or c["year_start"] == str(int(fys) + 1)]
        if year_candidates:
            candidates = year_candidates

    # Normalize the website stem (drop year prefix + slug suffix for matching)
    show_part = re.sub(r"^\d{4}[-_](\d{4}[-_])?", "", stem_no_suffix)
    query = norm_text(show_part)

    if not query or not candidates:
        return (None, 0, "no candidates")

    # Score against norm_show (the show portion only)
    scored = []
    for c in candidates:
        s_show = fuzz.token_set_ratio(query, c["norm_show"])
        s_stem = fuzz.token_set_ratio(query, c["norm_stem"])
        score = max(s_show, s_stem)
        scored.append((score, c))
    scored.sort(key=lambda x: -x[0])
    best_score, best = scored[0]

    if season_years and season_constraint_satisfied:
        strategy = "season+token"
    elif season_years and not season_constraint_satisfied:
        # We had a season but no server file matches that season — likely
        # a same-name but wrong-era match (e.g. 1972 Mousetrap for a 2024 page).
        # Demote the score so it lands in "review" rather than "match".
        strategy = "token-only (no server file for show's season)"
        best_score = min(best_score, REVIEW + 5)  # cap below HIGH_CONFIDENCE
    elif fys:
        strategy = "year+token"
    else:
        strategy = "token"
    return (best, best_score, strategy)


def main():
    with open(PDF_INVENTORY, "r", encoding="utf-8") as f:
        pdf_inv = json.load(f)
    with open(SERVER_INVENTORY, "r", encoding="utf-8") as f:
        server_inv = json.load(f)

    website_pdfs = pdf_inv["website_pdfs"]
    unmatched = [p for p in website_pdfs if not p.get("server_match")]
    matched_prior = [p for p in website_pdfs if p.get("server_match")]
    print(f"Website PDFs total: {len(website_pdfs)}")
    print(f"Previously matched: {len(matched_prior)}")
    print(f"Previously unmatched: {len(unmatched)}")

    server_index = load_server_index(server_inv)
    print(f"Server program files indexed: {len(server_index)}")

    # Build a set of server paths already used by prior matches
    prior_matched_paths = {p["server_match"] for p in matched_prior if p.get("server_match")}

    high = []
    review = []
    nomatch = []
    rows = []  # (website_pdf, best, score, strategy, bucket)

    for p in sorted(unmatched, key=lambda x: x["filename"].lower()):
        best, score, strat = best_match(p, server_index)
        if best is None:
            bucket = "no match"
            nomatch.append(p)
        elif score >= HIGH_CONFIDENCE:
            bucket = "match"
            high.append((p, best, score, strat))
        elif score >= REVIEW:
            bucket = "review"
            review.append((p, best, score, strat))
        else:
            bucket = "no match"
            nomatch.append(p)
        rows.append((p, best, score, strat, bucket))

    # Bonus: server PDFs not referenced by website
    server_used = set()
    for p in matched_prior:
        sm = p.get("server_match")
        if sm:
            server_used.add(sm)
    # Also count new high-confidence matches
    for p, best, score, strat in high:
        server_used.add(best["path"].replace("\\", "/"))

    # Normalize comparison (server inventory uses forward-slash already)
    server_not_on_website = []
    for c in server_index:
        normalized = c["path"].replace("\\", "/")
        if normalized in server_used:
            continue
        # Filter out obvious non-show files
        if not c["year_start"]:
            continue
        server_not_on_website.append(c)

    # ---- Write supplemental matches JSON (high-confidence only) ----
    supplemental = {
        "generated_by": "wordpress/import/audit_program_pdfs.py",
        "threshold_score": HIGH_CONFIDENCE,
        "matches": [
            {
                "website_filename": p["filename"],
                "website_url": p["url"],
                "server_path": best["path"].replace("\\", "/"),
                "server_basename": best["basename"],
                "similarity": score,
                "strategy": strat,
            }
            for (p, best, score, strat) in high
        ],
    }
    os.makedirs(os.path.dirname(SUPPLEMENTAL_PATH), exist_ok=True)
    with open(SUPPLEMENTAL_PATH, "w", encoding="utf-8") as f:
        json.dump(supplemental, f, indent=2)
        f.write("\n")

    # ---- Write Markdown report ----
    os.makedirs(os.path.dirname(REPORT_PATH), exist_ok=True)
    lines = []
    lines.append("# Program PDF audit report")
    lines.append("")
    lines.append(f"_Generated by `wordpress/import/audit_program_pdfs.py` (idempotent)._")
    lines.append("")
    lines.append("## Summary")
    lines.append("")
    lines.append(f"- Website PDFs total: **{len(website_pdfs)}**")
    lines.append(f"- Matched by prior migration: **{len(matched_prior)}**")
    lines.append(f"- Unmatched going into this audit: **{len(unmatched)}**")
    lines.append(f"- New high-confidence matches (score >= {HIGH_CONFIDENCE}): **{len(high)}**")
    lines.append(f"- Worth manual review (score {REVIEW}-{HIGH_CONFIDENCE - 1}): **{len(review)}**")
    lines.append(f"- Still no match (score < {REVIEW} or no candidates): **{len(nomatch)}**")
    lines.append("")
    lines.append("Final coverage after applying high-confidence matches: "
                 f"**{len(matched_prior) + len(high)} / {len(website_pdfs)}** "
                 f"({100.0 * (len(matched_prior) + len(high)) / len(website_pdfs):.1f}%)")
    lines.append("")
    lines.append("## High-confidence new matches")
    lines.append("")
    lines.append("| Website filename | Server file | Score | Strategy |")
    lines.append("|---|---|---|---|")
    for p, best, score, strat in sorted(high, key=lambda x: -x[2]):
        lines.append(f"| `{p['filename']}` | `{best['path']}` | {score:.0f} | {strat} |")
    if not high:
        lines.append("| _(none)_ | | | |")
    lines.append("")

    lines.append("## Review (worth checking)")
    lines.append("")
    lines.append("| Website filename | First-ref source | Best candidate | Score | Strategy |")
    lines.append("|---|---|---|---|---|")
    for p, best, score, strat in sorted(review, key=lambda x: -x[2]):
        src = (p.get("first_ref") or {}).get("from", "")
        lines.append(f"| `{p['filename']}` | `{src}` | `{best['basename'] if best else '-'}` | {score:.0f} | {strat} |")
    if not review:
        lines.append("| _(none)_ | | | | |")
    lines.append("")

    lines.append("## No match")
    lines.append("")
    lines.append("These appear to be non-program documents (bylaws, season brochures, order forms, enrollment forms, audition material, etc.) or programs that genuinely don't exist on the server.")
    lines.append("")
    lines.append("| Website filename | First-ref source | Best candidate (low score) | Score |")
    lines.append("|---|---|---|---|")
    # Rebuild nomatch with score from rows
    nomatch_rows = [(p, b, s, strat) for (p, b, s, strat, bucket) in rows if bucket == "no match"]
    for p, best, score, strat in sorted(nomatch_rows, key=lambda x: x[0]["filename"].lower()):
        src = (p.get("first_ref") or {}).get("from", "")
        best_str = f"`{best['basename']}`" if best else "_(no candidate)_"
        lines.append(f"| `{p['filename']}` | `{src}` | {best_str} | {score:.0f} |")
    lines.append("")

    lines.append("## Bonus: server PDFs not linked from any website show")
    lines.append("")
    lines.append(f"Server programs not referenced by any website show page (after applying high-confidence matches): **{len(server_not_on_website)}** files.")
    lines.append("")
    lines.append("These might be candidates for the prior-seasons archive page. Listing first 60 by year:")
    lines.append("")
    lines.append("| Server path | Year |")
    lines.append("|---|---|")
    for c in sorted(server_not_on_website, key=lambda x: (x["year_start"] or "", x["basename"]))[:60]:
        lines.append(f"| `{c['path']}` | {c['year_start']} |")
    if len(server_not_on_website) > 60:
        lines.append(f"| _(... and {len(server_not_on_website) - 60} more — see supplemental JSON for full list if needed)_ | |")
    lines.append("")

    with open(REPORT_PATH, "w", encoding="utf-8") as f:
        f.write("\n".join(lines))
        f.write("\n")

    print()
    print(f"New high-confidence matches: {len(high)}")
    print(f"Review:                       {len(review)}")
    print(f"No match:                     {len(nomatch)}")
    print(f"Server PDFs not on website:   {len(server_not_on_website)}")
    print()
    print(f"Report:       {REPORT_PATH}")
    print(f"Supplemental: {SUPPLEMENTAL_PATH}")


if __name__ == "__main__":
    main()
