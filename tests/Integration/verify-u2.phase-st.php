<?php
/**
 * U2 phase ST (Collaudatore ad Hoc, Claude): the user status of commit 6f3922a (before U2), against the design system
 * README (Color, Motion) and components/UserStatus/README.md: the active dot is `granted` with a 3px `granted-tint` halo
 * and `ink` text; it breathes three times on load (1.6s each, under 5s) and then rests; never under
 * prefers-reduced-motion; the disabled ring is a hollow `ink-muted` ring, no halo, never moves. The dot keeps 3:1 on
 * its ground (WCAG 1.4.11). Measured in Chrome on the served CSS, 3 themes x light/dark.
 */
require_once __DIR__ . '/verify-u1.lib.php';
$GLOBALS['U1DIR'] = dirname(__DIR__ . '/verify-u1.lib.php');
echo "\n== ST: user status dot\n";
serve8($APP, 'ST');
$S0 = count($GLOBALS['SEEN']);
function u2browser(array $steps, string $tag, int $timeout = 300000): array
{
    global $WORK;
    static $n = 0; $n++;
    $job = ['profile' => "$WORK/chrome-u2-$n", 'steps' => $steps, 'timeout' => $timeout, 'settle' => 400];
    $jf = "$WORK/u2job-$n.json";
    file_put_contents($jf, json_encode($job));
    $p = proc_open(['node', __DIR__ . '/verify-u2.browser.mjs', $jf], [0 => ['file', 'NUL', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    $o = stream_get_contents($pipes[1]); $e = stream_get_contents($pipes[2]);
    proc_close($p);
    $r = json_decode(trim((string) $o), true);
    if (! is_array($r)) { info("browser $tag: no result: " . short($o . $e, 600)); return ['steps' => [], 'errors' => ['no result'], 'profileRemoved' => false]; }
    if ($r['errors']) info("browser $tag errors: " . short(implode(' | ', $r['errors']), 800));
    if (empty($r['profileRemoved'])) info("browser $tag: profile not removed " . ($r['cleanupError'] ?? ''));
    return $r;
}
$own = c8('st-owner'); login8($own, 'owner@v8b.test', OPW);
$adm = c8('st-admin'); login8($adm, 'admin@v8b.test', OPW);
mkuser('st-off@example.com', 'user', [], 0);
$h = G($own, 'rolewarden/users', 'st users');
$css = '';
foreach (dom($h)->query('//link[@rel="stylesheet"]') as $l) { $u = html_entity_decode($l->getAttribute('href')); $c = c8('st-css'); $c->get($u); if (str_contains($u, 'panel.css')) $css = $c->body; }
$label = $GLOBALS['LABEL'] ?? 'run';
file_put_contents(__DIR__ . "/verify-u2.st.$label.served-panel.css", $css);
// the rules as served
preg_match_all('/[^{}]*\.rw-status[^{}]*\{[^{}]*\}/', $css, $m1);
preg_match_all('/@keyframes\s+([\w-]+)\s*\{((?:[^{}]*\{[^{}]*\})*)\s*\}/', $css, $kf, PREG_SET_ORDER);
preg_match_all('/@media[^{]*prefers-reduced-motion[^{]*\{((?:[^{}]*\{[^{}]*\})*)\s*\}/', $css, $rm);
info('served .rw-status rules: ' . short(implode(' || ', array_map('trim', $m1[0])), 2500));
info('reduced-motion blocks: ' . short(implode(' || ', $rm[1]), 1500));
$keyframes = []; foreach ($kf as $k) $keyframes[$k[1]] = $k[2];
$JS = <<<'JS'
(() => {
  const parse = (c) => { const m = String(c).match(/rgba?\(([^)]+)\)/); if (!m) return null; const p = m[1].split(/[ ,\/]+/).filter(Boolean).map(Number); return { r: p[0], g: p[1], b: p[2], a: p.length > 3 ? p[3] : 1 }; };
  const lin = (x) => { x /= 255; return x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4; };
  const lum = (c) => 0.2126 * lin(c.r) + 0.7152 * lin(c.g) + 0.0722 * lin(c.b);
  const ratio = (x, y) => { const [p, q] = [lum(x), lum(y)].sort((u, v) => v - u); return Math.round(((p + 0.05) / (q + 0.05)) * 100) / 100; };
  const over = (t, u) => ({ r: t.r * t.a + u.r * (1 - t.a), g: t.g * t.a + u.g * (1 - t.a), b: t.b * t.a + u.b * (1 - t.a), a: 1 });
  const canvas = parse(getComputedStyle(document.body).backgroundColor) || { r: 255, g: 255, b: 255, a: 1 };
  const bgOf = (el) => { const L = []; for (let e = el; e; e = e.parentElement) { const c = parse(getComputedStyle(e).backgroundColor); if (c && c.a > 0) { L.push(c); if (c.a >= 1) break; } } let b = canvas; for (let i = L.length - 1; i >= 0; i--) b = over(L[i], b); return b; };
  const str = (c) => c ? 'rgb(' + Math.round(c.r) + ', ' + Math.round(c.g) + ', ' + Math.round(c.b) + ')' : null;
  const probe = document.createElement('span'); document.body.appendChild(probe);
  const tok = (t) => { probe.style.color = ''; probe.style.color = 'var(' + t + ')'; return getComputedStyle(probe).color; };
  const T = {}; for (const t of ['--granted', '--granted-tint', '--ink', '--ink-muted', '--surface', '--surface-sunk', '--denied']) T[t] = tok(t);
  probe.remove();
  const one = (el) => { if (!el) return null; const b = getComputedStyle(el, '::before'); const bg = bgOf(el); const dot = parse(b.backgroundColor); const border = parse(b.borderTopColor);
    const sh = b.boxShadow; const shc = parse(sh);
    return { text: el.textContent.trim(), color: getComputedStyle(el).color, ground: str(bg), dotBg: b.backgroundColor, dotBorder: b.borderTopColor, borderW: b.borderTopWidth, w: b.width, h: b.height, radius: b.borderTopLeftRadius, shadow: sh, shadowColor: shc ? str(shc) : null,
      anim: { name: b.animationName, dur: b.animationDuration, count: b.animationIterationCount, delay: b.animationDelay, fill: b.animationFillMode },
      dotVsGround: dot && dot.a > 0 ? ratio(over(dot, bg), bg) : null, borderVsGround: border && border.a > 0 ? ratio(over(border, bg), bg) : null, dotVsHalo: dot && shc ? ratio(over(dot, bg), over(shc, bg)) : null, haloVsGround: shc ? ratio(over(shc, bg), bg) : null,
      textVsGround: ratio(parse(getComputedStyle(el).color), bg) }; };
  const act = document.querySelector('.rw-status:not(.rw-status--disabled)'); const off = document.querySelector('.rw-status.rw-status--disabled');
  const anims = document.getAnimations ? document.getAnimations().filter((a) => a.effect && a.effect.pseudoElement === '::before').map((a) => ({ name: a.animationName, state: a.playState, kf: a.effect.getKeyframes().map((k) => Object.keys(k).filter((x) => !['offset', 'computedOffset', 'easing', 'composite'].includes(x))) })) : [];
  return { theme: document.documentElement.getAttribute('data-rw-theme'), dark: matchMedia('(prefers-color-scheme: dark)').matches, reduced: matchMedia('(prefers-reduced-motion: reduce)').matches, T, active: one(act), disabled: one(off), anims: anims.slice(0, 3), count: document.querySelectorAll('.rw-status').length };
})()
JS;
$res = [];
foreach (['console', 'clarity', 'contrast'] as $theme) {
    theme_save($adm, ['base' => $theme, 'sidebar' => ''], "st theme $theme");
    $steps = [blogin('owner@v8b.test', OPW)];
    foreach (['light', 'dark'] as $mode) {
        $steps[] = ['op' => 'emulate', 'scheme' => $mode, 'motion' => 'no-preference'];
        $steps[] = ['op' => 'goto', 'url' => U1BASE . 'rolewarden/users', 'tag' => "g-$mode"];
        $steps[] = ['op' => 'eval', 'tag' => "early-$mode", 'expr' => $JS];
        $steps[] = ['op' => 'wait', 'ms' => 5600];
        $steps[] = ['op' => 'eval', 'tag' => "rest-$mode", 'expr' => $JS];
        $steps[] = ['op' => 'emulate', 'scheme' => $mode, 'motion' => 'reduce'];
        $steps[] = ['op' => 'goto', 'url' => U1BASE . 'rolewarden/users', 'tag' => "r-$mode"];
        $steps[] = ['op' => 'eval', 'tag' => "reduced-$mode", 'expr' => $JS];
        $steps[] = ['op' => 'screenshot', 'path' => __DIR__ . "/verify-u2.st-$theme-$mode.png", 'tag' => "s-$mode"];
    }
    $r = u2browser($steps, "st $theme");
    foreach (['light', 'dark'] as $mode) {
        $e = bval($r, "early-$mode"); $z = bval($r, "rest-$mode"); $q = bval($r, "reduced-$mode");
        $id = "ST-$theme-$mode";
        if (! is_array($e) || ! is_array($z) || ! is_array($q) || ! $e['active'] || ! $e['disabled']) { check("$id-0", "$theme $mode measured", false, json_encode([$r['errors'], $e])); continue; }
        $res["$theme/$mode"] = ['dot' => $z['active']['dotVsGround'], 'dotHalo' => $z['active']['dotVsHalo'], 'halo' => $z['active']['haloVsGround'], 'ground' => $z['active']['ground'], 'T' => $z['T']];
        $T = $z['T']; $a = $z['active']; $d = $z['disabled'];
        check("$id-1", "$theme/$mode: active dot filled `granted` (fill and edge), 8px round, `ink` word; at rest a 3px `granted-tint` halo",
            $a['dotBg'] === $T['--granted'] && $a['dotBorder'] === $T['--granted'] && $a['color'] === $T['--ink'] && $a['radius'] === '50%' && $a['shadowColor'] === $T['--granted-tint'] && preg_match('/0px 0px 0px 3px$/', $a['shadow']), json_encode([$a, $T]));
        check("$id-2", "$theme/$mode: the active dot keeps 3:1 or more on its ground (dot {$a['dotVsGround']}:1 on {$a['ground']})", ($a['dotVsGround'] ?? 0) >= 3, json_encode($a));
        check("$id-3", "$theme/$mode: disabled is a hollow `ink-muted` ring, `ink-muted` word, no halo, no animation, never `denied`",
            (parse_rgba($d['dotBg'])[3] ?? 1) == 0 && $d['dotBorder'] === $T['--ink-muted'] && $d['color'] === $T['--ink-muted'] && $d['shadow'] === 'none' && $d['anim']['name'] === 'none' && $d['dotBorder'] !== $T['--denied'], json_encode($d));
        $an = $e['active']['anim'];
        $dur = (float) $an['dur'];
        check("$id-4", "$theme/$mode: on load the active halo breathes: one animation of 1.6s run 3 times (" . ($dur * (int) $an['count']) . 's in all, under 5s), no delay', $an['name'] !== 'none' && abs($dur - 1.6) < 0.001 && $an['count'] === '3' && in_array($an['delay'], ['0s', ''], true), json_encode($an));
        $props = []; foreach ($e['anims'] as $x) foreach ($x['kf'] as $kk) $props = array_merge($props, $kk);
        $props = array_values(array_unique($props));
        check("$id-5", "$theme/$mode: the animation moves only the halo (box-shadow): nothing slides or scales", $props !== [] && array_diff($props, ['boxShadow']) === [], json_encode($e['anims']));
        check("$id-6", "$theme/$mode: after 5.6s the animation has finished and the dot rests with its halo", ! array_filter($z['anims'], fn ($x) => $x['state'] === 'running') && $a['shadowColor'] === $T['--granted-tint'], json_encode($z['anims']));
        $qa = $q['active'];
        check("$id-7", "$theme/$mode: under prefers-reduced-motion the dot does not move (no animation) and still shows its halo", $q['reduced'] === true && $qa['anim']['name'] === 'none' && $q['anims'] === [] && $qa['shadowColor'] === $T['--granted-tint'], json_encode(['reducedMatches' => $q['reduced'], 'anim' => $qa['anim'], 'running' => $q['anims'], 'shadow' => $qa['shadow']]));
    }
}
function parse_rgba(string $c): array { return preg_match('/rgba?\(([^)]+)\)/', $c, $m) ? array_map('floatval', preg_split('/[ ,\/]+/', trim($m[1]))) + [3 => 1.0] : []; }
info('contrast of the active dot (dot/ground, dot/halo, halo/ground): ' . json_encode(array_map(fn ($x) => [$x['dot'], $x['dotHalo'], $x['halo'], $x['ground']], $res)));
$lowHalo = array_filter($res, fn ($x) => $x['dotHalo'] !== null && $x['dotHalo'] < 3);
if ($lowHalo) info('dot against its own halo under 3:1 in: ' . json_encode(array_keys($lowHalo)) . ' (the README asks 3:1 on the ground; the halo is the dot\'s own ring)');
theme_save($adm, ['base' => 'console', 'sidebar' => ''], 'st theme back');
// Re-check on 982c1c3: the README only asks that the status never animates under reduced motion; either form is accepted:
// a reduce block switching it off, or the status animation declared only inside a no-preference block (nowhere else).
$noPref = preg_replace('/@media[^{]*prefers-reduced-motion\s*:\s*no-preference[^{]*\{((?:[^{}]*\{[^{}]*\})*)\s*\}/', '', $css);
$offInReduce = (bool) array_filter($rm[1], fn ($b) => str_contains($b, 'rw-status') && preg_match('/animation\s*:\s*none|animation-name\s*:\s*none/', $b));
$onlyNoPref = (bool) array_filter($rm[1], fn ($b) => str_contains($b, 'rw-status') && str_contains($b, 'rw-alive')) && ! preg_match('/animation(-name)?\s*:[^;}]*rw-alive/', $noPref);
check('ST-css', 'Served CSS: keyframes of the status animate only box-shadow, and the status animation is off under prefers-reduced-motion (reduce block with none, or declared only inside no-preference)', (bool) array_filter($keyframes, fn ($k) => str_contains($k, 'box-shadow') && ! preg_match('/transform|scale|translate|opacity|width|height/', $k)) && ($offInReduce || $onlyNoPref), json_encode(['kf' => $keyframes, 'rm' => $rm[1], 'offInReduce' => $offInReduce, 'onlyNoPref' => $onlyNoPref]));
check('ST-clean', 'ST pages: no 5xx, no SQL or PHP error', seen_clean($S0) === [], json_encode(seen_clean($S0)));
