<?php
/**
 * Template Name: Post Listing
 *
 * Renders the latest N posts of a chosen category. Used for /press,
 * /job-openings, and similar pages where Squarespace had a "summary block"
 * pulling from a category.
 *
 * Page meta:
 *   listing_category_slug — category slug to filter (e.g. 'press', 'job-openings')
 *   listing_per_page      — how many posts to show (default 12)
 *   listing_show_thumbs   — '1' to render thumbnails, '0' to hide
 *
 * The page's own post_content renders above the listing as intro/instructions.
 */
get_header();

while ( have_posts() ) : the_post();
  $category_slug = get_post_meta( get_the_ID(), 'listing_category_slug', true );
  $per_page      = (int) ( get_post_meta( get_the_ID(), 'listing_per_page', true ) ?: 12 );
  $show_thumbs   = get_post_meta( get_the_ID(), 'listing_show_thumbs', true ) !== '0';

  $posts_query = null;
  if ( $category_slug ) {
      $posts_query = new WP_Query( [
          'post_type'      => 'post',
          'category_name'  => $category_slug,
          'posts_per_page' => $per_page,
          'paged'          => max( 1, get_query_var( 'paged' ) ),
      ] );
  }
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
    <?php the_content(); ?>

    <?php if ( $posts_query && $posts_query->have_posts() ) : ?>
      <section class="post-list" aria-label="<?php echo esc_attr( get_the_title() ); ?> listings">
        <?php while ( $posts_query->have_posts() ) : $posts_query->the_post(); ?>
          <article class="post-list__item">
            <?php if ( $show_thumbs && has_post_thumbnail() ) : ?>
              <a class="post-list__thumb" href="<?php the_permalink(); ?>">
                <?php the_post_thumbnail( 'thumbnail' ); ?>
              </a>
            <?php endif; ?>
            <div class="post-list__body">
              <p class="post-list__meta"><?php echo esc_html( get_the_date() ); ?></p>
              <h3 class="post-list__title">
                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
              </h3>
              <p class="post-list__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 24 ) ); ?></p>
              <a class="post-list__more" href="<?php the_permalink(); ?>">Read more →</a>
            </div>
          </article>
        <?php endwhile; ?>
      </section>

      <?php
        // Pagination
        $big = 999999999;
        echo '<nav class="pagination" style="margin-top:2rem;text-align:center">';
        echo paginate_links( [
            'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
            'format'    => '?paged=%#%',
            'current'   => max( 1, get_query_var( 'paged' ) ),
            'total'     => $posts_query->max_num_pages,
            'prev_text' => '← Previous',
            'next_text' => 'Next →',
        ] );
        echo '</nav>';
        wp_reset_postdata();
      ?>
    <?php elseif ( $category_slug ) : ?>
      <p style="color:var(--color-muted);margin:2rem 0;font-style:italic">No posts found in this category yet.</p>
    <?php endif; ?>
  </article>
</div>

<?php endwhile;
get_footer(); ?>
