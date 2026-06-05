<?php
/**
 * Template Name: Calendar
 *
 * Month grid + agenda of everything happening at TLT — performances and
 * auditions (auto-pulled from shows) plus events (tlt_event). Navigate months
 * with ?ym=YYYY-MM. Data layer: includes/calendar.php.
 */
get_header();

$today = function_exists( 'tlt_today' ) ? tlt_today() : current_time( 'Y-m-d' );

// Target month from /calendar/YYYY-MM/ (pretty) or ?ym= (fallback), else "today".
$ym = get_query_var( 'tlt_cal_ym' );
if ( ! $ym && isset( $_GET['ym'] ) ) $ym = sanitize_text_field( wp_unslash( $_GET['ym'] ) );
if ( ! preg_match( '/^\d{4}-\d{2}$/', $ym ) ) $ym = substr( $today, 0, 7 );
try { $month = new DateTime( $ym . '-01' ); } catch ( Exception $e ) { $month = new DateTime( substr( $today, 0, 7 ) . '-01' ); }

$first_dow    = (int) $month->format( 'w' );          // 0=Sun … 6=Sat
$days_in_mo   = (int) $month->format( 't' );
$grid_start   = ( clone $month )->modify( "-{$first_dow} days" );
$total_cells  = (int) ( ceil( ( $first_dow + $days_in_mo ) / 7 ) * 7 );
$grid_end     = ( clone $grid_start )->modify( '+' . ( $total_cells - 1 ) . ' days' );

$entries = tlt_calendar_entries( $grid_start->format( 'Y-m-d' ), $grid_end->format( 'Y-m-d' ) );
$by_day  = tlt_calendar_group_by_day( $entries );
$types   = tlt_calendar_types();

$prev = ( clone $month )->modify( '-1 month' )->format( 'Y-m' );
$next = ( clone $month )->modify( '+1 month' )->format( 'Y-m' );

// Which types actually appear this month (for the legend).
$present_types = [];
foreach ( $entries as $e ) $present_types[ $e['type'] ] = true;
?>
<style>
  .cal { max-width: 1040px; margin: 0 auto; padding: 0 var(--pad); }
  .cal-head { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin: 2.5rem 0 1rem; }
  .cal-head h1 { margin:0; font-size: clamp(1.5rem, 4vw, 2.2rem); }
  .cal-nav { display:flex; gap:.5rem; }
  .cal-nav a, .cal-today a { display:inline-flex; align-items:center; justify-content:center; min-width:40px; height:40px; padding:0 .9rem;
    border:1px solid var(--color-line); border-radius:6px; color:var(--color-text); text-decoration:none; font-weight:600; background:#fff; }
  .cal-nav a:hover, .cal-today a:hover { border-color:var(--color-accent); color:var(--color-accent); }
  .cal-legend { display:flex; flex-wrap:wrap; gap:.5rem 1.1rem; margin:.5rem 0 1.5rem; font-size:.85rem; color:var(--color-muted); }
  .cal-legend span { display:inline-flex; align-items:center; gap:.4rem; }
  .cal-legend i { width:11px; height:11px; border-radius:50%; display:inline-block; }
  .cal-grid { display:grid; grid-template-columns: repeat(7, minmax(0,1fr)); border:1px solid var(--color-line); border-radius:8px; overflow:hidden; background:var(--color-line); gap:1px; }
  .cal-dow { background:#fafafa; text-align:center; font-size:.72rem; text-transform:uppercase; letter-spacing:.05em; color:var(--color-muted); padding:.5rem 0; font-weight:700; }
  .cal-cell { background:#fff; min-height:104px; padding:.35rem .4rem; }
  .cal-cell.is-out { background:#fafafa; }
  .cal-cell.is-today { background:#fff7f8; box-shadow: inset 0 0 0 2px var(--color-accent); }
  .cal-cell .d { font-size:.8rem; font-weight:600; color:var(--color-text); }
  .cal-cell.is-out .d { color:#bbb; }
  .cal-chip { display:block; margin-top:.25rem; padding:.22rem .4rem; border-radius:4px;
    font-size:.72rem; line-height:1.25; color:#fff; text-decoration:none; overflow:hidden; }
  .cal-chip:hover { text-decoration:none; filter:brightness(1.08); }
  .cal-chip .n { display:block; font-weight:700; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .cal-chip .t { display:block; font-size:.66rem; opacity:.92; white-space:nowrap; }
  /* Agenda (mobile + supplemental) */
  .cal-agenda { margin:2rem 0 1rem; }
  .cal-agenda h2 { font-size:1.1rem; margin:0 0 .35rem; }
  .cal-agenda-note { color:var(--color-muted); font-size:.85rem; margin:0 0 1.1rem; }
  .cal-day { display:flex; gap:1rem; padding:.8rem 0; border-top:1px solid var(--color-line); }
  .cal-day__date { flex:0 0 64px; text-align:center; }
  .cal-day__date .dow { font-size:.7rem; text-transform:uppercase; color:var(--color-muted); }
  .cal-day__date .num { font-size:1.4rem; font-weight:700; line-height:1; }
  .cal-day__items { flex:1; }
  .cal-item { display:flex; align-items:baseline; gap:.6rem; padding:.25rem 0; }
  .cal-item a { color:var(--color-text); text-decoration:none; font-weight:600; }
  .cal-item a:hover { color:var(--color-accent); }
  .cal-item .tm { flex:0 0 auto; font-size:.85rem; color:var(--color-muted); min-width:64px; }
  .cal-item .dot { width:9px; height:9px; border-radius:50%; flex:0 0 auto; align-self:center; }
  .cal-item .loc { font-size:.8rem; color:var(--color-muted); }
  .cal-empty { color:var(--color-muted); padding:2rem 0; text-align:center; }
  @media (max-width: 720px) { .cal-grid, .cal-dow { display:none; } .cal-agenda { margin-top:1rem; } }
  @media (min-width: 721px) { .cal-agenda { border-top:2px solid var(--color-line); padding-top:1.5rem; } }
</style>

<div class="cal">
  <div class="cal-head">
    <h1><?php echo esc_html( $month->format( 'F Y' ) ); ?></h1>
    <div style="display:flex; gap:.5rem; align-items:center">
      <span class="cal-today"><a href="<?php echo esc_url( tlt_calendar_url() ); ?>">Today</a></span>
      <span class="cal-nav">
        <a href="<?php echo esc_url( tlt_calendar_url( $prev ) ); ?>" aria-label="Previous month">&larr;</a>
        <a href="<?php echo esc_url( tlt_calendar_url( $next ) ); ?>" aria-label="Next month">&rarr;</a>
      </span>
    </div>
  </div>

  <?php if ( $present_types ) : ?>
    <div class="cal-legend">
      <?php foreach ( $present_types as $t => $_ ) : $info = $types[ $t ] ?? $types['other']; ?>
        <span><i style="background:<?php echo esc_attr( $info['color'] ); ?>"></i><?php echo esc_html( $info['label'] ); ?></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Month grid (desktop) -->
  <div class="cal-grid" role="grid" aria-label="Month grid">
    <?php foreach ( [ 'Sun','Mon','Tue','Wed','Thu','Fri','Sat' ] as $dow ) : ?>
      <div class="cal-dow"><?php echo esc_html( $dow ); ?></div>
    <?php endforeach; ?>
    <?php
    $cur = clone $grid_start;
    for ( $i = 0; $i < $total_cells; $i++ ) :
        $d        = $cur->format( 'Y-m-d' );
        $is_out   = $cur->format( 'Y-m' ) !== $month->format( 'Y-m' );
        $is_today = ( $d === $today );
        $items    = $by_day[ $d ] ?? [];
        $classes  = 'cal-cell' . ( $is_out ? ' is-out' : '' ) . ( $is_today ? ' is-today' : '' );
    ?>
      <div class="<?php echo $classes; ?>">
        <div class="d"><?php echo (int) $cur->format( 'j' ); ?></div>
        <?php foreach ( array_slice( $items, 0, 4 ) as $e ) :
            $info = $types[ $e['type'] ] ?? $types['other']; ?>
          <a class="cal-chip" href="<?php echo esc_url( $e['url'] ); ?>"<?php echo ! empty( $e['external'] ) ? ' target="_blank" rel="noopener"' : ''; ?> style="background:<?php echo esc_attr( $info['color'] ); ?>" title="<?php echo esc_attr( ( $e['time'] ? $e['time'] . ' — ' : '' ) . tlt_calendar_agenda_label( $e ) ); ?>">
            <span class="n"><?php echo esc_html( $e['title'] ); ?></span>
            <?php if ( $e['time'] ) : ?><span class="t"><?php echo esc_html( $e['time'] ); ?></span><?php endif; ?>
          </a>
        <?php endforeach; ?>
        <?php if ( count( $items ) > 4 ) : ?>
          <div style="font-size:.7rem;color:var(--color-muted);margin-top:.2rem">+<?php echo count( $items ) - 4; ?> more</div>
        <?php endif; ?>
      </div>
    <?php $cur->modify( '+1 day' ); endfor; ?>
  </div>

  <!-- Agenda (always shown; primary view on mobile) -->
  <div class="cal-agenda">
    <h2><?php echo esc_html( $month->format( 'F' ) ); ?> at a glance</h2>
    <?php
    $month_days = array_filter( $by_day, function ( $k ) use ( $month ) {
        return substr( $k, 0, 7 ) === $month->format( 'Y-m' );
    }, ARRAY_FILTER_USE_KEY );
    ksort( $month_days );
    if ( ! $month_days ) : ?>
      <p class="cal-empty">Nothing on the calendar this month yet.</p>
    <?php else : ?>
      <p class="cal-agenda-note">All events are at Tacoma Little Theatre unless otherwise noted.</p>
      <?php foreach ( $month_days as $date => $items ) :
        $dt = new DateTime( $date ); ?>
      <div class="cal-day">
        <div class="cal-day__date">
          <div class="dow"><?php echo esc_html( $dt->format( 'D' ) ); ?></div>
          <div class="num"><?php echo (int) $dt->format( 'j' ); ?></div>
        </div>
        <div class="cal-day__items">
          <?php foreach ( $items as $e ) : $info = $types[ $e['type'] ] ?? $types['other']; ?>
            <div class="cal-item">
              <span class="dot" style="background:<?php echo esc_attr( $info['color'] ); ?>"></span>
              <span class="tm"><?php echo $e['time'] ? esc_html( $e['time'] ) : 'All day'; ?></span>
              <span><a href="<?php echo esc_url( $e['url'] ); ?>"<?php echo ! empty( $e['external'] ) ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( tlt_calendar_agenda_label( $e ) ); ?></a>
                <?php if ( $e['location'] && $e['location'] !== 'Tacoma Little Theatre' ) : ?>
                  <span class="loc">· <?php echo esc_html( $e['location'] ); ?></span>
                <?php endif; ?>
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php get_footer();
