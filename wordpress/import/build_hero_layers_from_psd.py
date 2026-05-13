"""
Extract layers from a hero PSD and produce a hero-layers package.

Each top-level visible layer is composited onto a transparent canvas at the
PSD's full dimensions, so all output PNGs share an identical coordinate
system and stack with no scaling required.

The PSD's own layer order drives stacking — the bottom layer in Photoshop's
Layers panel becomes 1-*.png (back of the hero), the top becomes the last
N-*.png (front of the hero). Rearrange in Photoshop, re-run the script.

Usage:
  python build_hero_layers_from_psd.py <psd_path> <show_slug>

Example:
  python build_hero_layers_from_psd.py "G:/My Drive/Marketing/2627 Posters/1 The Outsider/Hero Animation/The Outsider Hero.psd" the-outsider
"""
import os, re, sys
from PIL import Image
from psd_tools import PSDImage

MAX_WIDTH = 2400        # downscale cap for web delivery
JPG_QUALITY = 85

def slugify(name):
    """Turn a layer name like 'Back Mics' into 'back-mics' for filenames."""
    s = name.lower().strip()
    s = re.sub(r'[^a-z0-9]+', '-', s)
    s = s.strip('-')
    # Friendly shortenings for common names
    if s == 'background': s = 'bg'
    if s == 'man':        s = 'person'
    return s or 'layer'

def layer_on_canvas(layer, canvas_w, canvas_h):
    """Render a layer's pixels onto a transparent canvas at PSD dimensions.
    psd-tools' layer.composite() returns just the layer's bbox area; we paste
    it into the full canvas using the layer's offset so positions are preserved."""
    canvas = Image.new('RGBA', (canvas_w, canvas_h), (0, 0, 0, 0))
    rendered = layer.composite()  # returns PIL Image sized to bbox
    if rendered is None:
        return canvas
    if rendered.mode != 'RGBA':
        rendered = rendered.convert('RGBA')
    canvas.paste(rendered, (layer.left, layer.top), rendered)
    return canvas

def downscale(img, max_w):
    if img.width <= max_w:
        return img
    ratio = max_w / img.width
    return img.resize((max_w, int(img.height * ratio)), Image.LANCZOS)

def main():
    if len(sys.argv) < 3:
        print(__doc__); sys.exit(1)
    psd_path = sys.argv[1]
    slug = sys.argv[2]

    if not os.path.isfile(psd_path):
        print(f"PSD not found: {psd_path}"); sys.exit(2)

    dest_folder = f"C:/Users/blake/Local Sites/tlt/app/public/wp-content/uploads/hero-layers/{slug}"
    os.makedirs(dest_folder, exist_ok=True)
    print(f"PSD:  {psd_path}")
    print(f"Dest: {dest_folder}\n")

    print("Opening PSD...")
    psd = PSDImage.open(psd_path)
    canvas_w, canvas_h = psd.width, psd.height
    print(f"  Canvas: {canvas_w}x{canvas_h}\n")

    # psd-tools iterates top-level layers in PSD stacking order
    # (bottom of Layers panel first = back of hero). That's exactly what we want.
    visible = [l for l in psd if l.visible and not l.is_group()]
    if not visible:
        print("No visible top-level layers found."); sys.exit(3)

    processed_layers = []
    for i, layer in enumerate(visible, start=1):
        out_name = f"{i}-{slugify(layer.name)}.png"
        print(f"  Layer {layer.name!r} -> {out_name}")
        full = layer_on_canvas(layer, canvas_w, canvas_h)
        scaled = downscale(full, MAX_WIDTH)
        dest = os.path.join(dest_folder, out_name)
        scaled.save(dest, 'PNG', optimize=True)
        size_kb = os.path.getsize(dest) / 1024
        print(f"    -> {scaled.width}x{scaled.height}, {size_kb:.0f} KB")
        processed_layers.append((out_name, scaled))

    if not processed_layers:
        print("No layers exported — nothing to composite."); sys.exit(3)

    # Composite jpg (mobile fallback)
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

    print("\nDone. Files in destination:")
    for fn in sorted(os.listdir(dest_folder)):
        size_kb = os.path.getsize(os.path.join(dest_folder, fn)) / 1024
        print(f"  {fn}  ({size_kb:.0f} KB)")

if __name__ == '__main__':
    main()
