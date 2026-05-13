<?php
/**
 * Flex block — Figure (image + caption + optional float)
 *
 * Args:
 *   image_url (string, required)
 *   alt       (string, recommended for a11y)
 *   caption   (string, optional)
 *   align     (string, optional: 'left' | 'right' | null for centered)
 *   link_url  (string, optional — wraps image in a link)
 */
$url      = isset( $args['image_url'] ) ? esc_url( $args['image_url'] ) : '';
$alt      = isset( $args['alt'] )       ? esc_attr( $args['alt'] ) : '';
$caption  = isset( $args['caption'] )   ? $args['caption'] : '';
$align    = isset( $args['align'] )     ? $args['align'] : '';
$link_url = isset( $args['link_url'] )  ? esc_url( $args['link_url'] ) : '';

if ( ! $url ) return;

$classes = 'flex-block flex-block--figure';
if ( $align === 'left' )  $classes .= ' float-left';
if ( $align === 'right' ) $classes .= ' float-right';
?>
<figure class="<?php echo esc_attr( $classes ); ?>">
  <?php if ( $link_url ) : ?><a href="<?php echo $link_url; ?>"><?php endif; ?>
    <img src="<?php echo $url; ?>" alt="<?php echo $alt; ?>" loading="lazy">
  <?php if ( $link_url ) : ?></a><?php endif; ?>
  <?php if ( $caption ) : ?>
    <figcaption><?php echo esc_html( $caption ); ?></figcaption>
  <?php endif; ?>
</figure>
