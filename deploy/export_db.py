"""
Dump the local WordPress database to a timestamped .sql file for pushing
to Cloudways.

Source: Local by Flywheel MySQL 8.4.0 on 127.0.0.1:10005 (db 'local').
Target: Cloudways MySQL (chosen to match local 8.4 -> no collation conversion).

Output: deploy/dumps/tlt_YYYYMMDD_HHMMSS.sql  (gitignored)

Usage:
    python deploy/export_db.py
"""
import os
import subprocess
import sys
from datetime import datetime
from pathlib import Path

MYSQLDUMP = r"C:\Users\blake\AppData\Roaming\Local\lightning-services\mysql-8.4.0\bin\win64\bin\mysqldump.exe"

HOST = "127.0.0.1"
PORT = "10005"
USER = "root"
PASSWORD = "root"
DATABASE = "local"

# Tables to exclude from the dump. wp_users + wp_usermeta are excluded so a
# deploy never clobbers Chris's (or any prod editor's) password with whatever
# the local dev copy happens to have. Prod-side accounts are managed on prod.
EXCLUDE_TABLES = ["wp_users", "wp_usermeta"]

OUT_DIR = Path(__file__).resolve().parent / "dumps"


def main():
    if not Path(MYSQLDUMP).exists():
        sys.exit(f"mysqldump not found at:\n  {MYSQLDUMP}\n"
                 "Local may have updated its MySQL version; re-find it under "
                 "%APPDATA%\\Local\\lightning-services\\mysql-*\\bin\\win64\\bin\\")

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    stamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    out_file = OUT_DIR / f"tlt_{stamp}.sql"

    cmd = [
        MYSQLDUMP,
        f"--host={HOST}",
        f"--port={PORT}",
        f"--user={USER}",
        "--single-transaction",       # consistent snapshot, no table locks
        "--no-tablespaces",           # avoid needing PROCESS privilege
        "--set-gtid-purged=OFF",      # don't emit GTID state (breaks import on fresh server)
        "--default-character-set=utf8mb4",
        "--routines",
        "--triggers",
        "--events",
    ]
    for t in EXCLUDE_TABLES:
        cmd.append(f"--ignore-table={DATABASE}.{t}")
    cmd.append(DATABASE)              # positional => tables only, no CREATE DATABASE/USE
                                      # (so it imports into any target DB name)

    env = dict(os.environ)
    env["MYSQL_PWD"] = PASSWORD  # avoids the insecure-password-on-CLI warning

    print(f"Dumping `{DATABASE}` -> {out_file.name} ...")
    with open(out_file, "wb") as fh:
        proc = subprocess.run(cmd, stdout=fh, stderr=subprocess.PIPE, env=env)

    if proc.returncode != 0:
        out_file.unlink(missing_ok=True)
        sys.exit("mysqldump failed:\n" + proc.stderr.decode("utf-8", "replace"))

    size_mb = out_file.stat().st_size / (1024 * 1024)
    print(f"Done: {out_file}  ({size_mb:.1f} MB)")
    print("\nNext: import on Cloudways, then run search-replace for the temp URL.")
    print("See deploy/DEPLOY.md.")


if __name__ == "__main__":
    main()
