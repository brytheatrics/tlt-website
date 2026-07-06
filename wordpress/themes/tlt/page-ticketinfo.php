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

<?php
$tif = function ( $n, $d = '' ) { $v = function_exists( 'get_field' ) ? get_field( $n ) : null; return ( $v === null || $v === '' ) ? $d : $v; };
$ti_buy_label    = $tif( 'ti_buy_label', 'Buy Tickets' );
$ti_buy_url      = $tif( 'ti_buy_url', 'https://tlt.ludus.com' );
$ti_season_label = $tif( 'ti_season_label', 'Season Tickets' );
$ti_season_url   = $tif( 'ti_season_url', '/season-tickets/' );
$ti_musical_prices = $tif( 'ti_musical_prices' );
$ti_play_prices    = $tif( 'ti_play_prices' );
$ti_musical_group  = $tif( 'ti_musical_group' );
$ti_play_group     = $tif( 'ti_play_group' );
$ti_group_note     = $tif( 'ti_group_note' );
$ti_info_cards     = function_exists( 'tlt_parse_heading_cards' ) ? tlt_parse_heading_cards( $tif( 'ti_info_cards' ) ) : [];
$ti_season_intro   = $tif( 'ti_season_intro' );
$ti_season_summary = $tif( 'ti_season_summary' );
$ti_season_bullets = $tif( 'ti_season_bullets' );
$ti_flex_summary   = $tif( 'ti_flex_summary' );
$ti_flex_bullets   = $tif( 'ti_flex_bullets' );
$ti_subscribe_heading   = $tif( 'ti_subscribe_heading', 'Ready to Subscribe?' );
$ti_subscribe_intro     = $tif( 'ti_subscribe_intro' );
$ti_mail_address        = $tif( 'ti_mail_address' );
$ti_subscribe_btn_label = $tif( 'ti_subscribe_btn_label', 'Subscribe Online' );
$ti_subscribe_btn_url   = $tif( 'ti_subscribe_btn_url', '/season-tickets/' );
$ti_general_policies    = function_exists( 'tlt_parse_heading_cards' ) ? tlt_parse_heading_cards( $tif( 'ti_general_policies' ) ) : [];
$ti_subscriber_policies = function_exists( 'tlt_parse_heading_cards' ) ? tlt_parse_heading_cards( $tif( 'ti_subscriber_policies' ) ) : [];

// Season & FLEX prices come from the Season Tickets page (one canonical source).
$st_page = get_page_by_path( 'season-tickets' );
$st_pid  = $st_page ? $st_page->ID : 0;
$ti_st_prices  = $st_pid ? get_post_meta( $st_pid, 'st_season_prices', true ) : '';
$ti_flex_price = $st_pid ? get_post_meta( $st_pid, 'st_flex_price', true ) : '';
if ( ! $ti_st_prices )  $ti_st_prices  = "\$171.20 | Adult\n\$160.00 | Senior / Student / Military\n\$132.00 | Child";
if ( ! $ti_flex_price ) $ti_flex_price = '$160.00 | 6 punches';

$ti_price_rows = function ( $text ) {
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
        $line = trim( $line ); if ( $line === '' ) continue;
        $p = array_map( 'trim', explode( '|', $line, 2 ) );
        echo '<div class="price-row"><span class="who">' . esc_html( $p[1] ?? '' ) . '</span><span class="price">' . esc_html( $p[0] ) . '</span></div>';
    }
};
$ti_price_tags = function ( $text ) {
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
        $line = trim( $line ); if ( $line === '' ) continue;
        $p = array_map( 'trim', explode( '|', $line, 2 ) );
        echo '<span class="price-tag">' . esc_html( $p[0] );
        if ( ! empty( $p[1] ) ) echo ' <span class="who">' . esc_html( $p[1] ) . '</span>';
        echo '</span>';
    }
};
$ti_bullets = function ( $text ) {
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
        $line = trim( $line ); if ( $line === '' ) continue;
        echo '<li>' . esc_html( $line ) . '</li>';
    }
};
?>

<div class="ti-page">

  <header class="ti-hero">
    <?php $_ti_eb = function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'eyebrow', 'Ticket Information' ) : 'Ticket Information'; ?>
    <?php if ( $_ti_eb ) : ?><span class="eyebrow"><?php echo esc_html( $_ti_eb ); ?></span><?php endif; ?>
    <h1><?php echo esc_html( function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'title', get_the_title() ) : get_the_title() ); ?></h1>
    <p class="lede"><?php echo esc_html( function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'lede', 'Everything you need to know about ticket prices, season passes, and the few house rules we have in place to keep everyone comfortable.' ) : 'Everything you need to know about ticket prices, season passes, and the few house rules we have in place to keep everyone comfortable.' ); ?></p>
    <div class="cta-row">
      <a class="btn btn-primary" href="<?php echo esc_url( $ti_buy_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $ti_buy_label ); ?></a>
      <a class="btn btn-outline" href="<?php echo esc_url( $ti_season_url ); ?>"><?php echo esc_html( $ti_season_label ); ?></a>
    </div>
  </header>

  <!-- PRICING ============================================ -->
  <section class="ti-section" id="pricing">
    <h2>Single Ticket Pricing</h2>

    <div class="pricing-grid">
      <div class="pricing-card">
        <div class="pricing-card__head"><h3>Musicals</h3></div>
        <div class="pricing-card__body">
          <?php $ti_price_rows( $ti_musical_prices ); ?>
          <?php if ( $ti_musical_group ) : ?>
          <div class="group-note">
            <strong>Group Rates</strong>
            <?php echo esc_html( $ti_musical_group ); ?><br>
            <?php if ( $ti_group_note ) : ?><em><?php echo esc_html( $ti_group_note ); ?></em><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="pricing-card">
        <div class="pricing-card__head"><h3>Plays</h3></div>
        <div class="pricing-card__body">
          <?php $ti_price_rows( $ti_play_prices ); ?>
          <?php if ( $ti_play_group ) : ?>
          <div class="group-note">
            <strong>Group Rates</strong>
            <?php echo esc_html( $ti_play_group ); ?><br>
            <?php if ( $ti_group_note ) : ?><em><?php echo esc_html( $ti_group_note ); ?></em><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="info-grid" style="margin-top:1.5rem">
      <?php foreach ( $ti_info_cards as $card ) : ?>
        <div class="info-card">
          <h3><?php echo esc_html( $card['heading'] ); ?></h3>
          <p><?php echo wp_kses_post( $card['body'] ); ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- SEASON TICKETS & FLEX PASS ============================================ -->
  <section class="ti-section" id="season">
    <h2>Season Tickets &amp; Flex Passes</h2>
    <?php if ( $ti_season_intro ) : ?><p style="max-width: 760px; line-height: 1.6;"><?php echo esc_html( $ti_season_intro ); ?></p><?php endif; ?>

    <div class="compare-grid">
      <div class="compare-card">
        <h3>Season Ticket</h3>
        <p class="summary"><?php echo esc_html( $ti_season_summary ); ?></p>
        <?php $ti_price_tags( $ti_st_prices ); ?>
        <ul><?php $ti_bullets( $ti_season_bullets ); ?></ul>
      </div>

      <div class="compare-card">
        <h3>FLEX Pass</h3>
        <p class="summary"><?php echo esc_html( $ti_flex_summary ); ?></p>
        <?php $ti_price_tags( $ti_flex_price ); ?>
        <ul><?php $ti_bullets( $ti_flex_bullets ); ?></ul>
      </div>
    </div>

    <div class="subscribe-cta">
      <h3><?php echo esc_html( $ti_subscribe_heading ); ?></h3>
      <?php if ( $ti_subscribe_intro ) : ?><p><?php echo esc_html( $ti_subscribe_intro ); ?></p><?php endif; ?>
      <?php if ( $ti_mail_address ) : ?><div class="mail-address"><?php echo nl2br( esc_html( $ti_mail_address ) ); ?></div><?php endif; ?>
      <div style="margin-top:1.25rem">
        <a class="btn btn-primary" href="<?php echo esc_url( $ti_subscribe_btn_url ); ?>"><?php echo esc_html( $ti_subscribe_btn_label ); ?></a>
      </div>
    </div>
  </section>

  <!-- GENERAL POLICIES ============================================ -->
  <section class="ti-section" id="policies">
    <h2>General Policies</h2>

    <div class="policy-grid">
      <?php foreach ( $ti_general_policies as $card ) : ?>
        <div class="policy-card">
          <h3><?php echo esc_html( $card['heading'] ); ?></h3>
          <?php echo wp_kses_post( wpautop( $card['body'] ) ); ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- SEASON TICKET & FLEX PASS POLICIES ============================================ -->
  <section class="ti-section" id="subscriber-policies">
    <h2>Season Ticket &amp; FLEX Pass Policies</h2>

    <div class="policy-grid">
      <?php foreach ( $ti_subscriber_policies as $card ) : ?>
        <div class="policy-card">
          <h3><?php echo esc_html( $card['heading'] ); ?></h3>
          <?php echo wp_kses_post( wpautop( $card['body'] ) ); ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

</div>

<?php get_footer(); ?>
