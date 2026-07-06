<?php
/**
 * Template Name: Auditions Hub
 *
 * The "Current Auditions" content auto-derives from each Show's audition fields
 * (tlt_get_audition_shows()): a show is FEATURED with a Sign-Up button once it
 * has a Casting Manager link, listed under "Audition Dates" whenever it has
 * upcoming dates, marked "cast" once a cast is entered, and retired ~3 weeks
 * after its last audition date. Everything else (facts, steps, tips, rehearsal)
 * is editable via ACF.
 */
get_header();

$af = function ( $n, $d = '' ) { $v = function_exists( 'get_field' ) ? get_field( $n ) : null; return ( $v === null || $v === '' ) ? $d : $v; };
$auditions   = function_exists( 'tlt_get_audition_shows' ) ? tlt_get_audition_shows() : [];
$featured    = array_values( array_filter( $auditions, function ( $a ) { return $a['featured']; } ) );
$casting_url = $af( 'aud_casting_url', 'http://castingmanager.com/profile/5b7c8e3901d88' );

$aud_location    = $af( 'aud_location', "Tacoma Little Theatre\n210 N \"I\" Street, Tacoma WA" );
$aud_appointment = $af( 'aud_appointment', 'All auditions are by appointment only. Sign-ups open one month before audition dates.' );
$aud_phone       = $af( 'aud_phone', '(253) 272-2281' );
$aud_phone_tel   = preg_replace( '/[^0-9]/', '', $aud_phone );
$aud_steps     = function_exists( 'tlt_parse_heading_cards' ) ? tlt_parse_heading_cards( $af( 'aud_steps' ) ) : [];
$aud_tips      = function_exists( 'tlt_parse_heading_cards' ) ? tlt_parse_heading_cards( $af( 'aud_tips' ) ) : [];
$aud_rehearsal = $af( 'aud_rehearsal' );
$aud_empty     = $af( 'aud_empty_text', "No auditions are currently scheduled." );

// Format an audition schedule (parsed) into display date lines.
$fmt_dates = function ( $sched ) {
    $out = [];
    foreach ( $sched as $s ) {
        $ts = strtotime( $s['date'] );
        if ( ! $ts ) continue;
        $out[] = date_i18n( 'D, M j', $ts ) . ( ! empty( $s['time'] ) ? ' · ' . $s['time'] : '' ) . ( ! empty( $s['location'] ) ? ' · ' . $s['location'] : '' );
    }
    return $out;
};
$status_label = [
    'signup'  => 'Now scheduling auditions',
    'coming'  => 'Sign-ups open about one month before audition dates',
    'cast'    => 'This show has been cast',
    'pending' => 'Auditions held — casting in progress',
];
?>

<style>
  .aud-page { max-width: 1100px; margin: 0 auto; padding: 2rem var(--pad) 4rem; }
  .aud-hero { text-align: center; padding: 2rem 0 1.5rem; }
  .aud-hero .eyebrow { display: inline-block; background: var(--color-accent); color: #fff; font-size: 0.75rem; letter-spacing: 0.15em; text-transform: uppercase; padding: 0.3rem 0.9rem; border-radius: 999px; margin-bottom: 1rem; }
  .aud-hero h1 { font-size: clamp(2rem, 5vw, 3rem); margin: 0 0 0.5rem; }
  .aud-hero .lede { font-size: 1.05rem; color: var(--color-muted); max-width: 640px; margin: 0 auto; }

  .aud-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin: 2.5rem 0; }
  .aud-fact { background: var(--color-soft); border-radius: 6px; padding: 1.25rem; display: flex; gap: 0.85rem; align-items: flex-start; }
  .aud-fact .icon { flex: 0 0 auto; width: 32px; height: 32px; color: var(--color-accent); }
  .aud-fact .icon svg { width: 100%; height: 100%; }
  .aud-fact h3 { margin: 0 0 0.25rem; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--color-accent); }
  .aud-fact p { margin: 0; font-size: 0.92rem; line-height: 1.5; }
  .aud-fact a { color: inherit; text-decoration: underline; }

  .aud-section { margin: 0 0 3rem; scroll-margin-top: 80px; }
  .aud-section > h2 { font-size: 1.6rem; margin: 0 0 1.25rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--color-accent); display: inline-block; }

  /* Featured audition cards (have a Casting Manager link) */
  .aud-featured { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
  .aud-feat { display: grid; grid-template-columns: 110px 1fr; gap: 1.25rem; background: #fff; border: 1px solid var(--color-line); border-radius: 8px; padding: 1.25rem; }
  .aud-feat__poster { width: 110px; }
  .aud-feat__poster img { width: 100%; height: auto; border-radius: 4px; display: block; }
  .aud-feat__poster .noimg { display: block; width: 100%; aspect-ratio: 3/4; background: var(--color-soft); border-radius: 4px; }
  .aud-feat__title { margin: 0 0 0.25rem; font-size: 1.15rem; }
  .aud-feat__title a { color: var(--color-text); text-decoration: none; }
  .aud-feat__title a:hover { color: var(--color-accent); }
  .aud-feat__meta { margin: 0 0 0.5rem; font-size: 0.85rem; color: var(--color-muted); }
  .aud-feat__blurb { margin: 0 0 0.9rem; font-size: 0.92rem; line-height: 1.5; }
  .aud-feat__dates { margin: 0 0 0.9rem; font-size: 0.85rem; line-height: 1.5; }
  .aud-feat__dates span { display: block; }

  /* Audition Dates list */
  .aud-dates { display: grid; gap: 0.75rem; }
  .aud-date-row { display: flex; flex-wrap: wrap; gap: 0.5rem 1.25rem; align-items: baseline; justify-content: space-between; border: 1px solid var(--color-line); border-left: 4px solid var(--color-accent); border-radius: 4px; padding: 1rem 1.25rem; }
  .aud-date-row__main h3 { margin: 0 0 0.2rem; font-size: 1.05rem; }
  .aud-date-row__main h3 a { color: var(--color-text); text-decoration: none; }
  .aud-date-row__main h3 a:hover { color: var(--color-accent); }
  .aud-date-row__when { font-size: 0.88rem; color: var(--color-muted); line-height: 1.5; }
  .aud-date-row__when span { display: block; }
  .aud-date-row__status { font-size: 0.85rem; font-weight: 600; color: var(--color-accent); white-space: nowrap; }
  .aud-date-row__status.is-cast { color: var(--color-muted); }

  .steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; counter-reset: step; }
  .step { border: 1px solid var(--color-line); border-radius: 6px; padding: 1.5rem 1.25rem 1.25rem; position: relative; padding-top: 2.5rem; }
  .step::before { counter-increment: step; content: counter(step); position: absolute; top: 0.6rem; right: 0.9rem; font-size: 2.5rem; font-weight: 700; line-height: 1; color: var(--color-accent); opacity: 0.15; font-family: var(--font-display); }
  .step h3 { margin: 0 0 0.5rem; font-size: 1.05rem; }
  .step p { margin: 0; font-size: 0.92rem; line-height: 1.55; color: var(--color-text); }
  .step a { color: var(--color-accent); }

  .tip-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
  .tip-card { background: var(--color-soft); padding: 1.25rem 1.5rem; border-radius: 6px; }
  .tip-card h3 { margin: 0 0 0.5rem; font-size: 0.9rem; color: var(--color-accent); text-transform: uppercase; letter-spacing: 0.05em; }
  .tip-card p { margin: 0; font-size: 0.92rem; line-height: 1.55; }

  .aud-notice { background: #fff; border: 1px solid var(--color-line); border-left: 4px solid var(--color-accent); border-radius: 4px; padding: 1.25rem 1.5rem; margin: 1.5rem 0; }
  .aud-notice p { margin: 0; line-height: 1.6; }

  .casting-manager-cta { background: linear-gradient(135deg, var(--color-soft) 0%, #fff 100%); border: 1px solid var(--color-line); border-radius: 6px; padding: 2rem; text-align: center; margin-top: 1.5rem; }
  .casting-manager-cta h3 { margin: 0 0 0.5rem; }
  .casting-manager-cta p { margin: 0 0 1.25rem; color: var(--color-muted); }

  .audition-list--empty { background: var(--color-soft); padding: 2rem; border-radius: 6px; text-align: center; }
</style>

<div class="aud-page">

  <header class="aud-hero">
    <?php $_aud_eb = function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'eyebrow', 'Get on Stage' ) : 'Get on Stage'; ?>
    <?php if ( $_aud_eb ) : ?><span class="eyebrow"><?php echo esc_html( $_aud_eb ); ?></span><?php endif; ?>
    <h1><?php echo esc_html( function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'title', get_the_title() ) : get_the_title() ); ?></h1>
    <p class="lede"><?php echo esc_html( function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'lede', "Audition opportunities at Tacoma Little Theatre. We're a community theatre — no experience required to audition, and we cast a wide range of roles each season." ) : "Audition opportunities at Tacoma Little Theatre. We're a community theatre — no experience required to audition, and we cast a wide range of roles each season." ); ?></p>
  </header>

  <!-- KEY FACTS STRIP -->
  <div class="aud-facts">
    <div class="aud-fact">
      <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg></span>
      <div><h3>Location</h3><p><?php echo nl2br( esc_html( $aud_location ) ); ?></p></div>
    </div>
    <div class="aud-fact">
      <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7v-5z"/></svg></span>
      <div><h3>By Appointment</h3><p><?php echo esc_html( $aud_appointment ); ?></p></div>
    </div>
    <div class="aud-fact">
      <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8l8 5 8-5v10zm-8-7L4 6h16l-8 5z"/></svg></span>
      <div><h3>Questions?</h3><p><a href="tel:+<?php echo esc_attr( $aud_phone_tel ); ?>"><?php echo esc_html( $aud_phone ); ?></a><br><a href="/contact/">Email the box office</a></p></div>
    </div>
  </div>

  <!-- CURRENT AUDITIONS (auto-derived) -->
  <section class="aud-section" id="current">
    <h2>Current Auditions</h2>

    <?php if ( $featured ) : ?>
      <div class="aud-featured">
        <?php foreach ( $featured as $a ) :
          $poster = function_exists( 'tlt_show_image_url' ) ? tlt_show_image_url( $a['show']->ID, 'medium' ) : get_the_post_thumbnail_url( $a['show']->ID, 'medium' );
          $lines  = $fmt_dates( $a['schedule'] );
        ?>
          <article class="aud-feat">
            <div class="aud-feat__poster">
              <?php if ( $poster ) : ?><a href="<?php echo esc_url( get_permalink( $a['show'] ) ); ?>"><img src="<?php echo esc_url( $poster ); ?>" alt=""></a><?php else : ?><span class="noimg"></span><?php endif; ?>
            </div>
            <div>
              <h3 class="aud-feat__title"><a href="<?php echo esc_url( get_permalink( $a['show'] ) ); ?>"><?php echo esc_html( $a['title'] ); ?></a></h3>
              <?php if ( $a['director'] ) : ?><p class="aud-feat__meta">Directed by <?php echo esc_html( $a['director'] ); ?></p><?php endif; ?>
              <?php if ( $a['blurb'] ) : ?><p class="aud-feat__blurb"><?php echo esc_html( $a['blurb'] ); ?></p><?php endif; ?>
              <?php if ( $lines ) : ?><p class="aud-feat__dates"><?php foreach ( $lines as $l ) echo '<span>' . esc_html( $l ) . '</span>'; ?></p><?php endif; ?>
              <a class="btn btn-primary" href="<?php echo esc_url( $a['signup_url'] ); ?>" target="_blank" rel="noopener">Sign Up to Audition</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ( $auditions ) : ?>
      <h3 style="font-size:1.1rem;margin:1.5rem 0 0.75rem">Audition Dates</h3>
      <div class="aud-dates">
        <?php foreach ( $auditions as $a ) :
          $lines = $fmt_dates( $a['schedule'] );
        ?>
          <div class="aud-date-row">
            <div class="aud-date-row__main">
              <h3><a href="<?php echo esc_url( get_permalink( $a['show'] ) ); ?>"><?php echo esc_html( $a['title'] ); ?></a></h3>
              <?php if ( $lines && $a['status'] !== 'cast' ) : ?>
                <div class="aud-date-row__when"><?php foreach ( $lines as $l ) echo '<span>' . esc_html( $l ) . '</span>'; ?></div>
              <?php endif; ?>
              <?php if ( $a['director'] ) : ?><div class="aud-date-row__when">Directed by <?php echo esc_html( $a['director'] ); ?></div><?php endif; ?>
            </div>
            <div class="aud-date-row__status<?php echo $a['status'] === 'cast' ? ' is-cast' : ''; ?>">
              <?php if ( $a['status'] === 'signup' ) : ?>
                <a class="btn btn-primary" href="<?php echo esc_url( $a['signup_url'] ); ?>" target="_blank" rel="noopener">Sign Up</a>
              <?php else : ?>
                <?php echo esc_html( $status_label[ $a['status'] ] ?? '' ); ?>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else : ?>
      <div class="audition-list--empty">
        <?php foreach ( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $aud_empty ) ) ) as $i => $line ) : ?>
          <p style="margin:<?php echo $i === 0 ? '0' : '0.75rem 0 0'; ?>;<?php echo $i === 0 ? 'font-size:1.05rem;' : ''; ?>"><?php echo esc_html( $line ); ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- HOW TO SIGN UP -->
  <section class="aud-section">
    <h2>How to Sign Up</h2>
    <div class="steps">
      <?php foreach ( $aud_steps as $step ) : ?>
        <div class="step">
          <h3><?php echo esc_html( $step['heading'] ); ?></h3>
          <p><?php echo wp_kses_post( $step['body'] ); ?></p>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="casting-manager-cta">
      <h3>Audition with Casting Manager</h3>
      <p>All TLT auditions are scheduled through Casting Manager. Head there to browse open auditions and book a time.</p>
      <a class="btn btn-primary" href="<?php echo esc_url( $casting_url ); ?>" target="_blank" rel="noopener">Go to TLT on Casting Manager</a>
    </div>
  </section>

  <!-- AUDITION TIPS -->
  <section class="aud-section">
    <h2>Audition Tips</h2>
    <div class="tip-grid">
      <?php foreach ( $aud_tips as $tip ) : ?>
        <div class="tip-card">
          <h3><?php echo esc_html( $tip['heading'] ); ?></h3>
          <p><?php echo wp_kses_post( $tip['body'] ); ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- REHEARSAL INFO -->
  <?php if ( $aud_rehearsal ) : ?>
  <section class="aud-section">
    <h2>Rehearsal Information</h2>
    <div class="aud-notice" style="margin-top:0"><p><?php echo wp_kses_post( $aud_rehearsal ); ?></p></div>
  </section>
  <?php endif; ?>

</div>

<?php get_footer(); ?>
