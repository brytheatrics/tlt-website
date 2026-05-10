<?php
/**
 * Default index.php — used for the news/blog index when not overridden.
 */
get_header(); ?>

<div class="container page-content">
  <header class="page-header">
    <h1><?php is_home() ? bloginfo( 'name' ) : the_archive_title(); ?></h1>
  </header>

  <?php if ( have_posts() ) : ?>
    <?php while ( have_posts() ) : the_post(); ?>
      <article style="border-bottom:1px solid var(--color-line);padding:1.5rem 0">
        <h2 style="text-transform:none"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
        <p style="color:var(--color-muted);font-size:0.85rem"><?php echo get_the_date(); ?></p>
        <?php the_excerpt(); ?>
      </article>
    <?php endwhile; ?>
    <?php the_posts_pagination(); ?>
  <?php else : ?>
    <p>Nothing here yet.</p>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
