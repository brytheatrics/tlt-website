<?php
/**
 * Template Name: Education
 *
 * /education/ — currently happening + program directory + philosophy + scholarships + policies
 */
get_header(); ?>

<style>
  /* Outer wrappers can be full-width; inner .edu-inner constrains content */
  .edu-page { padding: 0; margin: 0; }
  .edu-inner { max-width: 1100px; margin: 0 auto; padding: 0 var(--pad); }
  .edu-soft-band { background: var(--color-soft); }
  .edu-hero {
    text-align: center;
    padding: 4rem var(--pad) 2rem;
  }
  .edu-hero h1 { margin-bottom: 1rem; }
  .edu-hero .lead { max-width: 700px; margin: 0 auto 1.5rem; font-size: 1.1rem; line-height: 1.6; color: var(--color-text); }

  .edu-section { padding: 3rem 0; }
  .edu-section h2 { color: var(--color-accent); text-align: center; margin-bottom: 0.5rem; }
  .edu-section .lede { text-align: center; color: var(--color-muted); max-width: 720px; margin: 0 auto 2.5rem; }

  /* Currently happening cards (matches homepage feature-row image style) */
  .edu-current-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;
    max-width: 880px; margin: 0 auto;
  }
  @media (max-width: 700px) { .edu-current-grid { grid-template-columns: 1fr; } }
  .edu-current-card {
    background: #fff; border: 1px solid var(--color-line); border-radius: 4px;
    overflow: hidden; color: var(--color-text);
    display: flex; flex-direction: column;
    transition: transform 0.15s, box-shadow 0.15s;
  }
  .edu-current-card:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(0,0,0,0.08); text-decoration: none; }
  .edu-current-card .img-wrap { aspect-ratio: 16/10; background: var(--color-soft); }
  .edu-current-card .img-wrap img { width: 100%; height: 100%; object-fit: contain; }
  .edu-current-card .body { padding: 1.25rem; }
  .edu-current-card h3 { margin: 0 0 0.4rem; font-size: 1.1rem; }
  .edu-current-card p { color: var(--color-muted); font-size: 0.92rem; margin: 0; line-height: 1.5; }

  /* Programs directory (no images, just text in 2 columns) */
  .programs-dir {
    display: grid; grid-template-columns: 1fr 1fr; gap: 2.5rem 3rem;
  }
  @media (max-width: 800px) { .programs-dir { grid-template-columns: 1fr; gap: 2rem; } }
  .program-entry h3 {
    color: var(--color-accent);
    font-size: 1.05rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin: 0 0 0.6rem;
    display: flex; align-items: center; gap: 0.5rem;
  }
  .program-entry h3 a {
    display: inline-flex; align-items: center;
    color: inherit; text-decoration: none;
  }
  .program-entry h3 a:hover { color: var(--color-accent-dark); text-decoration: none; }
  .program-entry h3 .link-icon {
    width: 16px; height: 16px;
    opacity: 0.6;
    transition: opacity 0.15s, transform 0.15s;
  }
  .program-entry h3 a:hover .link-icon { opacity: 1; transform: translate(2px, -2px); }
  .program-entry p { line-height: 1.6; color: var(--color-text); margin: 0; font-size: 0.95rem; }

  .philosophy { background: var(--color-soft); padding: 4rem var(--pad); }
  .philosophy .inner { max-width: 800px; margin: 0 auto; text-align: center; }
  .philosophy h2 { color: var(--color-accent); }
  .philosophy p { line-height: 1.7; margin: 1rem 0; }

  .scholarship-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3rem;
    align-items: center;
    padding: 4rem 0;
  }
  @media (max-width: 800px) { .scholarship-section { grid-template-columns: 1fr; } }
  .scholarship-section img { aspect-ratio: 4/3; object-fit: cover; border-radius: 4px; }

  .policies { padding: 3rem 0; }
  .policies-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 2rem;
    margin-top: 2rem;
  }
  .policies-grid > div {
    padding: 1.25rem;
    background: var(--color-soft);
    border-radius: 4px;
  }
  .policies-grid h3 {
    color: var(--color-accent);
    font-size: 0.95rem;
    margin-bottom: 0.5rem;
  }
  .policies-grid p { font-size: 0.9rem; line-height: 1.6; margin: 0; }
</style>

<?php
// All editable text comes from ACF fields via tlt_edu_field(). Defaults defined
// in includes/acf-page-templates.php → tlt_edu_defaults(). External links are
// auto-detected so an http(s) URL opens in a new tab; on-site /paths/ don't.
$_edu_target = function ( $u ) {
    return ( $u && preg_match( '#^https?://#i', $u ) ) ? ' target="_blank" rel="noopener"' : '';
};
$scholarship_image = function_exists( 'get_field' ) ? get_field( 'edu_scholarship_image' ) : null;
$scholarship_img_url = '';
if ( is_array( $scholarship_image ) && ! empty( $scholarship_image['url'] ) ) {
    $scholarship_img_url = $scholarship_image['url'];
} else {
    $scholarship_img_url = get_template_directory_uri() . '/assets/edu-clubtlt.jpg';
}
?>
<div class="edu-page">

  <!-- Hero -->
  <div class="edu-soft-band">
    <div class="edu-inner edu-hero">
      <h1><?php echo esc_html( tlt_edu_field( 'hero_title' ) ); ?></h1>
      <p class="lead"><?php echo esc_html( tlt_edu_field( 'hero_intro' ) ); ?></p>
      <?php
        $hero_label = tlt_edu_field( 'hero_cta_label' );
        $hero_url   = tlt_edu_field( 'hero_cta_url' );
        if ( $hero_label && $hero_url ) :
      ?>
      <p>
        <a href="<?php echo esc_url( $hero_url ); ?>"<?php echo $_edu_target( $hero_url ); ?> class="btn btn-primary"><?php echo esc_html( $hero_label ); ?></a>
      </p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Why Theatre Education? -->
  <div class="philosophy">
    <div class="inner">
      <h2><?php echo esc_html( tlt_edu_field( 'why_heading' ) ); ?></h2>
      <?php echo wp_kses_post( tlt_edu_field( 'why_body' ) ); ?>
    </div>
  </div>

  <!-- Currently Happening — driven by Promotions with location=education -->
  <?php
  $edu_promos = function_exists( 'tlt_get_active_promotions' )
      ? tlt_get_active_promotions( 'education' )
      : [];
  if ( $edu_promos ) :
  ?>
  <div class="edu-inner edu-section">
    <h2>Currently Happening</h2>
    <p class="lede">What's running right now. Click for details and registration.</p>
    <div class="edu-current-grid">
      <?php foreach ( $edu_promos as $i => $p ) tlt_render_promo( $p, $i, 'edu-card' ); ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Our Programs -->
  <?php $programs = tlt_edu_field( 'programs' ); if ( ! is_array( $programs ) ) $programs = []; ?>
  <?php if ( $programs ) : ?>
  <div class="edu-soft-band">
   <div class="edu-inner edu-section">
    <h2><?php echo esc_html( tlt_edu_field( 'programs_heading' ) ); ?></h2>
    <p class="lede"><?php echo esc_html( tlt_edu_field( 'programs_lede' ) ); ?></p>
    <div class="programs-dir">
      <?php foreach ( $programs as $prog ) :
        $title = isset( $prog['title'] ) ? $prog['title'] : '';
        $link  = isset( $prog['link_url'] ) ? trim( $prog['link_url'] ) : '';
        $body  = isset( $prog['body'] ) ? $prog['body'] : '';
        if ( ! $title && ! $body ) continue;
      ?>
      <div class="program-entry">
        <h3>
          <?php if ( $link ) : ?>
            <a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?>
              <svg class="link-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M7 7h10v10"/></svg>
            </a>
          <?php else : ?>
            <?php echo esc_html( $title ); ?>
          <?php endif; ?>
        </h3>
        <?php echo wp_kses_post( $body ); ?>
      </div>
      <?php endforeach; ?>
    </div>
   </div>
  </div>
  <?php endif; ?>

  <section class="edu-inner scholarship-section">
    <div>
      <h2 style="color:var(--color-accent)"><?php echo esc_html( tlt_edu_field( 'scholarship_heading' ) ); ?></h2>
      <?php echo wp_kses_post( tlt_edu_field( 'scholarship_body' ) ); ?>
      <?php
        $sch_label = tlt_edu_field( 'scholarship_cta_label' );
        $sch_url   = tlt_edu_field( 'scholarship_cta_url' );
        if ( $sch_label && $sch_url ) :
      ?>
      <p style="margin-top:1.5rem">
        <a href="<?php echo esc_url( $sch_url ); ?>"<?php echo $_edu_target( $sch_url ); ?> class="btn btn-primary"><?php echo esc_html( $sch_label ); ?></a>
      </p>
      <?php endif; ?>
    </div>
    <img src="<?php echo esc_url( $scholarship_img_url ); ?>" alt="">
  </section>

  <?php $policies = tlt_edu_field( 'policies' ); if ( ! is_array( $policies ) ) $policies = []; ?>
  <?php if ( $policies ) : ?>
  <section class="edu-inner policies">
    <h2 style="color:var(--color-accent);text-align:center;margin-bottom:0"><?php echo esc_html( tlt_edu_field( 'policies_heading' ) ); ?></h2>
    <p style="text-align:center;color:var(--color-muted);margin-bottom:2rem"><?php echo esc_html( tlt_edu_field( 'policies_lede' ) ); ?></p>
    <div class="policies-grid">
      <?php foreach ( $policies as $pol ) :
        $title = isset( $pol['title'] ) ? $pol['title'] : '';
        $body  = isset( $pol['body'] ) ? $pol['body'] : '';
        if ( ! $title && ! $body ) continue;
      ?>
      <div>
        <h3><?php echo esc_html( $title ); ?></h3>
        <?php echo wp_kses_post( $body ); ?>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

</div>

<?php get_footer(); ?>
