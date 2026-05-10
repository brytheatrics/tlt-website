<?php
/**
 * TLT Theme — functions.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

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
    // Google Font: Montserrat
    wp_enqueue_style( 'tlt-google-fonts',
        'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap',
        [],
        null
    );
    wp_enqueue_style( 'tlt-style', get_stylesheet_uri(), [ 'tlt-google-fonts' ], '1.0.0' );
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
        $csv = WP_CONTENT_DIR . '/uploads/migration-redirects.csv';
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
    return $classes;
} );
