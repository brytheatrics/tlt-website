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
    // Top-level /shows/ page: group by season
    $seasons = get_terms( [ 'taxonomy' => 'tlt_season', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'DESC' ] );
    if ( ! empty( $seasons ) ) :
      foreach ( $seasons as $season ) :
        $q = new WP_Query( [
            'post_type' => 'tlt_show',
            'posts_per_page' => -1,
            'tax_query' => [ [ 'taxonomy' => 'tlt_season', 'field' => 'term_id', 'terms' => $season->term_id ] ],
            'orderby' => 'meta_value',
            'meta_key' => 'show_open_date',
            'order' => 'ASC',
        ] );
        if ( ! $q->have_posts() ) continue;
  ?>
        <section style="margin: 2.5rem 0">
          <h2 style="text-align:center; margin-bottom: 1.25rem">
            <a href="<?php echo esc_url( get_term_link( $season ) ); ?>" style="color:var(--color-text)">
              <?php echo esc_html( $season->name ); ?> Season
            </a>
          </h2>
          <div class="show-grid">
            <?php while ( $q->have_posts() ) : $q->the_post();
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
            <?php endwhile; wp_reset_postdata(); ?>
          </div>
        </section>
  <?php
      endforeach;
    endif;

    // Shows without a season tag
    $no_season = new WP_Query( [
        'post_type' => 'tlt_show',
        'posts_per_page' => -1,
        'tax_query' => [ [ 'taxonomy' => 'tlt_season', 'operator' => 'NOT EXISTS' ] ],
        'orderby' => 'title',
        'order' => 'ASC',
    ] );
    if ( $no_season->have_posts() ) : ?>
      <section style="margin: 2.5rem 0; padding-top:2rem; border-top: 1px solid var(--color-line)">
        <h2 style="text-align:center; margin-bottom: 1.25rem">Other Productions</h2>
        <p style="text-align:center; color: var(--color-muted); margin-bottom: 1rem">Shows pending season assignment.</p>
        <div class="show-grid">
          <?php while ( $no_season->have_posts() ) : $no_season->the_post();
            $img = tlt_show_image_url( get_the_ID(), 'medium_large' );
          ?>
            <a href="<?php the_permalink(); ?>" class="show-card">
              <div class="img-wrap"><?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt=""><?php endif; ?></div>
              <div class="body">
                <h3><?php the_title(); ?></h3>
              </div>
            </a>
          <?php endwhile; wp_reset_postdata(); ?>
        </div>
      </section>
    <?php endif;
  endif; ?>
</div>

<?php get_footer(); ?>
