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

/**
 * Templates whose body content lives in post_content (classic editor stays
 * visible) but still need ACF fields alongside — so they still need the
 * classic editor loaded, and the auto-reload should fire for them too.
 */
function tlt_acf_body_driven_templates() {
    return [ 'page-job-posting.php', 'page-press-post.php', 'page-clubtlt.php' ];
}

/**
 * Union of every template that needs the classic editor (managed OR
 * body-driven). Used by the auto-reload guard.
 */
function tlt_acf_classic_editor_templates() {
    return array_merge( tlt_acf_managed_templates(), tlt_acf_body_driven_templates() );
}

add_filter( 'use_block_editor_for_post', function ( $use, $post ) {
    if ( ! $post || $post->post_type !== 'page' ) return $use;
    $tpl = function_exists( 'tlt_effective_page_template' ) ? tlt_effective_page_template( $post->ID ) : get_page_template_slug( $post->ID );
    if ( in_array( $tpl, tlt_acf_classic_editor_templates(), true ) ) return false;
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
    $managed = wp_json_encode( tlt_acf_classic_editor_templates() );
    $js = <<<JS
(function () {
  if (typeof wp === 'undefined' || !wp.data || !wp.data.select('core/editor')) return;
  var MANAGED  = {$managed};
  var sel      = wp.data.select('core/editor');
  var reloaded = false;
  var startedOnNew = /post-new\.php/.test(location.pathname);

  function tpl() {
    return sel.getEditedPostAttribute('template')
        || sel.getCurrentPostAttribute('template')
        || '';
  }
  function doReload() {
    if (reloaded) return;
    reloaded = true;
    setTimeout(function () { window.location.reload(); }, 200);
  }

  // Path A — brand-new page. On first save, Gutenberg pushState-swaps the
  // URL from post-new.php → post.php. That swap is our signal.
  if (startedOnNew) {
    var pathPoll = setInterval(function () {
      if (reloaded) { clearInterval(pathPoll); return; }
      if (!/post-new\.php/.test(location.pathname)) {
        clearInterval(pathPoll);
        if (MANAGED.indexOf(tpl()) !== -1) doReload();
      }
    }, 120);
  }

  // Path B — existing page whose template was just changed to a managed one.
  var wasSaving  = false;
  var initialTpl = tpl();
  wp.data.subscribe(function () {
    if (reloaded) return;
    var isSaving   = sel.isSavingPost();
    var isAutosave = sel.isAutosavingPost();
    if (wasSaving && !isSaving && !isAutosave) {
      var current = tpl();
      if (current !== initialTpl && MANAGED.indexOf(current) !== -1) doReload();
      initialTpl = current;
    }
    wasSaving = isSaving;
  });
})();
JS;
    wp_add_inline_script( 'wp-edit-post', $js );
} );

/**
 * Numbered "how to add a new page" banner shown at the top of the
 * Add New Page screen. Walks Chris through Title → Template → Save Draft
 * (Save Draft first sidesteps a first-publish race in the auto-reload above,
 * and gives him the ACF form to fill before publish anyway).
 *
 * Gutenberg suppresses classic admin_notices, so we inject via JS into the
 * editor DOM.
 */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'post-new.php' ) return;
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || $screen->post_type !== 'page' ) return;
    $html = '<div id="tlt-newpage-help" style="border-left:4px solid #b8252f;background:#fff;padding:14px 18px;margin:20px 0;box-shadow:0 1px 2px rgba(0,0,0,0.06);">'
          . '<p style="margin:0 0 8px;font-size:15px;font-weight:600;color:#1e1e1e;">How to add a new page</p>'
          . '<ol style="margin:0 0 4px 20px;padding:0;font-size:13px;line-height:1.7;color:#1e1e1e;">'
          . '<li><strong>Enter a Title</strong> above &mdash; this also becomes the page URL (e.g. <em>&ldquo;Marketing Coordinator&rdquo;</em> &rarr; <code>/marketing-coordinator/</code>).</li>'
          . '<li><strong>Pick a Template</strong> on the right sidebar under <em>Page &rarr; Template</em>. Use <em>Job Posting (Detail)</em> for a job listing, <em>Press Post</em> for a press item, etc.</li>'
          . '<li><strong>Click Save Draft</strong> (not Publish yet). The page will reload with the right fields to fill in for that template.</li>'
          . '<li>Fill in the fields, then click <strong>Publish</strong> when ready.</li>'
          . '</ol></div>';
    $html_json = wp_json_encode( $html );
    $js = <<<JS
(function () {
  function inject() {
    if (document.getElementById('tlt-newpage-help')) return true;
    // Preferred spot: right above the editor content area.
    var target = document.querySelector('.edit-post-visual-editor')
              || document.querySelector('.editor-styles-wrapper')
              || document.querySelector('.edit-post-editor-regions__content')
              || document.querySelector('.interface-interface-skeleton__content');
    if (!target) return false;
    var wrap = document.createElement('div');
    wrap.innerHTML = {$html_json};
    var node = wrap.firstChild;
    // Put it above the block canvas so it doesn't collide with the toolbar.
    target.parentNode.insertBefore(node, target);
    return true;
  }
  // Poll briefly — editor mounts asynchronously.
  var tries = 0;
  var t = setInterval(function () {
    if (inject() || ++tries > 40) clearInterval(t);
  }, 150);
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
        'page-home.php',
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
        'page-video-archive.php',
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
                'default_value'=> "Tuesday – Friday\n1:00 pm – 6:00 pm\n\nPlus 1.5 hours prior to all public performances",
                'wrapper'      => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_contact_address',
                'label'        => 'Address',
                'name'         => 'contact_address',
                'type'         => 'textarea',
                'rows'         => 4,
                'default_value'=> "Tacoma Little Theatre\n210 N \"I\" Street\nTacoma, WA 98403",
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
 * Field group: Donation Request Page
 *
 * Exposes the form (CF7 shortcode) + the two sidebar panels (Review Process,
 * Questions) so they're editable instead of hardcoded. Defaults are the real
 * current copy so the editor matches the live page. Hero fields come from the
 * separate hero group (tlt_register_hero_acf_group).
 * ------------------------------------------------------------------------- */

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'    => 'group_tlt_page_donation_request',
        'title'  => 'Donation Request Content',
        'fields' => [
            [
                'key'          => 'field_dr_form_intro',
                'label'        => 'Form intro line',
                'name'         => 'dr_form_intro',
                'type'         => 'text',
                'default_value'=> 'Fields marked with (required) must be filled in. Please allow at least four weeks before your event.',
                'instructions' => 'Small italic note shown just above the form.',
            ],
            [
                'key'          => 'field_dr_form_shortcode',
                'label'        => 'Donation form shortcode',
                'name'         => 'donation_form_shortcode',
                'type'         => 'text',
                'default_value'=> '[contact-form-7 id="1313" title="Donation Request"]',
                'instructions' => 'The auction-donation form (Contact Form 7 shortcode). Edit the form fields themselves under Forms.',
            ],
            [
                'key'          => 'field_dr_review_heading',
                'label'        => 'Sidebar: Review Process heading',
                'name'         => 'dr_review_heading',
                'type'         => 'text',
                'default_value'=> 'Review Process',
                'wrapper'      => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_dr_questions_heading',
                'label'        => 'Sidebar: Questions heading',
                'name'         => 'dr_questions_heading',
                'type'         => 'text',
                'default_value'=> 'Questions?',
                'wrapper'      => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_dr_review_body',
                'label'        => 'Sidebar: Review Process text',
                'name'         => 'dr_review_body',
                'type'         => 'wysiwyg',
                'tabs'         => 'visual,text',
                'toolbar'      => 'basic',
                'media_upload' => 0,
                'default_value'=> "<p>This request must be submitted at least <strong>four weeks</strong> prior to the day your organization needs the item.</p>\n<p>Staff reviews submissions on an ongoing basis. Due to the high volume we receive, we are unable to honor every request.</p>\n<p><strong>Good luck at your upcoming event!</strong></p>",
                'wrapper'      => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_dr_questions_body',
                'label'        => 'Sidebar: Questions text',
                'name'         => 'dr_questions_body',
                'type'         => 'wysiwyg',
                'tabs'         => 'visual,text',
                'toolbar'      => 'basic',
                'media_upload' => 0,
                'default_value'=> "<p>Contact our Box Office:</p>\n<p><a href=\"mailto:info@tacomalittletheatre.com\">info@tacomalittletheatre.com</a><br><a href=\"tel:+12532722281\">(253) 272-2281</a></p>",
                'wrapper'      => [ 'width' => '50' ],
            ],
        ],
        'location' => [
            [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'page-donation-request.php' ] ],
        ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ] );
} );

/* ---------------------------------------------------------------------------
 * Field group: Job Posting (Detail)
 *
 * Gives page-job-posting.php a real form + image picker (was hidden post-meta:
 * job_eyebrow/job_meta/job_thumb/job_apply_*). Image uses a NEW field job_image
 * (media picker) with the legacy job_thumb URL as fallback. Body stays editable.
 * ------------------------------------------------------------------------- */

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'    => 'group_tlt_job_posting',
        'title'  => 'Job Posting',
        'fields' => [
            [
                'key'          => 'field_job_eyebrow',
                'label'        => 'Eyebrow pill',
                'name'         => 'job_eyebrow',
                'type'         => 'text',
                'default_value'=> 'Now Hiring',
                'wrapper'      => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_job_meta',
                'label'        => 'Subtitle / meta line',
                'name'         => 'job_meta',
                'type'         => 'text',
                'instructions' => 'Small line under the title, e.g. "Letters reviewed on a rolling basis · Positions filled by May 31".',
                'wrapper'      => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_job_image',
                'label'        => 'Hero image',
                'name'         => 'job_image',
                'type'         => 'image',
                'return_format'=> 'url',
                'preview_size' => 'medium',
                'instructions' => 'Shown beside the title. Square-ish works best.',
            ],
            [
                'key'          => 'field_job_apply_intro',
                'label'        => 'How to Apply — intro',
                'name'         => 'job_apply_intro',
                'type'         => 'textarea',
                'rows'         => 3,
                'instructions' => 'Paragraph above the apply button. Leave blank to hide the apply box.',
                'wrapper'      => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_job_apply_url',
                'label'        => 'Apply button URL',
                'name'         => 'job_apply_url',
                'type'         => 'text',
                'placeholder'  => 'mailto:jobs@tacomalittletheatre.com?subject=…',
                'instructions' => 'mailto: or https:// link for the apply button. Spaces in the subject line are OK — they get URL-encoded automatically on save. Leave blank to hide the apply box.',
                'wrapper'      => [ 'width' => '50' ],
            ],
            [
                'key'           => 'field_job_deadline',
                'label'         => 'Application deadline',
                'name'          => 'job_deadline',
                'type'          => 'date_picker',
                'display_format'=> 'F j, Y',
                'return_format' => 'Y-m-d',
                'first_day'     => 0,
                'instructions'  => 'Optional. Shown on the posting as "Apply by [date]". After this date the page shows Expired in Pages admin so you can trash it.',
                'wrapper'       => [ 'width' => '50' ],
            ],
        ],
        'location' => [
            [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'page-job-posting.php' ] ],
        ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ] );
} );

/**
 * Auto URL-encode raw spaces in the query string of a mailto: apply link on
 * save, so editors can type "Subject: My Job Application" naturally instead
 * of hand-typing %20 for every space. Only touches the ?query part, only
 * encodes spaces (existing %20 is left alone), and only for mailto: links.
 */
add_filter( 'acf/update_value/name=job_apply_url', function ( $value ) {
    if ( ! is_string( $value ) || $value === '' ) return $value;
    if ( strpos( $value, 'mailto:' ) !== 0 ) return $value;
    $q = strpos( $value, '?' );
    if ( $q === false ) return $value;
    return substr( $value, 0, $q + 1 ) . str_replace( ' ', '%20', substr( $value, $q + 1 ) );
} );

/**
 * Deadline column + Expired badge in Pages admin so Chris can see at a glance
 * which job postings are stale and safe to trash. No cron — cleanup stays
 * manual (which at TLT's scale is fine).
 */
add_filter( 'manage_pages_columns', function ( $cols ) {
    $cols['tlt_job_deadline'] = 'Job Deadline';
    return $cols;
} );
add_action( 'manage_pages_custom_column', function ( $col, $post_id ) {
    if ( $col !== 'tlt_job_deadline' ) return;
    if ( get_page_template_slug( $post_id ) !== 'page-job-posting.php' ) {
        echo '<span style="color:#ccc">—</span>';
        return;
    }
    $deadline = get_post_meta( $post_id, 'job_deadline', true );
    if ( ! $deadline ) {
        echo '<span style="color:#888">— (no deadline)</span>';
        return;
    }
    $ts     = strtotime( $deadline );
    $today  = strtotime( current_time( 'Y-m-d' ) );
    $pretty = date_i18n( 'M j, Y', $ts );
    if ( $ts < $today ) {
        printf(
            '<span style="color:#a00000;font-weight:600">× Expired</span><br><span style="color:#888;font-size:11px">%s</span>',
            esc_html( $pretty )
        );
    } else {
        printf( '<span style="color:#1d6f1d">● %s</span>', esc_html( $pretty ) );
    }
}, 10, 2 );
add_filter( 'manage_edit-page_sortable_columns', function ( $cols ) {
    $cols['tlt_job_deadline'] = 'tlt_job_deadline';
    return $cols;
} );

/* ---------------------------------------------------------------------------
 * Field group: Press Post (Detail)
 *
 * Same shape as Job Posting — real fields + image picker for page-press-post.php
 * (was hidden post-meta press_date/press_thumb). Body stays editable.
 * ------------------------------------------------------------------------- */

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'    => 'group_tlt_press_post',
        'title'  => 'Press Release',
        'fields' => [
            [
                'key'          => 'field_press_date',
                'label'        => 'Display date',
                'name'         => 'press_date',
                'type'         => 'text',
                'placeholder'  => 'May 28, 2021',
                'instructions' => 'Shown above the title (free text so you control the format).',
                'wrapper'      => [ 'width' => '50' ],
            ],
            [
                'key'          => 'field_press_image',
                'label'        => 'Hero image',
                'name'         => 'press_image',
                'type'         => 'image',
                'return_format'=> 'url',
                'preview_size' => 'medium',
                'wrapper'      => [ 'width' => '50' ],
            ],
        ],
        'location' => [
            [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'page-press-post.php' ] ],
        ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ] );
} );

/* ---------------------------------------------------------------------------
 * Field group: Auditions Page
 *
 * The "Current Auditions" list auto-derives from each Show's audition fields
 * (schedule + Casting Manager link + cast) — NOT edited here. These fields cover
 * the evergreen boxes: the facts strip, How-to-Sign-Up steps, tips, rehearsal
 * info, and the empty state. Defaults = the real current copy; seed as usual.
 * ------------------------------------------------------------------------- */

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'    => 'group_tlt_auditions',
        'title'  => 'Auditions Page Content',
        'fields' => [
            [
                'key'     => 'field_aud_note',
                'label'   => '',
                'name'    => '',
                'type'    => 'message',
                'message' => "The **Current Auditions** list builds itself from each Show's audition fields (audition dates, Casting Manager link, cast list) — edit those on the Show, not here. A show is featured with a Sign-Up button once it has a Casting Manager link, shows \"This show has been cast\" once a cast is entered, and drops off about 3 weeks after its last audition date. The fields below are the page's evergreen text.",
            ],
            [ 'key' => 'field_aud_location', 'label' => 'Facts: Location', 'name' => 'aud_location', 'type' => 'textarea', 'rows' => 2, 'default_value' => "Tacoma Little Theatre\n210 N \"I\" Street, Tacoma WA", 'wrapper' => [ 'width' => '34' ] ],
            [ 'key' => 'field_aud_appointment', 'label' => 'Facts: By Appointment', 'name' => 'aud_appointment', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'All auditions are by appointment only. Sign-ups open one month before audition dates.', 'wrapper' => [ 'width' => '33' ] ],
            [ 'key' => 'field_aud_phone', 'label' => 'Facts: Box office phone', 'name' => 'aud_phone', 'type' => 'text', 'default_value' => '(253) 272-2281', 'wrapper' => [ 'width' => '33' ] ],
            [ 'key' => 'field_aud_steps', 'label' => 'How to Sign Up — steps', 'name' => 'aud_steps', 'type' => 'textarea', 'rows' => 10, 'new_lines' => '', 'instructions' => 'One step per "## Heading" + text line.', 'default_value' => "## Create a Casting Manager account\nAll auditions are scheduled through Casting Manager. Sign up for a free account if you don't already have one.\n\n## Pick a show and schedule a time\nClick the show's audition link above to choose an appointment that works for you. Sign-ups open about one month before audition dates.\n\n## Prepare your audition\nBring a 1–2 minute monologue (or 16 bars of a song for a musical). See tips below for what to expect.\n\n## Show up and have fun\nArrive a few minutes early. Auditions are friendly and low-pressure — we want to see what you can do." ],
            [ 'key' => 'field_aud_casting_url', 'label' => 'Casting Manager URL', 'name' => 'aud_casting_url', 'type' => 'text', 'default_value' => 'http://castingmanager.com/profile/5b7c8e3901d88' ],
            [ 'key' => 'field_aud_tips', 'label' => 'Audition Tips', 'name' => 'aud_tips', 'type' => 'textarea', 'rows' => 12, 'new_lines' => '', 'instructions' => 'One tip per "## Heading" + text line.', 'default_value' => "## Most Directors Want…\nA 1–2 minute monologue for your initial audition. For comedies, prepare a humorous monologue; for non-comedies, a serious one. For musicals, prepare a song of no less than 16 bars and bring sheet music — an accompanist will be provided.\n\n## Non-Musical Plays\nCome prepared with a monologue. A résumé and head shot are nice but not required.\n\n## Musicals\nChoose a song that enhances and shows off your singing skills — usually not a song from the play for which you are auditioning.\n\n## Callbacks\nAt the callback, actors will read from the script. The director may also ask you to do scene work with other auditioners." ],
            [ 'key' => 'field_aud_rehearsal', 'label' => 'Rehearsal Information', 'name' => 'aud_rehearsal', 'type' => 'textarea', 'rows' => 3, 'default_value' => "If cast, most rehearsals take place Monday–Thursday, 7:00 pm – 9:30 pm, plus one weekend day. Directors do their best to work around rehearsal conflicts, and you'll only be called to the rehearsals you actually need to attend." ],
            [ 'key' => 'field_aud_empty', 'label' => 'Empty state (no auditions)', 'name' => 'aud_empty_text', 'type' => 'textarea', 'rows' => 2, 'default_value' => "No auditions are currently scheduled.\nCheck back soon, or join our email list to be notified when new auditions are announced." ],
        ],
        'location' => [
            [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'page-auditions.php' ] ],
        ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ] );
} );

/* ---------------------------------------------------------------------------
 * Field group: ClubTLT slideshow
 *
 * page-clubtlt.php is auto-selected by slug (no template meta), so the location
 * targets the page itself. A textarea of image URLs (free ACF has no Gallery);
 * the template falls back to scraping the body images if it's empty. Seeded from
 * the existing body images so the editor shows them.
 * ------------------------------------------------------------------------- */

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;
    $club = function_exists( 'get_page_by_path' ) ? get_page_by_path( 'clubtlt' ) : null;
    if ( ! $club ) return;
    acf_add_local_field_group( [
        'key'    => 'group_tlt_clubtlt',
        'title'  => 'ClubTLT Slideshow',
        'fields' => [
            [
                'key'          => 'field_clubtlt_slideshow',
                'label'        => 'Slideshow images',
                'name'         => 'clubtlt_slideshow',
                'type'         => 'textarea',
                'rows'         => 10,
                'new_lines'    => '',
                'instructions' => 'One image URL per line (e.g. /wp-content/uploads/…/photo.jpg). Add, remove, or reorder lines to change the slideshow. Upload images under Media first, then copy each image\'s URL.',
            ],
        ],
        'location' => [
            [ [ 'param' => 'page', 'operator' => '==', 'value' => (string) $club->ID ] ],
        ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ] );
} );

/* ---------------------------------------------------------------------------
 * Field group: Visit Page
 *
 * Makes the high-churn content editable: address/phone, the accessibility &
 * lobby card grids, venue Quick Facts, the restaurant list (swap one out when a
 * spot closes), and the Harbor Lights feature. Repeating items use the
 * formatted-textarea pattern (free ACF, no Repeater). Defaults are the real
 * current copy. (The Transportation & Parking and Our Venue prose paragraphs
 * remain in the template for now — see task #32 follow-up.)
 * ------------------------------------------------------------------------- */

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'    => 'group_tlt_visit',
        'title'  => 'Visit Page Content',
        'fields' => [
            [ 'key' => 'field_visit_tab_top', 'label' => 'Address', 'type' => 'tab' ],
            [
                'key' => 'field_visit_address', 'label' => 'Address line', 'name' => 'visit_address',
                'type' => 'text', 'default_value' => '210 N "I" Street, Tacoma, WA 98403',
                'wrapper' => [ 'width' => '60' ],
            ],
            [
                'key' => 'field_visit_phone', 'label' => 'Box office phone', 'name' => 'visit_phone',
                'type' => 'text', 'default_value' => '(253) 272-2281', 'wrapper' => [ 'width' => '40' ],
            ],
            [ 'key' => 'field_visit_tab_access', 'label' => 'Accessibility', 'type' => 'tab' ],
            [
                'key' => 'field_visit_access_intro', 'label' => 'Accessibility intro', 'name' => 'visit_access_intro',
                'type' => 'textarea', 'rows' => 2,
                'default_value' => "We want every patron to have a comfortable, dignified experience at TLT. If you have a question or accommodation request, please call the box office at (253) 272-2281 at least 48 hours before your performance and we'll do our best to help.",
            ],
            [
                'key' => 'field_visit_access_cards', 'label' => 'Accessibility cards', 'name' => 'visit_access_cards',
                'type' => 'textarea', 'rows' => 14, 'new_lines' => '',
                'instructions' => 'One card per "## Heading", followed by its text on the next line(s).',
                'default_value' => "## Wheelchair Seating\nDesignated wheelchair-accessible seats are available in the back row of the auditorium. Companion seating is provided adjacent. Please request these seats when booking so we can hold them for you.\n\n## Hearing Assistance\nAssistive listening devices are available from the box office at no charge — just ask when you arrive. Please bring photo ID to check one out.\n\n## Restrooms\nTwo ADA-accessible restrooms are located in the lobby. There are no stairs.\n\n## Service Animals\nService animals are welcome at all TLT performances. Please let the box office know in advance if possible.\n\n## Fragrance Sensitivity\nWe ask all patrons to refrain from wearing strong fragrances out of consideration for those with chemical sensitivities.\n\n## Content Advisories\nShow-specific content advisories (language, themes, stage effects such as fog or strobe) are posted on each show's page. Call the box office if you'd like more detail before booking.",
            ],
            [ 'key' => 'field_visit_tab_venue', 'label' => 'Venue', 'type' => 'tab' ],
            [
                'key' => 'field_visit_quick_facts', 'label' => 'Quick Facts', 'name' => 'visit_quick_facts',
                'type' => 'textarea', 'rows' => 6, 'new_lines' => '',
                'instructions' => 'One fact per line.',
                'default_value' => "~200 seats, single auditorium\nFounded 1918 · building occupied since 1940s\nYear-round Mainstage and Off the Shelf seasons\nEducation programs for ages 6 through adult\n501(c)(3) nonprofit · Federal ID 91-0485763",
            ],
            [
                'key' => 'field_visit_seating_chart', 'label' => 'Seating chart URL', 'name' => 'visit_seating_chart_url',
                'type' => 'text', 'default_value' => '/wp-content/uploads/TLT-Seating-Chart.png',
            ],
            [ 'key' => 'field_visit_tab_lobby', 'label' => 'Lobby', 'type' => 'tab' ],
            [
                'key' => 'field_visit_lobby_intro', 'label' => 'Lobby intro', 'name' => 'visit_lobby_intro',
                'type' => 'textarea', 'rows' => 2,
                'default_value' => "Doors open about 30 minutes before curtain. Our concessions bar serves wine, beer, soft drinks, water, coffee, and a rotating selection of snacks and treats. All proceeds from concessions support TLT's productions and education programs.",
            ],
            [
                'key' => 'field_visit_lobby_cards', 'label' => 'Lobby cards', 'name' => 'visit_lobby_cards',
                'type' => 'textarea', 'rows' => 6, 'new_lines' => '',
                'instructions' => 'One card per "## Heading" + text line.',
                'default_value' => "## Before the Show\nArrive early to browse the lobby, peek at the cast bios, grab a drink, and settle in before the show.\n\n## At Intermission\nMost performances include one 15-minute intermission. Drinks and snacks are available again — we appreciate cash and cards equally.",
            ],
            [ 'key' => 'field_visit_tab_eat', 'label' => 'Eat & Drink', 'type' => 'tab' ],
            [
                'key' => 'field_visit_eat_intro', 'label' => 'Eat & Drink intro', 'name' => 'visit_eat_intro',
                'type' => 'textarea', 'rows' => 2,
                'default_value' => 'Make a night of it. The Stadium District is walkable, with restaurants and bars five minutes from the theatre door.',
            ],
            [
                'key' => 'field_visit_harbor_heading', 'label' => 'Harbor Lights — heading', 'name' => 'visit_harbor_heading',
                'type' => 'text', 'default_value' => 'Harbor Lights', 'wrapper' => [ 'width' => '50' ],
            ],
            [
                'key' => 'field_visit_harbor_pill', 'label' => 'Harbor Lights — pill', 'name' => 'visit_harbor_pill',
                'type' => 'text', 'default_value' => 'Featured Partner', 'wrapper' => [ 'width' => '50' ],
            ],
            [
                'key' => 'field_visit_harbor_blurb', 'label' => 'Harbor Lights — blurb', 'name' => 'visit_harbor_blurb',
                'type' => 'textarea', 'rows' => 2,
                'default_value' => "Tacoma waterfront landmark since 1959. We're proud to partner with Harbor Lights for our patrons.",
            ],
            [
                'key' => 'field_visit_harbor_perks', 'label' => 'Harbor Lights — perks', 'name' => 'visit_harbor_perks',
                'type' => 'wysiwyg', 'tabs' => 'visual,text', 'toolbar' => 'basic', 'media_upload' => 0,
                'default_value' => "<p><strong>All Patrons</strong><br>Access to the 3-Course Sunset Dinner menu any day of the week before or after a performance. Just show your ticket.</p><p><strong>Season &amp; Flex Pass Holders</strong><br>One complimentary appetizer or dessert with every performance.</p>",
            ],
            [
                'key' => 'field_visit_restaurants_intro', 'label' => 'Restaurant list intro', 'name' => 'visit_restaurants_intro',
                'type' => 'textarea', 'rows' => 2,
                'default_value' => "A few of our favorite Stadium District spots within easy walking distance. We're not affiliated with any of these — just good neighbors.",
            ],
            [
                'key' => 'field_visit_restaurants', 'label' => 'Restaurants', 'name' => 'visit_restaurants',
                'type' => 'textarea', 'rows' => 16, 'new_lines' => '',
                'instructions' => 'One per line, pipe-separated: <code>Name | URL | Distance | tier | Tags | Blurb</code>. tier = close / normal / far (controls the distance-badge colour).',
                'default_value' =>
                    "Parkway Tavern | https://www.parkwaytavern.com/ | 0.1 mi | close | Craft Beer · Pub Food | Tacoma craft-beer institution literally a block away. Big rotating tap list, gourmet burgers, and sandwiches. 313 N I St.\n" .
                    "Hank's Bar and Pizza | https://www.hankstacoma.com/ | 0.3 mi | close | Pizza · Bar | Neighborhood pizza-and-beer joint, easy walk from the theatre. Pies, salads, and a solid beer list. 524 N K St.\n" .
                    "Shake Shake Shake | https://shakeshakeshake.me/ | 0.3 mi | close | Burgers · Retro | Old-school diner-style burgers, hand-pressed and voted Best in Tacoma. Plus 25+ craft milkshakes. 124 N Tacoma Ave.\n" .
                    "Indo Asian Street Eatery | https://indostreeteatery.com/ | 0.3 mi | close | Southeast Asian | Pan-Asian street food and craft cocktails — bao, dumplings, satays, rice bowls — set in the historic Stadium District. 110 N Tacoma Ave.\n" .
                    "Sapp Sapp Thai Noodle House | https://sappsapptacoma.com/ | 0.3 mi | close | Thai · Noodles | Newer Thai noodle spot on Tacoma Ave. Boat noodles, curries, stir-fries, and cocktails. 110 N Tacoma Ave (Suite B).\n" .
                    "Salamone's Pizza | https://salamonespizzeria.com/ | 0.4 mi | close | Pizza · Italian | New York–style pizza by the slice or whole pie, plus Italian standards. Great if you're feeding a group on the way in. 24 N Tacoma Ave.\n" .
                    "Manuscript | https://manuscripttacoma.com/ | 0.5 mi | normal | Italian-Inspired · Scratch Kitchen | Italian-inspired fusion in a lively, vinyl-DJ atmosphere — in the former Hub space. Weekend brunch too. 203 Tacoma Ave S.\n" .
                    "Le Sel Bistro | https://www.leselbistro.com/ | 0.6 mi | normal | French Bistro | Classic French bistro fare in a small, intimate room. Steak frites, mussels, well-curated wine list. Reservations a good idea. 229 St Helens Ave.\n" .
                    "Doyle's Public House | https://www.doylespublichouse.com/ | 0.6 mi | normal | Irish Pub | Cozy Irish pub a few blocks south. Whiskeys, Guinness on draft, and pub food until late. 208 St Helens Ave.\n" .
                    "Zen Ramen & Sushi Burrito | https://www.zenramensushiburrito.com/ | 0.7 mi | normal | Ramen · Sushi Burritos | Ramen, sushi burritos, poke, and rice bowls. Fast and reliable for a quick pre-show meal. 322 Tacoma Ave S.\n" .
                    "Frisko Freeze | https://friskofreeze.com/ | 0.7 mi | normal | Burgers · Drive-In | Tacoma landmark since 1950. Burgers, fries, and shakes from the drive-thru or walk-up window — pure retro Americana. 1201 Division Ave.\n" .
                    "Red Star Taco Bar | https://www.redstartacobar.com/tacoma | 0.9 mi | far | Tacos · Tequila | Tacos, tequila flights, and margaritas in a lively bar setting. A bit further down St Helens — a good after-show stop. 454 St Helens Ave.",
            ],
            [
                'key' => 'field_visit_disclaimer', 'label' => 'Disclaimer', 'name' => 'visit_disclaimer',
                'type' => 'text',
                'default_value' => 'Restaurant and bar hours change. Please call ahead or check online before you head out.',
            ],
        ],
        'location' => [
            [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'page-visit.php' ] ],
        ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ] );
} );

/* ---------------------------------------------------------------------------
 * Field group: Ticket Information Page (/tickets/, page-ticketinfo.php)
 *
 * Full editability: hero buttons, single-ticket pricing, info cards, the
 * season/flex comparison, subscribe CTA, and both policy grids. Repeating items
 * use formatted textareas (free ACF). The SEASON & FLEX PRICES are read from the
 * Season Tickets page (one canonical source — no drift); only the bullets/wording
 * live here. Defaults = the real current copy; seed via the standard seeder.
 * ------------------------------------------------------------------------- */

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'    => 'group_tlt_ticketinfo',
        'title'  => 'Ticket Information Content',
        'fields' => [
            [ 'key' => 'field_ti_tab_hero', 'label' => 'Hero Buttons', 'type' => 'tab' ],
            [ 'key' => 'field_ti_buy_label', 'label' => 'Buy Tickets — label', 'name' => 'ti_buy_label', 'type' => 'text', 'default_value' => 'Buy Tickets', 'wrapper' => [ 'width' => '25' ] ],
            [ 'key' => 'field_ti_buy_url', 'label' => 'Buy Tickets — URL', 'name' => 'ti_buy_url', 'type' => 'text', 'default_value' => 'https://tlt.ludus.com', 'wrapper' => [ 'width' => '25' ] ],
            [ 'key' => 'field_ti_season_label', 'label' => 'Season Tickets — label', 'name' => 'ti_season_label', 'type' => 'text', 'default_value' => 'Season Tickets', 'wrapper' => [ 'width' => '25' ] ],
            [ 'key' => 'field_ti_season_url', 'label' => 'Season Tickets — URL', 'name' => 'ti_season_url', 'type' => 'text', 'default_value' => '/season-tickets/', 'wrapper' => [ 'width' => '25' ] ],

            [ 'key' => 'field_ti_tab_pricing', 'label' => 'Single Pricing', 'type' => 'tab' ],
            [ 'key' => 'field_ti_musical_prices', 'label' => 'Musicals — prices', 'name' => 'ti_musical_prices', 'type' => 'textarea', 'rows' => 3, 'new_lines' => '', 'instructions' => 'One per line: <code>$Price | Who</code>.', 'default_value' => "\$32.00 | Adult\n\$30.00 | Senior (60+) / Student / Military\n\$25.00 | Child (12 and under)", 'wrapper' => [ 'width' => '50' ] ],
            [ 'key' => 'field_ti_play_prices', 'label' => 'Plays — prices', 'name' => 'ti_play_prices', 'type' => 'textarea', 'rows' => 3, 'new_lines' => '', 'instructions' => 'One per line: <code>$Price | Who</code>.', 'default_value' => "\$30.00 | Adult\n\$28.00 | Senior (60+) / Student / Military\n\$23.00 | Child (12 and under)", 'wrapper' => [ 'width' => '50' ] ],
            [ 'key' => 'field_ti_musical_group', 'label' => 'Musicals — group rates', 'name' => 'ti_musical_group', 'type' => 'text', 'default_value' => '10–24 tickets: $26.00 · 25+ tickets: $25.00', 'wrapper' => [ 'width' => '50' ] ],
            [ 'key' => 'field_ti_play_group', 'label' => 'Plays — group rates', 'name' => 'ti_play_group', 'type' => 'text', 'default_value' => '10–24 tickets: $24.00 · 25+ tickets: $23.00', 'wrapper' => [ 'width' => '50' ] ],
            [ 'key' => 'field_ti_group_note', 'label' => 'Group rates note', 'name' => 'ti_group_note', 'type' => 'text', 'default_value' => 'Group rates available through the Box Office only.' ],
            [ 'key' => 'field_ti_info_cards', 'label' => 'Info cards', 'name' => 'ti_info_cards', 'type' => 'textarea', 'rows' => 8, 'new_lines' => '', 'instructions' => 'One card per "## Heading" + text line(s).', 'default_value' => "## Pay What You Can\nPWYC performances are typically held on the third Thursday of a show's run. Suggested minimum donation is \$5.00. Available in person, over the phone, or online.\n\n## Card Transaction Fees\nCredit/debit card orders carry a 5% convenience fee + \$0.85 per ticket/pass. No transaction fees for cash or check.\n\n## Gift Cards\nAvailable in any amount online, by phone, or in person. Redeemable for tickets, Season Tickets, FLEX Passes, and class enrollments. Not currently usable on concessions." ],

            [ 'key' => 'field_ti_tab_season', 'label' => 'Season & FLEX', 'type' => 'tab' ],
            [ 'key' => 'field_ti_season_intro', 'label' => 'Section intro', 'name' => 'ti_season_intro', 'type' => 'textarea', 'rows' => 2, 'default_value' => 'Season tickets offer the same seat and date of your choice for all seven Main Stage shows. Flex passes are six admissions that can be used on any Main Stage show with advance reservations. Both options save you money over single tickets.' ],
            [ 'key' => 'field_ti_season_summary', 'label' => 'Season Ticket — summary', 'name' => 'ti_season_summary', 'type' => 'text', 'default_value' => 'One seat to all seven regular Main Stage productions, same date and seat every show.' ],
            [ 'key' => 'field_ti_season_bullets', 'label' => 'Season Ticket — bullets', 'name' => 'ti_season_bullets', 'type' => 'textarea', 'rows' => 5, 'new_lines' => '', 'default_value' => "Guaranteed same seat for every show in your package\nSave per show over the single-ticket price\nFree exchanges with at least 24 hours notice\nValid only for the season purchased\nDoes not include Special Events" ],
            [ 'key' => 'field_ti_flex_summary', 'label' => 'FLEX Pass — summary', 'name' => 'ti_flex_summary', 'type' => 'text', 'default_value' => 'Six prepaid admissions you can use any way you want — bring a friend, double up, save for later.' ],
            [ 'key' => 'field_ti_flex_bullets', 'label' => 'FLEX Pass — bullets', 'name' => 'ti_flex_bullets', 'type' => 'textarea', 'rows' => 6, 'new_lines' => '', 'default_value' => "Save per show over the single-ticket price\nUse punches in any combination (bring a friend!)\nReserve at least 24 hours before the performance\nMany shows sell out — reserve 2+ weeks ahead when possible\nValid for Main Stage and Second Stage productions only\nNot valid for Special Events · 6 punches but 7 shows in a season" ],
            [ 'key' => 'field_ti_subscribe_heading', 'label' => 'Subscribe — heading', 'name' => 'ti_subscribe_heading', 'type' => 'text', 'default_value' => 'Ready to Subscribe?', 'wrapper' => [ 'width' => '50' ] ],
            [ 'key' => 'field_ti_subscribe_btn_label', 'label' => 'Subscribe — button label', 'name' => 'ti_subscribe_btn_label', 'type' => 'text', 'default_value' => 'Subscribe Online', 'wrapper' => [ 'width' => '25' ] ],
            [ 'key' => 'field_ti_subscribe_btn_url', 'label' => 'Subscribe — button URL', 'name' => 'ti_subscribe_btn_url', 'type' => 'text', 'default_value' => '/season-tickets/', 'wrapper' => [ 'width' => '25' ] ],
            [ 'key' => 'field_ti_subscribe_intro', 'label' => 'Subscribe — intro', 'name' => 'ti_subscribe_intro', 'type' => 'text', 'default_value' => 'Subscribe online, give us a call, or mail an order form to:' ],
            [ 'key' => 'field_ti_mail_address', 'label' => 'Subscribe — mailing address', 'name' => 'ti_mail_address', 'type' => 'textarea', 'rows' => 3, 'default_value' => "Tacoma Little Theatre\n210 N \"I\" Street\nTacoma, WA 98403" ],

            [ 'key' => 'field_ti_tab_policies', 'label' => 'Policies', 'type' => 'tab' ],
            [ 'key' => 'field_ti_general_policies', 'label' => 'General Policies', 'name' => 'ti_general_policies', 'type' => 'textarea', 'rows' => 20, 'new_lines' => '', 'instructions' => 'One card per "## Heading" + text. For a bulleted policy, put the whole &lt;ul&gt;…&lt;/ul&gt; on one line.', 'default_value' =>
                "## Lost Tickets\nCall the Box Office and we can reprint them for you. Reprints will be held at WILL CALL under your last name on the date of the performance.\n\n" .
                "## Ticket Sales\n<ul><li>All sales final — no refunds, but we offer exchanges</li><li>Murder Mystery Dinners must be exchanged at least 5 days in advance</li><li>Online orders require a credit card and receive email confirmation</li><li>Added donations are charged together with your order</li></ul>\n\n" .
                "## Babes in Arms\nFor the comfort of other patrons, no babes in arms during our productions.\n\n" .
                "## Accessible Seating\nWheelchair-accessible seating is available for every performance. Call the Box Office to arrange seating and confirm availability.\n\n" .
                "## Cameras & Devices\nFor the safety and comfort of the actors and audience, no cameras or recording devices. Please silence phones, watches, and any noise-making electronics before the show starts.\n\n" .
                "## Late Seating\nIf you arrive after the show has started, you'll be seated at the back at the House Manager's discretion until intermission. Unclaimed seats may be released 15 minutes after curtain.\n\n" .
                "## Concessions\nBeer, wine, cocktails, soft drinks, coffee, tea, and snacks are available before the show and at intermission.\n\n" .
                "## Coat Check\nA self-serve coat check is located inside the auditorium. TLT is not responsible for lost or stolen articles.\n\n" .
                "## Weather\nAll performances take place as scheduled, regardless of weather. Performances may only be cancelled in the event of a complete power outage.\n\n" .
                "## Right to Refuse Service\nTacoma Little Theatre reserves the right to refuse service." ],
            [ 'key' => 'field_ti_subscriber_policies', 'label' => 'Season Ticket & FLEX Pass Policies', 'name' => 'ti_subscriber_policies', 'type' => 'textarea', 'rows' => 10, 'new_lines' => '', 'default_value' =>
                "## Season Ticket Exchanges\nIf necessary, you can exchange your Season Ticket by calling the Box Office at <a href=\"tel:+12532722281\">(253) 272-2281</a> at least 24 hours in advance.\nSeason Tickets are only valid for the season for which they are purchased.\n\n" .
                "## FLEX Pass Reservations\nReserve seats by calling the Box Office at least 24 hours before the performance.\nOnce reservations are made, there are no refunds — but we offer free exchanges.\n\n" .
                "## FLEX Pass Use\nFLEX Passes are valid for Main Stage and Second Stage productions. They are not valid for Special Events.\nEach pass has 6 punches; use them however you prefer (including bringing guests). FLEX passes are only valid for the season in which they are purchased." ],
        ],
        'location' => [
            [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'page-ticketinfo.php' ] ],
        ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ] );
} );

/* ---------------------------------------------------------------------------
 * Field group: Season Tickets Page
 *
 * The 7 show cards auto-populate from the current season's Mainstage shows (in
 * the template). These fields cover the operational bits that change each year:
 * online-ordering toggle, PDF links, hero image, prices, and pass wording.
 * Defaults are the real current copy so the editor matches the live page.
 * ------------------------------------------------------------------------- */

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'    => 'group_tlt_season_tickets',
        'title'  => 'Season Tickets Settings',
        'fields' => [
            [
                'key'     => 'field_st_note',
                'label'   => '',
                'name'    => '',
                'type'    => 'message',
                'message' => "The 7 show cards on this page **auto-populate from the current season's Mainstage Shows** — edit those on each Show, not here. The season name and date range are derived automatically too. Use the fields below for the online-ordering toggle, PDF links, hero image, prices, and pass wording.",
                'new_lines' => 'wpautop',
            ],
            [ 'key' => 'field_st_tab_ordering', 'label' => 'Ordering', 'type' => 'tab' ],
            [
                'key'          => 'field_st_online_live',
                'label'        => 'Online ordering is open',
                'name'         => 'st_online_live',
                'type'         => 'true_false',
                'ui'           => 1,
                'message'      => 'When ON: shows the "Order Online" button and hides the "Heads up — online orders open in July" notice. Turn OFF between seasons.',
                'default_value'=> 0,
            ],
            [
                'key'          => 'field_st_online_url',
                'label'        => 'Order Online URL',
                'name'         => 'st_online_url',
                'type'         => 'text',
                'default_value'=> 'https://tlt.ludus.com',
                'wrapper'      => [ 'width' => '34' ],
            ],
            [
                'key'          => 'field_st_brochure_url',
                'label'        => 'Season Brochure PDF',
                'name'         => 'st_brochure_url',
                'type'         => 'file',
                'return_format'=> 'url',
                'library'      => 'all',
                'mime_types'   => 'pdf',
                'instructions' => 'Click to upload or pick from Media Library. Leave blank to fall back to the default.',
                'wrapper'      => [ 'width' => '33' ],
            ],
            [
                'key'          => 'field_st_order_form_url',
                'label'        => 'Mail-In Order Form PDF',
                'name'         => 'st_order_form_url',
                'type'         => 'file',
                'return_format'=> 'url',
                'library'      => 'all',
                'mime_types'   => 'pdf',
                'instructions' => 'Click to upload or pick from Media Library. Leave blank to fall back to the default.',
                'wrapper'      => [ 'width' => '33' ],
            ],
            [
                'key'          => 'field_st_hero_image',
                'label'        => 'Hero poster image',
                'name'         => 'st_hero_image',
                'type'         => 'image',
                'return_format'=> 'url',
                'preview_size' => 'medium',
                'instructions' => 'The season poster beside the headline. Leave blank to hide the poster slot entirely.',
            ],
            [ 'key' => 'field_st_tab_pass', 'label' => 'Passes & Prices', 'type' => 'tab' ],
            [
                'key'          => 'field_st_pass_intro',
                'label'        => 'Choose Your Pass — intro',
                'name'         => 'st_pass_intro',
                'type'         => 'textarea',
                'rows'         => 3,
                'default_value'=> "Both options save you money over single tickets. Season Tickets are best if you like seeing the same seat at the same time of week every show. FLEX Passes are best if your schedule changes — or if you'd rather bring a friend than commit to dates.",
            ],
            [
                'key'          => 'field_st_season_summary',
                'label'        => 'Season Ticket — summary',
                'name'         => 'st_season_summary',
                'type'         => 'text',
                'default_value'=> 'One reserved seat to all seven Mainstage productions, same date and seat every show.',
            ],
            [
                'key'          => 'field_st_season_prices',
                'label'        => 'Season Ticket — prices',
                'name'         => 'st_season_prices',
                'type'         => 'textarea',
                'rows'         => 3,
                'new_lines'    => '',
                'instructions' => 'One per line: <code>$Price | Who</code>.',
                'default_value'=> "\$171.20 | Adult\n\$160.00 | Senior / Student / Military\n\$132.00 | Child",
            ],
            [
                'key'          => 'field_st_season_bullets',
                'label'        => 'Season Ticket — bullets',
                'name'         => 'st_season_bullets',
                'type'         => 'textarea',
                'rows'         => 4,
                'new_lines'    => '',
                'instructions' => 'One bullet per line.',
                'default_value'=> "Guaranteed same seat for every show in your package\nSave per show over the single ticket price\nFree exchanges with at least 24 hours notice",
            ],
            [
                'key'          => 'field_st_flex_summary',
                'label'        => 'FLEX Pass — summary',
                'name'         => 'st_flex_summary',
                'type'         => 'text',
                'default_value'=> 'Six prepaid admissions you can use however you want — bring a friend, double up, save for later.',
            ],
            [
                'key'          => 'field_st_flex_price',
                'label'        => 'FLEX Pass — price line',
                'name'         => 'st_flex_price',
                'type'         => 'text',
                'instructions' => 'Format: <code>$Price | Who</code>.',
                'default_value'=> '$160.00 | 6 punches',
            ],
            [
                'key'          => 'field_st_flex_bullets',
                'label'        => 'FLEX Pass — bullets',
                'name'         => 'st_flex_bullets',
                'type'         => 'textarea',
                'rows'         => 5,
                'new_lines'    => '',
                'default_value'=> "Save per show over the single ticket price\nUse punches in any combination — bring guests, double up\nReserve at least 24 hours before each performance\nValid for Mainstage only · not Special Events\n6 punches across 7 shows",
            ],
        ],
        'location' => [
            [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'page-season-tickets.php' ] ],
        ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
    ] );
} );

/* ---------------------------------------------------------------------------
 * Field group: Video Archive (Recorded Programs)
 *
 * Free ACF has no Repeater, so videos are entered as a documented textarea
 * (parsed by tlt_parse_video_sections) instead of raw JSON. Seeded from the
 * existing JSON so the editor shows the real content. Template falls back to
 * the legacy video_sections JSON if the text field is empty.
 * ------------------------------------------------------------------------- */

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    acf_add_local_field_group( [
        'key'    => 'group_tlt_video_archive',
        'title'  => 'Recorded Programs',
        'fields' => [
            [
                'key'          => 'field_va_intro',
                'label'        => 'Intro paragraph',
                'name'         => 'va_intro',
                'type'         => 'textarea',
                'rows'         => 2,
                'default_value'=> 'Tacoma Little Theatre is proud to present a collection of recorded productions, virtual staged readings, and dance lessons. Click any video below to watch.',
            ],
            [
                'key'          => 'field_video_sections_text',
                'label'        => 'Video sections',
                'name'         => 'video_sections_text',
                'type'         => 'textarea',
                'rows'         => 14,
                'new_lines'    => '',
                'instructions' => 'One section per "## Heading". Optional "> intro line" under a heading. Then one video per line as: <code>YouTube/Vimeo URL | Caption</code>. Blank lines are ignored.',
                'placeholder'  => "## Main Stage Productions\nhttps://youtu.be/XXXX | Macbeth — 2019\n\n## Virtual Readings\n> Produced during the 2020 closure.\nhttps://youtu.be/YYYY | A Minidoka Christmas",
            ],
            [
                'key'          => 'field_partner_logos_text',
                'label'        => 'Partner theatre logos',
                'name'         => 'partner_logos_text',
                'type'         => 'textarea',
                'rows'         => 5,
                'new_lines'    => '',
                'instructions' => 'One logo per line: <code>Image URL | Alt text | Link URL</code> (link optional). Leave blank to hide the partner row.',
            ],
        ],
        'location' => [
            [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'page-video-archive.php' ] ],
        ],
        'menu_order' => 0,
        'position'   => 'normal',
        'style'      => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
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
                'default_value' => 'Education Program',
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

            [
                'key'           => 'field_edu_hero_tagline',
                'label'         => 'Hero tagline',
                'name'          => 'edu_hero_tagline',
                'type'          => 'text',
                'instructions'  => 'Short line under the title, above the benefit icons.',
                'default_value' => 'Our classes and camps build real skills on stage — and life skills that last well beyond it.',
            ],
            [
                'key'           => 'field_edu_benefits',
                'label'         => 'Benefit icons',
                'name'          => 'edu_benefits',
                'type'          => 'textarea',
                'rows'          => 4,
                'new_lines'     => '',
                'instructions'  => 'One per line: <code>Label | Description | icon</code>. icon = edu-confidence, edu-teamwork, edu-communication, or edu-creative.',
                'default_value' => "Confidence | Finding their voice on stage and off. | edu-confidence\nTeamwork | Creating something bigger, together. | edu-teamwork\nCommunication | Speaking and listening with intention. | edu-communication\nCreative Problem Solving | Solving problems in inventive ways. | edu-creative",
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
                'type'          => 'textarea',
                'rows'          => 18,
                'new_lines'     => '',
                'instructions'  => 'One program per <code>## Name</code>. Add <code>| /link</code> after the name to make it a link (e.g. <code>## Club TLT | /clubtlt/</code>). The line(s) below each name are the description — basic HTML like &lt;p&gt; and &lt;a&gt; is allowed. Blank line between programs.',
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
                'instructions'  => 'Image shown next to the scholarship section. Leave blank to use the default. Recommended ~600×400 or larger.',
            ],

            /* ===== Policies ===== */
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
                'type'          => 'textarea',
                'rows'          => 18,
                'new_lines'     => '',
                'instructions'  => 'One policy per <code>## Title</code>. The line(s) below each title are the description — basic HTML like &lt;p&gt; and &lt;a&gt; is allowed. Blank line between policies.',
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
        'hero_title'             => 'Education Program',
        'hero_intro'             => "TLT's Theatre classes help students of all ages to grow to their full potential as performers and more importantly as people. TLT's vision is to bring together students in our community to learn about and practice the skills and techniques of performance art, building life skills in the process.\n\nWhile TLT prides itself in educating students with extensive knowledge and powerful skills they need as performers, our courses and camps are also created to build confidence, team work, collaboration, self esteem, communication, innovative thinking and much, much more!\n\nOur classes are designed to enhance curriculums of study for both students attending public or private schools and those who are homeschooled, by providing opportunities for art to be part of the daily lives of our students.\n\nIn addition to skill building courses, TLT also offers exciting avenues for performance through our drama camps and stage productions.\n\nOur instructors are trained theatre artists and bring a variety of experiences within the industry of theatre. Additionally, all instructors provide thorough curriculums for outstanding learning potential and must pass an extensive background check required by TLT.\n\nTLT is excited to further our mission of enriching our community with quality, live theater experiences. Come join the fun!",
        'hero_cta_label'         => 'Camp & Class Registration',
        'hero_cta_url'           => 'https://tlt.ludus.com/index.php?sections=classes',
        'hero_tagline'           => 'Our classes and camps build real skills on stage — and life skills that last well beyond it.',
        'benefits'               => "Confidence | Finding their voice on stage and off. | edu-confidence\nTeamwork | Creating something bigger, together. | edu-teamwork\nCommunication | Speaking and listening with intention. | edu-communication\nCreative Problem Solving | Solving problems in inventive ways. | edu-creative",
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

/* ---------------------------------------------------------------------------
 * Make page-editability obvious to staff.
 *
 * Classifies each page by its template and surfaces the result as an "Editing"
 * column on the Pages list, plus a short note at the top of the editor — so it's
 * clear at a glance whether a page is fully editable, title/intro-only, or
 * auto-generated / fixed in the design.
 * ------------------------------------------------------------------------- */
/**
 * The template WordPress will actually use for a page. Falls back to the
 * page-{slug}.php / page-{id}.php hierarchy when no template is explicitly
 * assigned (e.g. the "Home" page auto-uses page-home.php by slug).
 */
function tlt_effective_page_template( $post_id ) {
    $tpl = get_page_template_slug( $post_id );
    if ( $tpl ) return $tpl;
    $post = get_post( $post_id );
    if ( $post ) {
        $by_slug = 'page-' . $post->post_name . '.php';
        if ( locate_template( $by_slug ) ) return $by_slug;
        $by_id = 'page-' . $post_id . '.php';
        if ( locate_template( $by_id ) ) return $by_id;
    }
    return '';
}

function tlt_page_editability( $template ) {
    // Auto-generated — content is assembled entirely from other data; nothing to type.
    $auto   = [ 'page-home.php', 'page-board-and-staff.php', 'page-calendar.php', 'page-prior-seasons.php', 'page-splash.php', 'page-current-season.php' ];
    // Editable + auto-filled — main cards/lists pull from Shows or postings; the
    // surrounding text/settings ARE editable here.
    $hybrid = [ 'page-season-tickets.php', 'page-auditions.php', 'page-press.php', 'page-job-openings.php' ];
    // Hero-only — only the top is editable; body still hardcoded in the template.
    $hero   = [ 'page-off-the-shelf.php' ];
    // Developer / utility page.
    $dev    = [ 'page-styleguide.php' ];

    if ( in_array( $template, $auto, true ) ) {
        return [ 'auto', 'Auto-generated', '#2563eb',
            'This page builds itself from your Shows, Board & Staff, Promotions, and Calendar entries. Update those items to change it — there is nothing to type here.' ];
    }
    if ( in_array( $template, $hybrid, true ) ) {
        return [ 'hybrid', 'Editable + auto-filled', '#0f766e',
            'The show cards / listings on this page fill in automatically (from your Shows, and for Press/Job Openings from those postings). The surrounding text, settings, and prices are editable right here.' ];
    }
    if ( in_array( $template, $hero, true ) ) {
        return [ 'hero', 'Title & intro only', '#b45309',
            'You can edit the heading and intro at the top. The rest of this page is still hardcoded — ask your web admin to change the body.' ];
    }
    if ( in_array( $template, $dev, true ) ) {
        return [ 'dev', 'Developer page', '#6b7280',
            'Internal style reference — not a public content page.' ];
    }
    return [ 'full', 'Fully editable', '#15803d',
        'You can edit all of this page\'s content from here.' ];
}

/**
 * Editability for a specific page — checks alias/redirect pages first (those
 * just forward elsewhere), then falls back to template classification.
 */
function tlt_page_editability_for_post( $post_id ) {
    $slug      = get_post_field( 'post_name', $post_id );
    $redirects = function_exists( 'tlt_page_redirects' ) ? tlt_page_redirects() : [];
    if ( isset( $redirects[ $slug ] ) ) {
        return [ 'link', 'Linked', '#7c3aed',
            'This page just forwards visitors to ' . $redirects[ $slug ] . ' — there is nothing to edit here.' ];
    }
    return tlt_page_editability( tlt_effective_page_template( $post_id ) );
}

// "Editing" column on the Pages list.
add_filter( 'manage_pages_columns', function ( $cols ) {
    $new = [];
    foreach ( $cols as $k => $v ) {
        $new[ $k ] = $v;
        if ( $k === 'title' ) $new['tlt_edit'] = 'Editing';
    }
    return $new;
} );
add_action( 'manage_pages_custom_column', function ( $col, $post_id ) {
    if ( $col !== 'tlt_edit' ) return;
    list( $key, $label, $color, $tip ) = tlt_page_editability_for_post( $post_id );
    printf(
        '<span title="%s" style="display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:600;color:#fff;background:%s;white-space:nowrap">%s</span>',
        esc_attr( $tip ), esc_attr( $color ), esc_html( $label )
    );
}, 10, 2 );

// Short note at the top of the page editor explaining what's editable.
add_action( 'edit_form_after_title', function ( $post ) {
    if ( ! $post || $post->post_type !== 'page' ) return;
    list( $key, $label, $color, $tip ) = tlt_page_editability_for_post( $post->ID );
    if ( $key === 'full' ) return; // no note needed when everything is editable
    printf(
        '<div style="margin:12px 0;padding:10px 14px;border-left:4px solid %s;background:#f6f7f7;border-radius:3px;line-height:1.5"><strong>%s.</strong> %s</div>',
        esc_attr( $color ), esc_html( $label ), esc_html( $tip )
    );
} );

/* ---------------------------------------------------------------------------
 * HOME PAGE — Section headlines, subheads, number toggle, and per-section
 * buttons. Tabbed ACF group so Chris can edit each section without touching
 * PHP. All fields are optional — blank means "use the built-in default" so a
 * fresh install renders identically to before.
 *
 * Buttons format: one button per line, in the textarea field. Each line:
 *     Label | URL | new   (the "| new" suffix opens the link in a new tab)
 *
 * Examples:
 *     Order Single Tickets Here | https://tlt.ludus.com/index.php | new
 *     Order Season Tickets | /season-tickets/
 *
 * Helpers below render this consistently in the home template.
 * ------------------------------------------------------------------------- */

/**
 * Default copy for each home section. Mirrors what page-home.php used to
 * hardcode, so the page renders the same without Chris editing anything.
 */
function tlt_home_section_defaults() {
    return [
        'onstage' => [
            'eyebrow' => 'Onstage',
            'title'   => '', // blank → auto "{Year} Season" in the template
            'lede'    => '', // blank → auto progress text in the template
            'buttons' => "Order Single Tickets Here | https://tlt.ludus.com/index.php | new\nOrder Season Tickets | /season-tickets/",
        ],
        'education' => [
            'eyebrow' => 'Education',
            'title'   => 'Programs for Every Age',
            'lede'    => 'Classes, camps, and youth productions — explore the craft of theatre at TLT.',
            'buttons' => '',
        ],
        'special_events' => [
            'eyebrow' => 'Beyond the Stage',
            'title'   => 'Special Events',
            'lede'    => 'Mystery dinners, partner restaurants, and other ways to make a night of it.',
            'buttons' => '',
        ],
        'get_involved' => [
            'eyebrow' => 'Get Involved',
            'title'   => 'Join Us',
            'lede'    => 'Hiring, season tickets, and other ways to be part of TLT.',
            'buttons' => '',
        ],
        'support' => [
            'eyebrow' => 'Support',
            'title'   => 'Easy (and Free) Ways to Help TLT',
            'lede'    => '',
            'buttons' => '',
        ],
        'sponsors' => [
            'eyebrow' => 'With Gratitude',
            'title'   => 'Tacoma Little Theatre is honored to receive support from',
            'lede'    => '',
            'buttons' => 'Join Our Weekly Email List | https://tlt.ludus.com/subscribe.php | new',
        ],
    ];
}

/**
 * Read a home-page ACF field, falling back to the section defaults.
 * Example: tlt_home_field( 'onstage', 'title' )
 */
function tlt_home_field( $section, $name ) {
    $defaults = tlt_home_section_defaults();
    $default  = $defaults[ $section ][ $name ] ?? '';
    if ( function_exists( 'get_field' ) ) {
        $v = get_field( "home_{$section}_{$name}" );
        if ( $v !== null && $v !== '' && $v !== false ) return $v;
    }
    return $default;
}

/**
 * Whether the user opted to hide the "01" number badge on this section.
 */
function tlt_home_hide_number( $section ) {
    if ( function_exists( 'get_field' ) ) {
        return (bool) get_field( "home_{$section}_hide_number" );
    }
    return false;
}

/**
 * Parse a buttons textarea into [ ['label','url','new_tab'], ... ].
 * Format: Label | URL | new   (the "| new" is optional)
 */
function tlt_parse_home_buttons( $text ) {
    if ( ! $text ) return [];
    $out = [];
    foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
        $line = trim( $line );
        if ( '' === $line ) continue;
        $parts = array_map( 'trim', explode( '|', $line ) );
        if ( count( $parts ) < 2 ) continue;
        if ( $parts[0] === '' || $parts[1] === '' ) continue;
        $out[] = [
            'label'   => $parts[0],
            'url'     => $parts[1],
            'new_tab' => isset( $parts[2] ) && strtolower( $parts[2] ) === 'new',
        ];
    }
    return $out;
}

/**
 * Render a row of red CTA buttons under a home section. Returns nothing if
 * the buttons list is empty.
 */
function tlt_render_home_buttons( $buttons ) {
    if ( ! $buttons ) return;
    echo '<div style="text-align:center;margin-top:2rem;display:flex;gap:1rem;justify-content:center;flex-wrap:wrap">';
    foreach ( $buttons as $b ) {
        $tgt = ! empty( $b['new_tab'] ) ? ' target="_blank" rel="noopener"' : '';
        printf(
            '<a href="%s" class="btn btn-primary"%s>%s</a>',
            esc_url( $b['url'] ),
            $tgt,
            esc_html( $b['label'] )
        );
    }
    echo '</div>';
}

/**
 * Render a complete section head (eyebrow pill + h2 + lede) for the home
 * page, honouring the "hide number" toggle. Used by page-home.php and by
 * tlt_render_homepage_section() so all sections look the same.
 *
 * @param string $section    Section key (onstage, education, ...)
 * @param string $number     The "01" / "02" / ... label
 * @param string $eyebrow    Eyebrow text (after the number)
 * @param string $title      H2 title text
 * @param string $lede       Optional lede paragraph
 */
function tlt_render_home_section_head( $section, $number, $eyebrow, $title, $lede = '' ) {
    /* Numbers dropped from the eyebrow because sections hide themselves when
       they have no active promos, which left gaps like 01, 03, 04. Cleaner
       to just not number the sections at all. */
    ?>
    <div class="section-head">
      <div class="eyebrow"><?php echo esc_html( $eyebrow ); ?></div>
      <?php if ( $title !== '' ) : ?><h2><?php echo esc_html( $title ); ?></h2><?php endif; ?>
      <?php if ( $lede !== '' ) : ?><p><?php echo wp_kses_post( $lede ); ?></p><?php endif; ?>
    </div>
    <?php
}

/* ----- Register the field group ----- */

add_action( 'acf/init', function () {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) return;

    $sections = [
        'onstage'        => [ 'tab' => 'Onstage (current season)',       'num' => '01' ],
        'education'      => [ 'tab' => 'Education',                       'num' => '02' ],
        'special_events' => [ 'tab' => 'Special Events',                  'num' => '03' ],
        'get_involved'   => [ 'tab' => 'Get Involved',                    'num' => '04' ],
        'support'        => [ 'tab' => 'Support',                         'num' => '05' ],
        'sponsors'       => [ 'tab' => 'Sponsors',                        'num' => '06' ],
    ];

    $defaults = tlt_home_section_defaults();
    $fields   = [];

    foreach ( $sections as $key => $cfg ) {
        $d = $defaults[ $key ];
        $fields[] = [
            'key'   => "field_home_{$key}_tab",
            'label' => $cfg['tab'],
            'type'  => 'tab',
        ];
        $fields[] = [
            'key'           => "field_home_{$key}_eyebrow",
            'label'         => 'Eyebrow pill',
            'name'          => "home_{$key}_eyebrow",
            'type'          => 'text',
            'wrapper'       => [ 'width' => '50' ],
            'instructions'  => 'Small text next to the number (e.g. "Onstage").',
            'default_value' => $d['eyebrow'],
        ];
        $fields[] = [
            'key'           => "field_home_{$key}_hide_number",
            'label'         => "Hide the {$cfg['num']} number",
            'name'          => "home_{$key}_hide_number",
            'type'          => 'true_false',
            'wrapper'       => [ 'width' => '50' ],
            'ui'            => 1,
            'instructions'  => 'Hide the small number badge before the eyebrow text.',
            'default_value' => 0,
        ];
        $fields[] = [
            'key'           => "field_home_{$key}_title",
            'label'         => 'Headline',
            'name'          => "home_{$key}_title",
            'type'          => 'text',
            'instructions'  => $key === 'onstage'
                ? 'Big section heading. Leave blank to auto-show "{Year} Season".'
                : 'Big section heading.',
            'default_value' => $d['title'],
        ];
        $fields[] = [
            'key'           => "field_home_{$key}_lede",
            'label'         => 'Subhead / intro paragraph',
            'name'          => "home_{$key}_lede",
            'type'          => 'textarea',
            'rows'          => 3,
            'instructions'  => $key === 'onstage'
                ? 'Short paragraph under the headline. Leave blank to auto-show the season progress text ("3 of 7 shows so far this season").'
                : 'Short paragraph under the headline. Leave blank to hide.',
            'default_value' => $d['lede'],
        ];
        $fields[] = [
            'key'           => "field_home_{$key}_buttons",
            'label'         => 'Buttons',
            'name'          => "home_{$key}_buttons",
            'type'          => 'textarea',
            'rows'          => 4,
            'new_lines'     => '',
            'instructions'  => 'One button per line. Format: <code>Label | URL | new</code>. The "| new" suffix is optional and opens the link in a new tab. Leave the whole field blank for no buttons. Examples:<br><code>Order Single Tickets Here | https://tlt.ludus.com/index.php | new</code><br><code>Order Season Tickets | /season-tickets/</code>',
            'default_value' => $d['buttons'],
        ];
    }

    acf_add_local_field_group( [
        'key'      => 'group_home_sections',
        'title'    => 'Home Page — Sections',
        'fields'   => $fields,
        'location' => [
            [ [ 'param' => 'page_template', 'operator' => '==', 'value' => 'page-home.php' ] ],
        ],
        'menu_order'            => 0,
        'position'              => 'normal',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
    ] );
} );
