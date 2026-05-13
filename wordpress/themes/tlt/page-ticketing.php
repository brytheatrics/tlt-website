<?php
/**
 * Template Name: Ticketing
 *
 * Covers /ticketinfo, /season-tickets, /parking-information patterns.
 * Renders standard intro + body, then optionally a tier-card section if the
 * page has tier data stored in 'ticketing_tiers' meta (JSON array of
 * { heading, price, price_note, body }).
 */
get_header();

while ( have_posts() ) : the_post();
  $tiers_raw = get_post_meta( get_the_ID(), 'ticketing_tiers', true );
  $tiers = $tiers_raw ? json_decode( $tiers_raw, true ) : [];

  $cta_primary_label = get_post_meta( get_the_ID(), 'cta_primary_label', true );
  $cta_primary_url   = get_post_meta( get_the_ID(), 'cta_primary_url', true );
  $cta_secondary_label = get_post_meta( get_the_ID(), 'cta_secondary_label', true );
  $cta_secondary_url   = get_post_meta( get_the_ID(), 'cta_secondary_url', true );
?>

<?php if ( has_post_thumbnail() ) : ?>
  <div class="page-hero"><?php the_post_thumbnail( 'full' ); ?></div>
<?php endif; ?>

<div class="container page-content">
  <header class="page-header">
    <h1><?php the_title(); ?></h1>
    <?php if ( has_excerpt() ) : ?>
      <p class="page-subtitle"><?php echo esc_html( get_the_excerpt() ); ?></p>
    <?php endif; ?>
  </header>

  <article class="page-body">
    <?php the_content(); ?>

    <?php if ( is_array( $tiers ) && $tiers ) : ?>
      <section class="ticketing-tiers" aria-label="Pricing">
        <?php foreach ( $tiers as $tier ) : ?>
          <div class="ticketing-tier">
            <?php if ( ! empty( $tier['heading'] ) ) : ?>
              <h3><?php echo esc_html( $tier['heading'] ); ?></h3>
            <?php endif; ?>
            <?php if ( ! empty( $tier['price'] ) ) : ?>
              <p class="price"><?php echo esc_html( $tier['price'] ); ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $tier['price_note'] ) ) : ?>
              <p class="price-note"><?php echo esc_html( $tier['price_note'] ); ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $tier['body'] ) ) : ?>
              <?php echo wp_kses_post( wpautop( $tier['body'] ) ); ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if ( $cta_primary_url || $cta_secondary_url ) : ?>
      <div class="cta-row" style="justify-content:center;margin:3rem 0">
        <?php if ( $cta_primary_url ) : ?>
          <a class="btn btn-primary" href="<?php echo esc_url( $cta_primary_url ); ?>" target="_blank" rel="noopener">
            <?php echo esc_html( $cta_primary_label ?: 'Buy Tickets' ); ?>
          </a>
        <?php endif; ?>
        <?php if ( $cta_secondary_url ) : ?>
          <a class="btn btn-outline" href="<?php echo esc_url( $cta_secondary_url ); ?>" target="_blank" rel="noopener">
            <?php echo esc_html( $cta_secondary_label ?: 'Learn More' ); ?>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </article>
</div>

<?php endwhile;
get_footer(); ?>
