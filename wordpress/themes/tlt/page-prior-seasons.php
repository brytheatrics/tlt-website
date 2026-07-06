<?php
/**
 * Template Name: Prior Seasons
 *
 * Lists all historical content: modern season landing pages + decade summary posts.
 */
get_header(); ?>

<style>
  .seasons-page { max-width: 1000px; margin: 0 auto; padding: 0 var(--pad); }
  .seasons-hero { text-align: center; padding: 4rem 0 2rem; }
  .seasons-hero h1 { margin-bottom: 0.75rem; }
  .seasons-hero .lede { color: var(--color-muted); max-width: 720px; margin: 0 auto; }
  .seasons-section { padding: 2rem 0; }
  .seasons-section h2 {
    color: var(--color-accent);
    border-bottom: 2px solid var(--color-line);
    padding-bottom: 0.5rem;
    margin-bottom: 1.5rem;
  }
  .seasons-list { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.75rem 2rem; }
  .seasons-list li a {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.85rem 1rem;
    background: #fff;
    border: 1px solid var(--color-line);
    border-radius: 4px;
    color: var(--color-text);
    text-decoration: none;
    font-weight: 500;
    transition: border-color 0.15s, transform 0.15s, color 0.15s;
  }
  .seasons-list li a:hover {
    border-color: var(--color-accent);
    color: var(--color-accent);
    transform: translateX(2px);
    text-decoration: none;
  }
  .seasons-list .count { color: var(--color-muted); font-size: 0.85rem; font-weight: 400; }
  .seasons-list .arrow { color: var(--color-accent); font-weight: 700; margin-left: 0.5rem; }
</style>

<div class="seasons-page">

  <div class="seasons-hero">
    <h1>Prior Seasons</h1>
    <p class="lede">Tacoma Little Theatre has been telling stories since 1918. Browse our archive by season or decade.</p>
  </div>

  <?php
  // What decade are we currently in? (rough: today's year rounded to start of decade)
  $current_decade_start = (int) ( ( (int) date( 'Y' ) ) / 10 ) * 10;
  // Current-decade seasons: start year >= current_decade_start
  $current_season_term = function_exists('tlt_get_current_season_term') ? tlt_get_current_season_term() : null;
  $next_season_term    = function_exists('tlt_get_next_season_term')    ? tlt_get_next_season_term()    : null;
  $current_id = $current_season_term ? $current_season_term->term_id : 0;
  $next_id    = $next_season_term    ? $next_season_term->term_id    : 0;
  $seasons = get_terms( [
      'taxonomy' => 'tlt_season',
      'hide_empty' => true,
      'orderby' => 'name',
      'order' => 'DESC',
  ] );
  $current_decade_seasons = [];
  if ( ! is_wp_error( $seasons ) && ! empty( $seasons ) ) {
      foreach ( $seasons as $term ) {
          if ( $term->term_id === $current_id ) continue; // skip the actively-running season
          if ( $next_id && $term->term_id === $next_id ) continue; // skip the not-yet-started next season
          // Parse season name like "2024-2025" — first year tells us the decade
          if ( preg_match( '/^(\d{4})-/', $term->name, $m ) ) {
              if ( (int) $m[1] >= $current_decade_start ) {
                  $current_decade_seasons[] = $term;
              }
          }
      }
  }
  if ( ! empty( $current_decade_seasons ) ): ?>
    <section class="seasons-section">
      <h2>This Decade &mdash; By Season</h2>
      <ul class="seasons-list">
        <?php foreach ( $current_decade_seasons as $term ) : ?>
          <li>
            <a href="<?php echo esc_url( get_term_link( $term ) ); ?>">
              <span><?php echo esc_html( $term->name ); ?> Season</span>
              <span><span class="count"><?php echo $term->count; ?> show<?php echo $term->count !== 1 ? 's' : ''; ?></span> <span class="arrow">&rarr;</span></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <?php
  // Past decades: only true decade-spanning summaries (YYYY-YYYY where end - start >= 10) OR known decade slugs
  $decade_q = new WP_Query( [
      'post_type' => 'post',
      'posts_per_page' => -1,
      'meta_query' => [
          [ 'key' => '_migration_legacy_url', 'value' => '/blog/(2015|tag)/[0-9]{4}-[0-9]{4}', 'compare' => 'REGEXP' ],
      ],
      'orderby' => 'title',
      'order' => 'DESC',
  ] );
  $decade_posts = [];
  $year_posts = [];
  if ( $decade_q->have_posts() ) {
      while ( $decade_q->have_posts() ) {
          $decade_q->the_post();
          $title = get_the_title();
          if ( preg_match( '/^(\d{4})-(\d{4})$/', $title, $m ) ) {
              $start = (int) $m[1]; $end = (int) $m[2];
              if ( ( $end - $start ) >= 10 ) {
                  $decade_posts[] = [ 'id' => get_the_ID(), 'title' => $title, 'permalink' => get_permalink() ];
              } else {
                  $year_posts[] = [ 'id' => get_the_ID(), 'title' => $title, 'permalink' => get_permalink(), 'start' => $start ];
              }
          }
      }
      wp_reset_postdata();
  }
  // Also include the 2010-2020 decade page we just created
  $extra_q = new WP_Query( [
      'post_type' => 'post', 'posts_per_page' => -1, 'name' => '2010-2020',
  ] );
  if ( $extra_q->have_posts() ) {
      while ( $extra_q->have_posts() ) {
          $extra_q->the_post();
          $decade_posts[] = [ 'id' => get_the_ID(), 'title' => get_the_title(), 'permalink' => get_permalink() ];
      }
      wp_reset_postdata();
  }
  // Dedupe by title (some decades have multiple migrated copies in the DB)
  $seen_titles = [];
  $decade_posts = array_values( array_filter( $decade_posts, function ( $p ) use ( &$seen_titles ) {
      $t = trim( $p['title'] );
      if ( isset( $seen_titles[ $t ] ) ) return false;
      $seen_titles[ $t ] = true;
      return true;
  } ) );
  // Sort decades DESC by start year
  usort( $decade_posts, function ( $a, $b ) {
      preg_match( '/^(\d{4})/', $a['title'], $aa );
      preg_match( '/^(\d{4})/', $b['title'], $bb );
      return ((int)($bb[1] ?? 0)) - ((int)($aa[1] ?? 0));
  } );
  if ( ! empty( $decade_posts ) ): ?>
    <section class="seasons-section">
      <h2>Past Decades</h2>
      <ul class="seasons-list">
        <?php foreach ( $decade_posts as $p ) : ?>
          <li>
            <a href="<?php echo esc_url( $p['permalink'] ); ?>">
              <span><?php echo esc_html( $p['title'] ); ?></span>
              <span class="arrow">&rarr;</span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>

  <section class="seasons-section">
    <h2>Browse Everything</h2>
    <p style="color:var(--color-muted)">Looking for a specific show? <a href="/shows/">View all shows in our archive &rarr;</a></p>
  </section>

</div>

<?php get_footer(); ?>
