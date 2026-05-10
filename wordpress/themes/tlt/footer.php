<?php if ( ! is_page_template( 'page-splash.php' ) && ! is_page( 'splash' ) ) : ?>
<footer class="site-footer">
  <div class="container footer-mission">

    <h3 class="footer-address">Located at 210 N "I" Street, Tacoma, WA 98403</h3>

    <p><strong>Our Mission:</strong><br>
      <em>Providing live theatre and education programs that inspire through stories reflecting the vibrancy of our diverse community.</em>
    </p>

    <p><strong>Our Vision:</strong><br>
      <em>TLT enriches lives by providing opportunities for equitable inclusion and representation. Our goal is to ensure everyone &mdash; regardless of identity, background or personal experience &mdash; belongs at TLT.</em>
    </p>

    <div class="land-ack">
      <p><strong>Tacoma Little Theatre recognizes that they teach and perform on Indigenous land:</strong> the traditional homelands of the Puyallup people.</p>
      <p lang="lut" class="lushootseed">ʔuk’ʷədiitəb ʔuhigʷətəb čəɫ txʷəl tiiɫ ʔa čəɫ ʔal tə swatxʷixʷtxʷəd ʔə tiiɫ puyaləpabš dxʷəsɫaɫlils gʷəl ʔutxʷəlšucidəbs həlgʷəʔ.</p>
      <p><em>&ldquo;We gratefully acknowledge that we rest on the traditional lands of the Puyallup People where they make their home and speak the Lushootseed language.&rdquo;</em></p>
    </div>

    <div class="social-row">
      <a href="https://www.facebook.com/tacomalittletheatre/" target="_blank" rel="noopener" aria-label="Facebook">
        <svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.879V14.89h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.989C18.343 21.128 22 16.991 22 12z"/></svg>
      </a>
      <a href="http://instagram.com/tacomalittletheatre" target="_blank" rel="noopener" aria-label="Instagram">
        <svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
      </a>
      <a href="https://www.youtube.com/channel/UCIdbR2k-vM3vKSOSn_hbr9A" target="_blank" rel="noopener" aria-label="YouTube">
        <svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
      </a>
    </div>

    <div class="footer-bottom">
      <p>&copy; <?php echo date('Y'); ?> Tacoma Little Theatre. All Rights Reserved. &middot; (253) 272-2281</p>
      <p>TLT is a registered 501(c)(3) organization. Federal ID 91-0485763</p>
    </div>

  </div>
</footer>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
