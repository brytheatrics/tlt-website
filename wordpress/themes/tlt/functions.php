<?php
/**
 * TLT Theme — functions.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Disable the in-browser Theme & Plugin File Editors. A typo there can
// white-screen the live site, and we edit code in the repo anyway. Defined here
// (rather than wp-config) so it ships with the theme to every environment.
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
    define( 'DISALLOW_FILE_EDIT', true );
}

// ACF field groups for page templates (Designed, Campaign, etc.)
require_once __DIR__ . '/includes/acf-page-templates.php';

// Flex content library — Gutenberg block patterns (Prose, Figure, Pull-quote, etc.)
require_once __DIR__ . '/includes/block-patterns.php';

// Decade-archive rendering (parse decade posts -> per-season photo/program buttons)
require_once __DIR__ . '/includes/archive-decades.php';

// Calendar data layer (performances + auditions + events -> /calendar/)
require_once __DIR__ . '/includes/calendar.php';

/**
 * Pre-launch date override. The TLT site doesn't go live until after Bedroom
 * Farce closes (Jul 26, 2026). Until then, simulate that the site is already
 * post-launch by forcing "today" to a date in the announce-window for the
 * 2026-2027 season. This drives the hero, season grid, splash logic, status
 * badges, /shows/, etc. as if it were Aug 1, 2026.
 *
 * REMOVE THIS LINE WHEN THE SITE GOES LIVE (or change date as you want to
 * preview different states). All other code reads it via tlt_today().
 */
// TLT_AS_OF is no longer forced — tlt_today() will use the real current
// date. For per-user preview you can still hit ?as_of=YYYY-MM-DD (sets a
// 24h cookie; ?as_of=clear drops it), or uncomment the line below to
// force everyone to a fixed date site-wide.
// if ( ! defined( 'TLT_AS_OF' ) ) define( 'TLT_AS_OF', '2027-06-07' );

add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', [ 'search-form', 'gallery', 'caption', 'script', 'style' ] );
    add_theme_support( 'custom-logo' );
    register_nav_menus( [
        'primary' => 'Primary Menu',
        'footer_visit' => 'Footer — Visit',
        'footer_about' => 'Footer — About',
        'footer_get_involved' => 'Footer — Get Involved',
        'topbar' => 'Top Bar (Donate / Volunteer / Login)',
    ] );
} );

add_action( 'wp_enqueue_scripts', function () {
    // Google Font: Jost — closest Google Fonts match to TLT's print font Century Gothic.
    // Geometric humanist sans-serif; loads 400/500/600/700 for body + display + headings.
    wp_enqueue_style( 'tlt-google-fonts',
        'https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700&display=swap',
        [],
        null
    );
    // Use filemtime() as version so any save instantly busts browser caches.
    $style_path = get_stylesheet_directory() . '/style.css';
    $ver = file_exists( $style_path ) ? filemtime( $style_path ) : '1.0.0';
    wp_enqueue_style( 'tlt-style', get_stylesheet_uri(), [ 'tlt-google-fonts' ], $ver );
} );

/**
 * Parse a show_cast string ("Actor as Character, Actor as Character, …") into
 * [ ['actor'=>…, 'role'=>…], … ]. Commas separate cast members; " as " splits
 * actor from role; an entry with no " as " is a name-only credit (ensemble/revue).
 */
function tlt_parse_cast( $str ) {
    $str = (string) $str;
    // Protect suffix commas ("DuWayne Andrews, Jr.", "Martin Luther King, Jr.",
    // "Sammy Davis III") so a name/role suffix isn't read as a cast separator.
    // Stash the comma as \x01, split on the real separators, then restore it.
    $str = preg_replace( '/,\s*(Jr|Sr|II|III|IV|Ph\.?\s?D|M\.?\s?D|Esq)(\.?)/i', "\x01$1$2", $str );
    $out = [];
    foreach ( explode( ',', $str ) as $entry ) {
        $entry = trim( str_replace( "\x01", ', ', $entry ) );
        if ( $entry === '' ) continue;
        if ( preg_match( '/^(.*?)\s+as\s+(.+)$/i', $entry, $m ) ) {
            $out[] = [ 'actor' => trim( $m[1] ), 'role' => trim( $m[2] ) ];
        } else {
            $out[] = [ 'actor' => $entry, 'role' => '' ];
        }
    }
    return $out;
}

/**
 * Render a photo slideshow (arrows, dots, counter, keyboard, swipe) from an
 * array of [ 'url'=>…, 'alt'=>…, 'caption'=>… ] items. Shared by the show page
 * and content pages (e.g. /clubtlt/). The CSS + JS are emitted only once per
 * request even if called for multiple galleries.
 */
function tlt_render_slideshow( $images, $heading = '' ) {
    $images = array_values( array_filter( (array) $images, function ( $g ) {
        return is_array( $g ) && ! empty( $g['url'] );
    } ) );
    if ( ! $images ) return '';
    static $assets_done = false;
    $n = count( $images );
    ob_start(); ?>
    <section class="show-photo-gallery" style="margin-top:3rem">
      <?php if ( $heading ) : ?><h2 class="section-heading"><?php echo esc_html( $heading ); ?></h2><?php endif; ?>
      <div class="show-slideshow" data-count="<?php echo $n; ?>">
        <div class="show-slideshow__viewport">
          <?php foreach ( $images as $i => $g ) :
            $cap = isset( $g['caption'] ) ? $g['caption'] : ''; ?>
            <figure class="show-slide<?php echo $i === 0 ? ' is-active' : ''; ?>">
              <img src="<?php echo esc_url( $g['url'] ); ?>" alt="<?php echo isset( $g['alt'] ) ? esc_attr( $g['alt'] ) : ''; ?>" loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>">
              <?php if ( $cap ) : ?><figcaption><?php echo esc_html( $cap ); ?></figcaption><?php endif; ?>
            </figure>
          <?php endforeach; ?>
        </div>
        <button type="button" class="show-slideshow__nav show-slideshow__nav--prev" aria-label="Previous photo">&#8592;</button>
        <button type="button" class="show-slideshow__nav show-slideshow__nav--next" aria-label="Next photo">&#8594;</button>
        <div class="show-slideshow__dots" role="tablist" aria-label="Slide selector">
          <?php foreach ( $images as $i => $g ) : ?>
            <button type="button" class="show-slideshow__dot<?php echo $i === 0 ? ' is-active' : ''; ?>" role="tab" aria-label="Photo <?php echo $i + 1; ?>" data-index="<?php echo $i; ?>"></button>
          <?php endforeach; ?>
        </div>
        <div class="show-slideshow__counter"><span class="show-slideshow__current">1</span> / <?php echo $n; ?></div>
      </div>
    </section>
    <?php if ( ! $assets_done ) : $assets_done = true; ?>
      <style>
        .show-slideshow { position: relative; max-width: 1100px; margin: 0 auto; background: #000; border-radius: 6px; overflow: hidden; }
        .show-slideshow__viewport { position: relative; aspect-ratio: 3/2; }
        .show-slide { position: absolute; inset: 0; margin: 0; opacity: 0; transition: opacity 0.4s ease; display: flex; align-items: center; justify-content: center; background: #000; }
        .show-slide.is-active { opacity: 1; z-index: 1; }
        .show-slide img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .show-slide figcaption { position: absolute; left: 0; right: 0; bottom: 0; padding: 0.85rem 1rem; background: linear-gradient(to top, rgba(0,0,0,0.75), transparent); color: #fff; font-size: 0.9rem; text-align: center; }
        .show-slideshow__nav { position: absolute; top: 50%; transform: translateY(-50%); z-index: 5; background: rgba(255,255,255,0.85); border: 0; width: 44px; height: 44px; border-radius: 50%; font-size: 1.2rem; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #000; transition: background 0.15s; box-shadow: 0 2px 8px rgba(0,0,0,0.3); }
        .show-slideshow__nav:hover { background: #fff; }
        .show-slideshow__nav--prev { left: 1rem; }
        .show-slideshow__nav--next { right: 1rem; }
        .show-slideshow__dots { position: absolute; left: 0; right: 0; bottom: 0.6rem; display: flex; justify-content: center; gap: 0.4rem; z-index: 5; flex-wrap: wrap; padding: 0 1rem; max-width: 80%; margin: 0 auto; }
        .show-slideshow__dot { width: 8px; height: 8px; border-radius: 50%; border: 0; background: rgba(255,255,255,0.45); cursor: pointer; padding: 0; transition: background 0.15s, transform 0.15s; }
        .show-slideshow__dot.is-active { background: #fff; transform: scale(1.3); }
        .show-slideshow__dot:hover { background: rgba(255,255,255,0.75); }
        .show-slideshow__counter { position: absolute; top: 1rem; right: 1rem; z-index: 5; background: rgba(0,0,0,0.55); color: #fff; padding: 0.25rem 0.65rem; border-radius: 999px; font-size: 0.78rem; font-weight: 600; letter-spacing: 0.05em; }
        @media (max-width: 600px) {
          .show-slideshow__viewport { aspect-ratio: 4/3; }
          .show-slideshow__nav { width: 36px; height: 36px; }
          .show-slideshow__nav--prev { left: 0.4rem; }
          .show-slideshow__nav--next { right: 0.4rem; }
          .show-slideshow__dots { display: none; }
        }
      </style>
      <script>
        (function () {
          document.querySelectorAll('.show-slideshow').forEach(function (box) {
            var slides = box.querySelectorAll('.show-slide');
            var dots   = box.querySelectorAll('.show-slideshow__dot');
            var counter= box.querySelector('.show-slideshow__current');
            if (!slides.length) return;
            var idx = 0;
            function go(n) {
              idx = (n + slides.length) % slides.length;
              slides.forEach(function (s, i) { s.classList.toggle('is-active', i === idx); });
              dots.forEach(function (d, i) { d.classList.toggle('is-active', i === idx); });
              if (counter) counter.textContent = (idx + 1);
            }
            var p = box.querySelector('.show-slideshow__nav--prev');
            var nx = box.querySelector('.show-slideshow__nav--next');
            if (p)  p.addEventListener('click', function () { go(idx - 1); });
            if (nx) nx.addEventListener('click', function () { go(idx + 1); });
            dots.forEach(function (d, i) { d.addEventListener('click', function () { go(i); }); });
            document.addEventListener('keydown', function (e) {
              if (e.key === 'ArrowLeft')  go(idx - 1);
              if (e.key === 'ArrowRight') go(idx + 1);
            });
            var startX = 0;
            box.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
            box.addEventListener('touchend', function (e) {
              var dx = e.changedTouches[0].clientX - startX;
              if (Math.abs(dx) > 50) go(idx + (dx < 0 ? 1 : -1));
            });
          });
        })();
      </script>
    <?php endif;
    return ob_get_clean();
}

/**
 * Helper to format date ranges like "Oct 24 – Nov 9, 2025"
 */
/**
 * Normalise any YouTube/Vimeo URL into an iframe-embeddable URL.
 *
 * YouTube and Vimeo refuse to render their watch pages in an <iframe> (you
 * get "refused to connect"). They both require dedicated /embed/ URLs.
 * Chris will paste whatever URL he copied from the browser bar — short, share,
 * watch, shorts, embed — so we normalise here in one place.
 *
 * Returns the original URL unchanged if it's not a recognised pattern (e.g.
 * already an /embed/ URL, or a non-YouTube/Vimeo URL).
 */
function tlt_video_embed_url( $url ) {
    $url = trim( (string) $url );
    if ( $url === '' ) return '';

    // Strip everything after a query separator on YouTube share links — they
    // include ?si=… tracking IDs that aren't part of the video id and can
    // sneak into the regex match unless we anchor cleanly.
    // (We still preserve query params on the rebuilt /embed/ URL only when
    // they're meaningful, e.g. ?t= start time — handled below.)
    $parsed_query = [];
    if ( strpos( $url, '?' ) !== false ) {
        $qs = substr( $url, strpos( $url, '?' ) + 1 );
        parse_str( $qs, $parsed_query );
    }

    // YouTube — youtu.be/ID, youtube.com/watch?v=ID, /shorts/ID, /v/ID, /embed/ID
    if ( preg_match( '~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?(?:.+&)?v=|embed/|v/|shorts/))([A-Za-z0-9_-]{6,})~', $url, $m ) ) {
        $id  = $m[1];
        $out = 'https://www.youtube.com/embed/' . $id;
        // Preserve start time when set as ?t=90 or ?start=90.
        $start = isset( $parsed_query['start'] ) ? (int) $parsed_query['start']
               : ( isset( $parsed_query['t'] ) ? (int) $parsed_query['t'] : 0 );
        if ( $start > 0 ) $out .= '?start=' . $start;
        return $out;
    }

    // Vimeo — vimeo.com/ID or player.vimeo.com/video/ID
    if ( preg_match( '~vimeo\.com/(?:video/)?(\d+)~', $url, $m ) ) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }

    return $url;
}

function tlt_format_date_range( $start, $end ) {
    if ( ! $start ) return '';
    $s = strtotime( $start );
    $e = $end ? strtotime( $end ) : null;
    if ( ! $e ) return date( 'F j, Y', $s );
    if ( date( 'Y', $s ) === date( 'Y', $e ) ) {
        if ( date( 'F', $s ) === date( 'F', $e ) ) {
            return date( 'F j', $s ) . ' – ' . date( 'j, Y', $e );
        }
        return date( 'M j', $s ) . ' – ' . date( 'M j, Y', $e );
    }
    return date( 'M j, Y', $s ) . ' – ' . date( 'M j, Y', $e );
}

/**
 * Get featured image with fallback to legacy/external image URL.
 * During migration we stashed the Squarespace hero URL in _thumbnail_external_url
 * so the theme can display it without needing the image to be downloaded yet.
 */
function tlt_show_image_url( $post_id, $size = 'large', $context = '' ) {
    // $context is kept for back-compat (e.g. 'hero'); the image is always the
    // poster — the homepage hero uses uploaded animation layers when present,
    // and the poster as its static fallback.
    $img = get_the_post_thumbnail_url( $post_id, $size );
    if ( $img ) return $img;
    $ext = get_post_meta( $post_id, '_thumbnail_external_url', true );
    if ( $ext ) return $ext;
    $legacy = get_post_meta( $post_id, 'show_legacy_image_url', true );
    return $legacy ?: '';
}

/**
 * Migration redirects: catch old Squarespace URLs and 301-redirect to new ones.
 * Loads the CSV from wp-content/uploads/migration-redirects.csv on first request,
 * caches in transient for performance.
 */
add_action( 'template_redirect', function () {
    if ( ! is_404() && ! ( $_SERVER['REQUEST_URI'] ?? '' ) ) return;
    $req = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
    $req = '/' . trim( $req, '/' );
    if ( $req === '/' ) return;

    $map = wp_cache_get( 'tlt_redirects', 'tlt' );
    if ( ! is_array( $map ) ) {
        // Prefer theme-bundled redirects (git-synced). Fall back to legacy uploads location.
        $csv = get_stylesheet_directory() . '/redirects.csv';
        if ( ! file_exists( $csv ) ) {
            $csv = WP_CONTENT_DIR . '/uploads/migration-redirects.csv';
        }
        $map = [];
        if ( file_exists( $csv ) ) {
            $h = fopen( $csv, 'r' );
            if ( $h ) {
                fgetcsv( $h ); // skip header
                while ( ( $row = fgetcsv( $h ) ) !== false ) {
                    if ( ! isset( $row[0], $row[1] ) ) continue;
                    $src = '/' . trim( $row[0], '/' );
                    $dst = $row[1];
                    if ( $src && $dst && $src !== $dst ) $map[ $src ] = $dst;
                }
                fclose( $h );
            }
        }
        wp_cache_set( 'tlt_redirects', $map, 'tlt', 3600 );
    }

    if ( isset( $map[ $req ] ) ) {
        wp_redirect( $map[ $req ], 301 );
        exit;
    }
}, 1 ); // priority 1 so we run before normal 404 handling

/**
 * Alias pages — slugs that just forward visitors to an external URL instead of
 * rendering content. Centralised so the redirect and the admin "Linked" badge
 * (see tlt_page_editability_for_post) stay in sync. Add future aliases here.
 */
function tlt_page_redirects() {
    return [
        'donate'    => 'https://tlt.ludus.com/donate.php',
        'volunteer' => 'https://tlt.ludus.com/volunteer',
        // Interim: /about/ just forwards to History until a real About page is built (task #18).
        'about'     => home_url( '/history/' ),
    ];
}

/**
 * Are auditions currently open for a given show? Matches the /auditions/
 * page's own retire-in-21-days logic — returns true when there is at least
 * one audition date still in the future AND the cast hasn't been posted yet.
 * Used by single-tlt_show.php to show an "Audition for this show" button.
 */
function tlt_show_auditions_open( $show_id ) {
    $today = function_exists( 'tlt_today' ) ? tlt_today() : current_time( 'Y-m-d' );
    $raw   = get_post_meta( $show_id, 'show_audition_schedule', true );
    if ( ! $raw ) return false;
    $sched = function_exists( 'tlt_parse_schedule' ) ? tlt_parse_schedule( $raw ) : [];
    if ( ! $sched ) return false;
    $dates = array_values( array_filter( array_map( function ( $s ) { return $s['date']; }, $sched ) ) );
    if ( ! $dates ) return false;
    sort( $dates );
    // Cast posted → auditions no longer open.
    if ( trim( (string) get_post_meta( $show_id, 'show_cast', true ) ) !== '' ) return false;
    // A future audition date must still exist.
    foreach ( $dates as $d ) if ( $d >= $today ) return true;
    return false;
}

/**
 * Auto-derive the auditions shown on /auditions/ from show fields — no manual
 * status. A show appears if it has an audition schedule and we're within ~3
 * weeks of its last audition date. Status derives from the schedule + Casting
 * Manager link + cast list:
 *   - cast posted        → 'cast'   ("This show has been cast")
 *   - future date + link → 'signup' (featured up top + in the list)
 *   - future date, no link → 'coming' (list only)
 *   - dates passed, no cast yet → 'pending' (casting in progress)
 * Returns entries sorted by next/first audition date.
 */
function tlt_get_audition_shows() {
    $today = function_exists( 'tlt_today' ) ? tlt_today() : current_time( 'Y-m-d' );
    $shows = get_posts( [
        'post_type'      => 'tlt_show',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [ [ 'key' => 'show_audition_schedule', 'value' => '', 'compare' => '!=' ] ],
    ] );
    $out = [];
    foreach ( $shows as $show ) {
        $raw   = get_post_meta( $show->ID, 'show_audition_schedule', true );
        $sched = function_exists( 'tlt_parse_schedule' ) ? tlt_parse_schedule( $raw ) : [];
        if ( ! $sched ) continue;
        $dates = array_values( array_filter( array_map( function ( $s ) { return $s['date']; }, $sched ) ) );
        if ( ! $dates ) continue;
        sort( $dates );
        $first = $dates[0];
        $last  = end( $dates );
        // Retire ~3 weeks after the last audition date (cast or not).
        $retire = date( 'Y-m-d', strtotime( $last . ' +21 days' ) );
        if ( $retire < $today ) continue;

        $has_cast = trim( (string) get_post_meta( $show->ID, 'show_cast', true ) ) !== '';
        $signup   = get_post_meta( $show->ID, 'show_audition_signup_url', true );
        $next = '';
        foreach ( $dates as $d ) { if ( $d >= $today ) { $next = $d; break; } }

        if ( $has_cast )            $status = 'cast';
        elseif ( $next && $signup ) $status = 'signup';
        elseif ( $next )            $status = 'coming';
        else                        $status = 'pending';

        $out[] = [
            'show'       => $show,
            'title'      => get_the_title( $show ),
            'director'   => get_post_meta( $show->ID, 'show_director', true ),
            'blurb'      => get_post_meta( $show->ID, 'show_audition_blurb', true ),
            'signup_url' => $signup,
            'schedule'   => $sched,
            'next'       => $next,
            'first'      => $first,
            'status'     => $status,
            'featured'   => ( $status === 'signup' ),
        ];
    }
    usort( $out, function ( $a, $b ) {
        return strcmp( ( $a['next'] ?: $a['first'] ), ( $b['next'] ?: $b['first'] ) );
    } );
    return $out;
}

/**
 * Parse the Recorded Programs "video sections" textarea into the same shape the
 * template used to read from JSON: [ ['heading','intro','videos'=>[['url','caption']]] ].
 * Format: "## Heading" starts a section; "> text" sets its intro; "url | caption"
 * adds a video.
 */
function tlt_parse_video_sections( $text ) {
    $sections = [];
    $cur = null;
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
        $line = trim( $line );
        if ( $line === '' ) continue;
        if ( strpos( $line, '## ' ) === 0 ) {
            if ( $cur ) $sections[] = $cur;
            $cur = [ 'heading' => trim( substr( $line, 3 ) ), 'intro' => '', 'videos' => [] ];
            continue;
        }
        if ( $cur === null ) $cur = [ 'heading' => '', 'intro' => '', 'videos' => [] ];
        if ( strpos( $line, '> ' ) === 0 ) { $cur['intro'] = trim( substr( $line, 2 ) ); continue; }
        $parts = array_map( 'trim', explode( '|', $line, 2 ) );
        if ( $parts[0] !== '' ) $cur['videos'][] = [ 'url' => $parts[0], 'caption' => $parts[1] ?? '' ];
    }
    if ( $cur ) $sections[] = $cur;
    return $sections;
}

/**
 * Parse a "card" textarea into [ ['heading','body'] ]. A line starting with
 * "## " begins a card; following lines are its body (joined with <br>). Used by
 * the Visit accessibility/lobby card grids.
 */
function tlt_parse_heading_cards( $text ) {
    $cards = []; $cur = null;
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
        $line = rtrim( $line );
        if ( strpos( ltrim( $line ), '## ' ) === 0 ) {
            if ( $cur ) $cards[] = $cur;
            $cur = [ 'heading' => trim( substr( ltrim( $line ), 3 ) ), 'body' => [] ];
            continue;
        }
        if ( $cur === null ) continue;
        if ( trim( $line ) === '' ) continue;
        $cur['body'][] = trim( $line );
    }
    if ( $cur ) $cards[] = $cur;
    // Join body lines with <br>; do NOT escape here — templates render via
    // wp_kses_post so inline links/lists in a card body survive. Put any
    // multi-line HTML (e.g. a <ul>) on a single line in the source.
    foreach ( $cards as &$c ) $c['body'] = implode( '<br>', $c['body'] );
    return $cards;
}

/**
 * Parse the Visit restaurant list. One per line, pipe-delimited:
 * Name | URL | Distance | tier(close|normal|far) | Tags | Blurb
 */
function tlt_parse_visit_restaurants( $text ) {
    $out = [];
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
        $line = trim( $line );
        if ( $line === '' ) continue;
        $p = array_map( 'trim', explode( '|', $line ) );
        if ( $p[0] === '' ) continue;
        $out[] = [
            'name'  => $p[0],
            'url'   => $p[1] ?? '',
            'dist'  => $p[2] ?? '',
            'tier'  => $p[3] ?? 'normal',
            'tags'  => $p[4] ?? '',
            'blurb' => $p[5] ?? '',
        ];
    }
    return $out;
}

/**
 * Parse the Education Programs/Policies textarea into [ ['title','link_url','body'] ].
 * Format: "## Title" (optionally "## Title | /link") starts an entry; following
 * lines are the body (raw HTML preserved, rendered via wp_kses_post). Policies
 * just ignore link_url.
 */
function tlt_parse_edu_list( $text ) {
    if ( is_array( $text ) ) return $text; // already structured (default-array fallback)
    $items = []; $cur = null;
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
        if ( strpos( ltrim( $line ), '## ' ) === 0 ) {
            if ( $cur ) $items[] = $cur;
            $head  = trim( substr( ltrim( $line ), 3 ) );
            $parts = array_map( 'trim', explode( '|', $head, 2 ) );
            $cur = [ 'title' => $parts[0], 'link_url' => $parts[1] ?? '', 'body' => [] ];
            continue;
        }
        if ( $cur === null ) continue;
        $cur['body'][] = $line;
    }
    if ( $cur ) $items[] = $cur;
    foreach ( $items as &$it ) $it['body'] = trim( implode( "\n", $it['body'] ) );
    return $items;
}

/** Parse the partner-logos textarea: "Image URL | Alt | Link" per line. */
function tlt_parse_partner_logos( $text ) {
    $out = [];
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
        $line = trim( $line );
        if ( $line === '' ) continue;
        $p = array_map( 'trim', explode( '|', $line ) );
        if ( $p[0] !== '' ) $out[] = [ 'url' => $p[0], 'alt' => $p[1] ?? '', 'link' => $p[2] ?? '' ];
    }
    return $out;
}

/**
 * All published pages assigned a given template, newest first. Used by the
 * Job Openings / Press listings to auto-build their cards from the detail
 * pages instead of a hardcoded array.
 */
function tlt_pages_using_template( $template, $args = [] ) {
    return get_posts( array_merge( [
        'post_type'   => 'page',
        'post_status' => 'publish',
        'numberposts' => -1,
        'meta_key'    => '_wp_page_template',
        'meta_value'  => $template,
        'orderby'     => 'date',
        'order'       => 'DESC',
    ], $args ) );
}
add_action( 'template_redirect', function () {
    if ( ! is_page() ) return;
    $slug      = get_post_field( 'post_name', get_queried_object_id() );
    $redirects = tlt_page_redirects();
    if ( isset( $redirects[ $slug ] ) ) {
        wp_redirect( $redirects[ $slug ], 302 );
        exit;
    }
} );

/**
 * Allow /splash/ to bypass the header (it has its own takeover layout)
 */
add_filter( 'body_class', function ( $classes ) {
    if ( is_page( 'splash' ) || is_page_template( 'page-splash.php' ) ) {
        $classes[] = 'splash-page';
    }
    if ( is_page( 'home' ) ) {
        $classes[] = 'home-page';
    }
    return $classes;
} );

/**
 * Site search: include shows + pages + posts; exclude promotions + attachments.
 */
add_action( 'pre_get_posts', function ( $query ) {
    if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) return;
    $query->set( 'post_type', [ 'tlt_show', 'page', 'post' ] );
} );

/**
 * "This Season" menu item — rewrite the URL on render so it always points at
 * the current season's archive (/seasons/YYYY-YYYY/), not the static /shows/.
 * Auto-tracks as seasons change; falls back to /shows/ if no current season.
 *
 * "Next Season" menu item — same idea but points at whichever season is queued
 * up AFTER the current one. Auto-hides between seasons (nothing announced yet).
 */
add_filter( 'wp_nav_menu_objects', function ( $items ) {
    $current = function_exists( 'tlt_get_current_season_term' ) ? tlt_get_current_season_term() : null;
    $next    = function_exists( 'tlt_get_next_season_term' )    ? tlt_get_next_season_term()    : null;
    $out = [];
    foreach ( $items as $item ) {
        $t = strtolower( trim( wp_strip_all_tags( $item->title ) ) );
        if ( $t === 'this season' ) {
            if ( $current && ! is_wp_error( $current ) ) {
                $link = get_term_link( $current );
                if ( $link && ! is_wp_error( $link ) ) $item->url = $link;
            }
        } elseif ( $t === 'next season' ) {
            if ( ! $next || is_wp_error( $next ) ) {
                continue; // no next season → hide the item entirely
            }
            $link = get_term_link( $next );
            if ( ! $link || is_wp_error( $link ) ) continue;
            $item->url = $link;
        }
        $out[] = $item;
    }
    return $out;
} );

/**
 * Season archive (/seasons/<slug>/) and main show archive (/shows/) need shows
 * ordered chronologically by show_open_date. Without this, WP defaults to
 * post_date DESC and the season grid looks shuffled.
 */
add_action( 'pre_get_posts', function ( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) return;
    if ( $query->is_tax( 'tlt_season' ) || ( $query->is_post_type_archive( 'tlt_show' ) ) ) {
        $query->set( 'meta_key', 'show_open_date' );
        $query->set( 'orderby', 'meta_value' );
        $query->set( 'order', 'ASC' );
        $query->set( 'posts_per_page', -1 ); // all shows in a season fit on one page
    }
} );

/* ===================================================================
 * Customizer — Content settings only (brand controls stay in CSS vars)
 * Adds editable fields for logo (handled by core), address, phone,
 * mission text, vision text, land acknowledgement, social media URLs.
 * =================================================================== */

add_action( 'customize_register', function ( $wp_customize ) {

    // --- Contact info section ---
    $wp_customize->add_section( 'tlt_contact', [
        'title'    => 'Contact Information',
        'priority' => 30,
    ] );

    $contact_fields = [
        'tlt_address_street'  => [ 'Street Address',  '210 N "I" Street' ],
        'tlt_address_city'    => [ 'City',            'Tacoma' ],
        'tlt_address_state'   => [ 'State',           'WA' ],
        'tlt_address_zip'     => [ 'ZIP',             '98403' ],
        'tlt_phone'           => [ 'Phone',           '(253) 272-2281' ],
        'tlt_email_general'   => [ 'General Email',   'info@tacomalittletheatre.com' ],
        'tlt_email_boxoffice' => [ 'Box Office Email','boxoffice@tacomalittletheatre.com' ],
        'tlt_federal_id'      => [ 'Federal Tax ID',  '91-0485763' ],
    ];
    foreach ( $contact_fields as $key => [ $label, $default ] ) {
        $wp_customize->add_setting( $key, [ 'default' => $default, 'sanitize_callback' => 'sanitize_text_field' ] );
        $wp_customize->add_control( $key, [
            'label'   => $label,
            'section' => 'tlt_contact',
            'type'    => 'text',
        ] );
    }

    // --- Footer mission/vision/land acknowledgement ---
    $wp_customize->add_section( 'tlt_mission', [
        'title'    => 'Mission / Vision / Land Acknowledgement',
        'priority' => 35,
    ] );

    $mission_fields = [
        'tlt_mission_text'      => [ 'Mission Statement', 'Providing live theatre and education programs that inspire through stories reflecting the vibrancy of our diverse community.', 'textarea' ],
        'tlt_vision_text'       => [ 'Vision Statement',  'TLT enriches lives by providing opportunities for equitable inclusion and representation. Our goal is to ensure everyone — regardless of identity, background or personal experience — belongs at TLT.', 'textarea' ],
        'tlt_land_ack_english'  => [ 'Land Acknowledgement (English)', 'Tacoma Little Theatre recognizes that they teach and perform on Indigenous land: the traditional homelands of the Puyallup people.', 'textarea' ],
        'tlt_land_ack_lushootseed' => [ 'Land Acknowledgement (Lushootseed)', "ʔuk\u{2019}ʷədiitəb ʔuhigʷətəb čəɫ txʷəl tiiɫ ʔa čəɫ ʔal tə swatxʷixʷtxʷəd ʔə tiiɫ puyaləpabš dxʷəsɫaɫlils gʷəl ʔutxʷəlšucidəbs həlgʷəʔ.", 'textarea' ],
    ];
    foreach ( $mission_fields as $key => [ $label, $default, $type ] ) {
        $wp_customize->add_setting( $key, [ 'default' => $default, 'sanitize_callback' => 'wp_kses_post' ] );
        $wp_customize->add_control( $key, [
            'label'   => $label,
            'section' => 'tlt_mission',
            'type'    => $type,
        ] );
    }

    // --- Social media links ---
    $wp_customize->add_section( 'tlt_social', [
        'title'    => 'Social Media',
        'priority' => 40,
    ] );

    $social_fields = [
        'tlt_social_facebook'  => 'Facebook URL',
        'tlt_social_instagram' => 'Instagram URL',
        'tlt_social_youtube'   => 'YouTube URL',
        'tlt_social_twitter'   => 'X (Twitter) URL',
        'tlt_social_tiktok'    => 'TikTok URL',
    ];
    foreach ( $social_fields as $key => $label ) {
        $wp_customize->add_setting( $key, [ 'default' => '', 'sanitize_callback' => 'esc_url_raw' ] );
        $wp_customize->add_control( $key, [
            'label'   => $label,
            'section' => 'tlt_social',
            'type'    => 'url',
        ] );
    }
} );

/**
 * Convenience: get a TLT customizer value with a fallback.
 */
function tlt_setting( $key, $default = '' ) {
    return get_theme_mod( $key, $default );
}

/**
 * Organization-level JSON-LD on every page (in addition to the per-show
 * TheaterEvent schema in single-tlt_show.php). Tells search engines who we
 * are, where, and how to contact us.
 */
add_action( 'wp_footer', function () {
    if ( is_404() ) return;
    $org = [
        '@context' => 'https://schema.org',
        '@type'    => 'PerformingArtsTheater',
        'name'     => get_bloginfo( 'name' ),
        'url'      => home_url( '/' ),
        'logo'     => get_template_directory_uri() . '/assets/logo-1918.svg',
        'telephone' => tlt_setting( 'tlt_phone', '(253) 272-2281' ),
        'email'    => tlt_setting( 'tlt_email_general', 'info@tacomalittletheatre.com' ),
        'address'  => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => tlt_setting( 'tlt_address_street', '210 N "I" Street' ),
            'addressLocality' => tlt_setting( 'tlt_address_city', 'Tacoma' ),
            'addressRegion'   => tlt_setting( 'tlt_address_state', 'WA' ),
            'postalCode'      => tlt_setting( 'tlt_address_zip', '98403' ),
            'addressCountry'  => 'US',
        ],
        'sameAs' => array_values( array_filter( [
            tlt_setting( 'tlt_social_facebook' ),
            tlt_setting( 'tlt_social_instagram' ),
            tlt_setting( 'tlt_social_youtube' ),
            tlt_setting( 'tlt_social_twitter' ),
            tlt_setting( 'tlt_social_tiktok' ),
        ] ) ),
    ];
    echo '<script type="application/ld+json">' . wp_json_encode( $org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
} );

/* ---------------------------------------------------------------------------
 * "System" catch-all admin menu.
 *
 * A low-priority group pinned to the bottom of the sidebar that collects the
 * behind-the-scenes pages staff rarely (if ever) need: the old imported blog
 * Posts, plus core Plugins / Users / Tools / Settings and the ACF field-group
 * editor. They stay fully reachable — we just tuck them out of the daily
 * workflow so the main menu is only the stuff Chris actually uses. Each item
 * keeps its own capability, so it only appears for users allowed to see it.
 *
 * Note: nesting a core menu collapses its sub-pages (e.g. Settings →
 * Permalinks, Reading) from the sidebar. Those remain reachable by direct URL
 * (e.g. /wp-admin/options-permalink.php).
 * ------------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
    global $menu;

    add_menu_page(
        'System',
        'System',
        'edit_posts',
        'tlt-system',
        function () {
            echo '<div class="wrap"><h1>System</h1>'
               . '<p>Behind-the-scenes settings and admin pages. You rarely need these day to day.</p></div>';
        },
        'dashicons-admin-generic',
        99
    );
    // First child shares the parent slug so "Overview" sits first and replaces
    // the auto-generated duplicate label.
    add_submenu_page( 'tlt-system', 'System', 'Overview', 'edit_posts', 'tlt-system' );

    // Old imported blog Posts.
    remove_menu_page( 'edit.php' );
    add_submenu_page( 'tlt-system', 'Blog Posts (old)', 'Blog Posts (old)', 'edit_posts', 'edit.php' );

    // Relocate a stock top-level menu under System, preserving its capability.
    $move = function ( $slug, $label = null ) use ( &$menu ) {
        foreach ( (array) $menu as $item ) {
            if ( isset( $item[2] ) && $item[2] === $slug ) {
                $cap  = isset( $item[1] ) ? $item[1] : 'manage_options';
                $name = $label ?: wp_strip_all_tags( $item[0] );
                remove_menu_page( $slug );
                add_submenu_page( 'tlt-system', $name, $name, $cap, $slug );
                return;
            }
        }
    };

    $move( 'plugins.php',         'Plugins' );
    $move( 'users.php',           'Users' );
    $move( 'tools.php',           'Tools' );
    $move( 'options-general.php', 'Settings' );

    // Newly-added plugins (Chris rarely touches these — bury them under System).
    $move( 'wp-mail-smtp',         'Mail (SMTP)' );
    $move( 'flamingo_inbound',     'Form Submissions (Flamingo)' );
    $move( 'limit-login-attempts', 'Login Protection' );
    $move( 'redirection.php',      'Redirects' );

    // Menus whose slug varies between versions — match by label instead.
    $move_by_label = function ( $label, $display = null ) use ( &$menu ) {
        foreach ( (array) $menu as $item ) {
            if ( ! isset( $item[0], $item[2] ) ) continue;
            if ( wp_strip_all_tags( $item[0] ) === $label ) {
                $cap  = isset( $item[1] ) ? $item[1] : 'manage_options';
                $slug = $item[2];
                $name = $display ?: $label;
                remove_menu_page( $slug );
                add_submenu_page( 'tlt-system', $name, $name, $cap, $slug );
                return;
            }
        }
    };
    $move_by_label( 'ACF' );
    // Fallbacks in case the slug-based moves above missed (labels vary too).
    $move_by_label( 'WP Mail SMTP',                 'Mail (SMTP)' );
    $move_by_label( 'Flamingo',                     'Form Submissions (Flamingo)' );
    $move_by_label( 'Limit Login Attempts',         'Login Protection' );
    $move_by_label( 'Limit Login Attempts Reloaded','Login Protection' );
    $move_by_label( 'Redirection',                  'Redirects' );
}, 999 );

/* ---------------------------------------------------------------------------
 * Top-level admin menu order. Lead with the content Chris touches most and
 * keep the "every show/season" items grouped, then supporting tools, then the
 * System catch-all at the very bottom.
 * ------------------------------------------------------------------------- */
add_filter( 'custom_menu_order', '__return_true' );
add_filter( 'menu_order', function ( $order ) {
    $desired = [
        'index.php',                        // Dashboard
        'edit.php?post_type=tlt_show',      // Shows
        'edit.php?post_type=tlt_promotion', // Promotions
        'edit.php?post_type=page',          // Pages
        'edit.php?post_type=tlt_event',     // Calendar
        'edit.php?post_type=tlt_team',      // Board & Staff
        'upload.php',                       // Media
        'wpcf7',                            // Forms
        'nav-menus.php',                    // Appearance (rebuilt group)
        'tlt-system',                       // System (catch-all)
    ];
    // Append anything not explicitly ordered (separators, stray menus) after.
    return array_merge( $desired, array_diff( (array) $order, $desired ) );
}, 999 );

/* ---------------------------------------------------------------------------
 * Disable comments entirely.
 *
 * TLT doesn't take public comments (spam magnet, no value). Force them closed
 * everywhere, strip the front-end + admin UI, and remove the Comments menu so
 * nobody has to wonder what it's for.
 * ------------------------------------------------------------------------- */
// Force comments + pings closed on everything, and never show existing ones.
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open',    '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 20 );

// Remove the Comments menu item.
add_action( 'admin_menu', function () {
    remove_menu_page( 'edit-comments.php' );
} );

add_action( 'admin_init', function () {
    // Drop comment/trackback support from every post type (kills the meta box
    // and the Comments column on list tables).
    foreach ( get_post_types() as $pt ) {
        if ( post_type_supports( $pt, 'comments' ) ) {
            remove_post_type_support( $pt, 'comments' );
            remove_post_type_support( $pt, 'trackbacks' );
        }
    }
    // Bounce anyone who navigates straight to the comments screen.
    if ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] === 'edit-comments.php' ) {
        wp_safe_redirect( admin_url() );
        exit;
    }
} );

// Remove the admin-bar Comments bubble.
add_action( 'admin_bar_menu', function ( $bar ) {
    $bar->remove_node( 'comments' );
}, 999 );

// Remove the "Recent Comments" dashboard widget.
add_action( 'wp_dashboard_setup', function () {
    remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
} );

/* ---------------------------------------------------------------------------
 * Rename the Contact Form 7 admin menu ("Contact") to "Forms" — clearer that
 * it's where the submission forms are built. Runs late so CF7 has registered.
 * ------------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
    global $menu, $submenu;
    if ( is_array( $menu ) ) {
        foreach ( $menu as $i => $item ) {
            if ( isset( $item[2] ) && $item[2] === 'wpcf7' ) {
                $menu[ $i ][0] = 'Forms';
                break;
            }
        }
    }
    // Also rename the first submenu entry (the "Contact Forms" list).
    if ( isset( $submenu['wpcf7'][0][0] ) ) {
        $submenu['wpcf7'][0][0] = 'All Forms';
    }
}, 999 );

/* ---------------------------------------------------------------------------
 * Rebuild the "Appearance" menu so it contains ONLY the two screens staff use:
 * Customize (contact info / mission / social / logo) and Menus. We drop the
 * stock Appearance menu (which exposes Themes/Patterns/Fonts/Theme File Editor)
 * and register a fresh "Appearance" group with just those two. The screens are
 * core WordPress and stay fully functional — only the navigation changes.
 * ------------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
    remove_menu_page( 'themes.php' ); // stock Appearance + all of its children

    add_menu_page(
        'Appearance', 'Appearance', 'edit_theme_options', 'nav-menus.php', '',
        'dashicons-admin-appearance', 60
    );
    // First child shares the parent slug so clicking "Appearance" opens Menus,
    // and its label ("Menus") replaces the auto-generated "Appearance" duplicate.
    add_submenu_page( 'nav-menus.php', 'Menus', 'Menus', 'edit_theme_options', 'nav-menus.php' );
    add_submenu_page( 'nav-menus.php', 'Customize', 'Customize', 'edit_theme_options', 'customize.php' );
}, 999 );

// Keep the new Appearance group highlighted while on the Customize screen.
add_filter( 'parent_file', function ( $parent_file ) {
    if ( isset( $GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] === 'customize.php' ) {
        return 'nav-menus.php';
    }
    return $parent_file;
} );
