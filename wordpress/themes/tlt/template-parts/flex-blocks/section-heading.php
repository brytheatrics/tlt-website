<?php
/**
 * Flex block — Section heading (used as a divider/break in long pages)
 *
 * Args:
 *   text (string, required)
 *   id   (string, optional — anchor target for in-page nav)
 */
$text = isset( $args['text'] ) ? $args['text'] : '';
$id   = isset( $args['id'] ) ? sanitize_title( $args['id'] ) : '';
if ( ! $text ) return;
?>
<h2 class="flex-block flex-block--section-heading section-heading"<?php if ( $id ) echo ' id="' . esc_attr( $id ) . '"'; ?>>
  <?php echo esc_html( $text ); ?>
</h2>
