#!/usr/bin/env python3
"""
The Jos icon set, rendered from the wordmark in images/og-default.png (Jos Yellow, Jos Brown).

    python3 scripts/once/2026-10-10-icons.py

favicon.ico (16/32/48) and icon-192/-512 are new; favicon.png and touchicon.png (what YOOtheme's
Customizer points at) are left alone. Re-run only if the logo changes.
"""
import json
import pathlib

from PIL import Image

IMAGES = pathlib.Path(__file__).resolve().parents[2] / 'app/content/themes/josworld/images'
YELLOW = (255, 247, 148)
WIDTH_SHARE = 0.74  # the wordmark's share of the icon width, as on touchicon.png


def wordmark() -> Image.Image:
    src = Image.open(IMAGES / 'og-default.png').convert('RGB')
    dark = [(x, y) for y in range(src.height) for x in range(src.width) if src.getpixel((x, y))[0] < 150]
    xs, ys = zip(*dark)
    return src.crop((min(xs), min(ys), max(xs) + 1, max(ys) + 1))


def icon(mark: Image.Image, size: int) -> Image.Image:
    width = round(size * WIDTH_SHARE)
    height = round(mark.height * width / mark.width)
    canvas = Image.new('RGB', (size, size), YELLOW)
    canvas.paste(mark.resize((width, height), Image.LANCZOS), ((size - width) // 2, (size - height) // 2))
    return canvas


def main() -> None:
    mark = wordmark()
    icon(mark, 512).save(IMAGES / 'icon-512.png', optimize=True)
    icon(mark, 192).save(IMAGES / 'icon-192.png', optimize=True)
    icon(mark, 256).save(IMAGES / 'favicon.ico', sizes=[(16, 16), (32, 32), (48, 48)])
    manifest = {
        'name': 'Jos',
        'short_name': 'Jos',
        'icons': [
            {'src': 'icon-192.png', 'sizes': '192x192', 'type': 'image/png'},
            {'src': 'icon-512.png', 'sizes': '512x512', 'type': 'image/png'},
        ],
        'theme_color': '#FFF794',
        'background_color': '#FFF794',
        'display': 'browser',
    }
    (IMAGES / 'site.webmanifest').write_text(json.dumps(manifest, indent=2) + '\n')
    print('icon-192.png icon-512.png favicon.ico site.webmanifest')


if __name__ == '__main__':
    main()
