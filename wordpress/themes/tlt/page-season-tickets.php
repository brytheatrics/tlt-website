<?php
/**
 * Template Name: Season Tickets
 *
 * 2026–2027 season ticket landing page. Hero + ordering CTAs + season pass
 * explainer + 7 show cards. Update the $shows array as the season changes.
 */
get_header();

// Toggle this to true once online ordering goes live in July.
$online_orders_live = false;
$online_orders_url  = 'https://tlt.ludus.com';

// Resources
$brochure_url    = '/wp-content/uploads/programs/2627-Season-Descriptions.pdf';
$order_form_url  = '/wp-content/uploads/programs/2627-Season-Ticket-Order-Form.pdf';

$hero_image      = '/wp-content/uploads/migrated/2627-season.jpg';
$season_label    = '2026–2027 Season';
$season_window   = 'August 28, 2026 – July 25, 2027';

$shows = [
    [
        'title'   => 'The Outsider',
        'author'  => 'By Paul Slade Smith',
        'dates'   => 'August 28 – September 13, 2026',
        'tagline' => 'Politics have never been this awkward… or this funny',
        'blurb'   => "Ned Newley doesn't even want to be governor. He's terrified of public speaking, and his poll numbers are impressively bad. But political consultant Arthur Vance sees things differently: Ned might be the worst candidate to ever run for office. Unless the public is looking for the worst candidate to ever run for office. A timely and hilarious comedy that skewers politics and celebrates democracy.",
    ],
    [
        'title'   => 'Arsenic and Old Lace',
        'author'  => 'By Joseph Kesserling',
        'dates'   => 'October 16 – November 1, 2026',
        'tagline' => 'Meet the sweetest little old ladies… with a deadly hobby',
        'blurb'   => "Drama critic Mortimer Brewster's engagement announcement is upended when he discovers a corpse in his elderly aunts' window seat — only to learn the two women aren't just aware of the dead man in their parlor, they killed him! Between his aunts' penchant for poisoning wine, a brother who thinks he's Teddy Roosevelt, and another brother using plastic surgery to hide from the police, it'll be a miracle if Mortimer makes it to his wedding.",
    ],
    [
        'title'   => 'Hallmarked',
        'author'  => 'By Michael D. Fox',
        'dates'   => 'December 4 – December 27, 2026',
        'tagline' => 'The West Coast premiere of a new musical for people who love Hallmark movies… and people who don\'t',
        'blurb'   => "It seems everyone on the planet is obsessed with Hallmark movies. Everyone except Julie. She had her heart stomped on once and it will not happen again. No way. No how. Not even in Idyllic, Vermont. Packed with fabulous new pop songs, loads of laughter, and heartwarming delight, Hallmarked is a rom-com fever dream.",
    ],
    [
        'title'   => 'Dot',
        'author'  => 'By Colman Domingo',
        'dates'   => 'February 5 – February 21, 2027',
        'tagline' => 'A family comedy with heart — and just a touch of heartbreak',
        'blurb'   => "The holidays are always a wild family affair at the Shealy house. This year, Dotty and her three grown children gather with more than presents on their minds. As Dotty struggles to hold on to her memory, her children must fight to balance care for their mother and care for themselves. A twisted, hilarious play set in the heart of a West Philly neighborhood.",
    ],
    [
        'title'   => 'Urinetown',
        'author'  => 'By Greg Kotis',
        'dates'   => 'March 26 – April 18, 2027',
        'tagline' => 'A wickedly funny, fast-paced, and surprisingly intelligent comedic romp',
        'blurb'   => 'In this side-splitting satire, young hero Bobby Strong leads his community in a fight against oppression. Set in a dystopian world where water is scarce and "Hope" is even scarcer, citizens must now pay for "The Privilege to Pee" at facilities controlled by a selfish tycoon. The poorest of these — run by the formidable Penelope Pennywise — becomes a "number one" site for major change.',
    ],
    [
        'title'   => 'The Importance of Being Earnest',
        'author'  => 'By Oscar Wilde · UWT Partner Project',
        'dates'   => 'May 21 – June 6, 2027',
        'tagline' => 'A trivial comedy for serious people, in partnership with UWT',
        'blurb'   => 'Being sensible can be excessively boring. At least Jack thinks so. While assuming the role of dutiful guardian in the country, he lets loose in town under a false identity. His friend Algernon takes on a similar facade. Unfortunately, double lives have drawbacks, especially in love — and especially when two eligible ladies are involved.',
    ],
    [
        'title'   => 'The Play That Goes Wrong',
        'author'  => 'By Henry Lewis, Jonathan Sayer &amp; Henry Shields',
        'dates'   => 'July 9 – July 25, 2027',
        'tagline' => 'Back by popular demand — get ready to laugh even more',
        'blurb'   => "Welcome to opening night of the Cornley University Drama Society's newest production, where things are quickly going from bad to utterly disastrous. An unconscious leading lady, a corpse that can't play dead, and actors who trip over everything (including their lines). Part Monty Python, part Sherlock Holmes — this Olivier Award–winning comedy is a global phenomenon guaranteed to leave you aching with laughter.",
    ],
];
?>

<style>
  .st-page { max-width: 1100px; margin: 0 auto; padding: 2rem var(--pad) 4rem; }

  /* HERO */
  .st-hero { display: grid; grid-template-columns: 1fr 320px; gap: 3rem; align-items: center; margin-bottom: 3rem; }
  @media (max-width: 760px) { .st-hero { grid-template-columns: 1fr; gap: 2rem; text-align: center; } }
  .st-hero__text .eyebrow { display: inline-block; background: var(--color-accent); color: #fff; font-size: 0.75rem; letter-spacing: 0.15em; text-transform: uppercase; padding: 0.3rem 0.9rem; border-radius: 999px; margin-bottom: 1rem; }
  .st-hero__text h1 { font-size: clamp(2rem, 5vw, 3rem); margin: 0 0 0.5rem; line-height: 1.1; }
  .st-hero__text .lede { font-size: 1.05rem; color: var(--color-muted); line-height: 1.6; margin: 0 0 1.5rem; }
  .st-hero__text .cta-row { display: flex; gap: 0.75rem; flex-wrap: wrap; }
  @media (max-width: 760px) { .st-hero__text .cta-row { justify-content: center; } }
  .st-page .btn-outline { border-color: var(--color-accent); color: var(--color-accent); background: transparent; }
  .st-page .btn-outline:hover { background: var(--color-accent); color: #fff; }
  .st-hero__poster img { width: 100%; height: auto; border-radius: 6px; display: block; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }

  /* Online orders notice — matches the site's accent-callout pattern */
  .st-notice { background: var(--color-soft); border-left: 4px solid var(--color-accent); border-radius: 4px; padding: 1.25rem 1.5rem; margin-bottom: 3rem; }
  .st-notice .eyebrow { display: block; color: var(--color-accent); font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.4rem; font-weight: 700; }
  .st-notice p { margin: 0; line-height: 1.6; }

  /* PASS COMPARISON */
  .st-section { margin: 0 0 3.5rem; }
  .st-section > h2 { font-size: 1.5rem; margin: 0 0 0.5rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--color-accent); display: inline-block; }
  .st-section > .intro { margin: 0 0 1.5rem; color: var(--color-muted); line-height: 1.6; max-width: 760px; }

  .pass-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
  @media (max-width: 720px) { .pass-grid { grid-template-columns: 1fr; } }
  .pass-card { background: #fff; border: 1px solid var(--color-line); border-radius: 6px; padding: 1.75rem; }
  .pass-card h3 { color: var(--color-accent); margin: 0 0 0.4rem; font-size: 1.2rem; }
  .pass-card .summary { margin: 0 0 1.25rem; color: var(--color-muted); line-height: 1.5; }
  .pass-card .price-tag { display: inline-block; background: var(--color-soft); padding: 0.35rem 0.75rem; border-radius: 999px; font-size: 0.85rem; font-weight: 600; margin-right: 0.4rem; margin-bottom: 0.4rem; }
  .pass-card .price-tag .who { color: var(--color-muted); font-weight: 400; }
  .pass-card ul { list-style: none; padding: 0; margin: 1rem 0 0; }
  .pass-card li { padding: 0.35rem 0 0.35rem 1.5rem; position: relative; font-size: 0.93rem; line-height: 1.45; }
  .pass-card li::before { content: "✓"; position: absolute; left: 0; color: var(--color-accent); font-weight: 700; }
  .pass-footnote { margin-top: 1.25rem; font-size: 0.88rem; color: var(--color-muted); text-align: center; }
  .pass-footnote a { color: var(--color-accent); }

  /* SHOW CARDS */
  .show-list { display: grid; gap: 1.25rem; }
  .show-card { display: grid; grid-template-columns: 60px 1fr; gap: 1.25rem; background: #fff; border: 1px solid var(--color-line); border-radius: 6px; padding: 1.5rem 1.75rem; transition: border-color 0.15s, box-shadow 0.15s; }
  .show-card:hover { border-color: var(--color-accent); box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
  .show-card__num { font-family: var(--font-display); font-size: 2.4rem; font-weight: 700; color: var(--color-accent); opacity: 0.3; line-height: 1; }
  .show-card__title { font-size: 1.25rem; margin: 0 0 0.15rem; }
  .show-card__author { margin: 0 0 0.5rem; font-size: 0.85rem; color: var(--color-muted); font-style: italic; }
  .show-card__dates { display: inline-block; background: var(--color-soft); color: var(--color-text); font-size: 0.8rem; font-weight: 600; padding: 0.2rem 0.6rem; border-radius: 4px; margin-bottom: 0.85rem; }
  .show-card__tagline { font-weight: 600; color: var(--color-accent); margin: 0 0 0.5rem; font-size: 0.95rem; }
  .show-card__blurb { margin: 0; line-height: 1.6; font-size: 0.95rem; }
  @media (max-width: 540px) {
    .show-card { grid-template-columns: 1fr; padding: 1.25rem 1.5rem; }
    .show-card__num { display: none; }
  }
</style>

<div class="st-page">

  <!-- HERO -->
  <header class="st-hero">
    <div class="st-hero__text">
      <span class="eyebrow"><?php echo esc_html( $season_label ); ?></span>
      <h1>Season Tickets &amp; FLEX Passes</h1>
      <p class="lede">Seven Mainstage productions. One subscription. Save per show over single ticket prices, lock in your seat for every show, or grab a FLEX Pass and use the six punches however you like.</p>
      <div class="cta-row">
        <?php if ( $online_orders_live ) : ?>
          <a class="btn btn-primary" href="<?php echo esc_url( $online_orders_url ); ?>" target="_blank" rel="noopener">Order Online</a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?php echo esc_url( $brochure_url ); ?>" target="_blank" rel="noopener">Season Brochure (PDF)</a>
        <a class="btn btn-outline" href="<?php echo esc_url( $order_form_url ); ?>" target="_blank" rel="noopener">Mail-In Order Form (PDF)</a>
      </div>
    </div>
    <div class="st-hero__poster">
      <img src="<?php echo esc_url( $hero_image ); ?>" alt="<?php echo esc_attr( $season_label . ' poster' ); ?>">
    </div>
  </header>

  <?php if ( ! $online_orders_live ) : ?>
    <div class="st-notice">
      <span class="eyebrow">Heads up</span>
      <p>Online orders open in <strong>July</strong>. Until then, use the Mail-In Order Form above, or call the Box Office at <a href="tel:+12532722281">(253) 272-2281</a>.</p>
    </div>
  <?php endif; ?>

  <!-- SEASON PASS EXPLAINER -->
  <section class="st-section">
    <h2>Choose Your Pass</h2>
    <p class="intro">Both options save you money over single tickets. Season Tickets are best if you like seeing the same seat at the same time of week every show. FLEX Passes are best if your schedule changes — or if you'd rather bring a friend than commit to dates.</p>

    <div class="pass-grid">
      <div class="pass-card">
        <h3>Season Ticket</h3>
        <p class="summary">One reserved seat to all seven Mainstage productions, same date and seat every show.</p>
        <span class="price-tag">$171.20 <span class="who">Adult</span></span>
        <span class="price-tag">$160.00 <span class="who">Senior / Student / Military</span></span>
        <span class="price-tag">$132.00 <span class="who">Child</span></span>
        <ul>
          <li>Guaranteed same seat for every show in your package</li>
          <li>Save per show over the single ticket price</li>
          <li>Free exchanges with at least 24 hours notice</li>
          <li>Valid <?php echo esc_html( $season_window ); ?></li>
        </ul>
      </div>

      <div class="pass-card">
        <h3>FLEX Pass</h3>
        <p class="summary">Six prepaid admissions you can use however you want — bring a friend, double up, save for later.</p>
        <span class="price-tag">$160.00 <span class="who">6 punches</span></span>
        <ul>
          <li>Save per show over the single ticket price</li>
          <li>Use punches in any combination — bring guests, double up</li>
          <li>Reserve at least 24 hours before each performance</li>
          <li>Valid for Mainstage only · not Special Events</li>
          <li>6 punches across 7 shows</li>
          <li>Valid <?php echo esc_html( $season_window ); ?></li>
        </ul>
      </div>
    </div>

    <p class="pass-footnote">For the full set of policies (exchanges, refunds, group rates, etc.), see <a href="/ticketinfo/">Ticket Information</a>.</p>
  </section>

  <!-- THE 7 SHOWS -->
  <section class="st-section">
    <h2>The <?php echo esc_html( $season_label ); ?></h2>
    <p class="intro">Seven Mainstage productions across the year.</p>

    <div class="show-list">
      <?php foreach ( $shows as $i => $s ) : ?>
        <article class="show-card">
          <div class="show-card__num"><?php echo $i + 1; ?></div>
          <div>
            <h3 class="show-card__title"><?php echo esc_html( $s['title'] ); ?></h3>
            <p class="show-card__author"><?php echo wp_kses( $s['author'], [ 'em' => [] ] ); ?></p>
            <span class="show-card__dates"><?php echo esc_html( $s['dates'] ); ?></span>
            <p class="show-card__tagline"><?php echo esc_html( $s['tagline'] ); ?></p>
            <p class="show-card__blurb"><?php echo esc_html( $s['blurb'] ); ?></p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

</div>

<?php get_footer(); ?>
