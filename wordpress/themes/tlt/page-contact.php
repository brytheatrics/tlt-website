<?php
/**
 * Template Name: Contact
 *
 * Contact page: hero + styled form + info sidebar. Visual rhythm matches
 * the donation-request, season-tickets, and visit page rebuilds.
 *
 * Page meta (all optional; sensible defaults used otherwise):
 *   contact_form_shortcode  — CF7 shortcode (e.g. [contact-form-7 id="1312" …])
 *   contact_box_office      — box office hours block (HTML or plain)
 *   contact_address         — postal address block (HTML or plain)
 *   contact_phone           — phone number
 *   contact_email           — primary contact email
 *
 * Page body content renders as the intro paragraph above the form.
 */
get_header();

while ( have_posts() ) : the_post();
  $form_shortcode = get_post_meta( get_the_ID(), 'contact_form_shortcode', true );
  $box_office     = get_post_meta( get_the_ID(), 'contact_box_office', true );
  $address        = get_post_meta( get_the_ID(), 'contact_address', true );
  $phone          = get_post_meta( get_the_ID(), 'contact_phone', true ) ?: '(253) 272-2281';
  $email          = get_post_meta( get_the_ID(), 'contact_email', true ) ?: 'boxoffice@tacomalittletheatre.com';

  // Default box-office hours if not set in meta
  if ( ! $box_office ) {
      $box_office = "Tuesday – Friday\n1:00 pm – 6:00 pm\n\nPlus 1.5 hours prior to all public performances";
  }
  if ( ! $address ) {
      $address = "Tacoma Little Theatre\n210 N \"I\" Street\nTacoma, WA 98403";
  }
?>

<style>
  .ct-page { max-width: 1100px; margin: 0 auto; padding: 2rem var(--pad) 4rem; }

  .ct-hero { text-align: center; padding: 2rem 0 1.5rem; }
  .ct-hero .eyebrow { display: inline-block; background: var(--color-accent); color: #fff; font-size: 0.75rem; letter-spacing: 0.15em; text-transform: uppercase; padding: 0.3rem 0.9rem; border-radius: 999px; margin-bottom: 1rem; }
  .ct-hero h1 { font-size: clamp(2rem, 5vw, 3rem); margin: 0 0 0.75rem; }
  .ct-hero .lede { font-size: 1.05rem; line-height: 1.6; color: var(--color-text); max-width: 700px; margin: 0 auto; }
  .ct-hero .lede a { color: var(--color-accent); }

  .ct-layout { display: grid; grid-template-columns: 1fr 320px; gap: 2.5rem; margin-top: 2.5rem; align-items: start; }
  @media (max-width: 880px) { .ct-layout { grid-template-columns: 1fr; } }

  .ct-form-card { background: #fff; border: 1px solid var(--color-line); border-radius: 6px; padding: 2rem; }
  .ct-form-card > p:first-of-type { color: var(--color-muted); font-size: 0.88rem; font-style: italic; margin: 0 0 1.5rem; }

  .ct-aside { display: flex; flex-direction: column; gap: 1.25rem; position: sticky; top: 1rem; }
  .ct-aside .panel { background: var(--color-soft); border: 1px solid var(--color-line); border-radius: 6px; padding: 1.25rem 1.5rem; }
  .ct-aside .panel h3 { margin: 0 0 0.5rem; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-accent); }
  .ct-aside .panel p { margin: 0; font-size: 0.93rem; line-height: 1.6; }
  .ct-aside .panel a { color: var(--color-text); text-decoration: underline; }
  .ct-aside .panel a:hover { color: var(--color-accent); }

  /* Form styling — mirrors donation-request so the look is consistent */
  .ct-page .wpcf7 { font-family: inherit; }
  .ct-page .wpcf7-form > p { margin: 0 0 1.1rem; }
  .ct-page .wpcf7-form label { display: block; font-weight: 600; font-size: 0.9rem; color: var(--color-text); margin-bottom: 0.35rem; }
  .ct-page .wpcf7-form input[type=text],
  .ct-page .wpcf7-form input[type=email],
  .ct-page .wpcf7-form input[type=tel],
  .ct-page .wpcf7-form textarea {
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
  .ct-page .wpcf7-form input:focus,
  .ct-page .wpcf7-form textarea:focus {
    outline: none;
    border-color: var(--color-accent);
    box-shadow: 0 0 0 3px rgba(184, 37, 47, 0.15);
  }
  .ct-page .wpcf7-form textarea { min-height: 140px; resize: vertical; }
  .ct-page .wpcf7-form input[type=submit] {
    background: var(--color-accent); color: #fff; border: none;
    padding: 0.9rem 2rem; font-family: var(--font-display); font-weight: 600;
    font-size: 0.95rem; letter-spacing: 0.08em; text-transform: uppercase;
    border-radius: 4px; cursor: pointer; transition: background 0.15s;
    margin-top: 0.5rem;
  }
  .ct-page .wpcf7-form input[type=submit]:hover { background: var(--color-accent-dark); }
  .ct-page .wpcf7-response-output { border-radius: 4px; padding: 1rem; margin: 1.5rem 0 0; }
  .ct-page .wpcf7-not-valid-tip { color: var(--color-accent); font-size: 0.85rem; }
</style>

<div class="ct-page">

  <header class="ct-hero">
    <span class="eyebrow">Contact</span>
    <h1><?php the_title(); ?></h1>
    <div class="lede">
      <?php the_content(); ?>
    </div>
  </header>

  <div class="ct-layout">

    <div class="ct-form-card">
      <p>Fields marked with (required) must be filled in.</p>
      <?php
        if ( $form_shortcode ) {
            echo do_shortcode( $form_shortcode );
        } else {
            // Fallback if no meta is set
            echo do_shortcode( '[contact-form-7 id="1312" title="Contact"]' );
        }
      ?>
    </div>

    <aside class="ct-aside">
      <div class="panel">
        <h3>Box Office Hours</h3>
        <p><?php echo nl2br( esc_html( $box_office ) ); ?></p>
      </div>

      <div class="panel">
        <h3>Address</h3>
        <p><?php echo nl2br( esc_html( $address ) ); ?></p>
      </div>

      <div class="panel">
        <h3>Phone</h3>
        <p><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></p>
      </div>

      <div class="panel">
        <h3>Email</h3>
        <p><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></p>
      </div>
    </aside>

  </div>
</div>

<?php endwhile;
get_footer(); ?>
