"""Build the RoleWarden brand kit (logo E "Keyhole Shield") as standalone SVG + PNG.
All text is converted to outlines, so files render identically without fonts installed."""
import os, subprocess, sys
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen

FD = sys.argv[1]  # folder with the fontsource packages extracted
OUT = os.path.dirname(os.path.abspath(__file__))
FONTS = {
    "serif400": f"{FD}/x/package/files/ibm-plex-serif-latin-400-normal.woff",
    "serif500": f"{FD}/x/package/files/ibm-plex-serif-latin-500-normal.woff",
    "sans400": f"{FD}/s/package/files/ibm-plex-sans-latin-400-normal.woff",
    "sans600": f"{FD}/s/package/files/ibm-plex-sans-latin-600-normal.woff",
}
_cache = {}
def font(k):
    if k not in _cache:
        f = TTFont(FONTS[k]); _cache[k] = (f, f.getGlyphSet(), f.getBestCmap(), f["hmtx"], f["head"].unitsPerEm)
    return _cache[k]

def text_width(s, k, size, tracking=0.0):
    f, gs, cmap, hmtx, upm = font(k)
    w = sum(hmtx[cmap[ord(c)]][0] for c in s) * size / upm
    return w + tracking * size * (len(s) - 1)

def text_path(s, k, size, x, y, fill, anchor="start", tracking=0.0, upper=False):
    """Return <path> of string s, baseline at y. tracking in em."""
    f, gs, cmap, hmtx, upm = font(k)
    if upper: s = s.upper()
    sc = size / upm
    w = text_width(s, k, size, tracking)
    if anchor == "middle": x -= w / 2
    elif anchor == "end": x -= w
    d = []
    cx = x
    for c in s:
        g = cmap[ord(c)]
        pen = SVGPathPen(gs)
        gs[g].draw(TransformPen(pen, (sc, 0, 0, -sc, cx, y)))
        d.append(pen.getCommands())
        cx += hmtx[g][0] * sc + tracking * size
    return f'<path fill="{fill}" d="{" ".join(p for p in d if p)}"/>'

# ---------- palette (RoleWarden DS, Console) ----------
C = dict(accent="#1e4ea6", accent_l="#93b2f0", navy="#0f1d3a", navy_r="#18294d", navy_rule="#2c3f66",
         side_ink="#eef2fa", side_muted="#a9b6d1", ink="#17191c", ink_muted="#565b63", ink_faint="#62676f",
         surface="#fafaf9", sunk="#f2f2f0", rule="#e4e5e7", rule_s="#c8cace", white="#ffffff")

# Keyhole Shield (chosen 2026-11-01). Geometry in its own design units, box 120.94 98 358.11 458:
# the shield is three arcs of one 610 circle with top corners filleted at 21; the R's bowl an 89 circle,
# its leg at 45 degrees; the keyhole a 34 circle (optically 36) punched through the R in the shield color.
# The full mark adds the ledger margin (a rule with three row ticks); below 48px the compact mark drops it.
KS_BOX = (120.94, 98.00, 358.11, 458.00)
KS_SHIELD = ("M300.00,554.00 A610.00,610.00 0 0 0 477.05,142.69 A21.00,21.00 0 0 0 461.62,121.80 "
             "A610.00,610.00 0 0 0 138.38,121.80 A21.00,21.00 0 0 0 122.95,142.69 A610.00,610.00 0 0 0 300.00,554.00 Z")
KS_R = ['M214,186 H266 V364 H258 V412 H214 Z', 'M266,186 A89,89 0 0 1 266,364 H256 V186 Z',
        'M264,334 L320.6,334 L398.6,412 L342.0,412 Z']
KS_KEYHOLE = '<circle cx="284" cy="269" r="36"/><path d="M271,288.8 L297,288.8 L306,338 L262,338 Z"/>'
KS_MARGIN = ('<rect x="168" y="98" width="8" height="458"/><rect x="152" y="163.0" width="16" height="6"/>'
             '<rect x="152" y="217.0" width="16" height="6"/><rect x="152" y="271.0" width="16" height="6"/>')
_clip_n = [0]

def mark(variant="full", shield=C["accent"], line=C["accent_l"], letter=C["white"]):
    """Mark in a 64x72 box (shield 72 tall, centred). variant: full (>=48px) keeps the ledger margin;
    medium and small are the compact mark (40px and below)."""
    bx, by, bw, bh = KS_BOX
    s = 72 / bh
    ox = (64 - bw * s) / 2
    _clip_n[0] += 1
    cid = f"ks{_clip_n[0]}"
    p = [f'<g transform="translate({ox:.3f} 0) scale({s:.5f}) translate({-bx} {-by})">',
         f'<defs><clipPath id="{cid}"><path d="{KS_SHIELD}"/></clipPath></defs>',
         f'<path fill="{shield}" d="{KS_SHIELD}"/>']
    if variant == "full":
        p.append(f'<g fill="{line}" clip-path="url(#{cid})">{KS_MARGIN}</g>')
    p.append(f'<g fill="{letter}" clip-path="url(#{cid})">' + "".join(f'<path d="{d}"/>' for d in KS_R) + '</g>')
    p.append(f'<g fill="{shield}">{KS_KEYHOLE}</g></g>')
    return "".join(p)

def place(inner, x, y, scale):
    return f'<g transform="translate({x:.2f} {y:.2f}) scale({scale:.4f})">{inner}</g>'

def svg(w, h, body, bg=None, rx=0):
    b = f'<rect width="{w}" height="{h}" rx="{rx}" fill="{bg}"/>' if bg else ""
    return f'<svg xmlns="http://www.w3.org/2000/svg" width="{w}" height="{h}" viewBox="0 0 {w} {h}">{b}{body}</svg>\n'

def wordmark(x, y_base, size, fill):
    return text_path("RoleWarden", "serif400", size, x, y_base, fill, tracking=-0.015)

def lockup(fill_word, shield, line, letter, mark_h=72):
    """Horizontal lockup, mark 72 units tall; returns (body, w, h)."""
    s = mark_h / 72
    size = 43 * s
    gap = 16.3 * s
    ww = text_width("RoleWarden", "serif400", size, -0.015)
    body = place(mark("full", shield, line, letter), 0, 0, s) + wordmark(64 * s + gap, 51 * s, size, fill_word)
    return body, 64 * s + gap + ww, mark_h

files = {}
def add(name, content): files[name] = content

# ---------- logo files ----------
V = {"light": (C["accent"], C["accent_l"], C["white"], C["ink"]),
     "dark": (C["accent_l"], C["navy"], C["navy"], C["side_ink"]),
     "mono-black": (C["ink"], C["white"], C["white"], C["ink"]),
     "mono-white": (C["white"], C["navy"], C["navy"], C["white"])}
for vn, (sh, ln, lt, wd) in V.items():
    for var in ("full", "medium", "small"):
        add(f"logo/mark-{var}-{vn}.svg", svg(64, 72, mark(var, sh, ln, lt)))
    body, w, h = lockup(wd, sh, ln, lt)
    add(f"logo/lockup-horizontal-{vn}.svg", svg(round(w + 1), h, body))
    # stacked lockup
    sw = text_width("RoleWarden", "serif400", 30, -0.015)
    W = max(sw, 64) + 4
    stacked = place(mark("full", sh, ln, lt), (W - 64) / 2, 0, 1) + text_path("RoleWarden", "serif400", 30, W / 2, 112, wd, "middle", -0.015)
    add(f"logo/lockup-stacked-{vn}.svg", svg(round(W), 122, stacked))
add("logo/wordmark-ink.svg", svg(round(text_width("RoleWarden", "serif400", 43, -0.015) + 1), 56, wordmark(0, 42, 43, C["ink"])))
add("logo/wordmark-white.svg", svg(round(text_width("RoleWarden", "serif400", 43, -0.015) + 1), 56, wordmark(0, 42, 43, C["white"])))

# ---------- app icons ----------
def app_icon(size, maskable=False):
    rx = 0 if maskable else size * 0.22
    mh = size * (0.5 if maskable else 0.6)
    s = mh / 72
    return svg(size, size, place(mark("medium", C["accent_l"], C["navy"], C["navy"]),
                                 (size - 64 * s) / 2, (size - mh) / 2 + size * 0.01, s), C["navy"], rx)
add("icons/app-icon.svg", app_icon(512))
add("icons/app-icon-maskable.svg", app_icon(512, True))

# ---------- shared ledger background pieces ----------
def ledger_rows(x, y, w, rows, size, color_txt, color_num, color_rule, gutter=56, row_h=None, font_k="sans400"):
    row_h = row_h or size * 2.4
    out = []
    for i, r in enumerate(rows):
        yy = y + i * row_h
        out.append(f'<line x1="{x}" y1="{yy + row_h:.1f}" x2="{x + w}" y2="{yy + row_h:.1f}" stroke="{color_rule}" stroke-width="1"/>')
        out.append(text_path(f"{i + 1:02d}", "sans400", size * 0.72, x, yy + row_h * 0.62, color_num))
        out.append(text_path(r, font_k, size, x + gutter, yy + row_h * 0.64, color_txt))
    return "".join(out)

FEATURES = ["Roles and permissions in the database", "Role inheritance and per-user overrides", "Admin panel built on Shield",
            "Activity log and sign-in rules", "Dashboard and command search", "Three themes, light and dark"]

def brand_row(x, y, h, dark=True):
    sh, ln, lt, wd = V["dark"] if dark else V["light"]
    body, w, _ = lockup(wd, sh, ln, lt, h)
    return place(body, x, y, 1), w

# ---------- CodeCanyon ----------
add("codecanyon/thumbnail-80.svg", svg(80, 80, place(mark("full", C["accent_l"], C["navy"], C["navy"]), (80 - 64 * 0.78) / 2, 12, 0.78), C["navy"]))

def inline_preview():
    W, H = 590, 300
    b = []
    lk, lw = brand_row(32, 30, 40)
    b.append(lk)
    b.append(f'<line x1="32" y1="92" x2="{W - 32}" y2="92" stroke="{C["side_ink"]}" stroke-width="2"/>')
    b.append(text_path("Roles and permissions", "serif400", 26, 32, 136, C["side_ink"], tracking=-0.01))
    b.append(text_path("for CodeIgniter 4 Shield", "serif400", 26, 32, 170, C["accent_l"], tracking=-0.01))
    b.append(ledger_rows(32, 188, W - 64, ["Roles and permissions in the database", "Inheritance and per-user overrides", "Admin panel built on Shield"],
                         13, C["side_ink"], C["side_muted"], C["navy_rule"], gutter=34, row_h=30))
    return svg(W, H, "".join(b), C["navy"])
add("codecanyon/inline-preview-590x300.svg", inline_preview())

# ---------- social ----------
def og():
    W, H = 1200, 630
    b = [f'<line x1="96" y1="0" x2="96" y2="{H}" stroke="{C["navy_rule"]}" stroke-width="1"/>']
    lk, _ = brand_row(140, 64, 64)
    b.append(lk)
    b.append(f'<line x1="140" y1="170" x2="{W - 72}" y2="170" stroke="{C["side_ink"]}" stroke-width="3"/>')
    b.append(text_path("Roles and permissions,", "serif400", 52, 140, 252, C["side_ink"], tracking=-0.015))
    b.append(text_path("in the database, for Shield.", "serif400", 52, 140, 318, C["accent_l"], tracking=-0.015))
    rows = FEATURES[:4]
    for i, r in enumerate(rows):
        yy = 372 + i * 52
        b.append(f'<line x1="140" y1="{yy + 52}" x2="{W - 72}" y2="{yy + 52}" stroke="{C["navy_rule"]}"/>')
        b.append(text_path(f"{i + 1:02d}", "sans400", 18, 52, yy + 34, C["side_muted"]))
        b.append(text_path(r, "sans400", 24, 140, yy + 35, C["side_ink"]))
    return svg(W, H, "".join(b), C["navy"])
add("social/open-graph-1200x630.svg", og())

def header_banner():
    W, H = 1500, 500
    b = [f'<line x1="120" y1="0" x2="120" y2="{H}" stroke="{C["navy_rule"]}"/>']
    for i in range(8):
        yy = 40 + i * 56
        b.append(f'<line x1="120" y1="{yy + 56}" x2="{W}" y2="{yy + 56}" stroke="{C["navy_r"]}"/>')
        b.append(text_path(f"{i + 1:02d}", "sans400", 18, 64, yy + 36, C["navy_rule"]))
    lk, _ = brand_row(560, 150, 88)
    b.append(lk)
    b.append(text_path("Roles and permissions for CodeIgniter 4", "sans400", 26, 560, 300, C["side_muted"]))
    return svg(W, H, "".join(b), C["navy"])
add("social/header-1500x500.svg", header_banner())
add("social/avatar-400.svg", svg(400, 400, place(mark("full", C["accent_l"], C["navy"], C["navy"]), (400 - 64 * 3.5) / 2, (400 - 72 * 3.5) / 2 + 4, 3.5), C["navy"]))

def square_post():
    W = H = 1080
    b = [f'<line x1="96" y1="0" x2="96" y2="{H}" stroke="{C["navy_rule"]}"/>']
    lk, _ = brand_row(140, 96, 64)
    b.append(lk)
    b.append(f'<line x1="140" y1="212" x2="{W - 96}" y2="212" stroke="{C["side_ink"]}" stroke-width="3"/>')
    b.append(text_path("Stop editing AuthGroups.php", "serif400", 64, 140, 312, C["side_ink"], tracking=-0.015))
    b.append(text_path("to change who can do what.", "serif400", 64, 140, 392, C["accent_l"], tracking=-0.015))
    for i, r in enumerate(FEATURES):
        yy = 460 + i * 76
        b.append(f'<line x1="140" y1="{yy + 76}" x2="{W - 96}" y2="{yy + 76}" stroke="{C["navy_rule"]}"/>')
        b.append(text_path(f"{i + 1:02d}", "sans400", 22, 46, yy + 48, C["side_muted"]))
        b.append(text_path(r, "sans400", 30, 140, yy + 49, C["side_ink"]))
    return svg(W, H, "".join(b), C["navy"])
add("social/post-1080x1080.svg", square_post())

# ---------- promo video frames ----------
def title_card(W, H, vertical=False):
    b = []
    mh = 200 if vertical else 180
    body, lw, _ = lockup(C["side_ink"], C["accent_l"], C["navy"], C["navy"], mh)
    x = (W - lw) / 2
    y = H / 2 - mh / 2 - (60 if not vertical else 120)
    if vertical:
        # stacked for 9:16
        s = 3.6
        b.append(place(mark("full", C["accent_l"], C["navy"], C["navy"]), (W - 64 * s) / 2, 620, s))
        b.append(text_path("RoleWarden", "serif400", 120, W / 2, 1060, C["side_ink"], "middle", -0.015))
        b.append(text_path("Roles and permissions for Shield", "sans400", 40, W / 2, 1160, C["side_muted"], "middle"))
    else:
        b.append(place(body, x, y, 1))
        b.append(text_path("Roles and permissions for CodeIgniter 4 Shield.", "sans400", 40, W / 2, y + mh + 110, C["side_muted"], "middle"))
    return svg(W, H, "".join(b), C["navy"])
add("promo/title-1920x1080.svg", title_card(1920, 1080))
add("promo/title-1080x1920.svg", title_card(1080, 1920, True))

def end_card(W, H, vertical=False):
    b = []
    if vertical:
        s = 3
        b.append(place(mark("full", C["accent_l"], C["navy"], C["navy"]), (W - 64 * s) / 2, 640, s))
        b.append(text_path("RoleWarden", "serif400", 104, W / 2, 1010, C["side_ink"], "middle", -0.015))
        b.append(f'<line x1="240" y1="1070" x2="{W - 240}" y2="1070" stroke="{C["navy_rule"]}" stroke-width="2"/>')
        b.append(text_path("Available on CodeCanyon", "sans600", 44, W / 2, 1150, C["accent_l"], "middle"))
    else:
        body, lw, _ = lockup(C["side_ink"], C["accent_l"], C["navy"], C["navy"], 140)
        b.append(place(body, (W - lw) / 2, 380, 1))
        b.append(f'<line x1="660" y1="590" x2="1260" y2="590" stroke="{C["navy_rule"]}" stroke-width="2"/>')
        b.append(text_path("Available on CodeCanyon", "sans600", 44, W / 2, 668, C["accent_l"], "middle"))
    return svg(W, H, "".join(b), C["navy"])
add("promo/end-1920x1080.svg", end_card(1920, 1080))
add("promo/end-1080x1920.svg", end_card(1080, 1920, True))

# ---------- write + rasterize ----------
for name, content in files.items():
    p = os.path.join(OUT, "svg", name)
    os.makedirs(os.path.dirname(p), exist_ok=True)
    open(p, "w").write(content)

def png(src, dst, w=None, h=None):
    os.makedirs(os.path.dirname(dst), exist_ok=True)
    cmd = ["rsvg-convert", src, "-o", dst]
    if w: cmd += ["-w", str(w)]
    if h: cmd += ["-h", str(h)]
    subprocess.run(cmd, check=True)

S = lambda n: os.path.join(OUT, "svg", n)
P = lambda n: os.path.join(OUT, "png", n)
for vn in V:
    png(S(f"logo/lockup-horizontal-{vn}.svg"), P(f"logo/lockup-horizontal-{vn}.png"), h=288)
    png(S(f"logo/lockup-stacked-{vn}.svg"), P(f"logo/lockup-stacked-{vn}.png"), h=488)
    png(S(f"logo/mark-full-{vn}.svg"), P(f"logo/mark-{vn}-512.png"), h=512)
for n, w in [("codecanyon/thumbnail-80", 80), ("codecanyon/inline-preview-590x300", 590), ("social/open-graph-1200x630", 1200),
             ("social/header-1500x500", 1500), ("social/avatar-400", 400), ("social/post-1080x1080", 1080),
             ("promo/title-1920x1080", 1920), ("promo/title-1080x1920", 1080), ("promo/end-1920x1080", 1920), ("promo/end-1080x1920", 1080)]:
    png(S(n + ".svg"), P(n + ".png"), w=w)
# web icons
FV = os.path.join(OUT, "favicon")
os.makedirs(FV, exist_ok=True)
for sz in (16, 32, 48):
    png(S("logo/mark-small-light.svg" if sz < 24 else "logo/mark-medium-light.svg"), f"{FV}/_m{sz}.png", h=sz)
    subprocess.run(["convert", f"{FV}/_m{sz}.png", "-background", "none", "-gravity", "center", "-extent", f"{sz}x{sz}", f"{FV}/favicon-{sz}.png"], check=True)
    os.remove(f"{FV}/_m{sz}.png")
subprocess.run(["convert", f"{FV}/favicon-16.png", f"{FV}/favicon-32.png", f"{FV}/favicon-48.png", f"{FV}/favicon.ico"], check=True)
open(f"{FV}/favicon.svg", "w").write(files["logo/mark-medium-light.svg"])
png(S("icons/app-icon.svg"), f"{FV}/apple-touch-icon.png", w=180)
png(S("icons/app-icon.svg"), f"{FV}/icon-192.png", w=192)
png(S("icons/app-icon.svg"), f"{FV}/icon-512.png", w=512)
png(S("icons/app-icon-maskable.svg"), f"{FV}/icon-512-maskable.png", w=512)
print(len(files), "svg files built")
