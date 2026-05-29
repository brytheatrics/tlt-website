<?php
/**
 * Single team member.
 */
get_header();

while ( have_posts() ) : the_post();
    $role  = get_post_meta( get_the_ID(), 'team_role_title', true );
    $email = get_post_meta( get_the_ID(), 'team_email', true );
    $pron  = get_post_meta( get_the_ID(), 'team_pronouns', true );
    $img   = get_the_post_thumbnail_url( get_the_ID(), 'large' );
    if ( ! $img ) $img = get_post_meta( get_the_ID(), '_thumbnail_external_url', true );
?>

<style>
  .team-page { max-width: 960px; }
  .team-page h1 { color: var(--color-accent); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.25rem; }
  .team-page .role { font-size: 1rem; color: var(--color-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1.5rem; }
  .team-bio { display: grid; grid-template-columns: 280px 1fr; gap: 2rem; align-items: start; }
  .team-bio .photo { width: 100%; aspect-ratio: 3/4; object-fit: cover; object-position: center top; border-radius: 4px; background: var(--color-soft); }
  .team-bio .bio-text { font-size: 1rem; line-height: 1.6; }
  .team-bio .bio-text p:first-child { margin-top: 0; }
  @media (max-width: 640px) {
    .team-bio { grid-template-columns: 1fr; }
    .team-bio .photo { max-width: 240px; margin: 0 auto; }
  }
</style>

<div class="container page-content team-page">
  <h1><?php the_title(); ?> <?php if ( $pron ) echo '<small style="font-size:0.5em;color:var(--color-muted);text-transform:none;letter-spacing:0">(' . esc_html( $pron ) . ')</small>'; ?></h1>
  <?php if ( $role ) : ?><p class="role"><?php echo esc_html( $role ); ?></p><?php endif; ?>

  <div class="team-bio">
    <?php if ( $img ) : ?>
      <img class="photo" src="<?php echo esc_url( $img ); ?>" alt="">
    <?php else : ?>
      <div class="photo"></div>
    <?php endif; ?>
    <div class="bio-text">
      <?php the_content(); ?>
      <?php if ( $email ) : ?>
        <p><a href="mailto:<?php echo esc_attr( $email ); ?>" class="btn btn-primary">Email <?php echo esc_html( get_the_title() ); ?></a></p>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php endwhile; get_footer(); ?>
