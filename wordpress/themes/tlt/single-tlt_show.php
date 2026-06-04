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
    $cancelled = get_post_meta( get_the_ID(), 'show_cancelled', true );
    $img       = tlt_show_image_url( get_the_ID(), 'full' );
    $videos_raw = get_post_meta( get_the_ID(), 'show_video_urls', true );
    $videos    = $videos_raw ? array_filter( array_map( 'trim', explode( ',', $videos_raw ) ) ) : [];
    $cityline_url = get_post_meta( get_the_ID(), 'show_cityline_url', true );

    // --- New as of 2026-05-13 ---
    $ptype       = get_post_meta( get_the_ID(), 'show_program_type', true );
    $venue_name  = get_post_meta( get_the_ID(), 'show_venue_name', true );
    $venue_addr  = get_post_meta( get_the_ID(), 'show_venue_address', true );
    $dinner_menu = get_post_meta( get_the_ID(), 'show_dinner_menu', true );
    $gallery_raw = get_post_meta( get_the_ID(), 'show_photo_gallery', true );
    $gallery     = $gallery_raw ? json_decode( $gallery_raw, true ) : [];
?>

<article class="show-detail">
  <div class="container">
    <div class="layout">
      <div class="poster">
        <?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>"><?php endif; ?>
      </div>
      <div class="info">
        <?php if ( $cancelled ) : ?>
          <p style="background:#ef5350;color:#fff;padding:0.5rem 1rem;display:inline-block;font-weight:600;text-transform:uppercase;letter-spacing:0.05em">Cancelled</p>
        <?php endif; ?>
        <div class="dates"><?php echo esc_html( tlt_format_date_range( $open, $close ) ); ?></div>
        <h1><?php the_title(); ?></h1>
        <p class="credits">
          <?php if ( $director ) echo 'Directed by ' . esc_html( $director ); ?>
          <?php if ( $music_dir ) echo '<br>Musically Directed by ' . esc_html( $music_dir ); ?>
          <?php if ( $choreo ) echo '<br>Choreographed by ' . esc_html( $choreo ); ?>
        </p>

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
            $warning_kw = '(?:recommended|please be advised|this (?:show|production) contains|contains:|warning:|advisory)';
            $body = preg_replace_callback(
                '#<(h[1-6]|p)([^>]*)>(.*?)</\1>#is',
                function ( $m ) use ( $warning_kw ) {
                    $tag = $m[1]; $inner = $m[3];
                    $plain = wp_strip_all_tags( $inner );
                    if ( ! preg_match( '/' . $warning_kw . '/i', $plain ) ) return $m[0];
                    // Strip inline emphasis wrappers
                    $inner = preg_replace( '#</?(?:strong|em|b|i)\b[^>]*>#i', '', $inner );
                    // Always emit as plain <p>
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
                '(?:</(?:strong|em|b|i)>\s*)*' .              // close any outer wrap
                '</p>#i',
                '',
                $body
            );

            echo apply_filters( 'the_content', $body );
          ?>
        </div>

        <?php if ( $venue_name ) : ?>
          <div class="show-venue" style="background:var(--color-soft);padding:1rem 1.25rem;border-left:4px solid var(--color-accent);margin:1.5rem 0">
            <h3 style="color:var(--color-accent);font-size:0.95rem;letter-spacing:0.08em;margin:0 0 0.5rem">Presented At</h3>
            <p style="margin:0;font-weight:600"><?php echo esc_html( $venue_name ); ?></p>
            <?php if ( $venue_addr ) : ?>
              <p style="margin:0.25rem 0 0;color:var(--color-muted)"><?php echo esc_html( $venue_addr ); ?></p>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ( $run_time || $age ) : ?>
          <div class="schedule">
            <?php if ( $run_time ) : ?><h3>Run Time</h3><p><?php echo esc_html( $run_time ); ?></p><?php endif; ?>
            <?php if ( $age ) : ?><p><strong><?php echo esc_html( $age ); ?></strong></p><?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if ( $warn ) : ?>
          <div class="content-warning"><?php echo esc_html( $warn ); ?></div>
        <?php endif; ?>

        <p>
          <?php if ( $tix && ! $cancelled ) : ?>
            <a href="<?php echo esc_url( $tix ); ?>" class="btn btn-primary">Buy Tickets</a>
          <?php endif; ?>
          <?php if ( $program ) : ?>
            <a href="<?php echo esc_url( $program ); ?>" class="btn btn-primary" target="_blank" rel="noopener" style="background:transparent;color:var(--color-accent);border:2px solid var(--color-accent)">View Program</a>
          <?php else : ?>
            <a href="#" class="btn btn-primary" style="background:transparent;color:var(--color-muted);border:2px solid var(--color-muted);cursor:not-allowed" title="Program PDF not yet linked — coming soon" onclick="event.preventDefault()">View Program</a>
          <?php endif; ?>
        </p>

        <?php if ( $cityline_url ) :
          $cityline_embed = wp_oembed_get( $cityline_url, [ 'width' => 600 ] );
          // Build a clean embed src as a fallback
          $cityline_iframe_src = '';
          if ( ! $cityline_embed && preg_match( '~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|v/|shorts/))([A-Za-z0-9_-]{6,})~', $cityline_url, $m ) ) {
              $cityline_iframe_src = 'https://www.youtube.com/embed/' . $m[1];
          }
        ?>
          <div class="show-cityline" style="margin-top:2rem">
            <h3 style="margin-bottom:0.4rem;font-size:0.85rem;text-transform:uppercase;letter-spacing:0.08em;color:var(--color-accent)">Cityline Interview</h3>
            <div class="video-wrap" style="position:relative;aspect-ratio:16/9;background:#000;border-radius:4px;overflow:hidden">
              <?php
              if ( $cityline_embed ) {
                  echo $cityline_embed;
              } elseif ( $cityline_iframe_src ) {
                  echo '<iframe src="' . esc_url( $cityline_iframe_src ) . '" title="Cityline interview" allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen frameborder="0" style="position:absolute;inset:0;width:100%;height:100%;border:0"></iframe>';
              }
              ?>
            </div>
          </div>
          <style>.show-cityline .video-wrap iframe { position:absolute; inset:0; width:100%; height:100%; border:0; }</style>
        <?php endif; ?>

        <?php if ( ! empty( $videos ) ) : ?>
          <div class="show-videos" style="margin-top:2rem">
            <h3 style="margin-bottom:0.75rem"><?php echo count( $videos ) > 1 ? 'Videos' : 'Video'; ?></h3>
            <?php foreach ( $videos as $v ) : ?>
              <div class="video-wrap" style="margin-bottom:1rem">
                <iframe src="<?php echo esc_url( $v ); ?>" allow="autoplay; fullscreen" allowfullscreen frameborder="0" style="width:100%; aspect-ratio:16/9; height:auto"></iframe>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ( $dinner_menu ) : ?>
      <section class="show-dinner-menu" style="max-width:880px;margin:3rem auto;padding:2rem;background:var(--color-soft);border-top:4px solid var(--color-accent)">
        <h2 style="color:var(--color-accent);margin-top:0">Dinner Menu</h2>
        <?php echo wp_kses_post( wpautop( $dinner_menu ) ); ?>
      </section>
    <?php endif; ?>

    <?php if ( is_array( $gallery ) && $gallery ) : ?>
      <section class="show-photo-gallery" style="margin-top:3rem">
        <h2 class="section-heading">Production Photos</h2>
        <div class="show-slideshow" data-count="<?php echo count( $gallery ); ?>">
          <div class="show-slideshow__viewport">
            <?php foreach ( $gallery as $i => $g ) :
              $url = isset( $g['url'] ) ? esc_url( $g['url'] ) : '';
              $alt = isset( $g['alt'] ) ? esc_attr( $g['alt'] ) : '';
              $cap = isset( $g['caption'] ) ? $g['caption'] : '';
              if ( ! $url ) continue;
            ?>
              <figure class="show-slide<?php echo $i === 0 ? ' is-active' : ''; ?>">
                <img src="<?php echo $url; ?>" alt="<?php echo $alt; ?>" loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>">
                <?php if ( $cap ) : ?><figcaption><?php echo esc_html( $cap ); ?></figcaption><?php endif; ?>
              </figure>
            <?php endforeach; ?>
          </div>
          <button type="button" class="show-slideshow__nav show-slideshow__nav--prev" aria-label="Previous photo">&#8592;</button>
          <button type="button" class="show-slideshow__nav show-slideshow__nav--next" aria-label="Next photo">&#8594;</button>
          <div class="show-slideshow__dots" role="tablist" aria-label="Slide selector">
            <?php foreach ( $gallery as $i => $g ) : ?>
              <button type="button" class="show-slideshow__dot<?php echo $i === 0 ? ' is-active' : ''; ?>" role="tab" aria-label="Photo <?php echo $i + 1; ?>" data-index="<?php echo $i; ?>"></button>
            <?php endforeach; ?>
          </div>
          <div class="show-slideshow__counter"><span class="show-slideshow__current">1</span> / <?php echo count( $gallery ); ?></div>
        </div>
      </section>
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
          var box = document.querySelector('.show-slideshow');
          if (!box) return;
          var slides = box.querySelectorAll('.show-slide');
          var dots   = box.querySelectorAll('.show-slideshow__dot');
          var counter= box.querySelector('.show-slideshow__current');
          var idx = 0;
          function go(n) {
            idx = (n + slides.length) % slides.length;
            slides.forEach(function (s, i) { s.classList.toggle('is-active', i === idx); });
            dots.forEach(function (d, i) { d.classList.toggle('is-active', i === idx); });
            if (counter) counter.textContent = (idx + 1);
          }
          box.querySelector('.show-slideshow__nav--prev').addEventListener('click', function () { go(idx - 1); });
          box.querySelector('.show-slideshow__nav--next').addEventListener('click', function () { go(idx + 1); });
          dots.forEach(function (d, i) { d.addEventListener('click', function () { go(i); }); });
          // Keyboard arrows
          document.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft')  go(idx - 1);
            if (e.key === 'ArrowRight') go(idx + 1);
          });
          // Swipe on touch
          var startX = 0;
          box.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
          box.addEventListener('touchend',   function (e) {
            var dx = e.changedTouches[0].clientX - startX;
            if (Math.abs(dx) > 50) go(idx + (dx < 0 ? 1 : -1));
          });
        })();
      </script>
    <?php endif; ?>
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
        'description' => wp_strip_all_tags( get_the_excerpt() ),
        'image'       => $img,
        'offers'      => $tix ? [ '@type' => 'Offer', 'url' => $tix, 'availability' => 'https://schema.org/InStock' ] : null,
    ];
    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) . '</script>';
}

endwhile;

get_footer();
