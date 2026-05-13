"""
Rehost Squarespace-hosted images locally.

Scans wp_posts.post_content and wp_postmeta.meta_value for URLs on
*.squarespace.com / squarespace-cdn.com, downloads each unique image once
(de-duplicated by SHA-256 of content), stores them under
  wp-content/uploads/migrated/<slug>.<ext>
and rewrites every reference to the new local path.

Also scans for any remaining /s/*.pdf links (the Squarespace asset URL form)
that have a counterpart already on disk under wp-content/uploads/. The
companion cleanup_imported_html.py agent is the primary owner of /s/*.pdf
rewriting; this script only picks up additional matches and logs the
unmatched ones for manual triage.

Usage:
  python rehost_squarespace_images.py --dry-run         # report only
  python rehost_squarespace_images.py                   # real run
  python rehost_squarespace_images.py --no-backup       # skip db backup
  python rehost_squarespace_images.py --max-workers N   # default 10

Idempotent: re-running after a successful run is a no-op (no new URLs to fetch,
no rewrites left to do). Resumable: downloads are content-hash deduped against
files already on disk; partial-state crash just re-runs and skips finished URLs.
"""
from __future__ import annotations

import argparse
import concurrent.futures as cf
import hashlib
import json
import mimetypes
import os
import re
import sys
import time
from pathlib import Path
from urllib.parse import urlparse, unquote

import pymysql
import requests


# ---------------------------------------------------------------- config

DB = dict(host="127.0.0.1", port=10005, user="root", password="root", database="local")

PROJECT_ROOT = Path("C:/Users/blake/dev/TLT_Website")
WP_UPLOADS   = Path("C:/Users/blake/Local Sites/tlt/app/public/wp-content/uploads")
DEST_DIR     = WP_UPLOADS / "migrated"
SNAPSHOT_DIR = PROJECT_ROOT / "_snapshots"
STATE_FILE   = PROJECT_ROOT / "wordpress" / "import" / ".rehost_squarespace_state.json"
LOG_DIR      = PROJECT_ROOT / "wordpress" / "import"

UA = "TLT-Migration/1.0 (blakeryork@gmail.com; rehosting our own Squarespace site)"
TIMEOUT = 45
RETRIES = 3

# Patterns
SQUARESPACE_HOST_RE = re.compile(
    r"""https?://[^\s"'<>)\]\}]*?squarespace[^\s"'<>)\]\}]*""",
    re.I,
)
SPACE_PDF_RE = re.compile(r"/s/([^\s\"'<>?)]+\.pdf)", re.I)


# ---------------------------------------------------------------- helpers

def log(msg: str) -> None:
    print(msg, flush=True)


def connect():
    return pymysql.connect(**DB, autocommit=False)


def strip_format(url: str) -> str:
    """Strip ?format=NNNw and other Squarespace query params we don't want."""
    # Remove all ?format=... and &format=... params; if the only param was format,
    # also strip trailing '?'.
    cleaned = re.sub(r"([?&])format=[^&]*", r"\1", url, flags=re.I)
    # Collapse '&&' or trailing '?' or '?&'
    cleaned = re.sub(r"[?&]+$", "", cleaned)
    cleaned = cleaned.replace("?&", "?").replace("&&", "&")
    return cleaned


def slugify(s: str) -> str:
    s = unquote(s).lower()
    s = re.sub(r"[^a-z0-9.]+", "-", s)
    s = re.sub(r"-+", "-", s).strip("-")
    return s


def filename_from_url(url: str, content_type: str | None) -> str:
    """Derive a clean local filename for a Squarespace URL."""
    p = urlparse(url)
    last = unquote(p.path.rstrip("/").rsplit("/", 1)[-1])
    # last might be empty or be a 13-digit timestamp like "1475872586123"
    has_ext = "." in last and len(last.rsplit(".", 1)[-1]) <= 5
    if last and has_ext:
        stem, ext = last.rsplit(".", 1)
        stem = slugify(stem) or "image"
        ext = ext.lower()
    else:
        # Use the last two path segments as a stem hint
        parts = [x for x in p.path.split("/") if x]
        stem = slugify("-".join(parts[-2:]) or parts[-1] or "image") or "image"
        ext = ""
    # Pick ext from content-type if missing/garbage
    valid_image_exts = {"jpg", "jpeg", "png", "gif", "webp", "svg", "avif", "bmp", "ico"}
    if ext not in valid_image_exts:
        if content_type:
            ct = content_type.split(";")[0].strip().lower()
            guessed = mimetypes.guess_extension(ct) or ""
            guessed = guessed.lstrip(".")
            if guessed == "jpe":
                guessed = "jpg"
            ext = guessed or "bin"
        else:
            ext = "bin"
    if ext == "jpeg":
        ext = "jpg"
    return f"{stem}.{ext}"


def sha256_bytes(b: bytes) -> str:
    return hashlib.sha256(b).hexdigest()


def sha256_file(p: Path) -> str:
    h = hashlib.sha256()
    with p.open("rb") as f:
        for chunk in iter(lambda: f.read(65536), b""):
            h.update(chunk)
    return h.hexdigest()


# ---------------------------------------------------------------- state

def load_state() -> dict:
    if STATE_FILE.exists():
        try:
            return json.loads(STATE_FILE.read_text(encoding="utf-8"))
        except Exception:
            pass
    return {"url_to_local": {}, "hash_to_local": {}, "failed": {}}


def save_state(state: dict) -> None:
    STATE_FILE.parent.mkdir(parents=True, exist_ok=True)
    tmp = STATE_FILE.with_suffix(".tmp")
    tmp.write_text(json.dumps(state, indent=2, sort_keys=True), encoding="utf-8")
    tmp.replace(STATE_FILE)


# ---------------------------------------------------------------- backup

def backup_tables(timestamp: str) -> Path:
    """Dump wp_posts and wp_postmeta via pymysql to a .sql file."""
    SNAPSHOT_DIR.mkdir(parents=True, exist_ok=True)
    out = SNAPSHOT_DIR / f"before_image_rehost_{timestamp}.sql"
    log(f"Backing up wp_posts + wp_postmeta to {out} ...")
    c = connect()
    cur = c.cursor()
    with out.open("w", encoding="utf-8", newline="\n") as f:
        f.write(f"-- TLT rehost backup {timestamp}\n")
        f.write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n")
        for table in ("wp_posts", "wp_postmeta"):
            cur.execute(f"SHOW CREATE TABLE `{table}`")
            create = cur.fetchone()[1]
            f.write(f"DROP TABLE IF EXISTS `{table}`;\n")
            f.write(create + ";\n\n")
            cur.execute(f"SELECT * FROM `{table}`")
            cols = [d[0] for d in cur.description]
            collist = ",".join(f"`{c}`" for c in cols)
            batch = []
            BATCH_SIZE = 200
            for row in cur:
                vals = []
                for v in row:
                    if v is None:
                        vals.append("NULL")
                    elif isinstance(v, (int, float)):
                        vals.append(str(v))
                    elif isinstance(v, (bytes, bytearray)):
                        vals.append("0x" + bytes(v).hex())
                    else:
                        s = str(v).replace("\\", "\\\\").replace("'", "\\'")
                        s = s.replace("\0", "\\0").replace("\n", "\\n").replace("\r", "\\r").replace("\x1a", "\\Z")
                        vals.append(f"'{s}'")
                batch.append("(" + ",".join(vals) + ")")
                if len(batch) >= BATCH_SIZE:
                    f.write(f"INSERT INTO `{table}` ({collist}) VALUES\n")
                    f.write(",\n".join(batch) + ";\n")
                    batch = []
            if batch:
                f.write(f"INSERT INTO `{table}` ({collist}) VALUES\n")
                f.write(",\n".join(batch) + ";\n")
            f.write("\n")
        f.write("SET FOREIGN_KEY_CHECKS=1;\n")
    c.close()
    sz = out.stat().st_size
    log(f"  backup written, {sz/1024/1024:.1f} MiB")
    return out


# ---------------------------------------------------------------- scan

def scan_all() -> tuple[set[str], dict[int, str], dict[tuple[int, str], str], set[str]]:
    """
    Returns:
      sqs_urls: set of raw Squarespace URLs seen (as written in content)
      post_content: ID -> content (only rows containing 'squarespace' or '/s/')
      postmeta: (post_id, meta_key) -> meta_value (only rows containing it)
      pdf_paths: set of '/s/foo.pdf' path-strings
    """
    sqs_urls: set[str] = set()
    pdf_paths: set[str] = set()
    post_content: dict[int, str] = {}
    postmeta: dict[tuple[int, str], str] = {}

    c = connect()
    cur = c.cursor()

    # post_content
    cur.execute(
        "SELECT ID, post_content FROM wp_posts "
        "WHERE post_content LIKE %s OR post_content LIKE %s",
        ("%squarespace%", "%/s/%.pdf%"),
    )
    for pid, pc in cur.fetchall():
        if pc is None:
            continue
        post_content[pid] = pc
        for u in SQUARESPACE_HOST_RE.findall(pc):
            sqs_urls.add(u)
        for m in SPACE_PDF_RE.findall(pc):
            pdf_paths.add("/s/" + m)

    # postmeta
    cur.execute(
        "SELECT post_id, meta_key, meta_value FROM wp_postmeta "
        "WHERE meta_value LIKE %s OR meta_value LIKE %s",
        ("%squarespace%", "%/s/%.pdf%"),
    )
    for pid, mk, mv in cur.fetchall():
        if mv is None:
            continue
        postmeta[(pid, mk)] = mv
        for u in SQUARESPACE_HOST_RE.findall(mv):
            sqs_urls.add(u)
        for m in SPACE_PDF_RE.findall(mv):
            pdf_paths.add("/s/" + m)

    c.close()
    return sqs_urls, post_content, postmeta, pdf_paths


# ---------------------------------------------------------------- download

def fetch_one(session: requests.Session, url: str) -> tuple[bytes, str | None, str]:
    """Returns (content, content_type, final_url). Raises on failure."""
    clean = strip_format(url)
    last_err = None
    for attempt in range(1, RETRIES + 1):
        try:
            r = session.get(
                clean,
                headers={"User-Agent": UA, "Accept": "image/*,*/*;q=0.8"},
                timeout=TIMEOUT,
                allow_redirects=True,
            )
            if r.status_code == 200 and r.content:
                return r.content, r.headers.get("Content-Type"), r.url
            last_err = f"HTTP {r.status_code}"
        except Exception as e:
            last_err = f"{type(e).__name__}: {e}"
        # backoff
        time.sleep(0.6 * attempt)
    raise RuntimeError(last_err or "fetch failed")


def reserve_filename(dest_dir: Path, base: str, taken: set[str]) -> str:
    """Return a filename in dest_dir that doesn't collide. Mutates `taken`."""
    name = base
    if name not in taken and not (dest_dir / name).exists():
        taken.add(name)
        return name
    stem, _, ext = base.rpartition(".")
    i = 2
    while True:
        candidate = f"{stem}-{i}.{ext}"
        if candidate not in taken and not (dest_dir / candidate).exists():
            taken.add(candidate)
            return candidate
        i += 1


def download_all(
    urls: list[str],
    state: dict,
    dry_run: bool,
    max_workers: int,
) -> dict:
    """Download all urls (skipping ones in state['url_to_local']). Returns state."""
    DEST_DIR.mkdir(parents=True, exist_ok=True)

    url_to_local: dict[str, str] = state["url_to_local"]
    hash_to_local: dict[str, str] = state["hash_to_local"]
    failed: dict[str, str] = state["failed"]

    todo = [u for u in urls if u not in url_to_local]
    log(f"  already mapped: {len(urls) - len(todo)}")
    log(f"  to download:    {len(todo)}")
    if dry_run:
        return state

    # Reserve filename uniqueness within this run
    taken: set[str] = set()
    for v in url_to_local.values():
        taken.add(Path(v).name)

    total_bytes = 0
    successes = 0
    deduped = 0
    new_failures = 0

    session = requests.Session()

    def worker(url: str):
        try:
            content, ct, final_url = fetch_one(session, url)
            return ("ok", url, content, ct, final_url)
        except Exception as e:
            return ("err", url, str(e), None, None)

    save_every = 25
    processed = 0
    with cf.ThreadPoolExecutor(max_workers=max_workers) as ex:
        for result in ex.map(worker, todo):
            kind = result[0]
            url = result[1]
            processed += 1
            if kind == "err":
                err = result[2]
                failed[url] = err
                new_failures += 1
                log(f"  FAIL {url[:120]} :: {err}")
            else:
                content, ct, final_url = result[2], result[3], result[4]
                h = sha256_bytes(content)
                if h in hash_to_local:
                    # Already have an identical file
                    url_to_local[url] = hash_to_local[h]
                    deduped += 1
                else:
                    fname = filename_from_url(final_url or url, ct)
                    # Some squarespace URLs decode to filenames that just collide
                    fname = reserve_filename(DEST_DIR, fname, taken)
                    dest = DEST_DIR / fname
                    dest.write_bytes(content)
                    local = f"/wp-content/uploads/migrated/{fname}"
                    url_to_local[url] = local
                    hash_to_local[h] = local
                    successes += 1
                    total_bytes += len(content)
                # Drop from failed if previously failed
                failed.pop(url, None)
            if processed % save_every == 0:
                save_state(state)
                log(f"  ... progress {processed}/{len(todo)}  ok={successes} dup={deduped} fail={new_failures}")

    save_state(state)
    log(
        f"  downloaded:    {successes}\n"
        f"  deduped:       {deduped}\n"
        f"  failed:        {new_failures}\n"
        f"  total bytes:   {total_bytes:,}"
    )
    state["_run_stats"] = {
        "downloaded_new": successes,
        "deduped": deduped,
        "failed_new": new_failures,
        "bytes_downloaded": total_bytes,
    }
    return state


# ---------------------------------------------------------------- pdf

def index_uploads_pdfs() -> dict[str, str]:
    """Map filename (lowercased) -> /wp-content/uploads/.../file.pdf."""
    out: dict[str, str] = {}
    if not WP_UPLOADS.exists():
        return out
    for p in WP_UPLOADS.rglob("*.pdf"):
        rel = p.relative_to(WP_UPLOADS).as_posix()
        out.setdefault(p.name.lower(), f"/wp-content/uploads/{rel}")
    return out


def resolve_pdf_paths(pdf_paths: set[str]) -> tuple[dict[str, str], list[str]]:
    """
    For each '/s/foo.pdf', try to find a matching file in wp-content/uploads.
    Returns (matched, unmatched).
    """
    index = index_uploads_pdfs()
    matched: dict[str, str] = {}
    unmatched: list[str] = []
    for path in pdf_paths:
        fn = path.rsplit("/", 1)[-1].lower()
        if fn in index:
            matched[path] = index[fn]
        else:
            unmatched.append(path)
    return matched, unmatched


# ---------------------------------------------------------------- rewrite

def apply_url_map(text: str, url_map: dict[str, str], pdf_map: dict[str, str]) -> tuple[str, int]:
    """Replace all url occurrences in text. Returns (new_text, count_replacements)."""
    if not text:
        return text, 0
    count = 0
    new = text
    # Longest-first to avoid prefix collisions (e.g. URL with format= vs without)
    for old in sorted(url_map, key=len, reverse=True):
        local = url_map[old]
        if old in new:
            n = new.count(old)
            new = new.replace(old, local)
            count += n
    for old in sorted(pdf_map, key=len, reverse=True):
        local = pdf_map[old]
        # Only replace as a standalone path token: in href=/src= attributes
        # The leading '/' makes this safe — '/s/foo.pdf' isn't substring of other paths.
        if old in new:
            n = new.count(old)
            new = new.replace(old, local)
            count += n
    return new, count


def rewrite_db(
    post_content: dict[int, str],
    postmeta: dict[tuple[int, str], str],
    url_map: dict[str, str],
    pdf_map: dict[str, str],
    dry_run: bool,
) -> dict:
    pc_updated = 0
    pc_replacements = 0
    pm_updated = 0
    pm_replacements = 0

    c = connect()
    cur = c.cursor()

    for pid, content in post_content.items():
        new, n = apply_url_map(content, url_map, pdf_map)
        if n > 0 and new != content:
            pc_updated += 1
            pc_replacements += n
            if not dry_run:
                cur.execute("UPDATE wp_posts SET post_content=%s WHERE ID=%s", (new, pid))

    for (pid, mk), mv in postmeta.items():
        new, n = apply_url_map(mv, url_map, pdf_map)
        if n > 0 and new != mv:
            pm_updated += 1
            pm_replacements += n
            if not dry_run:
                cur.execute(
                    "UPDATE wp_postmeta SET meta_value=%s WHERE post_id=%s AND meta_key=%s",
                    (new, pid, mk),
                )

    if not dry_run:
        c.commit()
    c.close()

    return {
        "post_content_rows_updated": pc_updated,
        "post_content_replacements": pc_replacements,
        "postmeta_rows_updated": pm_updated,
        "postmeta_replacements": pm_replacements,
    }


# ---------------------------------------------------------------- main

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--no-backup", action="store_true")
    ap.add_argument("--max-workers", type=int, default=10)
    ap.add_argument("--retry-failed", action="store_true",
                    help="Clear failed list and try again")
    args = ap.parse_args()

    log("=" * 70)
    log(f"Squarespace asset rehost — {'DRY RUN' if args.dry_run else 'REAL RUN'}")
    log("=" * 70)

    # 1. Backup
    if not args.dry_run and not args.no_backup:
        ts = time.strftime("%Y%m%d-%H%M%S")
        backup_tables(ts)

    # 2. Scan
    log("\nScanning database for Squarespace URLs and /s/*.pdf paths ...")
    sqs_urls, post_content, postmeta, pdf_paths = scan_all()
    log(f"  unique squarespace URLs:  {len(sqs_urls)}")
    log(f"  post_content rows hit:    {len(post_content)}")
    log(f"  postmeta rows hit:        {len(postmeta)}")
    log(f"  unique /s/*.pdf paths:    {len(pdf_paths)}")

    # 3. Resolve /s/*.pdf against local uploads
    log("\nMatching /s/*.pdf against wp-content/uploads/ ...")
    pdf_map, pdf_unmatched = resolve_pdf_paths(pdf_paths)
    log(f"  /s/*.pdf matched to local file: {len(pdf_map)}")
    log(f"  /s/*.pdf unmatched:             {len(pdf_unmatched)}")
    if pdf_unmatched:
        unmatched_path = LOG_DIR / "unmatched_s_pdfs.txt"
        unmatched_path.write_text("\n".join(sorted(pdf_unmatched)) + "\n", encoding="utf-8")
        log(f"  -> logged to {unmatched_path}")

    # 4. Download images
    log("\nDownloading Squarespace images ...")
    state = load_state()
    if args.retry_failed:
        log(f"  clearing previously-failed list ({len(state.get('failed', {}))} entries)")
        state["failed"] = {}
    state = download_all(sorted(sqs_urls), state, args.dry_run, args.max_workers)
    url_map = state["url_to_local"]
    log(f"\n  cumulative URL -> local map size: {len(url_map)}")

    # 5. Dry-run sample report
    if args.dry_run:
        log("\nDRY RUN: sample of URL -> local mappings (top 5 by URL):")
        for u in list(sqs_urls)[:5]:
            log(f"  {u[:90]}  ->  {url_map.get(u, '(would-be downloaded)')}")
        log("\nDRY RUN: skipping rewrite. Run without --dry-run to apply.")
        # But still simulate rewrite count
        sim = rewrite_db(post_content, postmeta, url_map, pdf_map, dry_run=True)
        log(f"  would update {sim['post_content_rows_updated']} post_content rows "
            f"({sim['post_content_replacements']} substitutions)")
        log(f"  would update {sim['postmeta_rows_updated']} postmeta rows "
            f"({sim['postmeta_replacements']} substitutions)")
        return

    # 6. Rewrite
    log("\nRewriting database references ...")
    stats = rewrite_db(post_content, postmeta, url_map, pdf_map, dry_run=False)
    log(f"  post_content: {stats['post_content_rows_updated']} rows, "
        f"{stats['post_content_replacements']} substitutions")
    log(f"  postmeta:     {stats['postmeta_rows_updated']} rows, "
        f"{stats['postmeta_replacements']} substitutions")

    # 7. Final summary
    log("\n" + "=" * 70)
    log("SUMMARY")
    log("=" * 70)
    rs = state.get("_run_stats", {})
    log(f"Unique Squarespace URLs found:   {len(sqs_urls)}")
    log(f"  downloaded this run:           {rs.get('downloaded_new', 0)}")
    log(f"  deduped this run:              {rs.get('deduped', 0)}")
    log(f"  failed this run:               {rs.get('failed_new', 0)}")
    log(f"  cumulative success:            {len(url_map)}")
    log(f"  bytes downloaded this run:     {rs.get('bytes_downloaded', 0):,}")
    log(f"post_content rows rewritten:     {stats['post_content_rows_updated']}")
    log(f"  total substitutions:           {stats['post_content_replacements']}")
    log(f"postmeta rows rewritten:         {stats['postmeta_rows_updated']}")
    log(f"  total substitutions:           {stats['postmeta_replacements']}")
    log(f"/s/*.pdf matched locally:        {len(pdf_map)}")
    log(f"/s/*.pdf unmatched (manual):     {len(pdf_unmatched)}")
    if state.get("failed"):
        flog = LOG_DIR / "rehost_failed_urls.txt"
        flog.write_text(
            "\n".join(f"{u}\t{e}" for u, e in sorted(state["failed"].items())) + "\n",
            encoding="utf-8",
        )
        log(f"\nFailed URLs ({len(state['failed'])}) logged to {flog}")


if __name__ == "__main__":
    main()
