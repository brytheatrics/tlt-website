<?php
/**
 * Team archive — /team/
 */
get_header(); ?>

<div class="container">
  <header class="page-header">
    <h1>Board &amp; Staff</h1>
  </header>

  <?php
    // Show staff first, then board
    $sections = [
        'Staff' => [ 'team_is_staff', 1 ],
        'Board of Directors' => [ 'team_is_board', 1 ],
    ];
    foreach ( $sections as $heading => [ $key, $val ] ) :
        $q = new WP_Query( [
            'post_type' => 'tlt_team',
            'posts_per_page' => -1,
            'meta_query' => [ [ 'key' => $key, 'value' => $val ] ],
            'orderby' => 'title', 'order' => 'ASC',
        ] );
        if ( $q->have_posts() ) :
  ?>
    <h2 style="margin-top:2rem;text-align:center"><?php echo esc_html( $heading ); ?></h2>
    <div class="team-grid">
      <?php while ( $q->have_posts() ) : $q->the_post();
        $img = get_the_post_thumbnail_url( get_the_ID(), 'medium' );
        $role = get_post_meta( get_the_ID(), 'team_role_title', true );
      ?>
        <a href="<?php the_permalink(); ?>" class="team-card" style="color:var(--color-text)">
          <?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt=""><?php endif; ?>
          <h3><?php the_title(); ?></h3>
          <?php if ( $role ) : ?><div class="role"><?php echo esc_html( $role ); ?></div><?php endif; ?>
        </a>
      <?php endwhile; ?>
    </div>
  <?php endif; wp_reset_postdata(); endforeach; ?>
</div>

<?php get_footer(); ?>
