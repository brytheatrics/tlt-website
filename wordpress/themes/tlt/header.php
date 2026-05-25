<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="profile" href="https://gmpg.org/xfn/11">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php if ( ! is_page_template( 'page-splash.php' ) && ! is_page( 'splash' ) ) : ?>
<header class="site-header">
  <div class="container">
    <a href="<?php echo esc_url( home_url( '/home/' ) ); ?>" class="logo">
      <?php if ( has_custom_logo() ) {
        the_custom_logo();
      } else { ?>
        <img src="<?php echo get_template_directory_uri(); ?>/assets/logo-1918.svg" alt="<?php bloginfo( 'name' ); ?>">
      <?php } ?>
    </a>
    <nav class="primary">
      <?php
      if ( has_nav_menu( 'primary' ) ) {
          wp_nav_menu( [ 'theme_location' => 'primary', 'container' => false ] );
      } else { ?>
        <ul>
          <li><a href="/shows/">Shows</a></li>
          <li><a href="/tickets/">Tickets</a></li>
          <li><a href="/education/">Education</a></li>
          <li><a href="/get-involved/">Get Involved</a></li>
          <li><a href="/about/">About</a></li>
          <li><a href="/visit/">Visit</a></li>
        </ul>
      <?php } ?>
    </nav>

    <div class="site-search-wrap">
      <button type="button" class="site-search-toggle" aria-label="Search" aria-expanded="false" aria-controls="site-search-form">
        <svg xmlns="http://www.w3.org/2000/svg" height="22" width="22" viewBox="0 -960 960 960" fill="currentColor" aria-hidden="true">
          <path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z"/>
        </svg>
      </button>
      <form role="search" method="get" class="site-search" id="site-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>" hidden>
        <label class="visually-hidden" for="site-search-input">Search the site</label>
        <input id="site-search-input" type="search" name="s" placeholder="Search…" value="<?php echo esc_attr( get_search_query() ); ?>" autocomplete="off">
        <button type="submit" aria-label="Submit search">
          <svg xmlns="http://www.w3.org/2000/svg" height="18" width="18" viewBox="0 -960 960 960" fill="currentColor" aria-hidden="true">
            <path d="M784-120 532-372q-30 24-69 38t-83 14q-109 0-184.5-75.5T120-580q0-109 75.5-184.5T380-840q109 0 184.5 75.5T640-580q0 44-14 83t-38 69l252 252-56 56ZM380-400q75 0 127.5-52.5T560-580q0-75-52.5-127.5T380-760q-75 0-127.5 52.5T200-580q0 75 52.5 127.5T380-400Z"/>
          </svg>
        </button>
        <button type="button" class="site-search-close" aria-label="Close search">×</button>
      </form>
    </div>
  </div>
</header>
<script>
  (function () {
    const toggle = document.querySelector('.site-search-toggle');
    const form = document.getElementById('site-search-form');
    const input = document.getElementById('site-search-input');
    const close = document.querySelector('.site-search-close');
    if (!toggle || !form || !input) return;

    function openSearch() {
      form.hidden = false;
      toggle.setAttribute('aria-expanded', 'true');
      // Wait one frame for display to take effect, then animate in + focus
      requestAnimationFrame(() => {
        form.classList.add('is-open');
        input.focus();
        input.select();
      });
    }
    function closeSearch() {
      form.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      setTimeout(() => { form.hidden = true; }, 200);
      toggle.focus();
    }
    toggle.addEventListener('click', openSearch);
    close && close.addEventListener('click', closeSearch);
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') closeSearch();
    });
    // Click outside to close
    document.addEventListener('click', (e) => {
      if (form.hidden) return;
      if (!form.contains(e.target) && !toggle.contains(e.target)) closeSearch();
    });
  })();
</script>
<?php endif; ?>
