<?php
/**
 * Template Name: Guest Artist (Detail)
 *
 * Per-show guest artist landing page. The homepage's Guest Artists section
 * points here. Blake reuses ONE page for the season — each show he swaps out
 * the meta values below rather than creating a new page every time.
 *
 * Page meta:
 *   guest_eyebrow      — pill above the title (e.g. "For The Play That Goes Wrong")
 *   guest_meta         — small line under title (e.g. "Acrylic on canvas · 24×36")
 *   guest_flyer        — URL of the flyer image (ACF `guest_flyer_image` picker preferred)
 *   guest_website_url  — link the button opens (their portfolio, etsy, etc.)
 *   guest_button_label — override for the button text (default: "Visit Artist's Website")
 *
 * Body content (post_content) is the artist's bio / statement.
 */
get_header();

while ( have_posts() ) : the_post();
    $eyebrow      = get_post_meta( get_the_ID(), 'guest_eyebrow', true ) ?: 'Guest Artist';
    $meta         = get_post_meta( get_the_ID(), 'guest_meta', true );
    // ACF picker preferred, plain URL meta as fallback.
    $flyer        = ( function_exists( 'get_field' ) ? get_field( 'guest_flyer_image' ) : '' )
                    ?: get_post_meta( get_the_ID(), 'guest_flyer', true );
    $website_url  = get_post_meta( get_the_ID(), 'guest_website_url', true );
    $button_label = get_post_meta( get_the_ID(), 'guest_button_label', true ) ?: "Visit Artist's Website";
?>

<style>
  .ga-page { max-width: 900px; margin: 0 auto; padding: 2rem var(--pad) 4rem; }

  .ga-head { text-align: center; margin-bottom: 2rem; }
  .ga-head__eyebrow {
    display: inline-block; background: var(--color-accent); color: #fff;
    font-size: 0.7rem; letter-spacing: 0.1em; text-transform: uppercase;
    padding: 0.2rem 0.6rem; border-radius: 999px; margin-bottom: 0.75rem;
  }
  .ga-head__title { font-size: 2rem; margin: 0 0 0.4rem; line-height: 1.2; }
  .ga-head__meta { margin: 0; font-size: 0.95rem; color: var(--color-muted); line-height: 1.5; }

  .ga-flyer {
    display: block; margin: 0 auto 2.5rem; width: 100%;
    max-width: 620px; height: auto; border-radius: 4px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.14);
  }

  .ga-body { font-size: 1rem; max-width: 640px; margin: 0 auto; }
  .ga-body p { line-height: 1.7; margin: 0 0 1rem; }

  .ga-visit { text-align: center; margin-top: 2.5rem; }
  .ga-visit .btn { font-size: 1rem; padding: 0.75rem 1.75rem; }
</style>

<div class="ga-page">

  <div class="ga-head">
    <span class="ga-head__eyebrow"><?php echo esc_html( $eyebrow ); ?></span>
    <h1 class="ga-head__title"><?php the_title(); ?></h1>
    <?php if ( $meta ) : ?>
      <p class="ga-head__meta"><?php echo esc_html( $meta ); ?></p>
    <?php endif; ?>
  </div>

  <?php if ( $flyer ) : ?>
    <img class="ga-flyer" src="<?php echo esc_url( $flyer ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
  <?php endif; ?>

  <div class="ga-body">
    <?php the_content(); ?>

    <?php if ( $website_url ) : ?>
      <div class="ga-visit">
        <a class="btn btn-primary" href="<?php echo esc_url( $website_url ); ?>" target="_blank" rel="noopener">
          <?php echo esc_html( $button_label ); ?>
        </a>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php endwhile; get_footer(); ?>
