<?php
/**
 * Template Name: Off the Shelf
 *
 * Off the Shelf readings hub — current season featured at top, prior seasons
 * in a uniform poster archive. To add a new poster, append to the appropriate
 * year's array below.
 */
get_header();

// === Current season: featured posters with optional ticket links ===
// (Leave empty when the season's readings have all closed; the template
//  shows a "more titles announced soon" message and the recent posters
//  move into the archive below.)
$current_season  = '2025–2026';
$current_posters = [];

// === Past seasons: poster-only archive ===
$archive = [
    '2025–2026' => [
        '/wp-content/uploads/migrated/2526-chirstmas-carol-2.jpg',
        '/wp-content/uploads/migrated/tlt-metamorphoses.jpg',
        '/wp-content/uploads/migrated/hijinx-sue-h.png',
    ],
    '2024–2025' => [
        '/wp-content/uploads/migrated/christmas-carol-v2.jpg',
        '/wp-content/uploads/migrated/sexiest-couple.png',
        '/wp-content/uploads/migrated/moors.jpg',
        '/wp-content/uploads/migrated/dogseesgod.png',
    ],
    '2023–2024' => [
        '/wp-content/uploads/migrated/moon.png',
        '/wp-content/uploads/migrated/venus-2.png',
        '/wp-content/uploads/migrated/shipwrecked.jpg',
        '/wp-content/uploads/migrated/eleemosynary.png',
        '/wp-content/uploads/migrated/montreal.png',
    ],
    '2022–2023' => [
        '/wp-content/uploads/migrated/sonnets.png',
        '/wp-content/uploads/migrated/among-many-worlds-tlt-online-poster-9.4.22a.png',
        '/wp-content/uploads/migrated/tlt-men-of-tortuga.jpg',
        '/wp-content/uploads/migrated/tlt-boston-marriage.jpg',
        '/wp-content/uploads/migrated/tlt-the-trojan-women.jpg',
    ],
    '2021–2022' => [
        '/wp-content/uploads/migrated/autonomous.png',
        '/wp-content/uploads/migrated/triumph.png',
        '/wp-content/uploads/migrated/boudica-social-media-image.png',
        '/wp-content/uploads/migrated/cry-it-out.png',
    ],
    '2019–2020' => [
        '/wp-content/uploads/migrated/tlt-outside-mullingar.png',
        '/wp-content/uploads/migrated/fuddy-meers.png',
        '/wp-content/uploads/migrated/tlt-4000-days.png',
        '/wp-content/uploads/migrated/larmie-10-years-later.png',
    ],
    '2018–2019' => [
        '/wp-content/uploads/migrated/tlt-for-peter-pan-on-her-70th-birthday.png',
        '/wp-content/uploads/migrated/hurly-burly.png',
        '/wp-content/uploads/migrated/toyland-half-page.jpg',
        '/wp-content/uploads/migrated/measure.png',
        '/wp-content/uploads/migrated/revolutionists.png',
        '/wp-content/uploads/migrated/tlt-mrs.-packard.png',
        '/wp-content/uploads/migrated/bootleg.png',
    ],
    '2017–2018' => [
        '/wp-content/uploads/migrated/tltmyhusbandlikedbeverlybetter.png',
        '/wp-content/uploads/migrated/fade.png',
        '/wp-content/uploads/migrated/honey.png',
        '/wp-content/uploads/migrated/sisters.png',
        '/wp-content/uploads/migrated/dear-liar.png',
        '/wp-content/uploads/migrated/art-and-mountain.png',
        '/wp-content/uploads/migrated/fawn.png',
        '/wp-content/uploads/migrated/building-the-wall.png',
    ],
    '2016–2017' => [
        '/wp-content/uploads/migrated/image-asset-3.png',
        '/wp-content/uploads/migrated/image-asset-48.jpg',
        '/wp-content/uploads/migrated/image-asset-4.png',
        '/wp-content/uploads/migrated/image-asset-5.png',
        '/wp-content/uploads/migrated/image-asset-6.png',
        '/wp-content/uploads/migrated/image-asset-7.png',
        '/wp-content/uploads/migrated/venus.png',
    ],
    '2015–2016' => [
        '/wp-content/uploads/migrated/storyofmylife.png',
        '/wp-content/uploads/migrated/oleanna.png',
        '/wp-content/uploads/migrated/wrecks.png',
        '/wp-content/uploads/migrated/10th-muse.jpg',
        '/wp-content/uploads/migrated/library.png',
        '/wp-content/uploads/migrated/top-girls.png',
        '/wp-content/uploads/migrated/tlt-fat-pig.png',
    ],
    '2014–2015' => [
        '/wp-content/uploads/migrated/true-west.png',
        '/wp-content/uploads/migrated/rachel-corrie-v2.png',
        '/wp-content/uploads/migrated/quartet.png',
        '/wp-content/uploads/migrated/regretsonly.png',
        '/wp-content/uploads/migrated/talk-radio.png',
        '/wp-content/uploads/migrated/diviners.png',
        '/wp-content/uploads/migrated/ceremony.png',
    ],
];
?>

<style>
  .ots-page { max-width: 1100px; margin: 0 auto; padding: 2rem var(--pad) 4rem; }

  .ots-hero { text-align: center; padding: 2rem 0 1.5rem; }
  .ots-hero .eyebrow { display: inline-block; background: var(--color-accent); color: #fff; font-size: 0.75rem; letter-spacing: 0.15em; text-transform: uppercase; padding: 0.3rem 0.9rem; border-radius: 999px; margin-bottom: 1rem; }
  .ots-hero h1 { font-size: clamp(2rem, 5vw, 3rem); margin: 0 0 1rem; }
  .ots-hero .lede { font-size: 1.05rem; line-height: 1.7; color: var(--color-text); max-width: 760px; margin: 0 auto; }
  .ots-hero .schedule-note { display: inline-block; background: var(--color-soft); color: var(--color-text); padding: 0.6rem 1.2rem; border-radius: 999px; margin-top: 1.5rem; font-size: 0.92rem; font-style: italic; }

  .ots-section { margin: 3rem 0 0; }
  .ots-section > h2 { font-size: 1.4rem; margin: 0 0 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--color-accent); display: inline-block; }
  .ots-section > p.intro { margin: 0 0 1.5rem; color: var(--color-muted); line-height: 1.6; }

  /* Current season — larger feature cards */
  .ots-current-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.5rem; }
  .ots-feature {
    background: #fff; border: 1px solid var(--color-line); border-radius: 6px;
    overflow: hidden; transition: border-color 0.15s, box-shadow 0.15s;
    display: flex; flex-direction: column;
  }
  .ots-feature:hover { border-color: var(--color-accent); box-shadow: 0 4px 16px rgba(0,0,0,0.06); }
  .ots-feature__poster {
    background: transparent;
    aspect-ratio: 3/4;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
  }
  .ots-feature__poster img,
  .ots-feature__poster a {
    max-width: 100%; max-height: 100%;
    display: flex; align-items: center; justify-content: center;
  }
  .ots-feature__poster img {
    width: auto; height: auto; object-fit: contain;
  }
  .ots-feature__body { padding: 1rem 1.25rem 1.25rem; text-align: center; }
  .ots-feature__title { font-size: 1.05rem; margin: 0 0 0.5rem; }
  .ots-feature__ticket { font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--color-accent); text-decoration: none; }
  .ots-feature__ticket:hover { text-decoration: underline; }
  .ots-feature__pending { font-size: 0.85rem; color: var(--color-muted); font-style: italic; }

  /* Archive — uniform poster grid */
  .ots-season-head {
    margin: 2rem 0 1rem; font-size: 0.85rem;
    text-transform: uppercase; letter-spacing: 0.12em; color: var(--color-muted);
    font-weight: 700;
  }
  /* Uniform poster cells: 5:4 landscape frame (matches most OTS posters).
     Tall portrait posters letterbox with side whitespace; landscape posters
     fill the cell. Predictable left-to-right row layout. */
  .ots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 1.5rem;
  }
  .ots-poster {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    aspect-ratio: 5 / 4;
    background: transparent;
    border: 0;
    padding: 0;
    margin: 0;
    cursor: zoom-in;
    overflow: hidden;
    transition: transform 0.15s;
  }
  .ots-poster:hover { transform: translateY(-2px); }
  .ots-poster:focus-visible { outline: 3px solid var(--color-accent); outline-offset: 2px; }
  .ots-poster img {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
    display: block;
    border-radius: 4px;
  }

  /* Lightbox */
  .ots-lightbox {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.88);
    display: none; align-items: center; justify-content: center;
    padding: 2rem; cursor: zoom-out;
  }
  .ots-lightbox[hidden] { display: none; }
  .ots-lightbox.is-open { display: flex; }
  .ots-lightbox img {
    max-width: 100%; max-height: 100%; width: auto; height: auto;
    object-fit: contain; display: block; cursor: default;
    box-shadow: 0 4px 24px rgba(0,0,0,0.5);
  }
  .ots-lightbox__close {
    position: absolute; top: 1.25rem; right: 1.5rem;
    background: rgba(255,255,255,0.1); color: #fff;
    border: none; border-radius: 50%; width: 44px; height: 44px;
    font-size: 1.8rem; line-height: 1; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background 0.15s;
  }
  .ots-lightbox__close:hover { background: rgba(255,255,255,0.25); }
  .ots-lightbox__close:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
</style>

<div class="ots-page">

  <header class="ots-hero">
    <?php $_ots_eb = function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'eyebrow', 'Staged Readings' ) : 'Staged Readings'; ?>
    <?php if ( $_ots_eb ) : ?><span class="eyebrow"><?php echo esc_html( $_ots_eb ); ?></span><?php endif; ?>
    <h1><?php echo esc_html( function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'title', 'Off the Shelf' ) : 'Off the Shelf' ); ?></h1>
    <p class="lede"><?php echo esc_html( function_exists( 'tlt_hero_field' ) ? tlt_hero_field( 'lede', 'Each season TLT presents its "Off the Shelf" series. There is a tremendous amount of wonderful theatre that deserves to be heard but sometimes just doesn\'t get the opportunity. With "Off the Shelf," local directors and actors bring some of these scripts to life — entertaining, challenging, educational. Sit back and enjoy an evening of theatre. You never know, you might see one of these on our Mainstage in the future.' ) : 'Each season TLT presents its "Off the Shelf" series. There is a tremendous amount of wonderful theatre that deserves to be heard but sometimes just doesn\'t get the opportunity. With "Off the Shelf," local directors and actors bring some of these scripts to life — entertaining, challenging, educational. Sit back and enjoy an evening of theatre. You never know, you might see one of these on our Mainstage in the future.' ); ?></p>
    <span class="schedule-note">Events take place in December, March, April, June, and July.</span>
  </header>

  <!-- CURRENT SEASON ============================================ -->
  <section class="ots-section">
    <h2>What's Next</h2>
    <?php if ( ! empty( $current_posters ) ) : ?>
      <div class="ots-current-grid">
        <?php foreach ( $current_posters as $p ) : ?>
          <article class="ots-feature">
            <button type="button" class="ots-feature__poster ots-poster" data-lightbox="<?php echo esc_attr( $p['img'] ); ?>" data-alt="<?php echo esc_attr( $p['title'] ); ?>" aria-label="View poster larger: <?php echo esc_attr( $p['title'] ); ?>" style="cursor: zoom-in;">
              <img src="<?php echo esc_url( $p['img'] ); ?>" alt="<?php echo esc_attr( $p['title'] ); ?>">
            </button>
            <div class="ots-feature__body">
              <h3 class="ots-feature__title"><?php echo esc_html( $p['title'] ); ?></h3>
              <?php if ( $p['ticket'] ) : ?>
                <a class="ots-feature__ticket" href="<?php echo esc_url( $p['ticket'] ); ?>" target="_blank" rel="noopener">Get Tickets &rarr;</a>
              <?php else : ?>
                <span class="ots-feature__pending">Tickets coming soon</span>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else : ?>
      <div style="background: var(--color-soft); padding: 2.5rem 2rem; border-radius: 6px; text-align: center;">
        <p style="margin: 0; font-size: 1.05rem;">More titles for our next season are being announced soon.</p>
        <p style="margin: 0.75rem 0 0; color: var(--color-muted); font-size: 0.95rem;">In the meantime, take a look back at our past Off the Shelf readings below.</p>
      </div>
    <?php endif; ?>
  </section>

  <!-- ARCHIVE ============================================ -->
  <?php if ( ! empty( $archive ) ) : ?>
    <section class="ots-section">
      <h2>From the Archive</h2>
      <p class="intro">A look back at past Off the Shelf readings.</p>

      <?php foreach ( $archive as $season => $posters ) : ?>
        <h3 class="ots-season-head"><?php echo esc_html( $season ); ?></h3>
        <div class="ots-grid">
          <?php foreach ( $posters as $src ) : ?>
            <button type="button" class="ots-poster" data-lightbox="<?php echo esc_attr( $src ); ?>" aria-label="View poster larger">
              <img src="<?php echo esc_url( $src ); ?>" alt="" loading="lazy">
            </button>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endif; ?>

</div>

<!-- Lightbox overlay -->
<div class="ots-lightbox" id="ots-lightbox" role="dialog" aria-modal="true" aria-label="Poster viewer" hidden>
  <button type="button" class="ots-lightbox__close" aria-label="Close">&times;</button>
  <img src="" alt="">
</div>

<script>
(function () {
  const lb = document.getElementById('ots-lightbox');
  if (!lb) return;
  const lbImg = lb.querySelector('img');
  const lbClose = lb.querySelector('.ots-lightbox__close');
  let lastTrigger = null;

  function open(src, alt) {
    lbImg.src = src;
    lbImg.alt = alt || '';
    lb.hidden = false;
    lb.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    lbClose.focus();
  }
  function close() {
    lb.classList.remove('is-open');
    lb.hidden = true;
    lbImg.src = '';
    document.body.style.overflow = '';
    if (lastTrigger) lastTrigger.focus();
  }

  document.querySelectorAll('[data-lightbox]').forEach(btn => {
    btn.addEventListener('click', e => {
      e.preventDefault();
      lastTrigger = btn;
      open(btn.dataset.lightbox, btn.dataset.alt || btn.getAttribute('aria-label'));
    });
  });

  // Click backdrop (but not the image itself) closes
  lb.addEventListener('click', e => {
    if (e.target === lb || e.target === lbClose) close();
  });

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !lb.hidden) close();
  });
})();
</script>

<?php get_footer(); ?>
