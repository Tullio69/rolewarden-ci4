"""V5 token audit of a served stylesheet (Collaudatore ad Hoc). Black box: input is the CSS exactly as
the panel serves it over HTTP; nothing from resources/ or the views is read.
Usage: python verify-v5.css.py <css-file> [dump]
Prints one JSON document: the rule list (dump) or the audit (default).

Classification (from SPEC "Design system" and documentation/design-guide.html):
- token blocks: rules whose declarations are only custom properties (--*) -> where values live;
- component rules: every other rule. Their values must come from var(--semantic-token);
  primitives (--p-*) must never be read there;
- raw values inside component rules are reported by token group: colour, spacing, typography,
  radius, border width, shadow, motion. Keywords (0, none, transparent, currentColor, inherit,
  auto, 100%) are not values of a theme. The declared exceptions are widths of individual table
  columns and icon geometry (width/height/min/max/flex-basis on column or svg selectors).
"""
import json
import re
import sys

src = open(sys.argv[1], encoding='utf-8').read()
src = re.sub(r'/\*.*?\*/', '', src, flags=re.S)


def parse(text, ctx):
    """Yield (context list, selector, declarations string) for every style rule."""
    i, n = 0, len(text)
    while i < n:
        j = text.find('{', i)
        if j < 0:
            break
        # statement-level at-rules without a block (e.g. '@layer theme,base;')
        head = text[i:j]
        while ';' in head and head.strip().startswith('@'):
            k = text.find(';', i)
            i = k + 1
            head = text[i:j]
        head = head.strip()
        depth, k = 1, j + 1
        while k < n and depth:
            c = text[k]
            if c == '{':
                depth += 1
            elif c == '}':
                depth -= 1
            elif c in '"\'':
                q = c
                k += 1
                while k < n and text[k] != q:
                    k += 2 if text[k] == '\\' else 1
            k += 1
        body = text[j + 1:k - 1]
        if head.startswith('@') and not head.startswith('@font-face') and not head.startswith('@property'):
            yield from parse(body, ctx + [head])
        elif '{' in body:  # CSS nesting
            decls = re.sub(r'[^;{}]*\{[^{}]*\}', '', body)
            yield ctx, head, decls
            for c2, s2, d2 in parse(body, ctx):
                yield c2, s2.replace('&', head), d2
        else:
            yield ctx, head, body
        i = k


def decls(body):
    out, buf, depth, q = [], '', 0, None
    for c in body:
        if q:
            buf += c
            if c == q:
                q = None
            continue
        if c in '"\'':
            q = c
        elif c == '(':
            depth += 1
        elif c == ')':
            depth -= 1
        if c == ';' and depth == 0:
            out.append(buf)
            buf = ''
        else:
            buf += c
    out.append(buf)
    res = []
    for d in out:
        if ':' in d:
            p, v = d.split(':', 1)
            if p.strip():
                res.append((p.strip(), v.strip()))
    return res


rules = [(c, s, decls(b)) for c, s, b in parse(src, [])]
if len(sys.argv) > 2 and sys.argv[2] == 'dump':
    print(json.dumps([{'ctx': c, 'sel': s, 'decls': d} for c, s, d in rules], indent=1))
    sys.exit(0)

COLOR = re.compile(r'#[0-9a-fA-F]{3,8}\b|\b(?:rgba?|hsla?|oklch|oklab|lab|lch|color-mix|hwb)\(|\b(?:white|black|red|green|blue|gray|grey|silver|navy|orange|yellow|purple)\b')
LENGTH = re.compile(r'(?<![\w.-])(-?\d*\.?\d+)(px|rem|em|ch|vh|vw|pt)\b')
TIME = re.compile(r'(?<![\w.-])\d*\.?\d+m?s\b')
GROUP = [
    ('colour', re.compile(r'^(color|background|background-color|border(-top|-right|-bottom|-left)?(-color)?|outline(-color)?|fill|stroke|box-shadow|text-decoration(-color)?|caret-color|accent-color|column-rule(-color)?|border-(block|inline)(-start|-end)?(-color)?)$')),
    ('spacing', re.compile(r'^(margin|padding|gap|row-gap|column-gap|inset|top|right|bottom|left)(-.*)?$')),
    ('typography', re.compile(r'^(font|font-size|font-weight|line-height|font-family)$')),
    ('radius', re.compile(r'^border(-(top|bottom|start|end)-(left|right|start|end))?-radius$')),
    ('border width', re.compile(r'^(border(-top|-right|-bottom|-left|-block|-inline)?(-start|-end)?(-width)?|outline(-width)?)$')),
    ('shadow', re.compile(r'^(box-shadow|text-shadow)$')),
    ('motion', re.compile(r'^(transition|transition-duration|transition-timing-function|animation|animation-duration)$')),
]
EXEMPT_PROPS = re.compile(r'^(width|min-width|max-width|height|min-height|max-height|flex-basis|flex|grid-template-columns|inline-size|block-size|stroke-width|vertical-align|outline-offset|letter-spacing|text-underline-offset|opacity|z-index|transform|content|cursor)$')

token_defs = {}   # name -> list of (ctx, selector, value)
uses = {}         # name -> count in component rules
prim_reads = []   # component rules reading --p-*
token_prim_reads = []
raw = []          # (group, ctx, selector, prop, value)
info = []         # raw values on properties outside the token groups
for c, s, ds in rules:
    custom = [d for d in ds if d[0].startswith('--')]
    normal = [d for d in ds if not d[0].startswith('--')]
    for p, v in custom:
        token_defs.setdefault(p, []).append((' '.join(c), s, v))
    for p, v in normal:
        for name in re.findall(r'var\((--[\w-]+)', v):
            uses[name] = uses.get(name, 0) + 1
            if name.startswith('--p-'):
                prim_reads.append((' '.join(c), s, p, v))
        if s.startswith('@') or ' '.join(c).startswith('@font-face'):
            continue
        stripped = re.sub(r'var\([^()]*(\([^()]*\)[^()]*)*\)', '', v)
        stripped = re.sub(r'url\([^)]*\)', '', stripped)
        hits = []
        if COLOR.search(stripped) and not re.fullmatch(r'\s*(#0000|transparent)\s*', stripped):
            hits.append('colour')
        lens = [m for m in LENGTH.finditer(stripped) if float(m.group(1)) != 0]
        if TIME.search(stripped):
            hits.append('time')
        if lens:
            hits.append('length')
        if re.search(r'\b(cubic-bezier|ease|ease-in|ease-out|linear)\b', stripped) and 'transition' in p:
            hits.append('easing')
        if re.fullmatch(r'\s*\d{3}\s*', stripped) and p == 'font-weight':
            hits.append('weight')
        if not hits:
            continue
        grp = next((g for g, rx in GROUP if rx.match(p)), None)
        rec = {'ctx': ' '.join(c), 'sel': s, 'prop': p, 'value': v, 'kind': hits}
        if grp and not EXEMPT_PROPS.match(p):
            # border shorthand: classify by what is raw (a colour or a width)
            if grp == 'colour' and 'colour' not in hits:
                grp = 'border width' if p.startswith(('border', 'outline')) else grp
                if grp == 'colour':
                    info.append(rec)
                    continue
            rec['group'] = grp
            raw.append(rec)
        else:
            info.append(rec)
# primitives read inside token blocks by semantic tokens are expected; record which tokens read them
for name, defs in token_defs.items():
    for ctx, s, v in defs:
        for ref in re.findall(r'var\((--[\w-]+)', v):
            if ref.startswith('--p-') and not (name.startswith('--l-') or name.startswith('--d-') or name.startswith('--p-')):
                token_prim_reads.append((name, s, v))
print(json.dumps({
    'rules': len(rules),
    'token_names': sorted(token_defs),
    'token_defs': {k: v for k, v in token_defs.items()},
    'uses': uses,
    'primitive_reads_in_components': prim_reads,
    'primitive_reads_by_non_mode_tokens': token_prim_reads,
    'raw_in_token_groups': raw,
    'raw_outside_token_groups': info,
}, indent=1))
