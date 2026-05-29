"""
Pull the canonical database from Cloudways down to this computer's Local site.

Use this on your *second* computer (or any time you want this machine's local
DB to match what's on Cloudways).

How it works (keeps the canonical DB untouched):
  1. SSH to Cloudways and run WP-CLI `search-replace ... --export`, which writes
     a dump with the temp URL rewritten to http://tlt.local. `--export` does NOT
     modify the live DB, and the rewrite is serialization-safe (WP-CLI handles
     PHP-serialized length prefixes that a raw SQL REPLACE would corrupt).
  2. SCP that dump down.
  3. Import it into the local `local` DB with the bundled MySQL client.

Requirements:
  - Windows OpenSSH `ssh` + `scp` on PATH (built into Win10+).
  - deploy/cloudways.json filled in (copy deploy/cloudways.example.json).
  - For passwordless runs, set up an SSH key to Cloudways (recommended);
    otherwise ssh/scp will prompt for the server password (a few times).

Usage:
    python deploy/sync_down.py

NOTE: a single rewrite pass replaces `https://<temp-host>` -> `http://tlt.local`,
which covers siteurl/home and the overwhelming majority of stored URLs. Bare
host references without a scheme are rare and left as-is.
"""
import json
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
CONFIG = HERE / "cloudways.json"
DUMPS = HERE / "dumps"

LOCAL_MYSQL = r"C:\Users\blake\AppData\Roaming\Local\lightning-services\mysql-8.4.0\bin\win64\bin\mysql.exe"
LOCAL_DB = dict(host="127.0.0.1", port="10005", user="root", password="root", database="local")
LOCAL_URL = "http://tlt.local"

REMOTE_DUMP = "/tmp/tlt_for_local.sql"


def load_config():
    if not CONFIG.exists():
        sys.exit(f"Missing {CONFIG.name}. Copy cloudways.example.json -> cloudways.json "
                 "and fill in your server details.")
    cfg = json.loads(CONFIG.read_text())
    for k in ("ssh_user", "ssh_host", "app_folder", "temp_url"):
        if not cfg.get(k):
            sys.exit(f"cloudways.json is missing '{k}'.")
    return cfg


def run(cmd, **kw):
    print("  $ " + " ".join(cmd))
    r = subprocess.run(cmd, **kw)
    if r.returncode != 0:
        sys.exit(f"Command failed (exit {r.returncode}).")
    return r


def main():
    cfg = load_config()
    target = f"{cfg['ssh_user']}@{cfg['ssh_host']}"
    temp_host = cfg["temp_url"].split("://", 1)[-1].rstrip("/")
    https_temp = f"https://{temp_host}"
    app_path = f"applications/{cfg['app_folder']}/public_html"

    DUMPS.mkdir(parents=True, exist_ok=True)
    local_dump = DUMPS / "from_cloudways.sql"

    if not Path(LOCAL_MYSQL).exists():
        sys.exit(f"Local mysql client not found at:\n  {LOCAL_MYSQL}")

    # 1. Server-side: export a tlt.local-rewritten dump (canonical DB untouched).
    print("\n[1/3] Exporting rewritten dump on Cloudways ...")
    remote_cmd = (
        f"cd {app_path} && "
        f"wp search-replace '{https_temp}' '{LOCAL_URL}' "
        f"--skip-columns=guid --all-tables --export={REMOTE_DUMP}"
    )
    run(["ssh", target, remote_cmd])

    # 2. Pull it down.
    print("\n[2/3] Downloading dump ...")
    run(["scp", f"{target}:{REMOTE_DUMP}", str(local_dump)])
    run(["ssh", target, f"rm -f {REMOTE_DUMP}"])

    # 3. Import into local DB.
    print("\n[3/3] Importing into local `local` DB ...")
    import os
    env = dict(os.environ)
    env["MYSQL_PWD"] = LOCAL_DB["password"]
    with open(local_dump, "rb") as fh:
        run([LOCAL_MYSQL,
             f"--host={LOCAL_DB['host']}", f"--port={LOCAL_DB['port']}",
             f"--user={LOCAL_DB['user']}", LOCAL_DB["database"]],
            stdin=fh, env=env)

    print(f"\nDone. Local DB now matches Cloudways. Visit {LOCAL_URL}")
    print("If images are missing, sync wp-content/uploads down via WinSCP.")


if __name__ == "__main__":
    main()
