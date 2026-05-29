<?php
/**
 * Template Name: Auditions Hub
 *
 * Single hub page for all current auditions. The Current Auditions list is
 * pulled from tlt_show records with show_audition_status meta. Everything
 * else (intro, signup steps, tips, rehearsal info, contact) lives in this
 * template — page post_content is no longer rendered.
 */
get_header();

$today = function_exists( 'tlt_today' ) ? tlt_today() : current_time( 'Y-m-d' );

$audition_shows = get_posts( [
    'post_type'      => 'tlt_show',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_query'     => [
        'relation' => 'AND',
        [ 'key' => 'show_audition_status', 'value' => [ 'open', 'scheduled', 'cast' ], 'compare' => 'IN' ],
        [ 'key' => 'show_open_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ],
    ],
    'meta_key' => 'show_open_date',
    'orderby'  => 'meta_value',
    'order'    => 'ASC',
] );

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

  /* Numbered "How to Sign Up" steps */
  .steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; counter-reset: step; }
  .step { border: 1px solid var(--color-line); border-radius: 6px; padding: 1.5rem 1.25rem 1.25rem; position: relative; padding-top: 2.5rem; }
  .step::before { counter-increment: step; content: counter(step); position: absolute; top: 0.6rem; right: 0.9rem; font-size: 2.5rem; font-weight: 700; line-height: 1; color: var(--color-accent); opacity: 0.15; font-family: var(--font-display); }
  .step h3 { margin: 0 0 0.5rem; font-size: 1.05rem; }
  .step p { margin: 0; font-size: 0.92rem; line-height: 1.55; color: var(--color-text); }
  .step a { color: var(--color-accent); }

  /* Audition tip / rehearsal info cards */
  .tip-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
  .tip-card { background: var(--color-soft); padding: 1.25rem 1.5rem; border-radius: 6px; }
  .tip-card h3 { margin: 0 0 0.5rem; font-size: 0.9rem; color: var(--color-accent); text-transform: uppercase; letter-spacing: 0.05em; }
  .tip-card p { margin: 0; font-size: 0.92rem; line-height: 1.55; }

  /* Important notice */
  .aud-notice { background: #fff; border: 1px solid var(--color-line); border-left: 4px solid var(--color-accent); border-radius: 4px; padding: 1.25rem 1.5rem; margin: 1.5rem 0; }
  .aud-notice p { margin: 0; line-height: 1.6; }
  .aud-notice strong { color: var(--color-accent); }

  /* Casting Manager CTA card */
  .casting-manager-cta { background: linear-gradient(135deg, var(--color-soft) 0%, #fff 100%); border: 1px solid var(--color-line); border-radius: 6px; padding: 2rem; text-align: center; margin-top: 1.5rem; }
  .casting-manager-cta h3 { margin: 0 0 0.5rem; }
  .casting-manager-cta p { margin: 0 0 1.25rem; color: var(--color-muted); }
  .casting-manager-cta .btn { margin: 0 0.25rem 0.5rem; }

  /* Empty-state callout for Current Auditions */
  .audition-list--empty { background: var(--color-soft); padding: 2rem; border-radius: 6px; text-align: center; }
  .audition-list--empty .section-heading { margin-top: 0; }
</style>

<div class="aud-page">

  <header class="aud-hero">
    <span class="eyebrow">Get on Stage</span>
    <h1><?php the_title(); ?></h1>
    <p class="lede">Audition opportunities at Tacoma Little Theatre. We're a community theatre — no experience required to audition, and we cast a wide range of roles each season.</p>
  </header>

  <!-- KEY FACTS STRIP ============================================ -->
  <div class="aud-facts">
    <div class="aud-fact">
      <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg></span>
      <div>
        <h3>Location</h3>
        <p>Tacoma Little Theatre<br>210 N "I" Street, Tacoma WA</p>
      </div>
    </div>
    <div class="aud-fact">
      <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7v-5z"/></svg></span>
      <div>
        <h3>By Appointment</h3>
        <p>All auditions are by appointment only. Sign-ups open one month before audition dates.</p>
      </div>
    </div>
    <div class="aud-fact">
      <span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 14H4V8l8 5 8-5v10zm-8-7L4 6h16l-8 5z"/></svg></span>
      <div>
        <h3>Questions?</h3>
        <p><a href="tel:+12532722281">(253) 272-2281</a><br><a href="/contact/">Email the box office</a></p>
      </div>
    </div>
  </div>

  <!-- CURRENT AUDITIONS ============================================ -->
  <section class="aud-section" id="current">
    <h2>Current Auditions</h2>
    <?php if ( ! empty( $audition_shows ) ) : ?>
      <?php foreach ( $audition_shows as $show ) :
          $title        = get_the_title( $show );
          $director     = get_post_meta( $show->ID, 'show_director', true );
          $audition_dt  = get_post_meta( $show->ID, 'show_audition_dates', true );
          $audition_loc = get_post_meta( $show->ID, 'show_audition_location', true );
          $packet_url   = get_post_meta( $show->ID, 'show_audition_packet_url', true );
          $signup_url   = get_post_meta( $show->ID, 'show_audition_signup_url', true );
          $status       = get_post_meta( $show->ID, 'show_audition_status', true );
          $logo_url     = get_post_meta( $show->ID, 'show_logo_url', true );
          if ( ! $audition_loc ) {
              $audition_loc = 'Tacoma Little Theatre · 210 N "I" Street, Tacoma WA';
          }
      ?>
        <div class="audition-row audition-row--<?php echo esc_attr( $status ); ?>">
          <?php if ( $logo_url ) : ?>
            <div class="audition-row__logo">
              <img src="<?php echo esc_url( $logo_url ); ?>" alt="">
            </div>
          <?php endif; ?>
          <div class="audition-row__info">
            <h3 class="audition-row__title">
              <a href="<?php echo esc_url( get_permalink( $show ) ); ?>"><?php echo esc_html( $title ); ?></a>
            </h3>
            <?php if ( $audition_dt ) : ?><p class="audition-row__dates"><?php echo esc_html( $audition_dt ); ?></p><?php endif; ?>
            <?php if ( $director ) : ?><p class="audition-row__director">Directed by <?php echo esc_html( $director ); ?></p><?php endif; ?>
            <p class="audition-row__location"><?php echo esc_html( $audition_loc ); ?></p>

            <?php if ( $status === 'cast' ) : ?>
              <p class="audition-row__status audition-row__status--cast">This show has been cast.</p>
            <?php elseif ( $status === 'scheduled' ) : ?>
              <p class="audition-row__status audition-row__status--scheduled">Sign-ups open one month before audition dates.</p>
            <?php elseif ( $status === 'open' && $signup_url ) : ?>
              <p class="audition-row__cta">
                <a class="btn btn-primary" href="<?php echo esc_url( $signup_url ); ?>" target="_blank" rel="noopener">Schedule an Audition</a>
                <?php if ( $packet_url ) : ?>
                  <a class="btn btn-outline" href="<?php echo esc_url( $packet_url ); ?>" target="_blank" rel="noopener">Audition Packet (PDF)</a>
                <?php endif; ?>
              </p>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else : ?>
      <div class="audition-list--empty">
        <p style="margin:0; font-size: 1.05rem;">No auditions are currently scheduled.</p>
        <p style="margin:0.75rem 0 0;">Check back soon, or <a href="https://tlt.ludus.com/subscribe.php" target="_blank" rel="noopener">join our email list</a> to be notified when new auditions are announced.</p>
      </div>
    <?php endif; ?>

    <div class="aud-notice" style="margin-top:1.5rem">
      <p><strong>Note:</strong> If there is no logo next to a show above, auditions are not yet being scheduled or have already passed. Audition dates are subject to change without notice.</p>
    </div>
  </section>

  <!-- HOW TO SIGN UP ============================================ -->
  <section class="aud-section">
    <h2>How to Sign Up</h2>
    <div class="steps">
      <div class="step">
        <h3>Create a Casting Manager account</h3>
        <p>All auditions are scheduled through Casting Manager. <a href="http://castingmanager.com/profile/5b7c8e3901d88" target="_blank" rel="noopener">Sign up for a free account</a> if you don't already have one.</p>
      </div>
      <div class="step">
        <h3>Pick a show and schedule a time</h3>
        <p>Click the show's audition link above to choose an appointment that works for you. Sign-ups open about one month before audition dates.</p>
      </div>
      <div class="step">
        <h3>Prepare your audition</h3>
        <p>Bring a 1–2 minute monologue (or 16 bars of a song for a musical). See tips below for what to expect.</p>
      </div>
      <div class="step">
        <h3>Show up and have fun</h3>
        <p>Arrive a few minutes early. Auditions are friendly and low-pressure — we want to see what you can do.</p>
      </div>
    </div>

    <div class="casting-manager-cta">
      <h3>Audition with Casting Manager</h3>
      <p>Already have a Casting Manager account? Head straight there to browse open auditions.</p>
      <a class="btn btn-primary" href="http://castingmanager.com/profile/5b7c8e3901d88" target="_blank" rel="noopener">Go to TLT on Casting Manager</a>
    </div>
  </section>

  <!-- AUDITION TIPS ============================================ -->
  <section class="aud-section">
    <h2>Audition Tips</h2>
    <div class="tip-grid">
      <div class="tip-card">
        <h3>Most Directors Want…</h3>
        <p>A 1–2 minute monologue for your initial audition. For comedies, prepare a humorous monologue; for non-comedies, a serious one. For musicals, prepare a song of no less than 16 bars and bring sheet music — an accompanist will be provided.</p>
      </div>
      <div class="tip-card">
        <h3>Non-Musical Plays</h3>
        <p>Come prepared with a monologue. A résumé and head shot are nice but not required.</p>
      </div>
      <div class="tip-card">
        <h3>Musicals</h3>
        <p>Choose a song that enhances and shows off your singing skills — usually not a song from the play for which you are auditioning.</p>
      </div>
      <div class="tip-card">
        <h3>Callbacks</h3>
        <p>At the callback, actors will read from the script. The director may also ask you to do scene work with other auditioners.</p>
      </div>
    </div>
  </section>

  <!-- REHEARSAL INFO ============================================ -->
  <section class="aud-section">
    <h2>Rehearsal Information</h2>
    <div class="aud-notice" style="margin-top:0">
      <p>If cast, most rehearsals take place <strong>Monday–Thursday, 7:00 pm – 9:30 pm</strong>, plus one weekend day. Directors do their best to work around rehearsal conflicts, and you'll only be called to the rehearsals you actually need to attend.</p>
    </div>
  </section>

</div>

<?php get_footer(); ?>
