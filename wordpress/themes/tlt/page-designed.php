<?php
/**
 * Template Name: Designed Page
 *
 * For "image + headline + body + CTA(s)" pages. The workhorse for class
 * announcements, gift-of-tlt, partner perks, fundraising landings, special
 * event teasers — anything that's basically a promo poster + a button.
 *
 * Page meta:
 *   designed_desktop_image — full-width hero image (also accepts featured image)
 *   designed_mobile_image  — optional smaller image for narrow screens
 *   designed_subhead       — line below the headline
 *   designed_cta_1_label / _url / _style (primary|outline) / _target (_blank|null)
 *   designed_cta_2_label / _url / _style / _target
 *   designed_cta_3_label / _url / _style / _target
 *
 * Page body content goes between subhead and CTAs.
 */
get_header();

while ( have_posts() ) : the_post();
  $desktop = get_post_meta( get_the_ID(), 'designed_desktop_image', true );
  if ( ! $desktop && has_post_thumbnail() ) {
      $desktop = get_the_post_thumbnail_url( null, 'full' );
  }
  $mobile  = get_post_meta( get_the_ID(), 'designed_mobile_image', true );
  $subhead = get_post_meta( get_the_ID(), 'designed_subhead', true );

  $ctas = [];
  for ( $i = 1; $i <= 3; $i++ ) {
      $label  = get_post_meta( get_the_ID(), "designed_cta_{$i}_label", true );
      $url    = get_post_meta( get_the_ID(), "designed_cta_{$i}_url", true );
      $style  = get_post_meta( get_the_ID(), "designed_cta_{$i}_style", true );
      $target = get_post_meta( get_the_ID(), "designed_cta_{$i}_target", true );
      if ( $label && $url ) {
          $ctas[] = compact( 'label', 'url', 'style', 'target' );
      }
  }
?>

<section class="designed-page">
  <?php if ( $desktop || $mobile ) : ?>
    <div class="designed-page__hero">
      <?php if ( $desktop ) : ?>
        <img class="desktop-hero" src="<?php echo esc_url( $desktop ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
      <?php endif; ?>
      <?php if ( $mobile ) : ?>
        <img class="mobile-hero" src="<?php echo esc_url( $mobile ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="designed-page__content">
    <h1 class="designed-page__headline"><?php the_title(); ?></h1>
    <?php if ( $subhead ) : ?>
      <p class="designed-page__subhead"><?php echo esc_html( $subhead ); ?></p>
    <?php endif; ?>

    <div class="designed-page__body">
      <?php the_content(); ?>
    </div>

    <?php if ( $ctas ) : ?>
      <div class="designed-page__ctas">
        <?php foreach ( $ctas as $cta ) :
          $style  = $cta['style'] === 'outline' ? 'outline' : 'primary';
          $target = $cta['target'] === '_blank' ? '_blank' : '';
        ?>
          <a class="btn btn-<?php echo $style; ?>"
             href="<?php echo esc_url( $cta['url'] ); ?>"
             <?php if ( $target ) echo 'target="_blank" rel="noopener"'; ?>>
            <?php echo esc_html( $cta['label'] ); ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php endwhile;
get_footer(); ?>
