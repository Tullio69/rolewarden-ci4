<?php
// U1 phase A2 (riverifica, decision of 19 Oct 2026): the served CSS defines --side-*, --on-side-accent and --charcoal-*
// by name in the three themes, light and dark, with the values of the design system (tokens.css for Console, the README
// table for Clarity and Contrast); a stylesheet of the application (themeStylesheet, documentation/configuration.html)
// that sets --side-accent, --on-side-accent and --charcoal-surface changes the rendered active item of Ink and Charcoal
// and the Charcoal rail; without it the values are back to the table. Measured on the colours Chrome computes.
require_once __DIR__ . '/verify-u1.lib.php';
$GLOBALS['U1DIR'] = dirname(__DIR__ . '/verify-u1.lib.php');
echo "\n== A2: sidebar token names\n";
serve8($APP, 'A2');
$S0 = count($GLOBALS['SEEN']);
$adm = c8('a2-admin'); login8($adm, 'admin@v8b.test', OPW);
$own = c8('a2-owner'); login8($own, 'owner@v8b.test', OPW);
$so = side(G($own, 'rolewarden/users', 'a2 owner rail?'));
if ($so['rail'] && $so['toggle']) P($own, $so['toggle']['action'], $so['toggle']['fields'], 'a2 expand owner');
// same table as phase STYLE (docs/design-system/tokens.css and README.md)
$TOK = ['console' => ['light' => ['side-surface' => '#0f1d3a', 'side-raised' => '#18294d', 'side-rule' => '#2c3f66', 'side-ink' => '#eef2fa', 'side-muted' => '#a9b6d1', 'side-accent' => '#93b2f0', 'on-side-accent' => '#0f1a33', 'charcoal-surface' => '#1c1d20', 'charcoal-raised' => '#232428', 'charcoal-rule' => '#43464c', 'charcoal-ink' => '#ebebe8', 'charcoal-muted' => '#aeb2b9'],
    'dark' => ['side-surface' => '#0b1630', 'side-raised' => '#142344', 'side-rule' => '#283a5e', 'side-ink' => '#eef2fa', 'side-muted' => '#a9b6d1', 'side-accent' => '#93b2f0', 'on-side-accent' => '#0f1a33', 'charcoal-surface' => '#1c1d20', 'charcoal-raised' => '#232428', 'charcoal-rule' => '#43464c', 'charcoal-ink' => '#ebebe8', 'charcoal-muted' => '#aeb2b9']]];
$rows = ['side-surface' => ['#1a1b4b', '#14153a', '#072a66', '#051d47'], 'side-raised' => ['#262a52', '#1f2247', '#0b3d91', '#072a66'], 'side-rule' => ['#3a3f75', '#33376a', '#dde7f7', '#dde7f7'],
    'side-ink' => ['#eef0f5', '#eef0f5', '#ffffff', '#ffffff'], 'side-muted' => ['#b4bad8', '#b4bad8', '#dde7f7', '#dde7f7'], 'side-accent' => ['#a5b4fc', '#a5b4fc', '#facc15', '#facc15'],
    'on-side-accent' => ['#1a1b4b', '#1a1b4b', '#0a0a0b', '#0a0a0b'], 'charcoal-surface' => ['#14161c', '#14161c', '#0a0a0b', '#0a0a0b'], 'charcoal-raised' => ['#1b1e26', '#1b1e26', '#161618', '#161618'],
    'charcoal-rule' => ['#3a3f4c', '#3a3f4c', '#a1a1aa', '#a1a1aa'], 'charcoal-ink' => ['#eef0f5', '#eef0f5', '#ffffff', '#ffffff'], 'charcoal-muted' => ['#b4bac6', '#b4bac6', '#e4e4e7', '#e4e4e7']];
foreach ($rows as $t => [$cl, $cd, $xl, $xd]) { $TOK['clarity']['light'][$t] = $cl; $TOK['clarity']['dark'][$t] = $cd; $TOK['contrast']['light'][$t] = $xl; $TOK['contrast']['dark'][$t] = $xd; }
function a2rgb(string $hex): string { $h = ltrim($hex, '#'); return sprintf('rgb(%d, %d, %d)', hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))); }
$NAMES = json_encode(array_map(fn ($t) => "--$t", array_keys($TOK['console']['light'])));
// computed value of each token on :root (an undefined one leaves the probe on rgb(1, 2, 3)), the sidebar ground and the active item
$M = <<<JS
(() => {
  const wrap = document.createElement('div'); wrap.style.color = 'rgb(1, 2, 3)'; const probe = document.createElement('span'); wrap.appendChild(probe); document.body.appendChild(wrap);
  const tok = {}; for (const n of $NAMES) { probe.style.color = ''; probe.style.color = 'var(' + n + ')'; tok[n.slice(2)] = getComputedStyle(probe).color; }
  wrap.remove();
  const a = document.querySelector('aside.rw-side'); const act = a && a.querySelector('a[aria-current="page"]');
  const sheets = [...document.querySelectorAll('link[rel=stylesheet]')].map((l) => l.getAttribute('href'));
  return { tok, style: a && a.getAttribute('data-sidebar'), ground: a && getComputedStyle(a).backgroundColor, actBg: act && getComputedStyle(act).backgroundColor, actFg: act && getComputedStyle(act).color,
    dark: matchMedia('(prefers-color-scheme: dark)').matches, sheets };
})()
JS;
function a2measure(string $tag): array
{
    global $M;
    $steps = [blogin('owner@v8b.test', OPW)];
    foreach (['light', 'dark'] as $mode) {
        $steps[] = ['op' => 'emulate', 'scheme' => $mode];
        $steps[] = ['op' => 'goto', 'url' => U1BASE . 'rolewarden/users', 'tag' => "g-$mode"];
        $steps[] = ['op' => 'eval', 'tag' => "m-$mode", 'expr' => $M];
    }
    $r = u1browser($steps, $tag);
    return ['light' => bval($r, 'm-light'), 'dark' => bval($r, 'm-dark'), 'errors' => $r['errors']];
}
function a2table(string $theme, string $mode): array { return array_map('a2rgb', $GLOBALS['TOK'][$theme][$mode]); }

// 1. without an application stylesheet: the 12 tokens defined by name, with the table's values, in 3 themes x light/dark
foreach (['console' => 'ink', 'clarity' => 'charcoal', 'contrast' => 'ink'] as $theme => $st) {
    theme_save($adm, ['base' => $theme, 'sidebar' => $st], "a2 $theme $st");
    $r = a2measure("a2 defaults $theme");
    foreach (['light', 'dark'] as $mode) {
        $m = $r[$mode];
        $ok = is_array($m) && $m['dark'] === ($mode === 'dark') && $m['tok'] == a2table($theme, $mode);
        $diff = is_array($m) ? array_diff_assoc(a2table($theme, $mode), $m['tok']) : $r['errors'];
        check("A2-def-$theme-$mode", "$theme/$mode, no application stylesheet: --side-*, --on-side-accent and --charcoal-* are defined by name with the design system's values (12 tokens)", $ok, json_encode(['diff' => $diff, 'got' => $m['tok'] ?? null]));
    }
}
// 2. the application's stylesheet sets them (themeStylesheet, loaded after the panel's own)
$css = ":root { --side-accent: #c2410c; --on-side-accent: #ffffff; --charcoal-surface: #3b0764; }\n";
@mkdir("$APP/public/css", 0777, true);
file_put_contents("$APP/public/css/u1-a2.css", $css);
file_put_contents("$APP/app/Config/RoleWarden.php", "<?php\n\nnamespace Config;\n\n// verify-u1 A2 (Collaudatore ad Hoc): an application stylesheet, as documentation/configuration.html shows\nclass RoleWarden extends \\RoleWarden\\Config\\RoleWarden\n{\n    public ?string \$themeStylesheet = '/css/u1-a2.css';\n}\n");
$probe = c8('a2-css'); $probe->get('css/u1-a2.css');
info('application stylesheet served: HTTP ' . $probe->status);
$OV = ['side-accent' => a2rgb('#c2410c'), 'on-side-accent' => a2rgb('#ffffff'), 'charcoal-surface' => a2rgb('#3b0764')];
foreach (['ink', 'charcoal'] as $st) {
    theme_save($adm, ['base' => 'console', 'sidebar' => $st], "a2 override $st");
    $r = a2measure("a2 override $st");
    foreach (['light', 'dark'] as $mode) {
        $m = $r[$mode];
        $T = a2table('console', $mode);
        $expGround = $st === 'charcoal' ? $OV['charcoal-surface'] : $T['side-surface'];
        $got = is_array($m) ? ['style' => $m['style'], 'ground' => $m['ground'], 'actBg' => $m['actBg'], 'actFg' => $m['actFg']] : $r['errors'];
        $ok = is_array($m) && $m['style'] === $st && $m['actBg'] === $OV['side-accent'] && $m['actFg'] === $OV['on-side-accent'] && $m['ground'] === $expGround
            && (bool) preg_grep('#/css/u1-a2\.css$#', $m['sheets']);
        check("A2-ov-$st-$mode", "console/$st/$mode: an application stylesheet setting --side-accent, --on-side-accent" . ($st === 'charcoal' ? ' and --charcoal-surface' : '') . ' changes the rendered active item' . ($st === 'charcoal' ? ' and the Charcoal rail' : ''), $ok, json_encode(['exp' => ['actBg' => $OV['side-accent'], 'actFg' => $OV['on-side-accent'], 'ground' => $expGround], 'got' => $got, 'sheets' => $m['sheets'] ?? null]));
    }
}
// 3. stylesheet removed: back to the table
unlink("$APP/app/Config/RoleWarden.php"); unlink("$APP/public/css/u1-a2.css");
theme_save($adm, ['base' => 'console', 'sidebar' => 'charcoal'], 'a2 back');
$r = a2measure('a2 back');
foreach (['light', 'dark'] as $mode) {
    $m = $r[$mode]; $T = a2table('console', $mode);
    check("A2-back-$mode", "console/charcoal/$mode, stylesheet removed: active item and rail back to the design system's values", is_array($m) && $m['actBg'] === $T['side-accent'] && $m['actFg'] === $T['on-side-accent'] && $m['ground'] === $T['charcoal-surface'] && $m['tok'] == $T, json_encode($m ? ['ground' => $m['ground'], 'actBg' => $m['actBg'], 'actFg' => $m['actFg']] : $r['errors']));
}
@unlink(theme_file_u1());
check('A2-clean', 'A2 pages: no 5xx, no SQL or PHP error text', seen_clean($S0) === [], json_encode(seen_clean($S0)));
