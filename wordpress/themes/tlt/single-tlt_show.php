<?php
/**
 * Single show page.
 */
get_header();

while ( have_posts() ) : the_post();
    $director  = get_post_meta( get_the_ID(), 'show_director', true );
    $music_dir = get_post_meta( get_the_ID(), 'show_music_director', true );
    $choreo    = get_post_meta( get_the_ID(), 'show_choreographer', true );
    $open      = get_post_meta( get_the_ID(), 'show_open_date', true );
    $close     = get_post_meta( get_the_ID(), 'show_close_date', true );
    $run_time  = get_post_meta( get_the_ID(), 'show_run_time', true );
    $age       = get_post_meta( get_the_ID(), 'show_age_rec', true );
    $warn      = get_post_meta( get_the_ID(), 'show_content_warning', true );
    $tix       = get_post_meta( get_the_ID(), 'show_ticket_url', true );
    $program   = get_post_meta( get_the_ID(), 'show_program_pdf_url', true );
    $dramaturgy_url     = get_post_meta( get_the_ID(), 'show_dramaturgy_url', true );
    $dramaturgy_raw     = get_post_meta( get_the_ID(), 'show_dramaturgy_gallery', true );
    $dramaturgy_gallery = $dramaturgy_raw ? json_decode( $dramaturgy_raw, true ) : [];
    if ( ! is_array( $dramaturgy_gallery ) ) $dramaturgy_gallery = [];
    $cancelled = get_post_meta( get_the_ID(), 'show_cancelled', true );
    $cast      = tlt_parse_cast( get_post_meta( get_the_ID(), 'show_cast', true ) );
    $img       = tlt_show_image_url( get_the_ID(), 'full' );
    $videos_raw = get_post_meta( get_the_ID(), 'show_video_urls', true );
    $videos    = $videos_raw ? array_filter( array_map( 'trim', explode( ',', $videos_raw ) ) ) : [];
    $cityline_url = get_post_meta( get_the_ID(), 'show_cityline_url', true );

    // Has this show already closed? Hide the "Buy Tickets" button once the run
    // is over. Uses tlt_today() so it respects the pre-launch date override.
    $is_closed = false;
    if ( $close ) {
        $close_ts = strtotime( $close );
        $today_ts = strtotime( function_exists( 'tlt_today' ) ? tlt_today() : date( 'Y-m-d' ) );
        if ( $close_ts && $today_ts ) $is_closed = $today_ts > $close_ts;
    }

    // --- New as of 2026-05-13 ---
    $ptype       = get_post_meta( get_the_ID(), 'show_program_type', true );
    $venue_name  = get_post_meta( get_the_ID(), 'show_venue_name', true );
    $venue_addr  = get_post_meta( get_the_ID(), 'show_venue_address', true );
    $dinner_menu = get_post_meta( get_the_ID(), 'show_dinner_menu', true );
    $gallery_raw = get_post_meta( get_the_ID(), 'show_photo_gallery', true );
    $gallery     = $gallery_raw ? json_decode( $gallery_raw, true ) : [];
    if ( ! is_array( $gallery ) ) $gallery = [];
?>

<article class="show-detail">
  <?php $announcement = get_post_meta( get_the_ID(), 'show_announcement', true ); ?>
  <?php if ( $announcement ) : ?>
    <div class="show-announcement">
      <div class="container">
        <p><?php echo nl2br( esc_html( $announcement ) ); ?></p>
      </div>
    </div>
  <?php endif; ?>
  <div class="container">
    <div class="layout">
      <div class="poster">
        <?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>"><?php endif; ?>

        <?php /* Left column = poster + videos only. All other content (CTAs,
                reviews, fact sheet, warning, cast) lives in the right column
                so the eye doesn't have to zig-zag across the page. */ ?>
        <?php
          $cityline_embed = '';
          $cityline_iframe_src = '';
          if ( $cityline_url ) {
              $cityline_embed = wp_oembed_get( $cityline_url, [ 'width' => 600 ] );
              if ( ! $cityline_embed && preg_match( '~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|v/|shorts/))([A-Za-z0-9_-]{6,})~', $cityline_url, $m ) ) {
                  $cityline_iframe_src = 'https://www.youtube.com/embed/' . $m[1];
              }
          }
          $has_cityline = $cityline_url && ( $cityline_embed || $cityline_iframe_src );
          $has_other_videos = ! empty( $videos );
        ?>
        <?php if ( $has_cityline || $has_other_videos ) : ?>
          <div class="show-videos" style="margin-top:1.5rem">
            <h3 style="margin-bottom:0.75rem">Videos</h3>
            <?php if ( $has_cityline ) : ?>
              <div class="show-cityline video-wrap" style="position:relative;aspect-ratio:16/9;background:#000;border-radius:4px;overflow:hidden;margin-bottom:1rem">
                <?php
                if ( $cityline_embed ) {
                    echo $cityline_embed;
                } else {
                    echo '<iframe src="' . esc_url( $cityline_iframe_src ) . '" title="Cityline interview" allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen frameborder="0" style="position:absolute;inset:0;width:100%;height:100%;border:0"></iframe>';
                }
                ?>
              </div>
              <style>.show-cityline iframe { position:absolute; inset:0; width:100%; height:100%; border:0; }</style>
            <?php endif; ?>
            <?php if ( $has_other_videos ) : ?>
              <?php foreach ( $videos as $v ) : ?>
                <?php $embed_src = function_exists( 'tlt_video_embed_url' ) ? tlt_video_embed_url( $v ) : $v; ?>
                <div class="video-wrap" style="margin-bottom:1rem">
                  <iframe src="<?php echo esc_url( $embed_src ); ?>" allow="autoplay; fullscreen" allowfullscreen frameborder="0" style="width:100%; aspect-ratio:16/9; height:auto"></iframe>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="info">
        <?php if ( $cancelled ) : ?>
          <p style="background:#ef5350;color:#fff;padding:0.5rem 1rem;display:inline-block;font-weight:600;text-transform:uppercase;letter-spacing:0.05em">Cancelled</p>
        <?php endif; ?>
        <?php
          // Exact dates when known; otherwise fall back to a season label
          // (archival shows where only the season year is known).
          $datestr = tlt_format_date_range( $open, $close );
          if ( ! $datestr ) $datestr = get_post_meta( get_the_ID(), 'show_season_label', true );
        ?>
        <div class="dates"><?php echo esc_html( $datestr ); ?></div>
        <h1><?php the_title(); ?></h1>
        <?php
          $playwright = trim( (string) get_post_meta( get_the_ID(), 'show_playwright', true ) );
          // Smart rendering: a plain name ("Aaron Sorkin") gets "by " prepended.
          // A list or anything that already starts with a credit phrase
          // ("Book by …", "Music by …", "Adapted by …", "by …") renders verbatim
          // so musicals with multi-line credits work correctly.
          $has_credit_prefix = (bool) preg_match(
              '/^\s*(by|book by|music by|lyric(s)? by|words by|words and music by|music and lyrics by|adapted by|adapted from|based on|conceived by|story by|written by|libretto by|book and lyrics by|original (story|book) by)\b/i',
              $playwright
          );
          $is_multiline = ( strpos( $playwright, "\n" ) !== false ) || ( strpos( $playwright, "\r" ) !== false );
        ?>
        <?php if ( $playwright ) : ?>
          <p class="show-playwright"><?php
            if ( $has_credit_prefix || $is_multiline ) {
                echo nl2br( esc_html( $playwright ) );
            } else {
                echo 'by ' . esc_html( $playwright );
            }
          ?></p>
        <?php endif; ?>
        <?php $tagline = get_post_meta( get_the_ID(), 'show_tagline', true ); ?>
        <?php if ( $tagline ) : ?>
          <p class="tagline"><?php echo esc_html( $tagline ); ?></p>
        <?php endif; ?>
        <?php if ( $tix && ! $cancelled && ! $is_closed ) : ?>
          <p class="show-buy-cta"><a href="<?php echo esc_url( $tix ); ?>" class="btn btn-primary">Buy Tickets</a></p>
        <?php endif; ?>
        <?php if ( ! $cancelled && function_exists( 'tlt_show_auditions_open' ) && tlt_show_auditions_open( get_the_ID() ) ) : ?>
          <p class="show-audition-cta"><a href="/auditions/" class="btn btn-outline">Audition for this Show &rarr;</a></p>
        <?php endif; ?>
        <p class="credits">
          <?php if ( $director ) echo 'Directed by ' . esc_html( $director ); ?>
          <?php if ( $music_dir ) echo '<br>Musically Directed by ' . esc_html( $music_dir ); ?>
          <?php if ( $choreo ) echo '<br>Choreographed by ' . esc_html( $choreo ); ?>
        </p>

        <?php if ( $venue_name ) : ?>
          <div class="show-venue" style="background:var(--color-soft);padding:1rem 1.25rem;border-left:4px solid var(--color-accent);margin:1.5rem 0">
            <h3 style="color:var(--color-accent);font-size:0.95rem;letter-spacing:0.08em;margin:0 0 0.5rem">Presented At</h3>
            <p style="margin:0;font-weight:600"><?php echo esc_html( $venue_name ); ?></p>
            <?php if ( $venue_addr ) : ?>
              <p style="margin:0.25rem 0 0;color:var(--color-muted)"><?php echo esc_html( $venue_addr ); ?></p>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="show-content">
          <?php
            // Body content arrives from the original scrape and almost always contains:
            //   - a duplicate of the poster image (we already render it in .poster above)
            //   - "Directed by …", "Musically Directed by …", "Choreographed by …" paragraphs
            //     which we already render in .credits
            // Strip them before applying content filters so they don't appear twice.
            $body = get_the_content();

            // 1) Drop the first figure / linked-image / bare <img> — that's the duplicate poster.
            $body = preg_replace( '/<figure[^>]*>.*?<\/figure>/s', '', $body, 1, $cnt );
            if ( ! $cnt ) $body = preg_replace( '/<a[^>]*>\s*<img[^>]+>\s*<\/a>/s', '', $body, 1, $cnt );
            if ( ! $cnt ) $body = preg_replace( '/<img[^>]+>/', '', $body, 1 );

            // 2) Drop credit paragraphs we already show in .credits above.
            //    Two passes:
            //    a) Strip any whole <p> whose ENTIRE content is credit lines
            //       (Directed by ... <br> Musically Directed by ... <br> ...).
            //       Handles the common case where all three credits live in one
            //       paragraph joined by <br>.
            //    b) Strip single-credit paragraphs (date below, etc.).
            // Match any credit-line opener. Order matters — list longest variants first
            // so "Co-Directed and Choreographed by" doesn't get partially-matched as
            // "Directed by".
            $credit_phrase = '(?:'
                . 'Co-Directed and Choreographed by'
                . '|Co-Directed by'
                . '|Directed (?:&amp;|&|and) Choreographed by'
                . '|Directed (?:&amp;|&|and) Musically Directed by'
                . '|Musically Directed by'
                . '|Music(?:al)? Direction by'
                . '|Choreographed by'
                . '|Choreography by'
                . '|Directed by'
                . ')';
            // 2a — whole-paragraph match: <p> containing only credit lines + <br>s
            $body = preg_replace(
                '#<p[^>]*>\s*(?:<[^>]+>\s*)*' .
                $credit_phrase . '\b[^<]*' .                   // first credit
                '(?:\s*<br\s*/?>\s*' . $credit_phrase . '\b[^<]*)*' .  // subsequent credits via <br>
                '\s*</p>#i',
                '',
                $body
            );
            // 2b — single-credit paragraph fallback (also catches credits with stray inline tags)
            foreach ( [ 'Co-Directed and Choreographed by', 'Co-Directed by', 'Directed (?:&amp;|&|and) Choreographed by', 'Directed (?:&amp;|&|and) Musically Directed by', 'Musically Directed by', 'Music(?:al)? Direction by', 'Choreographed by', 'Choreography by', 'Directed by' ] as $phrase ) {
                $body = preg_replace( '#<p[^>]*>\s*(?:<[^>]+>)*\s*' . $phrase . '\b[^<]*(?:<br\s*/?>[^<]*)?\s*</p>#i', '', $body );
            }

            // 2b) If we're going to render our own video embeds (from show_video_urls meta),
            //     strip any iframes (and Squarespace embed-block wrappers) from the body so
            //     videos don't show up twice.
            if ( ! empty( $videos ) ) {
                $body = preg_replace( '#<div[^>]*class="[^"]*website-component-block embed-block[^"]*"[^>]*>.*?</div>\s*</div>\s*</div>\s*</div>#s', '', $body );
                $body = preg_replace( '#<div[^>]*class="[^"]*embed-block[^"]*"[^>]*>.*?</div>#s', '', $body );
                $body = preg_replace( '#<iframe\b[^>]*>.*?</iframe>#s', '', $body );
            }

            // 2c) Content-warning blocks (e.g. "Bug is recommended for mature audiences…",
            //     "Please be advised that this show contains…") were wrapped by Squarespace
            //     in <h2><strong> or <p><strong><em>, rendering them huge and bold. Demote any
            //     such block to a plain <p> so it reads as normal body text.
            $warning_kw = '(?:recommended|please be advised|this (?:show|production) contains|contains flashing|flashing lights|mature themes|strong language|hypoallergenic|recorded gunshot|content advisory|warning:|advisory)';
            $body = preg_replace_callback(
                '#<(h[1-6]|p)([^>]*)>(.*?)</\1>#is',
                function ( $m ) use ( $warning_kw, $warn ) {
                    $inner = $m[3];
                    $plain = wp_strip_all_tags( $inner );
                    if ( ! preg_match( '/' . $warning_kw . '/i', $plain ) ) return $m[0];
                    // When we render the warning structurally from show_content_warning
                    // meta, drop it from the body to avoid a duplicate. Otherwise demote
                    // the (often huge/bold) block to plain body text.
                    if ( $warn ) return '';
                    $inner = preg_replace( '#</?(?:strong|em|b|i)\b[^>]*>#i', '', $inner );
                    return '<p>' . $inner . '</p>';
                },
                $body
            );

            // 3) Drop inline program-PDF link paragraphs — the .actions row below already
            //    renders a "View Program" button from show_program_pdf_url meta. Match a <p>
            //    whose only meaningful content is an <a href="*.pdf"> whose visible text is
            //    "Program" / "View Program" / "View Program (PDF)" / "PROGRAM" — allowing
            //    <strong>/<em>/etc wrappers in any position (inside or outside the anchor).
            $body = preg_replace(
                '#<p[^>]*>\s*' .
                '(?:<(?:strong|em|b|i)[^>]*>\s*)*' .          // optional bold/em wrapping the anchor
                '<a[^>]+href="[^"]+\.pdf[^"]*"[^>]*>' .
                '\s*(?:<(?:strong|em|b|i)[^>]*>\s*)*' .       // optional bold/em wrapping the link text
                '(?:View\s+)?Program(?:\s*\(PDF\))?' .
                '\s*(?:</(?:strong|em|b|i)>\s*)*' .
                '</a>\s*' .
                '(?:</(?:strong|em|b|i)>\s*|<br\s*/?>\s*)*' . // close any outer wrap / trailing <br>
                '</p>#i',
                '',
                $body
            );

            // 4) Drop body "Run Time:" blocks — the schedule box below already shows
            //    this from show_run_time meta.
            if ( $run_time ) {
                $body = preg_replace( '#<(h[1-6]|p)[^>]*>\s*(?:<[^>]+>\s*)*Run\s*Time\b.*?</\1>#is', '', $body );
            }

            // 5) Drop the inline cast list from the body — we render it structurally
            //    from show_cast below, so the body copy would be a duplicate.
            if ( ! empty( $cast ) ) {
                // "Featuring the talents of:" / "Cast" header block.
                $body = preg_replace( '#<(p|h[1-6])[^>]*>\s*(?:<[^>]+>\s*)*(?:Featuring the talents of|The Cast|Cast of Characters)\b.*?</\1>#is', '', $body );
                // Leaf paragraphs/headings that are predominantly a cast list (3+
                // "Name as Role"). NOT <div> — a wrapping div can hold the synopsis
                // too, and we'd strip the lot.
                $body = preg_replace_callback( '#<(p|h[1-6])[^>]*>(.*?)</\1>#is', function ( $m ) {
                    $plain = wp_strip_all_tags( $m[2] );
                    $n = preg_match_all( '/[A-Z][A-Za-z.\'-]+(?:\s+[A-Z][A-Za-z.\'-]+){1,3}\s+as\s+[A-Z]/', $plain );
                    return $n >= 3 ? '' : $m[0];
                }, $body );
                // Single-line cast paragraphs ("Firstname Lastname as Role").
                $body = preg_replace( '#<p[^>]*>\s*[A-Z][A-Za-z.\'-]+(?:\s+[A-Z][A-Za-z.\'-]+){1,3}\s+as\s+[^<]{1,60}</p>#', '', $body );
            }

            echo apply_filters( 'the_content', $body );
          ?>
        </div>

        <?php $perf_details = get_post_meta( get_the_ID(), 'show_performance_details', true ); ?>
        <?php if ( $perf_details ) : ?>
          <div class="show-perf">
            <div class="show-perf__header">Showtimes &amp; Tickets</div>
            <div class="show-perf__body"><?php echo nl2br( esc_html( $perf_details ) ); ?></div>
          </div>
        <?php endif; ?>

        <?php if ( $run_time || $age ) : ?>
          <?php
            // For broad-audience values ("All Ages", "General Audiences", etc.)
            // collapse the header+value pair into one inline line so it reads
            // naturally instead of "Recommended for Ages / General Audiences".
            $age_broad   = preg_match( '/^\s*(all\s*ages|all|family\s*friendly|general\s*audiences?)\s*$/i', (string) $age );
            $age_display = trim( (string) $age );
          ?>
          <div class="schedule">
            <div class="schedule__header">At A Glance</div>
            <div class="schedule__body">
              <?php if ( $run_time ) : ?>
                <p><strong>Run Time: <?php echo esc_html( $run_time ); ?></strong></p>
              <?php endif; ?>
              <?php if ( $age && $age_broad ) : ?>
                <p><strong>Recommended for <?php echo esc_html( $age_display ); ?></strong></p>
              <?php elseif ( $age ) : ?>
                <p><strong>Recommended for Ages: <?php echo esc_html( $age_display ); ?></strong></p>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>

        <?php if ( $warn ) : ?>
          <div class="content-warning">
            <div class="content-warning__header">Content Warning</div>
            <div class="content-warning__body">
              <p class="content-warning__subhead">This production of <?php echo esc_html( get_the_title() ); ?> includes the following:</p>
              <p><?php echo esc_html( $warn ); ?></p>
            </div>
          </div>
        <?php endif; ?>

        <?php if ( $cast ) : ?>
          <section class="show-cast">
            <h3 class="section-heading">Cast</h3>
            <ul class="cast-list">
              <?php foreach ( $cast as $cm ) : ?>
                <li>
                  <span class="cast-actor"><?php echo esc_html( $cm['actor'] ); ?></span>
                  <?php if ( $cm['role'] !== '' ) : ?><span class="cast-role"><?php echo esc_html( $cm['role'] ); ?></span><?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        <?php endif; ?>

        <?php /* Secondary CTAs — read about the show first, then dig deeper. */ ?>
        <p class="show-actions">
          <?php if ( $program ) : ?>
            <a href="<?php echo esc_url( $program ); ?>" class="btn btn-primary" target="_blank" rel="noopener" style="background:transparent;color:var(--color-accent);border:2px solid var(--color-accent)">View Program</a>
          <?php else : ?>
            <a href="#" class="btn btn-primary" style="background:transparent;color:var(--color-muted);border:2px solid var(--color-muted);cursor:not-allowed" title="Program PDF not yet linked — coming soon" onclick="event.preventDefault()">View Program</a>
          <?php endif; ?>
          <?php if ( $dramaturgy_gallery ) : ?>
            <button type="button" class="btn btn-primary dramaturgy-open" style="background:transparent;color:var(--color-accent);border:2px solid var(--color-accent)">View Dramaturgy</button>
          <?php elseif ( $dramaturgy_url ) : ?>
            <a href="<?php echo esc_url( $dramaturgy_url ); ?>" class="btn btn-primary" target="_blank" rel="noopener" style="background:transparent;color:var(--color-accent);border:2px solid var(--color-accent)">View Dramaturgy</a>
          <?php endif; ?>
        </p>

        <?php
          // Reviews — one per line: "Publication | https://url" (URL alone also ok).
          $reviews_raw = get_post_meta( get_the_ID(), 'show_reviews', true );
          $reviews = [];
          if ( $reviews_raw ) {
              foreach ( preg_split( '/\r\n|\r|\n/', $reviews_raw ) as $line ) {
                  $line = trim( $line );
                  if ( $line === '' ) continue;
                  $parts = array_map( 'trim', explode( '|', $line, 2 ) );
                  if ( ! empty( $parts[1] ) ) {
                      $reviews[] = [ 'name' => $parts[0], 'url' => $parts[1] ];
                  } elseif ( filter_var( $parts[0], FILTER_VALIDATE_URL ) ) {
                      $reviews[] = [ 'name' => $parts[0], 'url' => $parts[0] ];
                  }
              }
          }
        ?>
        <?php if ( $reviews ) : ?>
          <section class="show-reviews">
            <div class="show-reviews__header">Reviews</div>
            <div class="show-reviews__body">
              <ul class="review-list">
                <?php foreach ( $reviews as $r ) : ?>
                  <li><a href="<?php echo esc_url( $r['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $r['name'] ); ?></a></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </section>
        <?php endif; ?>

        <?php /* Videos live in the left .poster column. The dramaturgy
                lightbox modal lives here so the JS is alongside its markup. */ ?>

        <?php if ( $dramaturgy_gallery ) : ?>
          <div class="dramaturgy-lightbox" hidden>
            <div class="dramaturgy-lightbox__inner">
              <button type="button" class="dramaturgy-lightbox__close" aria-label="Close dramaturgy">&times;</button>
              <?php echo tlt_render_slideshow( $dramaturgy_gallery, 'Dramaturgy' ); ?>
            </div>
          </div>
          <script>
            (function () {
              var openBtn = document.querySelector('.dramaturgy-open');
              var box = document.querySelector('.dramaturgy-lightbox');
              if (!openBtn || !box) return;
              var closeBtn = box.querySelector('.dramaturgy-lightbox__close');
              function open()  { box.hidden = false; document.body.style.overflow = 'hidden'; }
              function close() { box.hidden = true;  document.body.style.overflow = ''; }
              openBtn.addEventListener('click', open);
              closeBtn.addEventListener('click', close);
              box.addEventListener('click', function (e) { if (e.target === box) close(); });
              document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !box.hidden) close(); });
            })();
          </script>
        <?php endif; ?>
      </div>
    </div>

    <?php if ( $dinner_menu ) : ?>
      <section class="show-dinner-menu" style="max-width:880px;margin:3rem auto;padding:2rem;background:var(--color-soft);border-top:4px solid var(--color-accent)">
        <h2 style="color:var(--color-accent);margin-top:0">Dinner Menu</h2>
        <?php echo wp_kses_post( wpautop( $dinner_menu ) ); ?>
      </section>
    <?php endif; ?>

    <?php echo tlt_render_slideshow( $gallery, 'Production Photos' ); ?>
  </div>
</article>

<?php
// JSON-LD Event schema for SEO
if ( $open && ! $cancelled ) {
    $schema = [
        '@context' => 'https://schema.org',
        '@type'    => 'TheaterEvent',
        'name'     => get_the_title(),
        'startDate' => $open,
        'endDate'   => $close ?: $open,
        'location' => [
            '@type' => 'Place',
            'name'  => 'Tacoma Little Theatre',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => '210 N "I" Street',
                'addressLocality' => 'Tacoma',
                'addressRegion' => 'WA',
                'postalCode' => '98403',
                'addressCountry' => 'US',
            ],
        ],
        'description' => wp_strip_all_tags( get_post_meta( get_the_ID(), 'show_tagline', true ) ?: wp_trim_words( get_the_content(), 40 ) ),
        'image'       => $img,
        'offers'      => ( $tix && ! $is_closed ) ? [ '@type' => 'Offer', 'url' => $tix, 'availability' => 'https://schema.org/InStock' ] : null,
    ];
    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . '</script>';
}

endwhile;

get_footer();
