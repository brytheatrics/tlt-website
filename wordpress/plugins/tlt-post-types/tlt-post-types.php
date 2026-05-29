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
        'show_program_type'      => [ 'string', 'mainstage|off_the_shelf|murder_mystery_dinner|club_tlt|childrens|education|special' ],
        'show_legacy_url'        => [ 'string', 'Original Squarespace URL' ],
        // --- New as of 2026-05-13 ---
        'show_venue_name'        => [ 'string', 'Off-site venue name (e.g. murder-mystery dinners)' ],
        'show_venue_address'     => [ 'string', 'Off-site venue address' ],
        'show_dinner_menu'       => [ 'string', 'Dinner menu HTML (for murder-mystery dinners)' ],
        'show_photo_gallery'     => [ 'string', 'JSON array of [{url, alt, caption}] production photos' ],
        'show_splash_gallery'    => [ 'string', 'JSON array of splash-page rotation image URLs' ],
        'show_tagline'           => [ 'string', 'Short tagline shown on hero / cards' ],
        'show_hero_image_url'    => [ 'string', 'Hero image URL override' ],
        // --- Audition meta (single auditions hub uses these from the show record) ---
        'show_audition_status'   => [ 'string', 'open|scheduled|cast|closed (controls visibility on /auditions/)' ],
        'show_audition_dates'    => [ 'string', 'Human-readable audition dates ("September 21-23, 2025")' ],
        'show_audition_location' => [ 'string', 'Audition location text (default: TLT)' ],
        'show_audition_packet_url'=>[ 'string', 'Audition packet PDF URL' ],
        'show_audition_signup_url'=>[ 'string', 'Casting Manager signup URL' ],
        'show_logo_url'          => [ 'string', 'Optional small show logo (used on auditions hub)' ],
        'show_video_urls'        => [ 'string', 'Comma-separated list of video embed URLs' ],
        'show_cityline_url'      => [ 'string', 'Cityline interview YouTube URL — featured on homepage when this is the running show' ],
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

    // Core production details
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
        'show_tagline'         => 'Tagline (short hero subtitle)',
        'show_hero_image_url'  => 'Hero Image URL Override',
        'show_legacy_url'      => 'Legacy Squarespace URL',
    ];

    // Program type as a select for clarity
    $program_types = [
        'mainstage'              => 'Mainstage',
        'off_the_shelf'          => 'Off the Shelf (staged reading)',
        'murder_mystery_dinner'  => 'Murder Mystery Dinner',
        'club_tlt'               => 'ClubTLT',
        'childrens'              => "Children's / Family",
        'education'              => 'Education event',
        'special'                => 'Special event',
    ];
    $current_type = get_post_meta( $post->ID, 'show_program_type', true ) ?: 'mainstage';

    echo '<table class="form-table">';
    foreach ( $fields as $key => $label ) {
        $val = esc_attr( get_post_meta( $post->ID, $key, true ) );
        echo "<tr><th><label for='$key'>$label</label></th><td><input type='text' id='$key' name='$key' value='$val' style='width:100%'></td></tr>";
    }
    // Program type select
    echo "<tr><th><label for='show_program_type'>Program Type</label></th><td><select id='show_program_type' name='show_program_type'>";
    foreach ( $program_types as $v => $label ) {
        $sel = $v === $current_type ? 'selected' : '';
        echo "<option value='$v' $sel>" . esc_html( $label ) . "</option>";
    }
    echo "</select></td></tr>";
    // Cancelled checkbox
    $cancelled = get_post_meta( $post->ID, 'show_cancelled', true );
    $checked = $cancelled ? 'checked' : '';
    echo "<tr><th><label for='show_cancelled'>Cancelled</label></th><td><input type='checkbox' name='show_cancelled' id='show_cancelled' value='1' $checked> Was this production cancelled (e.g. COVID)?</td></tr>";

    // --- Off-site venue (used by murder mystery dinners) ---
    echo '<tr><th colspan="2" style="padding-top:1em;border-top:1px solid #ddd"><strong>Off-site Venue (optional — for shows not at TLT)</strong></th></tr>';
    foreach ( [
        'show_venue_name'    => 'Venue Name',
        'show_venue_address' => 'Venue Address',
    ] as $key => $label ) {
        $val = esc_attr( get_post_meta( $post->ID, $key, true ) );
        echo "<tr><th><label for='$key'>$label</label></th><td><input type='text' id='$key' name='$key' value='$val' style='width:100%'></td></tr>";
    }

    // --- Dinner menu (Murder Mystery Dinners) ---
    echo '<tr><th colspan="2" style="padding-top:1em;border-top:1px solid #ddd"><strong>Dinner Menu (Murder Mystery Dinners only)</strong></th></tr>';
    $dinner = get_post_meta( $post->ID, 'show_dinner_menu', true );
    echo "<tr><th><label for='show_dinner_menu'>Menu HTML</label></th><td><textarea id='show_dinner_menu' name='show_dinner_menu' rows='8' style='width:100%'>" . esc_textarea( $dinner ) . "</textarea><p class='description'>Rich text supported. Use &lt;h4&gt; for course headings.</p></td></tr>";

    // --- Galleries ---
    echo '<tr><th colspan="2" style="padding-top:1em;border-top:1px solid #ddd"><strong>Galleries</strong></th></tr>';
    foreach ( [
        'show_photo_gallery'  => 'Production Photo Gallery (JSON: [{"url":"…","alt":"…","caption":"…"}, …])',
        'show_splash_gallery' => 'Splash Gallery (JSON: ["url","url",…] — splash auto-shows if non-empty AND show is running)',
        'show_video_urls'     => 'Video URLs (comma-separated YouTube/Vimeo URLs)',
    ] as $key => $label ) {
        $val = esc_textarea( get_post_meta( $post->ID, $key, true ) );
        echo "<tr><th><label for='$key'>$label</label></th><td><textarea id='$key' name='$key' rows='3' style='width:100%'>$val</textarea></td></tr>";
    }

    // --- Cityline interview ---
    echo '<tr><th colspan="2" style="padding-top:1em;border-top:1px solid #ddd"><strong>Cityline Interview</strong></th></tr>';
    $cityline = esc_attr( get_post_meta( $post->ID, 'show_cityline_url', true ) );
    echo "<tr><th><label for='show_cityline_url'>YouTube URL</label></th><td><input type='url' id='show_cityline_url' name='show_cityline_url' value='$cityline' style='width:100%' placeholder='https://www.youtube.com/watch?v=…'><p class='description'>Paste the YouTube link to the Cityline interview for this show. When this show is currently running, the video will appear on the homepage.</p></td></tr>";

    // --- Auditions ---
    echo '<tr><th colspan="2" style="padding-top:1em;border-top:1px solid #ddd"><strong>Auditions (drives the /auditions/ hub page)</strong></th></tr>';
    $audition_status = get_post_meta( $post->ID, 'show_audition_status', true );
    echo "<tr><th><label for='show_audition_status'>Audition Status</label></th><td><select id='show_audition_status' name='show_audition_status'>";
    foreach ( [ '' => '— Not on auditions hub —', 'scheduled' => 'Scheduled (dates announced, signups not yet open)', 'open' => 'Open for signups', 'cast' => 'Cast (briefly shown on hub)', 'closed' => 'Closed (hidden)' ] as $v => $label ) {
        $sel = $v === $audition_status ? 'selected' : '';
        echo "<option value='$v' $sel>" . esc_html( $label ) . "</option>";
    }
    echo "</select></td></tr>";
    foreach ( [
        'show_audition_dates'      => 'Audition Dates (human-readable)',
        'show_audition_location'   => 'Audition Location (default: TLT)',
        'show_audition_packet_url' => 'Audition Packet PDF URL',
        'show_audition_signup_url' => 'Audition Signup URL (e.g. Casting Manager)',
        'show_logo_url'            => 'Show Logo URL (small, optional — used on auditions hub)',
    ] as $key => $label ) {
        $val = esc_attr( get_post_meta( $post->ID, $key, true ) );
        echo "<tr><th><label for='$key'>$label</label></th><td><input type='text' id='$key' name='$key' value='$val' style='width:100%'></td></tr>";
    }

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
    $text_keys = [
        'show_director','show_music_director','show_choreographer','show_open_date','show_close_date',
        'show_run_time','show_age_rec','show_content_warning','show_ticket_url','show_program_pdf_url',
        'show_program_type','show_legacy_url','show_tagline','show_hero_image_url',
        'show_venue_name','show_venue_address',
        'show_audition_status','show_audition_dates','show_audition_location',
        'show_audition_packet_url','show_audition_signup_url','show_logo_url',
        'show_cityline_url',
    ];
    foreach ( $text_keys as $k ) {
        if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $k, sanitize_text_field( $_POST[ $k ] ) );
    }
    // Rich/multi-line fields — allow safe HTML
    foreach ( [ 'show_dinner_menu' ] as $k ) {
        if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $k, wp_kses_post( $_POST[ $k ] ) );
    }
    // JSON/textarea fields — store as-is but strip tags for safety
    foreach ( [ 'show_photo_gallery','show_splash_gallery','show_video_urls' ] as $k ) {
        if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $k, wp_kses_post( $_POST[ $k ] ) );
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
    $today = function_exists( 'tlt_today' ) ? tlt_today() : current_time( 'Y-m-d' );
    // Find the show that's actually running today (open <= today <= close).
    // If nothing's running, return the next upcoming show so splash etc. has
    // something to render in development.
    $q = new WP_Query( [
        'post_type'      => 'tlt_show',
        'posts_per_page' => 1,
        'meta_query'     => [
            'relation' => 'AND',
            [ 'key' => 'show_open_date', 'value' => $today, 'compare' => '<=', 'type' => 'DATE' ],
            [ 'key' => 'show_close_date', 'value' => $today, 'compare' => '>=', 'type' => 'DATE' ],
            [ 'key' => 'show_program_type', 'value' => 'mainstage', 'compare' => '=' ],
        ],
        'meta_key'       => 'show_open_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    ] );
    if ( $q->have_posts() ) return $q->posts[0];
    // Fallback — soonest upcoming
    $q = new WP_Query( [
        'post_type'      => 'tlt_show',
        'posts_per_page' => 1,
        'meta_query'     => [
            'relation' => 'AND',
            [ 'key' => 'show_open_date', 'value' => $today, 'compare' => '>', 'type' => 'DATE' ],
            [ 'key' => 'show_program_type', 'value' => 'mainstage', 'compare' => '=' ],
        ],
        'meta_key'       => 'show_open_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
    ] );
    return $q->have_posts() ? $q->posts[0] : null;
}

function tlt_get_upcoming_shows( $limit = 6 ) {
    $today = function_exists( 'tlt_today' ) ? tlt_today() : current_time( 'Y-m-d' );
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
 * Returns "today" as Y-m-d, with overrides for development previews.
 *
 * Precedence (highest first):
 *   1. TLT_AS_OF constant (defined in functions.php or wp-config.php) —
 *      a permanent site-wide override. Use this until the site goes live,
 *      then remove it. Affects every visitor.
 *   2. ?as_of=YYYY-MM-DD URL parameter — sticky via a 24h cookie. Per-user
 *      preview. Set ?as_of=clear to drop the cookie.
 *   3. Real current date.
 */
function tlt_today() {
    // 1. Constant override (set in functions.php while site is pre-launch)
    if ( defined( 'TLT_AS_OF' ) && TLT_AS_OF && preg_match( '/^\d{4}-\d{2}-\d{2}$/', TLT_AS_OF ) ) {
        return TLT_AS_OF;
    }
    // 2. URL parameter (sets cookie for stickiness during a single dev session)
    if ( isset( $_GET['as_of'] ) ) {
        $raw = sanitize_text_field( wp_unslash( $_GET['as_of'] ) );
        if ( $raw === 'clear' ) {
            if ( ! headers_sent() ) setcookie( 'tlt_as_of', '', time() - 3600, '/' );
            unset( $_COOKIE['tlt_as_of'] );
        } elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
            if ( ! headers_sent() ) setcookie( 'tlt_as_of', $raw, time() + 86400, '/' );
            return $raw;
        }
    }
    if ( ! empty( $_COOKIE['tlt_as_of'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $_COOKIE['tlt_as_of'] ) ) {
        return $_COOKIE['tlt_as_of'];
    }
    return current_time( 'Y-m-d' );
}

/**
 * Smart hero: returns ['show' => $post, 'mode' => 'now-playing'|'coming-soon'|'recap', 'label' => ...]
 * - now-playing: there's a show currently running
 * - coming-soon: next show is in the future (hasn't opened yet)
 * - recap: no upcoming show, falls back to most recently closed (between-seasons mode)
 */
function tlt_get_hero_show() {
    $today = tlt_today();

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
    $today = tlt_today();
    // 1) Season that has a show whose end >= today (we're inside it). This keeps
    //    the current season featured until its last show actually closes, even
    //    if the next season has already been announced.
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
    $today = tlt_today();
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

/* ---------------------------------------------------------------------------
 * Off the Shelf URL rewriting
 * Shows with show_program_type='off_the_shelf' get URLs at /off-the-shelf/<slug>/
 * instead of /shows/<slug>/. Both URLs resolve.
 * ------------------------------------------------------------------------- */

// Change permalink for off-the-shelf shows
add_filter( 'post_type_link', function ( $link, $post ) {
    if ( $post->post_type !== 'tlt_show' ) return $link;
    $ptype = get_post_meta( $post->ID, 'show_program_type', true );
    if ( $ptype === 'off_the_shelf' ) {
        return home_url( '/off-the-shelf/' . $post->post_name . '/' );
    }
    return $link;
}, 10, 2 );

// Add rewrite rule so /off-the-shelf/<slug>/ resolves to the show
add_action( 'init', function () {
    add_rewrite_rule(
        '^off-the-shelf/([^/]+)/?$',
        'index.php?post_type=tlt_show&name=$matches[1]',
        'top'
    );
} );

register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );
