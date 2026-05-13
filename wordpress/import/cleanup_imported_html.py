"""Cleanup pass over wp_posts.post_content for migrated Squarespace pages.

Three passes:

  1. Strip Squarespace wrapper markup (layout/row/block divs, embed wrappers,
     POST HEADER/BODY/FOOTER comments). Preserves inner content.
  2. Replace stray U+FFFD replacement characters with U+2019 (right single
     quote) — that covers >90% of the cases seen in practice (possessives
     and contractions).
  3. Rewrite Squarespace `/s/<file>.pdf` links to
     `/wp-content/uploads/programs/<file>.pdf`, skipping any filename in the
     project's `unmatched_pdfs.txt` list (those don't have a destination
     and will be logged for manual follow-up).

Idempotent: running twice is a no-op. Pass --dry-run to preview changes.

Backup of wp_posts is taken (as INSERT statements via pymysql) to
`_snapshots/wp_posts_before_cleanup.sql` before any real write.
"""
import argparse
import os
import re
import sys
import time
from collections import Counter

import pymysql
from bs4 import BeautifulSoup

# ---------------------------------------------------------------------------
# Paths

ROOT     = os.path.dirname(os.path.abspath(__file__))
PROJECT  = os.path.normpath(os.path.join(ROOT, "..", ".."))
SNAPDIR  = os.path.join(PROJECT, "_snapshots")
BACKUP   = os.path.join(SNAPDIR, "wp_posts_before_cleanup.sql")
UNMATCH  = os.path.join(PROJECT, "unmatched_pdfs.txt")
REPORT   = os.path.join(PROJECT, "_planning", "cleanup_imported_html_report.txt")

# ---------------------------------------------------------------------------
# Config

POST_TYPES = ("page", "post", "tlt_show", "tlt_team")

# Squarespace wrapper-div classes that should be unwrapped (tag removed,
# children kept). Match is "any of these classes is present".
WRAPPER_CLASS_PREFIXES = (
    "sqs-layout",
    "sqs-row",
    "sqs-block",
    "sqs-block-content",
    "row sqs-row",
    "columns-12",
)

# Specific website-component-block variants we know how to convert.
BUTTON_BLOCK_CLASS = "website-component-block button-block"
EMBED_BLOCK_CLASS  = "website-component-block embed-block"

# Squarespace inserted these wrapper HTML comments around the post body.
COMMENT_PATTERNS = [
    re.compile(r"<!--\s*SPECIAL CONTENT\s*-->", re.I),
    re.compile(r"<!--\s*POST HEADER\s*-->", re.I),
    re.compile(r"<!--\s*POST BODY\s*-->", re.I),
    re.compile(r"<!--\s*POST FOOTER\s*-->", re.I),
]

# Mojibake replacement: U+FFFD => U+2019. Doc says >90% of these are
# possessives/contractions.
REPLACEMENT_CHAR = "�"
PREFERRED_REPLACEMENT = "’"

# /s/X.pdf rewriting
S_PDF_RE = re.compile(r'(href=["\'])/s/([^"\']+\.pdf)(["\'])', re.I)
NEW_PDF_BASE = "/wp-content/uploads/programs/"

# ---------------------------------------------------------------------------
# Backup

def backup_wp_posts(conn):
    """Write a minimal SQL backup of wp_posts to _snapshots/."""
    os.makedirs(SNAPDIR, exist_ok=True)
    cur = conn.cursor()
    cur.execute("SELECT * FROM wp_posts")
    cols = [d[0] for d in cur.description]
    rows = cur.fetchall()
    with open(BACKUP, "w", encoding="utf-8") as f:
        f.write(f"-- wp_posts backup taken {time.strftime('%Y-%m-%d %H:%M:%S')}\n")
        f.write(f"-- {len(rows)} rows, {len(cols)} columns\n")
        f.write("SET NAMES utf8mb4;\n\n")
        col_list = ", ".join("`" + c + "`" for c in cols)
        for row in rows:
            vals = []
            for v in row:
                if v is None:
                    vals.append("NULL")
                elif isinstance(v, (int, float)):
                    vals.append(str(v))
                elif hasattr(v, "strftime"):
                    vals.append("'" + v.strftime("%Y-%m-%d %H:%M:%S") + "'")
                else:
                    s = str(v).replace("\\", "\\\\").replace("'", "\\'").replace("\r", "\\r").replace("\n", "\\n")
                    vals.append("'" + s + "'")
            f.write(f"INSERT INTO `wp_posts` ({col_list}) VALUES (" + ", ".join(vals) + ");\n")
    return len(rows)

# ---------------------------------------------------------------------------
# Cleanup helpers

def has_wrapper_class(el):
    classes = el.get("class") or []
    if not classes:
        return False
    cls_str = " ".join(classes)
    for prefix in WRAPPER_CLASS_PREFIXES:
        # match "sqs-layout" or "sqs-block" as whole-class-name OR with hyphen suffix
        for c in classes:
            if c == prefix or c.startswith(prefix + "-") or c.startswith(prefix):
                # avoid false-positives like "sqs-something-unrelated"; the
                # known wrappers all start with one of these literal prefixes
                return True
    return False

def is_button_block(el):
    classes = el.get("class") or []
    return "website-component-block" in classes and "button-block" in classes

def is_embed_block(el):
    classes = el.get("class") or []
    return "website-component-block" in classes and "embed-block" in classes

def unwrap_div(el):
    """Replace <div>...</div> with its children in place."""
    el.unwrap()

def strip_wrappers(html):
    """Run wrapper-div stripping. Returns (new_html, divs_stripped_count)."""
    if not html or "<div" not in html:
        # nothing to do beyond comment stripping
        out = html or ""
        for pat in COMMENT_PATTERNS:
            out = pat.sub("", out)
            # also collapse the blank line it leaves behind
        out = re.sub(r"\n{3,}", "\n\n", out)
        return out, 0

    # Strip block-level HTML comments first (string level — BeautifulSoup
    # preserves comments as Comment nodes too; we handle both).
    for pat in COMMENT_PATTERNS:
        html = pat.sub("", html)

    soup = BeautifulSoup(html, "html.parser")

    # Remove comment nodes matching our patterns (in case any survived).
    from bs4 import Comment
    for c in list(soup.find_all(string=lambda s: isinstance(s, Comment))):
        text = str(c).strip()
        if text in ("SPECIAL CONTENT", "POST HEADER", "POST BODY", "POST FOOTER"):
            c.extract()

    stripped = 0

    # First handle button-blocks specifically: extract <a> and wrap in <p class="button-row">.
    for el in list(soup.find_all("div")):
        if is_button_block(el):
            anchor = el.find("a")
            if anchor:
                new_p = soup.new_tag("p")
                new_p["class"] = ["button-row"]
                new_p.append(anchor.extract())
                el.replace_with(new_p)
                stripped += 1
            else:
                # No anchor inside; just unwrap
                el.unwrap()
                stripped += 1

    # Embed blocks: keep the iframe (or any contents) but drop the wrapper div.
    for el in list(soup.find_all("div")):
        if is_embed_block(el):
            el.unwrap()
            stripped += 1

    # Now generic Squarespace wrappers — unwrap any <div> whose class matches
    # the wrapper prefixes. Repeat until no more changes (nested wrappers).
    while True:
        changed = False
        for el in list(soup.find_all("div")):
            classes = el.get("class") or []
            if not classes:
                continue
            # Wrapper if ANY class starts with a known wrapper prefix.
            wrap = False
            for c in classes:
                if (c.startswith("sqs-layout")
                        or c.startswith("sqs-row")
                        or c.startswith("sqs-block")
                        or c == "row"
                        or c == "columns-12"
                        or c.startswith("columns-12")):
                    wrap = True
                    break
            if wrap:
                el.unwrap()
                stripped += 1
                changed = True
        if not changed:
            break

    # Also strip data-block-type wrapper divs (rare in this DB but in spec).
    for el in list(soup.find_all("div", attrs={"data-block-type": True})):
        el.unwrap()
        stripped += 1

    out = str(soup)
    # Tidy excess blank lines from comment removal.
    out = re.sub(r"\n{3,}", "\n\n", out)
    return out, stripped

def fix_mojibake(html):
    """Replace U+FFFD with U+2019. Returns (new_html, replacements)."""
    if not html or REPLACEMENT_CHAR not in html:
        return html, 0
    n = html.count(REPLACEMENT_CHAR)
    return html.replace(REPLACEMENT_CHAR, PREFERRED_REPLACEMENT), n

def rewrite_pdf_links(html, unmatched_set, rewrite_log, unmatched_log):
    """Rewrite /s/X.pdf -> /wp-content/uploads/programs/X.pdf.

    Skips filenames in `unmatched_set` (appends them to unmatched_log).
    Records every rewritten filename in rewrite_log.
    Returns (new_html, rewrite_count).
    """
    if not html or "/s/" not in html:
        return html, 0
    count = 0

    def _sub(m):
        nonlocal count
        prefix, fname, suffix = m.group(1), m.group(2), m.group(3)
        if fname in unmatched_set:
            unmatched_log[fname] += 1
            return m.group(0)
        rewrite_log[fname] += 1
        count += 1
        return f"{prefix}{NEW_PDF_BASE}{fname}{suffix}"

    new = S_PDF_RE.sub(_sub, html)
    return new, count

# ---------------------------------------------------------------------------
# Main

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--dry-run", action="store_true",
                    help="Print what would change without writing to DB.")
    ap.add_argument("--limit", type=int, default=0,
                    help="Process at most N rows (0 = all).")
    ap.add_argument("--show-diffs", type=int, default=0,
                    help="In dry-run, show before/after snippets for first N modified posts.")
    args = ap.parse_args()

    # Load unmatched list
    unmatched_set = set()
    if os.path.exists(UNMATCH):
        with open(UNMATCH, "r", encoding="utf-8") as f:
            for line in f:
                fn = line.strip()
                if fn:
                    unmatched_set.add(fn)
    print(f"Loaded {len(unmatched_set)} unmatched PDF filenames from {UNMATCH}")

    conn = pymysql.connect(
        host="127.0.0.1", port=10005, user="root", password="root",
        database="local", charset="utf8mb4",
    )

    # Backup before any real-mode writes
    if not args.dry_run:
        n = backup_wp_posts(conn)
        print(f"Backup written: {BACKUP}  ({n} rows)")
    else:
        print("Dry run: skipping backup.")

    cur = conn.cursor()
    sql = (
        "SELECT ID, post_title, post_type, post_content FROM wp_posts "
        "WHERE post_status='publish' AND post_type IN %s "
        "ORDER BY ID"
    )
    cur.execute(sql, (POST_TYPES,))
    rows = cur.fetchall()
    if args.limit:
        rows = rows[: args.limit]
    print(f"Processing {len(rows)} posts (post_type in {POST_TYPES})...")

    total_wrappers       = 0
    total_replacements   = 0
    total_pdf_rewrites   = 0
    posts_modified       = 0
    high_mojibake_pages  = []   # (id, title, count)
    rewrite_log          = Counter()
    unmatched_log        = Counter()
    sample_diffs         = []

    for pid, title, ptype, content in rows:
        if content is None:
            continue

        # 1. wrappers + comments
        c1, n_wrap = strip_wrappers(content)
        # 2. mojibake
        c2, n_moj = fix_mojibake(c1)
        # 3. /s/ pdfs
        c3, n_pdf = rewrite_pdf_links(c2, unmatched_set, rewrite_log, unmatched_log)

        total_wrappers     += n_wrap
        total_replacements += n_moj
        total_pdf_rewrites += n_pdf

        if n_moj > 10:
            high_mojibake_pages.append((pid, title, n_moj))

        if c3 != content:
            posts_modified += 1
            if args.dry_run and args.show_diffs and len(sample_diffs) < args.show_diffs:
                sample_diffs.append((pid, title, ptype, content, c3))
            if not args.dry_run:
                cur.execute(
                    "UPDATE wp_posts SET post_content=%s WHERE ID=%s",
                    (c3, pid),
                )

    if not args.dry_run:
        conn.commit()
    conn.close()

    # Report --------------------------------------------------------------
    print()
    print("=" * 60)
    print("Cleanup summary")
    print("=" * 60)
    print(f"Posts examined          : {len(rows)}")
    print(f"Posts modified          : {posts_modified}")
    print(f"Wrapper divs stripped   : {total_wrappers}")
    print(f"U+FFFD chars replaced   : {total_replacements}")
    print(f"/s/*.pdf links rewritten: {total_pdf_rewrites}")
    print(f"/s/*.pdf links skipped (unmatched): {sum(unmatched_log.values())} "
          f"across {len(unmatched_log)} unique filenames")
    if high_mojibake_pages:
        print()
        print("Pages with >10 U+FFFD replacements (review recommended):")
        for pid, title, n in high_mojibake_pages:
            print(f"  ID={pid:5d}  count={n:4d}  {title}")
    print()
    if unmatched_log:
        print("Top 10 unmatched /s/ filenames left in content:")
        for fn, n in unmatched_log.most_common(10):
            print(f"  {n:3d}x  {fn}")

    # Show diffs in dry-run mode if requested
    if sample_diffs:
        print()
        print("=" * 60)
        print(f"Showing {len(sample_diffs)} before/after diffs")
        print("=" * 60)
        for pid, title, ptype, before, after in sample_diffs:
            print(f"\n--- ID={pid} ({ptype}) {title} ---")
            print(f"BEFORE ({len(before)} chars):")
            print(before[:800])
            print(f"...\nAFTER ({len(after)} chars):")
            print(after[:800])
            print("...")

    # Write a persistent report
    os.makedirs(os.path.dirname(REPORT), exist_ok=True)
    with open(REPORT, "w", encoding="utf-8") as f:
        f.write(f"# Cleanup report ({'DRY-RUN' if args.dry_run else 'APPLIED'})\n")
        f.write(f"# Generated {time.strftime('%Y-%m-%d %H:%M:%S')}\n\n")
        f.write(f"Posts examined          : {len(rows)}\n")
        f.write(f"Posts modified          : {posts_modified}\n")
        f.write(f"Wrapper divs stripped   : {total_wrappers}\n")
        f.write(f"U+FFFD chars replaced   : {total_replacements}\n")
        f.write(f"/s/*.pdf links rewritten: {total_pdf_rewrites}\n")
        f.write(f"/s/*.pdf links skipped  : {sum(unmatched_log.values())}\n\n")
        if high_mojibake_pages:
            f.write("Pages with >10 U+FFFD replacements:\n")
            for pid, title, n in high_mojibake_pages:
                f.write(f"  ID={pid}  count={n}  {title}\n")
            f.write("\n")
        if unmatched_log:
            f.write("Unmatched /s/ filenames still referenced (count x file):\n")
            for fn, n in sorted(unmatched_log.items()):
                f.write(f"  {n:3d}x  {fn}\n")
    print(f"\nReport: {REPORT}")

    if args.dry_run:
        print("\n[dry-run] no changes written.")


if __name__ == "__main__":
    main()
