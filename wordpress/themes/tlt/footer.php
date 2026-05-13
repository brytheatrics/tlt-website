<?php
/**
 * Footer. Reads address / phone / mission / vision / land acknowledgement /
 * social links from the WordPress Customizer (Appearance → Customize).
 * Defaults baked in match the original design — visible even before anyone
 * touches the Customizer.
 */
$addr_street = tlt_setting( 'tlt_address_street', '210 N "I" Street' );
$addr_city   = tlt_setting( 'tlt_address_city', 'Tacoma' );
$addr_state  = tlt_setting( 'tlt_address_state', 'WA' );
$addr_zip    = tlt_setting( 'tlt_address_zip', '98403' );
$phone       = tlt_setting( 'tlt_phone', '(253) 272-2281' );
$fed_id      = tlt_setting( 'tlt_federal_id', '91-0485763' );

$mission = tlt_setting( 'tlt_mission_text', 'Providing live theatre and education programs that inspire through stories reflecting the vibrancy of our diverse community.' );
$vision  = tlt_setting( 'tlt_vision_text', 'TLT enriches lives by providing opportunities for equitable inclusion and representation. Our goal is to ensure everyone — regardless of identity, background or personal experience — belongs at TLT.' );
$land_en = tlt_setting( 'tlt_land_ack_english', 'Tacoma Little Theatre recognizes that they teach and perform on Indigenous land: the traditional homelands of the Puyallup people.' );
$land_lush = tlt_setting( 'tlt_land_ack_lushootseed', "ʔuk\u{2019}ʷədiitəb ʔuhigʷətəb čəɫ txʷəl tiiɫ ʔa čəɫ ʔal tə swatxʷixʷtxʷəd ʔə tiiɫ puyaləpabš dxʷəsɫaɫlils gʷəl ʔutxʷəlšucidəbs həlgʷəʔ." );

$fb = tlt_setting( 'tlt_social_facebook', 'https://www.facebook.com/tacomalittletheatre/' );
$ig = tlt_setting( 'tlt_social_instagram', 'http://instagram.com/tacomalittletheatre' );
$yt = tlt_setting( 'tlt_social_youtube', 'https://www.youtube.com/channel/UCIdbR2k-vM3vKSOSn_hbr9A' );
$tw = tlt_setting( 'tlt_social_twitter' );
$tk = tlt_setting( 'tlt_social_tiktok' );
?>
<?php if ( ! is_page_template( 'page-splash.php' ) && ! is_page( 'splash' ) ) : ?>
<footer class="site-footer">
  <div class="container footer-mission">

    <h3 class="footer-address">Located at <?php echo esc_html( "$addr_street, $addr_city, $addr_state $addr_zip" ); ?></h3>

    <?php if ( $mission ) : ?>
      <p><strong>Our Mission:</strong><br><em><?php echo esc_html( $mission ); ?></em></p>
    <?php endif; ?>

    <?php if ( $vision ) : ?>
      <p><strong>Our Vision:</strong><br><em><?php echo esc_html( $vision ); ?></em></p>
    <?php endif; ?>

    <?php if ( $land_en || $land_lush ) : ?>
      <div class="land-ack">
        <?php if ( $land_en ) : ?>
          <p><strong>Tacoma Little Theatre recognizes that they teach and perform on Indigenous land:</strong> the traditional homelands of the Puyallup people.</p>
        <?php endif; ?>
        <?php if ( $land_lush ) : ?>
          <p lang="lut" class="lushootseed"><?php echo esc_html( $land_lush ); ?></p>
        <?php endif; ?>
        <p><em>&ldquo;We gratefully acknowledge that we rest on the traditional lands of the Puyallup People where they make their home and speak the Lushootseed language.&rdquo;</em></p>
      </div>
    <?php endif; ?>

    <div class="social-row">
      <?php if ( $fb ) : ?>
        <a href="<?php echo esc_url( $fb ); ?>" target="_blank" rel="noopener" aria-label="Facebook">
          <svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.879V14.89h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.989C18.343 21.128 22 16.991 22 12z"/></svg>
        </a>
      <?php endif; ?>
      <?php if ( $ig ) : ?>
        <a href="<?php echo esc_url( $ig ); ?>" target="_blank" rel="noopener" aria-label="Instagram">
          <svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
        </a>
      <?php endif; ?>
      <?php if ( $yt ) : ?>
        <a href="<?php echo esc_url( $yt ); ?>" target="_blank" rel="noopener" aria-label="YouTube">
          <svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
        </a>
      <?php endif; ?>
    </div>

    <div class="footer-bottom">
      <p>&copy; <?php echo date( 'Y' ); ?> Tacoma Little Theatre. All Rights Reserved. &middot; <?php echo esc_html( $phone ); ?></p>
      <p>TLT is a registered 501(c)(3) organization. Federal ID <?php echo esc_html( $fed_id ); ?></p>
    </div>

  </div>
</footer>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
