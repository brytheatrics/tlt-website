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
        // Order by open date when present, but include shows without it (LEFT-join style via meta_query)
        $q = new WP_Query( [
            'post_type' => 'tlt_show',
            'posts_per_page' => -1,
            'tax_query' => [ [ 'taxonomy' => 'tlt_season', 'field' => 'term_id', 'terms' => $season->term_id ] ],
            'meta_query' => [
                'relation' => 'OR',
                'has_open' => [ 'key' => 'show_open_date', 'compare' => 'EXISTS' ],
                'no_open'  => [ 'key' => 'show_open_date', 'compare' => 'NOT EXISTS' ],
            ],
            'orderby' => [ 'has_open' => 'ASC', 'meta_value' => 'ASC', 'title' => 'ASC' ],
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

    /* -------------------------------------------------------------------
     * Earlier seasons (1918-2010): pre-poster era — list every show by
     * season, pulled from the decade-summary posts. Each old decade post
     * has post_content shaped like:
     *   <h2>1980-81</h2><ul><li><a href="PDF">SHOW</a></li>...</ul>
     *   <h2>1981-82</h2><ul>…</ul>
     * We walk every decade post (excluding 2010-2020 since those seasons
     * are already covered by tlt_show entries above) and re-render the
     * season blocks here. ------------------------------------------------ */
    $decades = new WP_Query( [
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => [ [ 'key' => '_migration_legacy_url', 'value' => '/blog/2015/[0-9]{4}-[0-9]{4}', 'compare' => 'REGEXP' ] ],
    ] );
    $decade_blocks = []; // [start_year => ['title'=>'1980-1990', 'permalink'=>'/1980-1990/', 'sections'=>[['header'=>'1980-81','shows'=>[...]],…]]]
    if ( $decades->have_posts() ) {
        while ( $decades->have_posts() ) {
            $decades->the_post();
            $title = get_the_title();
            if ( ! preg_match( '/^(\d{4})-(\d{4})$/', $title, $m ) ) continue;
            $start = (int) $m[1]; $end = (int) $m[2];
            if ( ( $end - $start ) < 10 ) continue;
            if ( $title === '2010-2020' ) continue; // covered above by tlt_show grid

            $raw = get_the_content();
            $sections = [];
            // Split on <h2>...</h2>
            $parts = preg_split( '/<h2[^>]*>(.*?)<\/h2>/s', $raw, -1, PREG_SPLIT_DELIM_CAPTURE );
            for ( $i = 1; $i < count( $parts ); $i += 2 ) {
                $header = trim( wp_strip_all_tags( $parts[ $i ] ) );
                $body   = $parts[ $i + 1 ] ?? '';
                // Extract each <li>...<p>SHOW</p>...</li> entry. Capture optional PDF href in <a>.
                $shows = [];
                if ( preg_match_all( '/<li[^>]*>(.*?)<\/li>/s', $body, $items ) ) {
                    foreach ( $items[1] as $li ) {
                        $pdf = '';
                        if ( preg_match( '/href="([^"]+\.pdf)"/i', $li, $hm ) ) $pdf = $hm[1];
                        // Strip tags to get the show name
                        $name = trim( wp_strip_all_tags( $li ) );
                        if ( ! $name ) continue;
                        // Skip "Theatre was organized." flavour text in 1918 block
                        if ( stripos( $name, 'theatre was organized' ) !== false ) continue;
                        $shows[] = [ 'name' => $name, 'pdf' => $pdf ];
                    }
                }
                if ( $shows ) $sections[] = [ 'header' => $header, 'shows' => $shows ];
            }
            if ( $sections ) {
                $decade_blocks[ $start ] = [
                    'title'     => $title,
                    'permalink' => get_permalink(),
                    'sections'  => $sections,
                ];
            }
        }
        wp_reset_postdata();
    }
    krsort( $decade_blocks ); // newest decade first
    if ( $decade_blocks ) : ?>
      <section class="earlier-seasons" style="margin: 4rem 0 2rem; padding-top: 2rem; border-top: 2px solid var(--color-line)">
        <header style="text-align:center; margin-bottom: 2rem">
          <h2 style="margin-bottom:0.5rem">Earlier Seasons</h2>
          <p style="color: var(--color-muted); max-width:640px; margin: 0 auto">Productions from 1918 to 2010. Detailed pages and posters weren&rsquo;t kept for these early seasons — most have a program available as a PDF (links below).</p>
        </header>

        <?php foreach ( $decade_blocks as $start => $deco ) : ?>
          <div class="decade-block" style="margin: 2.5rem 0; padding: 1.5rem; background:#fafafa; border:1px solid var(--color-line); border-radius:4px">
            <h3 style="margin: 0 0 1rem"><a href="<?php echo esc_url( $deco['permalink'] ); ?>" style="color: var(--color-accent)"><?php echo esc_html( $deco['title'] ); ?> &mdash; Decade Summary &rarr;</a></h3>
            <div class="earlier-seasons-grid" style="display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.5rem">
              <?php foreach ( $deco['sections'] as $sec ) : ?>
                <div class="earlier-season">
                  <h4 style="margin: 0 0 0.5rem; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-text)"><?php echo esc_html( $sec['header'] ); ?></h4>
                  <ul style="list-style:none; padding:0; margin:0; font-size: 0.9rem; line-height: 1.55">
                    <?php foreach ( $sec['shows'] as $show ) : ?>
                      <li style="padding: 0.15rem 0">
                        <?php if ( $show['pdf'] ) : ?>
                          <a href="<?php echo esc_url( $show['pdf'] ); ?>" title="View program PDF"><?php echo esc_html( $show['name'] ); ?></a>
                        <?php else : ?>
                          <?php echo esc_html( $show['name'] ); ?>
                        <?php endif; ?>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif;
  endif; ?>
</div>

<?php get_footer(); ?>
