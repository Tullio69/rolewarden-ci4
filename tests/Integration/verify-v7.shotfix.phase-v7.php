<?php
/**
 * V7 phase (Collaudatore ad Hoc, Claude): recheck of SHOT V-7 after the fix f38debd (parent 885b018), in black box: served
 * HTML, DOM and computed geometry through Chrome DevTools Protocol (verify-motion.browser.mjs), HTTP, rolewarden_test only.
 * Sources of the assertions: the caller's prompt (V-7 with dlRight and 0.5 px tolerance; wrap only inside the facts column;
 * "Theme file / Download" row aligned and readable; Appearance assertions of SHOT shot.b; console; save and download;
 * Dashboard and a role page), docs/SPEC.md (theme file, app/ file wins), documentation/views.html, troubleshooting.html.
 * States of Appearance: open (fresh install, no theme file), saved (writable/rolewarden/theme.json written by the panel),
 * locked (the downloaded file copied to app/Config/RoleWarden/theme.json of the test app copy).
 * RW_SHOT_LABEL=parent measures only (no screenshots, no checks); the commit run compares "no new wrap" with it.
 */
require_once __DIR__ . '/verify-u1.lib.php';
date_default_timezone_set('UTC');
$LBL = getenv('RW_SHOT_LABEL') ?: 'commit';
$PARENT = $LBL === 'parent';
echo "\n== V7: SHOT V-7 recheck ($LBL, " . (getenv('RW_V8_NEW') ?: '?') . ")\n";
serve8($APP, 'V7');
$S0 = count($GLOBALS['SEEN']);
$OUTP = __DIR__ . '/verify-v7.';
$MEAS = [];
$GLOBALS['JERR'] = [];

function sb(array $steps, string $tag, int $timeout = 400000): array
{
    global $WORK;
    static $n = 0; $n++;
    $jf = "$WORK/v7job-$n.json";
    file_put_contents($jf, json_encode(['profile' => "$WORK/chrome-v7-$n", 'steps' => $steps, 'timeout' => $timeout, 'settle' => 350]));
    $p = proc_open(['node', __DIR__ . '/verify-motion.browser.mjs', $jf], [0 => ['file', 'NUL', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    $o = stream_get_contents($pipes[1]); $e = stream_get_contents($pipes[2]);
    proc_close($p);
    $r = json_decode(trim((string) $o), true);
    if (! is_array($r)) { info("browser $tag: no result: " . short($o . $e, 600)); $GLOBALS['JERR'][$tag] = ['no result']; return ['steps' => [], 'errors' => ['no result']]; }
    if ($r['errors']) info("browser $tag driver errors: " . short(implode(' | ', $r['errors']), 800));
    if (empty($r['profileRemoved'])) info("browser $tag: profile not removed " . ($r['cleanupError'] ?? ''));
    $GLOBALS['JERR'][$tag] = array_merge($r['consoleErrors'] ?? [], $r['consoleCalls'] ?? [], $r['logEntries'] ?? []);
    return $r;
}
function v(array $r, string $tag) { foreach ($r['steps'] as $s) if (($s['tag'] ?? '') === $tag) return $s['value'] ?? ['error' => $s['error'] ?? null]; return null; }
function j($x): string { return short(json_encode($x, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 900); }
function lang_to(string $l): void { static $c = null; if (! $c) { $c = c8('v7-lang'); login8($c, 'owner@v8b.test', OPW); } postform($c, 'rolewarden/profile', '#profile/language$#', ['language' => $l], "lang $l"); }

// page-side geometry, fractional px, no knowledge of the module's code
const GEO = <<<'JS'
(() => {
  const q = (s, r) => (r || document).querySelector(s), qa = (s, r) => [...(r || document).querySelectorAll(s)];
  const cs = (e) => getComputedStyle(e);
  const R = (e) => { const b = e.getBoundingClientRect(); return { l: +b.left.toFixed(2), t: +b.top.toFixed(2), r: +b.right.toFixed(2), b: +b.bottom.toFixed(2), w: +b.width.toFixed(2), h: +b.height.toFixed(2) }; };
  const txt = (e) => e ? e.textContent.replace(/\s+/g, ' ').trim() : null;
  const shown = (e) => { for (let x = e; x; x = x.parentElement) { const s = cs(x); if (s.display === 'none' || s.visibility === 'hidden') return false; } const b = e.getBoundingClientRect(); return b.width > 0 && b.height > 0; };
  // line boxes of an element's text: distinct tops of the range's client rects
  const lines = (e) => { const rg = document.createRange(); rg.selectNodeContents(e); const rs = [...rg.getClientRects()].filter(x => x.width > 0.5);
    const tops = []; for (const x of rs.sort((a, b) => a.top - b.top)) if (!tops.length || x.top - tops[tops.length - 1] > 3) tops.push(x.top);
    return { n: tops.length, minL: rs.length ? +Math.min(...rs.map(x => x.left)).toFixed(2) : null, maxR: rs.length ? +Math.max(...rs.map(x => x.right)).toFixed(2) : null }; };
  const main = q('main');
  const ph = q('main .rw-page-head') || q('main .rw-dash-head') || q('main header');
  const phs = ph ? cs(ph) : null;
  const o = { url: location.pathname + location.search, vw: innerWidth, docW: document.documentElement.scrollWidth, lang: document.documentElement.lang,
    dark: matchMedia('(prefers-color-scheme: dark)').matches, eyebrow: txt(q('main .rw-page-head .rw-header')), h1: txt(q('main h1')),
    head: ph ? { cls: String(ph.className).slice(0, 60), box: R(ph), contentR: +(ph.getBoundingClientRect().right - parseFloat(phs.paddingRight) - parseFloat(phs.borderRightWidth)).toFixed(2) } : null,
    main: main ? { box: R(main), contentR: +(main.getBoundingClientRect().right - parseFloat(cs(main).paddingRight)).toFixed(2) } : null };
  // elements of the page head past its right edge (border box)
  o.headOut = ph ? qa('*', ph).filter(e => shown(e) && e.getBoundingClientRect().right > ph.getBoundingClientRect().right + 0.5).map(e => e.tagName + '.' + String(e.className).slice(0, 30) + ' ' + R(e).r) : [];
  // every element of main past main's right edge (border box), dialogs and toasts left out
  o.mainOut = main ? qa('*', main).filter(e => shown(e) && !e.closest('dialog, .rw-toasts, template') && e.getBoundingClientRect().right > main.getBoundingClientRect().right + 0.5).map(e => e.tagName + '.' + String(e.className).slice(0, 30)) : [];
  // facts lists (dl) of main: each row dt/dd, line boxes, links
  o.dls = qa('main dl').filter(shown).map(dl => { const d = R(dl);
    const rows = qa('dt', dl).filter(shown).map(dt => { let dd = dt.nextElementSibling; while (dd && dd.tagName !== 'DD') dd = dd.nextElementSibling;
      const code = dd ? (q('code', dd) || dd) : null;
      const lk = dd ? qa('a, button', dd).filter(shown).map(a => ({ t: txt(a), href: a.getAttribute('href'), box: R(a), lines: lines(a).n })) : [];
      return { dt: txt(dt), dtBox: R(dt), dtLines: lines(dt).n, dtWs: cs(dt).whiteSpace,
        dd: dd ? txt(dd).slice(0, 120) : null, ddBox: dd ? R(dd) : null, ddOverflow: dd ? dd.scrollWidth > dd.clientWidth + 1 : null,
        code: code && code !== dd ? { t: txt(code), box: R(code), lines: lines(code), ws: cs(code).whiteSpace, ow: cs(code).overflowWrap, wb: cs(code).wordBreak } : null,
        ddLines: dd ? lines(dd) : null, links: lk }; });
    return { inHead: !!(ph && ph.contains(dl)), box: d, rows }; });
  // Appearance: section titles and the "Saved in" row in the SHOT form (codeRight against dlRight)
  if (/appearance/.test(location.pathname)) {
    o.sections = qa('main .rw-section-title').map(t => ({ t: txt(t), right: +t.getBoundingClientRect().right.toFixed(2) }));
    const dt = qa('main dt').find(d => /Saved in|Salvato in/i.test(d.textContent));
    if (dt) { const dd = dt.nextElementSibling; const code = q('code', dd) || dd; const dl = dt.closest('dl'); const lh = parseFloat(cs(code).lineHeight) || parseFloat(cs(code).fontSize) * 1.5;
      o.savedIn = { codeRight: +code.getBoundingClientRect().right.toFixed(2), dlRight: dl ? +dl.getBoundingClientRect().right.toFixed(2) : null, ddRight: +dd.getBoundingClientRect().right.toFixed(2),
        dt: txt(dt), dd: txt(dd), ddRects: code.getClientRects().length, codeLines: lines(code), codeH: +code.getBoundingClientRect().height.toFixed(2), lh, dtLines: lines(dt).n, dtRects: dt.getClientRects().length }; }
    o.forms = qa('main form').map(f => ({ action: f.getAttribute('action'), buttons: qa('button[type=submit], button:not([type])', f).map(b => txt(b) + (b.disabled ? ' (disabled)' : '')), disabledFields: qa('input, select', f).filter(i => i.disabled).length, fields: qa('input, select', f).length }));
    o.notes = qa('main p, main .rw-note, main [role=status]').filter(shown).map(p => txt(p)).filter(t => t && /app\/|pinned|locked|bloccat|applicazione|application/i.test(t)).slice(0, 4);
  }
  return o;
})()
JS;
const SAVECLICK = "(() => { const b = [...document.querySelectorAll('main form button')].find(b => /^(Save theme|Salva tema|Save|Salva)$/i.test(b.textContent.trim())); if (!b) return 'no button'; b.click(); return 'clicked ' + b.textContent.trim(); })()";
function audit(string $tag): array { return ['op' => 'audit', 'tokens' => ['--surface', '--ink', '--accent'], 'semantic' => ['--surface'], 'tag' => $tag]; }
function ax(string $tag): array { return ['op' => 'ax', 'selector' => 'main button, main a[href], main input:not([type=hidden]), main select, main summary', 'tag' => $tag]; }

$APPROVER = role_id('demo-approver');
info("users: owner=$OWNER admin=$ADMIN; role Approver=$APPROVER");
$PAGES = ['appearance' => 'rolewarden/appearance', 'dashboard' => 'rolewarden?period=30', 'role' => "rolewarden/roles/$APPROVER"];

/** One browser job per (state, language): light/dark x 1440/1600 on Appearance (and on the other pages when $others). */
function state_job(string $state, string $lang, bool $others, bool $extra): array
{
    global $PAGES, $PARENT, $OUTP;
    $s = [blogin('owner@v8b.test', OPW)];
    foreach (['light', 'dark'] as $mode) {
        $s[] = ['op' => 'emulate', 'scheme' => $mode];
        foreach ([1440, 1600] as $w) {
            $s[] = ['op' => 'viewport', 'width' => $w, 'height' => 900];
            foreach ($PAGES as $k => $u) {
                if ($k !== 'appearance' && ! $others) continue;
                $s[] = ['op' => 'goto', 'url' => U1BASE . $u, 'tag' => "g-$k-$w-$mode"];
                $s[] = ['op' => 'wait', 'ms' => $k === 'dashboard' ? 1500 : 600];
                $s[] = ['op' => 'eval', 'expr' => GEO, 'tag' => "m-$k-$w-$mode"];
                if (! $PARENT) $s[] = ['op' => 'screenshot', 'path' => $OUTP . ($k === 'appearance' ? "appearance-$state" : $k) . ".$w.$mode.$lang.png", 'tag' => "s-$k-$w-$mode"];
                if ($extra && $k === 'appearance' && $w === 1440) { $s[] = audit("a-$mode"); if ($mode === 'light') $s[] = ax('x-ap'); }
            }
        }
    }
    return $s;
}
function run_state(string $state, bool $others): void
{
    global $MEAS;
    foreach (['en', 'it'] as $lang) {
        lang_to($lang);
        $r = sb(state_job($state, $lang, $others, $lang === 'en' && $state === 'open'), "$state-$lang", 500000);
        foreach ($r['steps'] as $st) if (is_string($st['tag'] ?? null) && preg_match('/^[max]-/', $st['tag'])) $MEAS[$state][$lang][$st['tag']] = $st['value'] ?? ['error' => $st['error'] ?? null];
    }
    lang_to('en');
}

// ---------------------------------------------------------------- 1. open: fresh install, no theme file (as in SHOT)
$TF = theme_file_u1();
$APPF = "$APP/app/Config/RoleWarden/theme.json";
info('theme files at start: writable=' . (is_file($TF) ? 'yes' : 'no') . ' app=' . (is_file($APPF) ? 'yes' : 'no'));
run_state('open', true);

// ---------------------------------------------------------------- 2. save from the browser (the real button), then over HTTP; download
$r = sb([blogin('owner@v8b.test', OPW), ['op' => 'goto', 'url' => U1BASE . 'rolewarden/appearance', 'tag' => 'ga'], ['op' => 'wait', 'ms' => 600],
    ['op' => 'eval', 'expr' => SAVECLICK, 'tag' => 'click'], ['op' => 'wait', 'ms' => 2500],
    ['op' => 'eval', 'expr' => "(() => ({ url: location.pathname, toasts: [...document.querySelectorAll('.rw-toasts .rw-toast, [role=status], [role=alert]')].map(t => t.className + ' | ' + t.textContent.replace(/\\s+/g, ' ').trim()).filter(t => t.length > 3).slice(0, 3) }))()", 'tag' => 'after']], 'save-browser');
clearstatcache();
$MEAS['save'] = ['click' => v($r, 'click'), 'after' => v($r, 'after'), 'file' => is_file($TF)];
$saved1 = theme_json();
[$hc] = u1login('v7-http', 'owner@v8b.test', OPW);
theme_save($hc, [], 'save http');
$saveStatus = $hc->status; $saveLoc = $hc->location;
clearstatcache();
$saved2 = theme_json();
$hc->get('rolewarden/appearance/download'); seen($hc, 'download');
$dl = ['status' => $hc->status, 'type' => $hc->headers['content-type'] ?? null, 'disp' => $hc->headers['content-disposition'] ?? null, 'len' => strlen($hc->body)];
$dlJson = json_decode($hc->body, true);
$MEAS['download'] = $dl + ['equalsFile' => is_array($dlJson) && $dlJson == $saved2];
run_state('saved', false);

// ---------------------------------------------------------------- 3. locked: the downloaded file as the application's theme file
@mkdir(dirname($APPF), 0777, true);
file_put_contents($APPF, $hc->body);
info('app theme file written: ' . (is_file($APPF) ? strlen((string) file_get_contents($APPF)) . ' bytes' : 'no'));
run_state('locked', false);
$appBefore = (string) file_get_contents($APPF);
theme_save($hc, ['radius' => ''], 'save while locked');
$lockedSave = ['status' => $hc->status, 'appUnchanged' => (string) file_get_contents($APPF) === $appBefore];
$hc->get('rolewarden/appearance'); seen($hc, 'appearance locked');
$lockedSave['servesApp'] = str_contains($hc->body, 'app/Config/RoleWarden/theme.json');
$MEAS['lockedSave'] = $lockedSave;
@unlink($APPF);

$MEAS['jerr'] = $GLOBALS['JERR'];
file_put_contents($OUTP . "shotfix.measure.$LBL.json", json_encode($MEAS, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
info("measures written: verify-v7.shotfix.measure.$LBL.json");
if ($PARENT) { info('parent run: measures only'); return; }
$P = is_file($OUTP . 'shotfix.measure.parent.json') ? json_decode((string) file_get_contents($OUTP . 'shotfix.measure.parent.json'), true) : null;
if (! $P) info('no parent measures: the "no new wrap" comparisons are skipped');

// ================================================================ CHECKS
$m = fn (string $state, string $lang, string $k, int $w, string $mode) => $MEAS[$state][$lang]["m-$k-$w-$mode"] ?? null;
$TOL = 0.5;
// ---- V-7 matrix
foreach (['open', 'saved', 'locked'] as $state) foreach (['en', 'it'] as $lang) foreach ([1440, 1600] as $w) foreach (['light', 'dark'] as $mode) {
    $x = $m($state, $lang, 'appearance', $w, $mode); $si = $x['savedIn'] ?? null; $edge = $si['dlRight'] ?? 0;
    $id = "V-7-$state-$lang-$w-$mode";
    check($id, "Appearance ($state) $lang $w $mode: the \"Saved in\" path stays inside the right margin of the page head (path right " . ($si['codeRight'] ?? '?') . " px, dlRight $edge px, tolerance 0.5)", $si && $edge > 0 && $si['codeRight'] <= $edge + $TOL, j($si));
    // wrap only inside the facts column: every line box of the path inside its dd and inside the dl; the dl inside the page head
    $rows = []; foreach ($x['dls'] ?? [] as $d) foreach ($d['rows'] as $row) $rows[] = $row + ['dl' => $d['box'], 'inHead' => $d['inHead']];
    $sr = array_values(array_filter($rows, fn ($row) => preg_match('/^(Saved in|Salvato in)/', (string) $row['dt'])))[0] ?? null;
    $cl = $sr['code']['lines'] ?? $sr['ddLines'] ?? null;
    check("V-7w-$state-$lang-$w-$mode", 'The path wraps (' . ($cl['n'] ?? '?') . ' line(s)) only inside the facts column: every line box between the dd left and the dl right, "Saved in" on one line, the dl inside the page head', $sr && $cl && $cl['minL'] >= $sr['ddBox']['l'] - $TOL && $cl['maxR'] <= $sr['dl']['r'] + $TOL && $sr['dtLines'] === 1 && ! $sr['ddOverflow'] && $sr['inHead'] && $sr['dl']['r'] <= ($x['head']['box']['r'] ?? 0) + $TOL, j([$sr, 'head' => $x['head'] ?? null]));
    $tr = array_values(array_filter($rows, fn ($row) => array_filter($row['links'], fn ($a) => str_contains((string) $a['href'], 'appearance/download'))))[0] ?? null;
    $a = $tr ? array_values(array_filter($tr['links'], fn ($a) => str_contains((string) $a['href'], 'appearance/download')))[0] : null;
    $okT = $tr && $a && $tr['dtLines'] === 1 && $a['lines'] === 1 && abs($tr['dtBox']['t'] - $tr['ddBox']['t']) <= 2 && $a['box']['w'] > 0 && $a['box']['r'] <= $tr['dl']['r'] + $TOL && $a['box']['l'] >= $tr['ddBox']['l'] - $TOL && $a['box']['r'] <= $w && trim((string) $a['t']) !== '';
    check("V-7t-$state-$lang-$w-$mode", 'The "Theme file / Download" row: dt on one line, dt and dd tops aligned (2 px), the Download link on one line, visible and inside the dd and the facts column', $okT, j($tr));
}
// ---- Appearance regression (shot.b assertions), open state as in SHOT
$ap = $m('open', 'en', 'appearance', 1440, 'light');
$rights = array_unique(array_map(fn ($s) => round($s['right']), $ap['sections'] ?? []));
check('V-4', 'Appearance: the section titles share one right edge (' . implode(',', $rights) . ')', count($ap['sections'] ?? []) >= 2 && max($rights) - min($rights) <= 1, j($ap['sections'] ?? null));
$si = $ap['savedIn'] ?? null;
check('V-5', 'SHOT V-5 as written: "Saved in" and its path do not wrap (one line box each), en 1440 light', $si && $si['ddRects'] === 1 && $si['dtLines'] === 1 && $si['codeH'] <= $si['lh'] * 1.4, j($si));
foreach (['en', 'it'] as $lang) foreach ([1440, 1600] as $w) {
    $x = $m('open', $lang, 'appearance', $w, 'light'); $sx = $x['savedIn'] ?? null; $rx = array_unique(array_map(fn ($s) => round($s['right']), $x['sections'] ?? []));
    check("V-6-$lang-$w", "SHOT V-6 as written: Appearance $lang at {$w}px, same right edge for the sections and no wrap of \"Saved in\" (dt and path one line box)", $sx && $sx['ddRects'] === 1 && $sx['dtLines'] === 1 && $rx && max($rx) - min($rx) <= 1, j([$sx, $rx]));
}
check('ACT-3', 'Appearance eyebrow "System", in Italian "Sistema"', ($ap['eyebrow'] ?? '') === 'System' && ($m('open', 'it', 'appearance', 1440, 'light')['eyebrow'] ?? '') === 'Sistema', j([$ap['eyebrow'] ?? null, $m('open', 'it', 'appearance', 1440, 'light')['eyebrow'] ?? null]));
foreach (['light', 'dark'] as $mode) { $au = $MEAS['open']['en']["a-$mode"] ?? null;
    check("A11Y-1-$mode", "Appearance $mode: contrast AA (4.5:1) of every visible text", is_array($au) && isset($au['lowCount']) && $au['lowCount'] === 0, j([$au['lowCount'] ?? null, array_slice($au['low'] ?? [], 0, 3)])); }
$axn = $MEAS['open']['en']['x-ap'] ?? null;
$empty = is_array($axn) ? array_filter($axn, fn ($n) => is_array($n) && ! $n['ignored'] && in_array($n['role'], ['button', 'link', 'checkbox', 'combobox', 'textbox', 'searchbox', 'radio', 'menuitem', 'DisclosureTriangle'], true) && trim((string) $n['name']) === '') : [1];
check('A11Y-2', 'Appearance: every button, link, field and select has a non-empty accessible name (' . (is_array($axn) ? count($axn) : 0) . ' nodes)', is_array($axn) && count($axn) > 0 && ! $empty, j(array_values($empty)));
foreach (['open', 'saved', 'locked'] as $state) foreach (['en', 'it'] as $lang) foreach ([1440, 1600] as $w) foreach (['light', 'dark'] as $mode) { $x = $m($state, $lang, 'appearance', $w, $mode);
    if (! $x || ($x['docW'] ?? 9e9) > ($x['vw'] ?? 0) || ($x['headOut'] ?? [1])) $hsc[] = ["$state-$lang-$w-$mode", $x['docW'] ?? null, $x['headOut'] ?? null]; }
check('V-8', 'Appearance, all 24 cases: no horizontal scroll of the page and no element of the page head past its right edge', empty($hsc), j($hsc ?? []));
// ---- console
$jerr = array_filter($GLOBALS['JERR'], fn ($e) => $e !== []);
$envOnly = $jerr && ! array_filter($jerr, fn ($e) => array_filter($e, fn ($l) => ! str_contains(json_encode($l), 'ERR_NO_BUFFER_SPACE')));
check('JS-1', 'No JavaScript error or console error in any browser job (' . count($GLOBALS['JERR']) . ' jobs)', ! $jerr, j($jerr));
if ($envOnly) info('JS-1: every console entry is ERR_NO_BUFFER_SPACE (environment)');
// ---- save and download
$sv = $MEAS['save'];
check('SAVE-1', 'Save theme from the browser (the real button): the page comes back on Appearance with a success message and writes writable/rolewarden/theme.json', str_starts_with((string) $sv['click'], 'clicked') && $sv['file'] && is_array($saved1) && str_contains((string) ($sv['after']['url'] ?? ''), 'appearance') && array_filter($sv['after']['toasts'] ?? [], fn ($t) => preg_match('/success|saved|salvat/i', $t)), j($sv + ['keys' => is_array($saved1) ? array_keys($saved1) : null]));
check('SAVE-2', 'Save theme over HTTP with the page\'s form (CSRF token): redirect, the theme file is valid JSON', in_array($saveStatus, [302, 303], true) && is_array($saved2), j([$saveStatus, $saveLoc, is_array($saved2) ? array_keys($saved2) : null]));
check('DL-1', 'Download: 200, JSON, as an attachment, the same content as the saved theme file', $dl['status'] === 200 && str_contains((string) $dl['disp'], 'attachment') && $MEAS['download']['equalsFile'], j($MEAS['download']));
$lk = $m('locked', 'en', 'appearance', 1440, 'light');
check('LOCK-1', 'Locked theme (app/Config/RoleWarden/theme.json): the panel names the application file and the file is not changed by a save', $lockedSave['servesApp'] && $lockedSave['appUnchanged'], j([$lockedSave, $lk['savedIn'] ?? null, $lk['forms'] ?? null, $lk['notes'] ?? null]));
// ---- other pages with a page head: Dashboard and a role page
foreach (['dashboard', 'role'] as $k) foreach (['en', 'it'] as $lang) foreach ([1440, 1600] as $w) foreach (['light', 'dark'] as $mode) {
    $x = $m('open', $lang, $k, $w, $mode); $pp = $P['open'][$lang]["m-$k-$w-$mode"] ?? null;
    $bad = [];
    if (! $x || isset($x['error'])) $bad[] = 'no measure';
    if ($x && ($x['docW'] ?? 9e9) > ($x['vw'] ?? 0)) $bad[] = 'hscroll ' . $x['docW'];
    if ($x && $x['headOut']) $bad[] = ['headOut' => $x['headOut']];
    foreach ($x['dls'] ?? [] as $i => $d) {
        if ($d['inHead'] && $d['box']['r'] > ($x['head']['box']['r'] ?? 0) + $TOL) $bad[] = "dl $i past the page head";
        foreach ($d['rows'] as $ri => $row) {
            $lr = $row['code']['lines']['maxR'] ?? $row['ddLines']['maxR'] ?? null;
            if ($lr !== null && $lr > $d['box']['r'] + $TOL) $bad[] = "dl $i row {$row['dt']}: text right $lr > dl {$d['box']['r']}";
            $was = $pp['dls'][$i]['rows'][$ri]['dtLines'] ?? 1;
            if ($row['dtLines'] > max(1, $was)) $bad[] = "dt '{$row['dt']}' {$row['dtLines']} lines (parent $was)";
        }
    }
    $newOut = $pp ? array_values(array_diff($x['mainOut'] ?? [], $pp['mainOut'] ?? [])) : ($x['mainOut'] ?? []);
    if ($newOut) $bad[] = ['main past its edge, new against the parent' => $newOut];
    check("PH-$k-$lang-$w-$mode", "$k $lang $w $mode: page head and facts inside their margin, no new wrap in a dt, nothing new past main's edge (" . count($x['dls'] ?? []) . ' dl)', ! $bad, j($bad));
}
// ---- LOG
$bad = seen_clean($S0);
check('LOG-1', 'No HTTP response >= 500, no SQL text, no PHP error in the bodies of this phase (' . (count($GLOBALS['SEEN']) - $S0) . ')', ! $bad, j($bad));
$ll = bad_log_lines(logs_of($APP));
check('LOG-2', 'No WARNING/ERROR/CRITICAL line in the application log', ! $ll, j(array_slice($ll, 0, 4)));
