<?php
/**
 * Template Name: Off The Shelf
 *
 * Restructures the long wall-of-posters Squarespace content into
 * an intro + per-season poster grids, with the current/upcoming
 * season featured at the top.
 */
get_header();

// Pull the imported post body and split it into intro + per-season chunks.
$raw = get_the_content( null, false, get_queried_object_id() );

// Strip Squarespace's image lazy-load attrs that bloat HTML and break responsive sizing.
$raw = preg_replace( '/\s+(?:srcset|sizes|loading|decoding|elementtiming|onload|data-image|data-load|data-src)="[^"]*"/i', '', $raw );
// Normalize non-breaking spaces (Squarespace adds them to headings) so \s matches inside the split regex.
$raw = str_replace( [ "\xc2\xa0", "&nbsp;" ], ' ', $raw );

// Split on "OFF THE SHELF YYYY-YYYY" headings (either h1 or h2).
$parts = preg_split(
    '#<h[12][^>]*>\s*(?:<[^>]+>\s*)*\s*(OFF THE SHELF\s+\d{4}\s*-\s*\d{4})\s*(?:</[^>]+>\s*)*</h[12]>#i',
    $raw,
    -1,
    PREG_SPLIT_DELIM_CAPTURE
);

// Helper: pull poster figures out of a content chunk, returning [['src'=>..., 'href'=>...], …].
function tlt_ots_extract_posters( $chunk ) {
    $out = [];
    if ( preg_match_all( '#(?:<a[^>]+href="([^"]+)"[^>]*>\s*)?<figure[^>]*>(?:.*?<img[^>]+src="([^"]+)"[^>]*>).*?</figure>\s*(?:</a>)?#si', $chunk, $matches, PREG_SET_ORDER ) ) {
        foreach ( $matches as $m ) {
            $out[] = [ 'src' => $m[2], 'href' => $m[1] ?: '' ];
        }
    }
    return $out;
}

// Helper: pull the text paragraphs (skip empty placeholders).
function tlt_ots_extract_text( $chunk ) {
    $out = '';
    if ( preg_match_all( '#<p[^>]*>(.*?)</p>#is', $chunk, $matches ) ) {
        foreach ( $matches[1] as $p ) {
            $t = trim( wp_strip_all_tags( $p ) );
            if ( strlen( $t ) > 5 ) $out .= '<p>' . $p . '</p>';
        }
    }
    return $out;
}

// First chunk is intro (includes the 2025-26 posters since they appear before the first heading).
$intro_chunk = $parts[0] ?? '';
$intro_text  = tlt_ots_extract_text( $intro_chunk );
$intro_posters = tlt_ots_extract_posters( $intro_chunk );

// Remaining parts come in (heading_text, content_chunk) pairs.
$seasons = [];
for ( $i = 1; $i < count( $parts ); $i += 2 ) {
    $label = trim( $parts[ $i ] );
    $chunk = $parts[ $i + 1 ] ?? '';
    $posters = tlt_ots_extract_posters( $chunk );
    // Extract the year for sorting
    $year = 0;
    if ( preg_match( '/(\d{4})-\d{4}/', $label, $ym ) ) $year = (int) $ym[1];
    $seasons[] = [ 'label' => $label, 'year' => $year, 'posters' => $posters ];
}
// Sort newest-first (the source is already DESC but be safe)
usort( $seasons, fn( $a, $b ) => $b['year'] - $a['year'] );

// Figure out the "upcoming" label from the intro posters' season.
$upcoming_label = '';
if ( $intro_posters ) {
    // The intro posters belong to whatever season is mentioned in the intro text — most recent + 1.
    $top_year = $seasons[0]['year'] ?? 0;
    if ( $top_year ) {
        $upcoming_label = sprintf( '%d-%d', $top_year + 1, $top_year + 2 );
    }
}
?>

<style>
  .ots-page { max-width: 1100px; margin: 0 auto; padding: 0 var(--pad); }
  .ots-hero { text-align: center; padding: 4rem 0 2rem; }
  .ots-hero h1 { margin-bottom: 1rem; font-size: clamp(2rem, 4vw, 3rem); }
  .ots-hero .ots-lede { color: var(--color-muted); max-width: 760px; margin: 0 auto; font-size: 1.05rem; line-height: 1.6; }
  .ots-hero .ots-lede p { margin: 0 0 1rem; }
  .ots-hero .ots-lede p:last-child { margin-bottom: 0; }

  .ots-section { margin: 3rem 0; }
  .ots-section-header {
    display: flex; align-items: center; gap: 1rem;
    margin-bottom: 1.5rem;
  }
  .ots-section-header h2 {
    margin: 0; font-size: 1.5rem; letter-spacing: 0.02em;
    color: var(--color-text); white-space: nowrap;
  }
  .ots-section-header .ots-rule {
    flex: 1; height: 2px; background: var(--color-line);
  }
  .ots-section-header .ots-count {
    color: var(--color-muted); font-size: 0.85rem; font-weight: 500;
    text-transform: uppercase; letter-spacing: 0.08em;
  }

  /* Featured upcoming row: bigger posters */
  .ots-featured {
    background: linear-gradient(180deg, #fff 0%, var(--color-soft) 100%);
    border: 1px solid var(--color-line); border-radius: 6px;
    padding: 2rem; margin-bottom: 3rem;
  }
  .ots-featured .ots-section-header h2 {
    color: var(--color-accent); font-family: var(--font-display);
    text-transform: uppercase;
  }
  .ots-featured .ots-grid {
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  }

  /* Posters can be any aspect (some are 2:3, some are square, some landscape) —
     let each one display at its native ratio and align the grid by row baseline.
     No background or shadow on the container, so empty space isn't framed. */
  .ots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 1.5rem;
    align-items: start;
  }
  .ots-poster {
    position: relative; display: block; overflow: hidden;
    background: transparent;
    border-radius: 3px;
  }
  .ots-poster img {
    width: 100%; height: auto; display: block;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
  }
  /* Hover treatment only on clickable (linked) posters */
  a.ots-poster {
    transition: transform 0.25s;
  }
  a.ots-poster img {
    transition: transform 0.35s ease, box-shadow 0.25s;
  }
  a.ots-poster:hover { transform: translateY(-4px); }
  a.ots-poster:hover img {
    transform: scale(1.02);
    box-shadow: 0 12px 28px rgba(0,0,0,0.18);
  }

  /* Linked posters: a centered button fades in on hover; image dims slightly. */
  a.ots-poster img { transition: transform 0.35s ease, box-shadow 0.25s, filter 0.25s; }
  a.ots-poster:hover img { filter: brightness(0.75); }
  a.ots-poster .ots-cta {
    position: absolute;
    top: 50%; left: 50%; transform: translate(-50%, -50%) scale(0.9);
    background: var(--color-accent); color: #fff;
    padding: 0.7rem 1.4rem;
    font-family: var(--font-display); font-weight: 700; font-size: 0.85rem;
    text-transform: uppercase; letter-spacing: 0.08em;
    border-radius: 3px;
    opacity: 0; transition: opacity 0.25s, transform 0.25s;
    pointer-events: none;
    white-space: nowrap;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
  }
  a.ots-poster:hover .ots-cta {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1);
  }

  @media (max-width: 600px) {
    .ots-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
    .ots-featured { padding: 1.25rem; }
    .ots-featured .ots-grid { grid-template-columns: repeat(2, 1fr); }
  }
</style>

<div class="ots-page">

  <header class="ots-hero">
    <h1><?php the_title(); ?></h1>
    <?php if ( $intro_text ) : ?>
      <div class="ots-lede"><?php echo wp_kses_post( $intro_text ); ?></div>
    <?php endif; ?>
  </header>

  <?php if ( $intro_posters ) : ?>
    <section class="ots-section ots-featured">
      <div class="ots-section-header">
        <h2>Upcoming<?php if ( $upcoming_label ) echo ' &middot; ' . esc_html( $upcoming_label ); ?></h2>
        <span class="ots-rule"></span>
        <span class="ots-count"><?php echo count( $intro_posters ); ?> Title<?php echo count( $intro_posters ) !== 1 ? 's' : ''; ?></span>
      </div>
      <div class="ots-grid">
        <?php foreach ( $intro_posters as $p ) :
          // Upcoming-season posters always link out — use the specific URL if present,
          // otherwise fall back to TLT's Ludus storefront.
          $href = $p['href'] ?: 'https://tlt.ludus.com/';
        ?>
          <a href="<?php echo esc_url( $href ); ?>" target="_blank" rel="noopener" class="ots-poster">
            <img src="<?php echo esc_url( $p['src'] ); ?>" alt="">
            <span class="ots-cta">Get Tickets &rarr;</span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php foreach ( $seasons as $season ) : if ( ! $season['posters'] ) continue; ?>
    <section class="ots-section">
      <div class="ots-section-header">
        <h2><?php echo esc_html( $season['label'] ); ?></h2>
        <span class="ots-rule"></span>
        <span class="ots-count"><?php echo count( $season['posters'] ); ?> Title<?php echo count( $season['posters'] ) !== 1 ? 's' : ''; ?></span>
      </div>
      <div class="ots-grid">
        <?php foreach ( $season['posters'] as $p ) :
          $tag  = $p['href'] ? 'a' : 'div';
          $attr = $p['href'] ? sprintf( ' href="%s" target="_blank" rel="noopener"', esc_url( $p['href'] ) ) : '';
        ?>
          <<?php echo $tag . $attr; ?> class="ots-poster">
            <img src="<?php echo esc_url( $p['src'] ); ?>" alt="">
            <?php if ( $p['href'] ) : ?><span class="ots-cta">Get Tickets &rarr;</span><?php endif; ?>
          </<?php echo $tag; ?>>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>

</div>

<?php get_footer(); ?>
