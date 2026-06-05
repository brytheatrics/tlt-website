"""
Fix galleries the photo-matcher wrongly linked to a same-titled but different
production. For each: move the (correctly-sized) photos to a proper archival
record and clear the gallery from the show that shouldn't have had them.

  Arsenic and Old Lace (2026-27)  <- Arsenic 2001-02   -> new archival 0102
  Annie (2010)                    <- Annie Get Your Gun 2004-05 -> new archival 0405
  Complete Works (2013-14)        <- Complete Works 2002-03     -> new archival 0203
  Six Dance Lessons (2012-13)     <- Six Dance 2009-10          -> new archival 0910
  Scrooge (2014-15)               <- Scrooge 2018-19 (already correctly on
                                     scrooge-the-musical) -> just drop the dup

Idempotent.
"""
import os, json, shutil, time, pymysql
import create_archive_shows as B

DEST = B.DEST_BASE  # uploads/productions

REHOME = [
    { 'wrong_id': 1306, 'old': 'arsenic-and-old-lace',                 'new': 'arsenic-and-old-lace-0102',
      'title': 'Arsenic and Old Lace', 'start': 2001 },
    { 'wrong_id': 1317, 'old': 'annie-2010',                           'new': 'annie-get-your-gun-0405',
      'title': 'Annie Get Your Gun', 'start': 2004 },
    { 'wrong_id': 1151, 'old': 'complete-works-of',                    'new': 'complete-works-of-william-shakespeare-0203',
      'title': 'The Complete Works of William Shakespeare (Abridged)', 'start': 2002 },
    { 'wrong_id': 1330, 'old': 'six-dance-lessons-in-six-weeks-2012',  'new': 'six-dance-lessons-in-six-weeks-0910',
      'title': 'Six Dance Lessons in Six Weeks', 'start': 2009 },
]
REMOVE_ONLY = [
    { 'wrong_id': 1133, 'old': 'scrooge' },  # photos already correct on scrooge-the-musical
]

def gallery_json(slug, title):
    folder = os.path.join( DEST, slug )
    files = sorted( f for f in os.listdir( folder ) if f.lower().endswith( '.jpg' ) )
    return json.dumps( [ { 'url': f"{B.URL_BASE}/{slug}/{f}",
                           'alt': f"{title} - production photo {i+1}", 'caption': '' }
                         for i, f in enumerate( files ) ] )

def main():
    c = pymysql.connect( host='127.0.0.1', port=10005, user='root', password='root', database='local' )
    cur = c.cursor()
    now = time.strftime( '%Y-%m-%d %H:%M:%S' )

    def clear_gallery( pid ):
        cur.execute( "DELETE FROM wp_postmeta WHERE post_id=%s AND meta_key='show_photo_gallery'", (pid,) )

    for r in REHOME:
        old_dir = os.path.join( DEST, r['old'] )
        new_dir = os.path.join( DEST, r['new'] )
        if os.path.isdir( old_dir ):
            if os.path.isdir( new_dir ): shutil.rmtree( new_dir, ignore_errors=True )
            shutil.move( old_dir, new_dir )
        elif not os.path.isdir( new_dir ):
            print( f"  ! no photo folder for {r['old']} — skipping" ); continue
        start = r['start']; end = start + 1
        post_date = f"{start}-09-01 00:00:00"
        pid, action = B.upsert( cur, r['title'], r['new'], post_date, now )
        B.set_meta( cur, pid, 'show_open_date', '' )
        B.set_meta( cur, pid, 'show_close_date', '' )
        B.set_meta( cur, pid, 'show_season_label', f"{start}–{end} Season" )
        B.set_meta( cur, pid, 'show_program_type', 'mainstage' )
        B.set_meta( cur, pid, 'show_photo_gallery', gallery_json( r['new'], r['title'] ) )
        B.assign_season( cur, pid, f"{start}-{end}", f"{start}-{end}" )
        clear_gallery( r['wrong_id'] )
        c.commit()
        n = len( json.loads( gallery_json( r['new'], r['title'] ) ) )
        print( f"  {action:>7} {r['new']:<46} {n} photos | cleared gallery on id {r['wrong_id']}" )

    for r in REMOVE_ONLY:
        d = os.path.join( DEST, r['old'] )
        if os.path.isdir( d ): shutil.rmtree( d, ignore_errors=True )
        clear_gallery( r['wrong_id'] )
        c.commit()
        print( f"  removed  dup folder + gallery for {r['old']} (id {r['wrong_id']})" )

    c.close()
    print( "Done." )

if __name__ == '__main__':
    main()
