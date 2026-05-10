<?php
/**
 * Single team member.
 */
get_header();

while ( have_posts() ) : the_post();
    $role  = get_post_meta( get_the_ID(), 'team_role_title', true );
    $email = get_post_meta( get_the_ID(), 'team_email', true );
    $pron  = get_post_meta( get_the_ID(), 'team_pronouns', true );
?>

<div class="container page-content">
  <header class="page-header">
    <?php $img = get_the_post_thumbnail_url( get_the_ID(), 'large' ); ?>
    <?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt="" style="max-width:300px;border-radius:4px;margin-bottom:1rem"><?php endif; ?>
    <h1><?php the_title(); ?> <?php if ( $pron ) echo '<small style="font-size:0.5em;color:var(--color-muted)">(' . esc_html( $pron ) . ')</small>'; ?></h1>
    <?php if ( $role ) : ?><p style="font-size:1.1rem;color:var(--color-accent);font-weight:600;text-transform:uppercase;letter-spacing:0.05em"><?php echo esc_html( $role ); ?></p><?php endif; ?>
  </header>

  <?php the_content(); ?>

  <?php if ( $email ) : ?>
    <p><a href="mailto:<?php echo esc_attr( $email ); ?>" class="btn btn-primary">Email <?php echo esc_html( get_the_title() ); ?></a></p>
  <?php endif; ?>
</div>

<?php endwhile; get_footer(); ?>
