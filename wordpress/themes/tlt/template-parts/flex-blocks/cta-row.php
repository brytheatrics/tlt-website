<?php
/**
 * Flex block — CTA row (multiple buttons)
 *
 * Args:
 *   buttons (array of [ 'label' => '...', 'url' => '...', 'style' => 'primary|outline', 'target' => '_blank|null' ])
 */
$buttons = isset( $args['buttons'] ) && is_array( $args['buttons'] ) ? $args['buttons'] : [];
if ( ! $buttons ) return;
?>
<div class="flex-block flex-block--cta-row cta-row">
  <?php foreach ( $buttons as $btn ) :
    $label  = isset( $btn['label'] ) ? $btn['label'] : '';
    $url    = isset( $btn['url'] ) ? esc_url( $btn['url'] ) : '';
    $style  = isset( $btn['style'] ) && $btn['style'] === 'outline' ? 'outline' : 'primary';
    $target = isset( $btn['target'] ) && $btn['target'] === '_blank' ? '_blank' : '';
    if ( ! $label || ! $url ) continue;
  ?>
    <a class="btn btn-<?php echo esc_attr( $style ); ?>" href="<?php echo $url; ?>"<?php if ( $target ) echo ' target="_blank" rel="noopener"'; ?>>
      <?php echo esc_html( $label ); ?>
    </a>
  <?php endforeach; ?>
</div>
