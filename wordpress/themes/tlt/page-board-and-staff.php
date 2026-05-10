<?php
/**
 * Template Name: Board & Staff
 *
 * Replaces the auto-generated team archive with a structured page matching
 * https://www.tacomalittletheatre.com/board-and-staff layout.
 */
get_header(); ?>

<style>
  .bs-page { max-width: 1100px; margin: 0 auto; padding: 3rem var(--pad); }
  .bs-page h1 { text-align: center; margin: 2.5rem 0 1.5rem; }
  .bs-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1.5rem; }
  .bs-card { text-align: center; }
  .bs-card .photo { width: 100%; aspect-ratio: 1/1; object-fit: cover; background: #eee; border-radius: 4px; margin-bottom: 0.75rem; }
  .bs-card h3 { font-size: 0.95rem; margin: 0 0 0.15rem; text-transform: uppercase; letter-spacing: 0.03em; }
  .bs-card .role { font-size: 0.8rem; color: var(--color-muted); text-transform: uppercase; letter-spacing: 0.05em; }
  .bs-bylaws { text-align: center; margin: 2rem 0; }
  .bs-bylaws a { font-size: 1.05rem; font-weight: 600; }
  .bs-section-divider { border: 0; border-top: 1px solid var(--color-line); margin: 3rem 0; }
  .past-mads { background: var(--color-soft); padding: 2rem; margin: 3rem 0; border-radius: 4px; }
  .past-mads h2 { text-align: center; font-size: 1rem; letter-spacing: 0.1em; margin-bottom: 1.5rem; }
  .past-mads ul { list-style: none; padding: 0; columns: 2; gap: 1.5rem; }
  @media (max-width: 600px) { .past-mads ul { columns: 1; } }
  .past-mads li { padding: 0.3rem 0; font-size: 0.95rem; break-inside: avoid; }
  .past-mads li b { display: block; }
  .past-mads .group-photo { margin: 2rem 0 1rem; max-width: 800px; margin-left: auto; margin-right: auto; }
  .past-mads .group-photo img { width: 100%; border-radius: 4px; }
  .past-mads .caption { text-align: center; font-size: 0.85rem; color: var(--color-muted); font-style: italic; }
  .venue-section { text-align: center; padding: 3rem 0; border-top: 1px solid var(--color-line); margin-top: 3rem; }
  .venue-section h2 { font-size: 1.3rem; }
  .venue-section .mission { max-width: 700px; margin: 1rem auto; }
  .venue-section .land-ack { max-width: 700px; margin: 1.5rem auto; font-size: 0.9rem; color: var(--color-muted); font-style: italic; }
</style>

<?php
function tlt_render_team_grid( $args ) {
    $q = new WP_Query( array_merge( [
        'post_type' => 'tlt_team',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ], $args ) );
    if ( ! $q->have_posts() ) { echo '<p style="text-align:center">(empty)</p>'; return; }
    echo '<div class="bs-grid">';
    while ( $q->have_posts() ) : $q->the_post();
        $img = get_the_post_thumbnail_url( get_the_ID(), 'medium' );
        if ( ! $img ) $img = get_post_meta( get_the_ID(), '_thumbnail_external_url', true );
        $role = get_post_meta( get_the_ID(), 'team_role_title', true );
        $title = get_the_title();
        $upper = strtoupper( $title );
        ?>
        <a href="<?php the_permalink(); ?>" class="bs-card" style="color:var(--color-text)">
          <?php if ( $img ) : ?>
            <img class="photo" src="<?php echo esc_url( $img ); ?>" alt="">
          <?php else : ?>
            <div class="photo"></div>
          <?php endif; ?>
          <h3><?php echo esc_html( $upper ); ?></h3>
          <?php if ( $role ) : ?><div class="role"><?php echo esc_html( strtoupper( $role ) ); ?></div><?php endif; ?>
        </a>
        <?php
    endwhile;
    echo '</div>';
    wp_reset_postdata();
}
?>

<div class="bs-page">

  <h1>Board of Directors</h1>
  <?php tlt_render_team_grid( [
      'meta_query' => [ [ 'key' => 'team_is_board', 'value' => '1' ] ],
  ] ); ?>

  <p class="bs-bylaws">
    <a href="/wp-content/uploads/TLT-Amended-Bylaws-2016-11-1.pdf">View our current bylaws here</a>
    &nbsp;&middot;&nbsp;
    <a href="mailto:board@tacomalittletheatre.com">Contact the Board</a>
  </p>

  <hr class="bs-section-divider">

  <h1>Staff</h1>
  <?php tlt_render_team_grid( [
      'meta_query' => [ [ 'key' => 'team_is_staff', 'value' => '1' ] ],
  ] ); ?>

  <section class="past-mads">
    <h2>Past Managing Artistic Directors</h2>
    <ul>
      <li><b>Elaine Winters</b> 1983–1988</li>
      <li><b>Nikki Smith</b> 1988–1990</li>
      <li><b>Peter Epperson</b> 1991–1993</li>
      <li><b>David Fischer</b> 1994–1996</li>
      <li><b>Charlotte Teincken</b> 1996–1999</li>
      <li><b>Judy Cullen</b> 1999–2006</li>
      <li><b>David Duvall</b> 2006–2007</li>
      <li><b>Scott Campbell</b> 2009–2011</li>
      <li><b>Chris Serface</b> 2013–current</li>
    </ul>
    <div class="group-photo">
      <?php
      // The group photo of past artistic directors. We pulled it during scrape.
      $group_img = get_template_directory_uri() . '/assets/past-mads.jpg';
      ?>
      <img src="<?php echo esc_url( $group_img ); ?>" alt="Past Managing Artistic Directors group photo">
      <p class="caption">Pictured left to right: TLT Artistic Directors Judy Cullen (1999–2000 &amp; 2001–2006), Chris Serface (2013–present), David Duvall (2007–2008), David Fischer (1994–1996), Charlotte Teincken (1996–1999), Scott Campbell (2009–2011), Nikki Smith (1988–1990).</p>
    </div>
  </section>

  <section class="venue-section">
    <h2>Located at 210 N "I" Street, Tacoma, WA 98403</h2>
    <p class="mission"><strong>Our Mission:</strong> Providing live theatre and education programs that inspire through stories reflecting the vibrancy of our diverse community.</p>
    <p class="land-ack">Tacoma Little Theatre recognizes that they teach and perform on Indigenous land: the traditional homelands of the Puyallup Tribe and Coast Salish peoples.</p>
  </section>

</div>

<?php get_footer(); ?>
