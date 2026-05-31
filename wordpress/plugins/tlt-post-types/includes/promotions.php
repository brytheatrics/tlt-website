<?php
/**
 * tlt_promotion — universal banner / event / call-to-action post type.
 *
 * Drives:
 *   - Homepage promo rows (Education group, Special Events group, Get Involved
 *     group, Support group)
 *   - Visit page promo zone
 *   - Education page "Currently Happening" zone
 *   - Get Involved page promo zone (when that template is built)
 *   - Sitewide banner (top of every page) — dismissable per-visitor
 *
 * Required fields: start_date, end_date, at least one display location.
 * Auto-expiration: a promo whose end_date < today stops rendering automatically.
 *
 * Fields are defined as ACF "local" field groups (in PHP, version-controlled).
 * If ACF is deactivated the post type still exists but the editing UI degrades
 * to plain WP "Custom Fields" — the rendering helpers fall back to raw meta.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------------------------------------------------------------------------
 * Post type
 * ------------------------------------------------------------------------- */

add_action( 'init', function () {
    register_post_type( 'tlt_promotion', [
        'labels' => [
            'name'               => 'Promotions',
            'singular_name'      => 'Promotion',
            'add_new'            => 'Add New',
            'add_new_item'       => 'Add New Promotion',
            'edit_item'          => 'Edit Promotion',
            'new_item'           => 'New Promotion',
            'view_item'          => 'View Promotion',
            'search_items'       => 'Search Promotions',
            'menu_name'          => 'Promotions',
            'all_items'          => 'All Promotions',
        ],
        // Not publicly browsable — promos live inside other pages, not as
        // standalone URLs. Still visible in the admin and editable.
        'public'              => false,
        'publicly_queryable'  => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_nav_menus'   => false,
        'show_in_admin_bar'   => true,
        'show_in_rest'        => true,
        'has_archive'         => false,
        'exclude_from_search' => true,
        'menu_position'       => 22,
        'menu_icon'           => 'dashicons-megaphone',
        // Only need title — body/image/CTAs are ACF fields, no editor needed.
        'supports'            => [ 'title', 'revisions' ],
        'rewrite'             => false,
    ] );
} );

/* ---------------------------------------------------------------------------
 * ACF field group
 * ------------------------------------------------------------------------- */

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'    => 'group_tlt_promotion',
        'title'  => 'Promotion Details',
        'fields' => [

            // ---- Dates ----
            [
                'key'           => 'field_promo_start_date',
                'label'         => 'Start date',
                'name'          => 'promo_start_date',
                'type'          => 'date_picker',
                'required'      => 1,
                'display_format'=> 'F j, Y',
                'return_format' => 'Y-m-d',
                'first_day'     => 0,
                'instructions'  => 'The first day this promo appears.',
                'wrapper'       => [ 'width' => '50' ],
            ],
            [
                'key'           => 'field_promo_end_date',
                'label'         => 'End date',
                'name'          => 'promo_end_date',
                'type'          => 'date_picker',
                'required'      => 1,
                'display_format'=> 'F j, Y',
                'return_format' => 'Y-m-d',
                'first_day'     => 0,
                'instructions'  => 'The last day this promo appears. Required so old promos disappear automatically.',
                'wrapper'       => [ 'width' => '50' ],
            ],

            // ---- Where ----
            [
                'key'           => 'field_promo_locations',
                'label'         => 'Where to show this',
                'name'          => 'promo_locations',
                'type'          => 'checkbox',
                'required'      => 1,
                'choices'       => [
                    'homepage'     => 'Homepage',
                    'visit'        => 'Visit page',
                    'education'    => 'Education page',
                    'get_involved' => 'Get Involved page',
                    'sitewide'     => 'Sitewide banner (top of every page)',
                ],
                'layout'        => 'vertical',
                'instructions'  => 'Pick one or more places where this promo should appear.',
            ],
            [
                'key'           => 'field_promo_homepage_section',
                'label'         => 'Homepage section',
                'name'          => 'promo_homepage_section',
                'type'          => 'select',
                'choices'       => [
                    'standalone'     => 'Standalone (its own row at the top of the homepage promos)',
                    'education'      => 'Education group',
                    'special_events' => 'Special Events / Beyond the Stage group',
                    'get_involved'   => 'Get Involved group',
                    'support'        => 'Support group (centered, smaller)',
                ],
                'default_value' => 'standalone',
                'instructions'  => 'Only matters when "Homepage" is checked above. Picks which existing homepage section this promo lives in so it fits visually.',
                'conditional_logic' => [
                    [
                        [ 'field' => 'field_promo_locations', 'operator' => '==', 'value' => 'homepage' ],
                    ],
                ],
            ],
            [
                'key'           => 'field_promo_priority',
                'label'         => 'Priority (display order)',
                'name'          => 'promo_priority',
                'type'          => 'number',
                'default_value' => 50,
                'min'           => 0,
                'max'           => 999,
                'instructions'  => 'Lower numbers show first. Use this to reorder when multiple promos are active in the same zone. Default 50.',
                'wrapper'       => [ 'width' => '30' ],
            ],

            // ---- Content ----
            [
                'key'           => 'field_promo_body',
                'label'         => 'Body text',
                'name'          => 'promo_body',
                'type'          => 'textarea',
                'rows'          => 3,
                'instructions'  => 'One or two short sentences. The Title field above (at the top of the page) is used as the promo headline.',
            ],
            [
                'key'           => 'field_promo_image',
                'label'         => 'Image',
                'name'          => 'promo_image',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'instructions'  => 'A wide-ish image works best (roughly 16:10). Leave blank if you only want text + a button.',
            ],
            [
                'key'           => 'field_promo_cta_label',
                'label'         => 'Button label',
                'name'          => 'promo_cta_label',
                'type'          => 'text',
                'placeholder'   => 'Learn More',
                'instructions'  => 'e.g. "Learn More", "Buy Tickets", "Camp Details".',
                'wrapper'       => [ 'width' => '40' ],
            ],
            [
                'key'           => 'field_promo_cta_url',
                'label'         => 'Button URL',
                'name'          => 'promo_cta_url',
                'type'          => 'text',
                'instructions'  => 'Full URL (https://…) or an on-site path like /board-and-staff/',
                'placeholder'   => 'https://… or /page-slug/',
                'wrapper'       => [ 'width' => '60' ],
            ],

            // ---- Linked show (optional) ----
            [
                'key'           => 'field_promo_linked_show',
                'label'         => 'Linked show (optional)',
                'name'          => 'promo_linked_show',
                'type'          => 'post_object',
                'post_type'     => [ 'tlt_show' ],
                'return_format' => 'id',
                'ui'            => 1,
                'allow_null'    => 1,
                'instructions'  => 'If this promo is about a specific show, link it here. Currently informational; future versions may auto-default the end date to the show close date.',
            ],
        ],
        'location' => [
            [
                [ 'param' => 'post_type', 'operator' => '==', 'value' => 'tlt_promotion' ],
            ],
        ],
        'menu_order'             => 0,
        'position'               => 'normal',
        'style'                  => 'default',
        'label_placement'        => 'top',
        'instruction_placement'  => 'label',
    ] );
} );

/* ---------------------------------------------------------------------------
 * Admin list table — add Start / End / Zones / Status columns
 * ------------------------------------------------------------------------- */

add_filter( 'manage_tlt_promotion_posts_columns', function ( $cols ) {
    // Rebuild from scratch so we control the order.
    $new = [];
    if ( isset( $cols['cb'] ) ) $new['cb'] = $cols['cb'];
    $new['title']      = 'Headline';
    $new['promo_zones']= 'Where it shows';
    $new['promo_window'] = 'Window';
    $new['promo_status'] = 'Status';
    $new['date']       = 'Last edited';
    return $new;
} );

add_action( 'manage_tlt_promotion_posts_custom_column', function ( $col, $post_id ) {
    if ( $col === 'promo_zones' ) {
        $locs = get_post_meta( $post_id, 'promo_locations', true );
        $locs = is_array( $locs ) ? $locs : maybe_unserialize( $locs );
        if ( ! is_array( $locs ) || ! $locs ) { echo '—'; return; }
        $labels = [
            'homepage'     => 'Homepage',
            'visit'        => 'Visit',
            'education'    => 'Education',
            'get_involved' => 'Get Involved',
            'sitewide'     => 'Sitewide',
        ];
        $out = [];
        foreach ( $locs as $l ) $out[] = $labels[$l] ?? $l;
        echo esc_html( implode( ', ', $out ) );
    } elseif ( $col === 'promo_window' ) {
        $s = get_post_meta( $post_id, 'promo_start_date', true );
        $e = get_post_meta( $post_id, 'promo_end_date', true );
        echo esc_html( tlt_promo_format_date( $s ) . ' – ' . tlt_promo_format_date( $e ) );
    } elseif ( $col === 'promo_status' ) {
        $status = tlt_promo_status( $post_id );
        $colors = [ 'active' => '#1d6f1d', 'upcoming' => '#7a5a00', 'expired' => '#a00000', 'no-dates' => '#888' ];
        $labels = [ 'active' => '● Active', 'upcoming' => '○ Upcoming', 'expired' => '× Expired', 'no-dates' => '— Missing dates' ];
        $c = $colors[ $status ] ?? '#888';
        $l = $labels[ $status ] ?? $status;
        echo "<span style='color:{$c};font-weight:600'>" . esc_html( $l ) . "</span>";
    }
}, 10, 2 );

function tlt_promo_format_date( $iso ) {
    if ( ! $iso ) return '?';
    // Stored as Y-m-d (per ACF return_format). Display friendly.
    $t = strtotime( $iso );
    if ( ! $t ) return $iso;
    return date( 'M j, Y', $t );
}

function tlt_promo_status( $post_id ) {
    $today = function_exists( 'tlt_today' ) ? tlt_today() : current_time( 'Y-m-d' );
    $s = get_post_meta( $post_id, 'promo_start_date', true );
    $e = get_post_meta( $post_id, 'promo_end_date', true );
    if ( ! $s || ! $e ) return 'no-dates';
    if ( $today < $s ) return 'upcoming';
    if ( $today > $e ) return 'expired';
    return 'active';
}

/* ---------------------------------------------------------------------------
 * Query helpers
 * ------------------------------------------------------------------------- */

/**
 * Return active promotions in a given display zone, ordered by priority asc.
 *
 * "Active" = today is between promo_start_date and promo_end_date AND the
 * zone is in promo_locations.
 *
 * @param string $zone   one of: homepage, visit, education, get_involved, sitewide
 * @param array  $args   optional: ['homepage_section' => 'education'|'special_events'|...]
 *                       to further filter homepage promos by their group.
 * @return WP_Post[]
 */
function tlt_get_active_promotions( $zone, $args = [] ) {
    $today = function_exists( 'tlt_today' ) ? tlt_today() : current_time( 'Y-m-d' );

    $q = new WP_Query( [
        'post_type'      => 'tlt_promotion',
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'meta_key'       => 'promo_priority',
        'orderby'        => [ 'meta_value_num' => 'ASC', 'date' => 'DESC' ],
        'no_found_rows'  => true,
    ] );

    // Helper: normalize date to Y-m-d. ACF date picker can store either
    // Ymd (admin-saved) or Y-m-d (legacy/seeded). get_field() formats per
    // the field's return_format; raw meta is the fallback.
    $normalize = function ( $v ) {
        if ( ! $v ) return '';
        // Already Y-m-d
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) return $v;
        // Ymd → Y-m-d
        if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $v, $m ) ) return "{$m[1]}-{$m[2]}-{$m[3]}";
        return $v;
    };

    $out = [];
    foreach ( $q->posts as $p ) {
        // Date window check
        $s = $normalize( get_post_meta( $p->ID, 'promo_start_date', true ) );
        $e = $normalize( get_post_meta( $p->ID, 'promo_end_date', true ) );
        if ( ! $s || ! $e ) continue;
        if ( $today < $s || $today > $e ) continue;

        // Zone check
        $locs = get_post_meta( $p->ID, 'promo_locations', true );
        if ( ! is_array( $locs ) ) $locs = maybe_unserialize( $locs );
        if ( ! is_array( $locs ) || ! in_array( $zone, $locs, true ) ) continue;

        // Homepage section check (if requested)
        if ( $zone === 'homepage' && isset( $args['homepage_section'] ) ) {
            $sec = get_post_meta( $p->ID, 'promo_homepage_section', true ) ?: 'standalone';
            if ( $sec !== $args['homepage_section'] ) continue;
        }

        $out[] = $p;
    }
    return $out;
}

/**
 * Resolve the best image URL for a promo. Prefers the ACF image field, falls
 * back to the seeded fallback URL (used by migration seeder for hardcoded
 * promos that reference theme assets, not media-library uploads).
 */
function tlt_promo_image_url( $post_id, $size = 'large' ) {
    // ACF image field returns either an attachment ID, an array, or empty.
    if ( function_exists( 'get_field' ) ) {
        $img = get_field( 'promo_image', $post_id );
        if ( is_array( $img ) && ! empty( $img['url'] ) ) {
            if ( ! empty( $img['sizes'][ $size ] ) ) return $img['sizes'][ $size ];
            return $img['url'];
        }
        if ( is_numeric( $img ) ) {
            $url = wp_get_attachment_image_url( (int) $img, $size );
            if ( $url ) return $url;
        }
    }
    // Raw meta fallback (handles cases where ACF stored just an ID).
    $raw = get_post_meta( $post_id, 'promo_image', true );
    if ( is_numeric( $raw ) ) {
        $url = wp_get_attachment_image_url( (int) $raw, $size );
        if ( $url ) return $url;
    }
    // Seeded promotions: theme-asset URL string.
    $seeded = get_post_meta( $post_id, 'promo_image_url', true );
    return $seeded ?: '';
}

/**
 * Get promo body text (ACF textarea). nl2br for line breaks; otherwise plain.
 */
function tlt_promo_body( $post_id ) {
    $body = get_post_meta( $post_id, 'promo_body', true );
    return $body;
}

/* ---------------------------------------------------------------------------
 * Renderer — one card style ("feature row") used across homepage + Visit + Edu.
 * Sitewide banner has its own renderer below.
 * ------------------------------------------------------------------------- */

/**
 * Render a single promo as a feature-row (image + text alternating left/right).
 * Matches the existing .feature-row markup used on page-home.php so styling
 * carries over unchanged.
 *
 * @param int|WP_Post $promo
 * @param int $index   0-based — even = image right, odd = image left.
 * @param string $variant  'feature-row' (default) | 'support' (centered small)
 */
function tlt_render_promo( $promo, $index = 0, $variant = 'feature-row' ) {
    $post_id   = is_object( $promo ) ? $promo->ID : (int) $promo;
    if ( ! $post_id ) return;
    $title     = get_the_title( $post_id );
    $body      = tlt_promo_body( $post_id );
    $img       = tlt_promo_image_url( $post_id, 'large' );
    $cta_label = get_post_meta( $post_id, 'promo_cta_label', true );
    $cta_url   = get_post_meta( $post_id, 'promo_cta_url', true );

    if ( $variant === 'support' ) {
        // Smaller centered card used in the homepage "Support" group.
        ?>
        <a href="<?php echo esc_url( $cta_url ?: '#' ); ?>" style="text-align:center;text-decoration:none;display:inline-block;max-width:300px">
            <?php if ( $img ) : ?>
                <img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $title ); ?>" style="max-width:280px;margin-bottom:0.5rem">
            <?php endif; ?>
            <?php if ( $body ) : ?>
                <p style="font-size:0.85rem;color:var(--color-muted);margin:0"><?php echo esc_html( $body ); ?></p>
            <?php endif; ?>
        </a>
        <?php
        return;
    }

    if ( $variant === 'edu-card' ) {
        // Card style used on the /education/ page "Currently Happening" grid.
        ?>
        <a href="<?php echo esc_url( $cta_url ?: '#' ); ?>" class="edu-current-card">
            <?php if ( $img ) : ?>
                <div class="img-wrap"><img src="<?php echo esc_url( $img ); ?>" alt=""></div>
            <?php endif; ?>
            <div class="body">
                <h3><?php echo esc_html( $title ); ?></h3>
                <?php if ( $body ) : ?><p><?php echo esc_html( $body ); ?></p><?php endif; ?>
            </div>
        </a>
        <?php
        return;
    }

    // Default: feature-row (alternating left/right by index).
    $image_left = ( $index % 2 === 1 );
    $style_attr = $index > 0 ? ' style="margin-top:3rem"' : '';
    ?>
    <div class="feature-row"<?php echo $style_attr; ?>>
        <?php if ( $image_left ) : ?>
            <?php if ( $img ) : ?>
                <a href="<?php echo esc_url( $cta_url ?: '#' ); ?>"><img src="<?php echo esc_url( $img ); ?>" alt="" class="promo-image"></a>
            <?php endif; ?>
            <div>
                <h2><?php echo esc_html( $title ); ?></h2>
                <?php if ( $body ) : ?><p><?php echo wp_kses_post( wpautop_first_line( $body ) ); ?></p><?php endif; ?>
                <?php if ( $cta_label && $cta_url ) : ?>
                    <p><a href="<?php echo esc_url( $cta_url ); ?>" class="btn btn-primary"><?php echo esc_html( $cta_label ); ?></a></p>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div>
                <h2><?php echo esc_html( $title ); ?></h2>
                <?php if ( $body ) : ?><p><?php echo wp_kses_post( wpautop_first_line( $body ) ); ?></p><?php endif; ?>
                <?php if ( $cta_label && $cta_url ) : ?>
                    <p><a href="<?php echo esc_url( $cta_url ); ?>" class="btn btn-primary"><?php echo esc_html( $cta_label ); ?></a></p>
                <?php endif; ?>
            </div>
            <?php if ( $img ) : ?>
                <a href="<?php echo esc_url( $cta_url ?: '#' ); ?>"><img src="<?php echo esc_url( $img ); ?>" alt="" class="promo-image"></a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Render the full homepage promo block for a given section (Education, Special
 * Events, Get Involved, Support). Outputs the wrapping <section> with eyebrow
 * + heading + lede if the page-home template asks for it. Returns true if any
 * promos were rendered (so caller can decide whether to emit the section
 * wrapper at all).
 */
function tlt_render_homepage_section( $section_key, $section_num, $eyebrow, $heading, $lede = '', $block_class = 'block' ) {
    $promos = tlt_get_active_promotions( 'homepage', [ 'homepage_section' => $section_key ] );
    if ( ! $promos ) return false;
    ?>
    <section class="<?php echo esc_attr( $block_class ); ?>" data-section-num="<?php echo esc_attr( $section_num ); ?>">
      <div class="container">
        <div class="section-head">
          <div class="eyebrow"><span class="num"><?php echo esc_html( $section_num ); ?></span> <?php echo esc_html( $eyebrow ); ?></div>
          <h2><?php echo esc_html( $heading ); ?></h2>
          <?php if ( $lede ) : ?><p><?php echo esc_html( $lede ); ?></p><?php endif; ?>
        </div>
        <?php if ( $section_key === 'support' ) : ?>
          <div style="display:flex;justify-content:center;gap:2rem;flex-wrap:wrap;align-items:center">
            <?php foreach ( $promos as $i => $p ) tlt_render_promo( $p, $i, 'support' ); ?>
          </div>
        <?php else : ?>
          <?php foreach ( $promos as $i => $p ) tlt_render_promo( $p, $i, 'feature-row' ); ?>
        <?php endif; ?>
      </div>
    </section>
    <?php
    return true;
}

/**
 * Trim a textarea body to its first paragraph and pass it through wpautop so
 * single line breaks render as a single <p> rather than wrapping each line.
 * Body is plain text (textarea), so we keep escaping in the caller via wp_kses_post.
 */
if ( ! function_exists( 'wpautop_first_line' ) ) {
    function wpautop_first_line( $text ) {
        return nl2br( esc_html( trim( (string) $text ) ) );
    }
}

/* ---------------------------------------------------------------------------
 * Sitewide banner — small strip at top of every page when any "sitewide" promo
 * is active. Dismiss button sets a 7-day cookie per visitor.
 * ------------------------------------------------------------------------- */

function tlt_render_sitewide_banner() {
    $promos = tlt_get_active_promotions( 'sitewide' );
    if ( ! $promos ) return;
    $p = $promos[0]; // Highest-priority sitewide promo wins. (We don't stack banners.)

    $title     = get_the_title( $p->ID );
    $body      = tlt_promo_body( $p->ID );
    $cta_label = get_post_meta( $p->ID, 'promo_cta_label', true );
    $cta_url   = get_post_meta( $p->ID, 'promo_cta_url', true );
    $key       = 'tlt_dismiss_promo_' . $p->ID; // dismissal scoped per promo
    ?>
    <div class="sitewide-banner" data-dismiss-key="<?php echo esc_attr( $key ); ?>" role="region" aria-label="Site announcement">
      <div class="container sitewide-banner-inner">
        <div class="sitewide-banner-text">
          <strong><?php echo esc_html( $title ); ?></strong>
          <?php if ( $body ) : ?><span class="sitewide-banner-body"><?php echo esc_html( $body ); ?></span><?php endif; ?>
          <?php if ( $cta_label && $cta_url ) : ?>
            <a class="sitewide-banner-cta" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?> &rarr;</a>
          <?php endif; ?>
        </div>
        <button type="button" class="sitewide-banner-dismiss" aria-label="Dismiss banner">&times;</button>
      </div>
    </div>
    <script>
      (function () {
        var bar = document.currentScript.previousElementSibling;
        if (!bar || !bar.classList.contains('sitewide-banner')) return;
        var key = bar.getAttribute('data-dismiss-key');
        try {
          if (document.cookie.indexOf(key + '=1') !== -1) { bar.remove(); return; }
        } catch (e) {}
        var btn = bar.querySelector('.sitewide-banner-dismiss');
        if (btn) btn.addEventListener('click', function () {
          var d = new Date(); d.setDate(d.getDate() + 7);
          document.cookie = key + '=1; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
          bar.style.transition = 'opacity 0.2s, max-height 0.3s';
          bar.style.maxHeight = bar.offsetHeight + 'px';
          requestAnimationFrame(function () { bar.style.maxHeight = '0'; bar.style.opacity = '0'; });
          setTimeout(function () { bar.remove(); }, 350);
        });
      })();
    </script>
    <?php
}

/* ---------------------------------------------------------------------------
 * Migration seeder — Tools > "TLT: Seed Homepage Promotions" admin page.
 *
 * Creates the 6 hardcoded homepage promo records (Spring Education, Summer
 * Camp, Golden Ball Murder, Harbor Lights, Now Hiring, Season Tickets) plus
 * Fred Meyer rewards in the Support group. Idempotent — re-running won't
 * double-create.
 *
 * After Chris edits a seeded promo through the admin, the ACF fields take
 * over and the seed metadata becomes irrelevant.
 * ------------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
    add_management_page(
        'TLT: Seed Homepage Promotions',
        'TLT: Seed Promotions',
        'manage_options',
        'tlt-seed-promotions',
        'tlt_render_seed_promotions_page'
    );
} );

function tlt_seed_homepage_promotions_data() {
    $theme_uri = function_exists( 'get_template_directory_uri' ) ? get_template_directory_uri() : '';
    return [
        [
            'slug'    => 'promo-spring-education',
            'title'   => 'Spring Education',
            'body'    => 'Three great programs for students in grades 1–8 — classes, after-school sessions, and youth productions.',
            'image'   => $theme_uri . '/assets/home-promo-spring-classes.png',
            'cta'     => [ 'Learn More', '/spring-classes-2026/' ],
            'locations' => [ 'homepage', 'education' ],
            'section' => 'education',
            'priority'=> 10,
            'start'   => '2026-01-01',
            'end'     => '2026-06-30',
        ],
        [
            'slug'    => 'promo-summer-camp-2026',
            'title'   => 'Summer Camp at TLT',
            'body'    => 'Registration now open for summer 2026 camps. Performance-based programs for kids who love the stage.',
            'image'   => $theme_uri . '/assets/home-promo-summer-camp.png',
            'cta'     => [ 'Camp Details', '/summer-camp-2026/' ],
            'locations' => [ 'homepage', 'education' ],
            'section' => 'education',
            'priority'=> 20,
            'start'   => '2026-01-01',
            'end'     => '2026-09-01',
        ],
        [
            'slug'    => 'promo-golden-ball-murder',
            'title'   => 'The Golden Ball Murder',
            'body'    => 'Our annual murder mystery dinner returns at a NEW LOCATION! Join us May 28–31, 2026 at La Quinta Inn for a fun-filled evening.',
            'image'   => $theme_uri . '/assets/home-promo-mystery.jpg',
            'cta'     => [ 'Mystery Dinner Info', '/golden-ball-murder/' ],
            'locations' => [ 'homepage' ],
            'section' => 'special_events',
            'priority'=> 10,
            'start'   => '2026-01-01',
            'end'     => '2026-06-01',
        ],
        [
            'slug'    => 'promo-harbor-lights',
            'title'   => 'Dinner at Harbor Lights',
            'body'    => "Grab dinner before the show at Anthony's Harbor Lights. Mention TLT and a portion supports the theatre — great food and great support, all in one stop.",
            'image'   => $theme_uri . '/assets/home-promo-harbor-lights.jpg',
            'cta'     => [ 'How It Works', '/harbor-lights/' ],
            'locations' => [ 'homepage', 'visit' ],
            'section' => 'special_events',
            'priority'=> 20,
            'start'   => '2026-01-01',
            'end'     => '2027-12-31',
        ],
        [
            'slug'    => 'promo-now-hiring-2026-27',
            'title'   => 'Now Hiring',
            'body'    => 'TLT is currently accepting applications for production team members for the 2026–2027 season. Join the crew that brings every show to life.',
            'image'   => $theme_uri . '/assets/home-promo-hiring.png',
            'cta'     => [ 'View Openings', '/now-hiring-2026-27/' ],
            'locations' => [ 'homepage', 'get_involved' ],
            'section' => 'get_involved',
            'priority'=> 10,
            'start'   => '2026-01-01',
            'end'     => '2026-09-01',
        ],
        [
            'slug'    => 'promo-season-tickets-2026-27',
            'title'   => '2026–2027 Season Tickets',
            'body'    => 'New and renewing orders welcome. Lock in your seats for the entire upcoming season and never miss a show.',
            'image'   => $theme_uri . '/assets/home-promo-season-tickets.jpg',
            'cta'     => [ 'Order Now', '/season-tickets/' ],
            'locations' => [ 'homepage', 'get_involved' ],
            'section' => 'get_involved',
            'priority'=> 20,
            'start'   => '2026-01-01',
            'end'     => '2027-02-01',
        ],
        [
            'slug'    => 'promo-fred-meyer-rewards',
            'title'   => 'Fred Meyer Community Rewards',
            'body'    => 'Link your Fred Meyer Rewards card → TLT',
            'image'   => $theme_uri . '/assets/sponsor-fred-meyer.jpg',
            'cta'     => [ 'How to Link', '/news/fred-meyer-community-rewards/' ],
            'locations' => [ 'homepage' ],
            'section' => 'support',
            'priority'=> 10,
            'start'   => '2026-01-01',
            'end'     => '2030-01-01',
        ],
    ];
}

function tlt_render_seed_promotions_page() {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'No permission.' );

    $did_seed = false;
    $created  = [];
    $skipped  = [];

    if ( isset( $_POST['tlt_seed_run'] ) && check_admin_referer( 'tlt_seed_promos' ) ) {
        $did_seed = true;
        foreach ( tlt_seed_homepage_promotions_data() as $p ) {
            // Idempotency: skip if a promotion with this slug already exists.
            $existing = get_page_by_path( $p['slug'], OBJECT, 'tlt_promotion' );
            if ( $existing ) { $skipped[] = $p['title']; continue; }
            $post_id = wp_insert_post( [
                'post_type'   => 'tlt_promotion',
                'post_status' => 'publish',
                'post_title'  => $p['title'],
                'post_name'   => $p['slug'],
            ], true );
            if ( is_wp_error( $post_id ) || ! $post_id ) continue;

            update_post_meta( $post_id, 'promo_start_date',         $p['start'] );
            update_post_meta( $post_id, 'promo_end_date',           $p['end'] );
            update_post_meta( $post_id, 'promo_locations',          $p['locations'] );
            update_post_meta( $post_id, 'promo_homepage_section',   $p['section'] );
            update_post_meta( $post_id, 'promo_priority',           $p['priority'] );
            update_post_meta( $post_id, 'promo_body',               $p['body'] );
            update_post_meta( $post_id, 'promo_cta_label',          $p['cta'][0] );
            update_post_meta( $post_id, 'promo_cta_url',            $p['cta'][1] );
            // Fallback image URL for theme-asset references that aren't in the
            // media library yet. ACF picker will override this if Chris uploads.
            update_post_meta( $post_id, 'promo_image_url',          $p['image'] );

            $created[] = $p['title'];
        }
    }
    ?>
    <div class="wrap">
        <h1>TLT: Seed Homepage Promotions</h1>
        <p>One-time migration tool. Creates Promotion records for the seven hardcoded homepage promos so Chris can edit/remove them through the WordPress admin instead of needing developer changes.</p>
        <p><strong>This is idempotent.</strong> Running it twice won't duplicate anything. Already-existing slugs are skipped.</p>

        <?php if ( $did_seed ) : ?>
            <div class="notice notice-success"><p>
                Created <?php echo count( $created ); ?>, skipped <?php echo count( $skipped ); ?>.
                <?php if ( $created ) : ?><br><strong>Created:</strong> <?php echo esc_html( implode( ', ', $created ) ); ?><?php endif; ?>
                <?php if ( $skipped ) : ?><br><strong>Skipped (already exist):</strong> <?php echo esc_html( implode( ', ', $skipped ) ); ?><?php endif; ?>
            </p></div>
            <p><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=tlt_promotion' ) ); ?>" class="button button-primary">View all promotions &rarr;</a></p>
        <?php else : ?>
            <h2>What will be created</h2>
            <table class="widefat striped">
                <thead><tr><th>Slug</th><th>Title</th><th>Section</th><th>Dates</th></tr></thead>
                <tbody>
                <?php foreach ( tlt_seed_homepage_promotions_data() as $p ) :
                    $existing = get_page_by_path( $p['slug'], OBJECT, 'tlt_promotion' );
                ?>
                    <tr>
                        <td><code><?php echo esc_html( $p['slug'] ); ?></code></td>
                        <td><?php echo esc_html( $p['title'] ); ?>
                            <?php if ( $existing ) echo ' <em>(already exists — will skip)</em>'; ?>
                        </td>
                        <td><?php echo esc_html( $p['section'] ); ?></td>
                        <td><?php echo esc_html( $p['start'] . ' → ' . $p['end'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <form method="post" style="margin-top:1.5rem">
                <?php wp_nonce_field( 'tlt_seed_promos' ); ?>
                <button type="submit" name="tlt_seed_run" value="1" class="button button-primary">Seed homepage promotions now</button>
            </form>
        <?php endif; ?>
    </div>
    <?php
}
