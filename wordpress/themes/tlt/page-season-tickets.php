<?php
/**
 * Template Name: Season Tickets
 *
 * Self-maintaining season ticket landing page. The 7 show cards auto-populate
 * from the current season's Mainstage shows; the season label + date window are
 * derived from those shows. Operational bits (online-ordering toggle, PDF links,
 * hero image, prices, pass wording) are editable via the "Season Tickets
 * Settings" ACF fields, with the real current copy as defaults.
 */
get_header();

// Small ACF reader with a baked-in default so the page renders unchanged.
// Falls through to raw post_meta if ACF returns nothing/false — that lets
// old plain-URL string values (from before st_brochure_url/st_order_form_url
// switched from text to file field) keep working while Chris hasn't re-selected.
$stf = function ( $name, $default = '' ) {
    $v = function_exists( 'get_field' ) ? get_field( $name ) : null;
    if ( $v === null || $v === '' || $v === false ) {
        $v = get_post_meta( get_the_ID(), $name, true );
    }
    return ( $v === null || $v === '' || $v === false ) ? $default : $v;
};

// --- Operational settings (editable) ---
$online_orders_live = (bool) ( function_exists( 'get_field' ) ? get_field( 'st_online_live' ) : false );
$online_orders_url  = $stf( 'st_online_url', 'https://tlt.ludus.com' );
$brochure_url       = $stf( 'st_brochure_url', '/wp-content/uploads/programs/2627-Season-Descriptions.pdf' );
$order_form_url     = $stf( 'st_order_form_url', '/wp-content/uploads/programs/2627-Season-Ticket-Order-Form.pdf' );
// --- Shows + season label/window auto-derived. Season tickets are always
// for the UPCOMING season, so we prefer next-season data when it exists and
// only fall back to the current season if nothing is announced yet.
$next_term    = function_exists( 'tlt_get_next_season_term' )    ? tlt_get_next_season_term()    : null;
$next_shows   = function_exists( 'tlt_get_next_season_shows' )   ? tlt_get_next_season_shows()   : [];
$season_term  = $next_term
    ?: ( function_exists( 'tlt_get_current_season_term' ) ? tlt_get_current_season_term() : null );
$season_shows = $next_shows
    ?: ( function_exists( 'tlt_get_current_season_shows' ) ? tlt_get_current_season_shows() : [] );
$season_label = $season_term ? str_replace( '-', "\u{2013}", $season_term->name ) . ' Season' : '2026–2027 Season';

// Hero image — no fallback; if Chris hasn't uploaded one, the poster slot
// stays empty rather than showing a stale prior-season default.
$hero_image = function_exists( 'get_field' ) ? get_field( 'st_hero_image' ) : '';
$shows = [];
$first_open = $last_close = '';
foreach ( $season_shows as $sp ) {
    $open  = get_post_meta( $sp->ID, 'show_open_date', true );
    $close = get_post_meta( $sp->ID, 'show_close_date', true );
    if ( $open  && ( ! $first_open || $open  < $first_open ) ) $first_open = $open;
    if ( $close && ( ! $last_close || $close > $last_close ) ) $last_close = $close;
    $shows[] = [
        'title'   => get_the_title( $sp ),
        'author'  => get_post_meta( $sp->ID, 'show_playwright', true ),
        'dates'   => function_exists( 'tlt_format_date_range' ) ? tlt_format_date_range( $open, $close ) : '',
        'tagline' => get_post_meta( $sp->ID, 'show_tagline', true ),
        'blurb'   => wp_strip_all_tags( $sp->post_content ),
    ];
}
$season_window = ( $first_open && $last_close && function_exists( 'tlt_format_date_range' ) )
    ? tlt_format_date_range( $first_open, $last_close )
    : 'August 28, 2026 – July 25, 2027';

// --- Pass content (editable) ---
$pass_intro     = $stf( 'st_pass_intro', "Both options save you money over single tickets. Season Tickets are best if you like seeing the same seat at the same time of week every show. FLEX Passes are best if your schedule changes — or if you'd rather bring a friend than commit to dates." );
$season_summary = $stf( 'st_season_summary', 'One reserved seat to all seven Mainstage productions, same date and seat every show.' );
$season_prices  = $stf( 'st_season_prices', "\$171.20 | Adult\n\$160.00 | Senior / Student / Military\n\$132.00 | Child" );
$season_bullets = $stf( 'st_season_bullets', "Guaranteed same seat for every show in your package\nSave per show over the single ticket price\nFree exchanges with at least 24 hours notice" );
$flex_summary   = $stf( 'st_flex_summary', 'Six prepaid admissions you can use however you want — bring a friend, double up, save for later.' );
$flex_price     = $stf( 'st_flex_price', '$160.00 | 6 punches' );
$flex_bullets   = $stf( 'st_flex_bullets', "Save per show over the single ticket price\nUse punches in any combination — bring guests, double up\nReserve at least 24 hours before each performance\nValid for Mainstage only · not Special Events\n6 punches across 7 shows" );

$render_price_tags = function ( $text ) {
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
        $line = trim( $line ); if ( $line === '' ) continue;
        $p = array_map( 'trim', explode( '|', $line, 2 ) );
        echo '<span class="price-tag">' . esc_html( $p[0] );
        if ( ! empty( $p[1] ) ) echo ' <span class="who">' . esc_html( $p[1] ) . '</span>';
        echo '</span>';
    }
};
$render_bullets = function ( $text ) {
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
        $line = trim( $line ); if ( $line === '' ) continue;
        echo '<li>' . esc_html( $line ) . '</li>';
    }
};
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
      <?php $_st_eb = function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'eyebrow', $season_label ) : $season_label; ?>
      <?php if ( $_st_eb ) : ?><span class="eyebrow"><?php echo esc_html( $_st_eb ); ?></span><?php endif; ?>
      <h1><?php echo wp_kses_post( function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'title', 'Season Tickets &amp; FLEX Passes' ) : 'Season Tickets &amp; FLEX Passes' ); ?></h1>
      <p class="lede"><?php echo esc_html( function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'lede', 'Seven Mainstage productions. One subscription. Save per show over single ticket prices, lock in your seat for every show, or grab a FLEX Pass and use the six punches however you like.' ) : 'Seven Mainstage productions. One subscription. Save per show over single ticket prices, lock in your seat for every show, or grab a FLEX Pass and use the six punches however you like.' ); ?></p>
      <div class="cta-row">
        <?php if ( $online_orders_live ) : ?>
          <a class="btn btn-primary" href="<?php echo esc_url( $online_orders_url ); ?>" target="_blank" rel="noopener">Order Online</a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?php echo esc_url( $brochure_url ); ?>" target="_blank" rel="noopener">Season Brochure (PDF)</a>
        <a class="btn btn-outline" href="<?php echo esc_url( $order_form_url ); ?>" target="_blank" rel="noopener">Mail-In Order Form (PDF)</a>
      </div>
    </div>
    <?php if ( $hero_image ) : ?>
      <div class="st-hero__poster">
        <img src="<?php echo esc_url( $hero_image ); ?>" alt="<?php echo esc_attr( $season_label . ' poster' ); ?>">
      </div>
    <?php endif; ?>
  </header>

  <?php if ( ! $online_orders_live ) : ?>
    <div class="st-notice">
      <span class="eyebrow">Heads up</span>
      <p>Online orders open in <strong>August</strong>. Until then, use the Mail-In Order Form above, or call the Box Office at <a href="tel:+12532722281">(253) 272-2281</a>.</p>
    </div>
  <?php endif; ?>

  <!-- SEASON PASS EXPLAINER -->
  <section class="st-section">
    <h2>Choose Your Pass</h2>
    <p class="intro"><?php echo esc_html( $pass_intro ); ?></p>

    <div class="pass-grid">
      <div class="pass-card">
        <h3>Season Ticket</h3>
        <p class="summary"><?php echo esc_html( $season_summary ); ?></p>
        <?php $render_price_tags( $season_prices ); ?>
        <ul>
          <?php $render_bullets( $season_bullets ); ?>
          <li>Valid <?php echo esc_html( $season_window ); ?></li>
        </ul>
      </div>

      <div class="pass-card">
        <h3>FLEX Pass</h3>
        <p class="summary"><?php echo esc_html( $flex_summary ); ?></p>
        <?php $render_price_tags( $flex_price ); ?>
        <ul>
          <?php $render_bullets( $flex_bullets ); ?>
          <li>Valid <?php echo esc_html( $season_window ); ?></li>
        </ul>
      </div>
    </div>

    <p class="pass-footnote">For the full set of policies (exchanges, refunds, group rates, etc.), see <a href="/tickets/">Ticket Information</a>.</p>
  </section>

  <!-- THE SHOWS -->
  <section class="st-section">
    <h2>The <?php echo esc_html( $season_label ); ?></h2>
    <p class="intro">Seven Mainstage productions across the year.</p>

    <div class="show-list">
      <?php foreach ( $shows as $i => $s ) : ?>
        <article class="show-card">
          <div class="show-card__num"><?php echo $i + 1; ?></div>
          <div>
            <h3 class="show-card__title"><?php echo esc_html( $s['title'] ); ?></h3>
            <?php if ( $s['author'] ) : ?><p class="show-card__author"><?php echo esc_html( $s['author'] ); ?></p><?php endif; ?>
            <?php if ( $s['dates'] ) : ?><span class="show-card__dates"><?php echo esc_html( $s['dates'] ); ?></span><?php endif; ?>
            <?php if ( $s['tagline'] ) : ?><p class="show-card__tagline"><?php echo esc_html( $s['tagline'] ); ?></p><?php endif; ?>
            <?php if ( $s['blurb'] ) : ?><p class="show-card__blurb"><?php echo esc_html( $s['blurb'] ); ?></p><?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

</div>

<?php get_footer(); ?>
