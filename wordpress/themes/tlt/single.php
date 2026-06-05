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
      // Parse the decade body into a clean intro (text before the first season
      // heading) plus per-season sections. Each season is re-rendered below as a
      // uniform archive-list with [Photos]/[Program] buttons.
      list( $decade_intro, $decade_sections ) = tlt_parse_decade_body( get_the_content() );
      // Use the body only when its sections are actual season lists (YYYY-YY
      // headers). Modern decades (e.g. 2010-2020) instead carry "Seasons" /
      // "Year Summaries" nav, or nothing — for those, build the season lists
      // from tlt_show records so they match the other decade pages.
      $has_year_sections = false;
      foreach ( $decade_sections as $sec ) {
          if ( preg_match( '/^\d{4}[-\x{2013}]\d{2,4}/u', $sec['header'] ) ) { $has_year_sections = true; break; }
      }
      if ( ! $has_year_sections ) {
          $decade_sections = tlt_decade_record_sections( $decade_start, $decade_end );
          $decade_intro = ''; // drop the legacy nav intro; the season lists stand on their own
      }
    ?>
    <?php if ( $decade_intro ) : ?>
      <div class="decade-intro" style="max-width:780px;margin:0 auto 2rem;text-align:center;color:var(--color-muted)">
        <?php echo apply_filters( 'the_content', $decade_intro ); ?>
      </div>
    <?php endif; ?>

    <?php // Each season rendered like the 2005-06 block — one row per show
          // with [Photos] (when a show page exists) + [Program] buttons. ?>
    <?php if ( $decade_sections ) : ?>
      <div class="decade-archive-seasons" style="margin-top:3rem">
        <?php foreach ( $decade_sections as $sec ) : ?>
          <section class="archive-season" style="margin:2.5rem auto;max-width:720px">
            <h2 style="text-align:center;color:var(--color-accent);margin-bottom:1rem"><?php echo esc_html( $sec['header'] ); ?></h2>
            <?php echo tlt_render_archive_list( $sec['header'], $sec['shows'] ); ?>
          </section>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

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
