<?php
/**
 * Template Name: Press Post (Detail)
 *
 * Individual press release page (linked from page-press.php).
 *
 * Page meta:
 *   press_date  — display date string (e.g. "May 28, 2021")
 *   press_thumb — URL of the hero/thumbnail image
 *
 * The body is whatever post_content has.
 */
get_header();

while ( have_posts() ) : the_post();
    $date  = get_post_meta( get_the_ID(), 'press_date', true );
    $thumb = get_post_meta( get_the_ID(), 'press_thumb', true );
?>

<style>
  .pp-page { max-width: 900px; margin: 0 auto; padding: 2rem var(--pad) 4rem; }
  .pp-back { display: inline-block; margin-bottom: 1.5rem; font-size: 0.85rem; color: var(--color-muted); text-decoration: none; }
  .pp-back:hover { color: var(--color-accent); }

  .pp-header { margin-bottom: 2rem; }
  .pp-eyebrow { display: inline-block; background: var(--color-accent); color: #fff; font-size: 0.7rem; letter-spacing: 0.1em; text-transform: uppercase; padding: 0.2rem 0.6rem; border-radius: 999px; margin-bottom: 0.75rem; }
  .pp-title { font-size: clamp(1.6rem, 3.5vw, 2.2rem); margin: 0 0 0.5rem; line-height: 1.2; }
  .pp-date { font-size: 0.85rem; color: var(--color-muted); margin: 0; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; }

  .pp-hero-img { width: 100%; height: auto; border-radius: 6px; margin-bottom: 2rem; display: block; }

  .pp-body p { line-height: 1.7; font-size: 1rem; margin: 0 0 1.2rem; }
  .pp-body h2, .pp-body h3 { margin-top: 2rem; }
  .pp-body a { color: var(--color-accent); }
</style>

<div class="pp-page">

  <a href="/press/" class="pp-back">&larr; All Press</a>

  <header class="pp-header">
    <span class="pp-eyebrow">Press Release</span>
    <h1 class="pp-title"><?php the_title(); ?></h1>
    <?php if ( $date ) : ?>
      <p class="pp-date"><?php echo esc_html( $date ); ?></p>
    <?php endif; ?>
  </header>

  <?php if ( $thumb ) : ?>
    <img class="pp-hero-img" src="<?php echo esc_url( $thumb ); ?>" alt="">
  <?php endif; ?>

  <div class="pp-body">
    <?php the_content(); ?>
  </div>

</div>

<?php endwhile; get_footer(); ?>
