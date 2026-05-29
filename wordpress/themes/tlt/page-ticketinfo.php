<?php
/**
 * Template Name: Ticket Information
 *
 * Pricing, season tickets, flex passes, and policies. Hardcoded in the
 * template so wpautop can't mangle the structured layout. To update
 * prices/policies, edit this file directly.
 */
get_header(); ?>

<style>
  .ti-page { max-width: 1100px; margin: 0 auto; padding: 2rem var(--pad) 4rem; }

  .ti-hero { text-align: center; padding: 2rem 0 1.5rem; }
  .ti-hero .eyebrow { display: inline-block; background: var(--color-accent); color: #fff; font-size: 0.75rem; letter-spacing: 0.15em; text-transform: uppercase; padding: 0.3rem 0.9rem; border-radius: 999px; margin-bottom: 1rem; }
  .ti-hero h1 { font-size: clamp(2rem, 5vw, 3rem); margin: 0 0 0.5rem; }
  .ti-hero .lede { font-size: 1.05rem; color: var(--color-muted); max-width: 640px; margin: 0 auto 1.5rem; }
  .ti-hero .cta-row { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; }

  .ti-section { margin: 3rem 0 0; }
  .ti-section > h2 { font-size: 1.6rem; margin: 0 0 1.25rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--color-accent); display: inline-block; }

  /* Pricing cards: musicals + plays side-by-side */
  .pricing-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
  @media (max-width: 720px) { .pricing-grid { grid-template-columns: 1fr; } }
  .pricing-card { border: 1px solid var(--color-line); border-radius: 6px; overflow: hidden; }
  .pricing-card__head { background: var(--color-accent); color: #fff; padding: 1rem 1.5rem; }
  .pricing-card__head h3 { margin: 0; font-size: 1.1rem; letter-spacing: 0.05em; text-transform: uppercase; }
  .pricing-card__body { padding: 1.25rem 1.5rem; }
  .price-row { display: flex; justify-content: space-between; align-items: baseline; padding: 0.5rem 0; border-bottom: 1px dashed var(--color-line); }
  .price-row:last-of-type { border-bottom: 0; }
  .price-row .who { font-size: 0.95rem; }
  .price-row .price { font-weight: 700; font-size: 1.05rem; font-family: var(--font-display); color: var(--color-accent); }
  .pricing-card .group-note { background: var(--color-soft); padding: 0.75rem 1rem; margin: 1rem -1.5rem -1.25rem; font-size: 0.85rem; }
  .pricing-card .group-note strong { display: block; color: var(--color-accent); text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.7rem; margin-bottom: 0.25rem; }
  .pricing-card .group-note em { color: var(--color-muted); font-size: 0.78rem; }

  /* Mini info card grid (Pay What You Can, CC fees, Gift Cards) */
  .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
  .info-card { background: var(--color-soft); padding: 1.25rem 1.5rem; border-radius: 6px; }
  .info-card h3 { margin: 0 0 0.5rem; font-size: 0.85rem; color: var(--color-accent); text-transform: uppercase; letter-spacing: 0.06em; }
  .info-card p { margin: 0; font-size: 0.92rem; line-height: 1.55; }
  .info-card p + p { margin-top: 0.5rem; }

  /* Season ticket / flex pass comparison */
  .compare-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
  @media (max-width: 720px) { .compare-grid { grid-template-columns: 1fr; } }
  .compare-card { background: #fff; border: 1px solid var(--color-line); border-radius: 6px; padding: 1.5rem; }
  .compare-card h3 { color: var(--color-accent); margin: 0 0 0.4rem; font-size: 1.15rem; }
  .compare-card .summary { margin: 0 0 1rem; color: var(--color-muted); font-size: 0.92rem; }
  .compare-card ul { list-style: none; padding: 0; margin: 0; }
  .compare-card li { padding: 0.4rem 0 0.4rem 1.5rem; position: relative; font-size: 0.95rem; line-height: 1.45; }
  .compare-card li::before { content: "✓"; position: absolute; left: 0; color: var(--color-accent); font-weight: 700; }
  .compare-card .price-tag { display: inline-block; background: var(--color-soft); padding: 0.35rem 0.75rem; border-radius: 999px; font-size: 0.85rem; font-weight: 600; margin-right: 0.4rem; margin-bottom: 0.4rem; }
  .compare-card .price-tag .who { color: var(--color-muted); font-weight: 400; }

  .subscribe-cta { background: linear-gradient(135deg, var(--color-soft) 0%, #fff 100%); border: 1px solid var(--color-line); border-radius: 6px; padding: 2rem; text-align: center; margin-top: 1.5rem; }
  .subscribe-cta h3 { margin: 0 0 0.5rem; }
  .subscribe-cta p { margin: 0 0 1.25rem; color: var(--color-muted); font-size: 0.95rem; }
  .subscribe-cta .mail-address { display: inline-block; background: #fff; padding: 0.75rem 1rem; border-radius: 4px; font-size: 0.88rem; line-height: 1.4; border: 1px dashed var(--color-line); margin-top: 0.75rem; }

  /* Policies — accordion-like cards */
  .policy-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; }
  .policy-card { background: #fff; border: 1px solid var(--color-line); border-radius: 6px; padding: 1.25rem 1.5rem; }
  .policy-card h3 { margin: 0 0 0.5rem; font-size: 0.85rem; color: var(--color-accent); text-transform: uppercase; letter-spacing: 0.06em; }
  .policy-card p { margin: 0; font-size: 0.92rem; line-height: 1.55; }
  .policy-card p + p { margin-top: 0.5rem; }
  .policy-card ul { margin: 0.4rem 0 0; padding-left: 1.25rem; font-size: 0.92rem; line-height: 1.55; }
</style>

<div class="ti-page">

  <header class="ti-hero">
    <span class="eyebrow">Ticket Information</span>
    <h1><?php the_title(); ?></h1>
    <p class="lede">Everything you need to know about ticket prices, season passes, and the few house rules we have in place to keep everyone comfortable.</p>
    <div class="cta-row">
      <a class="btn btn-primary" href="https://tlt.ludus.com" target="_blank" rel="noopener">Buy Tickets</a>
      <a class="btn btn-outline" href="/season-tickets/">Season Tickets</a>
    </div>
  </header>

  <!-- PRICING ============================================ -->
  <section class="ti-section" id="pricing">
    <h2>Single Ticket Pricing</h2>

    <div class="pricing-grid">
      <div class="pricing-card">
        <div class="pricing-card__head"><h3>Musicals</h3></div>
        <div class="pricing-card__body">
          <div class="price-row"><span class="who">Adult</span><span class="price">$32.00</span></div>
          <div class="price-row"><span class="who">Senior (60+) / Student / Military</span><span class="price">$30.00</span></div>
          <div class="price-row"><span class="who">Child (12 and under)</span><span class="price">$25.00</span></div>
          <div class="group-note">
            <strong>Group Rates</strong>
            10–24 tickets: $26.00 &middot; 25+ tickets: $25.00<br>
            <em>Group rates available through the Box Office only.</em>
          </div>
        </div>
      </div>

      <div class="pricing-card">
        <div class="pricing-card__head"><h3>Plays</h3></div>
        <div class="pricing-card__body">
          <div class="price-row"><span class="who">Adult</span><span class="price">$30.00</span></div>
          <div class="price-row"><span class="who">Senior (60+) / Student / Military</span><span class="price">$28.00</span></div>
          <div class="price-row"><span class="who">Child (12 and under)</span><span class="price">$23.00</span></div>
          <div class="group-note">
            <strong>Group Rates</strong>
            10–24 tickets: $24.00 &middot; 25+ tickets: $23.00<br>
            <em>Group rates available through the Box Office only.</em>
          </div>
        </div>
      </div>
    </div>

    <div class="info-grid" style="margin-top:1.5rem">
      <div class="info-card">
        <h3>Pay What You Can</h3>
        <p>PWYC performances are typically held on the third Thursday of a show's run. Suggested minimum donation is <strong>$5.00</strong>. Available in person, over the phone, or online.</p>
      </div>
      <div class="info-card">
        <h3>Card Transaction Fees</h3>
        <p>Credit/debit card orders carry a <strong>5% convenience fee + $0.85 per ticket/pass</strong>. No transaction fees for cash or check.</p>
      </div>
      <div class="info-card">
        <h3>Gift Cards</h3>
        <p>Available in any amount online, by phone, or in person. Redeemable for tickets, Season Tickets, FLEX Passes, and class enrollments. Not currently usable on concessions.</p>
      </div>
    </div>
  </section>

  <!-- SEASON TICKETS & FLEX PASS ============================================ -->
  <section class="ti-section" id="season">
    <h2>Season Tickets &amp; Flex Passes</h2>
    <p style="max-width: 760px; line-height: 1.6;">Season tickets offer the same seat and date of your choice for all <strong>seven</strong> Main Stage shows. Flex passes are <strong>six</strong> admissions that can be used on any Main Stage show with advance reservations. Both options save you money over single tickets.</p>

    <div class="compare-grid">
      <div class="compare-card">
        <h3>Season Ticket</h3>
        <p class="summary">One seat to all seven regular Main Stage productions, same date and seat every show.</p>
        <span class="price-tag">$171.20 <span class="who">Adult</span></span>
        <span class="price-tag">$160.00 <span class="who">Senior / Student / Military</span></span>
        <span class="price-tag">$132.00 <span class="who">Child</span></span>
        <ul>
          <li>Guaranteed same seat for every show in your package</li>
          <li>Save per show over the single-ticket price</li>
          <li>Free exchanges with at least 24 hours notice</li>
          <li>Valid only for the season purchased</li>
          <li>Does not include Special Events</li>
        </ul>
      </div>

      <div class="compare-card">
        <h3>FLEX Pass</h3>
        <p class="summary">Six prepaid admissions you can use any way you want — bring a friend, double up, save for later.</p>
        <span class="price-tag">$160.00 <span class="who">6 punches</span></span>
        <ul>
          <li>Save per show over the single-ticket price</li>
          <li>Use punches in any combination (bring a friend!)</li>
          <li>Reserve at least 24 hours before the performance</li>
          <li>Many shows sell out — reserve 2+ weeks ahead when possible</li>
          <li>Valid for Main Stage and Second Stage productions only</li>
          <li>Not valid for Special Events &middot; 6 punches but 7 shows in a season</li>
        </ul>
      </div>
    </div>

    <div class="subscribe-cta">
      <h3>Ready to Subscribe?</h3>
      <p>Subscribe online, give us a call at <a href="tel:+12532722281">(253) 272-2281</a>, or mail an order form to:</p>
      <div class="mail-address">
        Tacoma Little Theatre<br>
        210 N "I" Street<br>
        Tacoma, WA 98403
      </div>
      <div style="margin-top:1.25rem">
        <a class="btn btn-primary" href="/season-tickets/">Subscribe Online</a>
      </div>
    </div>
  </section>

  <!-- GENERAL POLICIES ============================================ -->
  <section class="ti-section" id="policies">
    <h2>General Policies</h2>

    <div class="policy-grid">
      <div class="policy-card">
        <h3>Lost Tickets</h3>
        <p>Call the Box Office and we can reprint them for you. Reprints will be held at WILL CALL under your last name on the date of the performance.</p>
      </div>

      <div class="policy-card">
        <h3>Ticket Sales</h3>
        <ul>
          <li>All sales final &mdash; no refunds, but we offer exchanges</li>
          <li>Murder Mystery Dinners must be exchanged at least 5 days in advance</li>
          <li>Online orders require a credit card and receive email confirmation</li>
          <li>Added donations are charged together with your order</li>
        </ul>
      </div>

      <div class="policy-card">
        <h3>Babes in Arms</h3>
        <p>For the comfort of other patrons, no babes in arms during our productions.</p>
      </div>

      <div class="policy-card">
        <h3>Accessible Seating</h3>
        <p>Wheelchair-accessible seating is available for every performance. Call the Box Office to arrange seating and confirm availability.</p>
      </div>

      <div class="policy-card">
        <h3>Cameras &amp; Devices</h3>
        <p>For the safety and comfort of the actors and audience, no cameras or recording devices. Please silence phones, watches, and any noise-making electronics before the show starts.</p>
      </div>

      <div class="policy-card">
        <h3>Late Seating</h3>
        <p>If you arrive after the show has started, you'll be seated at the back at the House Manager's discretion until intermission. Unclaimed seats may be released 15 minutes after curtain.</p>
      </div>

      <div class="policy-card">
        <h3>Concessions</h3>
        <p>Beer, wine, cocktails, soft drinks, coffee, tea, and snacks are available before the show and at intermission.</p>
      </div>

      <div class="policy-card">
        <h3>Coat Check</h3>
        <p>A self-serve coat check is located inside the auditorium. TLT is not responsible for lost or stolen articles.</p>
      </div>

      <div class="policy-card">
        <h3>Weather</h3>
        <p>All performances take place as scheduled, regardless of weather. Performances may only be cancelled in the event of a complete power outage.</p>
      </div>

      <div class="policy-card">
        <h3>Right to Refuse Service</h3>
        <p>Tacoma Little Theatre reserves the right to refuse service.</p>
      </div>
    </div>
  </section>

  <!-- SEASON TICKET & FLEX PASS POLICIES ============================================ -->
  <section class="ti-section" id="subscriber-policies">
    <h2>Season Ticket &amp; FLEX Pass Policies</h2>

    <div class="policy-grid">
      <div class="policy-card">
        <h3>Season Ticket Exchanges</h3>
        <p>If necessary, you can exchange your Season Ticket by calling the Box Office at <a href="tel:+12532722281">(253) 272-2281</a> at least 24 hours in advance.</p>
        <p>Season Tickets are only valid for the season for which they are purchased.</p>
      </div>

      <div class="policy-card">
        <h3>FLEX Pass Reservations</h3>
        <p>Reserve seats by calling the Box Office at least 24 hours before the performance.</p>
        <p>Once reservations are made, there are no refunds &mdash; but we offer free exchanges.</p>
      </div>

      <div class="policy-card">
        <h3>FLEX Pass Use</h3>
        <p>FLEX Passes are valid for Main Stage and Second Stage productions. They are not valid for Special Events.</p>
        <p>Each pass has 6 punches; use them however you prefer (including bringing guests). FLEX passes are only valid for the season in which they are purchased.</p>
      </div>
    </div>
  </section>

</div>

<?php get_footer(); ?>
