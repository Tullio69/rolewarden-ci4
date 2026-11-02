# RoleWarden brand kit

Official logo since 2026-11-01: **Keyhole Shield** (a shield of three arcs of one circle, a geometric R with a keyhole through its bowl, and the ledger margin on the full mark). It replaces the Warden Shield. The name is always written **RoleWarden**. Every letter is converted to outlines, so no font is needed to render these files.

This folder is kept out of the package (`/docs` is `export-ignore`): it serves the listing, the demo, the promo material and the documentation site.

## Folders

- `svg/logo/` - mark (full, with the ledger margin, 48px and up; compact, without it, 40px and below: the `medium` and `small` files are the compact mark), horizontal and stacked lockups, wordmark. Variants: `light`, `dark`, `mono-black`, `mono-white`.
- `png/logo/` - the same lockups and the mark as high-resolution PNG.
- `favicon/` - `favicon.ico` (16/32/48), `favicon.svg`, `apple-touch-icon.png`, `icon-192.png`, `icon-512.png`, `icon-512-maskable.png`.
- `png/codecanyon/` - thumbnail 80x80, inline preview 590x300.
- `png/social/` - Open Graph 1200x630, header 1500x500, avatar 400x400, post 1080x1080.
- `png/promo/` - title and end frames for the promo video, 16:9 and 9:16.
- `svg/` - editable sources of everything above.

## Colors

| Use | Hex |
| --- | --- |
| Shield on light / accent | `#1e4ea6` |
| Shield on dark / margin on light | `#93b2f0` |
| Navy ground (side-surface) | `#0f1d3a` |
| Ink | `#17191c` |
| Light text on navy | `#eef2fa` |

Type: IBM Plex Serif (wordmark, headlines), IBM Plex Sans (body).

## Rebuilding

`build.py` regenerates the whole kit (texts, sizes, colors live there). It needs fontTools, `rsvg-convert`, ImageMagick and the IBM Plex fontsource packages, so it runs in a throwaway container:

```sh
docker run --rm -v "$PWD/docs/brand:/kit" python:3.13-slim sh -c '
  apt-get update -qq && apt-get install -y -qq librsvg2-bin imagemagick curl >/dev/null
  pip install -q fonttools && mkdir -p /f/x /f/s
  curl -sL https://registry.npmjs.org/@fontsource/ibm-plex-serif/-/ibm-plex-serif-5.3.0.tgz | tar xz -C /f/x
  curl -sL https://registry.npmjs.org/@fontsource/ibm-plex-sans/-/ibm-plex-sans-5.3.0.tgz | tar xz -C /f/s
  cd /kit && python build.py /f'
```

Every claim in the kit's texts must be covered by `docs/codecanyon/inventario.md`.

## In the panel

Not yet: the panel shows the name as text. The mark in the sidebar, the favicon and the white-label options (`$brandName`, `$brandMark`) are planned for 1.2 (`docs/BRIEF-v1.2.md`).
