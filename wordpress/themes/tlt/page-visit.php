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

<div class="visit-page">

  <header class="visit-hero">
    <?php $_v_eb = function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'eyebrow', '' ) : ''; ?>
    <?php if ( $_v_eb ) : ?><span class="eyebrow"><?php echo esc_html( $_v_eb ); ?></span><?php endif; ?>
    <h1><?php echo esc_html( function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'title', 'Visit Tacoma Little Theatre' ) : 'Visit Tacoma Little Theatre' ); ?></h1>
    <p class="lede"><?php echo esc_html( function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'lede', "Five minutes from downtown Tacoma, tucked into the historic Stadium District — here's everything you need to plan a great night at the theatre." ) : "Five minutes from downtown Tacoma, tucked into the historic Stadium District — here's everything you need to plan a great night at the theatre." ); ?></p>
    <p class="address"><a href="https://www.google.com/maps/dir/?api=1&destination=210+N+I+Street,+Tacoma,+WA+98403" target="_blank" rel="noopener">210 N "I" Street, Tacoma, WA 98403</a> &middot; Box Office: <a href="tel:+12532722281">(253) 272-2281</a></p>
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
    <p>We want every patron to have a comfortable, dignified experience at TLT. If you have a question or accommodation request, please call the box office at <a href="tel:+12532722281">(253) 272-2281</a> at least 48 hours before your performance and we'll do our best to help.</p>
    <div class="access-grid">
      <div class="info-card">
        <h3>Wheelchair Seating</h3>
        <p>Designated wheelchair-accessible seats are available in the back row of the auditorium. Companion seating is provided adjacent. Please request these seats when booking so we can hold them for you.</p>
      </div>
      <div class="info-card">
        <h3>Hearing Assistance</h3>
        <p>Assistive listening devices are available from the box office at no charge — just ask when you arrive. Please bring photo ID to check one out.</p>
      </div>
      <div class="info-card">
        <h3>Restrooms</h3>
        <p>Two ADA-accessible restrooms are located in the lobby. There are no stairs.</p>
      </div>
      <div class="info-card">
        <h3>Service Animals</h3>
        <p>Service animals are welcome at all TLT performances. Please let the box office know in advance if possible.</p>
      </div>
      <div class="info-card">
        <h3>Fragrance Sensitivity</h3>
        <p>We ask all patrons to refrain from wearing strong fragrances out of consideration for those with chemical sensitivities.</p>
      </div>
      <div class="info-card">
        <h3>Content Advisories</h3>
        <p>Show-specific content advisories (language, themes, stage effects such as fog or strobe) are posted on each show's page. Call the box office if you'd like more detail before booking.</p>
      </div>
    </div>
  </section>

  <!-- OUR VENUE ======================================================== -->
  <section class="visit-section" id="venue">
    <h2>Our Venue</h2>
    <div class="two-col">
      <div>
        <p>Tacoma Little Theatre has been making theatre in Tacoma for more than a century — we're one of the oldest continuously operating community theatres in the country. Our home at 210 N "I" Street has been our stage since the 1940s.</p>
        <p>The auditorium seats roughly 200, with no seat more than a few rows from the action. There isn't a bad seat in the house — but if it's your first visit, the center section about ten rows back is a favorite.</p>
        <p><a href="/wp-content/uploads/TLT-Seating-Chart.png" target="_blank" rel="noopener">View the seating chart &rarr;</a></p>
        <p>For more about our history, board, and staff, visit the <a href="/board-and-staff/">Board &amp; Staff</a> page.</p>
      </div>
      <div class="info-card">
        <h3>Quick Facts</h3>
        <ul>
          <li>~200 seats, single auditorium</li>
          <li>Founded 1918 &middot; building occupied since 1940s</li>
          <li>Year-round Mainstage and Off the Shelf seasons</li>
          <li>Education programs for ages 6 through adult</li>
          <li>501(c)(3) nonprofit &middot; Federal ID 91-0485763</li>
        </ul>
      </div>
    </div>
  </section>

  <!-- LOBBY & CONCESSIONS ============================================== -->
  <section class="visit-section" id="lobby">
    <h2>Lobby &amp; Concessions</h2>
    <p>Doors open about 30 minutes before curtain. Our concessions bar serves wine, beer, soft drinks, water, coffee, and a rotating selection of snacks and treats. All proceeds from concessions support TLT's productions and education programs.</p>
    <div class="two-col" style="margin-top:1.25rem">
      <div class="info-card">
        <h3>Before the Show</h3>
        <p>Arrive early to browse the lobby, peek at the cast bios, grab a drink, and settle in before the show.</p>
      </div>
      <div class="info-card">
        <h3>At Intermission</h3>
        <p>Most performances include one 15-minute intermission. Drinks and snacks are available again — we appreciate cash and cards equally.</p>
      </div>
    </div>
  </section>

  <!-- EAT & DRINK ====================================================== -->
  <section class="visit-section" id="eat">
    <h2>Eat &amp; Drink Nearby</h2>
    <p>Make a night of it. The Stadium District is walkable, with restaurants and bars five minutes from the theatre door.</p>

    <!-- Featured: Harbor Lights -->
    <div class="harbor-feature">
      <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/sponsor-harbor-lights.png' ); ?>" alt="Harbor Lights">
      <div>
        <span class="pill">Featured Partner</span>
        <h3>Harbor Lights</h3>
        <p>Tacoma waterfront landmark since 1959. We're proud to partner with Harbor Lights for our patrons.</p>
        <div class="perks">
          <strong>All Patrons</strong>
          Access to the 3-Course Sunset Dinner menu any day of the week before or after a performance. Just show your ticket.
          <strong>Season &amp; Flex Pass Holders</strong>
          One complimentary appetizer or dessert with every performance.
        </div>
        <div class="actions">
          <a class="btn btn-primary" href="https://www.anthonys.com/restaurant/harbor-lights/#reservation-section" target="_blank" rel="noopener">Make a Reservation</a>
          <a class="btn btn-outline" href="/harbor-lights/">Partnership Details</a>
        </div>
      </div>
    </div>

    <h3 style="margin-top:2rem">Food &amp; Drinks</h3>
    <p style="color:var(--color-muted); font-size:0.9rem">A few of our favorite Stadium District spots within easy walking distance. We're not affiliated with any of these — just good neighbors.</p>
    <div class="eats-grid">

      <div class="eats-card">
        <span class="distance close">0.1 mi</span>
        <div class="meta">Craft Beer &middot; Pub Food</div>
        <h4><a href="https://www.parkwaytavern.com/" target="_blank" rel="noopener">Parkway Tavern</a></h4>
        <p>Tacoma craft-beer institution literally a block away. Big rotating tap list, gourmet burgers, and sandwiches. 313 N I St.</p>
      </div>

      <div class="eats-card">
        <span class="distance close">0.3 mi</span>
        <div class="meta">Pizza &middot; Bar</div>
        <h4><a href="https://www.hankstacoma.com/" target="_blank" rel="noopener">Hank's Bar and Pizza</a></h4>
        <p>Neighborhood pizza-and-beer joint, easy walk from the theatre. Pies, salads, and a solid beer list. 524 N K St.</p>
      </div>

      <div class="eats-card">
        <span class="distance close">0.3 mi</span>
        <div class="meta">Burgers &middot; Retro</div>
        <h4><a href="https://shakeshakeshake.me/" target="_blank" rel="noopener">Shake Shake Shake</a></h4>
        <p>Old-school diner-style burgers, hand-pressed and voted Best in Tacoma. Plus 25+ craft milkshakes. 124 N Tacoma Ave.</p>
      </div>

      <div class="eats-card">
        <span class="distance close">0.3 mi</span>
        <div class="meta">Southeast Asian</div>
        <h4><a href="https://indostreeteatery.com/" target="_blank" rel="noopener">Indo Asian Street Eatery</a></h4>
        <p>Pan-Asian street food and craft cocktails — bao, dumplings, satays, rice bowls — set in the historic Stadium District. 110 N Tacoma Ave.</p>
      </div>

      <div class="eats-card">
        <span class="distance close">0.3 mi</span>
        <div class="meta">Thai &middot; Noodles</div>
        <h4><a href="https://sappsapptacoma.com/" target="_blank" rel="noopener">Sapp Sapp Thai Noodle House</a></h4>
        <p>Newer Thai noodle spot on Tacoma Ave. Boat noodles, curries, stir-fries, and cocktails. 110 N Tacoma Ave (Suite B).</p>
      </div>

      <div class="eats-card">
        <span class="distance close">0.4 mi</span>
        <div class="meta">Pizza &middot; Italian</div>
        <h4><a href="https://salamonespizzeria.com/" target="_blank" rel="noopener">Salamone's Pizza</a></h4>
        <p>New York–style pizza by the slice or whole pie, plus Italian standards. Great if you're feeding a group on the way in. 24 N Tacoma Ave.</p>
      </div>

      <div class="eats-card">
        <span class="distance">0.5 mi</span>
        <div class="meta">Italian-Inspired &middot; Scratch Kitchen</div>
        <h4><a href="https://manuscripttacoma.com/" target="_blank" rel="noopener">Manuscript</a></h4>
        <p>Italian-inspired fusion in a lively, vinyl-DJ atmosphere — in the former Hub space. Weekend brunch too. 203 Tacoma Ave S.</p>
      </div>

      <div class="eats-card">
        <span class="distance">0.6 mi</span>
        <div class="meta">French Bistro</div>
        <h4><a href="https://www.leselbistro.com/" target="_blank" rel="noopener">Le Sel Bistro</a></h4>
        <p>Classic French bistro fare in a small, intimate room. Steak frites, mussels, well-curated wine list. Reservations a good idea. 229 St Helens Ave.</p>
      </div>

      <div class="eats-card">
        <span class="distance">0.6 mi</span>
        <div class="meta">Irish Pub</div>
        <h4><a href="https://www.doylespublichouse.com/" target="_blank" rel="noopener">Doyle's Public House</a></h4>
        <p>Cozy Irish pub a few blocks south. Whiskeys, Guinness on draft, and pub food until late. 208 St Helens Ave.</p>
      </div>

      <div class="eats-card">
        <span class="distance">0.7 mi</span>
        <div class="meta">Ramen &middot; Sushi Burritos</div>
        <h4><a href="https://www.zenramensushiburrito.com/" target="_blank" rel="noopener">Zen Ramen &amp; Sushi Burrito</a></h4>
        <p>Ramen, sushi burritos, poke, and rice bowls. Fast and reliable for a quick pre-show meal. 322 Tacoma Ave S.</p>
      </div>

      <div class="eats-card">
        <span class="distance far">0.9 mi</span>
        <div class="meta">Tacos &middot; Tequila</div>
        <h4><a href="https://www.redstartacobar.com/tacoma" target="_blank" rel="noopener">Red Star Taco Bar</a></h4>
        <p>Tacos, tequila flights, and margaritas in a lively bar setting. A bit further down St Helens — a good after-show stop. 454 St Helens Ave.</p>
      </div>

      <div class="eats-card">
        <span class="distance">0.7 mi</span>
        <div class="meta">Burgers &middot; Drive-In</div>
        <h4><a href="https://friskofreeze.com/" target="_blank" rel="noopener">Frisko Freeze</a></h4>
        <p>Tacoma landmark since 1950. Burgers, fries, and shakes from the drive-thru or walk-up window — pure retro Americana. 1201 Division Ave.</p>
      </div>

    </div>

    <p class="disclaimer">Restaurant and bar hours change. Please call ahead or check online before you head out.</p>
  </section>

</div>

<?php get_footer(); ?>
