<?php
/**
 * V5 "Temi": independent black-box verification (Collaudatore ad Hoc, Claude), commit d3c7dbb,
 * upgrade baseline bd7488e. Assertions come only from docs/SPEC.md (Modello dati, Pannello admin
 * with the V5 decisions of 2026-09-30 and 2026-10-03, Design system), docs/BRIEF-v1.0.md (V5,
 * "Sulla V5", definition of done, protocol), README ("Updating the module", "Themes and
 * appearance", CSS build paragraph) and documentation/design-guide.html. Form fields, routes and
 * the behaviour of the customiser are read from the served HTML, CSS and JS, never from the code.
 * Started by verify-v5.run.py:  php verify-v5.php <app> <module-bd7488e> <module-d3c7dbb> v5 <env>
 * Only rolewarden_test on 127.0.0.1:3317; credentials only from RW_DB_* process variables.
 * Browser checks: verify-v5.browser.mjs (headless Chrome, throwaway profile, closed by PID).
 */
declare(strict_types=1);
require __DIR__ . '/verify-v3.lib.php';

$ENVN = $argv[5] ?? 'development';
$WORKDIR = dirname($APP);
$MOD_AGAIN = $MOD_NEW . '-again';
const HOST = 'http://localhost:8070/';
const SEMANTIC = ['--surface', '--surface-sunk', '--surface-raised', '--ink', '--ink-muted', '--ink-faint', '--rule', '--rule-strong', '--control-border', '--accent', '--accent-hover', '--on-accent', '--accent-tint', '--granted', '--granted-tint', '--denied', '--denied-tint', '--on-denied', '--focus'];
const THEMES = ['console', 'clarity', 'contrast'];

function info(string $s): void { echo "[INFO] $s\n"; }
function tables(): array { $t = array_map(fn ($r) => array_values($r)[0], q('SHOW TABLES')); sort($t); return $t; }
function theme_file(): string { global $APP; return "$APP/writable/rolewarden/theme.json"; }
function app_theme_file(): string { global $APP; return "$APP/app/Config/RoleWarden/theme.json"; }
function tf(): ?string { clearstatcache(); return is_file(theme_file()) ? (string) file_get_contents(theme_file()) : null; }
function nav_has(string $html, string $path): bool { return (bool) preg_match('#<ul class="rw-nav">(?:(?!</ul>).)*' . preg_quote($path, '#') . '"#s', $html); }
function html_theme(string $h): ?string { return preg_match('/<html[^>]*\sdata-rw-theme="([^"]*)"/', $h, $m) ? $m[1] : null; }
function html_mode(string $h): ?string { return preg_match('/<html[^>]*\sdata-rw-mode="([^"]*)"/', $h, $m) ? $m[1] : null; }
function theme_link(string $h): ?string { return preg_match('/<link[^>]+href="([^"]*theme\.css[^"]*)"/', $h, $m) ? html_entity_decode($m[1]) : null; }
function css_link(string $h): ?string { return preg_match('/<link[^>]+href="([^"]*assets\/css\/panel\.css[^"]*)"/', $h, $m) ? html_entity_decode($m[1]) : null; }
function mode_form(string $h): ?array { foreach (forms($h) as $f) if (str_contains($f['action'], 'appearance/mode')) return $f; return null; }
function pressed(string $h): ?string { return preg_match('/<button[^>]*name="mode" value="(\w+)" aria-pressed="true"/', $h, $m) ? $m[1] : null; }
function set_mode(Client $c, string $mode, bool $csrf = true): void {
    $f = mode_form(page($c, 'rolewarden/profile'));
    if (! $f) throw new RuntimeException('mode form not found');
    if (! $csrf) unset($f['fields']['csrf_test_name']);
    submit($c, $f, ['mode' => $mode]);
}
function ap_form(Client $c): array { return matching(forms(page($c, 'rolewarden/appearance')), 'Save theme'); }
/** Plain POST of the appearance form (no JavaScript): every colour override empty unless given. */
function save_theme(Client $c, array $v, bool $csrf = true): string {
    $f = ap_form($c);
    foreach (array_keys($f['fields']) as $k) if (str_starts_with($k, 'colors[')) $f['fields'][$k] = '';
    $f['fields']['base'] = 'console'; $f['fields']['radius'] = ''; $f['fields']['density'] = '';
    if (! $csrf) unset($f['fields']['csrf_test_name']);
    submit($c, $f, $v);
    return in_array($c->status, [302, 303], true) ? follow($c) : $c->body;
}
function raw_post(Client $c, string $url, string $body): void { $c->req('POST', $url, $body); $GLOBALS['SEEN'][] = [$url, $c->status, $c->body]; }
function reset_form(string $h): ?array { foreach (forms($h) as $f) if (str_ends_with($f['action'], 'appearance/reset')) return $f; return null; }
/** An error shown under (after) the named control, inside its row or field. */
function error_under(string $h, string $name): bool {
    if (field_error($h, $name)) return true;
    // Also accepted: an error paragraph after the control group, before the next lettered section.
    $hc = clean_html($h); $p = strpos($hc, 'name="' . $name . '"');
    if ($p !== false) { $end = strpos($hc, 'rw-section-title', $p); $seg = substr($hc, $p, $end === false ? null : $end - $p); if (preg_match('/class="rw-field-error"[^>]*>\s*(<b>)?Error:/', $seg)) return true; }
    $d = new DOMDocument(); @$d->loadHTML($h); $xp = new DOMXPath($d);
    foreach ($xp->query('//*[@name="' . $name . '"]') as $node) {
        foreach (['ancestor::tr[1]', 'ancestor::td[1]', 'ancestor::*[contains(@class,"rw-field")][1]'] as $a) {
            $row = $xp->query($a, $node)->item(0); if (! $row) continue;
            $html = $d->saveHTML($row); $pos = strpos($html, 'name="' . $name . '"');
            if (preg_match('/Error:|rw-field-error/i', substr($html, (int) $pos))) return true;
        }
    }
    return false;
}
function logs_take(string $label): array {
    global $APP, $WORKDIR; $lines = [];
    foreach (glob("$APP/writable/logs/*.log") as $f) { $lines = array_merge($lines, file($f, FILE_IGNORE_NEW_LINES)); unlink($f); }
    file_put_contents("$WORKDIR/verify-v5.phase-$label.log", implode("\n", $lines) . "\n", FILE_APPEND);
    return $lines;
}
function bad_lines(array $lines): array {
    return array_values(array_filter($lines, fn ($l) => preg_match('/^(WARNING|NOTICE|DEPRECATED|ERROR|CRITICAL|ALERT|EMERGENCY) /', $l) && ! str_contains($l, 'SecurityException') && ! str_contains($l, 'The action you requested is not allowed')));
}
function lin(float $x): float { $x /= 255; return $x <= 0.03928 ? $x / 12.92 : (($x + 0.055) / 1.055) ** 2.4; }
function lum_hex(string $h): float { [$r, $g, $b] = sscanf($h, '#%02x%02x%02x'); return 0.2126 * lin($r) + 0.7152 * lin($g) + 0.0722 * lin($b); }
function ratio_hex(string $a, string $b): float { $x = lum_hex($a); $y = lum_hex($b); return (max($x, $y) + 0.05) / (min($x, $y) + 0.05); }
function rgb_hex(string $rgb): string { return preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+)/', $rgb, $m) ? sprintf('#%02x%02x%02x', $m[1], $m[2], $m[3]) : $rgb; }
function tree_hash(string $dir): string {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)); $h = [];
    foreach ($it as $f) $h[str_replace('\\', '/', substr((string) $f, strlen($dir)))] = md5_file((string) $f);
    ksort($h); return md5(json_encode($h)) . ' (' . count($h) . ' files)';
}

// ---------- headless Chrome ----------
$BJ = 0;
function browser(array $steps, string $tag, int $settle = 250): array {
    global $WORKDIR, $BJ; $BJ++;
    // Throwaway profiles outside the work folder (a locked profile file must not block the cleanup).
    $job = ['profile' => str_replace('\\', '/', (getenv('RW_V5_CHROME_DIR') ?: sys_get_temp_dir()) . '/rwv5-chrome-' . getmypid() . "-$BJ"), 'steps' => $steps, 'settle' => $settle, 'timeout' => 900000];
    $file = "$WORKDIR/browser-job-$BJ.json"; file_put_contents($file, json_encode($job, JSON_UNESCAPED_SLASHES));
    $p = proc_open(['node', "$WORKDIR/verify-v5.browser.mjs", $file], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    $o = stream_get_contents($pipes[1]); $e = stream_get_contents($pipes[2]); proc_close($p);
    $lines = array_values(array_filter(explode("\n", trim($o))));
    $r = json_decode((string) end($lines), true);
    if (! is_array($r)) { info("browser $tag: no result; stderr=" . substr($e, 0, 400)); return ['steps' => [], 'errors' => ['no result'], 'pid' => null]; }
    info("browser $tag: chrome pid {$r['pid']}, steps " . count($r['steps']) . ', errors ' . json_encode($r['errors']) . ', profile removed ' . json_encode($r['profileRemoved'] ?? null));
    $GLOBALS['CHROME_PIDS'][] = $r['pid'];
    return $r;
}
function by_tag(array $r, string $tag) { foreach ($r['steps'] as $s) if (($s['tag'] ?? '') === $tag) return $s; return null; }
function js_set_color(string $id, string $hex): string {
    return "(() => { const i = document.getElementById(" . json_encode($id) . "); if (!i) return 'missing'; i.value = " . json_encode($hex) . "; i.dispatchEvent(new Event('input', { bubbles: true })); return i.value; })()";
}
const JS_STATE = "(() => { const r = document.documentElement, cs = getComputedStyle(r), p = document.createElement('span'); document.body.appendChild(p);
  const res = (t) => { p.style.color = 'var(' + t + ')'; return getComputedStyle(p).color; }; const bar = document.querySelector('.rw-bar-msg');
  const o = { theme: r.dataset.rwTheme, mode: r.dataset.rwMode || null, warn: !!document.querySelector('.rw-bar-msg .rw-appearance-warn'), bar: bar ? bar.innerText.trim() : null,
    surface: res('--surface'), ink: res('--ink'), accent: res('--accent'), onAccent: res('--on-accent'), raised: res('--surface-raised'), sunk: res('--surface-sunk'),
    radius: cs.getPropertyValue('--radius-md').trim(), row: cs.getPropertyValue('--size-row').trim(), control: cs.getPropertyValue('--size-control').trim(),
    lSurface: cs.getPropertyValue('--l-surface').trim(), btnRadius: getComputedStyle(document.querySelector('.rw-btn')).borderTopLeftRadius,
    body: getComputedStyle(document.querySelector('.rw')).backgroundColor,
    input: (() => { const i = document.querySelector('.rw-appearance-sample .rw-input'); if (!i) return null; const s = getComputedStyle(i); return [s.color, s.backgroundColor]; })(),
    nav: (() => { const a = document.querySelector('.rw-nav a:not([aria-current])'); const sb = document.querySelector('.rw-sidebar'); return a && sb ? [getComputedStyle(a).color, getComputedStyle(sb).backgroundColor] : null; })(),
    hidden: Object.fromEntries([...document.querySelectorAll('input[type=hidden][name^=colors]')].filter((i) => i.value).map((i) => [i.name, i.value])) };
  p.remove(); return o; })()";

$GLOBALS['CHROME_PIDS'] = [];
try {
    mail_guard();
    // =====================================================================================
    echo "== 1 Upgrade from bd7488e by replacing the folder, rollback, fresh install, no build ==\n";
    reset_db(); use_module($MOD_OLD); readme_install();
    $F = make_fixtures(); $U = $F['u']; $pw = $F['pw'];
    $before = acl_state(); $tBefore = tables(); $batch = (int) q1('SELECT MAX(batch) FROM migrations');
    check('U01', 'Baseline bd7488e has no appearance.update permission', (int) q1("SELECT COUNT(*) FROM acl_permissions WHERE slug='appearance.update'") === 0);
    server_start();
    $adm = login2('up-admin', $U['admin2']['email'], $pw);
    $h = page($adm, 'rolewarden/users');
    info('Baseline: nav has Appearance=' . json_encode(nav_has($h, 'rolewarden/appearance')) . ' html theme attr=' . json_encode(html_theme($h)));
    server_stop(); use_module($MOD_NEW); server_start();
    $h = page($adm, 'rolewarden/users');
    check('U02', 'Folder replaced, before migrate: the signed-in panel still answers 200 without SQL text', $adm->status === 200 && ! leaks_sql($h), 'HTTP ' . $adm->status);
    check('U02b', 'Before migrate: admin has no Appearance menu entry (permission not yet granted)', ! nav_has($h, 'rolewarden/appearance'));
    $h = page($adm, 'rolewarden/appearance');
    check('U02c', 'Before migrate: /rolewarden/appearance is refused cleanly (not 200, not 500)', $adm->status !== 200 && $adm->status !== 500 && ! leaks_sql($h), 'HTTP ' . $adm->status);
    $anon = new Client('v5-anon-window'); page($anon, 'login');
    check('U02d', 'Before migrate: the login page answers 200', $anon->status === 200 && ! leaks_sql($anon->body));
    spark('migrate', '-n', 'RoleWarden');
    check('U03', 'Upgrade grants appearance.update to admin', in_array('appearance.update', role_perms('admin'), true));
    $holders = array_column(q("SELECT r.slug FROM acl_role_permissions rp JOIN acl_roles r ON r.id=rp.role_id JOIN acl_permissions p ON p.id=rp.permission_id WHERE p.slug='appearance.update' ORDER BY r.slug"), 'slug');
    check('U03b', 'Only admin receives appearance.update from the data migration', $holders === ['admin'], json_encode($holders));
    check('U03c', 'Upgrade adds no table (theme in a file, mode in Settings)', tables() === $tBefore, json_encode(array_values(array_diff(tables(), $tBefore))));
    $h = page($adm, 'rolewarden/users');
    check('U04', 'After migrate: admin sees the Appearance menu entry', nav_has($h, 'rolewarden/appearance'));
    $own = login2('up-owner', $U['owner']['email'], $pw);
    check('U04b', 'Super admin sees the Appearance menu entry', nav_has(page($own, 'rolewarden/users'), 'rolewarden/appearance'));
    $lim = login2('up-limited', $U['limited']['email'], $pw);
    $h = page($lim, 'rolewarden/users');
    check('U05', 'users.view-only user: no Appearance menu entry', $lim->status === 200 && ! nav_has($h, 'rolewarden/appearance'));
    $h = page($lim, 'rolewarden/appearance');
    check('U05b', 'users.view-only user: /rolewarden/appearance refused (not 200, not 500)', $lim->status !== 200 && $lim->status !== 500, 'HTTP ' . $lim->status);
    $sub = login2('up-subject', $U['subject']['email'], $pw);
    check('U05c', 'User without permissions: no Appearance entry on the profile page', ! nav_has(page($sub, 'rolewarden/profile'), 'rolewarden/appearance'));
    check('U06', 'After migrate: admin opens the customiser (200, Save theme form)', str_contains(page($adm, 'rolewarden/appearance'), 'Save theme') && $adm->status === 200);
    server_stop(); spark('migrate:rollback', '-b', (string) $batch, '-f');
    check('U07', 'Rollback restores ACL rows, migrations and tables exactly', acl_state() === $before && tables() === $tBefore);
    server_start();
    $h = page($adm, 'rolewarden/users');
    check('U08', 'Rolled back, d3c7dbb folder still in place: panel 200, no Appearance entry, customiser refused', $adm->status === 200 && ! nav_has($h, 'rolewarden/appearance') && ! str_contains(page($adm, 'rolewarden/appearance'), 'Save theme') && $adm->status !== 500, 'HTTP ' . $adm->status);
    server_stop(); use_module($MOD_OLD); server_start();
    check('U09', 'Restored bd7488e folder works after the rollback', page($adm, 'rolewarden/users') !== '' && $adm->status === 200);
    server_stop(); use_module($MOD_NEW); spark('migrate', '-n', 'RoleWarden');
    check('U10', 'Upgrade applies again after the rollback', in_array('appearance.update', role_perms('admin'), true));
    reset_db(); readme_install();
    check('U11', 'Fresh install grants appearance.update to admin', in_array('appearance.update', role_perms('admin'), true));
    $rb = (int) q1("SELECT MIN(batch) FROM migrations WHERE namespace='RoleWarden'") - 1;
    spark('migrate:rollback', '-b', (string) $rb, '-f');
    check('U12', 'Fresh install rolls back cleanly (no acl_ table left)', q("SHOW TABLES LIKE 'acl\\_%'") === []);
    spark('migrate', '-n', 'RoleWarden'); spark('db:seed', 'RoleWarden\\Database\\Seeds\\RoleWardenSeeder');
    // No build for the installer: the compiled stylesheet ships in the package and is what is served.
    $shipped = "$MOD_NEW/src/Assets/css/panel.css";
    check('U13', 'The package (git archive of d3c7dbb) contains the compiled panel stylesheet', is_file($shipped) && filesize($shipped) > 10000 && str_starts_with((string) file_get_contents($shipped), '/*! tailwindcss'), is_file($shipped) ? filesize($shipped) . ' bytes' : 'missing');
    logs_take('upgrade');

    // =====================================================================================
    echo "== Fixtures ==\n";
    $F = make_fixtures(); $U = $F['u']; $pw = $F['pw'];
    $U2 = ['id' => add_user('V5Second', 'second@v5.test', 'user', $pw), 'email' => 'second@v5.test'];
    server_start();
    $adm = login2('admin', $U['admin2']['email'], $pw);
    settings($adm, ['sign_in_rate' => '100']); cache_clear();
    $anon = new Client('v5-anon');
    $lh = page($anon, 'login');
    $served = css_link($lh);
    $anon->req('GET', $served); $css = $anon->body;
    check('U14', 'Served panel.css is byte-identical to the compiled file in the package (installer builds nothing)', $anon->status === 200 && str_contains($anon->headers['content-type'] ?? '', 'text/css') && $css === (string) file_get_contents($shipped), 'HTTP ' . $anon->status . ' ' . strlen($css) . ' bytes');
    file_put_contents("$WORKDIR/verify-v5.served-panel.css", $css);
    $moduleTree = tree_hash($MOD_NEW);
    // V2 G07 ("view-only settings has no save button", type="submit" anywhere) is superseded by the V5
    // top-bar mode buttons; its intent is rechecked here on the settings form alone.
    add_role('v5-settings-view', ['settings.view']); add_user('v5settings', 'settings@v5.test', 'v5-settings-view', $pw);
    $sv = login2('settings-view', 'settings@v5.test', $pw); $hs = page($sv, 'rolewarden/settings');
    $sub = 0; foreach (forms($hs) as $f) if (! str_contains($f['action'], 'appearance/mode')) $sub += substr_count($f['html'], 'type="submit"');
    check('G07v5', 'View-only settings: no submit button outside the top-bar mode switch (V2 G07 adapted to V5)', $sv->status === 200 && $sub === 0 && mode_form($hs) !== null, "HTTP {$sv->status} submits=$sub");

    // =====================================================================================
    echo "== 2 Tokens in the served stylesheet ==\n";
    $p = proc_open(['python', __DIR__ . '/../verify-v5.css.py', "$WORKDIR/verify-v5.served-panel.css"], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    $A = json_decode(stream_get_contents($pipes[1]), true); $err = stream_get_contents($pipes[2]); proc_close($p);
    if (! is_array($A)) throw new RuntimeException('css audit failed: ' . $err);
    file_put_contents("$WORKDIR/verify-v5.css-audit.json", json_encode($A, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $used = array_keys($A['uses']); sort($used);
    info('Stylesheet: ' . $A['rules'] . ' rules, ' . count($A['token_names']) . ' custom properties defined, ' . count($used) . ' read by components');
    check('T01', 'No component rule reads a primitive (--p-*)', $A['primitive_reads_in_components'] === [], json_encode($A['primitive_reads_in_components']));
    check('T02', 'Semantic tokens are mapped to primitives only through the per-theme --l-/--d- pairs', $A['primitive_reads_by_non_mode_tokens'] === [], json_encode($A['primitive_reads_by_non_mode_tokens']));
    check('T03', 'Every custom property read by a component is defined in the stylesheet', array_values(array_diff($used, $A['token_names'])) === [], json_encode(array_values(array_diff($used, $A['token_names']))));
    // Instrument correction after the first development run: --tw-* are Tailwind's own filter plumbing
    // (the generated .filter utility, every one 'initial'), not theme values; only palettes count here.
    $tw = array_values(array_filter($used, fn ($u) => str_starts_with($u, '--p-') || str_starts_with($u, '--color-')));
    info('Tailwind internal variables read by utilities: ' . json_encode(array_values(array_filter($used, fn ($u) => str_starts_with($u, '--tw-')))));
    check('T04', 'Components read neither primitives nor Tailwind palette variables', $tw === [], json_encode($tw));
    // Declared exceptions (design guide): widths of single table columns and icon geometry.
    $icon = '/(svg|\.rw-mark|\.rw-check:before|\.rw-check:indeterminate:before|\.rw-status:before|\.rw-cell--changed:after|\.rw-tree-branch)/';
    $rawIn = []; $iconRaw = [];
    foreach ($A['raw_in_token_groups'] as $r) { if (preg_match($icon, $r['sel'])) $iconRaw[] = $r; else $rawIn[] = $r; }
    info('Raw values in token groups on icon geometry (exempt): ' . json_encode(array_map(fn ($r) => $r['sel'] . ' {' . $r['prop'] . ':' . $r['value'] . '}', $iconRaw), JSON_UNESCAPED_SLASHES));
    check('T05', 'Component rules write no raw colour, spacing, type, radius, border width, shadow or motion value outside the declared exceptions',
        $rawIn === [], implode("\n            ", array_map(fn ($r) => '[' . $r['group'] . '] ' . $r['sel'] . ' { ' . $r['prop'] . ': ' . $r['value'] . ' }', $rawIn)));
    $cols = '/(\.rw-c-|\.rw-m-|\.rw-l-|\.rw-anno|\.rw-cell\b)/';
    $layout = array_values(array_filter($A['raw_outside_token_groups'], fn ($r) => ! preg_match($cols, $r['sel']) && ! preg_match($icon, $r['sel']) && ! preg_match('/\.rw-select$/', $r['sel'])
        && in_array($r['prop'], ['width', 'max-width', 'min-width', 'height', 'grid-template-columns'], true) && ! str_contains($r['value'], '100vh')));
    if ($layout) amb('T06', 'Fixed sizes that are neither table columns nor icons (dialog, form, toasts, sign-in sheet, search, labels...)', implode('; ', array_map(fn ($r) => $r['sel'] . ' ' . $r['prop'] . ':' . $r['value'], $layout)));
    $other = array_values(array_filter($A['raw_outside_token_groups'], fn ($r) => ! in_array($r['prop'], ['width', 'max-width', 'min-width', 'height', 'grid-template-columns'], true) || str_contains($r['value'], '100vh')));
    info('Other plain values outside the token groups (offsets, letter-spacing, keyframes...): ' . json_encode(array_map(fn ($r) => $r['sel'] . ' {' . $r['prop'] . ':' . $r['value'] . '}', $other), JSON_UNESCAPED_SLASHES));
    // Each theme block defines the same set of per-mode colours and shape tokens.
    $sets = [];
    foreach ($A['token_defs'] as $name => $defs) foreach ($defs as [$ctx, $sel, $v]) foreach (THEMES as $t) if (preg_match('/data-rw-theme="?' . $t . '"?\]/', $sel)) $sets[$t][] = $name;
    foreach ($sets as &$s) { $s = array_values(array_unique($s)); sort($s); } unset($s);
    check('T07', 'Console, Clarity and Contrast define the same tokens (light and dark pairs, type, size, shape)', count($sets) === 3 && $sets['console'] === $sets['clarity'] && $sets['console'] === $sets['contrast'], json_encode(array_map('count', $sets)));
    $pairs = array_filter($sets['console'] ?? [], fn ($n) => str_starts_with($n, '--l-'));
    $missingDark = array_values(array_filter($pairs, fn ($n) => ! in_array('--d-' . substr($n, 4), $sets['console'], true)));
    check('T08', 'Every light colour token has its dark counterpart', $missingDark === [], json_encode($missingDark));

    // =====================================================================================
    echo "== 3 Light and dark per user ==\n";
    $h = page($adm, 'rolewarden/users'); $mf = mode_form($h);
    $opts = $mf && preg_match_all('/<button[^>]*name="mode" value="(\w+)"/', $mf['html'], $mm) ? $mm[1] : [];
    check('M01', 'Top bar offers System, Light and Dark with a CSRF token', $opts === ['system', 'light', 'dark'] && ! empty($mf['fields']['csrf_test_name']), json_encode($opts));
    check('M02', 'Without a choice: no data-rw-mode on <html> (follows the system), System pressed', html_mode($h) === null && pressed($h) === 'system');
    $ctxRows = fn (int $id) => q("SELECT class, `key`, value, context FROM settings WHERE context LIKE ?", ['%' . $id . '%']);
    info('Settings rows for admin before any choice: ' . json_encode($ctxRows($U['admin2']['id'])));
    set_mode($adm, 'dark'); $st = $adm->status; $h = after($adm)->body;
    check('M03', 'Choosing Dark: redirect back, <html data-rw-mode="dark">, Dark pressed', in_array($st, [302, 303], true) && html_mode($h) === 'dark' && pressed($h) === 'dark', "HTTP $st mode=" . json_encode(html_mode($h)));
    $rows = $ctxRows($U['admin2']['id']);
    info('Settings rows for admin after Dark: ' . json_encode($rows));
    check('M04', 'The choice is stored with the Settings library in the user context (no new table)', count($rows) >= 1 && str_contains(json_encode($rows), 'dark') && tables() === tables(), json_encode($rows));
    $pagesDark = [];
    foreach (['rolewarden/users', 'rolewarden/roles', 'rolewarden/settings', 'rolewarden/profile', 'rolewarden/activity', 'rolewarden/appearance'] as $u) $pagesDark[$u] = html_mode(page($adm, $u));
    check('M05', 'Dark applies to every panel page of that user', array_unique(array_values($pagesDark)) === ['dark'], json_encode($pagesDark));
    $adm2 = login2('admin-again', $U['admin2']['email'], $pw);
    check('M06', 'Persistent: a new sign-in of the same user gets Dark', html_mode(page($adm2, 'rolewarden/profile')) === 'dark');
    $u2 = login2('second', $U2['email'], $pw);
    $h2 = page($u2, 'rolewarden/profile');
    check('M07', 'Independent: another user (no permissions) still follows the system', $u2->status === 200 && html_mode($h2) === null && pressed($h2) === 'system', 'HTTP ' . $u2->status);
    set_mode($u2, 'light'); $h2 = after($u2)->body;
    check('M08', 'A user without any permission can choose Light from the profile top bar', html_mode($h2) === 'light');
    check('M09', 'Independent: admin keeps Dark after the other user chose Light', html_mode(page($adm, 'rolewarden/users')) === 'dark');
    $own = login2('owner', $U['owner']['email'], $pw);
    check('M09b', 'Independent: a third user still has no mode attribute', html_mode(page($own, 'rolewarden/users')) === null);
    $anon2 = new Client('v5-anon-mode');
    check('M10', 'Signed-out login page carries no user mode (follows the system)', html_mode(page($anon2, 'login')) === null);
    foreach (['purple', '', 'DARK'] as $bad) {
        set_mode($adm, $bad); $st = $adm->status; $hb = in_array($st, [302, 303], true) ? after($adm)->body : $adm->body;
        check('M11-' . ($bad === '' ? 'empty' : $bad), "Invalid mode '$bad' is not stored (admin stays Dark), no 500", $st !== 500 && html_mode(page($adm, 'rolewarden/users')) === 'dark' && ! leaks_sql($hb), "HTTP $st");
    }
    $f = mode_form(page($adm, 'rolewarden/users'));
    raw_post($adm, $f['action'], http_build_query(['csrf_test_name' => $f['fields']['csrf_test_name']]) . '&mode[]=light');
    check('M12', 'Mode as array: rejected without 500, admin stays Dark', $adm->status !== 500 && html_mode(page($adm, 'rolewarden/users')) === 'dark' && ! preg_match('/Array to string|TypeError/', clean_html($adm->body)), 'HTTP ' . $adm->status);
    set_mode($adm, 'light', false); $st = $adm->status;
    check('M13', 'Mode POST without CSRF token is refused (admin stays Dark)', html_mode(page($adm, 'rolewarden/users')) === 'dark' && $st !== 500, "HTTP $st");
    set_mode($adm, 'system'); after($adm);
    check('M14', 'Back to System: the attribute disappears', html_mode(page($adm, 'rolewarden/users')) === null && pressed($adm->body) === 'system');
    // Browser: system preference followed only without a choice.
    $base = HOST . 'index.php/';
    $r = browser([
        ['op' => 'emulate', 'scheme' => 'dark'], ['op' => 'goto', 'url' => $base . 'login', 'tag' => 'login-dark'], ['op' => 'eval', 'tag' => 'login-dark-state', 'expr' => JS_STATE],
        ['op' => 'emulate', 'scheme' => 'light'], ['op' => 'eval', 'tag' => 'login-light-state', 'expr' => JS_STATE],
        ['op' => 'login', 'url' => $base . 'login', 'email' => $U2['email'], 'password' => $pw, 'tag' => 'login-u2'],
        ['op' => 'emulate', 'scheme' => 'dark'], ['op' => 'goto', 'url' => $base . 'rolewarden/profile', 'tag' => 'u2-profile'], ['op' => 'eval', 'tag' => 'u2-light-under-dark-os', 'expr' => JS_STATE],
        ['op' => 'click', 'selector' => 'form.rw-mode button[value=system]', 'nav' => true, 'tag' => 'u2-system'], ['op' => 'eval', 'tag' => 'u2-system-dark-os', 'expr' => JS_STATE],
        ['op' => 'emulate', 'scheme' => 'light'], ['op' => 'eval', 'tag' => 'u2-system-light-os', 'expr' => JS_STATE],
        ['op' => 'click', 'selector' => 'form.rw-mode button[value=dark]', 'nav' => true, 'tag' => 'u2-dark'], ['op' => 'eval', 'tag' => 'u2-dark-light-os', 'expr' => JS_STATE],
    ], 'mode');
    $g = fn ($t) => by_tag($r, $t)['value'] ?? [];
    $consoleL = '#fafaf9'; $consoleD = '#1c1d20'; // Console --l-surface / --d-surface as served (graphite-25 / graphite-900)
    check('M15', 'Login without a choice: dark OS -> dark surface; light OS -> light surface', rgb_hex($g('login-dark-state')['surface'] ?? '') === $consoleD && rgb_hex($g('login-light-state')['surface'] ?? '') === $consoleL, json_encode([$g('login-dark-state')['surface'] ?? null, $g('login-light-state')['surface'] ?? null]));
    check('M16', 'A user who chose Light stays light under a dark OS', rgb_hex($g('u2-light-under-dark-os')['surface'] ?? '') === $consoleL && ($g('u2-light-under-dark-os')['mode'] ?? null) === 'light');
    check('M17', 'Top bar System button (clicked in the browser) returns to following the OS (dark, then light)', array_key_exists('mode', $g('u2-system-dark-os')) && $g('u2-system-dark-os')['mode'] === null && rgb_hex($g('u2-system-dark-os')['surface'] ?? '') === $consoleD && rgb_hex($g('u2-system-light-os')['surface'] ?? '') === $consoleL, json_encode([$g('u2-system-dark-os'), $g('u2-system-light-os')]));
    check('M18', 'Top bar Dark button under a light OS: dark surface and text', ($g('u2-dark-light-os')['mode'] ?? null) === 'dark' && rgb_hex($g('u2-dark-light-os')['surface'] ?? '') === $consoleD, json_encode($g('u2-dark-light-os')));
    set_mode($u2, 'system');
    logs_take('mode');

    // =====================================================================================
    echo "== 4 Customiser over HTTP: validation, save, file, download, permission, reset ==\n";
    $h = page($adm, 'rolewarden/appearance'); $af = ap_form($adm);
    $bases = preg_match_all('/name="base" value="([^"]+)"/', $h, $mm) ? $mm[1] : [];
    $colourFields = array_values(array_filter(array_keys($af['fields']), fn ($k) => str_starts_with($k, 'colors[')));
    info('Customiser fields: ' . json_encode(array_keys($af['fields'])));
    check('C01', 'Customiser form: starting theme (3 themes), light/dark background, text and accent, radius, density, CSRF',
        $bases === THEMES && count(array_intersect(['colors[light][surface]', 'colors[light][ink]', 'colors[light][accent]', 'colors[dark][surface]', 'colors[dark][ink]', 'colors[dark][accent]'], $colourFields)) === 6
        && array_key_exists('radius', $af['fields']) && array_key_exists('density', $af['fields']) && ! empty($af['fields']['csrf_test_name']), json_encode($bases));
    check('C01b', 'The page names the theme file location and offers a download', str_contains($h, 'writable/rolewarden/theme.json') && str_contains($h, 'appearance/download'));
    check('C01c', 'No theme file before the first save', tf() === null);
    $invalid = [
        ['base', ['base' => 'neon']], ['colors[light][surface]', ['colors[light][surface]' => 'red']], ['colors[light][ink]', ['colors[light][ink]' => '#12345']],
        ['colors[dark][accent]', ['colors[dark][accent]' => '#gggggg']], ['colors[light][accent]', ['colors[light][accent]' => '#fff;}body{display:none}']],
        ['colors[dark][surface]', ['colors[dark][surface]' => 'expression(alert(1))']], ['radius', ['radius' => '5']], ['radius', ['radius' => '-4']],
        ['radius', ['radius' => '8px;}*{color:red}']], ['density', ['density' => 'huge']],
    ];
    foreach ($invalid as $i => [$field, $vals]) {
        $hb = save_theme($adm, $vals + ['base' => $vals['base'] ?? 'clarity']);
        $err = error_under($hb, $field);
        check('C02-' . $i, 'Invalid ' . json_encode($vals) . ': nothing saved, error under the control, no 500', tf() === null && $adm->status !== 500 && $err && ! leaks_sql($hb),
            'file=' . json_encode(tf() !== null) . ' error_under=' . json_encode($err) . ' text=' . substr(trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/<script.*?<\/script>/s', '', clean_html($hb))))), 0, 300));
        if (tf() !== null) @unlink(theme_file());
    }
    // A derived colour has no visible control (hidden field filled by the script): only "not stored" is asserted.
    $hb = save_theme($adm, ['base' => 'clarity', 'colors[light][accent-hover]' => 'url(javascript:alert(1))']);
    info('Invalid derived colour (hidden field): saved=' . json_encode(tf()) . ' toasts=' . json_encode(static_toasts($hb)) . ' field errors=' . json_encode(substr_count($hb, 'class="rw-field-error"')));
    check('C02-10', 'Invalid derived colour (hidden accent-hover field): nothing stored, no 500', tf() === null && $adm->status !== 500);
    if (tf() !== null) @unlink(theme_file());
    foreach ([['base[]=clarity', 'base'], ['colors[light][surface][]=%23ffffff', 'colors'], ['radius[]=8', 'radius'], ['colors=1', 'colors scalar']] as $i => [$q, $lab]) {
        $f = ap_form($adm); raw_post($adm, $f['action'], http_build_query(['csrf_test_name' => $f['fields']['csrf_test_name'], 'base' => 'console']) . '&' . $q);
        $hb = in_array($adm->status, [302, 303], true) ? follow($adm) : $adm->body;
        info("Field as array/scalar ($lab): HTTP {$adm->status}, saved " . json_encode(tf()) . ', toasts ' . json_encode(static_toasts($hb)));
        check('C03-' . $i, "Field as array/scalar ($lab): no 500, no PHP error on screen, never stored as given", $adm->status !== 500 && ! str_contains((string) tf(), '#ffffff') && ! preg_match('/Array to string|TypeError|ErrorException/', clean_html($hb)), 'HTTP ' . $adm->status);
        if (tf() !== null) @unlink(theme_file());
    }
    $mine = ['base' => 'clarity', 'colors[light][surface]' => '#fdfdfd', 'colors[light][ink]' => '#111111', 'colors[light][accent]' => '#0b6e8a',
        'colors[dark][surface]' => '#101418', 'colors[dark][ink]' => '#eeeeee', 'colors[dark][accent]' => '#6cc3dc', 'radius' => '8', 'density' => 'comfortable'];
    $hb = save_theme($adm, $mine); $st = $adm->status;
    $json = tf(); $data = json_decode((string) $json, true);
    info('Saved theme file: ' . $json);
    check('C04', 'Valid save: theme file written in writable/rolewarden/theme.json', is_array($data), 'HTTP ' . $st);
    check('C04b', 'Valid save: success toast, no field error', toast_ok(static_toasts($hb), 'success') && ! str_contains($hb, 'class="rw-field-error"'), json_encode(static_toasts($hb)));
    $flat = json_encode($data);
    check('C04c', 'The file holds the starting theme and every value chosen', is_array($data) && str_contains($flat, 'clarity') && str_contains($flat, '#fdfdfd') && str_contains($flat, '#111111') && str_contains($flat, '#0b6e8a') && str_contains($flat, '#101418') && str_contains($flat, '#eeeeee') && str_contains($flat, '#6cc3dc') && str_contains($flat, 'comfortable') && preg_match('/"radius":\s*"?8"?/', (string) $json), $flat);
    check('C04d', 'The module folder is untouched by the save (no theme written inside the module)', tree_hash($MOD_NEW) === $moduleTree);
    $h = page($adm, 'rolewarden/users'); $tl = theme_link($h);
    check('C05', 'Panel pages switch to the saved starting theme and link the theme stylesheet', html_theme($h) === 'clarity' && $tl !== null, json_encode([html_theme($h), $tl]));
    $tc = new Client('v5-themecss'); $tc->req('GET', (string) $tl); $themeCss = $tc->body;
    file_put_contents("$WORKDIR/verify-v5.served-theme.css", $themeCss);
    info('Served theme stylesheet: HTTP ' . $tc->status . ' ' . ($tc->headers['content-type'] ?? '') . ' ' . json_encode($themeCss));
    check('C05b', 'The theme stylesheet is served as CSS (also to a signed-out client) with the saved values', $tc->status === 200 && str_contains($tc->headers['content-type'] ?? '', 'text/css') && str_contains($themeCss, '#fdfdfd') && str_contains($themeCss, '#6cc3dc') && str_contains($themeCss, '8px'));
    $nonCustom = []; preg_match_all('/\{([^{}]*)\}/', $themeCss, $blocks);
    foreach ($blocks[1] as $b) { $rest = trim(preg_replace('/--[\w-]+\s*:[^;]*;?/', '', $b)); if ($rest !== '') $nonCustom[] = $rest; }
    check('C05c', 'The theme stylesheet sets only custom properties', $themeCss !== '' && $blocks[1] !== [] && $nonCustom === [], json_encode($nonCustom));
    $views = [];
    foreach (['subject' => login2('subject', $U['subject']['email'], $pw), 'owner' => $own] as $k => $c) $views[$k] = html_theme(page($c, 'rolewarden/profile'));
    $lp = page(new Client('v5-anon-theme'), 'login');
    check('C06', 'The theme is global: other users and the signed-out login page get it too', $views === ['subject' => 'clarity', 'owner' => 'clarity'] && html_theme($lp) === 'clarity' && theme_link($lp) !== null, json_encode([$views, html_theme($lp), theme_link($lp)]));
    $af2 = ap_form($adm);
    check('C06b', 'Reopening the customiser shows the saved starting theme, radius and density', preg_match('/name="base" value="clarity"[^>]*checked/', $adm->body) && preg_match('/<option value="8" selected/', $adm->body) && preg_match('/<option value="comfortable" selected/', $adm->body));
    $dl = new Client('v5-dl'); $dl->jar = $adm->jar; $dl->req('GET', 'rolewarden/appearance/download');
    info('Download: HTTP ' . $dl->status . ' headers ' . json_encode(array_intersect_key($dl->headers, array_flip(['content-type', 'content-disposition']))));
    check('C07', 'Download: attachment named theme.json with the saved theme', $dl->status === 200 && preg_match('/attachment;.*theme\.json/i', $dl->headers['content-disposition'] ?? '') && json_decode($dl->body, true) == $data, substr($dl->body, 0, 200));
    $lim = login2('limited', $U['limited']['email'], $pw);
    $limDl = new Client('v5-limdl'); $limDl->jar = $lim->jar; $limDl->req('GET', 'rolewarden/appearance/download');
    if ($limDl->status === 200) amb('C08a', 'Download of the theme file by a user without appearance.update', 'HTTP 200; SPEC says the customiser requires appearance.update and offers the download, it does not say whether the download alone is protected');
    else check('C08a', 'Download refused without appearance.update', $limDl->status !== 500, 'HTTP ' . $limDl->status);
    $before = tf();
    $f = $af2; $f['fields']['csrf_test_name'] = csrf_of(page($lim, 'rolewarden/profile')); submit($lim, $f, ['base' => 'contrast']);
    check('C08b', 'Direct save POST without appearance.update is refused, file unchanged', tf() === $before && ! csrf_refused($lim) && $lim->status !== 500, 'HTTP ' . $lim->status);
    $rf = reset_form(page($adm, 'rolewarden/appearance'));
    $rf2 = $rf; $rf2['fields']['csrf_test_name'] = csrf_of(page($lim, 'rolewarden/profile')); submit($lim, $rf2);
    check('C08c', 'Direct reset POST without appearance.update is refused, file kept', tf() === $before && ! csrf_refused($lim), 'HTTP ' . $lim->status);
    $h = page($adm, 'rolewarden/appearance');
    check('C09', 'Reset sits behind a confirmation: the reset form is only inside a <dialog>, the visible button opens it', (bool) preg_match('#<dialog[^>]*>(?:(?!</dialog>).)*appearance/reset(?:(?!</dialog>).)*</dialog>#s', $h) && substr_count($h, 'appearance/reset') === 1 && preg_match('/onclick="rwConfirm\(/', $h));
    $rf = reset_form($h); $noTok = $rf; unset($noTok['fields']['csrf_test_name']); submit($adm, $noTok);
    check('C09b', 'Reset without CSRF token is refused, file kept', tf() === $before && $adm->status !== 500, 'HTTP ' . $adm->status);
    submit($adm, reset_form(page($adm, 'rolewarden/appearance'))); $hb = after($adm)->body;
    $h = page($adm, 'rolewarden/users');
    check('C10', 'Confirmed reset: theme file deleted, panel back to Console, no theme stylesheet', tf() === null && html_theme($h) === 'console' && theme_link($h) === null, json_encode([tf() !== null, html_theme($h), theme_link($h)]));
    check('C10b', 'Reset shows a success toast', toast_ok(static_toasts($hb), 'success'), json_encode(static_toasts($hb)));
    $tc->req('GET', (string) $tl);
    info('Theme stylesheet after reset: HTTP ' . $tc->status . ' ' . json_encode(substr($tc->body, 0, 200)));
    check('C10c', 'After reset the theme stylesheet URL leaks nothing and does not fail', $tc->status !== 500 && ! leaks_sql($tc->body) && ! str_contains($tc->body, '#fdfdfd'));
    logs_take('customiser-http');

    // =====================================================================================
    echo "== 5 Customiser in the browser: live preview, WCAG AA warnings, save anyway ==\n";
    // Contrast reference computed here, independently of the served JS (WCAG 2.1 relative luminance).
    $bgL = $consoleL; $bgD = $consoleD;
    $below = $above = null;
    for ($v = 0; $v <= 255; $v++) { $hx = sprintf('#%02x%02x%02x', $v, $v, $v); $ra = ratio_hex($hx, $bgL); if ($ra >= 4.5) $above = [$hx, $ra]; elseif ($below === null && $ra < 4.5) $below = [$hx, $ra]; }
    info('Grey just under AA on Console light surface: ' . json_encode($below) . '; just over: ' . json_encode($above));
    $pairsDark = [];
    $app = $base . 'rolewarden/appearance';
    // The page as served (real behaviour), then the same script with the Alpine components registered
    // and started by the test after load (diagnostic for D1: what the customiser does once it runs).
    $MANUAL = "(() => { Alpine.data('rwToasts', rwToastsController); Alpine.data('rwAppearance', rwAppearanceController); const old = document.querySelector('form[x-data^=rwAppearance]'); const el = old.cloneNode(true); old.replaceWith(el); Alpine.initTree(el); return !!el._x_dataStack && typeof el._x_dataStack[0].warnings === 'function'; })()";
    $wsteps = fn (bool $manual) => [
        ['op' => 'login', 'url' => $base . 'login', 'email' => $U['admin2']['email'], 'password' => $pw, 'tag' => 'login'],
        ['op' => 'goto', 'url' => $base . 'rolewarden/profile'],
        ['op' => 'click', 'selector' => 'form.rw-mode button[value=light]', 'nav' => true, 'tag' => 'mode-light'],
        ['op' => 'goto', 'url' => $app, 'tag' => 'open'], ['op' => 'eval', 'tag' => 'manual', 'expr' => $manual ? $MANUAL : 'false', 'wait' => 200], ['op' => 'eval', 'tag' => 's0', 'expr' => JS_STATE],
        ['op' => 'eval', 'tag' => 'set-under', 'expr' => js_set_color('rw-light-ink', $below[0]), 'wait' => 150], ['op' => 'eval', 'tag' => 's1', 'expr' => JS_STATE],
        ['op' => 'eval', 'tag' => 'set-over', 'expr' => js_set_color('rw-light-ink', $above[0]), 'wait' => 150], ['op' => 'eval', 'tag' => 's2', 'expr' => JS_STATE],
        ['op' => 'eval', 'tag' => 'set-light-bad', 'expr' => js_set_color('rw-light-ink', '#c8c8c8'), 'wait' => 150], ['op' => 'eval', 'tag' => 's3', 'expr' => JS_STATE],
        ['op' => 'eval', 'tag' => 'set-light-good', 'expr' => js_set_color('rw-light-ink', '#111111'), 'wait' => 150],
        ['op' => 'eval', 'tag' => 'set-dark-bad', 'expr' => js_set_color('rw-dark-ink', '#3a3a3a'), 'wait' => 150], ['op' => 'eval', 'tag' => 's4', 'expr' => JS_STATE],
        ['op' => 'eval', 'tag' => 'set-dark-good', 'expr' => js_set_color('rw-dark-ink', '#eeeeee'), 'wait' => 150],
        ['op' => 'eval', 'tag' => 'set-accent-pale', 'expr' => js_set_color('rw-light-accent', '#a0c4ff'), 'wait' => 150], ['op' => 'eval', 'tag' => 's5', 'expr' => JS_STATE],
        ['op' => 'eval', 'tag' => 'set-accent-ok', 'expr' => js_set_color('rw-light-accent', '#0b4f8a'), 'wait' => 150], ['op' => 'eval', 'tag' => 's6', 'expr' => JS_STATE],
        // Background dark in light mode with a light text: every pair with the background passes.
        ['op' => 'eval', 'tag' => 'set-bg-dark', 'expr' => js_set_color('rw-light-surface', '#111111'), 'wait' => 150],
        ['op' => 'eval', 'tag' => 'set-ink-white', 'expr' => js_set_color('rw-light-ink', '#f0f0f0'), 'wait' => 150], ['op' => 'eval', 'tag' => 's7', 'expr' => JS_STATE],
        ['op' => 'eval', 'tag' => 'set-bg-back', 'expr' => js_set_color('rw-light-surface', '#fafaf9'), 'wait' => 150],
        ['op' => 'eval', 'tag' => 'set-ink-back', 'expr' => js_set_color('rw-light-ink', '#111111'), 'wait' => 150],
        // Live preview of shape and starting theme.
        ['op' => 'eval', 'tag' => 'radius', 'expr' => "(() => { const s = document.getElementById('rw-radius'); s.value = '8'; s.dispatchEvent(new Event('change', { bubbles: true })); return s.value; })()", 'wait' => 150],
        ['op' => 'eval', 'tag' => 'density', 'expr' => "(() => { const s = document.getElementById('rw-density'); s.value = 'comfortable'; s.dispatchEvent(new Event('change', { bubbles: true })); return s.value; })()", 'wait' => 150],
        ['op' => 'eval', 'tag' => 's8', 'expr' => JS_STATE],
        ['op' => 'eval', 'tag' => 'base', 'expr' => "(() => { const r = document.querySelector('input[name=base][value=contrast]'); r.click(); return r.checked; })()", 'wait' => 200],
        ['op' => 'eval', 'tag' => 's9', 'expr' => JS_STATE],
        // A warning does not block saving: a low-contrast text colour is saved anyway.
        ['op' => 'eval', 'tag' => 'set-save-bad', 'expr' => js_set_color('rw-light-ink', '#c8c8c8'), 'wait' => 150], ['op' => 'eval', 'tag' => 's10', 'expr' => JS_STATE],
        ['op' => 'click', 'selector' => 'form[action$="/appearance"] button[type=submit]', 'nav' => true, 'tag' => 'save'],
        ['op' => 'eval', 'tag' => 's11', 'expr' => JS_STATE],
        ['op' => 'screenshot', 'path' => str_replace('\\', '/', "$WORKDIR/verify-v5.customiser-" . ($manual ? 'started' : 'served') . '.png'), 'tag' => 'shot'],
        // Reset needs the dialog: clicking the visible button only opens it.
        ['op' => 'click', 'selector' => 'button[onclick*=confirm-reset-theme]', 'tag' => 'reset-open'],
        ['op' => 'eval', 'tag' => 'dialog', 'expr' => "document.getElementById('confirm-reset-theme').open"],
        ['op' => 'click', 'selector' => '#confirm-reset-theme button.rw-btn--ghost', 'tag' => 'reset-cancel'],
        ['op' => 'eval', 'tag' => 'dialog-closed', 'expr' => "document.getElementById('confirm-reset-theme').open"],
    ];
    $r0 = browser($wsteps(false), 'customiser-served');
    $s0 = fn ($t) => by_tag($r0, $t)['value'] ?? [];
    info('Served customiser states: ' . json_encode(array_map(fn ($t) => [$t => array_intersect_key((array) $s0($t), array_flip(['theme', 'warn', 'bar', 'ink', 'radius']))], ['s0', 's1', 's3', 's8', 's9']), JSON_UNESCAPED_SLASHES));
    $first = fn (array $errs) => array_map(fn ($e) => strtok($e, "\n"), array_slice($errs, 0, 4));
    check('W00', 'As served, the customiser starts in the browser: all-clear message, a low-contrast text is flagged, the preview follows the choice',
        str_contains((string) ($s0('s0')['bar'] ?? ''), 'WCAG AA') && ($s0('s1')['warn'] ?? false) === true && rgb_hex((string) ($s0('s3')['ink'] ?? '')) === '#c8c8c8',
        'bar=' . json_encode($s0('s0')['bar'] ?? null) . ' warn=' . json_encode($s0('s1')['warn'] ?? null) . ' ink after pick=' . json_encode($s0('s3')['ink'] ?? null) . ' console: ' . json_encode($first($r0['consoleErrors'] ?? [])));
    check('W14', 'No JavaScript error on the customiser page as served', ($r0['consoleErrors'] ?? ['?']) === [] && $r0['errors'] === [], json_encode($first($r0['consoleErrors'] ?? [])));
    // Instrument correction (second development run): the served run's Save posts base=contrast with no
    // colours, so the theme is reset to Console here; the contrast references below assume Console.
    info('Theme file left by the served run: ' . json_encode(tf()));
    @unlink(theme_file());
    $r = browser($wsteps(true), 'customiser-started');
    $s = fn ($t) => by_tag($r, $t)['value'] ?? [];
    info('Component started by the test: ' . json_encode(by_tag($r, 'manual')['value'] ?? null) . '; console: ' . json_encode($first($r['consoleErrors'] ?? [])));
    info('Customiser states: ' . json_encode(array_map(fn ($t) => [$t => array_intersect_key((array) $s($t), array_flip(['theme', 'mode', 'warn', 'bar', 'ink', 'surface', 'radius', 'row', 'btnRadius', 'body', 'input', 'nav']))], ['s0', 's1', 's2', 's3', 's4', 's5', 's6', 's7', 's8', 's9', 's10', 's11']), JSON_UNESCAPED_SLASHES));
    check('W01', 'Console defaults: no warning, the all-clear message is shown', ($s('s0')['warn'] ?? true) === false && str_contains((string) ($s('s0')['bar'] ?? ''), 'WCAG AA'), json_encode($s('s0')['bar'] ?? null));
    $exp = sprintf('%.1f', $below[1]);
    check('W02', "Text just under AA ({$below[0]}, {$exp}:1 by our own computation) is flagged with its ratio", ($s('s1')['warn'] ?? false) === true && str_contains((string) $s('s1')['bar'], 'Text (Light)') && str_contains((string) $s('s1')['bar'], $exp . ':1'), json_encode($s('s1')['bar'] ?? null));
    check('W03', "Text just over AA ({$above[0]}, " . sprintf('%.2f', $above[1]) . ':1) is not flagged', ($s('s2')['warn'] ?? true) === false, json_encode($s('s2')['bar'] ?? null));
    check('W04', 'Live preview: the chosen light text colour is applied to the page as it is picked', rgb_hex((string) ($s('s3')['ink'] ?? '')) === '#c8c8c8' && ($s('s3')['warn'] ?? false) === true);
    check('W05', 'A dark-mode text colour under AA is flagged as Dark', str_contains((string) ($s('s4')['bar'] ?? ''), 'Text (Dark)'), json_encode($s('s4')['bar'] ?? null));
    check('W06', 'A pale accent under AA on the background is flagged', str_contains((string) ($s('s5')['bar'] ?? ''), 'Accent (Light)'), json_encode($s('s5')['bar'] ?? null));
    check('W07', 'Accent preview applied and hover, tint and text-on-accent derived', rgb_hex((string) ($s('s6')['accent'] ?? '')) === '#0b4f8a' && isset($s('s6')['hidden']['colors[light][accent-hover]'], $s('s6')['hidden']['colors[light][on-accent]']), json_encode($s('s6')['hidden'] ?? null));
    $in = $s('s7')['input'] ?? null; $nv = $s('s7')['nav'] ?? null;
    $inRatio = $in ? ratio_hex(rgb_hex($in[0]), rgb_hex($in[1])) : 0; $navRatio = $nv ? ratio_hex(rgb_hex($nv[0]), rgb_hex($nv[1])) : 0;
    info(sprintf('Dark background #111111 + light text #f0f0f0 in light mode: warnings=%s; sample field text %s on %s = %.2f:1; sidebar link %s on %s = %.2f:1', json_encode($s('s7')['warn'] ?? null), $in[0] ?? '?', $in[1] ?? '?', $inRatio, $nv[0] ?? '?', $nv[1] ?? '?', $navRatio));
    info('W08 note: the warnings list compares only against the background (surface); surface-raised/sunk are not customisable and not compared. The warning shown here comes from: ' . json_encode($s('s7')['bar'] ?? null));
    check('W08', 'With a changed background, a warning is shown whenever field or sidebar text falls under AA',
        ! ($inRatio < 4.5 || $navRatio < 4.5) || ($s('s7')['warn'] ?? false) === true,
        sprintf('no warning shown, yet field text %.2f:1 and sidebar text %.2f:1', $inRatio, $navRatio));
    check('W09', 'Live preview of radius and density: tokens and a button change on the page', ($s('s8')['radius'] ?? '') === '8px' && ($s('s8')['btnRadius'] ?? '') === '8px' && ($s('s8')['row'] ?? '') === '52px', json_encode(array_intersect_key((array) $s('s8'), array_flip(['radius', 'btnRadius', 'row', 'control']))));
    check('W10', 'Live preview of the starting theme: <html data-rw-theme> and the theme values switch to Contrast', ($s('s9')['theme'] ?? '') === 'contrast' && ($s('s9')['sunk'] ?? '') !== ($s('s8')['sunk'] ?? ''), json_encode([$s('s8')['sunk'] ?? null, $s('s9')['sunk'] ?? null]));
    // Instrument correction (third development run): the light background is still overridden (set back
    // to #fafaf9, not cleared), so --l-surface rightly stays; a token the form does not override is compared.
    $saved = json_decode((string) tf(), true);
    $savedOk = is_array($saved);
    check('W11', 'You can still save with a warning: the low-contrast colour is in the theme file', ($s('s10')['warn'] ?? false) === true && is_array($saved) && str_contains(json_encode($saved), '#c8c8c8') && str_contains(json_encode($saved), 'contrast'), (string) tf());
    check('W12', 'After saving, the reloaded page shows the saved theme (theme stylesheet applied)', ($s('s11')['theme'] ?? '') === 'contrast' && rgb_hex((string) ($s('s11')['ink'] ?? '')) === '#c8c8c8', json_encode($s('s11')));
    check('W13', 'Reset button opens a confirmation dialog; Cancel closes it without resetting', (by_tag($r, 'dialog')['value'] ?? false) === true && (by_tag($r, 'dialog-closed')['value'] ?? true) === false && tf() !== null);
    // Known theme for the next phases, saved over HTTP (also shows a low-contrast colour is accepted).
    save_theme($adm, ['base' => 'contrast', 'colors[light][ink]' => '#c8c8c8'] + $mine);
    $saved = json_decode((string) tf(), true); $savedTheme = tf();
    check('W15', 'A low-contrast text colour is accepted by a plain save too (a warning, never a refusal)', is_array($saved) && str_contains((string) $savedTheme, '#c8c8c8') && str_contains((string) $savedTheme, 'contrast'), (string) $savedTheme);
    logs_take('customiser-browser');

    // =====================================================================================
    echo "== 6 The theme survives a module update ==\n";
    $themeCssBefore = (new Client('v5-tcss-1'))->req('GET', (string) theme_link(page($adm, 'rolewarden/users')))->body;
    server_stop(); use_module($MOD_AGAIN); spark('migrate', '-n', 'RoleWarden'); server_start();
    $h = page($adm, 'rolewarden/users');
    check('S01', 'Module folder replaced again (fresh extraction of d3c7dbb): theme file byte-identical', tf() === $savedTheme && $savedTheme !== null);
    $tl2 = theme_link($h); $themeCssAfter = (new Client('v5-tcss-2'))->req('GET', (string) $tl2)->body;
    check('S02', 'After the update the panel still shows the saved theme with the same values', html_theme($h) === 'contrast' && $themeCssAfter === $themeCssBefore && str_contains($themeCssAfter, '#c8c8c8'), json_encode([html_theme($h), strlen($themeCssBefore), strlen($themeCssAfter)]));
    check('S03', 'The replaced module folder contains no theme file (the customisation lives in the application)', ! is_file("$MOD_AGAIN/writable/rolewarden/theme.json") && tree_hash($MOD_NEW) === $moduleTree);
    // Back to the first extraction for the rest of the run.
    server_stop(); use_module($MOD_NEW); server_start();
    logs_take('update');

    // =====================================================================================
    echo "== 7 app/Config/RoleWarden/theme.json wins; hand-written malicious files ==\n";
    @mkdir(dirname(app_theme_file()), 0777, true);
    $appTheme = $saved; // same structure as a downloaded file
    $walk = function (array $a, callable $fn) use (&$walk) { foreach ($a as $k => $v) $a[$k] = is_array($v) ? $walk($v, $fn) : $fn($k, $v); return $a; };
    $appTheme = $walk($appTheme, fn ($k, $v) => $v === 'contrast' ? 'clarity' : ($v === '#c8c8c8' ? '#222222' : $v));
    file_put_contents(app_theme_file(), json_encode($appTheme, JSON_PRETTY_PRINT));
    $writableBefore = tf();
    $h = page($adm, 'rolewarden/users'); $tlA = theme_link($h);
    $cssA = (new Client('v5-tcss-a'))->req('GET', (string) $tlA)->body;
    check('A01', 'With app/Config/RoleWarden/theme.json present, its theme is shown (it wins over writable/)', html_theme($h) === 'clarity' && str_contains($cssA, '#222222') && ! str_contains($cssA, '#c8c8c8'), json_encode([html_theme($h), $cssA]));
    $h = page($adm, 'rolewarden/appearance');
    $canSave = (bool) preg_match('/<button[^>]*type="submit"[^>]*>\s*Save theme/', $h);
    info('Customiser with an app theme file: HTTP ' . $adm->status . ', Save button ' . json_encode($canSave) . ', reset form ' . json_encode(reset_form($h) !== null) . ', text: ' . substr(trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/<script.*?<\/script>/s', '', preg_replace('/^.*?<main[^>]*>/s', '', clean_html($h)))))), 0, 400));
    check('A02', 'The panel does not let anyone change it: no Save button and no reset form', $adm->status === 200 && ! $canSave && reset_form($h) === null);
    $f = $af2; $f['fields']['csrf_test_name'] = csrf_of(page($adm, 'rolewarden/profile')); submit($adm, $f, ['base' => 'console', 'colors[light][surface]' => '#ff00ff', 'radius' => '0']);
    check('A03', 'A direct save POST does not change either theme file', tf() === $writableBefore && json_decode((string) file_get_contents(app_theme_file()), true) == $appTheme && $adm->status !== 500, 'HTTP ' . $adm->status);
    $rf['fields']['csrf_test_name'] = csrf_of(page($adm, 'rolewarden/profile')); submit($adm, $rf);
    check('A04', 'A direct reset POST deletes neither file', tf() === $writableBefore && is_file(app_theme_file()), 'HTTP ' . $adm->status);
    $dl->req('GET', 'rolewarden/appearance/download');
    info('Download with an app theme file: HTTP ' . $dl->status . ' body=' . substr($dl->body, 0, 120));
    @unlink(app_theme_file()); @rmdir(dirname(app_theme_file()));
    check('A05', 'Removing the app file gives back the writable/ theme', html_theme(page($adm, 'rolewarden/users')) === 'contrast');
    // Hand-written files with hostile values (both locations).
    $P = ['#fff;}body{display:none}/*', 'red</style><script>alert(1)</script>', 'url(javascript:alert(1))', 'expression(alert(1))', "#fff\n}html{background:url(http://evil.test/x)}", '#000;--surface:red', '8px;}*{color:red}', '"><img src=x onerror=alert(9)>'];
    $hostile = [];
    foreach ($P as $i => $pl) {
        $hostile[] = $walk($saved, fn ($k, $v) => $pl);
    }
    $hostile[] = ['base' => 'console"><script>alert(2)</script>', 'radius' => '8px;}*{color:red}', 'density' => 'compact;}', 'colors' => ['light' => ['surface' => '#fff;}body{display:none}', 'ink' => ['nested' => 'x'], 'evil' => '#000'], 'dark' => 'notanarray'], 'extra' => '<script>alert(3)</script>'];
    $hostile[] = ['base' => '../../../../etc/passwd'];
    $hostile[] = '{"base": "clarity", "colors": {';           // invalid JSON
    $hostile[] = "\xEF\xBB\xBF" . '{"base":"clarity"}' . str_repeat(' ', 10);  // BOM
    $hostile[] = str_repeat('{"a":', 600) . '1' . str_repeat('}', 600); // deep nesting
    foreach (['writable' => theme_file(), 'app' => app_theme_file()] as $where => $path) {
        @mkdir(dirname($path), 0777, true);
        foreach ($hostile as $i => $content) {
            file_put_contents($path, is_string($content) ? $content : json_encode($content, JSON_UNESCAPED_SLASHES));
            $c = new Client("v5-hostile-$where-$i"); $lpH = page($c, 'login'); $stL = $c->status;
            $hp = page($adm, 'rolewarden/users'); $stP = $adm->status; $hap = page($adm, 'rolewarden/appearance'); $stA = $adm->status;
            $tlH = theme_link($hp); $cssH = '';
            if ($tlH) { $c->req('GET', $tlH); $cssH = $c->body; $stC = $c->status; } else $stC = 0;
            $bad = [];
            if (in_array(500, [$stL, $stP, $stA, $stC], true)) $bad[] = "HTTP 500 ($stL/$stP/$stA/$stC)";
            foreach (['<', '>', '}body', '}html', '*{', 'javascript', 'expression', 'evil.test', 'display:none', 'url('] as $needle) if ($cssH !== '' && stripos($cssH, $needle) !== false) $bad[] = "theme css contains $needle";
            if (preg_match('/:\s*red\b/', $cssH)) $bad[] = 'theme css redefines a token with an injected value';
            foreach ([$lpH, $hp, $hap] as $pg) foreach (['<script>alert', '<img src=x', 'onerror=alert'] as $needle) if (str_contains(clean_html($pg), $needle)) $bad[] = "page contains $needle";
            foreach ([$lpH, $hp] as $pg) { $ta = html_theme($pg); if (! in_array($ta, THEMES, true)) $bad[] = 'data-rw-theme=' . json_encode($ta); }
            foreach ([$lpH, $hp, $hap, $cssH] as $pg) if (leaks_sql($pg) || preg_match('/(ErrorException|JsonException|TypeError|Stack trace|json_decode)/', clean_html($pg))) $bad[] = 'error text on screen';
            check("H-$where-$i", "Hand-written $where theme file #$i: no CSS/markup injection, no error page", $bad === [], implode('; ', $bad) . ' css=' . json_encode(substr($cssH, 0, 240)));
        }
        @unlink($path);
    }
    @rmdir(dirname(app_theme_file()));
    $hl = logs_take('hostile');
    $php = array_values(array_filter($hl, fn ($l) => preg_match('/^(ERROR|CRITICAL|ALERT|EMERGENCY) /', $l) || preg_match('/(ErrorException|TypeError|Warning - |Notice - |Deprecated - |json_decode)/', $l)));
    info('Log lines at WARNING or above with hostile theme files: ' . json_encode(array_slice(bad_lines($hl), 0, 8), JSON_UNESCAPED_SLASHES));
    check('H-LOG', 'Hostile theme files cause no PHP error, warning or exception in the log', $php === [], implode("\n", array_slice($php, 0, 8)));
    file_put_contents(theme_file(), (string) $savedTheme); // restore the saved theme for the next phases

    // =====================================================================================
    echo "== 8 Three themes, light and dark, every screen (headless Chrome) ==\n";
    @unlink(theme_file());
    $screens = ['users' => 'rolewarden/users', 'user-create' => 'rolewarden/users/create', 'user-detail' => 'rolewarden/users/' . $U['subject']['id'],
        'user-edit' => 'rolewarden/users/' . $U['subject']['id'] . '/edit', 'roles' => 'rolewarden/roles', 'role-create' => 'rolewarden/roles/create',
        'role-matrix' => 'rolewarden/roles/' . (int) q1("SELECT id FROM acl_roles WHERE slug='v1-child'"), 'role-edit' => 'rolewarden/roles/' . (int) q1("SELECT id FROM acl_roles WHERE slug='v1-child'") . '/edit',
        'permissions' => 'rolewarden/permissions', 'settings' => 'rolewarden/settings', 'activity' => 'rolewarden/activity', 'profile' => 'rolewarden/profile',
        'sessions' => 'rolewarden/sessions', 'appearance' => 'rolewarden/appearance'];
    foreach ($screens as $k => $u) { page($adm, $u); if ($adm->status !== 200) { info("Screen $k ($u) answers HTTP {$adm->status}: left out"); unset($screens[$k]); } }
    info('Screens rendered: ' . implode(', ', array_keys($screens)) . ' + login');
    $tokens = array_values(array_filter($used, fn ($u) => ! str_starts_with($u, '--tw-'))); // --tw-* are 'initial' by design (see T04)
    $RENDER = [];
    foreach (THEMES as $theme) {
        save_theme($adm, ['base' => $theme]);
        if ($theme !== 'console' && html_theme(page($adm, 'rolewarden/users')) !== $theme) throw new RuntimeException("could not select $theme");
        $steps = [];
        foreach (['light', 'dark'] as $scheme) {
            $steps[] = ['op' => 'emulate', 'scheme' => $scheme];
            $steps[] = ['op' => 'goto', 'url' => $base . 'login', 'tag' => "$theme/$scheme/login/goto"];
            $steps[] = ['op' => 'audit', 'tokens' => $tokens, 'semantic' => SEMANTIC, 'tag' => "$theme/$scheme/login"];
            $steps[] = ['op' => 'screenshot', 'path' => str_replace('\\', '/', "$WORKDIR/shot-$theme-$scheme-login.png"), 'full' => true];
        }
        $steps[] = ['op' => 'emulate', 'scheme' => 'light'];
        $steps[] = ['op' => 'login', 'url' => $base . 'login', 'email' => $U['admin2']['email'], 'password' => $pw, 'tag' => "$theme/login-submit"];
        foreach (['light', 'dark'] as $mode) {
            $steps[] = ['op' => 'goto', 'url' => $base . 'rolewarden/profile'];
            $steps[] = ['op' => 'click', 'selector' => "form.rw-mode button[value=$mode]", 'nav' => true, 'tag' => "$theme/$mode/pick"];
            foreach ($screens as $k => $u) {
                $steps[] = ['op' => 'goto', 'url' => $base . $u, 'tag' => "$theme/$mode/$k/goto"];
                $steps[] = ['op' => 'audit', 'tokens' => $tokens, 'semantic' => SEMANTIC, 'tag' => "$theme/$mode/$k"];
                $steps[] = ['op' => 'screenshot', 'path' => str_replace('\\', '/', "$WORKDIR/shot-$theme-$mode-$k.png"), 'full' => true];
            }
        }
        $steps[] = ['op' => 'goto', 'url' => $base . 'rolewarden/profile'];
        $steps[] = ['op' => 'click', 'selector' => 'form.rw-mode button[value=system]', 'nav' => true];
        $r = browser($steps, "render-$theme", 150);
        foreach ($r['steps'] as $st) if (($st['op'] ?? '') === 'audit' || str_ends_with((string) ($st['tag'] ?? ''), '/goto')) $RENDER[$st['tag']] = $st;
        $RENDER["$theme/errors"] = ['errors' => $r['errors'], 'console' => $r['consoleErrors'] ?? []];
    }
    save_theme($adm, ['base' => 'console']); @unlink(theme_file());
    // Evaluate: per theme and mode, every screen 200, attributes right, all tokens resolve, surface/ink applied.
    $low = []; $minR = [];
    foreach (THEMES as $theme) {
        foreach (['light', 'dark'] as $mode) {
            $problems = [];
            foreach (array_merge(['login'], array_keys($screens)) as $k) {
                $go = $RENDER["$theme/$mode/$k/goto"] ?? null; $au = $RENDER["$theme/$mode/$k"]['value'] ?? null;
                if (! $go || ($go['status'] ?? 0) !== 200) { $problems[] = "$k: HTTP " . json_encode($go['status'] ?? null) . ' ' . ($go['error'] ?? ''); continue; }
                if (! $au) { $problems[] = "$k: no audit " . ($RENDER["$theme/$mode/$k"]['error'] ?? ''); continue; }
                if ($au['theme'] !== $theme) $problems[] = "$k: data-rw-theme=" . json_encode($au['theme']);
                if ($k !== 'login' && $au['mode'] !== $mode) $problems[] = "$k: data-rw-mode=" . json_encode($au['mode']);
                if ($au['unresolved'] !== []) $problems[] = "$k: unresolved " . implode(',', $au['unresolved']);
                if ($au['rwBg'] !== ($au['values']['--surface'] ?? '')) $problems[] = "$k: .rw background {$au['rwBg']} != --surface " . ($au['values']['--surface'] ?? '');
                if ($au['rwColor'] !== ($au['values']['--ink'] ?? '')) $problems[] = "$k: .rw colour {$au['rwColor']} != --ink";
                $dark = lum_hex(rgb_hex($au['values']['--surface'] ?? '#ffffff')) < 0.2;
                if ($dark !== ($mode === 'dark')) $problems[] = "$k: surface " . ($au['values']['--surface'] ?? '?') . " does not look $mode";
                foreach ($au['low'] as $l) $low["$theme/$mode"][] = "$k " . $l['el'] . ' "' . $l['text'] . '" ' . $l['ratio'];
                $minR["$theme/$mode"][] = $au['minRatio'];
            }
            check("R-$theme-$mode", ucfirst($theme) . " $mode: login + " . count($screens) . ' screens render (200), attributes, every token resolved, surface and text from the tokens', $problems === [], implode("\n            ", array_slice($problems, 0, 12)));
        }
        $er = $RENDER["$theme/errors"];
        check("R-$theme-js", ucfirst($theme) . ': no JavaScript error and no driver error while rendering', $er['errors'] === [] && $er['console'] === [], json_encode($er));
    }
    foreach ($low as $combo => $items) info("Visible text under 4.5:1 in $combo (" . count($items) . '): ' . json_encode(array_slice(array_values(array_unique($items)), 0, 25), JSON_UNESCAPED_SLASHES));
    $under3 = [];
    foreach ($low as $combo => $items) foreach ($items as $it) if ((float) substr($it, strrpos($it, ' ') + 1) < 3.0) $under3[] = "$combo: $it";
    check('R-READ', 'No visible text below 3:1 against its rendered background in any theme, mode or screen', $under3 === [], implode("\n            ", array_slice(array_values(array_unique($under3)), 0, 20)));
    // Design guide claim: text pairs meet AA in all six combinations (ink, ink-muted, ink-faint on surface; on-accent on accent).
    $claim = [];
    foreach (THEMES as $theme) foreach (['light', 'dark'] as $mode) {
        $v = $RENDER["$theme/$mode/users"]['value']['values'] ?? null; if (! $v) continue;
        foreach ([['--ink', '--surface'], ['--ink-muted', '--surface'], ['--ink-faint', '--surface'], ['--on-accent', '--accent'], ['--accent', '--surface'], ['--granted', '--surface'], ['--denied', '--surface'], ['--ink', '--surface-raised'], ['--ink-muted', '--surface-sunk'], ['--ink-faint', '--surface-sunk']] as [$fg, $bg]) {
            $ra = ratio_hex(rgb_hex($v[$fg]), rgb_hex($v[$bg]));
            if ($ra < 4.5) $claim[] = sprintf('%s/%s %s on %s = %.2f', $theme, $mode, $fg, $bg, $ra);
        }
    }
    check('R-AA', 'Shipped themes: text tokens on their backgrounds meet 4.5:1 in all six combinations (design guide)', $claim === [], implode('; ', $claim));
    logs_take('render');

    // =====================================================================================
    echo "== 9 Fourth theme and a component of the host (README, design guide) ==\n";
    $cfg = "$APP/app/Config/RoleWarden.php";
    file_put_contents($cfg, "<?php\n\nnamespace Config;\n\n// verify-v5 (Collaudatore ad Hoc): fourth theme exactly as in documentation/design-guide.html\nclass RoleWarden extends \\RoleWarden\\Config\\RoleWarden\n{\n    public array \$extraThemes = ['ocean' => 'Ocean'];\n    public ?string \$themeStylesheet = 'http://localhost:8070/css/rolewarden-ocean.css';\n}\n");
    @mkdir("$APP/public/css", 0777, true);
    file_put_contents("$APP/public/css/rolewarden-ocean.css", ":root[data-rw-theme=\"ocean\"] {\n  --l-surface: #f7fafb; --l-ink: #0f1d24; --l-accent: #0b6e8a;\n  --d-surface: #0c171c; --d-ink: #e8f2f5; --d-accent: #6cc3dc;\n  --radius-md: 6px; --size-row: 44px;\n}\n.v5-host-chip { display: inline-block; background: var(--surface-raised); color: var(--ink); border: var(--rule-width) solid var(--rule-strong); border-radius: var(--radius-md); padding: var(--space-1) var(--space-2); }\n");
    cache_clear(); server_stop(); server_start();
    $h = page($adm, 'rolewarden/appearance');
    $bases = preg_match_all('/name="base" value="([^"]+)"/', $h, $mm) ? $mm[1] : [];
    check('X01', 'The Appearance screen offers the fourth theme next to the other three', $adm->status === 200 && $bases === ['console', 'clarity', 'contrast', 'ocean'] && str_contains($h, 'Ocean'), 'HTTP ' . $adm->status . ' ' . json_encode($bases));
    $hb = save_theme($adm, ['base' => 'ocean']);
    $h = page($adm, 'rolewarden/users'); $lp = page(new Client('v5-anon-ocean'), 'login');
    check('X02', 'Saving Ocean: <html data-rw-theme="ocean"> and the host stylesheet linked on panel and login pages', html_theme($h) === 'ocean' && str_contains(html_entity_decode($h), 'localhost:8070/css/rolewarden-ocean.css') && str_contains(html_entity_decode($lp), 'localhost:8070/css/rolewarden-ocean.css'), json_encode([html_theme($h), tf()]));
    $hb = save_theme($adm, ['base' => 'oceanx']);
    check('X03', 'An undeclared theme is still refused', str_contains((string) tf(), 'ocean') && ! str_contains((string) tf(), 'oceanx') && error_under($hb, 'base'));
    $chip = "(() => { const c = document.createElement('span'); c.className = 'v5-host-chip'; c.textContent = 'Host chip'; document.querySelector('.rw-content').appendChild(c); const s = getComputedStyle(c), p = document.createElement('span'); document.body.appendChild(p); const res = (t) => { p.style.color = 'var(' + t + ')'; return getComputedStyle(p).color; }; const o = { bg: s.backgroundColor, color: s.color, radius: s.borderTopLeftRadius, raised: res('--surface-raised'), ink: res('--ink'), surface: res('--surface'), rule: res('--rule'), rowsize: getComputedStyle(document.documentElement).getPropertyValue('--size-row').trim(), textmd: getComputedStyle(document.documentElement).getPropertyValue('--text-md').trim() }; p.remove(); return o; })()";
    $r = browser([
        ['op' => 'emulate', 'scheme' => 'light'],
        ['op' => 'login', 'url' => $base . 'login', 'email' => $U['admin2']['email'], 'password' => $pw, 'tag' => 'login'],
        ['op' => 'goto', 'url' => $base . 'rolewarden/profile'], ['op' => 'click', 'selector' => 'form.rw-mode button[value=light]', 'nav' => true],
        ['op' => 'goto', 'url' => $base . 'rolewarden/users', 'tag' => 'users'], ['op' => 'eval', 'tag' => 'light', 'expr' => $chip],
        ['op' => 'audit', 'tokens' => $tokens, 'semantic' => SEMANTIC, 'tag' => 'audit-light'],
        ['op' => 'screenshot', 'path' => str_replace('\\', '/', "$WORKDIR/verify-v5.ocean-light.png"), 'full' => false],
        ['op' => 'click', 'selector' => 'form.rw-mode button[value=dark]', 'nav' => true], ['op' => 'eval', 'tag' => 'dark', 'expr' => $chip],
        ['op' => 'audit', 'tokens' => $tokens, 'semantic' => SEMANTIC, 'tag' => 'audit-dark'],
        ['op' => 'screenshot', 'path' => str_replace('\\', '/', "$WORKDIR/verify-v5.ocean-dark.png"), 'full' => false],
        ['op' => 'goto', 'url' => $app, 'tag' => 'app'], ['op' => 'eval', 'tag' => 'cust', 'expr' => js_set_color('rw-dark-ink', '#203038'), 'wait' => 150], ['op' => 'eval', 'tag' => 'cust-state', 'expr' => JS_STATE],
        ['op' => 'goto', 'url' => $base . 'rolewarden/profile'], ['op' => 'click', 'selector' => 'form.rw-mode button[value=system]', 'nav' => true],
    ], 'ocean');
    $L = by_tag($r, 'light')['value'] ?? []; $D = by_tag($r, 'dark')['value'] ?? [];
    info('Ocean light: ' . json_encode($L) . ' dark: ' . json_encode($D));
    check('X04', 'Ocean colours apply in light and dark', rgb_hex($L['surface'] ?? '') === '#f7fafb' && rgb_hex($L['ink'] ?? '') === '#0f1d24' && rgb_hex($D['surface'] ?? '') === '#0c171c' && rgb_hex($D['ink'] ?? '') === '#e8f2f5');
    check('X05', 'Ocean shape tokens apply (radius 6px, row 44px)', ($L['radius'] ?? '') === '6px' && ($L['rowsize'] ?? '') === '44px');
    check('X06', 'A token the fourth theme leaves out falls back to Console (rule colour, type size)', rgb_hex($L['rule'] ?? '') === '#e4e5e7' && ($L['textmd'] ?? '') === '13px', json_encode([$L['rule'] ?? null, $L['textmd'] ?? null]));
    check('X07', 'A host component built from semantic tokens follows theme and mode', ($L['bg'] ?? 'a') === ($L['raised'] ?? 'b') && ($L['color'] ?? 'a') === ($L['ink'] ?? 'b') && ($D['color'] ?? 'a') === ($D['ink'] ?? 'b') && ($L['color'] ?? '') !== ($D['color'] ?? ''));
    $al = by_tag($r, 'audit-light')['value'] ?? []; $ad = by_tag($r, 'audit-dark')['value'] ?? [];
    check('X08', 'With Ocean every token used by the panel still resolves (light and dark)', ($al['unresolved'] ?? ['?']) === [] && ($ad['unresolved'] ?? ['?']) === []);
    check('X09', 'The customiser works on Ocean: a dark text under AA is flagged', str_contains((string) (by_tag($r, 'cust-state')['value']['bar'] ?? ''), 'Text (Dark)'), json_encode(by_tag($r, 'cust-state')['value']['bar'] ?? null));
    check('X10', 'No JavaScript error with the fourth theme', ($r['consoleErrors'] ?? ['?']) === [] && $r['errors'] === [], json_encode([$r['consoleErrors'] ?? null, $r['errors']]));
    save_theme($adm, ['base' => 'console']); @unlink(theme_file());
    unlink($cfg); unlink("$APP/public/css/rolewarden-ocean.css"); @rmdir("$APP/public/css");
    cache_clear(); server_stop(); server_start();
    logs_take('fourth');

    // =====================================================================================
    echo "== 10 Publication ==\n";
    // CSRF on every new form, as served on every panel page.
    $newForms = [];
    foreach (['rolewarden/users', 'rolewarden/appearance', 'rolewarden/profile'] as $u) foreach (forms(page($adm, $u)) as $f) if (str_contains($f['action'], 'appearance')) $newForms[$f['action']] = $f;
    $noCsrf = array_keys(array_filter($newForms, fn ($f) => empty($f['fields']['csrf_test_name'])));
    check('P01', 'Every new form (mode, save theme, reset) carries a CSRF token', count($newForms) === 3 && $noCsrf === [], json_encode(array_keys($newForms)));
    $hb = save_theme($adm, ['base' => 'clarity'], false); $st = $adm->status;
    check('P02', 'Save theme without CSRF token is refused (no file, no 500)', tf() === null && $st !== 500, "HTTP $st");
    if (in_array($st, [302, 303], true)) check('P02b', 'The CSRF refusal shows an error toast', toast_ok(static_toasts($hb), 'error'), json_encode(static_toasts($hb)));
    // Escape: a stored mode value and the user's name in the top bar.
    $uid = $U['admin2']['id']; set_mode($adm, 'dark'); after($adm);
    $row = q('SELECT id, value FROM settings WHERE context LIKE ? AND value = ?', ['%' . $uid . '%', 'dark']);
    if ($row) {
        q('UPDATE settings SET value=? WHERE id=?', ['dark" onmouseover="alert(1)', $row[0]['id']]); cache_clear();
        $h = page($adm, 'rolewarden/users');
        check('P03', 'A tampered stored mode value is never written raw into the page', $adm->status === 200 && ! str_contains(clean_html($h), 'onmouseover="alert(1)'), 'HTTP ' . $adm->status . ' mode attr=' . json_encode(html_mode($h)));
        q('UPDATE settings SET value=? WHERE id=?', ['dark', $row[0]['id']]); cache_clear();
    } else info('Stored mode row not identified for the tamper check: ' . json_encode(q('SELECT * FROM settings WHERE context IS NOT NULL')));
    set_mode($adm, 'system'); after($adm);
    // The theme stylesheet route: what it exposes.
    save_theme($adm, $mine);
    $tl = theme_link(page($adm, 'rolewarden/users'));
    $t = new Client('v5-route');
    $t->req('GET', (string) $tl); $body = $t->body;
    $t->req('GET', (string) $tl . (str_contains((string) $tl, '?') ? '&' : '?') . 'file=../../.env&path=app/Config/Database.php'); $body2 = $t->body;
    check('P04', 'Theme stylesheet route: custom properties only, no path, no JSON, no user or config data; query parameters ignored', $body !== '' && $body2 === $body && ! preg_match('/(writable|\{"|"base"|database|password|@|SQLSTATE|<\?php|Exception)/i', $body), json_encode(substr($body, 0, 300)));
    @unlink(theme_file());
    $t->req('GET', (string) $tl);
    check('P05', 'Theme stylesheet route with no theme file: no error, no data', $t->status !== 500 && ! preg_match('/(writable|Exception|No such file|SQLSTATE)/i', $t->body), 'HTTP ' . $t->status . ' ' . json_encode(substr($t->body, 0, 120)));
    logs_take('pre-probes');
    $probes = [];
    foreach (['rolewarden/assets/css/../../Config/RoleWarden.php', 'rolewarden/assets/..%2f..%2f.env', 'rolewarden/assets/css/%2e%2e/%2e%2e/%2e%2e/.env', 'rolewarden/assets/css/..%5c..%5cConfig%5cRoleWarden.php', 'rolewarden/theme.css/../../../.env', 'rolewarden/assets/css/panel.css%00.php'] as $u) {
        $t->req('GET', HOST . 'index.php/' . $u);
        $probes[$u] = $t->status;
        if (preg_match('/(database\.default|<\?php|namespace RoleWarden|CI_ENVIRONMENT)/', $t->body)) $probes[$u] = 'LEAK ' . $t->status;
    }
    info('Log lines caused by the traversal probes (framework URI filter): ' . json_encode(array_map(fn ($l) => substr($l, 0, 160), bad_lines(logs_take('probes'))), JSON_UNESCAPED_SLASHES));
    check('P06', 'Asset and theme routes reject path traversal without leaking files', ! preg_grep('/LEAK/', array_map('strval', $probes)) && ! in_array(500, $probes, true), json_encode($probes));
    $leak = []; foreach ($GLOBALS['SEEN'] as [$u, $s, $b]) if (leaks_sql((string) $b) || $s === 500) $leak[] = "$u HTTP $s";
    check('P07', 'No SQL error text and no HTTP 500 in any page seen (' . count($GLOBALS['SEEN']) . ')', $leak === [], json_encode(array_slice($leak, 0, 10)));
    $all = [];
    foreach (['upgrade', 'mode', 'customiser-http', 'customiser-browser', 'update', 'render', 'fourth', 'pre-probes'] as $ph) if (is_file($pf = "$WORKDIR/verify-v5.phase-$ph.log")) $all = array_merge($all, bad_lines(file($pf, FILE_IGNORE_NEW_LINES)));
    $all = array_merge($all, bad_lines(logs_take('publication')));
    check('P08', 'Application log at threshold 9: no warning or error (CSRF refusals and the hostile-file phase excluded)', $all === [], implode("\n", array_slice($all, 0, 15)));
} finally {
    server_stop();
    @unlink("$APP/app/Config/RoleWarden.php"); @unlink("$APP/public/css/rolewarden-ocean.css"); @rmdir("$APP/public/css");
    @unlink(app_theme_file()); @rmdir(dirname(app_theme_file())); @unlink(theme_file()); @rmdir(dirname(theme_file()));
}
$srv = @file("$WORKDIR/verify-v5.regression.v5.server.log", FILE_IGNORE_NEW_LINES) ?: [];
$php = array_values(array_filter($srv, fn ($l) => preg_match('/PHP (Warning|Notice|Deprecated|Fatal error|Parse error)|Stack trace/i', $l)));
check('P09', 'PHP built-in server log (error_reporting=-1): no PHP warning, notice, deprecation or fatal', $srv !== [] && $php === [], $srv === [] ? 'server log missing' : implode("\n", array_slice($php, 0, 10)));
// Chrome: every instance this run started has exited.
$alive = [];
foreach (array_filter($GLOBALS['CHROME_PIDS']) as $pid) { exec('tasklist /FI "PID eq ' . (int) $pid . '" /NH', $o); if (preg_grep('/chrome\.exe/i', $o)) $alive[] = $pid; $o = []; }
check('P10', 'Every headless Chrome started by this run has exited (' . count($GLOBALS['CHROME_PIDS']) . ' instances)', $alive === [], json_encode($alive));
$inl = []; $rawInl = [];
foreach ($GLOBALS['SEEN'] as [$u, $st, $b]) {
    if ($st >= 400 || str_contains((string) $b, 'SecurityException') || str_contains((string) $b, 'The action you requested is not allowed')) continue; // framework error pages
    $b = preg_replace('/<div id="debug-bar".*$/s', '', clean_html((string) $b));
    if (preg_match_all('/\sstyle="([^"]*)"/', $b, $m)) foreach ($m[1] as $sv) {
        $key = preg_replace('#^.*?index\.php/#', '', $u) . ': ' . $sv; $inl[$key] = true;
        if (preg_match('/#[0-9a-f]{3,8}\b|rgba?\(|(?<![\d.])(?!0(px)?\b)\d*\.?\d+(px|rem|em)\b/i', $sv)) $rawInl[$key] = true;
    }
}
info('Inline style attributes in served markup (' . count($inl) . '): ' . json_encode(array_slice(array_keys($inl), 0, 30), JSON_UNESCAPED_SLASHES));
check('P11', 'No raw colour or non-zero length in inline styles of the served pages', $rawInl === [], json_encode(array_slice(array_keys($rawInl), 0, 20), JSON_UNESCAPED_SLASHES));
$pass = count(array_filter($GLOBALS['results'], fn ($r) => $r[2]));
echo 'V5 RESULT ' . count($GLOBALS['results']) . ' checks ' . $pass . ' PASS ' . (count($GLOBALS['results']) - $pass) . ' FAIL ' . count($GLOBALS['ambiguities']) . " AMB\n";
exit($pass === count($GLOBALS['results']) ? 0 : 1);
