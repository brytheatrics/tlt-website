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

            // 2) Drop credit paragraphs we already show in .credits above. Each pattern matches
            //    a <p>...</p> whose visible text starts with the credit phrase, optionally with
            //    a <br> on the next line.
            foreach ( [ 'Directed by', 'Musically Directed by', 'Music(?:al)? Direction by', 'Choreographed by', 'Co-Directed by' ] as $phrase ) {
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
            <a href="<?php echo esc_url( $program ); ?>" class="btn btn-primary" style="background:transparent;color:var(--color-accent);border:2px solid var(--color-accent)">View Program</a>
          <?php else : ?>
            <a href="#" class="btn btn-primary" style="background:transparent;color:var(--color-muted);border:2px solid var(--color-muted);cursor:not-allowed" title="Program PDF not yet linked — coming soon" onclick="event.preventDefault()">View Program</a>
          <?php endif; ?>
        </p>

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
        <div class="photo-gallery">
          <?php foreach ( $gallery as $g ) :
            $url = isset( $g['url'] ) ? esc_url( $g['url'] ) : '';
            $alt = isset( $g['alt'] ) ? esc_attr( $g['alt'] ) : '';
            $cap = isset( $g['caption'] ) ? $g['caption'] : '';
            if ( ! $url ) continue;
          ?>
            <a href="<?php echo $url; ?>" class="gallery-item" target="_blank" rel="noopener">
              <img src="<?php echo $url; ?>" alt="<?php echo $alt; ?>" loading="lazy">
              <?php if ( $cap ) : ?>
                <span class="visually-hidden"><?php echo esc_html( $cap ); ?></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
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
