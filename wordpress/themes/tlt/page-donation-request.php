<?php
/**
 * Template Name: Donation Request
 *
 * Auction-donation request form with grouped fields, a "review process"
 * sidebar, and form styling that matches the rest of the site.
 *
 * The actual form is the CF7 shortcode (form #1313).
 */
get_header(); ?>

<style>
  .dr-page { max-width: 1100px; margin: 0 auto; padding: 2rem var(--pad) 4rem; }
  .dr-hero { text-align: center; padding: 2rem 0 1rem; }
  .dr-hero .eyebrow { display: inline-block; background: var(--color-accent); color: #fff; font-size: 0.75rem; letter-spacing: 0.15em; text-transform: uppercase; padding: 0.3rem 0.9rem; border-radius: 999px; margin-bottom: 1rem; }
  .dr-hero h1 { font-size: clamp(1.8rem, 4vw, 2.6rem); margin: 0 0 0.75rem; }
  .dr-hero .lede { font-size: 1.05rem; line-height: 1.6; color: var(--color-text); max-width: 720px; margin: 0 auto; }

  .dr-layout { display: grid; grid-template-columns: 1fr 320px; gap: 2.5rem; margin-top: 2.5rem; align-items: start; }
  @media (max-width: 880px) { .dr-layout { grid-template-columns: 1fr; } }

  .dr-form-card { background: #fff; border: 1px solid var(--color-line); border-radius: 6px; padding: 2rem; }

  .dr-aside { display: flex; flex-direction: column; gap: 1.25rem; position: sticky; top: 1rem; }
  .dr-aside .panel { background: var(--color-soft); border: 1px solid var(--color-line); border-radius: 6px; padding: 1.25rem 1.5rem; }
  .dr-aside .panel.accent { background: #fff; border-left: 4px solid var(--color-accent); }
  .dr-aside .panel h3 { margin: 0 0 0.5rem; font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); }
  .dr-aside .panel p { margin: 0 0 0.5rem; font-size: 0.92rem; line-height: 1.55; }
  .dr-aside .panel p:last-child { margin-bottom: 0; }

  /* Form styling — scoped to this page so we don't touch other CF7 forms */
  .dr-page .wpcf7 { font-family: inherit; }
  .dr-page .wpcf7-form > p { margin: 0 0 1.1rem; }
  .dr-page .wpcf7-form label { display: block; font-weight: 600; font-size: 0.9rem; color: var(--color-text); margin-bottom: 0.35rem; }
  .dr-page .wpcf7-form input[type=text],
  .dr-page .wpcf7-form input[type=email],
  .dr-page .wpcf7-form input[type=tel],
  .dr-page .wpcf7-form input[type=date],
  .dr-page .wpcf7-form select,
  .dr-page .wpcf7-form textarea {
    width: 100%;
    padding: 0.65rem 0.85rem;
    border: 1px solid var(--color-line);
    border-radius: 4px;
    font-family: inherit;
    font-size: 0.95rem;
    background: #fff;
    transition: border-color 0.15s, box-shadow 0.15s;
    box-sizing: border-box;
    margin-top: 0.25rem;
  }
  .dr-page .wpcf7-form input:focus,
  .dr-page .wpcf7-form select:focus,
  .dr-page .wpcf7-form textarea:focus {
    outline: none;
    border-color: var(--color-accent);
    box-shadow: 0 0 0 3px rgba(184, 37, 47, 0.15);
  }
  .dr-page .wpcf7-form textarea { min-height: 120px; resize: vertical; }
  /* Place address city/state/zip on one row.
     CF7 wraps each field in <span class="wpcf7-form-control-wrap"> with <br/>
     between, so we hide the br's and target the spans as grid items. */
  .dr-page .wpcf7-form .addr-row {
    display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 0.5rem;
    align-items: end;
  }
  .dr-page .wpcf7-form .addr-row br { display: none; }
  .dr-page .wpcf7-form .addr-row .wpcf7-form-control-wrap { display: block; width: 100%; }
  .dr-page .wpcf7-form .addr-row input { margin-top: 0; }
  @media (max-width: 540px) {
    .dr-page .wpcf7-form .addr-row { grid-template-columns: 1fr; gap: 0.6rem; }
  }
  /* First/Last name pair */
  .dr-page .wpcf7-form .name-row {
    display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;
  }
  .dr-page .wpcf7-form .name-row br { display: none; }
  .dr-page .wpcf7-form .name-row .wpcf7-form-control-wrap { display: block; width: 100%; }
  .dr-page .wpcf7-form .name-row input { margin-top: 0; }
  @media (max-width: 540px) {
    .dr-page .wpcf7-form .name-row { grid-template-columns: 1fr; }
  }
  /* Section headings inside the form */
  .dr-page .wpcf7-form .form-section-h {
    font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em;
    color: var(--color-muted); margin: 2rem 0 0.75rem; padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--color-line);
    font-weight: 700;
  }
  .dr-page .wpcf7-form .form-section-h:first-child { margin-top: 0; }
  /* Submit button */
  .dr-page .wpcf7-form input[type=submit] {
    background: var(--color-accent); color: #fff; border: none;
    padding: 0.9rem 2rem; font-family: var(--font-display); font-weight: 600;
    font-size: 0.95rem; letter-spacing: 0.08em; text-transform: uppercase;
    border-radius: 4px; cursor: pointer; transition: background 0.15s;
    margin-top: 0.5rem;
  }
  .dr-page .wpcf7-form input[type=submit]:hover { background: var(--color-accent-dark); }
  /* Required-field indicator: CF7 doesn't add a visible asterisk; we use the (required) label text but also show a red dot */
  .dr-page .wpcf7-form .wpcf7-validates-as-required + .wpcf7-not-valid-tip,
  .dr-page .wpcf7-form .wpcf7-not-valid-tip { color: var(--color-accent); font-size: 0.85rem; }
  .dr-page .wpcf7-response-output {
    border-radius: 4px; padding: 1rem; margin: 1.5rem 0 0;
  }
  /* Helper hint text (the small paragraph just inside the form card) */
  .dr-page .dr-form-card > p:first-of-type {
    color: var(--color-muted); font-size: 0.88rem; font-style: italic; margin: 0 0 1.5rem;
  }
</style>

<div class="dr-page">

  <header class="dr-hero">
    <span class="eyebrow">Auction Donations</span>
    <h1><?php the_title(); ?></h1>
    <p class="lede">Tacoma Little Theatre is always happy to give back to our wonderful and supportive community. Thank you for thinking of TLT as a way to support your organization.</p>
  </header>

  <div class="dr-layout">

    <div class="dr-form-card">
      <p>Fields marked with (required) must be filled in. Please allow at least four weeks before your event.</p>
      <?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
    </div>

    <aside class="dr-aside">
      <div class="panel accent">
        <h3>Review Process</h3>
        <p>This request must be submitted at least <strong>four weeks</strong> prior to the day your organization needs the item.</p>
        <p>Staff reviews submissions on an ongoing basis. Due to the high volume we receive, we are unable to honor every request.</p>
        <p><strong>Good luck at your upcoming event!</strong></p>
      </div>

      <div class="panel">
        <h3>Questions?</h3>
        <p>Contact our Box Office:</p>
        <p>
          <a href="mailto:info@tacomalittletheatre.com">info@tacomalittletheatre.com</a><br>
          <a href="tel:+12532722281">(253) 272-2281</a>
        </p>
      </div>
    </aside>

  </div>
</div>

<?php get_footer(); ?>
