# Snapshot — 2026-05-12 pre-homepage-redesign

State of the theme right before starting the "less flat, more interesting" homepage redesign experiment.

## To revert

Copy any of these back over the live theme files:

```bash
cp _snapshots/2026-05-12-pre-homepage-redesign/style.css      wordpress/themes/tlt/style.css
cp _snapshots/2026-05-12-pre-homepage-redesign/page-home.php  wordpress/themes/tlt/page-home.php
cp _snapshots/2026-05-12-pre-homepage-redesign/header.php     wordpress/themes/tlt/header.php
cp _snapshots/2026-05-12-pre-homepage-redesign/footer.php     wordpress/themes/tlt/footer.php
```

## What "good" was at snapshot time

- Charcoal header (`var(--color-text)`) with white nav, red Donate/Buy Tickets CTAs
- Hero fills viewport: `min-height: calc(100svh - var(--header-h))`
- Hero image full-bleed at opacity 0.6, content anchored to bottom-left
- Show-card grid, season archives, /shows/ with Earlier Seasons section, /off-the-shelf/, /recorded-programs/ all functional
