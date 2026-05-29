<?php
/**
 * Homepage — auto-rotating hero + current season + promo blocks + sponsors.
 * Matches mockup design while including all the content from the live homepage.
 */
get_header();

$hero         = function_exists('tlt_get_hero_show') ? tlt_get_hero_show() : null;
$current      = $hero ? $hero['show'] : null;
$hero_mode    = $hero ? $hero['mode'] : '';
$hero_label   = $hero ? $hero['label'] : '';
$season_shows = function_exists('tlt_get_current_season_shows') ? tlt_get_current_season_shows() : [];
$season_term  = function_exists('tlt_get_current_season_term') ? tlt_get_current_season_term() : null;
$today        = function_exists( 'tlt_today' ) ? tlt_today() : current_time( 'Y-m-d' );

// Determine which upcoming show is "next" (first one whose open_date > today)
$first_upcoming_id = null;
foreach ( $season_shows as $s ) {
    $o = get_post_meta( $s->ID, 'show_open_date', true );
    if ( $o && $o > $today ) { $first_upcoming_id = $s->ID; break; }
}

// Splash → Home wipe is now handled in header.php (it has to run BEFORE the
// header markup renders to prevent the header from flashing into view).
?>
<?php

if ( $current ) :
    $hero_img = tlt_show_image_url( $current->ID, 'full', 'hero' );
    $director = get_post_meta( $current->ID, 'show_director', true );
    $open  = get_post_meta( $current->ID, 'show_open_date', true );
    $close = get_post_meta( $current->ID, 'show_close_date', true );
    $tix = get_post_meta( $current->ID, 'show_ticket_url', true );

    // ----- Layered hero detection -----
    // If /wp-content/uploads/hero-layers/[slug]/ has files like 1-bg.png, 2-foo.png, etc.,
    // render the layered animated hero. Layer order is the leading number in the filename.
    $slug = $current->post_name;
    $layers_dir = WP_CONTENT_DIR . '/uploads/hero-layers/' . $slug;
    $layers_url = content_url( '/uploads/hero-layers/' . $slug );
    $layers = [];
    if ( is_dir( $layers_dir ) ) {
        $found = glob( $layers_dir . '/[0-9]-*.png' );
        sort( $found );
        foreach ( $found as $i => $path ) {
            $base = basename( $path );
            $layers[] = [
                'name'  => preg_replace( '/\.png$/i', '', $base ),
                'url'   => $layers_url . '/' . $base,
                'order' => $i + 1,
            ];
        }
    }
    $composite_path = $layers_dir . '/composite.jpg';
    $composite_url  = file_exists( $composite_path ) ? $layers_url . '/composite.jpg' : '';
?>
<section class="hero <?php echo esc_attr( 'hero-mode-' . $hero_mode ); ?><?php echo $layers ? ' hero-layered' : ''; ?>">
  <?php if ( $layers ) : ?>
    <!-- Layered hero (desktop/tablet): each layer slides in from a different direction, staggered -->
    <div class="hero-layers" aria-hidden="true">
      <?php foreach ( $layers as $l ) : ?>
        <img class="hero-layer hero-layer-<?php echo (int) $l['order']; ?>"
             data-name="<?php echo esc_attr( $l['name'] ); ?>"
             src="<?php echo esc_url( $l['url'] ); ?>" alt="">
      <?php endforeach; ?>
    </div>
    <?php if ( $composite_url ) : ?>
      <!-- Mobile static fallback -->
      <img class="hero-image hero-image-mobile" src="<?php echo esc_url( $composite_url ); ?>" alt="">
    <?php elseif ( $hero_img ) : ?>
      <img class="hero-image hero-image-mobile" src="<?php echo esc_url( $hero_img ); ?>" alt="">
    <?php endif; ?>
  <?php elseif ( $hero_img ) : ?>
    <img class="hero-image" src="<?php echo esc_url( $hero_img ); ?>" alt="">
  <?php endif; ?>
  <div class="hero-content">
    <div class="container">
      <div class="eyebrow"><?php echo esc_html( $hero_label ); ?></div>
      <h1><?php echo esc_html( get_the_title( $current ) ); ?></h1>
      <p class="meta">
        <?php echo esc_html( tlt_format_date_range( $open, $close ) ); ?>
        <?php if ( $director ) echo ' · Directed by ' . esc_html( $director ); ?>
      </p>
      <?php if ( $hero_mode === 'recap' ) : ?>
        <p class="meta" style="opacity:0.85">Our next season is being prepared. Stay tuned!</p>
      <?php endif; ?>
      <div class="cta">
        <?php if ( $hero_mode === 'now-playing' && $tix ) : ?>
          <a href="<?php echo esc_url( $tix ); ?>" class="btn btn-primary">Buy Tickets</a>
        <?php elseif ( $hero_mode === 'coming-soon' && $tix ) : ?>
          <a href="<?php echo esc_url( $tix ); ?>" class="btn btn-primary">Get Tickets</a>
        <?php elseif ( $hero_mode === 'coming-soon' ) : ?>
          <a href="/season-tickets/" class="btn btn-primary">Season Tickets</a>
        <?php elseif ( $hero_mode === 'recap' ) : ?>
          <a href="/season-tickets/" class="btn btn-primary">Next Season Tickets</a>
        <?php endif; ?>
        <a href="<?php echo esc_url( get_permalink( $current ) ); ?>" class="btn btn-outline">
          <?php echo $hero_mode === 'recap' ? 'View Show Details' : 'Show Details'; ?>
        </a>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php
// Cityline interview block — only renders when the hero show has a URL set.
$cityline_url = $current ? get_post_meta( $current->ID, 'show_cityline_url', true ) : '';
if ( $cityline_url ) :
  $cityline_embed = wp_oembed_get( $cityline_url, [ 'width' => 800 ] );
?>
<section class="block cityline-block" data-section-num="00" aria-label="Cityline interview">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow"><span class="num">★</span> As Seen On TV</div>
      <h2>Watch Our Cityline Interview</h2>
      <p class="section-lede">Hear from the team behind <em><?php echo esc_html( get_the_title( $current ) ); ?></em>.</p>
    </div>
    <div class="cityline-video">
      <?php
      if ( $cityline_embed ) {
          echo $cityline_embed;
      } else {
          // Fallback: extract the YouTube ID and build the iframe directly
          if ( preg_match( '~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|v/|shorts/))([A-Za-z0-9_-]{6,})~', $cityline_url, $m ) ) {
              $vid = $m[1];
              echo '<iframe src="https://www.youtube.com/embed/' . esc_attr( $vid ) . '" title="Cityline interview" frameborder="0" allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
          }
      }
      ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- Current Season -->
<section class="block" data-section-num="01">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow"><span class="num">01</span> Onstage</div>
      <h2><?php echo esc_html( $season_term ? $season_term->name : '' ); ?> Season</h2>
      <?php
      // Tally counts for live progress text
      $closed = $now = $upcoming_count = 0;
      foreach ( $season_shows as $s ) {
          $st = tlt_show_status( $s->ID, false );
          if ( $st === 'closed' ) $closed++;
          elseif ( $st === 'now-playing' ) $now++;
          else $upcoming_count++;
      }
      $total = count( $season_shows );
      $progress = $total ? '<strong>' . ($closed + $now) . '</strong> of <strong>' . $total . '</strong> shows so far this season' : '';
      ?>
      <p><?php echo $progress ? wp_kses_post( $progress ) : 'A full season of mainstage productions, special events, and educational programs.'; ?></p>
    </div>
    <div class="show-scroller-wrap">
      <div class="show-scroller">
        <div class="show-scroller-track" id="seasonTrack">
        <?php
        // Render the cards twice for seamless infinite scroll
        $passes = max(1, count($season_shows) > 0 ? 2 : 1);
        for ( $pass = 0; $pass < $passes; $pass++ ) :
          foreach ( $season_shows as $show ) :
        $img = tlt_show_image_url( $show->ID, 'medium_large' );
        $open  = get_post_meta( $show->ID, 'show_open_date', true );
        $close = get_post_meta( $show->ID, 'show_close_date', true );
        $status = tlt_show_status( $show->ID, $show->ID === $first_upcoming_id );
        $status_label = [
            'closed' => 'Closed',
            'now-playing' => 'Now Playing',
            'next' => 'Up Next',
        ];
        $label = $status_label[$status] ?? '';  // upcoming = no badge
      ?>
        <a href="<?php echo esc_url( get_permalink( $show ) ); ?>" class="show-card status-<?php echo esc_attr( $status ); ?>">
          <div class="img-wrap">
            <?php if ( $img ) : ?><img src="<?php echo esc_url( $img ); ?>" alt=""><?php endif; ?>
            <?php if ( $label ) : ?>
              <span class="status-badge status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $label ); ?></span>
            <?php endif; ?>
          </div>
          <div class="body">
            <div class="dates"><?php echo esc_html( tlt_format_date_range( $open, $close ) ); ?></div>
            <h3><?php echo esc_html( get_the_title( $show ) ); ?></h3>
            <p><?php echo esc_html( wp_trim_words( get_the_excerpt( $show ), 22 ) ); ?></p>
            <span class="more"><?php
              if ( $status === 'closed' ) echo 'Photos &amp; details &rarr;';
              elseif ( $status === 'now-playing' ) echo 'Buy tickets &rarr;';
              else echo 'More info &rarr;';
            ?></span>
          </div>
        </a>
          <?php endforeach;
        endfor; ?>
        </div>
      </div>
    </div>

    <div style="text-align:center;margin-top:2rem">
      <a href="https://tlt.ludus.com/index.php" target="_blank" rel="noopener" class="btn btn-primary">Order Single Tickets Here</a>
    </div>
  </div>
</section>

<?php
/* ---------------------------------------------------------------------------
 * Promotion sections (CPT-driven). Each section auto-hides when there are no
 * active promos for that group. Chris edits these from WP admin → Promotions.
 * ------------------------------------------------------------------------- */

// Standalone promos (their own rows, no section heading). Rendered first.
$standalone_promos = function_exists( 'tlt_get_active_promotions' )
    ? tlt_get_active_promotions( 'homepage', [ 'homepage_section' => 'standalone' ] )
    : [];
if ( $standalone_promos ) : ?>
<section class="block">
  <div class="container">
    <?php foreach ( $standalone_promos as $i => $p ) tlt_render_promo( $p, $i, 'feature-row' ); ?>
  </div>
</section>
<?php endif; ?>

<?php if ( function_exists( 'tlt_render_homepage_section' ) ) : ?>
<?php tlt_render_homepage_section(
    'education', '02', 'Education', 'Programs for Every Age',
    'Classes, camps, and youth productions — explore the craft of theatre at TLT.',
    'block alt'
); ?>
<?php tlt_render_homepage_section(
    'special_events', '03', 'Beyond the Stage', 'Special Events',
    'Mystery dinners, partner restaurants, and other ways to make a night of it.',
    'block'
); ?>
<?php tlt_render_homepage_section(
    'get_involved', '04', 'Get Involved', 'Join Us',
    'Hiring, season tickets, and other ways to be part of TLT.',
    'block alt'
); ?>
<?php tlt_render_homepage_section(
    'support', '05', 'Support', 'Easy (and Free) Ways to Help TLT',
    '', 'block'
); ?>
<?php endif; ?>

<!-- Sponsors -->
<section class="block dark" data-section-num="06">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow"><span class="num">06</span> With Gratitude</div>
      <h2 style="font-size:1.3rem">Tacoma Little Theatre is honored to receive support from</h2>
    </div>
    <div style="display:flex;justify-content:center;gap:3rem;flex-wrap:wrap;align-items:center">
      <a href="https://www.tacomacreates.org/" target="_blank" rel="noopener">
        <img src="<?php echo get_template_directory_uri(); ?>/assets/sponsor-tacoma.png" alt="Tacoma Creates" style="max-height:120px;width:auto">
      </a>
      <a href="https://www.anthonys.com/restaurant/harbor-lights/" target="_blank" rel="noopener">
        <img src="<?php echo get_template_directory_uri(); ?>/assets/sponsor-harbor-lights.png" alt="Anthony's Harbor Lights" style="max-height:120px;width:auto">
      </a>
      <a href="https://www.elevationtacoma.com/" target="_blank" rel="noopener">
        <img src="<?php echo get_template_directory_uri(); ?>/assets/sponsor-ep.png" alt="Elevation Tacoma" style="max-height:120px;width:auto">
      </a>
    </div>

    <div class="section-head" style="margin-top:3rem">
      <h2 style="font-size:1.05rem;letter-spacing:0.1em">Tacoma Little Theatre is a proud member of</h2>
    </div>
    <div style="display:flex;justify-content:center;gap:3rem;flex-wrap:wrap;align-items:center">
      <a href="https://aact.org/" target="_blank" rel="noopener">
        <img src="<?php echo get_template_directory_uri(); ?>/assets/sponsor-tcg.jpg" alt="American Association of Community Theatre" style="max-height:100px;width:auto">
      </a>
      <a href="https://www.wscta.org/" target="_blank" rel="noopener">
        <img src="<?php echo get_template_directory_uri(); ?>/assets/sponsor-wscta.png" alt="Washington State Community Theatre Association" style="max-height:100px;width:auto">
      </a>
    </div>

    <div style="text-align:center;margin-top:3rem">
      <a href="https://tlt.ludus.com/subscribe.php" target="_blank" rel="noopener" class="btn btn-primary">Join Our Weekly Email List</a>
    </div>
  </div>
</section>

<style>
  .mini-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    max-width: 880px;
    margin: 0 auto;
  }
  @media (max-width: 600px) { .mini-grid { grid-template-columns: 1fr; } }
  .mini-card {
    background: #fff;
    display: grid;
    grid-template-columns: 140px 1fr;
    border: 1px solid var(--color-line);
    color: var(--color-text);
    overflow: hidden;
    transition: transform 0.15s, box-shadow 0.15s;
  }
  .mini-card:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.08); text-decoration: none; }
  .mini-card img { width: 100%; height: 100%; object-fit: cover; }
  .mini-body { padding: 0.85rem 1rem; display: flex; flex-direction: column; justify-content: center; }
  .mini-body h3 { font-size: 0.95rem; margin: 0 0 0.3rem; line-height: 1.25; }
  .mini-body p { color: var(--color-muted); font-size: 0.85rem; margin: 0; line-height: 1.4; }
  @media (max-width: 480px) {
    .mini-card { grid-template-columns: 110px 1fr; }
    .mini-body h3 { font-size: 0.85rem; }
    .mini-body p { font-size: 0.78rem; }
  }
</style>

<?php get_footer(); ?>
