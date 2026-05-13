"""
Finalize PDF link rewriting after we located the previously-unmatched programs.

The cleanup pass rewrote /s/*.pdf -> /wp-content/uploads/programs/*.pdf in
post_content but skipped postmeta and any references where the local file
didn't exist yet. Now that 549 PDFs are in /uploads/programs/, this script:

1. Walks all postmeta values matching /s/*.pdf
2. For each one, checks if the local file now exists at
   /wp-content/uploads/programs/<same-filename>
3. If yes -> rewrites the meta value to the local path
4. If no -> leaves it alone (and logs)

Also re-scans post_content for any /s/*.pdf that crept in or wasn't handled
the first time around. And rewrites absolute https://*.tacomalittletheatre.com/s/
URLs to local paths too.

Idempotent.
"""
import os, re, pymysql

UPLOADS_DIR = "C:/Users/blake/Local Sites/tlt/app/public/wp-content/uploads/programs"


def main():
    c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
    cur = c.cursor()

    # Index local programs
    local_files = set(os.listdir(UPLOADS_DIR)) if os.path.isdir(UPLOADS_DIR) else set()
    print(f"Local /uploads/programs/ has {len(local_files)} files")

    # --- 1. Fix postmeta ---
    cur.execute("""SELECT meta_id, post_id, meta_key, meta_value FROM wp_postmeta
                   WHERE meta_value LIKE %s OR meta_value LIKE %s""",
                ('%/s/%.pdf%', '%tacomalittletheatre.com/s/%.pdf%'))
    meta_rows = cur.fetchall()
    print(f"\nPostmeta rows with /s/*.pdf: {len(meta_rows)}")

    rewritten_meta = 0
    skipped_meta = []
    for mid, pid, key, val in meta_rows:
        # Strip the absolute domain if present
        new_val = re.sub(r'https?://(?:www\.)?tacomalittletheatre\.com', '', val)
        # Replace /s/<file>.pdf with /wp-content/uploads/programs/<file>.pdf when file exists
        def replace_match(m):
            full = m.group(0)
            fn = m.group(1)
            if fn in local_files:
                return f'/wp-content/uploads/programs/{fn}'
            return full  # leave unchanged
        new_val = re.sub(r'/s/([^\s"\'<>]+\.pdf)', replace_match, new_val)
        if new_val != val:
            cur.execute("UPDATE wp_postmeta SET meta_value=%s WHERE meta_id=%s", (new_val, mid))
            rewritten_meta += 1
        else:
            # Track which filenames still couldn't be matched
            for m in re.finditer(r'/s/([^\s"\'<>]+\.pdf)', val):
                skipped_meta.append((pid, key, m.group(1)))

    print(f"  Rewritten: {rewritten_meta}")
    print(f"  Skipped (file not on disk yet): {len(skipped_meta)}")
    for pid, key, fn in skipped_meta[:10]:
        print(f"    post {pid} {key}: /s/{fn}")

    # --- 2. Fix post_content too (in case any /s/ snuck in) ---
    cur.execute("""SELECT ID, post_content FROM wp_posts
                   WHERE post_status IN ('publish','draft','private')
                     AND (post_content LIKE %s OR post_content LIKE %s)""",
                ('%/s/%.pdf%', '%tacomalittletheatre.com/s/%.pdf%'))
    posts = cur.fetchall()
    print(f"\npost_content rows still containing /s/*.pdf: {len(posts)}")

    rewritten_posts = 0
    skipped_posts = []
    for pid, body in posts:
        new_body = re.sub(r'https?://(?:www\.)?tacomalittletheatre\.com', '', body)
        def replace_match(m):
            full = m.group(0)
            fn = m.group(1)
            if fn in local_files:
                return f'/wp-content/uploads/programs/{fn}'
            return full
        new_body = re.sub(r'/s/([^\s"\'<>]+\.pdf)', replace_match, new_body)
        if new_body != body:
            cur.execute("UPDATE wp_posts SET post_content=%s WHERE ID=%s", (new_body, pid))
            rewritten_posts += 1
        else:
            for m in re.finditer(r'/s/([^\s"\'<>]+\.pdf)', body):
                skipped_posts.append((pid, m.group(1)))

    print(f"  Rewritten: {rewritten_posts}")
    print(f"  Skipped (file not on disk yet): {len(skipped_posts)}")

    c.commit()

    # --- 3. Final verification ---
    cur.execute("SELECT COUNT(*) FROM wp_postmeta WHERE meta_value LIKE %s OR meta_value LIKE %s",
                ('%/s/%.pdf%', '%tacomalittletheatre.com/s/%.pdf%'))
    print(f"\nFinal postmeta /s/*.pdf count: {cur.fetchone()[0]}")
    cur.execute("SELECT COUNT(*) FROM wp_posts WHERE post_content LIKE %s",
                ('%/s/%.pdf%',))
    print(f"Final post_content /s/*.pdf count: {cur.fetchone()[0]}")

    c.close()


if __name__ == '__main__':
    main()
