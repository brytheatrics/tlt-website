<?php
/**
 * Flex block — PDF link list (e.g. decade programs, audition packets, downloadable resources)
 *
 * Args:
 *   heading (string, optional)
 *   links   (array of [ 'label' => '...', 'url' => '...' ])
 */
$heading = isset( $args['heading'] ) ? $args['heading'] : '';
$links   = isset( $args['links'] ) && is_array( $args['links'] ) ? $args['links'] : [];
if ( ! $links ) return;
?>
<div class="flex-block flex-block--pdf-list pdf-list">
  <?php if ( $heading ) : ?>
    <h3><?php echo esc_html( $heading ); ?></h3>
  <?php endif; ?>
  <ul class="pdf-list">
    <?php foreach ( $links as $link ) :
      $label = isset( $link['label'] ) ? $link['label'] : '';
      $url   = isset( $link['url'] ) ? esc_url( $link['url'] ) : '';
      if ( ! $label || ! $url ) continue;
    ?>
      <li><a href="<?php echo $url; ?>" target="_blank" rel="noopener"><?php echo esc_html( $label ); ?></a></li>
    <?php endforeach; ?>
  </ul>
</div>
