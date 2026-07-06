# Hero Animation Workflow

Reference for building a show's layered animated hero. Written after
Outsider / Arsenic / Hallmarked to avoid rediscovering the same patterns.

## Per-show checklist

1. **Inspect the PSD** with `psd_tools` — get canvas dims + every layer's
   name, blend mode, visible flag, and bbox. Bboxes drive extraction decisions:
   any layer with bbox extending past canvas needs per-layer viewport.
2. **Decide the animation sequence** — what slides, what fades, what scales,
   in what order, from where. Sketch delays before writing CSS.
3. **Write / adapt the extraction script** — `extract_<show>_desktop.py` and
   `extract_<show>_mobile.py`. Desktop outputs PNG. Mobile outputs WebP.
4. **Extract, view composite.jpg** — confirms layer stacking + scale look right
   before touching CSS.
5. **Add CSS rules** — content-name selectors like `[data-name$="-foo"]`, both
   at desktop scope and inside the `@media (max-width: 700px)` block.
6. **Test with `TLT_AS_OF`** in `functions.php` set to when the show opens.
7. **Cache-bust already handled** — `page-home.php` appends `?v=filemtime` to
   layer URLs, so re-extraction always shows fresh files.

## Extraction — canonical form

```python
SRC = '//TLT-SERVER/Marketing/2627 Marketing/2627 Posters/<N> <Show>/Animated Hero <Show>.psd'
OUT = r'C:/Users/blake/Local Sites/tlt/app/public/wp-content/uploads/hero-layers/<slug>'

# Bottom-to-top stacking order → 1-*, 2-*, ... Higher number = renders on top.
NAME_MAP = { 'background': '1-bg', ..., 'overlays': 'N-overlays' }

VIEWPORT = (0, 0, psd.width, psd.height)   # canvas-only default
# Per-layer bleed viewports for layers whose bbox extends past canvas
# and whose animation should reveal that content:
BLEED_VIEWPORTS = { '5-smoke': (0, -350, psd.width, psd.height), ... }
```

## When each layer needs bleed

| Situation | Fix |
| --- | --- |
| Layer bbox extends **above** canvas, layer scales up or slides in from above | Top bleed = at least `abs(bbox.top)` + a safety margin for any rotation |
| Layer bbox extends **left/right** of canvas, layer rotates or scales | Symmetric horizontal bleed = at least `max(-bbox.left, bbox.right - canvas.width)` |
| Layer rotates by θ° | Bleed on all sides ≥ `~sin(θ) * half_diagonal` of the shape |
| Layer slides in from off-canvas but bbox is fully inside canvas | No bleed needed — the slide's translate will move it off-screen naturally |

## CSS positioning for bleed layers

A layer extracted with bleed has a different aspect than the canvas. **Do
NOT** use `inset: 0`/`width: 100%; height: 100%` — that stretches or forces
the aspect. Use the formula below instead so the canvas portion aligns
exactly with the hero, and the bleed sits outside the hero (clipped by
`overflow: hidden`).

Given canvas dims `Wc × Hc` and viewport with top bleed `Tb`, bottom bleed
`Bb`, left bleed `Lb`, right bleed `Rb`, the extracted layer is
`(Wc+Lb+Rb) × (Hc+Tb+Bb)`.

**Desktop** — canvas fills hero_h, natural aspect makes layer wider than
hero_w, horizontal excess is clipped:

```css
.hero-layered [data-name$="-<layer>"] {
  height: <(Hc+Tb+Bb)/Hc * 100>%;         /* e.g. 117.5% for 350 top bleed */
  width: auto;
  inset: -<Tb/Hc * 100>% auto auto 50%;   /* e.g. top: -17.5% */
  translate: -50% 0;
  object-fit: unset;
}
```

**Mobile** — same formula, but the mobile base rule uses height-fit anyway
so this override slots in cleanly under `@media (max-width: 700px)`. Watch
out for horizontal bleed: if it's asymmetric, canvas won't be centered
under the default `left: 50%; translate: -50% 0` — extract symmetrically or
adjust translate percentage to compensate.

## Animation keyframes (already defined in style.css)

- `layerIn` — fade + optional translate. Default for background, overlays,
  and any "just appears in place" layer. Uses `--layer-from` for translate.
- `layerScaleIn` — scale from 0 → 1. Use `transform-origin` to anchor the
  growth to a meaningful point (e.g. bottle tip for a splash effect).
- `layerSlideIn` — translate only, no fade. Physical objects entering the
  frame. Requires `animation-fill-mode: both` + `opacity: 1` so the layer
  stays hidden during delay. See the shared solid-slide selector list.
- `layerSlideRotateIn` — translate + rotate simultaneously. Compound
  `--layer-from` like `translateY(100%) rotate(15deg)`.

## Timing patterns

- Fade-only sequence: 0.3–0.7s gaps between starts feels natural.
- Solid slide (bottle, stamp): duration ~1.8s. Wait for `delay + 1.8` before
  triggering the next dependent layer.
- Scale-up: shorter perceived duration (~1.0s) works too, but keeping 1.8s
  default is fine if the layer sequence spaces them out.
- Total intro budget: keep under ~6s. Longer feels sluggish.

## Mobile-specific gotchas

- **Mobile media query numbered fallback rules** (`.hero-layer-N`) override
  desktop content-name delays via cascade. If a show has content-name rules
  for layers whose stack position matches a numbered rule, add matching
  content-name rules INSIDE the mobile media query so timings don't reset.
- **Canvas-only viewport is usually right for mobile** — the mobile PSD is
  already sized for portrait; adding uniform bleed shrinks the visible
  composition (Arsenic mobile had this: BLEED_Y=700 made canvas 75% of hero
  height when user wanted 100%).
- **Per-layer bleed for smoke / stamp / etc.** on mobile — extract with
  needed bleed only for that layer, use special CSS positioning. Other
  layers stay canvas-only.
- **Slides need bigger translate distances than fades**. With `layerSlideIn`
  (no fade), the layer is visible at slide-start position during the delay.
  translateX(20%) leaves the subject partially on-screen. Use 70-100%.

## Debugging patterns

- **Straight vertical/horizontal edge visible during animation** → extraction
  clipped a layer's natural edge at canvas boundary. Add bleed.
- **Layer looks scaled down** after adding bleed → CSS is stretching aspect.
  Switch from `inset: 0/width: 100%; height: 100%` to explicit height
  percentage + width auto + horizontal center. See "CSS positioning for
  bleed layers" above.
- **Element appears rotated around wrong point** → default `transform-origin`
  is 50% 50% of the element (which includes bleed). If the content isn't
  centered in the element, set `transform-origin` to the content's %
  position in the element.
- **Bg not reaching hero top** → bg extracted with top bleed but its own
  bbox doesn't cover the bleed area. Reduce bleed to match bg's extent, or
  accept the transparent margin.
- **Mobile transform-origins wrong** → % is of element box, not canvas.
  When mobile viewport has bleed, canvas is offset within the element —
  origin percentages shift accordingly. Formula:
  `origin_element_y% = (target_canvas_y * Hc + Tb) / (Hc + Tb + Bb)`

## WebP for mobile

Mobile PSD extractions output **WebP directly** (`quality=82, method=6`).
Real audit result: a typical PNG mobile hero was 2.1 MB; the same layers as
WebP were 555 KB — 74% reduction. Grain overlays compress ~50%, opaque
subjects (person, podium, sandwiches) crush 85-95%. Desktop stays PNG
because file size matters less and PNG is lossless.

- Save via `img.save(p, 'WEBP', quality=82, method=6)`.
- Discovery in `page-home.php` prefers `.webp` in the mobile dir, falls back
  to `.png` — no template change needed per show once wired up.
- Composite for reduced-motion fallback stays JPEG at 88%.

## Cache-busting and pre-load layout

Two subtle template features that fix a whole class of "why is this
broken" issues:

- **`?v=filemtime` on every layer URL** — added by `page-home.php` to both
  desktop `src` and mobile `srcset`. Any re-extraction touches the file's
  mtime, so browsers refresh instantly. Without this, Live Link + phone
  caches make it look like your changes aren't landing.
- **HTML `width`/`height` attrs on `<img>` and `<source>`** — read from
  `getimagesize()` in PHP and emitted verbatim. Gives the browser the
  aspect ratio before the image decodes, so layers with `height: X%;
  width: auto` don't collapse to a wrong size on first paint and then
  snap after load. Without these you see a visible "half-screen bg" flash
  on refresh.

Both are already wired up globally — nothing per-show to remember.

## Show-slug class hook

`page-home.php` emits `hero-slug-<slug>` on the `.hero` section. Use it to
scope any per-show CSS override (bg scale/translate, custom keyframes,
etc.) without leaking to other shows. Example:

```css
.hero-slug-the-importance-of-being-earnest [data-name$="-bg"] {
  scale: 2.1;
  translate: 0 18%;
}
```

Wrap in `@media (min-width: 701px)` if the override is desktop-only.

## Layer effects on shape layers — rasterize in the PSD

If a Background is a **shape layer with Gradient Overlay / Pattern Overlay
/ Inner Glow / Drop Shadow** effects, `psd_tools` only renders those
effects when compositing the FULL PSD. `layer.composite()` alone (with
non-bg layers hidden) returns a flat grey — no color, no gradient.

Fix: in Photoshop, right-click the layer → **Rasterize Layer Style**.
Flattens the effects into pixel data. `layer.composite()` then extracts
correctly. Symptom to watch for: extracted bg is a greyscale gradient
when the PSD shows color.

Workaround if you can't rasterize (last resort): composite the whole PSD,
sample a clean vertical strip of bg from an area no subject covers, tile
it. Loses pattern-overlay fidelity, doesn't work if subjects cover the
whole vertical range.

## Multi-state keyframe pattern (PTGW-style)

Some shows need more than a simple slide/fade — e.g. The Play That Goes
Wrong walks each word through 3 positions (state 1 scrambled → state 2
correct → state 3 rotated). Pattern:

1. Extract each word as its own layer from the "base" state PSD (usually
   the correct-arrangement one).
2. Compute per-word offsets from bbox deltas across the state PSDs: state
   1 offset = state1_bbox_topleft - state2_bbox_topleft, likewise state 3.
3. Convert to canvas-percent (`dx / canvas_width * 100`, etc.).
4. Write per-word `@keyframes` moving through 0% (off-screen random) →
   25% (state 1) → 50-72% (staggered state 2) → 92% (hold) → 100% (state
   3 rotate/scale).
5. For grouped rotation: give ALL words the SAME `transform-origin` and
   the SAME rotation angle at 100% — the whole title tilts as one unit.
6. Random order in the staggered rearrange feels chaotic; sequential feels
   fluid. Pick per-show.

Watch out on mobile: the mobile media query has numbered fallback rules
(`.hero-layer-N { animation-delay: N*0.35s }`) that override desktop
delays via cascade. For multi-state shows, restore the shared delay
(`animation-delay: 0.5s`) inside the mobile block.

## Per-show reference (2026-27 season)

| Show | Canvas | Slide layers | Scale/rotate | Fade layers | Bleed layers |
| --- | --- | --- | --- | --- | --- |
| Outsider | 4800×2000 (D), 2700×4269 (M) | podium, mics, person | — | bg, overlays | mobile all (slide bleed) |
| Arsenic | 4800×2000 (D), 2700×4275 (M) | lace, bottle | arsenic (scale), smoke (scale) | bg, overlays | smoke (desktop 250 top; mobile top + horiz) |
| Hallmarked | 4800×2000 (D), 2700×4275 (M) | stamp (+15° rotate in) | — | mountain, buildings, people, heart, star, bg, overlays | stamp (top-bleed 350 desktop; symm horiz + vert mobile) |
| Dot | 4800×2000 (D), 2700×4275 (M) | woman, pieces | — | bg, overlays | none |
| Urinetown | 4800×2000 (D), 2700×4275 (M) | ground, pipes, toilet, toilet-paper | — | bg, overlays | none |
| Earnest | 4800×2000 (D), 2700×4275 (M) | back-sandwich, front-sandwich | — | bg (desktop only: `scale: 2.1; translate: 0 18%`), overlays | bg PSD needs rasterized layer style |
| PTGW | 4800×2000 (D), 2700×4275 (M) | 5 word layers via multi-state keyframes | grouped rotate+scale at end | bg, overlays | none |

## Extraction script gotchas

- **Numbered filename order = render order.** Higher number renders on top.
  Overlays always last unless deliberately below (e.g. Dot's PSD had them
  below subjects but user meant on top — fixed in numbering).
- **Skip hidden layers.** `layer.visible` and the layer's own `.visible`
  check; watch for hidden reference groups Chris leaves as design notes.
- **Multi-state PSDs (like PTGW):** each state PSD has all 3 group variants
  but only one visible. Extract from the state with the correct group
  visible (usually state 2 for base positions).
- **Skip a `Background copy` layer** if you see it in the extraction log —
  it's often the pre-rasterize backup Chris keeps and it's hidden anyway.

## Deploy note

After any layer file change: local dev works instantly (cache-busted).
Cloudways push requires purging the Full Page Cache from the dashboard
after every deploy — WP-CLI doesn't purge it.
