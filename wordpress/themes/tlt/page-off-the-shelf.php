<?php
/**
 * Template Name: Off The Shelf (dynamic)
 *
 * Hub page for the Off the Shelf staged-reading series.
 * - Intro / about content from the page body (Chris can edit normally)
 * - Auto-generated list of upcoming + past Off the Shelf events, grouped by season
 *
 * Off the Shelf events are stored as tlt_show records with
 * show_program_type='off_the_shelf'. Their URLs resolve at /off-the-shelf/<slug>/.
 */
get_header();

// Pull all Off the Shelf events grouped by season term
$events = get_posts( [
    'post_type'      => 'tlt_show',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_query'     => [
        [ 'key' => 'show_program_type', 'value' => 'off_the_shelf' ],
    ],
    'meta_key' => 'show_open_date',
    'orderby'  => 'meta_value',
    'order'    => 'DESC',
] );

// Group by season term slug
$by_season = [];
foreach ( $events as $event ) {
    $terms = wp_get_object_terms( $event->ID, 'tlt_season' );
    $season_name = $terms && ! is_wp_error( $terms ) ? $terms[0]->name : 'Other';
    $by_season[ $season_name ][] = $event;
}
// Sort seasons by name desc (most recent first)
krsort( $by_season );

$today = function_exists( 'tlt_today' ) ? tlt_today() : current_time( 'Y-m-d' );

?>

<?php if ( has_post_thumbnail() ) : ?>
  <div class="page-hero"><?php the_post_thumbnail( 'full' ); ?></div>
<?php endif; ?>

<div class="container page-content">
  <header class="page-header">
    <h1><?php the_title(); ?></h1>
    <?php if ( has_excerpt() ) : ?>
      <p class="page-subtitle"><?php echo esc_html( get_the_excerpt() ); ?></p>
    <?php endif; ?>
  </header>

  <article class="page-body">
    <?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>

    <?php if ( $by_season ) : ?>
      <?php foreach ( $by_season as $season_name => $season_events ) : ?>
        <section class="off-the-shelf-season" style="margin-top:3rem">
          <h2 class="section-heading">Off the Shelf <?php echo esc_html( $season_name ); ?></h2>
          <div class="off-the-shelf-list" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1.5rem">
            <?php foreach ( $season_events as $event ) :
              $open  = get_post_meta( $event->ID, 'show_open_date', true );
              $close = get_post_meta( $event->ID, 'show_close_date', true );
              $dir   = get_post_meta( $event->ID, 'show_director', true );
              $img   = get_the_post_thumbnail_url( $event, 'medium' );
              $is_upcoming = $open && $open > $today;
            ?>
              <a href="<?php echo esc_url( get_permalink( $event ) ); ?>" class="ots-card" style="display:block;background:#fff;border:1px solid var(--color-line);transition:transform 0.2s, box-shadow 0.2s">
                <?php if ( $img ) : ?>
                  <div class="ots-card__image" style="aspect-ratio:4/3;overflow:hidden;background:var(--color-soft)">
                    <img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( get_the_title( $event ) ); ?>" style="width:100%;height:100%;object-fit:cover;display:block">
                  </div>
                <?php endif; ?>
                <div style="padding:1rem">
                  <?php if ( $is_upcoming ) : ?>
                    <p style="font-size:0.75rem;color:var(--color-accent);text-transform:uppercase;letter-spacing:0.08em;margin:0 0 0.25rem">Upcoming</p>
                  <?php endif; ?>
                  <h3 style="margin:0 0 0.5rem;font-size:1.1rem;color:var(--color-text)"><?php echo esc_html( get_the_title( $event ) ); ?></h3>
                  <?php if ( $open ) : ?>
                    <p style="margin:0 0 0.25rem;font-size:0.9rem;color:var(--color-muted)"><?php echo esc_html( tlt_format_date_range( $open, $close ) ); ?></p>
                  <?php endif; ?>
                  <?php if ( $dir ) : ?>
                    <p style="margin:0;font-size:0.85rem;color:var(--color-muted)">Directed by <?php echo esc_html( $dir ); ?></p>
                  <?php endif; ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    <?php else : ?>
      <p style="color:var(--color-muted);margin:2rem 0;font-style:italic">No Off the Shelf events scheduled yet.</p>
    <?php endif; ?>
  </article>
</div>

<?php
// Reset post data so any footer queries don't get confused
wp_reset_postdata();
get_footer();
?>
