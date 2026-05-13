# Flex-block partials

Small, reusable PHP partials that templates can include to render common content patterns. The styling for each lives in `style.css` under "Default page template extensions".

## How a template uses them

```php
get_template_part( 'template-parts/flex-blocks/figure', null, [
    'image_url' => 'https://example.com/photo.jpg',
    'alt'       => 'A description',
    'caption'   => 'Production photo from the 2026 run',
    'align'     => 'right',   // optional: 'left' | 'right' | null
] );
```

The third argument (an `$args` array) is passed as `$args` inside the partial.

## How a flexible-content page would use them

A page-level flexible-content area iterates through stored blocks and dispatches:

```php
foreach ( $blocks as $block ) {
    get_template_part(
        'template-parts/flex-blocks/' . sanitize_file_name( $block['type'] ),
        null,
        $block['data']
    );
}
```

(Implementation of the storage side — saving/loading blocks per page — comes later when we wire up admin UX, probably with ACF Flexible Content or a meta-based equivalent.)

## Available blocks

| File | Purpose |
|---|---|
| `prose.php` | Rich text body |
| `figure.php` | Image with optional caption, optional float |
| `image-text.php` | 2-column image + body text |
| `full-bleed.php` | Full-width banner image |
| `button.php` | Single CTA button |
| `cta-row.php` | Multiple buttons in a row |
| `section-heading.php` | Big section break heading |
| `pull-quote.php` | Highlighted pull quote |
| `video-embed.php` | YouTube / Vimeo embed via oEmbed |
| `pdf-link-list.php` | List of PDF download links |
| `photo-gallery.php` | Lightbox photo grid |
| `callout-pair.php` | Side-by-side two-column callout |
| `logo-row.php` | Sponsor / partner logo row |

All accept a `$args` array of strings/URLs; none accept HTML for security (esc_html / esc_url applied).
