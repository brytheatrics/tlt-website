<?php
/**
 * Flex block — Sponsor / partner logo row
 *
 * Args:
 *   heading (string, optional)
 *   logos   (array of [ 'url' => 'image url', 'alt' => '...', 'link' => 'optional href' ])
 */
$heading = isset( $args['heading'] ) ? $args['heading'] : '';
$logos   = isset( $args['logos'] ) && is_array( $args['logos'] ) ? $args['logos'] : [];
if ( ! $logos ) return;
?>
<div class="flex-block flex-block--logo-row">
  <?php if ( $heading ) : ?><h3 class="section-heading"><?php echo esc_html( $heading ); ?></h3><?php endif; ?>
  <div class="logo-row">
    <?php foreach ( $logos as $logo ) :
      $url  = isset( $logo['url'] ) ? esc_url( $logo['url'] ) : '';
      $alt  = isset( $logo['alt'] ) ? esc_attr( $logo['alt'] ) : '';
      $link = isset( $logo['link'] ) ? esc_url( $logo['link'] ) : '';
      if ( ! $url ) continue;
    ?>
      <?php if ( $link ) : ?>
        <a href="<?php echo $link; ?>" target="_blank" rel="noopener">
          <img src="<?php echo $url; ?>" alt="<?php echo $alt; ?>" loading="lazy">
        </a>
      <?php else : ?>
        <img src="<?php echo $url; ?>" alt="<?php echo $alt; ?>" loading="lazy">
      <?php endif; ?>
    <?php endforeach; ?>
  </div>
</div>
