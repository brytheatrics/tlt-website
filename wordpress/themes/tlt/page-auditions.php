<?php
/**
 * Template Name: Auditions Hub
 *
 * Single hub page for all current auditions. Replaces the Squarespace pattern
 * of one page per audition (43 of those were trashed in triage).
 *
 * Data sources, in priority order:
 *   1. tlt_show records with show_audition_dates / show_audition_status meta
 *      (current shows whose auditions haven't happened yet)
 *   2. Manual rows entered in page content (for special-case auditions
 *      that aren't tied to a regular show)
 *   3. Standard intro / instructions / FAQ content in the page body
 *
 * Status values for show_audition_status:
 *   - 'open'      → "Sign up now" CTA visible
 *   - 'scheduled' → dates listed but signups not yet open
 *   - 'cast'     → "This show has been cast" (kept on page for a short window)
 *   - 'closed'    → hidden from page entirely
 */
get_header();

// ---- Pull upcoming auditions from show records ----
$today = function_exists( 'tlt_today' ) ? tlt_today() : current_time( 'Y-m-d' );

$audition_shows = get_posts( [
    'post_type'      => 'tlt_show',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_query'     => [
        'relation' => 'AND',
        [ 'key' => 'show_audition_status', 'value' => [ 'open', 'scheduled', 'cast' ], 'compare' => 'IN' ],
        [ 'key' => 'show_open_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ],
    ],
    'meta_key' => 'show_open_date',
    'orderby'  => 'meta_value',
    'order'    => 'ASC',
] );

?>

<?php if ( has_post_thumbnail() ) : ?>
  <div class="page-hero">
    <?php the_post_thumbnail( 'full' ); ?>
  </div>
<?php endif; ?>

<div class="container page-content">
  <header class="page-header">
    <h1><?php the_title(); ?></h1>
    <p class="page-subtitle">Audition opportunities at Tacoma Little Theatre</p>
  </header>

  <?php while ( have_posts() ) : the_post(); ?>
    <article class="page-body">
      <?php the_content(); ?>

      <?php if ( ! empty( $audition_shows ) ) : ?>
        <section class="audition-list" aria-labelledby="audition-list-heading">
          <h2 id="audition-list-heading" class="section-heading">Current Auditions</h2>
          <?php foreach ( $audition_shows as $show ) :
              $title        = get_the_title( $show );
              $director     = get_post_meta( $show->ID, 'show_director', true );
              $audition_dt  = get_post_meta( $show->ID, 'show_audition_dates', true );
              $audition_loc = get_post_meta( $show->ID, 'show_audition_location', true );
              $packet_url   = get_post_meta( $show->ID, 'show_audition_packet_url', true );
              $signup_url   = get_post_meta( $show->ID, 'show_audition_signup_url', true );
              $status       = get_post_meta( $show->ID, 'show_audition_status', true );
              $logo_url     = get_post_meta( $show->ID, 'show_logo_url', true ); // optional small logo

              // Default audition location if not set
              if ( ! $audition_loc ) {
                  $audition_loc = 'Tacoma Little Theatre · 210 N "I" Street, Tacoma WA';
              }
          ?>
            <div class="audition-row audition-row--<?php echo esc_attr( $status ); ?>">
              <?php if ( $logo_url ) : ?>
                <div class="audition-row__logo">
                  <img src="<?php echo esc_url( $logo_url ); ?>" alt="">
                </div>
              <?php endif; ?>
              <div class="audition-row__info">
                <h3 class="audition-row__title">
                  <a href="<?php echo esc_url( get_permalink( $show ) ); ?>"><?php echo esc_html( $title ); ?></a>
                </h3>
                <?php if ( $audition_dt ) : ?>
                  <p class="audition-row__dates"><?php echo esc_html( $audition_dt ); ?></p>
                <?php endif; ?>
                <?php if ( $director ) : ?>
                  <p class="audition-row__director">Directed by <?php echo esc_html( $director ); ?></p>
                <?php endif; ?>
                <p class="audition-row__location"><?php echo esc_html( $audition_loc ); ?></p>

                <?php if ( $status === 'cast' ) : ?>
                  <p class="audition-row__status audition-row__status--cast">This show has been cast.</p>
                <?php elseif ( $status === 'scheduled' ) : ?>
                  <p class="audition-row__status audition-row__status--scheduled">Sign-ups open one month before audition dates.</p>
                <?php elseif ( $status === 'open' && $signup_url ) : ?>
                  <p class="audition-row__cta">
                    <a class="btn btn-primary" href="<?php echo esc_url( $signup_url ); ?>" target="_blank" rel="noopener">Schedule an Audition</a>
                    <?php if ( $packet_url ) : ?>
                      <a class="btn btn-outline" href="<?php echo esc_url( $packet_url ); ?>" target="_blank" rel="noopener">Audition Packet (PDF)</a>
                    <?php endif; ?>
                  </p>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </section>
      <?php else : ?>
        <section class="audition-list audition-list--empty">
          <h2 class="section-heading">Current Auditions</h2>
          <p>No auditions are currently scheduled. Check back soon, or
            <a href="https://tlt.ludus.com/subscribe.php" target="_blank" rel="noopener">join our email list</a>
            to be notified when new auditions are announced.</p>
        </section>
      <?php endif; ?>
    </article>
  <?php endwhile; ?>
</div>

<?php get_footer(); ?>
