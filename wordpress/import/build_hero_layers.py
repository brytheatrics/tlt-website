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
    ('background', '1-bg.png'),
    ('back mics',  '2-back-mics.png'),
    ('podium',     '3-podium.png'),
    ('man',        '4-person.png'),
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

def main():
    if len(sys.argv) < 3:
        print(__doc__); sys.exit(1)
    src_folder = sys.argv[1]
    slug = sys.argv[2]

    if not os.path.isdir(src_folder):
        print(f"Source folder not found: {src_folder}"); sys.exit(2)

    dest_folder = os.path.normpath(
        os.path.join(os.path.dirname(os.path.abspath(__file__)),
                     '..', '..',
                     '..', '..',  # go up to ~/dev/TLT_Website then back out
                     'Local Sites', 'tlt', 'app', 'public', 'wp-content',
                     'uploads', 'hero-layers', slug)
    )
    # Simpler: use absolute path
    dest_folder = f"C:/Users/blake/Local Sites/tlt/app/public/wp-content/uploads/hero-layers/{slug}"
    os.makedirs(dest_folder, exist_ok=True)
    print(f"Source: {src_folder}")
    print(f"Dest:   {dest_folder}\n")

    # Process each layer
    processed_layers = []
    for keyword, out_name in LAYER_MAP:
        src = find_source(src_folder, keyword)
        if not src:
            print(f"  [skip] no match for '{keyword}'")
            continue
        print(f"  Processing {os.path.basename(src)} -> {out_name}")
        img = Image.open(src).convert('RGBA')
        img = resize_to_max_width(img, MAX_WIDTH)
        dest = os.path.join(dest_folder, out_name)
        img.save(dest, 'PNG', optimize=True)
        size_kb = os.path.getsize(dest) / 1024
        print(f"    -> {img.width}×{img.height}, {size_kb:.0f} KB")
        processed_layers.append( (out_name, img) )

    # Build composite jpg by stacking layers in order
    if processed_layers:
        print("\nBuilding composite.jpg (mobile fallback)...")
        # Use the first (background) layer's dimensions as the composite canvas
        base = processed_layers[0][1].copy().convert('RGBA')
        for _, layer in processed_layers[1:]:
            # Resize layer to match base canvas if needed (they should already match)
            if layer.size != base.size:
                layer = layer.resize(base.size, Image.LANCZOS)
            base.alpha_composite(layer)
        # Convert to RGB and save as JPG
        composite_rgb = Image.new('RGB', base.size, (255, 255, 255))
        composite_rgb.paste(base, mask=base.split()[3])  # use alpha channel as mask
        composite_path = os.path.join(dest_folder, 'composite.jpg')
        composite_rgb.save(composite_path, 'JPEG', quality=JPG_QUALITY, optimize=True)
        size_kb = os.path.getsize(composite_path) / 1024
        print(f"  -> {composite_rgb.width}×{composite_rgb.height}, {size_kb:.0f} KB")

    # Summary
    print("\nDone. Files in destination:")
    for fn in sorted(os.listdir(dest_folder)):
        size_kb = os.path.getsize(os.path.join(dest_folder, fn)) / 1024
        print(f"  {fn}  ({size_kb:.0f} KB)")

if __name__ == '__main__':
    main()
