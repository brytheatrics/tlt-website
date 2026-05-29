"""
Vectorize the Harbor Lights logo (300x450 PNG from anthonys.com) to a clean
SVG using potrace. The logo is a solid blue silhouette + crisp type, so
potrace produces clean curves.

Outputs wordpress/themes/tlt/assets/harbor-lights.svg
"""
import os
from PIL import Image
import numpy as np
import potrace

SRC = r"C:/temp/hl-orig.png"
DEST_SVG = r"C:/Users/blake/dev/TLT_Website/wordpress/themes/tlt/assets/harbor-lights.svg"

# Upscale the 300x450 to ~1200x1800 with nearest-neighbor + light blur so
# potrace gets a richer source to trace.
im = Image.open(SRC).convert('RGBA')
# Composite onto white in case alpha is non-trivial
bg = Image.new('RGBA', im.size, (255, 255, 255, 255))
im = Image.alpha_composite(bg, im).convert('RGB')

# Threshold: anything not near-white becomes black
arr = np.array(im)
gray = arr.mean(axis=2)
# The logo is dark blue (#1f55a5 ish), background is white (255).
# Threshold at 200 keeps the blue intact.
mask = gray > 200  # potracer treats True as background — invert so bird+text get traced
print(f"source {im.size} -> mask sum {mask.sum()} pixels black")

# Potrace
bitmap = potrace.Bitmap(mask)
# turdsize: remove specks smaller than this many px
path = bitmap.trace(turdsize=4, alphamax=1.0, opttolerance=0.2)

W, H = im.size
# Build SVG paths
parts = []
def pt(p):
    return p.x, p.y

for curve in path:
    sx, sy = pt(curve.start_point)
    d = [f"M{sx:.2f} {sy:.2f}"]
    for seg in curve.segments:
        if seg.is_corner:
            cx, cy = pt(seg.c)
            ex, ey = pt(seg.end_point)
            d.append(f"L{cx:.2f} {cy:.2f}L{ex:.2f} {ey:.2f}")
        else:
            c1x, c1y = pt(seg.c1)
            c2x, c2y = pt(seg.c2)
            ex, ey = pt(seg.end_point)
            d.append(f"C{c1x:.2f} {c1y:.2f} {c2x:.2f} {c2y:.2f} {ex:.2f} {ey:.2f}")
    d.append("Z")
    parts.append("".join(d))

svg = (
    f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" '
    f'fill="#1f55a5" fill-rule="evenodd" role="img" aria-label="Harbor Lights">'
    f'<path d="{" ".join(parts)}"/>'
    f'</svg>'
)

os.makedirs(os.path.dirname(DEST_SVG), exist_ok=True)
with open(DEST_SVG, 'w', encoding='utf-8') as f:
    f.write(svg)

print(f"Wrote {DEST_SVG} ({len(svg)} bytes, {len(parts)} subpaths)")
