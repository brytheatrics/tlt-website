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
      // Split body into intro paragraph + rest-of-content.
      // Intro: rendered centered as a short blurb under the title.
      // Rest (typically <h2>Seasons</h2><ul>… and <h2>Year Summaries</h2><ul>…):
      //   rendered as two left-aligned columns inside a centered card so the
      //   bullets line up with their text instead of floating to the left.
      $raw = trim( get_the_content() );
      $intro = '';
      $rest  = '';
      if ( $raw ) {
          // Pull the first <p>…</p> out as intro; everything after = rest.
          if ( preg_match( '#^(\s*<p[^>]*>.*?</p>)\s*(.*)$#s', $raw, $m ) ) {
              $intro = $m[1];
              $rest  = trim( $m[2] );
          } else {
              $intro = $raw;
          }
      }
    ?>
    <?php if ( $intro ) : ?>
      <div class="decade-intro" style="max-width:780px;margin:0 auto 2rem;text-align:center;color:var(--color-muted)">
        <?php echo apply_filters( 'the_content', $intro ); ?>
      </div>
    <?php endif; ?>
    <?php // (Body sections beyond the intro are intentionally skipped on
          // decade-summary pages — the season-card grid below replaces them.) ?>

    <?php
      // Grid of season cards for this decade. Each card links to the season
      // archive page where visitors see the actual shows.
      $season_cards = [];
      for ( $sy = $decade_end - 1; $sy >= $decade_start; $sy-- ) {
          $season_name = sprintf( '%d-%d', $sy, $sy + 1 );
          $season_term = get_term_by( 'name', $season_name, 'tlt_season' );

          // Count DB shows in this season, if the term exists
          $show_count = 0;
          if ( $season_term ) {
              $q = new WP_Query( [
                  'post_type'      => 'tlt_show',
                  'posts_per_page' => -1,
                  'fields'         => 'ids',
                  'tax_query'      => [ [ 'taxonomy' => 'tlt_season', 'field' => 'term_id', 'terms' => $season_term->term_id ] ],
              ] );
              $show_count = $q->found_posts;
              wp_reset_postdata();
          }

          // Look for a year-summary post (slug matches YYYY-YYYY)
          $summary_post = get_page_by_path( $season_name, OBJECT, 'post' );

          // Build link target — prefer season archive when shows exist, otherwise summary
          $link = null;
          if ( $show_count > 0 && $season_term ) {
              $link = get_term_link( $season_term );
          } elseif ( $summary_post ) {
              $link = get_permalink( $summary_post );
          }

          $season_cards[] = [
              'name'        => $season_name,
              'show_count'  => $show_count,
              'link'        => $link,
              'has_summary' => (bool) $summary_post,
          ];
      }
    ?>
    <section class="decade-seasons" aria-label="Seasons in this decade">
      <div class="season-card-grid">
        <?php foreach ( $season_cards as $card ) : ?>
          <?php if ( $card['link'] ) : ?>
            <a href="<?php echo esc_url( $card['link'] ); ?>" class="season-card">
              <div class="season-card__name"><?php echo esc_html( $card['name'] ); ?></div>
              <div class="season-card__meta">
                <?php if ( $card['show_count'] > 0 ) : ?>
                  <?php echo (int) $card['show_count']; ?> show<?php echo $card['show_count'] === 1 ? '' : 's'; ?>
                <?php elseif ( $card['has_summary'] ) : ?>
                  Summary
                <?php endif; ?>
              </div>
            </a>
          <?php else : ?>
            <div class="season-card season-card--empty" aria-hidden="true">
              <div class="season-card__name"><?php echo esc_html( $card['name'] ); ?></div>
              <div class="season-card__meta">No records yet</div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </section>

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
