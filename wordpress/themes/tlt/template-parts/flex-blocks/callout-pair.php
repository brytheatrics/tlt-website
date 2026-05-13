<?php
/**
 * Flex block — Two-column callout pair (e.g. address + hours, two pricing tiers)
 *
 * Args:
 *   left  (array of [ 'heading' => '...', 'body' => 'HTML' ])
 *   right (array of [ 'heading' => '...', 'body' => 'HTML' ])
 */
$left  = isset( $args['left'] )  && is_array( $args['left'] )  ? $args['left']  : null;
$right = isset( $args['right'] ) && is_array( $args['right'] ) ? $args['right'] : null;
if ( ! $left && ! $right ) return;
?>
<div class="flex-block flex-block--callout-pair callout-pair">
  <?php foreach ( [ $left, $right ] as $col ) :
    if ( ! $col ) continue;
    $heading = isset( $col['heading'] ) ? $col['heading'] : '';
    $body    = isset( $col['body'] ) ? $col['body'] : '';
  ?>
    <div class="callout-pair__col">
      <?php if ( $heading ) : ?><h3><?php echo esc_html( $heading ); ?></h3><?php endif; ?>
      <?php if ( $body ) : echo wp_kses_post( $body ); endif; ?>
    </div>
  <?php endforeach; ?>
</div>
