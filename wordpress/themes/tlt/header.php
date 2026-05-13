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
        <img src="<?php echo get_template_directory_uri(); ?>/assets/logo.png" alt="<?php bloginfo( 'name' ); ?>">
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

    <form role="search" method="get" class="site-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
      <label class="visually-hidden" for="site-search-input">Search</label>
      <input id="site-search-input" type="search" name="s" placeholder="Search…" value="<?php echo esc_attr( get_search_query() ); ?>" autocomplete="off">
      <button type="submit" aria-label="Search">🔍</button>
    </form>
  </div>
</header>
<?php endif; ?>
