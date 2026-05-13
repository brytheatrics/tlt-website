<?php
/**
 * Flex block — Photo gallery (responsive grid)
 *
 * Args:
 *   images (array of [ 'url' => '...', 'alt' => '...', 'caption' => '...', 'link' => '...' ])
 *   heading (string, optional)
 *
 * For lightbox behavior, theme JS can intercept clicks on links and open a modal.
 * For now, links open the full-size image directly.
 */
$heading = isset( $args['heading'] ) ? $args['heading'] : '';
$images  = isset( $args['images'] ) && is_array( $args['images'] ) ? $args['images'] : [];
if ( ! $images ) return;
?>
<div class="flex-block flex-block--photo-gallery">
  <?php if ( $heading ) : ?>
    <h3><?php echo esc_html( $heading ); ?></h3>
  <?php endif; ?>
  <div class="photo-gallery">
    <?php foreach ( $images as $img ) :
      $url     = isset( $img['url'] ) ? esc_url( $img['url'] ) : '';
      $alt     = isset( $img['alt'] ) ? esc_attr( $img['alt'] ) : '';
      $caption = isset( $img['caption'] ) ? $img['caption'] : '';
      $link    = isset( $img['link'] ) ? esc_url( $img['link'] ) : $url;
      if ( ! $url ) continue;
    ?>
      <a href="<?php echo $link; ?>" class="gallery-item" target="_blank" rel="noopener">
        <img src="<?php echo $url; ?>" alt="<?php echo $alt; ?>" loading="lazy">
        <?php if ( $caption ) : ?>
          <span class="visually-hidden"><?php echo esc_html( $caption ); ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>
