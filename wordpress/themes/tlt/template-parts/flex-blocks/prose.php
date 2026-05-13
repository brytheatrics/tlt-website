<?php
/**
 * Flex block — Prose
 *
 * Args:
 *   content (string, HTML allowed via wp_kses_post)
 */
$content = isset( $args['content'] ) ? $args['content'] : '';
if ( ! $content ) return;
?>
<div class="flex-block flex-block--prose">
  <?php echo wp_kses_post( $content ); ?>
</div>
