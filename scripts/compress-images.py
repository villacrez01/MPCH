"""Compress OTI images using Pillow (resize + quality)."""
import sys, pathlib

try:
    from PIL import Image
except ImportError:
    sys.exit(1)

BASE = pathlib.Path(__file__).parent.parent / "public/assets/img"
FILES = ["bg-municipal.jpg", "OTI.jpeg"]
MAX_W  = 1920
QUALITY = 70

for name in FILES:
    src = BASE / name
    if not src.exists():
        print(f"MISSING {name}")
        continue

    old = src.stat().st_size
    img = Image.open(src)
    w, h = img.size

    try:
        exif = img._getexif() or {}
        orient = exif.get(0x11287, 1)
        if orient == 3: img = img.rotate(180, expand=True)
        elif orient == 6: img = img.rotate(-90, expand=True)
        elif orient == 8: img = img.rotate(90, expand=True)
    except Exception:
        pass

    if w > MAX_W:
        ratio = MAX_W / w
        img = img.resize((MAX_W, int(h * ratio)), Image.LANCZOS)

    img.save(src, "JPEG", quality=QUALITY, optimize=True, progressive=True)
    new = src.stat().st_size
    saved = old - new
    print(f"{name:25} {old//1024:>7} KB -> {new//1024:>7} KB  (saved {saved//1024} KB)")
