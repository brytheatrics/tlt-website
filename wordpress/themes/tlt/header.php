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
<?php if ( is_page( 'home' ) ) : ?>
<script>
  // Splash → Home wipe: when we arrive from /splash/, insert a charcoal cover
  // BEFORE the header markup renders, then collapse it down to header height
  // once DOMContentLoaded fires. Inserting here (not on DOMContentLoaded) is
  // what kills the flash — the wipe is in the DOM before the header paints.
  (function () {
    try {
      if (sessionStorage.getItem('tlt_splash_to_home') !== '1') return;
      if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      sessionStorage.removeItem('tlt_splash_to_home');

      var w = document.createElement('div');
      w.id = 'homeWipe';
      document.body.appendChild(w);

      document.addEventListener('DOMContentLoaded', function () {
        requestAnimationFrame(function () {
          requestAnimationFrame(function () {
            w.classList.add('is-collapsing');
          });
        });
      });

      function done(ev) {
        if (ev && ev.propertyName && ev.propertyName !== 'height') return;
        w.removeEventListener('transitionend', done);
        w.classList.add('is-done');
        setTimeout(function () { if (w.parentNode) w.remove(); }, 500);
      }
      w.addEventListener('transitionend', done);
      // Safety: kill the wipe after the longest reasonable duration
      setTimeout(function () { if (document.body.contains(w)) done(); }, 1500);
    } catch (_) {}
  })();
</script>
<?php endif; ?>

<?php if ( ! is_page_template( 'page-splash.php' ) && ! is_page( 'splash' ) ) : ?>
<?php
// Sitewide promo banner (auto-renders when any active promo has
// location=sitewide; dismissable per-visitor via cookie).
if ( function_exists( 'tlt_render_sitewide_banner' ) ) tlt_render_sitewide_banner();
?>
<header class="site-header">
  <div class="container">
    <a href="<?php echo esc_url( home_url( '/home/' ) ); ?>" class="logo">
      <?php if ( has_custom_logo() ) {
        the_custom_logo();
      } else { ?>
        <img src="<?php echo get_template_directory_uri(); ?>/assets/logo-1918.svg" alt="<?php bloginfo( 'name' ); ?>">
      <?php } ?>
    </a>
    <button type="button" class="mobile-nav-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="primary-nav">
      <svg viewBox="0 0 24 24" fill="currentColor" width="28" height="28" aria-hidden="true">
        <path class="mnt-open" d="M3 6h18v2H3zm0 5h18v2H3zm0 5h18v2H3z"/>
        <path class="mnt-close" d="M18.3 5.7L12 12l6.3 6.3-1.4 1.4L10.6 13.4 4.3 19.7 2.9 18.3 9.2 12 2.9 5.7l1.4-1.4L10.6 10.6 16.9 4.3z" style="display:none"/>
      </svg>
    </button>
    <nav class="primary" id="primary-nav">
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
      <a class="site-cal-link" href="<?php echo esc_url( home_url( '/calendar/' ) ); ?>" aria-label="Calendar">
        <svg xmlns="http://www.w3.org/2000/svg" height="22" width="22" viewBox="0 -960 960 960" fill="currentColor" aria-hidden="true">
          <path d="M200-80q-33 0-56.5-23.5T120-160v-560q0-33 23.5-56.5T200-800h40v-80h80v80h320v-80h80v80h40q33 0 56.5 23.5T840-720v560q0 33-23.5 56.5T760-80H200Zm0-80h560v-400H200v400Zm0-480h560v-80H200v80Zm0 0v-80 80Z"/>
        </svg>
      </a>
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
  // Mobile nav drawer
  (function () {
    const btn = document.querySelector('.mobile-nav-toggle');
    const nav = document.getElementById('primary-nav');
    if (!btn || !nav) return;
    const openIcon = btn.querySelector('.mnt-open');
    const closeIcon = btn.querySelector('.mnt-close');

    function setOpen(open) {
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
      nav.classList.toggle('is-open', open);
      document.body.classList.toggle('mobile-nav-open', open);
      if (openIcon)  openIcon.style.display  = open ? 'none' : '';
      if (closeIcon) closeIcon.style.display = open ? '' : 'none';
    }

    btn.addEventListener('click', () => setOpen(btn.getAttribute('aria-expanded') !== 'true'));
    // If a link points to the page we're already on, navigation would be a
    // visible no-op — give meaningful feedback by closing the drawer and
    // scrolling to top. Other URLs pass through untouched.
    nav.addEventListener('click', (e) => {
      const a = e.target.closest('a');
      if (!a || !a.href) return;
      try {
        const here  = window.location.href.replace(/#.*$/, '').replace(/\/$/, '');
        const there = a.href.replace(/#.*$/, '').replace(/\/$/, '');
        if (here === there) {
          e.preventDefault();
          setOpen(false);
          window.scrollTo({ top: 0, behavior: 'smooth' });
        }
      } catch (_) {}
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && nav.classList.contains('is-open')) setOpen(false);
    });
  })();
</script>
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
