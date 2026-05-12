<?php
/**
 * Single post template.
 *
 * Two modes:
 *  - Decade-summary posts (slug matches YYYY-YYYY): render a stacked season-grid
 *    layout that mirrors /seasons/X/, with each season term in the decade
 *    appearing as its own show-card section. Falls back to post_content for
 *    seasons that have no tlt_show entries (older PDF-only eras).
 *  - All other posts: hero image (featured / first inline) + title + body.
 */
get_header(); ?>

<div class="container page-content">
  <?php while ( have_posts() ) : the_post();
      $slug = get_post_field( 'post_name', get_the_ID() );
      $is_decade_summary = (bool) preg_match( '/^(\d{4})-(\d{4})$/', $slug, $dm ) && ( (int) $dm[2] - (int) $dm[1] >= 10 );

      if ( $is_decade_summary ) :
          $decade_start = (int) $dm[1];
          $decade_end   = (int) $dm[2];
  ?>
    <header class="page-header" style="text-align:center">
      <h1><?php the_title(); ?></h1>
    </header>

    <?php
      // Intro from post_content (short blurb)
      $intro = trim( get_the_content() );
      if ( $intro ) :
    ?>
      <div class="decade-intro" style="max-width:780px;margin:0 auto 2rem;text-align:center;color:var(--color-muted)">
        <?php echo apply_filters( 'the_content', $intro ); ?>
      </div>
    <?php endif; ?>

    <?php
      // Walk every potential season inside this decade (newest first).
      for ( $sy = $decade_end - 1; $sy >= $decade_start; $sy-- ) :
          $season_name = sprintf( '%d-%d', $sy, $sy + 1 );
          $season_term = get_term_by( 'name', $season_name, 'tlt_season' );
          if ( ! $season_term ) continue;

          $q = new WP_Query( [
              'post_type'      => 'tlt_show',
              'posts_per_page' => -1,
              'tax_query'      => [ [ 'taxonomy' => 'tlt_season', 'field' => 'term_id', 'terms' => $season_term->term_id ] ],
              'meta_query'     => [
                  'relation' => 'OR',
                  'has_open' => [ 'key' => 'show_open_date', 'compare' => 'EXISTS' ],
                  'no_open'  => [ 'key' => 'show_open_date', 'compare' => 'NOT EXISTS' ],
              ],
              'orderby' => [ 'has_open' => 'ASC', 'meta_value' => 'ASC', 'title' => 'ASC' ],
              'order'   => 'ASC',
          ] );
          if ( ! $q->have_posts() ) continue;
    ?>
      <section style="margin: 2.5rem 0">
        <h2 style="text-align:center; margin-bottom: 1.25rem">
          <a href="<?php echo esc_url( get_term_link( $season_term ) ); ?>" style="color:var(--color-text)">
            <?php echo esc_html( $season_term->name ); ?> Season
          </a>
        </h2>
        <div class="show-grid">
          <?php while ( $q->have_posts() ) : $q->the_post();
            $img       = function_exists( 'tlt_show_image_url' ) ? tlt_show_image_url( get_the_ID(), 'medium_large' ) : get_post_meta( get_the_ID(), '_thumbnail_external_url', true );
            $open      = get_post_meta( get_the_ID(), 'show_open_date', true );
            $close     = get_post_meta( get_the_ID(), 'show_close_date', true );
            $director  = get_post_meta( get_the_ID(), 'show_director', true );
            $cancelled = get_post_meta( get_the_ID(), 'show_cancelled', true );
          ?>
            <a href="<?php the_permalink(); ?>" class="show-card<?php echo $cancelled ? ' status-cancelled' : ''; ?>">
              <div class="img-wrap">
                <?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt=""><?php endif; ?>
                <?php if ( $cancelled ) : ?><span class="status-badge status-closed">Cancelled</span><?php endif; ?>
              </div>
              <div class="body">
                <?php if ( $open ) : ?>
                  <div class="dates"><?php echo esc_html( function_exists('tlt_format_date_range') ? tlt_format_date_range( $open, $close ) : $open ); ?></div>
                <?php endif; ?>
                <h3><?php the_title(); ?></h3>
                <?php if ( $director ) : ?>
                  <p style="color:var(--color-muted);font-size:0.9rem;margin:0">Directed by <?php echo esc_html( $director ); ?></p>
                <?php endif; ?>
              </div>
            </a>
          <?php endwhile; wp_reset_postdata(); ?>
        </div>
      </section>
    <?php endfor; ?>

  <?php else :
      // Normal single-post layout
      $top_img = get_the_post_thumbnail_url( get_the_ID(), 'large' );
      if ( ! $top_img ) $top_img = get_post_meta( get_the_ID(), '_thumbnail_external_url', true );
      $content = get_the_content();
      if ( ! $top_img ) {
          if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $content, $m ) ) {
              $top_img = $m[1];
          }
      }
      if ( $top_img ) {
          $content = preg_replace( '/<figure[^>]*>.*?<\/figure>/s', '', $content, 1, $count );
          if ( ! $count ) $content = preg_replace( '/<a[^>]*>\s*<img[^>]+>\s*<\/a>/', '', $content, 1, $count );
          if ( ! $count ) $content = preg_replace( '/<img[^>]+>/', '', $content, 1 );
      }
  ?>
    <?php if ( $top_img ) : ?>
      <div class="post-hero-image">
        <img src="<?php echo esc_url( $top_img ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
      </div>
    <?php endif; ?>

    <header class="page-header" style="text-align:center">
      <h1><?php the_title(); ?></h1>
      <p style="color:var(--color-muted);font-size:0.9rem"><?php echo get_the_date(); ?></p>
    </header>

    <div class="post-body">
      <?php echo apply_filters( 'the_content', $content ); ?>
    </div>
  <?php endif; ?>

  <?php endwhile; ?>
</div>

<?php get_footer(); ?>
