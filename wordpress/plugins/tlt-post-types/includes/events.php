<?php
/**
 * tlt_event — calendar events that aren't productions: galas, fundraisers,
 * rentals, ClubTLT, Off-the-Shelf readings, camp showcases, community nights.
 * Productions (performances) and auditions come from tlt_show records instead;
 * this type is for everything Chris adds by hand.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------- Register the post type ---------- */
add_action( 'init', function () {
    register_post_type( 'tlt_event', [
        'labels' => [
            'name'          => 'Calendar',
            'singular_name' => 'Event',
            'add_new_item'  => 'Add New Event',
            'edit_item'     => 'Edit Event',
            'menu_name'     => 'Calendar',
            'all_items'     => 'All Events',
        ],
        'public'       => true,
        'show_in_rest' => true,
        'has_archive'  => false,
        'menu_icon'    => 'dashicons-calendar-alt',
        'supports'     => [ 'title', 'editor', 'thumbnail', 'revisions', 'custom-fields' ],
        'rewrite'      => [ 'slug' => 'events', 'with_front' => false ],
    ] );
} );

/* ---------- Categories (for colour + filtering on the calendar) ---------- */
function tlt_event_categories() {
    return [
        'special'       => 'Special Event',
        'fundraiser'    => 'Fundraiser / Gala',
        'off_the_shelf' => 'Off the Shelf',
        'education_performance' => 'Education Performance',
        'education'     => 'Education / Class',
        'rental'        => 'Rental',
        'community'     => 'Community',
        'other'         => 'Other',
    ];
}

/* ---------- Meta ---------- */
add_action( 'init', function () {
    $fields = [
        'event_start_date' => [ 'string', 'Start date (YYYY-MM-DD)' ],
        'event_end_date'   => [ 'string', 'End date (YYYY-MM-DD) — leave blank for single-day' ],
        'event_time'       => [ 'string', 'Time text, e.g. "7:30 PM" (blank = all day)' ],
        'event_location'   => [ 'string', 'Location (default: Tacoma Little Theatre)' ],
        'event_url'        => [ 'string', 'Tickets / info / registration URL' ],
        'event_category'   => [ 'string', 'Category key (see tlt_event_categories)' ],
    ];
    foreach ( $fields as $key => [ $type, $desc ] ) {
        register_post_meta( 'tlt_event', $key, [
            'type'          => $type,
            'description'   => $desc,
            'single'        => true,
            // Managed via the classic meta box — keep OUT of REST so the block
            // editor can't save a stale copy over the meta-box changes.
            'show_in_rest'  => false,
            'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
        ] );
    }
} );

/* ---------- Admin meta box ---------- */
add_action( 'add_meta_boxes', function () {
    add_meta_box( 'tlt_event_meta', 'Event Details', 'tlt_render_event_meta', 'tlt_event', 'normal', 'high' );
} );

function tlt_render_event_meta( $post ) {
    wp_nonce_field( 'tlt_event_meta', 'tlt_event_nonce' );
    $get = function ( $k ) use ( $post ) { return esc_attr( get_post_meta( $post->ID, $k, true ) ); };
    $cat = get_post_meta( $post->ID, 'event_category', true ) ?: 'special';
    echo '<table class="form-table">';
    echo "<tr><th><label for='event_start_date'>Start Date</label></th><td><input type='date' id='event_start_date' name='event_start_date' value='{$get('event_start_date')}'></td></tr>";
    echo "<tr><th><label for='event_end_date'>End Date</label></th><td><input type='date' id='event_end_date' name='event_end_date' value='{$get('event_end_date')}'> <span class='description'>Optional — only for multi-day events.</span></td></tr>";
    echo "<tr><th><label for='event_time'>Time</label></th><td><input type='text' id='event_time' name='event_time' value='{$get('event_time')}' placeholder='7:30 PM' style='width:200px'> <span class='description'>Leave blank for an all-day event.</span></td></tr>";
    echo "<tr><th><label for='event_location'>Location</label></th><td><input type='text' id='event_location' name='event_location' value='{$get('event_location')}' placeholder='Tacoma Little Theatre' style='width:100%'></td></tr>";
    echo "<tr><th><label for='event_url'>Link (tickets / info)</label></th><td><input type='url' id='event_url' name='event_url' value='{$get('event_url')}' style='width:100%' placeholder='https://…'></td></tr>";
    echo "<tr><th><label for='event_category'>Category</label></th><td><select id='event_category' name='event_category'>";
    foreach ( tlt_event_categories() as $v => $label ) {
        echo "<option value='" . esc_attr( $v ) . "' " . selected( $v, $cat, false ) . ">" . esc_html( $label ) . "</option>";
    }
    echo "</select></td></tr>";
    echo '</table>';
}

add_action( 'save_post_tlt_event', function ( $post_id ) {
    if ( ! isset( $_POST['tlt_event_nonce'] ) || ! wp_verify_nonce( $_POST['tlt_event_nonce'], 'tlt_event_meta' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    foreach ( [ 'event_start_date','event_end_date','event_time','event_location','event_url','event_category' ] as $k ) {
        if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $k, sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) );
    }
} );

/* ---------------------------------------------------------------------------
 * Calendar Overview — a read-only admin page (under Calendar) listing every
 * source that feeds the public /calendar/, with quick edit links. Show
 * performances & auditions edit on the Show; events and calendar-promotions on
 * their own records. Single source of truth — nothing is duplicated.
 * ------------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=tlt_event',
        'Calendar Overview', 'Overview (everything)', 'edit_posts', 'tlt-calendar-overview',
        'tlt_render_calendar_overview',
        0 // first item under Calendar
    );
}, 99 );

/** Earliest date >= $today from a parsed schedule (tlt_parse_schedule output). */
function tlt_next_sched_date( $sched, $today ) {
    $dates = [];
    foreach ( (array) $sched as $s ) { if ( ! empty( $s['date'] ) ) $dates[] = $s['date']; }
    sort( $dates );
    foreach ( $dates as $d ) { if ( $d >= $today ) return $d; }
    return '';
}

function tlt_render_calendar_overview() {
    $today = function_exists( 'tlt_today' ) ? tlt_today() : current_time( 'Y-m-d' );
    $parse = function_exists( 'tlt_parse_schedule' ) ? 'tlt_parse_schedule' : null;
    $fmt   = function ( $d ) { $ts = strtotime( $d ); return $ts ? date_i18n( 'M j, Y', $ts ) : $d; };

    echo '<div class="wrap"><h1>Calendar Overview</h1>';
    echo '<p>Everything feeding the public <a href="' . esc_url( home_url( '/calendar/' ) ) . '" target="_blank" rel="noopener">/calendar/</a>. Edit show dates on the Show; events and calendar-promotions on their own records. Showing items from <strong>' . esc_html( $fmt( $today ) ) . '</strong> onward.</p>';

    // ---- Shows (performances + auditions) ----
    echo '<h2 style="margin-top:1.5em">Shows</h2>';
    echo '<table class="widefat striped"><thead><tr><th>Show</th><th>Next performance</th><th>Next audition</th><th></th></tr></thead><tbody>';
    $shows = get_posts( [
        'post_type' => 'tlt_show', 'post_status' => 'publish', 'posts_per_page' => -1,
        'meta_query' => [ 'relation' => 'OR',
            [ 'key' => 'show_performances', 'value' => '', 'compare' => '!=' ],
            [ 'key' => 'show_audition_schedule', 'value' => '', 'compare' => '!=' ],
        ],
    ] );
    $any = false;
    foreach ( $shows as $s ) {
        $perf = $parse ? tlt_parse_schedule( get_post_meta( $s->ID, 'show_performances', true ) ) : [];
        $aud  = $parse ? tlt_parse_schedule( get_post_meta( $s->ID, 'show_audition_schedule', true ) ) : [];
        $np = tlt_next_sched_date( $perf, $today );
        $na = tlt_next_sched_date( $aud, $today );
        if ( ! $np && ! $na ) continue;
        $any = true;
        printf(
            '<tr><td><strong>%s</strong></td><td>%s</td><td>%s</td><td><a class="button button-small" href="%s">Edit dates</a></td></tr>',
            esc_html( get_the_title( $s ) ),
            $np ? esc_html( $fmt( $np ) ) : '&mdash;',
            $na ? esc_html( $fmt( $na ) ) : '&mdash;',
            esc_url( get_edit_post_link( $s->ID ) )
        );
    }
    if ( ! $any ) echo '<tr><td colspan="4"><em>No upcoming show dates.</em></td></tr>';
    echo '</tbody></table>';

    // ---- Events ----
    echo '<h2 style="margin-top:1.5em">Events <a class="page-title-action" href="' . esc_url( admin_url( 'post-new.php?post_type=tlt_event' ) ) . '">Add New</a></h2>';
    echo '<table class="widefat striped"><thead><tr><th>Event</th><th>Date</th><th>Category</th><th></th></tr></thead><tbody>';
    $events = get_posts( [ 'post_type' => 'tlt_event', 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_key' => 'event_start_date', 'orderby' => 'meta_value', 'order' => 'ASC' ] );
    $any = false;
    foreach ( $events as $ev ) {
        $start = get_post_meta( $ev->ID, 'event_start_date', true );
        $end   = get_post_meta( $ev->ID, 'event_end_date', true ) ?: $start;
        if ( $end && $end < $today ) continue;
        $any = true;
        $cats = function_exists( 'tlt_event_categories' ) ? tlt_event_categories() : [];
        $cat  = get_post_meta( $ev->ID, 'event_category', true );
        printf(
            '<tr><td><strong>%s</strong></td><td>%s</td><td>%s</td><td><a class="button button-small" href="%s">Edit</a></td></tr>',
            esc_html( get_the_title( $ev ) ),
            $start ? esc_html( $fmt( $start ) ) : '&mdash;',
            esc_html( $cats[ $cat ] ?? $cat ),
            esc_url( get_edit_post_link( $ev->ID ) )
        );
    }
    if ( ! $any ) echo '<tr><td colspan="4"><em>No upcoming events.</em></td></tr>';
    echo '</tbody></table>';

    // ---- Promotions on the calendar ----
    echo '<h2 style="margin-top:1.5em">Promotions on the calendar</h2>';
    echo '<table class="widefat striped"><thead><tr><th>Promotion</th><th>Next date</th><th></th></tr></thead><tbody>';
    $promos = get_posts( [ 'post_type' => 'tlt_promotion', 'post_status' => 'publish', 'posts_per_page' => -1, 'meta_key' => 'promo_on_calendar', 'meta_value' => '1' ] );
    $any = false;
    foreach ( $promos as $p ) {
        $sched = $parse ? tlt_parse_schedule( get_post_meta( $p->ID, 'promo_event_schedule', true ) ) : [];
        $nd = tlt_next_sched_date( $sched, $today );
        if ( ! $nd ) continue;
        $any = true;
        printf(
            '<tr><td><strong>%s</strong></td><td>%s</td><td><a class="button button-small" href="%s">Edit</a></td></tr>',
            esc_html( get_the_title( $p ) ), esc_html( $fmt( $nd ) ), esc_url( get_edit_post_link( $p->ID ) )
        );
    }
    if ( ! $any ) echo '<tr><td colspan="3"><em>No promotions on the calendar.</em></td></tr>';
    echo '</tbody></table>';

    echo '</div>';
}
