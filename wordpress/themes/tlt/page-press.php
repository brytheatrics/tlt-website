<?php
/**
 * Template Name: Press (Listing)
 *
 * Listing of press releases — one card per item linking to the full post.
 *
 * Adding a new release:
 *   1. Create a WP page under "Press" with full content, assign template
 *      "Press Post (Detail)".
 *   2. Add a row to the $items array below.
 */
get_header();

// Cards auto-build from the Press Post (Detail) pages — create a page with that
// template and it appears here automatically; no array to edit.
$press_pages = function_exists( 'tlt_pages_using_template' ) ? tlt_pages_using_template( 'page-press-post.php' ) : [];
$items = [];
foreach ( $press_pages as $pp ) {
    $img = ( function_exists( 'get_field' ) ? get_field( 'press_image', $pp->ID ) : '' ) ?: get_post_meta( $pp->ID, 'press_thumb', true );
    $excerpt = has_excerpt( $pp->ID ) ? get_the_excerpt( $pp ) : wp_trim_words( wp_strip_all_tags( $pp->post_content ), 40 );
    $items[] = [
        'title'    => get_the_title( $pp ),
        'permalink'=> get_permalink( $pp ),
        'thumb'    => $img,
        'date'     => get_post_meta( $pp->ID, 'press_date', true ),
        'excerpt'  => $excerpt,
    ];
}
?>

<style>
  .press-page { max-width: 1100px; margin: 0 auto; padding: 2rem var(--pad) 4rem; }
  .press-hero { text-align: center; padding: 2rem 0 1.5rem; }
  .press-hero .eyebrow { display: inline-block; background: var(--color-accent); color: #fff; font-size: 0.75rem; letter-spacing: 0.15em; text-transform: uppercase; padding: 0.3rem 0.9rem; border-radius: 999px; margin-bottom: 1rem; }
  .press-hero h1 { font-size: clamp(2rem, 5vw, 3rem); margin: 0 0 0.5rem; }
  .press-hero .lede { font-size: 1.05rem; line-height: 1.6; color: var(--color-muted); max-width: 720px; margin: 0 auto; }
  .press-hero .lede a { color: var(--color-accent); }

  .press-list { display: grid; gap: 1.5rem; margin-top: 2.5rem; }
  .press-card {
    display: grid; grid-template-columns: 240px 1fr; gap: 1.5rem;
    background: #fff; border: 1px solid var(--color-line); border-radius: 6px;
    overflow: hidden; transition: border-color 0.15s, box-shadow 0.15s;
  }
  .press-card:hover { border-color: var(--color-accent); box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
  @media (max-width: 640px) { .press-card { grid-template-columns: 1fr; } }
  .press-card__thumb { background: var(--color-soft); display: block; overflow: hidden; line-height: 0; }
  .press-card__thumb img { width: 100%; height: 100%; object-fit: cover; aspect-ratio: 1 / 1; display: block; }
  @media (max-width: 640px) { .press-card__thumb img { aspect-ratio: 16/9; } }
  .press-card__body { padding: 1.5rem 1.75rem 1.5rem 0; }
  @media (max-width: 640px) { .press-card__body { padding: 1.25rem 1.5rem 1.5rem; } }
  .press-card__date { display: block; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--color-muted); margin-bottom: 0.5rem; font-weight: 600; }
  .press-card__title { font-size: 1.25rem; margin: 0 0 0.35rem; }
  .press-card__title a { color: var(--color-text); text-decoration: none; }
  .press-card__title a:hover { color: var(--color-accent); }
  .press-card__excerpt { margin: 0 0 1rem; line-height: 1.55; font-size: 0.95rem; }
  .press-card__more {
    font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;
    color: var(--color-accent); text-decoration: none;
  }
  .press-card__more:hover { text-decoration: underline; }

  .press-empty { text-align: center; padding: 2.5rem; background: var(--color-soft); border-radius: 6px; color: var(--color-muted); }
  .press-contact { margin-top: 3rem; padding: 2rem; background: var(--color-soft); border-radius: 6px; text-align: center; }
  .press-contact h2 { margin: 0 0 0.5rem; font-size: 1.2rem; }
  .press-contact p { margin: 0; color: var(--color-muted); font-size: 0.95rem; }
  .press-contact a { color: var(--color-accent); }
</style>

<div class="press-page">
  <header class="press-hero">
    <?php $_pr_eb = function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'eyebrow', 'Press' ) : 'Press'; ?>
    <?php if ( $_pr_eb ) : ?><span class="eyebrow"><?php echo esc_html( $_pr_eb ); ?></span><?php endif; ?>
    <h1><?php echo esc_html( function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'title', get_the_title() ) : get_the_title() ); ?></h1>
    <p class="lede"><?php echo esc_html( function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'lede', 'Press releases, recognitions, and news about Tacoma Little Theatre. For media inquiries, reach out to our box office.' ) : 'Press releases, recognitions, and news about Tacoma Little Theatre. For media inquiries, reach out to our box office.' ); ?></p>
  </header>

  <?php if ( ! empty( $items ) ) : ?>
    <div class="press-list">
      <?php foreach ( $items as $item ) : ?>
        <article class="press-card">
          <a class="press-card__thumb" href="<?php echo esc_url( $item['permalink'] ); ?>">
            <img src="<?php echo esc_url( $item['thumb'] ); ?>" alt="">
          </a>
          <div class="press-card__body">
            <?php if ( ! empty( $item['date'] ) ) : ?>
              <span class="press-card__date"><?php echo esc_html( $item['date'] ); ?></span>
            <?php endif; ?>
            <h2 class="press-card__title">
              <a href="<?php echo esc_url( $item['permalink'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a>
            </h2>
            <p class="press-card__excerpt"><?php echo esc_html( $item['excerpt'] ); ?></p>
            <a class="press-card__more" href="<?php echo esc_url( $item['permalink'] ); ?>">Read full release &rarr;</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else : ?>
    <div class="press-empty">
      <p style="margin:0">No press releases posted yet.</p>
    </div>
  <?php endif; ?>

  <div class="press-contact">
    <h2>Media Inquiries</h2>
    <p>Contact the box office at <a href="mailto:info@tacomalittletheatre.com">info@tacomalittletheatre.com</a> or <a href="tel:+12532722281">(253) 272-2281</a>.</p>
  </div>
</div>

<?php get_footer(); ?>
