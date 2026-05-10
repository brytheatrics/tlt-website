# TLT Website — Design Tokens

Extracted from `https://www.tacomalittletheatre.com/site.css` (Squarespace) + homepage HTML.
Use this as the style guide for the WordPress rebuild.

## Google Fonts loaded

- `Montserrat:ital,wght@0,400`

## Most-used font-family declarations

| Count | font-family |
|---|---|
| 49 | `"Helvetica Neue",Arial,sans-serif` |
| 38 | `Arial` |
| 26 | `'squarespace-ui-font'` |
| 18 | `proxima-nova` |
| 15 | `inherit` |
| 12 | `"Helvetica Neue",Helvetica,Arial,sans-serif` |
| 11 | `adelle-sans` |
| 10 | `Georgia,serif` |
| 10 | `Helvetica,arial,sans-serif` |
| 8 | `Helvetica,Arial,sans-serif` |
| 6 | `"proxima-nova","Helvetica Neue",Helvetica,Arial,sans-serif` |
| 4 | `sans-serif` |
| 4 | `Libre Franklin` |
| 3 | `'social-icon-font'` |
| 3 | `"futura-pt",Helvetica,sans-serif` |

## Most-used colors (top 20 hex)

| Count | Color | Swatch |
|---|---|---|
| 148 | `#fff` | ![#fff](https://placehold.co/40x20/fff/fff.png) |
| 80 | `#272727` | ![#272727](https://placehold.co/40x20/272727/272727.png) |
| 71 | `#000` | ![#000](https://placehold.co/40x20/000/000.png) |
| 58 | `#222` | ![#222](https://placehold.co/40x20/222/222.png) |
| 22 | `#3e3e3e` | ![#3e3e3e](https://placehold.co/40x20/3e3e3e/3e3e3e.png) |
| 19 | `#8f1a24` | ![#8f1a24](https://placehold.co/40x20/8f1a24/8f1a24.png) |
| 17 | `#ddd` | ![#ddd](https://placehold.co/40x20/ddd/ddd.png) |
| 16 | `#131313` | ![#131313](https://placehold.co/40x20/131313/131313.png) |
| 12 | `#3b5998` | ![#3b5998](https://placehold.co/40x20/3b5998/3b5998.png) |
| 12 | `#0099e5` | ![#0099e5](https://placehold.co/40x20/0099e5/0099e5.png) |
| 12 | `#0063dc` | ![#0063dc](https://placehold.co/40x20/0063dc/0063dc.png) |
| 12 | `#f94877` | ![#f94877](https://placehold.co/40x20/f94877/f94877.png) |
| 12 | `#4183c4` | ![#4183c4](https://placehold.co/40x20/4183c4/4183c4.png) |
| 12 | `#e4405f` | ![#e4405f](https://placehold.co/40x20/e4405f/e4405f.png) |
| 12 | `#0976b4` | ![#0976b4](https://placehold.co/40x20/0976b4/0976b4.png) |
| 12 | `#cc2127` | ![#cc2127](https://placehold.co/40x20/cc2127/cc2127.png) |
| 12 | `#7dbb00` | ![#7dbb00](https://placehold.co/40x20/7dbb00/7dbb00.png) |
| 12 | `#f60` | ![#f60](https://placehold.co/40x20/f60/f60.png) |
| 12 | `#84bd00` | ![#84bd00](https://placehold.co/40x20/84bd00/84bd00.png) |
| 12 | `#35465d` | ![#35465d](https://placehold.co/40x20/35465d/35465d.png) |

## Common font sizes

- `12px` (48x)
- `14px` (34x)
- `13px` (33x)
- `16px` (30x)
- `18px` (16x)
- `20px` (14x)
- `22px` (12x)
- `1em` (11x)
- `11px` (11x)
- `15px` (11x)
- `.9em` (10x)
- `32px` (7x)
- `1.1em` (6x)
- `60px` (6x)
- `30px` (5x)

## Border radii (button/card rounding)

- `3px` (6x)
- `50%` (5x)
- `0px` (4x)
- `300px` (4x)
- `300px 0px 0px 300px` (4x)
- `0px 300px 300px 0px` (4x)
- `0` (3x)
- `2px` (3x)
- `4px` (2x)
- `15%` (2x)

## Notes for the rebuild

- Squarespace's site.css is auto-generated and includes a lot of framework noise. Use the *most-frequent* values above as the real design system, not every value present.
- The homepage uses one Google Font (Montserrat) per the `<link>` tag. Other fonts (Helvetica Neue, etc.) are system fallbacks Squarespace ships in normalize CSS.
- For the WP rebuild: load Montserrat from Google Fonts (or self-host for performance), use the top 5-8 hex colors as the palette, match heading sizes from the most-used `font-size` values.
