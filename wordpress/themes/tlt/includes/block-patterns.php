<?php
/**
 * Flex content library — Gutenberg block patterns.
 *
 * 12 pre-styled section "blocks" Chris can stack on one-off pages (page.php)
 * to assemble flex-content layouts without needing a developer. Each pattern
 * is a curated arrangement of core Gutenberg blocks, with TLT-themed
 * className attributes that hook into the existing styles in style.css.
 *
 * To use: in the block editor, click the "+" inserter → switch to the
 * "Patterns" tab → "TLT Flex Blocks" category → click a pattern.
 *
 * Why patterns (not ACF Flexible Content): see _planning/decisions.md.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', function () {

    if ( ! function_exists( 'register_block_pattern_category' ) ) return;

    register_block_pattern_category( 'tlt-flex', [
        'label'       => 'TLT Flex Blocks',
        'description' => 'Pre-styled section blocks for one-off pages.',
    ] );

    /* -----------------------------------------------------------------------
     * 1. Prose (heading + paragraph)
     * --------------------------------------------------------------------- */
    register_block_pattern( 'tlt/prose', [
        'title'       => 'Prose: heading + paragraphs',
        'description' => 'The workhorse. A heading and a couple of paragraphs.',
        'categories'  => [ 'tlt-flex' ],
        'keywords'    => [ 'text', 'paragraph', 'heading' ],
        'content'     => <<<'HTML'
<!-- wp:heading {"level":2} --><h2>Section title</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Your paragraph text here. Replace this with the section body.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>A second paragraph for more detail. Delete if you only need one.</p><!-- /wp:paragraph -->
HTML
    ] );

    /* -----------------------------------------------------------------------
     * 2. Figure (image + caption)
     * --------------------------------------------------------------------- */
    register_block_pattern( 'tlt/figure', [
        'title'       => 'Figure: image with caption',
        'description' => 'A single image with an italic caption underneath.',
        'categories'  => [ 'tlt-flex' ],
        'keywords'    => [ 'image', 'photo', 'caption' ],
        'content'     => <<<'HTML'
<!-- wp:image {"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large"><img alt=""/><figcaption class="wp-element-caption">Caption text — describe the image briefly.</figcaption></figure>
<!-- /wp:image -->
HTML
    ] );

    /* -----------------------------------------------------------------------
     * 3. Image with text float (image-right + prose wraps)
     * --------------------------------------------------------------------- */
    register_block_pattern( 'tlt/image-text-float', [
        'title'       => 'Image with text wrap',
        'description' => 'Image floated to the right with body text wrapping around it.',
        'categories'  => [ 'tlt-flex' ],
        'keywords'    => [ 'image', 'float', 'wrap' ],
        'content'     => <<<'HTML'
<!-- wp:image {"sizeSlug":"medium","align":"right","linkDestination":"none","className":"alignright"} -->
<figure class="wp-block-image alignright size-medium"><img alt=""/></figure>
<!-- /wp:image -->
<!-- wp:paragraph --><p>Your body text starts here and wraps around the floated image. On mobile, the image stacks above the text automatically.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p>Continue with another paragraph or two as needed. Delete this paragraph if you don't need more body.</p><!-- /wp:paragraph -->
HTML
    ] );

    /* -----------------------------------------------------------------------
     * 4. Section heading (eyebrow-style divider)
     * --------------------------------------------------------------------- */
    register_block_pattern( 'tlt/section-heading', [
        'title'       => 'Section heading',
        'description' => 'Big underlined h2 used as a section break on long pages.',
        'categories'  => [ 'tlt-flex' ],
        'keywords'    => [ 'heading', 'divider', 'section' ],
        'content'     => <<<'HTML'
<!-- wp:heading {"level":2,"className":"section-heading"} -->
<h2 class="section-heading">Section heading</h2>
<!-- /wp:heading -->
HTML
    ] );

    /* -----------------------------------------------------------------------
     * 5. Full-bleed banner image
     * --------------------------------------------------------------------- */
    register_block_pattern( 'tlt/full-bleed-banner', [
        'title'       => 'Full-bleed banner image',
        'description' => 'A wide image that breaks out of the body column edge-to-edge.',
        'categories'  => [ 'tlt-flex' ],
        'keywords'    => [ 'image', 'banner', 'hero', 'full-bleed' ],
        'content'     => <<<'HTML'
<!-- wp:image {"sizeSlug":"full","linkDestination":"none","className":"full-bleed"} -->
<figure class="wp-block-image full-bleed size-full"><img alt=""/></figure>
<!-- /wp:image -->
HTML
    ] );

    /* -----------------------------------------------------------------------
     * 6. Button / CTA
     * --------------------------------------------------------------------- */
    register_block_pattern( 'tlt/button-cta', [
        'title'       => 'Button (CTA)',
        'description' => 'A primary-style call-to-action button.',
        'categories'  => [ 'tlt-flex' ],
        'keywords'    => [ 'button', 'cta', 'link' ],
        'content'     => <<<'HTML'
<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} -->
<div class="wp-block-buttons">
<!-- wp:button {"className":"btn btn-primary is-style-fill"} -->
<div class="wp-block-button btn btn-primary is-style-fill"><a class="wp-block-button__link wp-element-button" href="#">Button label</a></div>
<!-- /wp:button -->
</div>
<!-- /wp:buttons -->
HTML
    ] );

    /* -----------------------------------------------------------------------
     * 7. Video embed (YouTube/Vimeo)
     * --------------------------------------------------------------------- */
    register_block_pattern( 'tlt/video-embed', [
        'title'       => 'Video embed',
        'description' => 'Paste a YouTube or Vimeo URL inside the embed block.',
        'categories'  => [ 'tlt-flex' ],
        'keywords'    => [ 'video', 'youtube', 'vimeo', 'embed' ],
        'content'     => <<<'HTML'
<!-- wp:embed {"providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">https://www.youtube.com/watch?v=</div><figcaption class="wp-element-caption">Optional caption</figcaption></figure>
<!-- /wp:embed -->
HTML
    ] );

    /* -----------------------------------------------------------------------
     * 8. PDF link list (decade pages, audition packets)
     * --------------------------------------------------------------------- */
    register_block_pattern( 'tlt/pdf-link-list', [
        'title'       => 'PDF link list',
        'description' => 'A bulleted list of PDF links. Each link gets a 📄 icon automatically.',
        'categories'  => [ 'tlt-flex' ],
        'keywords'    => [ 'pdf', 'list', 'programs', 'downloads' ],
        'content'     => <<<'HTML'
<!-- wp:list {"className":"pdf-list"} -->
<ul class="pdf-list">
<!-- wp:list-item --><li><a href="/wp-content/uploads/programs/example.pdf">Document name</a></li><!-- /wp:list-item -->
<!-- wp:list-item --><li><a href="/wp-content/uploads/programs/example2.pdf">Another document</a></li><!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
HTML
    ] );

    /* -----------------------------------------------------------------------
     * 9. Photo gallery
     * --------------------------------------------------------------------- */
    register_block_pattern( 'tlt/photo-gallery', [
        'title'       => 'Photo gallery',
        'description' => 'A multi-image gallery (3 columns).',
        'categories'  => [ 'tlt-flex' ],
        'keywords'    => [ 'gallery', 'photos', 'images' ],
        'content'     => <<<'HTML'
<!-- wp:gallery {"columns":3,"linkTo":"media"} -->
<figure class="wp-block-gallery has-nested-images columns-3 is-cropped"></figure>
<!-- /wp:gallery -->
HTML
    ] );

    /* -----------------------------------------------------------------------
     * 10. Two-column callout (address + hours, two pricing tiers)
     * --------------------------------------------------------------------- */
    register_block_pattern( 'tlt/two-column-callout', [
        'title'       => 'Two-column callout',
        'description' => 'A bordered box with two side-by-side info blocks (address + hours, two tiers, etc).',
        'categories'  => [ 'tlt-flex' ],
        'keywords'    => [ 'callout', 'two-column', 'info' ],
        'content'     => <<<'HTML'
<!-- wp:group {"className":"callout-pair","tagName":"div"} -->
<div class="wp-block-group callout-pair">
<!-- wp:group {"tagName":"div"} -->
<div class="wp-block-group">
<!-- wp:heading {"level":3} --><h3>Box Office Hours</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>Tuesday – Friday<br>1:00 pm – 6:00 pm</p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
<!-- wp:group {"tagName":"div"} -->
<div class="wp-block-group">
<!-- wp:heading {"level":3} --><h3>Address</h3><!-- /wp:heading -->
<!-- wp:paragraph --><p>210 N "I" Street<br>Tacoma, WA 98403</p><!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
HTML
    ] );

    /* -----------------------------------------------------------------------
     * 11. Logo / sponsor row
     * --------------------------------------------------------------------- */
    register_block_pattern( 'tlt/logo-row', [
        'title'       => 'Logo / sponsor row',
        'description' => 'A wrapping row of logos with links. Used for partner theatres, sponsors, donors.',
        'categories'  => [ 'tlt-flex' ],
        'keywords'    => [ 'logo', 'sponsor', 'partner', 'row' ],
        'content'     => <<<'HTML'
<!-- wp:group {"className":"logo-row","tagName":"div"} -->
<div class="wp-block-group logo-row">
<!-- wp:image {"sizeSlug":"thumbnail","linkDestination":"custom"} -->
<figure class="wp-block-image size-thumbnail"><a href="#"><img alt="Sponsor 1"/></a></figure>
<!-- /wp:image -->
<!-- wp:image {"sizeSlug":"thumbnail","linkDestination":"custom"} -->
<figure class="wp-block-image size-thumbnail"><a href="#"><img alt="Sponsor 2"/></a></figure>
<!-- /wp:image -->
<!-- wp:image {"sizeSlug":"thumbnail","linkDestination":"custom"} -->
<figure class="wp-block-image size-thumbnail"><a href="#"><img alt="Sponsor 3"/></a></figure>
<!-- /wp:image -->
</div>
<!-- /wp:group -->
HTML
    ] );

    /* -----------------------------------------------------------------------
     * 12. Pull-quote
     * --------------------------------------------------------------------- */
    register_block_pattern( 'tlt/pull-quote', [
        'title'       => 'Pull-quote',
        'description' => 'A large emphasized quote with optional attribution.',
        'categories'  => [ 'tlt-flex' ],
        'keywords'    => [ 'quote', 'pullquote', 'testimonial' ],
        'content'     => <<<'HTML'
<!-- wp:pullquote -->
<figure class="wp-block-pullquote"><blockquote><p>"A short, memorable quote that captures the moment."</p><cite>— Attribution (name, role, or publication)</cite></blockquote></figure>
<!-- /wp:pullquote -->
HTML
    ] );

}, 20 ); // priority 20 to run after core categories are registered
