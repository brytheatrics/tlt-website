<?php
/**
 * TLT Theme — functions.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ACF field groups for page templates (Designed, Campaign, etc.)
require_once __DIR__ . '/includes/acf-page-templates.php';

// Flex content library — Gutenberg block patterns (Prose, Figure, Pull-quote, etc.)
require_once __DIR__ . '/includes/block-patterns.php';

/**
 * Pre-launch date override. The TLT site doesn't go live until after Bedroom
 * Farce closes (Jul 26, 2026). Until then, simulate that the site is already
 * post-launch by forcing "today" to a date in the announce-window for the
 * 2026-2027 season. This drives the hero, season grid, splash logic, status
 * badges, /shows/, etc. as if it were Aug 1, 2026.
 *
 * REMOVE THIS LINE WHEN THE SITE GOES LIVE (or change date as you want to
 * preview different states). All other code reads it via tlt_today().
 */
if ( ! defined( 'TLT_AS_OF' ) ) define( 'TLT_AS_OF', '2026-08-01' );

add_action( 'after_setup_theme', function () {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', [ 'search-form', 'gallery', 'caption', 'script', 'style' ] );
    add_theme_support( 'custom-logo' );
    register_nav_menus( [
        'primary' => 'Primary Menu',
        'footer_visit' => 'Footer — Visit',
        'footer_about' => 'Footer — About',
        'footer_get_involved' => 'Footer — Get Involved',
        'topbar' => 'Top Bar (Donate / Volunteer / Login)',
    ] );
} );

add_action( 'wp_enqueue_scripts', function () {
    // Google Font: Jost — closest Google Fonts match to TLT's print font Century Gothic.
    // Geometric humanist sans-serif; loads 400/500/600/700 for body + display + headings.
    wp_enqueue_style( 'tlt-google-fonts',
        'https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700&display=swap',
        [],
        null
    );
    // Use filemtime() as version so any save instantly busts browser caches.
    $style_path = get_stylesheet_directory() . '/style.css';
    $ver = file_exists( $style_path ) ? filemtime( $style_path ) : '1.0.0';
    wp_enqueue_style( 'tlt-style', get_stylesheet_uri(), [ 'tlt-google-fonts' ], $ver );
} );

/**
 * Helper to format date ranges like "Oct 24 – Nov 9, 2025"
 */
function tlt_format_date_range( $start, $end ) {
    if ( ! $start ) return '';
    $s = strtotime( $start );
    $e = $end ? strtotime( $end ) : null;
    if ( ! $e ) return date( 'F j, Y', $s );
    if ( date( 'Y', $s ) === date( 'Y', $e ) ) {
        if ( date( 'F', $s ) === date( 'F', $e ) ) {
            return date( 'F j', $s ) . ' – ' . date( 'j, Y', $e );
        }
        return date( 'M j', $s ) . ' – ' . date( 'M j, Y', $e );
    }
    return date( 'M j, Y', $s ) . ' – ' . date( 'M j, Y', $e );
}

/**
 * Get featured image with fallback to legacy/external image URL.
 * During migration we stashed the Squarespace hero URL in _thumbnail_external_url
 * so the theme can display it without needing the image to be downloaded yet.
 */
function tlt_show_image_url( $post_id, $size = 'large', $context = '' ) {
    // For homepage hero, prefer a 'show_hero_image_url' override if set so the hero
    // can use a production photo while the show card below keeps the poster.
    if ( $context === 'hero' ) {
        $hero = get_post_meta( $post_id, 'show_hero_image_url', true );
        if ( $hero ) return $hero;
    }
    $img = get_the_post_thumbnail_url( $post_id, $size );
    if ( $img ) return $img;
    $ext = get_post_meta( $post_id, '_thumbnail_external_url', true );
    if ( $ext ) return $ext;
    $legacy = get_post_meta( $post_id, 'show_legacy_image_url', true );
    return $legacy ?: '';
}

/**
 * Migration redirects: catch old Squarespace URLs and 301-redirect to new ones.
 * Loads the CSV from wp-content/uploads/migration-redirects.csv on first request,
 * caches in transient for performance.
 */
add_action( 'template_redirect', function () {
    if ( ! is_404() && ! ( $_SERVER['REQUEST_URI'] ?? '' ) ) return;
    $req = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
    $req = '/' . trim( $req, '/' );
    if ( $req === '/' ) return;

    $map = wp_cache_get( 'tlt_redirects', 'tlt' );
    if ( ! is_array( $map ) ) {
        // Prefer theme-bundled redirects (git-synced). Fall back to legacy uploads location.
        $csv = get_stylesheet_directory() . '/redirects.csv';
        if ( ! file_exists( $csv ) ) {
            $csv = WP_CONTENT_DIR . '/uploads/migration-redirects.csv';
        }
        $map = [];
        if ( file_exists( $csv ) ) {
            $h = fopen( $csv, 'r' );
            if ( $h ) {
                fgetcsv( $h ); // skip header
                while ( ( $row = fgetcsv( $h ) ) !== false ) {
                    if ( ! isset( $row[0], $row[1] ) ) continue;
                    $src = '/' . trim( $row[0], '/' );
                    $dst = $row[1];
                    if ( $src && $dst && $src !== $dst ) $map[ $src ] = $dst;
                }
                fclose( $h );
            }
        }
        wp_cache_set( 'tlt_redirects', $map, 'tlt', 3600 );
    }

    if ( isset( $map[ $req ] ) ) {
        wp_redirect( $map[ $req ], 301 );
        exit;
    }
}, 1 ); // priority 1 so we run before normal 404 handling

/**
 * Allow /splash/ to bypass the header (it has its own takeover layout)
 */
add_filter( 'body_class', function ( $classes ) {
    if ( is_page( 'splash' ) || is_page_template( 'page-splash.php' ) ) {
        $classes[] = 'splash-page';
    }
    if ( is_page( 'home' ) ) {
        $classes[] = 'home-page';
    }
    return $classes;
} );

/**
 * Site search: include shows + pages + posts; exclude promotions + attachments.
 */
add_action( 'pre_get_posts', function ( $query ) {
    if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) return;
    $query->set( 'post_type', [ 'tlt_show', 'page', 'post' ] );
} );

/**
 * "This Season" menu item — rewrite the URL on render so it always points at
 * the current season's archive (/seasons/YYYY-YYYY/), not the static /shows/.
 * Auto-tracks as seasons change; falls back to /shows/ if no current season.
 */
add_filter( 'wp_nav_menu_objects', function ( $items ) {
    if ( ! function_exists( 'tlt_get_current_season_term' ) ) return $items;
    $current = tlt_get_current_season_term();
    if ( ! $current || is_wp_error( $current ) ) return $items;
    $link = get_term_link( $current );
    if ( ! $link || is_wp_error( $link ) ) return $items;
    foreach ( $items as $item ) {
        if ( strcasecmp( trim( wp_strip_all_tags( $item->title ) ), 'This Season' ) === 0 ) {
            $item->url = $link;
        }
    }
    return $items;
} );

/**
 * Season archive (/seasons/<slug>/) and main show archive (/shows/) need shows
 * ordered chronologically by show_open_date. Without this, WP defaults to
 * post_date DESC and the season grid looks shuffled.
 */
add_action( 'pre_get_posts', function ( $query ) {
    if ( is_admin() || ! $query->is_main_query() ) return;
    if ( $query->is_tax( 'tlt_season' ) || ( $query->is_post_type_archive( 'tlt_show' ) ) ) {
        $query->set( 'meta_key', 'show_open_date' );
        $query->set( 'orderby', 'meta_value' );
        $query->set( 'order', 'ASC' );
        $query->set( 'posts_per_page', -1 ); // all shows in a season fit on one page
    }
} );

/* ===================================================================
 * Customizer — Content settings only (brand controls stay in CSS vars)
 * Adds editable fields for logo (handled by core), address, phone,
 * mission text, vision text, land acknowledgement, social media URLs.
 * =================================================================== */

add_action( 'customize_register', function ( $wp_customize ) {

    // --- Contact info section ---
    $wp_customize->add_section( 'tlt_contact', [
        'title'    => 'Contact Information',
        'priority' => 30,
    ] );

    $contact_fields = [
        'tlt_address_street'  => [ 'Street Address',  '210 N "I" Street' ],
        'tlt_address_city'    => [ 'City',            'Tacoma' ],
        'tlt_address_state'   => [ 'State',           'WA' ],
        'tlt_address_zip'     => [ 'ZIP',             '98403' ],
        'tlt_phone'           => [ 'Phone',           '(253) 272-2281' ],
        'tlt_email_general'   => [ 'General Email',   'info@tacomalittletheatre.com' ],
        'tlt_email_boxoffice' => [ 'Box Office Email','boxoffice@tacomalittletheatre.com' ],
        'tlt_federal_id'      => [ 'Federal Tax ID',  '91-0485763' ],
    ];
    foreach ( $contact_fields as $key => [ $label, $default ] ) {
        $wp_customize->add_setting( $key, [ 'default' => $default, 'sanitize_callback' => 'sanitize_text_field' ] );
        $wp_customize->add_control( $key, [
            'label'   => $label,
            'section' => 'tlt_contact',
            'type'    => 'text',
        ] );
    }

    // --- Footer mission/vision/land acknowledgement ---
    $wp_customize->add_section( 'tlt_mission', [
        'title'    => 'Mission / Vision / Land Acknowledgement',
        'priority' => 35,
    ] );

    $mission_fields = [
        'tlt_mission_text'      => [ 'Mission Statement', 'Providing live theatre and education programs that inspire through stories reflecting the vibrancy of our diverse community.', 'textarea' ],
        'tlt_vision_text'       => [ 'Vision Statement',  'TLT enriches lives by providing opportunities for equitable inclusion and representation. Our goal is to ensure everyone — regardless of identity, background or personal experience — belongs at TLT.', 'textarea' ],
        'tlt_land_ack_english'  => [ 'Land Acknowledgement (English)', 'Tacoma Little Theatre recognizes that they teach and perform on Indigenous land: the traditional homelands of the Puyallup people.', 'textarea' ],
        'tlt_land_ack_lushootseed' => [ 'Land Acknowledgement (Lushootseed)', "ʔuk\u{2019}ʷədiitəb ʔuhigʷətəb čəɫ txʷəl tiiɫ ʔa čəɫ ʔal tə swatxʷixʷtxʷəd ʔə tiiɫ puyaləpabš dxʷəsɫaɫlils gʷəl ʔutxʷəlšucidəbs həlgʷəʔ.", 'textarea' ],
    ];
    foreach ( $mission_fields as $key => [ $label, $default, $type ] ) {
        $wp_customize->add_setting( $key, [ 'default' => $default, 'sanitize_callback' => 'wp_kses_post' ] );
        $wp_customize->add_control( $key, [
            'label'   => $label,
            'section' => 'tlt_mission',
            'type'    => $type,
        ] );
    }

    // --- Social media links ---
    $wp_customize->add_section( 'tlt_social', [
        'title'    => 'Social Media',
        'priority' => 40,
    ] );

    $social_fields = [
        'tlt_social_facebook'  => 'Facebook URL',
        'tlt_social_instagram' => 'Instagram URL',
        'tlt_social_youtube'   => 'YouTube URL',
        'tlt_social_twitter'   => 'X (Twitter) URL',
        'tlt_social_tiktok'    => 'TikTok URL',
    ];
    foreach ( $social_fields as $key => $label ) {
        $wp_customize->add_setting( $key, [ 'default' => '', 'sanitize_callback' => 'esc_url_raw' ] );
        $wp_customize->add_control( $key, [
            'label'   => $label,
            'section' => 'tlt_social',
            'type'    => 'url',
        ] );
    }
} );

/**
 * Convenience: get a TLT customizer value with a fallback.
 */
function tlt_setting( $key, $default = '' ) {
    return get_theme_mod( $key, $default );
}

/**
 * Organization-level JSON-LD on every page (in addition to the per-show
 * TheaterEvent schema in single-tlt_show.php). Tells search engines who we
 * are, where, and how to contact us.
 */
add_action( 'wp_footer', function () {
    if ( is_404() ) return;
    $org = [
        '@context' => 'https://schema.org',
        '@type'    => 'PerformingArtsTheater',
        'name'     => get_bloginfo( 'name' ),
        'url'      => home_url( '/' ),
        'logo'     => get_template_directory_uri() . '/assets/logo-1918.svg',
        'telephone' => tlt_setting( 'tlt_phone', '(253) 272-2281' ),
        'email'    => tlt_setting( 'tlt_email_general', 'info@tacomalittletheatre.com' ),
        'address'  => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => tlt_setting( 'tlt_address_street', '210 N "I" Street' ),
            'addressLocality' => tlt_setting( 'tlt_address_city', 'Tacoma' ),
            'addressRegion'   => tlt_setting( 'tlt_address_state', 'WA' ),
            'postalCode'      => tlt_setting( 'tlt_address_zip', '98403' ),
            'addressCountry'  => 'US',
        ],
        'sameAs' => array_values( array_filter( [
            tlt_setting( 'tlt_social_facebook' ),
            tlt_setting( 'tlt_social_instagram' ),
            tlt_setting( 'tlt_social_youtube' ),
            tlt_setting( 'tlt_social_twitter' ),
            tlt_setting( 'tlt_social_tiktok' ),
        ] ) ),
    ];
    echo '<script type="application/ld+json">' . wp_json_encode( $org, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';
} );
