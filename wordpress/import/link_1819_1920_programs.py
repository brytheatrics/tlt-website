"""
2018-19 and 2019-20 show records have no show_program_pdf_url meta, so their
"View Program" buttons render disabled. The PDFs exist on the server.
Copy them to /wp-content/uploads/programs/ and set the meta.

Idempotent.
"""
import os, shutil, pymysql

PROGRAMS_DEST = "C:/Users/blake/Local Sites/tlt/app/public/wp-content/uploads/programs"

# show slug -> server source path
PROGRAMS = {
    # 2018-19 — TLT Programs/2013-2018/
    'a-dolls-house':       ("//TLT-SERVER/TLT Programs/2013-2018/1819 A Doll's House.pdf", '1819-A-Dolls-House.pdf'),
    'bell-book-and-candle':("//TLT-SERVER/TLT Programs/2013-2018/1819 Bell Book Candle.pdf", '1819-Bell-Book-Candle.pdf'),
    'hay-fever':           ('//TLT-SERVER/TLT Programs/2013-2018/1819 Hay Fever.pdf', '1819-Hay-Fever.pdf'),
    'laura':               ('//TLT-SERVER/TLT Programs/2013-2018/1819 Laura.pdf', '1819-Laura.pdf'),
    'a-little-night-music':('//TLT-SERVER/TLT Programs/2013-2018/1819 Night Music.pdf', '1819-Night-Music.pdf'),
    'scrooge-the-musical': ('//TLT-SERVER/TLT Programs/2013-2018/1819 Scrooge.pdf', '1819-Scrooge.pdf'),
    'foreigner':           ('//TLT-SERVER/TLT Programs/2013-2018/1819 The Foreigner.pdf', '1819-The-Foreigner.pdf'),
    # Sophie was a short May 2019 production — no program on file

    # 2019-20 — TLT Programs/2013-2018/
    'a-chorus-line-2019':  ('//TLT-SERVER/TLT Programs/2013-2018/1920 A Chorus Line.pdf', '1920-A-Chorus-Line.pdf'),
    'calendar-girls':      ('//TLT-SERVER/TLT Programs/2013-2018/1920 Calendar Girls.pdf', '1920-Calendar-Girls.pdf'),
    'evil-dead':           ('//TLT-SERVER/TLT Programs/2013-2018/1920 Evil Dead.pdf', '1920-Evil-Dead.pdf'),
    'holmes-for-the-holidays': ('//TLT-SERVER/TLT Programs/2013-2018/1920 Holmes.pdf', '1920-Holmes.pdf'),
    'shattering':          ('//TLT-SERVER/TLT Programs/2013-2018/1920 Shattering.pdf', '1920-Shattering.pdf'),
    'twas-the-night-before-christmas': ('//TLT-SERVER/TLT Programs/2013-2018/1920 Twas.pdf', '1920-Twas.pdf'),
    # Terms and Manchurian Candidate were cancelled — no programs printed
}


def set_meta(cur, pid, key, value):
    cur.execute("DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key=%s", (pid, key))
    cur.execute("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (%s,%s,%s)", (pid, key, value))


os.makedirs(PROGRAMS_DEST, exist_ok=True)
c = pymysql.connect(host='127.0.0.1', port=10005, user='root', password='root', database='local')
cur = c.cursor()

linked = 0
for slug, (src, dest_name) in PROGRAMS.items():
    if not os.path.isfile(src):
        print(f"  [missing src] {slug}: {src}")
        continue
    cur.execute("SELECT ID FROM wp_posts WHERE post_name=%s AND post_type='tlt_show'", (slug,))
    r = cur.fetchone()
    if not r:
        print(f"  [no show] {slug}")
        continue
    pid = r[0]
    dest = os.path.join(PROGRAMS_DEST, dest_name)
    if not os.path.exists(dest) or os.path.getsize(dest) != os.path.getsize(src):
        shutil.copy2(src, dest)
        action = 'copied'
    else:
        action = 'exists'
    url = f'/wp-content/uploads/programs/{dest_name}'
    set_meta(cur, pid, 'show_program_pdf_url', url)
    print(f"  [{action}] {slug:<35} -> {url}")
    linked += 1

c.commit()
c.close()
print(f"\nLinked {linked} programs.")
