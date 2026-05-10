<?php
/**
 * Single news post: hero image (or first inline image) above title.
 */
get_header(); ?>

<div class="container page-content">
  <?php while ( have_posts() ) : the_post();
      // Find an image to display at top: featured image, then external thumb meta, then first inline image
      $top_img = get_the_post_thumbnail_url( get_the_ID(), 'large' );
      if ( ! $top_img ) $top_img = get_post_meta( get_the_ID(), '_thumbnail_external_url', true );

      $content = get_the_content();
      $stripped_first_image = false;
      if ( ! $top_img ) {
          // Pull the first <img src=...> out of content
          if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $content, $m ) ) {
              $top_img = $m[1];
          }
      }
      if ( $top_img ) {
          // Remove the first image (and its surrounding figure/anchor) from content so it isn't shown twice
          $content = preg_replace( '/<figure[^>]*>.*?<\/figure>/s', '', $content, 1, $count );
          if ( ! $count ) {
              $content = preg_replace( '/<a[^>]*>\s*<img[^>]+>\s*<\/a>/', '', $content, 1, $count );
          }
          if ( ! $count ) {
              $content = preg_replace( '/<img[^>]+>/', '', $content, 1 );
          }
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

  <?php endwhile; ?>
</div>

<?php get_footer(); ?>
