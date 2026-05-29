<?php
/**
 * ACF field groups for page templates that need "fill-in-the-blanks" editing.
 *
 * Each field group is bound to one or more page templates via ACF location
 * rules. When Chris picks the template in WP admin, the editor reloads with
 * those specific input boxes (image picker, headline, body, CTAs, etc.) —
 * no need to know which meta keys exist or what the template expects.
 *
 * Field definitions live here (in PHP, in git) — NOT in the database. This
 * keeps them version-controlled and synced across Local + Cloudways.
 *
 * Convention: field "name" matches the meta key the template reads, so legacy
 * pages with raw post_meta values keep rendering correctly after this is
 * added (ACF reads and writes the same meta keys it stores).
 *
 * If ACF is deactivated, the field groups disappear but the post_meta values
 * remain and the templates continue to render them.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ---------------------------------------------------------------------------
 * Helper: hide the block editor body for templates we've ACF-ified.
 * The full editing surface is then the ACF field group — no "write your
 * content here" blank canvas that confuses Chris.
 * ------------------------------------------------------------------------- */

add_filter( 'use_block_editor_for_post', function ( $use, $post ) {
    if ( ! $post || $post->post_type !== 'page' ) return $use;
    $tpl = get_page_template_slug( $post->ID );
    if ( in_array( $tpl, tlt_acf_managed_templates(), true ) ) return false;
    return $use;
}, 10, 2 );

// Hide the post_content textarea on the Classic editor for managed templates,
// since the body lives in ACF for those templates.
add_action( 'admin_head', function () {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || $screen->base !== 'post' || $screen->post_type !== 'page' ) return;
    global $post;
    if ( ! $post ) return;
    $tpl = get_page_template_slug( $post->ID );
    if ( ! in_array( $tpl, tlt_acf_managed_templates(), true ) ) return;
    // Remove the post_content editor entirely on managed templates.
    remove_post_type_support( 'page', 'editor' );
    echo '<style>#postdivrich, #wp-content-wrap { display: none !important; }</style>';
} );

/**
 * Templates that have an ACF field group defined and shouldn't show the
 * block editor body. Add to this list when retrofitting more templates.
 */
function tlt_acf_managed_templates() {
    return [
        'page-designed.php',
    ];
}

/* ---------------------------------------------------------------------------
 * Field group: Designed Page
 *
 * Spec from ARCHITECTURE.md: hero image (desktop + mobile), headline (uses
 * post_title), optional subhead, rich-text body, up to 3 CTAs.
 *
 * Field NAMES match the meta keys page-designed.php already reads (designed_*),
 * so any legacy pages keep working.
 * ------------------------------------------------------------------------- */

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'    => 'group_tlt_page_designed',
        'title'  => 'Designed Page Content',
        'fields' => [

            // --- Hero ---
            [
                'key' => 'field_designed_tab_hero',
                'label' => 'Hero',
                'type' => 'tab',
                'placement' => 'top',
            ],
            [
                'key'           => 'field_designed_desktop_image',
                'label'         => 'Desktop hero image',
                'name'          => 'designed_desktop_image',
                'type'          => 'image',
                'return_format' => 'url',
                'preview_size'  => 'medium',
                'instructions'  => 'Full-width hero image at the top of the page. Recommend 1600×900 or larger. If left blank, the Featured Image is used.',
            ],
            [
                'key'           => 'field_designed_mobile_image',
                'label'         => 'Mobile hero image (optional)',
                'name'          => 'designed_mobile_image',
                'type'          => 'image',
                'return_format' => 'url',
                'preview_size'  => 'medium',
                'instructions'  => 'Optional alternate image for phones. Use a taller / squarer crop if the desktop image gets cropped awkwardly on portrait screens. Leave blank to use the desktop image everywhere.',
            ],

            // --- Headline / body ---
            [
                'key' => 'field_designed_tab_body',
                'label' => 'Headline & Body',
                'type' => 'tab',
                'placement' => 'top',
            ],
            [
                'key' => 'field_designed_headline_note',
                'label' => '',
                'type' => 'message',
                'message' => '<strong>Headline:</strong> uses the page Title field at the top of this screen.',
                'new_lines' => 'wpautop',
                'esc_html' => 0,
            ],
            [
                'key'          => 'field_designed_subhead',
                'label'        => 'Subhead (optional)',
                'name'         => 'designed_subhead',
                'type'         => 'text',
                'instructions' => 'A single line that appears just below the headline. Keep short.',
            ],
            [
                'key'          => 'field_designed_body',
                'label'        => 'Body',
                'name'         => 'designed_body',
                'type'         => 'wysiwyg',
                'tabs'         => 'visual,text',
                'toolbar'      => 'full',
                'media_upload' => 1,
                'instructions' => 'The main page copy. Headings, paragraphs, images, and links are supported.',
            ],

            // --- CTAs ---
            [
                'key' => 'field_designed_tab_ctas',
                'label' => 'Buttons (up to 3)',
                'type' => 'tab',
                'placement' => 'top',
            ],
            [
                'key' => 'field_designed_cta_note',
                'label' => '',
                'type' => 'message',
                'message' => 'Add up to three buttons after the body. Leave a row blank to skip it.',
                'new_lines' => 'wpautop',
                'esc_html' => 0,
            ],
        ],
        'location' => [
            [
                [ 'param' => 'page_template', 'operator' => '==', 'value' => 'page-designed.php' ],
            ],
        ],
        'menu_order'             => 0,
        'position'               => 'normal',
        'style'                  => 'default',
        'label_placement'        => 'top',
        'instruction_placement'  => 'label',
    ] );

    // Generate the three CTA blocks. Each one is independent — they don't
    // depend on the previous CTA being filled. Chris can fill in any
    // combination; the template skips rows where label OR url is empty.
    foreach ( [ 1, 2, 3 ] as $n ) {
        acf_add_local_field_group( [
            'key'    => "group_tlt_page_designed_cta_{$n}",
            'title'  => "Button {$n}",
            'fields' => [
                [
                    'key'         => "field_designed_cta_{$n}_label",
                    'label'       => 'Button label',
                    'name'        => "designed_cta_{$n}_label",
                    'type'        => 'text',
                    'placeholder' => $n === 1 ? 'e.g. Buy Tickets' : '',
                    'wrapper'     => [ 'width' => '40' ],
                ],
                [
                    'key'         => "field_designed_cta_{$n}_url",
                    'label'       => 'Button URL',
                    'name'        => "designed_cta_{$n}_url",
                    'type'        => 'url',
                    'placeholder' => 'https://… or /page-slug/',
                    'wrapper'     => [ 'width' => '60' ],
                ],
                [
                    'key'           => "field_designed_cta_{$n}_style",
                    'label'         => 'Style',
                    'name'          => "designed_cta_{$n}_style",
                    'type'          => 'select',
                    'choices'       => [
                        'primary' => 'Solid (primary)',
                        'outline' => 'Outline',
                    ],
                    'default_value' => 'primary',
                    'wrapper'       => [ 'width' => '50' ],
                ],
                [
                    'key'           => "field_designed_cta_{$n}_target",
                    'label'         => 'Open in new tab?',
                    'name'          => "designed_cta_{$n}_target",
                    'type'          => 'true_false',
                    'ui'            => 1,
                    'ui_on_text'    => '_blank',
                    'ui_off_text'   => 'same tab',
                    'wrapper'       => [ 'width' => '50' ],
                    'instructions'  => 'Turn ON for external links / PDFs.',
                ],
            ],
            'location' => [
                [
                    [ 'param' => 'page_template', 'operator' => '==', 'value' => 'page-designed.php' ],
                ],
            ],
            'menu_order'             => $n, // 1, 2, 3 so they appear below the main group in order
            'position'               => 'normal',
            'style'                  => 'default',
            'label_placement'        => 'top',
            'instruction_placement'  => 'label',
        ] );
    }
} );

/**
 * Bridge: ACF true_false stores 0/1, but page-designed.php reads
 * 'designed_cta_N_target' expecting '_blank' or empty. Translate on save so
 * legacy templates keep working with the simpler string convention.
 */
add_filter( 'acf/update_value/name=designed_cta_1_target', 'tlt_designed_cta_target_save' );
add_filter( 'acf/update_value/name=designed_cta_2_target', 'tlt_designed_cta_target_save' );
add_filter( 'acf/update_value/name=designed_cta_3_target', 'tlt_designed_cta_target_save' );
function tlt_designed_cta_target_save( $value ) {
    return $value ? '_blank' : '';
}
// Same in reverse so the toggle shows ON when the stored value is '_blank'.
add_filter( 'acf/load_value/name=designed_cta_1_target', 'tlt_designed_cta_target_load' );
add_filter( 'acf/load_value/name=designed_cta_2_target', 'tlt_designed_cta_target_load' );
add_filter( 'acf/load_value/name=designed_cta_3_target', 'tlt_designed_cta_target_load' );
function tlt_designed_cta_target_load( $value ) {
    return ( $value === '_blank' || $value === '1' || $value === 1 ) ? 1 : 0;
}
