---
name: TLT website design inspiration — theatre site references
description: Theatre sites Blake likes as visual/UX reference for the TLT redesign
type: reference
---
Sites Blake has flagged as design inspiration for the TLT site (2026-05-12):

## Centaur Theatre
https://centaurtheatre.com/
Initial reference — solid all-around theatre site UX.

## National Theatre (US site)
https://www.nationaltheatre.org.uk/home-us/
Has **similar horizontal banding** to TLT's current layout (header / hero / section strips), but feels **less generic** — Blake's exact words. Study this one when working on hero, section-divider treatment, and overall page rhythm.

## Ford's Theatre
https://fords.org/
Blake likes specific aspects (not the whole site):
- **Hero photo is much larger** than TLT's — full-bleed, dominant
- **Double footer** pattern (richer info row above a thin legal/social strip)
- **More useful footer** content — actionable links and content, not just legal boilerplate

Look here when working on the homepage hero size/proportion and when rebuilding the site footer.

## Southern Futures CPA *(non-theatre — pure design reference)*
https://southernfuturescpa.org/
Blake likes "quite a bit about this." Key takeaway: **dynamic feel of movement without images scaling**. The **banners themselves move a little** — horizontal drift / slow pan inside the band rather than scroll-triggered photo growth. Refines the earlier scroll-motion preference: Blake prefers *banner movement* over *image scaling on scroll*.

Likely implementation: a `@keyframes` slow horizontal drift on the banner's background-image (e.g. `background-position` shift over 20-30s), or a slow `transform: translateX()` loop on a wide image strip inside the band. Subtle — should be noticeable but not distracting.

---

## Treatments Blake likes across these sites

- **Subtle motion in banners (preferred).** Per Southern Futures CPA reference — banners with a slow horizontal drift or pan give a dynamic feel without making things grow/scale on scroll. This is Blake's *refined* preference over scroll-triggered image scaling. Implementation: slow `@keyframes` on `background-position` or `transform: translateX()` inside the band; loop 20-30s, ease-in-out.
- **Scroll-driven motion (secondary).** Photos that grow / scale up slightly as they come into view also work, but Blake's preference leans toward banner-drift over image-scale. If using, keep it light: `animation-timeline: view()` for modern browsers or IntersectionObserver fallback. Never scrolljack.

---

Surface these when working on TLT visual / UX questions (header treatment, show-page layout, navigation patterns, hero / CTA styling, section banding, scroll motion). When Blake says "similar to X but better," check these first.

If Blake adds more references later, append them above this line as new sections.
