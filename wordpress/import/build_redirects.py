"""Generate redirect files from migration_redirect_map.csv.

Outputs:
 - redirects.htaccess  — Apache rewrite rules (most hosts)
 - redirects.conf      — nginx-style config block
 - redirection-plugin.csv — for the WP "Redirection" plugin's CSV importer
"""
import os, csv, re

ROOT = os.path.dirname(os.path.abspath(__file__))
PROJECT = os.path.normpath(os.path.join(ROOT, "..", ".."))
SRC = os.path.join(PROJECT, "migration_redirect_map.csv")

apache_path  = os.path.join(ROOT, "redirects.htaccess")
nginx_path   = os.path.join(ROOT, "redirects.nginx.conf")
plugin_path  = os.path.join(ROOT, "redirection-plugin.csv")

apache = ["# TLT migration redirects — drop into top of .htaccess",
          "# Order: most-specific (full path) first; never use 'r=302', use 301 (permanent)",
          ""]
nginx = ["# TLT migration redirects (nginx)",
         "# Use inside server { } block",
         ""]
plugin_rows = [["source","target","code","group"]]

with open(SRC, "r", encoding="utf-8") as f:
    for r in csv.DictReader(f):
        old = r["old_url"]
        new = r["new_url"]
        # Normalize: ensure leading /, no trailing space
        if not old.startswith("/"): old = "/" + old
        if not new.startswith("/") and not new.startswith("http"): new = "/" + new
        if old == new: continue

        # Apache: escape regex metachars in old
        apache_old = re.escape(old).replace(r"\/", "/")
        apache.append(f"Redirect 301 {old} {new}")

        # Nginx
        nginx.append(f'rewrite ^{re.escape(old)}/?$ {new} permanent;')

        # Plugin
        plugin_rows.append([old, new, "301", r["category"]])

with open(apache_path, "w", encoding="utf-8") as f:
    f.write("\n".join(apache) + "\n")
with open(nginx_path, "w", encoding="utf-8") as f:
    f.write("\n".join(nginx) + "\n")
with open(plugin_path, "w", encoding="utf-8", newline="") as f:
    csv.writer(f).writerows(plugin_rows)

print(f"Wrote:")
print(f"  {apache_path}")
print(f"  {nginx_path}")
print(f"  {plugin_path}")
print(f"Total redirects: {len(plugin_rows)-1}")
