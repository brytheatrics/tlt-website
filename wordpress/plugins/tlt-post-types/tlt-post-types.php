<?php
/**
 * Plugin Name: TLT Post Types
 * Description: Registers Show, Team, and News custom post types + structured meta fields for Tacoma Little Theatre.
 * Version:     1.0.0
 * Author:      TLT Migration
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Promotions (tlt_promotion CPT + ACF fields + helpers + seeder)
require_once __DIR__ . '/includes/promotions.php';

// Events (tlt_event CPT — calendar events that aren't productions)
require_once __DIR__ . '/includes/events.php';

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
        'supports'             => [ 'title', 'editor', 'thumbnail', 'revisions', 'custom-fields' ],
        'rewrite'              => [ 'slug' => 'shows', 'with_front' => false ],
        'taxonomies'           => [ 'tlt_season' ],
    ] );

    // ---------- Team (board + staff) ----------
    register_post_type( 'tlt_team', [
        'labels' => [
            'name'          => 'Board & Staff',
            'singular_name' => 'Team Member',
            'menu_name'     => 'Board & Staff',
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
        'labels'         => [
            'name'              => 'Seasons',
            'singular_name'     => 'Season',
            'menu_name'         => 'Season',
            'all_items'         => 'All Seasons',
            'edit_item'         => 'Edit Season',
            'view_item'         => 'View Season',
            'update_item'       => 'Update Season',
            'add_new_item'      => 'Add Season',
            'new_item_name'     => 'New Season',
            'search_items'      => 'Search Seasons',
            'not_found'         => 'No seasons found',
            'no_terms'          => 'No seasons',
            'back_to_items'     => '&larr; Go to Seasons',
        ],
        'public'         => true,
        'hierarchical'   => false,
        'show_in_rest'   => true,
        'rewrite'        => [ 'slug' => 'seasons' ],
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
        'show_playwright'        => [ 'string', 'Playwright / author' ],
        'show_music_director'    => [ 'string', 'Music Director' ],
        'show_choreographer'     => [ 'string', 'Choreographer' ],
        'show_open_date'         => [ 'string', 'Open date (YYYY-MM-DD)' ],
        'show_close_date'        => [ 'string', 'Close date (YYYY-MM-DD)' ],
        'show_run_time'          => [ 'string', 'Run time text' ],
        'show_age_rec'           => [ 'string', 'Age recommendation text' ],
        'show_content_warning'   => [ 'string', 'Content warning' ],
        'show_announcement'      => [ 'string', 'Top-of-page announcement ribbon (e.g. opening-night talkback). Clear to hide.' ],
        'show_performance_details' => [ 'string', 'Showtimes & ticket prices card (free-form: times, prices, double-cast schedule, PWYC/ASL nights, etc.)' ],
        'show_ticket_url'        => [ 'string', 'Buy tickets URL' ],
        'show_program_pdf_url'   => [ 'string', 'Program PDF URL' ],
        'show_dramaturgy_url'    => [ 'string', 'Dramaturgy link (PDF / Canva / external page)' ],
        'show_dramaturgy_gallery'=> [ 'string', 'JSON array of dramaturgy images [{url,alt,caption}] — opens as a slideshow' ],
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
        // --- Homepage feature (maintains a linked tlt_promotion) ---
        'show_feature_homepage'  => [ 'string', 'Feature on homepage (1/empty) — maintains a linked promotion' ],
        'show_feature_from'      => [ 'string', 'Homepage promo start (YYYY-MM-DD; blank = now)' ],
        'show_feature_until'     => [ 'string', 'Homepage promo end (YYYY-MM-DD; blank = show close date)' ],
        // --- Audition meta (auditions hub auto-derives status from these) ---
        'show_audition_signup_url'=>[ 'string', 'Casting Manager signup URL — when set, the show is featured on /auditions/' ],
        'show_audition_blurb'    => [ 'string', 'Short audition blurb for the featured auditions callout' ],
        'show_video_urls'        => [ 'string', 'Comma-separated list of video embed URLs' ],
        'show_cityline_url'      => [ 'string', 'Cityline interview YouTube URL — featured on homepage when this is the running show' ],
        // --- Calendar schedules (one entry per line) ---
        'show_performances'      => [ 'string', 'Performance schedule — one per line: "YYYY-MM-DD 7:30 PM"' ],
        'show_audition_schedule' => [ 'string', 'Audition schedule — one per line: "YYYY-MM-DD 6:00 PM @ Location"' ],
        'show_cast'              => [ 'string', 'Cast — "Actor as Character, …" (comma-separated; "/" for multiple roles; name alone if no character)' ],
        'show_reviews'           => [ 'string', 'Reviews — one per line: "Publication | https://url"' ],
    ];
    foreach ( $show_fields as $key => [ $type, $desc ] ) {
        register_post_meta( 'tlt_show', $key, [
            'type'         => $type,
            'description'  => $desc,
            'single'       => true,
            // Managed via the classic meta box — keep OUT of REST so the block
            // editor can't save a stale copy over the meta-box changes.
            'show_in_rest' => false,
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
            // Managed via the classic meta box — keep OUT of REST so the block
            // editor can't save a stale copy over the meta-box changes.
            'show_in_rest' => false,
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

/**
 * Use the Classic Editor for our content types. The block editor buries custom
 * meta boxes in a collapsed "Meta Boxes" drawer at the bottom; the classic
 * editor renders "Show Details" inline and expanded right under the body.
 */
add_filter( 'use_block_editor_for_post_type', function ( $use, $post_type ) {
    return in_array( $post_type, [ 'tlt_show', 'tlt_event', 'tlt_team' ], true ) ? false : $use;
}, 10, 2 );

/**
 * Enqueue the WP media library + our editor enhancements (photo/splash pickers,
 * performance/audition date tools) on the Show edit screen only.
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'tlt_show' ) return;
    wp_enqueue_media();
    $base = plugin_dir_url( __FILE__ ) . 'admin/';
    $ver  = '1.0.0';
    wp_enqueue_style( 'tlt-show-editor', $base . 'show-editor.css', [], $ver );
    wp_enqueue_script( 'tlt-show-editor', $base . 'show-editor.js', [ 'jquery', 'jquery-ui-sortable' ], $ver, true );
} );

/**
 * Promotion edit screen: date tools (spreadsheet paste + Generate run) for the
 * "Event dates" field, mirroring the show performance-date helpers.
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'tlt_promotion' ) return;
    wp_enqueue_script( 'tlt-promo-editor', plugin_dir_url( __FILE__ ) . 'admin/promo-editor.js', [ 'jquery' ], '1.0.0', true );
} );

/**
 * Show editor body: label it "Show Description" and strip the rich-text controls
 * so the synopsis is plain paragraphs styled by the theme — Chris can type, but
 * can't change formatting or drop in off-design images. ("Add Media" button and
 * the Visual/Text tabs are hidden via show-editor.css.)
 */
function tlt_is_show_edit_screen() {
    $s = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    return $s && $s->post_type === 'tlt_show';
}
add_action( 'edit_form_after_title', function ( $post ) {
    if ( ! $post || $post->post_type !== 'tlt_show' ) return;
    echo '<h2 style="margin:1.4em 0 .4em;font-size:1.05rem">Show Description</h2>';
} );
add_filter( 'mce_buttons',   function ( $b ) { return tlt_is_show_edit_screen() ? [] : $b; } );
add_filter( 'mce_buttons_2', function ( $b ) { return tlt_is_show_edit_screen() ? [] : $b; } );
add_filter( 'tiny_mce_before_init', function ( $init ) {
    if ( tlt_is_show_edit_screen() ) { $init['statusbar'] = false; $init['menubar'] = false; }
    return $init;
} );

function tlt_render_show_meta( $post ) {
    wp_nonce_field( 'tlt_show_meta', 'tlt_show_nonce' );

    // Small helpers: keep render inline (no $fields lookup table) so the order
    // matches the front-end show page exactly and is easy to re-read.
    $text_field = function ( $key, $label, $placeholder = '', $desc = '' ) use ( $post ) {
        $val = esc_attr( get_post_meta( $post->ID, $key, true ) );
        $ph  = $placeholder ? " placeholder='" . esc_attr( $placeholder ) . "'" : '';
        $d   = $desc ? "<p class='description'>$desc</p>" : '';
        echo "<tr><th><label for='$key'>$label</label></th><td><input type='text' id='$key' name='$key' value='$val' style='width:100%'$ph>$d</td></tr>";
    };
    $section = function ( $title, $note = '' ) {
        echo '<tr><th colspan="2" style="padding-top:1em;border-top:1px solid #ddd"><strong>' . $title . '</strong>'
           . ( $note ? " &mdash; <span style='font-weight:400;color:#555'>$note</span>" : '' )
           . '</th></tr>';
    };

    echo '<table class="form-table">';

    /* ========================================================================
     * Admin order mirrors the show page top-to-bottom:
     *   1. Announcement ribbon (top of show page)
     *   2. Cancelled badge
     *   3. Dates → Title → Tagline → Buy Tickets → Credits
     *   4. Presented At
     *   5. (Body content — lives in the post editor, not here)
     *   6. Showtimes & Tickets card
     *   7. At A Glance (Run Time / Recommended for Ages)
     *   8. Content Warning
     *   9. Cast
     *  10. View Program / View Dramaturgy
     *  11. Reviews
     *   --- left column on the show page ---
     *  12. Videos (Cityline + others)
     *  13. Poster & Photos
     *   --- not on show page; drives other pages ---
     *  14. Promote on Homepage
     *  15. Auditions (drives /auditions/)
     *  16. Calendar Schedules (drives /calendar/)
     *  17. Admin / Other (program type, dinner menu, legacy URL)
     * ===================================================================== */

    /* ----- 1. Announcement (top-of-page ribbon) ----- */
    $section( 'Top-of-Page Announcement', 'e.g. opening-night talkback, free preview' );
    $announcement = get_post_meta( $post->ID, 'show_announcement', true );
    echo "<tr><th><label for='show_announcement'>Announcement</label></th><td><textarea id='show_announcement' name='show_announcement' rows='3' style='width:100%' placeholder='e.g. Join us on opening night for a talkback with the playwright, immediately following the performance!'>" . esc_textarea( $announcement ) . "</textarea><p class='description'>Shows as a gold ribbon at the top of the show page. <strong>Clear the field</strong> after the event to hide it.</p></td></tr>";

    /* ----- 2. Cancelled badge ----- */
    $cancelled = get_post_meta( $post->ID, 'show_cancelled', true );
    $checked = $cancelled ? 'checked' : '';
    echo "<tr><th><label for='show_cancelled'>Cancelled</label></th><td><input type='checkbox' name='show_cancelled' id='show_cancelled' value='1' $checked> Was this production cancelled (e.g. COVID)?</td></tr>";

    /* ----- 3. Dates → Title → Tagline → Buy Tickets → Credits ----- */
    $section( 'Dates' );
    $text_field( 'show_open_date',  'Open Date (YYYY-MM-DD)' );
    $text_field( 'show_close_date', 'Close Date (YYYY-MM-DD)' );

    $section( 'Hero (under the title)' );
    $text_field( 'show_tagline',    'Tagline (short subtitle)' );
    $text_field( 'show_ticket_url', 'Tickets URL', '', 'Powers the red "Buy Tickets" button under the tagline.' );

    $section( 'Credits' );
    $text_field( 'show_director',         'Director' );
    // Playwright/Author is a textarea because musicals have multiple credit
    // lines (Book by … / Music by … / Lyrics by …). For a single playwright
    // just type the name; the front end auto-prepends "by " in that case.
    $pw = esc_textarea( get_post_meta( $post->ID, 'show_playwright', true ) );
    echo "<tr><th><label for='show_playwright'>Playwright / Author</label></th><td><textarea id='show_playwright' name='show_playwright' rows='4' style='width:100%' placeholder='Aaron Sorkin&#10;&#10;…or for musicals, one credit per line:&#10;Book by Thomas Meehan and Sylvester Stallone&#10;Music by Stephen Flaherty&#10;Lyrics by Lynn Ahrens'>$pw</textarea><p class='description'>Just a name? Type it (e.g. <code>Aaron Sorkin</code>) — the show page auto-renders &ldquo;by Aaron Sorkin&rdquo;. For musicals, list each credit on its own line (<code>Book by …</code> / <code>Music by …</code> / <code>Lyrics by …</code>) and the page renders them verbatim.</p></td></tr>";
    $text_field( 'show_music_director',   'Music Director' );
    $text_field( 'show_choreographer',    'Choreographer' );

    /* ----- 4. Presented At (off-site venue) ----- */
    $section( 'Presented At', 'only when the show is off-site (e.g. murder mystery dinners)' );
    $text_field( 'show_venue_name',    'Venue Name' );
    $text_field( 'show_venue_address', 'Venue Address' );

    /* ----- 5. Body content lives in the WP post editor above this box ----- */

    /* ----- 6. Showtimes & Tickets card ----- */
    $section( 'Showtimes & Tickets', 'free-form practical info card on the show page' );
    $perf_details = get_post_meta( $post->ID, 'show_performance_details', true );
    $perf_placeholder = "Thursdays – Saturdays at 7:30pm / Sundays at 2:00pm\n\n\$30 Adults – \$28 Students/Seniors/Military – \$23 Children 12 & Under\n\nASL-interpreted: Sunday, Sep 6\nPay-What-You-Can: Thursday, Sep 10";
    echo "<tr><th><label for='show_performance_details'>Showtimes &amp; Tickets</label></th><td><textarea id='show_performance_details' name='show_performance_details' rows='8' style='width:100%' placeholder=" . esc_attr( $perf_placeholder ) . ">" . esc_textarea( $perf_details ) . "</textarea><p class='description'>Times, ticket prices, double-cast schedule, ASL/PWYC nights, etc. Renders as its own card so it doesn't blend into the synopsis. Leave blank to hide the card.</p></td></tr>";

    /* ----- 7. At A Glance ----- */
    $section( 'At A Glance' );
    $text_field( 'show_run_time', 'Run Time' );
    $text_field( 'show_age_rec',  'Recommended for Ages', '', 'Type a number like <code>12+</code>, or a phrase like <code>All Ages</code> / <code>General Audiences</code> for the inline display.' );

    /* ----- 8. Content Warning ----- */
    $section( 'Content Warning' );
    $text_field( 'show_content_warning', 'Content Warning' );

    /* ----- 9. Cast ----- */
    $section( 'Cast' );
    $cast = esc_textarea( get_post_meta( $post->ID, 'show_cast', true ) );
    echo "<tr><th></th><td><textarea id='show_cast' name='show_cast' rows='4' style='width:100%' placeholder='Kennedy Miller as Annie, Roxanne De Vito as Miss Hannigan, …'>$cast</textarea><p class='description'>Comma-separated <code>Actor as Character</code>. Use <code>/</code> for an actor's multiple roles; a name alone (no &ldquo;as&rdquo;) is fine for ensemble/revue.</p></td></tr>";

    /* ----- 10. View Program / View Dramaturgy buttons ----- */
    $section( 'Program & Dramaturgy', 'buttons under the cast list' );
    $text_field( 'show_program_pdf_url', 'Program PDF URL' );
    $du = esc_attr( get_post_meta( $post->ID, 'show_dramaturgy_url', true ) );
    echo "<tr><th><label for='show_dramaturgy_url'>Dramaturgy Link</label></th><td><input type='text' id='show_dramaturgy_url' name='show_dramaturgy_url' value='$du' style='width:100%' placeholder='PDF, Canva, or web page URL'><p class='description'>Add a link <em>or</em> upload images below — use whichever applies.</p></td></tr>";
    $dg = esc_textarea( get_post_meta( $post->ID, 'show_dramaturgy_gallery', true ) );
    echo "<tr><th><label for='show_dramaturgy_gallery'>&hellip; or Images</label></th><td><textarea id='show_dramaturgy_gallery' name='show_dramaturgy_gallery' rows='3' style='width:100%'>$dg</textarea><p class='description'>Upload images here instead of a link — they open as a slideshow from the &ldquo;View Dramaturgy&rdquo; button.</p></td></tr>";

    /* ----- 11. Reviews ----- */
    $section( 'Reviews' );
    $reviews = esc_textarea( get_post_meta( $post->ID, 'show_reviews', true ) );
    echo "<tr><th></th><td><textarea id='show_reviews' name='show_reviews' rows='4' style='width:100%' placeholder='The News Tribune | https://www.thenewstribune.com/…&#10;Weekly Volcano | https://…'>$reviews</textarea><p class='description'>One review per line: <code>Publication | https://link</code>. They appear as a linked list on the show page.</p></td></tr>";

    /* ----- 12. Videos (Cityline + others) — LEFT column on show page ----- */
    $section( 'Videos', 'left column on the show page, under the poster' );
    $cityline = esc_attr( get_post_meta( $post->ID, 'show_cityline_url', true ) );
    echo "<tr><th><label for='show_cityline_url'>Cityline Interview URL</label></th><td><input type='url' id='show_cityline_url' name='show_cityline_url' value='$cityline' style='width:100%' placeholder='https://www.youtube.com/watch?v=…'><p class='description'>When this show is currently running, the Cityline video also appears on the homepage.</p></td></tr>";
    $vu = esc_textarea( get_post_meta( $post->ID, 'show_video_urls', true ) );
    echo "<tr><th><label for='show_video_urls'>Other Videos</label></th><td><textarea id='show_video_urls' name='show_video_urls' rows='2' style='width:100%' placeholder='comma-separated YouTube/Vimeo embed URLs'>$vu</textarea></td></tr>";

    /* ----- 13. Poster & Photos ----- */
    $section( 'Poster & Photos' );
    $poster_url = has_post_thumbnail( $post->ID )
        ? get_the_post_thumbnail_url( $post->ID, 'medium' )
        : get_post_meta( $post->ID, '_thumbnail_external_url', true );
    echo "<tr><th><label>Poster</label></th><td>"
        . "<div class='tlt-poster' data-current='" . esc_attr( $poster_url ) . "'>"
        . "<input type='hidden' id='tlt_poster_id' name='tlt_poster_id' value=''>"
        . "</div>"
        . "<p class='description'>Used on the show card and as the show page poster. Click to upload or pick a new one.</p></td></tr>";
    $pg = esc_textarea( get_post_meta( $post->ID, 'show_photo_gallery', true ) );
    echo "<tr><th><label for='show_photo_gallery'>Photo Gallery</label></th><td><textarea id='show_photo_gallery' name='show_photo_gallery' rows='3' style='width:100%'>$pg</textarea><p class='description'>Shown on this show's page <strong>and</strong> as the rotating cover background when this is the current production.</p></td></tr>";

    /* ----- 14. Promote on Homepage (not on show page; drives the homepage promo block) ----- */
    $section( 'Promote on Homepage', 'creates a linked Promotion in WP admin' );
    $feat   = get_post_meta( $post->ID, 'show_feature_homepage', true );
    $ffrom  = esc_attr( get_post_meta( $post->ID, 'show_feature_from', true ) );
    $funtil = esc_attr( get_post_meta( $post->ID, 'show_feature_until', true ) );
    echo "<tr><th><label for='show_feature_homepage'>Feature on homepage</label></th><td><label><input type='checkbox' id='show_feature_homepage' name='show_feature_homepage' value='1' " . checked( $feat, '1', false ) . "> Show a homepage promo for this show</label><p class='description'>Creates &amp; keeps a linked <strong>Promotion</strong> in sync (edit its wording/image under Promotions; your edits there are preserved). The mainstage hero already auto-features the current show, so this is mainly for Off the Shelf, camps, murder-mystery dinners, etc. Uncheck to hide it (the promotion is set to draft, not deleted).</p></td></tr>";
    echo "<tr><th><label for='show_feature_from'>Promote from</label></th><td><input type='date' id='show_feature_from' name='show_feature_from' value='$ffrom'> <span class='description'>Blank = start now.</span></td></tr>";
    echo "<tr><th><label for='show_feature_until'>Promote until</label></th><td><input type='date' id='show_feature_until' name='show_feature_until' value='$funtil'> <span class='description'>Blank = the show's close date.</span></td></tr>";

    /* ----- 15. Auditions (drives /auditions/ hub) ----- */
    $section( 'Auditions', 'drives the /auditions/ hub page' );
    echo '<tr><td colspan="2"><p class="description" style="margin:0 0 0.5em">The /auditions/ page builds itself from the <strong>Audition Dates</strong> (below, under Calendar Schedules) plus the two fields here — no status to set. Add a Casting Manager link and the show is featured with a Sign-Up button; enter a Cast and it shows &ldquo;has been cast&rdquo;; it drops off ~3 weeks after the last audition date.</p></td></tr>';
    $text_field( 'show_audition_signup_url', 'Casting Manager Signup URL', '', 'Featured on /auditions/ when set.' );
    $text_field( 'show_audition_blurb',      'Audition Blurb', '', 'Short line for the featured callout.' );

    /* ----- 16. Calendar Schedules (drives /calendar/) ----- */
    $section( 'Calendar Schedules', 'drives the /calendar/ page' );
    $perf = esc_textarea( get_post_meta( $post->ID, 'show_performances', true ) );
    echo "<tr><th><label for='show_performances'>Performance Dates</label></th><td><textarea id='show_performances' name='show_performances' rows='6' style='width:100%' placeholder='2026-08-28 7:30 PM&#10;2026-08-29 7:30 PM&#10;2026-08-30 2:00 PM'>$perf</textarea><p class='description'>One performance per line: <code>YYYY-MM-DD 7:30 PM</code>. Each becomes a calendar entry linking to this show.</p></td></tr>";
    $aud = esc_textarea( get_post_meta( $post->ID, 'show_audition_schedule', true ) );
    echo "<tr><th><label for='show_audition_schedule'>Audition Dates</label></th><td><textarea id='show_audition_schedule' name='show_audition_schedule' rows='4' style='width:100%' placeholder='2026-06-07 6:00 PM @ Tacoma Little Theatre&#10;2026-06-09 7:00 PM @ STAR Center'>$aud</textarea><p class='description'>One audition per line: <code>YYYY-MM-DD 7:00 PM @ Location</code>. Location is optional (defaults to TLT).</p></td></tr>";

    /* ----- 17. Admin / Other ----- */
    $section( 'Other / Admin' );
    // Program type select
    $program_types = [
        'mainstage'              => 'Mainstage',
        'off_the_shelf'          => 'Off the Shelf (staged reading)',
        'murder_mystery_dinner'  => 'Murder Mystery Dinner',
        'education'              => 'Education event',
        'special'                => 'Special event',
    ];
    $current_type = get_post_meta( $post->ID, 'show_program_type', true ) ?: 'mainstage';
    echo "<tr><th><label for='show_program_type'>Program Type</label></th><td><select id='show_program_type' name='show_program_type'>";
    foreach ( $program_types as $v => $label ) {
        $sel = $v === $current_type ? 'selected' : '';
        echo "<option value='$v' $sel>" . esc_html( $label ) . "</option>";
    }
    echo "</select></td></tr>";
    // Dinner menu (Murder Mystery Dinners)
    $dinner = get_post_meta( $post->ID, 'show_dinner_menu', true );
    echo "<tr><th><label for='show_dinner_menu'>Dinner Menu</label></th><td><textarea id='show_dinner_menu' name='show_dinner_menu' rows='8' style='width:100%'>" . esc_textarea( $dinner ) . "</textarea><p class='description'>HTML supported. Use <code>&lt;h4&gt;</code> for course headings. Only relevant for Murder Mystery Dinners.</p></td></tr>";
    $text_field( 'show_legacy_url', 'Legacy Squarespace URL', '', 'Where this show lived on the old Squarespace site (for redirect mapping). Not shown publicly.' );

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
        'show_program_type','show_legacy_url','show_tagline','show_dramaturgy_url',
        'show_venue_name','show_venue_address',
        'show_audition_signup_url','show_audition_blurb',
        'show_feature_from','show_feature_until',
        'show_cityline_url',
    ];
    foreach ( $text_keys as $k ) {
        if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $k, sanitize_text_field( $_POST[ $k ] ) );
    }
    // Checkbox: only present in POST when checked (nonce above guarantees our meta box was submitted).
    update_post_meta( $post_id, 'show_feature_homepage', isset( $_POST['show_feature_homepage'] ) ? '1' : '' );
    // Multi-line plain-text fields — preserve newlines, strip HTML.
    foreach ( [ 'show_announcement', 'show_performance_details', 'show_playwright' ] as $k ) {
        if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $k, sanitize_textarea_field( $_POST[ $k ] ) );
    }
    // Rich/multi-line fields — allow safe HTML
    foreach ( [ 'show_dinner_menu' ] as $k ) {
        if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $k, wp_kses_post( $_POST[ $k ] ) );
    }
    // JSON/textarea fields — store as-is but strip tags for safety
    foreach ( [ 'show_photo_gallery','show_splash_gallery','show_dramaturgy_gallery','show_video_urls','show_performances','show_audition_schedule','show_cast','show_reviews' ] as $k ) {
        if ( isset( $_POST[ $k ] ) ) update_post_meta( $post_id, $k, wp_kses_post( $_POST[ $k ] ) );
    }
    update_post_meta( $post_id, 'show_cancelled', ! empty( $_POST['show_cancelled'] ) ? 1 : 0 );

    // Poster picker (Photos section). Sets the real Featured Image so it shows
    // everywhere; supersedes any legacy external-URL poster.
    //   ''  = no change   |   '0' = remove   |   <id> = set that attachment
    if ( isset( $_POST['tlt_poster_id'] ) ) {
        $pid = trim( (string) $_POST['tlt_poster_id'] );
        if ( $pid === '0' ) {
            delete_post_thumbnail( $post_id );
            delete_post_meta( $post_id, '_thumbnail_external_url' );
        } elseif ( ctype_digit( $pid ) && (int) $pid > 0 ) {
            set_post_thumbnail( $post_id, (int) $pid );
            delete_post_meta( $post_id, '_thumbnail_external_url' );
        }
    }
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
 * Return the season term AFTER the current one — i.e. the season whose earliest
 * show is still upcoming, distinct from tlt_get_current_season_term(). Returns
 * null if no future season exists (nothing announced beyond the current).
 * Used by the "Next Season" nav item filter, which hides the item when null.
 */
function tlt_get_next_season_term() {
    $current = tlt_get_current_season_term();
    $today   = tlt_today();
    $upcoming = get_posts( [
        'post_type'      => 'tlt_show',
        'posts_per_page' => -1,
        'meta_query'     => [
            [ 'key' => 'show_open_date',    'value' => $today, 'compare' => '>', 'type' => 'DATE' ],
            [ 'key' => 'show_program_type', 'value' => 'mainstage' ],
        ],
        'meta_key' => 'show_open_date',
        'orderby'  => 'meta_value',
        'order'    => 'ASC',
    ] );
    foreach ( $upcoming as $show ) {
        $terms = wp_get_object_terms( $show->ID, 'tlt_season' );
        if ( ! $terms || is_wp_error( $terms ) ) continue;
        $term = $terms[0];
        // Skip shows still in the current season (early upcoming shows in the
        // active season shouldn't count as "next season").
        if ( $current && (int) $term->term_id === (int) $current->term_id ) continue;
        return $term;
    }
    return null;
}

/**
 * Get all mainstage shows in the NEXT season (whichever tlt_get_next_season_term()
 * returns), ordered by open_date ascending. Returns [] if no next season exists.
 * Used by /season-tickets/ so season ticket sales always feature the upcoming
 * season, even while the current season is still running.
 */
function tlt_get_next_season_shows() {
    $term = tlt_get_next_season_term();
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

/* ---------------------------------------------------------------------------
 * Admin "All Shows" list: sort by OPEN DATE (newest/upcoming first) instead of
 * publish date. Otherwise an archival show created today would jump to the top
 * and bury the current season — forcing Chris to search every time. Also adds a
 * sortable "Opens" column so the date is visible and the order is obvious.
 * ------------------------------------------------------------------------- */
add_filter( 'manage_tlt_show_posts_columns', function ( $cols ) {
    $new = [];
    foreach ( $cols as $key => $label ) {
        $new[ $key ] = $label;
        if ( $key === 'title' ) $new['show_opens'] = 'Opens';
    }
    return $new;
} );

add_action( 'manage_tlt_show_posts_custom_column', function ( $col, $post_id ) {
    if ( $col !== 'show_opens' ) return;
    $open = get_post_meta( $post_id, 'show_open_date', true );
    if ( $open ) {
        $ts = strtotime( $open );
        echo $ts ? esc_html( date_i18n( 'M j, Y', $ts ) ) : esc_html( $open );
        return;
    }
    // Archival shows often have only a season label instead of exact dates.
    $season = get_post_meta( $post_id, 'show_season_label', true );
    echo $season ? esc_html( $season ) : '&mdash;';
}, 10, 2 );

add_filter( 'manage_edit-tlt_show_sortable_columns', function ( $cols ) {
    $cols['show_opens'] = 'show_opens';
    return $cols;
} );

add_action( 'pre_get_posts', function ( $q ) {
    if ( ! is_admin() || ! $q->is_main_query() ) return;
    global $pagenow;
    if ( $pagenow !== 'edit.php' || $q->get( 'post_type' ) !== 'tlt_show' ) return;

    // Apply the open-date sort by default (no explicit sort chosen) OR when the
    // user clicks the "Opens" column header. Any other column the user picks
    // (Title, Date) is left alone.
    $orderby = $q->get( 'orderby' );
    if ( $orderby && $orderby !== 'show_opens' ) return;

    $order = strtoupper( (string) $q->get( 'order' ) );
    if ( $order !== 'ASC' ) $order = 'DESC';

    // OR meta query so shows WITHOUT an open date (season-label archives) still
    // appear — they fall to the bottom (NULL sorts last in DESC).
    $q->set( 'meta_query', [
        'relation' => 'OR',
        'has_open' => [ 'key' => 'show_open_date', 'compare' => 'EXISTS' ],
        'no_open'  => [ 'key' => 'show_open_date', 'compare' => 'NOT EXISTS' ],
    ] );
    $q->set( 'orderby', [ 'has_open' => $order ] );
}, 20 );

/* ---------------------------------------------------------------------------
 * Season taxonomy: friendlier term editor.
 *
 * Instead of free-typing a Name + Slug (typo-prone, ugly slugs), Chris picks a
 * Start Year and End Year; the Name + Slug are generated as "YYYY-YYYY" and
 * locked. The Description field is repurposed as an optional "season tagline"
 * that shows at the top of the season page (see archive-tlt_show.php).
 * ------------------------------------------------------------------------- */
function tlt_season_year_options( $selected = 0 ) {
    $max = (int) current_time( 'Y' ) + 2;
    $out = '';
    for ( $y = $max; $y >= 1900; $y-- ) {
        $out .= '<option value="' . $y . '"' . selected( $y, $selected, false ) . '>' . $y . '</option>';
    }
    return $out;
}

// Add New Season form.
add_action( 'tlt_season_add_form_fields', function () {
    $cur = (int) current_time( 'Y' );
    echo '<div class="form-field tlt-season-years"><label>Season years</label>'
       . '<select id="tlt_season_start">' . tlt_season_year_options( $cur ) . '</select>'
       . '<span style="margin:0 6px">&ndash;</span>'
       . '<select id="tlt_season_end">' . tlt_season_year_options( $cur + 1 ) . '</select>'
       . '<p>Pick the start and end year &mdash; the season name &amp; slug fill in automatically as <code>YYYY-YYYY</code>.</p></div>';
} );

// Edit Season form.
add_action( 'tlt_season_edit_form_fields', function ( $term ) {
    $start = 0; $end = 0;
    if ( preg_match( '/^(\d{4})-(\d{4})$/', $term->name, $m ) ) { $start = (int) $m[1]; $end = (int) $m[2]; }
    $cur = (int) current_time( 'Y' );
    echo '<tr class="form-field tlt-season-years"><th scope="row"><label>Season years</label></th><td>'
       . '<select id="tlt_season_start">' . tlt_season_year_options( $start ?: $cur ) . '</select>'
       . '<span style="margin:0 6px">&ndash;</span>'
       . '<select id="tlt_season_end">' . tlt_season_year_options( $end ?: $cur + 1 ) . '</select>'
       . '<p class="description">Sets the season name as <code>YYYY-YYYY</code>. The slug is left as-is so existing links don\'t break.</p></td></tr>';
}, 10, 1 );

// Wire the dropdowns to the Name/Slug, lock them, and relabel Description.
add_action( 'admin_print_footer_scripts', function () {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || $screen->taxonomy !== 'tlt_season' ) return;
    ?>
    <style>
      #tag-name[readonly], #tag-slug[readonly], #name[readonly] { background:#f0f0f1; color:#50575e; }
      .tlt-season-years select { min-width:90px; }
    </style>
    <script>
    (function($){
      var $start = $('#tlt_season_start'), $end = $('#tlt_season_end');
      if (!$start.length) return;

      function fields(){
        return {
          name: document.getElementById('tag-name') || document.getElementById('name'),
          slug: document.getElementById('tag-slug') || document.getElementById('slug'),
          isAdd: !!document.getElementById('tag-name')
        };
      }
      function sync(){
        var s = parseInt($start.val(),10), e = parseInt($end.val(),10), f = fields(), val = s + '-' + e;
        if (f.name){ f.name.value = val; f.name.readOnly = true; }
        // Only auto-manage the slug on the Add form (don't rewrite an existing
        // season's slug and break its URL).
        if (f.isAdd && f.slug){ f.slug.value = val; f.slug.readOnly = true; }
      }

      $start.on('change', function(){
        var s = parseInt($start.val(),10), e = parseInt($end.val(),10);
        if (e <= s){ $end.val(s + 1); }
        sync();
      });
      $end.on('change', sync);
      // Re-apply after the Add-New AJAX clears the form.
      $(document).ajaxComplete(sync);

      // Move the year picker above the Name field.
      var $years = $('.tlt-season-years'), $nameWrap = $('.term-name-wrap');
      if ($years.length && $nameWrap.length){ $years.insertBefore($nameWrap); }

      // Repurpose the Description field as the season tagline.
      var $desc = $('.term-description-wrap');
      $desc.find('label').text('Season tagline (optional)');
      $desc.find('p').text('Optional — shows as a short line at the top of this season’s page.');

      sync();
    })(jQuery);
    </script>
    <?php
} );

register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );
