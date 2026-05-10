<?php
/**
 * Default page template.
 */
get_header(); ?>

<div class="container page-content">
  <header class="page-header">
    <h1><?php the_title(); ?></h1>
  </header>
  <?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
</div>

<?php get_footer(); ?>
