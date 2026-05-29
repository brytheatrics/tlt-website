<?php
/**
 * Template Name: Job Posting (Detail)
 *
 * Individual job posting page (linked from page-job-openings.php).
 * Page meta:
 *   job_eyebrow   — pill text above title (e.g. "Now Hiring")
 *   job_meta      — small text under title (e.g. "Letters reviewed on a rolling basis…")
 *   job_thumb     — URL of the hero/thumbnail image
 *   job_apply_url — mailto: or https:// URL for the apply button (e.g. mailto:jobs@…?subject=…)
 *   job_apply_intro — paragraph above the apply button
 *
 * The body is whatever post_content has — full posting details go there.
 */
get_header();

while ( have_posts() ) : the_post();
    $eyebrow      = get_post_meta( get_the_ID(), 'job_eyebrow', true ) ?: 'Now Hiring';
    $meta         = get_post_meta( get_the_ID(), 'job_meta', true );
    $thumb        = get_post_meta( get_the_ID(), 'job_thumb', true );
    $apply_url    = get_post_meta( get_the_ID(), 'job_apply_url', true );
    $apply_intro  = get_post_meta( get_the_ID(), 'job_apply_intro', true );
?>

<style>
  .jp-page { max-width: 900px; margin: 0 auto; padding: 2rem var(--pad) 4rem; }
  .jp-back { display: inline-block; margin-bottom: 1rem; font-size: 0.85rem; color: var(--color-muted); text-decoration: none; }
  .jp-back:hover { color: var(--color-accent); }

  .jp-hero { background: var(--color-soft); border-radius: 6px; padding: 2rem; margin-bottom: 2rem; display: grid; gap: 2rem; align-items: center; }
  .jp-hero.has-thumb { grid-template-columns: 200px 1fr; }
  @media (max-width: 640px) {
    .jp-hero.has-thumb { grid-template-columns: 1fr; text-align: center; }
  }
  .jp-hero img { width: 100%; max-width: 200px; height: auto; border-radius: 4px; display: block; }
  @media (max-width: 640px) { .jp-hero img { margin: 0 auto; } }
  .jp-hero__eyebrow { display: inline-block; background: var(--color-accent); color: #fff; font-size: 0.7rem; letter-spacing: 0.1em; text-transform: uppercase; padding: 0.2rem 0.6rem; border-radius: 999px; margin-bottom: 0.6rem; }
  .jp-hero__title { font-size: 1.6rem; margin: 0 0 0.4rem; line-height: 1.2; }
  .jp-hero__meta { margin: 0; font-size: 0.88rem; color: var(--color-muted); line-height: 1.5; }

  .jp-body { font-size: 1rem; }
  .jp-body h2, .jp-body h3 {
    font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.08em;
    color: var(--color-accent); margin: 2rem 0 0.75rem;
    padding-bottom: 0.5rem; border-bottom: 1px solid var(--color-line);
  }
  .jp-body h2:first-child, .jp-body h3:first-child { margin-top: 0; }
  .jp-body p { line-height: 1.65; margin: 0 0 1rem; }

  .jp-body ul.job-positions { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 0.5rem; margin: 0; padding: 0; list-style: none; }
  .jp-body ul.job-positions li { background: var(--color-soft); padding: 0.65rem 1rem; border-radius: 4px; font-weight: 600; font-size: 0.95rem; }

  .jp-body .show-list { display: grid; gap: 1rem; }
  .jp-body .show-list .show { border-left: 3px solid var(--color-accent); padding: 0.5rem 0 0.5rem 1rem; }
  .jp-body .show-list .show-title { font-weight: 700; font-size: 1rem; margin: 0 0 0.15rem; }
  .jp-body .show-list .show-meta { font-size: 0.85rem; color: var(--color-muted); margin: 0 0 0.4rem; }
  .jp-body .show-list .show-blurb { font-size: 0.92rem; line-height: 1.5; margin: 0; }

  .jp-apply { background: linear-gradient(135deg, var(--color-soft) 0%, #fff 100%); border: 1px solid var(--color-line); border-radius: 6px; padding: 2rem; text-align: center; margin-top: 2rem; }
  .jp-apply h2 { color: var(--color-text); border: none; padding: 0; text-transform: none; letter-spacing: 0; font-size: 1.3rem; margin: 0 0 0.5rem; }
  .jp-apply p { margin: 0 0 1.25rem; color: var(--color-muted); font-size: 0.95rem; }
</style>

<div class="jp-page">

  <a href="/job-openings/" class="jp-back">&larr; All Job Openings</a>

  <div class="jp-hero<?php echo $thumb ? ' has-thumb' : ''; ?>">
    <?php if ( $thumb ) : ?>
      <img src="<?php echo esc_url( $thumb ); ?>" alt="">
    <?php endif; ?>
    <div>
      <span class="jp-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></span>
      <h1 class="jp-hero__title"><?php the_title(); ?></h1>
      <?php if ( $meta ) : ?>
        <p class="jp-hero__meta"><?php echo esc_html( $meta ); ?></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="jp-body">
    <?php the_content(); ?>

    <?php if ( $apply_url ) : ?>
      <div class="jp-apply">
        <h2>How to Apply</h2>
        <?php if ( $apply_intro ) : ?>
          <p><?php echo esc_html( $apply_intro ); ?></p>
        <?php endif; ?>
        <a class="btn btn-primary" href="<?php echo esc_url( $apply_url ); ?>">Email Your Application</a>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php endwhile; get_footer(); ?>
