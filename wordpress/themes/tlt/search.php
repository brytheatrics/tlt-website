<?php
/**
 * Search results template.
 *
 * Groups results by post type — Shows, Pages, Posts (news) — and renders
 * each group with appropriate metadata (dates for shows, excerpt for posts).
 */
get_header();

$query = get_search_query();
$total = $wp_query->found_posts;

// Re-organize results by post type for grouped display
$by_type = [
    'tlt_show' => [],
    'page'     => [],
    'post'     => [],
];
while ( have_posts() ) {
    the_post();
    $pt = get_post_type();
    if ( isset( $by_type[ $pt ] ) ) {
        $by_type[ $pt ][] = get_the_ID();
    }
}

$type_labels = [
    'tlt_show' => 'Shows',
    'page'     => 'Pages',
    'post'     => 'News & Posts',
];
?>

<div class="container page-content">
  <header class="page-header">
    <h1>Search Results</h1>
    <?php if ( $query ) : ?>
      <p class="page-subtitle">
        <?php echo $total; ?> result<?php echo $total === 1 ? '' : 's'; ?>
        for "<strong><?php echo esc_html( $query ); ?></strong>"
      </p>
    <?php endif; ?>
  </header>

  <article class="page-body">
    <?php if ( $total === 0 ) : ?>
      <p>No matches found. Try different keywords, or
        <a href="/shows/">browse all shows</a>,
        <a href="/prior-seasons/">prior seasons</a>, or
        <a href="/contact/">contact us</a>.
      </p>
    <?php else : ?>
      <?php foreach ( $type_labels as $pt => $label ) :
        $ids = $by_type[ $pt ];
        if ( ! $ids ) continue;
      ?>
        <section class="search-results__group">
          <h2><?php echo esc_html( $label ); ?> (<?php echo count( $ids ); ?>)</h2>
          <?php foreach ( $ids as $id ) : ?>
            <div class="search-results__item">
              <?php if ( $pt === 'tlt_show' ) :
                $open = get_post_meta( $id, 'show_open_date', true );
                $close = get_post_meta( $id, 'show_close_date', true );
                $dir = get_post_meta( $id, 'show_director', true );
              ?>
                <span class="post-type-label">Show</span>
                <h3><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a></h3>
                <?php if ( $open ) : ?>
                  <p style="margin:0 0 0.25rem;color:var(--color-muted)">
                    <?php echo esc_html( tlt_format_date_range( $open, $close ) ); ?>
                    <?php if ( $dir ) echo ' · Directed by ' . esc_html( $dir ); ?>
                  </p>
                <?php endif; ?>
                <p style="margin:0;color:var(--color-muted)"><?php echo esc_html( wp_trim_words( get_post_field( 'post_content', $id ), 28 ) ); ?></p>
              <?php else : ?>
                <span class="post-type-label"><?php echo $pt === 'post' ? 'Post · ' . esc_html( get_the_date( '', $id ) ) : 'Page'; ?></span>
                <h3><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a></h3>
                <p style="margin:0;color:var(--color-muted)"><?php echo esc_html( wp_trim_words( get_post_field( 'post_content', $id ), 32 ) ); ?></p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </section>
      <?php endforeach; ?>

      <?php the_posts_pagination( [
          'prev_text' => '← Previous',
          'next_text' => 'Next →',
      ] ); ?>
    <?php endif; ?>
  </article>
</div>

<?php get_footer(); ?>
