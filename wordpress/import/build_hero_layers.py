"""
Process a folder of poster layers into a hero-layers package.
Resizes, renames, optimizes, and creates a flat composite jpg.

Usage:
  python build_hero_layers.py <source_folder> <show_slug>

Example:
  python build_hero_layers.py "G:/My Drive/Marketing/2627 Posters/1 The Outsider/Hero Animation" the-outsider
"""
import os, sys, shutil
from PIL import Image

MAX_WIDTH = 2400   # cap any layer at this; preserves quality at retina sizes
JPG_QUALITY = 85

# Map "source filename keyword" -> our convention output name
# Order matters: bottom-most listed first in the dict for the composite stacking order.
LAYER_MAP = [
    # Order = back -> front (1-bg sits at the back, 5-front-mics in front)
    ('background', '1-bg.png'),
    ('man',        '2-person.png'),
    ('back mics',  '3-back-mics.png'),
    ('podium',     '4-podium.png'),
    ('front mics', '5-front-mics.png'),
]

def find_source(folder, keyword):
    """Find a file in folder whose name (case-insensitive) contains keyword."""
    for fn in os.listdir(folder):
        if not fn.lower().endswith('.png'): continue
        if keyword.lower() in fn.lower():
            return os.path.join(folder, fn)
    return None

def resize_to_max_width(img, max_w):
    if img.width <= max_w:
        return img
    ratio = max_w / img.width
    new_size = (max_w, int(img.height * ratio))
    return img.resize(new_size, Image.LANCZOS)

def fit_layer_to_canvas(layer, canvas_w, canvas_h, fit='width', anchor='bottom-center'):
    """Scale a layer and position by anchor within a canvas-sized transparent image.

    fit='width'  -> scale layer width to canvas_w (good when portrait layers from a
                    full-poster export need to fit a landscape hero crop; the figure
                    ends up at the bottom and the title area extends above the canvas).
    fit='height' -> scale layer height to canvas_h.
    """
    if fit == 'width':
        scale = canvas_w / layer.width
    else:
        scale = canvas_h / layer.height
    new_w = max(1, int(layer.width * scale))
    new_h = max(1, int(layer.height * scale))
    scaled = layer.resize((new_w, new_h), Image.LANCZOS)

    canvas = Image.new('RGBA', (canvas_w, canvas_h), (0, 0, 0, 0))
    # Bottom-center anchor (extends above canvas top if needed; clipped by paste)
    x = (canvas_w - new_w) // 2
    y = canvas_h - new_h
    canvas.paste(scaled, (x, y), scaled if scaled.mode == 'RGBA' else None)
    return canvas

def main():
    if len(sys.argv) < 3:
        print(__doc__); sys.exit(1)
    src_folder = sys.argv[1]
    slug = sys.argv[2]

    if not os.path.isdir(src_folder):
        print(f"Source folder not found: {src_folder}"); sys.exit(2)

    dest_folder = f"C:/Users/blake/Local Sites/tlt/app/public/wp-content/uploads/hero-layers/{slug}"
    os.makedirs(dest_folder, exist_ok=True)
    print(f"Source: {src_folder}")
    print(f"Dest:   {dest_folder}\n")

    # First pass: open background to determine canvas dimensions
    bg_src = find_source(src_folder, 'background')
    if not bg_src:
        print("No background.png found — cannot determine canvas size.")
        sys.exit(3)
    print("Loading background to set canvas dimensions...")
    with Image.open(bg_src) as bg_orig:
        bg_orig = bg_orig.convert('RGBA')
        bg_orig = resize_to_max_width(bg_orig, MAX_WIDTH)
        canvas_w, canvas_h = bg_orig.size
        print(f"  Canvas: {canvas_w}x{canvas_h}\n")
        bg_layer = bg_orig.copy()

    processed_layers = []
    # Background first (already correct dimensions)
    bg_out = os.path.join(dest_folder, '1-bg.png')
    bg_layer.save(bg_out, 'PNG', optimize=True)
    print(f"  1-bg.png  -> {canvas_w}x{canvas_h}, {os.path.getsize(bg_out)/1024:.0f} KB")
    processed_layers.append( ('1-bg.png', bg_layer) )

    # Other layers: scale to canvas height, anchor to bottom-center
    for keyword, out_name in LAYER_MAP[1:]:  # skip 'background', already done
        src = find_source(src_folder, keyword)
        if not src:
            print(f"  [skip] no match for '{keyword}'")
            continue
        print(f"  Processing {os.path.basename(src)} -> {out_name}")
        img = Image.open(src).convert('RGBA')
        # Don't pre-resize — fit_layer_to_canvas does the scaling
        positioned = fit_layer_to_canvas(img, canvas_w, canvas_h)
        dest = os.path.join(dest_folder, out_name)
        positioned.save(dest, 'PNG', optimize=True)
        size_kb = os.path.getsize(dest) / 1024
        print(f"    -> {canvas_w}x{canvas_h}, {size_kb:.0f} KB")
        processed_layers.append( (out_name, positioned) )

    # Build composite jpg
    if processed_layers:
        print("\nBuilding composite.jpg (mobile fallback)...")
        base = processed_layers[0][1].copy().convert('RGBA')
        for _, layer in processed_layers[1:]:
            base.alpha_composite(layer)
        composite_rgb = Image.new('RGB', base.size, (255, 255, 255))
        composite_rgb.paste(base, mask=base.split()[3])
        composite_path = os.path.join(dest_folder, 'composite.jpg')
        composite_rgb.save(composite_path, 'JPEG', quality=JPG_QUALITY, optimize=True)
        size_kb = os.path.getsize(composite_path) / 1024
        print(f"  -> {composite_rgb.width}x{composite_rgb.height}, {size_kb:.0f} KB")

    # Summary
    print("\nDone. Files in destination:")
    for fn in sorted(os.listdir(dest_folder)):
        size_kb = os.path.getsize(os.path.join(dest_folder, fn)) / 1024
        print(f"  {fn}  ({size_kb:.0f} KB)")

if __name__ == '__main__':
    main()
