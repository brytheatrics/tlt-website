<?php
/**
 * Flex block — Pull quote
 *
 * Args:
 *   text    (string, required — the quote itself)
 *   cite    (string, optional — attribution)
 */
$text = isset( $args['text'] ) ? $args['text'] : '';
$cite = isset( $args['cite'] ) ? $args['cite'] : '';
if ( ! $text ) return;
?>
<blockquote class="flex-block flex-block--pull-quote pull-quote">
  <?php echo esc_html( $text ); ?>
  <?php if ( $cite ) : ?>
    <cite><?php echo esc_html( $cite ); ?></cite>
  <?php endif; ?>
</blockquote>
