<?php
// U1 phase STYLE (point 5): Paper, Ink and Charcoal from the customizer (appearance.update), saved in the theme file;
// wrong value refused; a theme file without a style is Paper; app/Config/RoleWarden/theme.json wins; the three styles in
// the three themes, light and dark, measured on the colours Chrome computes: only token values, active item at least
// 3:1 on the sidebar, texts at least 4.5:1. Token values against docs/design-system (tokens.css for Console, the table
// of README.md for Clarity and Contrast).
require_once __DIR__ . '/verify-u1.lib.php';
$GLOBALS['U1DIR'] = dirname(__DIR__ . '/verify-u1.lib.php');
echo "\n== STYLE: sidebar styles\n";
serve8($APP, 'STYLE');
$S0 = count($GLOBALS['SEEN']);
$adm = c8('s-admin'); login8($adm, 'admin@v8b.test', OPW);
$own = c8('s-owner'); login8($own, 'owner@v8b.test', OPW);
$pl = c8('s-plain'); login8($pl, 'plain@v8b.test', OPW);
$so = side(G($own, 'rolewarden/users', 'style owner rail?'));
if ($so['rail'] && $so['toggle']) { P($own, $so['toggle']['action'], $so['toggle']['fields'], 'style expand owner'); info('owner expanded for the measurements'); }
function style_of(Client $c, string $tag): ?string { return side(G($c, 'rolewarden/profile', $tag))['style']; }
function sidebar_in(?array $j): ?string
{
    if (! is_array($j)) return null;
    $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($j), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $k => $v) if (is_string($k) && stripos($k, 'sidebar') !== false && ! is_array($v)) return (string) $v;
    return null;
}
check('S-0', 'No theme file yet: the sidebar is Paper', theme_json() === null && style_of($own, 'style start') === 'paper', json_encode(theme_json()));
foreach (['ink', 'charcoal'] as $st) {
    $h = theme_save($adm, ['base' => 'console', 'sidebar' => $st], "style save $st");
    $code = $adm->status;
    $j = theme_json();
    info("saved $st: HTTP $code; theme file " . json_encode($j));
    check("S-$st", "Customizer saves $st in the theme file and every user's sidebar takes it", in_array($code, [302, 303], true) && sidebar_in($j) === $st && style_of($own, "style $st owner") === $st && style_of($pl, "style $st plain") === $st, json_encode([$code, sidebar_in($j)]));
}
$h = theme_save($adm, ['base' => 'console', 'sidebar' => ''], 'style save paper');
$j = theme_json();
check('S-paper', 'Customizer saves Paper (the first choice): the file holds no other style and the sidebar is Paper', in_array($adm->status, [302, 303], true) && in_array(sidebar_in($j), [null, '', 'paper'], true) && style_of($own, 'style paper owner') === 'paper', json_encode($j));
theme_save($adm, ['base' => 'console', 'sidebar' => 'ink'], 'style ink again');
$before = (string) @file_get_contents(theme_file_u1());
foreach (['neon', 'Ink', '"><script>alert(1)</script>', 'paper '] as $i => $bad) {
    $hb = theme_save($adm, ['base' => 'console', 'sidebar' => $bad], "style wrong $i");
    $code = $adm->status;
    if (in_array($code, [302, 303], true)) $hb = G($adm, $adm->location, "style wrong $i follow");
    $same = (string) @file_get_contents(theme_file_u1()) === $before;
    check("S-bad$i", 'A style outside Paper/Ink/Charcoal (' . json_encode($bad) . ') is refused: file unchanged, no error page, never printed raw', $same && $code < 500 && ! str_contains($hb, '<script>alert(1)') && style_of($own, "style after wrong $i") === 'ink', json_encode([$code, $same]));
}
[$sv, $ok] = u1login('s-settings', 'nav-settings-view@example.com');
$hs = G($sv, 'rolewarden/appearance', 'appearance without permission');
$stA = $sv->status;
P($sv, 'rolewarden/appearance', ['csrf_test_name' => csrf_of(G($sv, 'rolewarden/profile', 'token')), 'base' => 'console', 'sidebar' => 'charcoal'], 'appearance POST without permission');
check('S-perm', 'Without appearance.update the customizer neither opens nor saves', $stA !== 200 && (string) @file_get_contents(theme_file_u1()) === $before, json_encode([$stA, $sv->status]));
theme_save($adm, ['base' => 'console', 'sidebar' => 'charcoal'], 'style no csrf', false);
check('S-csrf', 'A save without the CSRF token is refused (403, or the production redirect), file unchanged', in_array($adm->status, CSRF_REFUSED, true) && (string) @file_get_contents(theme_file_u1()) === $before, (string) $adm->status);
// a theme file without the style (as an updated 1.0 installation has it)
$j = json_decode($before, true);
$strip = function (array $a) use (&$strip) { foreach ($a as $k => $v) { if (is_string($k) && stripos($k, 'sidebar') !== false) unset($a[$k]); elseif (is_array($v)) $a[$k] = $strip($v); } return $a; };
file_put_contents(theme_file_u1(), json_encode($strip($j), JSON_PRETTY_PRINT));
check('S-nostyle', 'A theme file without a sidebar style gives Paper', style_of($own, 'style file without key') === 'paper', (string) file_get_contents(theme_file_u1()));
file_put_contents(theme_file_u1(), $before);
// the application's own theme file wins
@mkdir("$APP/app/Config/RoleWarden", 0777, true);
$appj = $j; $set = function (array $a, string $v) use (&$set) { foreach ($a as $k => $x) { if (is_string($k) && stripos($k, 'sidebar') !== false && ! is_array($x)) $a[$k] = $v; elseif (is_array($x)) $a[$k] = $set($x, $v); } return $a; };
file_put_contents("$APP/app/Config/RoleWarden/theme.json", json_encode($set($appj, 'charcoal'), JSON_PRETTY_PRINT));
$s1 = style_of($own, 'style app file');
$hA = G($adm, 'rolewarden/appearance', 'appearance with app file');
info('appearance with app/Config/RoleWarden/theme.json: sidebar select present=' . json_encode(str_contains($hA, 'name="sidebar"')));
check('S-app', 'app/Config/RoleWarden/theme.json with Charcoal wins over writable/ (Ink)', $s1 === 'charcoal', (string) $s1);
unlink("$APP/app/Config/RoleWarden/theme.json"); @rmdir("$APP/app/Config/RoleWarden");
check('S-app2', 'Without it, back to the file in writable/ (Ink)', style_of($own, 'style app file removed') === 'ink', '');

// ---- the browser: 3 themes x 3 styles x light/dark
// expected token values: Console from docs/design-system/tokens.css, Clarity and Contrast from the README table
$TOK = ['console' => ['light' => ['side-surface' => '#0f1d3a', 'side-raised' => '#18294d', 'side-rule' => '#2c3f66', 'side-ink' => '#eef2fa', 'side-muted' => '#a9b6d1', 'side-accent' => '#93b2f0', 'on-side-accent' => '#0f1a33', 'charcoal-surface' => '#1c1d20', 'charcoal-raised' => '#232428', 'charcoal-rule' => '#43464c', 'charcoal-ink' => '#ebebe8', 'charcoal-muted' => '#aeb2b9'],
    'dark' => ['side-surface' => '#0b1630', 'side-raised' => '#142344', 'side-rule' => '#283a5e', 'side-ink' => '#eef2fa', 'side-muted' => '#a9b6d1', 'side-accent' => '#93b2f0', 'on-side-accent' => '#0f1a33', 'charcoal-surface' => '#1c1d20', 'charcoal-raised' => '#232428', 'charcoal-rule' => '#43464c', 'charcoal-ink' => '#ebebe8', 'charcoal-muted' => '#aeb2b9']]];
$rows = ['side-surface' => ['#1a1b4b', '#14153a', '#072a66', '#051d47'], 'side-raised' => ['#262a52', '#1f2247', '#0b3d91', '#072a66'], 'side-rule' => ['#3a3f75', '#33376a', '#dde7f7', '#dde7f7'],
    'side-ink' => ['#eef0f5', '#eef0f5', '#ffffff', '#ffffff'], 'side-muted' => ['#b4bad8', '#b4bad8', '#dde7f7', '#dde7f7'], 'side-accent' => ['#a5b4fc', '#a5b4fc', '#facc15', '#facc15'],
    'on-side-accent' => ['#1a1b4b', '#1a1b4b', '#0a0a0b', '#0a0a0b'], 'charcoal-surface' => ['#14161c', '#14161c', '#0a0a0b', '#0a0a0b'], 'charcoal-raised' => ['#1b1e26', '#1b1e26', '#161618', '#161618'],
    'charcoal-rule' => ['#3a3f4c', '#3a3f4c', '#a1a1aa', '#a1a1aa'], 'charcoal-ink' => ['#eef0f5', '#eef0f5', '#ffffff', '#ffffff'], 'charcoal-muted' => ['#b4bac6', '#b4bac6', '#e4e4e7', '#e4e4e7']];
foreach ($rows as $t => [$cl, $cd, $xl, $xd]) { $TOK['clarity']['light'][$t] = $cl; $TOK['clarity']['dark'][$t] = $cd; $TOK['contrast']['light'][$t] = $xl; $TOK['contrast']['dark'][$t] = $xd; }
function rgb(string $hex): string { $h = ltrim($hex, '#'); return sprintf('rgb(%d, %d, %d)', hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))); }
$MEASURE = <<<'JS'
(() => {
  const a = document.querySelector('aside.rw-side');
  const wrap = document.createElement('div'); wrap.style.color = 'rgb(1, 2, 3)'; const probe = document.createElement('span'); wrap.appendChild(probe); document.body.appendChild(wrap);
  const res = (t) => { probe.style.color = ''; probe.style.color = 'var(' + t + ')'; return getComputedStyle(probe).color; };
  const names = new Set();
  const walk = (rules) => { for (const r of rules) { if (r.style) for (let i = 0; i < r.style.length; i++) if (r.style[i].startsWith('--')) names.add(r.style[i]); if (r.cssRules) walk(r.cssRules); } };
  for (const sh of document.styleSheets) { try { walk(sh.cssRules); } catch (e) {} }
  const allowed = new Set(); const semantic = {};
  for (const n of names) { if (n.startsWith('--p-')) continue; const v = res(n); if (v !== 'rgb(1, 2, 3)') { allowed.add(v); semantic[n] = v; } }
  for (const n of ['--side-surface','--side-raised','--side-rule','--side-ink','--side-muted','--side-accent','--on-side-accent','--charcoal-surface','--charcoal-raised','--charcoal-rule','--charcoal-ink','--charcoal-muted','--surface','--surface-sunk','--surface-raised','--ink','--ink-muted','--ink-faint','--accent','--accent-tint','--on-accent','--rule','--rule-strong']) semantic[n] = res(n);
  wrap.remove();
  const parse = (c) => { const m = String(c).match(/rgba?\(([^)]+)\)/); if (!m) return null; const p = m[1].split(/[ ,\/]+/).filter(Boolean).map(Number); return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 }; };
  const lin = (x) => { x /= 255; return x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4; };
  const lum = (c) => 0.2126 * lin(c.r) + 0.7152 * lin(c.g) + 0.0722 * lin(c.b);
  const ratio = (x, y) => { const [p, q] = [lum(x), lum(y)].sort((u, v) => v - u); return Math.round(((p + 0.05) / (q + 0.05)) * 100) / 100; };
  const over = (t, u) => ({ r: t.r * t.a + u.r * (1 - t.a), g: t.g * t.a + u.g * (1 - t.a), b: t.b * t.a + u.b * (1 - t.a), a: 1 });
  const canvas = parse(getComputedStyle(document.body).backgroundColor) || { r: 255, g: 255, b: 255, a: 1 };
  const bgOf = (el) => { const L = []; for (let e = el; e; e = e.parentElement) { const c = parse(getComputedStyle(e).backgroundColor); if (c && c.a > 0) { L.push(c); if (c.a >= 1) break; } } let b = canvas; for (let i = L.length - 1; i >= 0; i--) b = over(L[i], b); return b; };
  const str = (c) => c ? 'rgb(' + Math.round(c.r) + ', ' + Math.round(c.g) + ', ' + Math.round(c.b) + ')' : null;
  const used = []; const outside = [];
  const note = (el, prop, v) => { const c = parse(v); if (!c || c.a === 0) return; const s = c.a < 1 ? v : str(c); used.push(prop); if (!allowed.has(s) && !allowed.has(v)) outside.push({ el: el.tagName.toLowerCase() + '.' + String(el.getAttribute('class') || '').trim().split(/\s+/).join('.'), prop, v }); };
  const els = [a, ...a.querySelectorAll('*')];
  const low = []; let texts = 0, minText = 99;
  for (const el of els) {
    const cs = getComputedStyle(el);
    if (!el.getClientRects().length || cs.visibility === 'hidden') continue;
    const r = el.getBoundingClientRect(); const hidden = r.width <= 1 && r.height <= 1;
    note(el, 'background-color', cs.backgroundColor);
    for (const s of ['Top', 'Right', 'Bottom', 'Left']) if (parseFloat(cs['border' + s + 'Width']) > 0 && cs['border' + s + 'Style'] !== 'none') note(el, 'border-' + s.toLowerCase(), cs['border' + s + 'Color']);
    if (el instanceof SVGElement) { if (cs.stroke && cs.stroke !== 'none') note(el, 'stroke', cs.stroke); if (cs.fill && cs.fill !== 'none' && el.tagName !== 'svg') note(el, 'fill', cs.fill); }
    const own = [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim() !== '');
    if (own && !hidden) {
      note(el, 'color', cs.color);
      const fg = parse(cs.color), bg = bgOf(el), q = ratio(over(fg, bg), bg); texts++; minText = Math.min(minText, q);
      if (q < 4.5) low.push({ text: el.textContent.trim().slice(0, 30), q, fg: cs.color, bg: str(bg) });
    }
  }
  const ground = parse(getComputedStyle(a).backgroundColor);
  const act = a.querySelector('a[aria-current="page"]'); const item = a.querySelector('.rw-side-nav a:not([aria-current])');
  const lab = a.querySelector('.rw-side-label'); const chip = a.querySelector('.rw-side-count:not(.rw-side-count--hot)'); const hot = a.querySelector('.rw-side-count--hot');
  const actBg = act ? bgOf(act) : null; const actFg = act ? parse(getComputedStyle(act).color) : null;
  return { mode: document.documentElement.getAttribute('data-rw-mode'), theme: document.documentElement.getAttribute('data-rw-theme'), style: a.getAttribute('data-sidebar'),
    ground: str(ground), item: item ? getComputedStyle(item).color : null, label: lab ? getComputedStyle(lab).color : null,
    active: act ? { bg: str(actBg), fg: str(actFg), weight: getComputedStyle(act).fontWeight, bgVsGround: ratio(actBg, ground), fgVsGround: ratio(actFg, ground), fgVsBg: ratio(actFg, actBg) } : null,
    chip: chip ? { bg: getComputedStyle(chip).backgroundColor, fg: getComputedStyle(chip).color } : null, hot: hot ? { bg: getComputedStyle(hot).backgroundColor, fg: getComputedStyle(hot).color, text: hot.textContent.trim() } : null,
    today: !!a.querySelector('.rw-today'), texts, minText, low, outside: outside.slice(0, 20), outsideCount: outside.length, usedCount: used.length, semantic,
    dark: matchMedia('(prefers-color-scheme: dark)').matches };
})()
JS;
// owner gets an attention counter: the owner opens the log, then the admin changes a setting
G($own, 'rolewarden/activity', 'style owner opens log'); sleep(1);
settings_save($adm, ['activity_retention' => '90'], 'style admin change');
$shots = [];
foreach (['console', 'clarity', 'contrast'] as $theme) foreach (['paper', 'ink', 'charcoal'] as $st) {
    theme_save($adm, ['base' => $theme, 'sidebar' => $st === 'paper' ? '' : $st], "style browser $theme $st");
    $steps = [blogin('owner@v8b.test', OPW)];
    foreach (['light', 'dark'] as $mode) {
        $png = __DIR__ . "/verify-u1.style-$theme-$st-$mode.png";
        $steps[] = ['op' => 'emulate', 'scheme' => $mode];
        $steps[] = ['op' => 'goto', 'url' => U1BASE . 'rolewarden/users', 'tag' => "g-$mode"];
        $steps[] = ['op' => 'eval', 'tag' => "m-$mode", 'expr' => $MEASURE];
        $steps[] = ['op' => 'screenshot', 'path' => $png, 'tag' => "s-$mode"];
    }
    $r = u1browser($steps, "style $theme $st");
    foreach (['light', 'dark'] as $mode) {
        $m = bval($r, "m-$mode");
        $id = "S-$theme-$st-$mode";
        if (! is_array($m)) { check("$id-0", "$theme $st $mode measured in Chrome", false, json_encode($r['errors'])); continue; }
        $sem = $m['semantic'];
        $T = array_map('rgb', $TOK[$theme][$mode]);
        if ($st === 'paper') $exp = ['ground' => $sem['--surface-sunk'], 'item' => $sem['--ink'], 'abg' => $sem['--accent-tint'], 'afg' => $sem['--accent']];
        elseif ($st === 'ink') $exp = ['ground' => $T['side-surface'], 'item' => $T['side-ink'], 'label' => $T['side-muted'], 'chip' => $T['side-raised'], 'abg' => $T['side-accent'], 'afg' => $T['on-side-accent'], 'hot' => $T['side-accent']];
        else $exp = ['ground' => $T['charcoal-surface'], 'item' => $T['charcoal-ink'], 'label' => $T['charcoal-muted'], 'chip' => $T['charcoal-raised'], 'abg' => $T['side-accent'], 'afg' => $T['on-side-accent'], 'hot' => $T['side-accent']];
        $got = ['ground' => $m['ground'], 'item' => $m['item'], 'abg' => $m['active']['bg'] ?? null, 'afg' => $m['active']['fg'] ?? null];
        foreach (['label' => $m['label'], 'chip' => $m['chip']['bg'] ?? null, 'hot' => $m['hot']['bg'] ?? null] as $k => $v) if (isset($exp[$k])) $got[$k] = $v;
        $undef = [];
        foreach (array_keys($TOK[$theme][$mode]) as $t) if (($sem["--$t"] ?? '') === 'rgb(1, 2, 3)') $undef[] = "--$t";
        $GLOBALS['UNDEF'][implode(',', $undef)][] = "$theme/$mode";
        check("$id-1", "$theme/$st/$mode: Chrome renders " . ($mode === 'dark' ? 'dark' : 'light') . " with data-sidebar=$st", $m['style'] === $st && $m['dark'] === ($mode === 'dark'), json_encode([$m['style'], $m['dark']]));
        ksort($exp); ksort($got);
        check("$id-2", "$theme/$st/$mode: ground, items, labels, counter chips and active item have the values the design system gives for $st in $theme $mode (weight 600 or more)", $exp == $got && (int) ($m['active']['weight'] ?? 0) >= 600, json_encode(['exp' => $exp, 'got' => $got, 'weight' => $m['active']['weight'] ?? null]));
        check("$id-3", "$theme/$st/$mode: every colour in the sidebar is a token value (" . $m['usedCount'] . ' measured)', $m['outsideCount'] === 0, json_encode($m['outside']));
        check("$id-4", "$theme/$st/$mode: every text of the sidebar at least 4.5:1 (min " . $m['minText'] . ", {$m['texts']} texts)", $m['low'] === [] && $m['texts'] > 5, json_encode($m['low']));
        $a = $m['active'];
        if ($st === 'paper' && ($a['bgVsGround'] ?? 0) < 3 && ($a['fgVsGround'] ?? 0) >= 3) {
            $GLOBALS['PAPER31'][] = "$theme/$mode: wash {$a['bgVsGround']}:1, accent text {$a['fgVsGround']}:1";
            check("$id-5", "$theme/$st/$mode: the active item stands out from the sidebar at 3:1 or more by its accent text ({$a['fgVsGround']}:1; wash {$a['bgVsGround']}:1, see AMB S-31)", true);
        } else {
            check("$id-5", "$theme/$st/$mode: the active item stands out from the sidebar at 3:1 or more (fill {$a['bgVsGround']}:1, text on fill {$a['fgVsBg']}:1)", ($a['bgVsGround'] ?? 0) >= 3 && ($a['fgVsBg'] ?? 0) >= 4.5, json_encode($a));
        }
        $shots[] = basename(__DIR__ . "/verify-u1.style-$theme-$st-$mode.png");
        info("$theme/$st/$mode: " . json_encode(['ground' => $m['ground'], 'active' => $a, 'chip' => $m['chip'], 'hot' => $m['hot'], 'label' => $m['label'], 'min' => $m['minText']]));
    }
}
if (! empty($GLOBALS['PAPER31'])) amb('S-31', 'How the 3:1 of the active item applies to Paper', 'BRIEF/SPEC: "la voce attiva si distingue dalla barra almeno a 3:1". The Paper active item is an accent-tint wash with accent text (design system README), so its fill differs from the surface-sunk ground by about 1.1:1 and only its text reaches 3:1: ' . implode('; ', $GLOBALS['PAPER31']) . '. Whether 3:1 is meant for the fill (as on the dark rails) or for the item as a whole is not stated.');
foreach ($GLOBALS['UNDEF'] ?? [] as $names => $where) if ($names !== '') amb('S-tok', 'Token names of the 1.1 sidebar families not defined in the panel CSS', "The design system names the tokens " . $names . " (values per theme in tokens.css and the README table); the served CSS defines none of these names in " . implode(', ', $where) . ": Ink and Charcoal read the dark-mode tokens (--d-accent, --d-on-accent, --d-surface, --d-surface-raised, --d-ink, --d-ink-muted, --d-rule-strong) in both modes. The values Chrome renders are the design system's (checks -2), but a buyer cannot set these tokens by name, and a dark accent or dark surface chosen in the customizer also changes Ink/Charcoal in light mode. Whether the families must exist as named tokens is not stated.");
info('screenshots: ' . implode(', ', $shots));
@unlink(theme_file_u1());
settings_save($adm, ['activity_retention' => '365'], 'style restore retention');
check('S-clean', 'STYLE pages: no 5xx, no SQL or PHP error text', seen_clean($S0) === [], json_encode(seen_clean($S0)));
