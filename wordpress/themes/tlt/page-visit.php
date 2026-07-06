<?php
/**
 * Template Name: Visit
 *
 * One-page "plan your visit" guide. Anchor-nav at the top, then sections:
 * directions/parking, accessibility, our venue, lobby & concessions,
 * eat & drink (Harbor Lights featured), and a few bar/drink picks.
 *
 * Content here is hand-curated; edit this template directly when info changes.
 * For the Harbor Lights perk, the canonical page is /harbor-lights/.
 */
get_header(); ?>

<style>
  .visit-page { max-width: 1100px; margin: 0 auto; padding: 2rem var(--pad) 4rem; }
  .visit-hero { text-align: center; padding: 2rem 0 1.5rem; }
  .visit-hero h1 { font-size: clamp(2rem, 5vw, 3rem); margin: 0 0 0.5rem; }
  .visit-hero .lede { font-size: 1.1rem; color: var(--color-muted); max-width: 640px; margin: 0 auto 1.25rem; }
  .visit-hero .address { font-weight: 600; }
  .visit-hero .address a { color: inherit; }

  .visit-nav { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.5rem 1.25rem; padding: 1rem; margin: 1.5rem 0 3rem; border-top: 1px solid var(--color-line); border-bottom: 1px solid var(--color-line); }
  .visit-nav a { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--color-muted); text-decoration: none; }
  .visit-nav a:hover { color: var(--color-accent); }

  .visit-section { margin: 0 0 3.5rem; scroll-margin-top: 80px; }
  .visit-section > h2 { font-size: 1.6rem; margin: 0 0 1.25rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--color-accent); display: inline-block; }
  .visit-section p, .visit-section li { line-height: 1.6; }

  .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
  @media (max-width: 720px) { .two-col { grid-template-columns: 1fr; } }

  .info-card { background: var(--color-soft); padding: 1.25rem 1.5rem; border-radius: 4px; }
  .info-card h3 { margin: 0 0 0.5rem; font-size: 1.05rem; }
  .info-card p:last-child { margin-bottom: 0; }
  .info-card ul { margin: 0.5rem 0 0; padding-left: 1.25rem; }

  .map-embed { width: 100%; aspect-ratio: 16/9; border: 0; border-radius: 4px; background: var(--color-soft); }

  .access-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; }
  .access-grid .info-card h3 { color: var(--color-accent); font-size: 0.95rem; text-transform: uppercase; letter-spacing: 0.05em; }

  .harbor-feature { display: grid; grid-template-columns: 280px 1fr; gap: 2rem; align-items: center; background: var(--color-soft); border: 1px solid var(--color-line); padding: 2rem; border-radius: 6px; margin-bottom: 2rem; }
  @media (max-width: 720px) { .harbor-feature { grid-template-columns: 1fr; text-align: center; } }
  .harbor-feature img { width: 100%; max-width: 280px; height: auto; }
  .harbor-feature .pill { display: inline-block; background: var(--color-accent); color: #fff; padding: 0.2rem 0.7rem; border-radius: 999px; font-size: 0.7rem; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 0.5rem; }
  .harbor-feature h3 { font-size: 1.4rem; margin: 0 0 0.5rem; }
  .harbor-feature .perks { margin: 0.75rem 0; }
  .harbor-feature .perks strong { display: block; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-accent); margin-top: 0.5rem; }
  .harbor-feature .actions { margin-top: 1rem; display: flex; gap: 0.75rem; flex-wrap: wrap; }
  .harbor-feature .btn-outline { border-color: var(--color-accent); color: var(--color-accent); background: transparent; }
  .harbor-feature .btn-outline:hover { background: var(--color-accent); color: #fff; }
  @media (max-width: 720px) { .harbor-feature .actions { justify-content: center; } }

  .eats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem; }
  .eats-card { position: relative; border: 1px solid var(--color-line); border-radius: 4px; padding: 1rem 1.25rem; padding-right: 6rem; transition: border-color 0.15s; }
  .eats-card:hover { border-color: var(--color-accent); }
  .eats-card h4 { margin: 0 0 0.25rem; font-size: 1rem; }
  .eats-card h4 a { color: var(--color-text); text-decoration: none; }
  .eats-card h4 a:hover { color: var(--color-accent); }
  .eats-card .meta { font-size: 0.8rem; color: var(--color-muted); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.4rem; }
  .eats-card p { margin: 0; font-size: 0.9rem; line-height: 1.5; }
  .eats-card .distance { position: absolute; top: 0.85rem; right: 0.85rem; background: var(--color-soft); color: var(--color-text); font-size: 0.7rem; font-weight: 700; padding: 0.25rem 0.55rem; border-radius: 999px; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; }
  .eats-card .distance.close { background: #e8f4ea; color: #2d6a3a; }
  .eats-card .distance.far { background: #fdf3e3; color: #8a5a1a; }

  .disclaimer { font-size: 0.8rem; color: var(--color-muted); font-style: italic; margin-top: 1.5rem; }
</style>

<?php
$vf = function ( $n, $d = '' ) { $v = function_exists( 'get_field' ) ? get_field( $n ) : null; return ( $v === null || $v === '' ) ? $d : $v; };
$v_address       = $vf( 'visit_address', '210 N "I" Street, Tacoma, WA 98403' );
$v_phone         = $vf( 'visit_phone', '(253) 272-2281' );
$v_phone_tel     = preg_replace( '/[^0-9]/', '', $v_phone );
$v_access_intro  = $vf( 'visit_access_intro' );
$v_access_cards  = function_exists( 'tlt_parse_heading_cards' ) ? tlt_parse_heading_cards( $vf( 'visit_access_cards' ) ) : [];
$v_quick_facts   = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $vf( 'visit_quick_facts' ) ) ) );
$v_seating_url   = $vf( 'visit_seating_chart_url', '/wp-content/uploads/TLT-Seating-Chart.png' );
$v_lobby_intro   = $vf( 'visit_lobby_intro' );
$v_lobby_cards   = function_exists( 'tlt_parse_heading_cards' ) ? tlt_parse_heading_cards( $vf( 'visit_lobby_cards' ) ) : [];
$v_eat_intro     = $vf( 'visit_eat_intro' );
$v_harbor_heading= $vf( 'visit_harbor_heading', 'Harbor Lights' );
$v_harbor_pill   = $vf( 'visit_harbor_pill', 'Featured Partner' );
$v_harbor_blurb  = $vf( 'visit_harbor_blurb' );
$v_harbor_perks  = $vf( 'visit_harbor_perks' );
$v_rest_intro    = $vf( 'visit_restaurants_intro' );
$v_restaurants   = function_exists( 'tlt_parse_visit_restaurants' ) ? tlt_parse_visit_restaurants( $vf( 'visit_restaurants' ) ) : [];
$v_disclaimer    = $vf( 'visit_disclaimer' );
?>

<div class="visit-page">

  <header class="visit-hero">
    <?php $_v_eb = function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'eyebrow', '' ) : ''; ?>
    <?php if ( $_v_eb ) : ?><span class="eyebrow"><?php echo esc_html( $_v_eb ); ?></span><?php endif; ?>
    <h1><?php echo esc_html( function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'title', 'Visit Tacoma Little Theatre' ) : 'Visit Tacoma Little Theatre' ); ?></h1>
    <p class="lede"><?php echo esc_html( function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'lede', "Five minutes from downtown Tacoma, tucked into the historic Stadium District — here's everything you need to plan a great night at the theatre." ) : "Five minutes from downtown Tacoma, tucked into the historic Stadium District — here's everything you need to plan a great night at the theatre." ); ?></p>
    <p class="address"><a href="https://www.google.com/maps/dir/?api=1&destination=210+N+I+Street,+Tacoma,+WA+98403" target="_blank" rel="noopener"><?php echo esc_html( $v_address ); ?></a> &middot; Box Office: <a href="tel:+<?php echo esc_attr( $v_phone_tel ); ?>"><?php echo esc_html( $v_phone ); ?></a></p>
  </header>

  <?php
  // Visit-page promotions (active promos with location=visit). Auto-hides
  // when there are no active visit promos.
  $visit_promos = function_exists( 'tlt_get_active_promotions' )
      ? tlt_get_active_promotions( 'visit' )
      : [];
  if ( $visit_promos ) :
  ?>
  <section class="visit-promos" aria-label="Featured">
    <?php foreach ( $visit_promos as $i => $p ) tlt_render_promo( $p, $i, 'feature-row' ); ?>
  </section>
  <?php endif; ?>

  <nav class="visit-nav" aria-label="On this page">
    <a href="#directions">Transportation &amp; Parking</a>
    <a href="#accessibility">Accessibility</a>
    <a href="#venue">Our Venue</a>
    <a href="#lobby">Lobby &amp; Concessions</a>
    <a href="#eat">Eat &amp; Drink</a>
  </nav>

  <!-- TRANSPORTATION & PARKING ======================================== -->
  <section class="visit-section" id="directions">
    <h2>Transportation &amp; Parking</h2>
    <div class="two-col">
      <div>
        <p>TLT sits on the corner of N "I" Street and N 2nd, one block north of Stadium High School in Tacoma's Stadium District. The Google Maps directions link below will route you from wherever you are.</p>

        <h3>Buses</h3>
        <p>Tacoma Little Theatre is located walking distance from Route 11 &amp; Route 16 on the Pierce Transit System. <strong>Stop ID: 1686.</strong></p>

        <h3>Light Rail</h3>
        <p>The Theater District / S 9th &amp; Commerce stop on the Tacoma Dome Link is roughly a 15-minute walk or 5-minute drive from the theatre.</p>

        <h3>Parking</h3>
        <p>Tacoma Little Theatre has limited parking available for Season Ticket Holders and Donors. For more information about this parking agreement, please contact the <a href="tel:+12532722281">Box Office</a>.</p>
        <p>For general parking, we recommend that you arrive 30 minutes before curtain to find parking. <strong>Yakima Avenue, 2nd, I, and J streets</strong> are popular choices.</p>

        <div class="info-card" style="margin-top:1rem; border-left: 3px solid var(--color-accent);">
          <p style="margin:0"><strong>Please note:</strong> The 7-Eleven parking lot has <strong>three</strong> marked spots for TLT patrons. Do not park in any other 7-Eleven parking spot. Also, do not park in any of the private apartment lots — they will tow your vehicle.</p>
        </div>

        <h3 style="margin-top:1.5rem">Accessible Parking</h3>
        <p>TLT has one ADA parking space in front of the building. If you require accessible parking, please <a href="tel:+12532722281">contact the Box Office</a> and if space is available, we will cone off either the spot in front or one of the spots in our parking spaces next door.</p>

        <h3>Bicycles</h3>
        <p>Tacoma Little Theatre has a bike rack located in front of the main building.</p>
      </div>
      <div>
        <iframe class="map-embed"
          src="https://www.google.com/maps?q=210+N+I+Street,+Tacoma,+WA+98403&output=embed"
          loading="lazy" title="Map to Tacoma Little Theatre" allowfullscreen></iframe>
        <p style="text-align:center; margin-top:0.5rem">
          <a href="https://www.google.com/maps/dir/?api=1&destination=210+N+I+Street,+Tacoma,+WA+98403" target="_blank" rel="noopener">Get driving directions &rarr;</a>
        </p>
      </div>
    </div>
  </section>

  <!-- ACCESSIBILITY ==================================================== -->
  <section class="visit-section" id="accessibility">
    <h2>Accessibility</h2>
    <?php if ( $v_access_intro ) : ?><p><?php echo esc_html( $v_access_intro ); ?></p><?php endif; ?>
    <div class="access-grid">
      <?php foreach ( $v_access_cards as $card ) : ?>
        <div class="info-card">
          <h3><?php echo esc_html( $card['heading'] ); ?></h3>
          <p><?php echo wp_kses_post( $card['body'] ); ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- OUR VENUE ======================================================== -->
  <section class="visit-section" id="venue">
    <h2>Our Venue</h2>
    <div class="two-col">
      <div>
        <p>Tacoma Little Theatre has been making theatre in Tacoma for more than a century — we're one of the oldest continuously operating community theatres in the country. Our home at 210 N "I" Street has been our stage since the 1940s.</p>
        <p>The auditorium seats roughly 200, with no seat more than a few rows from the action. There isn't a bad seat in the house — but if it's your first visit, the center section about ten rows back is a favorite.</p>
        <p><a href="<?php echo esc_url( $v_seating_url ); ?>" target="_blank" rel="noopener">View the seating chart &rarr;</a></p>
        <p>For more about our history, board, and staff, visit the <a href="/board-and-staff/">Board &amp; Staff</a> page.</p>
      </div>
      <div class="info-card">
        <h3>Quick Facts</h3>
        <ul>
          <?php foreach ( $v_quick_facts as $fact ) : ?><li><?php echo esc_html( $fact ); ?></li><?php endforeach; ?>
        </ul>
      </div>
    </div>
  </section>

  <!-- LOBBY & CONCESSIONS ============================================== -->
  <section class="visit-section" id="lobby">
    <h2>Lobby &amp; Concessions</h2>
    <?php if ( $v_lobby_intro ) : ?><p><?php echo esc_html( $v_lobby_intro ); ?></p><?php endif; ?>
    <div class="two-col" style="margin-top:1.25rem">
      <?php foreach ( $v_lobby_cards as $card ) : ?>
        <div class="info-card">
          <h3><?php echo esc_html( $card['heading'] ); ?></h3>
          <p><?php echo wp_kses_post( $card['body'] ); ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- EAT & DRINK ====================================================== -->
  <section class="visit-section" id="eat">
    <h2>Eat &amp; Drink Nearby</h2>
    <?php if ( $v_eat_intro ) : ?><p><?php echo esc_html( $v_eat_intro ); ?></p><?php endif; ?>

    <!-- Featured: Harbor Lights -->
    <div class="harbor-feature">
      <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/sponsor-harbor-lights.png' ); ?>" alt="Harbor Lights">
      <div>
        <span class="pill"><?php echo esc_html( $v_harbor_pill ); ?></span>
        <h3><?php echo esc_html( $v_harbor_heading ); ?></h3>
        <?php if ( $v_harbor_blurb ) : ?><p><?php echo esc_html( $v_harbor_blurb ); ?></p><?php endif; ?>
        <div class="perks"><?php echo wp_kses_post( $v_harbor_perks ); ?></div>
        <div class="actions">
          <a class="btn btn-primary" href="https://www.anthonys.com/restaurant/harbor-lights/#reservation-section" target="_blank" rel="noopener">Make a Reservation</a>
          <a class="btn btn-outline" href="/harbor-lights/">Partnership Details</a>
        </div>
      </div>
    </div>

    <h3 style="margin-top:2rem">Food &amp; Drinks</h3>
    <?php if ( $v_rest_intro ) : ?><p style="color:var(--color-muted); font-size:0.9rem"><?php echo esc_html( $v_rest_intro ); ?></p><?php endif; ?>
    <div class="eats-grid">
      <?php foreach ( $v_restaurants as $r ) :
        $tier_class = $r['tier'] === 'close' ? ' close' : ( $r['tier'] === 'far' ? ' far' : '' );
      ?>
      <div class="eats-card">
        <span class="distance<?php echo $tier_class; ?>"><?php echo esc_html( $r['dist'] ); ?></span>
        <?php if ( $r['tags'] ) : ?><div class="meta"><?php echo esc_html( $r['tags'] ); ?></div><?php endif; ?>
        <h4><?php if ( $r['url'] ) : ?><a href="<?php echo esc_url( $r['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $r['name'] ); ?></a><?php else : echo esc_html( $r['name'] ); endif; ?></h4>
        <?php if ( $r['blurb'] ) : ?><p><?php echo esc_html( $r['blurb'] ); ?></p><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ( $v_disclaimer ) : ?><p class="disclaimer"><?php echo esc_html( $v_disclaimer ); ?></p><?php endif; ?>
  </section>

</div>

<?php get_footer(); ?>
