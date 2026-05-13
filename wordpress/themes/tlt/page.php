<?php
/**
 * Default page template.
 *
 * Renders a page with:
 * - Optional featured image as a full-width hero band above the title
 * - Title in a styled page header
 * - the_content() — supports prose, inline images, image floats, pull quotes,
 *   buttons, two-column callouts, PDF link lists, sponsor rows (see style.css)
 * - Optional sidebar slot via page_sidebar meta (used by some templates for
 *   audition packets, related downloads, etc.)
 */
get_header(); ?>

<?php while ( have_posts() ) : the_post(); ?>

  <?php if ( has_post_thumbnail() ) : ?>
    <div class="page-hero">
      <?php the_post_thumbnail( 'full', [ 'alt' => esc_attr( get_the_title() ) ] ); ?>
    </div>
  <?php endif; ?>

  <div class="container page-content">
    <header class="page-header">
      <h1><?php the_title(); ?></h1>
      <?php if ( has_excerpt() ) : ?>
        <p class="page-subtitle"><?php echo esc_html( get_the_excerpt() ); ?></p>
      <?php endif; ?>
    </header>

    <article class="page-body">
      <?php the_content(); ?>
    </article>
  </div>

<?php endwhile; ?>

<?php get_footer(); ?>
