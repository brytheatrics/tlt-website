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
            'name'          => 'Events',
            'singular_name' => 'Event',
            'add_new_item'  => 'Add New Event',
            'edit_item'     => 'Edit Event',
            'menu_name'     => 'Events',
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
        'club_tlt'      => 'ClubTLT',
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
            'show_in_rest'  => true,
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
