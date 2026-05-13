<?php
/**
 * Flex block — Image + Text (2-column)
 *
 * Args:
 *   image_url   (string, required)
 *   alt         (string, recommended)
 *   heading     (string, optional)
 *   body        (string, HTML allowed)
 *   image_side  (string, 'left' | 'right' — default 'left')
 *   link_url    (string, optional — wraps image)
 */
$url     = isset( $args['image_url'] ) ? esc_url( $args['image_url'] ) : '';
$alt     = isset( $args['alt'] ) ? esc_attr( $args['alt'] ) : '';
$heading = isset( $args['heading'] ) ? $args['heading'] : '';
$body    = isset( $args['body'] ) ? $args['body'] : '';
$side    = isset( $args['image_side'] ) && $args['image_side'] === 'right' ? 'right' : 'left';
$link    = isset( $args['link_url'] ) ? esc_url( $args['link_url'] ) : '';

if ( ! $url ) return;
?>
<div class="flex-block flex-block--image-text image-text<?php echo $side === 'right' ? ' image-text--image-right' : ''; ?>">
  <div class="image-text__image">
    <?php if ( $link ) : ?><a href="<?php echo $link; ?>"><?php endif; ?>
      <img src="<?php echo $url; ?>" alt="<?php echo $alt; ?>" loading="lazy">
    <?php if ( $link ) : ?></a><?php endif; ?>
  </div>
  <div class="image-text__body">
    <?php if ( $heading ) : ?><h3><?php echo esc_html( $heading ); ?></h3><?php endif; ?>
    <?php if ( $body ) : echo wp_kses_post( $body ); endif; ?>
  </div>
</div>
