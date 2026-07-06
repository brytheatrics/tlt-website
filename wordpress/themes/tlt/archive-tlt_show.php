<?php
/**
 * Show archive — /shows/
 * Groups by season descending; shows with no season fall under "Other".
 */
get_header(); ?>

<div class="container">
  <header class="page-header">
    <h1><?php
      if ( is_tax( 'tlt_season' ) ) {
        echo 'Season: ' . single_term_title( '', false );
      } else {
        echo 'Shows';
      }
    ?></h1>
    <?php
      if ( is_tax( 'tlt_season' ) ) {
        $season_term = get_queried_object();
        if ( $season_term && ! empty( $season_term->description ) ) {
          echo '<p class="season-tagline">' . wp_kses_post( $season_term->description ) . '</p>';
        }
      }
    ?>
  </header>

  <?php if ( is_tax( 'tlt_season' ) ) :
    // Single-season view: just show the grid
  ?>
    <?php if ( have_posts() ) : ?>
      <div class="show-grid">
        <?php while ( have_posts() ) : the_post();
          $img = tlt_show_image_url( get_the_ID(), 'medium_large' );
          $open  = get_post_meta( get_the_ID(), 'show_open_date', true );
          $close = get_post_meta( get_the_ID(), 'show_close_date', true );
        ?>
          <a href="<?php the_permalink(); ?>" class="show-card">
            <div class="img-wrap"><?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt=""><?php endif; ?></div>
            <div class="body">
              <?php if ( $open ) : ?><div class="dates"><?php echo esc_html( tlt_format_date_range( $open, $close ) ); ?></div><?php endif; ?>
              <h3><?php the_title(); ?></h3>
            </div>
          </a>
        <?php endwhile; ?>
      </div>
    <?php endif; ?>

  <?php else :
    // Top-level /shows/ page: one newest-to-oldest list of every production.
    // Each season is a heading (linking to the season page) followed by its
    // shows in reverse-chronological order. Earlier decades follow below.
    $seasons = get_terms( [ 'taxonomy' => 'tlt_season', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'DESC' ] );
    if ( ! empty( $seasons ) ) :
      foreach ( $seasons as $season ) :
        // Modern seasons (2010-2011+) listed here; earlier decades below.
        if ( preg_match( '/^(\d{4})/', $season->name, $sm ) && (int) $sm[1] < 2010 ) continue;
        $q = new WP_Query( [
            'post_type'      => 'tlt_show',
            'posts_per_page' => -1,
            'tax_query'      => [ [ 'taxonomy' => 'tlt_season', 'field' => 'term_id', 'terms' => $season->term_id ] ],
            'no_found_rows'  => true,
        ] );
        if ( ! $q->have_posts() ) continue;
        // Newest first within the season (reverse chronological); undated last.
        $items = [];
        foreach ( $q->posts as $p ) $items[] = [ 'p' => $p, 'open' => get_post_meta( $p->ID, 'show_open_date', true ) ];
        wp_reset_postdata();
        usort( $items, function ( $a, $b ) {
            if ( $a['open'] && $b['open'] ) return strcmp( $b['open'], $a['open'] );
            if ( $a['open'] ) return -1;
            if ( $b['open'] ) return 1;
            return strcmp( get_the_title( $b['p'] ), get_the_title( $a['p'] ) );
        } );
        $shows = [];
        foreach ( $items as $it ) {
            $shows[] = [ 'name' => get_the_title( $it['p'] ), 'pdf' => get_post_meta( $it['p']->ID, 'show_program_pdf_url', true ) ];
        }
    ?>
        <section class="season-list" style="margin: 2.25rem auto; max-width: 760px">
          <h2 style="text-align:center; margin-bottom: 0.75rem">
            <a href="<?php echo esc_url( get_term_link( $season ) ); ?>" style="color: var(--color-accent)"><?php echo esc_html( $season->name ); ?> Season &rarr;</a>
          </h2>
          <?php echo tlt_render_archive_list( $season->name, $shows ); ?>
        </section>
    <?php
      endforeach;
    endif;

    // Shows without a season tag (rare) — list them too.
    $no_season = new WP_Query( [
        'post_type' => 'tlt_show', 'posts_per_page' => -1,
        'tax_query' => [ [ 'taxonomy' => 'tlt_season', 'operator' => 'NOT EXISTS' ] ],
        'orderby' => 'title', 'order' => 'ASC', 'no_found_rows' => true,
    ] );
    if ( $no_season->have_posts() ) : ?>
      <section class="season-list" style="margin: 2.25rem auto; max-width: 760px">
        <h2 style="text-align:center; margin-bottom: 0.75rem">Other Productions</h2>
        <ul class="archive-list">
          <?php while ( $no_season->have_posts() ) : $no_season->the_post();
            $np = get_post_meta( get_the_ID(), 'show_program_pdf_url', true ); ?>
            <li class="archive-row">
              <a href="<?php the_permalink(); ?>" class="archive-row__title"><?php the_title(); ?></a>
              <span class="archive-row__btns"><?php if ( $np ) : ?><a href="<?php echo esc_url( $np ); ?>" target="_blank" rel="noopener" class="archive-btn archive-btn--program"><span class="archive-btn__icon" aria-hidden="true">&#128196;</span><span>Program</span></a><?php endif; ?></span>
            </li>
          <?php endwhile; wp_reset_postdata(); ?>
        </ul>
      </section>
    <?php endif;

    /* Earlier seasons (1918-2010): pulled from the decade-summary posts and
     * flattened into the SAME newest-to-oldest list as the modern seasons —
     * one clean section per season, linking to its decade page. */
    $decades = new WP_Query( [
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => [ [ 'key' => '_migration_legacy_url', 'value' => '/blog/(2015|tag)/[0-9]{4}-[0-9]{4}', 'compare' => 'REGEXP' ] ],
    ] );
    $earlier = [];
    if ( $decades->have_posts() ) {
        while ( $decades->have_posts() ) {
            $decades->the_post();
            $title = get_the_title();
            if ( ! preg_match( '/^(\d{4})-(\d{4})$/', $title, $m ) ) continue;
            if ( ( (int) $m[2] - (int) $m[1] ) < 10 ) continue;
            if ( $title === '2010-2020' ) continue; // covered by the modern seasons above
            $permalink = get_permalink();
            list( , $sections ) = tlt_parse_decade_body( get_the_content() );
            foreach ( $sections as $sec ) {
                if ( ! preg_match( '/^(\d{4})/', $sec['header'], $ym ) ) continue;
                $earlier[] = [
                    'header'    => $sec['header'],
                    'shows'     => array_reverse( $sec['shows'] ),  // newest first within the season
                    'permalink' => $permalink,
                    'start'     => (int) $ym[1],
                ];
            }
        }
        wp_reset_postdata();
    }
    usort( $earlier, function ( $a, $b ) { return $b['start'] - $a['start']; } ); // newest season first
    foreach ( $earlier as $es ) : ?>
        <section class="season-list" style="margin: 2.25rem auto; max-width: 760px">
          <h2 style="text-align:center; margin-bottom: 0.75rem">
            <a href="<?php echo esc_url( $es['permalink'] ); ?>" style="color: var(--color-accent)"><?php echo esc_html( $es['header'] ); ?> Season &rarr;</a>
          </h2>
          <?php echo tlt_render_archive_list( $es['header'], $es['shows'] ); ?>
        </section>
    <?php endforeach;
  endif; ?>
</div>

<?php get_footer(); ?>
