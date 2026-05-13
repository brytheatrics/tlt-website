# Architecture — NO page builder

Decision (2026-05-12): TLT website uses a custom theme with hard templates + a small flex-content block library + a "Designed Page" template for bespoke one-offs. **No Elementor, no Bricks, no page builder.**

## Three-tier system

1. **Hard templates** for predictable content (shows, board, season grids, auditions, ticketing, contact, campaign, video archive)
2. **Flex-content blocks** Chris stacks for one-off-but-similar pages (prose, figure, button, section heading, image-with-text-float, full-bleed banner, video embed, PDF link list, photo gallery, two-column callout, logo row)
3. **Designed Page** template (hero image + headline + body + up to 3 CTAs) for bespoke design

## Customizer exposes only content settings

Logo, address, mission/vision text, social links. **NOT** colors, fonts, layouts — those are CSS variables in code. Chris is Administrator but the controls he can use are constrained by what's exposed in the UI, not by role permissions.

## Why this and not Elementor

Chris's job is updating content within already-designed pages, not designing pages. Templates enforce cohesion (Blake explicitly wants this). No $59/yr cost. Faster pages. No vendor lock-in. Distinctive look. Bus factor mitigated by standard-WordPress patterns + documentation.

## Full architecture

`_planning/ARCHITECTURE.md` is the authoritative document. `_planning/decisions.md` logs each decision and why.
