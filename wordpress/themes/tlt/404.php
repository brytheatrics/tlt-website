<?php
/**
 * 404 — Page Not Found.
 *
 * Friendly recovery page with links back to useful sections. Suppresses the
 * dramatic WordPress default and gives visitors a way to get un-lost.
 */
get_header(); ?>

<section class="error-404">
  <div class="container">
    <h1>404</h1>
    <h2>This page took an early curtain.</h2>
    <p>The page you're looking for might have moved, been renamed, or never existed at all.
       Let's get you back to something good.</p>

    <div class="cta-row" style="justify-content:center;margin-top:2rem">
      <a class="btn btn-primary" href="<?php echo esc_url( home_url( '/home/' ) ); ?>">Go Home</a>
      <a class="btn btn-outline" href="<?php echo esc_url( home_url( '/shows/' ) ); ?>">Current Season</a>
      <a class="btn btn-outline" href="<?php echo esc_url( home_url( '/prior-seasons/' ) ); ?>">Prior Seasons</a>
    </div>

    <form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" style="margin-top:3rem">
      <label for="error-search" style="display:block;font-size:0.9rem;color:var(--color-muted);margin-bottom:0.5rem">
        Or search the site:
      </label>
      <input
        id="error-search"
        type="search"
        name="s"
        placeholder="Search…"
        style="padding:0.75rem 1rem;border:1px solid var(--color-line);width:280px;max-width:100%">
      <button type="submit" class="btn btn-primary" style="vertical-align:top;margin-left:0.5rem">Search</button>
    </form>
  </div>
</section>

<?php get_footer(); ?>
