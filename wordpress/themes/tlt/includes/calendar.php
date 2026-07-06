<?php
/**
 * Calendar data layer. Aggregates dated entries from three sources into one
 * normalised list the /calendar/ page renders:
 *   - performances  — from each show's `show_performances` schedule
 *   - auditions     — from each show's `show_audition_schedule`
 *   - events        — from tlt_event records (galas, rentals, ClubTLT, …)
 *
 * Each entry: [ 'date'=>'Y-m-d', 'time'=>'7:30 PM'|'', 'minutes'=>int (for sort),
 *               'title'=>…, 'type'=>'performance'|'audition'|<event-cat>,
 *               'url'=>…, 'location'=>… ]
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Pretty month URLs: /calendar/2026-08/ instead of /calendar/?ym=2026-08.
 * Maps to the Calendar page with a tlt_cal_ym query var.
 */
add_action( 'init', function () {
    add_rewrite_tag( '%tlt_cal_ym%', '([0-9]{4}-[0-9]{2})' );
    add_rewrite_rule( '^calendar/([0-9]{4}-[0-9]{2})/?$', 'index.php?pagename=calendar&tlt_cal_ym=$matches[1]', 'top' );
} );

/** Build a pretty calendar URL for a given YYYY-MM (or the base for "today"). */
function tlt_calendar_url( $ym = '' ) {
    return $ym ? home_url( '/calendar/' . $ym . '/' ) : home_url( '/calendar/' );
}

// Default audition signup link (TLT's Casting Manager). A show can override via
// its show_audition_signup_url meta.
if ( ! defined( 'TLT_AUDITION_SIGNUP_URL' ) ) {
    define( 'TLT_AUDITION_SIGNUP_URL', 'https://castingmanager.com/profile/5b7c8e3901d88' );
}

/** True only for off-site (different-host) absolute URLs — those open in a new tab. */
function tlt_is_external_url( $url ) {
    if ( ! $url || ! preg_match( '#^https?://#i', $url ) ) return false; // relative = internal
    return stripos( $url, (string) parse_url( home_url(), PHP_URL_HOST ) ) === false;
}

/**
 * Label for the agenda ("at a glance") list. The grid badges show just the
 * name (their colour signals the type); the agenda spells the type out.
 */
function tlt_calendar_agenda_label( $e ) {
    $prefix = '';
    if ( $e['type'] === 'performance' ) $prefix = 'Performance: ';
    elseif ( $e['type'] === 'audition' ) $prefix = 'Auditions: ';
    elseif ( $e['type'] === 'education_performance' ) $prefix = 'Education Performance: ';
    return $prefix . $e['title'];
}

/** Colour + label per entry type (event categories fall back to 'event'). */
function tlt_calendar_types() {
    return [
        'performance' => [ 'label' => 'Performance', 'color' => '#a8122b' ], // accent red
        'audition'    => [ 'label' => 'Audition',    'color' => '#1f6f8b' ], // teal-blue
        'education_performance' => [ 'label' => 'Education Performance', 'color' => '#ea580c' ], // orange
        'fundraiser'  => [ 'label' => 'Fundraiser',  'color' => '#8a5a00' ], // amber
        'off_the_shelf'=>[ 'label' => 'Off the Shelf','color' => '#3a7d44' ], // green
        'education'   => [ 'label' => 'Education',    'color' => '#2f6f9f' ], // blue
        'rental'      => [ 'label' => 'Rental',       'color' => '#777' ],
        'community'   => [ 'label' => 'Community',     'color' => '#b5651d' ],
        'special'     => [ 'label' => 'Special Event','color' => '#444' ],
        'other'       => [ 'label' => 'Event',         'color' => '#444' ],
    ];
}

/** Minutes-since-midnight from a "7:30 PM" style string (for stable ordering). */
function tlt_time_to_minutes( $t ) {
    $t = trim( $t );
    if ( $t === '' ) return -1; // all-day / untimed sorts first
    if ( preg_match( '/(\d{1,2}):(\d{2})\s*([ap]m)?/i', $t, $m ) ) {
        $h = (int) $m[1]; $min = (int) $m[2];
        $ap = isset( $m[3] ) ? strtolower( $m[3] ) : '';
        if ( $ap === 'pm' && $h < 12 ) $h += 12;
        if ( $ap === 'am' && $h === 12 ) $h = 0;
        return $h * 60 + $min;
    }
    return -1;
}

/**
 * Parse a schedule textarea (one entry per line) into [ ['date','time','location'], … ].
 * Line format: "YYYY-MM-DD 7:30 PM" or "YYYY-MM-DD 6:00 PM @ Location".
 */
function tlt_parse_schedule( $text ) {
    $out = [];
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
        $line = trim( $line );
        if ( $line === '' ) continue;
        if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2})\b(.*)$/', $line, $m ) ) continue;
        $date = $m[1];
        $rest = trim( $m[2] );
        $location = '';
        if ( strpos( $rest, '@' ) !== false ) {
            list( $time, $location ) = array_map( 'trim', explode( '@', $rest, 2 ) );
        } else {
            $time = $rest;
        }
        $out[] = [ 'date' => $date, 'time' => $time, 'location' => $location ];
    }
    return $out;
}

/**
 * All calendar entries with a date in [$from, $to] (inclusive, Y-m-d).
 */
function tlt_calendar_entries( $from, $to ) {
    $entries = [];
    $in_range = function ( $d ) use ( $from, $to ) { return $d >= $from && $d <= $to; };

    // ---- Performances + auditions (from shows) ----
    $shows = get_posts( [
        'post_type'      => 'tlt_show',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'OR',
            [ 'key' => 'show_performances', 'value' => '', 'compare' => '!=' ],
            [ 'key' => 'show_audition_schedule', 'value' => '', 'compare' => '!=' ],
        ],
    ] );
    foreach ( $shows as $show ) {
        $url   = get_permalink( $show );
        $title = get_the_title( $show );
        $venue = get_post_meta( $show->ID, 'show_venue_name', true );
        // Map the show's program type to a calendar entry type so non-mainstage
        // shows (Off the Shelf, ClubTLT, Education, Special events, etc.) get
        // their own colour + label on the calendar instead of looking like a
        // mainstage performance.
        $ptype = get_post_meta( $show->ID, 'show_program_type', true ) ?: 'mainstage';
        $perf_type_map = [
            'mainstage'             => 'performance',
            'off_the_shelf'         => 'off_the_shelf',
            'murder_mystery_dinner' => 'special',
            'education'             => 'education_performance',
            'special'               => 'special',
        ];
        $perf_type = $perf_type_map[ $ptype ] ?? 'performance';
        foreach ( tlt_parse_schedule( get_post_meta( $show->ID, 'show_performances', true ) ) as $p ) {
            if ( ! $in_range( $p['date'] ) ) continue;
            $entries[] = [
                'date' => $p['date'], 'time' => $p['time'], 'minutes' => tlt_time_to_minutes( $p['time'] ),
                'title' => $title, 'type' => $perf_type, 'url' => $url, 'external' => false,
                'location' => $p['location'] ?: ( $venue ?: 'Tacoma Little Theatre' ),
            ];
        }
        // Auditions link to the casting site (per-show signup URL, else the TLT default).
        $aud_url = get_post_meta( $show->ID, 'show_audition_signup_url', true ) ?: TLT_AUDITION_SIGNUP_URL;
        foreach ( tlt_parse_schedule( get_post_meta( $show->ID, 'show_audition_schedule', true ) ) as $a ) {
            if ( ! $in_range( $a['date'] ) ) continue;
            $entries[] = [
                'date' => $a['date'], 'time' => $a['time'], 'minutes' => tlt_time_to_minutes( $a['time'] ),
                'title' => $title, 'type' => 'audition', 'url' => $aud_url, 'external' => true,
                'location' => $a['location'] ?: 'Tacoma Little Theatre',
            ];
        }
    }

    // ---- Events ----
    $events = get_posts( [
        'post_type'      => 'tlt_event',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_key'       => 'event_start_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    ] );
    foreach ( $events as $ev ) {
        $start = get_post_meta( $ev->ID, 'event_start_date', true );
        if ( ! $start ) continue;
        $end  = get_post_meta( $ev->ID, 'event_end_date', true ) ?: $start;
        $time = get_post_meta( $ev->ID, 'event_time', true );
        $cat  = get_post_meta( $ev->ID, 'event_category', true ) ?: 'special';
        $loc  = get_post_meta( $ev->ID, 'event_location', true ) ?: 'Tacoma Little Theatre';
        $ext  = get_post_meta( $ev->ID, 'event_url', true );
        $link = $ext ?: get_permalink( $ev );
        // Walk each day of the (possibly multi-day) event.
        try {
            $cursor = new DateTime( $start );
            $last   = new DateTime( $end );
        } catch ( Exception $e ) { continue; }
        $guard = 0;
        while ( $cursor <= $last && $guard++ < 90 ) {
            $d = $cursor->format( 'Y-m-d' );
            if ( $in_range( $d ) ) {
                $entries[] = [
                    'date' => $d, 'time' => $time, 'minutes' => tlt_time_to_minutes( $time ),
                    'title' => get_the_title( $ev ), 'type' => $cat, 'url' => $link,
                    'external' => tlt_is_external_url( $link ), 'location' => $loc,
                ];
            }
            $cursor->modify( '+1 day' );
        }
    }

    // ---- Promotions flagged "Also show on the calendar" ----
    $promos = get_posts( [
        'post_type'      => 'tlt_promotion',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_key'       => 'promo_on_calendar',
        'meta_value'     => '1',
    ] );
    foreach ( $promos as $promo ) {
        $schedule = tlt_parse_schedule( get_post_meta( $promo->ID, 'promo_event_schedule', true ) );
        if ( ! $schedule ) continue;
        $cat   = get_post_meta( $promo->ID, 'promo_event_category', true ) ?: 'special';
        $loc   = get_post_meta( $promo->ID, 'promo_event_location', true ) ?: 'Tacoma Little Theatre';
        $title = get_the_title( $promo );
        // Link to the promo's button URL, else its linked show, else nothing.
        $link = get_post_meta( $promo->ID, 'promo_cta_url', true );
        if ( ! $link ) {
            $ls = (int) get_post_meta( $promo->ID, 'promo_linked_show', true );
            if ( $ls ) $link = get_permalink( $ls );
        }
        foreach ( $schedule as $s ) {
            if ( ! $in_range( $s['date'] ) ) continue;
            $entries[] = [
                'date' => $s['date'], 'time' => $s['time'], 'minutes' => tlt_time_to_minutes( $s['time'] ),
                'title' => $title, 'type' => $cat, 'url' => $link,
                'external' => tlt_is_external_url( $link ),
                'location' => $s['location'] ?: $loc,
            ];
        }
    }

    // Sort by date, then time.
    usort( $entries, function ( $a, $b ) {
        return [ $a['date'], $a['minutes'] ] <=> [ $b['date'], $b['minutes'] ];
    } );
    return $entries;
}

/** Group entries by Y-m-d date. */
function tlt_calendar_group_by_day( $entries ) {
    $by = [];
    foreach ( $entries as $e ) $by[ $e['date'] ][] = $e;
    return $by;
}
