<?php
/**
 * Flex block — Button / CTA
 *
 * Args:
 *   label      (string, required)
 *   url        (string, required)
 *   style      (string, 'primary' | 'outline' — default 'primary')
 *   target     (string, '_blank' to open in new tab; null otherwise)
 */
$label  = isset( $args['label'] ) ? $args['label'] : '';
$url    = isset( $args['url'] ) ? esc_url( $args['url'] ) : '';
$style  = isset( $args['style'] ) && $args['style'] === 'outline' ? 'outline' : 'primary';
$target = isset( $args['target'] ) && $args['target'] === '_blank' ? '_blank' : '';

if ( ! $label || ! $url ) return;
?>
<p class="flex-block flex-block--button">
  <a class="btn btn-<?php echo esc_attr( $style ); ?>" href="<?php echo $url; ?>"<?php if ( $target ) echo ' target="_blank" rel="noopener"'; ?>>
    <?php echo esc_html( $label ); ?>
  </a>
</p>
