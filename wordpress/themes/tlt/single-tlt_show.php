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
          <?php the_content(); ?>
        </div>

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
          <?php endif; ?>
        </p>
      </div>
    </div>
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
