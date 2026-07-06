<?php
/**
 * Template Name: Video Archive
 *
 * Renders /recorded-programs and similar pages — section headers + video grids.
 *
 * Page meta:
 *   video_sections — JSON array of:
 *     [
 *       {
 *         "heading": "Section title",
 *         "intro": "Optional supporting text",
 *         "videos": [
 *           { "url": "youtube/vimeo url", "caption": "Show title — May 2024" },
 *           ...
 *         ]
 *       },
 *       ...
 *     ]
 *   partner_logos — JSON array of [ { "url": "...", "alt": "...", "link": "..." }, ... ]
 *                   rendered as a partner-theatres logo row at the bottom
 */
get_header();

while ( have_posts() ) : the_post();
  // Prefer the editable textarea fields (parsed); fall back to the legacy JSON meta.
  $sections_text    = function_exists( 'get_field' ) ? get_field( 'video_sections_text' ) : '';
  if ( $sections_text && function_exists( 'tlt_parse_video_sections' ) ) {
      $sections = tlt_parse_video_sections( $sections_text );
  } else {
      $sections_raw = get_post_meta( get_the_ID(), 'video_sections', true );
      $sections     = $sections_raw ? json_decode( $sections_raw, true ) : [];
  }
  $logos_text = function_exists( 'get_field' ) ? get_field( 'partner_logos_text' ) : '';
  if ( $logos_text && function_exists( 'tlt_parse_partner_logos' ) ) {
      $partner_logos = tlt_parse_partner_logos( $logos_text );
  } else {
      $partner_logos_raw = get_post_meta( get_the_ID(), 'partner_logos', true );
      $partner_logos     = $partner_logos_raw ? json_decode( $partner_logos_raw, true ) : [];
  }
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
    <?php
      $va_intro = function_exists( 'get_field' ) ? get_field( 'va_intro' ) : '';
      if ( $va_intro ) {
          echo wpautop( wp_kses_post( $va_intro ) );
      } else {
          the_content(); // fallback for any legacy body content
      }
    ?>
  </article>

  <?php if ( is_array( $sections ) && $sections ) : ?>
    <div class="video-archive">
      <?php foreach ( $sections as $section ) :
        $heading = isset( $section['heading'] ) ? $section['heading'] : '';
        $intro   = isset( $section['intro'] ) ? $section['intro'] : '';
        $videos  = isset( $section['videos'] ) && is_array( $section['videos'] ) ? $section['videos'] : [];
        if ( ! $videos ) continue;
      ?>
        <section class="video-archive__section">
          <?php if ( $heading ) : ?>
            <h2><?php echo esc_html( $heading ); ?></h2>
          <?php endif; ?>
          <?php if ( $intro ) : ?>
            <p style="color:var(--color-muted);margin-bottom:1.5rem"><?php echo esc_html( $intro ); ?></p>
          <?php endif; ?>
          <div class="video-grid">
            <?php foreach ( $videos as $v ) :
              $url     = isset( $v['url'] ) ? esc_url( $v['url'] ) : '';
              $caption = isset( $v['caption'] ) ? $v['caption'] : '';
              if ( ! $url ) continue;
              $embed = wp_oembed_get( $url );
            ?>
              <div class="video-grid__item">
                <?php
                  if ( $embed ) {
                      echo $embed;
                  } else {
                      echo '<iframe src="' . $url . '" allow="autoplay; fullscreen" allowfullscreen frameborder="0"></iframe>';
                  }
                ?>
                <?php if ( $caption ) : ?>
                  <p class="video-grid__caption"><?php echo esc_html( $caption ); ?></p>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ( is_array( $partner_logos ) && $partner_logos ) : ?>
    <section class="partner-logos" style="margin-top:3rem">
      <h2 class="section-heading">Partner Theatres</h2>
      <div class="logo-row">
        <?php foreach ( $partner_logos as $logo ) :
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
    </section>
  <?php endif; ?>
</div>

<?php endwhile;
get_footer(); ?>
