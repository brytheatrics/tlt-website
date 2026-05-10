<?php
/**
 * Template Name: Splash (cycling production photos)
 *
 * Use this on the page set as "Splash" to get the full-screen takeover
 * matching TLT's existing /cover page. Photos cycle behind static text.
 *
 * Photos come from the current show's gallery (attached photos), or fall
 * back to the featured image.
 */
get_header();

$current = function_exists('tlt_get_current_show') ? tlt_get_current_show() : null;

if ( ! $current ) {
    echo '<div class="container" style="padding:5rem 0;text-align:center"><h1>Welcome</h1><p><a href="' . esc_url( home_url( '/home/' ) ) . '" class="btn btn-primary">Continue to Website</a></p></div>';
    get_footer();
    return;
}

$open    = get_post_meta( $current->ID, 'show_open_date', true );
$close   = get_post_meta( $current->ID, 'show_close_date', true );
$tix     = get_post_meta( $current->ID, 'show_ticket_url', true );
$director = get_post_meta( $current->ID, 'show_director', true );
$run_time = get_post_meta( $current->ID, 'show_run_time', true );
$age      = get_post_meta( $current->ID, 'show_age_rec', true );
$warn     = get_post_meta( $current->ID, 'show_content_warning', true );
$tagline  = get_post_meta( $current->ID, 'show_tagline', true );

// Gather photos: gallery attachments first, then featured image
$photos = get_attached_media( 'image', $current->ID );
$photo_urls = [];
foreach ( $photos as $p ) {
    $u = wp_get_attachment_image_url( $p->ID, 'full' );
    if ( $u ) $photo_urls[] = $u;
}
if ( empty( $photo_urls ) ) {
    $hero = tlt_show_image_url( $current->ID, 'full' );
    if ( $hero ) $photo_urls[] = $hero;
}
?>

<div class="splash-bg">
  <?php foreach ( $photo_urls as $i => $u ) : ?>
    <div class="splash-photo<?php echo $i === 0 ? ' active' : ''; ?>" style="background-image:url('<?php echo esc_url( $u ); ?>')"></div>
  <?php endforeach; ?>
</div>
<div class="splash-overlay"></div>

<div class="splash-wrap">

  <div style="display:flex;justify-content:flex-start">
    <a href="<?php echo esc_url( home_url( '/home/' ) ); ?>" class="logo">
      <?php if ( has_custom_logo() ) {
        echo get_custom_logo();
      } else { ?>
        <img src="<?php echo get_template_directory_uri(); ?>/assets/logo.png" alt="<?php bloginfo( 'name' ); ?>" style="height:70px;filter:brightness(0) invert(1)">
      <?php } ?>
    </a>
  </div>

  <div class="splash-mid">
    <div class="splash-text">
      <h1><?php echo esc_html( get_the_title( $current ) ); ?></h1>
      <div class="dates"><?php echo esc_html( tlt_format_date_range( $open, $close ) ); ?></div>
      <?php if ( $tagline ) : ?>
        <p class="tagline"><em><strong><?php echo esc_html( $tagline ); ?></strong></em></p>
      <?php endif; ?>
      <?php if ( $run_time ) : ?><p class="meta-line"><strong>Run Time:</strong> <?php echo esc_html( $run_time ); ?></p><?php endif; ?>
      <?php if ( $age ) : ?><p class="meta-line"><strong><?php echo esc_html( $age ); ?></strong></p><?php endif; ?>
      <?php if ( $warn ) : ?><p class="content-warning-text"><em><strong><?php echo esc_html( $warn ); ?></strong></em></p><?php endif; ?>
    </div>

    <div class="splash-actions">
      <a href="https://www.facebook.com/tacomalittletheatre/" target="_blank" rel="noopener" class="splash-social splash-social-fb" aria-label="Facebook">
        <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.879V14.89h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.989C18.343 21.128 22 16.991 22 12z"/></svg>
      </a>
      <a href="http://instagram.com/tacomalittletheatre" target="_blank" rel="noopener" class="splash-social splash-social-ig" aria-label="Instagram">
        <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
      </a>
      <a href="https://www.youtube.com/channel/UCIdbR2k-vM3vKSOSn_hbr9A" target="_blank" rel="noopener" class="splash-social splash-social-yt" aria-label="YouTube">
        <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
      </a>
      <?php if ( $tix ) : ?>
        <a href="<?php echo esc_url( $tix ); ?>" target="_blank" rel="noopener" class="btn">Buy Tickets</a>
      <?php endif; ?>
      <a href="<?php echo esc_url( home_url( '/home/' ) ); ?>" class="btn">Continue to TLT's Webpage</a>
    </div>
  </div>

  <div style="text-align:center;font-size:0.7rem;letter-spacing:0.2em;color:rgba(255,255,255,0.5);text-transform:uppercase;margin-top:1rem">
    Tacoma Little Theatre &middot; Est. 1918
  </div>

</div>

<?php if ( count( $photo_urls ) > 1 ) : ?>
<script>
  (function(){
    const photos = document.querySelectorAll('.splash-photo');
    let i = 0;
    setInterval(() => {
      photos[i].classList.remove('active');
      i = (i + 1) % photos.length;
      photos[i].classList.add('active');
    }, 5000);
  })();
</script>
<?php endif; ?>

<?php get_footer(); ?>
