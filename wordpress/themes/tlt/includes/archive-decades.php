<?php
/**
 * Archive / decade rendering helpers.
 *
 * Decade-summary posts (slug YYYY-YYYY) store their seasons as
 *   <h2>2005-06</h2><ul><li><p>Show</p></li><li><p><a href="PROGRAM.pdf">Show</a></p></li>…</ul>
 *
 * These helpers parse that body and re-render every season as a uniform
 * "archive-list" of rows — each show getting a [📷 Photos] button when a
 * tlt_show record with photos exists for it, and a [📄 Program] button when a
 * program PDF is known. This is the pattern hand-mocked for 2005-06 on
 * /2000-2010/, generalised so all decade pages look the same.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/** Normalise a show title for matching: lowercase, strip punctuation + leading "the". */
function tlt_archive_norm_title( $t ) {
    $t = strtolower( html_entity_decode( wp_strip_all_tags( (string) $t ), ENT_QUOTES ) );
    $t = preg_replace( '/[^a-z0-9 ]+/', ' ', $t );
    $t = preg_replace( '/^the\s+/', '', $t );
    $t = preg_replace( '/\s+/', ' ', $t );
    return trim( $t );
}

/**
 * Lookup of every published tlt_show, grouped by season-start year.
 * Returns [ startYear => [ ['norm'=>…, 'permalink'=>…, 'has_photos'=>bool, 'program'=>url], … ] ].
 * Cached for the request.
 */
function tlt_archive_show_index() {
    static $idx = null;
    if ( $idx !== null ) return $idx;
    $idx = [];
    $q = new WP_Query( [
        'post_type'      => 'tlt_show',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ] );
    foreach ( $q->posts as $pid ) {
        // Season start year: prefer the tlt_season term, then open date, then label.
        $start = 0;
        foreach ( wp_get_post_terms( $pid, 'tlt_season', [ 'fields' => 'names' ] ) as $tn ) {
            if ( preg_match( '/^(\d{4})/', $tn, $m ) ) { $start = (int) $m[1]; break; }
        }
        if ( ! $start ) {
            $open = get_post_meta( $pid, 'show_open_date', true );
            if ( preg_match( '/^(\d{4})-(\d{2})/', $open, $m ) ) {
                $start = ( (int) $m[2] >= 8 ) ? (int) $m[1] : (int) $m[1] - 1;  // theatre season starts late summer
            }
        }
        if ( ! $start ) {
            $lbl = get_post_meta( $pid, 'show_season_label', true );
            if ( preg_match( '/(\d{4})/', $lbl, $m ) ) $start = (int) $m[1];
        }
        if ( ! $start ) continue;
        $gal = get_post_meta( $pid, 'show_photo_gallery', true );
        $has_photos = $gal && $gal !== '[]' && ! empty( json_decode( $gal, true ) );
        $idx[ $start ][] = [
            'norm'       => tlt_archive_norm_title( get_the_title( $pid ) ),
            'permalink'  => get_permalink( $pid ),
            'has_photos' => (bool) $has_photos,
            'program'    => get_post_meta( $pid, 'show_program_pdf_url', true ) ?: '',
        ];
    }
    return $idx;
}

/** Find the tlt_show record (if any) for a show name within a given season-start year. */
function tlt_archive_match( $name, $start ) {
    $idx = tlt_archive_show_index();
    if ( empty( $idx[ $start ] ) ) return null;
    $n = tlt_archive_norm_title( $name );
    if ( $n === '' ) return null;
    // Exact match first.
    foreach ( $idx[ $start ] as $rec ) {
        if ( $rec['norm'] === $n ) return $rec;
    }
    // Fall back to a containment match (Job B titles are sometimes abbreviated,
    // e.g. "Over the River" vs "Over the River and Through the Woods"). Guard on
    // length so short words don't collide.
    foreach ( $idx[ $start ] as $rec ) {
        $a = $rec['norm'];
        if ( strlen( $a ) >= 5 && strlen( $n ) >= 5 &&
             ( strpos( $a, $n ) === 0 || strpos( $n, $a ) === 0 ) ) {
            return $rec;
        }
    }
    return null;
}

/**
 * Parse a decade post body into [ intro_html, sections ] where each section is
 * [ 'header' => '2005-06', 'shows' => [ ['name'=>…, 'pdf'=>…], … ] ].
 * Handles both plain <li> bullets and the already-converted archive-row markup.
 */
function tlt_parse_decade_body( $raw ) {
    $raw = trim( (string) $raw );
    if ( $raw === '' ) return [ '', [] ];
    $first = stripos( $raw, '<h2' );
    if ( $first === false ) return [ $raw, [] ];
    $intro = $first > 0 ? trim( substr( $raw, 0, $first ) ) : '';
    // The migrated decade posts often open with empty Squarespace wrapper
    // <div>s whose closing tags sit after the last season (and get dropped when
    // we extract sections) — rendering them would leak unclosed divs that wrap
    // the footer. Blank an intro with no real text; balance one that has text.
    if ( trim( wp_strip_all_tags( $intro ) ) === '' ) {
        $intro = '';
    } else {
        $intro = force_balance_tags( $intro );
    }
    $body  = substr( $raw, $first );
    $sections = [];
    $parts = preg_split( '/<h2[^>]*>(.*?)<\/h2>/s', $body, -1, PREG_SPLIT_DELIM_CAPTURE );
    for ( $i = 1; $i < count( $parts ); $i += 2 ) {
        $header = trim( wp_strip_all_tags( $parts[ $i ] ) );
        $chunk  = $parts[ $i + 1 ] ?? '';
        $shows  = [];
        if ( preg_match_all( '/<li[^>]*>(.*?)<\/li>/s', $chunk, $items ) ) {
            foreach ( $items[1] as $li ) {
                $pdf = '';
                if ( preg_match( '/href="([^"]+\.pdf)"/i', $li, $hm ) ) $pdf = $hm[1];
                // Name: prefer an explicit archive-row__title span; else strip tags
                // and drop any trailing "Program"/"Photos" button labels.
                if ( preg_match( '/archive-row__title[^>]*>(.*?)<\/span>/s', $li, $tm ) ) {
                    $name = trim( wp_strip_all_tags( $tm[1] ) );
                } else {
                    $name = trim( wp_strip_all_tags( $li ) );
                    $name = trim( preg_replace( '/\s*(Program|Photos)\s*$/i', '', $name ) );
                }
                if ( $name === '' ) continue;
                if ( stripos( $name, 'theatre was organized' ) !== false ) continue;
                $shows[] = [ 'name' => $name, 'pdf' => $pdf ];
            }
        }
        if ( $shows ) $sections[] = [ 'header' => $header, 'shows' => $shows ];
    }
    return [ $intro, $sections ];
}

/** Render one season's shows as an <ul class="archive-list"> with photo/program buttons. */
function tlt_render_archive_list( $header, $shows ) {
    $start = preg_match( '/^(\d{4})/', $header, $m ) ? (int) $m[1] : 0;
    $out = '<ul class="archive-list">';
    foreach ( $shows as $sh ) {
        $name = $sh['name'];
        $pdf  = $sh['pdf'] ?? '';
        $rec  = tlt_archive_match( $name, $start );
        $photos = ( $rec && $rec['has_photos'] ) ? $rec['permalink'] : '';
        if ( ! $pdf && $rec && $rec['program'] ) $pdf = $rec['program'];

        $btns = '';
        if ( $photos ) {
            $btns .= '<a href="' . esc_url( $photos ) . '" class="archive-btn archive-btn--photos">'
                   . '<span class="archive-btn__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" height="1.1em" width="1.1em" viewBox="0 -960 960 960" fill="currentColor"><path d="M480-260q75 0 127.5-52.5T660-440q0-75-52.5-127.5T480-620q-75 0-127.5 52.5T300-440q0 75 52.5 127.5T480-260Zm0-80q-42 0-71-29t-29-71q0-42 29-71t71-29q42 0 71 29t29 71q0 42-29 71t-71 29ZM160-120q-33 0-56.5-23.5T80-200v-480q0-33 23.5-56.5T160-760h126l74-80h240l74 80h126q33 0 56.5 23.5T880-680v480q0 33-23.5 56.5T800-120H160Zm0-80h640v-480H638l-73-80H395l-73 80H160v480Zm320-240Z"/></svg></span><span>Photos</span></a>';
        }
        if ( $pdf ) {
            $btns .= '<a href="' . esc_url( $pdf ) . '" target="_blank" rel="noopener" class="archive-btn archive-btn--program">'
                   . '<span class="archive-btn__icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" height="1.1em" width="1.1em" viewBox="0 -960 960 960" fill="currentColor"><path d="M280-280h280v-80H280v80Zm0-160h400v-80H280v80Zm0-160h400v-80H280v80Zm-80 480q-33 0-56.5-23.5T120-200v-560q0-33 23.5-56.5T200-840h560q33 0 56.5 23.5T840-760v560q0 33-23.5 56.5T760-120H200Zm0-80h560v-560H200v560Zm0-560v560-560Z"/></svg></span><span>Program</span></a>';
        }
        // Link the title to the show page whenever a record exists (even with no
        // photos) — the page still has cast, director, synopsis, etc.
        $page_url = $rec ? $rec['permalink'] : '';
        $title = $page_url
            ? '<a href="' . esc_url( $page_url ) . '" class="archive-row__title">' . esc_html( $name ) . '</a>'
            : '<span class="archive-row__title">' . esc_html( $name ) . '</span>';
        $out .= '<li class="archive-row">' . $title . '<span class="archive-row__btns">' . $btns . '</span></li>';
    }
    return $out . '</ul>';
}

/**
 * Build decade sections from tlt_show RECORDS (one per season term in the
 * decade) — used for modern decades like 2010-2020 whose post body has no
 * legacy bullet content. Returns the same [ ['header','shows'=>[['name','pdf']]] ]
 * shape tlt_render_archive_list expects, so each show still gets its buttons.
 */
function tlt_decade_record_sections( $start, $end ) {
    $sections = [];
    for ( $sy = $start; $sy < $end; $sy++ ) {
        $name = sprintf( '%d-%d', $sy, $sy + 1 );
        $term = get_term_by( 'name', $name, 'tlt_season' );
        if ( ! $term ) continue;
        $q = new WP_Query( [
            'post_type'      => 'tlt_show',
            'posts_per_page' => -1,
            'tax_query'      => [ [ 'taxonomy' => 'tlt_season', 'field' => 'term_id', 'terms' => $term->term_id ] ],
            // The decade archive lists season productions — exclude special-event
            // types (murder-mystery dinners, ClubTLT, education, special). Shows
            // with no program_type set are kept (treated as mainstage).
            'meta_query'     => [
                'relation' => 'OR',
                [ 'key' => 'show_program_type', 'value' => [ 'murder_mystery_dinner', 'club_tlt', 'education', 'special' ], 'compare' => 'NOT IN' ],
                [ 'key' => 'show_program_type', 'compare' => 'NOT EXISTS' ],
            ],
            'no_found_rows'  => true,
        ] );
        // Sort in PHP — dated shows by open_date ascending, undated ones (archival
        // season-label records) last by title. WP_Query's meta-sort over an OR
        // meta_query is unreliable, so we order here instead.
        $items = [];
        foreach ( $q->posts as $p ) {
            $items[] = [ 'p' => $p, 'open' => get_post_meta( $p->ID, 'show_open_date', true ) ];
        }
        wp_reset_postdata();
        usort( $items, function ( $a, $b ) {
            if ( $a['open'] && $b['open'] ) return strcmp( $a['open'], $b['open'] );
            if ( $a['open'] ) return -1;
            if ( $b['open'] ) return 1;
            return strcmp( get_the_title( $a['p'] ), get_the_title( $b['p'] ) );
        } );
        $shows = [];
        foreach ( $items as $it ) {
            $shows[] = [ 'name' => get_the_title( $it['p'] ), 'pdf' => get_post_meta( $it['p']->ID, 'show_program_pdf_url', true ) ];
        }
        if ( $shows ) $sections[] = [ 'header' => $name, 'shows' => $shows ];
    }
    return $sections;
}

/** Full decade body -> intro + per-season headers and archive-lists. */
function tlt_render_decade_sections( $raw ) {
    list( $intro, $sections ) = tlt_parse_decade_body( $raw );
    $html = '';
    if ( $intro ) {
        $html .= '<div class="decade-intro" style="max-width:780px;margin:0 auto 2rem;text-align:center;color:var(--color-muted)">'
               . apply_filters( 'the_content', $intro ) . '</div>';
    }
    foreach ( $sections as $sec ) {
        $html .= '<section class="archive-season" style="margin:2rem auto;max-width:720px">'
               . '<h2 style="text-align:center;color:var(--color-accent);margin-bottom:1rem">' . esc_html( $sec['header'] ) . '</h2>'
               . tlt_render_archive_list( $sec['header'], $sec['shows'] )
               . '</section>';
    }
    return $html;
}
