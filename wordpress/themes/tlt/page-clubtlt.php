<?php
/**
 * /clubtlt/ — same intro + workshop copy as the default page, but the trailing
 * Squarespace photo-gallery block (a wall of <img class="thumb-image">, each
 * shadowed by a <noscript> twin) is pulled out and rendered as a slideshow via
 * tlt_render_slideshow(). The single floating intro image is left in place.
 *
 * WordPress auto-selects this file for the page with slug "clubtlt"
 * (page-{slug}.php in the template hierarchy) — no template assignment needed.
 */
get_header(); ?>

<?php while ( have_posts() ) : the_post();
    $content = get_the_content();

    // Slideshow images: prefer the editable ACF field (one URL per line); fall
    // back to scraping the body's class="thumb-image" images for un-migrated pages.
    $gallery = [];
    $club_field = function_exists( 'get_field' ) ? get_field( 'clubtlt_slideshow' ) : '';
    if ( $club_field ) {
        foreach ( preg_split( '/\r\n|\r|\n/', (string) $club_field ) as $line ) {
            $line = trim( $line );
            if ( $line !== '' && ! isset( $gallery[ $line ] ) ) {
                $gallery[ $line ] = [ 'url' => $line, 'alt' => '', 'caption' => '' ];
            }
        }
    } else {
        preg_match_all( '/<img\b[^>]*class="[^"]*thumb-image[^"]*"[^>]*>/i', $content, $imgs );
        foreach ( $imgs[0] as $tag ) {
            if ( preg_match( '/src="([^"]+)"/i', $tag, $sm ) ) {
                $url = $sm[1];
                if ( ! isset( $gallery[ $url ] ) ) {
                    $alt = preg_match( '/alt="([^"]*)"/i', $tag, $am ) ? $am[1] : '';
                    $gallery[ $url ] = [ 'url' => $url, 'alt' => $alt, 'caption' => '' ];
                }
            }
        }
    }
    $gallery = array_values( $gallery );

    // Strip the gallery from the body: the <noscript> twins and the thumb-image
    // tags themselves. (The inline float image up top has no thumb-image class,
    // so it stays.) Then drop any wrapper divs left empty by the removal.
    $content = preg_replace( '/<noscript>.*?<\/noscript>/is', '', $content );
    $content = preg_replace( '/<img\b[^>]*class="[^"]*thumb-image[^"]*"[^>]*>/i', '', $content );
    for ( $i = 0; $i < 4; $i++ ) {
        $content = preg_replace( '/<div[^>]*>\s*<\/div>/i', '', $content );
    }
?>

  <div class="container page-content">
    <header class="page-header">
      <h1><?php the_title(); ?></h1>
    </header>

    <article class="page-body">
      <?php echo apply_filters( 'the_content', $content ); ?>
    </article>

    <?php echo tlt_render_slideshow( $gallery, 'ClubTLT in Action' ); ?>
  </div>

<?php endwhile; ?>

<?php get_footer(); ?>
