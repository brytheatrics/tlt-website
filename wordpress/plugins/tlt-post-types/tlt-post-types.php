<?php
/**
 * Plugin Name: TLT Post Types
 * Description: Registers Show, Team, and News custom post types + structured meta fields for Tacoma Little Theatre.
 * Version:     1.0.0
 * Author:      TLT Migration
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------------------------------------------------------------------------
 * Custom Post Types
 * ------------------------------------------------------------------------- */

add_action( 'init', function () {

    // ---------- Shows ----------
    register_post_type( 'tlt_show', [
        'labels' => [
            'name'               => 'Shows',
            'singular_name'      => 'Show',
            'add_new_item'       => 'Add New Show',
            'edit_item'          => 'Edit Show',
            'menu_name'          => 'Shows',
            'all_items'          => 'All Shows',
        ],
        'public'              => true,
        'show_in_rest'         => true,            // enables block editor + REST API
        'has_archive'          => 'shows',
        'menu_icon'            => 'dashicons-tickets-alt',
        'supports'             => [ 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ],
        'rewrite'              => [ 'slug' => 'shows', 'with_front' => false ],
        'taxonomies'           => [ 'tlt_season', 'tlt_show_tag' ],
    ] );

    // ---------- Team (board + staff) ----------
    register_post_type( 'tlt_team', [
        'labels' => [
            'name'          => 'Team',
            'singular_name' => 'Team Member',
            'menu_name'     => 'Team',
        ],
        'public'       => true,
        'show_in_rest' => true,
        'has_archive'  => 'team',
        'menu_icon'    => 'dashicons-businessperson',
        'supports'     => [ 'title', 'editor', 'thumbnail', 'revisions', 'custom-fields' ],
        'rewrite'      => [ 'slug' => 'team', 'with_front' => false ],
        'taxonomies'   => [ 'tlt_team_role' ],
    ] );

    // News (uses built-in WP "post" type but we expose a 'news' rewrite)
    // We don't register a new post type for news; we just use core posts and
    // reroute /blog -> /news/ via theme.

    /* ---------- Taxonomies ---------- */

    register_taxonomy( 'tlt_season', [ 'tlt_show' ], [
        'label'          => 'Season',
        'public'         => true,
        'hierarchical'   => false,
        'show_in_rest'   => true,
        'rewrite'        => [ 'slug' => 'seasons' ],
    ] );

    register_taxonomy( 'tlt_show_tag', [ 'tlt_show' ], [
        'label'          => 'Show Tag',
        'public'         => true,
        'hierarchical'   => false,
        'show_in_rest'   => true,
        'rewrite'        => [ 'slug' => 'show-tags' ],
    ] );

    register_taxonomy( 'tlt_team_role', [ 'tlt_team' ], [
        'label'          => 'Role',
        'public'         => true,
        'hierarchical'   => true,
        'show_in_rest'   => true,
        'rewrite'        => [ 'slug' => 'roles' ],
    ] );
} );

/* ---------------------------------------------------------------------------
 * Meta fields (structured data per show / per team member)
 * Using register_post_meta makes them available in REST + block editor.
 * ------------------------------------------------------------------------- */

add_action( 'init', function () {

    // ----- Show meta -----
    $show_fields = [
        'show_director'          => [ 'string', 'Director' ],
        'show_music_director'    => [ 'string', 'Music Director' ],
        'show_choreographer'     => [ 'string', 'Choreographer' ],
        'show_open_date'         => [ 'string', 'Open date (YYYY-MM-DD)' ],
        'show_close_date'        => [ 'string', 'Close date (YYYY-MM-DD)' ],
        'show_run_time'          => [ 'string', 'Run time text' ],
        'show_age_rec'           => [ 'string', 'Age recommendation text' ],
        'show_content_warning'   => [ 'string', 'Content warning' ],
        'show_ticket_url'        => [ 'string', 'Buy tickets URL' ],
        'show_program_pdf_url'   => [ 'string', 'Program PDF URL' ],
        'show_cancelled'         => [ 'boolean', 'Was cancelled (e.g. COVID)' ],
        'show_program_type'      => [ 'string', 'mainstage|off-the-shelf|club-tlt|education|special' ],
        'show_legacy_url'        => [ 'string', 'Original Squarespace URL' ],
    ];
    foreach ( $show_fields as $key => [ $type, $desc ] ) {
        register_post_meta( 'tlt_show', $key, [
            'type'         => $type,
            'description'  => $desc,
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
        ] );
    }

    // ----- Team meta -----
    $team_fields = [
        'team_role_title'    => [ 'string', 'Role title (e.g. Managing Artistic Director)' ],
        'team_email'         => [ 'string', 'Public email' ],
        'team_pronouns'      => [ 'string', 'Pronouns' ],
        'team_is_board'      => [ 'boolean', 'Is board member' ],
        'team_is_staff'      => [ 'boolean', 'Is staff' ],
        'team_legacy_url'    => [ 'string', 'Original Squarespace URL' ],
    ];
    foreach ( $team_fields as $key => [ $type, $desc ] ) {
        register_post_meta( 'tlt_team', $key, [
            'type'         => $type,
            'description'  => $desc,
            'single'       => true,
            'show_in_rest' => true,
            'auth_callback' => function () { return current_user_can( 'edit_posts' ); },
        ] );
    }
} );

/* ---------------------------------------------------------------------------
 * Admin UI: simple meta box for non-block-editor editing
 * ------------------------------------------------------------------------- */

add_action( 'add_meta_boxes', function () {
    add_meta_box( 'tlt_show_meta', 'Show Details', 'tlt_render_show_meta', 'tlt_show', 'normal', 'high' );
    add_meta_box( 'tlt_team_meta', 'Team Member Details', 'tlt_render_team_meta', 'tlt_team', 'normal', 'high' );
} );

function tlt_render_show_meta( $post ) {
    wp_nonce_field( 'tlt_show_meta', 'tlt_show_nonce' );
    $fields = [
        'show_director'        => 'Director',
        'show_music_director'  => 'Music Director',
        'show_choreographer'   => 'Choreographer',
        'show_open_date'       => 'Open Date (YYYY-MM-DD)',
        'show_close_date'      => 'Close Date (YYYY-MM-DD)',
        'show_run_time'        => 'Run Time',
        'show_age_rec'         => 'Age Recommendation',
        'show_content_warning' => 'Content Warning',
        'show_ticket_url'      => 'Tickets URL',
        'show_program_pdf_url' => 'Program PDF URL',
        'show_program_type'    => 'Program Type (mainstage|off-the-shelf|club-tlt|education|special)',
        'show_legacy_url'      => 'Legacy Squarespace URL',
    ];
    echo '<table class="form-table">';
    foreach ( $fields as $key => $label ) {
        $val = esc_attr( get_post_meta( $post->ID, $key, true ) );
        echo "<tr><th><label for='$key'>$label</label></th><td><input type='text' id='$key' name='$key' value='$val' style='width:100%'></td></tr>";
    }
    $cancelled = get_post_meta( $post->ID, 'show_cancelled', true );
    $checked = $cancelled ? 'checked' : '';
    echo "<tr><th><label for='show_cancelled'>Cancelled</label></th><td><input type='checkbox' name='show_cancelled' id='show_cancelled' value='1' $checked> Was this production cancelled (e.g. COVID)?</td></tr>";
    echo '</table>';
}

function tlt_render_team_meta( $post ) {
    wp_nonce_field( 'tlt_team_meta', 'tlt_team_nonce' );
    $fields = [
        'team_role_title' => 'Role / Title',
        'team_email'      => 'Public Email',
        'team_pronouns'   => 'Pronouns',
        'team_legacy_url' => 'Legacy Squarespace URL',
    ];
    echo '<table class="form-table">';
    foreach ( $fields as $key => $label ) {
        $val = esc_attr( get_post_meta( $post->ID, $key, true ) );
        echo "<tr><th><label for='$key'>$label</label></th><td><input type='text' id='$key' name='$key' value='$val' style='width:100%'></td></tr>";
    }
    $b = get_post_meta( $post->ID, 'team_is_board', true );
    $s = get_post_meta( $post->ID, 'team_is_staff', true );
    echo "<tr><th>Type</th><td>";
    echo "<label><input type='checkbox' name='team_is_board' value='1' " . ($b?'checked':'') . "> Board Member</label> &nbsp; ";
    echo "<label><input type='checkbox' name='team_is_staff' value='1' " . ($s?'checked':'') . "> Staff</label>";
    echo "</td></tr>";
    echo '</table>';
}

add_action( 'save_post_tlt_show', 'tlt_save_show_meta', 10, 2 );
function tlt_save_show_meta( $post_id, $post ) {
    if ( ! isset( $_POST['tlt_show_nonce'] ) || ! wp_verify_nonce( $_POST['tlt_show_nonce'], 'tlt_show_meta' ) ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    $keys = [ 'show_director','show_music_director','show_choreographer','show_open_date','show_close_date','show_run_time','show_age_rec','show_content_warning','show_ticket_url','show_program_pdf_url','show_program_type','show_legacy_url' ];
    foreach ( $keys as $k ) {
        if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $k, sanitize_text_field( $_POST[ $k ] ) );
    }
    update_post_meta( $post_id, 'show_cancelled', ! empty( $_POST['show_cancelled'] ) ? 1 : 0 );
}

add_action( 'save_post_tlt_team', 'tlt_save_team_meta', 10, 2 );
function tlt_save_team_meta( $post_id, $post ) {
    if ( ! isset( $_POST['tlt_team_nonce'] ) || ! wp_verify_nonce( $_POST['tlt_team_nonce'], 'tlt_team_meta' ) ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    foreach ( [ 'team_role_title','team_email','team_pronouns','team_legacy_url' ] as $k ) {
        if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $k, sanitize_text_field( $_POST[ $k ] ) );
    }
    update_post_meta( $post_id, 'team_is_board', ! empty( $_POST['team_is_board'] ) ? 1 : 0 );
    update_post_meta( $post_id, 'team_is_staff', ! empty( $_POST['team_is_staff'] ) ? 1 : 0 );
}

/* ---------------------------------------------------------------------------
 * Helper: get currently-running show (for splash + homepage hero)
 * ------------------------------------------------------------------------- */

function tlt_get_current_show() {
    $today = current_time( 'Y-m-d' );
    // Find shows whose end_date >= today, ordered by start_date asc, limit 1
    $q = new WP_Query( [
        'post_type'      => 'tlt_show',
        'posts_per_page' => 1,
        'meta_query'     => [
            'relation' => 'AND',
            [ 'key' => 'show_close_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ],
            [ 'key' => 'show_program_type', 'value' => 'mainstage', 'compare' => '=' ],
        ],
        'meta_key'       => 'show_open_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    ] );
    return $q->have_posts() ? $q->posts[0] : null;
}

function tlt_get_upcoming_shows( $limit = 6 ) {
    $today = current_time( 'Y-m-d' );
    $q = new WP_Query( [
        'post_type'      => 'tlt_show',
        'posts_per_page' => $limit,
        'meta_query'     => [
            [ 'key' => 'show_close_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ],
            [ 'key' => 'show_program_type', 'value' => 'mainstage', 'compare' => '=' ],
        ],
        'meta_key'       => 'show_open_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    ] );
    return $q->posts;
}

/**
 * Smart hero: returns ['show' => $post, 'mode' => 'now-playing'|'coming-soon'|'recap', 'label' => ...]
 * - now-playing: there's a show currently running
 * - coming-soon: next show is in the future (hasn't opened yet)
 * - recap: no upcoming show, falls back to most recently closed (between-seasons mode)
 */
function tlt_get_hero_show() {
    $today = current_time( 'Y-m-d' );

    // 1) Currently running show?
    $now = get_posts( [
        'post_type' => 'tlt_show', 'posts_per_page' => 1,
        'meta_query' => [ 'relation' => 'AND',
            [ 'key' => 'show_open_date', 'value' => $today, 'compare' => '<=', 'type' => 'DATE' ],
            [ 'key' => 'show_close_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ],
            [ 'key' => 'show_program_type', 'value' => 'mainstage' ],
        ],
        'meta_key' => 'show_open_date', 'orderby' => 'meta_value', 'order' => 'ASC',
    ] );
    if ( $now ) return [ 'show' => $now[0], 'mode' => 'now-playing', 'label' => 'Now Playing' ];

    // 2) Soonest upcoming show?
    $upcoming = get_posts( [
        'post_type' => 'tlt_show', 'posts_per_page' => 1,
        'meta_query' => [
            [ 'key' => 'show_open_date', 'value' => $today, 'compare' => '>', 'type' => 'DATE' ],
            [ 'key' => 'show_program_type', 'value' => 'mainstage' ],
        ],
        'meta_key' => 'show_open_date', 'orderby' => 'meta_value', 'order' => 'ASC',
    ] );
    if ( $upcoming ) return [ 'show' => $upcoming[0], 'mode' => 'coming-soon', 'label' => 'Coming Soon' ];

    // 3) Fallback: most recently closed show (recap / between-seasons mode)
    $recent = get_posts( [
        'post_type' => 'tlt_show', 'posts_per_page' => 1,
        'meta_query' => [
            [ 'key' => 'show_close_date', 'value' => $today, 'compare' => '<', 'type' => 'DATE' ],
            [ 'key' => 'show_program_type', 'value' => 'mainstage' ],
        ],
        'meta_key' => 'show_close_date', 'orderby' => 'meta_value', 'order' => 'DESC',
    ] );
    if ( $recent ) return [ 'show' => $recent[0], 'mode' => 'recap', 'label' => 'Recently Closed' ];

    return null;
}

/**
 * Get the "current season" term: the season whose shows are running, announced,
 * or just-closed. Falls back to the most recently named season term.
 */
function tlt_get_current_season_term() {
    $today = current_time( 'Y-m-d' );
    // 1) Season that has a show whose end >= today (we're inside it)
    $shows = get_posts( [
        'post_type' => 'tlt_show', 'posts_per_page' => 1,
        'meta_query' => [
            [ 'key' => 'show_close_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ],
            [ 'key' => 'show_program_type', 'value' => 'mainstage' ],
        ],
        'meta_key' => 'show_open_date', 'orderby' => 'meta_value', 'order' => 'ASC',
    ] );
    if ( $shows ) {
        $terms = wp_get_object_terms( $shows[0]->ID, 'tlt_season' );
        if ( $terms && ! is_wp_error( $terms ) ) return $terms[0];
    }
    // 2) Season that has the soonest upcoming show (we're between seasons but next is announced)
    $upcoming = get_posts( [
        'post_type' => 'tlt_show', 'posts_per_page' => 1,
        'meta_query' => [
            [ 'key' => 'show_open_date', 'value' => $today, 'compare' => '>', 'type' => 'DATE' ],
            [ 'key' => 'show_program_type', 'value' => 'mainstage' ],
        ],
        'meta_key' => 'show_open_date', 'orderby' => 'meta_value', 'order' => 'ASC',
    ] );
    if ( $upcoming ) {
        $terms = wp_get_object_terms( $upcoming[0]->ID, 'tlt_season' );
        if ( $terms && ! is_wp_error( $terms ) ) return $terms[0];
    }
    // 3) Fallback: most recent season term by name
    $terms = get_terms( [ 'taxonomy' => 'tlt_season', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'DESC', 'number' => 1 ] );
    return $terms ? $terms[0] : null;
}

/**
 * Get all mainstage shows in the current season, ordered by open_date ascending.
 */
function tlt_get_current_season_shows() {
    $term = tlt_get_current_season_term();
    if ( ! $term ) return [];
    $q = new WP_Query( [
        'post_type'      => 'tlt_show',
        'posts_per_page' => -1,
        'tax_query'      => [ [ 'taxonomy' => 'tlt_season', 'field' => 'term_id', 'terms' => $term->term_id ] ],
        'meta_query'     => [
            [ 'key' => 'show_program_type', 'value' => 'mainstage', 'compare' => '=' ],
        ],
        'meta_key'       => 'show_open_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    ] );
    return $q->posts;
}

/**
 * Compute a status label for a show given today's date.
 * Returns: 'closed' | 'now-playing' | 'next' | 'upcoming'
 */
function tlt_show_status( $post_id, $is_first_upcoming = false ) {
    $today = current_time( 'Y-m-d' );
    $open  = get_post_meta( $post_id, 'show_open_date', true );
    $close = get_post_meta( $post_id, 'show_close_date', true );
    if ( ! $open ) return 'upcoming';
    if ( $close && $close < $today ) return 'closed';
    if ( $open <= $today && (! $close || $close >= $today) ) return 'now-playing';
    return $is_first_upcoming ? 'next' : 'upcoming';
}

/* ---------------------------------------------------------------------------
 * Activation: flush rewrite rules so /shows/, /team/, /seasons/ work
 * ------------------------------------------------------------------------- */

register_activation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );
