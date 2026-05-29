<?php
/**
 * Template Name: Job Openings (Listing)
 *
 * Listing page that shows one card per active job posting, linking to the
 * full posting page (which uses page-job-posting.php).
 *
 * Adding a new posting:
 *   1. Create a new WP page under "Job Openings" with full posting content,
 *      assign template "Job Posting (Detail)".
 *   2. Add a row to the $listings array below.
 *
 * Note: we don't pull listings from the DB because there's typically just
 * one open posting at a time. Editing this array keeps the listing simple
 * and avoids extra page meta or a custom post type.
 */
get_header();

// Listing cards — add entries here as new postings go live
$listings = [
    [
        'eyebrow'  => 'Now Hiring',
        'title'    => 'Production Team Members for 2026–2027',
        'permalink'=> '/job-openings/2627-production-team/',
        'thumb'    => '/wp-content/uploads/migrated/orange-and-peach-simple-now-hiring-announcement-instagram-post.png',
        'meta'     => 'Letters reviewed on a rolling basis · Positions filled by May 31, 2026',
        'excerpt'  => 'TLT is seeking designers — stage managers, properties, sound, costume, and a scenic artist apprentice — for our 2026–2027 season. Collaborative artists with a passion for the craft are encouraged to apply.',
    ],
];
?>

<style>
  .jobs-page { max-width: 1100px; margin: 0 auto; padding: 2rem var(--pad) 4rem; }
  .jobs-hero { text-align: center; padding: 2rem 0 1.5rem; }
  .jobs-hero .eyebrow { display: inline-block; background: var(--color-accent); color: #fff; font-size: 0.75rem; letter-spacing: 0.15em; text-transform: uppercase; padding: 0.3rem 0.9rem; border-radius: 999px; margin-bottom: 1rem; }
  .jobs-hero h1 { font-size: clamp(2rem, 5vw, 3rem); margin: 0 0 0.5rem; }
  .jobs-hero .lede { font-size: 1.05rem; line-height: 1.6; color: var(--color-muted); max-width: 720px; margin: 0 auto; }
  .jobs-hero .lede a { color: var(--color-accent); }

  .job-list { display: grid; gap: 1.5rem; margin-top: 2.5rem; }
  .job-card {
    display: grid; grid-template-columns: 240px 1fr; gap: 1.5rem;
    background: #fff; border: 1px solid var(--color-line); border-radius: 6px;
    overflow: hidden; transition: border-color 0.15s, box-shadow 0.15s;
  }
  .job-card:hover { border-color: var(--color-accent); box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
  @media (max-width: 640px) { .job-card { grid-template-columns: 1fr; } }
  .job-card__thumb {
    background: var(--color-soft); display: block; overflow: hidden;
    line-height: 0;
  }
  .job-card__thumb img {
    width: 100%; height: 100%; object-fit: cover; aspect-ratio: 1 / 1;
    display: block;
  }
  @media (max-width: 640px) { .job-card__thumb img { aspect-ratio: 16/9; } }
  .job-card__body { padding: 1.5rem 1.75rem 1.5rem 0; }
  @media (max-width: 640px) { .job-card__body { padding: 1.25rem 1.5rem 1.5rem; } }
  .job-card__eyebrow { display: inline-block; background: var(--color-accent); color: #fff; font-size: 0.7rem; letter-spacing: 0.1em; text-transform: uppercase; padding: 0.2rem 0.6rem; border-radius: 999px; margin-bottom: 0.6rem; }
  .job-card__title { font-size: 1.25rem; margin: 0 0 0.35rem; }
  .job-card__title a { color: var(--color-text); text-decoration: none; }
  .job-card__title a:hover { color: var(--color-accent); }
  .job-card__meta { margin: 0 0 0.6rem; font-size: 0.85rem; color: var(--color-muted); }
  .job-card__excerpt { margin: 0 0 1rem; line-height: 1.55; font-size: 0.95rem; }
  .job-card__more {
    font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;
    color: var(--color-accent); text-decoration: none;
  }
  .job-card__more:hover { text-decoration: underline; }

  .jobs-empty { text-align: center; padding: 2.5rem; background: var(--color-soft); border-radius: 6px; color: var(--color-muted); }
</style>

<div class="jobs-page">
  <header class="jobs-hero">
    <span class="eyebrow">Work With Us</span>
    <h1><?php the_title(); ?></h1>
    <p class="lede">Tacoma Little Theatre is always looking for talented people to join our team. Check below for current openings, and feel free to send a resume to <a href="mailto:jobs@tacomalittletheatre.com">jobs@tacomalittletheatre.com</a> any time — even if nothing's posted, we keep applications on file.</p>
  </header>

  <?php if ( ! empty( $listings ) ) : ?>
    <div class="job-list">
      <?php foreach ( $listings as $job ) : ?>
        <article class="job-card">
          <a class="job-card__thumb" href="<?php echo esc_url( $job['permalink'] ); ?>">
            <img src="<?php echo esc_url( $job['thumb'] ); ?>" alt="">
          </a>
          <div class="job-card__body">
            <?php if ( ! empty( $job['eyebrow'] ) ) : ?>
              <span class="job-card__eyebrow"><?php echo esc_html( $job['eyebrow'] ); ?></span>
            <?php endif; ?>
            <h2 class="job-card__title">
              <a href="<?php echo esc_url( $job['permalink'] ); ?>"><?php echo esc_html( $job['title'] ); ?></a>
            </h2>
            <?php if ( ! empty( $job['meta'] ) ) : ?>
              <p class="job-card__meta"><?php echo esc_html( $job['meta'] ); ?></p>
            <?php endif; ?>
            <p class="job-card__excerpt"><?php echo esc_html( $job['excerpt'] ); ?></p>
            <a class="job-card__more" href="<?php echo esc_url( $job['permalink'] ); ?>">Read full posting &rarr;</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php else : ?>
    <div class="jobs-empty">
      <p style="margin:0">No openings posted at the moment.</p>
      <p style="margin:0.75rem 0 0">Send a resume to <a href="mailto:jobs@tacomalittletheatre.com">jobs@tacomalittletheatre.com</a> any time — we keep applications on file.</p>
    </div>
  <?php endif; ?>
</div>

<?php get_footer(); ?>
