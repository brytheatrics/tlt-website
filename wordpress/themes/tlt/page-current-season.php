<?php
/**
 * Template Name: Current Season
 *
 * Auto-generated listing of the current season's Mainstage shows. Builds itself
 * from tlt_get_current_season_shows() — there is nothing to edit here. As the
 * season rolls over (new shows tagged to the next Season taxonomy term), this
 * page follows automatically.
 *
 * WordPress auto-selects this file for the page with slug "current-season"
 * (page-{slug}.php in the template hierarchy) — no template assignment needed.
 */
get_header();

$season = function_exists( 'tlt_get_current_season_term' )  ? tlt_get_current_season_term()  : null;
$shows  = function_exists( 'tlt_get_current_season_shows' ) ? tlt_get_current_season_shows() : [];
?>

<div class="container">
  <header class="page-header">
    <h1><?php echo $season ? esc_html( $season->name ) . ' Season' : 'Current Season'; ?></h1>
    <?php
      // Optional season tagline (the Season term's description, editable under Shows → Season).
      if ( $season && ! empty( $season->description ) ) {
          echo '<p class="season-tagline">' . wp_kses_post( $season->description ) . '</p>';
      }
    ?>
  </header>

  <?php if ( $shows ) : ?>
    <div class="show-grid">
      <?php foreach ( $shows as $i => $show ) :
        $img   = function_exists( 'tlt_show_image_url' )
                   ? tlt_show_image_url( $show->ID, 'medium_large' )
                   : get_the_post_thumbnail_url( $show->ID, 'medium_large' );
        $open  = get_post_meta( $show->ID, 'show_open_date', true );
        $close = get_post_meta( $show->ID, 'show_close_date', true );
      ?>
        <a href="<?php echo esc_url( get_permalink( $show ) ); ?>" class="show-card">
          <div class="img-wrap"><?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt=""><?php endif; ?></div>
          <div class="body">
            <?php if ( $open ) : ?>
              <div class="dates"><?php echo esc_html( tlt_format_date_range( $open, $close ) ); ?></div>
            <?php endif; ?>
            <h3><?php echo esc_html( get_the_title( $show ) ); ?></h3>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else : ?>
    <div style="background:var(--color-soft);padding:2.5rem 2rem;border-radius:6px;text-align:center;margin-top:2rem">
      <p style="margin:0;font-size:1.05rem">Our next season will be announced soon.</p>
    </div>
  <?php endif; ?>
</div>

<?php get_footer();
