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

/**
 * Auto-reload the page editor after save when the template was just changed
 * to an ACF-managed one. Without this, Chris has to manually navigate back
 * to Pages → click the page → reload, because the Gutenberg editor doesn't
 * full-reload after save and so the editor stays Gutenberg instead of
 * switching to the classic+ACF view that templates need.
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) return;
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'page' ) return;
    $managed = wp_json_encode( tlt_acf_managed_templates() );
    $js = <<<JS
(function () {
  if (typeof wp === 'undefined' || !wp.data || !wp.data.select('core/editor')) return;
  var MANAGED = {$managed};
  function get() {
    return wp.data.select('core/editor').getEditedPostAttribute('template') || '';
  }
  var initial   = get();
  var wasSaving = false;
  var dirty     = false;

  wp.data.subscribe(function () {
    var current   = get();
    var isSaving  = wp.data.select('core/editor').isSavingPost();
    var isAutosave= wp.data.select('core/editor').isAutosavingPost();
    if (current !== initial) dirty = true;
    // Detect a real save completing (not autosave)
    if (wasSaving && !isSaving && !isAutosave && dirty) {
      // If the just-saved template is ACF-managed, reload so the editor
      // switches to the ACF view.
      if (MANAGED.indexOf(current) !== -1) {
        dirty = false;
        // Tiny delay so WP can finish post-save cleanup before reload
        setTimeout(function () { window.location.reload(); }, 250);
      } else {
        // Template still not managed — accept the new state
        initial = current;
        dirty = false;
      }
    }
    wasSaving = isSaving;
  });
})();
JS;
    wp_add_inline_script( 'wp-edit-post', $js );
} );

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
        'page-education.php',
        // Hero-ACF templates (use tlt_register_hero_acf_group below)
        'page-visit.php',
        'page-off-the-shelf.php',
        'page-auditions.php',
        'page-season-tickets.php',
        'page-ticketinfo.php',
        'page-donation-request.php',
        'page-press.php',
        'page-job-openings.php',
    ];
}

/**
 * Register a lightweight "page hero" ACF group for any template that has a
 * standard hero pattern (eyebrow pill + title + lede). Used for templates
 * where Chris's most likely edit is the hero copy — keeps the field count
 * low while letting him change the headline without code.
 *
 * @param string $template Template filename, e.g. 'page-visit.php'
 * @param string $title    Field group title shown in admin
 * @param array  $defaults [ 'eyebrow', 'title', 'lede' ] — current hardcoded values
 */
function tlt_register_hero_acf_group( $template, $title, $defaults ) {
    $key = 'group_hero_' . str_replace( [ '-', '.' ], '_', $template );
    acf_add_local_field_group( [
        'key'    => $key,
        'title'  => $title,
        'fields' => [
            [
                'key'           => $key . '_eyebrow',
                'label'         => 'Eyebrow pill',
                'name'          => 'hero_eyebrow',
                'type'          => 'text',
                'instructions'  => 'Small red pill text at the top of the hero (e.g. "Auditions", "Ticket Information"). Leave blank to hide.',
                'default_value' => $defaults['eyebrow'] ?? '',
            ],
            [
                'key'           => $key . '_title',
                'label'         => 'Page title',
                'name'          => 'hero_title',
                'type'          => 'text',
                'instructions'  => 'The big headline at the top of the page.',
                'default_value' => $defaults['title'] ?? '',
            ],
            [
                'key'           => $key . '_lede',
                'label'         => 'Intro paragraph',
                'name'          => 'hero_lede',
                'type'          => 'textarea',
                'rows'          => 3,
                'instructions'  => 'One-paragraph intro under the title.',
                'default_value' => $defaults['lede'] ?? '',
            ],
        ],
        'location' => [
            [ [ 'param' => 'page_template', 'operator' => '==', 'value' => $template ] ],
        ],
        'menu_order'             => 0,
        'position'               => 'normal',
        'label_placement'        => 'top',
        'instruction_placement'  => 'label',
        'active'                 => true,
    ] );
}

/**
 * Get a hero field by name, falling back to a default.
 * Use inside hero-ACF templates: tlt_hero_field( 'title', 'Default Title' ).
 */
function tlt_hero_field( $name, $default = '' ) {
    if ( function_exists( 'get_field' ) ) {
        $v = get_field( 'hero_' . $name );
        if ( $v !== null && $v !== '' && $v !== false ) return $v;
    }
    return $default;
}

/**
 * Bulk-register hero ACF groups for every "light" template that uses one.
 * Defaults match the current hardcoded values so the front-end renders
 * identically on a brand-new install.
 */
add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    $templates = [
        'page-visit.php' => [
            'title'    => 'Visit Page — Hero',
            'defaults' => [
                'eyebrow' => '',
                'title'   => 'Visit Tacoma Little Theatre',
                'lede'    => "Five minutes from downtown Tacoma, tucked into the historic Stadium District — here's everything you need to plan a great night at the theatre.",
            ],
        ],
        'page-off-the-shelf.php' => [
            'title'    => 'Off the Shelf — Hero',
            'defaults' => [
                'eyebrow' => 'Staged Readings',
                'title'   => 'Off the Shelf',
                'lede'    => 'Each season TLT presents its "Off the Shelf" series. There is a tremendous amount of wonderful theatre that deserves to be heard but sometimes just doesn\'t get the opportunity. With "Off the Shelf," local directors and actors bring some of these scripts to life — entertaining, challenging, educational. Sit back and enjoy an evening of theatre. You never know, you might see one of these on our Mainstage in the future.',
            ],
        ],
        'page-auditions.php' => [
            'title'    => 'Auditions — Hero',
            'defaults' => [
                'eyebrow' => 'Get on Stage',
                'title'   => 'Auditions',
                'lede'    => "Audition opportunities at Tacoma Little Theatre. We're a community theatre — no experience required to audition, and we cast a wide range of roles each season.",
            ],
        ],
        'page-season-tickets.php' => [
            'title'    => 'Season Tickets — Hero',
            'defaults' => [
                'eyebrow' => '2026–2027 Season',
                'title'   => 'Season Tickets & FLEX Passes',
                'lede'    => 'Seven Mainstage productions. One subscription. Save per show over single ticket prices, lock in your seat for every show, or grab a FLEX Pass and use the six punches however you like.',
            ],
        ],
        'page-ticketinfo.php' => [
            'title'    => 'Ticket Information — Hero',
            'defaults' => [
                'eyebrow' => 'Ticket Information',
                'title'   => 'Ticket Information',
                'lede'    => 'Everything you need to know about ticket prices, season passes, and the few house rules we have in place to keep everyone comfortable.',
            ],
        ],
        'page-donation-request.php' => [
            'title'    => 'Donation Request — Hero',
            'defaults' => [
                'eyebrow' => 'Auction Donations',
                'title'   => 'Request an Auction Donation',
                'lede'    => 'Tacoma Little Theatre is always happy to give back to our wonderful and supportive community. Thank you for thinking of TLT as a way to support your organization.',
            ],
        ],
        'page-press.php' => [
            'title'    => 'Press — Hero',
            'defaults' => [
                'eyebrow' => 'Press',
                'title'   => 'Press',
                'lede'    => 'Press releases, recognitions, and news about Tacoma Little Theatre. For media inquiries, reach out to our box office.',
            ],
        ],
        'page-job-openings.php' => [
            'title'    => 'Job Openings — Hero',
            'defaults' => [
                'eyebrow' => 'Work With Us',
                'title'   => 'Job Openings',
                'lede'    => "Tacoma Little Theatre is always looking for talented people to join our team. Check below for current openings, and feel free to send a resume to jobs@tacomalittletheatre.com any time — even if nothing's posted, we keep applications on file.",
            ],
        ],
    ];

    foreach ( $templates as $tpl => $cfg ) {
        tlt_register_hero_acf_group( $tpl, $cfg['title'], $cfg['defaults'] );
    }
} );

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

/* ---------------------------------------------------------------------------
 * Field group: Education (/education/)
 *
 * Bound to page-education.php. Tabbed into sections so the editor stays
 * navigable: Hero / Why / Programs / Scholarships / Policies.
 * "Currently Happening" is NOT a field — that section is driven by Promotions
 * with location=education and auto-shows/hides.
 * ------------------------------------------------------------------------- */

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'    => 'group_tlt_education',
        'title'  => 'Education Page Content',
        'fields' => [

            /* ===== Hero ===== */
            [
                'key'   => 'field_edu_tab_hero',
                'label' => 'Hero',
                'type'  => 'tab',
            ],
            [
                'key'           => 'field_edu_hero_title',
                'label'         => 'Page title',
                'name'          => 'edu_hero_title',
                'type'          => 'text',
                'instructions'  => 'The big headline at the top of the page.',
                'default_value' => 'Education at Tacoma Little Theatre',
            ],
            [
                'key'           => 'field_edu_hero_intro',
                'label'         => 'Intro paragraph',
                'name'          => 'edu_hero_intro',
                'type'          => 'textarea',
                'rows'          => 4,
                'instructions'  => 'The first paragraph under the title, describing what the program is about.',
                'default_value' => "TLT's Theatre classes help students of all ages to grow to their full potential as performers and more importantly as people. TLT's vision is to bring together students in our community to learn about and practice the skills and techniques of performance art, building life skills in the process.",
            ],
            [
                'key'           => 'field_edu_hero_cta_label',
                'label'         => 'Hero button label',
                'name'          => 'edu_hero_cta_label',
                'type'          => 'text',
                'wrapper'       => [ 'width' => '40' ],
                'instructions'  => 'Text shown on the big button under the intro.',
                'default_value' => 'Camp & Class Registration',
            ],
            [
                'key'           => 'field_edu_hero_cta_url',
                'label'         => 'Hero button URL',
                'name'          => 'edu_hero_cta_url',
                'type'          => 'text',
                'wrapper'       => [ 'width' => '60' ],
                'instructions'  => 'Where the button links to. Full URL (https://…) or an on-site path like /clubtlt/.',
                'default_value' => 'https://tlt.ludus.com/index.php?sections=classes',
            ],

            /* ===== Why Theatre Education ===== */
            [
                'key'   => 'field_edu_tab_why',
                'label' => 'Why Theatre Education',
                'type'  => 'tab',
            ],
            [
                'key'           => 'field_edu_why_heading',
                'label'         => 'Section heading',
                'name'          => 'edu_why_heading',
                'type'          => 'text',
                'instructions'  => 'Heading for the "philosophy" section that sits right under the hero.',
                'default_value' => 'Why Theatre Education?',
            ],
            [
                'key'           => 'field_edu_why_body',
                'label'         => 'Philosophy body',
                'name'          => 'edu_why_body',
                'type'          => 'wysiwyg',
                'tabs'          => 'visual,text',
                'toolbar'       => 'basic',
                'media_upload'  => 0,
                'instructions'  => 'Multiple paragraphs describing the philosophy and approach of TLT\'s education programs.',
                'default_value' => "<p>While TLT prides itself in educating students with extensive knowledge and powerful skills they need as performers, our courses and camps are also created to build confidence, team work, collaboration, self esteem, communication, innovative thinking and much, much more!</p>\n<p>Our classes are designed to enhance curriculums of study for both students attending public or private schools and those who are homeschooled, by providing opportunities for art to be part of the daily lives of our students.</p>\n<p>In addition to skill building courses, TLT also offers exciting avenues for performance through our drama camps and stage productions.</p>\n<p>Our instructors are trained theatre artists and bring a variety of experiences within the industry of theatre. Additionally, all instructors provide thorough curriculums for outstanding learning potential and must pass an extensive background check required by TLT.</p>\n<p>TLT is excited to further our mission of enriching our community with quality, live theater experiences. Come join the fun!</p>",
            ],

            /* ===== Programs (repeater) ===== */
            [
                'key'   => 'field_edu_tab_programs',
                'label' => 'Programs',
                'type'  => 'tab',
            ],
            [
                'key'           => 'field_edu_programs_heading',
                'label'         => 'Section heading',
                'name'          => 'edu_programs_heading',
                'type'          => 'text',
                'default_value' => 'Our Programs',
            ],
            [
                'key'           => 'field_edu_programs_lede',
                'label'         => 'Section subhead',
                'name'          => 'edu_programs_lede',
                'type'          => 'text',
                'instructions'  => 'Smaller gray line under the heading.',
                'default_value' => 'A full menu of theatre education for every age and interest.',
            ],
            [
                'key'           => 'field_edu_programs',
                'label'         => 'Program list',
                'name'          => 'edu_programs',
                'type'          => 'repeater',
                'instructions'  => 'Add, remove, or reorder the program entries shown in this section.',
                'min'           => 0,
                'layout'        => 'block',
                'button_label'  => 'Add program',
                'sub_fields'    => [
                    [
                        'key'           => 'field_edu_program_title',
                        'label'         => 'Program name',
                        'name'          => 'title',
                        'type'          => 'text',
                        'wrapper'       => [ 'width' => '70' ],
                    ],
                    [
                        'key'           => 'field_edu_program_link',
                        'label'         => 'Link to (optional)',
                        'name'          => 'link_url',
                        'type'          => 'text',
                        'wrapper'       => [ 'width' => '30' ],
                        'instructions'  => 'Optional. If set, the program name becomes a link. e.g. /clubtlt/',
                    ],
                    [
                        'key'           => 'field_edu_program_body',
                        'label'         => 'Description',
                        'name'          => 'body',
                        'type'          => 'wysiwyg',
                        'tabs'          => 'visual,text',
                        'toolbar'       => 'basic',
                        'media_upload'  => 0,
                    ],
                ],
            ],

            /* ===== Scholarships ===== */
            [
                'key'   => 'field_edu_tab_scholarships',
                'label' => 'Scholarships',
                'type'  => 'tab',
            ],
            [
                'key'           => 'field_edu_scholarship_heading',
                'label'         => 'Section heading',
                'name'          => 'edu_scholarship_heading',
                'type'          => 'text',
                'default_value' => 'Scholarships',
            ],
            [
                'key'           => 'field_edu_scholarship_body',
                'label'         => 'Scholarship description',
                'name'          => 'edu_scholarship_body',
                'type'          => 'wysiwyg',
                'tabs'          => 'visual,text',
                'toolbar'       => 'basic',
                'media_upload'  => 0,
            ],
            [
                'key'           => 'field_edu_scholarship_cta_label',
                'label'         => 'Button label',
                'name'          => 'edu_scholarship_cta_label',
                'type'          => 'text',
                'wrapper'       => [ 'width' => '40' ],
                'default_value' => 'Scholarship Application',
            ],
            [
                'key'           => 'field_edu_scholarship_cta_url',
                'label'         => 'Button URL',
                'name'          => 'edu_scholarship_cta_url',
                'type'          => 'text',
                'wrapper'       => [ 'width' => '60' ],
                'instructions'  => 'Full URL to the Google Form or other application page.',
            ],
            [
                'key'           => 'field_edu_scholarship_image',
                'label'         => 'Side image',
                'name'          => 'edu_scholarship_image',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'medium',
                'instructions'  => 'Image shown next to the scholarship section. Recommended ~600×400 or larger.',
            ],

            /* ===== Policies (repeater) ===== */
            [
                'key'   => 'field_edu_tab_policies',
                'label' => 'Policies',
                'type'  => 'tab',
            ],
            [
                'key'           => 'field_edu_policies_heading',
                'label'         => 'Section heading',
                'name'          => 'edu_policies_heading',
                'type'          => 'text',
                'default_value' => 'Registration & Program Policies',
            ],
            [
                'key'           => 'field_edu_policies_lede',
                'label'         => 'Section subhead',
                'name'          => 'edu_policies_lede',
                'type'          => 'text',
                'default_value' => 'A quick look at what to expect when enrolling.',
            ],
            [
                'key'           => 'field_edu_policies',
                'label'         => 'Policy list',
                'name'          => 'edu_policies',
                'type'          => 'repeater',
                'instructions'  => 'Add, remove, or reorder the policy entries shown in this section.',
                'min'           => 0,
                'layout'        => 'block',
                'button_label'  => 'Add policy',
                'sub_fields'    => [
                    [
                        'key'   => 'field_edu_policy_title',
                        'label' => 'Policy title',
                        'name'  => 'title',
                        'type'  => 'text',
                    ],
                    [
                        'key'   => 'field_edu_policy_body',
                        'label' => 'Policy body',
                        'name'  => 'body',
                        'type'  => 'wysiwyg',
                        'tabs'  => 'visual,text',
                        'toolbar' => 'basic',
                        'media_upload' => 0,
                    ],
                ],
            ],
        ],
        'location' => [
            [
                [ 'param' => 'page_template', 'operator' => '==', 'value' => 'page-education.php' ],
            ],
        ],
        'menu_order'             => 0,
        'position'               => 'normal',
        'style'                  => 'default',
        'label_placement'        => 'top',
        'instruction_placement'  => 'label',
        'hide_on_screen'         => [ 'the_content', 'excerpt', 'discussion', 'comments', 'slug', 'author', 'format', 'page_attributes', 'featured_image', 'tags', 'send-trackbacks' ],
        'active'                 => true,
    ] );
} );

/**
 * Defaults for Education fields — used by the template when a field isn't set.
 * Centralizes the same defaults that exist in the ACF field group above so we
 * don't drift, and so the template renders identically on a brand-new page.
 */
function tlt_edu_defaults() {
    return [
        'hero_title'             => 'Education at Tacoma Little Theatre',
        'hero_intro'             => "TLT's Theatre classes help students of all ages to grow to their full potential as performers and more importantly as people. TLT's vision is to bring together students in our community to learn about and practice the skills and techniques of performance art, building life skills in the process.",
        'hero_cta_label'         => 'Camp & Class Registration',
        'hero_cta_url'           => 'https://tlt.ludus.com/index.php?sections=classes',
        'why_heading'            => 'Why Theatre Education?',
        'why_body'               => "<p>While TLT prides itself in educating students with extensive knowledge and powerful skills they need as performers, our courses and camps are also created to build confidence, team work, collaboration, self esteem, communication, innovative thinking and much, much more!</p>\n<p>Our classes are designed to enhance curriculums of study for both students attending public or private schools and those who are homeschooled, by providing opportunities for art to be part of the daily lives of our students.</p>\n<p>In addition to skill building courses, TLT also offers exciting avenues for performance through our drama camps and stage productions.</p>\n<p>Our instructors are trained theatre artists and bring a variety of experiences within the industry of theatre. Additionally, all instructors provide thorough curriculums for outstanding learning potential and must pass an extensive background check required by TLT.</p>\n<p>TLT is excited to further our mission of enriching our community with quality, live theater experiences. Come join the fun!</p>",
        'programs_heading'       => 'Our Programs',
        'programs_lede'          => 'A full menu of theatre education for every age and interest.',
        'programs'               => [
            [ 'title' => 'After-School @ TLT', 'link_url' => '', 'body' => "<p>These wonderful six-week sessions are held twice weekly (Mondays & Wednesdays or Tuesdays & Thursdays) from 4:00pm-6:00pm. We'll offer classes in the fall, winter, and spring, and each class will culminate in a fully staged play or musical production for friends and family to come enjoy. Students can enroll in one or both sessions. Classes are open to students in grades 1-8.</p>" ],
            [ 'title' => 'Homeschool @ TLT', 'link_url' => '', 'body' => "<p>Modeled on our after school program, this class is designed for the homeschool families in our community. These classes meet twice weekly and take place over a six-week period, culminating in a fully staged production for friends and family to come and enjoy. Tacoma Little Theatre is a certified Community Based Instructor (CBI). Classes are open to students in grades 1-8.</p>" ],
            [ 'title' => 'Improv', 'link_url' => '', 'body' => "<p>Learn the skills necessary to think on your feet and say &ldquo;Yes, and…&rdquo;. These tools help young and adult actors with their onstage skills, as well as off stage in school and work. Classes vary from evening to weekend times; please be sure to check our website for the latest details. Classes are available for 12-18-year-olds and for adults.</p>" ],
            [ 'title' => 'Voice Lessons', 'link_url' => '', 'body' => '<p>TLT is not currently offering voice lessons. If you are interested, feel free to reach out to us and we may be able to put you in contact with a teaching artist in our community. You can email us at <a href="mailto:education@tacomalittletheatre.com">education@tacomalittletheatre.com</a>.</p>' ],
            [ 'title' => 'Dance', 'link_url' => '', 'body' => '<p>Getting ready for an audition or just wanting to build some skills? Come join us for these fast-paced classes. Classes are offered at a variety of levels and skill sets ranging from the basics of ballet, jazz and musical theater, to more intensive and specific styles of musical theater. Students will spend six weeks focused on a specific style or skill set. Classes are available for all ages.</p>' ],
            [ 'title' => 'Adult Classes @ TLT', 'link_url' => '', 'body' => '<p>TLT\'s education program includes our adult actors. These programs start for students 18 and up, they include classes like intro to acting, advanced acting, improv classes, and dance classes. We offer classes and workshops periodically. Check in to see what\'s available online or email us at <a href="mailto:education@tacomalittletheatre.com">education@tacomalittletheatre.com</a>.</p>' ],
            [ 'title' => 'Club TLT', 'link_url' => '/clubtlt/', 'body' => '<p>A unique club that offers year-round education dedicated to middle and high school students, ages 13-19. Students will have opportunities to focus on their audition skills to help prepare for school and community auditions they are working on! Students will also have opportunities to learn more about writing and directing their first play. Students can work their improv skills to help build confidence to mold into a variety of characters for their theatrical endeavors. Workshops and master classes in performance, direction, design, stage management, and play writing have all been offered in the past. Special events and activities offer students opportunities to attend performances and volunteer at TLT. Students will work together to create performances specially suited for teenagers.</p>' ],
            [ 'title' => 'Winter Break Camp', 'link_url' => '', 'body' => '<p>Join us during winter break for a fun and exciting camp experience! Dates change year to year to avoid Holidays. This is a great chance for students to spend some time onstage preparing an exciting musical before jumping back into the school year! Camp is most Mondays-Thursdays 9:00am-4:00pm, with one to two performances the last weekend of camp. Open for grades 1-12.</p>' ],
            [ 'title' => 'Spring Break Camp', 'link_url' => '', 'body' => '<p>Join TLT for this lightning fast theater experience! Students will work hard to put together a fully staged musical in just one week! If you plan to stay home for spring break, come join us for this wonderful program! Monday-Friday 9:00am-4:00pm, performs on the weekend. In 2023, we will offer a skills break camp – featuring all of the skill building, learning, and fun of a theater workshop without the pressure of putting on a play. This immersive experience would be appropriate for students who are interested in exploring theater arts, or deepening their skills on stage. Open for grades 1-12.</p>' ],
            [ 'title' => 'Summer Break Camps', 'link_url' => '', 'body' => '<p>We have two summer break camps each year. Camps are four weeks long, and provide an in-depth experience of putting a fully staged musical together, learning about the technical aspects of a production, and learning new theater techniques. Camp meets for four weeks, Monday-Friday 9:00am-4:00pm.</p>' ],
            [ 'title' => 'Students On Stage', 'link_url' => '/students-on-stage/', 'body' => '<p>Our outreach program is designed to bring the entire educational experience to your school! Our programs range from a variety of musical and non-musical options, all designed to bring the importance and value of art into students\' learning. Please contact us for more details by emailing <a href="mailto:education@tacomalittletheatre.com">education@tacomalittletheatre.com</a>.</p>' ],
        ],
        'scholarship_heading'    => 'Scholarships',
        'scholarship_body'       => '<p>The following button will direct you to our online scholarship application. This application is in draft form, and is being used for beta testing. If you choose to submit an application with us and do not hear back in just a couple of days, please send us an email at <a href="mailto:education@tacomalittletheatre.com">education@tacomalittletheatre.com</a>, to make sure your application has been received and is being processed. We may reach out for additional information as well. Thank you for your patience, as we seek to make the application process easy and accessible!</p>',
        'scholarship_cta_label'  => 'Scholarship Application',
        'scholarship_cta_url'    => 'https://docs.google.com/forms/d/e/1FAIpQLSdEwJCTMI4GGxAXoBhfZhi1GrNk0DP5pFTkFFJd1qKc8TciDA/viewform?usp=header',
        'policies_heading'       => 'Registration & Program Policies',
        'policies_lede'          => 'A quick look at what to expect when enrolling.',
        'policies'               => [
            [ 'title' => 'Registration', 'body' => '<p>All registrations are processed in the order they are received. Only payment in full or a payment of the $50 registration fee will secure your spot. Registrations will be accepted until the class is full or until the end of the first week of class, whichever comes first. Once your registration is processed, you will receive confirmation and further class details via e-mail.</p>' ],
            [ 'title' => 'Payment', 'body' => '<p>When you register for classes or camps online, you will be prompted to pay the full tuition amount at that time. If you would prefer to set up a payment plan, we can arrange that! Just contact us at <a href="mailto:education@tacomalittletheatre.com">education@tacomalittletheatre.com</a>. The minimum, nonrefundable fee for enrollment is $50. Some scholarship funding is available! Click the button above to apply now. A $35.00 service charge will be attached to any check returned by the bank due to insufficient funds.</p>' ],
            [ 'title' => 'Cancellations', 'body' => '<p>If you cancel or withdraw from the class more than 14 days prior to the class start, TLT can refund tuition minus a $50.00 cancellation fee. If you cancel within 14 days of the class/camp start date TLT can refund up to half of the tuition. No refunds will be given after the first class or day of camp. In camps or classes where casting is done, no refunds will be offered after casting is complete. We reserve the right to cancel a class if enrollment is insufficient. In this instance, any tuition paid will be refunded in full.</p>' ],
            [ 'title' => 'Performances', 'body' => '<p>Please check all performance dates and times for conflicts before enrolling. Typically, actors are called to the theater one to one and a half hours before the performance. Details will be provided for the individual camp or class.</p>' ],
            [ 'title' => 'Attendance', 'body' => '<p>Please check all rehearsal and class dates/times for conflicts before enrolling. Theater is a team based activity, and absences can impact the whole group. We are often able to work around absences with enough advanced notice.</p>' ],
            [ 'title' => 'Participation', 'body' => '<p>When students join our program, they will be expected to participate in a safe manner, demonstrating respect for others and for property. If a student violates any rules or creates an unsafe situation for staff or other students, we reserve the right to remove the student from the class. Tacoma Little Theatre is not responsible for any lost, damaged, or stolen personal belongings. All dates, times and programming are subject to change.</p>' ],
        ],
    ];
}

/**
 * Tiny helper: get an Education field, fall back to default.
 */
function tlt_edu_field( $name ) {
    static $defs = null;
    if ( $defs === null ) $defs = tlt_edu_defaults();
    if ( function_exists( 'get_field' ) ) {
        $v = get_field( 'edu_' . $name );
        if ( $v !== null && $v !== '' && $v !== false ) return $v;
    }
    return $defs[ $name ] ?? '';
}
