<?php
/**
 * Flex block — Full-bleed banner image
 *
 * Args:
 *   image_url (string, required)
 *   alt       (string, recommended)
 *   caption   (string, optional — overlaid or below depending on design)
 */
$url     = isset( $args['image_url'] ) ? esc_url( $args['image_url'] ) : '';
$alt     = isset( $args['alt'] ) ? esc_attr( $args['alt'] ) : '';
$caption = isset( $args['caption'] ) ? $args['caption'] : '';

if ( ! $url ) return;
?>
<div class="flex-block flex-block--full-bleed full-bleed">
  <img src="<?php echo $url; ?>" alt="<?php echo $alt; ?>" loading="lazy">
  <?php if ( $caption ) : ?>
    <p class="image-caption" style="margin-top:0.5rem"><?php echo esc_html( $caption ); ?></p>
  <?php endif; ?>
</div>
