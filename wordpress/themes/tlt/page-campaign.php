<?php
/**
 * Template Name: Campaign
 *
 * Fundraising / capital campaign landing page (Flush, annual fund, future
 * capital campaigns). Hero + lead + body + donate CTA band + optional donor list.
 *
 * Page meta:
 *   campaign_hero_caption    — small italic caption under the hero image
 *   campaign_lead            — large display-font paragraph above the body
 *   campaign_donate_url      — URL the CTA buttons point at (defaults to ludus.com/donate.php)
 *   campaign_cta_heading     — heading inside the dark CTA band
 *   campaign_cta_body        — supporting text inside the CTA band
 *   campaign_cta_button      — button label
 *   campaign_donors          — JSON array of donor names by tier:
 *                              [{ "tier_name": "Founders", "names": [ "Jane Doe", ... ] }, ...]
 */
get_header();

while ( have_posts() ) : the_post();
  $hero_url      = get_the_post_thumbnail_url( null, 'full' );
  $hero_caption  = get_post_meta( get_the_ID(), 'campaign_hero_caption', true );
  $lead          = get_post_meta( get_the_ID(), 'campaign_lead', true );
  $donate_url    = get_post_meta( get_the_ID(), 'campaign_donate_url', true ) ?: 'https://tlt.ludus.com/donate.php';
  $cta_heading   = get_post_meta( get_the_ID(), 'campaign_cta_heading', true ) ?: 'Become part of the campaign';
  $cta_body      = get_post_meta( get_the_ID(), 'campaign_cta_body', true );
  $cta_button    = get_post_meta( get_the_ID(), 'campaign_cta_button', true ) ?: 'Donate Now';

  // Donors: prefer the new ACF textarea ("## Tier" format); fall back to
  // legacy JSON in campaign_donors meta.
  $donors_text = get_post_meta( get_the_ID(), 'campaign_donors_text', true );
  if ( $donors_text && function_exists( 'tlt_parse_campaign_donors' ) ) {
      $donors = tlt_parse_campaign_donors( $donors_text );
  } else {
      $donors_raw = get_post_meta( get_the_ID(), 'campaign_donors', true );
      $donors     = $donors_raw ? json_decode( $donors_raw, true ) : [];
  }

  // Body: prefer ACF wysiwyg; fall back to the_content() for legacy pages.
  $body_acf = function_exists( 'get_field' ) ? get_field( 'campaign_body' ) : '';
?>

<?php if ( $hero_url ) : ?>
  <div class="campaign-hero">
    <img src="<?php echo esc_url( $hero_url ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
  </div>
  <?php if ( $hero_caption ) : ?>
    <p class="image-caption" style="text-align:center;margin-top:0.5rem"><?php echo esc_html( $hero_caption ); ?></p>
  <?php endif; ?>
<?php endif; ?>

<div class="container page-content">
  <header class="page-header">
    <h1><?php the_title(); ?></h1>
  </header>

  <article class="page-body">
    <?php if ( $lead ) : ?>
      <p class="campaign-lead"><?php echo esc_html( $lead ); ?></p>
    <?php endif; ?>

    <?php
    if ( $body_acf ) {
        echo apply_filters( 'the_content', $body_acf );
    } else {
        the_content();
    }
    ?>
  </article>

  <section class="campaign-cta-band">
    <h2><?php echo esc_html( $cta_heading ); ?></h2>
    <?php if ( $cta_body ) : ?>
      <p style="max-width:600px;margin:0 auto 1.5rem"><?php echo esc_html( $cta_body ); ?></p>
    <?php endif; ?>
    <a class="btn btn-primary" href="<?php echo esc_url( $donate_url ); ?>" target="_blank" rel="noopener">
      <?php echo esc_html( $cta_button ); ?>
    </a>
  </section>

  <?php if ( is_array( $donors ) && $donors ) : ?>
    <section class="campaign-donors" style="max-width:800px;margin:3rem auto">
      <h2 class="section-heading">Thank You to Our Donors</h2>
      <?php foreach ( $donors as $group ) :
        $tier  = isset( $group['tier_name'] ) ? $group['tier_name'] : '';
        $names = isset( $group['names'] ) && is_array( $group['names'] ) ? $group['names'] : [];
        if ( ! $names ) continue;
      ?>
        <div class="donor-tier" style="margin-bottom:2rem">
          <?php if ( $tier ) : ?><h3 style="color:var(--color-accent);font-size:1rem;letter-spacing:0.06em;margin-bottom:0.5rem"><?php echo esc_html( $tier ); ?></h3><?php endif; ?>
          <p style="line-height:1.8"><?php echo esc_html( implode( ' · ', $names ) ); ?></p>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>
</div>

<?php endwhile;
get_footer(); ?>
