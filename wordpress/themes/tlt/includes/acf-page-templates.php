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
        'page-campaign.php',
        'page-contact.php',
        'page-ticketing.php',
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

/* ---------------------------------------------------------------------------
 * Field group: Campaign Page
 *
 * Hero (featured image) + caption + lead paragraph + body + donate CTA band
 * + optional donor recognition list.
 *
 * Field names match the meta keys page-campaign.php already reads (campaign_*).
 * ------------------------------------------------------------------------- */

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'    => 'group_tlt_page_campaign',
        'title'  => 'Campaign Page Content',
        'fields' => [
            [ 'key' => 'field_campaign_tab_hero', 'label' => 'Hero & Lead', 'type' => 'tab' ],
            [
                'key' => 'field_campaign_hero_note',
                'label' => '',
                'type' => 'message',
                'message' => '<strong>Hero image:</strong> set the Featured Image at the right side of this screen.',
                'esc_html' => 0,
            ],
            [
                'key'          => 'field_campaign_hero_caption',
                'label'        => 'Hero caption (optional)',
                'name'         => 'campaign_hero_caption',
                'type'         => 'text',
                'instructions' => 'Italic line below the hero image, e.g. "Photo: Cast of A Christmas Story, 2024".',
            ],
            [
                'key'          => 'field_campaign_lead',
                'label'        => 'Lead paragraph',
                'name'         => 'campaign_lead',
                'type'         => 'textarea',
                'rows'         => 3,
                'instructions' => 'Large display-font paragraph at the top of the body. Keep this to one or two strong sentences — it sets the tone for the whole page.',
            ],

            [ 'key' => 'field_campaign_tab_body', 'label' => 'Body', 'type' => 'tab' ],
            [
                'key'          => 'field_campaign_body',
                'label'        => 'Body',
                'name'         => 'campaign_body',
                'type'         => 'wysiwyg',
                'tabs'         => 'visual,text',
                'toolbar'      => 'full',
                'media_upload' => 1,
                'instructions' => 'The main story of the campaign. Headings, paragraphs, images, lists.',
            ],

            [ 'key' => 'field_campaign_tab_cta', 'label' => 'Donate CTA', 'type' => 'tab' ],
            [
                'key'           => 'field_campaign_donate_url',
                'label'         => 'Donate URL',
                'name'          => 'campaign_donate_url',
                'type'          => 'url',
                'default_value' => 'https://tlt.ludus.com/donate.php',
                'instructions'  => 'Where the donate button goes. Defaults to the Ludus donation page.',
            ],
            [
                'key'           => 'field_campaign_cta_heading',
                'label'         => 'CTA heading',
                'name'          => 'campaign_cta_heading',
                'type'          => 'text',
                'default_value' => 'Become part of the campaign',
                'wrapper'       => [ 'width' => '60' ],
            ],
            [
                'key'           => 'field_campaign_cta_button',
                'label'         => 'Button label',
                'name'          => 'campaign_cta_button',
                'type'          => 'text',
                'default_value' => 'Donate Now',
                'wrapper'       => [ 'width' => '40' ],
            ],
            [
                'key'          => 'field_campaign_cta_body',
                'label'        => 'CTA supporting text (optional)',
                'name'         => 'campaign_cta_body',
                'type'         => 'textarea',
                'rows'         => 2,
                'instructions' => 'One-line description inside the dark CTA band, e.g. "Every dollar goes directly to the new lobby project."',
            ],

            [ 'key' => 'field_campaign_tab_donors', 'label' => 'Donors (optional)', 'type' => 'tab' ],
            [
                'key' => 'field_campaign_donors_note',
                'label' => '',
                'type' => 'message',
                'message' => 'Format: one tier per block. Start a tier with <code>## Tier name</code>, then one donor name per line.<br>Example:<br><pre>## Founders Circle ($5,000+)
Jane &amp; John Smith
Tacoma Foundation

## Producers ($1,000+)
Alice Doe
Bob Lee</pre>',
                'esc_html' => 0,
            ],
            [
                'key'          => 'field_campaign_donors',
                'label'        => 'Donors list',
                'name'         => 'campaign_donors_text',
                'type'         => 'textarea',
                'rows'         => 10,
                'instructions' => 'Leave blank to hide the donors section.',
            ],
        ],
        'location' => [
            [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'page-campaign.php' ] ],
        ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
        'label_placement'        => 'top',
        'instruction_placement'  => 'label',
    ] );
} );

/**
 * Parse the campaign donors textarea into the [{tier_name, names: []}] shape
 * page-campaign.php expects. Lines starting with "## " begin a new tier;
 * other non-blank lines are donor names in the current tier.
 */
function tlt_parse_campaign_donors( $text ) {
    $out = [];
    $current = null;
    foreach ( preg_split( "/\r?\n/", (string) $text ) as $line ) {
        $line = trim( $line );
        if ( $line === '' ) continue;
        if ( strpos( $line, '## ' ) === 0 ) {
            if ( $current ) $out[] = $current;
            $current = [ 'tier_name' => trim( substr( $line, 3 ) ), 'names' => [] ];
        } else {
            if ( ! $current ) $current = [ 'tier_name' => '', 'names' => [] ];
            $current['names'][] = $line;
        }
    }
    if ( $current ) $out[] = $current;
    return $out;
}

/* ---------------------------------------------------------------------------
 * Field group: Contact Page
 *
 * Field names match the existing meta keys (contact_*). Adds contact_intro
 * for the lead paragraph (was using post_content before).
 * ------------------------------------------------------------------------- */

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'    => 'group_tlt_page_contact',
        'title'  => 'Contact Page Content',
        'fields' => [
            [
                'key'          => 'field_contact_intro',
                'label'        => 'Intro paragraph',
                'name'         => 'contact_intro',
                'type'         => 'wysiwyg',
                'tabs'         => 'visual,text',
                'toolbar'      => 'basic',
                'media_upload' => 0,
                'instructions' => 'One-paragraph welcome message under the page title, above the form.',
            ],
            [
                'key'          => 'field_contact_form_shortcode',
                'label'        => 'Contact form shortcode',
                'name'         => 'contact_form_shortcode',
                'type'         => 'text',
                'placeholder'  => '[contact-form-7 id="1312" title="Contact"]',
                'instructions'  => 'CF7 (or other form plugin) shortcode. Leave blank to use the default contact form.',
            ],
            [
                'key'          => 'field_contact_box_office',
                'label'        => 'Box office hours',
                'name'         => 'contact_box_office',
                'type'         => 'textarea',
                'rows'         => 4,
                'instructions' => 'Plain text — line breaks are preserved.',
                'wrapper'      => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_contact_address',
                'label'        => 'Address',
                'name'         => 'contact_address',
                'type'         => 'textarea',
                'rows'         => 4,
                'wrapper'      => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_contact_phone',
                'label'        => 'Phone',
                'name'         => 'contact_phone',
                'type'         => 'text',
                'default_value'=> '(253) 272-2281',
                'wrapper'      => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_contact_email',
                'label'        => 'Email',
                'name'         => 'contact_email',
                'type'         => 'email',
                'default_value'=> 'boxoffice@tacomalittletheatre.com',
                'wrapper'      => [ 'width' => '50' ],
            ],
        ],
        'location' => [
            [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'page-contact.php' ] ],
        ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
        'label_placement'        => 'top',
        'instruction_placement'  => 'label',
    ] );
} );

/* ---------------------------------------------------------------------------
 * Field group: Ticketing Page
 *
 * Body + primary/secondary CTAs + tier cards. Tier cards are entered as a
 * formatted textarea (no ACF Pro Repeater in Free). Format documented inline.
 * ------------------------------------------------------------------------- */

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'    => 'group_tlt_page_ticketing',
        'title'  => 'Ticketing Page Content',
        'fields' => [
            [ 'key' => 'field_ticketing_tab_body', 'label' => 'Body', 'type' => 'tab' ],
            [
                'key'          => 'field_ticketing_body',
                'label'        => 'Body',
                'name'         => 'ticketing_body',
                'type'         => 'wysiwyg',
                'tabs'         => 'visual,text',
                'toolbar'      => 'full',
                'media_upload' => 1,
                'instructions' => 'Body copy above the pricing tiers and CTAs.',
            ],

            [ 'key' => 'field_ticketing_tab_ctas', 'label' => 'CTAs', 'type' => 'tab' ],
            [
                'key'         => 'field_ticketing_cta_primary_label',
                'label'       => 'Primary button label',
                'name'        => 'cta_primary_label',
                'type'        => 'text',
                'placeholder' => 'Buy Tickets',
                'wrapper'     => [ 'width' => '50' ],
            ],
            [
                'key'         => 'field_ticketing_cta_primary_url',
                'label'       => 'Primary button URL',
                'name'        => 'cta_primary_url',
                'type'        => 'url',
                'wrapper'     => [ 'width' => '50' ],
            ],
            [
                'key'         => 'field_ticketing_cta_secondary_label',
                'label'       => 'Secondary button label (optional)',
                'name'        => 'cta_secondary_label',
                'type'        => 'text',
                'wrapper'     => [ 'width' => '50' ],
            ],
            [
                'key'         => 'field_ticketing_cta_secondary_url',
                'label'       => 'Secondary button URL (optional)',
                'name'        => 'cta_secondary_url',
                'type'        => 'url',
                'wrapper'     => [ 'width' => '50' ],
            ],

            [ 'key' => 'field_ticketing_tab_tiers', 'label' => 'Pricing tiers (optional)', 'type' => 'tab' ],
            [
                'key' => 'field_ticketing_tiers_note',
                'label' => '',
                'type' => 'message',
                'message' => 'Format: one tier per block, separated by a blank line. Each tier looks like:<br><pre>Heading: Musicals
Price: $32
Note: General admission · students/seniors $28
Body: All seats unreserved; doors open 30 minutes before curtain.</pre>',
                'esc_html' => 0,
            ],
            [
                'key'          => 'field_ticketing_tiers',
                'label'        => 'Pricing tiers',
                'name'         => 'ticketing_tiers_text',
                'type'         => 'textarea',
                'rows'         => 12,
                'instructions' => 'Leave blank to hide the pricing tiers section.',
            ],
        ],
        'location' => [
            [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'page-ticketing.php' ] ],
        ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
        'label_placement'        => 'top',
        'instruction_placement'  => 'label',
    ] );
} );

/**
 * Parse the ticketing tiers textarea into [{heading, price, price_note, body}].
 * Tiers are separated by blank lines; within a tier, "Key: value" lines map
 * to known keys (case-insensitive). Unknown keys are ignored. Anything that
 * doesn't match "Key: value" is appended to the body.
 */
function tlt_parse_ticketing_tiers( $text ) {
    $tiers = [];
    $blocks = preg_split( '/\n\s*\n/', trim( (string) $text ) );
    $map = [
        'heading'    => 'heading',
        'price'      => 'price',
        'note'       => 'price_note',
        'price_note' => 'price_note',
        'body'       => 'body',
    ];
    foreach ( $blocks as $block ) {
        $tier = [ 'heading' => '', 'price' => '', 'price_note' => '', 'body' => '' ];
        $body_lines = [];
        foreach ( preg_split( "/\r?\n/", $block ) as $line ) {
            $line = trim( $line );
            if ( $line === '' ) continue;
            if ( preg_match( '/^([A-Za-z_ ]+):\s*(.+)$/', $line, $m ) ) {
                $k = strtolower( trim( $m[1] ) );
                if ( isset( $map[ $k ] ) ) {
                    if ( $map[ $k ] === 'body' ) $body_lines[] = $m[2];
                    else $tier[ $map[ $k ] ] = $m[2];
                    continue;
                }
            }
            $body_lines[] = $line;
        }
        if ( $body_lines ) $tier['body'] = trim( $tier['body'] . "\n" . implode( "\n", $body_lines ) );
        if ( $tier['heading'] || $tier['price'] || $tier['body'] ) $tiers[] = $tier;
    }
    return $tiers;
}
