<?php
/**
 * Flex block — Video embed (oEmbed-compatible URL: YouTube, Vimeo, etc.)
 *
 * Args:
 *   url     (string, required)
 *   caption (string, optional)
 */
$url     = isset( $args['url'] ) ? esc_url( $args['url'] ) : '';
$caption = isset( $args['caption'] ) ? $args['caption'] : '';
if ( ! $url ) return;
$embed = wp_oembed_get( $url, [ 'width' => 800 ] );
?>
<div class="flex-block flex-block--video-embed video-embed">
  <?php
    if ( $embed ) {
        // wp_oembed_get returns a string of HTML; let it through
        echo $embed;
    } else {
        // Fallback for non-oEmbed URLs (e.g. self-hosted, raw iframe URL)
        echo '<iframe src="' . $url . '" allow="autoplay; fullscreen" allowfullscreen frameborder="0"></iframe>';
    }
  ?>
  <?php if ( $caption ) : ?>
    <p class="image-caption"><?php echo esc_html( $caption ); ?></p>
  <?php endif; ?>
</div>
