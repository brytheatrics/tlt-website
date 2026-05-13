<?php
/**
 * Template Name: Contact
 *
 * Standard contact page: form + box-office hours + address card + map.
 *
 * Page meta:
 *   contact_form_shortcode  — Contact Form 7 (or equivalent) shortcode for the form
 *   contact_box_office      — box office hours block (HTML)
 *   contact_address         — postal address block (HTML)
 *   contact_phone           — phone number
 *   contact_email           — primary contact email
 *   contact_map_embed       — Google Maps iframe embed code (just the URL, we'll wrap)
 *
 * Page body content renders above the form (intro/instructions).
 */
get_header();

while ( have_posts() ) : the_post();
  $form_shortcode = get_post_meta( get_the_ID(), 'contact_form_shortcode', true );
  $box_office     = get_post_meta( get_the_ID(), 'contact_box_office', true );
  $address        = get_post_meta( get_the_ID(), 'contact_address', true );
  $phone          = get_post_meta( get_the_ID(), 'contact_phone', true ) ?: '(253) 272-2281';
  $email          = get_post_meta( get_the_ID(), 'contact_email', true ) ?: 'boxoffice@tacomalittletheatre.com';
  $map_url        = get_post_meta( get_the_ID(), 'contact_map_embed', true );
?>

<?php if ( has_post_thumbnail() ) : ?>
  <div class="page-hero"><?php the_post_thumbnail( 'full' ); ?></div>
<?php endif; ?>

<div class="container page-content">
  <header class="page-header">
    <h1><?php the_title(); ?></h1>
    <?php if ( has_excerpt() ) : ?>
      <p class="page-subtitle"><?php echo esc_html( get_the_excerpt() ); ?></p>
    <?php endif; ?>
  </header>

  <div class="contact-page">
    <div class="contact-page__main">
      <article class="page-body">
        <?php the_content(); ?>
      </article>

      <?php if ( $form_shortcode ) : ?>
        <div class="contact-page__form">
          <?php echo do_shortcode( $form_shortcode ); ?>
        </div>
      <?php endif; ?>
    </div>

    <aside class="contact-page__sidebar">
      <?php if ( $address ) : ?>
        <div class="contact-block">
          <h3>Address</h3>
          <?php echo wp_kses_post( wpautop( $address ) ); ?>
        </div>
      <?php else : ?>
        <div class="contact-block">
          <h3>Address</h3>
          <p>Tacoma Little Theatre<br>
          210 N "I" Street<br>
          Tacoma, WA 98403</p>
        </div>
      <?php endif; ?>

      <?php if ( $box_office ) : ?>
        <div class="contact-block">
          <h3>Box Office Hours</h3>
          <?php echo wp_kses_post( wpautop( $box_office ) ); ?>
        </div>
      <?php endif; ?>

      <div class="contact-block">
        <h3>Phone</h3>
        <p><a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></p>
      </div>

      <div class="contact-block">
        <h3>Email</h3>
        <p><a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></p>
      </div>

      <?php if ( $map_url ) : ?>
        <div class="contact-page__map">
          <iframe src="<?php echo esc_url( $map_url ); ?>" loading="lazy" allowfullscreen></iframe>
        </div>
      <?php endif; ?>
    </aside>
  </div>
</div>

<?php endwhile;
get_footer(); ?>
